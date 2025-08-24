<?php

namespace App\Services;

use App\Models\Charge;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class GatewayConfigService
{
    /**
     * Fetches the configuration for the Tap payment gateway
     *
     * @return array
     */
    public function getTapConfig(): array
    {
        $tapCharge = Charge::where('name', 'like', '%tap%')
                           ->where('is_active', true)
                           ->first();

        if ($tapCharge && $tapCharge->api_key && $tapCharge->base_url) {
            $config = [
                'secret' => $tapCharge->api_key,
                'url'    => rtrim($tapCharge->base_url, '/') . '/v2',
                'public' => Config::get('services.tap.public'),
            ];

            Log::info('Tap gateway config loaded from DB', [
                'company_id' => $tapCharge->company_id,
                'url'        => $config['url'],
                'secret'     => $config['secret'],
            ]);

            return $config;
        }
        
        $config = Config::get('services.tap');
        Log::info('Tap gateway config loaded from config/services.php', [
            'url'    => $config['url'] ?? null,
            'secret' => $config['secret'] ?? null,
        ]);

        return $config;
    }

    /**
     * Fetches the configuration for the MyFatoorah payment gateway
     *
     * @return array
     */
    public function getMyFatoorahConfig(): array
    {
        $myFatoorahCharge = Charge::where('name', 'like', '%myfatoorah%')
                                  ->where('is_active', true)
                                  ->first();

        if ($myFatoorahCharge && $myFatoorahCharge->api_key && $myFatoorahCharge->base_url) {
            $config = [
                'api_key'  => $myFatoorahCharge->api_key,
                'base_url' => rtrim($myFatoorahCharge->base_url, '/') . '/v2',
            ];

            Log::info('MyFatoorah gateway config loaded from DB', [
                'company_id' => $myFatoorahCharge->company_id,
                'base_url'   => $config['base_url'],
                'api_key'    => $config['api_key'],
            ]);

            return $config;
        }

        $config = Config::get('services.myfatoorah');
        Log::info('MyFatoorah gateway config loaded from config/services.php', [
            'base_url' => $config['base_url'] ?? null,
            'api_key'  => $config['api_key'] ?? null,
        ]);

        return $config;
    }
}