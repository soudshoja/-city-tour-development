<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_date' => $this->faker->dateTimeThisYear(),
            'description' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'type' => $this->faker->randomElement(['debit', 'credit']),
            'invoice_detail_id' => null, // Will be set in tests
            'created_at' => now(),
            'updated_at' => now(),
            'user_id' => 1, // Will be overridden in tests
            'company_id' => 1, // Will be overridden in tests
            'account_id' => 1, // Will be overridden in tests
            'task_id' => null, // Will be set in tests
        ];
    }
}
