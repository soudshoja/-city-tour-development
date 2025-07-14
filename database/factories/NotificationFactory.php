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
            'user_id' => 1, // Default to user ID 1 instead of random
            'title' => $this->faker->sentence,
            'message' => $this->faker->paragraph,
            'status' => $status,
            'close' => 0,
        ];
    }

    /**
     * State for creating read notifications
     */
    public function read()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'read',
            ];
        });
    }

    /**
     * State for creating unread notifications
     */
    public function unread()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'unread',
            ];
        });
    }

    /**
     * State for creating closed notifications
     */
    public function closed()
    {
        return $this->state(function (array $attributes) {
            return [
                'close' => 1,
            ];
        });
    }
}
