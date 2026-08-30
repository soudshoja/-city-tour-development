<?php

namespace Tests\Feature\Accounting;

use App\Models\Charge;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * KEY: invoice-w4d. W4.D (.planning/accounting-waves/w4/w4-brief.md item 3): "the company setting
 * bearer.gateway_fee=client is allowed at save (today it is refused-at-save; find the refusal and
 * lift it ONLY after the gross-up posting exists)".
 *
 * NOTE ON WHAT WAS ACTUALLY FOUND (see build report): no literal validation rule or guard
 * rejecting `Charge::paid_by === 'Client'` exists anywhere in the pre-W4.D tree --
 * ChargeController::store()/update() validated `paid_by` with a bare `'required'` (any string
 * passed), and resources/views/charges/create.blade.php already offers "Client" as a selectable
 * option. What made bearer=client effectively unsafe was NOT a save-time refusal but
 * `createGatewayProfitEntries()`'s double-booked posting, now deleted. This lane makes 'Client' an
 * EXPLICITLY validated, intentionally-supported value (`'required|in:Company,Client'`, replacing
 * the bare `'required'`) rather than an accidental any-string acceptance, and proves the full
 * save path -- validation and persistence -- for a client-borne gateway now works end to end.
 */
class ChargeControllerW4DBearerClientTest extends TestCase
{
    use RefreshDatabase;

    private function storeRules(): array
    {
        // Mirrors ChargeController::store()'s own validate() call exactly.
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'type' => 'required|string|max:255',
            'charge_type' => 'required',
            'paid_by' => 'required|in:Company,Client',
            'amount' => 'required|numeric',
            'self_charge' => 'required|numeric|gte:amount',
            'extra_charge' => 'nullable|numeric|min:0',
        ];
    }

    public function test_paid_by_client_passes_validation(): void
    {
        $validator = Validator::make([
            'name' => 'ClientPaysGateway',
            'type' => 'Payment Gateway',
            'charge_type' => 'Percent',
            'paid_by' => 'Client',
            'amount' => 2.5,
            'self_charge' => 3.0,
        ], $this->storeRules());

        $this->assertFalse($validator->fails(), 'bearer=client must be accepted at save.');
    }

    public function test_paid_by_company_still_passes_validation_unchanged(): void
    {
        $validator = Validator::make([
            'name' => 'CompanyPaysGateway',
            'type' => 'Payment Gateway',
            'charge_type' => 'Percent',
            'paid_by' => 'Company',
            'amount' => 2.5,
            'self_charge' => 3.0,
        ], $this->storeRules());

        $this->assertFalse($validator->fails(), 'bearer=company must remain accepted (unchanged).');
    }

    public function test_paid_by_garbage_value_is_rejected(): void
    {
        // Documents the tightening: 'required' alone (pre-W4.D) accepted ANY string; the new
        // 'in:Company,Client' rule makes 'Client'/'Company' the only two intentional values.
        $validator = Validator::make([
            'name' => 'GarbageGateway',
            'type' => 'Payment Gateway',
            'charge_type' => 'Percent',
            'paid_by' => 'NotARealBearer',
            'amount' => 2.5,
            'self_charge' => 3.0,
        ], $this->storeRules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('paid_by', $validator->errors()->toArray());
    }

    /**
     * End-to-end: a real HTTP POST to charges.store with paid_by=Client, as a company-role user
     * with the 'create charges' permission, actually persists the row -- proving the full save
     * path (route -> policy -> validation -> Charge::create()), not just the validation rule in
     * isolation.
     */
    public function test_charge_controller_store_persists_paid_by_client_end_to_end(): void
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        CoaSeeder::run($company->id);

        $role = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $company->id,
        ]);
        $user->assignRole($role);
        $role->givePermissionTo(['create charges']);

        Company::forgetModuleCache();

        $response = $this->actingAs($user)->post(route('charges.store'), [
            'name' => 'ClientPaysGatewayE2E',
            'type' => 'Payment Gateway',
            'description' => 'Custom gateway, client bears the fee',
            'charge_type' => 'Percent',
            'amount' => 2.5,
            'self_charge' => 3.0,
            'paid_by' => 'Client',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('charges', [
            'name' => 'ClientPaysGatewayE2E',
            'company_id' => $company->id,
            'paid_by' => 'Client',
        ]);
    }
}
