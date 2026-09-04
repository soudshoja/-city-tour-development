<?php

namespace Tests\Unit\Services\Resayil;

use App\Services\Resayil\ResayilClient;
use App\Services\Resayil\ResayilProvisioningService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Wave 2 — silent account-key capture.
 *
 * Deliberately DATABASE-FREE. The suite's RefreshDatabase path is broken on
 * this branch for an unrelated reason (a duplicated dotw_prebooks migration
 * makes every DB-backed test error before its first assertion), so a test
 * that needed a company row would have proved nothing. Everything here
 * exercises the two pieces that actually decide whether a credential is
 * captured — which apiKeys[] entry is chosen, and whether it is proven
 * before being trusted — through real HTTP plumbing (Http::fake intercepts
 * at the transport layer, so ResayilClient's headers, retries and base-URL
 * handling all really run).
 */
class ResayilAccountKeyCaptureTest extends TestCase
{
    protected bool $skipPermissionSeeder = true;

    private const RESELLER = 'https://api.resayil.test/v1/resellers';

    private const ACCOUNT = 'https://api.resayil.test/v1';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resayil.reseller_base_url', self::RESELLER);
        config()->set('resayil.reseller_token', 'reseller-token-for-tests');
        config()->set('resayil.account_base_url', self::ACCOUNT);
        config()->set('resayil.test_mode', false);
        config()->set('resayil.retries', 1);
    }

    private function service(): ResayilProvisioningService
    {
        return new ResayilProvisioningService(
            new ResayilClient(self::RESELLER, 'reseller-token-for-tests')
        );
    }

    // ---------------------------------------------------------------
    // selectApiKey — pure choice logic
    // ---------------------------------------------------------------

    public function test_it_prefers_the_default_active_key(): void
    {
        $chosen = ResayilProvisioningService::selectApiKey([
            ['id' => 'k1', 'alias' => 'n8n', 'isDefault' => false, 'status' => 50, 'value' => 'AAA'],
            ['id' => 'k2', 'alias' => 'Default', 'isDefault' => true, 'status' => 50, 'value' => 'BBB'],
            ['id' => 'k3', 'alias' => 'old', 'isDefault' => true, 'status' => 10, 'value' => 'CCC'],
        ]);

        $this->assertSame('k2', $chosen['id']);
    }

    public function test_it_falls_back_to_the_first_active_key_when_none_is_default(): void
    {
        $chosen = ResayilProvisioningService::selectApiKey([
            ['id' => 'k1', 'status' => 10, 'value' => 'AAA'],
            ['id' => 'k2', 'status' => 50, 'value' => 'BBB'],
            ['id' => 'k3', 'status' => 50, 'value' => 'CCC'],
        ]);

        $this->assertSame('k2', $chosen['id']);
    }

    public function test_it_ignores_entries_with_no_usable_value(): void
    {
        $this->assertNull(ResayilProvisioningService::selectApiKey([]));
        $this->assertNull(ResayilProvisioningService::selectApiKey([
            ['id' => 'k1', 'isDefault' => true, 'status' => 50],
            ['id' => 'k2', 'isDefault' => true, 'status' => 50, 'value' => ''],
            'not-an-array',
        ]));
    }

    // ---------------------------------------------------------------
    // fetchAccountKey — read, then PROVE
    // ---------------------------------------------------------------

    public function test_it_reads_the_detail_endpoint_and_returns_a_validated_key(): void
    {
        Http::fake([
            self::RESELLER.'/customers/CUST1' => Http::response([
                'id' => 'CUST1',
                'apiKeys' => [
                    ['id' => 'KEY1', 'alias' => 'Default', 'isDefault' => true, 'status' => 50, 'value' => 'live-account-key'],
                ],
            ], 200),
            // A brand-new customer has no numbers yet: an empty array with
            // HTTP 200 is the real, and sufficient, proof of a working key.
            self::ACCOUNT.'/devices' => Http::response([], 200),
        ]);

        $result = $this->service()->fetchAccountKey('CUST1');

        $this->assertTrue($result['ok']);
        $this->assertSame('live-account-key', $result['key']);
        $this->assertSame('KEY1', $result['key_id']);
        $this->assertSame('Default', $result['alias']);

        // The DETAIL endpoint, by id. The LIST endpoint omits apiKeys
        // entirely, so reading it would silently look like "no key exists".
        Http::assertSent(fn ($request) => $request->url() === self::RESELLER.'/customers/CUST1');

        // And the candidate was actually presented to the account API
        // under the Token header before it was called a credential.
        Http::assertSent(fn ($request) => str_starts_with($request->url(), self::ACCOUNT.'/devices')
            && $request->header('Token') === ['live-account-key']);
    }

    public function test_it_refuses_to_return_a_key_that_does_not_authenticate(): void
    {
        Http::fake([
            self::RESELLER.'/customers/CUST1' => Http::response([
                'apiKeys' => [
                    ['id' => 'KEY1', 'isDefault' => true, 'status' => 50, 'value' => 'not-really-a-key'],
                ],
            ], 200),
            self::ACCOUNT.'/devices' => Http::response(['message' => 'API key is not allowed'], 401),
        ]);

        $result = $this->service()->fetchAccountKey('CUST1');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['key']);
        $this->assertSame('validation_failed', $result['reason']);
        $this->assertSame(401, $result['http_status']);
    }

    public function test_a_customer_with_no_keys_is_reported_not_retried_forever(): void
    {
        Http::fake([
            // What a soft-deleted (status 20) customer really returns.
            self::RESELLER.'/customers/CUST1' => Http::response(['status' => 20, 'apiKeys' => []], 200),
        ]);

        $result = $this->service()->fetchAccountKey('CUST1');

        $this->assertFalse($result['ok']);
        $this->assertSame('no_api_keys', $result['reason']);
    }

    public function test_an_unreachable_reseller_api_degrades_instead_of_throwing(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 7'));

        $result = $this->service()->fetchAccountKey('CUST1');

        $this->assertFalse($result['ok']);
        $this->assertSame('detail_read_exception', $result['reason']);
    }

    public function test_a_failing_detail_read_never_writes_the_key_into_a_log_line(): void
    {
        // The one body on this whole integration that legitimately contains
        // a live credential is the customer detail response. If a later
        // change ever logs it, this test is what should fail.
        Log::spy();

        Http::fake([
            self::RESELLER.'/customers/CUST1' => Http::response([
                'apiKeys' => [
                    ['id' => 'KEY1', 'isDefault' => true, 'status' => 50, 'value' => 'super-secret-key'],
                ],
            ], 500),
        ]);

        $this->service()->fetchAccountKey('CUST1');

        Log::shouldHaveReceived('warning')->withArgs(function ($event, $context = []) {
            $this->assertStringNotContainsString('super-secret-key', json_encode($context));

            return true;
        });
    }

    public function test_the_redactor_strips_a_key_out_of_an_echoed_body(): void
    {
        $service = $this->service();

        $method = new \ReflectionMethod($service, 'redactSecret');
        $method->setAccessible(true);

        $redacted = $method->invoke($service, [
            'apiKeys' => [['alias' => 'Default', 'value' => 'super-secret-key']],
            'password' => 'hunter2',
            'nested' => ['token' => 'abc'],
        ]);

        $encoded = json_encode($redacted);

        $this->assertStringNotContainsString('super-secret-key', $encoded);
        $this->assertStringNotContainsString('hunter2', $encoded);
        $this->assertStringNotContainsString('abc', $encoded);
        // Non-secret context survives, or the diagnostic would be useless.
        $this->assertStringContainsString('Default', $encoded);
    }
}
