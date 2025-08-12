<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Client;
use App\Models\User;
use App\Models\AgentStatus;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\ClientStatus;
use App\Models\Company;
use App\Models\Role;
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
        return [
            'agent_id' => 1, // Will be overridden in tests
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional()->lastName(),
            'last_name' => $this->faker->lastName(),
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
