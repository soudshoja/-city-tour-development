<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AutoBillMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $company;

    public function __construct(array $data, $company = null)
    {
        $this->data = $data;
        $this->company = $company;
    }

    public function build()
    {
        return $this->subject($this->data['title'] ?? 'AutoBill Invoice Generated')
            ->view('email.autobill-notification')
            ->with(array_merge($this->data, [
                'company' => $this->company,
            ]));
    }
}
