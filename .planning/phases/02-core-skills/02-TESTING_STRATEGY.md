# Testing Strategy for Hotel API Integration Skills

## Overview

This document outlines comprehensive testing strategies for Claude skills that generate production-ready API integration code. The focus is on hotel booking APIs (DOTWconnect, Booking.com, Hotelbeds) with realistic scenarios, proper isolation, and validation of complex workflows.

**Goal**: Ensure generated code handles real-world scenarios including multi-passenger bookings, rate lock expiration, error conditions, and edge cases.

---

## 1. Unit Testing Patterns for Services

Unit tests focus on isolated components without database or external API access. They test individual methods in service classes.

### Test Structure

```php
<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\HotelSearchService;
use App\Services\RateLockService;

class HotelSearchServiceTest extends TestCase
{
    private HotelSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HotelSearchService();
    }

    /**
     * Test search request parameter validation
     */
    public function test_validates_required_search_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->buildSearchRequest([
            // Missing required hotel_id
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
        ]);
    }

    /**
     * Test date validation
     */
    public function test_rejects_invalid_date_ranges(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->buildSearchRequest([
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-05',
            'check_out' => '2026-04-01', // Checkout before check-in
        ]);
    }

    /**
     * Test guest count validation
     */
    public function test_validates_guest_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->buildSearchRequest([
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [
                [
                    'adults' => 0, // Invalid: must be >= 1
                    'children' => 2,
                ]
            ]
        ]);
    }
}
```

### Best Practices for Unit Tests

1. **Test one thing per test** - Each test method validates a single behavior
2. **Use descriptive names** - Method name explains what is being tested
3. **Arrange-Act-Assert pattern**:
   ```php
   // Arrange - Set up test data
   $params = ['hotel_id' => 'DOT123456', ...];

   // Act - Execute the code being tested
   $result = $this->service->buildSearchRequest($params);

   // Assert - Verify the outcome
   $this->assertInstanceOf(SearchRequest::class, $result);
   ```

4. **Test edge cases**:
   - Empty inputs
   - Null values
   - Maximum values
   - Boundary conditions (e.g., same-day checkout)

5. **Mock external dependencies** - Don't call real APIs in unit tests

---

## 2. Integration Testing Patterns for Full Workflows

Integration tests use the full Laravel application including database, caching, and HTTP client mocking. They test complete workflows from API request to database persistence.

### Feature Test Structure

```php
<?php

namespace Tests\Feature\HotelBooking;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class HotelSearchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete search to rate lock workflow
     */
    public function test_complete_hotel_search_and_rate_lock_workflow(): void
    {
        // Mock the external API response
        Http::fake([
            'api.dotw.com/search' => Http::response(
                file_get_contents(base_path('tests/Fixtures/hotel_search_response.xml')),
                200,
                ['Content-Type' => 'application/xml']
            ),
            'api.dotw.com/rate-lock' => Http::response(
                file_get_contents(base_path('tests/Fixtures/rate_lock_response.xml')),
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        // Step 1: Perform hotel search
        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [
                [
                    'adults' => 2,
                    'children' => 1,
                    'child_ages' => [8]
                ]
            ]
        ]);

        // Verify search response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'rates' => [
                        '*' => [
                            'rate_id',
                            'room_type',
                            'price',
                            'currency',
                            'available_rooms'
                        ]
                    ]
                ]
            ]);

        $searchData = $response->json('data');

        // Step 2: Lock a rate
        $lockResponse = $this->postJson('/api/hotel/lock-rate', [
            'rate_id' => $searchData['rates'][0]['rate_id'],
            'duration_minutes' => 15
        ]);

        $lockResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'lock_token',
                    'expires_at'
                ]
            ]);

        // Step 3: Verify rate lock persisted in database
        $this->assertDatabaseHas('rate_locks', [
            'rate_id' => $searchData['rates'][0]['rate_id'],
            'lock_token' => $lockResponse->json('data.lock_token'),
        ]);
    }

    /**
     * Test search with multiple rooms and passengers
     */
    public function test_multi_room_multi_passenger_search(): void
    {
        Http::fake([
            'api.dotw.com/search' => Http::response(
                file_get_contents(base_path('tests/Fixtures/multi_room_search_response.xml')),
                200
            ),
        ]);

        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT789012',
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-15',
            'rooms' => [
                [
                    'adults' => 2,
                    'children' => 2,
                    'child_ages' => [5, 10]
                ],
                [
                    'adults' => 1,
                    'children' => 0
                ]
            ]
        ]);

        $response->assertStatus(200);

        $rates = $response->json('data.rates');

        // Verify rates returned for correct number of rooms
        $this->assertCount(2, $rates[0]['room_breakdown']);
    }

    /**
     * Test deferred booking flow (search, lock, book later)
     */
    public function test_deferred_booking_workflow(): void
    {
        Http::fake([
            'api.dotw.com/*' => Http::sequence()
                ->push(Http::response(
                    file_get_contents(base_path('tests/Fixtures/hotel_search_response.xml')),
                    200
                ))
                ->push(Http::response(
                    file_get_contents(base_path('tests/Fixtures/rate_lock_response.xml')),
                    200
                ))
                ->push(Http::response(
                    file_get_contents(base_path('tests/Fixtures/booking_confirmation.xml')),
                    200
                )),
        ]);

        // Search and lock rate
        $searchResponse = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2, 'children' => 0]]
        ]);

        $lockResponse = $this->postJson('/api/hotel/lock-rate', [
            'rate_id' => $searchResponse->json('data.rates.0.rate_id'),
            'duration_minutes' => 60
        ]);

        $lockToken = $lockResponse->json('data.lock_token');

        // Store booking intent in database for later completion
        $this->assertDatabaseHas('pending_bookings', [
            'lock_token' => $lockToken,
            'status' => 'locked'
        ]);

        // Later, complete the booking
        $bookingResponse = $this->postJson('/api/hotel/book', [
            'lock_token' => $lockToken,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+965123456789'
        ]);

        $bookingResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'booking_id',
                    'confirmation_number',
                    'total_price'
                ]
            ]);

        // Verify booking persisted
        $this->assertDatabaseHas('bookings', [
            'lock_token' => $lockToken,
            'status' => 'confirmed'
        ]);
    }
}
```

### Integration Test Best Practices

1. **Use RefreshDatabase trait** - Automatically rolls back database changes after each test
   ```php
   use Illuminate\Foundation\Testing\RefreshDatabase;
   ```

2. **Mock external APIs with Http::fake()** - Don't make real API calls
   ```php
   Http::fake([
       'api.example.com/*' => Http::response($body, $status, $headers)
   ]);
   ```

3. **Test complete workflows** - Not just individual endpoints
4. **Verify database state** - Use `assertDatabaseHas()` and `assertDatabaseMissing()`
5. **Test with realistic data** - Use fixtures that mirror production API responses

---

## 3. Mock Data Generation Strategies

### Using Factories for Test Data

Create model factories to generate realistic test data without hardcoding values.

```php
<?php

namespace Database\Factories;

use App\Models\HotelProperty;
use Illuminate\Database\Eloquent\Factories\Factory;

class HotelPropertyFactory extends Factory
{
    protected $model = HotelProperty::class;

    public function definition(): array
    {
        return [
            'dotwconnect_id' => 'DOT' . $this->faker->numberBetween(100000, 999999),
            'name' => $this->faker->words(3, true),
            'city' => $this->faker->city,
            'country' => $this->faker->country,
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'stars' => $this->faker->numberBetween(2, 5),
            'description' => $this->faker->text(200),
        ];
    }

    /**
     * Create a luxury hotel
     */
    public function luxury(): static
    {
        return $this->state(fn (array $attributes) => [
            'stars' => 5,
            'name' => '5-Star ' . $this->faker->words(2, true),
        ]);
    }

    /**
     * Create budget hotel
     */
    public function budget(): static
    {
        return $this->state(fn (array $attributes) => [
            'stars' => 2,
            'name' => 'Budget ' . $this->faker->words(2, true),
        ]);
    }
}
```

Usage in tests:

```php
// Create single hotel
$hotel = HotelProperty::factory()->create();

// Create multiple
$hotels = HotelProperty::factory()->count(5)->create();

// Create with specific state
$luxury = HotelProperty::factory()->luxury()->create();

// Create with custom attributes
$hotel = HotelProperty::factory()->create([
    'dotwconnect_id' => 'DOT123456',
    'city' => 'Kuwait City'
]);
```

### Generating Fixture Data Programmatically

Create helper methods to generate consistent test fixtures:

```php
<?php

namespace Tests\Fixtures;

class HotelSearchFixtures
{
    /**
     * Generate realistic hotel search response XML
     */
    public static function generateSearchResponse(array $overrides = []): string
    {
        $defaults = [
            'hotel_id' => 'DOT123456',
            'room_count' => 1,
            'rate_count' => 3,
            'currency' => 'KWD',
            'base_price' => 150.00,
        ];

        $params = array_merge($defaults, $overrides);

        $rates = '';
        for ($i = 1; $i <= $params['rate_count']; $i++) {
            $price = $params['base_price'] + ($i * 10);
            $rates .= <<<XML
                <Rate RateID="RATE{$params['hotel_id']}_$i">
                    <RoomTypeCode>DLX</RoomTypeCode>
                    <BaseByGuestAmt>{$price}</BaseByGuestAmt>
                    <CurrencyCode>{$params['currency']}</CurrencyCode>
                    <AvailableQuantity>{$params['room_count']}</AvailableQuantity>
                    <Cancellation NonRefundableRate="false">
                        <CancellationPolicy>
                            <Deadline DateTime="2026-03-25T23:59:00"/>
                        </CancellationPolicy>
                    </Cancellation>
                </Rate>
XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<OTA_HotelSearchRS>
    <RoomStays>
        <RoomStay HotelCode="{$params['hotel_id']}">
            <RoomTypes>
                <RoomType RoomTypeCode="DLX">
                    <RoomTypeDescription>Deluxe Room</RoomTypeDescription>
                    <Rates>
                        $rates
                    </Rates>
                </RoomType>
            </RoomTypes>
        </RoomStay>
    </RoomStays>
</OTA_HotelSearchRS>
XML;
    }

    /**
     * Generate rate lock response
     */
    public static function generateRateLockResponse(array $overrides = []): string
    {
        $defaults = [
            'lock_token' => 'LOCK_' . bin2hex(random_bytes(16)),
            'rate_id' => 'RATE_' . bin2hex(random_bytes(8)),
            'expires_minutes' => 15,
        ];

        $params = array_merge($defaults, $overrides);
        $expiresAt = date('Y-m-d\TH:i:s', time() + ($params['expires_minutes'] * 60));

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<RateLockRS>
    <LockToken>{$params['lock_token']}</LockToken>
    <RateID>{$params['rate_id']}</RateID>
    <ExpiresAt>$expiresAt</ExpiresAt>
</RateLockRS>
XML;
    }
}
```

---

## 4. XML Fixture Examples

### Sample Hotel Search Response Fixture

**File**: `tests/Fixtures/hotel_search_response.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<OTA_HotelSearchRS xmlns="http://www.opentravel.org/OTA/2003/05">
    <Success/>
    <RoomStays>
        <RoomStay HotelCode="DOT123456" HotelName="Grand Hotel Kuwait">
            <RoomTypes>
                <RoomType RoomTypeCode="DLX">
                    <RoomTypeDescription>Deluxe Room</RoomTypeDescription>
                    <Rates>
                        <Rate RateID="RATE_DLX_001" RateStatus="Active">
                            <BaseByGuestAmt>150.000</BaseByGuestAmt>
                            <CurrencyCode>KWD</CurrencyCode>
                            <AvailableQuantity>5</AvailableQuantity>
                            <Cancellation NonRefundableRate="false">
                                <CancellationPolicy>
                                    <Deadline DateTime="2026-03-25T23:59:00"/>
                                </CancellationPolicy>
                            </Cancellation>
                            <MaxOccupancy>2</MaxOccupancy>
                        </Rate>
                        <Rate RateID="RATE_DLX_002" RateStatus="Active">
                            <BaseByGuestAmt>160.000</BaseByGuestAmt>
                            <CurrencyCode>KWD</CurrencyCode>
                            <AvailableQuantity>3</AvailableQuantity>
                            <Cancellation NonRefundableRate="true">
                                <CancellationPolicy>
                                    <Deadline DateTime="2026-03-20T23:59:00"/>
                                </CancellationPolicy>
                            </Cancellation>
                            <MaxOccupancy>2</MaxOccupancy>
                        </Rate>
                    </Rates>
                </RoomType>
                <RoomType RoomTypeCode="STE">
                    <RoomTypeDescription>Suite</RoomTypeDescription>
                    <Rates>
                        <Rate RateID="RATE_STE_001" RateStatus="Active">
                            <BaseByGuestAmt>250.000</BaseByGuestAmt>
                            <CurrencyCode>KWD</CurrencyCode>
                            <AvailableQuantity>2</AvailableQuantity>
                            <Cancellation NonRefundableRate="false">
                                <CancellationPolicy>
                                    <Deadline DateTime="2026-03-25T23:59:00"/>
                                </CancellationPolicy>
                            </Cancellation>
                            <MaxOccupancy>4</MaxOccupancy>
                        </Rate>
                    </Rates>
                </RoomType>
            </RoomTypes>
            <StayDateRange Start="2026-04-01" End="2026-04-05"/>
        </RoomStay>
    </RoomStays>
</OTA_HotelSearchRS>
```

### Sample Rate Lock Response Fixture

**File**: `tests/Fixtures/rate_lock_response.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<RateLockResponse>
    <Success/>
    <LockDetails>
        <LockToken>LOCK_abc123def456xyz789</LockToken>
        <RateID>RATE_DLX_001</RateID>
        <HotelCode>DOT123456</HotelCode>
        <LockedRate>150.000</LockedRate>
        <CurrencyCode>KWD</CurrencyCode>
        <CreatedAt>2026-03-09T10:30:00Z</CreatedAt>
        <ExpiresAt>2026-03-09T10:45:00Z</ExpiresAt>
        <ExpirationMinutes>15</ExpirationMinutes>
    </LockDetails>
</RateLockResponse>
```

### Sample Multi-Room Search Response Fixture

**File**: `tests/Fixtures/multi_room_search_response.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<OTA_HotelSearchRS xmlns="http://www.opentravel.org/OTA/2003/05">
    <Success/>
    <RoomStays>
        <RoomStay RoomStayIndex="1" HotelCode="DOT789012">
            <StayDateRange Start="2026-05-10" End="2026-05-15"/>
            <RoomTypes>
                <RoomType RoomTypeCode="DLX">
                    <RoomTypeDescription>Deluxe Double</RoomTypeDescription>
                    <Rates>
                        <Rate RateID="RATE_ROOM1_001">
                            <BaseByGuestAmt>175.000</BaseByGuestAmt>
                            <CurrencyCode>KWD</CurrencyCode>
                            <AvailableQuantity>1</AvailableQuantity>
                            <MaxOccupancy>2</MaxOccupancy>
                            <ChildrenAllowed>2</ChildrenAllowed>
                        </Rate>
                    </Rates>
                </RoomType>
            </RoomTypes>
        </RoomStay>
        <RoomStay RoomStayIndex="2" HotelCode="DOT789012">
            <StayDateRange Start="2026-05-10" End="2026-05-15"/>
            <RoomTypes>
                <RoomType RoomTypeCode="SGL">
                    <RoomTypeDescription>Single Room</RoomTypeDescription>
                    <Rates>
                        <Rate RateID="RATE_ROOM2_001">
                            <BaseByGuestAmt>100.000</BaseByGuestAmt>
                            <CurrencyCode>KWD</CurrencyCode>
                            <AvailableQuantity>1</AvailableQuantity>
                            <MaxOccupancy>1</MaxOccupancy>
                        </Rate>
                    </Rates>
                </RoomType>
            </RoomTypes>
        </RoomStay>
    </RoomStays>
</OTA_HotelSearchRS>
```

### Sample Booking Confirmation Fixture

**File**: `tests/Fixtures/booking_confirmation.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<BookingConfirmationRS>
    <Success/>
    <BookingDetails>
        <BookingID>BK20260309001</BookingID>
        <ConfirmationNumber>CONF_ABC123XYZ</ConfirmationNumber>
        <HotelCode>DOT123456</HotelCode>
        <HotelName>Grand Hotel Kuwait</HotelName>
        <CheckInDate>2026-04-01</CheckInDate>
        <CheckOutDate>2026-04-05</CheckOutDate>
        <NumberOfNights>4</NumberOfNights>
        <RoomCount>1</RoomCount>
        <GuestName>John Doe</GuestName>
        <GuestEmail>john@example.com</GuestEmail>
        <GuestPhone>+965123456789</GuestPhone>
        <PricingDetails>
            <SubTotal>600.000</SubTotal>
            <Tax>30.000</Tax>
            <Total>630.000</Total>
            <CurrencyCode>KWD</CurrencyCode>
        </PricingDetails>
        <BookingStatus>Confirmed</BookingStatus>
        <BookingDate>2026-03-09T10:35:00Z</BookingDate>
    </BookingDetails>
</BookingConfirmationRS>
```

---

## 5. Test Assertion Examples

### JSON Response Assertions

```php
class HotelAPIAssertionsTest extends TestCase
{
    /**
     * Test JSON structure validation
     */
    public function test_search_response_has_correct_structure(): void
    {
        Http::fake([
            'api.dotw.com/search' => Http::response(
                file_get_contents(base_path('tests/Fixtures/hotel_search_response.xml')),
                200
            ),
        ]);

        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2, 'children' => 0]]
        ]);

        // Assert overall structure
        $response->assertJsonStructure([
            'data' => [
                'hotel_id',
                'hotel_name',
                'check_in',
                'check_out',
                'rates' => [
                    '*' => [
                        'rate_id',
                        'room_type',
                        'price',
                        'currency',
                        'available_rooms',
                        'cancellation' => [
                            'refundable',
                            'deadline'
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test specific JSON values
     */
    public function test_search_response_contains_correct_values(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        $response = $this->postJson('/api/hotel/search', [...]);

        // Assert specific values
        $response->assertJson([
            'data' => [
                'hotel_id' => 'DOT123456',
                'currency' => 'KWD',
            ]
        ]);

        // Assert all rates have required fields
        collect($response->json('data.rates'))->each(function ($rate) use ($response) {
            $response->assertJson([
                'rate_id' => $rate['rate_id'],
                'price' => $rate['price'],
            ]);
        });
    }

    /**
     * Test JSON path assertions
     */
    public function test_specific_rate_has_expected_price(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        $response = $this->postJson('/api/hotel/search', [...]);

        // Assert specific path in JSON
        $this->assertNotNull($response->json('data.rates.0.rate_id'));
        $this->assertGreaterThan(0, $response->json('data.rates.0.price'));
        $this->assertEquals('KWD', $response->json('data.rates.0.currency'));
    }

    /**
     * Test JSON does not contain sensitive data
     */
    public function test_search_response_excludes_sensitive_fields(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        $response = $this->postJson('/api/hotel/search', [...]);

        // Assert fields don't exist
        $this->assertArrayNotHasKey('internal_cost', $response->json('data'));
        $this->assertArrayNotHasKey('supplier_id', $response->json('data'));
    }

    /**
     * Test rate count matches request
     */
    public function test_rates_returned_for_each_room(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        $response = $this->postJson('/api/hotel/search', [
            'rooms' => [
                ['adults' => 2, 'children' => 0],
                ['adults' => 1, 'children' => 0],
            ]
        ]);

        // Assert correct structure for multi-room
        $roomBreakdown = $response->json('data.room_breakdown');
        $this->assertCount(2, $roomBreakdown);
    }
}
```

### Database Assertion Examples

```php
class HotelDatabaseAssertionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test data persists correctly
     */
    public function test_successful_booking_creates_database_records(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        $response = $this->postJson('/api/hotel/book', [
            'lock_token' => 'LOCK_abc123',
            'guest_name' => 'Jane Smith',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '+965987654321'
        ]);

        // Assert booking record created
        $this->assertDatabaseHas('bookings', [
            'guest_name' => 'Jane Smith',
            'guest_email' => 'jane@example.com',
            'status' => 'confirmed'
        ]);

        // Assert booking has associated guest record
        $booking = Booking::where('guest_email', 'jane@example.com')->first();
        $this->assertNotNull($booking);
        $this->assertDatabaseHas('guests', [
            'booking_id' => $booking->id,
            'name' => 'Jane Smith'
        ]);
    }

    /**
     * Test data is not created on validation failure
     */
    public function test_invalid_booking_does_not_create_records(): void
    {
        $response = $this->postJson('/api/hotel/book', [
            'lock_token' => 'INVALID_TOKEN',
            'guest_name' => '', // Invalid: empty name
            'guest_email' => 'invalid-email',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('bookings', [
            'guest_email' => 'invalid-email'
        ]);
    }

    /**
     * Test rate lock deletion after booking
     */
    public function test_rate_lock_deleted_after_booking(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        // Create rate lock
        $lock = RateLock::create([
            'lock_token' => 'LOCK_abc123',
            'rate_id' => 'RATE_001',
            'expires_at' => now()->addMinutes(15)
        ]);

        // Book with lock token
        $response = $this->postJson('/api/hotel/book', [
            'lock_token' => 'LOCK_abc123',
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+965123456789'
        ]);

        $response->assertStatus(200);

        // Assert lock is deleted
        $this->assertDatabaseMissing('rate_locks', [
            'lock_token' => 'LOCK_abc123'
        ]);
    }
}
```

---

## 6. Rate Lock Timeout Testing

### Time Travel Testing for Expiration

Use Laravel's `travel()` helper to test time-dependent functionality without actual delays:

```php
<?php

namespace Tests\Feature\HotelBooking;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class RateLockTimeoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test rate lock expires after timeout
     */
    public function test_rate_lock_expires_after_specified_duration(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        // Create rate lock for 15 minutes
        $lockResponse = $this->postJson('/api/hotel/lock-rate', [
            'rate_id' => 'RATE_DLX_001',
            'duration_minutes' => 15
        ]);

        $lockToken = $lockResponse->json('data.lock_token');

        // Verify lock exists
        $this->assertDatabaseHas('rate_locks', [
            'lock_token' => $lockToken,
            'status' => 'active'
        ]);

        // Travel 15 minutes into the future
        $this->travel(15)->minutes();

        // Clean up expired locks
        $this->artisan('app:cleanup-expired-locks');

        // Verify lock is marked as expired
        $this->assertDatabaseHas('rate_locks', [
            'lock_token' => $lockToken,
            'status' => 'expired'
        ]);
    }

    /**
     * Test cannot book with expired lock
     */
    public function test_cannot_book_with_expired_lock(): void
    {
        // Create and immediately expire lock
        $lock = RateLock::create([
            'lock_token' => 'LOCK_expired',
            'rate_id' => 'RATE_001',
            'expires_at' => now()->subMinutes(5),
            'status' => 'expired'
        ]);

        $response = $this->postJson('/api/hotel/book', [
            'lock_token' => 'LOCK_expired',
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+965123456789'
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Rate lock has expired. Please search again.'
            ]);
    }

    /**
     * Test can book before lock expires
     */
    public function test_can_book_before_lock_expires(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        // Create lock
        $lockResponse = $this->postJson('/api/hotel/lock-rate', [
            'rate_id' => 'RATE_DLX_001',
            'duration_minutes' => 15
        ]);

        $lockToken = $lockResponse->json('data.lock_token');

        // Travel only 5 minutes
        $this->travel(5)->minutes();

        // Booking should succeed
        $bookingResponse = $this->postJson('/api/hotel/book', [
            'lock_token' => $lockToken,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+965123456789'
        ]);

        $bookingResponse->assertStatus(200);

        // Verify booking created
        $this->assertDatabaseHas('bookings', [
            'lock_token' => $lockToken,
            'status' => 'confirmed'
        ]);
    }

    /**
     * Test lock expiration time is calculated correctly
     */
    public function test_lock_expiration_calculated_from_lock_creation(): void
    {
        $before = now();

        $lockResponse = $this->postJson('/api/hotel/lock-rate', [
            'rate_id' => 'RATE_DLX_001',
            'duration_minutes' => 20
        ]);

        $after = now();

        $lockData = $lockResponse->json('data');
        $expiresAt = Carbon::parse($lockData['expires_at']);

        // Lock should expire between 20-21 minutes from before to after
        $this->assertTrue(
            $expiresAt->between(
                $before->addMinutes(20),
                $after->addMinutes(20)->addSecond()
            )
        );
    }
}
```

### Database Cleanup Test

```php
class RateLockCleanupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test cleanup command removes only expired locks
     */
    public function test_cleanup_command_only_removes_expired_locks(): void
    {
        // Create active lock
        RateLock::create([
            'lock_token' => 'LOCK_active',
            'rate_id' => 'RATE_001',
            'expires_at' => now()->addMinutes(10),
            'status' => 'active'
        ]);

        // Create expired lock
        RateLock::create([
            'lock_token' => 'LOCK_expired',
            'rate_id' => 'RATE_002',
            'expires_at' => now()->subMinutes(5),
            'status' => 'expired'
        ]);

        // Run cleanup
        $this->artisan('app:cleanup-expired-locks');

        // Active lock should still exist
        $this->assertDatabaseHas('rate_locks', [
            'lock_token' => 'LOCK_active'
        ]);

        // Expired lock should be deleted
        $this->assertDatabaseMissing('rate_locks', [
            'lock_token' => 'LOCK_expired'
        ]);
    }
}
```

---

## 7. Error Scenario Testing

### Validation Error Testing

```php
<?php

namespace Tests\Feature\HotelBooking;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HotelSearchValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test with data provider for multiple validation scenarios
     *
     * @dataProvider invalidSearchParameters
     */
    public function test_search_validation_with_invalid_parameters(array $params, string $expectedField): void
    {
        $response = $this->postJson('/api/hotel/search', $params);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([$expectedField]);
    }

    /**
     * Data provider for invalid parameters
     */
    public static function invalidSearchParameters(): array
    {
        return [
            'missing_hotel_id' => [
                [
                    'check_in' => '2026-04-01',
                    'check_out' => '2026-04-05',
                    'rooms' => [['adults' => 2]]
                ],
                'hotel_id'
            ],
            'invalid_date_format' => [
                [
                    'hotel_id' => 'DOT123456',
                    'check_in' => 'invalid-date',
                    'check_out' => '2026-04-05',
                    'rooms' => [['adults' => 2]]
                ],
                'check_in'
            ],
            'checkout_before_checkin' => [
                [
                    'hotel_id' => 'DOT123456',
                    'check_in' => '2026-04-05',
                    'check_out' => '2026-04-01',
                    'rooms' => [['adults' => 2]]
                ],
                'check_out'
            ],
            'no_adults_in_room' => [
                [
                    'hotel_id' => 'DOT123456',
                    'check_in' => '2026-04-01',
                    'check_out' => '2026-04-05',
                    'rooms' => [['adults' => 0, 'children' => 2]]
                ],
                'rooms.0.adults'
            ],
            'invalid_email' => [
                [
                    'hotel_id' => 'DOT123456',
                    'check_in' => '2026-04-01',
                    'check_out' => '2026-04-05',
                    'rooms' => [['adults' => 2]],
                    'guest_email' => 'not-an-email'
                ],
                'guest_email'
            ],
            'past_check_in_date' => [
                [
                    'hotel_id' => 'DOT123456',
                    'check_in' => '2020-04-01', // Past date
                    'check_out' => '2020-04-05',
                    'rooms' => [['adults' => 2]]
                ],
                'check_in'
            ],
        ];
    }
}
```

### API Error Response Testing

```php
class HotelAPIErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test API error for invalid hotel ID
     */
    public function test_search_with_nonexistent_hotel(): void
    {
        Http::fake([
            'api.dotw.com/search' => Http::response(
                '<?xml version="1.0"?><OTA_HotelSearchRS><Errors><Error Type="2">Hotel not found</Error></Errors></OTA_HotelSearchRS>',
                400
            ),
        ]);

        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT999999', // Non-existent
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2]]
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => 'Hotel not found'
            ]);
    }

    /**
     * Test API timeout handling
     */
    public function test_search_handles_api_timeout(): void
    {
        Http::fake([
            'api.dotw.com/search' => Http::response(null, 408), // 408 Request Timeout
        ]);

        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2]]
        ]);

        $response->assertStatus(503) // Service Unavailable
            ->assertJsonFragment([
                'error' => 'API request timed out. Please try again.'
            ]);
    }

    /**
     * Test handling of malformed XML response
     */
    public function test_search_handles_malformed_xml_response(): void
    {
        Http::fake([
            'api.dotw.com/search' => Http::response(
                '<?xml version="1.0"?><OTA_HotelSearchRS><RoomStays><Invalid>',
                200
            ),
        ]);

        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2]]
        ]);

        $response->assertStatus(500)
            ->assertJsonFragment([
                'error' => 'Failed to parse API response'
            ]);
    }

    /**
     * Test rate lock not available error
     */
    public function test_lock_rate_when_unavailable(): void
    {
        Http::fake([
            'api.dotw.com/rate-lock' => Http::response(
                '<?xml version="1.0"?><RateLockRS><Error>Rate unavailable</Error></RateLockRS>',
                409
            ),
        ]);

        $response = $this->postJson('/api/hotel/lock-rate', [
            'rate_id' => 'RATE_SOLD_OUT',
            'duration_minutes' => 15
        ]);

        $response->assertStatus(409)
            ->assertJsonFragment([
                'error' => 'Rate unavailable - possibly sold out'
            ]);
    }

    /**
     * Test authentication failure
     */
    public function test_search_with_invalid_credentials(): void
    {
        Http::fake([
            'api.dotw.com/search' => Http::response(
                '<?xml version="1.0"?><OTA_HotelSearchRS><Error>Invalid API credentials</Error></OTA_HotelSearchRS>',
                401
            ),
        ]);

        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2]]
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment([
                'error' => 'API authentication failed'
            ]);
    }
}
```

---

## 8. Performance Testing Considerations

### Query Performance Testing

```php
<?php

namespace Tests\Feature\HotelBooking;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class HotelSearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test search query count with single hotel
     */
    public function test_search_maintains_reasonable_query_count(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        // Start query logging
        DB::enableQueryLog();

        $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2]]
        ]);

        $queries = DB::getQueryLog();

        // Should use minimal queries (e.g., auth, hotel lookup, rate check)
        $this->assertLessThan(10, count($queries),
            'Search endpoint is executing too many database queries');

        DB::disableQueryLog();
    }

    /**
     * Test search response time
     */
    public function test_search_response_time_is_acceptable(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        $start = microtime(true);

        $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2]]
        ]);

        $duration = (microtime(true) - $start) * 1000; // Convert to ms

        // Should complete within 500ms
        $this->assertLessThan(500, $duration,
            "Search took {$duration}ms, expected less than 500ms");
    }

    /**
     * Test booking with large guest list doesn't timeout
     */
    public function test_booking_multiple_guests_performs_well(): void
    {
        Http::fake(['api.dotw.com/*' => Http::response(...)]);

        // Create multiple guest records
        $guests = [];
        for ($i = 0; $i < 10; $i++) {
            $guests[] = [
                'name' => "Guest $i",
                'email' => "guest$i@example.com",
                'phone' => "+965123456789"
            ];
        }

        $start = microtime(true);

        $response = $this->postJson('/api/hotel/book', [
            'lock_token' => 'LOCK_abc123',
            'primary_guest' => $guests[0],
            'additional_guests' => array_slice($guests, 1)
        ]);

        $duration = (microtime(true) - $start) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(1000, $duration,
            "Booking with multiple guests took {$duration}ms");
    }

    /**
     * Test N+1 query problem doesn't occur
     */
    public function test_no_n_plus_one_queries_when_listing_bookings(): void
    {
        // Create multiple bookings
        $bookings = Booking::factory()->count(5)->create();

        DB::enableQueryLog();

        $response = $this->getJson('/api/bookings');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should be 1 query for bookings + 1 for relations, not 1 + N
        $this->assertLessThan(5, count($queries),
            'Detected potential N+1 query problem');

        $response->assertStatus(200);
    }
}
```

### Load Testing with Artillery or Locust

Create a load test configuration file:

**File**: `tests/load-test.yml` (for Artillery)

```yaml
config:
  target: "http://localhost:8000"
  phases:
    - duration: 60
      arrivalRate: 5
      name: "Warm up"
    - duration: 120
      arrivalRate: 10
      name: "Normal load"
    - duration: 60
      arrivalRate: 20
      name: "High load"

scenarios:
  - name: "Hotel Search Scenario"
    flow:
      - post:
          url: "/api/hotel/search"
          json:
            hotel_id: "DOT123456"
            check_in: "2026-04-01"
            check_out: "2026-04-05"
            rooms:
              - adults: 2
                children: 0
          expect:
            - statusCode: 200
      - think: 5
      - post:
          url: "/api/hotel/lock-rate"
          json:
            rate_id: "RATE_DLX_001"
            duration_minutes: 15
          expect:
            - statusCode: 200
```

---

## 9. Test File Organization

### Recommended Directory Structure

```
tests/
├── Feature/
│   ├── HotelBooking/
│   │   ├── HotelSearchTest.php
│   │   ├── HotelSearchWorkflowTest.php
│   │   ├── RateLockTest.php
│   │   ├── RateLockTimeoutTest.php
│   │   ├── BookingTest.php
│   │   ├── ErrorHandlingTest.php
│   │   └── ValidationTest.php
│   ├── Auth/
│   │   └── AuthenticationTest.php
│   └── Payment/
│       └── PaymentTest.php
├── Unit/
│   ├── Services/
│   │   ├── HotelSearchServiceTest.php
│   │   ├── RateLockServiceTest.php
│   │   └── BookingServiceTest.php
│   ├── Models/
│   │   └── HotelPropertyTest.php
│   └── Parsers/
│       └── XMLResponseParserTest.php
├── Fixtures/
│   ├── hotel_search_response.xml
│   ├── rate_lock_response.xml
│   ├── multi_room_search_response.xml
│   ├── booking_confirmation.xml
│   └── error_responses/
│       ├── api_timeout.xml
│       ├── malformed_response.xml
│       └── auth_error.xml
├── Factories/
│   ├── HotelPropertyFactory.php
│   ├── BookingFactory.php
│   └── RateLockFactory.php
└── TestCase.php  // Base test class
```

### Base Test Class

**File**: `tests/TestCase.php`

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Setup mock API responses for all tests
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Default HTTP mocking
        Http::preventStrayRequests();
    }

    /**
     * Load a fixture file
     */
    protected function loadFixture(string $filename): string
    {
        $path = base_path("tests/Fixtures/$filename");

        if (!file_exists($path)) {
            throw new \Exception("Fixture not found: $filename");
        }

        return file_get_contents($path);
    }

    /**
     * Create a mock hotel property
     */
    protected function createMockHotel(array $overrides = []): array
    {
        return array_merge([
            'dotwconnect_id' => 'DOT' . rand(100000, 999999),
            'name' => 'Test Hotel',
            'city' => 'Kuwait',
            'stars' => 4,
        ], $overrides);
    }

    /**
     * Assert pagination structure in JSON response
     */
    protected function assertHasPaginationStructure($response): void
    {
        $response->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'next', 'prev'],
            'meta' => ['current_page', 'from', 'to', 'total', 'per_page']
        ]);
    }
}
```

---

## 10. Example Test Files with Realistic Scenarios

### Complete Hotel Search Test Example

**File**: `tests/Feature/HotelBooking/HotelSearchWorkflowTest.php`

```php
<?php

namespace Tests\Feature\HotelBooking;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class HotelSearchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: User searches for hotels in Kuwait City for April 1-5
     * Expected: Returns available rooms with prices and details
     */
    public function test_user_can_search_hotels_and_view_available_rates(): void
    {
        // Mock external API
        Http::fake([
            'api.dotw.com/search' => Http::response(
                $this->loadFixture('hotel_search_response.xml'),
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        // User performs search
        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [
                [
                    'adults' => 2,
                    'children' => 1,
                    'child_ages' => [8]
                ]
            ]
        ]);

        // Verify response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'hotel_id',
                    'hotel_name',
                    'address',
                    'check_in',
                    'check_out',
                    'rates' => [
                        '*' => [
                            'rate_id',
                            'room_type',
                            'price',
                            'currency',
                            'available_rooms',
                            'max_occupancy',
                            'cancellation' => ['refundable', 'deadline']
                        ]
                    ]
                ]
            ]);

        // Verify data consistency
        $data = $response->json('data');
        $this->assertEquals('DOT123456', $data['hotel_id']);
        $this->assertEquals('2026-04-01', $data['check_in']);
        $this->assertEquals('2026-04-05', $data['check_out']);
        $this->assertNotEmpty($data['rates']);

        // Verify each rate has required fields
        foreach ($data['rates'] as $rate) {
            $this->assertArrayHasKey('rate_id', $rate);
            $this->assertGreaterThan(0, $rate['price']);
            $this->assertEquals('KWD', $rate['currency']);
        }
    }

    /**
     * Test: User locks a rate for 15 minutes
     * Expected: Rate is reserved and expires after 15 minutes
     */
    public function test_user_can_lock_rate_with_expiration(): void
    {
        Http::fake([
            'api.dotw.com/rate-lock' => Http::response(
                $this->loadFixture('rate_lock_response.xml'),
                200
            ),
        ]);

        $lockResponse = $this->postJson('/api/hotel/lock-rate', [
            'rate_id' => 'RATE_DLX_001',
            'duration_minutes' => 15
        ]);

        $lockResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'lock_token',
                    'rate_id',
                    'locked_price',
                    'expires_at'
                ]
            ]);

        $lockData = $lockResponse->json('data');

        // Verify lock persisted
        $this->assertDatabaseHas('rate_locks', [
            'lock_token' => $lockData['lock_token'],
            'rate_id' => 'RATE_DLX_001',
            'status' => 'active'
        ]);
    }

    /**
     * Test: User completes booking within rate lock window
     * Expected: Booking is confirmed and lock is consumed
     */
    public function test_user_can_complete_booking_before_lock_expires(): void
    {
        Http::fake([
            'api.dotw.com/booking' => Http::response(
                $this->loadFixture('booking_confirmation.xml'),
                200
            ),
        ]);

        // Create active rate lock
        $lock = RateLock::create([
            'lock_token' => 'LOCK_abc123',
            'rate_id' => 'RATE_DLX_001',
            'expires_at' => now()->addMinutes(15),
            'status' => 'active'
        ]);

        // Complete booking
        $bookingResponse = $this->postJson('/api/hotel/book', [
            'lock_token' => 'LOCK_abc123',
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+965123456789'
        ]);

        $bookingResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'booking_id',
                    'confirmation_number',
                    'total_price',
                    'status'
                ]
            ]);

        // Verify booking created
        $this->assertDatabaseHas('bookings', [
            'lock_token' => 'LOCK_abc123',
            'guest_email' => 'john@example.com',
            'status' => 'confirmed'
        ]);

        // Verify lock consumed
        $this->assertDatabaseHas('rate_locks', [
            'lock_token' => 'LOCK_abc123',
            'status' => 'consumed'
        ]);
    }

    /**
     * Test: User searches for multiple rooms
     * Expected: Each room type returned with appropriate rates
     */
    public function test_multi_room_booking_scenario(): void
    {
        Http::fake([
            'api.dotw.com/search' => Http::response(
                $this->loadFixture('multi_room_search_response.xml'),
                200
            ),
        ]);

        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT789012',
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-15',
            'rooms' => [
                ['adults' => 2, 'children' => 2, 'child_ages' => [5, 10]],
                ['adults' => 1, 'children' => 0]
            ]
        ]);

        $response->assertStatus(200);

        $roomBreakdown = $response->json('data.room_breakdown');
        $this->assertCount(2, $roomBreakdown);

        // Verify pricing for multi-room
        $totalPrice = 0;
        foreach ($roomBreakdown as $room) {
            $this->assertGreaterThan(0, $room['price']);
            $totalPrice += $room['price'];
        }

        $this->assertEquals($totalPrice, $response->json('data.total_price'));
    }
}
```

### Complete Error Handling Test Example

**File**: `tests/Feature/HotelBooking/ErrorHandlingTest.php`

```php
<?php

namespace Tests\Feature\HotelBooking;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Invalid search parameters
     * Expected: 422 validation error response
     */
    public function test_search_with_invalid_dates_returns_validation_error(): void
    {
        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-05',
            'check_out' => '2026-04-01', // Checkout before check-in
            'rooms' => [['adults' => 2]]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_out'])
            ->assertJsonFragment([
                'message' => 'Check-out date must be after check-in date'
            ]);
    }

    /**
     * Test: API server error
     * Expected: 500 response with user-friendly message
     */
    public function test_search_handles_api_server_error(): void
    {
        Http::fake([
            'api.dotw.com/search' => Http::response(
                '<?xml version="1.0"?><Error>Internal Server Error</Error>',
                500
            ),
        ]);

        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT123456',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2]]
        ]);

        $response->assertStatus(503)
            ->assertJsonFragment([
                'error' => 'Hotel API is currently unavailable. Please try again later.'
            ]);
    }

    /**
     * Test: Cannot book with expired lock
     * Expected: 422 response with expiration message
     */
    public function test_booking_with_expired_lock_fails(): void
    {
        RateLock::create([
            'lock_token' => 'LOCK_expired',
            'rate_id' => 'RATE_001',
            'expires_at' => now()->subMinutes(5),
            'status' => 'expired'
        ]);

        $response = $this->postJson('/api/hotel/book', [
            'lock_token' => 'LOCK_expired',
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '+965123456789'
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'error' => 'Rate lock has expired'
            ]);
    }

    /**
     * Test: Non-existent hotel
     * Expected: 404 not found response
     */
    public function test_search_for_nonexistent_hotel(): void
    {
        Http::fake([
            'api.dotw.com/search' => Http::response(
                '<?xml version="1.0"?><OTA_HotelSearchRS><Error>Hotel not found</Error></OTA_HotelSearchRS>',
                404
            ),
        ]);

        $response = $this->postJson('/api/hotel/search', [
            'hotel_id' => 'DOT999999999',
            'check_in' => '2026-04-01',
            'check_out' => '2026-04-05',
            'rooms' => [['adults' => 2]]
        ]);

        $response->assertStatus(404)
            ->assertJsonFragment([
                'error' => 'Hotel not found'
            ]);
    }
}
```

---

## Summary: Testing Checklist for Skills

When generating API integration code, ensure tests cover:

### Unit Tests
- [ ] Input validation (dates, emails, phone numbers)
- [ ] Parameter building and transformation
- [ ] Error handling for edge cases
- [ ] Currency conversion accuracy

### Integration Tests
- [ ] Complete search to booking workflows
- [ ] Multi-room and multi-passenger scenarios
- [ ] Deferred booking (search, lock, book later)
- [ ] Rate lock expiration and timeout
- [ ] Database transaction rollback on errors

### Error Scenarios
- [ ] Validation errors (422)
- [ ] API timeouts (408)
- [ ] API server errors (500)
- [ ] Malformed responses
- [ ] Authentication failures (401)
- [ ] Rate unavailable (409)

### Performance
- [ ] Query count limits (< 10 for search)
- [ ] Response time targets (< 500ms for search)
- [ ] N+1 query detection
- [ ] Load test configuration

### Data Fixtures
- [ ] XML response fixtures for each scenario
- [ ] Multi-passenger and multi-room fixtures
- [ ] Error response fixtures
- [ ] Mock data generation helpers

### Organization
- [ ] Fixture files in `tests/Fixtures/`
- [ ] Test classes in `tests/Feature/` and `tests/Unit/`
- [ ] Shared helpers in base `TestCase` class
- [ ] Database factories for test data

---

## Resources

- [Laravel 11 Testing Documentation](https://laravel.com/docs/11.x/testing)
- [Laravel Database Testing](https://laravel.com/docs/12.x/database-testing)
- [Laravel HTTP Tests](https://laravel.com/docs/12.x/http-tests)
- [Testing Validation in Laravel](https://dev.to/mattdaneshvar/testing-laravel-5fdj)
- [Rate Limiting in Laravel with Tests](https://sinnbeck.dev/posts/rate-limiting-routes-in-laravel-with-tests/)
- [Travelling Through Time in Laravel Tests](https://danda.at/blog/travelling-through-time-in-laravel-tests)
- [Laravel Factory Pattern](https://kinsta.com/blog/laravel-model-factories/)
- [Performance Testing Laravel](https://martinjoo.dev/how-to-measure-performance-in-laravel-apps)

