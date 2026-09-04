<?php

namespace Tests\Feature\Security;

use App\Models\Charge;
use App\Models\HesabePayment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Hesabe's webhook (POST api/payment/hesabe-webhook) is unauthenticated and the
 * body it POSTs is unsigned -- reference_number/status/status_code/amount can
 * all be forged. handleHesabeWebhook() must never write (mark a payment paid,
 * add credit, post to the ledger) on the strength of the request body alone;
 * it must confirm the transaction via Hesabe's own GET /api/transaction/{token}
 * enquiry first, using the resolved company's credentials, and only proceed
 * when the enquiry's reference/token/amount/status all agree with the payment
 * it resolved.
 *
 * See .planning/PLAN-GATEWAY-TENANT-ISOLATION-2026-09-02.md §2 (Hesabe section).
 */
class HesabeWebhookVerificationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hesabe' => [
                'api_key' => 'unscoped-fallback-secret',
                'base_url' => 'https://fake-hesabe.test',
                'merchant_code' => 'MERCHANT-GLOBAL',
                'access_code' => 'ACCESS-GLOBAL',
                'iv_key' => '1234567890123456',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * @return array{tenant: array, payment: Payment}
     */
    private function makePendingPayment(float $amount, string $voucherNumber): array
    {
        $tenant = $this->createTenant();

        Charge::factory()->create([
            'name' => 'Hesabe',
            'company_id' => $tenant['company']->id,
            'api_key' => 'company-'.$tenant['company']->id.'-secret',
            'is_active' => true,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $tenant['company']->id,
            'agent_id' => $tenant['agent']->id,
            'client_id' => $tenant['client']->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => $tenant['user']->id,
            'payment_gateway' => 'Hesabe',
            'voucher_number' => $voucherNumber,
            'amount' => $amount,
            'status' => 'pending',
            'send_payment_receipt' => false,
        ]);

        return ['tenant' => $tenant, 'payment' => $payment];
    }

    private function postWebhook(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $payload = array_merge([
            'reference_number' => 'V-HES-1',
            'token' => 'tok-abc-123',
            'status' => 'SUCCESSFUL',
            'status_code' => 1,
            'amount' => '999.999',
            'payment_type' => 'KNET',
            'service_type' => 'Payment Gateway',
            'datetime' => now()->format('Y-m-d H:i:s'),
        ], $overrides);

        return $this->postJson(route('payment.hesabe.webhook'), $payload);
    }

    // ------------------------------------------------------------------
    // (a) Forged body, enquiry finds nothing -> nothing written.
    // ------------------------------------------------------------------

    public function test_forged_webhook_with_no_matching_enquiry_writes_nothing(): void
    {
        Http::fake([
            'fake-hesabe.test/api/transaction/*' => Http::response(['status' => false, 'message' => 'not found'], 404),
            '*' => Http::response([], 200),
        ]);

        ['payment' => $payment] = $this->makePendingPayment(123.45, 'V-FORGE-1');

        $response = $this->postWebhook([
            'reference_number' => 'V-FORGE-1',
            'token' => 'tok-does-not-exist',
            'status' => 'SUCCESSFUL',
            'status_code' => 1,
            'amount' => '123.450',
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertDatabaseCount('hesabe_payments', 0);
    }

    // ------------------------------------------------------------------
    // (b) Enquiry amount != payment amount -> nothing written.
    // ------------------------------------------------------------------

    public function test_enquiry_amount_mismatch_writes_nothing(): void
    {
        ['payment' => $payment] = $this->makePendingPayment(200.00, 'V-MISMATCH-1');

        Http::fake([
            'fake-hesabe.test/api/transaction/*' => Http::response([
                'status' => true,
                'message' => 'Transaction found',
                'data' => [
                    'token' => 'tok-mismatch',
                    'amount' => '50.000',
                    'reference_number' => 'V-MISMATCH-1',
                    'status' => 'SUCCESSFUL',
                    'payment_type' => 'KNET',
                ],
            ], 200),
            '*' => Http::response([], 200),
        ]);

        $response = $this->postWebhook([
            'reference_number' => 'V-MISMATCH-1',
            'token' => 'tok-mismatch',
            'status' => 'SUCCESSFUL',
            'status_code' => 1,
            'amount' => '200.000',
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
        $this->assertDatabaseCount('hesabe_payments', 0);
    }

    // ------------------------------------------------------------------
    // (c) Matching enquiry -> payment completes exactly as before.
    // ------------------------------------------------------------------

    public function test_matching_enquiry_completes_the_payment(): void
    {
        ['tenant' => $tenant, 'payment' => $payment] = $this->makePendingPayment(75.500, 'V-MATCH-1');

        Http::fake([
            'fake-hesabe.test/api/transaction/*' => Http::response([
                'status' => true,
                'message' => 'Transaction found',
                'data' => [
                    'token' => 'tok-match',
                    'amount' => '75.500',
                    'reference_number' => 'V-MATCH-1',
                    'status' => 'SUCCESSFUL',
                    'TransactionID' => 'TXN-1',
                    'TrackID' => 'TRACK-1',
                    'PaymentID' => 'PAY-1',
                    'payment_type' => 'KNET',
                    'datetime' => now()->format('Y-m-d H:i:s'),
                ],
            ], 200),
            '*' => Http::response([], 200),
        ]);

        $response = $this->postWebhook([
            'reference_number' => 'V-MATCH-1',
            'token' => 'tok-match',
            'status' => 'SUCCESSFUL',
            'status_code' => 1,
            'amount' => '75.500',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame('TXN-1', $payment->payment_reference);

        $this->assertDatabaseHas('hesabe_payments', [
            'payment_int_id' => $payment->id,
            'transaction_id' => 'TXN-1',
            'track_id' => 'TRACK-1',
        ]);

        // Requests to Hesabe were made with the resolved company's access code,
        // never a request-supplied value, and hit the transaction enquiry
        // endpoint documented by the hesabi skill.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/transaction/tok-match')
                && $request->hasHeader('accessCode', 'ACCESS-GLOBAL');
        });
    }

    // ------------------------------------------------------------------
    // Voucher collision across two companies: must not silently pick one.
    // ------------------------------------------------------------------

    public function test_voucher_collision_across_companies_is_not_written_without_disambiguation(): void
    {
        ['payment' => $paymentA] = $this->makePendingPayment(10.000, 'V-DUP-1');
        ['payment' => $paymentB] = $this->makePendingPayment(10.000, 'V-DUP-1');

        // Same amount on both sides -> the amount-based disambiguation probe
        // cannot pick a single candidate; refuse to write to either.
        Http::fake([
            'fake-hesabe.test/api/transaction/*' => Http::response([
                'status' => true,
                'message' => 'Transaction found',
                'data' => [
                    'token' => 'tok-dup',
                    'amount' => '10.000',
                    'reference_number' => 'V-DUP-1',
                    'status' => 'SUCCESSFUL',
                ],
            ], 200),
            '*' => Http::response([], 200),
        ]);

        $response = $this->postWebhook([
            'reference_number' => 'V-DUP-1',
            'token' => 'tok-dup',
            'status' => 'SUCCESSFUL',
            'status_code' => 1,
            'amount' => '10.000',
        ]);

        $response->assertStatus(404);

        $paymentA->refresh();
        $paymentB->refresh();
        $this->assertSame('pending', $paymentA->status);
        $this->assertSame('pending', $paymentB->status);
    }
}
