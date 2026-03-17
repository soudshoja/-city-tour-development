<?php

namespace Tests\Unit;

use App\Models\Agent;
use App\Models\AgentCharge;
use App\Models\AgentLoss;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentChargeDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $companyUser = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $companyUser->id]);

        $branch = Branch::factory()->create([
            'user_id' => $companyUser->id,
            'company_id' => $this->company->id,
        ]);

        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $agentType = AgentType::create(['name' => 'Commission']);

        $this->agent = Agent::factory()->create([
            'user_id' => $agentUser->id,
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
        ]);
    }

    // ─── AGENT CHARGE DEDUCTION ───────────────────────────────────────

    public function test_company_bears_all_charges(): void
    {
        $agentCharge = AgentCharge::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'charge_bearer' => AgentCharge::BEARER_COMPANY,
            'agent_percentage' => 0,
            'company_percentage' => 100,
        ]);

        $deduction = $agentCharge->calculateAgentChargeDeduction(50.0);

        $this->assertEquals(0, $deduction);
    }

    public function test_agent_bears_all_charges(): void
    {
        $agentCharge = AgentCharge::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'charge_bearer' => AgentCharge::BEARER_AGENT,
            'agent_percentage' => 100,
            'company_percentage' => 0,
        ]);

        $deduction = $agentCharge->calculateAgentChargeDeduction(50.0);

        $this->assertEquals(50.0, $deduction);
    }

    public function test_split_charges_between_agent_and_company(): void
    {
        $agentCharge = AgentCharge::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'charge_bearer' => AgentCharge::BEARER_SPLIT,
            'agent_percentage' => 60,
            'company_percentage' => 40,
        ]);

        // 60% of 50 = 30
        $deduction = $agentCharge->calculateAgentChargeDeduction(50.0);

        $this->assertEquals(30.0, $deduction);
    }

    public function test_split_charges_with_decimal_precision(): void
    {
        $agentCharge = AgentCharge::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'charge_bearer' => AgentCharge::BEARER_SPLIT,
            'agent_percentage' => 33.33,
            'company_percentage' => 66.67,
        ]);

        // 33.33% of 100 = 33.33 → rounded to 3 decimals = 33.33
        $deduction = $agentCharge->calculateAgentChargeDeduction(100.0);

        $this->assertEquals(round(100 * (33.33 / 100), 3), $deduction);
    }

    public function test_zero_charge_returns_zero_deduction(): void
    {
        $agentCharge = AgentCharge::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'charge_bearer' => AgentCharge::BEARER_AGENT,
            'agent_percentage' => 100,
            'company_percentage' => 0,
        ]);

        $deduction = $agentCharge->calculateAgentChargeDeduction(0);

        $this->assertEquals(0, $deduction);
    }

    // ─── AGENT LOSS DISTRIBUTION ──────────────────────────────────────

    public function test_company_bears_all_loss(): void
    {
        $agentLoss = AgentLoss::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'loss_bearer' => AgentLoss::BEARER_COMPANY,
            'agent_percentage' => 0,
            'company_percentage' => 100,
        ]);

        $distribution = $agentLoss->calculateLossDistribution(200.0);

        $this->assertEquals(0, $distribution['agent_loss']);
        $this->assertEquals(200.0, $distribution['company_loss']);
        $this->assertEquals('company', $distribution['loss_bearer']);
    }

    public function test_agent_bears_all_loss(): void
    {
        $agentLoss = AgentLoss::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'loss_bearer' => AgentLoss::BEARER_AGENT,
            'agent_percentage' => 100,
            'company_percentage' => 0,
        ]);

        $distribution = $agentLoss->calculateLossDistribution(200.0);

        $this->assertEquals(200.0, $distribution['agent_loss']);
        $this->assertEquals(0, $distribution['company_loss']);
        $this->assertEquals('agent', $distribution['loss_bearer']);
    }

    public function test_split_loss_between_agent_and_company(): void
    {
        $agentLoss = AgentLoss::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'loss_bearer' => AgentLoss::BEARER_SPLIT,
            'agent_percentage' => 40,
            'company_percentage' => 60,
        ]);

        // agent: 40% of 200 = 80, company: 200 - 80 = 120
        $distribution = $agentLoss->calculateLossDistribution(200.0);

        $this->assertEquals(80.0, $distribution['agent_loss']);
        $this->assertEquals(120.0, $distribution['company_loss']);
        $this->assertEquals('split', $distribution['loss_bearer']);
    }

    public function test_loss_distribution_handles_negative_input(): void
    {
        $agentLoss = AgentLoss::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'loss_bearer' => AgentLoss::BEARER_AGENT,
            'agent_percentage' => 100,
            'company_percentage' => 0,
        ]);

        // Should use abs() so -50 becomes 50
        $distribution = $agentLoss->calculateLossDistribution(-50.0);

        $this->assertEquals(50.0, $distribution['agent_loss']);
        $this->assertEquals(0, $distribution['company_loss']);
    }

    public function test_loss_total_equals_original_amount(): void
    {
        $agentLoss = AgentLoss::create([
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'loss_bearer' => AgentLoss::BEARER_SPLIT,
            'agent_percentage' => 70,
            'company_percentage' => 30,
        ]);

        $lossAmount = 157.50;
        $distribution = $agentLoss->calculateLossDistribution($lossAmount);

        // Agent + Company should equal total loss
        $total = $distribution['agent_loss'] + $distribution['company_loss'];
        $this->assertEquals(round($lossAmount, 3), round($total, 3));
    }
}
