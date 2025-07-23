<?php

namespace Database\Factories;

use App\Models\TaskHotelDetail;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskHotelDetail>
 */
class TaskHotelDetailFactory extends Factory
{
    protected $model = TaskHotelDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => 1, // Will be overridden in tests
            'hotel_id' => $this->faker->numberBetween(1, 100),
            'booking_time' => $this->faker->dateTimeBetween('now', '+6 months'),
            'check_in' => $this->faker->dateTimeBetween('now', '+6 months'),
            'check_out' => $this->faker->dateTimeBetween('+1 day', '+1 year'),
            'room_reference' => $this->faker->regexify('[A-Z0-9]{8}'),
            'room_number' => $this->faker->optional()->regexify('[0-9]{3,4}'),
            'room_type' => $this->faker->randomElement(['Standard', 'Deluxe', 'Suite', 'Executive']),
            'room_amount' => $this->faker->numberBetween(1, 5),
            'room_details' => $this->faker->optional()->text(100),
            'room_promotion' => $this->faker->optional()->randomElement(['Early Bird', 'Last Minute', 'Group Discount']),
            'rate' => $this->faker->randomFloat(2, 50, 500),
            'meal_type' => $this->faker->randomElement(['Breakfast', 'Half Board', 'Full Board', 'All Inclusive']),
            'is_refundable' => $this->faker->randomElement(['yes', 'no']),
            'supplements' => $this->faker->optional()->text(50),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_refundable' => 'yes'
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_refundable' => 'no',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_refundable' => 'no',
        ]);
    }

    public function withRoom(): static
    {
        return $this->state(fn (array $attributes) => [
            'room_number' => $this->faker->regexify('[0-9]{3,4}'),
        ]);
    }

    public function luxury(): static
    {
        return $this->state(fn (array $attributes) => [
            'room_type' => 'Suite',
            'rate' => $this->faker->randomFloat(2, 300, 1000),
            'meal_type' => 'All Inclusive',
        ]);
    }
}
