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

    public function __construct(int $paymentId,PaymentMailTypeEnum $type)
    {
        $this->paymentId = $paymentId;
        $this->type = $type;
    }

    public function build(){
        $subject = '';
        $view = '';
        $data = [];

        $payment = Payment::findOrFail($this->paymentId);

        switch ($this->type) {
            case PaymentMailTypeEnum::PAYMENT_LINK:
                throw new Exception('Payment link email not implemented yet.');
                break; // not implemented yet

                // $subject = 'Your Payment Link';
                // $view = 'email.payment-link';
                // $data = [
                //     'paymentUrl' => $payment->payment_url,
                //     'amount' => $payment->amount,
                // ];
                // break;

            case PaymentMailTypeEnum::PAYMENT_SUCCESS:
                $subject = 'Payment Successful';
                $view = 'email.payment.success';
                $data = [
                    'payment' => $payment,
                ];
                break;

            case PaymentMailTypeEnum::PAYMENT_FAILURE:
                throw new Exception('Payment failure email not implemented yet.');
                break; // not implemented yet

                // $subject = 'Payment Failed';
                // $view = 'email.payment.failure';
                // $data = [
                //     'amount' => $payment->amount,
                //     'errorMessage' => $payment->error_message,
                // ];
                // break;
        }

        return $this->subject($subject)
                    ->view($view)
                    ->with($data);

    }
}