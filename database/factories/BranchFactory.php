<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Merge fixup: both FKs are constrained() (NOT NULL), so the old hardcoded
            // id=1 default only ever worked against a seeded database — always broken
            // against a fresh RefreshDatabase test run with nothing at id 1. Callers
            // that need a SPECIFIC user/company still override these explicitly.
            'user_id' => User::factory(),
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'company_id' => Company::factory(),
        ];
    }
}
