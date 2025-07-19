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
            'task_id' => Task::factory(),
            'hotel_name' => $this->faker->company() . ' Hotel',
            'hotel_address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'check_in_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'check_out_date' => $this->faker->dateTimeBetween('+1 day', '+1 year'),
            'room_type' => $this->faker->randomElement(['Standard', 'Deluxe', 'Suite', 'Executive']),
            'number_of_rooms' => $this->faker->numberBetween(1, 5),
            'number_of_guests' => $this->faker->numberBetween(1, 8),
            'meal_plan' => $this->faker->randomElement(['Breakfast', 'Half Board', 'Full Board', 'All Inclusive']),
            'hotel_rating' => $this->faker->numberBetween(1, 5),
            'booking_reference' => $this->faker->regexify('[A-Z0-9]{8}'),
            'room_number' => $this->faker->optional()->regexify('[0-9]{3,4}'),
            'special_requests' => $this->faker->optional()->text(100),
            'booking_status' => $this->faker->randomElement(['confirmed', 'pending', 'cancelled']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_status' => 'confirmed',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_status' => 'pending',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_status' => 'cancelled',
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
            'hotel_rating' => 5,
            'meal_plan' => 'All Inclusive',
        ]);
    }
}
