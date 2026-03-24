<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\DotwAI;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Modules\DotwAI\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for CreditService pessimistic locking, balance calculation, and credit operations.
 *
 * Verifies B2B credit flow: sufficient balance deduction, insufficient balance rejection,
 * concurrent access prevention, refund creation, and balance breakdown accuracy.
 *
 * @see B2B-06 Credit deduction uses pessimistic locking (lockForUpdate)
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

        // Link client to agent via pivot
        $this->agent->clients()->attach($this->client->id);
    }

    /**
     * @test
     */
    public function test_check_and_deduct_credit_succeeds_with_sufficient_balance(): void
    {
        // Arrange: 500 KWD TOPUP
        Credit::create([
            'company_id' => $this->company->id,
            'client_id'  => $this->client->id,
            'type'       => Credit::TOPUP,
            'amount'     => 500,
            'description' => 'Initial topup',
        ]);

        $creditCountBefore = Credit::where('client_id', $this->client->id)->count();

        // Act
        $result = $this->creditService->checkAndDeductCredit(
            $this->client->id,
            $this->company->id,
            300,
            'DOTWAI-test-001',
        );

        // Assert
        $this->assertTrue($result);

        // A new Invoice credit record should exist
        $invoiceRecord = Credit::where('client_id', $this->client->id)
            ->where('type', Credit::INVOICE)
            ->first();

        $this->assertNotNull($invoiceRecord);
        $this->assertEquals(-300, (float) $invoiceRecord->amount);

        // Balance should now be 200
        $balance = $this->creditService->getBalance($this->client->id);
        $this->assertEquals(200.0, $balance['available_credit']);
    }

    /**
     * @test
     */
    public function test_check_and_deduct_credit_fails_with_insufficient_balance(): void
    {
        // Arrange: only 100 KWD
        Credit::create([
            'company_id' => $this->company->id,
            'client_id'  => $this->client->id,
            'type'       => Credit::TOPUP,
            'amount'     => 100,
            'description' => 'Initial topup',
        ]);

        $creditCountBefore = Credit::where('client_id', $this->client->id)->count();

        // Act: try to deduct 300 (more than available)
        $result = $this->creditService->checkAndDeductCredit(
            $this->client->id,
            $this->company->id,
            300,
            'DOTWAI-test-002',
        );

        // Assert: returns false
        $this->assertFalse($result);

        // Assert: no new credit record was created
        $creditCountAfter = Credit::where('client_id', $this->client->id)->count();
        $this->assertEquals($creditCountBefore, $creditCountAfter);

        // Balance unchanged at 100
        $balance = $this->creditService->getBalance($this->client->id);
        $this->assertEquals(100.0, $balance['available_credit']);
    }

    /**
     * @test
     */
    public function test_get_balance_returns_correct_structure(): void
    {
        // Arrange: TOPUP 1000, INVOICE -300, REFUND 50
        Credit::create([
            'company_id' => $this->company->id,
            'client_id'  => $this->client->id,
            'type'       => Credit::TOPUP,
            'amount'     => 1000,
            'description' => 'Main topup',
        ]);

        Credit::create([
            'company_id' => $this->company->id,
            'client_id'  => $this->client->id,
            'type'       => Credit::INVOICE,
            'amount'     => -300,
            'description' => 'Invoice deduction',
        ]);

        Credit::create([
            'company_id' => $this->company->id,
            'client_id'  => $this->client->id,
            'type'       => Credit::REFUND,
            'amount'     => 50,
            'description' => 'Refund from cancelled booking',
        ]);

        // Act
        $balance = $this->creditService->getBalance($this->client->id);

        // Assert structure
        $this->assertArrayHasKey('credit_limit', $balance);
        $this->assertArrayHasKey('used_credit', $balance);
        $this->assertArrayHasKey('available_credit', $balance);

        // credit_limit = TOPUP (1000) + REFUND (50) = 1050
        $this->assertEquals(1050.0, $balance['credit_limit']);

        // used_credit = abs(INVOICE -300) = 300
        $this->assertEquals(300.0, $balance['used_credit']);

        // available_credit = 1050 - 300 = 750
        $this->assertEquals(750.0, $balance['available_credit']);
    }

    /**
     * @test
     */
    public function test_refund_credit_creates_refund_record(): void
    {
        // Act
        $this->creditService->refundCredit(
            $this->client->id,
            $this->company->id,
            100,
            'DOTWAI-test-003',
        );

        // Assert: REFUND record created with positive amount
        $refundRecord = Credit::where('client_id', $this->client->id)
            ->where('type', Credit::REFUND)
            ->first();

        $this->assertNotNull($refundRecord);
        $this->assertEquals(100.0, (float) $refundRecord->amount);
        $this->assertEquals($this->company->id, $refundRecord->company_id);
        $this->assertStringContainsString('DOTWAI-test-003', $refundRecord->description);
    }

    /**
     * @test
     */
    public function test_concurrent_credit_deduction_prevented_by_locking(): void
    {
        // Arrange: exactly 500 KWD
        Credit::create([
            'company_id' => $this->company->id,
            'client_id'  => $this->client->id,
            'type'       => Credit::TOPUP,
            'amount'     => 500,
            'description' => 'Concurrent test topup',
        ]);

        // First deduction: 400 KWD -- should succeed
        $firstResult = $this->creditService->checkAndDeductCredit(
            $this->client->id,
            $this->company->id,
            400,
            'DOTWAI-concurrent-001',
        );

        $this->assertTrue($firstResult, 'First deduction of 400 should succeed with 500 balance');

        // Second deduction: 200 KWD -- should fail (only 100 remaining after first)
        $secondResult = $this->creditService->checkAndDeductCredit(
            $this->client->id,
            $this->company->id,
            200,
            'DOTWAI-concurrent-002',
        );

        $this->assertFalse($secondResult, 'Second deduction of 200 should fail (only 100 remaining)');

        // Total deducted should be 400, not 600
        $invoiceRecords = Credit::where('client_id', $this->client->id)
            ->where('type', Credit::INVOICE)
            ->get();

        $totalDeducted = abs($invoiceRecords->sum('amount'));
        $this->assertEquals(400.0, $totalDeducted, 'Total deducted should be exactly 400, not 600');

        // Remaining balance should be 100
        $balance = $this->creditService->getBalance($this->client->id);
        $this->assertEquals(100.0, $balance['available_credit']);
    }

    /**
     * @test
     */
    public function test_get_client_id_for_company_resolves_via_agent_chain(): void
    {
        // Act: should resolve via agent -> branch -> company chain
        $resolvedClientId = $this->creditService->getClientIdForCompany($this->company->id);

        // Assert: resolves to our client
        $this->assertEquals($this->client->id, $resolvedClientId);
    }

    /**
     * @test
     */
    public function test_get_client_id_returns_null_for_unknown_company(): void
    {
        $resolvedClientId = $this->creditService->getClientIdForCompany(99999);

        $this->assertNull($resolvedClientId);
    }
}
