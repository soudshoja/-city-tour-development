<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Log;

class UPaymentMethodSyncService
{
    public function sync(int $companyId)
    {
        try {
            $companyIds = $companyId ? collect([$companyId])
                : Charge::query()
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->where('name', 'like', '%upayment%')
                ->whereNotNull('company_id')
                ->pluck('company_id')
                ->unique();

            if ($companyIds->isEmpty()) {
                Log::info('UPayment sync: no companies found');
                return 0;
            }

            $count = 0;

            foreach ($companyIds as $cid) {
                PaymentMethod::withoutGlobalScopes()
                    ->where('company_id', $cid)
                    ->where('type', 'upayment')
                    ->update(['is_active' => false]);

                PaymentMethod::updateOrCreate(
                    [
                        'company_id' => $cid,
                        'type' => 'upayment',
                        'code' => 'knet',
                    ],
                    [
                        'english_name' => 'KNET',
                        'arabic_name' => null,
                        'is_active' => true,
                        'currency' => 'KWD',
                        'service_charge' => 0,
                        'self_charge' => 0,
                        'paid_by' => 'Company',
                        'charge_type' => 'Percent',
                        'image' => null,
                    ]
                );
                $count++;

                PaymentMethod::updateOrCreate(
                    [
                        'company_id' => $cid,
                        'type' => 'upayment',
                        'code' => 'cc',
                    ],
                    [
                        'english_name' => 'Credit Card',
                        'arabic_name' => null,
                        'is_active' => true,
                        'currency' => 'KWD',
                        'service_charge' => 0,
                        'self_charge' => 0,
                        'paid_by' => 'Company',
                        'charge_type' => 'Percent',
                        'image' => null,
                    ]
                );
                $count++;

                PaymentMethod::updateOrCreate(
                    [
                        'company_id' => $cid,
                        'type' => 'upayment',
                        'code' => 'samsung-pay',
                    ],
                    [
                        'english_name' => 'Samsung Pay',
                        'arabic_name' => null,
                        'is_active' => true,
                        'currency' => 'KWD',
                        'service_charge' => 0,
                        'self_charge' => 0,
                        'paid_by' => 'Company',
                        'charge_type' => 'Percent',
                        'image' => null,
                    ]
                );
                $count++;

                PaymentMethod::updateOrCreate(
                    [
                        'company_id' => $cid,
                        'type' => 'upayment',
                        'code' => 'apple-pay',
                    ],
                    [
                        'english_name' => 'Apple Pay',
                        'arabic_name' => null,
                        'is_active' => true,
                        'currency' => 'KWD',
                        'service_charge' => 0,
                        'self_charge' => 0,
                        'paid_by' => 'Company',
                        'charge_type' => 'Percent',
                        'image' => null,
                    ]
                );
                $count++;

                PaymentMethod::updateOrCreate(
                    [
                        'company_id' => $cid,
                        'type' => 'upayment',
                        'code' => 'apple-pay-knet',
                    ],
                    [
                        'english_name' => 'Apple Pay KNET',
                        'arabic_name' => null,
                        'is_active' => true,
                        'currency' => 'KWD',
                        'service_charge' => 0,
                        'self_charge' => 0,
                        'paid_by' => 'Company',
                        'charge_type' => 'Percent',
                        'image' => null,
                    ]
                );
                $count++;

                PaymentMethod::updateOrCreate(
                    [
                        'company_id' => $cid,
                        'type' => 'upayment',
                        'code' => 'google-pay',
                    ],
                    [
                        'english_name' => 'Google Pay',
                        'arabic_name' => null,
                        'is_active' => true,
                        'currency' => 'KWD',
                        'service_charge' => 0,
                        'self_charge' => 0,
                        'paid_by' => 'Company',
                        'charge_type' => 'Percent',
                        'image' => null,
                    ]
                );
                $count++;
            }

            Log::info('UPayment methods synced', ['total' => $count]);
            return $count;
        } catch (\Throwable $e) {
            Log::error('UPayment sync error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
