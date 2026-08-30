<?php

namespace App\Services;

use App\Models\Charge;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class GatewayConfigService
{
    /**
     * Produce a non-reversible, non-sensitive fingerprint of a secret for logging.
     * Never log the full key/secret value.
     */
    private function fingerprint(?string $key): ?string
    {
        return $key ? '...'.substr($key, -4) : null;
    }

    /**
     * Log the "resolved without company scoping" warning at most once per hour,
     * per gateway. storage/logs/laravel.log is already large and these unscoped
     * call sites (PaymentController, scheduled commands) fire on every payment /
     * every scheduler tick, so logging every single call would make that worse
     * fast. The signal itself (unscoped calls still exist) stays useful -- it's
     * just rate-limited rather than removed.
     */
    private function logUnscopedWarning(string $gateway): void
    {
        $cacheKey = 'gateway_unscoped_warning:'.$gateway;

        // Cache::add only succeeds (and returns true) if the key is not already
        // present, so only the first call in each hour-long window logs.
        if (Cache::add($cacheKey, true, now()->addHour())) {
            Log::warning("{$gateway} gateway config resolved without company scoping (unscoped call site).");
        }
    }

    /**
     * Fetches the configuration for the Tap payment gateway
     *
     * @param  int|null  $companyId  Scope credential lookup to this company. When null,
     *                               falls back to the first active Charge (legacy, unscoped) behavior.
     * @return array
     */
    public function getTapConfig(?int $companyId = null): array
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

        $tapChargeQuery = Charge::where('name', 'like', '%tap%')
            ->where('is_active', true);

        if ($companyId !== null) {
            $tapChargeQuery->where('company_id', $companyId);
        } else {
            $this->logUnscopedWarning('Tap');
        }

        $tapCharge = $tapChargeQuery->first();

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
                'key_fingerprint' => $this->fingerprint($config['data']['secret']),
            ]);

            return $config;
        }

        Log::info('Tap gateway config loaded from config/services.php', [
            'url'    => $config['data']['url'] ?? null,
            'key_fingerprint' => $this->fingerprint($config['data']['secret'] ?? null),
        ]);

        return $config;
    }

    /**
     * Fetches the configuration for the MyFatoorah payment gateway
     *
     * @param  int|null  $companyId  Scope credential lookup to this company. When null,
     *                               falls back to the first active Charge (legacy, unscoped) behavior.
     * @return array
     */
    public function getMyFatoorahConfig(?int $companyId = null): array
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
            'key_fingerprint' => $this->fingerprint($config['data']['api_key'] ?? null),
        ]);

        $myFatoorahChargeQuery = Charge::where('name', 'like', '%myfatoorah%')
            ->where('is_active', true);

        if ($companyId !== null) {
            $myFatoorahChargeQuery->where('company_id', $companyId);
        } else {
            $this->logUnscopedWarning('MyFatoorah');
        }

        $myFatoorahCharge = $myFatoorahChargeQuery->first();

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
                'key_fingerprint' => $this->fingerprint($config['data']['api_key']),
            ]);

            return $config;
        }

        return $config;
    }

    /**
     * @param  int|null  $companyId  Scope credential lookup to this company. When null,
     *                               falls back to the first active Charge (legacy, unscoped) behavior.
     */
    public function getHesabeConfig(?int $companyId = null): array
    {

        $configFromService = Config::get('services.hesabe');

        if($configFromService === null) {
            Log::warning('Hesabe does not have any configuration yet. Please contact your support team.');

            return [
                'status' => 'error',
                'message' => 'Hesabe payment gateway is not configured. Please contact your support team.'
            ];
        }

        $config = [
            'status' => 'success',
            'message' => 'Hesabe configuration loaded successfully.',
            'data' => $configFromService,
        ];

        Log::info('Hesabe gateway config loaded from config/services.php', [
            'base_url' => $config['data']['base_url'] ?? null,
            'key_fingerprint' => $this->fingerprint($config['data']['api_key'] ?? null),
        ]);

        $hesabeChargeQuery = Charge::where('name', 'like', '%hesabe%')
            ->where('is_active', true);

        if ($companyId !== null) {
            $hesabeChargeQuery->where('company_id', $companyId);
        } else {
            $this->logUnscopedWarning('Hesabe');
        }

        $hesabeCharge = $hesabeChargeQuery->first();

        if ($hesabeCharge && $hesabeCharge->api_key) {
            $config = [
                'status' => 'success',
                'message' => 'Hesabe configuration loaded successfully.',
                'data' => [
                    'api_key' => $hesabeCharge->api_key,
                    'base_url' => $configFromService['base_url'],
                    'access_code' => $configFromService['access_code'],
                    'merchant_code' => $configFromService['merchant_code'],
                    'iv_key' => $configFromService['iv_key'],
                ]
            ];

            Log::info('Hesabe gateway config loaded from database.', [
                'company_id' => $hesabeCharge->company_id,
                'base_url' => $config['data']['base_url'],
                'key_fingerprint' => $this->fingerprint($config['data']['api_key']),
            ]);

            return $config;
        }

        return $config;
    }

    /**
     * @param  int|null  $companyId  Scope credential lookup to this company. When null,
     *                               falls back to the first active Charge (legacy, unscoped) behavior.
     */
    public function getUPaymentConfig(?int $companyId = null): array
    {
        $configFromService = Config::get('services.uPayment');

        if($configFromService === null) {
            Log::warning('UPayment does not have any configuration yet. Please contact your support team.');

            return [
                'status' => 'error',
                'message' => 'UPayment payment gateway is not configured. Please contact your support team.'
            ];
        }

        $config = [
            'status' => 'success',
            'message' => 'UPayment configuration loaded successfully.',
            'data' => $configFromService,
        ];

        Log::info('UPayment gateway config loaded from config/services.php', [
            'base_url' => $config['data']['base_url'] ?? null,
            'key_fingerprint' => $this->fingerprint($config['data']['api_key'] ?? null),
        ]);

        $uPaymentChargeQuery = Charge::where('name', 'like', '%upayment%')
            ->where('is_active', true);

        if ($companyId !== null) {
            $uPaymentChargeQuery->where('company_id', $companyId);
        } else {
            $this->logUnscopedWarning('UPayment');
        }

        $uPaymentCharge = $uPaymentChargeQuery->first();

        if ($uPaymentCharge && $uPaymentCharge->api_key) {
            $config = [
                'status' => 'success',
                'message' => 'UPayment configuration loaded successfully.',
                'data' => [
                    'api_key' => $uPaymentCharge->api_key,
                    'base_url' => $configFromService['base_url'],
                ]
            ];

            Log::info('UPayment gateway config loaded from database.', [
                'company_id' => $uPaymentCharge->company_id,
                'base_url' => $config['data']['base_url'],
                'key_fingerprint' => $this->fingerprint($config['data']['api_key']),
            ]);

            return $config;
        }

        return $config;
    }
}