<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

use App\Http\Controllers\ReceiptVoucherController;
use App\Models\InvoiceReceipt;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ReceiptPostingRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * CT-A3 wave 2 (W2-1 + W2-2) — the `receipt` class: one `RV` document per `invoice_receipts` row,
 * under `ReceiptVoucherController::buildVoucherDraft()`'s own key `rv:{id}`.
 *
 * The draft comes from that method itself (made public in wave 2 for exactly this reason — a
 * backfill that re-implemented the RV shape would be a second definition of the receipt document,
 * which is how CT-A1 §1.7's twenty refund writers came to disagree with each other). Whether a
 * given row's status posts at all is {@see ReceiptPostingRule}'s decision, not this source's:
 * W2-2 put receipts on R-CT3's configured-status pattern, so `approved` posts, `bounced` /
 * `reversed` / `rejected` do not, and the vocabulary lives in `config('accounting.receipt')`
 * rather than in an `if` here.
 *
 * CT-A2 §3.1 replayed 0 of 109 receipt vouchers; CT-A3 wave 1 replayed 100 of 109 with the 9
 * refusals fully accounted for (3 company-2 rows the engine gate correctly refuses, 3
 * unresolvable-company rows, 3 zero-amount rows).
 *
 * ── Ledger only ─────────────────────────────────────────────────────────────────────────────────
 * This source posts the DOCUMENT. It deliberately does not run
 * `applyAllocationsToInvoices()` — a historical backfill must not rewrite `invoices.status` /
 * `invoice_partials` that the source system already settled years ago. The open-item apply belongs
 * to the live `approve()` path (W2-2), not to a replay of history.
 */
final class ReceiptReplaySource implements ReplaySource
{
    use ChecksExistingDocument;

    public function __construct(
        private readonly PostingService $posting,
        private readonly ReceiptPostingRule $rule,
    ) {}

    public function name(): string
    {
        return 'receipt';
    }

    public function idempotencyKeyFor(Model $row): string
    {
        return 'rv:'.$row->getKey();
    }

    public function describe(Model $row): string
    {
        return 'invoice_receipt #'.$row->getKey().' ('.$row->status.')';
    }

    public function rows(int $companyId, ?Carbon $from, ?Carbon $to, ?int $limit): iterable
    {
        // `invoice_receipts.company_id` is NULL on every legacy row (CT-F35), so the window
        // cannot be a plain `where('company_id', …)` — the whole population is walked and the
        // per-row company is resolved by buildVoucherDraft()'s own chain, with a row belonging to
        // another company refused by the engine gate exactly as it would be live.
        $query = InvoiceReceipt::query()
            ->when($from, fn ($q) => $q->whereDate('doc_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('doc_date', '<=', $to))
            ->orderByRaw('COALESCE(doc_date, created_at)')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->cursor();
    }

    public function replay(Model $row): ReplayOutcome
    {
        /** @var InvoiceReceipt $row */
        $amount = round((float) ($row->amount ?? 0), 3);
        $decision = $this->rule->decide($row);

        if (! $decision->shouldPost) {
            return ReplayOutcome::skipped($row->id, $decision->reason, $amount);
        }

        try {
            $draft = app(ReceiptVoucherController::class)->buildVoucherDraft($row);

            $existing = $this->existingDocument($draft->companyId, $draft->idempotencyKey);

            if ($existing !== null) {
                return ReplayOutcome::posted($row->id, (int) $existing->id, null, true);
            }

            $posted = $this->posting->post($draft);

            return ReplayOutcome::posted($row->id, (int) $posted->transaction->id, $amount);
        } catch (\Throwable $e) {
            return ReplayOutcome::refused($row->id, $e->getMessage(), $e, $amount);
        }
    }
}
