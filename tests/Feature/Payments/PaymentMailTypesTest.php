<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMailTypeEnum;
use App\Mail\PaymentMail;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PaymentMail's PAYMENT_LINK and PAYMENT_FAILURE branches used to be a bare
 * `throw new Exception('… not implemented yet')` with the real body commented
 * out underneath, so the platform could never email a payment link at all --
 * only WhatsApp delivery worked. These cover the implemented behaviour.
 *
 * The branding assertions matter as much as the delivery ones: the template
 * previously said "City Tour" in the brand line, the greeting, the sign-off and
 * the copyright, which meant every tenant's client was thanked for choosing a
 * different agency.
 */
class PaymentMailTypesTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(array $overrides = []): Payment
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
            'name' => 'Northwind Travel',
            'currency' => 'KWD',
        ]);
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $agent = Agent::factory()->create(['branch_id' => $branch->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        return Payment::create(array_merge([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'voucher_number' => 'VOU-2026-00042',
            'from' => $client->full_name,
            'pay_to' => $company->name,
            'currency' => 'KWD',
            'payment_date' => now(),
            'amount' => 125.500,
            'service_charge' => 0,
            'status' => 'pending',
            'payment_gateway' => 'MyFatoorah',
            'payment_url' => 'https://pay.example.test/abc123',
            'expiry_date' => now()->addDays(2),
        ], $overrides));
    }

    public function test_payment_link_mail_renders_with_the_agency_name_and_the_link(): void
    {
        $payment = $this->makePayment();

        $rendered = (new PaymentMail($payment->id, PaymentMailTypeEnum::PAYMENT_LINK))->render();

        $this->assertStringContainsString('https://pay.example.test/abc123', $rendered);
        $this->assertStringContainsString('Northwind Travel', $rendered);
        $this->assertStringNotContainsString('City Tour', $rendered);
    }

    public function test_payment_link_mail_refuses_to_send_a_link_that_does_not_exist_yet(): void
    {
        $payment = $this->makePayment(['payment_url' => null]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has no payment_url');

        (new PaymentMail($payment->id, PaymentMailTypeEnum::PAYMENT_LINK))->render();
    }

    public function test_failure_mail_offers_the_same_link_again_while_it_is_still_valid(): void
    {
        $payment = $this->makePayment(['status' => 'failed']);

        $rendered = (new PaymentMail($payment->id, PaymentMailTypeEnum::PAYMENT_FAILURE))->render();

        $this->assertStringContainsString('did not go through', $rendered);
        $this->assertStringContainsString('https://pay.example.test/abc123', $rendered);
        $this->assertStringContainsString('VOU-2026-00042', $rendered);
    }

    public function test_failure_mail_asks_for_a_new_link_once_the_old_one_has_expired(): void
    {
        $payment = $this->makePayment([
            'status' => 'failed',
            'expiry_date' => now()->subDay(),
        ]);

        $rendered = (new PaymentMail($payment->id, PaymentMailTypeEnum::PAYMENT_FAILURE))->render();

        $this->assertStringNotContainsString('https://pay.example.test/abc123', $rendered);
        $this->assertStringContainsString('new payment link', $rendered);
    }
}
