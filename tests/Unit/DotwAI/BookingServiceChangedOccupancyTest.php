<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\BookingService;
use App\Modules\DotwAI\Services\CreditService;
use App\Modules\DotwAI\Services\HotelSearchService;
use Tests\TestCase;

/**
 * CERT-10 regression suite.
 *
 * Verifies BookingService::buildConfirmParams maps per the changed-occupancy spec:
 *   adultsCode       <- validForOccupancy.adults  (what DOTW will book)
 *   actualAdults     <- original search adults    (what customer searched)
 *   children         <- validForOccupancy.childrenAges as int[] (what DOTW books)
 *   actualChildren   <- original search child ages (what customer searched)
 *   extraBed         <- validForOccupancy.extraBed
 *
 * Reference: .claude/skills/dotw-api/references/changed-occupancy.md
 *
 * @covers \App\Modules\DotwAI\Services\BookingService
 */
class BookingServiceChangedOccupancyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function invokeBuildConfirmParams(
        DotwAIBooking $booking,
        array $passengers,
        ?string $email = 'test@example.com',
        array $salutationMap = ['mr' => 147, 'mrs' => 149, 'miss' => 15134, 'ms' => 148],
    ): array {
        $service = new BookingService(
            $this->createMock(CreditService::class),
            $this->createMock(HotelSearchService::class),
        );
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildConfirmParams');
        $method->setAccessible(true);

        return $method->invoke($service, $booking, $passengers, $email, $salutationMap);
    }

    private function makeBooking(array $roomsData, ?array $validForOccupancy): DotwAIBooking
    {
        $booking = new DotwAIBooking;
        $booking->id = 1;
        $booking->hotel_id = '12345';
        $booking->check_in = now()->addDays(30);
        $booking->check_out = now()->addDays(31);
        $booking->room_type_code = 'RT1';
        $booking->rate_basis_id = '1331';
        $booking->allocation_details = 'ALLOC';
        $booking->nationality_code = '66';
        $booking->residence_code = '66';
        $booking->rooms_data = $roomsData;
        $booking->valid_for_occupancy = $validForOccupancy;

        return $booking;
    }

    // -------------------------------------------------------------------------
    // Fixture A: adults reduced — validForOccupancy has fewer adults than searched
    // -------------------------------------------------------------------------

    /**
     * When validForOccupancy.adults < original search adults,
     * adultsCode should reflect the reduced count while actualAdults retains the original.
     */
    public function test_adults_reduced_uses_original_for_actual_adults(): void
    {
        $booking = $this->makeBooking(
            roomsData: [['adults' => 3, 'children_ages' => []]],
            validForOccupancy: ['adults' => 2, 'children' => 0, 'childrenAges' => '', 'extraBed' => 0],
        );

        $params = $this->invokeBuildConfirmParams(
            $booking,
            [['first_name' => 'A', 'last_name' => 'B', 'salutation' => 'Mr']],
        );

        $room = $params['rooms'][0];
        $this->assertSame(2, $room['adultsCode'], 'adultsCode = validForOccupancy.adults');
        $this->assertSame(3, $room['actualAdults'], 'actualAdults = original search adults');
        $this->assertSame([], $room['children'], 'children = empty (vfo says 0)');
        $this->assertSame([], $room['actualChildren'], 'actualChildren = original searched child ages');
        $this->assertSame(0, $room['extraBed'], 'extraBed = 0 when not in vfo');
    }

    // -------------------------------------------------------------------------
    // Fixture B: child→adult upgrade when age exceeds maxChildAge (Example 1)
    // -------------------------------------------------------------------------

    /**
     * Mirror changed-occupancy.md Example 1:
     * Search: 2 adults + children [2, 6, 12]. Hotel maxChildAge=11 → 12yo becomes adult.
     * validForOccupancy: adults=3, children=2, childrenAges="2, 6", extraBed=0.
     */
    public function test_child_upgrade_to_adult_when_age_exceeds_max_child_age(): void
    {
        $booking = $this->makeBooking(
            roomsData: [['adults' => 2, 'children_ages' => [2, 6, 12]]],
            validForOccupancy: ['adults' => 3, 'children' => 2, 'childrenAges' => '2, 6', 'extraBed' => 0],
        );

        $params = $this->invokeBuildConfirmParams(
            $booking,
            [
                ['first_name' => 'A', 'last_name' => 'A', 'salutation' => 'Mr'],
                ['first_name' => 'B', 'last_name' => 'B', 'salutation' => 'Mrs'],
            ],
        );

        $room = $params['rooms'][0];
        $this->assertSame(3, $room['adultsCode'], 'adultsCode = 3 (validForOccupancy.adults, 12yo became adult)');
        $this->assertSame(2, $room['actualAdults'], 'actualAdults = 2 (original search adults)');
        $this->assertSame([2, 6], $room['children'], 'children = booked ages from validForOccupancy (12yo removed)');
        $this->assertSame([2, 6, 12], $room['actualChildren'], 'actualChildren = original searched ages inc 12yo');
        $this->assertSame(0, $room['extraBed'], 'extraBed = 0');
    }

    // -------------------------------------------------------------------------
    // Fixture C: adult→child (booking fewer adults and gaining a child)
    // -------------------------------------------------------------------------

    /**
     * Search: 3 adults, no children.
     * validForOccupancy: adults=2, children=1, childrenAges="10" — one adult re-classified as child.
     */
    public function test_adult_to_child_mapping(): void
    {
        $booking = $this->makeBooking(
            roomsData: [['adults' => 3, 'children_ages' => []]],
            validForOccupancy: ['adults' => 2, 'children' => 1, 'childrenAges' => '10', 'extraBed' => 0],
        );

        $params = $this->invokeBuildConfirmParams(
            $booking,
            [
                ['first_name' => 'A', 'last_name' => 'A', 'salutation' => 'Mr'],
                ['first_name' => 'B', 'last_name' => 'B', 'salutation' => 'Mr'],
                ['first_name' => 'C', 'last_name' => 'C', 'salutation' => 'Mr'],
            ],
        );

        $room = $params['rooms'][0];
        $this->assertSame(2, $room['adultsCode'], 'adultsCode = 2 (from validForOccupancy)');
        $this->assertSame(3, $room['actualAdults'], 'actualAdults = 3 (original)');
        $this->assertSame([10], $room['children'], 'children = [10] from validForOccupancy string');
        $this->assertSame([], $room['actualChildren'], 'actualChildren = [] (no children in original search)');
        $this->assertSame(0, $room['extraBed']);
    }

    // -------------------------------------------------------------------------
    // Fixture D: extra bed for adult (Example 2 Room 0)
    // -------------------------------------------------------------------------

    /**
     * Mirror changed-occupancy.md Example 2 Room 0:
     * Search: 3 adults, no children. validForOccupancy: adults=3, extraBed=1 (adult).
     * adultsCode == actualAdults because count unchanged; extraBed=1.
     */
    public function test_extra_bed_for_adult(): void
    {
        $booking = $this->makeBooking(
            roomsData: [['adults' => 3, 'children_ages' => []]],
            validForOccupancy: ['adults' => 3, 'children' => 0, 'childrenAges' => '', 'extraBed' => 1, 'extraBedOccupant' => 'adult'],
        );

        $params = $this->invokeBuildConfirmParams(
            $booking,
            [
                ['first_name' => 'A', 'last_name' => 'A', 'salutation' => 'Mr'],
                ['first_name' => 'B', 'last_name' => 'B', 'salutation' => 'Mr'],
                ['first_name' => 'C', 'last_name' => 'C', 'salutation' => 'Mr'],
            ],
        );

        $room = $params['rooms'][0];
        $this->assertSame(3, $room['adultsCode'], 'adultsCode = 3');
        $this->assertSame(3, $room['actualAdults'], 'actualAdults = 3 (unchanged from original)');
        $this->assertSame([], $room['children'], 'children = [] (no children booked)');
        $this->assertSame([], $room['actualChildren'], 'actualChildren = [] (no children searched)');
        $this->assertSame(1, $room['extraBed'], 'extraBed = 1 from validForOccupancy');
    }

    // -------------------------------------------------------------------------
    // Fixture E: extra bed for child (Example 2 Room 1)
    // -------------------------------------------------------------------------

    /**
     * Mirror changed-occupancy.md Example 2 Room 1:
     * Search: 2 adults + children [2, 5]. validForOccupancy: adults=2, children=1,
     * childrenAges="2", extraBed=1 (child) — the 5yo gets an extra bed but the
     * validForOccupancy only lists the 2yo in childrenAges.
     */
    public function test_extra_bed_for_child(): void
    {
        $booking = $this->makeBooking(
            roomsData: [['adults' => 2, 'children_ages' => [2, 5]]],
            validForOccupancy: ['adults' => 2, 'children' => 1, 'childrenAges' => '2', 'extraBed' => 1, 'extraBedOccupant' => 'child'],
        );

        $params = $this->invokeBuildConfirmParams(
            $booking,
            [
                ['first_name' => 'A', 'last_name' => 'A', 'salutation' => 'Mr'],
                ['first_name' => 'B', 'last_name' => 'B', 'salutation' => 'Mrs'],
            ],
        );

        $room = $params['rooms'][0];
        $this->assertSame(2, $room['adultsCode'], 'adultsCode = 2 (unchanged)');
        $this->assertSame(2, $room['actualAdults'], 'actualAdults = 2 (unchanged from original)');
        $this->assertSame([2], $room['children'], 'children = [2] (only 2yo in vfo childrenAges)');
        $this->assertSame([2, 5], $room['actualChildren'], 'actualChildren = [2, 5] (original searched)');
        $this->assertSame(1, $room['extraBed'], 'extraBed = 1 from validForOccupancy');
    }
}
