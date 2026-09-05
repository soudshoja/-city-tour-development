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
 * Preconditions (all BLOCK, no override):
 *   - every period this company has for `$year` (all 12 months under the default `monthly` length,
 *     or the single annual sentinel row under `annual`) must already be `locked`. A MISSING row is
 *     NOT treated as "locked" here (unlike {@see PeriodGuard}'s deliberate "no row = open" rule for
 *     the posting gate) — year-end close is the one place a missing row must count as "not yet
 *     confirmed", never as an implicit pass.
 *   - `1952 Airline Memo Control` must be zero as of year end (doc 11 §P5.2: "close-year refuses
 *     while 1952 is non-zero" — a non-zero balance means undispositioned BSP memos). This is a
 *     HARDER gate than {@see PeriodCloseChecklistService}'s own monthly WARN on the same account —
 *     see that class's own docblock on `airline_memo_control_code` for why the two differ.
 *   - `DIVIDENDS_PAID` must be MAPPED whenever the Dividends Paid leaf (3200, or a child of it)
 *     actually moved during the year — otherwise the dividend sweep below would be silently
 *     dropped and Retained Earnings overstated with no signal. See
 *     {@see self::checkDividendMappingGap()} for the full derivation of why "unmapped" cannot be
 *     read as "zero" once real money is on the leaf. An unmapped leaf that did NOT move is still a
 *     clean no-op, not a block, and neither is a balance CARRIED IN from an earlier year with no
 *     movement of its own (a correctly mapped company does not sweep that either — see that
 *     method's "MOVEMENT, NOT BALANCE" note). The mirror case blocks too: when the mapping DOES
 *     exist but points at only part of the 3200 subtree, the rest of that subtree's movement would
 *     be left behind by the mapped-account-only sweep — the same silent loss, same refusal.
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
 *
 * ── Dividend sweep (accounting-builds T5, L9) ─────────────────────────────────────────────────────
 * The SAME document also sweeps Dividends Paid (3200, `DIVIDENDS_PAID` purpose, debit-normal) to
 * Retained Earnings, as two ADDITIONAL lines appended after the P&L block: Cr 3200 (zeroing the
 * year's dividend movement) / Dr `RETAINED_EARNINGS` for the same amount — its own self-balancing
 * pair, independent of `$netProfit`, which the dividend sweep never alters (dividends are a
 * distribution of profit, not an expense). Kept inside this SAME `YEC` document — never a second
 * document — so `TrialBalanceService`'s whole-document YEC movement-exclusion (see that class's own
 * docblock) covers both sweeps identically; a separate document would let one sweep's movement stay
 * excluded while the other's leaked into the closing year's own trial balance. See
 * {@see self::buildClosingLines()} for the literal implementation.
 */
final class YearEndCloseService
{
    /**
     * Structural code of the Dividends Paid leaf (CoaSeeder seeds it identically for every
     * company; SystemAccountsSeeder maps `DIVIDENDS_PAID` onto exactly this code). Used ONLY by
     * {@see self::checkDividendMappingGap()}'s misconfiguration precondition — the sweep itself
     * always posts to the registry-resolved account, never to this literal.
     */
    private const DIVIDENDS_PAID_CODE = '3200';

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

        foreach ($this->checkDividendMappingGap($companyId, $year) as $reason) {
            $blocking[] = $reason;
        }

        return $blocking;
    }

    /**
     * POST-FIX RE-VERIFICATION guard (accounting-builds T5/T6 review §10): the misconfiguration
     * that "unmapped purpose = zero dividend movement" would otherwise hide.
     *
     * {@see self::buildClosingLines()} skips the dividend sweep entirely when a company has no
     * `DIVIDENDS_PAID` mapping — correct and necessary while the leaf carries no money (a company
     * whose registry was never seeded must still close a no-activity year cleanly), but WRONG the
     * moment real dividend money sits on the leaf: the YEC would post without the sweep, Retained
     * Earnings would be overstated by exactly the dividend, nothing would say so, and run()'s
     * "a YEC already exists" short-circuit makes it unrecoverable by simply re-running the close.
     *
     * The mapping is NOT guaranteed for a real company: {@see \Database\Seeders\SystemAccountsSeeder}
     * ::mapByCode() SKIPS-and-reports (never fails) when code 3200 is absent, duplicated, or has
     * grown children, and `DIVIDENDS_PAID` was only introduced by this phase's T0a — so every
     * company that predates T0a is unmapped until the seeder is re-run.
     * {@see \Tests\Feature\Accounting\PurposeMappingCoverageTest} only proves the mapping for a
     * FRESHLY CoaSeeder+SystemAccountsSeeder'd company, not for the installed base.
     *
     * So: BLOCK (nothing posted, fully recoverable — map the purpose, close again) rather than
     * silently under-post. Same shape as the 1952 Airline-Memo gate above, which likewise reads a
     * structural code directly: this is a precondition CHECK, never an account resolution used to
     * post — the sweep itself still refuses to post to anything but a registry-resolved leaf.
     *
     * Scope note: the check is on the YEAR'S OWN MOVEMENT (what the sweep would have posted), not
     * the leaf's cumulative balance, so it is exactly the amount that would go missing. Children of
     * 3200 are included — a 3200 that grew sub-accounts is itself a reason the seeder skipped the
     * mapping, and the money then lives on the children.
     *
     * MOVEMENT, NOT BALANCE — the ruling, re-derived in the loop-3 pass and pinned by
     * {@see \Tests\Feature\Accounting\T5T6AdversarialVerificationTest}::
     * test_a_carried_dividend_balance_with_no_movement_is_treated_identically_mapped_or_not():
     * a balance carried in from an earlier year with NO movement this year is NOT swept by a
     * correctly-MAPPED company either (L9 defines the sweep on the year's movement), so an unmapped
     * company in that state loses nothing the mapping would have saved. Gating on balance would
     * therefore refuse a close that a correct company sails through, and the remedy this guard
     * promises ("map the purpose and close again") would not actually sweep the balance — a block
     * the operator cannot clear by doing what it says. The carried balance is a real but SEPARATE
     * concern (a prior year never closed, or closed before T5 existed); it belongs to the one-off
     * backfill question in the review packet's §10.8, not to this precondition.
     *
     * Loop-3 addition: the guard now also covers the MAPPED side of the same hazard — see the
     * second arm below and {@see self::dividendMappingRemedy()} for why the old flat "re-run
     * SystemAccountsSeeder" remedy was a dead end for two of the three unmapped states.
     *
     * @return list<string>
     */
    private function checkDividendMappingGap(int $companyId, int $year): array
    {
        $accountIds = $this->dividendSubtreeAccountIds($companyId);

        if ($accountIds === []) {
            // No Dividends Paid leaf at all (a company with no COA seeded) — nothing can be
            // hiding on it. Stays the clean no-op the previous fix restored.
            return [];
        }

        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        $mappedId = DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('purpose_code', 'DIVIDENDS_PAID')
            ->whereNull('service_type')
            ->value('account_id');

        if ($mappedId === null) {
            $movement = $this->dividendMovement($accountIds, $year);

            if (abs($movement) <= $tolerance) {
                return [];
            }

            return [sprintf(
                'Dividends Paid (code %s) moved %s during %d but this company has no DIVIDENDS_PAID purpose mapping — the year-end dividend sweep would be silently dropped and Retained Earnings overstated by that amount. %s',
                self::DIVIDENDS_PAID_CODE,
                number_format($movement, 3),
                $year,
                $this->dividendMappingRemedy($companyId)
            )];
        }

        // FINAL RE-VERIFICATION (loop 3): the mapped side of the SAME hazard, reachable by
        // half-following the remedy the message above hands the operator ("map DIVIDENDS_PAID onto
        // the leaf that carries the dividends"). buildClosingLines()'s sweep queries ONLY the
        // mapped account's own journal_entries, so anything that moved elsewhere in the 3200
        // subtree is not swept. Two distinct outcomes, both bad, both caught here:
        //   - mapped onto the 3200 GROUP itself: {@see AccountResolver::resolve()} throws
        //     NonLeafAccountException from inside buildClosingLines() — AFTER preconditions passed,
        //     so the operator gets an uncaught 500 instead of a refusal they can act on (pinned by
        //     mutation MP-L4, whose failure mode is exactly that exception).
        //   - mapped onto ONE child leaf while a SIBLING child also carries dividends: resolve()
        //     is perfectly happy, the close posts, and the sibling's movement is dropped in
        //     silence with Retained Earnings overstated by it — the genuine silent-loss case.
        // On a standard CoaSeeder chart 3200 is a childless, unique leaf and the mapped account IS
        // the whole subtree, so this arm can never fire.
        $mappedId = (int) $mappedId;

        if (! in_array($mappedId, $accountIds, true)) {
            // DIVIDENDS_PAID deliberately points somewhere outside the 3200 subtree — the registry
            // is authoritative about which account this company treats as Dividends Paid, so 3200's
            // own movement is none of this guard's business.
            return [];
        }

        $strandedIds = array_values(array_diff($accountIds, [$mappedId]));

        if ($strandedIds === []) {
            return [];
        }

        $stranded = $this->dividendMovement($strandedIds, $year);

        if (abs($stranded) <= $tolerance) {
            return [];
        }

        return [sprintf(
            'Dividends Paid (code %s) has %d other account(s) in its subtree carrying %s of %d movement that the DIVIDENDS_PAID mapping does not point at — the year-end sweep only zeroes the mapped account, so that amount would be silently left behind and Retained Earnings overstated by it. Re-point the DIVIDENDS_PAID mapping at the account that actually carries the dividends (or consolidate the movement onto it) and close again.',
            self::DIVIDENDS_PAID_CODE,
            count($strandedIds),
            number_format($stranded, 3),
            $year
        )];
    }

    /**
     * Year movement (debit-normal) of a set of accounts, queried the identical way
     * {@see self::buildClosingLines()} queries the sweep itself.
     *
     * @param  list<int>  $accountIds
     */
    private function dividendMovement(array $accountIds, int $year): float
    {
        $totals = DB::table('journal_entries')
            ->whereIn('account_id', $accountIds)
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('COALESCE(posting_date, transaction_date)'), [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ])
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        return (float) $totals->d - (float) $totals->c;
    }

    /**
     * FINAL RE-VERIFICATION (loop 3): the ACTIONABLE half of the blocking message above.
     *
     * The previous wording was a flat "re-run SystemAccountsSeeder" — provably wrong for two of the
     * three states that leave `DIVIDENDS_PAID` unmapped, and they are exactly the two states this
     * guard's own subtree walk exists to catch. {@see \Database\Seeders\SystemAccountsSeeder}
     * ::mapByCode() refuses to map code 3200 when it is DUPLICATED ("ambiguous") or when it has
     * GROWN CHILDREN ("group account, not a leaf"), so re-running the seeder leaves the company
     * unmapped and the very next close refuses again — an endless dead end, reproduced by test.
     * Only the third state (a clean, childless, unique 3200 that the seeder simply never ran over —
     * the pre-T0a installed base) is actually fixed by re-running it.
     *
     * So the message now diagnoses WHICH state this company is in and names the remedy that works.
     */
    private function dividendMappingRemedy(int $companyId): string
    {
        $candidates = DB::table('accounts')
            ->where('company_id', $companyId)
            ->where('code', self::DIVIDENDS_PAID_CODE)
            ->get(['id', 'name']);

        if ($candidates->count() > 1) {
            return sprintf(
                '%d accounts share code %s, so SystemAccountsSeeder skips the mapping as ambiguous and re-running it will NOT help: de-duplicate the code, or map DIVIDENDS_PAID directly onto the account that carries the dividends (ids: %s), then close again.',
                $candidates->count(),
                self::DIVIDENDS_PAID_CODE,
                $candidates->pluck('id')->implode(', ')
            );
        }

        $account = $candidates->first();

        $children = DB::table('accounts')
            ->where('company_id', $companyId)
            ->where('parent_id', $account->id)
            ->get(['id', 'name']);

        if ($children->isNotEmpty()) {
            return sprintf(
                'Code %s is a group account with %d child account(s), so SystemAccountsSeeder skips the mapping ("not a leaf") and re-running it will NOT help: map DIVIDENDS_PAID directly onto the child leaf that carries the dividends (ids: %s), then close again.',
                self::DIVIDENDS_PAID_CODE,
                $children->count(),
                $children->pluck('id')->implode(', ')
            );
        }

        return sprintf(
            'Code %s is a clean unmapped leaf: re-run SystemAccountsSeeder to map DIVIDENDS_PAID onto it, then close again.',
            self::DIVIDENDS_PAID_CODE
        );
    }

    /**
     * The Dividends Paid leaf and every descendant of it, by structural code. Used ONLY by
     * {@see self::checkDividendMappingGap()}'s misconfiguration guard — never to resolve a posting
     * target.
     *
     * @return list<int>
     */
    private function dividendSubtreeAccountIds(int $companyId): array
    {
        $frontier = DB::table('accounts')
            ->where('company_id', $companyId)
            ->where('code', self::DIVIDENDS_PAID_CODE)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $all = $frontier;

        // The COA is shallow (CoaSeeder seeds 3200 at level 2); the bound is a cycle guard, not a
        // depth assumption.
        for ($depth = 0; $depth < 10 && $frontier !== []; $depth++) {
            $frontier = DB::table('accounts')
                ->where('company_id', $companyId)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $all = array_merge($all, $frontier);
        }

        return array_values(array_unique($all));
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

        // accounting-builds T5 (L9): dividend sweep. Dividends Paid (3200, DIVIDENDS_PAID purpose,
        // debit-normal) is closed to Retained Earnings as ADDITIONAL lines inside this SAME YEC
        // document, after the P&L block above — never a separate document. Posting it separately
        // would let TrialBalanceService's whole-document YEC exclusion (keyed off doc_type='YEC',
        // one document at a time) exclude the P&L sweep's movement but NOT this one (or vice
        // versa), reintroducing exactly the same-year self-zeroing bug the exclusion rule exists to
        // prevent — see YearEndCloseReportExclusionTest and this task's own MP-5-2 (adversarial
        // verification: moving this pair to a second document must fail that test).
        //
        // Computed and queried the identical way the P&L leaves above are (COALESCE(posting_date,
        // transaction_date), whereBetween $yearStart/$yearEnd, deleted_at excluded) so it is
        // subject to the exact same period-movement semantics — no separate rule to keep in sync.
        //
        // Deliberately NOT folded into $netProfit (dividends are a DISTRIBUTION of profit, not an
        // expense that reduces it — MP-5-1 pins this: folding it in must fail the net_profit
        // assertion) and NOT merged into the net-profit Retained-Earnings line below — kept as its
        // own explicit self-balancing pair (Cr 3200 / Dr RETAINED_EARNINGS) so a reviewer reading
        // the posted document sees the two effects (P&L sweep vs dividend sweep) as two distinct,
        // separately-labelled pairs on the same document, not one merged number.
        //
        // Adversarial-verification fix (post-T5 commit): DIVIDENDS_PAID must resolve via
        // AccountResolver()->resolve() to LOOK UP the leaf, but that call throws
        // UnmappedPurposeException the moment ANY company has no system_accounts row for the
        // purpose yet — a real, pre-existing state (a company whose registry setup never ran, or
        // ran before T0a introduced this purpose). Pre-T5, a no-activity year for such a company
        // short-circuited cleanly at the (now-later) `$lines === []` check without ever touching
        // the registry; this unconditional resolve() call regressed that into a hard 500
        // (PeriodControllerTest::test_close_year_endpoint_succeeds_as_a_no_op_when_every_month_is_locked_with_no_activity,
        // caught by adversarial verification, not by this task's own fixtures — every T5 fixture
        // runs the full SystemAccountsSeeder, which always maps DIVIDENDS_PAID). An unmapped
        // purpose is treated the same as "leaf has zero movement" — nothing to sweep, not a
        // failure — mirroring how the Income/Expenses loop above naturally no-ops when a company
        // has no matching leaves at all.
        $dividendsAccount = null;
        $dividendBalance = 0.0;

        $dividendsMapped = DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('purpose_code', 'DIVIDENDS_PAID')
            ->whereNull('service_type')
            ->exists();

        if ($dividendsMapped) {
            $dividendsAccount = $this->accountResolver->resolve('DIVIDENDS_PAID', $companyId);

            $dividendTotals = DB::table('journal_entries')
                ->where('account_id', $dividendsAccount->id)
                ->whereNull('deleted_at')
                ->whereBetween(DB::raw('COALESCE(posting_date, transaction_date)'), [$yearStart, $yearEnd])
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();

            // Debit-normal: a positive balance is the ordinary "dividends paid this year" case (Dr
            // 3200). A negative balance (net credits — e.g. a reversed/corrected dividend entry) is
            // handled by the mirrored sweep direction below, same defensive symmetry the Income/
            // Expenses loop above already applies.
            $dividendBalance = (float) $dividendTotals->d - (float) $dividendTotals->c;
        }

        $hasDividendSweep = $dividendsAccount !== null && abs($dividendBalance) > $tolerance;

        if ($lines === [] && ! $hasDividendSweep) {
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

        if ($hasDividendSweep) {
            $dividendAmount = abs($dividendBalance);
            // Zero the leaf: credit it when its own year balance is a normal positive debit
            // (the ordinary "dividends paid" case), mirrored otherwise — same flip convention the
            // Income/Expenses loop above documents in this class's own docblock proof.
            $sweepSide = $dividendBalance > 0 ? 'credit' : 'debit';
            $retainedEarningsSide = $dividendBalance > 0 ? 'debit' : 'credit';

            $lines[] = new LineDraft(
                purposeCode: '',
                accountId: $dividendsAccount->id,
                side: $sweepSide,
                amount: $dividendAmount,
                currency: (string) config('accounting.engine.base_currency', 'KWD'),
                originalAmount: $dividendAmount,
                exchangeRate: 1.0,
                transactionType: 'YEAR_END_CLOSE',
                description: "Year-end sweep of Dividends Paid (code {$dividendsAccount->code}) to Retained Earnings.",
            );
            $lines[] = new LineDraft(
                purposeCode: '',
                accountId: $retainedEarnings->id,
                side: $retainedEarningsSide,
                amount: $dividendAmount,
                currency: (string) config('accounting.engine.base_currency', 'KWD'),
                originalAmount: $dividendAmount,
                exchangeRate: 1.0,
                transactionType: 'YEAR_END_CLOSE',
                description: 'Dividends paid for the fiscal year swept to Retained Earnings.',
            );
        }

        return [$lines, $netProfit];
    }
}
