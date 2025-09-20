<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\PaymentMethod;
use Illuminate\Console\Command;

class SyncUPaymentMethods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-u-payment-methods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle()
    {
        $this->info('Start to sync payment method for UPayment payment gateway');
        $this->info('finding companies with active UPayment charges...');

        $companiesWithUPayment = Charge::where('is_active', true)
            ->where('name', 'like', '%upayment%')
            ->whereNotNull('company_id')
            ->distinct()
            ->pluck('company_id');

        $totalCompanies = count($companiesWithUPayment);
        $this->info("Found {$totalCompanies} companies with active UPayment charges");
        
        if ($totalCompanies === 0) {
            $this->warn('No companies found. Exiting...');
            return;
        }

        $this->info('Starting payment method synchronization...');
        $progressBar = $this->output->createProgressBar($totalCompanies);
        $progressBar->start();
        
        foreach ($companiesWithUPayment as $index => $companyId) {
            $this->line("\nProcessing company ID: {$companyId} (" . ($index + 1) . "/{$totalCompanies})");
            
           PaymentMethod::updateOrCreate(
                [
                    'company_id' => $companyId,
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
                    'description' => '',
                    'image' => null,
                ]
            ); 

           PaymentMethod::updateOrCreate(
                [
                    'company_id' => $companyId,
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
                    'description' => '',
                    'image' => null,
                ]
            ); 

           PaymentMethod::updateOrCreate(
                [
                    'company_id' => $companyId,
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
                    'description' => '',
                    'image' => null,
                ]
            ); 

           PaymentMethod::updateOrCreate(
                [
                    'company_id' => $companyId,
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
                    'description' => '',
                    'image' => null,
                ]
            ); 

           PaymentMethod::updateOrCreate(
                [
                    'company_id' => $companyId,
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
                    'description' => '',
                    'image' => null,
                ]
            ); 

           PaymentMethod::updateOrCreate(
                [
                    'company_id' => $companyId,
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
                    'description' => '',
                    'image' => null,
                ]
            ); 
            
            $this->line("✓ Completed payment methods for company ID: {$companyId}");
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line('');
        $this->info('✓ UPayment synchronization completed successfully!');
        $this->info("Total companies processed: {$totalCompanies}");
    }
}
