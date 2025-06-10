<?php

namespace App\Jobs;

use App\Services\MagicHolidayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncLanguageMagicHoliday implements ShouldQueue
{
    use Queueable;

    
    public function __construct(MagicHolidayService $magicHoliday)
    {
        
    }

    public function handle(): void
    {
        //
    }
}
