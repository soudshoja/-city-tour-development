<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            // P1 ROUND 3 test-runner fix: was hardcoded to 1, which has a real FK to
            // users(id) (2024_08_22_083438_create_companies_table.php:26) and no
            // guarantee a user with that id exists in a fresh test database — this
            // broke every Accounting test that calls Company::factory()->create()
            // without an explicit user_id override (SQLSTATE 23000 / MySQL 1452),
            // unrelated to the P1 accounting engine itself. A caller that overrides
            // 'user_id' explicitly (several accounting tests already do, to pin a
            // specific owning user) is unaffected — the override replaces this
            // factory relation instead of both running.
            'user_id' => User::factory(),
            'country_id' => 1,
            'gds_office_id' => $this->faker->randomLetter(),
            'status' => 1,
            'code' => $this->faker->unique()->bothify('COMP-###'),
            'email' => $this->faker->companyEmail(),
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
        ];
    }
}
