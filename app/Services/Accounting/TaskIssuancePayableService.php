<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * CT-A3 wave 1, feeder **E-iss** — the task-issuance supplier-payable accrual the engine did not
 * have. OWNER RULING, 2026-09-09, verbatim:
 *
 *   "anything comes into task where its been issued/vouchered and needs to be paid to supplier we
 *    want to automatically add it to the right account so we know how much we need to pay
 *    regardless of them being invoiced; invoicing them is another story where its accounts
 *    receivable."
 *
 * ── The gap this closes ─────────────────────────────────────────────────────────────────────────
 * CT-A2 §1.4 row 1 and §3.1 row 5: the engine has **no issuance document at all**. Issuance
 * auto-invoices ({@see \App\Services\TaskStatusService::issue()} -> `autoGenerateInvoice()`), so
 * the supplier cost only ever reaches the ledger on the SALE document. On the City Travelers data
 * that leaves 5,495 of 8,706 issued tasks (63%, CT-A1 §0) with no invoice and therefore, under the
 * engine, **no supplier payable anywhere** — the agency cannot see what it owes. The legacy path
 * did carry this event (`TaskController.php:2234`/`:2315`, `type = 'unbilled_cost'` /`'payable'`);
 * the engine dropped it.
 *
 * ── The document ────────────────────────────────────────────────────────────────────────────────
 *   Dr `UNBILLED_SUPPLIER_COST` (1430) = supplier cost      — the asset: work bought, not yet billed
 *   Cr `SERVICE_PAYABLE`/{type}        = supplier cost      — party = the supplier
 *
 * docType `JV`, subType `SUPPLIER_ACCRUAL`, idempotency key `task:{id}:issuance-payable`. Amount is
 * `tasks.total` in the engine's base currency, with the task's own `exchange_rate` captured on the
 * lines when the task carries a foreign currency, so the FX rate is on the row rather than inferred
 * later (CT-A1 CT-F9: 4,553 legacy non-KWD rows carry `exchange_rate = 1`).
 *
 * ── Why 1430 and not COGS ───────────────────────────────────────────────────────────────────────
 * The owner's own matching-principle instruction: "post to 1430 at issuance and reclassify to COGS
 * on invoice; if the task auto-invoices in the same transaction, post straight to COGS". Both
 * halves are honoured without a second reclass document:
 *   - A task that auto-invoices in the same flow never gets an accrual at all
 *     ({@see self::postIfDue()} skips a task that already has a posted sale document), so its cost
 *     goes straight to `SERVICE_COST` on the sale — "straight to COGS".
 *   - A task that does NOT invoice gets the accrual on 1430, and when an invoice is finally raised
 *     the sale document posts its own `SERVICE_COST`/`SERVICE_PAYABLE` pair and this accrual is
 *     REVERSED ({@see self::reverseForTask()}, called from `InvoiceController::
 *     postSaleJournalEntries()`). Accrual-out plus COGS-in *is* the reclassification, expressed as
 *     two audited documents rather than one hand-written JV — and it works identically under the
 *     gross basis owner ruling R-CT1 fixed on the same day.
 * Nothing here ever UPDATEs or deletes a posted line; the reversal goes through
 * {@see PostingService::reverse()}, which posts a dated `REV` document.
 *
 * ── Gating: OWNER RULING R-CT3, "not hold or some supplier confirmed" ───────────────────────────
 * The accrual fires only when the payable is *guaranteed*. That decision is master data, resolved
 * by {@see SupplierPayableRule} from `suppliers.payable_trigger` / `suppliers.payable_hold` against
 * `config('accounting.supplier_payable.triggers')` — never from a supplier name, id or list in this
 * file. See that class's docblock for the full pattern; it is the pattern every future automatic
 * posting follows.
 *
 * Status transitions drive posting, in both directions:
 *   - a held/unconfirmed task that LATER reaches its supplier's committed status posts then, and
 *     the idempotency key makes a second call a no-op;
 *   - a posted task that reaches a reversing status (`void`, `cancelled`, `refund`, `refunded`,
 *     `expired`) has the accrual reversed.
 *
 * When a supplier's RULE changes, tasks already past their new trigger are deliberately **not**
 * retro-posted — that is a data migration with real money attached and belongs to CT-A5. This
 * service logs the fact and leaves it.
 *
 * ── Posts direct, not through the seam ──────────────────────────────────────────────────────────
 * Same convention as every other TaskStatusService-era feeder that has no legacy counterpart to
 * preserve ({@see \App\Services\TaskStatusService::applyHoldDepositToInvoice()}'s own docblock):
 * there is no OFF-path behaviour for a document that did not exist before, so a `PostingSeam`
 * `$legacy` closure would have nothing to run. Callers invoke this only when the engine is already
 * confirmed ON for the company, and {@see self::postIfDue()} re-checks that itself.
 */
final class TaskIssuancePayableService
{
    public function __construct(
        private readonly SupplierPayableRule $rule,
        private readonly PostingService $posting,
        private readonly PostingSeam $seam,
    ) {}

    public static function idempotencyKeyFor(int $taskId): string
    {
        return 'task:'.$taskId.':issuance-payable';
    }

    /**
     * The one entry point. Decides, then posts / reverses / does nothing — and always logs which,
     * with the reason, so an operator can answer "why is this task not in my AP?" from the log
     * alone.
     *
     * @return PostedDocument|null the accrual document when one was posted (or already existed);
     *                             null in every other case, including a deliberate skip
     */
    public function postIfDue(Task $task): ?PostedDocument
    {
        $companyId = (int) $task->company_id;

        if ($companyId <= 0 || ! $this->seam->isEnabledFor($companyId)) {
            return null;
        }

        $supplier = $task->supplier_id ? Supplier::find($task->supplier_id) : null;
        $decision = $this->rule->decide($task, $supplier);

        // NOTE (CT-A3 wave-1 server-replay finding): the two later `skipped` logs below OVERRIDE
        // `reason` via array_merge, not the `+` union operator — PHP's `+` keeps the LEFT
        // operand's key, so `$context + ['reason' => 'already_invoiced']` silently kept the
        // decision's own 'committed' and the operator could not tell the two apart in the log.
        $context = array_merge($decision->toLogContext(), [
            'task_id' => $task->id,
            'company_id' => $companyId,
            'supplier_id' => $supplier?->id,
        ]);

        if ($decision->shouldReverse) {
            // CT-A3 wave 2 (W2-3). A VOID / CANCELLED / EXPIRED task never happened, so its
            // accrual comes straight off. A REFUNDED task did happen, and whether the supplier
            // gives the money back is R-CT3's question, not an assumption -- see
            // {@see self::settleAccrualOnRefund()}.
            if ($this->isRefundStatus($decision->status)) {
                $this->settleAccrualOnRefund($task, $context);
            } else {
                $this->reverseForTask($task);
            }

            return null;
        }

        if (! $decision->shouldPost) {
            Log::debug('accounting.supplier_payable.skipped', $context);

            return null;
        }

        $amount = round((float) ($task->total ?? 0), 3);

        // CT-A3 wave 2 (W2-1): the two remaining skip reasons -- 'zero_supplier_cost' and
        // 'already_invoiced' (the owner's "if the task auto-invoices in the same transaction,
        // post straight to COGS": its cost is already on the sale document's own
        // SERVICE_COST / SERVICE_PAYABLE pair, so accruing here would double the payable) -- are
        // decided by {@see self::amountSkipReason()}, the ONE implementation
        // {@see self::reasonFor()} also consults. Before wave 2 they were inline here and
        // `accounting:replay` had to re-derive them to report why a task did not accrue; two
        // copies of a decision this delicate is exactly how a report and a ledger drift apart.
        $amountReason = $this->amountSkipReason($task, $companyId, $amount);

        if ($amountReason !== null) {
            Log::debug('accounting.supplier_payable.skipped', array_merge($context, ['reason' => $amountReason]));

            return null;
        }

        // CT-A3 verify R1. Falling through to post() here would hand back the REVERSED header that
        // still occupies this key (step 1 does not filter `posting_status`), and the success line
        // below would then log `accounting.supplier_payable.posted` naming a document worth zero —
        // the feeder claiming a payable it did not create. {@see self::restoreForTask()} is the
        // only thing that can put this accrual back; say so instead.
        [$existingAccrual, $accrualIsLive] = $this->accrualChainTip($companyId, (int) $task->id);

        if ($existingAccrual !== null && ! $accrualIsLive) {
            Log::warning('accounting.supplier_payable.accrual_reversed', array_merge($context, [
                'reason' => 'accrual_reversed',
                'transaction_id' => $existingAccrual->id,
            ]));

            return null;
        }

        // FX. `tasks.total` is already the BASE-currency figure; `tasks.original_total` /
        // `original_currency` (falling back to `exchange_currency`) carry what the supplier
        // actually billed. PostingService step 3f's convention is
        // `amount (base) ~= originalAmount (FC) x exchangeRate` and it REFUSES a base-currency
        // line whose rate is not exactly 1.0 — so a real rate can only be carried by tagging the
        // line with the foreign currency and its own FC amount, which is exactly what CT-F9's
        // 4,553 legacy rows (non-KWD stamped, exchange_rate = 1) failed to do. When the task
        // carries no usable FC pair, the line stays cleanly base-currency at rate 1.0 rather than
        // inventing one.
        $base = strtoupper((string) config('accounting.engine.base_currency'));
        $taskCurrency = strtoupper(trim((string) ($task->original_currency ?: $task->exchange_currency ?: '')));
        $originalTotal = round((float) ($task->original_total ?? 0), 3);
        $taskRate = (float) ($task->exchange_rate ?: 0);

        $isForeign = $taskCurrency !== '' && $taskCurrency !== $base && $originalTotal > 0 && $taskRate > 0;

        // The FX columns must actually agree before they are trusted. PostingService step 3f
        // asserts `amount (base) ~= originalAmount (FC) x exchangeRate` and REFUSES the document
        // otherwise. On the City Travelers data that assertion is not safe to assume: the first
        // server replay of this feeder refused 25 tasks with FcConsistencyException because their
        // `original_total` is a copy of `total` (already base) while `exchange_rate` carries a real
        // rate — e.g. amount 368.000, originalAmount 368.000, rate 0.340000, which implies 125.120.
        // That is CT-F9's corruption (4,553 legacy non-KWD rows stamped at rate 1) seen from the
        // other side, and it must not cost the agency a payable it genuinely owes. When the triple
        // does not reconcile within PostingService's OWN tolerance, the line stays honestly
        // base-currency at rate 1.0 and the inconsistency is logged for CT-A5 rather than silently
        // "corrected" by inventing a converted figure.
        if ($isForeign) {
            $expected = round($originalTotal * $taskRate, 3);
            $fcTolerance = max(0.01, abs($amount) * 0.01);

            if (abs($amount - $expected) > $fcTolerance) {
                Log::warning('accounting.supplier_payable.fx_inconsistent', $context + [
                    'amount' => $amount,
                    'original_total' => $originalTotal,
                    'original_currency' => $taskCurrency,
                    'exchange_rate' => $taskRate,
                    'implied_base' => $expected,
                ]);

                $isForeign = false;
            }
        }

        $currency = $isForeign ? $taskCurrency : $base;
        $originalAmount = $isForeign ? $originalTotal : $amount;
        $exchangeRate = $isForeign ? $taskRate : 1.0;

        $supplierName = $supplier?->name;
        $narration = 'Unbilled supplier cost at issuance: '.$task->reference;

        $draft = new DocumentDraft(
            companyId: $companyId,
            // `tasks` has no branch_id column — the branch comes from the task's agent, the same
            // resolution every other TaskStatusService feeder uses (`:624`, `:1274`, `:1598`).
            branchId: (int) ($task->agent?->branch_id ?? 0),
            docType: 'JV',
            subType: 'SUPPLIER_ACCRUAL',
            docDate: $this->accrualDate($task),
            narration: $narration,
            lines: [
                new LineDraft(
                    purposeCode: 'UNBILLED_SUPPLIER_COST',
                    accountId: null,
                    side: 'debit',
                    amount: $amount,
                    currency: $currency,
                    originalAmount: $originalAmount,
                    exchangeRate: $exchangeRate,
                    transactionType: 'UNBILLED_SUPPLIER_COST',
                    partyAccountRef: $supplier?->id,
                    description: $narration,
                    taskId: $task->id,
                    ledgerType: 'asset',
                    partyName: $supplierName,
                ),
                new LineDraft(
                    purposeCode: 'SERVICE_PAYABLE',
                    accountId: null,
                    side: 'credit',
                    amount: $amount,
                    currency: $currency,
                    originalAmount: $originalAmount,
                    exchangeRate: $exchangeRate,
                    transactionType: 'SUPPLIERCREDITED',
                    partyAccountRef: $supplier?->id,
                    description: 'Records payable to supplier at issuance: '.$task->reference,
                    serviceType: (string) $task->type,
                    taskId: $task->id,
                    ledgerType: 'payable',
                    partyName: $supplierName,
                ),
            ],
            idempotencyKey: self::idempotencyKeyFor((int) $task->id),
            sourceType: 'Payment',
            sourceId: $task->id,
            userId: Auth::id(),
        );

        $posted = $this->posting->post($draft);

        Log::info('accounting.supplier_payable.posted', $context + [
            'amount' => $amount,
            'transaction_id' => $posted->transaction->id,
        ]);

        return $posted;
    }

    /**
     * CT-A3 wave 2 (W2-3) -- the refund half of a reversing status, under owner ruling R-CT3.
     *
     * An uninvoiced task's supplier cost sits in `1430 Unbilled Supplier Cost` against a
     * `SERVICE_PAYABLE` credit ({@see self::postIfDue()}). When that task is refunded there are
     * two genuinely different outcomes, and before wave 2 the feeder assumed the first one always:
     *
     *   - **The supplier refunds.** The agency no longer owes it and no longer holds the asset:
     *     the accrual is REVERSED, Dr payable / Cr 1430, as a dated REV document.
     *   - **The supplier does not refund** (its configured `refund_trigger` says so, it is on
     *     `refund_hold`, or the task has only been *asked* for a refund and not confirmed one).
     *     The agency still owes the supplier -- so the payable STAYS -- but the 1430 balance is no
     *     longer an asset, because there is no longer a sale it will ever be billed against. It is
     *     a loss. Reclassified onto `5131 Supplier Refund Loss` by its own document, keyed
     *     `task:{id}:refund-loss`, so a later confirmation can still reverse the accrual normally
     *     and the loss line is visible instead of the cost quietly staying an asset forever.
     *
     * This is the uninvoiced mirror of what {@see RefundPostingService::postSupplierCreditForDetail()}
     * does for an invoiced one -- {@see RefundPostingService} refuses a detail with no
     * `invoice_detail` at all, so without this an uninvoiced refunded task had no path.
     */
    private function settleAccrualOnRefund(Task $task, array $context): void
    {
        $companyId = (int) $task->company_id;
        $supplier = $task->supplier_id ? Supplier::find($task->supplier_id) : null;
        $detail = \App\Models\RefundDetail::where('task_id', $task->id)->orderByDesc('id')->first();

        $decision = app(SupplierRefundRule::class)->decide($task, $supplier, $detail);

        Log::info('accounting.supplier_refund.decided', array_merge($decision->toLogContext(), [
            'task_id' => $task->id,
            'company_id' => $companyId,
            'supplier_id' => $supplier?->id,
            'source' => 'task_issuance_accrual',
        ]));

        if ($decision->shouldRecover) {
            // CT-A3 verify R1, defect 1 of 2 in this branch. An earlier dispatch at an
            // UNCONFIRMED refund status may already have posted `task:{id}:refund-loss`, which
            // credited 1430 to zero and debited 5131. Reversing the accrual on top of that credits
            // 1430 a SECOND time: the asset goes to MINUS the cost and a loss that never happened
            // is left standing on 5131. This is precisely the sequence
            // {@see self::settleAccrualOnRefund()}'s own docblock advertises as supported ("so a
            // later confirmation can still reverse the accrual normally"), and it was the one
            // sequence no wave-2 case drove. The loss comes off first, restoring 1430 to the
            // accrued cost, and the accrual reversal below then takes it cleanly to zero.
            $this->reverseRefundLossIfPosted($task);

            $this->reverseForTask($task);

            // CT-A3 verify R1, defect 2 of 2. `shouldRecover` is `recoverable > 0`, so a supplier
            // that refunds 60 of a 100 cost and KEEPS a 40 penalty landed here — and the blanket
            // reversal above took the payable to ZERO. The agency still owes that 40. The invoiced
            // mirror gets this right ({@see RefundPostingService::postSupplierCreditForDetail()}:
            // "the supplier payable goes to the NON-RECOVERABLE part … the penalty kept"); the
            // uninvoiced mirror did not. Same end state, same account choice.
            $this->postRetainedSupplierPenalty($task, $decision, $supplier);

            return;
        }

        [$existing, $accrualIsLive] = $this->accrualChainTip($companyId, (int) $task->id);

        if ($existing === null || ! $accrualIsLive) {
            // Nothing was ever accrued for this task, so there is no asset to reclassify.
            Log::debug('accounting.supplier_refund.no_accrual_to_settle', $context);

            return;
        }

        $amount = round($decision->nonRecoverableAmount, 3);

        if ($amount <= (float) config('accounting.engine.balance_tolerance', 0.0005)) {
            return;
        }

        $narration = 'Unrecovered supplier cost on refunded task: '.$task->reference;

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($task->agent?->branch_id ?? 0),
            docType: 'JV',
            subType: 'REFUND_LOSS',
            docDate: Carbon::now(),
            narration: $narration,
            lines: [
                new LineDraft(
                    purposeCode: 'SUPPLIER_REFUND_LOSS',
                    accountId: null,
                    side: 'debit',
                    amount: $amount,
                    currency: (string) config('accounting.engine.base_currency'),
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_SUPPLIER_UNRECOVERED',
                    partyAccountRef: $supplier?->id,
                    description: $narration.' ('.$decision->reason.')',
                    taskId: $task->id,
                    ledgerType: 'expense',
                    partyName: $supplier?->name,
                ),
                new LineDraft(
                    purposeCode: 'UNBILLED_SUPPLIER_COST',
                    accountId: null,
                    side: 'credit',
                    amount: $amount,
                    currency: (string) config('accounting.engine.base_currency'),
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'UNBILLED_SUPPLIER_COST',
                    partyAccountRef: $supplier?->id,
                    description: $narration,
                    taskId: $task->id,
                    ledgerType: 'asset',
                    partyName: $supplier?->name,
                ),
            ],
            idempotencyKey: 'task:'.$task->id.':refund-loss',
            sourceType: 'Refund',
            sourceId: $task->id,
            userId: Auth::id(),
        );

        $posted = $this->posting->post($draft);

        Log::info('accounting.supplier_refund.loss_posted', array_merge($decision->toLogContext(), [
            'task_id' => $task->id,
            'company_id' => $companyId,
            'transaction_id' => $posted->transaction->id,
            'amount' => $amount,
        ]));
    }

    /**
     * CT-A3 verify R1. Takes a previously posted `task:{id}:refund-loss` back off the ledger, so a
     * supplier confirmation arriving after an unconfirmed refund does not relieve 1430 twice. A
     * no-op when no loss was ever posted, or when it has already been reversed.
     */
    private function reverseRefundLossIfPosted(Task $task): void
    {
        $loss = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', (int) $task->company_id)
            ->where('idempotency_key', 'task:'.$task->id.':refund-loss')
            ->where('posting_status', 'posted')
            ->first();

        if ($loss === null) {
            return;
        }

        $this->posting->reverse($loss, Carbon::now(), Auth::id());

        Log::info('accounting.supplier_refund.loss_reversed', [
            'task_id' => $task->id,
            'company_id' => (int) $task->company_id,
            'transaction_id' => $loss->id,
        ]);
    }

    /**
     * CT-A3 verify R1. The part of the cost the supplier KEPT out of a refund it did make. The
     * accrual reversal that precedes this call took the payable to zero, but the agency still owes
     * the retained penalty — so it is put back, against the same `PENALTY_COST_EXPENSE` (5124) the
     * invoiced mirror uses for exactly this amount:
     *
     *   Dr PENALTY_COST_EXPENSE = nonRecoverable   — the price of a refund that happened
     *       Cr SERVICE_PAYABLE/{type} = nonRecoverable   — party = the supplier
     *
     * A no-op on a full recovery (nothing retained), which is why every wave-2 case is unaffected.
     */
    private function postRetainedSupplierPenalty(Task $task, SupplierRefundDecision $decision, ?Supplier $supplier): void
    {
        $amount = round($decision->nonRecoverableAmount, 3);

        if ($amount <= (float) config('accounting.engine.balance_tolerance', 0.0005)) {
            return;
        }

        $currency = (string) config('accounting.engine.base_currency');
        $narration = 'Supplier charge retained on refunded task: '.$task->reference;

        $posted = $this->posting->post(new DocumentDraft(
            companyId: (int) $task->company_id,
            branchId: (int) ($task->agent?->branch_id ?? 0),
            docType: 'JV',
            subType: 'REFUND_PENALTY',
            docDate: Carbon::now(),
            narration: $narration,
            lines: [
                new LineDraft(
                    purposeCode: 'PENALTY_COST_EXPENSE',
                    accountId: null,
                    side: 'debit',
                    amount: $amount,
                    currency: $currency,
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'REFUND_SUPPLIER_CREDIT_PENALTY',
                    description: $narration.' ('.$decision->reason.')',
                    taskId: $task->id,
                    ledgerType: 'expense',
                ),
                new LineDraft(
                    purposeCode: 'SERVICE_PAYABLE',
                    accountId: null,
                    side: 'credit',
                    amount: $amount,
                    currency: $currency,
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'SUPPLIERCREDITED',
                    partyAccountRef: $supplier?->id,
                    description: $narration,
                    serviceType: (string) $task->type,
                    taskId: $task->id,
                    ledgerType: 'payable',
                    partyName: $supplier?->name,
                ),
            ],
            idempotencyKey: 'task:'.$task->id.':refund-penalty',
            sourceType: 'Refund',
            sourceId: $task->id,
            userId: Auth::id(),
        ));

        Log::info('accounting.supplier_refund.penalty_retained', array_merge($decision->toLogContext(), [
            'task_id' => $task->id,
            'company_id' => (int) $task->company_id,
            'transaction_id' => $posted->transaction->id,
            'amount' => $amount,
        ]));
    }

    /**
     * Is this reversing status a REFUND (the supplier may or may not give the money back), as
     * opposed to a void/cancellation (nothing happened, the accrual simply comes off)? Read from
     * `config('accounting.supplier_refund.triggers')` -- the union of every status any trigger
     * treats as a refund state -- so the two blocks cannot drift apart.
     */
    private function isRefundStatus(string $status): bool
    {
        $all = [];

        foreach ((array) config('accounting.supplier_refund.triggers', []) as $statuses) {
            foreach ((array) $statuses as $s) {
                $all[] = strtolower(trim((string) $s));
            }
        }

        return in_array(strtolower(trim($status)), $all, true);
    }

    /**
     * CT-A3 wave 2 (W2-1). Why this task would, or would not, accrue right now -- WITHOUT posting
     * anything. One of:
     *
     *   `engine_off` | `reversing_status` | `no_supplier_on_task` | `supplier_payable_hold` |
     *   `trigger_manual` | `status_not_committed` | `no_voucher_raised` | `zero_supplier_cost` |
     *   `already_invoiced` | `due`
     *
     * The first seven come straight from {@see SupplierPayableRule::decide()}; the last three are
     * this service's own. {@see self::postIfDue()} consults the SAME two helpers, so a report
     * built on this method can never disagree with what the feeder actually did -- which is the
     * whole point: `accounting:replay --class=issuance` prints its NOT_DUE breakdown from here,
     * and on the City Travelers data that breakdown IS the R-CT3 ruling ("not hold or some
     * supplier confirmed") made auditable.
     */
    public function reasonFor(Task $task): string
    {
        $companyId = (int) $task->company_id;

        if ($companyId <= 0 || ! $this->seam->isEnabledFor($companyId)) {
            return 'engine_off';
        }

        $supplier = $task->supplier_id ? Supplier::find($task->supplier_id) : null;
        $decision = $this->rule->decide($task, $supplier);

        if ($decision->shouldReverse) {
            return 'reversing_status';
        }

        if (! $decision->shouldPost) {
            return $decision->reason;
        }

        $amount = round((float) ($task->total ?? 0), 3);

        $amountReason = $this->amountSkipReason($task, $companyId, $amount);

        if ($amountReason !== null) {
            return $amountReason;
        }

        // CT-A3 verify R1. `due` used to be returned here for a task whose accrual EXISTS but is
        // reversed — and `postIfDue()` then posts nothing at all, because the idempotency key is
        // still occupied by that reversed header. An operator asking this method's own documented
        // question ("why is this task not in my AP?") was told the payable was due when nothing
        // could ever post it. {@see self::restoreForTask()} is the path that puts it back.
        [$existing, $accrualIsLive] = $this->accrualChainTip($companyId, (int) $task->id);

        if ($existing !== null && ! $accrualIsLive) {
            return 'accrual_reversed';
        }

        return 'due';
    }

    /**
     * The two amount/lifecycle skips that survive a positive {@see SupplierPayableRule} verdict,
     * or null when neither applies. Extracted (CT-A3 wave 2) so {@see self::postIfDue()} and
     * {@see self::reasonFor()} share one implementation rather than two that can drift.
     */
    private function amountSkipReason(Task $task, int $companyId, float $amount): ?string
    {
        if ($amount <= (float) config('accounting.engine.balance_tolerance', 0.0005)) {
            return 'zero_supplier_cost';
        }

        if ($this->hasPostedSaleDocument($task, $companyId)) {
            return 'already_invoiced';
        }

        return null;
    }

    /**
     * Reverses this task's accrual when one exists — on a cancel/void/refund, and on the sale
     * document that finally bills the task (the "reclassify to COGS on invoice" half of the
     * ruling). A no-op when nothing was ever accrued, so every caller can invoke it blindly.
     */
    public function reverseForTask(Task $task): void
    {
        $companyId = (int) $task->company_id;

        if ($companyId <= 0) {
            return;
        }

        // CT-A3 verify R1. Was: a single lookup for the accrual header at
        // `posting_status = 'posted'`. That is correct only at chain depth 0. Once
        // {@see self::restoreForTask()} has put a reversed accrual back — which it does by
        // reversing the REVERSAL, the only mechanism available, because the idempotency key stays
        // occupied forever — the accrual header itself is permanently stamped `reversed` while its
        // BALANCE is live again. The old lookup then found nothing and a second void left the
        // supplier payable standing on a voided booking. Both directions now read the same chain.
        [$tip, $accrualIsLive] = $this->accrualChainTip($companyId, (int) $task->id);

        if ($tip === null || ! $accrualIsLive) {
            return;
        }

        $this->posting->reverse($tip, Carbon::now(), Auth::id());

        Log::info('accounting.supplier_payable.reversed', [
            'task_id' => $task->id,
            'company_id' => $companyId,
            'transaction_id' => $tip->id,
            'task_status' => strtolower(trim((string) $task->status)),
        ]);
    }

    /**
     * CT-A3 verify R1 — the mirror of {@see self::reverseForTask()}, and the fix for a payable
     * that could never come back.
     *
     * A reversed accrual can NOT be re-posted under its own key: {@see PostingService::post()}
     * step 1 returns the existing header on a duplicate `(company_id, idempotency_key)` and
     * `findByIdempotencyKey()` deliberately does not filter `posting_status`, so
     * `task:{id}:issuance-payable` stays occupied by the reversed document for the life of the
     * task. `postIfDue()` therefore silently hands back the REVERSED document and posts nothing,
     * while `reasonFor()` still answers `due`.
     *
     * That matters because two live paths reverse an accrual on an event that can be UNDONE:
     *
     *   1. {@see \App\Services\TaskStatusService::void()} reverses it on a void — and
     *      {@see \App\Http\Controllers\TaskController::revertFinancialsForVoid()} un-voids by
     *      restoring the sale and the commission, but named no key for the accrual, so an
     *      un-voided uninvoiced booking owed its supplier nothing for ever after.
     *   2. {@see \App\Http\Controllers\InvoiceController::postSaleJournalEntries()} reverses it
     *      when the sale finally bills the task — and deleting that invoice reverses the sale
     *      without putting the accrual back, leaving an issued, now-uninvoiced task with no
     *      supplier payable anywhere. (`hasPostedSaleDocument()` is deliberately not filtered to
     *      `posted`, so the task also reports `already_invoiced` for ever — the two halves of
     *      wave 2 §5.8's fix meeting from opposite sides.)
     *
     * The restore is the same REV-of-REV the un-void path already uses for the sale: reverse the
     * REVERSAL, which is itself a live posted document. A no-op when the task was never accrued,
     * or when its accrual is already live.
     */
    public function restoreForTask(Task $task): void
    {
        $companyId = (int) $task->company_id;

        if ($companyId <= 0) {
            return;
        }

        [$tip, $accrualIsLive] = $this->accrualChainTip($companyId, (int) $task->id);

        if ($tip === null || $accrualIsLive) {
            return;
        }

        $this->posting->reverse($tip, Carbon::now(), Auth::id());

        Log::info('accounting.supplier_payable.restored', [
            'task_id' => $task->id,
            'company_id' => $companyId,
            'transaction_id' => $tip->id,
            'task_status' => strtolower(trim((string) $task->status)),
        ]);
    }

    /**
     * The newest live document in this task's accrual reversal chain, and whether the ACCRUAL's
     * balance is currently on the ledger.
     *
     * The chain is `A` (the accrual) -> `R1 = rev(A)` -> `R2 = rev(R1)` -> …, each link recorded
     * by `transactions.reversal_of_transaction_id` and each predecessor stamped
     * `posting_status = 'reversed'` by {@see PostingService::reverse()}. The net ledger effect
     * alternates, so an even depth means the accrual is live and an odd depth means it is off.
     *
     * @return array{0: Transaction|null, 1: bool} the tip (null when nothing was ever accrued) and
     *                                             whether the accrual is currently live
     */
    private function accrualChainTip(int $companyId, int $taskId): array
    {
        $doc = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', self::idempotencyKeyFor($taskId))
            ->first();

        if ($doc === null) {
            return [null, false];
        }

        $accrualIsLive = true;

        // Bounded: a chain this long is a bug elsewhere, and an unbounded walk over a cycle in
        // `reversal_of_transaction_id` would hang a status change.
        for ($depth = 0; $depth < 64 && $doc->posting_status === 'reversed'; $depth++) {
            $next = Transaction::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('reversal_of_transaction_id', $doc->id)
                ->orderByDesc('id')
                ->first();

            if ($next === null) {
                break;
            }

            $doc = $next;
            $accrualIsLive = ! $accrualIsLive;
        }

        return [$doc, $accrualIsLive];
    }

    /**
     * True once ANY `invoice-detail:{id}:sale` document exists for this task — i.e. the sale
     * document already carries the supplier cost, so no accrual belongs on top of it.
     *
     * Keyed on `journal_entries.task_id` + an engine sale document rather than on
     * `invoice_details` alone, because an invoice_details row can exist before its sale document
     * posts (`autoGenerateInvoice()` writes the detail first). Asking the ledger, not the source
     * table, is the only question that cannot answer "yes" before the money is actually there.
     *
     * ── Deliberately NOT filtered to `posting_status = 'posted'` (CT-A3 wave 2) ─────────────────
     * A refund or a void REVERSES the sale document, which flips its `posting_status` to
     * `reversed`. With the filter, such a task looked UNINVOICED again — and if its
     * `tasks.status` was not itself a reversing status (a refund is very often recorded against a
     * task still sitting at `issued`), the next dispatch or replay accrued a brand-new supplier
     * payable for a booking that had just been refunded. The second `accounting:replay` run on the
     * City Travelers scratch database did exactly that: 5 fresh accruals on tasks 13561, 13691,
     * 13692, 13949 and 14056, and 5 cascading reassignments behind them, breaking the "a re-run
     * posts zero" property this command is built on.
     *
     * The question this method asks is "was this task ever invoiced", not "is its sale currently
     * live" — a reversed sale still means the cost went to COGS once, and whatever undid it
     * (`RefundPostingService`, `TaskStatusService::void()`) also undid the cost. This is the same
     * reasoning, for the same reason, that {@see RefundPostingService::postCrnForDetail()} already
     * documents at its own sale lookup: *"Deliberately NOT filtered to posting_status='posted': on
     * a retry the sale's own status is by then 'reversed'."*
     */
    private function hasPostedSaleDocument(Task $task, int $companyId): bool
    {
        return Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('doc_type', 'INV')
            ->where('sub_type', 'SALE')
            ->whereExists(function ($q) use ($task) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('journal_entries')
                    ->whereColumn('journal_entries.transaction_id', 'transactions.id')
                    ->whereNull('journal_entries.deleted_at')
                    ->where('journal_entries.task_id', $task->id);
            })
            ->exists();
    }

    /**
     * The document date. `issued_date` is the economic event; `supplier_pay_date` and `created_at`
     * are the documented fallbacks the legacy writer used for this same accrual
     * (`TaskController.php:5751`'s own `supplier_pay_date ?? issued_date ?? created_at` chain),
     * kept so a backfill lands on the same period the legacy row did.
     */
    private function accrualDate(Task $task): \DateTimeInterface
    {
        $raw = $task->issued_date ?? $task->supplier_pay_date ?? $task->created_at;

        return $raw ? Carbon::parse($raw) : Carbon::now();
    }
}
