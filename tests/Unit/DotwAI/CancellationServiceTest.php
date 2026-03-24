<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\AccountingService;
use App\Modules\DotwAI\Services\CancellationService;
use App\Modules\DotwAI\Services\CreditService;
use App\Models\Credit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for CancellationService two-step cancellation flow.
 *
 * Covers: preview mode, confirm with penalty, free cancellation,
 * status validation, B2B credit refund, and no journal on preview.
 *
 * Uses Mockery overload to intercept `new DotwService($companyId)` calls.
 *
 * @covers \App\Modules\DotwAI\Services\CancellationService
 * @see CANC-01 2-step cancel with confirm=no preview
 * @see CANC-03 Penalty > 0 triggers Invoice + JournalEntry creation
 * @see CANC-04 Free cancellation updates status only
 */
class CancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private CancellationService $cancellationService;
    private Company $company;
    private Agent $agent;
    private Client $client;
    private CompanyDotwCredential $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->agent = Agent::factory()->create(['branch_id' => $branch->id]);
        $this->client = Client::factory()->create(['agent_id' => $this->agent->id]);
        $this->agent->clients()->attach($this->client->id);

        $this->credentials = CompanyDotwCredential::create([
            'company_id'        => $this->company->id,
            'dotw_username'     => Crypt::encrypt('testuser'),
            'dotw_password'     => Crypt::encrypt('testpass'),
            'dotw_company_code' => 'TEST',
            'markup_percent'    => 0,
            'is_active'         => true,
            'b2b_enabled'       => true,
            'b2c_enabled'       => false,
        ]);

        $this->cancellationService = new CancellationService(
            new AccountingService(),
            new CreditService(),
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Create a confirmed DotwAIBooking for the test company.
     */
    private function makeConfirmedBooking(array $overrides = []): DotwAIBooking
    {
        return DotwAIBooking::create(array_merge([
            'prebook_key'        => 'DOTWAI-TEST-' . uniqid(),
            'booking_ref'        => 'DOTW-REF-' . uniqid(),
            'company_id'         => $this->company->id,
            'agent_phone'        => '96599800027',
            'hotel_name'         => 'Test Hotel',
            'hotel_id'           => 'H001',
            'city_code'          => 'DXB',
            'check_in'           => now()->addDays(30),
            'check_out'          => now()->addDays(35),
            'display_total_fare' => 200.00,
            'display_currency'   => 'KWD',
            'track'              => DotwAIBooking::TRACK_B2B,
            'status'             => DotwAIBooking::STATUS_CONFIRMED,
        ], $overrides));
    }

    /**
     * Build a B2B DotwAIContext for the test company.
     */
    private function makeContext(): DotwAIContext
    {
        return new DotwAIContext(
            agent:         $this->agent,
            companyId:     $this->company->id,
            credentials:   $this->credentials,
            track:         'b2b',
            markupPercent: 0,
            b2bEnabled:    true,
            b2cEnabled:    false,
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Preview mode: DOTW returns charge=50. Assert step=preview and booking
     * transitions to cancellation_pending.
     *
     * @see CANC-01
     */
    public function test_preview_returns_penalty_amount(): void
    {
        $booking = $this->makeConfirmedBooking();
        $context = $this->makeContext();

        $dotwMock = Mockery::mock('overload:App\Services\DotwService');
        $dotwMock->shouldReceive('cancelBooking')
            ->once()
            ->andReturn(['charge' => 50.00, 'refund' => 100.00]);

        $result = $this->cancellationService->cancel($context, [
            'prebook_key' => $booking->prebook_key,
            'confirm'     => 'no',
        ]);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertEquals('preview', $result['step']);
        $this->assertEquals(50.00, $result['penalty_amount']);

        $booking->refresh();
        $this->assertEquals(DotwAIBooking::STATUS_CANCELLATION_PENDING, $booking->status);
    }

    /**
     * Confirm mode: DOTW returns charge=50. Assert booking is cancelled,
     * an Invoice is created, and 2 JournalEntry records are created.
     *
     * @see CANC-03
     * @see ACCT-03
     */
    public function test_confirm_cancels_booking_with_penalty(): void
    {
        $booking = $this->makeConfirmedBooking();
        $context = $this->makeContext();

        // Seed chart of accounts for JournalEntry creation
        \App\Models\Account::create([
            'company_id' => $this->company->id,
            'name'       => 'Client Receivable',
            'code'       => 'RECV-001',
        ]);
        \App\Models\Account::create([
            'company_id' => $this->company->id,
            'name'       => 'Revenue Account',
            'code'       => 'REV-001',
        ]);

        // Seed credit topup so client has a usable balance
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 500,
            'description' => 'Initial topup',
        ]);

        $dotwMock = Mockery::mock('overload:App\Services\DotwService');
        $dotwMock->shouldReceive('cancelBooking')
            ->once()
            ->andReturn(['charge' => 50.00, 'refund' => 150.00]);

        $result = $this->cancellationService->cancel($context, [
            'prebook_key'    => $booking->prebook_key,
            'confirm'        => 'yes',
            'penalty_amount' => 50.00,
        ]);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertEquals('confirmed', $result['step']);

        $booking->refresh();
        $this->assertEquals(DotwAIBooking::STATUS_CANCELLED, $booking->status);

        // Invoice should exist
        $invoice = Invoice::where('label', 'LIKE', '%' . $booking->prebook_key . '%')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(50.00, (float) $invoice->amount);

        // Two JournalEntry records should exist (debit + credit)
        $entries = JournalEntry::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('type', 'cancellation')
            ->get();
        $this->assertCount(2, $entries);
    }

    /**
     * Free cancellation: DOTW returns charge=0. No Invoice, no JournalEntry.
     *
     * @see CANC-04
     * @see ACCT-01
     */
    public function test_free_cancellation_no_accounting(): void
    {
        $booking = $this->makeConfirmedBooking();
        $context = $this->makeContext();

        $dotwMock = Mockery::mock('overload:App\Services\DotwService');
        $dotwMock->shouldReceive('cancelBooking')
            ->once()
            ->andReturn(['charge' => 0.00, 'refund' => 200.00]);

        $result = $this->cancellationService->cancel($context, [
            'prebook_key'    => $booking->prebook_key,
            'confirm'        => 'yes',
            'penalty_amount' => 0.00,
        ]);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertTrue($result['is_free_cancellation']);

        // No Invoice should have been created
        $invoiceCount = Invoice::count();
        $this->assertEquals(0, $invoiceCount);

        // No JournalEntry should have been created
        $journalCount = JournalEntry::withoutGlobalScopes()->count();
        $this->assertEquals(0, $journalCount);
    }

    /**
     * Non-confirmed bookings cannot be cancelled.
     *
     * @see Pitfall 6 in phase context
     */
    public function test_cancellation_not_allowed_for_non_confirmed(): void
    {
        $booking = $this->makeConfirmedBooking(['status' => DotwAIBooking::STATUS_PREBOOKED]);
        $context = $this->makeContext();

        $result = $this->cancellationService->cancel($context, [
            'prebook_key' => $booking->prebook_key,
            'confirm'     => 'no',
        ]);

        $this->assertTrue($result['error']);
        $this->assertEquals('CANCELLATION_NOT_ALLOWED', $result['code']);
    }

    /**
     * B2B booking: credit refund is applied for (original - penalty).
     *
     * @see B2B credit refund on cancellation
     */
    public function test_b2b_credit_refund_on_cancellation(): void
    {
        $booking = $this->makeConfirmedBooking([
            'display_total_fare' => 200.00,
            'track'              => DotwAIBooking::TRACK_B2B,
        ]);
        $context = $this->makeContext();

        // Seed a topup so the client account exists
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 500,
            'description' => 'Initial topup',
        ]);

        $dotwMock = Mockery::mock('overload:App\Services\DotwService');
        $dotwMock->shouldReceive('cancelBooking')
            ->once()
            ->andReturn(['charge' => 50.00, 'refund' => 150.00]);

        $creditCountBefore = Credit::where('company_id', $this->company->id)->count();

        $this->cancellationService->cancel($context, [
            'prebook_key'    => $booking->prebook_key,
            'confirm'        => 'yes',
            'penalty_amount' => 50.00,
        ]);

        // A refund credit record should have been created for amount=150
        $refundCredit = Credit::where('company_id', $this->company->id)
            ->where('type', Credit::REFUND)
            ->first();

        $this->assertNotNull($refundCredit);
        $this->assertEquals(150.00, (float) $refundCredit->amount);
    }

    /**
     * Preview step (confirm=no) must NOT create any JournalEntry records.
     *
     * @see ACCT-03
     */
    public function test_no_journal_entry_on_preview_step(): void
    {
        $booking = $this->makeConfirmedBooking();
        $context = $this->makeContext();

        $dotwMock = Mockery::mock('overload:App\Services\DotwService');
        $dotwMock->shouldReceive('cancelBooking')
            ->once()
            ->andReturn(['charge' => 50.00, 'refund' => 150.00]);

        $this->cancellationService->cancel($context, [
            'prebook_key' => $booking->prebook_key,
            'confirm'     => 'no',
        ]);

        $journalCount = JournalEntry::withoutGlobalScopes()->count();
        $this->assertEquals(0, $journalCount);
    }
}
