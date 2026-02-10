<?php

namespace Tests\Feature\ErrorLogging;

use Tests\TestCase;
use App\Models\DocumentProcessingLog;
use App\Models\Company;
use App\Services\ErrorAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class AlertThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected ErrorAlertService $service;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ErrorAlertService();
        $this->company = Company::factory()->create();

        // Clear cache before each test
        Cache::flush();

        // Configure alerting thresholds
        Config::set('webhook.alerting.enabled', true);
        Config::set('webhook.alerting.error_rate_threshold', 10); // 10%
        Config::set('webhook.alerting.consecutive_failures', 5);
        Config::set('webhook.alerting.alert_cooldown_minutes', 30);
    }

    /**
     * Test 1: Below threshold - no alert
     * 5% error rate → no alert triggered
     */
    public function test_below_threshold_no_alert(): void
    {
        // Arrange - Create 100 documents: 95 completed, 5 failed (5% failure rate)
        // This is below the 10% threshold
        DocumentProcessingLog::factory()->count(95)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'created_at' => now()->subMinutes(30),
        ]);

        DocumentProcessingLog::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(30),
        ]);

        // Ensure Log::log is NOT called (no alert should be triggered)
        Log::shouldNotReceive('log');

        // Act
        $result = $this->service->checkThresholds();

        // Assert
        $this->assertTrue($result['checked']);
        $this->assertEquals(0, $result['alert_count']);
        $this->assertEmpty($result['alerts']);

        // Verify no cooldown was set
        $this->assertFalse(Cache::has('error_alert:error_rate_threshold'));
    }

    /**
     * Test 2: Above threshold - alert fires
     * 15% error rate → alert logged
     */
    public function test_above_threshold_alert_fires(): void
    {
        // Arrange - Create 100 documents: 85 completed, 15 failed (15% failure rate)
        // This is above the 10% threshold
        DocumentProcessingLog::factory()->count(85)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'created_at' => now()->subMinutes(30),
        ]);

        DocumentProcessingLog::factory()->count(15)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(30),
        ]);

        Log::shouldReceive('log')
            ->once()
            ->with('warning', \Mockery::type('string'), \Mockery::type('array'));

        // Act
        $result = $this->service->checkThresholds();

        // Assert
        $this->assertTrue($result['checked']);
        $this->assertGreaterThan(0, $result['alert_count']);
        $this->assertCount(1, $result['alerts']);

        $alert = $result['alerts'][0];
        $this->assertEquals('error_rate_exceeded', $alert['type']);
        $this->assertEquals('warning', $alert['severity']);
        $this->assertEquals(10, $alert['threshold']);
        $this->assertEquals(15.0, $alert['current_rate']);
        $this->assertEquals(100, $alert['total_processed']);
        $this->assertEquals(15, $alert['total_failed']);

        // Verify cooldown was set
        $this->assertTrue(Cache::has('error_alert:error_rate_threshold'));
    }

    /**
     * Test 3: Consecutive failures trigger
     * 5 consecutive failures → alert
     */
    public function test_consecutive_failures_trigger(): void
    {
        // Arrange - Create exactly 5 documents, all failed
        DocumentProcessingLog::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(10),
        ]);

        Log::shouldReceive('log')
            ->once()
            ->with('critical', \Mockery::type('string'), \Mockery::type('array'));

        // Act
        $result = $this->service->checkThresholds();

        // Assert
        $this->assertTrue($result['checked']);
        $this->assertGreaterThan(0, $result['alert_count']);

        // Find the consecutive failures alert
        $consecutiveAlert = collect($result['alerts'])->firstWhere('type', 'consecutive_failures');
        $this->assertNotNull($consecutiveAlert);

        $this->assertEquals('consecutive_failures', $consecutiveAlert['type']);
        $this->assertEquals('critical', $consecutiveAlert['severity']);
        $this->assertEquals(5, $consecutiveAlert['threshold']);
        $this->assertEquals(5, $consecutiveAlert['consecutive_count']);
        $this->assertContains('ERR_TIMEOUT', $consecutiveAlert['error_codes']);

        // Verify cooldown was set
        $this->assertTrue(Cache::has('error_alert:consecutive_failures'));
    }

    /**
     * Test 4: Cooldown respected
     * Alert sent, then within 30min another threshold hit → no duplicate alert
     */
    public function test_cooldown_respected(): void
    {
        // Arrange - Create initial high error rate
        DocumentProcessingLog::factory()->count(85)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'created_at' => now()->subMinutes(30),
        ]);

        DocumentProcessingLog::factory()->count(15)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(30),
        ]);

        // First call should trigger alert
        Log::shouldReceive('log')->once();

        $result1 = $this->service->checkThresholds();

        $this->assertTrue($result1['checked']);
        $this->assertGreaterThan(0, $result1['alert_count']);

        // Clear log expectation for second call
        Log::shouldNotReceive('log');

        // Act - Call checkThresholds again immediately (within cooldown period)
        $result2 = $this->service->checkThresholds();

        // Assert - Second call should NOT trigger alert (cooldown active)
        $this->assertTrue($result2['checked']);
        $this->assertEquals(0, $result2['alert_count']);
        $this->assertEmpty($result2['alerts']);
    }

    /**
     * Test 5: Cooldown expired
     * After 30min cooldown, alert fires again
     */
    public function test_cooldown_expired(): void
    {
        // Arrange - Create high error rate
        DocumentProcessingLog::factory()->count(85)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'created_at' => now()->subMinutes(30),
        ]);

        DocumentProcessingLog::factory()->count(15)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(30),
        ]);

        // First check - trigger alert
        Log::shouldReceive('log')->once();
        $result1 = $this->service->checkThresholds();
        $this->assertGreaterThan(0, $result1['alert_count']);

        // Verify cooldown is active
        $this->assertTrue(Cache::has('error_alert:error_rate_threshold'));

        // Simulate cooldown expiration by manually clearing it
        Cache::forget('error_alert:error_rate_threshold');

        // Second check after cooldown expired should trigger alert again
        Log::shouldReceive('log')->once();

        // Act
        $result2 = $this->service->checkThresholds();

        // Assert
        $this->assertTrue($result2['checked']);
        $this->assertGreaterThan(0, $result2['alert_count']);

        // Verify new cooldown was set
        $this->assertTrue(Cache::has('error_alert:error_rate_threshold'));
    }

    /**
     * Additional Test 1: Multiple alerts in single check
     * Verify that both error rate AND consecutive failures can trigger simultaneously
     */
    public function test_multiple_alerts_in_single_check(): void
    {
        // Arrange - Create scenario that triggers both alerts:
        // - 5 consecutive failures at the end
        // - Overall high error rate

        DocumentProcessingLog::factory()->count(15)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'created_at' => now()->subHour(),
        ]);

        // Create 5 consecutive failures (most recent)
        DocumentProcessingLog::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(5),
        ]);

        Log::shouldReceive('log')->twice(); // One for error rate, one for consecutive

        // Act
        $result = $this->service->checkThresholds();

        // Assert - Both alerts should be triggered
        $this->assertTrue($result['checked']);
        $this->assertEquals(2, $result['alert_count']);

        $types = collect($result['alerts'])->pluck('type')->toArray();
        $this->assertContains('error_rate_exceeded', $types);
        $this->assertContains('consecutive_failures', $types);
    }

    /**
     * Additional Test 2: Alert status can be retrieved
     */
    public function test_alert_status_retrieved(): void
    {
        // Arrange - Manually set cooldowns
        Cache::put('error_alert:error_rate_threshold', true, now()->addMinutes(30));
        Cache::put('error_alert:consecutive_failures', true, now()->addMinutes(30));

        // Act
        $status = $this->service->getAlertStatus();

        // Assert
        $this->assertTrue($status['error_rate_alert_active']);
        $this->assertTrue($status['consecutive_failures_alert_active']);
        $this->assertEquals(30, $status['cooldown_minutes']);
    }

    /**
     * Additional Test 3: Alerting can be disabled via config
     */
    public function test_alerting_disabled_via_config(): void
    {
        // Arrange - Disable alerting
        Config::set('webhook.alerting.enabled', false);

        // Create high error rate
        DocumentProcessingLog::factory()->count(85)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'created_at' => now()->subMinutes(30),
        ]);

        DocumentProcessingLog::factory()->count(15)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(30),
        ]);

        // Ensure Log::log is never called when disabled
        Log::shouldNotReceive('log');

        // Act
        $result = $this->service->checkThresholds();

        // Assert
        $this->assertFalse($result['checked']);
        $this->assertEquals('Alerting disabled in config', $result['reason']);
    }

    /**
     * Additional Test 4: Threshold values are configurable
     */
    public function test_threshold_values_configurable(): void
    {
        // Arrange - Set custom threshold of 20%
        Config::set('webhook.alerting.error_rate_threshold', 20);

        // Create 15% error rate (below new threshold)
        DocumentProcessingLog::factory()->count(85)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'created_at' => now()->subMinutes(30),
        ]);

        DocumentProcessingLog::factory()->count(15)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(30),
        ]);

        // Should NOT trigger with 20% threshold and 15% error rate
        Log::shouldNotReceive('log');

        // Act
        $result = $this->service->checkThresholds();

        // Assert
        $this->assertTrue($result['checked']);
        $this->assertEquals(0, $result['alert_count']);
    }

    /**
     * Additional Test 5: Error codes are tracked in consecutive failure alerts
     */
    public function test_error_codes_tracked_in_alerts(): void
    {
        // Arrange - Create 5 failures with different error codes
        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(5),
        ]);

        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_PARSE_FAILURE',
            'created_at' => now()->subMinutes(4),
        ]);

        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(3),
        ]);

        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_VALIDATION_FAILURE',
            'created_at' => now()->subMinutes(2),
        ]);

        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_PARSE_FAILURE',
            'created_at' => now()->subMinute(),
        ]);

        Log::shouldReceive('log')->once();

        // Act
        $result = $this->service->checkThresholds();

        // Assert
        $this->assertGreaterThan(0, $result['alert_count']);

        $consecutiveAlert = collect($result['alerts'])->firstWhere('type', 'consecutive_failures');
        $this->assertNotNull($consecutiveAlert);

        $errorCodes = $consecutiveAlert['error_codes'];
        $this->assertCount(3, $errorCodes); // 3 unique error codes
        $this->assertContains('ERR_TIMEOUT', $errorCodes);
        $this->assertContains('ERR_PARSE_FAILURE', $errorCodes);
        $this->assertContains('ERR_VALIDATION_FAILURE', $errorCodes);
    }
}
