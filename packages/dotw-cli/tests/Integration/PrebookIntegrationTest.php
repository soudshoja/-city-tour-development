<?php

declare(strict_types=1);

namespace Dotw\Cli\Tests\Integration;

use Dotw\Cli\Dotw\Client;
use Dotw\Cli\Dotw\RequestBuilder;
use Dotw\Cli\Dotw\ResponseParser;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: browse rooms then block (dual getRooms pattern).
 * Skips automatically when DOTW_CLI_USERNAME not set.
 */
class PrebookIntegrationTest extends TestCase
{
    private array $credentials;
    private string $fromDate;
    private string $toDate;
    private string $hotelId;

    protected function setUp(): void
    {
        if (!getenv('DOTW_CLI_USERNAME')) {
            $this->markTestSkipped('DOTW_CLI_USERNAME not set — skipping sandbox integration test');
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
        $this->fromDate = date('Y-m-d', strtotime('+30 days'));
        $this->toDate   = date('Y-m-d', strtotime('+31 days'));
        $this->hotelId  = getenv('DOTW_CLI_TEST_HOTEL_ID') ?: '1013275';
    }

    public function test_browse_then_block_returns_allocation_details(): void
    {
        $client = new Client($this->credentials);

        $browseParams = [
            'hotelId'     => $this->hotelId,
            'fromDate'    => $this->fromDate,
            'toDate'      => $this->toDate,
            'currency'    => 769,
            'adults'      => 2,
            'children'    => 0,
            'nationality' => 66,
            'residence'   => 66,
            'rateBasis'   => -1,
        ];

        // Step 1: Browse
        $browseXml = $client->send('getrooms', RequestBuilder::getRoomsBrowse($browseParams));
        $client->assertSuccessful($browseXml);
        $rooms = ResponseParser::rooms($browseXml);

        $this->assertNotEmpty($rooms, 'Browse returned no rooms for hotel ' . $this->hotelId);

        $selected = $rooms[0];

        // Step 2: Block
        $blockParams = array_merge($browseParams, [
            'roomTypeCode'      => $selected['room_type_code'],
            'selectedRateBasis' => $selected['rate_basis_id'],
            'allocationDetails' => $selected['allocation_details'],
        ]);

        $blockXml     = $client->send('getrooms', RequestBuilder::getRoomsBlock($blockParams));
        $client->assertSuccessful($blockXml);
        $blockedRooms = ResponseParser::rooms($blockXml);

        $this->assertNotEmpty($blockedRooms);
        $this->assertNotEmpty($blockedRooms[0]['allocation_details'], 'Block must return allocationDetails');
    }
}
