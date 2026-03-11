<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\DocumentProcessingLog;
use App\Services\ErrorAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ErrorAlertServiceTest extends TestCase
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

        // Enable alerting
        Config::set('webhook.alerting.enabled', true);
        Config::set('webhook.alerting.error_rate_threshold', 10);
        Config::set('webhook.alerting.consecutive_failures', 5);
        Config::set('webhook.alerting.alert_cooldown_minutes', 30);
    }

    /** @test */
    public function it_triggers_alert_when_error_rate_exceeds_threshold()
    {
        // Create 10 total docs: 2 failed (20% failure rate) > 10% threshold
        DocumentProcessingLog::factory()->count(8)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'created_at' => now()->subMinutes(30),
        ]);

        DocumentProcessingLog::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(30),
        ]);

        Log::shouldReceive('log')
            ->once()
            ->with('warning', \Mockery::type('string'), \Mockery::type('array'));

        $result = $this->service->checkThresholds();

        $this->assertTrue($result['checked']);
        $this->assertGreaterThan(0, $result['alert_count']);
        $this->assertEquals('error_rate_exceeded', $result['alerts'][0]['type']);
    }

    /** @test */
    public function it_does_not_trigger_alert_when_error_rate_is_below_threshold()
    {
        // Create 100 total docs: 5 failed (5% failure rate) < 10% threshold
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

        $result = $this->service->checkThresholds();

        $this->assertTrue($result['checked']);
        $this->assertEquals(0, $result['alert_count']);
    }

    /** @test */
    public function it_triggers_alert_for_consecutive_failures()
    {
        // Create 5 consecutive failures
        DocumentProcessingLog::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_CRITICAL',
            'created_at' => now()->subMinutes(10),
        ]);

        Log::shouldReceive('log')
            ->once()
            ->with('critical', \Mockery::type('string'), \Mockery::type('array'));

        $result = $this->service->checkThresholds();

        $this->assertTrue($result['checked']);
        $this->assertGreaterThan(0, $result['alert_count']);

        $consecutiveAlert = collect($result['alerts'])->firstWhere('type', 'consecutive_failures');
        $this->assertNotNull($consecutiveAlert);
        $this->assertEquals('critical', $consecutiveAlert['severity']);
    }

    /** @test */
    public function it_respects_cooldown_period()
    {
        // Create error rate that exceeds threshold
        DocumentProcessingLog::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'created_at' => now()->subMinutes(30),
        ]);

        DocumentProcessingLog::factory()->count(8)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subMinutes(30),
        ]);

        Log::shouldReceive('log')->once();

        // First check - should trigger alert
        $result1 = $this->service->checkThresholds();
        $this->assertGreaterThan(0, $result1['alert_count']);

        // Second check immediately after - should NOT trigger (cooldown active)
        $result2 = $this->service->checkThresholds();
        $this->assertEquals(0, $result2['alert_count']);
    }

    /** @test */
    public function it_can_clear_cooldowns()
    {
        // Set cooldown manually
        Cache::put('error_alert:error_rate_threshold', true, now()->addMinutes(30));
        Cache::put('error_alert:consecutive_failures', true, now()->addMinutes(30));

        $this->service->clearCooldowns();

        $this->assertFalse(Cache::has('error_alert:error_rate_threshold'));
        $this->assertFalse(Cache::has('error_alert:consecutive_failures'));
    }

    /** @test */
    public function it_returns_alert_status()
    {
        // Set one cooldown active
        Cache::put('error_alert:error_rate_threshold', true, now()->addMinutes(30));

        $status = $this->service->getAlertStatus();

        $this->assertTrue($status['error_rate_alert_active']);
        $this->assertFalse($status['consecutive_failures_alert_active']);
        $this->assertEquals(30, $status['cooldown_minutes']);
    }

    /** @test */
    public function it_does_not_check_when_alerting_is_disabled()
    {
        Config::set('webhook.alerting.enabled', false);

        DocumentProcessingLog::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_CRITICAL',
        ]);

        $result = $this->service->checkThresholds();

        $this->assertFalse($result['checked']);
        $this->assertEquals('Alerting disabled in config', $result['reason']);
    }

    /** @test */
    public function it_logs_alert_with_correct_severity()
    {
        // Test critical severity for consecutive failures
        DocumentProcessingLog::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_CRITICAL',
        ]);

        Log::shouldReceive('log')
            ->once()
            ->with('critical', \Mockery::pattern('/consecutive_failures/'), \Mockery::type('array'));

        $this->service->checkThresholds();
    }
}
