<?php

namespace Tests\Unit\Services;

use App\Services\DotwService;
use Tests\TestCase;

/**
 * CERT-12 regression suite — verifies DotwService::buildRoomsXml correctly preserves
 * user-picked rate-basis ids (including 0 = Room Only) and only defaults to -1 when
 * no pick has been made. Also includes a Phase 24 CERT-03 regression test confirming
 * legacy behaviour (no pick + null/empty/0 → -1) is preserved.
 *
 * @covers \App\Services\DotwService::buildRoomsXml
 */
class DotwServiceRateBasisTest extends TestCase
{
    private function invokeBuildRoomsXml(array $rooms): string
    {
        // DotwService constructor needs a company id; in tests we just need the method body.
        // Use a stub — the method only reads from $rooms, not from instance state.
        $service = $this->getMockBuilder(DotwService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $reflection = new \ReflectionClass(DotwService::class);
        $method = $reflection->getMethod('buildRoomsXml');
        $method->setAccessible(true);

        return $method->invoke($service, $rooms);
    }

    // ── Fixture 1: no user pick, rateBasis missing entirely → -1 ──
    public function test_no_pick_no_rate_basis_defaults_to_minus_one(): void
    {
        $xml = $this->invokeBuildRoomsXml([
            [
                'adultsCode' => 2,
                'children' => [],
                'passengerNationality' => '66',
                'passengerCountryOfResidence' => '66',
            ],
        ]);

        $this->assertStringContainsString('<rateBasis>-1</rateBasis>', $xml);
    }

    // ── Fixture 2: user picked Room Only (rateBasis=0) — MUST preserve 0 ──
    public function test_user_picked_room_only_preserves_zero(): void
    {
        $xml = $this->invokeBuildRoomsXml([
            [
                'adultsCode' => 2,
                'children' => [],
                'rateBasis' => 0,
                'userPickedMeal' => true,
                'passengerNationality' => '66',
                'passengerCountryOfResidence' => '66',
            ],
        ]);

        $this->assertStringContainsString('<rateBasis>0</rateBasis>', $xml);
        $this->assertStringNotContainsString('<rateBasis>-1</rateBasis>', $xml);
    }

    // ── Fixture 3: user picked Breakfast (rateBasis=1331) — preserve verbatim ──
    public function test_user_picked_breakfast_preserves_1331(): void
    {
        $xml = $this->invokeBuildRoomsXml([
            [
                'adultsCode' => 2,
                'children' => [],
                'rateBasis' => 1331,
                'userPickedMeal' => true,
                'passengerNationality' => '66',
                'passengerCountryOfResidence' => '66',
            ],
        ]);

        $this->assertStringContainsString('<rateBasis>1331</rateBasis>', $xml);
    }

    // ── Fixture 4: user picked Half Board (some other meal code, e.g. 1340) — preserve verbatim ──
    public function test_user_picked_half_board_preserves_id(): void
    {
        $xml = $this->invokeBuildRoomsXml([
            [
                'adultsCode' => 2,
                'children' => [],
                'rateBasis' => 1340,
                'userPickedMeal' => true,
                'passengerNationality' => '66',
                'passengerCountryOfResidence' => '66',
            ],
        ]);

        $this->assertStringContainsString('<rateBasis>1340</rateBasis>', $xml);
    }

    // ── Fixture 5 (regression): Phase 24 CERT-03 — caller passes 0 WITHOUT userPickedMeal → still -1 ──
    public function test_phase24_cert03_regression_zero_without_pick_collapses_to_minus_one(): void
    {
        $xml = $this->invokeBuildRoomsXml([
            [
                'adultsCode' => 2,
                'children' => [],
                'rateBasis' => 0,
                // userPickedMeal NOT set — legacy caller / data corruption path
                'passengerNationality' => '66',
                'passengerCountryOfResidence' => '66',
            ],
        ]);

        // Legacy guard still active: 0 without explicit pick collapses to -1.
        $this->assertStringContainsString('<rateBasis>-1</rateBasis>', $xml);
        $this->assertStringNotContainsString('<rateBasis>0</rateBasis>', $xml);
    }
}
