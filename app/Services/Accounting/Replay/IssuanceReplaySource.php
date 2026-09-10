<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

use App\Models\Task;
use App\Services\Accounting\TaskIssuancePayableService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * CT-A3 wave 2 (W2-1) — the `issuance` class: wave 1's E-iss supplier-payable accrual
 * (`JV`/`SUPPLIER_ACCRUAL`, key `task:{id}:issuance-payable`), driven through
 * {@see TaskIssuancePayableService::postIfDue()} — the very method
 * {@see \App\Services\TaskStatusService::dispatchFinancial()} calls, so R-CT3's supplier-status
 * gate is exercised exactly as it is in production rather than re-implemented here.
 *
 * The reason a task did NOT accrue comes from
 * {@see TaskIssuancePayableService::reasonFor()} — the same single implementation `postIfDue()`
 * itself consults — so the command's "NOT_DUE by reason" table and the feeder's own log can never
 * disagree. On the City Travelers population that table is the R-CT3 ruling made visible: 424
 * `confirmed` tasks reported as `status_not_committed`, carrying the KWD 21,542.960 of revenue
 * CT-A1 §3.3 found on the legacy ledger and that the engine deliberately does not accrue.
 */
final class IssuanceReplaySource implements ReplaySource
{
    use ChecksExistingDocument;

    public function __construct(private readonly TaskIssuancePayableService $issuance) {}

    public function name(): string
    {
        return 'issuance';
    }

    public function idempotencyKeyFor(Model $row): string
    {
        return TaskIssuancePayableService::idempotencyKeyFor((int) $row->getKey());
    }

    public function describe(Model $row): string
    {
        return 'task #'.$row->getKey().' ('.$row->status.')';
    }

    public function rows(int $companyId, ?Carbon $from, ?Carbon $to, ?int $limit): iterable
    {
        $query = Task::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(issued_date, supplier_pay_date, created_at)'), '>=', $from))
            ->when($to, fn ($q) => $q->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(issued_date, supplier_pay_date, created_at)'), '<=', $to))
            ->orderByRaw('COALESCE(issued_date, supplier_pay_date, created_at)')
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
        $amount = round((float) ($row->total ?? 0), 3);

        // `postIfDue()` returns a PostedDocument in TWO cases that look identical from here: it
        // really posted, or PostingService's step-1 idempotency short-circuit handed back the
        // document that was already there. Without this check a second run reported all 5,676
        // accruals as freshly POSTED and the "a re-run posts 0" line was a lie about work the
        // engine had correctly refused to redo.
        $existing = $this->existingDocument((int) $row->company_id, $this->idempotencyKeyFor($row));

        if ($existing !== null) {
            return ReplayOutcome::posted($row->id, (int) $existing->id, null, true);
        }

        try {
            $reason = $this->issuance->reasonFor($row);
            $posted = $this->issuance->postIfDue($row);

            if ($posted !== null) {
                return ReplayOutcome::posted($row->id, (int) $posted->transaction->id, $amount);
            }

            return ReplayOutcome::skipped($row->id, $reason, $amount);
        } catch (\Throwable $e) {
            return ReplayOutcome::refused($row->id, $e->getMessage(), $e, $amount);
        }
    }
}
