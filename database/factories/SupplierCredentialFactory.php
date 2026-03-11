<?php

namespace Database\Factories;

use App\Models\SupplierCredential;
use App\Models\Supplier;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupplierCredential>
 */
class SupplierCredentialFactory extends Factory
{
    protected $model = SupplierCredential::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'company_id' => Company::factory(),
            'environment' => $this->faker->randomElement(['sandbox', 'production']),
            'type' => $this->faker->randomElement(['basic', 'oauth']),
            'username' => $this->faker->optional()->userName(),
            'password' => $this->faker->optional()->password(),
            'client_id' => $this->faker->optional()->uuid(),
            'client_secret' => $this->faker->optional()->sha256(),
            'access_token' => $this->faker->optional()->sha256(),
            'refresh_token' => $this->faker->optional()->sha256(),
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function sandbox(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'sandbox',
        ]);
    }

    public function production(): static
    {
        return $this->state(fn (array $attributes) => [
            'environment' => 'production',
        ]);
    }

    public function basicAuth(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'basic',
            'username' => $this->faker->userName(),
            'password' => $this->faker->password(),
            'client_id' => null,
            'client_secret' => null,
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ]);
    }

    public function oauthAuth(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'oauth',
            'username' => null,
            'password' => null,
            'client_id' => $this->faker->uuid(),
            'client_secret' => $this->faker->sha256(),
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 year'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }
}
