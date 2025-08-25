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
        $configFromService = Config::get('services.tap');

        if($configFromService === null) {
            Log::warning('Tap does not have any configuration yet. Please contact your support team.');
            return [
                'status' => 'error',
                'message' => 'Tap payment gateway is not configured. Please contact your support team.'
            ];
        }

        $config = [
            'status'  => 'success',
            'message' => 'Tap configuration loaded successfully',
            'data'    => $configFromService,
        ];

        $tapCharge = Charge::where('name', 'like', '%tap%')
                           ->where('is_active', true)
                           ->first();

        if ($tapCharge && $tapCharge->api_key) {

            $config = [
                'status' => 'success',
                'message' => 'Tap configuration loaded successfully',
                'data' => [
                    'secret' => $tapCharge->api_key,
                    'url'    => $configFromService['url'],
                ]
            ];

            Log::info('Tap gateway config loaded from DB', [
                'company_id' => $tapCharge->company_id,
                'url'        => $config['data']['url'],
                'secret'     => $config['data']['secret'],
            ]);

            return $config;
        }
        
        Log::info('Tap gateway config loaded from config/services.php', [
            'url'    => $config['data']['url'] ?? null,
            'secret' => $config['data']['secret'] ?? null,
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
        $configFromService = Config::get('services.myfatoorah');

        if($configFromService === null) {

            Log::warning('MyFatoorah does not have any configuration yet. Please contact your support team.');

            return [
                'status' => 'error',
                'message' => 'MyFatoorah payment gateway is not configured. Please contact your support team.'
            ];
        }

        $config = [
            'status'  => 'success',
            'message' => 'MyFatoorah configuration loaded successfully',
            'data'    => $configFromService,
        ];

        Log::info('MyFatoorah gateway config loaded from config/services.php', [
            'base_url' => $config['data']['base_url'] ?? null,
            'api_key'  => $config['data']['api_key'] ?? null,
        ]);

        $myFatoorahCharge = Charge::where('name', 'like', '%myfatoorah%')
                                  ->where('is_active', true)
                                  ->first();

        if ($myFatoorahCharge && $myFatoorahCharge->api_key) {
            $config = [
                'status' => 'success',
                'message' => 'MyFatoorah configuration loaded successfully',
                'data' => [
                    'api_key' => $myFatoorahCharge->api_key,
                    'base_url' => $configFromService['base_url'],
                ]  
            ];

            Log::info('MyFatoorah gateway config loaded from DB', [
                'company_id' => $myFatoorahCharge->company_id,
                'base_url'   => $config['data']['base_url'],
                'api_key'    => $config['data']['api_key'],
            ]);

            return $config;
        }
        
        return $config;
    }
}