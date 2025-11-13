<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Charge;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'charge_id' => Charge::factory(),
            'myfatoorah_id' => null,
            'company_id' => Company::factory(),
            'arabic_name' => $this->faker->word(),
            'english_name' => $this->faker->words(2, true),
            'code' => strtoupper($this->faker->lexify('???')),
            'type' => $this->faker->randomElement(['myfatoorah', 'tap', 'hesabe', 'upayment']),
            'is_active' => true,
            'currency' => $this->faker->randomElement(['KWD', 'SAR', 'USD', 'AED', 'BHD', 'QAR']),
            'service_charge' => $this->faker->randomFloat(2, 0, 10),
            'self_charge' => $this->faker->optional()->randomFloat(2, 0, 5),
            'paid_by' => $this->faker->randomElement(['Company', 'Client']),
            'charge_type' => $this->faker->randomElement(['Percent', 'Flat Rate']),
            'description' => $this->faker->optional()->sentence(),
            'image' => null,
        ];
    }

    public function tap(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'tap',
        ]);
    }

    public function myFatoorah(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'myfatoorah',
            'myfatoorah_id' => $this->faker->numberBetween(1, 100),
        ]);
    }

    public function hesabe(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'hesabe',
        ]);
    }

    public function upayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'upayment',
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function clientPays(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_by' => 'Client',
        ]);
    }

    public function companyPays(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_by' => 'Company',
        ]);
    }

    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'charge_type' => 'Percent',
        ]);
    }

    public function flatRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'charge_type' => 'Flat Rate',
        ]);
    }

    public function currency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }

    public function knet(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'tap',
            'english_name' => 'KNET',
            'code' => 'src_kw.knet',
            'currency' => 'KWD',
            'charge_type' => 'Percent',
            'service_charge' => 2.00,
        ]);
    }

    public function mada(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'tap',
            'english_name' => 'MADA',
            'code' => 'src_sa.mada',
            'currency' => 'SAR',
            'charge_type' => 'Percent',
            'service_charge' => 2.00,
        ]);
    }
}
