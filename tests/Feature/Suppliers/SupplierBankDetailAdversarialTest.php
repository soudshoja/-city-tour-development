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
