<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UninvoicedTaskReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $agent;
    public $tasks;
    public $company;
    public $windowLabel;

    public function __construct($agent, $tasks, $company, string $windowLabel = '1 - 7 days')
    {
        $this->agent = $agent;
        $this->tasks = $tasks;
        $this->company = $company;
        $this->windowLabel = $windowLabel;
    }

    public function build()
    {
        $count = count($this->tasks);

        return $this->subject("Uninvoiced Task Reminder - {$count} task(s) pending")
            ->view('notifications.pdf.uninvoiced-tasks')
            ->with([
                'agent' => $this->agent,
                'tasks' => $this->tasks,
                'company' => $this->company,
                'windowLabel' => $this->windowLabel,
                'isPdf' => false,
            ]);
    }
}
