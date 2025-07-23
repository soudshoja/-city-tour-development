<?php

// database/factories/AgentFactory.php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'user_id' => 1, // Will be overridden in tests
            'email' => $this->faker->unique()->safeEmail,
            'phone_number' => $this->faker->phoneNumber,
            'branch_id' => 1, // Will be overridden in tests
            'type_id' => 1, // Default type ID
        ];
    }
}

