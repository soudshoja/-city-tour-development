<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Company;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P2.5.D (p2_5-brief.md §P2.5.D; doc 22 §15.6, IFRS 15) — releases an `at_travel` service's
 * deferred sale on its travel/check-in date. Driven by `App\Console\Commands\RecognizeRevenue`
 * (`accounting:recognize-revenue`); the underlying computation is also used directly by
 * {@see DeferredRevenueScheduleReport}.
 *
 * ── Ledger-derived, not a second schedule table ───────────────────────────────────────────────────
 * This class deliberately does NOT persist its own "what is pending release" table. Every amount
 * it needs to release is already sitting on the `DEFERRED_REVENUE` / `PREPAID_SUPPLIER_COST`
 * leaves {@see SaleDraftBuilder} posted at sale time, tagged with `journal_entries.task_id` — the
 * SAME "derive from the ledger, never a second mutable source of truth" principle this build's
 * `actual_balance` DROP decision and BUG-C4 fix both already apply (doc 11 Appendix C note 2;
 * p2_5-brief.md §P2.5.B). Consequences:
 *   - REFUND/VOID BEFORE RELEASE needs no special handling here. Reversing the whole sale document
 *     via the existing {@see PostingService::reverse()} posts a mirror-image document against the
 *     SAME deferred/prepaid accounts — {@see self::outstandingByTask()} sums every line on those
 *     accounts for the task regardless of which document posted it, so the original credit and the
 *     reversal's debit net to exactly zero and the task simply stops appearing outstanding, with no
 *     schedule row to separately cancel (see that method's own docblock note on why it does NOT
 *     filter by `transactions.posting_status`).
 *   - IDEMPOTENCY is the ordinary engine mechanism: {@see self::release()} always attempts
 *     `PostingService::post()` with idempotency key `recognize:{task_id}` — a second call for the
 *     same task returns the SAME already-posted document (see `PostingService::post()`'s own step
 *     1), never double-posts, and this class never needs its own "already released" flag.
 *
 * ── Global leaves, never resolved by name ─────────────────────────────────────────────────────────
 * `DEFERRED_REVENUE` / `PREPAID_SUPPLIER_COST` are resolved once per company via
 * {@see AccountResolver::resolve()} (purpose code, never `Account::where('name', ...)`) — a
 * company with neither leaf mapped (i.e. `accounting:ensure-system-leaves` has not run for it, or
 * `SystemAccountsSeeder` never seeded it) simply has nothing to recognise; caught and reported,
 * never guessed onto an unrelated account.
 */
final class RevenueRecognitionService
{
    private const IDEMPOTENCY_PREFIX = 'recognize:';

    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly PostingService $posting,
    ) {}

    /**
     * Every task with an outstanding (posted, un-released) deferred balance for this company —
     * regardless of whether its travel_date has arrived yet. Used by
     * {@see DeferredRevenueScheduleReport} (the whole schedule, grouped by release month) and by
     * {@see self::findDue()} (which additionally filters on the date).
     *
     * @return array<int, array{
     *     task_id: int, task: Task, service_type: string, revenue_amount: float,
     *     revenue_side: string, cost_amount: float, cost_side: string, branch_id: int,
     *     invoice_id: ?int, invoice_detail_id: ?int, travel_date: ?Carbon,
     * }>
     */
    public function outstandingByTask(int $companyId): array
    {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        $deferredRevenueAccountId = $this->resolveOptionalAccountId('DEFERRED_REVENUE', $companyId);
        if ($deferredRevenueAccountId === null) {
            // Nothing this company could ever have deferred — see class docblock. Not an error:
            // a company that has never turned on an `at_travel` service type simply never mapped
            // this leaf.
            return [];
        }
        $prepaidCostAccountId = $this->resolveOptionalAccountId('PREPAID_SUPPLIER_COST', $companyId);

        $accountIds = array_values(array_filter([$deferredRevenueAccountId, $prepaidCostAccountId]));

        // NOTE: deliberately NOT filtered on t.posting_status = 'posted'. A reversed sale's
        // original lines (posting_status flipped to 'reversed' by PostingService::reverse()) and
        // its reversal document's own lines (posting_status = 'posted', same account_ids,
        // opposite sides — see PostingService::reverse()'s own docblock) are BOTH included here on
        // purpose: they net to exactly zero by construction (that is what a reversal IS), which is
        // what correctly drops a refunded/voided task out of `outstandingByTask()` — filtering out
        // only the original's now-'reversed' transaction while still counting the reversal's own
        // 'posted' lines would count HALF of a cancelled pair and misreport a large fake balance.
        // Engine-native posting_status values are only ever 'posted' or 'reversed' (verified
        // against PostingService's own writes) — there is no 'draft'/'void' row to worry about
        // excluding here.
        $rows = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereIn('je.account_id', $accountIds)
            ->whereNotNull('je.task_id')
            ->select(['je.task_id', 'je.account_id', 'je.debit', 'je.credit', 'je.branch_id', 'je.invoice_id', 'je.invoice_detail_id'])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $byTask = [];
        foreach ($rows as $row) {
            $taskId = (int) $row->task_id;
            $byTask[$taskId] ??= [
                'revenue_debit' => 0.0, 'revenue_credit' => 0.0,
                'cost_debit' => 0.0, 'cost_credit' => 0.0,
                'branch_id' => (int) ($row->branch_id ?? 0),
                'invoice_id' => $row->invoice_id !== null ? (int) $row->invoice_id : null,
                'invoice_detail_id' => $row->invoice_detail_id !== null ? (int) $row->invoice_detail_id : null,
            ];

            if ((int) $row->account_id === $deferredRevenueAccountId) {
                $byTask[$taskId]['revenue_debit'] += (float) $row->debit;
                $byTask[$taskId]['revenue_credit'] += (float) $row->credit;
            } elseif ($prepaidCostAccountId !== null && (int) $row->account_id === $prepaidCostAccountId) {
                $byTask[$taskId]['cost_debit'] += (float) $row->debit;
                $byTask[$taskId]['cost_credit'] += (float) $row->credit;
            }
        }

        $taskIds = array_keys($byTask);
        /** @var array<int, Task> $tasks */
        $tasks = Task::withoutGlobalScopes()
            ->whereIn('id', $taskIds)
            ->get()
            ->keyBy('id')
            ->all();

        $result = [];
        foreach ($byTask as $taskId => $sums) {
            $task = $tasks[$taskId] ?? null;
            if ($task === null) {
                // Orphaned line (task hard-deleted after the sale posted) — report and skip rather
                // than crash the whole run.
                Log::warning('accounting.revenue_recognition_task_missing', ['task_id' => $taskId, 'company_id' => $companyId]);

                continue;
            }

            $revenueNet = $sums['revenue_credit'] - $sums['revenue_debit'];
            $costNet = $sums['cost_debit'] - $sums['cost_credit'];

            if (abs($revenueNet) <= $tolerance && abs($costNet) <= $tolerance) {
                // Already released (or never carried a real balance) — nothing outstanding.
                continue;
            }

            $result[$taskId] = [
                'task_id' => $taskId,
                'task' => $task,
                'service_type' => (string) $task->type,
                'revenue_amount' => round(abs($revenueNet), 3),
                'revenue_side' => $revenueNet >= 0 ? 'credit' : 'debit',
                'cost_amount' => round(abs($costNet), 3),
                'cost_side' => $costNet >= 0 ? 'debit' : 'credit',
                'branch_id' => $sums['branch_id'],
                'invoice_id' => $sums['invoice_id'],
                'invoice_detail_id' => $sums['invoice_detail_id'],
                'travel_date' => $task->travel_date !== null ? Carbon::parse($task->travel_date) : null,
            ];
        }

        return $result;
    }

    /**
     * P2.5.D fix (verify finding) — lightweight per-task check for
     * {@see \App\Services\Accounting\SupplierCostCorrectionDraftBuilder}'s caller
     * ({@see \App\Http\Controllers\TaskController::updateAdminFinancial()}): true when this task
     * still carries an un-released deferred balance on `DEFERRED_REVENUE`/`PREPAID_SUPPLIER_COST`
     * (i.e. its sale has NOT yet been recognised by {@see self::release()}/`accounting:
     * recognize-revenue`) — the caller passes `alreadyRecognized: ! isDeferredOutstanding(...)`
     * into {@see \App\Services\Accounting\SupplierCostCorrectionInput}. Scoped to one task (unlike
     * {@see self::outstandingByTask()}, which scans the whole company) so a single admin-financial
     * correction does not pay for a company-wide query.
     */
    public function isDeferredOutstanding(int $companyId, int $taskId): bool
    {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        $deferredRevenueAccountId = $this->resolveOptionalAccountId('DEFERRED_REVENUE', $companyId);
        if ($deferredRevenueAccountId === null) {
            return false;
        }
        $prepaidCostAccountId = $this->resolveOptionalAccountId('PREPAID_SUPPLIER_COST', $companyId);

        $accountIds = array_values(array_filter([$deferredRevenueAccountId, $prepaidCostAccountId]));

        // Same "do not filter by posting_status" reasoning as outstandingByTask() — a reversed
        // sale's original lines and its reversal's own lines net to exactly zero by construction.
        $rows = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('je.company_id', $companyId)
            ->where('je.task_id', $taskId)
            ->whereNull('je.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereIn('je.account_id', $accountIds)
            ->select(['je.account_id', 'je.debit', 'je.credit'])
            ->get();

        if ($rows->isEmpty()) {
            return false;
        }

        $revenueNet = 0.0;
        $costNet = 0.0;
        foreach ($rows as $row) {
            if ((int) $row->account_id === $deferredRevenueAccountId) {
                $revenueNet += (float) $row->credit - (float) $row->debit;
            } elseif ($prepaidCostAccountId !== null && (int) $row->account_id === $prepaidCostAccountId) {
                $costNet += (float) $row->debit - (float) $row->credit;
            }
        }

        return abs($revenueNet) > $tolerance || abs($costNet) > $tolerance;
    }

    /**
     * {@see self::outstandingByTask()}, filtered to tasks whose `travel_date` is set and has
     * arrived on or before `$asOf`. A task with no `travel_date` never becomes due — see the
     * `tasks.travel_date` migration's own docblock; it is reported by
     * {@see DeferredRevenueScheduleReport} as "date pending", not silently skipped forever without
     * visibility.
     *
     * @return array<int, array>
     */
    public function findDue(int $companyId, \DateTimeInterface $asOf): array
    {
        $asOfDate = Carbon::instance($asOf)->endOfDay();

        return array_filter(
            $this->outstandingByTask($companyId),
            static fn (array $row): bool => $row['travel_date'] !== null && $row['travel_date']->lessThanOrEqualTo($asOfDate)
        );
    }

    /**
     * Release one task's deferred revenue/cost. Idempotent (idempotency key
     * `recognize:{task_id}`) — a repeat call for the same task returns the already-posted document
     * via `PostingService::post()`'s own step-1 lookup, never double-posts.
     *
     * Returns null when there is nothing outstanding for this task (already released, or never had
     * a real deferred balance) — NOT an error; the caller (the command, or a direct test) checks
     * `outstandingByTask()`/`findDue()` first for anything that needs reporting.
     */
    public function release(int $companyId, int $taskId, ?int $userId = null): ?PostedDocument
    {
        $outstanding = $this->outstandingByTask($companyId);
        $row = $outstanding[$taskId] ?? null;

        if ($row === null) {
            return null;
        }

        return $this->postRelease($companyId, $row, $userId);
    }

    /**
     * @param array{task_id:int, task:Task, service_type:string, revenue_amount:float,
     *              revenue_side:string, cost_amount:float, cost_side:string, branch_id:int,
     *              invoice_id:?int, invoice_detail_id:?int, travel_date:?Carbon} $row
     */
    private function postRelease(int $companyId, array $row, ?int $userId): PostedDocument
    {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $currency = (string) config('accounting.engine.base_currency');
        $taskId = $row['task_id'];
        $task = $row['task'];
        $reference = (string) ($task->reference ?? "#{$taskId}");

        $lines = [];

        if ($row['revenue_amount'] > $tolerance) {
            $deferredSide = $row['revenue_side'] === 'credit' ? 'debit' : 'credit';
            $revenueSide = $row['revenue_side'] === 'credit' ? 'credit' : 'debit';

            $lines[] = new LineDraft(
                purposeCode: 'DEFERRED_REVENUE',
                accountId: null,
                side: $deferredSide,
                amount: $row['revenue_amount'],
                currency: $currency,
                originalAmount: $row['revenue_amount'],
                exchangeRate: 1.0,
                transactionType: 'REVENUE_RECOGNITION',
                description: 'Revenue recognition release for '.$reference,
                invoiceId: $row['invoice_id'],
                invoiceDetailId: $row['invoice_detail_id'],
                taskId: $taskId,
            );

            $lines[] = new LineDraft(
                purposeCode: 'SERVICE_REVENUE',
                accountId: null,
                side: $revenueSide,
                amount: $row['revenue_amount'],
                currency: $currency,
                originalAmount: $row['revenue_amount'],
                exchangeRate: 1.0,
                transactionType: 'REVENUE_RECOGNITION',
                description: 'Revenue recognised on travel for '.$reference,
                serviceType: $row['service_type'],
                invoiceId: $row['invoice_id'],
                invoiceDetailId: $row['invoice_detail_id'],
                taskId: $taskId,
                ledgerType: 'income',
            );
        }

        if ($row['cost_amount'] > $tolerance) {
            $prepaidSide = $row['cost_side'] === 'debit' ? 'credit' : 'debit';
            $costSide = $row['cost_side'] === 'debit' ? 'debit' : 'credit';

            $lines[] = new LineDraft(
                purposeCode: 'PREPAID_SUPPLIER_COST',
                accountId: null,
                side: $prepaidSide,
                amount: $row['cost_amount'],
                currency: $currency,
                originalAmount: $row['cost_amount'],
                exchangeRate: 1.0,
                transactionType: 'REVENUE_RECOGNITION',
                description: 'Prepaid supplier cost release for '.$reference,
                invoiceId: $row['invoice_id'],
                invoiceDetailId: $row['invoice_detail_id'],
                taskId: $taskId,
            );

            $lines[] = new LineDraft(
                purposeCode: 'SERVICE_COST',
                accountId: null,
                side: $costSide,
                amount: $row['cost_amount'],
                currency: $currency,
                originalAmount: $row['cost_amount'],
                exchangeRate: 1.0,
                transactionType: 'REVENUE_RECOGNITION',
                description: 'Cost of sales recognised on travel for '.$reference,
                serviceType: $row['service_type'],
                invoiceId: $row['invoice_id'],
                invoiceDetailId: $row['invoice_detail_id'],
                taskId: $taskId,
                ledgerType: 'expense',
            );
        }

        $docDate = $row['travel_date'] ?? Carbon::now();

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: $row['branch_id'],
            docType: 'JV',
            subType: null,
            docDate: $docDate,
            narration: 'Revenue recognition release for task '.$reference,
            lines: $lines,
            idempotencyKey: self::IDEMPOTENCY_PREFIX.$taskId,
            userId: $userId,
        );

        return $this->posting->post($draft, $userId);
    }

    /**
     * @return array{
     *     processed: int, released: int[], not_due: int[], no_balance: int[], errors: array<int, string>,
     * }
     */
    public function run(?int $companyId, \DateTimeInterface $asOf, bool $dryRun = false, ?int $userId = null): array
    {
        $summary = ['processed' => 0, 'released' => [], 'not_due' => [], 'no_balance' => [], 'errors' => []];

        $companies = $companyId !== null
            ? Company::query()->where('id', $companyId)->get(['id'])
            : Company::query()->where('posting_engine_enabled', true)->get(['id']);

        foreach ($companies as $company) {
            $due = $this->findDue((int) $company->id, $asOf);

            foreach ($due as $taskId => $row) {
                $summary['processed']++;

                if ($dryRun) {
                    $summary['released'][] = $taskId;

                    continue;
                }

                try {
                    $posted = $this->postRelease((int) $company->id, $row, $userId);
                    $summary['released'][] = $taskId;
                    AccountingLog::event('revenue_recognized', [
                        'company_id' => $company->id,
                        'task_id' => $taskId,
                        'transaction_id' => $posted->transaction->id,
                        'revenue_amount' => $row['revenue_amount'],
                        'cost_amount' => $row['cost_amount'],
                    ]);
                } catch (\Throwable $e) {
                    $summary['errors'][$taskId] = $e->getMessage();
                    Log::error('accounting.revenue_recognition_failed', [
                        'company_id' => $company->id,
                        'task_id' => $taskId,
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $summary;
    }

    private function resolveOptionalAccountId(string $purposeCode, int $companyId): ?int
    {
        try {
            return $this->accounts->resolve($purposeCode, $companyId)->id;
        } catch (UnmappedPurposeException $e) {
            AccountingLog::event('revenue_recognition_leaf_unmapped', [
                'company_id' => $companyId,
                'purpose_code' => $purposeCode,
            ]);

            return null;
        }
    }
}
