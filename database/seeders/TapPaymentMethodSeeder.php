<?php

namespace Database\Seeders;

use App\Models\Charge;
use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class TapPaymentMethodSeeder extends Seeder
{
    /**
     * Seed Tap payment methods for all companies.
     * 
     * Standard redirect flow methods (ready for production):
     * - KNET (Kuwait - KWD)
     * - MADA (Saudi Arabia - SAR)
     * - BENEFIT (Bahrain - BHD)
     * - QPay/NAPS (Qatar - QAR)
     * - Deema BNPL (Kuwait - KWD)
     * - Fawry (Egypt - EGP)
     * - Visa, Mastercard, Amex cards
     * 
     * Complex implementations (deferred - see notes below):
     * - STC Pay: Requires two-step OTP flow
     * - Tabby: BNPL with special integration requirements
     */
    public function run(): void
    {
        // Get Tap gateway
        $tapGateway = Charge::where('name', 'Tap')->first();
        
        if (!$tapGateway) {
            $this->command->warn('Tap gateway not found. Please ensure ChargeSeeder has run first.');
            return;
        }

        // Get all companies
        $companies = Company::all();
        
        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Payment methods will be seeded when companies are created.');
            return;
        }

        // Define standard payment methods (redirect flow)
        $standardMethods = [
            // Cards - International (all use src_card source)
            [
                'code' => 'src_card',
                'arabic_name' => 'بطاقات ائتمانية',
                'english_name' => 'Credit/Debit Cards',
                'type' => 'Tap',
                'currency' => 'KWD', // Multi-currency support
                'is_active' => true,
                'charge_type' => 'Percent',
                'service_charge' => 2.5,
                'self_charge' => 0,
                'paid_by' => 'Company',
                'description' => 'All international cards (Visa, Mastercard, Amex) - Source: src_card - Standard redirect flow',
                'image' => null,
            ],
            
            // Kuwait
            [
                'code' => 'src_kw.knet',
                'arabic_name' => 'كي نت',
                'english_name' => 'KNET',
                'type' => 'Tap',
                'currency' => 'KWD',
                'is_active' => true,
                'charge_type' => 'Percent',
                'service_charge' => 0.150,
                'self_charge' => 0,
                'paid_by' => 'Company',
                'description' => 'Kuwait debit cards - Source: src_kw.knet - Redirect flow',
                'image' => null,
            ],
            [
                'code' => 'src_deema',
                'arabic_name' => 'ديمة',
                'english_name' => 'Deema BNPL',
                'type' => 'Tap',
                'currency' => 'KWD',
                'is_active' => true,
                'charge_type' => 'Percent',
                'service_charge' => 2.0,
                'self_charge' => 0,
                'paid_by' => 'Company',
                'description' => 'Kuwait Buy Now Pay Later (Shariah-compliant) - Source: src_deema - Redirect flow - Min: 10 KWD',
                'image' => null,
            ],
            
            // Saudi Arabia
            [
                'code' => 'src_sa.mada',
                'arabic_name' => 'مدى',
                'english_name' => 'MADA',
                'type' => 'Tap',
                'currency' => 'SAR',
                'is_active' => true,
                'charge_type' => 'Percent',
                'service_charge' => 1.5,
                'self_charge' => 0,
                'paid_by' => 'Company',
                'description' => 'Saudi debit cards - Source: src_sa.mada - Redirect flow',
                'image' => null,
            ],
            
            // Bahrain
            [
                'code' => 'src_bh.benefit',
                'arabic_name' => 'بنفت',
                'english_name' => 'BENEFIT',
                'type' => 'Tap',
                'currency' => 'BHD',
                'is_active' => false,
                'charge_type' => 'Flat Rate',
                'service_charge' => 0.100,
                'self_charge' => 0,
                'paid_by' => 'Company',
                'description' => 'Bahrain debit cards - Source: src_bh.benefit - Redirect flow',
                'image' => null,
            ],
            
            // Qatar
            [
                'code' => 'src_qa.qpay',
                'arabic_name' => 'كيو باي',
                'english_name' => 'QPay (NAPS)',
                'type' => 'Tap',
                'currency' => 'QAR',
                'is_active' => false,
                'charge_type' => 'Flat Rate',
                'service_charge' => 0.50,
                'self_charge' => 0,
                'paid_by' => 'Company',
                'description' => 'Qatar domestic debit - Source: src_qa.qpay - Redirect flow - Requires Tap support to enable',
                'image' => null,
            ],
            
            // Egypt
            // [
            //     'code' => 'src_fawry',
            //     'arabic_name' => 'فوري',
            //     'english_name' => 'Fawry',
            //     'type' => 'Tap',
            //     'currency' => 'EGP',
            //     'is_active' => false, // Disabled by default until configured
            //     'charge_type' => 'Percent',
            //     'service_charge' => 2.0,
            //     'self_charge' => 0,
            //     'paid_by' => 'Company',
            //     'description' => 'Egypt payment network - Source: src_eg.fawry - Redirect flow',
            //     'image' => null,
            // ],
            
            // Digital Wallets
            // [
            //     'code' => 'src_apple_pay',
            //     'arabic_name' => 'أبل باي',
            //     'english_name' => 'Apple Pay',
            //     'type' => 'Tap',
            //     'currency' => 'KWD',
            //     'is_active' => true,
            //     'charge_type' => 'Percent',
            //     'service_charge' => 2.5,
            //     'self_charge' => 0,
            //     'paid_by' => 'Company',
            //     'description' => 'Apple Pay digital wallet - Source: src_apple_pay - Redirect flow',
            //     'image' => null,
            // ],
            // [
            //     'code' => 'src_samsung_pay',
            //     'arabic_name' => 'سامسونج باي',
            //     'english_name' => 'Samsung Pay',
            //     'type' => 'Tap',
            //     'currency' => 'KWD',
            //     'is_active' => false, // Disabled by default
            //     'charge_type' => 'Percent',
            //     'service_charge' => 2.5,
            //     'self_charge' => 0,
            //     'paid_by' => 'Company',
            //     'description' => 'Samsung Pay digital wallet - Source: src_samsung_pay - Redirect flow',
            //     'image' => null,
            // ],
        ];

        // Create payment methods for each company
        $createdCount = 0;
        foreach ($companies as $company) {
            foreach ($standardMethods as $method) {
                // Check if already exists
                $exists = PaymentMethod::where('company_id', $company->id)
                    ->where('code', $method['code'])
                    ->exists();
                
                if (!$exists) {
                    PaymentMethod::create([
                        'charge_id' => $tapGateway->id,
                        'company_id' => $company->id,
                        'myfatoorah_id' => null, // Tap methods don't use MyFatoorah
                        'code' => $method['code'],
                        'arabic_name' => $method['arabic_name'],
                        'english_name' => $method['english_name'],
                        'type' => $method['type'],
                        'currency' => $method['currency'],
                        'is_active' => $method['is_active'],
                        'charge_type' => $method['charge_type'],
                        'service_charge' => $method['service_charge'],
                        'self_charge' => $method['self_charge'],
                        'paid_by' => $method['paid_by'],
                        'description' => $method['description'],
                        'image' => $method['image'],
                    ]);
                    $createdCount++;
                }
            }
        }

        $this->command->info("Created {$createdCount} Tap payment methods for " . $companies->count() . " companies.");
        $this->command->info("\nStandard methods seeded: Credit/Debit Cards (Visa/Mastercard/Amex), KNET, MADA, BENEFIT, QPay, Deema, Fawry, Apple Pay, Samsung Pay");
        $this->command->newLine();
        $this->command->warn('⚠️  DEFERRED IMPLEMENTATIONS:');
        $this->command->line('');
        $this->command->line('1. STC Pay (Saudi Arabia - SAR)');
        $this->command->line('   - Source: src_sa.stcpay');
        $this->command->line('   - Requires TWO-STEP OTP flow:');
        $this->command->line('     Step 1: Create charge with phone number');
        $this->command->line('     Step 2: Customer receives OTP via SMS');
        $this->command->line('     Step 3: Update charge with OTP to complete payment');
        $this->command->line('   - Needs custom UI for phone input and OTP submission');
        $this->command->line('   - Transaction expiry: 3 minutes (vs standard 30 minutes)');
        $this->command->line('   - Does NOT support authorize or recurring payments');
        $this->command->line('');
        $this->command->line('2. Tabby BNPL (Buy Now Pay Later)');
        $this->command->line('   - Source: src_tabby');
        $this->command->line('   - Requires special integration with Tabby API');
        $this->command->line('   - Customer credit check and approval flow');
        $this->command->line('   - Split payment scheduling (4 installments)');
        $this->command->line('');
        $this->command->info('These methods require custom implementation and will be added in future iterations.');
    }
}
