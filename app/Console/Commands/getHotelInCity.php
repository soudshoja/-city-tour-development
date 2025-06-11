<?php

namespace App\Console\Commands;

use App\Services\MagicHolidayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class getHotelInCity extends Command
{
    protected $signature = 'mapping:get-hotel-in-city {cityName}';

    protected $description = 'Get hotels in a specific city by name';

    public function handle(MagicHolidayService $magicHoliday)
    {
        $cityName = $this->argument('cityName');

        if (empty($cityName)) {
            $this->error('City name cannot be empty.');
            return;
        }
        
      
     
    }
}
