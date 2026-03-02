<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UnassignedTaskReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tasks;
    public $company;
    public $windowLabel;

    public function __construct($tasks, $company, string $windowLabel = '1 - 7 days')
    {
        $this->tasks = $tasks;
        $this->company = $company;
        $this->windowLabel = $windowLabel;
    }

    public function build()
    {
        $count = count($this->tasks);

        return $this->subject("Unassigned Task Reminder - {$count} task(s) pending")
            ->view('notifications.pdf.unassigned-tasks')
            ->with([
                'tasks' => $this->tasks,
                'company' => $this->company,
                'windowLabel' => $this->windowLabel,
                'isPdf' => false,
            ]);
    }
}
