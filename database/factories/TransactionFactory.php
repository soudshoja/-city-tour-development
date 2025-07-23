<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => $this->faker->unique()->uuid(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'transaction_type' => $this->faker->randomElement(['credit', 'debit']),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed']),
            'user_id' => 1, // Will be overridden in tests
            'created_at' => now(),
            'updated_at' => now(),
            'description' => $this->faker->optional()->sentence(),
            'currency' => $this->faker->currencyCode(),
        ];
    }
}
