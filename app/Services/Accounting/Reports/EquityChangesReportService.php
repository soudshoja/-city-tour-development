<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Account;
use App\Services\Accounting\AccountResolver;
use App\Services\TrialBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * accounting-builds T6 (L10): Statement of Changes in Equity — a pure READ-layer report. Every
 * figure is derived from {@see TrialBalanceService}'s own two primitives:
 *   - {@see TrialBalanceService::getOpeningBalances()} for "opening equity" (sum of all history
 *     strictly before the year starts, per leaf — no exclusion, same convention every opening
 *     balance in this system already uses).
 *   - {@see TrialBalanceService::generate()} for the year's period movement — which ALREADY
 *     carries the YEC whole-document exclusion (see that method's own docblock): a `doc_type=
 *     'YEC'` document's lines never count as movement for the year it was posted in, no matter
 *     how the range is drawn. This service adds NO exclusion logic of its own; it inherits
 *     TrialBalanceService's, exactly as L10 requires ("reuses the YEC exclusion").
 *
 * No engine write anywhere in this file (no `PostingSeam`, no `LineDraft`, no `DocumentDraft`) —
 * this is a report, not a feeder. Never reads `accounts.actual_balance` or
 * `journal_entries.balance` (see {@see \Tests\Feature\Accounting\LedgerDerivedBalanceCallSitesTest}'s
 * own rationale and this task's MP-6-2) — only `journal_entries.debit`/`.credit`, via
 * TrialBalanceService's own queries.
 *
 * ── The four equity components (08-akeed-as-built.md deviation 3: "5 COA roots, not 6") ─────────
 * Capital Stock (3100), Opening Balance Equity (3300), Retained Earnings (3400, `RETAINED_EARNINGS`
 * purpose) and Dividends Paid (3200, `DIVIDENDS_PAID` purpose, debit-normal contra). 3100/3300 have
 * no registered purpose code (nothing posts to them via the engine today; they exist as fixed,
 * as-built leaf CODES, not a per-company-variable mapping) — resolved by CODE the same way
 * {@see \App\Services\Accounting\YearEndCloseService::buildClosingLines()} already resolves its own
 * P&L leaves directly from `accounts`, i.e. reading a well-known structural code is the established
 * pattern for THIS class of leaf, distinct from the purpose-code registry that exists for accounts a
 * FEEDER needs to resolve per company/service-type. 3200/3400 ARE registered purposes (T0a/T5) and
 * are resolved through {@see AccountResolver} here for consistency with the engine's own posting
 * side, even though this is a read path — falling back to their own structural codes for a company
 * that has no mapping for them yet, so the report degrades instead of 500-ing on exactly the
 * misconfigured company whose un-swept dividends it is the only place to see (see
 * {@see self::resolvePurposeOrCode()}).
 *
 * ── The roll-forward formula (per component) ──────────────────────────────────────────────────
 *   closing = opening + movement                                          (Capital, OBE — direct
 *                                                                           leaf movement only)
 *   closing (Retained Earnings, PRO-FORMA) = opening + net profit for the year − dividends paid
 *                                                                          this year
 * `net profit` = Income root movement − Expense root movement for the year (YEC-excluded, reused
 * from `generate()`'s own grouped subtotals — the SAME figure `YearEndCloseService` would compute
 * and sweep if this year were closed right now, not a separately re-derived number).
 * `dividends paid this year` = the DIVIDENDS_PAID leaf's own period movement (YEC-excluded, so
 * this is always the REAL dividend payment for the year, never the YEC's own zeroing credit —
 * whether or not the year has actually been closed).
 *
 * The Retained Earnings component is deliberately PRO-FORMA: it always folds this year's net
 * profit and dividends in, exactly as the year-end close WOULD if run today — see
 * {@see self::generate()}'s own `checks` block for what this means for the tie-out to the ledger's
 * true next-year opening balance:
 *
 *   - AFTER this year has actually been closed (a `YEC` document exists — {@see
 *     \App\Services\Accounting\YearEndCloseService}), the pro-forma figure and the real ledger
 *     agree exactly: `checks.ties_to_next_year_opening` is TRUE. The sweep moved net profit and
 *     dividends into Retained Earnings and zeroed the Dividends Paid leaf — precisely what this
 *     component already assumed.
 *   - BEFORE this year is closed, the real ledger has NOT yet moved net profit into Retained
 *     Earnings (it still sits in the Income/Expense leaves, outside the Equity root entirely) and
 *     the Dividends Paid leaf still carries its own real (unswept) balance. The pro-forma figure
 *     therefore differs from `getOpeningBalances()` at next-year-start by EXACTLY this year's net
 *     profit — `checks.ties_to_next_year_opening` is FALSE whenever net profit is non-zero, TRUE
 *     whenever it happens to be zero. This is not a bug: a real balance sheet at any date DOES
 *     fold in the period's profit as a presentation matter (blueprint ref 05 §3: "folds in the
 *     period profit") even though the LEDGER itself only truly moves it at year-end close — the
 *     `checks` block reports both numbers so a reader can see which regime they are in, never
 *     silently picks one.
 *
 * `checks.ties_to_ledger_derivation` is a SECOND, independently-derived proof of the same closing
 * total: `Σ TrialBalanceService::generate()`'s own per-leaf `closing_balance` (opening_credit −
 * opening_debit + total_credit − total_debit, the SAME arithmetic that method already performs for
 * every other report) across the four equity leaves, plus net profit. Algebraically identical to
 * the component roll-forward above by construction, but computed through a DIFFERENT code path
 * (TrialBalanceService's own merged opening+movement fields rather than this service's own
 * `getOpeningBalances()` + `generate()` combination) — a real regression guard, not a tautology
 * dressed up as one; see MP-6-2's proof in the test suite for how the ladder actually exercises it.
 */
final class EquityChangesReportService
{
    public const CODE_CAPITAL = '3100';

    public const CODE_OPENING_BALANCE_EQUITY = '3300';

    public const CODE_DIVIDENDS_PAID = '3200';

    public const CODE_RETAINED_EARNINGS = '3400';

    public function __construct(
        private readonly TrialBalanceService $trialBalance,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * @return array{
     *     company_id: int, year: int,
     *     components: array<string, array{code: string, name: string, opening: float, movement: float, closing: float}>,
     *     net_profit: float,
     *     opening_equity_total: float,
     *     closing_equity_total: float,
     *     checks: array{
     *         ties_to_next_year_opening: bool, next_year_opening_total: float, difference: float,
     *         ties_to_ledger_derivation: bool, ledger_derivation_total: float, ledger_difference: float,
     *     },
     * }
     */
    public function generate(int $companyId, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();
        $nextYearStart = Carbon::create($year + 1, 1, 1)->startOfDay();
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        $capital = $this->resolveLeafByCode($companyId, self::CODE_CAPITAL, 'Capital Stock');
        $obe = $this->resolveLeafByCode($companyId, self::CODE_OPENING_BALANCE_EQUITY, 'Opening Balance Equity');
        $dividends = $this->resolvePurposeOrCode($companyId, 'DIVIDENDS_PAID', self::CODE_DIVIDENDS_PAID, 'Dividends Paid');
        $retainedEarnings = $this->resolvePurposeOrCode($companyId, 'RETAINED_EARNINGS', self::CODE_RETAINED_EARNINGS, 'Retained Earnings');

        $opening = $this->trialBalance->getOpeningBalances($companyId, $yearStart);
        $nextYearOpening = $this->trialBalance->getOpeningBalances($companyId, $nextYearStart);

        $openingCapital = $this->netCredit($capital->id, $opening);
        $openingObe = $this->netCredit($obe->id, $opening);
        $openingDividends = $this->netCredit($dividends->id, $opening);
        $openingRe = $this->netCredit($retainedEarnings->id, $opening);
        $openingTotal = $openingCapital + $openingObe + $openingDividends + $openingRe;

        $movementReport = $this->trialBalance->generate($companyId, $yearStart, $yearEnd, ['show_zero' => true]);
        $accountsById = collect($movementReport['accounts'])->keyBy('id');

        $capitalMovement = $this->netMovement($capital->id, $accountsById);
        $obeMovement = $this->netMovement($obe->id, $accountsById);
        // Debit-normal leaf read via the credit-normal (credit − debit) formula: a real dividend
        // payment (Dr 3200) yields a NEGATIVE value here — "dividends paid this year" as a positive
        // figure is therefore -$dividendsMovement; kept signed (not flipped) so it composes
        // directly into the additive roll-forward below without a second sign convention.
        $dividendsMovement = $this->netMovement($dividends->id, $accountsById);
        $reMovement = $this->netMovement($retainedEarnings->id, $accountsById);

        $income = $movementReport['grouped']['Income'] ?? ['subtotal_debit' => 0.0, 'subtotal_credit' => 0.0];
        $expenses = $movementReport['grouped']['Expenses'] ?? ['subtotal_debit' => 0.0, 'subtotal_credit' => 0.0];
        $netProfit = ((float) $income['subtotal_credit'] - (float) $income['subtotal_debit'])
            - ((float) $expenses['subtotal_debit'] - (float) $expenses['subtotal_credit']);

        $closingCapital = $openingCapital + $capitalMovement;
        $closingObe = $openingObe + $obeMovement;
        // Pro-forma: opening RE + this year's real RE movement (always ~0 pre-close, YEC-excluded
        // even post-close — see class docblock) + net profit − dividends paid, i.e. what RE WOULD
        // read the moment this year is closed.
        $closingRetainedEarnings = $openingRe + $reMovement + $netProfit + $dividendsMovement;

        $closingTotal = $closingCapital + $closingObe + $closingRetainedEarnings;

        // Wave 3 lane I item A2 (T5/T6 §12 sign-off finding): the Dividends Paid row's presented
        // Closing must NOT be the raw unswept leaf balance (`$openingDividends + $dividendsMovement`).
        // $closingRetainedEarnings above already folds `$dividendsMovement` in as part of the SAME
        // pro-forma "as if closed today" assumption the class docblock documents at length for RE —
        // showing the raw unswept Dividends Paid balance ALONGSIDE that pro-forma RE figure double-
        // counts the dividend movement, so Σ(every row's closing) != closing_equity_total whenever a
        // dividend moved this year. The fix is symmetric with RE's own treatment, not a special case:
        // exactly as RE's presented Closing assumes this year's close has already run, the Dividends
        // Paid leaf's presented Closing must assume the SAME pro-forma close already swept it to
        // zero (`Dr Retained Earnings / Cr Dividends Paid`, the real entry YearEndCloseService posts)
        // — so it is always presented as 0.0, regardless of the raw unswept balance. This is the
        // "dividends row closing = 0 after a pro-forma sweep" presentation (chosen over adding a
        // brand-new "explicit transfer" row/component, which would change this report's fixed
        // 4-component shape for no numeric benefit — the two are algebraically identical, this one
        // is the minimal diff and mirrors the RE row's own already-established pro-forma-fold-in
        // convention, including its own informational footnote row in the Blade view). The Movement
        // column is deliberately left UNCHANGED (still the real period dividend payment,
        // `$dividendsMovement`) — exactly as RE's own Movement column is left as the real, tiny,
        // YEC-excluded `$reMovement` rather than being inflated to match its pro-forma Closing; ANY
        // individual row's Opening+Movement=Closing was never an invariant this report enforced
        // (RE's row does not satisfy it either, pre- or post-close, on purpose) — see the class
        // docblock. What per-column FOOTING to the grand total actually requires (and what the
        // regression test below pins) is that summing each column DOWN across all four rows
        // reproduces `opening_equity_total` / `closing_equity_total` — never that a single row foots
        // internally.
        $closingDividendsPresented = 0.0;

        $nextYearOpeningTotal = $this->netCredit($capital->id, $nextYearOpening)
            + $this->netCredit($obe->id, $nextYearOpening)
            + $this->netCredit($dividends->id, $nextYearOpening)
            + $this->netCredit($retainedEarnings->id, $nextYearOpening);

        $difference = $closingTotal - $nextYearOpeningTotal;

        // Independent second derivation (class docblock's "ties_to_ledger_derivation" note): sum
        // of TrialBalanceService::generate()'s OWN per-leaf closing_balance (its opening_credit/
        // opening_debit/total_credit/total_debit fields, not this service's getOpeningBalances()
        // call) across the four equity leaves, plus net profit.
        $ledgerClosingSum = $this->closingBalance($capital->id, $accountsById)
            + $this->closingBalance($obe->id, $accountsById)
            + $this->closingBalance($dividends->id, $accountsById)
            + $this->closingBalance($retainedEarnings->id, $accountsById);
        $ledgerDerivationTotal = $ledgerClosingSum + $netProfit;
        $ledgerDifference = $closingTotal - $ledgerDerivationTotal;

        return [
            'company_id' => $companyId,
            'year' => $year,
            'components' => [
                'capital' => ['code' => $capital->code, 'name' => $capital->name, 'opening' => $openingCapital, 'movement' => $capitalMovement, 'closing' => $closingCapital],
                'opening_balance_equity' => ['code' => $obe->code, 'name' => $obe->name, 'opening' => $openingObe, 'movement' => $obeMovement, 'closing' => $closingObe],
                'retained_earnings' => ['code' => $retainedEarnings->code, 'name' => $retainedEarnings->name, 'opening' => $openingRe, 'movement' => $reMovement, 'closing' => $closingRetainedEarnings],
                'dividends_paid' => ['code' => $dividends->code, 'name' => $dividends->name, 'opening' => $openingDividends, 'movement' => $dividendsMovement, 'closing' => $closingDividendsPresented],
            ],
            'net_profit' => $netProfit,
            'dividends_paid_this_year' => -$dividendsMovement,
            'opening_equity_total' => $openingTotal,
            'closing_equity_total' => $closingTotal,
            'checks' => [
                'ties_to_next_year_opening' => abs($difference) <= $tolerance,
                'next_year_opening_total' => $nextYearOpeningTotal,
                'difference' => $difference,
                'ties_to_ledger_derivation' => abs($ledgerDifference) <= $tolerance,
                'ledger_derivation_total' => $ledgerDerivationTotal,
                'ledger_difference' => $ledgerDifference,
            ],
        ];
    }

    /**
     * @param  Collection<int, array{opening_debit: float, opening_credit: float}>  $openingBalances
     */
    private function netCredit(int $accountId, Collection $openingBalances): float
    {
        $row = $openingBalances[$accountId] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];

        return (float) $row['opening_credit'] - (float) $row['opening_debit'];
    }

    /**
     * @param  Collection<int, object>  $accountsById  TrialBalanceService::generate()'s 'accounts' collection, keyed by account id.
     */
    private function netMovement(int $accountId, Collection $accountsById): float
    {
        if (! $accountsById->has($accountId)) {
            return 0.0;
        }

        $row = $accountsById[$accountId];

        return (float) $row->total_credit - (float) $row->total_debit;
    }

    /**
     * @param  Collection<int, object>  $accountsById
     */
    private function closingBalance(int $accountId, Collection $accountsById): float
    {
        if (! $accountsById->has($accountId)) {
            return 0.0;
        }

        return (float) $accountsById[$accountId]->closing_balance;
    }

    /**
     * POST-FIX RE-VERIFICATION (accounting-builds T5/T6 review §10): resolve a registered purpose,
     * falling back to the leaf's own structural code when the company has no mapping for it.
     *
     * A company with no `DIVIDENDS_PAID` mapping is a real state (the purpose only arrived with
     * this phase's T0a; {@see \Database\Seeders\SystemAccountsSeeder}::mapByCode() also skips-and-
     * reports whenever code 3200 is absent, duplicated, or has grown children). The write path
     * REFUSES to close for such a company once real money sits on the leaf
     * ({@see \App\Services\Accounting\YearEndCloseService::checkDividendMappingGap()}) — but a
     * READ-layer report must not hard-crash (`UnmappedPurposeException` → HTTP 500) on it: this
     * statement is the one screen where the operator can SEE the un-swept dividends the guard is
     * complaining about. Degrading to the structural code shows the real figure instead of a 500
     * (and instead of a silent zero, which would be worse than either).
     *
     * The write/read asymmetry is deliberate: a posting target must never be guessed, a reported
     * figure must never be invented — reading a fixed, as-built code is not a guess, it is the
     * same accepted read-layer treatment 3100/3300 already get above.
     */
    private function resolvePurposeOrCode(int $companyId, string $purposeCode, string $code, string $label): Account
    {
        try {
            return $this->accountResolver->resolve($purposeCode, $companyId);
        } catch (UnmappedPurposeException) {
            return $this->resolveLeafByCode($companyId, $code, $label);
        }
    }

    private function resolveLeafByCode(int $companyId, string $code, string $label): Account
    {
        $account = Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->first();

        if ($account === null) {
            throw new \RuntimeException("Equity leaf {$label} (code {$code}) not found for company #{$companyId} — CoaSeeder must have seeded it.");
        }

        return $account;
    }
}
