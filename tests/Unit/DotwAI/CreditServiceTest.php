<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Modules\DotwAI\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for CreditService::getCreditHistory.
 *
 * Verifies that credit history is returned in reverse-chronological order,
 * filtered by company, and returns an empty array for companies with no records.
 *
 * @covers \App\Modules\DotwAI\Services\CreditService::getCreditHistory
 * @see ACCT-05 Credit history endpoint for agents
 */
class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private CreditService $creditService;
    private Company $company;
    private Agent $agent;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creditService = new CreditService();

        $this->company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->agent = Agent::factory()->create(['branch_id' => $branch->id]);
        $this->client = Client::factory()->create(['agent_id' => $this->agent->id]);
        $this->agent->clients()->attach($this->client->id);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────────────

    /**
     * getCreditHistory returns all credit records for a company/client,
     * each with keys: date, type, amount, description.
     * Records are ordered by created_at desc (most recent first).
     *
     * @see ACCT-05
     */
    public function test_credit_history_returns_transactions(): void
    {
        // Create 3 Credits at different times (oldest first)
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 500.00,
            'description' => 'Initial TOPUP',
            'created_at'  => now()->subDays(5),
        ]);

        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::INVOICE,
            'amount'      => -100.00,
            'description' => 'Hotel Booking invoice deduction',
            'created_at'  => now()->subDays(3),
        ]);

        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::REFUND,
            'amount'      => 50.00,
            'description' => 'Cancellation refund',
            'created_at'  => now()->subDays(1),
        ]);

        $history = $this->creditService->getCreditHistory($this->client->id, $this->company->id);

        // 3 records returned
        $this->assertCount(3, $history);

        // Each record has the required keys
        foreach ($history as $record) {
            $this->assertArrayHasKey('date', $record);
            $this->assertArrayHasKey('type', $record);
            $this->assertArrayHasKey('amount', $record);
            $this->assertArrayHasKey('description', $record);
        }

        // Ordered by created_at desc: most recent first
        $this->assertEquals(Credit::REFUND, $history[0]['type']);
        $this->assertEquals(Credit::INVOICE, $history[1]['type']);
        $this->assertEquals(Credit::TOPUP, $history[2]['type']);
    }

    /**
     * getCreditHistory must only return records for the requested company.
     * Records for another company must be excluded.
     */
    public function test_credit_history_filters_by_company(): void
    {
        // Company A records
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 300.00,
            'description' => 'Company A topup',
        ]);

        // Company B (different company, different client)
        $companyB = Company::factory()->create();
        $branchB  = Branch::factory()->create(['company_id' => $companyB->id]);
        $agentB   = Agent::factory()->create(['branch_id' => $branchB->id]);
        $clientB  = Client::factory()->create(['agent_id' => $agentB->id]);
        $agentB->clients()->attach($clientB->id);

        Credit::create([
            'company_id'  => $companyB->id,
            'client_id'   => $clientB->id,
            'type'        => Credit::TOPUP,
            'amount'      => 999.00,
            'description' => 'Company B topup',
        ]);

        // Query for Company A only
        $history = $this->creditService->getCreditHistory($this->client->id, $this->company->id);

        $this->assertCount(1, $history);
        $this->assertEquals(300.00, $history[0]['amount']);
    }

    /**
     * getCreditHistory returns an empty array (not null, not error)
     * when no credits exist for the requested company.
     */
    public function test_credit_history_empty_for_no_transactions(): void
    {
        $history = $this->creditService->getCreditHistory($this->client->id, $this->company->id);

        $this->assertIsArray($history);
        $this->assertCount(0, $history);
    }
}
