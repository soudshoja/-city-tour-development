<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * P2.5.I prod-drift port (verbatim from /home/citycomm/tour.citycommerce.group
 * app/Mail/UninvoicedPaymentLinkReminderMail.php, 2026-08-31). Email leg of
 * App\Console\Commands\SendAgentUninvoicedPaymentLinkReminders.
 */
class UninvoicedPaymentLinkReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $agent;
    public $payments;
    public $company;
    public $windowLabel;
    public $locale;

    public function __construct($agent, $payments, $company, string $windowLabel, string $locale = 'en')
    {
        $this->agent = $agent;
        $this->payments = $payments;
        $this->company = $company;
        $this->windowLabel = $windowLabel;
        $this->locale = $locale;
    }

    public function build()
    {
        $count = count($this->payments);
        $subject = trans('payment_link_reminder.subject', ['count' => $count], $this->locale);

        return $this->subject($subject)
            ->view('notifications.pdf.uninvoiced-payment-links')
            ->with([
                'agent' => $this->agent,
                'payments' => $this->payments,
                'company' => $this->company,
                'windowLabel' => $this->windowLabel,
                'locale' => $this->locale,
                'isPdf' => false,
            ]);
    }
}
