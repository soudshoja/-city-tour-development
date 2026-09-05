<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\BankStatementImportLine;
use App\Models\IdempotencyKeyRejection;
use App\Models\Transaction;
use App\Services\TrialBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C; period-lock-design.md §5): the "close checklist gate" behind
 * `accounting:period:close`/the period-control screen — the month-end account treatment table the
 * brief specifies per account class:
 *
 *   (a) bank/cash/gateway-clearing/cheques — reconciled item-by-item; WARN on unmatched, NEVER block.
 *   (b) control accounts (AR/AP/agent leaves) — sub-ledger sum of open items must equal the control
 *       balance; BLOCK on mismatch.
 *   (c) clearing/roll-forward accounts (2632, 1952, 2202, ...) — roll-forward report with
 *       exceptions; WARN, never block.
 *   (d) income/expense — analytical review only (variance vs. prior period); no gate at all.
 *
 * Plus the three document-level gates the brief lists ahead of the account-class table: every
 * document in the month balanced, no draft-status document dated in the month, no unresolved seam
 * (idempotency-key) failure logged in the month. All four of THESE are BLOCKING.
 *
 * Read-only: this service never writes. Same "return a structured array, not console text or an
 * exception" convention {@see RvPvInvariantChecker}/{@see \App\Console\Commands\AccountingVerify}
 * already establish for command-backed checker logic in this codebase — directly unit-testable
 * against its return value.
 *
 * ── (b)'s operationalisation, given P5.3 (the real open-item/apply engine) has not shipped ───────
 * There is no subsidiary ledger of "open items per client/supplier/agent" to sum yet — no
 * `settled_amount` writer exists (the column is present, migrated ahead of schedule, but nothing
 * populates it — see that migration's own docblock). What DOES already exist on every engine-posted
 * line is `journal_entries.type_reference_id` (the per-line party attribution `LineDraft::
 * $partyAccountRef` writes — see that class's own W1.1 FIX ROUND note). Grouping a control leaf's
 * own rows by `type_reference_id` and summing each group reproduces the leaf's own total by
 * construction (grouping never changes a sum) — UNLESS some rows carry no party attribution at all
 * (`type_reference_id IS NULL`), in which case that portion of the control balance belongs to no
 * party's sub-ledger and the "sub-ledger sum == control balance" equality genuinely fails. This
 * class treats that failure mode — money sitting on RECEIVABLE_CONTROL/PAYABLE_CONTROL with no
 * traceable party — as the mismatch the brief's (b) blocks on, reported as `unattributed_net`. This
 * is not a placeholder for P5.3's real open-item apply/settle math; it is the honest thing this
 * codebase's CURRENT data model can prove about (b) today, and it is a real, meaningful invariant
 * (an untraceable line on a control account is always worth blocking a close over).
 */
final class PeriodCloseChecklistService
{
    public function __construct(private readonly AccountResolver $accountResolver) {}

    /**
     * @return array{
     *     company_id: int, year: int, month: int,
     *     period_start: string, period_end: string,
     *     can_close: bool,
     *     blocking: list<array{code: string, message: string, meta: array}>,
     *     warnings: list<array{code: string, message: string, meta: array}>,
     *     sections: array{
     *         bank_cash: array,
     *         control_accounts: array,
     *         clearing_rollforward: array,
     *         income_expense: array,
     *     },
     * }
     */
    public function run(int $companyId, int $year, int $month): array
    {
        [$periodStart, $periodEnd] = $this->resolvePeriodBounds($year, $month);

        $blocking = [];
        $warnings = [];

        $blocking = array_merge($blocking, $this->checkDocumentsBalanced($companyId, $periodStart, $periodEnd));
        $blocking = array_merge($blocking, $this->checkNoDraftDocuments($companyId, $periodStart, $periodEnd));
        $blocking = array_merge($blocking, $this->checkNoSeamFailures($companyId, $periodStart, $periodEnd));

        $controlSection = $this->checkControlAccounts($companyId, $periodEnd);
        $blocking = array_merge($blocking, $controlSection['blocking']);

        $bankCashSection = $this->checkBankCashReconciliation($companyId, $periodStart, $periodEnd);
        $warnings = array_merge($warnings, $bankCashSection['warnings']);

        $rollforwardSection = $this->checkClearingRollforward($companyId, $periodStart, $periodEnd);
        $warnings = array_merge($warnings, $rollforwardSection['warnings']);

        $incomeExpenseSection = $this->incomeExpenseVariance($companyId, $periodStart, $periodEnd);

        return [
            'company_id' => $companyId,
            'year' => $year,
            'month' => $month,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'can_close' => $blocking === [],
            'blocking' => $blocking,
            'warnings' => $warnings,
            'sections' => [
                'bank_cash' => $bankCashSection['accounts'],
                'control_accounts' => $controlSection['accounts'],
                'clearing_rollforward' => $rollforwardSection['accounts'],
                'income_expense' => $incomeExpenseSection,
            ],
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolvePeriodBounds(int $year, int $month): array
    {
        $isAnnual = (string) config('accounting.period.length', 'monthly') === 'annual'
            || $month === AccountingPeriod::ANNUAL_MONTH;

        if ($isAnnual) {
            return [Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay()];
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()->endOfDay()];
    }

    private function tolerance(): float
    {
        return (float) config('accounting.engine.balance_tolerance', 0.0005);
    }

    /** @return list<array{code: string, message: string, meta: array}> */
    private function checkDocumentsBalanced(int $companyId, Carbon $start, Carbon $end): array
    {
        /** @var TrialBalanceService $trialBalance */
        $trialBalance = app(TrialBalanceService::class);
        $unbalanced = $trialBalance->findUnbalancedTransactions($companyId, $start, $end);

        if ($unbalanced->isEmpty()) {
            return [];
        }

        return [[
            'code' => 'documents_unbalanced',
            'message' => sprintf('%d document(s) in this period are not balanced (Σdebit ≠ Σcredit).', $unbalanced->count()),
            'meta' => ['transaction_ids' => $unbalanced->pluck('id')->all()],
        ]];
    }

    /** @return list<array{code: string, message: string, meta: array}> */
    private function checkNoDraftDocuments(int $companyId, Carbon $start, Carbon $end): array
    {
        $drafts = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('posting_status', 'draft')
            ->whereBetween(DB::raw('COALESCE(posting_date, transaction_date)'), [$start, $end])
            ->get(['id']);

        if ($drafts->isEmpty()) {
            return [];
        }

        return [[
            'code' => 'draft_documents_in_period',
            'message' => sprintf('%d draft document(s) are dated inside this period.', $drafts->count()),
            'meta' => ['transaction_ids' => $drafts->pluck('id')->all()],
        ]];
    }

    /** @return list<array{code: string, message: string, meta: array}> */
    private function checkNoSeamFailures(int $companyId, Carbon $start, Carbon $end): array
    {
        $failures = IdempotencyKeyRejection::where('company_id', $companyId)
            ->where('resolution_status', 'open')
            ->whereBetween('created_at', [$start, $end])
            ->get(['id']);

        if ($failures->isEmpty()) {
            return [];
        }

        return [[
            'code' => 'seam_failures_in_period',
            'message' => sprintf('%d unresolved posting-engine seam failure(s) were logged in this period.', $failures->count()),
            'meta' => ['idempotency_key_rejection_ids' => $failures->pluck('id')->all()],
        ]];
    }

    /**
     * (b) — control accounts. BLOCKING.
     *
     * @return array{blocking: list<array>, accounts: list<array>}
     */
    private function checkControlAccounts(int $companyId, Carbon $periodEnd): array
    {
        $blocking = [];
        $accounts = [];

        // P2.5.G verify fix: the (purpose_code/anchor -> leaf ids) resolution itself now lives in
        // ONE place ({@see AccountResolver::controlAccountGroups()}), shared with
        // ReconciliationCenterService::controlRows() — see that method's own docblock. This class
        // still owns everything downstream of the account set (the control_balance/
        // unattributed_net computation and the BLOCK decision), which legitimately stays this
        // class's own — only WHICH accounts count as "control" was ever at risk of drifting.
        foreach ($this->accountResolver->controlAccountGroups($companyId) as $group) {
            if ($group['account_ids'] === []) {
                $accounts[] = ['purpose_code' => $group['purpose_code'], 'label' => $group['label'], 'status' => 'not_configured'];

                continue;
            }

            [$row, $blockingItem] = $this->controlAccountRow($group['purpose_code'], $group['label'], $group['account_ids'], $periodEnd);
            $accounts[] = $row;
            if ($blockingItem !== null) {
                $blocking[] = $blockingItem;
            }
        }

        return ['blocking' => $blocking, 'accounts' => $accounts];
    }

    /**
     * @param  int[]  $leafIds  one leaf (a resolve()'d control code) or several (an anchor
     *                          group's minted per-agent children)
     * @return array{0: array, 1: ?array}
     */
    private function controlAccountRow(string $purposeCode, string $label, array $leafIds, Carbon $periodEnd): array
    {
        $totals = DB::table('journal_entries')
            ->whereIn('account_id', $leafIds)
            ->whereNull('deleted_at')
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $periodEnd)
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        $controlBalance = (float) $totals->d - (float) $totals->c;

        $unattributed = DB::table('journal_entries')
            ->whereIn('account_id', $leafIds)
            ->whereNull('deleted_at')
            ->whereNull('type_reference_id')
            ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $periodEnd)
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        $unattributedNet = (float) $unattributed->d - (float) $unattributed->c;
        $mismatch = abs($unattributedNet) > $this->tolerance();

        $row = [
            'purpose_code' => $purposeCode,
            'label' => $label,
            'account_ids' => $leafIds,
            'control_balance' => $controlBalance,
            'unattributed_net' => $unattributedNet,
            'status' => $mismatch ? 'mismatch' : 'ok',
        ];

        $blockingItem = $mismatch ? [
            'code' => 'control_account_mismatch',
            'message' => sprintf(
                '%s (%s): %s of the control balance cannot be traced to a party sub-ledger entry.',
                $label,
                $purposeCode,
                number_format($unattributedNet, 3)
            ),
            'meta' => ['purpose_code' => $purposeCode, 'account_ids' => $leafIds, 'unattributed_net' => $unattributedNet],
        ] : null;

        return [$row, $blockingItem];
    }

    /**
     * (a) — bank/cash/gateway-clearing/cheques. WARN-only, NEVER blocks.
     *
     * @return array{warnings: list<array>, accounts: list<array>}
     */
    private function checkBankCashReconciliation(int $companyId, Carbon $start, Carbon $end): array
    {
        // P2.5.G verify fix: the leaf-id resolution itself now lives in ONE place
        // ({@see AccountResolver::bankCashLeafIds()}), shared with
        // ReconciliationCenterService::bankCashRows() — see that method's own docblock. It still
        // walks the SAME sanctioned AccountResolver::isCashOrBankLeaf() classification (its own
        // docblock: "Report/invariant code ... should call THIS method ... never re-derive the
        // same classification"), plus the same instrument-code/gateway leaves this check always
        // included — only the "who computes the leaf-id list" question changes here.
        $leafIds = $this->accountResolver->bankCashLeafIds($companyId);

        $warnings = [];
        $accounts = [];

        foreach ($leafIds as $accountId) {
            $account = Account::withoutGlobalScopes()->find($accountId);
            if ($account === null) {
                continue;
            }

            $unreconciled = DB::table('journal_entries')
                ->where('account_id', $accountId)
                ->whereNull('deleted_at')
                ->where('reconciled', 0)
                ->whereBetween(DB::raw('COALESCE(posting_date, transaction_date)'), [$start, $end])
                ->where(fn ($q) => $q->where('debit', '!=', 0)->orWhere('credit', '!=', 0))
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
                ->first();

            $count = (int) $unreconciled->cnt;

            $accounts[] = [
                'account_id' => $accountId,
                'code' => $account->code,
                'name' => $account->name,
                'unreconciled_count' => $count,
                'unreconciled_net' => (float) $unreconciled->net,
                'status' => $count > 0 ? 'unreconciled' : 'ok',
            ];

            if ($count > 0) {
                $warnings[] = [
                    'code' => 'unreconciled_bank_cash_lines',
                    'message' => sprintf('%s (code %s) has %d unreconciled line(s) in this period.', $account->name, $account->code, $count),
                    'meta' => ['account_id' => $accountId, 'count' => $count],
                ];
            }
        }

        // accounting-builds T9 (Wave 2): "PeriodCloseChecklistService::checkBankCashReconciliation
        // gains a WARN row 'N statement lines unmatched'." A statement-side gap (a bank statement
        // line that never matched a ledger line, or a disputed amount) is exactly as WARN-worthy
        // as the book-side gap this check already reports — same (a) class, same "WARN, never
        // block" rule. Scoped to THIS company's bank leaves and this period only (a statement
        // whose `bank_account_id` is not one of $leafIds — a different company's leaf, or a
        // non-bank/cash leaf — never contributes, mirroring the per-leaf loop above).
        $unmatchedStatementCount = $leafIds === [] ? 0 : (int) BankStatementImportLine::whereIn('state', [
            BankStatementImportLine::STATE_UNMATCHED,
            BankStatementImportLine::STATE_DISPUTED,
        ])
            ->whereHas('import', fn ($q) => $q->whereIn('bank_account_id', $leafIds))
            ->whereBetween('value_date', [$start, $end])
            ->count();

        if ($unmatchedStatementCount > 0) {
            $warnings[] = [
                'code' => 'unmatched_bank_statement_lines',
                'message' => sprintf('%d bank statement line(s) unmatched in this period.', $unmatchedStatementCount),
                'meta' => ['count' => $unmatchedStatementCount, 'account_ids' => $leafIds],
            ];
        }

        return ['warnings' => $warnings, 'accounts' => $accounts];
    }

    /**
     * (c) — clearing / roll-forward accounts. WARN-only, NEVER blocks.
     *
     * @return array{warnings: list<array>, accounts: list<array>}
     */
    private function checkClearingRollforward(int $companyId, Carbon $start, Carbon $end): array
    {
        $warnings = [];
        $accounts = [];

        foreach (config('accounting.period_close.clearing_rollforward_codes', []) as $code => $label) {
            $account = Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->first();

            if ($account === null) {
                $accounts[] = ['code' => $code, 'label' => $label, 'status' => 'not_configured'];

                continue;
            }

            $opening = DB::table('journal_entries')
                ->where('account_id', $account->id)
                ->whereNull('deleted_at')
                ->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<', $start)
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();
            $openingBalance = (float) $opening->d - (float) $opening->c;

            $period = DB::table('journal_entries')
                ->where('account_id', $account->id)
                ->whereNull('deleted_at')
                ->whereBetween(DB::raw('COALESCE(posting_date, transaction_date)'), [$start, $end])
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();

            $periodDebit = (float) $period->d;
            $periodCredit = (float) $period->c;
            $closingBalance = $openingBalance + $periodDebit - $periodCredit;

            // "Exception": the balance GREW rather than cleared toward zero during the period —
            // the opposite of what a clearing/roll-forward account is meant to do. Purely
            // informational (design doc §5: reconciliation/roll-forward never gates a monthly
            // close) — see class docblock.
            $isException = abs($closingBalance) > (abs($openingBalance) + $this->tolerance())
                && abs($closingBalance) > $this->tolerance();

            $accounts[] = [
                'code' => $code,
                'label' => $label,
                'account_id' => $account->id,
                'name' => $account->name,
                'opening_balance' => $openingBalance,
                'period_debit' => $periodDebit,
                'period_credit' => $periodCredit,
                'closing_balance' => $closingBalance,
                'exception' => $isException,
            ];

            if ($isException) {
                $warnings[] = [
                    'code' => 'clearing_rollforward_exception',
                    'message' => sprintf(
                        '%s (code %s) grew from %s to %s this period instead of clearing down.',
                        $label,
                        $code,
                        number_format($openingBalance, 3),
                        number_format($closingBalance, 3)
                    ),
                    'meta' => ['account_id' => $account->id, 'opening_balance' => $openingBalance, 'closing_balance' => $closingBalance],
                ];
            }

            // $code is a config array KEY -- PHP casts a numeric-string array key ('1952') to a
            // real int, so this must compare as strings on both sides, not identity (===) on
            // whatever type each side happens to be.
            if ((string) $code === (string) config('accounting.period_close.airline_memo_control_code') && abs($closingBalance) > $this->tolerance()) {
                $warnings[] = [
                    'code' => 'airline_memo_control_nonzero',
                    'message' => sprintf(
                        'Airline Memo Control (code %s) has a non-zero balance of %s — undispositioned memos remain (blocks year-end close, warns at month-end).',
                        $code,
                        number_format($closingBalance, 3)
                    ),
                    'meta' => ['account_id' => $account->id, 'closing_balance' => $closingBalance],
                ];
            }
        }

        return ['warnings' => $warnings, 'accounts' => $accounts];
    }

    /**
     * (d) — income/expense analytical review. NEVER gates (no blocking, no warning) — informational
     * only, per the brief's own text ("no gate").
     *
     * @return list<array{root: string, this_period: float, prior_period: float, variance: float, variance_pct: ?float}>
     */
    private function incomeExpenseVariance(int $companyId, Carbon $start, Carbon $end): array
    {
        $priorLength = $start->diffInDays($end) + 1;
        $priorStart = $start->copy()->subDays($priorLength);
        $priorEnd = $start->copy()->subDay()->endOfDay();

        $rows = [];

        foreach (['Income', 'Expenses'] as $rootName) {
            $thisPeriod = $this->rootMovement($companyId, $rootName, $start, $end);
            $priorPeriod = $this->rootMovement($companyId, $rootName, $priorStart, $priorEnd);
            $variance = $thisPeriod - $priorPeriod;

            $rows[] = [
                'root' => $rootName,
                'this_period' => $thisPeriod,
                'prior_period' => $priorPeriod,
                'variance' => $variance,
                'variance_pct' => abs($priorPeriod) > $this->tolerance() ? round(($variance / abs($priorPeriod)) * 100, 2) : null,
            ];
        }

        return $rows;
    }

    private function rootMovement(int $companyId, string $rootName, Carbon $start, Carbon $end): float
    {
        $isDebitNormal = $rootName === 'Expenses';

        $totals = DB::table('journal_entries as je')
            ->join('accounts as a', 'je.account_id', '=', 'a.id')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('a.company_id', $companyId)
            ->where('root.name', $rootName)
            ->whereNull('je.deleted_at')
            ->whereBetween(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), [$start, $end])
            ->selectRaw('COALESCE(SUM(je.debit),0) as d, COALESCE(SUM(je.credit),0) as c')
            ->first();

        $debit = (float) $totals->d;
        $credit = (float) $totals->c;

        return $isDebitNormal ? $debit - $credit : $credit - $debit;
    }
}
