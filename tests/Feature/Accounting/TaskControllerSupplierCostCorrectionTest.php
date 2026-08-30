<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\TaskController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * W4.C (w4-brief.md — "supplier cost posts in the sale's own period"). Feature-level proof for
 * {@see TaskController::updateAdminFinancial()}'s late-arriving/wrong supplier-cost correction
 * path — the real call site {@see \App\Services\Accounting\SupplierCostCorrectionDraftBuilder} is
 * wired into. Pure-logic coverage of the builder itself lives in
 * {@see \Tests\Unit\Services\Accounting\SupplierCostCorrectionDraftBuilderTest}; this suite proves
 * the same shapes actually post through the real engine, against real seeders, from the real
 * controller method — including that the OFF path is untouched (byte-identical raw JE mutation).
 */
class TaskControllerSupplierCostCorrectionTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Invoice, 5: User}
     */
    private function makeFixtures(?Company $company = null): array
    {
        $company = $company ?? Company::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'Sales']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create();
        $invoice = Invoice::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
        ]);
        // role_id 99 matches none of BelongsToCompany's/getCompanyId()'s switch cases (ADMIN's
        // session('company_id', 1) default is the one that actually bit an earlier version of
        // this suite) -- getCompanyId() falls to its `default: return null` branch, so the
        // JournalEntry/Transaction company scope adds no filter at all for this acting user,
        // exactly like every OTHER accounting feature test in this suite gets for free by never
        // calling actingAs() in the first place. TaskController::updateAdminFinancial itself
        // requires an authenticated Auth::user() (used directly, unguarded, for SystemLog rows),
        // so this suite cannot avoid acting as a user the way ChatControllerPostingTest/
        // InvoiceControllerProfitLossPostingTest do.
        $user = User::factory()->create(['role_id' => 99]);

        return [$company, $agent, $client, $supplier, $invoice, $user];
    }

    private function makeTaskAndDetail(
        Company $company,
        Agent $agent,
        Client $client,
        Supplier $supplier,
        Invoice $invoice,
        float $originalCost,
        float $sellPrice,
        string $type = 'flight',
    ): Task {
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => $type,
            'total' => $originalCost,
        ]);

        InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'task_price' => $sellPrice,
            'supplier_price' => $originalCost,
            'markup_price' => $sellPrice - $originalCost,
        ]);

        return $task->fresh();
    }

    /**
     * P2.5.D fix (verify finding): {@see RevenueRecognitionService::isDeferredOutstanding()} reads
     * the LEDGER, not the InvoiceDetail row -- so a test proving the deferred-correction fix must
     * actually post the original sale document through the real engine (exactly as
     * InvoiceController::postSaleJournalEntries()/ChatController's feeder would), not just create
     * an InvoiceDetail row, or there is no DEFERRED_REVENUE balance for the correction to defer
     * against. Posts under the SAME `invoice-detail:{id}:sale` idempotency key convention every
     * other real sale-posting call site uses.
     */
    private function postOriginalSale(Company $company, Task $task, Invoice $invoice, float $sell, float $cost, string $serviceType, \DateTimeInterface $docDate): void
    {
        $postingBasis = SaleDraftBuilder::resolvePostingBasis($company->id, $serviceType);
        $recognitionTiming = SaleDraftBuilder::resolveRecognitionTiming($company->id, $serviceType);

        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $serviceType,
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: $postingBasis,
            clientId: $task->client_id,
            supplierId: $task->supplier_id,
            invoiceId: $invoice->id,
            invoiceDetailId: $task->invoiceDetail->id,
            taskId: $task->id,
            currency: (string) config('accounting.engine.base_currency'),
            recognitionTiming: $recognitionTiming,
        ));

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: (int) $task->agent->branch_id,
            docType: 'INV',
            subType: 'SALE',
            docDate: $docDate,
            narration: 'Original sale for '.$task->reference,
            lines: $lines,
            idempotencyKey: 'invoice-detail:'.$task->invoiceDetail->id.':sale',
            invoiceId: $invoice->id,
        );

        app(PostingService::class)->post($draft);
    }

    private function callUpdateAdminFinancial(User $user, Task $task, float $newTotal, ?float $price = null): mixed
    {
        $this->actingAs($user);

        $request = Request::create('/tasks/update-financial/'.$task->id, 'PUT', [
            'price' => $price ?? $task->price ?? $newTotal,
            'tax' => 0,
            'surcharge' => 0,
            'total' => $newTotal,
            'remarks' => 'W4.C feature test correction remarks',
        ]);
        $request->setUserResolver(fn () => $user);

        return app(TaskController::class)->updateAdminFinancial($request, $task);
    }

    // ── OFF path: byte-identical legacy raw JE mutation ───────────────────────────────────────────

    public function test_off_path_still_mutates_the_legacy_journal_entry_in_place(): void
    {
        config(['accounting.engine.enabled' => false]);

        [$company, $agent, $client, $supplier, $invoice, $user] = $this->makeFixtures();
        $task = $this->makeTaskAndDetail($company, $agent, $client, $supplier, $invoice, originalCost: 100.000, sellPrice: 130.000);

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => 100.000,
            'description' => 'Booking for '.$task->reference,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);
        $account = Account::factory()->create(['company_id' => $company->id]);
        $entry = JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'task_id' => $task->id,
            'debit' => 100.000,
            'credit' => 0,
            'amount' => 100.000,
            'name' => $account->name,
            'description' => 'Booking for '.$task->reference,
            'transaction_date' => $invoice->invoice_date,
        ]);

        $this->callUpdateAdminFinancial($user, $task, newTotal: 120.000);

        $entry = JournalEntry::withoutGlobalScopes()->find($entry->id);
        $this->assertEqualsWithDelta(120.000, (float) $entry->debit, 0.0005, 'OFF path must keep mutating the legacy JournalEntry row in place, unchanged behaviour.');
    }

    // ── ON path: same-period correction ───────────────────────────────────────────────────────────

    public function test_on_path_same_period_correction_posts_a_delta_document_dated_to_the_sale(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $agent, $client, $supplier, $invoice] = $this->makeFixtures($company);
        // role_id 99 matches none of BelongsToCompany's/getCompanyId()'s switch cases (ADMIN's
        // session('company_id', 1) default is the one that actually bit an earlier version of
        // this suite) -- getCompanyId() falls to its `default: return null` branch, so the
        // JournalEntry/Transaction company scope adds no filter at all for this acting user,
        // exactly like every OTHER accounting feature test in this suite gets for free by never
        // calling actingAs() in the first place. TaskController::updateAdminFinancial itself
        // requires an authenticated Auth::user() (used directly, unguarded, for SystemLog rows),
        // so this suite cannot avoid acting as a user the way ChatControllerPostingTest/
        // InvoiceControllerProfitLossPostingTest do.
        $user = User::factory()->create(['role_id' => 99]);

        $saleDate = Carbon::now()->startOfMonth()->addDay();
        $invoice->invoice_date = $saleDate;
        $invoice->save();

        $task = $this->makeTaskAndDetail($company, $agent, $client, $supplier, $invoice, originalCost: 100.000, sellPrice: 130.000);

        $this->callUpdateAdminFinancial($user, $task, newTotal: 120.000);

        $expectedKey = 'invoice-detail:'.$task->invoiceDetail->id.':supplier-cost-correction:120.000';
        $correctionTransaction = Transaction::where('company_id', $company->id)
            ->where('idempotency_key', $expectedKey)
            ->first();

        $this->assertNotNull($correctionTransaction, 'Expected a correction document keyed by the new idempotency-key convention.');
        $this->assertTrue(
            Carbon::parse($correctionTransaction->transaction_date)->isSameDay($saleDate),
            'Same-period correction must date to the sale document, not today.'
        );

        // JournalEntry::class has BelongsToCompany's own global scope, which filters by the
        // ACTING user's resolved company (session('company_id', 1) for role ADMIN) -- unrelated to
        // this test's fixture company, and a pre-existing app behaviour, not something this test
        // is proving. withoutGlobalScopes() matches this suite's own established convention (see
        // ChatControllerPostingTest's Account::withoutGlobalScopes() reads) for exactly this reason.
        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $correctionTransaction->id)->get();
        $this->assertCount(2, $lines);

        $resolver = app(AccountResolver::class);
        $payableAccountId = $resolver->resolve('SERVICE_PAYABLE', $company->id, 'flight')->id;
        $revenueAccountId = $resolver->resolve('SERVICE_REVENUE', $company->id, 'flight')->id;

        $accountIdsHit = $lines->pluck('account_id')->all();
        $this->assertContains($payableAccountId, $accountIdsHit, 'Delta document must post the corrected amount onto SERVICE_PAYABLE.');
        $this->assertContains($revenueAccountId, $accountIdsHit, 'Delta document must post the margin adjustment onto SERVICE_REVENUE, never a separate loss account.');

        $payableLine = $lines->firstWhere('account_id', $payableAccountId);
        $revenueLine = $lines->firstWhere('account_id', $revenueAccountId);
        $this->assertEqualsWithDelta(20.000, (float) $payableLine->credit, 0.0005, 'Cost increased by 20 -- SERVICE_PAYABLE credited by the delta.');
        $this->assertEqualsWithDelta(20.000, (float) $revenueLine->debit, 0.0005, 'Cost increase reduces margin -- SERVICE_REVENUE debited by the delta.');
    }

    // ── ON path: forward-dated correction after the sale's period has rolled over ─────────────────

    public function test_on_path_late_correction_after_period_close_posts_today_and_never_backdates(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $agent, $client, $supplier, $invoice] = $this->makeFixtures($company);
        // role_id 99 matches none of BelongsToCompany's/getCompanyId()'s switch cases (ADMIN's
        // session('company_id', 1) default is the one that actually bit an earlier version of
        // this suite) -- getCompanyId() falls to its `default: return null` branch, so the
        // JournalEntry/Transaction company scope adds no filter at all for this acting user,
        // exactly like every OTHER accounting feature test in this suite gets for free by never
        // calling actingAs() in the first place. TaskController::updateAdminFinancial itself
        // requires an authenticated Auth::user() (used directly, unguarded, for SystemLog rows),
        // so this suite cannot avoid acting as a user the way ChatControllerPostingTest/
        // InvoiceControllerProfitLossPostingTest do.
        $user = User::factory()->create(['role_id' => 99]);

        $saleDate = Carbon::now()->subMonthsNoOverflow(2)->startOfMonth()->addDay();
        $invoice->invoice_date = $saleDate;
        $invoice->save();

        $task = $this->makeTaskAndDetail($company, $agent, $client, $supplier, $invoice, originalCost: 100.000, sellPrice: 130.000);

        $this->callUpdateAdminFinancial($user, $task, newTotal: 120.000);

        $expectedKey = 'invoice-detail:'.$task->invoiceDetail->id.':supplier-cost-correction:120.000';
        $correctionTransaction = Transaction::where('company_id', $company->id)
            ->where('idempotency_key', $expectedKey)
            ->first();

        $this->assertNotNull($correctionTransaction);
        $this->assertTrue(
            Carbon::parse($correctionTransaction->transaction_date)->isToday(),
            'Late correction (sale period has already rolled over) must post dated today, never backdated.'
        );
        $this->assertFalse(
            Carbon::parse($correctionTransaction->transaction_date)->isSameMonth($saleDate),
            'Must never land back in the closed sale month.'
        );
        $this->assertSame($invoice->id, $correctionTransaction->invoice_id, 'Forward correction must still be linked to the original sale.');
    }

    // ── ON path: never emits the interim 5221 accrual ────────────────────────────────────────────

    public function test_on_path_never_posts_the_5221_company_loss_account(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $agent, $client, $supplier, $invoice] = $this->makeFixtures($company);
        // role_id 99 matches none of BelongsToCompany's/getCompanyId()'s switch cases (ADMIN's
        // session('company_id', 1) default is the one that actually bit an earlier version of
        // this suite) -- getCompanyId() falls to its `default: return null` branch, so the
        // JournalEntry/Transaction company scope adds no filter at all for this acting user,
        // exactly like every OTHER accounting feature test in this suite gets for free by never
        // calling actingAs() in the first place. TaskController::updateAdminFinancial itself
        // requires an authenticated Auth::user() (used directly, unguarded, for SystemLog rows),
        // so this suite cannot avoid acting as a user the way ChatControllerPostingTest/
        // InvoiceControllerProfitLossPostingTest do.
        $user = User::factory()->create(['role_id' => 99]);

        $saleDate = Carbon::now()->startOfMonth()->addDay();
        $invoice->invoice_date = $saleDate;
        $invoice->save();

        // Cost correction that pushes the sale into a negative margin, exercising the
        // sign-aware SERVICE_REVENUE debit leg -- this is exactly the shape the interim 5221
        // accrual used to cover. No line in this document may ever reference it.
        $task = $this->makeTaskAndDetail($company, $agent, $client, $supplier, $invoice, originalCost: 100.000, sellPrice: 130.000);

        $this->callUpdateAdminFinancial($user, $task, newTotal: 150.000);

        // withoutGlobalScopes(): see the same-period test's own comment -- BelongsToCompany's
        // scope filters JournalEntry by the ACTING user's resolved company, unrelated to this
        // test's fixture company.
        $postedLines = JournalEntry::withoutGlobalScopes()->where('task_id', $task->id)->get();
        $this->assertNotEmpty($postedLines, 'Sanity check: the correction must actually have posted lines for this assertion to mean anything.');

        $companyLossAccountIds = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Company Loss on Sales')
            ->pluck('id');

        $hit = $postedLines->whereIn('account_id', $companyLossAccountIds)->isNotEmpty();

        $this->assertFalse($hit, 'No line of the late-cost-correction path may ever post to the Company Loss on Sales (5221) account.');
    }

    // ── Zero-delta correction is a no-op for the engine ───────────────────────────────────────────

    public function test_zero_delta_request_does_not_post_any_engine_document(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $agent, $client, $supplier, $invoice] = $this->makeFixtures($company);
        // role_id 99 matches none of BelongsToCompany's/getCompanyId()'s switch cases (ADMIN's
        // session('company_id', 1) default is the one that actually bit an earlier version of
        // this suite) -- getCompanyId() falls to its `default: return null` branch, so the
        // JournalEntry/Transaction company scope adds no filter at all for this acting user,
        // exactly like every OTHER accounting feature test in this suite gets for free by never
        // calling actingAs() in the first place. TaskController::updateAdminFinancial itself
        // requires an authenticated Auth::user() (used directly, unguarded, for SystemLog rows),
        // so this suite cannot avoid acting as a user the way ChatControllerPostingTest/
        // InvoiceControllerProfitLossPostingTest do.
        $user = User::factory()->create(['role_id' => 99]);

        $task = $this->makeTaskAndDetail($company, $agent, $client, $supplier, $invoice, originalCost: 100.000, sellPrice: 130.000);

        $countBefore = Transaction::where('company_id', $company->id)->count();

        $this->callUpdateAdminFinancial($user, $task, newTotal: 100.000);

        $countAfter = Transaction::where('company_id', $company->id)->count();

        $this->assertSame($countBefore, $countAfter, 'A resubmitted/unchanged total has nothing to correct -- no engine document should be posted.');
    }

    // ── Regression: engine ON must never fall through to the raw legacy mutation ─────────────────
    // (verify-fix -- previous submission's zero-delta and no-invoiceDetail branches called the
    // raw $legacyLedgerCorrection closure unconditionally, bypassing PostingSeam::post() and its
    // isEnabledFor() gate entirely. With a stale pre-existing JournalEntry/Transaction pair for
    // the task -- matched by the closure's description LIKE -- this silently rewrote a posted
    // ledger row in place even with the engine ON, producing a single-sided (unbalanced)
    // Transaction. These fixtures reproduce that stale-pair precondition and assert the legacy
    // row is left completely untouched on the ON path.)

    public function test_on_path_zero_delta_never_mutates_a_preexisting_legacy_journal_entry(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $agent, $client, $supplier, $invoice] = $this->makeFixtures($company);
        $user = User::factory()->create(['role_id' => 99]);

        $task = $this->makeTaskAndDetail($company, $agent, $client, $supplier, $invoice, originalCost: 100.000, sellPrice: 130.000);

        // Stale legacy pair matched by the closure's description LIKE -- deliberately holding a
        // DIFFERENT amount (999) than the task's total (100), the way a genuinely stale/orphaned
        // legacy row would. If the raw closure runs, it rewrites this to 100 in place.
        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => 999.000,
            'description' => 'Booking for '.$task->reference,
            'reference_type' => 'Invoice',
            'transaction_date' => $invoice->invoice_date,
        ]);
        $account = Account::factory()->create(['company_id' => $company->id]);
        $entry = JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'task_id' => $task->id,
            'debit' => 999.000,
            'credit' => 0,
            'amount' => 999.000,
            'name' => $account->name,
            'description' => 'Booking for '.$task->reference,
            'transaction_date' => $invoice->invoice_date,
        ]);
        // Offsetting credit leg so this manually-inserted stale legacy pair is itself balanced --
        // this suite's AccountingInvariants tearDown hook (trackCompanyForInvariants() above)
        // asserts every tracked company's ledger balances; the fixture must satisfy that on its
        // own, independent of whether the correction path touches it.
        $offsetAccount = Account::factory()->create(['company_id' => $company->id]);
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'account_id' => $offsetAccount->id,
            'task_id' => $task->id,
            'debit' => 0,
            'credit' => 999.000,
            'amount' => 999.000,
            'name' => $offsetAccount->name,
            'description' => 'Booking for '.$task->reference,
            'transaction_date' => $invoice->invoice_date,
        ]);

        // Zero-delta resubmission (newTotal === task->total === 100.000) -- the exact case the
        // previous submission's unguarded branch mishandled.
        $this->callUpdateAdminFinancial($user, $task, newTotal: 100.000);

        $transaction = Transaction::withoutGlobalScopes()->find($transaction->id);
        $entry = JournalEntry::withoutGlobalScopes()->find($entry->id);

        $this->assertEqualsWithDelta(999.000, (float) $transaction->amount, 0.0005, 'Engine ON must never mutate a pre-existing legacy Transaction on a zero-delta request.');
        $this->assertEqualsWithDelta(999.000, (float) $entry->debit, 0.0005, 'Engine ON must never mutate a pre-existing legacy JournalEntry on a zero-delta request.');
    }

    public function test_on_path_no_invoice_detail_never_mutates_a_preexisting_legacy_journal_entry(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $agent, $client, $supplier] = $this->makeFixtures($company);
        $user = User::factory()->create(['role_id' => 99]);

        // No InvoiceDetail created for this task -- exercises the "no invoiceDetail/invoice"
        // else-branch, not the supplier-cost-correction elseif.
        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'total' => 100.000,
        ]);

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'branch_id' => $agent->branch_id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => 999.000,
            'description' => 'Booking for '.$task->reference,
            'reference_type' => 'Invoice',
            'transaction_date' => Carbon::now(),
        ]);
        $account = Account::factory()->create(['company_id' => $company->id]);
        $entry = JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'task_id' => $task->id,
            'debit' => 999.000,
            'credit' => 0,
            'amount' => 999.000,
            'name' => $account->name,
            'description' => 'Booking for '.$task->reference,
            'transaction_date' => Carbon::now(),
        ]);
        // Offsetting credit leg -- see the zero-delta test's own comment above for why.
        $offsetAccount = Account::factory()->create(['company_id' => $company->id]);
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'account_id' => $offsetAccount->id,
            'task_id' => $task->id,
            'debit' => 0,
            'credit' => 999.000,
            'amount' => 999.000,
            'name' => $offsetAccount->name,
            'description' => 'Booking for '.$task->reference,
            'transaction_date' => Carbon::now(),
        ]);

        $this->callUpdateAdminFinancial($user, $task, newTotal: 150.000);

        $transaction = Transaction::withoutGlobalScopes()->find($transaction->id);
        $entry = JournalEntry::withoutGlobalScopes()->find($entry->id);

        $this->assertEqualsWithDelta(999.000, (float) $transaction->amount, 0.0005, 'Engine ON must never mutate a pre-existing legacy Transaction when the task has no invoiceDetail/invoice to link a correction to.');
        $this->assertEqualsWithDelta(999.000, (float) $entry->debit, 0.0005, 'Engine ON must never mutate a pre-existing legacy JournalEntry when the task has no invoiceDetail/invoice to link a correction to.');
    }

    // ── ON path: principal basis, cost unknown at sale time ──────────────────────────────────────────

    public function test_on_path_principal_basis_cost_unknown_at_sale_time_posts_the_full_cost_pair(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $agent, $client, $supplier, $invoice] = $this->makeFixtures($company);
        $user = User::factory()->create(['role_id' => 99]);

        $saleDate = Carbon::now()->startOfMonth()->addDay();
        $invoice->invoice_date = $saleDate;
        $invoice->save();

        // 'tour' defaults to BASIS_PRINCIPAL (config('accounting.posting_basis.default_by_service_type')).
        // originalCost = 0.0 -- the exact shape SaleDraftBuilder's principal basis omits at sale
        // time when cost isn't known yet (the primary "genuinely late-arriving cost" case).
        //
        // P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6): 'tour' now also DEFAULTS to at_travel
        // revenue recognition, which would divert this correction's cost pair to
        // PREPAID_SUPPLIER_COST instead of SERVICE_COST — this test is about the supplier-cost
        // CORRECTION mechanism, not recognition timing, so it pins at_issue via the same
        // per-company Setting override SaleDraftBuilder::resolveRecognitionTiming() consults.
        \App\Models\Setting::create([
            'company_id' => $company->id, 'key' => 'accounting.revenue_recognition.tour',
            'value' => 'at_issue', 'type' => 'string',
        ]);

        $task = $this->makeTaskAndDetail($company, $agent, $client, $supplier, $invoice, originalCost: 0.0, sellPrice: 250.000, type: 'tour');

        $this->callUpdateAdminFinancial($user, $task, newTotal: 150.000);

        $expectedKey = 'invoice-detail:'.$task->invoiceDetail->id.':supplier-cost-correction:150.000';
        $correctionTransaction = Transaction::where('company_id', $company->id)
            ->where('idempotency_key', $expectedKey)
            ->first();

        $this->assertNotNull($correctionTransaction);

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $correctionTransaction->id)->get();
        $this->assertCount(2, $lines);

        $resolver = app(AccountResolver::class);
        $costAccountId = $resolver->resolve('SERVICE_COST', $company->id, 'tour')->id;
        $payableAccountId = $resolver->resolve('SERVICE_PAYABLE', $company->id, 'tour')->id;

        $costLine = $lines->firstWhere('account_id', $costAccountId);
        $payableLine = $lines->firstWhere('account_id', $payableAccountId);

        $this->assertNotNull($costLine, 'Principal basis correction must post SERVICE_COST.');
        $this->assertNotNull($payableLine, 'Principal basis correction must post SERVICE_PAYABLE.');
        $this->assertEqualsWithDelta(150.000, (float) $costLine->debit, 0.0005);
        $this->assertEqualsWithDelta(150.000, (float) $payableLine->credit, 0.0005);
    }

    // ── P2.5.D fix (verify finding) ────────────────────────────────────────────────────────────────
    // Previous submission: this exact call site (updateAdminFinancial()'s late-cost-correction
    // branch) had ZERO recognition-timing awareness. For a DEFAULT-configuration (no per-company
    // override) 'tour' task -- principal basis, at_travel BY DEFAULT -- a late cost correction
    // posted the full corrected cost straight to SERVICE_COST (a P&L expense), invisible to
    // RevenueRecognitionService::outstandingByTask() (which only ever reads DEFERRED_REVENUE/
    // PREPAID_SUPPLIER_COST), defeating the deferral before the travel date. This test reproduces
    // the exact default-configuration scenario the verify report proved mis-posts, and now asserts
    // the FIXED behaviour: PREPAID_SUPPLIER_COST, not SERVICE_COST.

    public function test_on_path_default_at_travel_service_type_defers_late_cost_correction_to_prepaid(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $agent, $client, $supplier, $invoice] = $this->makeFixtures($company);
        $user = User::factory()->create(['role_id' => 99]);

        $saleDate = Carbon::now()->startOfMonth()->addDay();
        $invoice->invoice_date = $saleDate;
        $invoice->save();

        // NO Setting override here -- 'tour' is left on its DEFAULT recognition timing
        // (config('accounting.revenue_recognition.default_by_service_type.tour') === at_travel),
        // exactly the production configuration the verify report reproduced against.
        $task = $this->makeTaskAndDetail($company, $agent, $client, $supplier, $invoice, originalCost: 0.0, sellPrice: 250.000, type: 'tour');
        $this->postOriginalSale($company, $task, $invoice, sell: 250.000, cost: 0.0, serviceType: 'tour', docDate: $saleDate);

        $this->callUpdateAdminFinancial($user, $task, newTotal: 150.000);

        $expectedKey = 'invoice-detail:'.$task->invoiceDetail->id.':supplier-cost-correction:150.000';
        $correctionTransaction = Transaction::where('company_id', $company->id)
            ->where('idempotency_key', $expectedKey)
            ->first();

        $this->assertNotNull($correctionTransaction);

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $correctionTransaction->id)->get();
        $this->assertCount(2, $lines);

        $resolver = app(AccountResolver::class);
        $prepaidAccountId = $resolver->resolve('PREPAID_SUPPLIER_COST', $company->id)->id;
        $serviceCostAccountId = $resolver->resolve('SERVICE_COST', $company->id, 'tour')->id;
        $payableAccountId = $resolver->resolve('SERVICE_PAYABLE', $company->id, 'tour')->id;

        $accountIdsHit = $lines->pluck('account_id')->all();
        $this->assertContains($prepaidAccountId, $accountIdsHit, 'Default at_travel + not yet recognized: the cost correction must hit PREPAID_SUPPLIER_COST, never expense it early.');
        $this->assertNotContains($serviceCostAccountId, $accountIdsHit, 'Must NOT post to the real SERVICE_COST expense account before the sale is recognised.');
        $this->assertContains($payableAccountId, $accountIdsHit, 'The real supplier liability is unaffected by recognition timing.');

        $prepaidLine = $lines->firstWhere('account_id', $prepaidAccountId);
        $this->assertEqualsWithDelta(150.000, (float) $prepaidLine->debit, 0.0005);
    }

    public function test_on_path_default_at_travel_service_type_already_recognized_corrects_real_service_cost(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        [, $agent, $client, $supplier, $invoice] = $this->makeFixtures($company);
        $user = User::factory()->create(['role_id' => 99]);

        $saleDate = Carbon::now()->subMonthsNoOverflow(2)->startOfMonth()->addDay();
        $invoice->invoice_date = $saleDate;
        $invoice->save();

        $task = $this->makeTaskAndDetail($company, $agent, $client, $supplier, $invoice, originalCost: 100.000, sellPrice: 250.000, type: 'tour');
        $task->travel_date = $saleDate->copy()->addDay();
        $task->save();

        $this->postOriginalSale($company, $task, $invoice, sell: 250.000, cost: 100.000, serviceType: 'tour', docDate: $saleDate);

        // Release BEFORE the correction -- RevenueRecognitionService has already flipped this
        // task's deferred balance to the real SERVICE_COST/SERVICE_REVENUE accounts.
        app(\App\Services\Accounting\RevenueRecognitionService::class)->release($company->id, $task->id, $user->id);

        $this->assertFalse(
            app(\App\Services\Accounting\RevenueRecognitionService::class)->isDeferredOutstanding($company->id, $task->id),
            'Sanity check: the task must actually be released for this test to mean anything.'
        );

        $this->callUpdateAdminFinancial($user, $task, newTotal: 130.000);

        $expectedKey = 'invoice-detail:'.$task->invoiceDetail->id.':supplier-cost-correction:130.000';
        $correctionTransaction = Transaction::where('company_id', $company->id)
            ->where('idempotency_key', $expectedKey)
            ->first();

        $this->assertNotNull($correctionTransaction);

        $lines = JournalEntry::withoutGlobalScopes()->where('transaction_id', $correctionTransaction->id)->get();

        $resolver = app(AccountResolver::class);
        $prepaidAccountId = $resolver->resolve('PREPAID_SUPPLIER_COST', $company->id)->id;
        $serviceCostAccountId = $resolver->resolve('SERVICE_COST', $company->id, 'tour')->id;

        $accountIdsHit = $lines->pluck('account_id')->all();
        $this->assertContains($serviceCostAccountId, $accountIdsHit, 'Already recognized: correction must target the REAL SERVICE_COST account, same as at_issue.');
        $this->assertNotContains($prepaidAccountId, $accountIdsHit, 'Already recognized: must NOT post a new deferred line -- the money is no longer sitting there.');
    }
}
