<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Models\Company;
use App\Models\Credit;
use App\Models\JournalEntry;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\StatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for StatementService statement aggregation.
 *
 * Verifies that bookings, journal entries, and credits for a date range
 * are correctly aggregated, and that records outside the range are excluded.
 *
 * All JournalEntry records are created with explicit company_id since
 * there is no auth context in tests (RefreshDatabase).
 *
 * @covers \App\Modules\DotwAI\Services\StatementService
 * @see ACCT-02 Statement aggregates bookings, cancellations, credits, debits for date range
 * @see ACCT-04 JournalEntry queries bypass global scopes
 */
class StatementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private StatementService $statementService;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statementService = new StatementService();
        $this->company = Company::factory()->create();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function makeBooking(array $overrides = []): DotwAIBooking
    {
        return DotwAIBooking::create(array_merge([
            'prebook_key'        => 'DOTWAI-STMT-' . uniqid(),
            'booking_ref'        => 'DOTW-REF-STMT-' . uniqid(),
            'company_id'         => $this->company->id,
            'agent_phone'        => '96599800027',
            'hotel_name'         => 'Statement Test Hotel',
            'hotel_id'           => 'H001',
            'city_code'          => 'DXB',
            'check_in'           => now()->addDays(30),
            'check_out'          => now()->addDays(35),
            'display_total_fare' => 150.00,
            'display_currency'   => 'KWD',
            'track'              => DotwAIBooking::TRACK_B2B,
            'status'             => DotwAIBooking::STATUS_CONFIRMED,
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Statement must include bookings, journal entries, credits, and correct totals.
     *
     * @see ACCT-02
     */
    public function test_statement_returns_bookings_and_totals(): void
    {
        $dateFrom = '2026-01-01';
        $dateTo   = '2026-01-31';

        // Create 1 confirmed + 1 cancelled booking within the range
        DotwAIBooking::create([
            'prebook_key'        => 'DOTWAI-STMT-C1',
            'booking_ref'        => 'DOTW-REF-C1',
            'company_id'         => $this->company->id,
            'agent_phone'        => '96599800027',
            'hotel_name'         => 'Hotel A',
            'hotel_id'           => 'H001',
            'city_code'          => 'DXB',
            'check_in'           => '2026-02-01',
            'check_out'          => '2026-02-05',
            'display_total_fare' => 100.00,
            'display_currency'   => 'KWD',
            'track'              => DotwAIBooking::TRACK_B2B,
            'status'             => DotwAIBooking::STATUS_CONFIRMED,
            'created_at'         => '2026-01-10 10:00:00',
        ]);

        DotwAIBooking::create([
            'prebook_key'        => 'DOTWAI-STMT-C2',
            'booking_ref'        => 'DOTW-REF-C2',
            'company_id'         => $this->company->id,
            'agent_phone'        => '96599800027',
            'hotel_name'         => 'Hotel B',
            'hotel_id'           => 'H002',
            'city_code'          => 'DXB',
            'check_in'           => '2026-02-10',
            'check_out'          => '2026-02-15',
            'display_total_fare' => 50.00,
            'display_currency'   => 'KWD',
            'track'              => DotwAIBooking::TRACK_B2B,
            'status'             => DotwAIBooking::STATUS_CANCELLED,
            'created_at'         => '2026-01-15 10:00:00',
        ]);

        // Create 1 JournalEntry (cancellation penalty debit)
        JournalEntry::create([
            'company_id'       => $this->company->id,
            'account_id'       => 1,
            'transaction_date' => '2026-01-15',
            'description'      => 'Cancellation penalty',
            'debit'            => 20.00,
            'credit'           => 0,
            'currency'         => 'KWD',
            'type'             => 'cancellation',
        ]);

        // Create 1 Credit (TOPUP)
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => 1,
            'type'        => Credit::TOPUP,
            'amount'      => 300.00,
            'description' => 'Monthly topup',
            'created_at'  => '2026-01-05 09:00:00',
        ]);

        $statement = $this->statementService->getStatement(
            $this->company->id,
            $dateFrom,
            $dateTo,
        );

        // Bookings
        $this->assertCount(2, $statement['bookings']);
        $this->assertEquals(2, $statement['totals']['total_bookings']);
        $this->assertEquals(1, $statement['totals']['total_cancellations']);

        // JournalEntries
        $this->assertCount(1, $statement['journal_entries']);
        $this->assertEquals(20.00, $statement['totals']['total_penalties']);

        // Credits
        $this->assertCount(1, $statement['credits']);
        $this->assertEquals(300.00, $statement['totals']['total_credits_topup']);
    }

    /**
     * Records outside the requested date range must be excluded from the statement.
     */
    public function test_statement_filters_by_date_range(): void
    {
        $dateFrom = '2026-03-01';
        $dateTo   = '2026-03-31';

        // Booking created BEFORE the range
        DotwAIBooking::create([
            'prebook_key'        => 'DOTWAI-STMT-BEFORE',
            'booking_ref'        => 'DOTW-REF-BEFORE',
            'company_id'         => $this->company->id,
            'agent_phone'        => '96599800027',
            'hotel_name'         => 'Old Hotel',
            'hotel_id'           => 'H001',
            'city_code'          => 'DXB',
            'check_in'           => '2026-04-01',
            'check_out'          => '2026-04-05',
            'display_total_fare' => 100.00,
            'display_currency'   => 'KWD',
            'track'              => DotwAIBooking::TRACK_B2B,
            'status'             => DotwAIBooking::STATUS_CONFIRMED,
            'created_at'         => '2026-02-15 10:00:00', // Before range
        ]);

        // Booking created AFTER the range
        DotwAIBooking::create([
            'prebook_key'        => 'DOTWAI-STMT-AFTER',
            'booking_ref'        => 'DOTW-REF-AFTER',
            'company_id'         => $this->company->id,
            'agent_phone'        => '96599800027',
            'hotel_name'         => 'Future Hotel',
            'hotel_id'           => 'H002',
            'city_code'          => 'DXB',
            'check_in'           => '2026-05-01',
            'check_out'          => '2026-05-05',
            'display_total_fare' => 200.00,
            'display_currency'   => 'KWD',
            'track'              => DotwAIBooking::TRACK_B2B,
            'status'             => DotwAIBooking::STATUS_CONFIRMED,
            'created_at'         => '2026-04-10 10:00:00', // After range
        ]);

        $statement = $this->statementService->getStatement(
            $this->company->id,
            $dateFrom,
            $dateTo,
        );

        // Both bookings are outside the range; statement should be empty
        $this->assertCount(0, $statement['bookings']);
        $this->assertEquals(0, $statement['totals']['total_bookings']);
    }
}
