<?php

namespace Tests\Unit;

use App\Models\Charge;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\ChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role_id' => 1]);
        $this->company = Company::factory()->create(['user_id' => $user->id]);
    }

    // ─── PERCENTAGE CHARGE TESTS ──────────────────────────────────────

    public function test_percentage_charge_for_client_payment(): void
    {
        // self_charge = 5% (back office), amount = 1000
        // Expected: ceil(5% * 1000) = ceil(50) = 50
        $result = ChargeService::calculateChargeForPayment(1000, 5, 'Percent', 0);

        $this->assertEquals(50, $result['back_office_charge']);
        $this->assertEquals(50, $result['total_charge']);
        $this->assertEquals(0, $result['rounding_profit']);
    }

    public function test_percentage_charge_rounds_up(): void
    {
        // self_charge = 2.5%, amount = 1000
        // 2.5% of 1000 = 25.0 → ceil(25.0) = 25
        $result = ChargeService::calculateChargeForPayment(1000, 2.5, 'Percent', 0);
        $this->assertEquals(25, $result['back_office_charge']);

        // self_charge = 2.5%, amount = 333
        // 2.5% of 333 = 8.325 → ceil(8.325) = 9
        $result = ChargeService::calculateChargeForPayment(333, 2.5, 'Percent', 0);
        $this->assertEquals(9, $result['back_office_charge']);
        $this->assertGreaterThan(0, $result['rounding_profit']);
    }

    public function test_percentage_charge_with_extra_charge(): void
    {
        // self_charge = 3%, amount = 1000, extra = 2
        // ceil(3% * 1000) = 30, total = 30 + 2 = 32
        $result = ChargeService::calculateChargeForPayment(1000, 3, 'Percent', 2);

        $this->assertEquals(30, $result['back_office_charge']);
        $this->assertEquals(2, $result['extra_charge']);
        $this->assertEquals(32, $result['total_charge']);
    }

    // ─── FLAT RATE CHARGE TESTS ───────────────────────────────────────

    public function test_flat_rate_charge(): void
    {
        $result = ChargeService::calculateChargeForPayment(1000, 15, 'Flat Rate', 0);

        $this->assertEquals(15, $result['back_office_charge']);
        $this->assertEquals(15, $result['total_charge']);
        $this->assertEquals(0, $result['rounding_profit']); // No rounding for flat
    }

    public function test_flat_rate_with_extra_charge(): void
    {
        $result = ChargeService::calculateChargeForPayment(1000, 15, 'Flat Rate', 3);

        $this->assertEquals(15, $result['back_office_charge']);
        $this->assertEquals(18, $result['total_charge']);
    }

    // ─── ACCOUNTING FEE TESTS ─────────────────────────────────────────

    public function test_accounting_fee_percentage(): void
    {
        // service_charge = 2% (API charge), amount = 1000
        // 2% * 1000 = 20.000
        $result = ChargeService::calculateChargeForAccounting(1000, 2, 'Percent', 0);

        $this->assertEquals(20, $result['contract_charge']);
        $this->assertEquals(20, $result['accounting_fee']);
    }

    public function test_accounting_fee_with_extra(): void
    {
        // service_charge = 2%, amount = 1000, extra = 1.5
        // API: 20 + extra: 1.5 = 21.5
        $result = ChargeService::calculateChargeForAccounting(1000, 2, 'Percent', 1.5);

        $this->assertEquals(20, $result['contract_charge']);
        $this->assertEquals(21.5, $result['accounting_fee']);
    }

    public function test_accounting_fee_flat_rate(): void
    {
        $result = ChargeService::calculateChargeForAccounting(1000, 10, 'Flat Rate', 0);

        $this->assertEquals(10, $result['contract_charge']);
        $this->assertEquals(10, $result['accounting_fee']);
    }

    // ─── MARKUP PROFIT TESTS ──────────────────────────────────────────

    public function test_markup_profit_percentage(): void
    {
        // contract = 2% (API), backOffice = 3.5% (API + 1.5% markup)
        // Markup = (3.5 - 2) / 100 * 1000 = 15.0
        $result = ChargeService::calculateMarkupProfit(1000, 2, 3.5, 'Percent');
        $this->assertEquals(15, $result);
    }

    public function test_markup_profit_flat_rate(): void
    {
        // contract = 10, backOffice = 15 → profit = 5
        $result = ChargeService::calculateMarkupProfit(1000, 10, 15, 'Flat Rate');
        $this->assertEquals(5, $result);
    }

    public function test_no_markup_when_charges_equal(): void
    {
        $result = ChargeService::calculateMarkupProfit(1000, 3, 3, 'Percent');
        $this->assertEquals(0, $result);
    }

    // ─── MAIN CALCULATE METHOD ────────────────────────────────────────

    public function test_calculate_with_payment_method(): void
    {
        $charge = Charge::factory()->create([
            'name' => 'Tap',
            'company_id' => $this->company->id,
        ]);

        $method = PaymentMethod::factory()->create([
            'charge_id' => $charge->id,
            'company_id' => $this->company->id,
            'service_charge' => 2.0,    // API charge: 2%
            'self_charge' => 3.0,       // Back office: 3%
            'extra_charge' => 1.0,      // Flat extra
            'charge_type' => 'Percent',
            'paid_by' => 'Client',
        ]);

        $result = ChargeService::calculate(1000, $this->company->id, $method->id, 'Tap');

        // Client payment: ceil(3% * 1000) + 1 = 30 + 1 = 31
        $this->assertEquals(31, $result['gatewayFee']);
        $this->assertEquals(1031, $result['finalAmount']);

        // Accounting: (2% * 1000) + 1 = 20 + 1 = 21
        $this->assertEquals(21, $result['accountingFee']);

        // Markup: (3-2)/100 * 1000 = 10
        $this->assertEquals(10, $result['markup_profit']);

        $this->assertEquals('Client', $result['paid_by']);
    }

    public function test_calculate_company_pays_gateway_fee(): void
    {
        $charge = Charge::factory()->create([
            'name' => 'Tap',
            'company_id' => $this->company->id,
        ]);

        $method = PaymentMethod::factory()->create([
            'charge_id' => $charge->id,
            'company_id' => $this->company->id,
            'service_charge' => 2.0,
            'self_charge' => 3.0,
            'extra_charge' => 0,
            'charge_type' => 'Percent',
            'paid_by' => 'Company',
        ]);

        $result = ChargeService::calculate(1000, $this->company->id, $method->id, 'Tap');

        // When company pays, gatewayFee = 0 (not added to invoice)
        $this->assertEquals(0, $result['gatewayFee']);
        $this->assertEquals(1000, $result['finalAmount']);

        // Accounting fee still calculated
        $this->assertEquals(20, $result['accountingFee']);

        $this->assertEquals('Company', $result['paid_by']);
    }

    public function test_calculate_falls_back_to_charge_table(): void
    {
        Charge::factory()->create([
            'name' => 'Hesabe',
            'company_id' => $this->company->id,
            'amount' => 1.5,         // API charge 1.5%
            'self_charge' => 2.5,    // Back office 2.5%
            'extra_charge' => 0.5,   // Flat extra
            'charge_type' => 'Percent',
            'paid_by' => 'Client',
        ]);

        // No method ID → falls back to charges table
        $result = ChargeService::calculate(1000, $this->company->id, null, 'Hesabe');

        // Client: ceil(2.5% * 1000) + 0.5 = 25 + 0.5 = 25.5
        $this->assertEquals(25.5, $result['gatewayFee']);

        // Accounting: (1.5% * 1000) + 0.5 = 15 + 0.5 = 15.5
        $this->assertEquals(15.5, $result['accountingFee']);

        // Markup: (2.5-1.5)/100 * 1000 = 10
        $this->assertEquals(10, $result['markup_profit']);
    }

    public function test_calculate_returns_zero_when_no_charge_found(): void
    {
        $result = ChargeService::calculate(1000, $this->company->id, null, 'NonExistentGateway');

        $this->assertEquals(1000, $result['finalAmount']);
        $this->assertEquals(0, $result['gatewayFee']);
        $this->assertEquals(0, $result['accountingFee']);
        $this->assertEquals(0, $result['markup_profit']);
    }

    public function test_rounding_profit_captured_on_odd_amounts(): void
    {
        $charge = Charge::factory()->create([
            'name' => 'Tap',
            'company_id' => $this->company->id,
        ]);

        $method = PaymentMethod::factory()->create([
            'charge_id' => $charge->id,
            'company_id' => $this->company->id,
            'service_charge' => 2.5,
            'self_charge' => 2.5,
            'extra_charge' => 0,
            'charge_type' => 'Percent',
            'paid_by' => 'Client',
        ]);

        // 2.5% of 333 = 8.325, ceil = 9, rounding profit = 0.675
        $result = ChargeService::calculate(333, $this->company->id, $method->id, 'Tap');

        $this->assertEquals(9, $result['gatewayFee']);
        $this->assertEquals(0.675, $result['rounding_profit']);
    }
}
