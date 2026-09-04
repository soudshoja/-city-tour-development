<?php

// database/factories/AgentFactory.php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            // Merge fixup (same class of bug as CompanyFactory's "P1 ROUND 3" fix and
            // BranchFactory above): agents.user_id/branch_id are both constrained()
            // FKs; a hardcoded id=1 only ever worked against a seeded database, not a
            // fresh RefreshDatabase test run. A caller that overrides either
            // explicitly is unaffected.
            'user_id' => \App\Models\User::factory(),
            'email' => $this->faker->unique()->safeEmail,
            'phone_number' => $this->faker->phoneNumber,
            'branch_id' => Branch::factory(),
            // A later migration (2025_03_17_155534_update_foreign_in_agents_table.php)
            // added agents_type_id_foreign -> agent_type(id); same fresh-DB gap as
            // user_id/branch_id above.
            'type_id' => AgentType::factory(),
            'target' => 0,
            'salary' => 0,
        ];
    }
}

