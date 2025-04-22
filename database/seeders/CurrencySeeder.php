<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $countries = Country::all();
        $currencies = [
            [ 'name' => 'United States Dollar', 'iso_code' => 'USD', 'symbol' => '$', 'country_name' => 'United States'],
            [ 'name' => 'Euro', 'iso_code' => 'EUR', 'symbol' => '€', 'country_name' => 'European Union'],
            [ 'name' => 'British Pound Sterling', 'iso_code' => 'GBP', 'symbol' => '£', 'country_name' => 'United Kingdom'],
            [ 'name' => 'Japanese Yen', 'iso_code' => 'JPY', 'symbol' => '¥', 'country_name' => 'Japan'],
            [ 'name' => 'Australian Dollar', 'iso_code' => 'AUD', 'symbol' => '$', 'country_name' => 'Australia'],
            [ 'name' => 'Canadian Dollar', 'iso_code' => 'CAD', 'symbol' => '$', 'country_name' => 'Canada'],
            [ 'name' => 'Swiss Franc', 'iso_code' => 'CHF', 'symbol' => 'CHF', 'country_name' => 'Switzerland'],
            [ 'name' => 'Chinese Yuan Renminbi', 'iso_code' => 'CNY', 'symbol' => '¥', 'country_name' => 'China'],
            [ 'name' => 'Indian Rupee', 'iso_code' => 'INR', 'symbol' => '₹', 'country_name' => 'India'],
            [ 'name' => 'Brazilian Real', 'iso_code' => 'BRL', 'symbol' => 'R$', 'country_name' => 'Brazil'],
            [ 'name' => 'Kuwait Dinar', 'iso_code' => 'KWD', 'symbol' => 'د.ك', 'country_name' => 'Kuwait'],
            [ 'name' => 'South African Rand', 'iso_code' => 'ZAR', 'symbol' => 'R', 'country_name' => 'South Africa'],
            [ 'name' => 'Singapore Dollar', 'iso_code' => 'SGD', 'symbol' => '$', 'country_name' => 'Singapore'],
            [ 'name' => 'Hong Kong Dollar', 'iso_code' => 'HKD', 'symbol' => '$', 'country_name' => 'Hong Kong'],
            [ 'name' => 'New Zealand Dollar', 'iso_code' => 'NZD', 'symbol' => '$', 'country_name' => 'New Zealand'],
            [ 'name' => 'Mexican Peso', 'iso_code' => 'MOP', 'symbol' => '$', 'country_name' => 'Mexico'],
            [ 'name' => 'Norwegian Krone', 'iso_code' => 'NOK', 'symbol' => 'Nkr', 'country_name' => 'Norway'],
            [ 'name' => 'Russian Ruble', 'iso_code' => 'RUB', 'symbol' => '₽', 'country_name' => 'Russia'],
            [ 'name' => 'Turkish Lira', 'iso_code' => 'TRY', 'symbol' => 'Tl', 'country_name' => 'Turkey'],
            [ 'name' => 'Saudi Riyal', 'iso_code' => 'SAR', 'symbol' => 'SR', 'country_name' => 'Saudi Arabia'],
            [ 'name' => 'United Arab Emirates Dirham', 'iso_code' => 'AED', 'symbol' => 'Dh', 'country_name' => 'United Arab Emirates'],
            [ 'name' => 'Thai Baht', 'iso_code' => 'THB', 'symbol' => '฿', 'country_name' => 'Thailand'],
            [ 'name' => 'Indonesian Rupiah', 'iso_code' => 'IDR', 'symbol' => 'Rp', 'country_name' => 'Indonesia'],
            [ 'name' => 'Philippine Peso', 'iso_code' => 'PHP', 'symbol' => '₱', 'country_name' => 'Philippines'],
            [ 'name' => 'Vietnamese Dong', 'iso_code' => 'VND', 'symbol' => '₫', 'country_name' => 'Vietnam'],
            [ 'name' => 'Malaysian Ringgit', 'iso_code' => 'MYR', 'symbol' => 'RM', 'country_name' => 'Malaysia'],
            [ 'name' => 'Pakistani Rupee', 'iso_code' => 'PKR', 'symbol' => '₨', 'country_name' => 'Pakistan'],
            [ 'name' => 'Bangladeshi Taka', 'iso_code' => 'BDT', 'symbol' => '৳', 'country_name' => 'Bangladesh'],
            [ 'name' => 'Egyptian Pound', 'iso_code' => 'EGP', 'symbol' => '£', 'country_name' => 'Egypt'],
            [ 'name' => 'Israeli New Shekel', 'iso_code' => 'ILS', 'symbol' => '₪', 'country_name' => 'Israel'],
            [ 'name' => 'Colombian Peso', 'iso_code' => 'COP', 'symbol' => '$', 'country_name' => 'Colombia'],
            [ 'name' => 'Chilean Peso', 'iso_code' => 'CLP', 'symbol' => '$', 'country_name' => 'Chile'],
            [ 'name' => 'Peruvian Sol', 'iso_code' => 'PEN', 'symbol' => 'S/', 'country_name' => 'Peru'],
            [ 'name' => 'Icelandic Króna', 'iso_code' => 'ISK', 'symbol' => 'kr', 'country_name' => 'Iceland'],
            [ 'name' => 'Danish Krone', 'iso_code' => 'DKK', 'symbol' => 'kr', 'country_name' => 'Denmark'],
            [ 'name' => 'Czech Koruna', 'iso_code' => 'Czk', 'symbol' => 'Kč', 'country_name' => 'Czech Republic'],
            [ 'name' => 'Hungarian Forint', 'iso_code' => 'HUF', 'symbol' => 'Ft', 'country_name' => 'Hungary'],
            [ 'name' => 'Bulgarian Lev', 'iso_code' => 'BGN', 'symbol' => 'лв.', 'country_name' => 'Bulgaria'],
            [ 'name' => 'Romanian Leu', 'iso_code' => 'RON', 'symbol' => 'lei', 'country_name' => 'Romania'],
            [ 'name' => 'Croatian Kuna', 'iso_code' => 'HRK', 'symbol' => 'kn', 'country_name' => 'Croatia'],
            [ 'name' => 'Serbian Dinar', 'iso_code' => 'SER', 'symbol' => 'дин', 'country_name' => 'Serbia'],
            [ 'name' => 'Bosnia and Herzegovina Convertible Mark', 'iso_code' => 'BAM', 'symbol' => 'КМ', 'country_name' => 'Bosnia and Herzegovina'],
            [ 'name' => 'Macedonian Denar', 'iso_code' => 'MKD', 'symbol' => 'ден', 'country_name' => 'North Macedonia'],
            [ 'name' => 'Albanian Lek', 'iso_code' => 'ALL', 'symbol' => 'L', 'country_name' => 'Albania'],
            [ 'name' => 'Lithuanian Litas', 'iso_code' => 'LTL', 'symbol' => 'Lt', 'country_name' => 'Lithuania'],
            [ 'name' => 'Latvian Lats', 'iso_code' => 'LVL', 'symbol' => 'Ls', 'country_name' => 'Latvia'],
            [ 'name' => 'Estonian Kroon', 'iso_code' => 'EEK', 'symbol' => 'kr', 'country_name' => 'Estonia'],
            [ 'name' => 'Cypriot Pound', 'iso_code' => 'CYP', 'symbol' => '£', 'country_name' => 'Cyprus'],
            [ 'name' => 'Ukrainian Hryvnia', 'iso_code' => 'UAH', 'symbol' => '₴', 'country_name' => 'Ukraine'],
            [ 'name' => 'Belarusian Ruble', 'iso_code' => 'BYN', 'symbol' => 'p.', 'country_name' => 'Belarus'],
            [ 'name' => 'Kazakhstani Tenge', 'iso_code' => 'TENGE', 'symbol' => 'T', 'country_name' => 'Kazakhstan'],
            [ 'name' => 'Armenian Dram', 'iso_code' => 'AED', 'symbol' => 'Dram', 'country_name' => 'Armenia'],
            [ 'name' => 'Georgian Lari', 'iso_code' => 'AED', 'symbol' => '₾', 'country_name' => 'Georgia'],
            [ 'name' => 'Azerbaijani Manat', 'iso_code' => 'AED', 'symbol' => '₼', 'country_name' => 'Azerbaijan'],
            [ 'name' => 'Moldovan Leu', 'iso_code' => 'AED', 'symbol' => 'L', 'country_name' => 'Moldova'],
            [ 'name' => 'Kyrgyzstani Som', 'iso_code' => 'AED', 'symbol' => 'S', 'country_name' => 'Kyrgyzstan'],
            [ 'name' => 'Tajikistani Somoni', 'iso_code' => 'AED', 'symbol' => 'Somoni', 'country_name' => 'Tajikistan'],
            [ 'name' => 'Uzbekistani Som', 'iso_code' => 'AED', 'symbol' => 'Sum', 'country_name' => 'Uzbekistan'],
            [ 'name' => 'Turkmenistani Manat', 'iso_code' => 'AED', 'symbol' => 'm', 'country_name' => 'Turkmenistan'],
        ];

        foreach ($countries as $country) {
            
            // Find the currency for the country

            $currency = array_filter($currencies, function ($currency) use ($country) {
                if(isset($currency[
                    'country_name'
                ])) {
                    return $currency['country_name'] === $country->name;
                }
            });

            if (!empty($currency)) {
                $currency = array_shift($currency);
                $currencies[] = [
                    'name' => $currency['name'],
                    'iso_code' => $currency['iso_code'],
                    'symbol' => $currency['symbol'],
                    'country_id' => $country->id ?? null,
                ];
            }
        }

        // Insert the currencies into the database
        // DB::table('currencies')->insert($currencies);
        // Optionally, you can also use the Currency model to create the records
        foreach($currencies as $currency) {
            Currency::firstOrCreate([
                'name' => $currency['name'],
                'iso_code' => $currency['iso_code'],
                'symbol' => $currency['symbol'],
                'country_id' => $currency['country_id'] ?? null,
            ]);
        }

    }
}
