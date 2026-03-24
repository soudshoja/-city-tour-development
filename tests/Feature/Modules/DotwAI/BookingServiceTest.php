<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\DotwAI;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Models\Credit;
use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\BookingService;
use App\Modules\DotwAI\Services\CreditService;
use App\Modules\DotwAI\Services\HotelSearchService;
use App\Services\DotwService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

/**
 * Integration tests for BookingService covering:
 * - Prebook from cached search results
 * - Blocking failure handling
 * - B2B credit confirm flow
 * - DOTW failure with credit refund
 * - Idempotency on already-confirmed bookings
 * - MSP enforcement for B2C track
 * - APR rate routing (saveBooking + bookItinerary)
 *
 * @see B2B-03 Rate locking via blocking=true
 * @see B2B-06 Pessimistic credit locking
 * @see B2C-05 MSP enforcement
 */
class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private BookingService $bookingService;
    private Company $company;
    private Agent $agent;
    private Client $client;
    private CompanyDotwCredential $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->agent = Agent::factory()->create([
            'branch_id'    => $branch->id,
            'phone_number' => '99800027',
        ]);
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

        $this->bookingService = new BookingService(
            new CreditService(),
            app(HotelSearchService::class),
        );
    }

    /**
     * Build a B2B DotwAIContext for the test company.
     */
    private function makeB2BContext(float $markupPercent = 0): DotwAIContext
    {
        return new DotwAIContext(
            agent:        $this->agent,
            companyId:    $this->company->id,
            credentials:  $this->credentials,
            track:        'b2b',
            markupPercent: $markupPercent,
            b2bEnabled:   true,
            b2cEnabled:   false,
        );
    }

    /**
     * Build a B2C DotwAIContext for the test company.
     */
    private function makeB2CContext(float $markupPercent = 20): DotwAIContext
    {
        return new DotwAIContext(
            agent:        $this->agent,
            companyId:    $this->company->id,
            credentials:  $this->credentials,
            track:        'b2c',
            markupPercent: $markupPercent,
            b2bEnabled:   false,
            b2cEnabled:   true,
        );
    }

    /**
     * Seed cache with a minimal search result for phone 99800027.
     */
    private function seedSearchCache(array $overrides = []): void
    {
        $hotel = array_merge([
            'option_number'        => 1,
            'hotel_id'             => 'HOTEL-123',
            'name'                 => 'Test Hotel Dubai',
            'city'                 => 'Dubai',
            'star_rating'          => 4,
            'cheapest_price'       => 40.0,
            'meal_type'            => 'Breakfast',
            'is_refundable'        => true,
            'currency'             => 'KWD',
            'minimum_selling_price' => 0,
        ], $overrides);

        Cache::put('dotwai:search:99800027', [$hotel], now()->addMinutes(30));
    }

    /**
     * Standard blocking result returned by mocked DotwService::getRooms.
     */
    private function mockBlockingResult(array $overrides = []): array
    {
        return [
            array_merge([
                'roomTypeCode' => 'DBL',
                'roomName'     => 'Deluxe Room',
                'details'      => [
                    [
                        'id'                => 'RATE-001',
                        'price'             => 40.0,
                        'allocationDetails' => 'ALLOC-TOKEN-XYZ',
                        'cancellationRules' => [],
                    ],
                ],
            ], $overrides),
        ];
    }

    // -------------------------------------------------------------------------
    // Prebook tests
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function test_prebook_creates_booking_from_cached_search_results(): void
    {
        $this->seedSearchCache();

        $mock = Mockery::mock('overload:' . DotwService::class);
        $mock->shouldReceive('getRooms')->andReturn($this->mockBlockingResult());
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getCityList')->andReturn([]);

        $context = $this->makeB2BContext();
        $input = [
            'telephone'     => '99800027',
            'option_number' => 1,
            'check_in'      => '2026-05-01',
            'check_out'     => '2026-05-03',
            'occupancy'     => [['adults' => 2]],
        ];

        $result = $this->bookingService->prebook($context, $input);

        // Should have prebook data
        $this->assertArrayHasKey('prebook_key', $result);
        $this->assertArrayHasKey('hotel_name', $result);
        $this->assertArrayHasKey('display_total_fare', $result);
        $this->assertArrayHasKey('track', $result);
        $this->assertArrayHasKey('needs_payment', $result);
        $this->assertFalse($result['needs_payment']); // B2B credit does not need payment

        // DotwAIBooking should be persisted
        $booking = DotwAIBooking::where('prebook_key', $result['prebook_key'])->first();
        $this->assertNotNull($booking);
        $this->assertEquals(DotwAIBooking::STATUS_PREBOOKED, $booking->status);
    }

    /**
     * @test
     */
    public function test_prebook_returns_error_when_blocking_fails(): void
    {
        $this->seedSearchCache();

        $mock = Mockery::mock('overload:' . DotwService::class);
        $mock->shouldReceive('getRooms')->andThrow(new \RuntimeException('DOTW blocking timeout'));
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getCityList')->andReturn([]);

        $context = $this->makeB2BContext();
        $input = [
            'telephone'     => '99800027',
            'option_number' => 1,
            'check_in'      => '2026-05-01',
            'check_out'     => '2026-05-03',
            'occupancy'     => [['adults' => 2]],
        ];

        $result = $this->bookingService->prebook($context, $input);

        $this->assertTrue($result['error']);
        $this->assertEquals('RATE_UNAVAILABLE', $result['code']);

        // No booking should have been persisted
        $this->assertEquals(0, DotwAIBooking::count());
    }

    // -------------------------------------------------------------------------
    // Confirm with credit tests
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function test_confirm_with_credit_deducts_and_confirms(): void
    {
        // Arrange: sufficient credit
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 500,
            'description' => 'Test topup',
        ]);

        $booking = DotwAIBooking::create([
            'prebook_key'         => 'DOTWAI-TEST-001',
            'track'               => DotwAIBooking::TRACK_B2B,
            'status'              => DotwAIBooking::STATUS_PREBOOKED,
            'company_id'          => $this->company->id,
            'agent_phone'         => '99800027',
            'hotel_id'            => 'HOTEL-123',
            'hotel_name'          => 'Test Hotel Dubai',
            'check_in'            => '2026-05-01',
            'check_out'           => '2026-05-03',
            'original_total_fare' => 200.0,
            'display_total_fare'  => 200.0,
            'display_currency'    => 'KWD',
            'original_currency'   => 'KWD',
            'is_refundable'       => true,
            'is_apr'              => false,
            'allocation_details'  => 'ALLOC-001',
            'nationality_code'    => '66',
            'residence_code'      => '66',
            'rooms_data'          => [['adults' => 2]],
        ]);

        $mock = Mockery::mock('overload:' . DotwService::class);
        $mock->shouldReceive('confirmBooking')->andReturn([
            'confirmationNumber'  => 'CONF-12345',
            'bookingCode'         => 'DOTW-REF-001',
            'paymentGuaranteedBy' => 'City Travelers Agency',
        ]);
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getRooms')->andReturn([]);

        $context = $this->makeB2BContext();
        $passengers = [
            ['first_name' => 'John', 'last_name' => 'Smith', 'salutation' => 'Mr'],
        ];

        $result = $this->bookingService->confirmWithCredit($booking, $context, $passengers, 'john@example.com');

        // Assert confirmation data returned
        $this->assertArrayHasKey('confirmation_no', $result);
        $this->assertEquals('CONF-12345', $result['confirmation_no']);
        $this->assertEquals('City Travelers Agency', $result['payment_guaranteed_by']);

        // Booking should be updated to confirmed
        $booking->refresh();
        $this->assertEquals(DotwAIBooking::STATUS_CONFIRMED, $booking->status);
        $this->assertEquals('CONF-12345', $booking->confirmation_no);

        // Credit INVOICE record should have been created
        $invoiceRecord = Credit::where('client_id', $this->client->id)
            ->where('type', Credit::INVOICE)
            ->first();
        $this->assertNotNull($invoiceRecord);
        $this->assertEquals(-200.0, (float) $invoiceRecord->amount);
    }

    /**
     * @test
     */
    public function test_confirm_with_credit_refunds_on_dotw_error(): void
    {
        // Arrange: sufficient credit
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 500,
            'description' => 'Test topup',
        ]);

        $booking = DotwAIBooking::create([
            'prebook_key'         => 'DOTWAI-TEST-002',
            'track'               => DotwAIBooking::TRACK_B2B,
            'status'              => DotwAIBooking::STATUS_PREBOOKED,
            'company_id'          => $this->company->id,
            'agent_phone'         => '99800027',
            'hotel_id'            => 'HOTEL-123',
            'hotel_name'          => 'Test Hotel Dubai',
            'check_in'            => '2026-05-01',
            'check_out'           => '2026-05-03',
            'original_total_fare' => 200.0,
            'display_total_fare'  => 200.0,
            'display_currency'    => 'KWD',
            'original_currency'   => 'KWD',
            'is_refundable'       => true,
            'is_apr'              => false,
            'allocation_details'  => 'ALLOC-002',
            'nationality_code'    => '66',
            'residence_code'      => '66',
            'rooms_data'          => [['adults' => 2]],
        ]);

        $mock = Mockery::mock('overload:' . DotwService::class);
        $mock->shouldReceive('confirmBooking')->andThrow(new \RuntimeException('DOTW API down'));
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getRooms')->andReturn([]);

        $context = $this->makeB2BContext();
        $result = $this->bookingService->confirmWithCredit(
            $booking,
            $context,
            [['first_name' => 'Jane', 'last_name' => 'Doe', 'salutation' => 'Ms']],
            null,
        );

        // Should return an error
        $this->assertTrue($result['error']);
        $this->assertEquals('BOOKING_FAILED', $result['code']);

        // Booking should be marked failed
        $booking->refresh();
        $this->assertEquals(DotwAIBooking::STATUS_FAILED, $booking->status);

        // Credit should be refunded (net balance restored)
        $balance = (new CreditService())->getBalance($this->client->id);
        $this->assertEquals(500.0, $balance['available_credit']);
    }

    /**
     * @test
     */
    public function test_confirm_with_credit_is_idempotent(): void
    {
        // Arrange: booking already confirmed
        $booking = DotwAIBooking::create([
            'prebook_key'         => 'DOTWAI-TEST-003',
            'track'               => DotwAIBooking::TRACK_B2B,
            'status'              => DotwAIBooking::STATUS_CONFIRMED,
            'company_id'          => $this->company->id,
            'agent_phone'         => '99800027',
            'hotel_id'            => 'HOTEL-123',
            'hotel_name'          => 'Test Hotel Dubai',
            'check_in'            => '2026-05-01',
            'check_out'           => '2026-05-03',
            'confirmation_no'     => 'EXISTING-CONF-999',
            'booking_ref'         => 'DOTW-EXISTING',
            'original_total_fare' => 200.0,
            'display_total_fare'  => 200.0,
            'display_currency'    => 'KWD',
            'original_currency'   => 'KWD',
            'is_refundable'       => true,
            'is_apr'              => false,
            'nationality_code'    => '66',
            'residence_code'      => '66',
        ]);

        // No DotwService mock needed -- should short-circuit on idempotency check
        $context = $this->makeB2BContext();
        $result = $this->bookingService->confirmWithCredit(
            $booking,
            $context,
            [['first_name' => 'John', 'last_name' => 'Smith', 'salutation' => 'Mr']],
            null,
        );

        // Should return existing confirmation
        $this->assertEquals('EXISTING-CONF-999', $result['confirmation_no']);

        // No credit deducted (no INVOICE records)
        $invoiceCount = Credit::where('client_id', $this->client->id)
            ->where('type', Credit::INVOICE)
            ->count();
        $this->assertEquals(0, $invoiceCount);
    }

    /**
     * @test
     */
    public function test_prebook_enforces_msp_for_b2c(): void
    {
        // Seed cache with a hotel where markup price (40 * 1.2 = 48) is below MSP (50)
        $hotel = [
            'option_number'         => 1,
            'hotel_id'              => 'HOTEL-MSP',
            'name'                  => 'MSP Test Hotel',
            'city'                  => 'Dubai',
            'star_rating'           => 4,
            'cheapest_price'        => 40.0,
            'meal_type'             => 'Room Only',
            'is_refundable'         => true,
            'currency'              => 'KWD',
            'minimum_selling_price' => 50.0,  // MSP higher than markup price
        ];
        Cache::put('dotwai:search:99800027', [$hotel], now()->addMinutes(30));

        // Mock DotwService to return a room at 40 KWD
        $blockingResult = [
            [
                'roomTypeCode' => 'STD',
                'roomName'     => 'Standard Room',
                'details'      => [
                    [
                        'id'                => 'RATE-MSP',
                        'price'             => 40.0,
                        'allocationDetails' => 'ALLOC-MSP',
                        'cancellationRules' => [],
                    ],
                ],
            ],
        ];

        $mock = Mockery::mock('overload:' . DotwService::class);
        $mock->shouldReceive('getRooms')->andReturn($blockingResult);
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getCityList')->andReturn([]);

        // B2C context with 20% markup (40 * 1.2 = 48, below MSP of 50)
        $b2cCredentials = CompanyDotwCredential::create([
            'company_id'        => $this->company->id . '_b2c_test',
            'dotw_username'     => Crypt::encrypt('b2cuser'),
            'dotw_password'     => Crypt::encrypt('b2cpass'),
            'dotw_company_code' => 'B2C',
            'markup_percent'    => 20,
            'is_active'         => true,
            'b2b_enabled'       => false,
            'b2c_enabled'       => true,
        ]);

        // Use the existing credentials but override for B2C context
        $b2cContext = new DotwAIContext(
            agent:        $this->agent,
            companyId:    $this->company->id,
            credentials:  $this->credentials,
            track:        'b2c',
            markupPercent: 20.0,
            b2bEnabled:   false,
            b2cEnabled:   true,
        );

        $input = [
            'telephone'     => '99800027',
            'option_number' => 1,
            'check_in'      => '2026-05-01',
            'check_out'     => '2026-05-03',
            'occupancy'     => [['adults' => 2]],
        ];

        $result = $this->bookingService->prebook($b2cContext, $input);

        // display_total_fare should be MSP (50), not markup price (48)
        $this->assertFalse($result['error'] ?? false);
        $this->assertEquals(50.0, (float) $result['display_total_fare']);
    }

    /**
     * @test
     */
    public function test_confirm_uses_save_booking_for_apr_rates(): void
    {
        // Arrange: credit
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 500,
            'description' => 'APR test topup',
        ]);

        // APR booking
        $booking = DotwAIBooking::create([
            'prebook_key'         => 'DOTWAI-APR-001',
            'track'               => DotwAIBooking::TRACK_B2B,
            'status'              => DotwAIBooking::STATUS_PREBOOKED,
            'company_id'          => $this->company->id,
            'agent_phone'         => '99800027',
            'hotel_id'            => 'HOTEL-APR',
            'hotel_name'          => 'APR Hotel Dubai',
            'check_in'            => '2026-05-01',
            'check_out'           => '2026-05-03',
            'original_total_fare' => 150.0,
            'display_total_fare'  => 150.0,
            'display_currency'    => 'KWD',
            'original_currency'   => 'KWD',
            'is_refundable'       => false,
            'is_apr'              => true,    // <-- APR rate
            'allocation_details'  => 'ALLOC-APR',
            'nationality_code'    => '66',
            'residence_code'      => '66',
            'rooms_data'          => [['adults' => 2]],
        ]);

        $mock = Mockery::mock('overload:' . DotwService::class);

        // For APR: saveBooking should be called, NOT confirmBooking
        $mock->shouldReceive('saveBooking')->once()->andReturn([
            'bookingCode' => 'ITINERARY-001',
        ]);
        $mock->shouldReceive('bookItinerary')->once()->andReturn([
            'confirmationNumber'  => 'CONF-APR-001',
            'bookingCode'         => 'DOTW-APR-REF',
            'paymentGuaranteedBy' => null,
        ]);
        $mock->shouldReceive('confirmBooking')->never();
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getRooms')->andReturn([]);

        $context = $this->makeB2BContext();
        $result = $this->bookingService->confirmWithCredit(
            $booking,
            $context,
            [['first_name' => 'Ali', 'last_name' => 'Hassan', 'salutation' => 'Mr']],
            null,
        );

        // Verify confirmation came from bookItinerary, not confirmBooking
        $this->assertFalse($result['error'] ?? false);
        $this->assertEquals('CONF-APR-001', $result['confirmation_no']);

        $booking->refresh();
        $this->assertEquals(DotwAIBooking::STATUS_CONFIRMED, $booking->status);
    }
}
