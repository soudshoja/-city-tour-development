<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentMethod;

class SyncHesabePaymentMethods extends Command 
{
    protected $signature = 'app:sync-hesabe-methods {--company=* : Limit to one or more company ID}';
    protected $description = 'Sync Hesabe payment methods for every company that has active Hesabe gateway';

    public function handle()
    {
        $this->info('Start to sync payment method for Hesabe payment gateway');

        $methods = [
            1 => 'KNET',
            2 => 'Visa/MasterCard',
            7 => 'Amex',
            9 => 'Apple Pay',
        ];

        $companyIds = $this->option('company');
        if (empty($companyIds)) {
            $companyIds = [1];
        }

        $total = 0; $error = 0;

        foreach ($companyIds as $company) {
            foreach ($methods as $id => $name) {
                try {
                    DB::transaction(function () use ($company, $id, $name, &$total) {
                        PaymentMethod::updateOrCreate(
                            [
                                'myfatoorah_id' => $id,
                                'company_id' => $company,
                                'type' => 'hesabe',
                            ],
                            [
                                'english_name' => $name,
                                'arabic_name' => null, 
                                'code' => strtolower(str_replace(['/', ' '], '_', $name)),
                                'is_active' => true,
                                'currency' => 'KWD',
                                'service_charge' => 0,
                                'self_charge' => 0,
                                'paid_by' => 'Company',
                                'charge_type' => 'Percent',
                                'description' => '',
                                'image' => null,
                            ]
                        );

                        $total++;
                    });
                } catch (\Exception $e) {
                    $this->error("Failed syncing {$name} for company {$company}: " . $e->getMessage());
                    $error++;
                }
            }
        }

        $this->info("Syncing completed. Total methods: {$total}. Errors: {$error}");
    }    
}
