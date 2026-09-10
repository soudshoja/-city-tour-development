<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * CT-A6-3 — per-account general ledger (opening balance, every posted line inside a period with a
 * running balance, closing balance) for the `reports/general-ledger` screen. Ported from Akeed's
 * `App\Services\GeneralLedgerService` (LP6a; PR #75) with two deliberate CT-specific departures:
 *
 * 1. **One ledger source, not one ledger.** Unlike Akeed, this codebase's engine and legacy
 *    postings share the SAME `journal_entries` table (CT-D1B's "no separate engine table"
 *    finding) — so every query here is additionally scoped by {@see LedgerSource::restrict()},
 *    which is what makes `1430 Unbilled Supplier Cost` (a leaf CT-D1b found mixing both sources,
 *    reporting no single coherent balance) show ONE coherent balance per company mode instead.
 * 2. **`journal_entries.type_reference_id` is a PARTY id (client/supplier/agent), never an
 *    account id** — verified against `LineDraft::$partyAccountRef`'s own docblock and
 *    `AccountingController::filterLedgers()`'s `case 'client'/'supplier'/'agent':
 *    $ledgersQuery->where('type_reference_id', $relatedId)`. The Akeed reference joins this
 *    column back to `accounts` as a "counter account"; doing that here would silently match a
 *    supplier/client id against an unrelated account row that happens to share the same numeric
 *    id. This port therefore surfaces `type_reference_id` as a bare party reference (labelled
 *    "Party ref" in the view) rather than resolving a name — CT has no single party table
 *    (clients/suppliers/agents are three separate models) and PLAN.md §0 CT-A6 forbids reading
 *    `journal_entries.type` (a column CT-A1 proved unusable), which is the only column that could
 *    otherwise disambiguate which of the three tables a given id belongs to.
 *
 * Every other parity definition mirrors {@see \App\Services\TrialBalanceService} deliberately, so
 * a general ledger for account X foots to the SAME closing balance the trial balance shows for X
 * over the same period:
 *   - **Date basis: `COALESCE(posting_date, transaction_date)`** — same P2.5.B/BUG-C4 rationale.
 *   - **`deleted_at IS NULL`** on the journal line.
 *   - **Opening balance: ALL history strictly before the period, YEC included** — exactly
 *     {@see \App\Services\TrialBalanceService::getOpeningBalances()}'s own rule.
 *   - **Period movement: whole-document exclusion of `doc_type = 'YEC'` only** — exactly
 *     {@see \App\Services\TrialBalanceService::getAccountBalances()}'s own exclusion, not a
 *     stricter one — a YEC's zeroing lines are never real trading activity for the account they
 *     touch inside a movement window, but any other document (including an opening journal dated
 *     at the very start of the period) is real ledger history for whatever account it posted to.
 *   - **Running balance computed in SQL** (`SUM(...) OVER (ORDER BY ...)`) rather than accumulated
 *     in a PHP loop, so paginating tens of thousands of lines never materialises them all in
 *     memory at once.
 *
 * Read-only: this class never writes to `journal_entries` or `transactions`.
 */
final class GeneralLedgerService
{
    public function __construct(private readonly ?LedgerSource $ledgerSource = null)
    {
    }

    private function ledgerSource(): LedgerSource
    {
        return $this->ledgerSource ?? app(LedgerSource::class);
    }

    /**
     * @return array{
     *     account: object{id:int,code:string,name:string,root_name:string},
     *     normal_side: string,
     *     opening_balance: float,
     *     lines: LengthAwarePaginator,
     *     totals: array{debit: float, credit: float, closing_balance: float},
     *     period: array{from: string, to: string},
     *     engine_on: bool
     * }
     */
    public function generate(
        int $companyId,
        int $accountId,
        Carbon $dateFrom,
        Carbon $dateTo,
        int $perPage = 50
    ): array {
        $dateFrom = $dateFrom->copy()->startOfDay();
        $dateTo = $dateTo->copy()->endOfDay();

        $account = $this->resolveAccount($companyId, $accountId);
        $normalSide = $this->resolveNormalSide($companyId, $account);
        $engineOn = $this->ledgerSource()->engineOn($companyId);

        $opening = $this->openingBalance($companyId, $accountId, $dateFrom, $normalSide);

        $totals = $this->periodTotals($companyId, $accountId, $dateFrom, $dateTo);
        $closingBalance = $opening + ($normalSide === 'debit'
            ? $totals['debit'] - $totals['credit']
            : $totals['credit'] - $totals['debit']);

        $lines = $this->periodLines($companyId, $accountId, $dateFrom, $dateTo, $normalSide, $opening, $perPage);

        return [
            'account' => $account,
            'normal_side' => $normalSide,
            'opening_balance' => $opening,
            'lines' => $lines,
            'totals' => [
                'debit' => $totals['debit'],
                'credit' => $totals['credit'],
                'closing_balance' => $closingBalance,
            ],
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'engine_on' => $engineOn,
        ];
    }

    /**
     * @return object{id:int,code:string,name:string,root_id:?int,root_name:string}
     */
    private function resolveAccount(int $companyId, int $accountId): object
    {
        $account = DB::table('accounts as a')
            ->leftJoin('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('a.company_id', $companyId)
            ->where('a.id', $accountId)
            ->selectRaw('a.id, a.code, a.name, a.root_id, COALESCE(root.name, a.name) as root_name')
            ->first();

        if (! $account) {
            throw new RuntimeException("Account #{$accountId} not found for company #{$companyId}.");
        }

        return $account;
    }

    /**
     * Same debit/credit-normal rule as {@see \App\Services\TrialBalanceService::
     * resolveAccountNormalSide()}: Assets & Expenses are debit-normal, Liabilities/Equity/Income
     * are credit-normal. An unrecognised root name falls through to credit-normal (a logged,
     * non-fatal guess) rather than aborting the whole ledger render.
     */
    private function resolveNormalSide(int $companyId, object $account): string
    {
        $rootName = (string) $account->root_name;

        if (! in_array($rootName, ['Assets', 'Expenses', 'Liabilities', 'Equity', 'Income'], true)) {
            Log::warning('GeneralLedgerService::resolveNormalSide() root name not recognized, defaulting to credit-normal', [
                'company_id' => $companyId,
                'account_id' => $account->id,
                'root_name' => $rootName,
            ]);
        }

        return in_array($rootName, ['Assets', 'Expenses'], true) ? 'debit' : 'credit';
    }

    /**
     * ALL journal lines strictly before $dateFrom, YEC included, restricted to this company's
     * current ledger source (see class docblock) — signed in the account's own normal direction.
     */
    private function openingBalance(int $companyId, int $accountId, Carbon $dateFrom, string $normalSide): float
    {
        $query = DB::table('journal_entries as je')
            ->where('je.account_id', $accountId)
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<', $dateFrom);

        $this->ledgerSource()->restrict($query, $companyId, 'je.transaction_id');

        $row = $query->selectRaw('COALESCE(SUM(je.debit), 0) as debit, COALESCE(SUM(je.credit), 0) as credit')->first();

        $debit = (float) $row->debit;
        $credit = (float) $row->credit;

        return $normalSide === 'debit' ? $debit - $credit : $credit - $debit;
    }

    /**
     * The shared FROM/WHERE every period query below uses — one place for the YEC exclusion and
     * the ledger-source restriction so the lines list and the totals can never disagree with each
     * other. `transactions` is INNER JOINed here (every period line this method returns has a
     * transaction header — an orphan legacy line with no header at all cannot carry a doc_type and
     * is, by the class docblock's discriminator, indistinguishable from "not this company's
     * current source" in engine mode; in legacy mode it is included via
     * {@see LedgerSource::restrictJoinedTransactions()}'s NULL branch only when a header exists —
     * a headerless orphan line is out of scope for a per-account ledger view either way, same as
     * it already is for {@see \App\Services\TrialBalanceService::findUnbalancedTransactions()}).
     */
    private function periodBaseQuery(int $companyId, int $accountId, Carbon $dateFrom, Carbon $dateTo): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.account_id', $accountId)
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereBetween(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), [$dateFrom, $dateTo])
            ->where(function ($q) {
                $q->whereNull('t.doc_type')->orWhere('t.doc_type', '<>', 'YEC');
            });

        return $this->ledgerSource()->restrictJoinedTransactions($query, $companyId, 't.doc_type');
    }

    /**
     * @return array{debit: float, credit: float}
     */
    private function periodTotals(int $companyId, int $accountId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $row = $this->periodBaseQuery($companyId, $accountId, $dateFrom, $dateTo)
            ->selectRaw('COALESCE(SUM(je.debit), 0) as debit, COALESCE(SUM(je.credit), 0) as credit')
            ->first();

        return ['debit' => (float) $row->debit, 'credit' => (float) $row->credit];
    }

    /**
     * Paginated period lines with a SQL-computed running balance (opening balance + a window-
     * function cumulative sum), the document's type/subtype/reference/idempotency key, and the
     * line's bare party reference (`journal_entries.type_reference_id` — see class docblock for
     * why this is never resolved to a name here).
     */
    private function periodLines(
        int $companyId,
        int $accountId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $normalSide,
        float $openingBalance,
        int $perPage
    ): LengthAwarePaginator {
        $deltaExpr = $normalSide === 'debit' ? '(je.debit - je.credit)' : '(je.credit - je.debit)';

        $query = $this->periodBaseQuery($companyId, $accountId, $dateFrom, $dateTo)
            ->selectRaw("
                je.id,
                COALESCE(je.posting_date, je.transaction_date) as entry_date,
                je.debit,
                je.credit,
                je.description,
                je.voucher_number,
                je.type_reference_id as party_ref,
                t.doc_type,
                t.sub_type,
                t.reference_number,
                t.idempotency_key,
                SUM({$deltaExpr}) OVER (ORDER BY COALESCE(je.posting_date, je.transaction_date), je.id) as running_delta
            ")
            ->orderBy(DB::raw('COALESCE(je.posting_date, je.transaction_date)'))
            ->orderBy('je.id');

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function ($row) use ($openingBalance) {
            $row->running_balance = $openingBalance + (float) $row->running_delta;

            return $row;
        });

        return $paginator;
    }
}
