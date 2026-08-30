<?php

declare(strict_types=1);

namespace App\Services\Accounting\Statements;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentApplication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * P2.5.H — client (AR) statement source. `Invoice` + `PaymentApplication` are the real,
 * populated open-item mechanism this codebase has today for the client side (doc 11 §P5.3 names
 * `PaymentApplication` "the single paid-state writer" and `Invoice.status` "a derived
 * projection" -- this class derives that projection itself, fresh from applications, rather than
 * trusting the stored `status` column). Never reads `journal_entries.settled_amount` (unpopulated,
 * see config('accounting.statements')'s own docblock) or `accounts.actual_balance`.
 */
final class ClientInvoiceStatementSource implements PartyStatementSourceInterface
{
    public function documents(int $companyId, int $partyId, Carbon $asOf): Collection
    {
        // Scoped by client_id alone -- the caller (StatementController) already resolved $partyId
        // to a Client the acting user is authorized to view (ClientPolicy::view(), company/branch
        // scoped), the same way ClientController::showCredit()'s own credit-ledger statement is
        // scoped. `invoices` carries no company_id/branch_id column of its own (verified against
        // the migrated schema), so re-deriving company scope through a join here would be both
        // redundant with, and a weaker check than, the policy gate already in front of this call.
        $invoices = Invoice::query()
            ->where('client_id', $partyId)
            ->where('invoice_date', '<=', $asOf->copy()->endOfDay())
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            return collect();
        }

        $applied = PaymentApplication::whereIn('invoice_id', $invoices->pluck('id'))
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->groupBy('invoice_id')
            ->pluck('total', 'invoice_id');

        return $invoices->map(function (Invoice $invoice) use ($applied) {
            $settled = (float) ($applied[$invoice->id] ?? 0);
            $amount = (float) $invoice->amount;

            return new StatementItem(
                kind: 'document',
                documentType: 'invoice',
                documentId: $invoice->id,
                documentNumber: (string) ($invoice->invoice_number ?? ('INV-'.$invoice->id)),
                documentDate: Carbon::parse($invoice->invoice_date),
                dueDate: $invoice->due_date ? Carbon::parse($invoice->due_date) : null,
                amount: $amount,
                // Never let a rounding/refund-adjustment artefact push settled above the
                // document's own amount -- an over-applied invoice reports fully settled
                // (outstanding 0), never a negative outstanding on a statement.
                settledAmount: min($settled, $amount),
                description: 'Invoice '.($invoice->invoice_number ?? $invoice->id),
            );
        })->values();
    }

    public function unapplied(int $companyId, int $partyId, Carbon $asOf): Collection
    {
        $items = collect();

        $payments = Payment::query()
            ->where('client_id', $partyId)
            ->where('payment_date', '<=', $asOf->copy()->endOfDay())
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->get();

        if ($payments->isNotEmpty()) {
            $appliedByPayment = PaymentApplication::whereIn('payment_id', $payments->pluck('id'))
                ->selectRaw('payment_id, SUM(amount) as total')
                ->groupBy('payment_id')
                ->pluck('total', 'payment_id');

            foreach ($payments as $payment) {
                $remaining = round((float) $payment->amount - (float) ($appliedByPayment[$payment->id] ?? 0), 3);
                if ($remaining > 0.001) {
                    $items->push(new StatementItem(
                        kind: 'unapplied',
                        documentType: 'receipt',
                        documentId: $payment->id,
                        documentNumber: (string) ($payment->voucher_number ?? ('RV-'.$payment->id)),
                        documentDate: Carbon::parse($payment->payment_date ?? $payment->created_at),
                        dueDate: null,
                        amount: $remaining,
                        settledAmount: 0.0,
                        description: 'Unapplied receipt',
                    ));
                }
            }
        }

        $credits = Credit::query()
            ->where('client_id', $partyId)
            ->where('type', Credit::TOPUP)
            ->where('created_at', '<=', $asOf->copy()->endOfDay())
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->get();

        if ($credits->isNotEmpty()) {
            $appliedByCredit = PaymentApplication::whereIn('credit_id', $credits->pluck('id'))
                ->selectRaw('credit_id, SUM(amount) as total')
                ->groupBy('credit_id')
                ->pluck('total', 'credit_id');

            foreach ($credits as $credit) {
                $remaining = round((float) $credit->amount - (float) ($appliedByCredit[$credit->id] ?? 0), 3);
                if ($remaining > 0.001) {
                    $items->push(new StatementItem(
                        kind: 'unapplied',
                        documentType: 'credit',
                        documentId: $credit->id,
                        documentNumber: 'CR-'.$credit->id,
                        documentDate: Carbon::parse($credit->created_at),
                        dueDate: null,
                        amount: $remaining,
                        settledAmount: 0.0,
                        description: $credit->description ?: 'Unapplied credit',
                    ));
                }
            }
        }

        return $items->sortBy(fn (StatementItem $i) => $i->documentDate)->values();
    }
}
