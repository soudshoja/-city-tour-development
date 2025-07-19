<?php

namespace Database\Factories;

use App\Models\AgentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentTypeFactory extends Factory
{
    protected $model = AgentType::class;

    public function definition()
    {
        return [
            'name' => $this->faker->randomElement(['Salary', 'Commission', 'Both']),
        ];
    }
}
