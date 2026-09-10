<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

use App\Models\Account;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SupplierPayableRule;
use App\Services\Accounting\SupplierReassignDraftBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * CT-A3 wave 2 (W2-1 + W2-4) — the `reassign` class: wave 1's E3 "Update For Whom to Pay"
 * document (`JV`/`PAYEE_REASSIGN`, key `task:{id}:supplier-reassign:{sequence}:{account}`), for
 * every task whose `payment_method_account_id` names an account that is not already carrying its
 * payable.
 *
 * CT-A1 §0 measured the legacy flow at 1,511 documents, KWD 220,908.987 of credits against 477.800
 * of debits, 1,435 of them later neutralised into Equity `3900 Suspense`. This source replays them
 * as balanced two-sided reclassifications.
 *
 * ── W2-4: the R-CT3 re-check ────────────────────────────────────────────────────────────────────
 * Reassignment moves a payable between parties. It must never CREATE one. That is guaranteed twice
 * over, and the belt-and-braces is deliberate:
 *
 *   1. Structurally — {@see SupplierReassignDraftBuilder::buildLines()} derives its debits from
 *      the ledger's CURRENT net credit per AP leaf for the task, so a task whose payable was
 *      gated off by R-CT3 (supplier on hold, trigger not reached, `manual`) has no position to
 *      move and the builder returns an empty array.
 *   2. Explicitly — {@see SupplierPayableRule::decide()} is consulted here and in
 *      `TaskController::postSupplierReassignDocument()`, so the SKIP is reported with the rule's
 *      own reason (`supplier_payable_hold`, `status_not_committed`, …) rather than as an
 *      indistinguishable "nothing to move". Without it, a genuine "the accrual was never posted
 *      because the supplier is on hold" and a benign "already reassigned" look identical in the
 *      run report, and an operator cannot tell a working gate from a broken feeder.
 */
final class ReassignReplaySource implements ReplaySource
{
    use ChecksExistingDocument;

    public function __construct(
        private readonly PostingService $posting,
        private readonly SupplierReassignDraftBuilder $builder,
        private readonly SupplierPayableRule $payableRule,
    ) {}

    public function name(): string
    {
        return 'reassign';
    }

    public function idempotencyKeyFor(Model $row): string
    {
        return 'task:'.$row->getKey().':supplier-reassign:'.$this->sequenceFor($row)
            .':'.(int) $row->payment_method_account_id;
    }

    public function describe(Model $row): string
    {
        return 'task #'.$row->getKey().' -> account #'.$row->payment_method_account_id;
    }

    public function rows(int $companyId, ?Carbon $from, ?Carbon $to, ?int $limit): iterable
    {
        $query = Task::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->whereNotNull('payment_method_account_id')
            ->when($from, fn ($q) => $q->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(supplier_pay_date, issued_date, created_at)'), '>=', $from))
            ->when($to, fn ($q) => $q->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(supplier_pay_date, issued_date, created_at)'), '<=', $to))
            ->orderByRaw('COALESCE(supplier_pay_date, issued_date, created_at)')
            ->orderBy('id')
            ->with(['supplier', 'agent']);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->cursor();
    }

    public function replay(Model $row): ReplayOutcome
    {
        /** @var Task $row */
        $companyId = (int) $row->company_id;
        $amount = round((float) ($row->total ?? 0), 3);

        $destination = Account::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->find($row->payment_method_account_id);

        if ($destination === null) {
            return ReplayOutcome::refused($row->id, 'payment_method_account_not_in_company', null, $amount);
        }

        $supplier = $row->supplier_id ? Supplier::find($row->supplier_id) : null;

        // W2-4. Reported BEFORE the builder runs so a gated-off task is named as gated off, not
        // as "nothing to move" (see class docblock).
        $decision = $this->payableRule->decide($row, $supplier);

        try {
            $lines = $this->builder->buildLines($row, $companyId, $destination, $supplier?->id, $supplier?->name);

            if ($lines === []) {
                return ReplayOutcome::skipped(
                    $row->id,
                    $decision->shouldPost ? 'nothing_to_move' : 'gated_off:'.$decision->reason,
                    $amount
                );
            }

            $key = $this->idempotencyKeyFor($row);
            $existing = $this->existingDocument($companyId, $key);

            if ($existing !== null) {
                return ReplayOutcome::posted($row->id, (int) $existing->id, null, true);
            }

            $docDate = $row->supplier_pay_date ?? $row->issued_date ?? $row->created_at;

            $draft = new DocumentDraft(
                companyId: $companyId,
                branchId: (int) ($row->agent?->branch_id ?? 0),
                docType: 'JV',
                subType: 'PAYEE_REASSIGN',
                docDate: $docDate ? Carbon::parse($docDate) : Carbon::now(),
                narration: 'Update For Whom to Pay: '.$row->reference,
                lines: $lines,
                idempotencyKey: $key,
                sourceType: 'Payment',
                sourceId: $row->id,
            );

            $posted = $this->posting->post($draft);

            return ReplayOutcome::posted($row->id, (int) $posted->transaction->id, $amount);
        } catch (\Throwable $e) {
            return ReplayOutcome::refused($row->id, $e->getMessage(), $e, $amount);
        }
    }

    /**
     * The same sequence `TaskController::postSupplierReassignDocument()` mints, so an A -> B -> A
     * -> B history produces four distinct documents rather than colliding on one key.
     */
    private function sequenceFor(Model $row): int
    {
        return Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', (int) $row->company_id)
            ->where('idempotency_key', 'like', 'task:'.$row->getKey().':supplier-reassign:%')
            ->count();
    }
}
