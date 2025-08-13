<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CoaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numberBetween(1000, 5999),
            'name' => $this->faker->unique()->word(),
            'level' => $this->faker->numberBetween(1, 4),
            'parent_id' => null, // For simplicity, set to null. Can be set in tests if needed.
            'account_type' => $this->faker->randomElement(['liability', 'asset', 'equity', 'income', 'expense', null]),
            'report_type' => $this->faker->randomElement(['balance sheet', 'profit loss']),
            'company_id' => 1,
            'serial_number' => null,
            'actual_balance' => $this->faker->randomFloat(2, 0, 10000),
            'budget_balance' => $this->faker->randomFloat(2, 0, 10000),
            'variance' => $this->faker->randomFloat(2, -500, 500),
            'branch_id' => null,
            'agent_id' => null,
            'client_id' => null,
            'supplier_id' => null,
            'reference_id' => null,
            'root_id' => null,
        ];
    }
}
