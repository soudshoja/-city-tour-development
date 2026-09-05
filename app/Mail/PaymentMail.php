<?php

namespace App\Mail;

use App\Enums\PaymentMailTypeEnum;
use App\Models\Notification;
use App\Models\Payment;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentMail extends Mailable
{
    protected $paymentId;
    protected $type;

    public function __construct(int $paymentId, PaymentMailTypeEnum $type)
    {
        $this->paymentId = $paymentId;
        $this->type = $type;
    }

    public function build()
    {
        $subject = '';
        $view = '';
        $data = [];

        $payment = Payment::with([
            'client',
            'agent.branch.company',
            'paymentMethod',
            'paymentItems'
        ])->findOrFail($this->paymentId);

        switch ($this->type) {
            case PaymentMailTypeEnum::PAYMENT_LINK:
                // A payment link with no URL is not a mail, it is a bug: the
                // gateway either has not been initiated yet or the charge
                // failed. Refusing here is louder than sending a dead link.
                if (blank($payment->payment_url)) {
                    throw new Exception(
                        "Payment {$payment->id} has no payment_url; the gateway charge has not been initiated."
                    );
                }

                $company = $payment->agent?->branch?->company;

                $subject = 'Your payment link from ' . ($company->name ?? config('app.name'));
                $view = 'email.payment-link';
                $data = [
                    'paymentUrl' => $payment->payment_url,
                    'company' => $company,
                    'payment' => $payment,
                ];
                break;

            case PaymentMailTypeEnum::PAYMENT_SUCCESS:
                $subject = 'Payment Successful - ' . $payment->voucher_number;
                $view = 'payment.pdf.success';

                $invoiceRef = null;
                if ($payment->invoice_id) {
                    $invoiceRef = $payment->invoice->invoice_number ?? null;
                }

                $data = [
                    'payment' => $payment,
                    'invoiceRef' => $invoiceRef,
                    'isPdf' => false,
                ];
                break;

            case PaymentMailTypeEnum::PAYMENT_FAILURE:
                $company = $payment->agent?->branch?->company;

                $subject = 'Payment could not be completed - ' . $payment->voucher_number;
                $view = 'email.payment.failure';
                $data = [
                    'payment' => $payment,
                    'company' => $company,
                    // A failed payment keeps its link until it expires, so the
                    // client can simply try again rather than ask for a new one.
                    'paymentUrl' => $payment->expiry_date && $payment->expiry_date->isFuture()
                        ? $payment->payment_url
                        : null,
                ];
                break;
        }

        if ($view === '') {
            throw new Exception('No template is defined for payment mail type ' . $this->type->value . '.');
        }

        return $this->subject($subject)
            ->view($view)
            ->with($data);
    }
}
