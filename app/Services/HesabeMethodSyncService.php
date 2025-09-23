<?php

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Log;

class HesabeMethodSyncService
{
    protected array $methods = [
        1 => 'KNET',
        2 => 'Visa/MasterCard',
        7 => 'Amex',
        9 => 'Apple Pay',
    ];

    public function sync(int $companyId)
    {
        try {
            $count = 0;

            foreach ($this->methods as $id => $name) {
                PaymentMethod::updateOrCreate(
                    [
                        'myfatoorah_id' => $id,
                        'company_id'    => $companyId,
                        'type'          => 'hesabe',
                    ],
                    [
                        'english_name'   => $name,
                        'arabic_name'    => null,
                        'code'           => strtolower(str_replace(['/', ' '], '_', $name)),
                        'is_active'      => true,
                        'currency'       => 'KWD',
                        'service_charge' => 0,
                        'self_charge'    => 0,
                        'paid_by'        => 'Company',
                        'charge_type'    => 'Percent',
                        'image'          => null,
                    ]
                );

                $count++;
            }

            Log::info("Hesabe methods synced", [
                'company_id' => $companyId,
                'count' => $count,
            ]);

            return $count;
        } catch (\Throwable $e) {
            Log::error('Hesabe sync error', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
