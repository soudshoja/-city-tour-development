<?php

namespace App\Jobs;

use App\Services\MFMethodSyncService;
use App\Services\UPaymentMethodSyncService;
use App\Services\HesabeMethodSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SyncGatewayMethods implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $companyId,
        public string $gatewayName
    ) {}

    public function handle(): void
    {
        $name = Str::lower($this->gatewayName);

        if (Str::contains($name, 'myfatoorah')) {
            app(MFMethodSyncService::class)->sync($this->companyId);
            return;
        }

        if (Str::contains($name, 'upayment') || Str::contains($name, 'welcome')) {
            app(UPaymentMethodSyncService::class)->sync($this->companyId);
            return;
        }

        if (Str::contains($name, 'hesabe')) {
            app(HesabeMethodSyncService::class)->sync($this->companyId);
            return;
        }
    }
}
