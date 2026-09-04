<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class CompanyWelcomeMail extends Mailable
{
    public function __construct(
        public string $companyName,
        public string $ownerEmail,
        public string $loginUrl,
    ) {
    }

    public function build()
    {
        return $this->subject('Welcome to CityTour — ' . $this->companyName)
            ->view('emails.company-welcome');
    }
}
