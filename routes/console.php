<?php

use App\Console\Commands\TboTask;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('app:tbo-task')->everyMinute()->runInBackground();
Schedule::command('app:update-exchange-rate')->daily()->runInBackground();
Schedule::command('perform:payment-release-to-company-bankacc-process')->daily()->runInBackground();