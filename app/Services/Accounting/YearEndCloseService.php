<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C; doc 11 §P5.2; period-lock-design.md §3): `accounting:year:close
 * {company} {yyyy}` — the ONE document per fiscal year that actually posts money, everywhere else
 * in this sub-wave's close routine is a pure gate (design doc §3: "monthly close posts zero
 * entries ... only year-end posts closing entries").
 *
 * Preconditions (both BLOCK, no override):
 *   - every period this company has for `$year` (all 12 months under the default `monthly` length,
 *     or the single annual sentinel row under `annual`) must already be `locked`. A MISSING row is
 *     NOT treated as "locked" here (unlike {@see PeriodGuard}'s deliberate "no row = open" rule for
 *     the posting gate) — year-end close is the one place a missing row must count as "not yet
 *     confirmed", never as an implicit pass.
 *   - `1952 Airline Memo Control` must be zero as of year end (doc 11 §P5.2: "close-year refuses
 *     while 1952 is non-zero" — a non-zero balance means undispositioned BSP memos). This is a
 *     HARDER gate than {@see PeriodCloseChecklistService}'s own monthly WARN on the same account —
 *     see that class's own docblock on `airline_memo_control_code` for why the two differ.
 *
 * ── The closing entry itself ──────────────────────────────────────────────────────────────────
 * This ledger has no separate "year" reset — every balance is a date-range QUERY
 * ({@see TrialBalanceService}), not a running column zeroed at rollover. "Sweeping P&L to retained
 * earnings" therefore means posting a REAL journal entry, dated at the fiscal year end, that debits
 * every Income leaf by its own net-credit balance for the year (and credits every Expense leaf by
 * its own net-debit balance), with the balancing line landing on RETAINED_EARNINGS (3400) — so that
 * every following year's `TrialBalanceService::getOpeningBalances()` (which sums ALL journal
 * history before the query's own `$dateFrom`) computes zero pre-existing P&L movement for those
 * leaves, exactly the "reset" a perpetual leaf normally gets from a real year-end close.
 *
 * PROOF the debit/credit totals from this sweep always balance on their own, before the
 * Retained-Earnings line is even added: for an Income leaf with credit-normal balance
 * B = credit − debit, "zero it" means debit max(B,0) / credit max(−B,0) — so
 * (debit_added − credit_added) = B for that leaf, summed across every Income leaf gives
 * ΣB_income. For an Expense leaf with debit-normal balance B = debit − credit, "zero it" means the
 * MIRROR flip — credit max(B,0) / debit max(−B,0) — so (debit_added − credit_added) = −B for that
 * leaf, summed gives −ΣB_expense. Combined: (Σdebit_added − Σcredit_added) across every P&L closing
 * line, BEFORE the Retained-Earnings line, equals exactly `$netProfit` (ΣB_income − ΣB_expense) —
 * an algebraic identity, not a rounding coincidence. The single Retained-Earnings line that then
 * makes the WHOLE document balance is therefore: CREDIT `$netProfit` when positive (profit
 * increases retained earnings, its own credit-normal direction) or DEBIT `abs($netProfit)` when
 * negative (a loss decreases it) — see {@see self::buildClosingLines()} for the literal
 * implementation of both halves of this proof.
 *
 * ── Idempotency ────────────────────────────────────────────────────────────────────────────────
 * A second run for the same (company, year) finds an existing `doc_type = 'YEC'` transaction dated
 * in that year and returns it unchanged rather than attempting to post again — belt-and-braces on
 * top of the fact that a second run's OWN P&L query would in practice already see every leaf back
 * at zero net movement (the first YEC's own lines are dated inside the year and therefore counted),
 * so there would be nothing left to sweep even without this explicit short-circuit.
 */
final class YearEndCloseService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     already_closed: bool,
     *     blocking: list<string>,
     *     net_profit: ?float,
     *     transaction: ?Transaction,
     * }
     */
    public function run(int $companyId, int $year, ?int $userId = null): array
    {
        $existing = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('doc_type', 'YEC')
            ->whereYear('transaction_date', $year)
            ->first();

        if ($existing !== null) {
            return [
                'success' => true,
                'already_closed' => true,
                'blocking' => [],
                'net_profit' => null,
                'transaction' => $existing,
            ];
        }

        $blocking = $this->checkPreconditions($companyId, $year);

        if ($blocking !== []) {
            return ['success' => false, 'already_closed' => false, 'blocking' => $blocking, 'net_profit' => null, 'transaction' => null];
        }

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        [$lines, $netProfit] = $this->buildClosingLines($companyId, $yearStart, $yearEnd);

        if ($lines === []) {
            // No P&L activity at all this year — nothing to sweep, nothing to post. Not a failure.
            return ['success' => true, 'already_closed' => false, 'blocking' => [], 'net_profit' => 0.0, 'transaction' => null];
        }

        $branch = Company::find($companyId)?->branches()->first();

        if ($branch === null) {
            return [
                'success' => false,
                'already_closed' => false,
                'blocking' => ["Company #{$companyId} has no branch to post the YEC document against."],
                'net_profit' => $netProfit,
                'transaction' => null,
            ];
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: $branch->id,
            docType: 'YEC',
            subType: null,
            docDate: $yearEnd,
            narration: "Year-end closing entry {$year}: sweep net P&L to Retained Earnings.",
            lines: $lines,
            idempotencyKey: "yec:{$companyId}:{$year}",
            userId: $userId,
            allowLockedPeriods: true,
        );

        $posted = $this->posting->post($draft, $userId);

        return [
            'success' => true,
            'already_closed' => false,
            'blocking' => [],
            'net_profit' => $netProfit,
            'transaction' => $posted->transaction,
        ];
    }

    /** @return list<string> */
    private function checkPreconditions(int $companyId, int $year): array
    {
        $blocking = [];
        $isAnnual = (string) config('accounting.period.length', 'monthly') === 'annual';

        if ($isAnnual) {
            $row = AccountingPeriod::query()->where('company_id', $companyId)->where('year', $year)
                ->where('month', AccountingPeriod::ANNUAL_MONTH)->first();
            if ($row === null || ! $row->isLocked()) {
                $blocking[] = "Fiscal year {$year} is not locked.";
            }
        } else {
            for ($month = 1; $month <= 12; $month++) {
                $row = AccountingPeriod::query()->where('company_id', $companyId)->where('year', $year)
                    ->where('month', $month)->first();
                if ($row === null || ! $row->isLocked()) {
                    $blocking[] = sprintf('%04d-%02d is not locked.', $year, $month);
                }
            }
        }

        $memoCode = (string) config('accounting.period_close.airline_memo_control_code', '1952');
        $memoAccount = Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $memoCode)->first();

        if ($memoAccount !== null) {
            $totals = DB::table('journal_entries')
                ->where('account_id', $memoAccount->id)
                ->whereNull('deleted_at')
                ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', Carbon::create($year, 12, 31)->endOfDay())
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();

            $balance = (float) $totals->d - (float) $totals->c;
            $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

            if (abs($balance) > $tolerance) {
                $blocking[] = sprintf(
                    'Airline Memo Control (code %s) has a non-zero balance of %s as of year end — undispositioned memos must be cleared before year-end close.',
                    $memoCode,
                    number_format($balance, 3)
                );
            }
        }

        return $blocking;
    }

    /**
     * @return array{0: LineDraft[], 1: float}
     */
    private function buildClosingLines(int $companyId, Carbon $yearStart, Carbon $yearEnd): array
    {
        $lines = [];
        $netProfit = 0.0;
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        foreach (['Income' => false, 'Expenses' => true] as $rootName => $isDebitNormal) {
            $leaves = DB::table('accounts as a')
                ->join('accounts as root', 'root.id', '=', 'a.root_id')
                ->where('a.company_id', $companyId)
                ->where('root.name', $rootName)
                ->whereRaw('NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id)')
                ->select('a.id', 'a.code', 'a.name')
                ->get();

            foreach ($leaves as $leaf) {
                $totals = DB::table('journal_entries')
                    ->where('account_id', $leaf->id)
                    ->whereNull('deleted_at')
                    ->whereBetween(DB::raw('COALESCE(posting_date, transaction_date)'), [$yearStart, $yearEnd])
                    ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                    ->first();

                $debit = (float) $totals->d;
                $credit = (float) $totals->c;
                $balance = $isDebitNormal ? $debit - $credit : $credit - $debit;

                if (abs($balance) <= $tolerance) {
                    continue;
                }

                $netProfit += $isDebitNormal ? -$balance : $balance;

                // Flip: credit-normal (Income) debits when positive; debit-normal (Expenses)
                // credits when positive. See class docblock's proof.
                $side = $isDebitNormal
                    ? ($balance > 0 ? 'credit' : 'debit')
                    : ($balance > 0 ? 'debit' : 'credit');

                $lines[] = new LineDraft(
                    purposeCode: '', // explicit accountId path — see PostingService::targetAccountId()'s
                    // "exactly one path" rule (reversals use the same empty-string convention).
                    accountId: (int) $leaf->id,
                    side: $side,
                    amount: abs($balance),
                    currency: (string) config('accounting.engine.base_currency', 'KWD'),
                    originalAmount: abs($balance),
                    exchangeRate: 1.0,
                    transactionType: 'YEAR_END_CLOSE',
                    description: "Year-end sweep of {$leaf->name} (code {$leaf->code}) to Retained Earnings.",
                );
            }
        }

        if ($lines === []) {
            return [[], 0.0];
        }

        $retainedEarnings = $this->accountResolver->resolve('RETAINED_EARNINGS', $companyId);

        if (abs($netProfit) > $tolerance) {
            $lines[] = new LineDraft(
                purposeCode: '', // already resolved above — see the accountId convention note in the loop.
                accountId: $retainedEarnings->id,
                side: $netProfit > 0 ? 'credit' : 'debit',
                amount: abs($netProfit),
                currency: (string) config('accounting.engine.base_currency', 'KWD'),
                originalAmount: abs($netProfit),
                exchangeRate: 1.0,
                transactionType: 'YEAR_END_CLOSE',
                description: 'Net profit/loss for the fiscal year swept to Retained Earnings.',
            );
        }

        return [$lines, $netProfit];
    }
}
