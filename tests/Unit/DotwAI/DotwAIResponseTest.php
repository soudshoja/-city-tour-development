<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Modules\DotwAI\Services\DotwAIResponse;
use Tests\TestCase;

/**
 * Unit tests for DotwAIResponse envelope helper.
 *
 * Verifies the response structure contract: success responses include
 * whatsappMessage + whatsappOptions, error responses include
 * suggestedAction + whatsappMessage.
 *
 * @covers \App\Modules\DotwAI\Services\DotwAIResponse
 * @see EVNT-02
 * @see EVNT-03
 */
class DotwAIResponseTest extends TestCase
{
    protected bool $skipPermissionSeeder = true;

    public function test_success_response_has_required_fields(): void
    {
        $response = DotwAIResponse::success(
            ['hotels' => []],
            'Test whatsapp message',
            ['Option 1', 'Option 2']
        );

        $json = $response->getData(true);

        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('whatsappMessage', $json);
        $this->assertArrayHasKey('whatsappOptions', $json);
        $this->assertEquals('Test whatsapp message', $json['whatsappMessage']);
        $this->assertEquals(['Option 1', 'Option 2'], $json['whatsappOptions']);
    }

    public function test_success_response_status_200(): void
    {
        $response = DotwAIResponse::success([], 'Test');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_error_response_has_required_fields(): void
    {
        $response = DotwAIResponse::error(
            DotwAIResponse::PHONE_NOT_FOUND,
            'Phone not found in database'
        );

        $json = $response->getData(true);

        $this->assertFalse($json['success']);
        $this->assertArrayHasKey('error', $json);
        $this->assertEquals('PHONE_NOT_FOUND', $json['error']['code']);
        $this->assertEquals('Phone not found in database', $json['error']['message']);
        $this->assertArrayHasKey('suggestedAction', $json['error']);
        $this->assertArrayHasKey('whatsappMessage', $json);
        $this->assertArrayHasKey('whatsappOptions', $json);
        $this->assertNotEmpty($json['error']['suggestedAction']);
        $this->assertNotEmpty($json['whatsappMessage']);
    }

    public function test_error_response_not_found_returns_specified_status(): void
    {
        $response = DotwAIResponse::error(
            DotwAIResponse::HOTEL_NOT_FOUND,
            'Hotel not found',
            null,
            null,
            404
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_error_response_default_returns_422(): void
    {
        $response = DotwAIResponse::error(
            DotwAIResponse::VALIDATION_ERROR,
            'Invalid input'
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_error_response_always_has_suggested_action(): void
    {
        // Test with explicit null suggestedAction -- should use default from error code
        $response = DotwAIResponse::error(
            DotwAIResponse::DOTW_API_ERROR,
            'API timeout',
            null,
            null
        );

        $json = $response->getData(true);

        $this->assertArrayHasKey('suggestedAction', $json['error']);
        $this->assertNotEmpty($json['error']['suggestedAction']);

        // Test with unknown error code -- should still have suggestedAction
        $response2 = DotwAIResponse::error(
            'UNKNOWN_CODE',
            'Some error'
        );

        $json2 = $response2->getData(true);
        $this->assertArrayHasKey('suggestedAction', $json2['error']);
        $this->assertNotEmpty($json2['error']['suggestedAction']);
    }
}
