<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class AgentWelcomeMail extends Mailable
{
    public function __construct(
        public string $agentName,
        public string $email,
        public string $tempPassword,
        public string $loginUrl,
    ) {
    }

    public function build()
    {
        return $this->subject('Your CityTour agent account')
            ->view('emails.agent-welcome');
    }
}
