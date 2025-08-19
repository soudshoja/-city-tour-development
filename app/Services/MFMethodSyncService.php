<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentMethod;
use Throwable;

class MFMethodSyncService
{
    public function sync(int $companyId)
    {
        try {
            $response = Http::withToken(config('services.myfatoorah.api_key'))
                ->post(config('services.myfatoorah.base_url') . '/InitiatePayment', [
                    'InvoiceAmount' => 100,
                    'CurrencyIso' => 'KWD',
                ]);

            Log::info('MyFatoorah payment methods sync request sent.', [
                'url' => config('services.myfatoorah.base_url') . 'InitiatePayment',
                'response' => $response->body(),
            ]);

            $result = $response->json();

            if (!data_get($result, 'IsSuccess')) {
                Log::warning('MyFatoorah payment methods sync failed.', [
                    'company_id' => $companyId,
                    'message' => $result['Message'] ?? 'Unknown',
                    'errors' => $result['ValidationErrors'] ?? [],
                ]);
                return false;
            }

            PaymentMethod::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('type', 'myfatoorah')
                ->update(['is_active' => false]);

            $methods = data_get($result, 'Data.PaymentMethods', []);
            foreach ($methods as $method) {
                PaymentMethod::updateOrCreate(
                    [
                        'myfatoorah_id' => $method['PaymentMethodId'],
                        'company_id' => $companyId,
                        'type' => 'myfatoorah',
                    ],
                    [
                        'code' => $method['PaymentMethodCode'],
                        'arabic_name' => $method['PaymentMethodAr'],
                        'english_name' => $method['PaymentMethodEn'],
                        'is_active' => 1,
                        'currency' => $method['CurrencyIso'],
                        'service_charge' => $method['ServiceCharge'] ?? 0,
                        'image' => $method['ImageUrl'] ?? null,
                    ]
                );
            }

            Log::info('MF methods synced', ['company_id' => $companyId, 'count' => count($methods)]);
            return count($methods);
        } catch (Throwable $e) {
            Log::error('MF sync exception', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
