<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

use App\Models\Refund;
use App\Models\Transaction;
use App\Services\Accounting\RefundPostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * CT-A3 wave 2 (W2-1 + W2-3) — the `refund` class, driven through
 * {@see RefundPostingService::post()}: the same entry point `RefundController` uses, so the CRN,
 * the recharge, the supplier credit item, the commission un-earn, the clawback and the
 * disposition are composed by the one implementation and W2-3's supplier-recovery gate applies to
 * a replay exactly as it does live.
 *
 * `RefundPostingService::post()` refuses a refund that is not past `approved` (its own status
 * guard, w4-brief.md's draft -> approved -> posted -> completed workflow) — that refusal is
 * reported here as a skip with the refund's status, not as an error, because a backfill walking
 * history will legitimately meet draft and rejected rows.
 *
 * CT-A1 §2.1 counted all 33 `refunds` rows with `posted_at` NULL and no `transaction_id` column at
 * all: the refund document table has never had a ledger link. This class is how it gets one.
 */
final class RefundReplaySource implements ReplaySource
{
    use ChecksExistingDocument;

    public function __construct(private readonly RefundPostingService $refunds) {}

    public function name(): string
    {
        return 'refund';
    }

    /**
     * A refund emits SEVEN documents, each with its own key (`refund:{id}:crn-legacy:{detail}`,
     * `:supplier-credit:{detail}`, `:recharge`, `:clawback`, `:disposition`, plus the reversals of
     * the sale and the commission, which are keyed off the ORIGINAL documents). There is no single
     * key for the refund as a whole; the representative one is the disposition, which every
     * posting refund emits.
     */
    public function idempotencyKeyFor(Model $row): string
    {
        return 'refund:'.$row->getKey().':disposition';
    }

    public function describe(Model $row): string
    {
        return 'refund #'.$row->getKey().' ('.$row->status.')';
    }

    public function rows(int $companyId, ?Carbon $from, ?Carbon $to, ?int $limit): iterable
    {
        $query = Refund::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('refund_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('refund_date', '<=', $to))
            ->orderByRaw('COALESCE(refund_date, created_at)')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->cursor();
    }

    public function replay(Model $row): ReplayOutcome
    {
        /** @var Refund $row */
        $companyId = (int) $row->company_id;
        $amount = round((float) ($row->total_nett_refund ?? 0), 3);

        $postable = [Refund::STATUS_APPROVED, Refund::STATUS_POSTED, Refund::STATUS_COMPLETED, 'processed'];

        if (! in_array($row->status, $postable, true)) {
            return ReplayOutcome::skipped($row->id, 'status_not_postable:'.$row->status, $amount);
        }

        if ($row->refundDetails()->count() === 0) {
            return ReplayOutcome::skipped($row->id, 'no_refund_details', $amount);
        }

        // "Has this refund already been posted?" cannot be asked of ONE key: a refund emits up to
        // seven documents and not every refund emits every one (a disposition of zero mints
        // nothing at all). Keying only on `:disposition` reported such a refund as freshly POSTED
        // on every re-run -- harmless to the ledger, because each sub-document is individually
        // idempotent, but it made the run report claim work that did not happen. Any document
        // under this refund's own key prefix means it has been through the composer already.
        $existing = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', 'like', 'refund:'.$row->id.':%')
            ->first();

        if ($existing !== null) {
            return ReplayOutcome::posted($row->id, (int) $existing->id, null, true);
        }

        try {
            $result = $this->refunds->post($row, null);

            $transactionId = $result['disposition']?->transaction->id
                ?? ($result['supplier_credit'][0] ?? null)?->transaction->id
                ?? ($result['crn'][0] ?? null)?->transaction->id;

            return ReplayOutcome::posted($row->id, $transactionId !== null ? (int) $transactionId : null, $amount);
        } catch (\Throwable $e) {
            return ReplayOutcome::refused($row->id, $e->getMessage(), $e, $amount);
        }
    }
}
