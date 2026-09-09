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

        $context = array_merge($decision->toLogContext(), [
            'task_id' => $task->id,
            'company_id' => $companyId,
            'supplier_id' => $supplier?->id,
        ]);

        if ($decision->shouldReverse) {
            $this->reverseForTask($task);

            return null;
        }

        if (! $decision->shouldPost) {
            Log::debug('accounting.supplier_payable.skipped', $context);

            return null;
        }

        $amount = round((float) ($task->total ?? 0), 3);

        if ($amount <= (float) config('accounting.engine.balance_tolerance', 0.0005)) {
            Log::debug('accounting.supplier_payable.skipped', $context + ['reason' => 'zero_supplier_cost']);

            return null;
        }

        // The task auto-invoiced in the same flow: its cost is already on the sale document's own
        // SERVICE_COST / SERVICE_PAYABLE pair, so accruing here would double the payable. This is
        // the owner's "if the task auto-invoices in the same transaction, post straight to COGS".
        if ($this->hasPostedSaleDocument($task, $companyId)) {
            Log::debug('accounting.supplier_payable.skipped', $context + ['reason' => 'already_invoiced']);

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

        $existing = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', self::idempotencyKeyFor((int) $task->id))
            ->where('posting_status', 'posted')
            ->first();

        if ($existing === null) {
            return;
        }

        $this->posting->reverse($existing, Carbon::now(), Auth::id());

        Log::info('accounting.supplier_payable.reversed', [
            'task_id' => $task->id,
            'company_id' => $companyId,
            'transaction_id' => $existing->id,
            'task_status' => strtolower(trim((string) $task->status)),
        ]);
    }

    /**
     * True once ANY `invoice-detail:{id}:sale` document exists for this task — i.e. the sale
     * document already carries the supplier cost, so no accrual belongs on top of it.
     *
     * Keyed on `journal_entries.task_id` + a posted engine document rather than on
     * `invoice_details` alone, because an invoice_details row can exist before its sale document
     * posts (`autoGenerateInvoice()` writes the detail first). Asking the ledger, not the source
     * table, is the only question that cannot answer "yes" before the money is actually there.
     */
    private function hasPostedSaleDocument(Task $task, int $companyId): bool
    {
        return Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('posting_status', 'posted')
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
