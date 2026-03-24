<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Modules\DotwAI\Services\MessageBuilderService;
use Tests\TestCase;

/**
 * Unit tests for MessageBuilderService.
 *
 * Pure unit tests -- no database needed. Verifies bilingual
 * Arabic/English WhatsApp message formatting.
 *
 * @covers \App\Modules\DotwAI\Services\MessageBuilderService
 * @see EVNT-02
 */
class MessageBuilderServiceTest extends TestCase
{
    protected bool $skipPermissionSeeder = true;

    /**
     * Sample hotel search results used across multiple tests.
     */
    private function sampleHotels(): array
    {
        return [
            [
                'option_number' => 1,
                'name' => 'Hilton Dubai Creek',
                'star_rating' => 5,
                'city' => 'Dubai',
                'cheapest_price' => 45.000,
                'meal_type' => 'Breakfast',
                'is_refundable' => true,
                'currency' => 'KWD',
            ],
            [
                'option_number' => 2,
                'name' => 'JW Marriott Marquis',
                'star_rating' => 5,
                'city' => 'Dubai',
                'cheapest_price' => 60.000,
                'meal_type' => 'Room Only',
                'is_refundable' => false,
                'currency' => 'KWD',
            ],
            [
                'option_number' => 3,
                'name' => 'Budget Inn',
                'star_rating' => 3,
                'city' => 'Dubai',
                'cheapest_price' => 20.000,
                'meal_type' => 'Room Only',
                'is_refundable' => true,
                'currency' => 'KWD',
            ],
        ];
    }

    public function test_format_search_results_includes_hotel_numbers(): void
    {
        $output = MessageBuilderService::formatSearchResults($this->sampleHotels(), 'KWD');

        $this->assertStringContainsString('1.', $output);
        $this->assertStringContainsString('2.', $output);
        $this->assertStringContainsString('3.', $output);
    }

    public function test_format_search_results_includes_stars_and_price(): void
    {
        $output = MessageBuilderService::formatSearchResults($this->sampleHotels(), 'KWD');

        // Stars are represented by '*' repeated star_rating times
        $this->assertStringContainsString('*****', $output);
        $this->assertStringContainsString('***', $output);

        // Prices should be formatted with currency
        $this->assertStringContainsString('KWD', $output);
        $this->assertStringContainsString('45.000', $output);
    }

    public function test_format_search_results_bilingual(): void
    {
        $output = MessageBuilderService::formatSearchResults($this->sampleHotels(), 'KWD');

        // Arabic text
        $this->assertStringContainsString('نتائج البحث', $output);
        $this->assertStringContainsString('قابل للاسترداد', $output);

        // English text
        $this->assertStringContainsString('Search Results', $output);
        $this->assertStringContainsString('Refundable', $output);
    }

    public function test_format_hotel_details_includes_rooms(): void
    {
        $hotel = ['name' => 'Hilton Dubai Creek', 'star_rating' => 5, 'city' => 'Dubai'];
        $rooms = [
            [
                'room_name' => 'Deluxe Room',
                'meal_type' => 'Breakfast',
                'display_price' => 45.000,
                'is_refundable' => true,
                'cancellation_rules' => [],
                'specials' => [],
            ],
            [
                'room_name' => 'Standard Room',
                'meal_type' => 'Room Only',
                'display_price' => 30.000,
                'is_refundable' => false,
                'cancellation_rules' => [],
                'specials' => [],
            ],
        ];

        $output = MessageBuilderService::formatHotelDetails($hotel, $rooms, 'KWD');

        // Both rooms should be listed
        $this->assertStringContainsString('Deluxe Room', $output);
        $this->assertStringContainsString('Standard Room', $output);
        $this->assertStringContainsString('1.', $output);
        $this->assertStringContainsString('2.', $output);
    }

    public function test_format_hotel_details_shows_cancellation_info(): void
    {
        $hotel = ['name' => 'Test Hotel', 'star_rating' => 4];
        $rooms = [
            [
                'room_name' => 'Deluxe Room',
                'meal_type' => 'Breakfast',
                'display_price' => 45.000,
                'is_refundable' => true,
                'cancellation_rules' => [
                    ['fromDate' => '2026-04-01', 'toDate' => '2026-04-05', 'charge' => 50.0, 'cancelRestricted' => false],
                ],
                'specials' => [],
            ],
        ];

        $output = MessageBuilderService::formatHotelDetails($hotel, $rooms, 'KWD');

        $this->assertStringContainsString('2026-04-01', $output);
        $this->assertStringContainsString('Cancel by', $output);
    }

    public function test_format_error_returns_bilingual_message(): void
    {
        $errorCodes = [
            'CITY_NOT_FOUND',
            'NO_RESULTS',
            'DOTW_API_ERROR',
            'PHONE_NOT_FOUND',
            'VALIDATION_ERROR',
        ];

        foreach ($errorCodes as $code) {
            $output = MessageBuilderService::formatError($code);

            // Each error should have both Arabic and English content (pipe separator or newline)
            $this->assertNotEmpty($output, "Error code {$code} returned empty message");
            $this->assertTrue(
                str_contains($output, '|') || mb_strlen($output) > 20,
                "Error code {$code} should be bilingual"
            );
        }
    }

    public function test_whatsapp_options_after_search(): void
    {
        $options = MessageBuilderService::buildWhatsAppOptions('search');

        $this->assertIsArray($options);
        $this->assertNotEmpty($options);

        // Should contain a "View details" related option
        $optionsText = implode(' ', $options);
        $this->assertTrue(
            str_contains(strtolower($optionsText), 'detail') || str_contains(strtolower($optionsText), 'view'),
            'Search options should include a "view details" action'
        );
    }

    public function test_whatsapp_options_after_details(): void
    {
        $options = MessageBuilderService::buildWhatsAppOptions('details');

        $this->assertIsArray($options);
        $this->assertNotEmpty($options);

        // Should contain a "Book" related option
        $optionsText = implode(' ', $options);
        $this->assertTrue(
            str_contains(strtolower($optionsText), 'book'),
            'Details options should include a "book" action'
        );
    }
}
