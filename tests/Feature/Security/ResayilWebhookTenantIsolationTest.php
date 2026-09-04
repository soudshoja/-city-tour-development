<?php

namespace Tests\Feature\Security;

use App\Models\Agent;
use App\Models\IncomingMedia;
use App\Models\ResayilAccount;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Security fix (sec/resayil-webhook): Resayil's register-webhook body
 * (`{name, device, url, events}`) carries no signature/secret of its own
 * (see the resayil-whatsapp-api skill, references/webhooks.md) — security
 * is entirely ours. The old handlers resolved the sending company by
 * looking up Agent::where('phone_number', $phone) straight from the
 * webhook BODY: anyone who knew (or guessed) another company's agent
 * phone number, or simply controlled what "phone" a forged payload
 * claimed, could get their media/client-creation flow attributed to that
 * OTHER company, or vice versa.
 *
 * This proves the fix end-to-end through the real route + middleware
 * stack (VerifyResayilWebhookSecret): company identity now comes only
 * from which per-company secret the request was delivered to
 * (`/api/webhook/resayil/media/{secret}`), never from the payload.
 */
class ResayilWebhookTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function makeResayilAccount(int $companyId, ?string $deviceId = null): array
    {
        $account = ResayilAccount::create([
            'company_id' => $companyId,
            'role' => ResayilAccount::ROLE_ADMIN,
            'resayil_customer_id' => 'cust_'.$companyId,
            'resayil_device_id' => $deviceId,
            'status' => ResayilAccount::STATUS_PROVISIONED,
        ]);

        $plainSecret = $account->ensureWebhookSecret();

        return [$account->fresh(), $plainSecret];
    }

    public function test_no_secret_or_wrong_secret_returns_404_and_writes_nothing(): void
    {
        $tenantA = $this->createTenant();
        [$accountA, $secretA] = $this->makeResayilAccount($tenantA['company']->id);

        // No secret at all (legacy route) — must 404, not proceed.
        $this->postJson('/api/webhook/resayil/media', [
            'data' => ['fromNumber' => $tenantA['agent']->phone_number],
        ])->assertStatus(404);

        // Wrong / unknown secret.
        $this->postJson('/api/webhook/resayil/media/not-a-real-secret', [
            'data' => ['fromNumber' => $tenantA['agent']->phone_number],
        ])->assertStatus(404);

        $this->assertSame(0, IncomingMedia::count());
    }

    public function test_valid_secret_for_a_with_agent_phone_belonging_to_b_is_not_matched(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        [$accountA, $secretA] = $this->makeResayilAccount($tenantA['company']->id);

        // Payload arrives on A's webhook URL but the phone number belongs to
        // an agent of company B.
        $response = $this->postJson("/api/webhook/resayil/media/{$secretA}", [
            'data' => [
                'fromNumber' => $tenantB['agent']->phone_number,
                'chat' => ['type' => 'direct', 'id' => $tenantB['agent']->phone_number.'@c.us'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Webhook ignored - agents only']);

        $this->assertSame(0, IncomingMedia::count());
        // Nothing was ever attributed to company B via this request.
        $this->assertDatabaseMissing('incoming_media', ['company_id' => $tenantB['company']->id]);
    }

    public function test_valid_secret_for_a_with_agent_of_a_is_recognized_as_that_company(): void
    {
        $tenantA = $this->createTenant();
        [$accountA, $secretA] = $this->makeResayilAccount($tenantA['company']->id);

        // Non-media text message from A's own agent: the request should be
        // recognized as belonging to A (not bounced as "agents only") and
        // instructed to send a document — proving the agent-lookup matched
        // scoped to company A's own secret/account.
        $response = $this->postJson("/api/webhook/resayil/media/{$secretA}", [
            'data' => [
                'fromNumber' => $tenantA['agent']->phone_number,
                'chat' => ['type' => 'direct', 'id' => $tenantA['agent']->phone_number.'@c.us'],
                'text' => 'hello',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertNotSame('Webhook ignored - agents only', $response->json('message'));
    }

    public function test_device_mismatch_on_valid_secret_writes_nothing(): void
    {
        $tenantA = $this->createTenant();
        [$accountA, $secretA] = $this->makeResayilAccount($tenantA['company']->id, 'deviceA123456789');

        $response = $this->postJson("/api/webhook/resayil/media/{$secretA}", [
            'device' => ['id' => 'someOtherDeviceId999'],
            'data' => [
                'fromNumber' => $tenantA['agent']->phone_number,
                'chat' => ['type' => 'direct', 'id' => $tenantA['agent']->phone_number.'@c.us'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Webhook ignored']);

        $this->assertSame(0, IncomingMedia::count());
    }

    public function test_no_fallback_notification_number_is_hardcoded_when_company_setting_unset(): void
    {
        $tenantA = $this->createTenant();
        [$accountA, $secretA] = $this->makeResayilAccount($tenantA['company']->id);

        // No `notification.agent_default_phone` / `notification.agent_default_email`
        // Setting row exists for company A — the old code fell back to
        // City Travelers' own hardcoded number/email
        // (+96522210017 / ops@citytravelers.co) here.
        $this->assertSame(
            0,
            Setting::where('company_id', $tenantA['company']->id)
                ->whereIn('key', ['notification.agent_default_phone', 'notification.agent_default_email'])
                ->count()
        );

        Http::fake();

        // Drive the controller into the exception-fallback assignment
        // directly: the source no longer contains the hardcoded literals,
        // and the config()-based defaults are gone in favour of
        // Setting::getByKey(), which returns null when unset — asserted at
        // the source level below since that fallback is only assigned on
        // an internal exception path that this black-box HTTP test cannot
        // deterministically trigger.
        $controllerSource = file_get_contents(app_path('Http/Controllers/IncomingMediaController.php'));
        // The old hardcoded fallback defaults are gone (the '+96522210017'
        // string still appears once, unrelated, as a user-facing phone
        // FORMAT example — "eg: +96522210017" — so assert on the specific
        // config() default assignment, not the bare literal).
        $this->assertStringNotContainsString("config('app.agent_default_phone', '+96522210017')", $controllerSource);
        $this->assertStringNotContainsString('ops@citytravelers.co', $controllerSource);
        $this->assertStringContainsString("Setting::getByKey(\$companyId, 'notification.agent_default_phone')", $controllerSource);
        $this->assertStringContainsString("Setting::getByKey(\$companyId, 'notification.agent_default_email')", $controllerSource);

        // And confirm no outbound Resayil message request went out as a
        // side effect of any of the above (belt-and-suspenders — the real
        // send transport is raw cURL in WhatsappController::sendToResayil,
        // not the Http facade, so this asserts the Http-facade side only).
        Http::assertNothingSent();
    }
}
