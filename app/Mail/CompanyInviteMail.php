<?php

namespace App\Mail;

use App\Models\CompanyInvite;
use Illuminate\Mail\Mailable;

class CompanyInviteMail extends Mailable
{
    public function __construct(public CompanyInvite $invite)
    {
    }

    public function build()
    {
        return $this->subject('Your CityTour registration link')
            ->view('emails.company-invite', [
                'url' => url('/register/company/' . $this->invite->token),
                'expiresAt' => $this->invite->expires_at,
            ]);
    }
}
