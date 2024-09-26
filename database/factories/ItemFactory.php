<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_ref' => $this->faker->word,
            'description' => $this->faker->sentence,
            'item_type' => $this->faker->word,
            'client_id' => $this->faker->numberBetween(1, 10),
            'agent_id' => $this->faker->numberBetween(1, 10),
            'item_status' => $this->faker->word,
            'created_at' => $this->faker->dateTime(),
            'updated_at' => $this->faker->dateTime(),
            'item_id' => $this->faker->word,
            'item_code' => $this->faker->word,
            'time_signed' => $this->faker->dateTime(),
            'client_email' => $this->faker->unique()->safeEmail,
            'agent_email' => $this->faker->unique()->safeEmail,
            'total_price' => $this->faker->randomFloat(2, 0, 1000),
            'payment_date' => $this->faker->dateTime(),
            'paid' => $this->faker->boolean,
            'payment_time' => $this->faker->dateTime(),
            'payment_amount' => $this->faker->randomFloat(2, 0, 1000),
            'refunded' => $this->faker->boolean,
            'trip_name' => $this->faker->word,
            'trip_code' => $this->faker->word,
            'client_email' => $this->faker->unique()->safeEmail,
        ];
    }
}
