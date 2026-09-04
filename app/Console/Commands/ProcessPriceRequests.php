<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PriceRequestService;

class ProcessPriceRequests extends Command
{
    protected $signature = 'price-requests:tick';
    protected $description = 'Send pending price asks (one per agent) and run reminders/expiry';

    public function handle(PriceRequestService $svc): int
    {
        $sent = $svc->sendNext();
        $svc->sweepReminders();
        $this->info("price-requests:tick sent={$sent}");
        return 0;
    }
}
