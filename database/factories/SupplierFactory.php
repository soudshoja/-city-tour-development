<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => 'TestSupplier',
            'auth_type' => 'basic',
            'has_hotel' => false,
            'has_flight' => true,
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country_id' => Country::factory(),
            'website' => $this->faker->url(),
            'payment_terms' => $this->faker->randomElement(['NET30', 'COD', 'Prepaid']),
        ];
    }
}
