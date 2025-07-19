<?php

namespace Database\Factories;

use App\Models\TaskFlightDetail;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskFlightDetail>
 */
class TaskFlightDetailFactory extends Factory
{
    protected $model = TaskFlightDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'flight_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'departure_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'departure_time' => $this->faker->time(),
            'arrival_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'arrival_time' => $this->faker->time(),
            'departure_airport' => $this->faker->regexify('[A-Z]{3}'),
            'arrival_airport' => $this->faker->regexify('[A-Z]{3}'),
            'flight_number' => $this->faker->regexify('[A-Z]{2}[0-9]{3,4}'),
            'airline' => $this->faker->company(),
            'aircraft_type' => $this->faker->randomElement(['Boeing 737', 'Airbus A320', 'Boeing 777']),
            'flight_class' => $this->faker->randomElement(['Economy', 'Business', 'First']),
            'baggage_allowance' => $this->faker->randomElement(['20kg', '30kg', '2x23kg']),
            'seat_number' => $this->faker->regexify('[0-9]{1,2}[A-F]'),
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
}
