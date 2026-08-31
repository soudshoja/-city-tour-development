<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\DotwAI;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebhookClient;
use App\Models\WebhookSecret;
use App\Modules\DotwAI\Services\DotwAIResponse;
use App\Services\WebhookSigningService;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Regression coverage for Accounting Gap blocker 8
 * (16-phase1-verification-findings-2026-08.md): GET /api/dotwai/statement
 * previously had no Laravel auth guard, no module gate, and no credential
 * at all -- the dotwai.resolve middleware's phone lookup identifies a
 * COMPANY, not a caller. This test locks in the fix:
 *
 * 1. An anonymous caller (any/no telephone, no signature) is rejected 401.
 * 2. A verified caller whose company lacks the accounting module is 404'd
 *    (same "route does not exist" philosophy as module:accounting elsewhere).
 * 3. A verified caller whose company has the accounting module enabled
 *    gets the statement envelope.
 *
 * @see App\Modules\DotwAI\Http\Controllers\StatementController::getStatement()
 */
class StatementSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private Company $company;
    private Agent $agent;
    private WebhookClient $webhookClient;
    private string $secret;
    private WebhookSigningService $signingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Pre-existing environment gap, unrelated to this fix: config/dotw.php
        // documents a 'dotw' log channel (config/dotw.php:116-119) that was
        // never actually added to config/logging.php, so every
        // Log::channel('dotw') call in this module (ResolveDotwAIContext,
        // StatementController, ...) throws "Log [dotw] is not defined."
        // Define it for this test process only -- not touching the shared
        // config/logging.php file from this ticket.
        config(['logging.channels.dotw' => [
            'driver' => 'single',
            'path'   => storage_path('logs/dotw-test.log'),
            'level'  => 'debug',
        ]]);

        // BranchFactory/AgentFactory hardcode user_id/type_id => 1 ("Will be
        // overridden in tests" per their own docblocks) -- create real rows
        // explicitly rather than depending on baseline seed data that
        // RefreshDatabase's own migrate:fresh (run once per test process,
        // unseeded) does not provide.
        $user = User::factory()->create();
        $agentType = AgentType::factory()->create();

        $this->company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => $user->id]);
        $this->agent = Agent::factory()->create([
            'branch_id'    => $branch->id,
            'user_id'      => $user->id,
            'type_id'      => $agentType->id,
            'phone_number' => '99800027',
            'country_code' => '+965',
        ]);

        CompanyDotwCredential::create([
            'company_id'        => $this->company->id,
            'dotw_username'     => Crypt::encrypt('testuser'),
            'dotw_password'     => Crypt::encrypt('testpass'),
            'dotw_company_code' => 'TEST',
            'markup_percent'    => 0,
            'is_active'         => true,
            'b2b_enabled'       => true,
            'b2c_enabled'       => false,
        ]);

        $this->signingService = new WebhookSigningService();
        $this->secret = 'test-dotwai-statement-secret';

        $this->webhookClient = WebhookClient::create([
            'name'       => 'Test N8n DotwAI Client',
            'type'       => 'n8n',
            'is_active'  => true,
            'rate_limit' => 60,
        ]);

        WebhookSecret::create([
            'webhook_client_id' => $this->webhookClient->id,
            'secret_hash'       => $this->signingService->hashSecret($this->secret),
            'secret_preview'    => substr($this->secret, -8),
            'algorithm'         => 'sha256',
            'is_active'         => true,
            'created_at'        => now(),
        ]);

        putenv('WEBHOOK_SECRET_' . $this->webhookClient->getActiveSecret()->id . '=' . $this->secret);
    }

    private function statementUrl(): string
    {
        // StatementRequest::rules() validates a 'phone' field even though
        // dotwai.resolve reads 'telephone' -- a pre-existing inconsistency
        // in StatementRequest.php, unrelated to this fix. Both are supplied
        // here so requests reach the security checks under test instead of
        // bouncing off that unrelated validation rule.
        return '/api/dotwai/statement?telephone=%2B96599800027&phone=%2B96599800027&date_from=2026-01-01&date_to=2026-01-31&client_id=' . $this->webhookClient->id;
    }

    private function signedHeaders(): array
    {
        // Illuminate\Foundation\Testing\Concerns\MakesHttpRequests::json()
        // always sends json_encode($data) as the body, defaulting $data to
        // [] for getJson() -- i.e. the literal string '[]', not ''. Sign the
        // same bytes the test client actually transmits.
        $signed = $this->signingService->signPayload('[]', $this->secret, 'GET', 'api/dotwai/statement');

        return [
            'X-Signature-SHA256'    => $signed['signature'],
            'X-Signature-Timestamp' => $signed['timestamp'],
        ];
    }

    /** @test */
    public function unsigned_request_is_rejected_even_with_a_valid_phone_number(): void
    {
        $response = $this->getJson($this->statementUrl());

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', DotwAIResponse::UNAUTHORIZED);
    }

    /** @test */
    public function signed_request_is_still_rejected_for_a_company_without_accounting_module(): void
    {
        // No module.accounting Setting row exists for $this->company, and
        // accounting fails CLOSED by default (config('modules.default_disabled')).
        $response = $this->getJson($this->statementUrl(), $this->signedHeaders());

        $response->assertStatus(404);
    }

    /** @test */
    public function signed_request_succeeds_once_the_company_has_the_accounting_module(): void
    {
        Setting::create([
            'company_id' => $this->company->id,
            'key'        => Modules::settingKey(Modules::ACCOUNTING),
            'type'       => 'boolean',
            'value'      => true,
        ]);
        Company::forgetModuleCache();

        $response = $this->getJson($this->statementUrl(), $this->signedHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['bookings', 'journal_entries', 'credits', 'totals'], 'whatsappMessage']);
    }

    /** @test */
    public function invalid_signature_is_rejected(): void
    {
        $response = $this->getJson($this->statementUrl(), [
            'X-Signature-SHA256'    => 'not-a-real-signature',
            'X-Signature-Timestamp' => time(),
        ]);

        $response->assertStatus(401);
    }
}
