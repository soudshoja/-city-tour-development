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
            'agent_id' => $agents->random()->id,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            'passport_no' => $this->faker->unique()->numberBetween(1000000, 9999999),
            'civil_no' => $this->faker->unique()->numberBetween(1000000, 9999999),
            'date_of_birth' => $this->faker->date(),
            'phone' => $this->faker->phoneNumber(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
