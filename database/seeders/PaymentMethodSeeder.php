<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run()
    {
        $methods = [
            [
                'id' => 6,
                'arabic_name' => 'مدى',
                'english_name' => 'MADA',
                'code' => 'md',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 1.0,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/md.png',
            ],
            [
                'id' => 11,
                'arabic_name' => 'أبل الدفع',
                'english_name' => 'Apple Pay',
                'code' => 'ap',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 1.01,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/ap.png',
            ],
            [
                'id' => 2,
                'arabic_name' => 'فيزا / ماستر',
                'english_name' => 'VISA/MASTER',
                'code' => 'vm',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 0.101,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/vm.png',
            ],
            [
                'id' => 14,
                'arabic_name' => 'إس تي سي باي',
                'english_name' => 'STC Pay',
                'code' => 'stc',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 0.101,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/stc.png',
            ],
            [
                'id' => 8,
                'arabic_name' => 'كروت الدفع المدنية (الإمارات)',
                'english_name' => 'UAE Debit Cards',
                'code' => 'uaecc',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 0.125,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/uaecc.png',
            ],
            [
                'id' => 9,
                'arabic_name' => 'Visa/Master Direct 3DS Flow',
                'english_name' => 'Visa/Master Direct 3DS Flow',
                'code' => 'vm',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 0.125,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/vm.png',
            ],
            [
                'id' => 20,
                'arabic_name' => 'Visa/Master Direct',
                'english_name' => 'Visa/Master Direct',
                'code' => 'vm',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 0.101,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/vm.png',
            ],
            [
                'id' => 3,
                'arabic_name' => 'اميكس',
                'english_name' => 'AMEX',
                'code' => 'ae',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 0.125,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/ae.png',
            ],
            [
                'id' => 32,
                'arabic_name' => 'جوجل للدفع',
                'english_name' => 'GooglePay',
                'code' => 'gp',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 0.02,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/gp.png',
            ],
            [
                'id' => 5,
                'arabic_name' => 'بنفت',
                'english_name' => 'Benefit',
                'code' => 'b',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 0.12,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/b.png',
            ],
            [
                'id' => 1,
                'arabic_name' => 'كي نت',
                'english_name' => 'KNET',
                'code' => 'kn',
                'type' => 'myfatoorah',
                'is_active' => true,
                'service_charge' => 1.01,
                'image' => 'https://demo.myfatoorah.com/imgs/payment-methods/kn.png',
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(['code' => $method['code'], 'english_name' => $method['english_name']], $method);
        }
    }
}
