<?php

declare(strict_types=1);

namespace Tests\Feature\DotwAI;

use Tests\TestCase;

/**
 * Feature tests for GET /api/dotwai/health endpoint.
 *
 * The health endpoint requires no authentication and no middleware.
 *
 * @covers \App\Modules\DotwAI\Routes\api.php health route
 * @see FOUND-01
 */
class HealthEndpointTest extends TestCase
{
    protected bool $skipPermissionSeeder = true;

    public function test_health_returns_ok(): void
    {
        $response = $this->getJson('/api/dotwai/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'module' => 'dotwai',
            ]);

        $json = $response->json();
        $this->assertArrayHasKey('version', $json);
        $this->assertArrayHasKey('timestamp', $json);
    }

    public function test_health_requires_no_authentication(): void
    {
        // No phone number, no headers, no middleware -- should work without any auth
        $response = $this->getJson('/api/dotwai/health');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }
}
