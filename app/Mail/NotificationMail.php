<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
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
        return $this->subject($this->data['title'] ?? 'Notification')
            ->view('email.notification')
            ->with([
                'title' => $this->data['title'] ?? 'Notification',
                'body' => $this->data['message'] ?? '',
                'company' => $this->company,
            ]);
    }
}
