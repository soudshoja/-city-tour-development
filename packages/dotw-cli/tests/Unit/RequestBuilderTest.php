<?php

declare(strict_types=1);

namespace Dotw\Cli\Tests\Unit;

use Dotw\Cli\Dotw\RequestBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RequestBuilder XML body generators.
 * No network connections. Tests XML string output only.
 */
class RequestBuilderTest extends TestCase
{
    // -----------------------------------------------------------------------
    // search()
    // -----------------------------------------------------------------------

    public function test_search_contains_required_elements(): void
    {
        $xml = RequestBuilder::search([
            'fromDate'    => '2026-05-01',
            'toDate'      => '2026-05-02',
            'currency'    => 769,
            'city'        => 364,
            'adults'      => 2,
            'children'    => 0,
            'nationality' => 66,
            'residence'   => 66,
            'rateBasis'   => -1,
        ]);

        $this->assertStringContainsString('<fromDate>2026-05-01</fromDate>', $xml);
        $this->assertStringContainsString('<toDate>2026-05-02</toDate>', $xml);
        $this->assertStringContainsString('<currency>769</currency>', $xml);
        $this->assertStringContainsString('<city>364</city>', $xml);
        $this->assertStringContainsString('<adultsCode>2</adultsCode>', $xml);
        $this->assertStringContainsString('<rateBasis>-1</rateBasis>', $xml);
        $this->assertStringContainsString('<children no="0"/>', $xml);
    }

    public function test_search_with_children_includes_child_element(): void
    {
        $xml = RequestBuilder::search([
            'fromDate'    => '2026-05-01',
            'toDate'      => '2026-05-02',
            'currency'    => 769,
            'city'        => 364,
            'adults'      => 2,
            'children'    => 1,
            'nationality' => 66,
            'residence'   => 66,
            'rateBasis'   => -1,
        ]);

        $this->assertStringContainsString('<children no="1">', $xml);
        $this->assertStringContainsString('<child runno="0">', $xml);
        $this->assertStringNotContainsString('<children no="0"/>', $xml);
    }

    public function test_search_escapes_xss_in_dates(): void
    {
        $xml = RequestBuilder::search([
            'fromDate'    => '<script>evil</script>',
            'toDate'      => '2026-05-02',
            'currency'    => 769,
            'city'        => 364,
            'adults'      => 2,
            'children'    => 0,
            'nationality' => 66,
            'residence'   => 66,
            'rateBasis'   => -1,
        ]);

        $this->assertStringNotContainsString('<script>', $xml);
        $this->assertStringContainsString('&lt;script&gt;', $xml);
    }

    // -----------------------------------------------------------------------
    // getRoomsBrowse()
    // -----------------------------------------------------------------------

    public function test_get_rooms_browse_contains_product_id(): void
    {
        $xml = RequestBuilder::getRoomsBrowse([
            'hotelId'     => '1013275',
            'fromDate'    => '2026-05-01',
            'toDate'      => '2026-05-02',
            'currency'    => 769,
            'adults'      => 2,
            'children'    => 0,
            'nationality' => 66,
            'residence'   => 66,
            'rateBasis'   => -1,
        ]);

        $this->assertStringContainsString('<productId>1013275</productId>', $xml);
        $this->assertStringNotContainsString('<roomTypeSelected>', $xml, 'Browse must not include roomTypeSelected');
    }

    public function test_get_rooms_browse_includes_return_fields(): void
    {
        $xml = RequestBuilder::getRoomsBrowse([
            'hotelId'     => '1013275',
            'fromDate'    => '2026-05-01',
            'toDate'      => '2026-05-02',
            'currency'    => 769,
            'adults'      => 2,
            'children'    => 0,
            'nationality' => 66,
            'residence'   => 66,
            'rateBasis'   => -1,
        ]);

        $this->assertStringContainsString('<roomField>cancellation</roomField>', $xml);
        $this->assertStringContainsString('<roomField>name</roomField>', $xml);
    }

    // -----------------------------------------------------------------------
    // getRoomsBlock()
    // -----------------------------------------------------------------------

    public function test_get_rooms_block_contains_allocation_details(): void
    {
        $xml = RequestBuilder::getRoomsBlock([
            'hotelId'            => '1013275',
            'fromDate'           => '2026-05-01',
            'toDate'             => '2026-05-02',
            'currency'           => 769,
            'adults'             => 2,
            'children'           => 0,
            'nationality'        => 66,
            'residence'          => 66,
            'rateBasis'          => -1,
            'roomTypeCode'       => '483146225',
            'selectedRateBasis'  => 1331,
            'allocationDetails'  => 'ABC123',
        ]);

        $this->assertStringContainsString('<roomTypeSelected>', $xml);
        $this->assertStringContainsString('<allocationDetails>ABC123</allocationDetails>', $xml);
        $this->assertStringContainsString('<selectedRateBasis>1331</selectedRateBasis>', $xml);
    }

    public function test_get_rooms_block_escapes_allocation_details(): void
    {
        $xml = RequestBuilder::getRoomsBlock([
            'hotelId'           => '1013275',
            'fromDate'          => '2026-05-01',
            'toDate'            => '2026-05-02',
            'currency'          => 769,
            'adults'            => 2,
            'children'          => 0,
            'nationality'       => 66,
            'residence'         => 66,
            'rateBasis'         => -1,
            'roomTypeCode'      => '483146225',
            'selectedRateBasis' => 1331,
            'allocationDetails' => '<evil>&content</evil>',
        ]);

        $this->assertStringNotContainsString('<evil>', $xml);
    }

    // -----------------------------------------------------------------------
    // cancelBooking()
    // -----------------------------------------------------------------------

    public function test_cancel_booking_preview_has_confirm_no(): void
    {
        $xml = RequestBuilder::cancelBooking('938745233', confirm: false);

        $this->assertStringContainsString('<bookingCode>938745233</bookingCode>', $xml);
        $this->assertStringContainsString('<confirm>no</confirm>', $xml);
        $this->assertStringNotContainsString('testPricesAndAllocation', $xml);
    }

    public function test_cancel_booking_execute_has_confirm_yes_and_penalty(): void
    {
        $xml = RequestBuilder::cancelBooking('938745233', confirm: true, penaltyApplied: 13.28);

        $this->assertStringContainsString('<confirm>yes</confirm>', $xml);
        $this->assertStringContainsString('<penaltyApplied>13.28</penaltyApplied>', $xml);
        $this->assertStringContainsString('testPricesAndAllocation', $xml);
    }

    public function test_cancel_booking_execute_without_penalty_omits_test_prices(): void
    {
        $xml = RequestBuilder::cancelBooking('938745233', confirm: true, penaltyApplied: null);

        $this->assertStringContainsString('<confirm>yes</confirm>', $xml);
        $this->assertStringNotContainsString('testPricesAndAllocation', $xml);
    }

    // -----------------------------------------------------------------------
    // confirmBooking()
    // -----------------------------------------------------------------------

    public function test_confirm_booking_contains_passenger_details(): void
    {
        $xml = RequestBuilder::confirmBooking([
            'fromDate'          => '2026-05-01',
            'toDate'            => '2026-05-02',
            'currency'          => 769,
            'hotelId'           => '1013275',
            'customerEmail'     => 'test@example.com',
            'customerReference' => 'PB-ABC123',
            'roomTypeCode'      => '483146225',
            'selectedRateBasis' => 1331,
            'allocationDetails' => 'ALLOC123',
            'adults'            => 2,
            'nationality'       => 66,
            'residence'         => 66,
            'passengers'        => [
                ['salutation' => 147, 'firstName' => 'Soud', 'lastName' => 'Shoja', 'leading' => true],
            ],
        ]);

        $this->assertStringContainsString('<firstName>Soud</firstName>', $xml);
        $this->assertStringContainsString('<lastName>Shoja</lastName>', $xml);
        $this->assertStringContainsString('leading="yes"', $xml);
        $this->assertStringContainsString('<allocationDetails>ALLOC123</allocationDetails>', $xml);
        $this->assertStringContainsString('<bookingDetails>', $xml, 'XML must contain bookingDetails root element');
    }

    public function test_confirm_booking_second_passenger_has_leading_no(): void
    {
        $xml = RequestBuilder::confirmBooking([
            'fromDate'          => '2026-05-01',
            'toDate'            => '2026-05-02',
            'currency'          => 769,
            'hotelId'           => '1013275',
            'customerEmail'     => 'test@example.com',
            'customerReference' => 'PB-XYZ',
            'roomTypeCode'      => '483146225',
            'selectedRateBasis' => 1331,
            'allocationDetails' => 'ALLOC456',
            'adults'            => 2,
            'nationality'       => 66,
            'residence'         => 66,
            'passengers'        => [
                ['salutation' => 147, 'firstName' => 'Alice', 'lastName' => 'Smith', 'leading' => true],
                ['salutation' => 147, 'firstName' => 'Bob',   'lastName' => 'Jones', 'leading' => false],
            ],
        ]);

        $this->assertStringContainsString('leading="yes"', $xml);
        $this->assertStringContainsString('leading="no"', $xml);
        $this->assertStringContainsString('<firstName>Alice</firstName>', $xml);
        $this->assertStringContainsString('<firstName>Bob</firstName>', $xml);
    }

    public function test_confirm_booking_escapes_email(): void
    {
        $xml = RequestBuilder::confirmBooking([
            'fromDate'          => '2026-05-01',
            'toDate'            => '2026-05-02',
            'currency'          => 769,
            'hotelId'           => '1013275',
            'customerEmail'     => '<evil>@example.com',
            'customerReference' => 'PB-SEC',
            'roomTypeCode'      => '483146225',
            'selectedRateBasis' => 1331,
            'allocationDetails' => 'ALLOC789',
            'adults'            => 1,
            'nationality'       => 66,
            'residence'         => 66,
            'passengers'        => [
                ['salutation' => 147, 'firstName' => 'Test', 'lastName' => 'User', 'leading' => true],
            ],
        ]);

        $this->assertStringNotContainsString('<evil>', $xml);
        $this->assertStringContainsString('&lt;evil&gt;', $xml);
    }
}
