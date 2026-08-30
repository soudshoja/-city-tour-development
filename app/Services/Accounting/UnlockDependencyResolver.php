<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * P2.5.E (p2_5-brief.md §P2.5.E; period-lock-design.md §8.2's dependency-aware unlock; owner
 * refinement 2026-08-30). Walks the chain the brief names literally -- "invoice -> applications/
 * allocations -> receipts -> reconciled bank lines -> period, plus any reversal/repost documents
 * pointing at the record" -- and returns a flat, ORDERED list of every node actually encountered
 * along a BLOCKING path. {@see \App\Http\Traits\Lockable::unlock()} refuses (throws
 * {@see \App\Exceptions\Accounting\UnlockDependencyBlockedException}) whenever this list is
 * non-empty; the same list is what the unlock modal renders as a dependency tree (owner
 * refinement: "must SHOW the dependencies, not just refuse") and what the JSON refusal response
 * carries verbatim as `blockers[]`.
 *
 * ── What counts as "blocking" (period-lock-design.md §8.2's own wording) ─────────────────────────
 * "An unlock request ... is refused if any downstream descendant is itself locked or reconciled,
 * or sits inside a closed accounting period" -- mere EXISTENCE of an application/receipt is never
 * blocking on its own (the owner's own worked example: "Editing an invoice whose only line is
 * already reconciled must be refused -- the dependency check stops at the reconciled leaf"). A
 * node with none of those three properties, and none of ITS descendants having them either, is
 * simply omitted from the list -- it never appears as `status: 'posted'`. `status: 'posted'` is
 * reserved for exactly two cases this class emits: (a) the `application`/`allocation`/`receipt`
 * CONTEXT node one level above an actual blocking leaf (so the UI can render the full path, not
 * just the leaf, per the owner's "dependency tree" requirement), and (b) a `reversal` node, which
 * is inherently "already posted" and blocks by its mere existence -- a reversal/repost having
 * already happened is precisely the signal that unlock is the wrong tool (design doc §8.2: "Unlock
 * is the exception, never the correction path").
 *
 * ── Global scopes are deliberately bypassed ────────────────────────────────────────────────────
 * `Transaction`/`JournalEntry`/`Payment` each carry a `company`-keyed global scope resolved off
 * `Auth::user()` (role-dependent for `Transaction`, `getCompanyId()`-based for the other two via
 * `App\Traits\BelongsToCompany`). This class is invoked only AFTER
 * {@see \App\Http\Traits\Lockable::assertUnlockAuthorized()} has already authorized the acting
 * user for this specific record, so it queries with `withoutGlobalScope('company')` throughout --
 * the chain must reflect the TRUE downstream state regardless of which role happens to be logged
 * in (or none, e.g. a console caller), not whatever slice that user's own scope would show.
 *
 * ── Chain coverage, and what is deliberately NOT walked (scope boundary, not an oversight) ───────
 * Covered: (1) the invoice's own accounting period; (2) reversal/repost `Transaction` rows whose
 * `reversal_of_transaction_id` points at one of the invoice's own transactions; (3) `PaymentApplication`
 * rows keyed to this invoice directly (`invoice_id` -- type `application`) or to one of its
 * `InvoicePartial`s (`invoice_partial_id` -- type `allocation`, since a partial IS a slice/
 * allocation of the invoice, not the whole document), walked to the underlying `Payment` or
 * `Credit`; (3.5) `Payment` rows with a DIRECT `invoice_id` (the pre-`PaymentApplication`,
 * single-payment-per-invoice legacy shape -- {@see \App\Models\Invoice::payment()}, a `hasOne` on
 * this same column) not already covered by a step-(3) application, surfaced as a top-level
 * `receipt` node with no application/allocation layer above it; (4) `InvoicePartial` rows with a
 * DIRECT `payment_id` and no `PaymentApplication` row at all (a real shape in this legacy schema --
 * multiple write paths pre-date the engine); (5) `InvoiceReceipt` rows linked directly via
 * `invoice_id`, `invoice_partial_id`, or `InvoicePartial::$receipt_voucher_id` (type `receipt`).
 *
 * NOT walked, deferred with the reason recorded here rather than silently: `InvoiceReceipt::
 * $allocations` (a JSON column used to split ONE receipt across several invoices) is not
 * cross-referenced to find a receipt this invoice is only PARTIALLY named inside -- that JSON's
 * exact shape/contract is not yet decided by any wave this build can find, and guessing at an
 * undocumented format risks silently missing real money movements rather than correctly reporting
 * "nothing found here". `Credit` rows with no `payment_id` (e.g. a pure topup credit or a
 * refund-sourced credit -- `refund_id`) are still surfaced as a `receipt`-type node
 * ({@see self::walkCreditReceipt()}) but their own reconciled/locked state cannot be checked
 * because this schema gives a `Credit` no `transaction_id` of its own; such a credit therefore
 * never itself blocks on reconciliation/lock (only its OWN period, resolved from `created_at`, is
 * checked) -- flagged so a future wave that adds ledger linkage to `Credit` knows to revisit this.
 */
final class UnlockDependencyResolver
{
    public function __construct(private readonly PeriodGuard $periodGuard) {}

    /**
     * @return array<int, array{type: string, id: int, number: ?string, status: string, url: ?string, hint: string, log_center_url: ?string}>
     */
    public function blockersForInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing('agent.branch');
        $companyId = (int) ($invoice->agent?->branch?->company_id ?? 0);

        $blockers = [];

        $transactionIds = Transaction::withoutGlobalScope('company')->where('invoice_id', $invoice->id)->pluck('id');

        // Invoice::$invoice_date is a PLAIN string column (no date cast on the model) -- unlike
        // Transaction/JournalEntry's own posting_date/transaction_date, which ARE cast to
        // Carbon/date. Normalize it here rather than adding a cast to Invoice (out of this
        // sub-wave's scope and a behaviour change other callers of the model do not expect).
        $invoiceDate = $invoice->invoice_date !== null ? Carbon::parse($invoice->invoice_date) : null;

        $ownPeriod = $this->periodBlockerFor(
            companyId: $companyId,
            date: $this->resolvePeriodDate($transactionIds, $invoiceDate),
        );
        if ($ownPeriod !== null) {
            $blockers[] = $ownPeriod;
        }

        if ($transactionIds->isNotEmpty()) {
            Transaction::withoutGlobalScope('company')
                ->whereIn('reversal_of_transaction_id', $transactionIds)
                ->get()
                ->each(function (Transaction $reversal) use (&$blockers) {
                    $blockers[] = $this->reversalBlocker($reversal);
                });
        }

        $partialIds = $invoice->invoicePartials()->pluck('id');

        // (3) PaymentApplication rows -> Payment/Credit.
        PaymentApplication::query()
            ->where(function ($q) use ($invoice, $partialIds) {
                $q->where('invoice_id', $invoice->id);
                if ($partialIds->isNotEmpty()) {
                    $q->orWhereIn('invoice_partial_id', $partialIds->all());
                }
            })
            ->get()
            ->each(function (PaymentApplication $application) use (&$blockers, $companyId) {
                $blockers = array_merge($blockers, $this->walkApplication($application, $companyId));
            });

        // (4) InvoicePartial rows with a direct payment_id and no PaymentApplication row.
        InvoicePartial::where('invoice_id', $invoice->id)
            ->whereNotNull('payment_id')
            ->get()
            ->each(function (InvoicePartial $partial) use (&$blockers, $companyId) {
                $payment = Payment::withoutGlobalScope('company')->find($partial->payment_id);
                if ($payment !== null) {
                    $blockers = array_merge(
                        $blockers,
                        $this->walkPaymentReceipt($payment, 'allocation', (int) $partial->id, $companyId)
                    );
                }
            });

        // (3.5) Payment rows with a DIRECT invoice_id (the pre-PaymentApplication, single-payment-
        // per-invoice legacy shape -- App\Models\Invoice::payment(), a hasOne on this same column)
        // and no PaymentApplication row of their own for this invoice.
        $appliedPaymentIds = PaymentApplication::where('invoice_id', $invoice->id)->pluck('payment_id')->filter();
        Payment::withoutGlobalScope('company')
            ->where('invoice_id', $invoice->id)
            ->whereNotIn('id', $appliedPaymentIds)
            ->get()
            ->each(function (Payment $payment) use (&$blockers, $companyId) {
                $blockers = array_merge($blockers, $this->walkPaymentReceipt($payment, null, null, $companyId));
            });

        // (5) InvoiceReceipt rows linked directly (by invoice_id, invoice_partial_id, or
        // InvoicePartial::$receipt_voucher_id).
        $receiptIds = InvoiceReceipt::where('invoice_id', $invoice->id)
            ->when($partialIds->isNotEmpty(), fn ($q) => $q->orWhereIn('invoice_partial_id', $partialIds->all()))
            ->pluck('id')
            ->merge(
                InvoicePartial::where('invoice_id', $invoice->id)
                    ->whereNotNull('receipt_voucher_id')
                    ->pluck('receipt_voucher_id')
            )
            ->unique()
            ->values();

        InvoiceReceipt::whereIn('id', $receiptIds)
            ->get()
            ->each(function (InvoiceReceipt $receipt) use (&$blockers, $companyId) {
                $blockers = array_merge($blockers, $this->walkInvoiceReceipt($receipt, $companyId));
            });

        return $this->dedupe($blockers);
    }

    // ── Application/allocation -> receipt -> reconciled-line/period ────────────────────────────

    private function walkApplication(PaymentApplication $application, int $fallbackCompanyId): array
    {
        $type = $application->invoice_partial_id !== null ? 'allocation' : 'application';

        if ($application->payment_id !== null) {
            $payment = Payment::withoutGlobalScope('company')->find($application->payment_id);

            return $payment === null
                ? []
                : $this->walkPaymentReceipt($payment, $type, (int) $application->id, $fallbackCompanyId);
        }

        if ($application->credit_id !== null) {
            $credit = Credit::find($application->credit_id);

            return $credit === null
                ? []
                : $this->walkCreditReceipt($credit, $type, (int) $application->id, $fallbackCompanyId);
        }

        return [];
    }

    /**
     * When `$contextType`/`$contextId` are given (an `application`/`allocation` node from
     * {@see self::walkApplication()}'s `PaymentApplication::$id`, or from the direct-InvoicePartial
     * path in {@see self::blockersForInvoice()}'s step (4)), emits that context node ahead of the
     * `receipt` node so the rendered tree shows the full "allocation -> receipt -> leaf" path. When
     * null (the DIRECT-`Payment.invoice_id` legacy shape, step (3.5) -- no application/allocation
     * layer exists at all for that link), the `receipt` node is the top-level item instead.
     */
    private function walkPaymentReceipt(Payment $payment, ?string $contextType, ?int $contextId, int $fallbackCompanyId): array
    {
        $companyId = (int) ($payment->company_id ?: $fallbackCompanyId);
        $transactionIds = Transaction::withoutGlobalScope('company')->where('payment_id', $payment->id)->pluck('id');

        $leaf = $this->leafBlockersForTransactions($transactionIds, $companyId);

        if ($leaf === []) {
            return [];
        }

        $items = [];
        if ($contextType !== null && $contextId !== null) {
            $items[] = $this->contextBlocker(
                $contextType,
                $contextId,
                null,
                null,
                'payment_application',
                'This '.$contextType.' channels funds from the receipt below; resolve that receipt, then reapply if needed.',
            );
        }
        $items[] = $this->contextBlocker(
            'receipt',
            (int) $payment->id,
            $payment->voucher_number ?? $payment->payment_reference ?? ('PAY-'.$payment->id),
            $this->paymentUrl($payment),
            'payment',
        );

        return array_merge($items, $leaf);
    }

    private function walkCreditReceipt(Credit $credit, string $contextType, int $contextId, int $fallbackCompanyId): array
    {
        $companyId = (int) ($credit->company_id ?: $fallbackCompanyId);

        $transactionIds = $credit->payment_id !== null
            ? Transaction::withoutGlobalScope('company')->where('payment_id', $credit->payment_id)->pluck('id')
            : collect();

        $leaf = $this->leafBlockersForTransactions($transactionIds, $companyId);

        // A credit with no ledger linkage of its own (see class docblock) still gets its OWN
        // period checked, from its creation date -- the one signal available without a
        // transaction_id.
        $ownPeriod = $transactionIds->isEmpty()
            ? $this->periodBlockerFor($companyId, $credit->created_at ?? Carbon::now())
            : null;

        if ($leaf === [] && $ownPeriod === null) {
            return [];
        }

        $items = [
            $this->contextBlocker(
                $contextType,
                $contextId,
                null,
                null,
                'payment_application',
                'This '.$contextType.' channels a client credit into the item below; resolve that item, then reapply if needed.',
            ),
            $this->contextBlocker('receipt', (int) $credit->id, 'CREDIT-'.$credit->id, null, 'credit'),
        ];

        if ($ownPeriod !== null) {
            $items[] = $ownPeriod;
        }

        return array_merge($items, $leaf);
    }

    private function walkInvoiceReceipt(InvoiceReceipt $receipt, int $fallbackCompanyId): array
    {
        $companyId = (int) ($receipt->company_id ?: $fallbackCompanyId);
        $transactionIds = collect(array_filter([$receipt->transaction_id, $receipt->applied_transaction_id ?? null]))
            ->unique()
            ->values();

        $leaf = $this->leafBlockersForTransactions($transactionIds, $companyId);

        if ($leaf === []) {
            return [];
        }

        $items = [$this->contextBlocker(
            'receipt',
            (int) $receipt->id,
            $receipt->voucher_number ?? ('RV-'.$receipt->id),
            $this->invoiceReceiptUrl($receipt),
            'invoice_receipt',
        )];

        return array_merge($items, $leaf);
    }

    /**
     * The shared leaf-resolution step every receipt-like node (Payment / Credit / InvoiceReceipt)
     * funnels through: is any of its own transactions Layer-1 `is_locked`? does any of its own
     * journal lines carry a non-zero `reconciled` flag? does it sit in a non-open accounting
     * period? Each `true` becomes its own blocker entry -- a node can contribute more than one
     * (e.g. both a reconciled line AND a closed period).
     *
     * @param  Collection<int, int>  $transactionIds
     * @return array<int, array{type: string, id: int, number: ?string, status: string, url: ?string, hint: string, log_center_url: ?string}>
     */
    private function leafBlockersForTransactions(Collection $transactionIds, int $companyId): array
    {
        if ($transactionIds->isEmpty()) {
            return [];
        }

        $blockers = [];

        $lockedTransaction = Transaction::withoutGlobalScope('company')
            ->whereIn('id', $transactionIds)
            ->where('is_locked', true)
            ->first();
        if ($lockedTransaction !== null) {
            $blockers[] = [
                'type' => 'reconciled_line',
                'id' => (int) $lockedTransaction->id,
                'number' => $lockedTransaction->reference_number ?? ('TXN-'.$lockedTransaction->id),
                'status' => 'locked',
                'url' => $this->journalEntryViewerUrl((int) $lockedTransaction->id),
                'hint' => 'This document is itself locked (Layer 1) -- unpin it there first, or use reverse + repost instead of unlocking the invoice.',
                'log_center_url' => AuditLogLinker::forSubject('transaction', (int) $lockedTransaction->id),
            ];
        }

        JournalEntry::withoutGlobalScope('company')
            ->whereIn('transaction_id', $transactionIds)
            ->where('reconciled', '!=', 0)
            ->get()
            ->each(function (JournalEntry $line) use (&$blockers) {
                $blockers[] = [
                    'type' => 'reconciled_line',
                    'id' => (int) $line->id,
                    'number' => 'JE-'.$line->id,
                    'status' => 'reconciled',
                    'url' => $this->journalEntryViewerUrl((int) $line->transaction_id),
                    'hint' => 'This line is bank-reconciled -- unreconcile it (with a reason) before it can be part of an unlock, or correct via reverse + repost instead.',
                    'log_center_url' => AuditLogLinker::forSubject('journal_entry', (int) $line->id),
                ];
            });

        $transactionDate = $this->resolvePeriodDate($transactionIds, null);
        if ($transactionDate !== null) {
            $period = $this->periodBlockerFor($companyId, $transactionDate);
            if ($period !== null) {
                $blockers[] = $period;
            }
        }

        return $blockers;
    }

    // ── Shared blocker builders ─────────────────────────────────────────────────────────────────

    private function reversalBlocker(Transaction $reversal): array
    {
        return [
            'type' => 'reversal',
            'id' => (int) $reversal->id,
            'number' => $reversal->reference_number ?? ('TXN-'.$reversal->id),
            'status' => 'posted',
            'url' => $this->journalEntryViewerUrl((int) $reversal->id),
            'hint' => 'This invoice has already been corrected via reverse + repost -- edit the repost document instead of unlocking the original.',
            'log_center_url' => AuditLogLinker::forSubject('transaction', (int) $reversal->id),
        ];
    }

    private function contextBlocker(string $type, int $id, ?string $number, ?string $url, string $logSubjectType, ?string $hint = null): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'number' => $number,
            'status' => 'posted',
            'url' => $url,
            'hint' => $hint ?? 'Funds have already moved through this item; correct the invoice via reverse + repost rather than unlocking it.',
            'log_center_url' => AuditLogLinker::forSubject($logSubjectType, $id),
        ];
    }

    private function periodBlockerFor(int $companyId, ?\DateTimeInterface $date): ?array
    {
        if ($date === null) {
            return null;
        }

        $status = $this->periodGuard->statusFor($companyId, $date);

        if ($status === AccountingPeriod::STATUS_OPEN) {
            return null;
        }

        $carbon = Carbon::instance($date);
        $isAnnual = (string) config('accounting.period.length', 'monthly') === 'annual';
        $year = (int) $carbon->format('Y');
        $month = $isAnnual ? AccountingPeriod::ANNUAL_MONTH : (int) $carbon->format('n');

        return [
            'type' => 'period',
            'id' => $year * 100 + $month,
            'number' => $isAnnual ? (string) $year : sprintf('%04d-%02d', $year, $month),
            'status' => 'period_closed',
            'url' => $this->periodViewerUrl($year),
            'hint' => $status === AccountingPeriod::STATUS_LOCKED
                ? 'This period is locked -- reopen it (accounting.period.reopen, with a reason) before this record can be unlocked, or correct forward via reverse + repost.'
                : 'This period is soft-closed -- an accounting.period.post-soft-closed override (with a reason) is required, or correct forward via reverse + repost.',
            'log_center_url' => AuditLogLinker::forSubject('accounting_period', $year * 100 + $month),
        ];
    }

    /**
     * @param  Collection<int, int>  $transactionIds
     */
    private function resolvePeriodDate(Collection $transactionIds, ?\DateTimeInterface $fallback): ?\DateTimeInterface
    {
        if ($transactionIds->isNotEmpty()) {
            $transaction = Transaction::withoutGlobalScope('company')
                ->whereIn('id', $transactionIds)
                ->orderByDesc('id')
                ->first(['posting_date', 'transaction_date']);

            if ($transaction !== null) {
                return $transaction->posting_date ?? $transaction->transaction_date ?? $fallback;
            }
        }

        return $fallback;
    }

    // ── Deep links ───────────────────────────────────────────────────────────────────────────────

    private function journalEntryViewerUrl(int $transactionId): ?string
    {
        return Route::has('journal-entries.index')
            ? route('journal-entries.index', ['transactionId' => $transactionId])
            : null;
    }

    private function paymentUrl(Payment $payment): ?string
    {
        return Route::has('payment.show')
            ? route('payment.show', ['id' => $payment->id])
            : null;
    }

    private function invoiceReceiptUrl(InvoiceReceipt $receipt): ?string
    {
        if (! Route::has('receipt-voucher.show') || $receipt->voucher_number === null || $receipt->company_id === null) {
            return null;
        }

        return route('receipt-voucher.show', ['companyId' => $receipt->company_id, 'voucherNumber' => $receipt->voucher_number]);
    }

    private function periodViewerUrl(int $year): ?string
    {
        return Route::has('accounting.periods.index')
            ? route('accounting.periods.index', ['year' => $year])
            : null;
    }

    /**
     * Dedupe by (type, id) -- the same node can be reached twice (e.g. an InvoicePartial with both
     * a PaymentApplication row and a direct receipt_voucher_id) without producing duplicate rows
     * in the rendered tree.
     *
     * @param  array<int, array{type: string, id: int}>  $blockers
     */
    private function dedupe(array $blockers): array
    {
        $seen = [];
        $out = [];

        foreach ($blockers as $blocker) {
            $key = $blocker['type'].':'.$blocker['id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $blocker;
        }

        return $out;
    }
}
