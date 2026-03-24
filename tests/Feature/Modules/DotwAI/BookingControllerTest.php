<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\DotwAI;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Models\Credit;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Services\DotwService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for DotwAI booking REST endpoints:
 * - POST /api/dotwai/prebook_hotel
 * - POST /api/dotwai/confirm_booking
 * - GET  /api/dotwai/balance
 *
 * Verifies full request lifecycle including middleware resolution,
 * response envelope format, and whatsappMessage presence.
 *
 * @see B2B-03 prebook_hotel endpoint
 * @see B2B-04 confirm_booking endpoint
 * @see B2B-05 balance endpoint
 * @see EVNT-02 Every response includes whatsappMessage
 */
class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private Company $company;
    private Branch $branch;
    private Agent $agent;
    private Client $client;
    private CompanyDotwCredential $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->agent = Agent::factory()->create([
            'branch_id'    => $this->branch->id,
            'phone_number' => '99800027',
            'country_code' => '+965',
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
    }

    /**
     * Seed the search cache for the agent's phone number.
     */
    private function seedSearchCache(): void
    {
        $hotel = [
            'option_number'         => 1,
            'hotel_id'              => 'HOTEL-123',
            'name'                  => 'Test Hotel Dubai',
            'city'                  => 'Dubai',
            'star_rating'           => 4,
            'cheapest_price'        => 40.0,
            'meal_type'             => 'Breakfast',
            'is_refundable'         => true,
            'currency'              => 'KWD',
            'minimum_selling_price' => 0,
        ];
        Cache::put('dotwai:search:96599800027', [$hotel], now()->addMinutes(30));
        Cache::put('dotwai:search:99800027', [$hotel], now()->addMinutes(30));
    }

    /**
     * Mock DotwService for blocking (prebook) calls.
     */
    private function mockDotwForPrebook(): void
    {
        $mock = Mockery::mock('overload:' . DotwService::class);
        $mock->shouldReceive('getRooms')->andReturn([
            [
                'roomTypeCode' => 'DBL',
                'roomName'     => 'Deluxe Room',
                'details'      => [
                    [
                        'id'                => 'RATE-001',
                        'price'             => 40.0,
                        'allocationDetails' => 'ALLOC-XYZ',
                        'cancellationRules' => [],
                    ],
                ],
            ],
        ]);
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getCityList')->andReturn([]);
        $mock->shouldReceive('confirmBooking')->andReturn([]);
        $mock->shouldReceive('saveBooking')->andReturn([]);
        $mock->shouldReceive('bookItinerary')->andReturn([]);
    }

    // -------------------------------------------------------------------------
    // prebook_hotel endpoint tests
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function test_prebook_hotel_endpoint_returns_success(): void
    {
        $this->seedSearchCache();
        $this->mockDotwForPrebook();

        $response = $this->postJson('/api/dotwai/prebook_hotel', [
            'telephone'     => '+96599800027',
            'option_number' => 1,
            'check_in'      => now()->addDays(7)->format('Y-m-d'),
            'check_out'     => now()->addDays(10)->format('Y-m-d'),
            'occupancy'     => [['adults' => 2]],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['prebook_key', 'hotel_name', 'display_total_fare', 'needs_payment'],
                'whatsappMessage',
                'whatsappOptions',
            ]);

        $this->assertNotEmpty($response->json('whatsappMessage'));
        $this->assertStringContainsString('DOTWAI-', $response->json('data.prebook_key'));
    }

    /**
     * @test
     */
    public function test_prebook_hotel_validates_required_fields(): void
    {
        // Send only telephone without other required fields
        $response = $this->postJson('/api/dotwai/prebook_hotel', [
            'telephone' => '+96599800027',
            // Missing: check_in, check_out, occupancy
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    // -------------------------------------------------------------------------
    // confirm_booking endpoint tests
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function test_confirm_booking_endpoint_b2b_credit(): void
    {
        // Setup credit
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 500,
            'description' => 'Test topup for confirm test',
        ]);

        // Create prebooked booking
        $booking = DotwAIBooking::create([
            'prebook_key'         => 'DOTWAI-CTRL-001',
            'track'               => DotwAIBooking::TRACK_B2B,
            'status'              => DotwAIBooking::STATUS_PREBOOKED,
            'company_id'          => $this->company->id,
            'agent_phone'         => '99800027',
            'hotel_id'            => 'HOTEL-CTRL',
            'hotel_name'          => 'Controller Test Hotel',
            'check_in'            => now()->addDays(7)->format('Y-m-d'),
            'check_out'           => now()->addDays(10)->format('Y-m-d'),
            'original_total_fare' => 200.0,
            'display_total_fare'  => 200.0,
            'display_currency'    => 'KWD',
            'original_currency'   => 'KWD',
            'is_refundable'       => true,
            'is_apr'              => false,
            'allocation_details'  => 'ALLOC-CTRL',
            'nationality_code'    => '66',
            'residence_code'      => '66',
            'rooms_data'          => [['adults' => 2]],
        ]);

        $mock = Mockery::mock('overload:' . DotwService::class);
        $mock->shouldReceive('confirmBooking')->andReturn([
            'confirmationNumber'  => 'CONF-CTRL-001',
            'bookingCode'         => 'DOTW-CTRL-REF',
            'paymentGuaranteedBy' => 'Test Agency',
        ]);
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getRooms')->andReturn([]);

        $response = $this->postJson('/api/dotwai/confirm_booking', [
            'telephone'   => '+96599800027',
            'prebook_key' => 'DOTWAI-CTRL-001',
            'passengers'  => [
                ['first_name' => 'Ahmed', 'last_name' => 'Ali', 'salutation' => 'Mr'],
            ],
            'email' => 'ahmed@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data'           => ['confirmation_no'],
                'whatsappMessage',
            ]);

        $this->assertNotEmpty($response->json('data.confirmation_no'));
        $this->assertNotEmpty($response->json('whatsappMessage'));
    }

    /**
     * @test
     */
    public function test_confirm_booking_returns_error_for_expired_prebook(): void
    {
        // Create expired booking (created 60 minutes ago)
        $booking = DotwAIBooking::create([
            'prebook_key'         => 'DOTWAI-EXPIRED-001',
            'track'               => DotwAIBooking::TRACK_B2B,
            'status'              => DotwAIBooking::STATUS_PREBOOKED,
            'company_id'          => $this->company->id,
            'agent_phone'         => '99800027',
            'hotel_id'            => 'HOTEL-EXP',
            'hotel_name'          => 'Expired Hotel',
            'check_in'            => now()->addDays(7)->format('Y-m-d'),
            'check_out'           => now()->addDays(10)->format('Y-m-d'),
            'original_total_fare' => 100.0,
            'display_total_fare'  => 100.0,
            'display_currency'    => 'KWD',
            'original_currency'   => 'KWD',
            'is_refundable'       => true,
            'is_apr'              => false,
            'nationality_code'    => '66',
            'residence_code'      => '66',
            'created_at'          => now()->subMinutes(60),
        ]);

        $response = $this->postJson('/api/dotwai/confirm_booking', [
            'telephone'   => '+96599800027',
            'prebook_key' => 'DOTWAI-EXPIRED-001',
            'passengers'  => [
                ['first_name' => 'John', 'last_name' => 'Doe', 'salutation' => 'Mr'],
            ],
        ]);

        $json = $response->json();
        $this->assertFalse($json['success']);
        $this->assertEquals('PREBOOK_EXPIRED', $json['error']['code']);
        $this->assertNotEmpty($json['whatsappMessage']);
    }

    // -------------------------------------------------------------------------
    // balance endpoint tests
    // -------------------------------------------------------------------------

    /**
     * @test
     */
    public function test_balance_endpoint_returns_credit_summary(): void
    {
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 1000,
            'description' => 'Balance test topup',
        ]);

        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::INVOICE,
            'amount'      => -250,
            'description' => 'Balance test deduction',
        ]);

        $response = $this->getJson('/api/dotwai/balance?telephone=+96599800027');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['credit_limit', 'used_credit', 'available_credit'],
                'whatsappMessage',
            ]);

        $data = $response->json('data');
        $this->assertEquals(1000.0, $data['credit_limit']);
        $this->assertEquals(250.0, $data['used_credit']);
        $this->assertEquals(750.0, $data['available_credit']);

        // whatsappMessage should mention balance amounts
        $msg = $response->json('whatsappMessage');
        $this->assertNotEmpty($msg);
        $this->assertStringContainsString('1,000', $msg);
    }

    /**
     * @test
     */
    public function test_balance_endpoint_rejects_b2c_track(): void
    {
        // Create B2C company setup (markup > 0, b2b_enabled=false)
        $b2cCompany = Company::factory()->create();
        $b2cBranch = Branch::factory()->create(['company_id' => $b2cCompany->id]);
        $b2cAgent = Agent::factory()->create([
            'branch_id'    => $b2cBranch->id,
            'phone_number' => '55500000',
            'country_code' => '+965',
        ]);

        CompanyDotwCredential::create([
            'company_id'        => $b2cCompany->id,
            'dotw_username'     => Crypt::encrypt('b2cuser'),
            'dotw_password'     => Crypt::encrypt('b2cpass'),
            'dotw_company_code' => 'B2C',
            'markup_percent'    => 20,   // markup > 0 = B2C track
            'is_active'         => true,
            'b2b_enabled'       => false,
            'b2c_enabled'       => true,
        ]);

        $response = $this->getJson('/api/dotwai/balance?telephone=+96555500000');

        $json = $response->json();
        $this->assertFalse($json['success']);
        $this->assertEquals('TRACK_DISABLED', $json['error']['code']);
        $this->assertNotEmpty($json['whatsappMessage']);
    }
}
