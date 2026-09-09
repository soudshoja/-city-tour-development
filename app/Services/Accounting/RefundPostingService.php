<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Events\Accounting\CommissionUnearned;
use App\Models\Credit;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Setting;
use App\Models\Task;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * W4.R (w4-brief.md §4, "the refund document" — main work). Composes every ledger document one
 * posted refund emits, in ONE DB transaction, entirely via {@see PostingService} (this class
 * calls PostingService DIRECTLY, not {@see PostingSeam} — see "Why PostingSeam is not used here"
 * below).
 *
 * ── Precondition (caller's responsibility) ─────────────────────────────────────────────────────
 * The caller (RefundController) MUST have already confirmed
 * `app(PostingSeam::class)->isEnabledFor($refund->company_id)` before calling {@see post()} — this
 * class does not re-check the kill-switch itself (PostingService::post()/reverse() still refuse
 * with PostingEngineDisabledException if it is somehow off by the time a line here posts, so a
 * caller that skips the precondition fails safe, just without PostingSeam's own OFF-path legacy
 * fallback).
 *
 * ── Why PostingSeam is not used here (documented design choice, not an oversight) ───────────────
 * Every InvoiceController feeder in this codebase calls `PostingSeam::post()` at its OWN
 * top-level entry point (one legacy-vs-engine decision per feeder), then — for a feeder that is
 * itself only ever invoked from inside an already-confirmed-ON branch (e.g.
 * `agentCommissionForInvoiceCharge()`'s `postOrRepostDraft()`, which calls
 * `app(PostingService::class)->reverse()` directly) — calls `PostingService` directly for its own
 * internal sub-steps, since PostingSeam's whole reason to exist (a legacy closure to fall back to)
 * does not apply once the caller has already decided "we are on the engine path for this
 * request". RefundController applies that SAME convention here: ONE legacy-vs-engine decision at
 * its own entry point (call this class vs. run the pre-existing `handlePaidRefund()` /
 * `handleUnpaidInvoice()` / `handlePartialRefund()` methods unchanged), then this class — reached
 * ONLY on the confirmed-ON branch — composes every sub-document via `PostingService` directly.
 * Six of this class's documents ((b) recharge, (c) supplier credit item, (e)(i) clawback, (d)'s
 * fresh refund-event commission hook) have NO legacy analogue in `ct-refund-map.md` at all — they
 * are genuinely new document types introduced by this wave — so there is no legacy closure a
 * PostingSeam call here could meaningfully fall back to; inventing a parallel raw-JournalEntry
 * "legacy" body for a document that never existed at HEAD would not be preserving legacy
 * behaviour, it would be fabricating it.
 *
 * ── Composition, matching w4-brief.md §4(a)-(f) exactly (see each method's own docblock) ────────
 *   (a) postCrnForDetail()             — CRN: reverse() the sale, or a standalone legacy CRN.
 *   (b) postRechargeLines()            — one recharge document across every detail.
 *   (c) postSupplierCreditForDetail()  — one supplier credit item PER detail (per-task supplier).
 *   (d) postCommissionUnearnForDetail()— reverse() the original per-detail commission JV.
 *   (e) postClawback()                 — three-event airline clawback (i unconditional, ii gated
 *                                        P5.13, iii = (d) above, already covered per-detail).
 *   (f) postDisposition()              — client-net disposition (credit default).
 */
final class RefundPostingService
{
    public function __construct(
        private readonly PostingService $posting,
    ) {}

    /**
     * Post every document for $refund in one DB transaction. Idempotent on retry: every
     * sub-document carries its own fixed idempotency key, so calling this twice for the same
     * refund is a no-op the second time (PostingService::post()'s own step-1 idempotency
     * short-circuit) and reverse() is itself idempotent (returns the existing reversal).
     *
     * @return array{crn: array<int,PostedDocument>, recharge: ?PostedDocument,
     *               supplier_credit: array<int,PostedDocument>,
     *               commission_unearn: array<int,PostedDocument>,
     *               commission_earn: array<int,PostedDocument>, clawback: ?PostedDocument,
     *               disposition: ?PostedDocument}
     */
    public function post(Refund $refund, ?int $userId): array
    {
        return DB::transaction(function () use ($refund, $userId) {
            $companyId = (int) $refund->company_id;
            $docDate = $refund->refund_date instanceof \DateTimeInterface
                ? Carbon::instance($refund->refund_date)
                : now();

            $refund->loadMissing([
                'refundDetails.task.agent',
                'refundDetails.task.client',
                'refundDetails.task.supplier',
                'refundDetails.task.invoiceDetail.invoice',
            ]);

            if ($refund->refundDetails->isEmpty()) {
                throw new \InvalidArgumentException(
                    "RefundPostingService::post(): refund #{$refund->id} has no refund_details lines."
                );
            }

            // W4.R verify-fix (finding #5, MEDIUM — root cause as stated by the finding itself:
            // "RefundPostingService::post() never checks $refund->status at all before composing
            // and posting every document"). w4-brief.md §4 workflow is draft -> approved -> posted
            // -> completed | rejected, gated by RefundPolicy::approve()/complete(); this is the
            // single choke point every caller (RefundController::handlePaidRefund(), reached from
            // store(), update(), and handlePartialRefund()) funnels through, so the guard belongs
            // HERE, not re-implemented per call site. 'processed' is the legacy OFF-created default
            // (kept postable so a refund created before the engine was turned on for a company
            // can still be driven through once ON — same permissive convention
            // RefundPolicy::COMPLETABLE_STATUSES already uses); STATUS_COMPLETED is included so a
            // retry after a first successful post() (this method's own documented idempotency)
            // is never refused.
            $postableStatuses = [Refund::STATUS_APPROVED, Refund::STATUS_POSTED, Refund::STATUS_COMPLETED, 'processed'];
            if (! in_array($refund->status, $postableStatuses, true)) {
                throw new \RuntimeException(
                    "RefundPostingService::post(): refund #{$refund->id} has status='{$refund->status}' -- "
                    .'posting is only allowed once a refund has been approved (w4-brief.md §4: '
                    .'draft -> approved -> posted -> completed). Call RefundController::approve() first.'
                );
            }

            $crnDocs = [];
            $unearnDocs = [];
            $supplierCreditDocs = [];
            $commissionEarnDocs = [];

            foreach ($refund->refundDetails as $detail) {
                $crnDocs[] = $this->postCrnForDetail($refund, $detail, $companyId, $docDate, $userId);

                $unearn = $this->postCommissionUnearnForDetail($refund, $detail, $companyId, $docDate, $userId);
                if ($unearn !== null) {
                    $unearnDocs[] = $unearn;

                    // P2.5.I (p2_5-brief.md §P2.5.I): "commission_unearned event-driven from
                    // W4.R's un-earn post" -- additive dispatch only, no change to the posting
                    // above. agent_id/client_id come from the task the refund detail targets (the
                    // same party the un-earned commission was originally posted against);
                    // amount is the un-earn reversal's own transaction total (the debit and
                    // credit lines of a reversal are always equal, so total_debit is the
                    // commission amount un-earned).
                    if ($detail->task !== null && $detail->task->agent_id !== null && $detail->task->client_id !== null) {
                        event(new CommissionUnearned(
                            companyId: $companyId,
                            agentId: (int) $detail->task->agent_id,
                            clientId: (int) $detail->task->client_id,
                            invoiceId: $detail->task->invoiceDetail?->invoice_id,
                            transactionId: (int) $unearn->transaction->id,
                            amount: (float) $unearn->transaction->total_debit,
                        ));
                    }
                }

                $commissionEarn = $this->postCommissionEarnForRefundDetail($refund, $detail, $companyId, $docDate, $userId);
                if ($commissionEarn !== null) {
                    $commissionEarnDocs[] = $commissionEarn;
                }

                $supplierCredit = $this->postSupplierCreditForDetail($refund, $detail, $companyId, $docDate, $userId);
                if ($supplierCredit !== null) {
                    $supplierCreditDocs[] = $supplierCredit;
                }
            }

            $recharge = $this->postRechargeLines($refund, $companyId, $docDate, $userId);
            $clawback = $this->postClawback($refund, $companyId, $docDate, $userId);
            $disposition = $this->postDisposition($refund, $companyId, $docDate, $userId);

            // postDisposition() may already have advanced $refund->status to STATUS_COMPLETED
            // (Credit/refund_out — settled synchronously in the same transaction) — never
            // downgrade that back to STATUS_POSTED. Only 'apply' and the async Online path leave
            // status at STATUS_POSTED here, waiting for a later completion step.
            $refund->refresh();
            if ($refund->status !== Refund::STATUS_COMPLETED) {
                $refund->forceFill([
                    'status' => Refund::STATUS_POSTED,
                    'posted_by' => $userId,
                    'posted_at' => $refund->posted_at ?? now(),
                ])->save();
            }

            return [
                'crn' => $crnDocs,
                'recharge' => $recharge,
                'supplier_credit' => $supplierCreditDocs,
                'commission_unearn' => $unearnDocs,
                'commission_earn' => $commissionEarnDocs,
                'clawback' => $clawback,
                'disposition' => $disposition,
            ];
        });
    }

    /**
     * (a) CRN. w4-brief.md §4a: "reverse() of sale lines (revenue, markup 4132 symmetric)".
     *
     * `refund_retain_markup` note: {@see SaleDraftBuilder}'s own docblock states it NEVER posts a
     * separate MARKUP_INCOME (4132) line for a task-based sale (both posting bases fold the
     * margin into SERVICE_REVENUE itself) — there is currently no discrete markup line for this
     * option to selectively retain. `refund_retain_markup` is read and honoured structurally (see
     * companyOption() call below) but is a documented NO-OP under the current sale shape: a full
     * reverse() is what a "retain markup, reverse only revenue" request would collapse to anyway,
     * since AR cannot be reversed without its offsetting revenue line without unbalancing the
     * document. This becomes meaningful again only if a future feeder starts booking a genuinely
     * separate MARKUP_INCOME line.
     *
     * Idempotency key: `refund:{refund_id}:crn:{refund_detail_id}` (per-detail — one refund can
     * cover several tasks/invoice-details, each needs its own CRN leg / reversal).
     *
     * ── CT-A3 R2-1 — VERIFY-CT-A3-STACK-R1 §3.2 D5 (BLOCKER), and §4 claim 1 ────────────────────
     * The key above is what this method's docblock has always PROMISED. The verify report found it
     * was never minted: on the engine path the reversal went out under `reverse()`'s own
     * `rev:{saleTxnId}`, and the sale itself was looked up by the FIXED key
     * `invoice-detail:{id}:sale` in ANY status. `PostingService::reverse()` short-circuits on any
     * pre-existing reversal, so after ANY prior reversal of that sale this method returned
     * somebody else's REV document as this refund's credit note — no revenue reversed, no
     * exception, no log, a success message, and a trial balance that still foots. Three ordinary
     * producers reach it:
     *
     *   1. **An invoice price correction, then a refund.** `repost()` renames the REPLACEMENT, so
     *      after a correction the dead, reversed sale still owns `invoice-detail:{id}:sale` and
     *      the LIVE sale sits under a revision key. This method reversed the corpse.
     *   2. **A second refund on the same task** — nothing prevents one.
     *   3. **A refund raised against the original task of a reissue**, whose sale
     *      `TaskStatusService::reissueReverseOldSale()` already reversed.
     *
     * Worked (sale 100, penalty 20, fee 5, credit disposition): revenue stays **+100** un-reversed
     * and AR reaches **+200** against a client who was credited 75. The COST half was relieved
     * correctly throughout, because {@see self::postSupplierCreditForDetail()} reads the LEDGER —
     * which is exactly why no supplier-side or AP-control check in the wave reports could see it.
     *
     * WHAT IT DOES NOW, in order:
     *
     *   1. **Retry first.** If a document already exists under THIS refund detail's own CRN key
     *      (engine or legacy), return it. That is what carries idempotency now — previously it was
     *      carried by accident, by the very "the sale is already reversed" condition that made the
     *      defect silent.
     *   2. **Compute the sale's CURRENT POSTED POSITION** — every sale-family document for this
     *      invoice detail (the base key plus every `repost()` revision of it) that is STILL LIVE.
     *      That set IS the outstanding revenue / AR / COGS / payable for the detail: a reversed
     *      document and its REV cancel out, so what remains posted is what is still carried.
     *      Reversing exactly those documents credits exactly what is outstanding, whichever
     *      revision the live sale happens to be.
     *   3. **Refuse loudly** — {@see NothingOutstandingToCreditException}, plus an
     *      `AccountingLog::event('refund_crn_refused', …)` row — when nothing is live and no legacy
     *      sale remains uncredited. The alternative is a second credit note for money that was
     *      already given back once.
     *
     * NOT FIXED HERE, and deliberately: a PARTIAL credit. The credit note is a FULL reversal of the
     * live sale, so a refund whose `original_invoice_price` is less than the outstanding sell still
     * reverses the whole document — it is logged with both figures, and "what a partial or second
     * refund on an already-refunded task means" is owner ruling territory (verify report §7 item
     * 2), not a patch.
     */
    private function postCrnForDetail(
        Refund $refund,
        RefundDetail $detail,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId
    ): PostedDocument {
        $task = $detail->task;
        $invoiceDetail = $task?->invoiceDetail;

        if ($invoiceDetail === null) {
            // CT-A3 wave 2 (W2-3): a NAMED refusal, not a bare RuntimeException.
            // `accounting:replay` groups refusals by exception class, and on the City Travelers
            // data 26 of the 33 refunds land here -- a refund of a task that was never invoiced is
            // the ORDINARY shape on that population (CT-A1 §0: 63% of issued tasks are
            // uninvoiced), not an error. Collapsing them into a bucket labelled `RuntimeException`
            // told an operator nothing. See the exception's own docblock for which path DOES carry
            // an uninvoiced task's refund.
            throw new \App\Exceptions\Accounting\RefundWithoutInvoiceDetailException(
                (int) $detail->id,
                $detail->task_id !== null ? (int) $detail->task_id : null
            );
        }

        $saleKey = 'invoice-detail:'.$invoiceDetail->id.':sale';
        $crnKey = 'refund:'.$refund->id.':crn:'.$detail->id;
        $legacyKey = 'refund:'.$refund->id.':crn-legacy:'.$detail->id;

        // ── (1) RETRY ────────────────────────────────────────────────────────────────────────
        // This refund detail's OWN credit note, in either form, if it already exists. Structural
        // targeting by idempotency_key -- NEVER description (w4-brief.md hard rule).
        $existing = $this->posting->findPostedDocument($companyId, $crnKey)
            ?? $this->posting->findPostedDocument($companyId, $legacyKey);

        if ($existing !== null) {
            return $existing;
        }

        // ── (2) THE SALE'S CURRENT POSTED POSITION ───────────────────────────────────────────
        // Every sale-family document for this invoice detail: the base key plus every revision
        // repost() has minted off it (':rev{n}', and ':repost:{id}' for anything edited before
        // CT-A3 R2-2). A reversed document and its REV cancel on the ledger, so the members still
        // at posting_status='posted' ARE the outstanding revenue / AR / COGS / payable.
        $saleFamily = $this->saleFamilyFor($companyId, $saleKey);
        $liveSales = $saleFamily->where('posting_status', 'posted')->values();

        if ($liveSales->isNotEmpty()) {
            $liveIds = $liveSales->pluck('id')->map(static fn ($id) => (int) $id)->all();
            $outstandingSell = $this->sellCarriedBy($companyId, $liveIds, (int) $invoiceDetail->id);
            $creditedSell = round((float) ($detail->original_invoice_price ?? 0.0), 3);

            AccountingLog::event('refund_crn_posted', [
                'refund_id' => $refund->id,
                'refund_detail_id' => $detail->id,
                'company_id' => $companyId,
                'invoice_detail_id' => $invoiceDetail->id,
                'idempotency_key' => $crnKey,
                'live_sale_transaction_ids' => $liveIds,
                'outstanding_sell' => $outstandingSell,
                'credited_sell' => $creditedSell,
                // Recorded, not enforced: the credit note is a FULL reversal of the live sale, so a
                // mismatch here is the partial-credit ruling this wave deliberately did not make
                // (verify report §7 item 2), never a silent rounding.
                'partial_credit_requested' => abs($outstandingSell - $creditedSell) > 0.0005,
            ]);

            $first = null;

            foreach ($liveSales as $index => $saleTransaction) {
                // One live document is the ordinary shape. More than one can only arise from a
                // feeder minting two sale documents for one invoice detail; each gets its own
                // suffixed CRN key so none collides, and EVERY one is reversed -- silently
                // reversing only the first would leave revenue standing, which is D5 again in a
                // different costume.
                $document = $this->posting->reverse(
                    $saleTransaction,
                    $docDate,
                    $userId,
                    false,
                    $index === 0 ? $crnKey : $crnKey.':'.$saleTransaction->id
                );

                $first ??= $document;
            }

            /** @var PostedDocument $first */
            return $first;
        }

        if ($saleFamily->isNotEmpty()) {
            // Every engine sale document for this invoice detail is already reversed. Nothing is
            // outstanding, and reverse() would hand back whoever reversed it first.
            $this->refuseNothingOutstanding(
                $refund,
                $detail,
                $companyId,
                (int) $invoiceDetail->id,
                $saleFamily->count(),
                'every sale document for this invoice detail is already reversed (a prior refund, a '
                .'reissue, or a deleted invoice)'
            );
        }

        // w4-brief.md §4a "Legacy original ... transactions.idempotency_key IS NULL for the
        // carrying sale ... post a standalone, balanced CRN document ... referencing the legacy
        // transaction_id in reversal_of_transaction_id ... idempotency key
        // 'refund:{refund_id}:crn-legacy'." (per-detail here, since one refund can carry several
        // legacy sales — suffixed with the detail id so each keeps its own key, following this
        // class's own per-detail convention above.)
        $serviceType = (string) ($task->type ?? '');
        $sellAmount = round((float) ($detail->original_invoice_price ?? 0.0), 3);

        if ($sellAmount <= 0) {
            throw new \RuntimeException(
                "RefundPostingService::postCrnForDetail(): refund_detail #{$detail->id} has no positive "
                .'original_invoice_price to reverse.'
            );
        }

        // CT-A3 R2-1, D5 producer 2 on the LEGACY side. A legacy sale carries no idempotency key,
        // so "has this already been credited?" cannot be asked of the sale -- it has to be asked of
        // the CREDIT NOTES. This refund's own is already ruled out at step (1); what remains is a
        // DIFFERENT refund having credited the same legacy sale earlier, which before R2-1 posted a
        // second full standalone CRN and reversed the same revenue twice.
        $priorLegacyCrn = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', 'like', 'refund:%:crn-legacy:%')
            ->whereIn('id', function ($q) use ($invoiceDetail) {
                $q->select('transaction_id')
                    ->from('journal_entries')
                    ->whereNull('deleted_at')
                    ->where('invoice_detail_id', $invoiceDetail->id);
            })
            ->count();

        if ($priorLegacyCrn > 0) {
            $this->refuseNothingOutstanding(
                $refund,
                $detail,
                $companyId,
                (int) $invoiceDetail->id,
                $priorLegacyCrn,
                'this invoice detail\'s legacy sale has already been credited by '
                ."{$priorLegacyCrn} earlier credit note(s)"
            );
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($refund->branch_id ?? $task->agent?->branch_id ?? 0),
            docType: 'CRN',
            subType: 'CRN_LEGACY_SALE', // transactions.sub_type is varchar(16) -- kept <= 16 chars
            docDate: $docDate,
            narration: 'Refund credit note (legacy sale, no engine transaction) for '.$refund->refund_number,
            lines: [
                new LineDraft(
                    purposeCode: 'SERVICE_REVENUE',
                    accountId: null,
                    side: 'debit',
                    amount: $sellAmount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $sellAmount,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_CRN_REVENUE_REVERSAL',
                    partyAccountRef: $task->client_id,
                    description: 'Refund credit note: '.$refund->refund_number,
                    invoiceId: $invoiceDetail->invoice_id,
                    invoiceDetailId: $invoiceDetail->id,
                    taskId: $task->id,
                    ledgerType: 'income',
                    partyName: $task->client?->full_name,
                    serviceType: $serviceType,
                ),
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL',
                    accountId: null,
                    side: 'credit',
                    amount: $sellAmount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $sellAmount,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_CRN_RECEIVABLE_REVERSAL',
                    partyAccountRef: $task->client_id,
                    description: 'Refund credit note: '.$refund->refund_number,
                    invoiceId: $invoiceDetail->invoice_id,
                    invoiceDetailId: $invoiceDetail->id,
                    taskId: $task->id,
                    ledgerType: 'receivable',
                    partyName: $task->client?->full_name,
                ),
            ],
            idempotencyKey: $legacyKey,
            invoiceId: $invoiceDetail->invoice_id,
        );

        $posted = $this->posting->post($draft, $userId);

        // w4-brief.md §4a: "referencing the legacy transaction_id in reversal_of_transaction_id".
        // The legacy sale (posted before the engine existed) has NO idempotency_key to target it
        // by, but its raw JournalEntry rows still carry invoice_detail_id — the same structural
        // (never description-based) link this class uses everywhere else — so the legacy
        // transaction header is found via THAT, not by re-deriving anything from text.
        $legacyTransactionId = \App\Models\JournalEntry::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('invoice_detail_id', $invoiceDetail->id)
            ->value('transaction_id');

        if ($legacyTransactionId !== null) {
            Transaction::withoutGlobalScopes()
                ->whereKey($posted->transaction->id)
                ->update(['reversal_of_transaction_id' => $legacyTransactionId]);
        }

        AccountingLog::event('refund_crn_legacy', [
            'refund_id' => $refund->id,
            'refund_detail_id' => $detail->id,
            'company_id' => $companyId,
            'idempotency_key' => $legacyKey,
        ]);

        return $posted;
    }

    /**
     * (b) Recharge lines. w4-brief.md §4b: "Dr AR / Cr penalty pass-through recovery + Cr 4130 fee
     * income (fee = service_type 'fee')" — TWO distinct credit legs, matching the two distinct
     * quantities `refund_details` actually carries (w4-brief.md §4 process decisions: "client net
     * = original_invoice_price − penalty recharge − fee"):
     *   - `supplier_charge` (documented as `airline_penalty` — the airline/consolidator penalty
     *     deducted from the supplier's own refund, see (c) below) is the amount PASSED THROUGH to
     *     the client as a recharge → Cr PENALTY_PASSTHROUGH_RECOVERY (4136).
     *   - `refund_fee_to_client` is the agency's OWN refund/cancellation fee ("our fee" in the
     *     w4-brief.md §4 top-level column list) → Cr SERVICE_FEE_INCOME (4133, the same leaf
     *     W4.0's `addInvoiceChargeJournalEntries()` feeder resolves — "fee = service_type 'fee'").
     * W4.R verify-fix (finding #2, HIGH): a prior build folded 100% of `refund_fee_to_client`
     * into the pass-through-recovery leg alone, misclassifying the agency's own fee income as a
     * pass-through recovery and never recognising it. Worked example from the brief/verify item:
     * sale 100 / cost 90 / penalty 20 / fee 5 → Dr AR 25 / Cr PENALTY_PASSTHROUGH_RECOVERY 20 /
     * Cr SERVICE_FEE_INCOME 5 (see RefundPostingServiceTest's own worked-example test).
     * Either leg (or both) may independently post — a no-op (returns null) only when NEITHER
     * quantity is chargeable.
     */
    private function postRechargeLines(
        Refund $refund,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId
    ): ?PostedDocument {
        $penaltyRecharge = round((float) $refund->refundDetails->sum('supplier_charge'), 3);
        $feeIncome = round((float) $refund->refundDetails->sum('refund_fee_to_client'), 3);
        $totalRecharge = round($penaltyRecharge + $feeIncome, 3);

        if ($totalRecharge <= 0) {
            return null;
        }

        $firstDetail = $refund->refundDetails->first();
        $task = $firstDetail->task;

        $lines = [
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit',
                amount: $totalRecharge,
                currency: config('accounting.engine.base_currency'),
                originalAmount: $totalRecharge,
                exchangeRate: 1.0,
                transactionType: 'REFUND_RECHARGE_RECEIVABLE',
                partyAccountRef: $task?->client_id,
                description: 'Refund recharge to client: '.$refund->refund_number,
                invoiceId: $firstDetail->task?->invoiceDetail?->invoice_id,
                ledgerType: 'receivable',
                partyName: $task?->client?->full_name,
            ),
        ];

        if ($penaltyRecharge > 0) {
            $lines[] = new LineDraft(
                purposeCode: 'PENALTY_PASSTHROUGH_RECOVERY',
                accountId: null,
                side: 'credit',
                amount: $penaltyRecharge,
                currency: config('accounting.engine.base_currency'),
                originalAmount: $penaltyRecharge,
                exchangeRate: 1.0,
                transactionType: 'REFUND_RECHARGE_RECOVERY',
                description: 'Refund recharge (penalty pass-through) to client: '.$refund->refund_number,
                ledgerType: 'income',
                // PENALTY_PASSTHROUGH_RECOVERY is mapped GLOBAL (service_type=null) in
                // SystemAccountsSeeder — unlike SERVICE_REVENUE/SERVICE_PAYABLE/SERVICE_COST, it
                // is not one of config('accounting.purpose_codes.per_service'), so passing a
                // serviceType here would make AccountResolver look for a service-scoped mapping
                // that was never seeded and throw UnmappedPurposeException.
            );
        }

        if ($feeIncome > 0) {
            $lines[] = new LineDraft(
                purposeCode: 'SERVICE_FEE_INCOME',
                accountId: null,
                side: 'credit',
                amount: $feeIncome,
                currency: config('accounting.engine.base_currency'),
                originalAmount: $feeIncome,
                exchangeRate: 1.0,
                transactionType: 'REFUND_RECHARGE_FEE_INCOME',
                description: 'Refund fee income for '.$refund->refund_number,
                ledgerType: 'income',
                // SERVICE_FEE_INCOME is also mapped GLOBAL (config/accounting.php's own comment:
                // "a flat invoice charge is not tied to any one service_type" — same reasoning
                // applies to a flat refund fee) — no serviceType arg, same as the leg above.
            );
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($refund->branch_id ?? 0),
            docType: 'DBN',
            subType: 'REFUND_RECHARGE',
            docDate: $docDate,
            narration: 'Refund recharge (penalty pass-through + fee) for '.$refund->refund_number,
            lines: $lines,
            idempotencyKey: 'refund:'.$refund->id.':recharge',
            invoiceId: $firstDetail->task?->invoiceDetail?->invoice_id,
        );

        return $this->posting->post($draft, $userId);
    }

    /**
     * (c) Supplier credit item, ONE per refund detail (a refund can span several tasks, each with
     * its own supplier).
     *
     * ── CT-A3 wave 2, item W2-3 — owner ruling R-CT3, the recovery direction ────────────────────
     * BEFORE this wave this method posted, on EVERY refund:
     *
     *     Dr SERVICE_PAYABLE       = supplierRefundAmount()   (defaulting to cost - penalty)
     *     Dr PENALTY_COST_EXPENSE  = the rest
     *         Cr SERVICE_COST      = the FULL original cost, unconditionally
     *
     * Three defects in that, all of which this rewrite closes:
     *
     *  1. **It assumed the supplier refunds.** `supplierRefundAmount()` defaulted to
     *     `original_task_cost - supplier_charge`, so "nobody recorded what the supplier did" was
     *     booked as a full recovery: a cost the agency had genuinely borne was erased and a
     *     payable it still owed was cleared. Whether the supplier refunds is now master data —
     *     {@see SupplierRefundRule} over `suppliers.refund_trigger`/`refund_hold` and
     *     `config('accounting.supplier_refund.triggers')` — never an assumption here, never a
     *     supplier name, never a constant. (CT-A1 CT-F11.)
     *
     *  2. **It credited `SERVICE_COST` even when the cost was not there.** CT-F11: *"319 refund
     *     lines (57,891.068) credit a COGS leaf, but post-P1a the original cost sits in asset
     *     1430."* Where the cost sits is a ledger question, so it is asked of the ledger —
     *     {@see self::costCarrierPurposeFor()}.
     *
     *  3. **It double-relieved a gross sale.** Found by this wave's own test suite, and a direct
     *     consequence of wave 1's R-CT1: under GROSS, the sale document carries its own
     *     `SERVICE_COST`/`SERVICE_PAYABLE` pair, so {@see self::postCrnForDetail()}'s
     *     `PostingService::reverse()` of that sale ALREADY reverses the cost and the payable in
     *     full. Posting the old fixed shape on top of it debited the payable twice — leaving a
     *     supplier we owed nothing to sitting at a 100-debit balance and cost of sales 100 in
     *     credit. Under the pre-R-CT1 net model the sale carried no cost leg for the reversal to
     *     touch, so the duplication did not exist and no wave-1 test could have caught it.
     *
     * ── The shape now: post the DIFFERENCE, not a fixed template ────────────────────────────────
     * This method runs AFTER the CRN in {@see self::post()}, so the ledger already reflects
     * whatever the CRN did — a true reversal for an engine sale, revenue and receivable only for a
     * legacy one, nothing at all for a task whose cost is still an unbilled accrual. Rather than
     * guessing which of those happened, it reads the task's CURRENT position and posts only what
     * is needed to reach the correct end state:
     *
     *     cost carrier for this task  ->  0.000        (the sale is undone either way)
     *     supplier payable for this task -> the NON-RECOVERABLE part
     *                                       (0.000 when the supplier refunds in full;
     *                                        the penalty it kept; or the whole cost when it
     *                                        refunds nothing — because we still owe that money)
     *
     * and the balancing leg is `PENALTY_COST_EXPENSE` when the supplier IS refunding and merely
     * kept a charge, or `SUPPLIER_REFUND_LOSS` (5131) when it is not refunding — the owner's "the
     * cost stays and a refund-loss expense line carries the non-recoverable part". Nothing is
     * posted at all when the ledger is already in the correct state (an engine sale, fully
     * refunded, no penalty): a balanced no-op document would be noise.
     *
     * An operator's explicit `refund_details.supplier_refund_amount` always wins over the rule —
     * see {@see SupplierRefundRule::decide()}. A supplier refunding MORE than the original cost
     * still lands credit-side on `PENALTY_COST_EXPENSE` as a genuine gain, never clamped away.
     */
    private function postSupplierCreditForDetail(
        Refund $refund,
        RefundDetail $detail,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId
    ): ?PostedDocument {
        $task = $detail->task;

        if ($task === null || $task->supplier_id === null) {
            return null;
        }

        $fullCost = round((float) ($detail->original_task_cost ?? 0.0), 3);

        if ($fullCost <= 0) {
            return null;
        }

        $serviceType = (string) ($task->type ?? '');
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $currency = config('accounting.engine.base_currency');

        $decision = app(SupplierRefundRule::class)->decide($task, $task->supplier, $detail);

        Log::info('accounting.supplier_refund.decided', array_merge($decision->toLogContext(), [
            'refund_id' => $refund->id,
            'refund_detail_id' => $detail->id,
            'task_id' => $task->id,
            'company_id' => $companyId,
            'supplier_id' => $task->supplier_id,
            'original_task_cost' => $fullCost,
        ]));

        // Where the cost sits, and how much of it is still there AFTER the CRN.
        $costCarrier = $this->costCarrierPurposeFor($task, $companyId);
        $costServiceType = $costCarrier === 'UNBILLED_SUPPLIER_COST' ? null : $serviceType;

        $costOutstanding = round($this->taskNetOnPurpose($task, $companyId, $costCarrier, $costServiceType), 3);
        $payableOutstanding = round(-1 * $this->taskNetOnPurpose($task, $companyId, 'SERVICE_PAYABLE', $serviceType), 3);

        // The target payable: what the agency still owes this supplier for this task once the
        // refund is settled. Zero on a full recovery; the penalty it kept; the whole cost when it
        // is refunding nothing.
        $payableTarget = round($decision->nonRecoverableAmount, 3);
        $payableDelta = round($payableOutstanding - $payableTarget, 3);

        $lines = [];

        if ($costOutstanding > $tolerance) {
            $lines[] = new LineDraft(
                purposeCode: $costCarrier,
                accountId: null,
                side: 'credit',
                amount: $costOutstanding,
                currency: $currency,
                originalAmount: $costOutstanding,
                exchangeRate: 1.0,
                transactionType: $costCarrier === 'UNBILLED_SUPPLIER_COST'
                    ? 'REFUND_SUPPLIER_CREDIT_ACCRUAL'
                    : 'REFUND_SUPPLIER_CREDIT_COGS',
                description: 'Supplier credit for refund: '.$refund->refund_number,
                invoiceId: $task->invoiceDetail?->invoice_id,
                taskId: $task->id,
                ledgerType: $costCarrier === 'UNBILLED_SUPPLIER_COST' ? 'asset' : 'expense',
                serviceType: $costServiceType,
            );
        }

        if (abs($payableDelta) > $tolerance) {
            $lines[] = new LineDraft(
                purposeCode: 'SERVICE_PAYABLE',
                accountId: null,
                side: $payableDelta > 0 ? 'debit' : 'credit',
                amount: round(abs($payableDelta), 3),
                currency: $currency,
                originalAmount: round(abs($payableDelta), 3),
                exchangeRate: 1.0,
                transactionType: $payableDelta > 0 ? 'REFUND_SUPPLIER_CREDIT_PAYABLE' : 'SUPPLIERCREDITED',
                partyAccountRef: $task->supplier_id,
                description: $payableDelta > 0
                    ? 'Supplier credit for refund: '.$refund->refund_number
                    : 'Supplier cost retained on refund: '.$refund->refund_number,
                invoiceId: $task->invoiceDetail?->invoice_id,
                taskId: $task->id,
                ledgerType: 'payable',
                partyName: $task->supplier?->name,
                serviceType: $serviceType,
            );
        }

        // The balancing leg: whatever the two legs above do not already offset is the amount the
        // agency is bearing (or, on a negative, gaining).
        $residual = 0.0;

        foreach ($lines as $line) {
            $residual += $line->side === 'debit' ? $line->amount : -$line->amount;
        }

        $residual = round($residual, 3);

        if (abs($residual) > $tolerance) {
            // Recovering with a charge kept -> a real penalty cost (5124). Not recovering -> the
            // agency's own loss on a refunded booking (5131). The two must stay distinguishable:
            // a penalty is the price of a refund that happened, a loss is a refund that did not.
            $isPenalty = $decision->shouldRecover;

            $lines[] = new LineDraft(
                purposeCode: $isPenalty ? 'PENALTY_COST_EXPENSE' : 'SUPPLIER_REFUND_LOSS',
                accountId: null,
                side: $residual < 0 ? 'debit' : 'credit',
                amount: round(abs($residual), 3),
                currency: $currency,
                originalAmount: round(abs($residual), 3),
                exchangeRate: 1.0,
                transactionType: match (true) {
                    ! $isPenalty => 'REFUND_SUPPLIER_UNRECOVERED',
                    $residual < 0 => 'REFUND_SUPPLIER_CREDIT_PENALTY',
                    default => 'REFUND_SUPPLIER_CREDIT_GAIN',
                },
                partyAccountRef: $isPenalty ? null : $task->supplier_id,
                description: $isPenalty
                    ? 'Supplier refund penalty for: '.$refund->refund_number
                    : 'Unrecovered supplier cost on refund '.$refund->refund_number.' ('.$decision->reason.')',
                invoiceId: $task->invoiceDetail?->invoice_id,
                taskId: $task->id,
                ledgerType: 'expense',
                partyName: $isPenalty ? null : $task->supplier?->name,
            );
        }

        if ($lines === []) {
            // The CRN's reversal of an engine gross sale already left the ledger exactly right:
            // no cost outstanding, nothing owed, nothing borne. Posting a balanced no-op document
            // would be noise on the supplier statement.
            Log::debug('accounting.supplier_refund.nothing_to_post', array_merge($decision->toLogContext(), [
                'refund_id' => $refund->id,
                'refund_detail_id' => $detail->id,
                'task_id' => $task->id,
            ]));

            return null;
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($refund->branch_id ?? 0),
            docType: 'DBN',
            subType: 'REFUND_SUPPLIER', // transactions.sub_type is varchar(16)
            docDate: $docDate,
            narration: 'Supplier credit item for refund '.$refund->refund_number,
            lines: $lines,
            idempotencyKey: 'refund:'.$refund->id.':supplier-credit:'.$detail->id,
            invoiceId: $task->invoiceDetail?->invoice_id,
        );

        $posted = $this->posting->post($draft, $userId);

        // transactions.bsptype = REFUND (w4-brief.md §4c). Not a DocumentDraft field (see that
        // class — no bsptype carrier exists), so stamped directly on the header immediately after
        // posting, matching migration 2026_08_27_130003's own note that bsptype is a plain,
        // application-validated classification column, not part of the balanced-document pipeline.
        Transaction::withoutGlobalScopes()->whereKey($posted->transaction->id)->update(['bsptype' => 'REFUND']);

        return $posted;
    }

    /**
     * CT-A3 R2-1 — every SALE document ever posted for one invoice detail, in any status.
     *
     * The family is the base key `invoice-detail:{id}:sale` plus every replacement
     * {@see PostingService::repost()} has minted off it. Both revision conventions are matched:
     * `:rev{n}` (current, CT-A3 R2-2) and `:repost:{transactionId}` (pre-R2, still on any invoice
     * whose price was corrected before that fix). Matching only the base key is precisely the D5
     * defect — after ANY correction the base key belongs to the dead document.
     *
     * LIKE metacharacters in the prefix are escaped: an invoice-detail id cannot contain one
     * today, but the escape is what makes that a fact about the data rather than a coincidence.
     *
     * @return \Illuminate\Support\Collection<int, Transaction>
     */
    private function saleFamilyFor(int $companyId, string $saleKey): \Illuminate\Support\Collection
    {
        $prefix = addcslashes($saleKey, '%_\\');

        return Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where(function ($q) use ($saleKey, $prefix) {
                $q->where('idempotency_key', $saleKey)
                    ->orWhere('idempotency_key', 'like', $prefix.':rev%')
                    ->orWhere('idempotency_key', 'like', $prefix.':repost:%');
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * CT-A3 R2-1 — the SELL still carried by the live sale documents for one invoice detail:
     * Cr − Dr on their revenue lines, read from the posted rows, never from a stored balance
     * column (CT-A1 §4.1 measured Σ|drift| KWD 6,277,563.301 on those).
     *
     * Reported, not enforced. The credit note is a FULL reversal of whatever is live, so this
     * figure exists to be LOGGED against the refund's own `original_invoice_price` — the pair is
     * what an operator needs to see when the two disagree, and what the partial-credit ruling
     * (verify report §7 item 2) will be decided from.
     *
     * @param  int[]  $transactionIds
     */
    private function sellCarriedBy(int $companyId, array $transactionIds, int $invoiceDetailId): float
    {
        if ($transactionIds === []) {
            return 0.0;
        }

        $net = (float) (DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->whereIn('transaction_id', $transactionIds)
            ->where('invoice_detail_id', $invoiceDetailId)
            ->whereNull('deleted_at')
            // `journal_entries.type` carries LedgerType's canonical vocabulary (CT-A3 E7): the
            // sale's revenue leg is stamped 'income' by SaleDraftBuilder, and reverse() carries
            // the original line's own `type` through verbatim.
            ->where('type', \App\Enums\LedgerType::INCOME->value)
            ->selectRaw('COALESCE(SUM(credit) - SUM(debit), 0) as net')
            ->value('net') ?? 0.0);

        return round($net, 3);
    }

    /**
     * CT-A3 R2-1 — refuse a credit note that has nothing to credit, LOUDLY: a named exception the
     * replay command can bucket by class, and an audit row that survives the rollback the throw
     * triggers, so the refusal is findable afterwards rather than only visible to whoever was
     * watching the screen.
     *
     * @return never
     */
    private function refuseNothingOutstanding(
        Refund $refund,
        RefundDetail $detail,
        int $companyId,
        int $invoiceDetailId,
        int $reversedSaleDocuments,
        string $reason
    ): void {
        Log::warning('accounting.refund_crn.nothing_outstanding', [
            'refund_id' => $refund->id,
            'refund_detail_id' => $detail->id,
            'company_id' => $companyId,
            'invoice_detail_id' => $invoiceDetailId,
            'task_id' => $detail->task_id,
            'reversed_sale_documents' => $reversedSaleDocuments,
            'reason' => $reason,
        ]);

        AccountingLog::event('refund_crn_refused', [
            'refund_id' => $refund->id,
            'refund_detail_id' => $detail->id,
            'company_id' => $companyId,
            'invoice_detail_id' => $invoiceDetailId,
            'reason' => $reason,
        ]);

        throw new \App\Exceptions\Accounting\NothingOutstandingToCreditException(
            (int) $detail->id,
            $invoiceDetailId,
            $detail->task_id !== null ? (int) $detail->task_id : null,
            $reversedSaleDocuments,
            $reason
        );
    }

    /**
     * CT-A3 wave 2 (W2-3). Dr - Cr for ONE task on the leaf a purpose code resolves to, computed
     * from posted journal rows — never from `accounts.actual_balance` or `journal_entries.balance`,
     * both of which CT-A1 §4.1 proved unusable (Σ|drift| KWD 6,277,563.301 across 200 of 207
     * posted accounts). Legacy rows carry `task_id` too, so a task whose original sale predates
     * the engine still reports its real position here rather than a zero.
     *
     * Returns 0.0 when the purpose does not resolve for this company: nothing is on an account
     * that does not exist, and a refund must not fail because a chart is missing a mapping.
     */
    private function taskNetOnPurpose(Task $task, int $companyId, string $purposeCode, ?string $serviceType): float
    {
        try {
            $account = app(AccountResolver::class)->resolve($purposeCode, $companyId, $serviceType);
        } catch (\Throwable $e) {
            return 0.0;
        }

        return (float) (DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->where('account_id', $account->id)
            ->where('task_id', $task->id)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as net')
            ->value('net') ?? 0.0);
    }

    /**
     * CT-A3 wave 2 (W2-3) — WHICH account currently carries this task's supplier cost, so a refund
     * credits the cost back where it actually is.
     *
     * This is the direct fix for CT-A1 CT-F11's supplier half: *"319 refund lines (57,891.068)
     * credit a COGS leaf, but post-P1a the original cost sits in asset 1430 — only 35 lines
     * (4,565.270) credit 1430 correctly. Net effect: COGS understated and Unbilled Supplier Cost
     * overstated by the same amount."*
     *
     * Answered by asking the LEDGER, not by assuming, and not from configuration: if this task
     * still has a net DEBIT balance on the company's `UNBILLED_SUPPLIER_COST` (1430) leaf, the
     * cost is sitting in the asset and that is what a refund must relieve. Otherwise the sale
     * document has already taken it to cost of sales and `SERVICE_COST` is right.
     *
     * Computed from posted journal rows — never from `accounts.actual_balance` or
     * `journal_entries.balance`, both of which CT-A1 §4.1 proved unusable (Σ|drift| KWD
     * 6,277,563.301 across 200 of 207 posted accounts). Same technique
     * {@see SupplierReassignDraftBuilder::openPayablePositions()} uses for the payable side.
     *
     * A company with no `UNBILLED_SUPPLIER_COST` mapping at all resolves to `SERVICE_COST`, which
     * is the pre-wave-2 behaviour and the correct answer for a chart that never used the deferral
     * model.
     */
    private function costCarrierPurposeFor(Task $task, int $companyId): string
    {
        try {
            $accrualAccount = app(AccountResolver::class)->resolve('UNBILLED_SUPPLIER_COST', $companyId);
        } catch (\Throwable $e) {
            return 'SERVICE_COST';
        }

        $net = (float) (DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->where('account_id', $accrualAccount->id)
            ->where('task_id', $task->id)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as net')
            ->value('net') ?? 0.0);

        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        return $net > $tolerance ? 'UNBILLED_SUPPLIER_COST' : 'SERVICE_COST';
    }

    /**
     * (d) Commission un-earn. w4-brief.md §4d: "reverse of JV/AGENT_COMMISSION (Dr 2201 / Cr
     * salary expense), fires UNCONDITIONALLY on refund. Option commission_on_refunded_sale
     * default un_earn". Targets InvoiceController::addJournalEntry()'s own per-detail commission
     * document STRUCTURALLY by its `invoice-detail:{id}:agent-commission` idempotency key — never
     * by description. A no-op (returns null, not an error) when no live commission document
     * exists under that key: this naturally covers every case the brief lists as "no un-earn"
     * without needing separate detection —
     *   - no commission was ever earned on this sale (agent-purchased ticket, Q-20.5 default off;
     *     zero-commission agent type; commission rate 0) — nothing was ever posted under this key.
     *   - the sale predates the engine (legacy) — same as above, nothing under this key.
     * `commission_on_refunded_sale` company option (default 'un_earn') gates whether the live
     * document is reversed at all — set to anything else ('keep'), this method still finds the
     * live document but deliberately does not touch it.
     */
    private function postCommissionUnearnForDetail(
        Refund $refund,
        RefundDetail $detail,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId
    ): ?PostedDocument {
        $policy = (string) $this->companyOption($companyId, 'accounting.refund.commission_on_refunded_sale', 'un_earn');

        if ($policy !== 'un_earn') {
            return null;
        }

        $invoiceDetail = $detail->task?->invoiceDetail;

        if ($invoiceDetail === null) {
            return null;
        }

        $commissionKey = 'invoice-detail:'.$invoiceDetail->id.':agent-commission';

        $commissionTransaction = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $commissionKey)
            ->where('posting_status', 'posted')
            ->first();

        if ($commissionTransaction === null) {
            return null;
        }

        return $this->posting->reverse($commissionTransaction, $docDate, $userId);
    }

    /**
     * (d) second half — w4-brief.md §4d: "SEPARATELY: margin the refund event itself creates ...
     * is commissionable per company policy and posts its own fresh JV/AGENT_COMMISSION (Dr
     * SALARY_EXPENSE / Cr SALARY_PAYABLE 2201), gated by company option commissionable_fee_types
     * default EMPTY (fees NOT commissionable — paying commission on cancellation fees means
     * cancelling costs the agent nothing)." Distinct from postCommissionUnearnForDetail() above
     * (the un-earn half of (d), which reverses the ORIGINAL sale's commission by its own
     * idempotency key) — this posts a BRAND NEW commission document on the refund event's OWN
     * margin, never touching the original sale's commission key, so the two can never collide or
     * double-count.
     *
     * Commissionable margin = `refund_fee_to_client` only (the brief's own "our refund/
     * cancellation fee -> 4130" clause). The penalty-spread half of "margin the refund event
     * creates" (w4-brief.md "Decisions": "where the agency ends up short ... that's a loss with a
     * bearer ... never modelled as negative commission") is deliberately excluded — it is a
     * company-absorbed-or-agent-recovered loss/gain, not a commission base.
     *
     * Gated by `commissionable_fee_types` (company option, JSON array, default `[]` — matches
     * "fees NOT commissionable" exactly): a no-op unless this detail's task type is explicitly
     * listed. This was previously the ONE unimplemented half of §4d — SettingController's
     * Accounting settings tab persisted the option but nothing ever read it back (W4.U
     * verify-fix, MEDIUM). Commission rate mirrors
     * InvoiceController::agentCommissionForInvoiceCharge()'s own convention (`$agent->commission`,
     * a fraction e.g. 0.15) so a refund-event commission computes identically to a sale-time one.
     *
     * Idempotency key: `refund:{refund_id}:commission-earn:{refund_detail_id}` — per-detail, its
     * own namespace (never collides with postCommissionUnearnForDetail()'s
     * `invoice-detail:{id}:agent-commission` key, which targets the ORIGINAL sale's document).
     */
    private function postCommissionEarnForRefundDetail(
        Refund $refund,
        RefundDetail $detail,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId
    ): ?PostedDocument {
        $fee = round((float) ($detail->refund_fee_to_client ?? 0.0), 3);

        if ($fee <= 0) {
            return null;
        }

        $task = $detail->task;
        $agent = $task?->agent;

        if ($task === null || $agent === null) {
            return null;
        }

        $serviceType = (string) ($task->type ?? '');
        $commissionableTypes = $this->companyOptionJsonArray($companyId, 'accounting.commissionable_fee_types');

        if ($serviceType === '' || ! in_array($serviceType, $commissionableTypes, true)) {
            return null;
        }

        $rate = (float) ($agent->commission ?? 0.0);
        $commission = round($rate * $fee, 3);

        if ($commission == 0.0) {
            return null;
        }

        $absCommission = abs($commission);
        $expenseSide = $commission > 0 ? 'debit' : 'credit';
        $liabilitySide = $commission > 0 ? 'credit' : 'debit';

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($refund->branch_id ?? $agent->branch_id ?? 0),
            docType: 'JV',
            subType: 'AGENT_COMMISSION',
            docDate: $docDate,
            narration: 'Agent commission on refund event fee: '.$refund->refund_number,
            lines: [
                new LineDraft(
                    // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                    purposeCode: 'COMMISSION_EXPENSE',
                    accountId: null,
                    side: $expenseSide,
                    amount: $absCommission,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $absCommission,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_AGENT_COMMISSION_EXPENSE',
                    partyAccountRef: $agent->id,
                    description: 'Agent commission (refund fee) for '.$refund->refund_number,
                    taskId: $task->id,
                    ledgerType: 'expense',
                    partyName: $agent->name,
                ),
                new LineDraft(
                    // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                    purposeCode: 'COMMISSION_PAYABLE',
                    accountId: null,
                    side: $liabilitySide,
                    amount: $absCommission,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $absCommission,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_AGENT_COMMISSION_PAYABLE',
                    partyAccountRef: $agent->id,
                    description: 'Agent commission (refund fee) for '.$refund->refund_number,
                    taskId: $task->id,
                    ledgerType: 'payable',
                    partyName: $agent->name,
                ),
            ],
            idempotencyKey: 'refund:'.$refund->id.':commission-earn:'.$detail->id,
        );

        return $this->posting->post($draft, $userId);
    }

    /**
     * (e) Three-event airline clawback. w4-brief.md §4e:
     *   (i) always: Dr 5125 / Cr airline payable — when an airline commission clawback amount is
     *       entered (`refunds.airline_clawback_amount`). Unconditional, independent of bearer.
     *   (ii) bearer recovery vs 5125, reason_tag=loss — gated behind the SAME P5.13 flag as
     *        W4.A's postAgentLossRecoveryHook() (see postClawbackBearerRecoveryHook() below).
     *   (iii) un-earn — already covered per-detail by postCommissionUnearnForDetail() above; not
     *         duplicated here.
     * A no-op (returns null) when no clawback amount was entered.
     */
    private function postClawback(
        Refund $refund,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId
    ): ?PostedDocument {
        $clawbackAmount = round((float) ($refund->airline_clawback_amount ?? 0.0), 3);

        if ($clawbackAmount <= 0) {
            return null;
        }

        $firstDetail = $refund->refundDetails->first();
        $task = $firstDetail->task;
        $serviceType = (string) ($task?->type ?? '');

        if ($task === null || $task->supplier_id === null) {
            throw new \RuntimeException(
                "RefundPostingService::postClawback(): refund #{$refund->id} has an airline_clawback_amount "
                .'but its first refund_detail has no supplier to book the payable against.'
            );
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($refund->branch_id ?? 0),
            docType: 'DBN',
            subType: 'REFUND_CLAWBACK',
            docDate: $docDate,
            narration: 'Airline refund clawback for '.$refund->refund_number,
            lines: [
                new LineDraft(
                    purposeCode: 'AIRLINE_CLAWBACK_EXPENSE',
                    accountId: null,
                    side: 'debit',
                    amount: $clawbackAmount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $clawbackAmount,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_AIRLINE_CLAWBACK',
                    description: 'Airline clawback: '.$refund->refund_number,
                    taskId: $task->id,
                    ledgerType: 'expense',
                ),
                new LineDraft(
                    purposeCode: 'SERVICE_PAYABLE',
                    accountId: null,
                    side: 'credit',
                    amount: $clawbackAmount,
                    currency: config('accounting.engine.base_currency'),
                    originalAmount: $clawbackAmount,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_AIRLINE_CLAWBACK_PAYABLE',
                    partyAccountRef: $task->supplier_id,
                    description: 'Airline clawback: '.$refund->refund_number,
                    taskId: $task->id,
                    ledgerType: 'payable',
                    partyName: $task->supplier?->name,
                    serviceType: $serviceType,
                ),
            ],
            idempotencyKey: 'refund:'.$refund->id.':clawback',
        );

        $posted = $this->posting->post($draft, $userId);

        $this->postClawbackBearerRecoveryHook($companyId, $refund, $clawbackAmount);

        return $posted;
    }

    /**
     * (e)(ii) — NOT implemented here, deliberately. Mirrors
     * InvoiceController::postAgentLossRecoveryHook() (W4.A) EXACTLY: the real posting shape
     * (bearer split %, reason_tag=loss, which agent leaf it nets against) is P5.13's design, gated
     * behind the SAME `config('accounting.engine.agent_loss_recovery_enabled')` flag — false (the
     * shipped default) is a true no-op; true without P5.13's implementation throws loudly rather
     * than guessing.
     */
    private function postClawbackBearerRecoveryHook(int $companyId, Refund $refund, float $clawbackAmount): void
    {
        if (! (bool) config('accounting.engine.agent_loss_recovery_enabled')) {
            return;
        }

        throw new \RuntimeException(
            'accounting.engine.agent_loss_recovery_enabled is ON but the P5.13 agent-clawback-recovery '
            .'posting (Cr 5125, reason_tag=loss) is not implemented -- '
            .'RefundPostingService::postClawbackBearerRecoveryHook() is a W4.R stub only, matching '
            .'InvoiceController::postAgentLossRecoveryHook() (W4.A). Do not enable this flag before P5.13 ships.'
        );
    }

    /**
     * (f) Disposition of client net. w4-brief.md §4f: "Cr 2632 advance (default) | PV refund-out
     * (bank/cash) | apply to an open invoice". Company option
     * `invoice_overpay_cancel_policy` {credit|refund_out|manual} default credit, honoured via
     * `refunds.disposition` when the caller has set a per-case override (w4-brief.md: "per-case
     * override" — `Refund::$disposition` IS that override column), else falling back to the
     * company default.
     *
     * `refunds.method` (Cash|Bank|Online|Credit) drives disposition per w4-brief.md §4 process
     * decisions: Credit -> Cr 2632; Cash/Bank -> refund_out; Online -> the async gateway-refund
     * listener (this method posts NOTHING for Online — see class docblock; completion is
     * {@see \App\Listeners\Accounting\HandleGatewayRefundStatusChanged}).
     *
     * `refund_out` requires a company-configured payout leaf (purpose code
     * `REFUND_PAYOUT_CASH_BANK`) — if unmapped, `AccountResolver` throws `UnmappedPurposeException`
     * loudly rather than guessing a bank/cash account, exactly the engine's existing "never
     * guesses, refuses instead" convention for every other unmapped purpose code.
     *
     * Idempotent on retry (W4.R verify-fix round 2, finding B): the JV is deduplicated by
     * PostingService::post()'s own idempotency-key short-circuit; the CREDIT disposition's
     * Credit::create() dual-write is separately guarded so a retry after an already-completed
     * post() writes the JV's no-op AND skips the Credit row — see the `$dispositionAlreadyPosted`
     * check below.
     *
     * ── W7.P fix round (refund-disposition-polarity-audit.md, verdict BACKWARDS) — POLARITY
     * CORRECTED ─────────────────────────────────────────────────────────────────────────────────
     * The two line `side` values below are FLIPPED relative to the shape this method shipped with
     * before this fix (`Cr RECEIVABLE_CONTROL` / `Dr {creditPurpose}`) — the exact mistake
     * `TaskStatusService::voidDisposition()` was independently fixed for in W6.U2 (see that
     * method's own docblock), never propagated back here (W4 was explicitly untouched by that
     * fix round; this fix closes that gap). Worked through by running-balance T-account
     * arithmetic (100 sale / 100 full cash payment / 100 credit-disposition refund):
     *   1. Sale: `Dr AR 100 / Cr Revenue 100` — AR=+100.
     *   2. Full payment: `Dr CASH_IN_HAND 100 / Cr AR 100` — AR=0.
     *   3. CRN (`postCrnForDetail()`, `reverse()` of the sale): `Cr AR 100 / Dr Revenue 100` —
     *      AR=0-100=-100 (a CREDIT balance — "the company now owes this client $100 back", the
     *      normal intermediate state after reversing a sale that was already paid off).
     *   4. THIS disposition must clear that -100 credit balance back to zero and land the $100
     *      obligation in 2632. Clearing a CREDIT balance in an asset-normal account (AR) requires
     *      a further DEBIT, not another credit — so the correct shape is `Dr RECEIVABLE_CONTROL /
     *      Cr {creditPurpose}`, producing AR=-100+100=0 and 2632=0-100=-100 (i.e. 2632 nets to a
     *      CREDIT of 100, the correct liability-side balance for "client is owed 100 in credit").
     *      The OLD shape (`Cr RECEIVABLE_CONTROL / Dr {creditPurpose}`) instead ADDED a third
     *      credit on top of an already-credit AR balance (driving it to -200, exactly what the
     *      audit's throwaway test measured) and DEBITED 2632 (driving it to +100 — a debit balance
     *      on a liability account, i.e. a negative liability, instead of the correct +100 credit).
     *   Verified identically for `refund_out` (`Dr AR / Cr REFUND_PAYOUT_CASH_BANK` correctly pays
     *   cash out, leaving AR at 0 and the payout leaf credited by the paid-out amount) and for
     *   `apply` (both lines share `RECEIVABLE_CONTROL`, so the flip is polarity-neutral at the
     *   pooled control-account level but still corrects per-invoice attribution direction — see
     *   the audit's §6).
     */
    private function postDisposition(
        Refund $refund,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId
    ): ?PostedDocument {
        // W4.R verify-fix round 3 (finding #3, MEDIUM): the `$dispositionAlreadyPosted` guard
        // below (verify round 2, finding B) only closes the SEQUENTIAL-retry race -- it is a plain
        // SELECT evaluated BEFORE post() is called, so two genuinely CONCURRENT calls to
        // RefundPostingService::post() for the SAME refund can both read "not yet posted" before
        // either transaction commits, and both then reach Credit::create() below.
        //
        // CHOICE: lock the refund row itself (`Refund::lockForUpdate()`), not a unique index on
        // credits(refund_id, type). Justification: (1) this method already runs inside post()'s
        // own outer DB::transaction() (see that method), so the lock is released automatically at
        // COMMIT/ROLLBACK with no separate cleanup step; (2) `refund_id` is nullable and
        // `credits.type` is a shared enum re-used by TOPUP/INVOICE/INVOICE_REFUND rows for
        // completely different business events, so a unique index on (refund_id, type) would
        // ALSO need to special-case "REFUND type only" and would not, by itself, close the race
        // for the disposition's own JV leg (only PostingService::post()'s own idempotency-key
        // unique index protects that, which already exists and is unaffected by this fix); (3)
        // locking the refund row serializes the ENTIRE critical section (existence check + JV
        // post + Credit dual-write) for that one refund, matching the "two concurrent callers
        // cannot both create the Credit row" requirement exactly, and composes with the OTHER
        // documents post() also writes for the same refund (CRN/recharge/supplier-credit/unearn/
        // clawback) without needing a lock per document.
        //
        // MECHANISM: `lockForUpdate()` is a locking read -- InnoDB locking reads always read the
        // LATEST committed row version regardless of the transaction's own REPEATABLE-READ
        // snapshot (unlike a plain SELECT, which can read a stale snapshot taken before a
        // concurrent transaction committed). The second concurrent caller therefore blocks here
        // until the first caller's transaction commits (releasing the row lock), then proceeds
        // with a lock acquired AFTER that commit -- and the `$dispositionAlreadyPosted` existence
        // check just below is ALSO made a locking read (`->lockForUpdate()`) for the same reason,
        // so it is guaranteed to observe the first caller's now-committed transaction row rather
        // than a pre-commit snapshot.
        Refund::whereKey($refund->id)->lockForUpdate()->first();

        $clientNet = round((float) $refund->refundDetails->sum('total_refund_to_client'), 3);

        if ($clientNet <= 0) {
            return null;
        }

        $firstDetail = $refund->refundDetails->first();
        $task = $firstDetail->task;

        $method = (string) ($refund->method ?? 'Credit');
        $disposition = $refund->disposition
            ?? match ($method) {
                'Cash', 'Bank' => Refund::DISPOSITION_REFUND_OUT,
                'Online' => null, // async — see docblock, nothing posted here.
                default => (string) $this->companyOption(
                    $companyId,
                    'accounting.refund.invoice_overpay_cancel_policy',
                    Refund::DISPOSITION_CREDIT
                ),
            };

        if ($disposition === null) {
            // Online: the gateway-refund listener owns this leg entirely (see class docblock).
            return null;
        }

        $lines = [
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit', // W7.P fix — was 'credit' (BACKWARDS, see method docblock).
                amount: $clientNet,
                currency: config('accounting.engine.base_currency'),
                originalAmount: $clientNet,
                exchangeRate: 1.0,
                transactionType: 'REFUND_DISPOSITION_RECEIVABLE',
                partyAccountRef: $task?->client_id,
                description: 'Refund disposition ('.$disposition.'): '.$refund->refund_number,
                ledgerType: 'receivable',
                partyName: $task?->client?->full_name,
            ),
        ];

        $creditPurpose = match ($disposition) {
            Refund::DISPOSITION_REFUND_OUT => 'REFUND_PAYOUT_CASH_BANK',
            // "apply to an open invoice" is a wash at the pooled RECEIVABLE_CONTROL leaf (AR above
            // is debited to clear the CRN's credit balance, and this second line credits the same
            // control account to record the disposition) — the two lines are still individually
            // attributed (invoiceId below) so per-invoice ledger filters can tell them apart even
            // though the control account's own balance nets to zero.
            Refund::DISPOSITION_APPLY => 'RECEIVABLE_CONTROL',
            default => 'CLIENT_ADVANCE',
        };

        if ($disposition === Refund::DISPOSITION_APPLY && $refund->applied_invoice_id === null) {
            throw new \RuntimeException(
                "RefundPostingService::postDisposition(): refund #{$refund->id} has disposition='apply' "
                .'but no applied_invoice_id was set — cannot determine which open invoice to apply the '
                .'CRN against.'
            );
        }

        $lines[] = new LineDraft(
            purposeCode: $creditPurpose,
            accountId: null,
            side: 'credit', // W7.P fix — was 'debit' (BACKWARDS, see method docblock).
            amount: $clientNet,
            currency: config('accounting.engine.base_currency'),
            originalAmount: $clientNet,
            exchangeRate: 1.0,
            transactionType: 'REFUND_DISPOSITION_'.strtoupper($disposition),
            partyAccountRef: $task?->client_id,
            description: 'Refund disposition ('.$disposition.'): '.$refund->refund_number,
            invoiceId: $disposition === Refund::DISPOSITION_APPLY ? (int) $refund->applied_invoice_id : null,
            ledgerType: $disposition === Refund::DISPOSITION_CREDIT ? 'liability' : 'asset',
            partyName: $task?->client?->full_name,
        );

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($refund->branch_id ?? 0),
            docType: $disposition === Refund::DISPOSITION_CREDIT ? 'JV' : 'PV',
            subType: 'REFUND_DISPO', // transactions.sub_type is varchar(16)
            docDate: $docDate,
            narration: 'Refund client-net disposition ('.$disposition.') for '.$refund->refund_number,
            lines: $lines,
            idempotencyKey: 'refund:'.$refund->id.':disposition',
        );

        // W4.R verify-fix round 2 (finding B, HIGH): the JV posted just below is deduplicated by
        // PostingService::post()'s own idempotency-key short-circuit (step 1 there returns the
        // PRE-EXISTING transaction for this exact key without writing anything new), but the
        // Credit::create() call that follows had no equivalent guard, so calling post() twice on
        // an already-completed CREDIT-disposition refund (a duplicate webhook, a queue redelivery,
        // a double-submitted completeProcess() — none of them idempotency-key-guarded at the HTTP
        // layer) silently wrote a SECOND Credit row for the same 2632 movement, double-crediting
        // the client's balance. Fixed by checking — BEFORE calling post() — whether a transaction
        // already exists under this exact (companyId, idempotencyKey) pair, using the identical
        // lookup PostingService::post() itself performs internally (company-scoped, soft-deleted
        // excluded). When it does, this call is a retry: the JV write below is the same
        // no-op-return-existing path PostingService always takes, and the Credit row (written only
        // once, on the call that actually created the JV) must NOT be written again.
        //
        // W4.R verify-fix round 3 (finding #3, MEDIUM): `->lockForUpdate()` added (a locking read
        // -- see the refund-row lock's own docblock above for why this, not a plain `exists()`,
        // is what actually makes this check see a concurrent caller's just-committed row rather
        // than a pre-commit snapshot). `->first(['id'])` used instead of `->exists()` deliberately
        // -- Laravel's exists()/COUNT(*) compilation wraps the query as `SELECT EXISTS(subquery)`,
        // and whether a lock clause propagates into that subquery is a grammar-compilation detail
        // this fix does not want to depend on; `SELECT ... FOR UPDATE LIMIT 1` (what
        // `first(['id'])` compiles to) unambiguously carries the lock on the exact rows this
        // check reads.
        $dispositionAlreadyPosted = Transaction::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $draft->idempotencyKey)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first(['id']) !== null;

        $posted = $this->posting->post($draft, $userId);

        if ($disposition === Refund::DISPOSITION_CREDIT && ! $dispositionAlreadyPosted) {
            // W4.R verify-fix (finding #1, HIGH): w4-brief.md §4 "credits app-ledger: on engine
            // ON, Credit row is a VIEW of 2632 movements, never a second source of truth (write
            // both in one txn until P3 dedup)". A prior build posted the 2632 JV above but never
            // wrote the App\Models\Credit row that PaymentApplicationService::
            // getAvailableBalanceByRefund() and the client credit-statement views actually read
            // to let this credit be applied to a future invoice. Only the CREDIT disposition
            // touches 2632 at all (refund_out/apply move cash/AR instead), so only that branch
            // dual-writes here — same transaction as the JV above (this whole method runs inside
            // RefundPostingService::post()'s outer DB::transaction()), so the two can never
            // diverge on a partial failure. The `! $dispositionAlreadyPosted` guard above is what
            // makes this a true no-op on retry (see comment above).
            if ($task?->client_id === null) {
                throw new \RuntimeException(
                    "RefundPostingService::postDisposition(): refund #{$refund->id} disposition='credit' "
                    .'but the first refund_detail has no resolvable client_id — cannot write the '
                    .'credits row (credits.client_id is NOT NULL).'
                );
            }

            Credit::create([
                'company_id' => $companyId,
                'branch_id' => (int) ($refund->branch_id ?? 0),
                'client_id' => $task?->client_id,
                'refund_id' => $refund->id,
                'type' => Credit::REFUND,
                'description' => 'Refund credit: '.$refund->refund_number,
                'amount' => $clientNet,
            ]);
        }

        $refund->forceFill([
            'disposition' => $disposition,
            'status' => $disposition === Refund::DISPOSITION_APPLY ? Refund::STATUS_POSTED : Refund::STATUS_COMPLETED,
            'completed_by' => $disposition === Refund::DISPOSITION_APPLY ? null : $userId,
            'completed_at' => $disposition === Refund::DISPOSITION_APPLY ? null : now(),
        ])->save();

        return $posted;
    }

    /** company-scoped Setting lookup — see w4-brief.md §"Owner answers" for the option names. */
    private function companyOption(int $companyId, string $key, mixed $default): mixed
    {
        $setting = Setting::where('company_id', $companyId)->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * Same as {@see companyOption()} but for a JSON-encoded array option (e.g.
     * `accounting.commissionable_fee_types`, written by
     * `SettingController::storeAccountingSettings()` as `json_encode(...)`). Returns an empty
     * array — never null, never a scalar — for a missing setting, malformed JSON, or a JSON value
     * that didn't decode to an array, so callers can always safely `in_array()` against the
     * result.
     */
    private function companyOptionJsonArray(int $companyId, string $key): array
    {
        $decoded = json_decode((string) $this->companyOption($companyId, $key, '[]'), true);

        return is_array($decoded) ? $decoded : [];
    }
}
