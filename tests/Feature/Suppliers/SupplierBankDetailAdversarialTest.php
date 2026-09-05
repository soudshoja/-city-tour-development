<?php

namespace Tests\Feature\Suppliers;

use App\Models\AccountingAuditLog;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierBankDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Adversarial verifier probe for T14 -- NOT part of the builder's own test suite. Checks
 * things the packet claims but does not directly pin: IBAN/SWIFT storage normalization,
 * duplicate-IBAN-across-suppliers behaviour, and a hold area for ad hoc mutation checks.
 */
class SupplierBankDetailAdversarialTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $companyOwner->id, 'country_id' => $country->id]);
        $this->supplier = Supplier::factory()->create(['name' => 'Adversarial Supplier']);

        session(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function userWithPermission(): User
    {
        SpatieRole::firstOrCreate(['name' => 't14-adv-role', 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 't14-adv-role')->first();
        $role->givePermissionTo(['update supplier', 'view supplier']);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('t14-adv-role');

        return $user;
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'currency' => 'eur',
            'bank_name' => 'Deutsche Bank',
            'beneficiary_name' => 'Adversarial Supplier',
            'iban' => 'DE89370400440532013000',
            'swift_bic' => 'DEUTDEFF',
            'bank_country' => 'DE',
        ], $overrides);
    }

    public function test_iban_is_stored_normalized_uppercase_no_spaces(): void
    {
        $user = $this->userWithPermission();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier), $this->validPayload([
            'iban' => 'de89 3704 0044 0532 0130 00',
        ]))->assertSessionDoesntHaveErrors();

        $row = SupplierBankDetail::first();
        $this->assertNotNull($row, 'Row should have been created.');
        $this->assertSame('DE89370400440532013000', $row->iban, 'IBAN should be normalized to uppercase/no-space on storage.');
    }

    public function test_swift_is_stored_normalized_uppercase_no_whitespace(): void
    {
        $user = $this->userWithPermission();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier), $this->validPayload([
            'swift_bic' => ' deutdeff ',
        ]))->assertSessionDoesntHaveErrors();

        $row = SupplierBankDetail::first();
        $this->assertNotNull($row);
        $this->assertSame('DEUTDEFF', $row->swift_bic, 'SWIFT/BIC should be normalized to uppercase/trimmed on storage.');
    }

    /**
     * FOCUS-1 round trip: a Kuwaiti IBAN typed the way a human reads it off a bank letter
     * (lowercase country code, ISO 13616 display grouping) must be STORED in canonical form, and
     * normalization must not change whether it validates -- the mod-97 checksum is computed on
     * the normalized string, so "kw81 cbku ..." and "KW81CBKU..." are the same IBAN.
     */
    public function test_lowercase_spaced_kuwaiti_iban_round_trips_to_canonical_form(): void
    {
        $user = $this->userWithPermission();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier), $this->validPayload([
            'currency' => 'kwd',
            'iban' => 'kw81 cbku 0000 0000 0000 1234 5601 01',
            'swift_bic' => 'cbkukwkw',
            'bank_country' => 'KW',
        ]))->assertSessionDoesntHaveErrors();

        $row = SupplierBankDetail::first();
        $this->assertNotNull($row, 'A lowercase/spaced but otherwise valid KW IBAN must pass validation.');
        $this->assertSame('KW81CBKU0000000000001234560101', $row->iban);
        $this->assertSame('CBKUKWKW', $row->swift_bic);
        $this->assertSame('KWD', $row->currency);
    }

    /**
     * The earlier verify pass pinned normalization on the STORE path only. The update path runs
     * its own write and is pinned here.
     */
    public function test_iban_and_swift_are_normalized_on_the_update_path(): void
    {
        $user = $this->userWithPermission();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier), $this->validPayload())
            ->assertSessionDoesntHaveErrors();
        $row = SupplierBankDetail::first();

        $this->actingAs($user)->put(route('suppliers.bank-details.update', $row), $this->validPayload([
            'currency' => 'usd',
            'iban' => 'gb82 west 1234 5698 7654 32',
            'swift_bic' => ' natwgb2l ',
            'intermediary_swift_bic' => 'deutdeff500',
        ]))->assertSessionDoesntHaveErrors();

        $row->refresh();
        $this->assertSame('GB82WEST12345698765432', $row->iban);
        $this->assertSame('NATWGB2L', $row->swift_bic);
        $this->assertSame('DEUTDEFF500', $row->intermediary_swift_bic);
        $this->assertSame('USD', $row->currency);
    }

    /**
     * Bypass path: nothing in app/ writes this table today except SupplierController, but a
     * seeder / import / tinker fix-up / factory / direct create() is one line away (and this
     * task's own SupplierBankDetailTest already writes this way). Normalization is a model
     * invariant, so a write that never touches the controller must still land canonical.
     */
    public function test_a_direct_model_save_normalizes_currency_iban_and_swift(): void
    {
        $row = SupplierBankDetail::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'currency' => 'eur',
            'bank_name' => 'Deutsche Bank',
            'beneficiary_name' => 'Adversarial Supplier',
            'iban' => 'de89 3704 0044 0532 0130 00',
            'swift_bic' => ' deutdeff ',
            'intermediary_swift_bic' => 'natwgb2l',
            'bank_country' => 'DE',
        ]);

        $this->assertSame('EUR', $row->fresh()->currency);
        $this->assertSame('DE89370400440532013000', $row->fresh()->iban);
        $this->assertSame('DEUTDEFF', $row->fresh()->swift_bic);
        $this->assertSame('NATWGB2L', $row->fresh()->intermediary_swift_bic);
    }

    /**
     * FOCUS-1 consistency: the default-per-currency swap and the voucher-side lookup must agree
     * on the normalized currency -- adding a "kwd" default after a "KWD" default must demote the
     * first (one default, not two), and `defaultBankDetailFor('KWD')` must return the new one.
     */
    public function test_default_swap_and_lookup_agree_on_the_normalized_currency(): void
    {
        $user = $this->userWithPermission();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier), $this->validPayload([
            'currency' => 'KWD', 'bank_name' => 'First Bank', 'is_default' => 1,
        ]))->assertSessionDoesntHaveErrors();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier), $this->validPayload([
            'currency' => 'kwd', 'bank_name' => 'Second Bank', 'is_default' => 1,
        ]))->assertSessionDoesntHaveErrors();

        $this->assertSame(1, SupplierBankDetail::where('supplier_id', $this->supplier->id)
            ->where('is_default', true)->where('is_active', true)->count(), 'The lowercase-currency default must demote the uppercase one, not sit alongside it.');

        $resolved = $this->supplier->fresh()->defaultBankDetailFor('KWD');
        $this->assertNotNull($resolved);
        $this->assertSame('Second Bank', $resolved->bank_name);
        $this->assertSame('KWD', $resolved->currency);
    }

    public function test_duplicate_iban_across_different_suppliers_is_currently_allowed(): void
    {
        $user = $this->userWithPermission();
        $otherSupplier = Supplier::factory()->create(['name' => 'Other Supplier']);

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier), $this->validPayload())
            ->assertSessionDoesntHaveErrors();
        $this->actingAs($user)->post(route('suppliers.bank-details.store', $otherSupplier), $this->validPayload())
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2, SupplierBankDetail::where('iban', 'DE89370400440532013000')->count(), 'No uniqueness constraint on iban -- documenting current (allowed) behaviour.');
    }
}
