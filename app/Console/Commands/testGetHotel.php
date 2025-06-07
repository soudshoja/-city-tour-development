<?php

namespace App\Console\Commands;

use App\Services\MagicHolidayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class testGetHotel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-get-hotel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(MagicHolidayService $magicHoliday)
    {
        try {
            $response = $magicHoliday->getHotels();
            Log::channel('magic_holiday')->info('Retrieved hotels successfully', ['response' => $response]);
            $this->info('Hotel details retrieved successfully: ' . json_encode($response));
        } catch (\Exception $e) {
            $this->error('Error retrieving hotel details: ' . $e->getMessage());
        }
    }
}
