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
            'farebase' => $this->faker->randomFloat(2, 100, 1000),
            'departure_time' => $this->faker->dateTime('now', 'Asia/Kuala_Lumpur'),
            'country_id_from' => $this->faker->numberBetween(1, 200),
            'airport_from' => $this->faker->regexify('[A-Z]{3}'),
            'terminal_from' => $this->faker->optional()->word(),
            'arrival_time' => $this->faker->dateTime('now', 'Asia/Kuala_Lumpur'),
            'country_id_to' => $this->faker->numberBetween(1, 200),
            'airport_to' => $this->faker->regexify('[A-Z]{3}'),
            'terminal_to' => $this->faker->optional()->word(),
            'airline_id' => $this->faker->numberBetween(1, 50),
            'flight_number' => $this->faker->regexify('[A-Z]{2}[0-9]{3,4}'),
            'ticket_number' => $this->faker->regexify('[0-9]{13}'),
            'class_type' => $this->faker->randomElement(['Economy','Business','First']),
            'baggage_allowed' => $this->faker->randomElement(['20kg', '30kg', '2x23kg']),
            'equipment' => $this->faker->optional()->word(),
            'flight_meal' => $this->faker->randomElement(['Vegetarian', 'Non-Vegetarian', 'Vegan']),
            'seat_no' => $this->faker->optional()->regexify('[0-9]{1,2}[A-Z]'),
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
