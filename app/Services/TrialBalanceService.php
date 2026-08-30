<?php

namespace App\Services;

use App\Exceptions\Accounting\CrossTenantAccountException;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrialBalanceService
{
    public function generate(
        int $companyId,
        Carbon $dateFrom,
        Carbon $dateTo,
        array $options = []
    ): array {
        $dateFrom = $dateFrom->startOfDay();
        $dateTo = $dateTo->endOfDay();

        $accounts = $this->getAccountBalances($companyId, $dateFrom, $dateTo, $options);
        $openingBalances = $this->getOpeningBalances($companyId, $dateFrom);

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $totalDebit += (float) $account->total_debit;
            $totalCredit += (float) $account->total_credit;

            if ($openingBalances->has($account->id)) {
                $account->opening_debit = (float) $openingBalances[$account->id]['opening_debit'];
                $account->opening_credit = (float) $openingBalances[$account->id]['opening_credit'];
            } else {
                $account->opening_debit = 0;
                $account->opening_credit = 0;
            }

            $normalBalance = $this->getNormalBalance($account);
            if ($normalBalance === 'debit') {
                $account->closing_balance = ($account->opening_debit - $account->opening_credit) + ($account->total_debit - $account->total_credit);
            } else {
                $account->closing_balance = ($account->opening_credit - $account->opening_debit) + ($account->total_credit - $account->total_debit);
            }
        }

        $grouped = $this->groupByRootCategory($accounts);

        $difference = abs($totalDebit - $totalCredit);

        return [
            'accounts' => $accounts,
            'grouped' => $grouped,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'difference' => $difference,
                'is_balanced' => $difference < 0.001,
            ],
            'opening_balances' => $openingBalances,
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
        ];
    }

    private function getAccountBalances(
        int $companyId,
        Carbon $dateFrom,
        Carbon $dateTo,
        array $options
    ): Collection {
        $query = DB::table('accounts as a')
            ->selectRaw('
                a.id,
                a.code,
                a.name,
                a.root_id,
                a.parent_id,
                a.level,
                root.name AS root_name,
                COALESCE(SUM(je.debit), 0) AS total_debit,
                COALESCE(SUM(je.credit), 0) AS total_credit
            ')
            // Date filter inside JOIN so accounts with zero activity still appear.
            // P2.5.B (p2_5-brief.md §P2.5.B; BUG-C4): filters/groups by posting_date, never
            // created_at — posting_date is the column PeriodGuard's shift resolves to, so a
            // document dated in a closed period but posted (bucketed) into a later one appears in
            // THAT later period's trial balance, not the closed one it was dated for. See
            // tests/Unit/Services/TrialBalanceServicePostingDateTest.php for the "Feb-dated doc
            // entered in March after Feb close" case this exists for.
            //
            // COALESCE(je.posting_date, je.transaction_date), not a bare je.posting_date: this
            // column is nullable and is populated ONLY by PostingService::post() going forward
            // (see that migration's own docblock) — a document written by any of the 131 legacy
            // call sites this build's strangler cutover has not yet migrated (doc 11 §C2) leaves
            // it NULL. A bare posting_date filter would silently make every such legacy-written
            // entry invisible to this report the moment this migration ran — a materially WORSE
            // regression than BUG-C4 itself (an entry counted in the wrong month vs. an entry
            // counted in no month at all). transaction_date is the correct, non-regressive
            // fallback — exactly the same "posting_date, else transaction_date" rule this wave's
            // own migration backfill uses for pre-existing rows, applied here at query time for
            // any row a future legacy writer still produces.
            ->leftJoin('journal_entries as je', function ($join) use ($dateFrom, $dateTo) {
                $join->on('je.account_id', '=', 'a.id')
                    ->whereNull('je.deleted_at');
                if ($dateFrom && $dateTo) {
                    $join->whereBetween(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), [$dateFrom, $dateTo]);
                }
            })
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('a.company_id', $companyId)
            // Leaf accounts only (no children)
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM accounts child WHERE child.parent_id = a.id
            )');

        if (!empty($options['branch_id'])) {
            $query->where(function ($q) use ($options) {
                $q->where('a.branch_id', $options['branch_id'])
                    ->orWhereNull('a.branch_id');
            });
        }

        if (!empty($options['agent_id'])) {
            $query->where(function ($q) use ($options) {
                $q->where('a.agent_id', $options['agent_id'])
                    ->orWhereNull('a.agent_id');
            });
        }

        $accounts = $query->groupBy('a.id', 'a.code', 'a.name', 'a.root_id', 'a.parent_id', 'a.level', 'root.name')
            ->orderBy('a.code')
            ->get();

        if (empty($options['show_zero'])) {
            $accounts = $accounts->filter(function ($account) {
                return (float)$account->total_debit != 0 || (float)$account->total_credit != 0;
            })->values();
        }

        return $accounts;
    }

    /**
     * Sum all journal entries before $dateFrom for each leaf account.
     * Public so other services/reports can reuse this.
     */
    public function getOpeningBalances(
        int $companyId,
        Carbon $dateFrom
    ): Collection {
        $openingEntries = DB::table('accounts as a')
            ->selectRaw('
                a.id,
                COALESCE(SUM(je.debit), 0) AS opening_debit,
                COALESCE(SUM(je.credit), 0) AS opening_credit
            ')
            // P2.5.B: COALESCE(posting_date, transaction_date) — same rationale as
            // getAccountBalances() above.
            ->leftJoin('journal_entries as je', function ($join) use ($dateFrom) {
                $join->on('je.account_id', '=', 'a.id')
                    ->whereNull('je.deleted_at')
                    ->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<', $dateFrom);
            })
            ->where('a.company_id', $companyId)
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM accounts child WHERE child.parent_id = a.id
            )')
            ->groupBy('a.id')
            ->get();

        return $openingEntries->keyBy('id')->map(fn($item) => [
            'opening_debit' => (float) $item->opening_debit,
            'opening_credit' => (float) $item->opening_credit,
        ]);
    }

    private function groupByRootCategory(Collection $accounts): array
    {
        $grouped = [];

        foreach ($accounts as $account) {
            $rootName = $account->root_name;

            if (!isset($grouped[$rootName])) {
                $grouped[$rootName] = [
                    'root_name' => $rootName,
                    'accounts' => [],
                    'subtotal_debit' => 0,
                    'subtotal_credit' => 0,
                ];
            }

            $grouped[$rootName]['accounts'][] = $account;
            $grouped[$rootName]['subtotal_debit'] += (float) $account->total_debit;
            $grouped[$rootName]['subtotal_credit'] += (float) $account->total_credit;
        }

        $order = ['Assets', 'Liabilities', 'Equity', 'Income', 'Expenses'];
        $sorted = [];
        foreach ($order as $rootName) {
            if (isset($grouped[$rootName])) {
                $sorted[$rootName] = $grouped[$rootName];
            }
        }

        return $sorted;
    }

    public function findUnbalancedTransactions(
        int $companyId,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null
    ): Collection {
        // P2.5.B: date range filters/orders by COALESCE(posting_date, transaction_date) — the
        // period this document actually lands in, falling back to its own date for any row a
        // legacy (not-yet-migrated) writer produced with no posting_date — same BUG-C4 rationale
        // as getAccountBalances()/getOpeningBalances() above. t.transaction_date is still
        // SELECTed (and kept in the GROUP BY, since it's a non-aggregated selected column) so a
        // caller can still see the document's own true date alongside the period it was searched
        // under.
        $query = DB::table('transactions as t')
            ->selectRaw('
                t.id,
                t.name,
                t.reference_number,
                t.transaction_date,
                t.posting_date,
                SUM(je.debit) as total_debit,
                SUM(je.credit) as total_credit,
                ABS(SUM(je.debit) - SUM(je.credit)) AS imbalance,
                (SUM(je.debit) - SUM(je.credit)) AS signed_imbalance
            ')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.transaction_id', '=', 't.id')
                    ->whereNull('je.deleted_at');
            })
            ->where('t.company_id', $companyId);

        if ($dateFrom && $dateTo) {
            $query->whereBetween(DB::raw('COALESCE(t.posting_date, t.transaction_date)'), [$dateFrom, $dateTo]);
        }

        return $query->groupBy('t.id', 't.name', 't.reference_number', 't.transaction_date', 't.posting_date')
            ->havingRaw('ABS(SUM(je.debit) - SUM(je.credit)) > 0.001')
            ->orderByRaw('COALESCE(t.posting_date, t.transaction_date) desc')
            ->get();
    }

    /**
     * Ledger-derived CURRENT BALANCE for a single account: its opening
     * balance plus the ledger movement (SUM(debit)/SUM(credit) across all
     * non-deleted journal entries posted to it, no date bound — this is the
     * "as of right now" figure, the same thing `accounts.actual_balance` was
     * hand-maintained to track), signed in the account's own natural
     * (normal-balance) direction. This used to omit opening_balance entirely
     * and return only the movement — see
     * tests/Unit/Services/TrialBalanceServiceLedgerBalanceTest.php for the
     * opening-balance-inclusion regression tests.
     *
     * The normal side (debit vs. credit) is derived FROM THE ACCOUNT ITSELF
     * — via its root account's name, exactly the rule getNormalBalance()
     * uses and CoaController.php's $rootConfig / JournalEntryController's
     * running-balance switch encode independently: Assets & Expenses are
     * debit-normal (balance = opening + SUM(debit) - SUM(credit));
     * Liabilities, Equity & Income are credit-normal
     * (balance = opening + SUM(credit) - SUM(debit)). There used to be a
     * `bool $creditPositive = false` parameter here instead, defaulting to
     * debit-normal — a footgun no production caller ever overrode (grep
     * showed 3 call sites, none passing true), which silently returned the
     * WRONG sign for every credit-normal account it was pointed at. It has
     * been removed; the three call sites below needed no signature change
     * since none of them passed the flag, but two of the accounts they
     * apply the result to (CheckMyFatoorahPayments.php's Liability
     * "Payment Gateway" account, and ClientController's analogous account —
     * see below) are actually credit-normal, and their arithmetic has been
     * corrected to add rather than subtract a credit, per the canonical
     * formula this method now enforces:
     * Migrated call sites, named by symbol and local variable — NOT by line
     * number: every :NNN anchor this docblock used to carry had gone stale
     * within a round of edits, and at least one pointed readers at the exact
     * net-vs-gross line that was the defect under repair. Grep the variable
     * name inside the named method to land on the site.
     *   - CheckMyFatoorahPayments::handle(), $paymentGateway
     *     (Liability — credit-normal; the sign correction above applies here)
     *   - ClientController::addCredit(), $clientAdvancePaymentGateway
     *     (Liability — credit-normal; the sign correction above applies here)
     *   - ClientController::addCredit(), $bankPaymentFee / $bankCOAFee
     *     (Asset / Expense — debit-normal, unaffected)
     *   - CreateClientCredit::processCredit(), $bankPaymentFee / $bankCOAFee
     *     (Asset / Expense — debit-normal, unaffected)
     *   - PaymentController::createInvoicePaymentCOA(), $gatewayAssetAccount /
     *     $gatewayExpenseAccount (Asset / Expense — debit-normal, unaffected)
     *
     * $companyId is required and is NEVER resolved from Auth::user() here —
     * queue workers and gateway webhooks (several of this method's actual
     * callers) have no authenticated user. The caller must already have the
     * company id in scope (it does, at every call site above) and pass it
     * explicitly. The account is loaded scoped to that company id and a
     * CrossTenantAccountException is thrown if it does not belong, rather
     * than silently reading another tenant's ledger or calling abort().
     *
     * Deliberately returns a float (not the accounts.actual_balance
     * decimal(10,2) column) so 3-decimal-place (fils) currency amounts are
     * not truncated — see tests/Unit/Services/TrialBalanceServiceLedgerBalanceTest.php
     * for the case the decimal(10,2) column cannot represent.
     *
     * Accepted consequence, not a bug to fix here: the journal_entries.balance
     * value this powers is user-visible on the running-balance column
     * ReportController selects as `'journal_entries.balance'`. On accounts
     * where actual_balance had already
     * drifted from the ledger (observed on up to 41.5% of City Travelers'
     * accounts), switching a call site's read to this ledger-derived figure
     * shows a one-time discontinuity in that column at the moment that site
     * cuts over — a jump from the old drifted number to the true ledger
     * number. After this build, every migrated writer of journal_entries.balance
     * on a given account agrees with every other migrated writer on the same
     * account (they all call this method), so that jump happens exactly
     * once per account, not on every alternating write the way it did before
     * (see the co-writer sign-convention note below). The remaining
     * discontinuity is therefore historical — rows written before this
     * build, under whichever convention their writer used at the time — not
     * an ongoing one. It is deliberately not smoothed or backfilled by this
     * method.
     *
     * Historical note on the co-writer conflict this fixes: prior to this
     * build, CheckMyFatoorahPayments::handle() wrote
     * `actual_balance - amount` for a CREDIT to the credit-normal "Payment
     * Gateway" liability account, while ClientController::addCredit() wrote
     * `actual_balance + amount` for the same kind of credit to the same
     * account tree — two co-writers of the same column disagreeing on sign
     * for the same operation. That legacy actual_balance arithmetic is left
     * untouched (strangler posture — legacy-mode companies still read it);
     * only the ledger-derived journal_entries.balance value is unified here.
     */
    public function getCurrentAccountBalance(int $companyId, int $accountId): float
    {
        $account = Account::where('id', $accountId)
            ->where('company_id', $companyId)
            ->first();

        if (! $account) {
            throw new CrossTenantAccountException(
                accountId: $accountId,
                accountCompanyId: Account::where('id', $accountId)->value('company_id'),
                expectedCompanyId: $companyId,
                message: "Account #{$accountId} does not belong to company #{$companyId} (or does not exist)."
            );
        }

        $totals = DB::table('journal_entries')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $totalDebit = (float) $totals->total_debit;
        $totalCredit = (float) $totals->total_credit;
        $openingBalance = (float) ($account->opening_balance ?? 0);

        $movement = $this->resolveAccountNormalSide($account, $companyId) === 'debit'
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;

        return $openingBalance + $movement;
    }

    /**
     * Same debit/credit-normal rule as getNormalBalance() below (Assets &
     * Expenses = debit; Liabilities, Equity & Income = credit), but derived
     * from a live Account model + its own root_id rather than from a
     * pre-joined query row's `root_name` column — getCurrentAccountBalance()
     * above has only the single Account it was asked about, not a
     * TrialBalanceService::getAccountBalances() result set.
     *
     * A root account itself (parent_id === null — Assets/Liabilities/Equity/
     * Income/Expenses, per AccountService::FIXED_ROOT_NAMES) has no root_id
     * of its own (AccountService::resolveRoot() leaves it null), so its own
     * name IS the root name. Every other account's root name is read off the
     * account row root_id points at, scoped to the same $companyId — never
     * via the root_id() Eloquent relation, which would run through Account's
     * BelongsToCompany global scope and could silently miss the row for an
     * authenticated user resolved to a different company than $companyId.
     */
    private function resolveAccountNormalSide(Account $account, int $companyId): string
    {
        if ($account->parent_id === null) {
            $rootName = $account->name;
        } else {
            $rootName = Account::where('id', $account->root_id)
                ->where('company_id', $companyId)
                ->value('name');

            if ($rootName === null) {
                throw new \RuntimeException(
                    "Account #{$account->id} has parent_id={$account->parent_id} but its root_id="
                    .($account->root_id !== null ? (string) $account->root_id : 'null')
                    ." does not resolve to a root account in company #{$companyId} — the tree above it is corrupt."
                );
            }
        }

        if (! in_array($rootName, ['Assets', 'Expenses', 'Liabilities', 'Equity', 'Income'], true)) {
            // Falls through to 'credit' below for any root name that is not
            // one of the five canonical roots — e.g. a typo'd or custom root
            // name. That fallback is a silent guess with real bookkeeping
            // consequences (wrong sign on every balance derived from it), so
            // it must be logged rather than passing unnoticed. Deliberately
            // NOT a throw here: unlike the corrupt-tree case above (parent_id
            // set, root_id unresolvable — a broken tree), an unrecognized but
            // otherwise valid root name is a data-quality issue the caller
            // may still need a best-effort balance for.
            Log::warning('TrialBalanceService::resolveAccountNormalSide() root name not recognized, defaulting to credit-normal', [
                'company_id' => $companyId,
                'account_id' => $account->id,
                'root_name' => $rootName,
            ]);
        }

        return in_array($rootName, ['Assets', 'Expenses'], true) ? 'debit' : 'credit';
    }

    /**
     * Assets & Expenses = 'debit'; Liabilities, Income, Equity = 'credit'
     */
    private function getNormalBalance(object $account): string
    {
        return in_array($account->root_name, ['Assets', 'Expenses']) ? 'debit' : 'credit';
    }

    public function formatCurrency(float $amount): string
    {
        return number_format($amount, 3, '.', ',');
    }
}
