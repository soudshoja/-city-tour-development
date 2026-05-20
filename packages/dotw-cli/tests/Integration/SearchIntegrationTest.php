<?php

declare(strict_types=1);

namespace Dotw\Cli\Tests\Integration;

use Dotw\Cli\Dotw\Client;
use Dotw\Cli\Dotw\RequestBuilder;
use Dotw\Cli\Dotw\ResponseParser;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: DOTW sandbox search.
 * Skips automatically when DOTW_CLI_USERNAME env var is not set.
 */
class SearchIntegrationTest extends TestCase
{
    private array $credentials;

    protected function setUp(): void
    {
        $username = getenv('DOTW_CLI_USERNAME');
        if (!$username) {
            $this->markTestSkipped('DOTW_CLI_USERNAME not set — skipping sandbox integration test');
        }

        $this->credentials = [
            'username'     => $username,
            'password'     => getenv('DOTW_CLI_PASSWORD')     ?: '',
            'company_code' => getenv('DOTW_CLI_COMPANY_CODE') ?: '',
            'source'       => 1,
            'product'      => 'hotel',
            'endpoint'     => getenv('DOTW_CLI_ENDPOINT') ?: 'https://xml.dotwconnect.com/2018-09-01/Dotw.asmx',
            'timeout'      => 30,
        ];
    }

    public function test_search_returns_hotels(): void
    {
        $client = new Client($this->credentials);
        $params = [
            'fromDate'    => date('Y-m-d', strtotime('+30 days')),
            'toDate'      => date('Y-m-d', strtotime('+31 days')),
            'currency'    => 769,
            'city'        => 364,
            'adults'      => 2,
            'children'    => 0,
            'nationality' => 66,
            'residence'   => 66,
            'rateBasis'   => -1,
        ];

        $xml    = $client->send('searchhotels', RequestBuilder::search($params));
        $client->assertSuccessful($xml);
        $hotels = ResponseParser::hotels($xml);

        $this->assertNotEmpty($hotels, 'Expected at least one hotel from sandbox search');
        $this->assertArrayHasKey('hotel_id',  $hotels[0]);
        $this->assertArrayHasKey('hotel_name', $hotels[0]);
        $this->assertArrayHasKey('min_fare',   $hotels[0]);
        $this->assertGreaterThan(0, $hotels[0]['min_fare']);
    }
}
