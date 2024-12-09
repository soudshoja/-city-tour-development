<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nette\Utils\Random;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notifications>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = ['unread', 'read'];
        $randomKey = array_rand($status);

        $status = $status[$randomKey];

        return [
            'user_id' => rand(16,18),
            'title' => $this->faker->sentence,
            'message' => $this->faker->paragraph,
            'status' => $status,
        ];
    }
}
