<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TemporaryOffer;
use Carbon\Carbon;

class DeleteExpiredHotelOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-expired-offers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete TemporaryOffers and their OfferedRooms after 15 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoff = Carbon::now()->subMinutes(15);

        $expiredOffers = TemporaryOffer::where('created_at', '<=', $cutoff)->get();

        foreach ($expiredOffers as $offer) {
            $offer->offeredRoom()->delete();
            $offer->delete();
            $this->info("Deleted offer ID: {$offer->id}");
        }
    }
}
