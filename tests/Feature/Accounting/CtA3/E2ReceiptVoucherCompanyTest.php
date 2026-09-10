<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Exceptions\Accounting\UnresolvedReceiptCompanyException;
use App\Http\Controllers\ReceiptVoucherController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 E2 — CT-F35: `invoice_receipts.company_id` is NULL on all 109 rows (CT-A2 §3.2, CT-A1
 * §2.1). `ReceiptVoucherController::buildVoucherDraft()` used to cast that NULL to the sentinel
 * `0`, so `AccountResolver::resolve('CASH_IN_HAND'|'CHEQUES_IN_HAND', 0)` always threw
 * `UnmappedPurposeException` — 109 of 109 receipt vouchers refused to post.
 *
 * Covers both halves of the fix:
 *  (a) {@see ReceiptVoucherController::buildVoucherDraft()} (via {@see
 *      ReceiptVoucherController::resolveReceiptCompanyId()}) — derives the company from the
 *      invoice/client/task/account/branch chain when the row's own `company_id` is null/
 *      non-positive, and throws {@see UnresolvedReceiptCompanyException} rather than resolving 0
 *      when the chain also fails.
 *  (b) `accounting:repair-receipt-company` — the data-repair command that back-fills
 *      `invoice_receipts.company_id` from that same chain.
 */
class E2ReceiptVoucherCompanyTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $company->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $agent, $client, $admin];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    private function makeUnpaidInvoice(Client $client, Agent $agent, float $amount = 100.000): Invoice
    {
        return Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => $amount,
            'status' => 'unpaid',
            'invoice_date' => now(),
        ]);
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /**
     * Creates an `invoice_receipts` row DIRECTLY (bypassing ReceiptVoucherController::store(),
     * which always overwrites `company_id` with the caller's own resolved company -- see that
     * method's own "Tenant-lock hardening" comment) so the row carries a NULL `company_id`, the
     * exact shape CT-A2 §3.2 found on all 109 real legacy rows.
     */
    private function makeNullCompanyReceipt(array $overrides = []): InvoiceReceipt
    {
        return InvoiceReceipt::create(array_merge([
            'type' => 'account',
            'company_id' => null,
            'branch_id' => null,
            'doc_date' => now()->toDateString(),
            'account_id' => null,
            'client_id' => null,
            'task_id' => null,
            'bank_account_id' => null,
            'invoice_id' => null,
            'amount' => 50,
            'allocations' => null,
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'status' => InvoiceReceipt::STATUS_PENDING,
        ], $overrides));
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (a) buildVoucherDraft() / resolveReceiptCompanyId() at post time
    // ────────────────────────────────────────────────────────────────────────────────────────

    /** Case 1: NULL company_id, resolvable via the invoice chain -> posts a balanced document to
     *  the right company (this used to refuse with company_id=0). */
    public function test_null_company_id_with_resolvable_invoice_posts_to_the_right_company(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $invoice = $this->makeUnpaidInvoice($client, $agent, 100.000);
        $cashInHand = $this->accountByCode($company->id, '1120');

        $receipt = $this->makeNullCompanyReceipt([
            'type' => 'invoice',
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 100]],
        ]);

        $this->actingAs($admin)
            ->post(route('receipt-voucher.approve', $receipt->id))
            ->assertRedirect(route('receipt-voucher.index'));

        $receipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $receipt->status);
        $this->assertNotNull($receipt->transaction_id);

        $transaction = Transaction::withoutGlobalScopes()->find($receipt->transaction_id);
        $this->assertNotNull($transaction);
        $this->assertSame($company->id, (int) $transaction->company_id);
        $this->assertNotSame(0, (int) $transaction->company_id);

        $lines = JournalEntry::where('transaction_id', $receipt->transaction_id)->get();
        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.001);

        $debit = $lines->firstWhere('account_id', $cashInHand->id);
        $this->assertNotNull($debit, 'Instrument leg should debit CASH_IN_HAND for the resolved company.');
        $this->assertEqualsWithDelta(100.0, (float) $debit->debit, 0.001);

        foreach ($lines as $line) {
            $this->assertSame($company->id, (int) $line->company_id);
        }

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);

        // The feeder resolves the company for POSTING purposes only -- it must not silently
        // persist a repair as a side effect of approve(); that is accounting:repair-receipt-
        // company's own, separate, explicit job (see the (b) tests below).
        $this->assertNull($receipt->company_id);
    }

    /** Case 2: no link in the chain resolves -> the named exception fires (asserted directly,
     *  bypassing the controller's own PostingException-to-flash-message catch), names the receipt
     *  id, and the HTTP-level approve() path never posts and never posts under company 0. */
    public function test_unresolvable_receipt_company_throws_named_exception_and_never_posts(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $receipt = $this->makeNullCompanyReceipt();

        $controller = app(ReceiptVoucherController::class);
        $method = new ReflectionMethod($controller, 'buildVoucherDraft');
        $method->setAccessible(true);

        $thrown = null;

        try {
            $method->invoke($controller, $receipt->fresh());
        } catch (UnresolvedReceiptCompanyException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(UnresolvedReceiptCompanyException::class, $thrown, 'Expected UnresolvedReceiptCompanyException to be thrown for an unresolvable receipt.');
        $this->assertSame($receipt->id, $thrown->receiptId);
        $this->assertStringContainsString((string) $receipt->id, $thrown->getMessage());

        // HTTP-level: the controller's own PostingException catch turns this into a flash error,
        // never a raw 500 -- but it must still leave the row untouched and must never post a
        // Transaction under the 0 sentinel.
        $this->actingAs($admin)
            ->post(route('receipt-voucher.approve', $receipt->id))
            ->assertRedirect();

        $receipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_PENDING, $receipt->status);
        $this->assertNull($receipt->transaction_id);
        $this->assertSame(0, Transaction::withoutGlobalScopes()->where('company_id', 0)->count());
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // (b) accounting:repair-receipt-company
    // ────────────────────────────────────────────────────────────────────────────────────────

    /** Case 3: --dry-run writes nothing and reports the count it would fix. */
    public function test_repair_command_dry_run_writes_nothing(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $invoice = $this->makeUnpaidInvoice($client, $agent, 80.000);
        $resolvable = $this->makeNullCompanyReceipt([
            'type' => 'invoice',
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 80,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 80]],
        ]);

        $unresolvable = $this->makeNullCompanyReceipt();

        // $this->artisan()->expectsOutputToContain(), never Artisan::call()+Artisan::output(): a
        // console-output-string match against Artisan::output() reads empty in this codebase's own
        // test suite, because Tests\TestCase::setUp() runs $this->artisan('db:seed', [...]) for
        // every RefreshDatabase test (via AccountingTestCase), which permanently rebinds
        // Illuminate\Console\OutputStyle to a fixed Mockery buffer for the rest of the test
        // (Laravel's InteractsWithConsole::mockConsoleOutput() is never unbound) -- only
        // $this->artisan()'s own PendingCommand reads that same rebound buffer back correctly. See
        // tests/Feature/Accounting/EnsureSystemLeavesTest.php's own docblock for the identical,
        // already-documented quirk and fix in this codebase.
        $this->artisan('accounting:repair-receipt-company', ['--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain(sprintf('[id=%d] WOULD SET company_id=%d', $resolvable->id, $company->id))
            ->expectsOutputToContain(sprintf('[id=%d] UNRESOLVED', $unresolvable->id))
            ->expectsOutputToContain('Would back-fill 1 row(s), 1 left unresolved.');

        $resolvable->refresh();
        $unresolvable->refresh();
        $this->assertNull($resolvable->company_id, 'Dry-run must not write anything, even for a resolvable row.');
        $this->assertNull($unresolvable->company_id);
    }

    /** Case 4: --apply back-fills every resolvable row, is idempotent (second run fixes 0), and
     *  never overwrites a row that already had a (deliberately different) company_id. */
    public function test_repair_command_apply_backfills_idempotently_and_never_overwrites(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        [$otherCompany] = $this->makeFixture();

        $invoice = $this->makeUnpaidInvoice($client, $agent, 45.000);

        $resolvable = $this->makeNullCompanyReceipt([
            'type' => 'invoice',
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 45,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 45]],
        ]);

        // Deliberately carries a DIFFERENT company_id than the chain would derive (the chain would
        // resolve $company->id via this same $invoice) -- must be left completely untouched.
        $preSeeded = $this->makeNullCompanyReceipt([
            'type' => 'invoice',
            'company_id' => $otherCompany->id,
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 10,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 10]],
        ]);

        $unresolvable = $this->makeNullCompanyReceipt();

        $this->artisan('accounting:repair-receipt-company', ['--apply' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain(sprintf('[id=%d] SET company_id=%d', $resolvable->id, $company->id))
            ->expectsOutputToContain(sprintf('[id=%d] UNRESOLVED', $unresolvable->id))
            ->expectsOutputToContain('Back-filled 1 row(s), 1 left unresolved.');

        $resolvable->refresh();
        $preSeeded->refresh();
        $unresolvable->refresh();

        $this->assertSame($company->id, $resolvable->company_id);
        $this->assertSame($otherCompany->id, $preSeeded->company_id, 'A row that already had a company_id must never be overwritten, even when the chain would derive a different one.');
        $this->assertNull($unresolvable->company_id);

        // Idempotent: a second run touches nothing already resolved and fixes 0 new rows.
        $this->artisan('accounting:repair-receipt-company', ['--apply' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain('Back-filled 0 row(s), 1 left unresolved.');

        $resolvable->refresh();
        $preSeeded->refresh();
        $this->assertSame($company->id, $resolvable->company_id, 'Second run must not change an already-repaired row.');
        $this->assertSame($otherCompany->id, $preSeeded->company_id, 'Second run must still never overwrite the pre-seeded row.');
    }

    /** Case 5: after --apply, a previously-refusing voucher posts. */
    public function test_after_apply_a_previously_unpostable_voucher_now_posts(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();
        $this->enableEngine($company);

        $invoice = $this->makeUnpaidInvoice($client, $agent, 60.000);

        $receipt = $this->makeNullCompanyReceipt([
            'type' => 'invoice',
            'branch_id' => $branch->id,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 60,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 60]],
        ]);

        $applyExitCode = Artisan::call('accounting:repair-receipt-company', ['--apply' => true]);
        $this->assertSame(0, $applyExitCode);

        $receipt->refresh();
        $this->assertSame($company->id, $receipt->company_id);

        $this->actingAs($admin)
            ->post(route('receipt-voucher.approve', $receipt->id))
            ->assertRedirect(route('receipt-voucher.index'));

        $receipt->refresh();
        $this->assertSame(InvoiceReceipt::STATUS_APPROVED, $receipt->status);
        $this->assertNotNull($receipt->transaction_id);

        $transaction = Transaction::withoutGlobalScopes()->find($receipt->transaction_id);
        $this->assertSame($company->id, (int) $transaction->company_id);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
    }
}
