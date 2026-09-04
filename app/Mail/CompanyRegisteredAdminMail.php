<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class CompanyRegisteredAdminMail extends Mailable
{
    public function __construct(
        public string $companyName,
        public string $inviteEmail,
        public bool $succeeded = true,
        public ?string $errorMessage = null,
    ) {
    }

    public function build()
    {
        $subject = $this->succeeded
            ? 'New company registered: ' . $this->companyName
            : 'Company registration FAILED for invite ' . $this->inviteEmail;

        return $this->subject($subject)
            ->view('emails.company-registered-admin');
    }
}
