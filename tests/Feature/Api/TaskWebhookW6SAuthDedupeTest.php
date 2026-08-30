<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\SupplierCompanyController;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\WebhookClient;
use App\Models\WebhookSecret;
use App\Services\WebhookSigningService;
use Database\Seeders\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W6.S item (4) (w6-brief.md "Consolidation + fixes" -- ct-void-map.md §7 bug 1). Pins:
 *  - `POST /api/task/webhook` rejects (401) a request with no verified signature -- the route's
 *    own `verify.webhook.signature` middleware alone is NOT enough (it silently skips verification
 *    when no signature header is present at all -- see VerifyWebhookSignature's own docblock);
 *    TaskWebhook::webhook() itself is what turns that into a hard requirement for this endpoint.
 *  - payload_hash dedupe: an identical signed payload sent twice creates exactly one task.
 *
 * Does NOT touch/assert anything about App\Http\Middleware\VerifyWebhookSignature's own internal
 * behaviour (HmacMiddlewareTest's 4 pre-existing failures belong to another track) -- this test
 * only consumes it via a route, exactly like TaskWebhook::webhook() does.
 */
class TaskWebhookW6SAuthDedupeTest extends TestCase
{
    use RefreshDatabase;

    private WebhookSigningService $signingService;
    private WebhookClient $client;
    private string $secret;
    private Company $company;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signingService = new WebhookSigningService();
        $this->secret = 'w6s-task-webhook-test-secret';

        $this->client = WebhookClient::create([
            'name' => 'W6S Test Client',
            'type' => 'n8n',
            'webhook_url' => 'http://localhost/webhook/test',
            'rate_limit' => 60,
            'is_active' => true,
        ]);

        $webhookSecret = WebhookSecret::create([
            'webhook_client_id' => $this->client->id,
            'secret_hash' => $this->signingService->hashSecret($this->secret),
            'secret_preview' => substr($this->secret, -8),
            'algorithm' => 'sha256',
            'is_active' => true,
            'created_at' => now(),
        ]);

        putenv('WEBHOOK_SECRET_' . $webhookSecret->id . '=' . $this->secret);

        $country = Country::factory()->create();
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create([
            'user_id' => $owner->id,
            'country_id' => $country->id,
        ]);

        CoaSeeder::run($this->company->id);

        $this->supplier = Supplier::factory()->create(['name' => 'Webhook Test Visa Supplier']);
        (new SupplierCompanyController())->activateSupplierProcess($this->supplier, $this->company);
    }

    private function signedPost(array $payload): \Illuminate\Testing\TestResponse
    {
        $path = 'api/task/webhook/' . $this->client->id;
        $payloadJson = json_encode($payload);

        $signed = $this->signingService->signPayload($payloadJson, $this->secret, 'POST', $path);

        return $this->postJson($path, $payload, [
            'X-Signature-SHA256' => $signed['signature'],
            'X-Signature-Timestamp' => $signed['timestamp'],
        ]);
    }

    private function visaPayload(string $reference): array
    {
        return [
            'reference' => $reference,
            'status' => 'issued',
            'company_id' => $this->company->id,
            'type' => 'visa',
            'supplier_id' => $this->supplier->id,
            'client_name' => 'Dedupe Test Client',
            'total' => 100,
            'price' => 100,
            'task_visa_details' => [
                [
                    'visa_type' => 'tourist',
                    'application_number' => 'APP-' . $reference,
                    'expiry_date' => now()->addYear()->toDateString(),
                    'number_of_entries' => 'single',
                    'stay_duration' => 30,
                    'issuing_country' => 'Kuwait',
                ],
            ],
        ];
    }

    public function test_webhook_rejects_unsigned_request_with_401(): void
    {
        $response = $this->postJson('api/task/webhook/' . $this->client->id, [
            'reference' => 'NOSIG-001',
            'status' => 'issued',
            'company_id' => $this->company->id,
            'type' => 'visa',
        ]);

        $response->assertStatus(401);
        $this->assertSame(0, Task::where('reference', 'NOSIG-001')->count());
    }

    public function test_webhook_rejects_invalid_signature_with_401(): void
    {
        $response = $this->postJson('api/task/webhook/' . $this->client->id, [
            'reference' => 'BADSIG-001',
            'status' => 'issued',
            'company_id' => $this->company->id,
            'type' => 'visa',
        ], [
            'X-Signature-SHA256' => 'not-a-real-signature',
            'X-Signature-Timestamp' => time(),
        ]);

        $response->assertStatus(401);
    }

    public function test_duplicate_signed_payload_creates_exactly_one_task(): void
    {
        $payload = $this->visaPayload('DEDUPE-001');

        $first = $this->signedPost($payload);
        $first->assertStatus(201);

        $this->assertSame(1, Task::where('reference', 'DEDUPE-001')->count());

        $second = $this->signedPost($payload);
        $second->assertStatus(200);
        $second->assertJsonPath('data.duplicate', true);

        // Still exactly one task -- the duplicate was a no-op, not a second creation.
        $this->assertSame(1, Task::where('reference', 'DEDUPE-001')->count());

        $this->assertDatabaseCount('task_webhook_dedupes', 1);
    }

    public function test_different_payload_is_not_treated_as_a_duplicate(): void
    {
        $this->signedPost($this->visaPayload('DEDUPE-002'))->assertStatus(201);
        $this->signedPost($this->visaPayload('DEDUPE-003'))->assertStatus(201);

        $this->assertSame(1, Task::where('reference', 'DEDUPE-002')->count());
        $this->assertSame(1, Task::where('reference', 'DEDUPE-003')->count());
        $this->assertDatabaseCount('task_webhook_dedupes', 2);
    }
}
