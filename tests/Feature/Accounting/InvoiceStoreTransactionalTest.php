<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\InvoiceController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceSequence;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * KEY: invoice-store-transactional. W3a store() fixes:
 *
 *   1. The invoice number is generated SERVER-SIDE, always
 *      (getInvoiceNumberGenerated()/generateInvoiceNumber() -> 'INV-{year}-{seq:05d}'); the
 *      client-supplied `invoiceNumber` request field is still validated for backward
 *      compatibility with existing frontend callers but its VALUE must never be used.
 *   2. Invoice + every InvoiceDetail + each task's sale-header journal posting run inside ONE
 *      DB::transaction() — a mid-loop failure (e.g. a task in the payload that no longer exists)
 *      rolls the WHOLE thing back; the pre-W3a code left a partially-created invoice behind.
 *   3. With the posting engine OFF, store() still writes exactly ONE legacy `transactions`
 *      header (reference_type 'Invoice') shared across every task; the engine-ON header is the
 *      seam's own (covered by the PostingSeam/ON-path suites).
 *
 * The controller method is invoked directly with a constructed Request (not via HTTP): nothing
 * in store()'s W3a behavior depends on middleware, only on validation + persistence.
 *
 * Plain Tests\TestCase (not AccountingTestCase): engine OFF here runs HEAD's legacy posting
 * closures verbatim — the trial-balance invariant hook belongs to the engine-path suites.
 */
class InvoiceStoreTransactionalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This file pins the OFF-path (legacy) behavior of store()'s W3a fixes; the ON-path is
        // covered by InvoiceControllerProfitLossPostingTest and the PostingSeam suites.
        config(['accounting.engine.enabled' => false]);
    }

    /**
     * @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task}
     */
    private function makeFixture(float $taskTotal = 100.00): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);

        // AgentType::$fillable is ['name'] — an 'id' attribute is silently dropped on create;
        // seed by name and use the row's real id. Type outside [2,3,4] keeps commission 0 so the
        // journal shape below is deterministic (no commission pair).
        $agentType = AgentType::firstOrCreate(['name' => 'type-1']);

        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
            'profit_account_id' => Account::where('company_id', $company->id)
                ->where('name', 'Salaries & Wages Payable')
                ->value('id'),
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'hotel', // matches CoaSeeder's pre-seeded 'Hotel Booking Revenue' leaf
            'total' => $taskTotal,
            'supplier_pay_date' => now(),
        ]);

        return [$company, $agent, $client, $supplier, $task];
    }

    private function buildStoreRequest(array $tasksPayload, Agent $agent, Client $client, float $subTotal): Request
    {
        return Request::create('/invoice/store', 'POST', [
            'tasks' => $tasksPayload,
            'invdate' => now()->toDateString(),
            'duedate' => now()->addDays(30)->toDateString(),
            'subTotal' => $subTotal,
            'clientId' => $client->id,
            'agentId' => $agent->id,
            'invoiceNumber' => 'CLIENT-SUPPLIED-DO-NOT-TRUST',
            'currency' => 'KWD',
        ]);
    }

    public function test_store_posts_inside_a_transaction_with_a_server_generated_invoice_number(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture();

        $response = app(InvoiceController::class)->store($this->buildStoreRequest([
            [
                'id' => $task->id,
                'description' => 'Hotel booking for test',
                'invprice' => 250.00,
                'supplier_id' => $supplier->id,
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'total' => 100.00,
            ],
        ], $agent, $client, 250.00));

        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['success'] ?? false, 'store() must succeed: '.json_encode($payload));

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertSame($invoice->id, $payload['invoiceId'] ?? null);

        // Fix 1: server-generated, NEVER the client-supplied value (which is still required by
        // the validator for existing frontends, proving the value is accepted but ignored).
        $this->assertNotSame('CLIENT-SUPPLIED-DO-NOT-TRUST', $invoice->invoice_number);
        $this->assertSame(sprintf('INV-%s-%05d', now()->year, 1), $invoice->invoice_number);

        // The sequence advanced (firstOrCreate at 1, then incremented) — the same row store()
        // bumped inside its transaction.
        $this->assertSame(
            2,
            (int) InvoiceSequence::where('company_id', $company->id)->value('current_sequence'),
            'The company sequence must advance exactly once for a single store().'
        );

        $this->assertSame(1, InvoiceDetail::count());
        $this->assertSame($task->id, InvoiceDetail::first()->task_id);

        // Fix 3: engine OFF -> exactly one legacy transactions header, shared across the invoice.
        $this->assertSame(1, Transaction::count());
        $header = Transaction::first();
        $this->assertSame('Invoice', $header->reference_type);
        $this->assertSame($invoice->id, $header->invoice_id);
        $this->assertEquals(250.00, (float) $header->amount);

        // The sale actually posted through addJournalEntry()'s legacy path: ENTRY1 (receivable
        // debit) + ENTRY2 (booking-revenue credit) + the profit pair (commission is 0 by fixture).
        $this->assertSame(4, JournalEntry::count());
    }

    public function test_store_rolls_back_everything_when_a_payload_task_does_not_exist(): void
    {
        [$company, $agent, $client, $supplier, $task] = $this->makeFixture();

        $response = app(InvoiceController::class)->store($this->buildStoreRequest([
            [
                'id' => $task->id, // valid — first loop iteration fully writes its detail + posting
                'description' => 'Hotel booking for test',
                'invprice' => 250.00,
                'supplier_id' => $supplier->id,
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'total' => 100.00,
            ],
            [
                'id' => 999999, // does not exist — store() throws mid-loop
                'description' => 'Ghost task',
                'invprice' => 50.00,
                'supplier_id' => $supplier->id,
                'client_id' => $client->id,
                'agent_id' => $agent->id,
                'total' => 10.00,
            ],
        ], $agent, $client, 300.00));

        $payload = json_decode($response->getContent(), true);
        $this->assertFalse($payload['success'] ?? true, 'store() must report failure, not partial success.');

        // Fix 2: ONE DB::transaction() around the whole loop — the first task's fully-completed
        // writes (Invoice, InvoiceDetail, journal lines) must be gone too, not orphaned.
        $this->assertSame(0, Invoice::count(), 'A failed store() must not leave the invoice behind.');
        $this->assertSame(0, InvoiceDetail::count(), 'A failed store() must not leave detail rows behind.');
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, JournalEntry::count());

        // The sequence bump also lived inside the transaction, so the rollback restores it —
        // retrying store() reuses INV-…-00001 rather than silently skipping a number.
        $this->assertSame(
            0,
            InvoiceSequence::where('company_id', $company->id)->count(),
            'Rolled-back sequence advance must not leave a bumped invoice_sequence row behind.'
        );
    }
}
