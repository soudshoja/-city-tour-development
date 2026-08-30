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
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * KEY: auto-generate-invoice-duplicate-guard. W3a — InvoiceController::autoGenerateInvoice() has
 * 8 known retry-prone callers (RunAutoBilling and others re-invoke it after catching a failure
 * from anywhere downstream of the DB transaction, including the n8n webhook) — without the
 * task_id soft-guard, a retry created a SECOND fully-duplicate Invoice + InvoiceDetail +
 * journal-entry set for the exact same task. The guard (an application-level, monitored
 * soft-guard — a UNIQUE index on invoice_details.task_id is deliberately NOT added because of
 * ~99 pre-existing duplicate groups in prod/dev, per the method's own docblock) must:
 *
 *  - short-circuit BEFORE any write when invoice_details.task_id already exists, returning an
 *    idempotent success payload naming the pre-existing invoice;
 *  - never touch Payment.invoice_id, notifications, or the n8n webhook on that path.
 *
 * The second test additionally pins the engine-ownership guard added to this method's
 * Transaction::create (same shape as store()/savePartial()): with the posting engine DISABLED
 * the legacy transactions header is still written exactly once — and a retry adds nothing.
 *
 * Plain Tests\TestCase (not AccountingTestCase): the OFF path below runs HEAD's legacy posting
 * closures verbatim (byte-parity by design), and legacy createProfitEntries() posts pairs that
 * are engine-clean but not subject to this lane's ON-path invariants — the trial-balance hook
 * belongs to the engine tests, not this one.
 */
class AutoGenerateInvoiceDuplicateGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // autoGenerateInvoice() must be exercised on the OFF/engine-disabled route here: the ON
        // route is covered by the PostingSeam suites; this file pins the legacy parity the
        // isEnabledFor() guard added in this lane must not break.
        config(['accounting.engine.enabled' => false]);

        // The method's post-success notification fan-out reads env('N8N_WEBHOOK_TEST_URL')
        // directly (Http::post on a raw env value) — unset in the test env it TypeErrors on a
        // null $url. A dummy URL + Http::fake() keeps that call inert.
        putenv('N8N_WEBHOOK_TEST_URL=https://n8n.test/auto-invoice-hook');
        $_ENV['N8N_WEBHOOK_TEST_URL'] = 'https://n8n.test/auto-invoice-hook';
        $_SERVER['N8N_WEBHOOK_TEST_URL'] = 'https://n8n.test/auto-invoice-hook';
    }

    /**
     * @return array{0: Company, 1: Agent, 2: Client, 3: Task, 4: Payment}
     */
    private function makeTaskAndPayment(float $taskTotal = 100.00, float $paymentAmount = 250.00): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);

        // type id 1's role: NOT in addJournalEntry()'s [2, 3, 4] commission set -> commission
        // stays 0, keeping the expected journal-entry shape deterministic (no commission pair).
        // AgentType::$fillable is ['name'] — an 'id' attribute is silently dropped on create,
        // so the row is seeded by name and its REAL id is used below.
        $agentType = AgentType::firstOrCreate(['name' => 'type-1']);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
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
            // NB: tasks has NO 'currency' column — legacy journal code reads
            // `$task->currency ?? 'KWD'` (the ?? fallback exists precisely because it is absent).
            'supplier_pay_date' => now(),
        ]);

        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            // payments.created_by is an FK into users — the factory's hardcoded 1 only happens to
            // exist in a totally fresh DB; pin it to a user this fixture actually created.
            'created_by' => $agentUser->id,
            'invoice_id' => null,
            'account_id' => null,
            'amount' => $paymentAmount,
            'service_charge' => 0,
            'currency' => 'KWD',
            // No Charge row and no payment_method_id for this gateway ->
            // ChargeService::calculate() returns its zero-charge payload, so no gateway-profit
            // legs fire and the journal-entry shape below stays exactly ENTRY1+ENTRY2+profit pair.
            'payment_gateway' => 'nonexistent-test-gateway',
            'payment_method_id' => null,
            'payment_date' => now(),
            'status' => 'completed',
        ]);

        return [$company, $agent, $client, $task, $payment];
    }

    public function test_existing_invoice_detail_for_task_returns_idempotent_success_without_writing(): void
    {
        [$company, $agent, $client, $task, $payment] = $this->makeTaskAndPayment();

        // Simulate a prior successful (but downstream-failed) run: the invoice + its detail row
        // already exist for this task.
        $existingInvoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
        ]);
        InvoiceDetail::factory()->create([
            'invoice_id' => $existingInvoice->id,
            'task_id' => $task->id,
        ]);

        Log::spy();
        Http::fake();

        $result = app(InvoiceController::class)->autoGenerateInvoice($task, $payment);

        $this->assertTrue($result['success'] ?? false, 'Guard path must return an idempotent success payload.');
        $this->assertStringContainsString('already generated', (string) ($result['message'] ?? ''));
        $this->assertSame($existingInvoice->id, $result['invoice_id'], 'The payload must name the PRE-EXISTING invoice.');

        $this->assertSame(1, Invoice::count(), 'The guard must not create a second invoice.');
        $this->assertSame(1, InvoiceDetail::count(), 'The guard must not create a second invoice detail.');
        $this->assertSame(0, InvoicePartial::count());
        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, JournalEntry::count());

        $this->assertNull($payment->fresh()->invoice_id, 'The guard must not re-point the payment at anything.');

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Auto Invoice Generation skipped: task is already invoiced', \Mockery::type('array'));

        // The guard returns before any outbound notification work.
        Http::assertNothingSent();
    }

    public function test_engine_off_first_call_writes_one_legacy_header_and_retry_adds_nothing(): void
    {
        [$company, $agent, $client, $task, $payment] = $this->makeTaskAndPayment();

        Log::spy();
        Http::fake();

        $first = app(InvoiceController::class)->autoGenerateInvoice($task, $payment);

        $this->assertTrue($first['success'] ?? false, 'First call must succeed: '.json_encode($first));
        $this->assertSame('Invoice generated successfully.', $first['message'] ?? null);

        $invoiceId = $first['invoice_id'] ?? null;
        $this->assertNotNull($invoiceId);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, InvoiceDetail::count());
        $this->assertSame(1, InvoicePartial::count());

        // The engine-ownership guard added in this lane: engine OFF -> the legacy transactions
        // header is still written, exactly once, with the pre-existing reference shape.
        $this->assertSame(1, Transaction::count(), 'Engine OFF must keep the legacy transactions header.');
        $header = Transaction::first();
        $this->assertSame('Invoice', $header->reference_type);
        $this->assertSame($invoiceId, $header->invoice_id);
        $this->assertSame($payment->id, $header->payment_id);

        // The payment is linked to the generated invoice (pre-existing behavior).
        $this->assertSame($invoiceId, $payment->fresh()->invoice_id);

        // Legacy posting actually ran: ENTRY1 (client receivable debit), ENTRY2 (booking revenue
        // credit) and the profit pair — 4 lines with this fixture (commission 0, no gateway
        // profit, no loss legs; see makeTaskAndPayment()).
        $journalCountAfterFirstCall = JournalEntry::count();
        $this->assertSame(4, $journalCountAfterFirstCall);

        // The retry (exactly what RunAutoBilling et al. do after a downstream failure) must hit
        // the guard and add NOTHING.
        $second = app(InvoiceController::class)->autoGenerateInvoice($task->fresh(), $payment->fresh());

        $this->assertTrue($second['success'] ?? false);
        $this->assertStringContainsString('already generated', (string) ($second['message'] ?? ''));
        $this->assertSame($invoiceId, $second['invoice_id']);

        $this->assertSame(1, Invoice::count(), 'Retry must not create a duplicate invoice.');
        $this->assertSame(1, InvoiceDetail::count(), 'Retry must not create a duplicate invoice detail.');
        $this->assertSame(1, InvoicePartial::count());
        $this->assertSame(1, Transaction::count(), 'Retry must not write a second transactions header.');
        $this->assertSame($journalCountAfterFirstCall, JournalEntry::count(), 'Retry must not double-post journal lines.');
    }
}
