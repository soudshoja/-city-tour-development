<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Client;
use App\Models\User;
use App\Models\AgentStatus;
use App\Models\ClientStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class ClientFactory extends Factory
{

    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $agents = Agent::all();

        return [
            'user_id' => User::factory(),
            'agent_id' => $agents->random()->id,
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'status_id' => ClientStatus::inRandomOrder()->first()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
