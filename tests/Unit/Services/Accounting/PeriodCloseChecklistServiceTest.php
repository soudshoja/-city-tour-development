<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\IdempotencyKeyRejection;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\PeriodCloseChecklistService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C): "checklist blocks/warns correctly" — the month-end account
 * treatment table's four gates, exercised in isolation against {@see PeriodCloseChecklistService}'s
 * return value.
 */
class PeriodCloseChecklistServiceTest extends AccountingTestCase
{
    private function service(): PeriodCloseChecklistService
    {
        return app(PeriodCloseChecklistService::class);
    }

    private function resolver(): AccountResolver
    {
        return app(AccountResolver::class);
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeCompany(bool $track = true): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        if ($track) {
            $this->trackCompanyForInvariants($company->id);
        }

        return [$company, $branch];
    }

    private function makeTransaction(Company $company, Branch $branch, string $postingStatus, Carbon $date, ?string $key = null): Transaction
    {
        return Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 100, 'description' => 'Test',
            'reference_type' => 'Invoice', 'reference_number' => 'TST-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => $postingStatus,
            'total_debit' => 100, 'total_credit' => 100, 'idempotency_key' => $key ?? uniqid('key:'),
        ]);
    }

    private function makeLine(Transaction $txn, Company $company, Branch $branch, Account $account, float $debit, float $credit, Carbon $date, ?int $typeReferenceId = null, int $reconciled = 0): JournalEntry
    {
        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $account->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Test line', 'debit' => $debit, 'credit' => $credit, 'name' => $account->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => max($debit, $credit),
            'voucher_number' => 'TST', 'type_reference_id' => $typeReferenceId, 'reconciled' => $reconciled,
        ]);
    }

    // ── Empty period ────────────────────────────────────────────────────────────────────────────

    public function test_empty_period_can_close_with_no_blocking(): void
    {
        [$company] = $this->makeCompany();

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']);
        $this->assertSame([], $result['blocking']);
    }

    // ── Documents balanced ──────────────────────────────────────────────────────────────────────

    /**
     * ── RE-DERIVED BY CT-A10 ────────────────────────────────────────────────────────────────────
     * This test built an ENGINE-shaped document (`doc_type` + `posting_date` both set) on a company
     * whose engine is OFF, and expected the checklist to see it. That stopped being true when
     * CT-A56 R3-1 made {@see \App\Services\Accounting\LedgerSource::restrictJoinedTransactions()}
     * **mode-dependent**:
     *
     *     engine ON  -> read ENGINE rows  (`doc_type` AND `posting_date` both NOT NULL)
     *     engine OFF -> read LEGACY rows  (either one NULL) -- the exact complement
     *
     * `makeCompany()` never enables the engine, so the checklist was taking the LEGACY branch and
     * the fixture's engine-shaped document was out of scope BY DESIGN. The service was right; the
     * fixture's document shape and its company's mode disagreed. The expectation was re-derived
     * rather than the assertion flipped, and the rule is now pinned in BOTH modes plus the trap
     * that caused the staleness.
     */
    public function test_unbalanced_engine_document_in_period_blocks_when_the_engine_is_on(): void
    {
        // NOT tracked for invariants -- this fixture deliberately posts an unbalanced document
        // (bypassing PostingService's own balance check via a raw forceCreate/JournalEntry write,
        // same convention AccountingVerifyCommandTest's own "unbalanced" fixtures use) specifically
        // to exercise this check; AccountingTestCase::tearDown()'s C1 invariant would otherwise
        // fail this test for the exact condition it is testing.
        [$company, $branch] = $this->makeCompany(track: false);
        $this->enableEngine($company);

        $date = Carbon::create(2026, 3, 15);
        $account = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, 'posted', $date);
        // Single one-sided line -> debit(100) != credit(0), an intentionally UNBALANCED document.
        $this->makeLine($txn, $company, $branch, $account, 100, 0, $date, $company->id);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertFalse($result['can_close']);
        $codes = array_column($result['blocking'], 'code');
        $this->assertContains('documents_unbalanced', $codes);
    }

    /**
     * The other half of the same rule: with the engine OFF the checklist reads LEGACY rows, and an
     * unbalanced legacy document must block just as loudly. This is the case that actually applies
     * to City Travelers today -- CT-D1b measured the legacy-only trial-balance residual drifting to
     * -12,657.265 while the engine ledger stayed exact.
     */
    public function test_unbalanced_legacy_document_in_period_blocks_when_the_engine_is_off(): void
    {
        [$company, $branch] = $this->makeCompany(track: false);
        $date = Carbon::create(2026, 3, 15);
        $account = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeLegacyTransaction($company, $branch, $date);
        $this->makeLine($txn, $company, $branch, $account, 100, 0, $date, $company->id);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertFalse($result['can_close']);
        $this->assertContains('documents_unbalanced', array_column($result['blocking'], 'code'));
    }

    /**
     * ── THE TRAP, PINNED ────────────────────────────────────────────────────────────────────────
     * The shape that made the old test stale, now asserted as the contract it actually is: while the
     * engine is OFF the checklist reads legacy rows ONLY, so an engine-shaped document in the period
     * is invisible to it and does NOT block.
     *
     * That is deliberate -- `restrictJoinedTransactions()` is written as an exact complement so no
     * row can fall through both branches -- but it is worth stating out loud, because "the
     * period-close checklist raised nothing" reads like a clean bill of health and is not one. A
     * company mid-cutover, flag still off and engine documents already written, gets no warning
     * about those documents from this screen.
     */
    public function test_an_engine_shaped_document_is_out_of_scope_while_the_engine_is_off(): void
    {
        [$company, $branch] = $this->makeCompany(track: false);
        $date = Carbon::create(2026, 3, 15);
        $account = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, 'posted', $date);   // doc_type + posting_date set
        $this->makeLine($txn, $company, $branch, $account, 100, 0, $date, $company->id);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertNotContains(
            'documents_unbalanced',
            array_column($result['blocking'], 'code'),
            'with the engine OFF the checklist reads LEGACY rows only -- an engine-shaped document is '
                .'out of scope by design (LedgerSource::restrictJoinedTransactions), not missed'
        );
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    /**
     * A LEGACY document: `doc_type` and `posting_date` NULL, which is exactly the complement
     * {@see \App\Services\Accounting\LedgerSource::restrictJoinedTransactions()} reads while the
     * engine is off. Everything else matches {@see self::makeTransaction()}, so the two fixtures
     * differ only in the discriminator.
     */
    private function makeLegacyTransaction(Company $company, Branch $branch, Carbon $date): Transaction
    {
        return Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 100, 'description' => 'Test legacy',
            'reference_type' => 'Invoice', 'reference_number' => 'TSTL-'.substr(uniqid(), -8),
            'name' => 'Test legacy', 'transaction_date' => $date,
            'doc_type' => null, 'posting_date' => null, 'posting_status' => 'posted',
            'total_debit' => 100, 'total_credit' => 100,
        ]);
    }

    // ── No draft documents ──────────────────────────────────────────────────────────────────────

    public function test_draft_document_in_period_blocks(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 10);

        $this->makeTransaction($company, $branch, 'draft', $date);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertFalse($result['can_close']);
        $this->assertContains('draft_documents_in_period', array_column($result['blocking'], 'code'));
    }

    public function test_draft_document_outside_period_does_not_block(): void
    {
        [$company, $branch] = $this->makeCompany();
        $this->makeTransaction($company, $branch, 'draft', Carbon::create(2026, 4, 10));

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']);
    }

    // ── No unresolved seam failures ─────────────────────────────────────────────────────────────

    public function test_unresolved_seam_failure_in_period_blocks(): void
    {
        [$company] = $this->makeCompany();

        $rejection = IdempotencyKeyRejection::create([
            'company_id' => $company->id, 'idempotency_key' => 'k1', 'dead_transaction_id' => 1,
            'attempted_amount' => 10, 'attempted_doc_type' => 'JV', 'resolution_status' => 'open',
        ]);
        // Eloquent's own updateTimestamps() overwrites a create()-time 'created_at' with now() on
        // INSERT -- backdate it with a raw UPDATE (on the model's own 'accounting_audit'
        // connection) so this fixture actually lands inside the March period being checked.
        DB::connection('accounting_audit')->table('idempotency_key_rejections')
            ->where('id', $rejection->id)->update(['created_at' => Carbon::create(2026, 3, 5)]);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertFalse($result['can_close']);
        $this->assertContains('seam_failures_in_period', array_column($result['blocking'], 'code'));
    }

    public function test_resolved_seam_failure_does_not_block(): void
    {
        [$company] = $this->makeCompany();

        $rejection = IdempotencyKeyRejection::create([
            'company_id' => $company->id, 'idempotency_key' => 'k1', 'dead_transaction_id' => 1,
            'attempted_amount' => 10, 'attempted_doc_type' => 'JV', 'resolution_status' => 'resolved',
        ]);
        DB::connection('accounting_audit')->table('idempotency_key_rejections')
            ->where('id', $rejection->id)->update(['created_at' => Carbon::create(2026, 3, 5)]);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']);
    }

    // ── Control accounts (b) — BLOCKING on unattributed money ──────────────────────────────────

    public function test_control_account_with_unattributed_line_blocks(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 12);
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);
        $offset = $this->resolver()->resolve('PAYABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, 'posted', $date);
        // Unattributed: no type_reference_id -- money on the control leaf with no traceable party.
        $this->makeLine($txn, $company, $branch, $ar, 75, 0, $date, null);
        $this->makeLine($txn, $company, $branch, $offset, 0, 75, $date, $company->id);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertFalse($result['can_close']);
        $this->assertContains('control_account_mismatch', array_column($result['blocking'], 'code'));
    }

    public function test_control_account_fully_attributed_does_not_block(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 12);
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);
        $offset = $this->resolver()->resolve('PAYABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, 'posted', $date);
        $this->makeLine($txn, $company, $branch, $ar, 75, 0, $date, 555);
        $this->makeLine($txn, $company, $branch, $offset, 0, 75, $date, 555);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']);

        $arRow = collect($result['sections']['control_accounts'])->firstWhere('purpose_code', 'RECEIVABLE_CONTROL');
        $this->assertSame('ok', $arRow['status']);
    }

    // ── Bank/cash reconciliation (a) — WARN, never block ────────────────────────────────────────

    public function test_unreconciled_bank_line_warns_but_does_not_block(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 8);
        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $offset = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, 'posted', $date);
        $this->makeLine($txn, $company, $branch, $bank, 40, 0, $date, $company->id, reconciled: 0);
        $this->makeLine($txn, $company, $branch, $offset, 0, 40, $date, $company->id);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']);
        $this->assertContains('unreconciled_bank_cash_lines', array_column($result['warnings'], 'code'));
    }

    public function test_reconciled_bank_line_has_no_warning(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 8);
        $bank = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $offset = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, 'posted', $date);
        $this->makeLine($txn, $company, $branch, $bank, 40, 0, $date, $company->id, reconciled: 1);
        $this->makeLine($txn, $company, $branch, $offset, 0, 40, $date, $company->id);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertNotContains('unreconciled_bank_cash_lines', array_column($result['warnings'], 'code'));
    }

    /**
     * Regression for the fix-round finding: the bank/cash set MUST be resolved via
     * {@see \App\Services\Accounting\AccountResolver::isCashOrBankLeaf()} (full ancestor-chain
     * walk), never via a one-level "direct children of the named group" lookup — mirrors
     * AccountResolverBankGroupTest::test_accepts_a_leaf_nested_two_levels_under_the_bank_group().
     * A leaf nested TWO levels under "Bank Accounts" (Bank Accounts -> KWD Accounts -> new leaf,
     * none of it part of the shipped CoaSeeder tree) must still surface an unreconciled-line
     * warning.
     */
    public function test_unreconciled_line_on_a_leaf_nested_two_levels_under_the_bank_group_warns(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 8);

        $bankGroup = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Bank Accounts')
            ->firstOrFail();
        $subGroup = Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $bankGroup->id,
            'name' => 'KWD Accounts',
        ]);
        $deepLeaf = Account::factory()->create([
            'company_id' => $company->id,
            'parent_id' => $subGroup->id,
            'name' => 'NBK Current Account',
        ]);
        $offset = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, 'posted', $date);
        $this->makeLine($txn, $company, $branch, $deepLeaf, 40, 0, $date, $company->id, reconciled: 0);
        $this->makeLine($txn, $company, $branch, $offset, 0, 40, $date, $company->id);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']);
        $this->assertContains('unreconciled_bank_cash_lines', array_column($result['warnings'], 'code'));
        $warningAccountIds = array_column(array_column($result['warnings'], 'meta'), 'account_id');
        $this->assertContains($deepLeaf->id, $warningAccountIds);
    }

    // ── Clearing / roll-forward (c) — WARN, never block ─────────────────────────────────────────

    public function test_rollforward_account_growing_in_period_warns(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 20);

        // 1952 is not shipped by any wave yet (P5.3.A, still future) -- create the leaf directly to
        // exercise the check's own logic in isolation from that unrelated build gap. Rooted under
        // the real 'Liabilities' root (not a bare orphan row) so TrialBalanceService's own
        // getAccountBalances() INNER JOIN on accounts.root_id still finds it -- a rootless leaf
        // would silently drop out of every trial balance query, including AccountingTestCase's own
        // tearDown() C1 invariant, and falsely report this company as unbalanced.
        $liabilitiesRoot = Account::withoutGlobalScopes()->where('company_id', $company->id)
            ->whereNull('parent_id')->where('name', 'Liabilities')->firstOrFail();

        $memoAccount = Account::create([
            'company_id' => $company->id, 'code' => '1952', 'name' => 'Airline Memo Control',
            'level' => 2, 'is_group' => false, 'currency' => 'KWD',
            'parent_id' => $liabilitiesRoot->id, 'root_id' => $liabilitiesRoot->id,
            'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0,
        ]);
        $offset = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, 'posted', $date);
        $this->makeLine($txn, $company, $branch, $memoAccount, 0, 200, $date, $company->id);
        $this->makeLine($txn, $company, $branch, $offset, 200, 0, $date, $company->id);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']); // WARN only, never blocks.
        $codes = array_column($result['warnings'], 'code');
        $this->assertContains('clearing_rollforward_exception', $codes);
        $this->assertContains('airline_memo_control_nonzero', $codes);
    }

    public function test_missing_rollforward_account_is_reported_not_configured_and_never_blocks(): void
    {
        [$company] = $this->makeCompany();

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']);
        $row = collect($result['sections']['clearing_rollforward'])->firstWhere('code', '1952');
        $this->assertSame('not_configured', $row['status']);
    }

    // ── Income / expense (d) — informational only, never gates ─────────────────────────────────

    public function test_income_expense_section_reports_variance_and_never_gates(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 5);
        $income = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4133')->firstOrFail();
        $offset = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, 'posted', $date);
        $this->makeLine($txn, $company, $branch, $offset, 500, 0, $date, $company->id);
        $this->makeLine($txn, $company, $branch, $income, 0, 500, $date, $company->id);

        $result = $this->service()->run($company->id, 2026, 3);

        $this->assertTrue($result['can_close']);
        $incomeRow = collect($result['sections']['income_expense'])->firstWhere('root', 'Income');
        $this->assertEqualsWithDelta(500.0, $incomeRow['this_period'], 0.001);
        $this->assertEqualsWithDelta(0.0, $incomeRow['prior_period'], 0.001);
    }
}
