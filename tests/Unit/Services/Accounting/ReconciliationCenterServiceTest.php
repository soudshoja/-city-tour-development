<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\ReconciliationCenterService;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): "grid BOOK balance = TrialBalanceService for seeded accounts
 * across every account group; gap math; ... control-row gap blocks close, bank-row gap only
 * warns."
 */
class ReconciliationCenterServiceTest extends AccountingTestCase
{
    private function service(): ReconciliationCenterService
    {
        return app(ReconciliationCenterService::class);
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

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    private function makeTransaction(Company $company, Branch $branch, Carbon $date): Transaction
    {
        return Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 100, 'description' => 'Test',
            'reference_type' => 'Invoice', 'reference_number' => 'RCG-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => 100, 'total_credit' => 100, 'idempotency_key' => uniqid('key:'),
        ]);
    }

    private function makeLine(Transaction $txn, Company $company, Branch $branch, Account $account, float $debit, float $credit, Carbon $date, ?int $typeReferenceId = null, int $reconciled = 0, ?string $chequeClearanceDate = null, ?string $chequeNo = null): JournalEntry
    {
        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $account->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Test line', 'debit' => $debit, 'credit' => $credit, 'name' => $account->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => max($debit, $credit),
            'voucher_number' => 'RCG', 'type_reference_id' => $typeReferenceId, 'reconciled' => $reconciled,
            'cheque_clearance_date' => $chequeClearanceDate, 'cheque_no' => $chequeNo,
        ]);
    }

    // ── BOOK balance == TrialBalanceService ────────────────────────────────────────────────────

    public function test_bank_cash_row_book_balance_matches_trial_balance_service(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 15);
        $bank = $this->accountByCode($company->id, '1201'); // a bank leaf
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, $date);
        $this->makeLine($txn, $company, $branch, $bank, 250.000, 0, $date, $company->id);
        $this->makeLine($txn, $company, $branch, $ar, 0, 250.000, $date, $company->id);

        $tb = app(TrialBalanceService::class)->generate($company->id, $date->copy()->startOfDay(), $date->copy()->endOfDay(), ['show_zero' => true]);
        $tbBank = collect($tb['accounts'])->firstWhere('id', $bank->id);

        $grid = $this->service()->grid($company->id, $date, 'day');
        $row = collect($grid['rows'])->firstWhere('key', 'bank:'.$bank->id);

        $this->assertNotNull($row, 'Bank leaf row must appear in the grid.');
        $this->assertEqualsWithDelta((float) $tbBank->closing_balance, $row['book_balance'], 0.001);
    }

    public function test_control_row_book_balance_matches_trial_balance_service(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 15);
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);
        $income = $this->accountByCode($company->id, '4110'); // any income leaf as counter-side

        $txn = $this->makeTransaction($company, $branch, $date);
        $this->makeLine($txn, $company, $branch, $ar, 300.000, 0, $date, $company->id);
        $this->makeLine($txn, $company, $branch, $income, 0, 300.000, $date, $company->id);

        $tb = app(TrialBalanceService::class)->generate($company->id, $date->copy()->startOfDay(), $date->copy()->endOfDay(), ['show_zero' => true]);
        $tbAr = collect($tb['accounts'])->firstWhere('id', $ar->id);

        $grid = $this->service()->grid($company->id, $date, 'day');
        $row = collect($grid['rows'])->firstWhere('key', 'control:RECEIVABLE_CONTROL');

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta((float) $tbAr->closing_balance, $row['book_balance'], 0.001);
    }

    public function test_clearing_row_book_balance_matches_trial_balance_service(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 15);
        $advance = $this->accountByCode($company->id, '2632'); // Client / Agent Advances
        $bank = $this->accountByCode($company->id, '1201');

        $txn = $this->makeTransaction($company, $branch, $date);
        $this->makeLine($txn, $company, $branch, $bank, 80.000, 0, $date, $company->id);
        $this->makeLine($txn, $company, $branch, $advance, 0, 80.000, $date, $company->id);

        $tb = app(TrialBalanceService::class)->generate($company->id, $date->copy()->startOfDay(), $date->copy()->endOfDay(), ['show_zero' => true]);
        $tbAdvance = collect($tb['accounts'])->firstWhere('id', $advance->id);

        $grid = $this->service()->grid($company->id, $date, 'day');
        $row = collect($grid['rows'])->firstWhere('key', 'clearing:2632');

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta((float) $tbAdvance->closing_balance, $row['book_balance'], 0.001);
    }

    // ── Gap math ────────────────────────────────────────────────────────────────────────────────

    public function test_bank_row_gap_equals_unreconciled_net_and_zero_when_fully_reconciled(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 15);
        $bank = $this->accountByCode($company->id, '1201');
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);

        $txn = $this->makeTransaction($company, $branch, $date);
        // Unreconciled line -> contributes to the gap.
        $this->makeLine($txn, $company, $branch, $bank, 60.000, 0, $date, $company->id, reconciled: 0);
        $this->makeLine($txn, $company, $branch, $ar, 0, 60.000, $date, $company->id);

        $grid = $this->service()->grid($company->id, $date, 'day');
        $row = collect($grid['rows'])->firstWhere('key', 'bank:'.$bank->id);

        $this->assertEqualsWithDelta(60.000, $row['gap'], 0.001);
        $this->assertSame('exceptions', $row['status']);
        $this->assertFalse($row['blocks_close'], 'A bank-row gap must never block close (warn-only).');
    }

    public function test_control_row_zero_gap_when_every_line_is_attributed(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 15);
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);
        $income = $this->accountByCode($company->id, '4110');

        $txn = $this->makeTransaction($company, $branch, $date);
        $this->makeLine($txn, $company, $branch, $ar, 120.000, 0, $date, typeReferenceId: $company->id);
        $this->makeLine($txn, $company, $branch, $income, 0, 120.000, $date, typeReferenceId: $company->id);

        $grid = $this->service()->grid($company->id, $date, 'day');
        $row = collect($grid['rows'])->firstWhere('key', 'control:RECEIVABLE_CONTROL');

        $this->assertEqualsWithDelta(0.0, $row['gap'], 0.001);
        $this->assertSame('reconciled', $row['status']);
        $this->assertFalse($row['blocks_close']);
    }

    public function test_control_row_nonzero_gap_blocks_close_while_bank_row_gap_only_warns(): void
    {
        [$company, $branch] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 15);
        $ar = $this->resolver()->resolve('RECEIVABLE_CONTROL', $company->id);
        $income = $this->accountByCode($company->id, '4110');
        $bank = $this->accountByCode($company->id, '1201');

        // Control leg WITHOUT attribution -> control row must BLOCK.
        $txn1 = $this->makeTransaction($company, $branch, $date);
        $this->makeLine($txn1, $company, $branch, $ar, 90.000, 0, $date, typeReferenceId: null);
        $this->makeLine($txn1, $company, $branch, $income, 0, 90.000, $date, typeReferenceId: $company->id);

        // Bank leg unreconciled -> bank row must only WARN.
        $txn2 = $this->makeTransaction($company, $branch, $date);
        $this->makeLine($txn2, $company, $branch, $bank, 40.000, 0, $date, typeReferenceId: $company->id, reconciled: 0);
        $this->makeLine($txn2, $company, $branch, $ar, 0, 40.000, $date, typeReferenceId: $company->id);

        $grid = $this->service()->grid($company->id, $date, 'day');
        $controlRow = collect($grid['rows'])->firstWhere('key', 'control:RECEIVABLE_CONTROL');
        $bankRow = collect($grid['rows'])->firstWhere('key', 'bank:'.$bank->id);

        $this->assertNotEqualsWithDelta(0.0, $controlRow['gap'], 0.001);
        $this->assertTrue($controlRow['blocks_close'], 'A non-zero control-row gap must block close.');
        $this->assertSame('exceptions', $controlRow['status']);

        $this->assertFalse($bankRow['blocks_close'], 'A bank-row gap must never block close.');
    }

    public function test_review_only_pl_rows_never_block_close(): void
    {
        [$company] = $this->makeCompany();
        $date = Carbon::create(2026, 3, 15);

        $grid = $this->service()->grid($company->id, $date, 'day');
        $income = collect($grid['rows'])->firstWhere('key', 'review:Income');
        $expenses = collect($grid['rows'])->firstWhere('key', 'review:Expenses');

        $this->assertNotNull($income);
        $this->assertNotNull($expenses);
        $this->assertSame('review_only', $income['status']);
        $this->assertFalse($income['blocks_close']);
        $this->assertFalse($expenses['blocks_close']);
    }

    // ── Gap explanation panel (verify-fix: CONFIRMED double-counting bug) ──────────────────────────

    /**
     * P2.5.G verify-fix regression test — reproduces the EXACT scenario the failed verify pass
     * specified: "one pending proposal, one >30d unmatched item, one cheque-in-hand timing item"
     * with BOOK "fully explained by 50+75+30=155". Before the fix this returned
     * residual=-50/exception=true with a false "unrecorded bank charge" advice line; after the fix
     * the residual must be ~0 and no exception must be raised.
     */
    public function test_gap_explanation_does_not_double_count_a_pending_proposal_against_unmatched_and_names_timing_differences(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $today = Carbon::create(2026, 3, 15);

        // 1) A pending-proposal line worth 50 — still reconciled=0 (a proposal never flips that
        //    flag; only approve() does), and it MUST NOT also show up as "unmatched".
        $proposalTxn = $this->makeTransaction($company, $branch, $today);
        $proposalLine = $this->makeLine($proposalTxn, $company, $branch, $bank, 50.000, 0, $today, $company->id, reconciled: 0);
        $this->makeLine($proposalTxn, $company, $branch, $income, 0, 50.000, $today, $company->id);
        ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id, 'run_id' => null,
            'source' => 'internal', 'kind' => ReconciliationProposal::KIND_CLEARING_ROLLFORWARD,
            'confidence' => ReconciliationProposal::CONFIDENCE_EXACT,
            'book_journal_entry_id' => $proposalLine->id, 'amount' => 50.000, 'difference_amount' => 0,
            'status' => ReconciliationProposal::STATUS_PENDING,
        ]);

        // 2) A genuinely stale unmatched line worth 75, 45 days old (>30d ageing bucket).
        $staleDate = $today->copy()->subDays(45);
        $staleTxn = $this->makeTransaction($company, $branch, $staleDate);
        $this->makeLine($staleTxn, $company, $branch, $bank, 75.000, 0, $staleDate, $company->id, reconciled: 0);
        $this->makeLine($staleTxn, $company, $branch, $income, 0, 75.000, $staleDate, $company->id);

        // 3) A cheque-in-hand-not-yet-cleared timing item worth 30 — cheque_no set, no clearance
        //    date yet (still in hand).
        $chequeTxn = $this->makeTransaction($company, $branch, $today);
        $this->makeLine($chequeTxn, $company, $branch, $bank, 30.000, 0, $today, $company->id, reconciled: 0, chequeNo: 'CHQ-GAP-1');
        $this->makeLine($chequeTxn, $company, $branch, $income, 0, 30.000, $today, $company->id);

        $grid = $this->service()->grid($company->id, $today, 'day');
        $row = collect($grid['rows'])->firstWhere('key', 'bank:'.$bank->id);
        $this->assertEqualsWithDelta(155.000, $row['book_balance'], 0.001, 'Sanity: BOOK must be the full 50+75+30.');
        $this->assertEqualsWithDelta(155.000, $row['gap'], 0.001, 'Sanity: every line is unreconciled, so GAP == BOOK.');

        $explanation = $this->service()->explainGap($company->id, $row, $today);

        $this->assertEqualsWithDelta(0.0, $explanation['residual'], 0.001, 'BOOK is fully explained by 50 (proposal) + 75 (unmatched) + 30 (timing) — residual must be ~0.');
        $this->assertFalse($explanation['exception'], 'A fully-explained gap must never be flagged as an exception.');
        $this->assertNull($explanation['advice'], 'No exception -> no advice line.');

        $labels = collect($explanation['components'])->pluck('label')->all();
        $this->assertContains('Proposals pending approval', $labels);
        $this->assertTrue(
            collect($labels)->contains(fn (string $l) => str_contains($l, '31-60d') || str_contains($l, '0-30d')),
            'The 45-day-old line must appear as an ageing-bucket component.'
        );
        $this->assertTrue(
            collect($labels)->contains(fn (string $l) => str_contains(strtolower($l), 'timing')),
            'Owner refinement 2026-08-30: a named timing-differences component must appear when a cheque-in-hand line is present.'
        );

        $proposalsComponent = collect($explanation['components'])->firstWhere('label', 'Proposals pending approval');
        $this->assertEqualsWithDelta(50.000, $proposalsComponent['amount'], 0.001);

        $timingComponent = collect($explanation['components'])->first(fn (array $c) => str_contains(strtolower($c['label']), 'timing'));
        $this->assertEqualsWithDelta(30.000, $timingComponent['amount'], 0.001);
    }

    /**
     * A genuine residual: pendingAmount is deliberately, documentedly an UNSIGNED approximation
     * (class docblock on {@see \App\Services\Accounting\ReconciliationCenterService::explainGap()}:
     * "the exact SIGNED contribution depends on which side of the line it sits on ... does not
     * need to get to the fils"). A pending proposal on the CREDIT side of a debit-normal bank leaf
     * (which makes its own line's contribution to GAP negative) still gets summed as a POSITIVE
     * amount — so the panel cannot fully explain this gap and must correctly surface it as a real,
     * un-auto-resolved EXCEPTION with advice, rather than silently (and wrongly) calling it clean.
     */
    public function test_gap_explanation_flags_a_genuine_exception_with_advice_when_a_residual_remains(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 15);

        $txn = $this->makeTransaction($company, $branch, $date);
        $line = $this->makeLine($txn, $company, $branch, $bank, 0, 40.000, $date, $company->id, reconciled: 0);
        $this->makeLine($txn, $company, $branch, $income, 40.000, 0, $date, $company->id);

        ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id,
            'source' => 'internal', 'kind' => ReconciliationProposal::KIND_MANUAL, 'confidence' => 'manual',
            'book_journal_entry_id' => $line->id, 'amount' => 40.000, 'status' => 'pending',
        ]);

        $grid = $this->service()->grid($company->id, $date, 'day');
        $row = collect($grid['rows'])->firstWhere('key', 'bank:'.$bank->id);
        $this->assertEqualsWithDelta(-40.000, $row['gap'], 0.001, 'Sanity: a credit-side line on a debit-normal leaf is a NEGATIVE gap contribution.');

        $explanation = $this->service()->explainGap($company->id, $row, $date);

        $this->assertTrue($explanation['exception'], 'A credit-side pending proposal cannot fully explain a negative gap under the unsigned-amount approximation — must surface as an exception, not silently pass as clean.');
        $this->assertNotNull($explanation['advice']);
        $this->assertArrayHasKey('cause', $explanation['advice']);
        $this->assertArrayHasKey('label', $explanation['advice']);
    }

    public function test_unmatched_for_excludes_a_line_already_covered_by_a_pending_proposal(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 15);

        $txn = $this->makeTransaction($company, $branch, $date);
        $line = $this->makeLine($txn, $company, $branch, $bank, 20.000, 0, $date, $company->id, reconciled: 0);
        $this->makeLine($txn, $company, $branch, $income, 0, 20.000, $date, $company->id);

        ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id,
            'source' => 'internal', 'kind' => ReconciliationProposal::KIND_MANUAL, 'confidence' => 'manual',
            'book_journal_entry_id' => $line->id, 'amount' => 20.000, 'status' => 'pending',
        ]);

        $unmatched = $this->service()->unmatchedFor($company->id, [$bank->id], ReconciliationCenterService::GROUP_BANK_CASH, $date);

        $this->assertEmpty($unmatched['items'], 'A line already covered by a pending proposal must not also appear as unmatched.');
    }

    public function test_grid_row_counts_do_not_double_count_a_pending_proposal_line_as_unmatched(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 15);

        $txn = $this->makeTransaction($company, $branch, $date);
        $line = $this->makeLine($txn, $company, $branch, $bank, 20.000, 0, $date, $company->id, reconciled: 0);
        $this->makeLine($txn, $company, $branch, $income, 0, 20.000, $date, $company->id);

        ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id,
            'source' => 'internal', 'kind' => ReconciliationProposal::KIND_MANUAL, 'confidence' => 'manual',
            'book_journal_entry_id' => $line->id, 'amount' => 20.000, 'status' => 'pending',
        ]);

        $grid = $this->service()->grid($company->id, $date, 'day');
        $row = collect($grid['rows'])->firstWhere('key', 'bank:'.$bank->id);

        $this->assertSame(1, $row['counts']['proposals']);
        $this->assertSame(0, $row['counts']['unmatched'], 'The single line has a pending proposal — it must count once, as a proposal, not also as unmatched.');
    }

    // ── Run-status panel ────────────────────────────────────────────────────────────────────────

    public function test_run_status_reports_the_last_nightly_and_last_any_run(): void
    {
        [$company] = $this->makeCompany();

        \App\Models\ReconciliationRun::create([
            'company_id' => $company->id, 'status' => 'completed', 'trigger' => 'nightly',
            'started_at' => now()->subDay(), 'finished_at' => now()->subDay()->addMinute(),
            'proposals_created' => 3, 'auto_matched_pending' => 2, 'exceptions_count' => 1, 'duration_ms' => 500,
        ]);
        $manualRun = \App\Models\ReconciliationRun::create([
            'company_id' => $company->id, 'status' => 'completed', 'trigger' => 'manual',
            'started_at' => now(), 'finished_at' => now()->addSecond(),
            'proposals_created' => 1, 'auto_matched_pending' => 1, 'exceptions_count' => 0, 'duration_ms' => 200,
        ]);

        $status = $this->service()->runStatus($company->id);

        $this->assertSame(3, $status['last_nightly_run']['proposals_created']);
        $this->assertSame($manualRun->id, $status['last_run']['id'], 'last_run must be the most recent run regardless of trigger.');
    }

    public function test_run_status_reports_null_when_no_run_has_ever_happened(): void
    {
        [$company] = $this->makeCompany();

        $status = $this->service()->runStatus($company->id);

        $this->assertNull($status['last_nightly_run']);
        $this->assertNull($status['last_run']);
    }

    // ── History drawer ──────────────────────────────────────────────────────────────────────────

    public function test_history_for_includes_a_proposal_approval_audit_row(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $income = $this->accountByCode($company->id, '4110');
        $date = Carbon::create(2026, 3, 15);

        $txn = $this->makeTransaction($company, $branch, $date);
        $line = $this->makeLine($txn, $company, $branch, $bank, 15.000, 0, $date, $company->id, reconciled: 0);
        $this->makeLine($txn, $company, $branch, $income, 0, 15.000, $date, $company->id);

        $proposal = ReconciliationProposal::create([
            'company_id' => $company->id, 'account_id' => $bank->id,
            'source' => 'internal', 'kind' => 'manual', 'confidence' => 'manual',
            'book_journal_entry_id' => $line->id, 'amount' => 15.000, 'status' => 'pending',
        ]);
        app(\App\Services\Accounting\ReconciliationProposalService::class)->approve($proposal, User::factory()->create());

        $history = $this->service()->historyFor($company->id, [$bank->id]);

        $this->assertGreaterThan(0, $history->count());
        $this->assertTrue($history->contains(fn ($h) => $h->subject_type === 'reconciliation_proposal' && (int) $h->subject_id === $proposal->id));
    }

    // ── Gateway settlement-lag timing difference ───────────────────────────────────────────────

    public function test_unmatched_for_names_a_recent_gateway_clearing_line_as_a_timing_difference(): void
    {
        [$company, $branch] = $this->makeCompany();
        $gateway = $this->resolver()->resolve('GATEWAY_CLEARING_MYFATOORAH', $company->id);
        $income = $this->accountByCode($company->id, '4110');
        $today = Carbon::create(2026, 3, 15);
        $recent = $today->copy()->subDays(2); // inside the default 5-day settlement-lag window

        $txn = $this->makeTransaction($company, $branch, $recent);
        $this->makeLine($txn, $company, $branch, $gateway, 60.000, 0, $recent, $company->id, reconciled: 0);
        $this->makeLine($txn, $company, $branch, $income, 0, 60.000, $recent, $company->id);

        $unmatched = $this->service()->unmatchedFor($company->id, [$gateway->id], ReconciliationCenterService::GROUP_BANK_CASH, $today);

        $item = collect($unmatched['items'])->first();
        $this->assertNotNull($item);
        $this->assertSame('timing_gateway', $item['ageing_bucket']);
        $this->assertTrue($item['is_timing_difference']);
        $this->assertEqualsWithDelta(60.000, $unmatched['buckets']['timing_gateway'], 0.001);
        $this->assertEqualsWithDelta(0.0, $unmatched['buckets']['0_30'], 0.001, 'A within-settlement-window gateway line must not also land in the plain ageing bucket.');
    }

    public function test_unmatched_for_leaves_a_stale_gateway_clearing_line_as_a_plain_ageing_item(): void
    {
        [$company, $branch] = $this->makeCompany();
        $gateway = $this->resolver()->resolve('GATEWAY_CLEARING_MYFATOORAH', $company->id);
        $income = $this->accountByCode($company->id, '4110');
        $today = Carbon::create(2026, 3, 15);
        $stale = $today->copy()->subDays(20); // outside the 5-day settlement-lag window

        $txn = $this->makeTransaction($company, $branch, $stale);
        $this->makeLine($txn, $company, $branch, $gateway, 25.000, 0, $stale, $company->id, reconciled: 0);
        $this->makeLine($txn, $company, $branch, $income, 0, 25.000, $stale, $company->id);

        $unmatched = $this->service()->unmatchedFor($company->id, [$gateway->id], ReconciliationCenterService::GROUP_BANK_CASH, $today);

        $item = collect($unmatched['items'])->first();
        $this->assertNotNull($item);
        $this->assertSame('0_30', $item['ageing_bucket'], 'A gateway line older than the settlement-lag window is a genuine unmatched/ageing item, never silently absorbed into timing.');
        $this->assertFalse($item['is_timing_difference']);
    }
}
