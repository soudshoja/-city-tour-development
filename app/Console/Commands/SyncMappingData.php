<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SyncCountriesJob;
use App\Jobs\SyncCitiesJob;
use App\Jobs\SyncHotelsJob;

class SyncMappingData extends Command
{
    protected $signature = 'mapping:sync {type?} {--full} {--date=}';
    protected $description = 'Sync mapping data from Magic Holidays API';

    public function handle()
    {
        $type = $this->argument('type');
        $isFull = $this->option('full');
        $date = $this->option('date');
        
        $this->info('Starting sync process...');
        
        switch ($type) {
            case 'countries':
                $this->info('Dispatching countries sync job...');
                dispatch(new SyncCountriesJob($isFull));
                break;
                
            case 'cities':
                $this->info('Dispatching cities sync job...');
                dispatch(new SyncCitiesJob($isFull));
                break;
                
            case 'hotels':
                $this->info('Dispatching hotels sync job...');
                dispatch(new SyncHotelsJob($isFull, null, $date));
                break;
                
            default:
                // Sync all with appropriate delays to prevent API rate limiting
                $this->info('Dispatching all sync jobs...');
                
                dispatch(new SyncCountriesJob($isFull));
                $this->info('Countries sync job dispatched');
                
                dispatch(new SyncCitiesJob($isFull))
                    ->delay(now()->addMinutes(5));
                $this->info('Cities sync job dispatched (delayed by 5 minutes)');
                
                dispatch(new SyncHotelsJob($isFull, null, $date))
                    ->delay(now()->addMinutes(10));
                $this->info('Hotels sync job dispatched (delayed by 10 minutes)');
        }
        
        $this->info('Sync jobs have been dispatched. Run queue worker to process them.');
    }
}