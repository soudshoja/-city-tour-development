<?php

declare(strict_types=1);

namespace Dotw\Cli\Tests\Integration;

use Dotw\Cli\Dotw\Client;
use Dotw\Cli\Dotw\RequestBuilder;
use Dotw\Cli\Dotw\ResponseParser;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end sandbox test: search → prebook (browse+block) → confirm (B2B) → cancel:preview
 *
 * This test makes real DOTW API calls in sequence.
 * Requires DOTW_CLI_USERNAME env var. Skips otherwise.
 * DOTW_CLI_CANCEL_AFTER_CONFIRM=1 will also test cancel:execute (destructive — creates and cancels a real sandbox booking).
 */
class FullBookingFlowTest extends TestCase
{
    private array $credentials;
    private string $fromDate;
    private string $toDate;

    protected function setUp(): void
    {
        if (!getenv('DOTW_CLI_USERNAME')) {
            $this->markTestSkipped('DOTW_CLI_USERNAME not set — skipping full flow integration test');
        }

        $this->credentials = [
            'username'     => getenv('DOTW_CLI_USERNAME'),
            'password'     => getenv('DOTW_CLI_PASSWORD'),
            'company_code' => getenv('DOTW_CLI_COMPANY_CODE'),
            'source'       => 1,
            'product'      => 'hotel',
            'endpoint'     => getenv('DOTW_CLI_ENDPOINT') ?: 'https://xml.dotwconnect.com/2018-09-01/Dotw.asmx',
            'timeout'      => 30,
        ];
        // +30 days ensures the booking is well within the cancellable window
        $this->fromDate = date('Y-m-d', strtotime('+30 days'));
        $this->toDate   = date('Y-m-d', strtotime('+31 days'));
    }

    public function test_search_prebook_confirm_b2b_cancel_preview(): void
    {
        $client   = new Client($this->credentials);
        $hotelId  = getenv('DOTW_CLI_TEST_HOTEL_ID') ?: '1013275';
        $currency = 769;

        // 1. Search
        $searchXml = $client->send('searchhotels', RequestBuilder::search([
            'fromDate'    => $this->fromDate,
            'toDate'      => $this->toDate,
            'currency'    => $currency,
            'city'        => 364,
            'adults'      => 2,
            'children'    => 0,
            'nationality' => 66,
            'residence'   => 66,
            'rateBasis'   => -1,
        ]));
        $client->assertSuccessful($searchXml);
        $hotels = ResponseParser::hotels($searchXml);
        $this->assertNotEmpty($hotels, 'Search returned no hotels');

        // 2. Browse target hotel
        $browseParams = [
            'hotelId'     => $hotelId,
            'fromDate'    => $this->fromDate,
            'toDate'      => $this->toDate,
            'currency'    => $currency,
            'adults'      => 2,
            'children'    => 0,
            'nationality' => 66,
            'residence'   => 66,
            'rateBasis'   => -1,
        ];
        $browseXml = $client->send('getrooms', RequestBuilder::getRoomsBrowse($browseParams));
        $client->assertSuccessful($browseXml);
        $rooms = ResponseParser::rooms($browseXml);
        $this->assertNotEmpty($rooms, "Hotel {$hotelId} returned no rooms");

        $selected = $rooms[0];

        // 3. Block
        $blockXml = $client->send('getrooms', RequestBuilder::getRoomsBlock(array_merge($browseParams, [
            'roomTypeCode'      => $selected['room_type_code'],
            'selectedRateBasis' => $selected['rate_basis_id'],
            'allocationDetails' => $selected['allocation_details'],
        ])));
        $client->assertSuccessful($blockXml);
        $blockedRooms = ResponseParser::rooms($blockXml);
        $this->assertNotEmpty($blockedRooms[0]['allocation_details'], 'Block must return allocationDetails');

        // 4. Confirm (B2B)
        $confirmXml = $client->send('confirmbooking', RequestBuilder::confirmBooking([
            'fromDate'          => $this->fromDate,
            'toDate'            => $this->toDate,
            'currency'          => $currency,
            'hotelId'           => $hotelId,
            'customerEmail'     => 'test@citycommerce.group',
            'customerReference' => 'CLI-TEST-' . uniqid(),
            'roomTypeCode'      => $blockedRooms[0]['room_type_code'],
            'selectedRateBasis' => $selected['rate_basis_id'],
            'allocationDetails' => $blockedRooms[0]['allocation_details'],
            'adults'            => 2,
            'nationality'       => 66,
            'residence'         => 66,
            'passengers'        => [
                ['salutation' => 147, 'firstName' => 'CLI', 'lastName' => 'Test',  'leading' => true],
                ['salutation' => 147, 'firstName' => 'Guest', 'lastName' => 'Two', 'leading' => false],
            ],
        ]));
        $client->assertSuccessful($confirmXml);
        $bookingRef = (string) ($confirmXml->services->service->references->reference ?? '');
        $this->assertNotEmpty($bookingRef, 'Confirm did not return a booking reference');

        // 5. Cancel preview (non-destructive — confirm=no)
        $cancelPreviewXml = $client->send('cancelbooking', RequestBuilder::cancelBooking($bookingRef, confirm: false));
        $client->assertSuccessful($cancelPreviewXml);
        $preview = ResponseParser::cancelPreview($cancelPreviewXml);
        $this->assertNotEmpty($preview);
        $this->assertArrayHasKey('charge', $preview[0]);

        // 6. Cancel execute (ONLY if env flag is explicitly set — destructive)
        if (getenv('DOTW_CLI_CANCEL_AFTER_CONFIRM') === '1') {
            $penalty     = (float) $preview[0]['charge'];
            $cancelExXml = $client->send(
                'cancelbooking',
                RequestBuilder::cancelBooking($bookingRef, confirm: true, penaltyApplied: $penalty)
            );
            $client->assertSuccessful($cancelExXml);
        }
    }
}
