<?php

namespace App\Observers;

use App\Models\InvoicePartial;
use Illuminate\Support\Facades\Log;

/**
 * Watches invoice partials for the moment an ONLINE payment gateway (payment
 * link) is attached to an unpaid invoice — that is when the accountant wants
 * the "Payment Required" email, not at bare invoice creation. Every selection
 * path funnels through a partial write (savePartial, updatePartialGateway,
 * updatePaymentGateway), so this single hook covers them all. Completion-time
 * partials (created already paid by the payment controllers) and Cash/Credit
 * settlements never notify; InvoiceObserver::notifyPaymentLinkSelected applies
 * those guards plus a once-per-invoice cache lock.
 */
class InvoicePartialObserver
{
    public function created(InvoicePartial $partial): void
    {
        $this->maybeNotify($partial);
    }

    public function updated(InvoicePartial $partial): void
    {
        if (!$partial->wasChanged('payment_gateway')) {
            return;
        }
        $this->maybeNotify($partial);
    }

    private function maybeNotify(InvoicePartial $partial): void
    {
        if (app()->runningInConsole()) {
            return; // gateway selection is always an interactive action
        }
        if ($partial->status === 'paid') {
            return; // completion-time partial, not a selection
        }
        $gateway = (string) $partial->payment_gateway;
        if ($gateway === '') {
            return;
        }
        $invoice = $partial->invoice;
        if (!$invoice) {
            return;
        }

        try {
            InvoiceObserver::notifyPaymentLinkSelected($invoice, $gateway);
        } catch (\Throwable $e) {
            Log::error('Payment-link-selected notification failed', [
                'invoice_id' => $invoice->id,
                'invoice_partial_id' => $partial->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
