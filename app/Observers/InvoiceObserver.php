<?php

namespace App\Observers;

use App\Http\Controllers\ResayilController;
use App\Mail\InvoiceMail;
use App\Models\AgentNotificationSetting;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvoiceObserver
{
    /**
     * Invoice notifications, sent at the moments the accountant actually needs
     * a record — NOT on every creation:
     *  - created() fires only for invoices born already PAID (e.g. generated
     *    from a completed payment): the email is the payment record.
     *  - An unpaid invoice emails "Payment Required" only when the agent
     *    selects an ONLINE payment gateway for it (payment link) — see
     *    notifyPaymentLinkSelected(), called from InvoiceController's
     *    updatePaymentGateway/updatePartialGateway.
     *  - updated() below sends the "Payment Received" record when any invoice
     *    transitions to paid.
     * Recipients: the accountant (settings key notification.invoice_created.email)
     * plus the creating agent per their "Invoice Notifications" channel
     * (email = staff invoice email, whatsapp = link message via Resayil).
     */
    public function created(Invoice $invoice): void
    {
        if (app()->runningInConsole()) {
            return; // avoid notification storms from artisan backfills/imports
        }
        if ($invoice->status !== 'paid') {
            // Unpaid at creation: stay silent. The "Payment Required" email is
            // sent only if/when a payment link gateway is selected.
            return;
        }

        self::sendInvoiceEmailNotifications($invoice);
    }

    /**
     * "Payment Required" notification when an online gateway (payment link) is
     * selected for an unpaid invoice. Sent at most once per invoice (cache
     * guard) so switching gateways doesn't re-email. Cash/Credit selections
     * never notify — those settle internally and only the paid record follows.
     */
    public static function notifyPaymentLinkSelected(Invoice $invoice, string $gateway): void
    {
        if ($invoice->status === 'paid') {
            return;
        }
        if (in_array(strtolower(trim($gateway)), ['', 'cash', 'credit', 'full'], true)) {
            return;
        }
        if (!\Illuminate\Support\Facades\Cache::add('invoice_payment_required_mail_' . $invoice->id, 1, now()->addDays(30))) {
            return; // already notified for this invoice
        }

        self::sendInvoiceEmailNotifications($invoice);
    }

    /**
     * Shared sender: accountant email + per-agent channel, after the response.
     * $context 'created'|'paid' selects the log labels and the agent WhatsApp
     * wording; the email itself is the staff invoice copy either way — its
     * subject renders Payment Required/Received from the live invoice status.
     */
    private static function sendInvoiceEmailNotifications(Invoice $invoice, string $context = 'created'): void
    {
        $companyId = $invoice->agent?->branch?->company_id;
        if (!$companyId) {
            return;
        }

        // 1. Accountant recipient (company-wide setting, always on unless disabled)
        $accountantEmail = null;
        $channel = Setting::getByKey($companyId, 'notification.invoice_created.channel', 'none');
        if (in_array($channel, ['email', 'both'], true)) {
            $email = trim((string) Setting::getByKey($companyId, 'notification.invoice_created.email', ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $accountantEmail = $email;
            }
        }

        // 2. Per-agent "Invoice Notifications" setting for the creating agent
        $agentChannel = null;
        if ($invoice->agent_id) {
            $agentSetting = AgentNotificationSetting::where('agent_id', $invoice->agent_id)
                ->where('company_id', $companyId)
                ->where('notification_type', AgentNotificationSetting::TYPE_INVOICE_CREATED)
                ->where('is_active', true)
                ->first();
            $agentChannel = $agentSetting?->channel;
        }

        if (!$accountantEmail && !$agentChannel) {
            return;
        }

        $invoiceId = $invoice->id;
        // Send after the response is delivered so the invoice items exist by the
        // time the email body is rendered (details rows are inserted after the
        // invoice row within the same request) and the mail never sits inside
        // the DB transaction some payment paths mark paid within.
        app()->terminating(function () use ($invoiceId, $accountantEmail, $agentChannel, $context) {
            if ($accountantEmail) {
                try {
                    Mail::to($accountantEmail)->send(new InvoiceMail($invoiceId, true));
                    Log::info("Invoice-{$context} notification email sent", [
                        'invoice_id' => $invoiceId,
                        'recipient' => $accountantEmail,
                    ]);
                } catch (\Throwable $e) {
                    Log::error("Invoice-{$context} notification email failed", [
                        'invoice_id' => $invoiceId,
                        'recipient' => $accountantEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!$agentChannel) {
                return;
            }

            $invoice = Invoice::with('agent.branch')->find($invoiceId);
            $agent = $invoice?->agent;
            if (!$agent) {
                return;
            }

            if (in_array($agentChannel, ['email', 'both'], true) && $agent->email) {
                try {
                    Mail::to($agent->email)->send(new InvoiceMail($invoiceId, true));
                    Log::info("Invoice-{$context} agent copy email sent", [
                        'invoice_id' => $invoiceId,
                        'agent_id' => $agent->id,
                        'recipient' => $agent->email,
                    ]);
                } catch (\Throwable $e) {
                    Log::error("Invoice-{$context} agent copy email failed", [
                        'invoice_id' => $invoiceId,
                        'agent_id' => $agent->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (in_array($agentChannel, ['whatsapp', 'both'], true) && $agent->phone_number) {
                try {
                    $url = route('invoice.details', [
                        'companyId' => $agent->branch->company_id,
                        'invoiceNumber' => $invoice->invoice_number,
                    ]);
                    $waText = $context === 'paid'
                        ? "Invoice *{$invoice->invoice_number}* has been PAID.\n{$url}"
                        : "New invoice *{$invoice->invoice_number}* created.\n{$url}";
                    $resayil = new ResayilController();
                    $response = $resayil->message(
                        phone: $agent->phone_number,
                        country_code: $agent->country_code ?? '',
                        message: $waText,
                    );
                    Log::info("Invoice-{$context} agent WhatsApp attempted", [
                        'invoice_id' => $invoiceId,
                        'agent_id' => $agent->id,
                        'success' => $response['success'] ?? null,
                    ]);
                } catch (\Throwable $e) {
                    Log::error("Invoice-{$context} agent WhatsApp failed", [
                        'invoice_id' => $invoiceId,
                        'agent_id' => $agent->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * Invoice-paid notifications. Whenever an invoice transitions to paid —
     * gateway callback/webhook, the 30-min reconciler, receipt voucher, client
     * credit or manual payment — the accountant gets the staff invoice email
     * (subject renders "Payment Received") and the agent gets a copy via
     * their "Invoice Notifications" channel (email and/or WhatsApp).
     *
     * No runningInConsole() guard here (unlike created()): the reconciler
     * completes missed webhooks from console and those payments need the
     * record too. Storms are prevented by the transition check — re-saving an
     * already-paid invoice never fires. Bulk payment imports that flip
     * invoices to paid therefore email one record per invoice; intentional.
     */
    public function updated(Invoice $invoice): void
    {
        if (!$invoice->wasChanged('status') || $invoice->status !== 'paid') {
            return;
        }
        if ($invoice->getOriginal('status') === 'paid') {
            return;
        }

        self::sendInvoiceEmailNotifications($invoice, 'paid');
    }
}
