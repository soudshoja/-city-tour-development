<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $item = Item::all();

        return [
            'item_id' => 4,
            'description' => $this->faker->text(),
            'reference' => $this->faker->text(),
            'status' => $this->faker->randomElement(['paid', 'unpaid']),
        ];
    }
}
