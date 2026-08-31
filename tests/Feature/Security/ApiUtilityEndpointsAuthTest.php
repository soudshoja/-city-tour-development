<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\SupplierCompanyController;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WebhookClient;
use App\Models\WebhookSecret;
use App\Services\WebhookSigningService;
use Database\Seeders\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Dev-branch hardening (unauthenticated personal-data disclosure). Before this fix,
 * POST /api/get-client, /api/get-agent, /api/get-company and /api/get-supplier had NO auth
 * middleware at all: an empty request body returned every row on the platform, including
 * Client.passport_no / civil_no / date_of_birth and Company.iata_client_secret. Fixed by gating
 * these four with the pre-existing `verify.webhook.signature` middleware (routes/api.php) --
 * the same one App\Http\Webhooks\TaskWebhook::webhook() uses -- plus a
 * WebhookClient::company_id scope and a response column allowlist (see APIController's own
 * docblocks). getCountry/getHotel/getTaskStructure are deliberately left unauthenticated (pure
 * reference/schema data, no PII, not tenant-scoped) -- covered here only as a sanity check that
 * the routing change didn't accidentally touch them.
 */
class ApiUtilityEndpointsAuthTest extends TestCase
{
    use RefreshDatabase;

    private WebhookSigningService $signingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signingService = new WebhookSigningService();
    }

    /**
     * @return array{client: WebhookClient, secret: string}
     */
    private function makeWebhookClientFor(Company $company): array
    {
        $client = WebhookClient::create([
            'name' => 'Test Webhook Client',
            'type' => 'n8n',
            'company_id' => $company->id,
            'webhook_url' => 'http://localhost/webhook/test',
            'rate_limit' => 60,
            'is_active' => true,
        ]);

        $secret = 'test-secret-' . $client->id;

        $webhookSecret = WebhookSecret::create([
            'webhook_client_id' => $client->id,
            'secret_hash' => $this->signingService->hashSecret($secret),
            'secret_preview' => substr($secret, -8),
            'algorithm' => 'sha256',
            'is_active' => true,
            'created_at' => now(),
        ]);

        putenv('WEBHOOK_SECRET_' . $webhookSecret->id . '=' . $secret);

        return ['client' => $client, 'secret' => $secret];
    }

    private function signedPost(string $path, WebhookClient $client, string $secret, array $payload = []): TestResponse
    {
        $fullPath = $path . '?client_id=' . $client->id;
        $payloadJson = json_encode($payload);

        // Signature covers method + PATH (no query string) + timestamp + payload -- see
        // WebhookSigningService::buildSigningMessage() and VerifyWebhookSignature's use of
        // $request->path(). The ?client_id= query param is only how the middleware finds WHICH
        // client's secret to try; it is not part of the signed message.
        $signed = $this->signingService->signPayload($payloadJson, $secret, 'POST', $path);

        return $this->postJson($fullPath, $payload, [
            'X-Signature-SHA256' => $signed['signature'],
            'X-Signature-Timestamp' => $signed['timestamp'],
        ]);
    }

    private function makeCompany(): Company
    {
        $country = Country::factory()->create();
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);

        return Company::factory()->create([
            'user_id' => $owner->id,
            'country_id' => $country->id,
        ]);
    }

    // --- unauthenticated requests are rejected -------------------------------------------------

    public function test_get_client_rejects_unauthenticated_request(): void
    {
        $this->postJson('api/get-client', [])->assertStatus(401);
    }

    public function test_get_agent_rejects_unauthenticated_request(): void
    {
        $this->postJson('api/get-agent', [])->assertStatus(401);
    }

    public function test_get_company_rejects_unauthenticated_request(): void
    {
        $this->postJson('api/get-company', [])->assertStatus(401);
    }

    public function test_get_supplier_rejects_unauthenticated_request(): void
    {
        $this->postJson('api/get-supplier', [])->assertStatus(401);
    }

    public function test_get_client_rejects_request_with_invalid_signature(): void
    {
        $companyA = $this->makeCompany();
        ['client' => $webhookClient] = $this->makeWebhookClientFor($companyA);

        $response = $this->postJson('api/get-client?client_id=' . $webhookClient->id, [], [
            'X-Signature-SHA256' => 'not-a-real-signature',
            'X-Signature-Timestamp' => time(),
        ]);

        $response->assertStatus(401);
    }

    // --- legitimate (signed) use: company scoping + column allowlist ---------------------------

    public function test_get_client_scopes_to_caller_company_and_excludes_sensitive_columns(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        ['client' => $webhookClient, 'secret' => $secret] = $this->makeWebhookClientFor($companyA);

        $agentType = AgentType::factory()->create();
        $branchA = Branch::factory()->create(['company_id' => $companyA->id, 'user_id' => $companyA->user_id]);
        $branchB = Branch::factory()->create(['company_id' => $companyB->id, 'user_id' => $companyB->user_id]);
        $agentA = Agent::factory()->create(['branch_id' => $branchA->id, 'type_id' => $agentType->id, 'user_id' => $companyA->user_id]);
        $agentB = Agent::factory()->create(['branch_id' => $branchB->id, 'type_id' => $agentType->id, 'user_id' => $companyB->user_id]);

        $clientA = Client::factory()->create([
            'agent_id' => $agentA->id,
            'company_id' => $companyA->id,
            'first_name' => 'Alice',
            'passport_no' => 'P-SECRET-A',
            'civil_no' => 'C-SECRET-A',
        ]);
        $clientB = Client::factory()->create([
            'agent_id' => $agentB->id,
            'company_id' => $companyB->id,
            'first_name' => 'Bob',
            'passport_no' => 'P-SECRET-B',
            'civil_no' => 'C-SECRET-B',
        ]);

        $response = $this->signedPost('api/get-client', $webhookClient, $secret);

        $response->assertOk();

        $ids = collect($response->json('clients'))->pluck('id');
        $this->assertTrue($ids->contains($clientA->id), 'Caller\'s own client missing from response.');
        $this->assertFalse($ids->contains($clientB->id), 'Leaked another company\'s client.');

        $raw = $response->getContent();
        foreach (['P-SECRET-A', 'C-SECRET-A', 'P-SECRET-B', 'C-SECRET-B', 'passport_no', 'civil_no', 'date_of_birth', 'old_passport_no'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw, "Response leaked '{$needle}'.");
        }
    }

    public function test_get_agent_scopes_to_caller_company_and_excludes_compensation_columns(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        ['client' => $webhookClient, 'secret' => $secret] = $this->makeWebhookClientFor($companyA);

        $agentType = AgentType::factory()->create();
        $branchA = Branch::factory()->create(['company_id' => $companyA->id, 'user_id' => $companyA->user_id]);
        $branchB = Branch::factory()->create(['company_id' => $companyB->id, 'user_id' => $companyB->user_id]);

        $agentA = Agent::factory()->create([
            'branch_id' => $branchA->id,
            'type_id' => $agentType->id,
            'user_id' => $companyA->user_id,
            'name' => 'Agent A',
            'salary' => 99999,
            'commission' => 15,
        ]);
        $agentB = Agent::factory()->create([
            'branch_id' => $branchB->id,
            'type_id' => $agentType->id,
            'user_id' => $companyB->user_id,
            'name' => 'Agent B',
        ]);

        $response = $this->signedPost('api/get-agent', $webhookClient, $secret);

        $response->assertOk();

        $ids = collect($response->json('agents'))->pluck('id');
        $this->assertTrue($ids->contains($agentA->id), 'Caller\'s own agent missing from response.');
        $this->assertFalse($ids->contains($agentB->id), 'Leaked another company\'s agent.');

        $raw = $response->getContent();
        foreach (['salary', 'commission', 'target', '99999'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw, "Response leaked '{$needle}'.");
        }
    }

    public function test_get_company_only_returns_callers_own_company_and_excludes_iata_secret(): void
    {
        $companyA = $this->makeCompany();
        $companyA->update(['iata_client_secret' => 'TOP-SECRET-IATA-VALUE']);
        $companyB = $this->makeCompany();

        ['client' => $webhookClient, 'secret' => $secret] = $this->makeWebhookClientFor($companyA);

        $response = $this->signedPost('api/get-company', $webhookClient, $secret);

        $response->assertOk();

        $ids = collect($response->json('companies'))->pluck('id');
        $this->assertEquals([$companyA->id], $ids->all(), 'Only the caller\'s own company should be returned.');

        $raw = $response->getContent();
        foreach (['TOP-SECRET-IATA-VALUE', 'iata_client_secret', 'iata_client_id'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw, "Response leaked '{$needle}'.");
        }
    }

    public function test_get_supplier_scopes_to_active_companies_and_excludes_payment_terms(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        ['client' => $webhookClient, 'secret' => $secret] = $this->makeWebhookClientFor($companyA);

        // activateSupplierProcess() posts to each company's own Accounts-Payable COA category
        // (e.g. "Suppliers (Flights)" for a has_flight supplier) -- needs a seeded COA to exist.
        CoaSeeder::run($companyA->id);
        CoaSeeder::run($companyB->id);

        $supplierA = Supplier::factory()->create(['name' => 'Supplier A', 'payment_terms' => 'NET90-SECRET']);
        $supplierB = Supplier::factory()->create(['name' => 'Supplier B', 'payment_terms' => 'NET90-SECRET']);

        $activationA = (new SupplierCompanyController())->activateSupplierProcess($supplierA, $companyA);
        $activationB = (new SupplierCompanyController())->activateSupplierProcess($supplierB, $companyB);
        $this->assertSame('success', $activationA['status'], $activationA['message'] ?? '');
        $this->assertSame('success', $activationB['status'], $activationB['message'] ?? '');

        $response = $this->signedPost('api/get-supplier', $webhookClient, $secret);

        $response->assertOk();

        $ids = collect($response->json('suppliers'))->pluck('id');
        $this->assertTrue($ids->contains($supplierA->id), 'Caller\'s own active supplier missing from response.');
        $this->assertFalse($ids->contains($supplierB->id), 'Leaked a supplier not active for the caller\'s company.');

        $raw = $response->getContent();
        foreach (['payment_terms', 'NET90-SECRET', 'auth_type'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw, "Response leaked '{$needle}'.");
        }
    }

    // --- deliberately-unauthenticated reference endpoints are unaffected -----------------------

    public function test_get_task_structure_remains_unauthenticated(): void
    {
        $this->postJson('api/get-task-structure', ['task_type' => 'flight'])->assertOk();
    }

    public function test_get_country_remains_unauthenticated(): void
    {
        Country::factory()->create(['name' => 'Kuwait']);

        $this->postJson('api/get-country', ['country_name' => 'Kuwait'])->assertOk();
    }

    public function test_get_hotel_remains_unauthenticated(): void
    {
        Hotel::create(['name' => 'Grand Hyatt']);

        $this->postJson('api/get-hotel', ['hotel_name' => 'Grand Hyatt'])->assertOk();
    }
}
