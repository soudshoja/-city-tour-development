<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentLinkEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $paymentUrl;

    public function __construct(string $paymentUrl)
    {
        $this->paymentUrl = $paymentUrl;
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        return $this->subject('Your Payment Link from City Tour')
                    ->view('email.payment-link')
                    ->with([
                        'paymentUrl' => $this->paymentUrl,
                    ]);
    }
}
