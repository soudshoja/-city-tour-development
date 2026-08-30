<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\InvoiceController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Role;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Support\AccountingTestCase;

/**
 * KEY: invoice-w3b. Covers the W3b scope on InvoiceController:
 *   (1) recalculateInvoiceCOA() is a no-op when the engine is ON for the posted transaction.
 *   (2) delete() reverses (never raw-deletes) the ledger half while still hard/soft-deleting
 *       partials/credits/applications exactly as before.
 *   (3) updateDateProcess()'s ON path reposts the invoice's own live transaction, targeted
 *       strictly by invoice_id -- proven by a decoy transaction on a DIFFERENT invoice that
 *       shares the same description text and must be left untouched.
 *   (4) updateStatus() rejects an illegal status and honours checkLocked().
 *   (5) checkLocked() fires on updateDate() and changeCreditToCash()/changeCreditToFull().
 */
class InvoiceControllerW3bTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function callPrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: Task, 5: Invoice, 6: InvoiceDetail, 7: User}
     */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => $branchOwner->id,
        ]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
            'commission' => 0.15,
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'type' => 'hotel',
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now(),
        ]);

        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
        ]);

        return [$company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, $branchOwner];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    /**
     * Posts a real ON-path sale document (idempotency_key set) for $invoiceDetail via the
     * existing seam-routed postSaleJournalEntries(), matching InvoiceControllerProfitLossPostingTest's
     * own pattern. Returns the resulting live Transaction.
     */
    private function postEngineSale(
        Company $company,
        Branch $branch,
        Agent $agent,
        Client $client,
        Task $task,
        Invoice $invoice,
        InvoiceDetail $invoiceDetail,
        float $selling = 250.000
    ): Transaction {
        $headerTransaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $selling,
            'description' => 'Invoice: '.$invoice->invoice_number.' Generated',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);

        $controller = app(InvoiceController::class);

        /** @var \App\Services\Accounting\PostedDocument $result */
        $result = $this->callPrivate($controller, 'postSaleJournalEntries', [
            $headerTransaction->id,
            $invoice,
            $invoice->id,
            $invoiceDetail->id,
            $task,
            $agent,
            $company->id,
            $selling,
            $client->full_name,
        ]);

        return $result->transaction;
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (1) recalculateInvoiceCOA() is a no-op when the engine posted the invoice.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_recalculate_invoice_coa_is_noop_when_engine_posted_the_invoice(): void
    {
        [$company, $branch, $agent, $client, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $transaction = $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail);
        $this->assertNotNull($transaction->idempotency_key, 'Fixture sanity: the ON-path sale must carry an idempotency_key.');

        $before = DB::table('journal_entries')->where('invoice_id', $invoice->id)->count();
        $this->assertGreaterThan(0, $before);

        $invoice->refresh();
        app(InvoiceController::class)->recalculateInvoiceCOA($invoice);

        $after = DB::table('journal_entries')->where('invoice_id', $invoice->id)->count();

        $this->assertSame(
            $before,
            $after,
            'recalculateInvoiceCOA() must be a no-op (write nothing) once the engine, not legacy, posted this invoice -- the engine is the source of truth.'
        );

        // Not just "same count" -- prove none of the legacy recalculation's own account names
        // (which would exist if it had run) were touched at all.
        $this->assertSame(
            0,
            DB::table('journal_entries')
                ->where('invoice_id', $invoice->id)
                ->where('description', 'like', 'Agent profit share:%')
                ->count(),
            'recalculateInvoiceCOA() must not have run its legacy profit-entry branch at all.'
        );
    }

    public function test_recalculate_invoice_coa_still_runs_for_a_legacy_posted_invoice(): void
    {
        // Sanity control for the test above: with the engine OFF, a legacy (no idempotency_key)
        // transaction DOES get recalculated -- proving the no-op above is conditional on the
        // engine having posted it, not recalculateInvoiceCOA() being broken outright.
        // NOTE: deliberately NOT tracked via trackCompanyForInvariants() -- this fixture plants a
        // single one-sided legacy journal_entries row on purpose (the shape recalculateInvoiceCOA()
        // is meant to top up), so the company is transiently unbalanced by construction; the
        // point of this control test is recalculateInvoiceCOA()'s own branch selection, not
        // whole-company balance (which the no-op test above already exercises on a real,
        // balanced, engine-posted fixture).
        [$company, $branch, $agent, $client, $task, $invoice, $invoiceDetail] = $this->makeFixture();

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => 100,
            'description' => 'Invoice: '.$invoice->invoice_number.' Generated',
            'invoice_id' => $invoice->id,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);
        $this->assertNull($transaction->idempotency_key);

        DB::table('journal_entries')->insert([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'invoice_detail_id' => $invoiceDetail->id,
            'account_id' => Account::where('company_id', $company->id)->first()->id,
            'task_id' => $task->id,
            'transaction_date' => now(),
            'description' => 'Invoice created for (Assets): '.$client->full_name,
            'debit' => 250,
            'credit' => 0,
            'balance' => 0,
            'name' => 'x',
            'type' => 'receivable',
            'currency' => 'KWD',
            'exchange_rate' => 1,
            'amount' => 250,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceDetail->task_price = 250;
        $invoiceDetail->supplier_price = 100;
        $invoiceDetail->save();

        app(InvoiceController::class)->recalculateInvoiceCOA($invoice->fresh());

        $this->assertGreaterThan(
            0,
            DB::table('journal_entries')
                ->where('invoice_id', $invoice->id)
                ->where('description', 'like', 'Agent profit share:%')
                ->count(),
            'Control case: recalculateInvoiceCOA() must still run its legacy branch for a transaction with no idempotency_key.'
        );
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (2) delete() -- legacy deletes of partials/credits/applications, ledger half reversed.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_delete_reverses_ledger_but_still_hard_and_soft_deletes_legacy_rows(): void
    {
        [$company, $branch, $agent, $client, $task, $invoice, $invoiceDetail, $branchOwner] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $saleTransaction = $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail);
        $originalLineCount = DB::table('journal_entries')->where('transaction_id', $saleTransaction->id)->count();
        $this->assertGreaterThan(0, $originalLineCount);

        // Legacy rows the delete() flow must still hard/soft-delete exactly as before.
        $creditPartial = InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'amount' => 250,
            'status' => 'paid',
            'type' => 'full',
            'payment_gateway' => 'Credit',
            'service_charge' => 0,
        ]);

        $creditRow = Credit::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => $creditPartial->id,
            'type' => Credit::INVOICE,
            'amount' => -250,
            'gateway_fee' => 0,
        ]);

        $account = Account::where('company_id', $company->id)->first();
        $payment = Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'created_by' => $branchOwner->id,
        ]);

        $paymentApplication = PaymentApplication::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => $creditPartial->id,
            'amount' => 250,
            'applied_at' => now(),
        ]);

        $invoiceController = app(InvoiceController::class);
        $response = $invoiceController->delete(new Request, (string) $invoice->id);

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSoftDeleted('credits', ['id' => $creditRow->id]);
        $this->assertSoftDeleted('payment_applications', ['id' => $paymentApplication->id]);
        $this->assertSame(0, InvoiceDetail::where('invoice_id', $invoice->id)->count(), 'InvoiceDetail must still be hard-deleted.');
        $this->assertSame(0, InvoicePartial::where('invoice_id', $invoice->id)->count(), 'InvoicePartial must still be hard-deleted.');

        // The ledger half: NEVER a raw delete. The original transaction/lines must still exist,
        // flipped to posting_status = 'reversed', with a NEW reversal document pointing back at it.
        $original = Transaction::withoutGlobalScopes()->withTrashed()->find($saleTransaction->id);
        $this->assertNotNull($original, 'The original transaction must still exist -- never hard-deleted.');
        $this->assertSame('reversed', $original->posting_status);
        $this->assertNull($original->deleted_at, 'reverse() only flips posting_status; it must not soft/hard-delete the original header.');

        $this->assertSame(
            $originalLineCount,
            DB::table('journal_entries')->where('transaction_id', $saleTransaction->id)->count(),
            'The original journal_entries lines must still exist -- never hard-deleted.'
        );

        $reversal = Transaction::withoutGlobalScopes()
            ->where('reversal_of_transaction_id', $saleTransaction->id)
            ->first();
        $this->assertNotNull($reversal, 'A real reversal document must exist for the original sale transaction.');
        $this->assertSame('posted', $reversal->posting_status);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (3) updateDateProcess() ON path: repost() targeted by invoice_id, never by description.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_date_process_reposts_only_this_invoices_transaction_not_a_decoy_with_same_description(): void
    {
        [$company, $branch, $agent, $client, $task, $invoice, $invoiceDetail] = $this->makeFixture();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);

        $saleTransaction = $this->postEngineSale($company, $branch, $agent, $client, $task, $invoice, $invoiceDetail);

        // A DECOY: a second invoice under the SAME company whose transaction carries the
        // IDENTICAL description text real invoices of this shape would share -- proving the
        // engine-ON date-change path targets by invoice_id, never by matching description.
        $decoyClient = Client::factory()->create(['agent_id' => $agent->id]);
        $decoyTask = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $decoyClient->id,
            'type' => 'hotel',
        ]);
        $decoyInvoice = Invoice::factory()->create([
            'client_id' => $decoyClient->id,
            'agent_id' => $agent->id,
            'invoice_date' => $invoice->invoice_date,
        ]);
        $decoyDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $decoyInvoice->id,
            'task_id' => $decoyTask->id,
        ]);
        $decoyTransaction = $this->postEngineSale($company, $branch, $agent, $decoyClient, $decoyTask, $decoyInvoice, $decoyDetail);

        // Force an identical description on both live transactions -- if the engine-ON date
        // change ever matched by text instead of invoice_id, this would make it ambiguous.
        Transaction::withoutGlobalScopes()->whereKey($saleTransaction->id)->update(['description' => 'SAME TEXT ON PURPOSE']);
        Transaction::withoutGlobalScopes()->whereKey($decoyTransaction->id)->update(['description' => 'SAME TEXT ON PURPOSE']);

        $newDate = '2027-01-15';
        $response = app(InvoiceController::class)->updateDateProcess(new Request([
            'company_id' => $company->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $newDate,
        ]));

        $this->assertArrayHasKey('success', $response, 'updateDateProcess() must succeed: '.json_encode($response));

        $original = Transaction::withoutGlobalScopes()->find($saleTransaction->id);
        $this->assertSame('reversed', $original->posting_status, "This invoice's own transaction must have been reversed.");

        $decoyStillLive = Transaction::withoutGlobalScopes()->find($decoyTransaction->id);
        $this->assertSame('posted', $decoyStillLive->posting_status, 'The decoy transaction (same description, different invoice) must be untouched.');
        $this->assertNotEquals(
            $newDate,
            \Carbon\Carbon::parse($decoyStillLive->transaction_date)->format('Y-m-d'),
            'The decoy transaction must keep its original date -- only the targeted invoice moves.'
        );

        $replacement = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('invoice_id', $invoice->id)
            ->where('posting_status', 'posted')
            ->where('id', '!=', $decoyTransaction->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($replacement, 'A live replacement document must exist for this invoice after the date change.');
        $this->assertSame(
            $newDate,
            \Carbon\Carbon::parse($replacement->transaction_date)->format('Y-m-d')
        );

        $replacementLines = DB::table('journal_entries')->where('transaction_id', $replacement->id)->get();
        $originalLines = DB::table('journal_entries')->where('transaction_id', $saleTransaction->id)->get();
        $this->assertCount($originalLines->count(), $replacementLines, 'The replacement must carry the same number of lines as the original.');
        $this->assertEqualsCanonicalizing(
            $originalLines->pluck('account_id')->all(),
            $replacementLines->pluck('account_id')->all(),
            'The replacement must post to the SAME accounts as the original.'
        );

        // The decoy's own lines must be completely unaffected.
        $decoyLines = DB::table('journal_entries')->where('transaction_id', $decoyTransaction->id)->get();
        $this->assertGreaterThan(0, $decoyLines->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (4) updateStatus() -- explicit allow-list + checkLocked.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_update_status_rejects_an_illegal_status(): void
    {
        [, , , , , $invoice] = $this->makeFixture();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(InvoiceController::class)->updateStatus(
            new Request(['status' => 'not-a-real-status']),
            $invoice
        );
    }

    public function test_update_status_accepts_every_legal_invoice_status(): void
    {
        foreach (\App\Enums\InvoiceStatus::cases() as $case) {
            [, , , , , $invoice] = $this->makeFixture();

            app(InvoiceController::class)->updateStatus(
                new Request(['status' => $case->value]),
                $invoice
            );

            $this->assertSame($case->value, $invoice->fresh()->status);
        }
    }

    public function test_check_locked_fires_on_update_status(): void
    {
        [, , , , , $invoice] = $this->makeFixture();
        $invoice->lock();
        $invoice->refresh();

        $response = app(InvoiceController::class)->updateStatus(
            new Request(['status' => 'paid']),
            $invoice
        );

        $this->assertNotSame('paid', $invoice->fresh()->status, 'A locked invoice must not have its status changed.');
        $this->assertNotNull($response);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (5) checkLocked() fires on updateDate() and changeCreditToCash()/changeCreditToFull().
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function test_check_locked_fires_on_update_date(): void
    {
        [$company, , $agent, $client] = $this->makeFixture();

        $adminUser = User::factory()->create(['role_id' => Role::ADMIN]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_number' => 'INV-W3B-LOCK-'.uniqid(),
            'invoice_date' => '2026-01-01',
        ]);
        $invoice->lock();
        $invoice->refresh();
        $originalDate = $invoice->invoice_date;

        $response = $this->actingAs($adminUser)
            ->put(route('invoice.updateDate', [
                'companyId' => $company->id,
                'invoiceNumber' => $invoice->invoice_number,
            ]), ['invdate' => '2026-06-01']);

        $response->assertStatus(403);

        $invoice->refresh();
        $this->assertEquals(
            \Carbon\Carbon::parse($originalDate)->format('Y-m-d'),
            \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d'),
            'A locked invoice must not have its date changed.'
        );
    }

    public function test_check_locked_fires_on_change_credit_to_cash(): void
    {
        [, , , , , $invoice] = $this->makeFixture();
        $invoice->lock();
        $invoice->refresh();

        $result = $this->callPrivate(app(InvoiceController::class), 'changeCreditToCash', [$invoice]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('locked', strtolower($result['error']));
    }

    public function test_check_locked_fires_on_change_credit_to_full(): void
    {
        [, , , , , $invoice] = $this->makeFixture();
        $invoice->lock();
        $invoice->refresh();

        $result = $this->callPrivate(app(InvoiceController::class), 'changeCreditToFull', [$invoice]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('locked', strtolower($result['error']));
    }

    public function test_change_credit_to_cash_still_runs_when_not_locked(): void
    {
        [$company, $branch, , $client, , $invoice] = $this->makeFixture();

        $creditPartial = InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'amount' => 150,
            'status' => 'paid',
            'type' => 'full',
            'payment_gateway' => 'Credit',
            'service_charge' => 0,
        ]);

        Credit::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'invoice_partial_id' => $creditPartial->id,
            'type' => Credit::INVOICE,
            'amount' => -150,
            'gateway_fee' => 0,
        ]);

        $result = $this->callPrivate(app(InvoiceController::class), 'changeCreditToCash', [$invoice]);

        $this->assertArrayHasKey('success', $result, 'An unlocked invoice must still be able to change payment type: '.json_encode($result));
    }
}
