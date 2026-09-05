<?php

namespace Tests\Feature\Suppliers;

use App\Models\AccountingAuditLog;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierBankDetail;
use App\Models\SupplierCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * T14 "Supplier bank details per currency" (accounting-builds PLAN.md §5 T14; L18). HTTP CRUD
 * through the supplier master screen's add/edit/deactivate endpoints: SupplierPolicy::update
 * authorization, company scoping (a user cannot touch a row belonging to another company), the
 * "setting a new default demotes the old one" flow, format validation surfacing as a redirect
 * with a validation error (never a 500), and the AccountingAuditLog row each write produces.
 */
class SupplierBankDetailUiTest extends TestCase
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
        $this->supplier = Supplier::factory()->create(['name' => 'T14 UI Supplier']);

        session(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function userWithoutPermission(): User
    {
        return User::factory()->create(['role_id' => Role::BRANCH]);
    }

    private function userWithPermission(): User
    {
        SpatieRole::firstOrCreate(['name' => 't14-bankdetail-role', 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 't14-bankdetail-role')->first();
        // 'view supplier' is also granted -- the master-screen render test (below) hits
        // SupplierController::show(), which gates on Gate::authorize('view', Supplier::class)
        // BEFORE the role_id check; 'update supplier' alone (the CRUD ability this class mostly
        // exercises) does not satisfy that separate ability.
        $role->givePermissionTo(['update supplier', 'view supplier']);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('t14-bankdetail-role');

        return $user;
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'currency' => 'eur',
            'bank_name' => 'Deutsche Bank',
            'beneficiary_name' => 'T14 UI Supplier',
            'iban' => 'DE89370400440532013000',
            'swift_bic' => 'DEUTDEFF',
            'bank_country' => 'DE',
        ], $overrides);
    }

    // ---------------------------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------------------------

    public function test_store_returns_403_without_permission(): void
    {
        $user = $this->userWithoutPermission();

        $response = $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $this->validPayload());

        $response->assertForbidden();
        $this->assertSame(0, SupplierBankDetail::count());
    }

    public function test_update_and_deactivate_return_403_without_permission(): void
    {
        $row = SupplierBankDetail::create(array_merge($this->validPayload(), [
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'currency' => 'EUR',
            'is_active' => true,
        ]));

        $user = $this->userWithoutPermission();

        $this->actingAs($user)->put(route('suppliers.bank-details.update', $row->id), $this->validPayload())->assertForbidden();
        $this->actingAs($user)->post(route('suppliers.bank-details.deactivate', $row->id))->assertForbidden();

        $this->assertTrue((bool) $row->fresh()->is_active);
    }

    // ---------------------------------------------------------------------------------------
    // Company scoping
    // ---------------------------------------------------------------------------------------

    public function test_update_and_deactivate_of_another_companys_row_are_refused(): void
    {
        $otherOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $otherCompany = Company::factory()->create(['user_id' => $otherOwner->id, 'country_id' => Country::factory()->create()->id]);

        $row = SupplierBankDetail::create(array_merge($this->validPayload(), [
            'company_id' => $otherCompany->id,
            'supplier_id' => $this->supplier->id,
            'currency' => 'EUR',
            'is_active' => true,
        ]));

        $user = $this->userWithPermission();
        session(['company_id' => $this->company->id]);

        $this->actingAs($user)->put(route('suppliers.bank-details.update', $row->id), $this->validPayload(['bank_name' => 'Hijacked']))
            ->assertForbidden();
        $this->actingAs($user)->post(route('suppliers.bank-details.deactivate', $row->id))
            ->assertForbidden();

        $this->assertSame('Deutsche Bank', $row->fresh()->bank_name);
        $this->assertTrue((bool) $row->fresh()->is_active);
    }

    // ---------------------------------------------------------------------------------------
    // Add / edit / deactivate + "setting a new default demotes the old one"
    // ---------------------------------------------------------------------------------------

    public function test_add_edit_deactivate_row(): void
    {
        $user = $this->userWithPermission();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $this->validPayload())
            ->assertRedirect();

        $row = SupplierBankDetail::where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('EUR', $row->currency, 'Currency must be normalized to uppercase.');
        $this->assertSame('Deutsche Bank', $row->bank_name);

        $this->actingAs($user)->put(route('suppliers.bank-details.update', $row->id), $this->validPayload(['bank_name' => 'Commerzbank']))
            ->assertRedirect();
        $this->assertSame('Commerzbank', $row->fresh()->bank_name);

        $this->actingAs($user)->post(route('suppliers.bank-details.deactivate', $row->id))->assertRedirect();
        $this->assertFalse((bool) $row->fresh()->is_active, 'Deactivate must soft-toggle is_active, never delete the row.');
        $this->assertNotNull(SupplierBankDetail::find($row->id), 'The row must still exist after deactivation.');

        $this->actingAs($user)->post(route('suppliers.bank-details.deactivate', $row->id))->assertRedirect();
        $this->assertTrue((bool) $row->fresh()->is_active, 'Deactivate toggles back to active (reactivate).');
    }

    public function test_setting_a_new_default_demotes_the_old_one_instead_of_erroring(): void
    {
        $user = $this->userWithPermission();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $this->validPayload([
            'currency' => 'EUR',
            'bank_name' => 'Bank One',
            'swift_bic' => 'DEUTDEFF',
            'is_default' => '1',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $first = SupplierBankDetail::where('supplier_id', $this->supplier->id)->where('bank_name', 'Bank One')->firstOrFail();
        $this->assertTrue((bool) $first->is_default);

        // A second EUR row, ALSO marked default -- must demote the first, not error.
        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $this->validPayload([
            'currency' => 'EUR',
            'bank_name' => 'Bank Two',
            'swift_bic' => 'ABCDEFGH',
            'is_default' => '1',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $second = SupplierBankDetail::where('supplier_id', $this->supplier->id)->where('bank_name', 'Bank Two')->firstOrFail();

        $this->assertFalse((bool) $first->fresh()->is_default, 'The old default must be demoted.');
        $this->assertTrue((bool) $second->is_default, 'The new row must hold the default.');
        $this->assertSame(1, SupplierBankDetail::where('supplier_id', $this->supplier->id)->where('currency', 'EUR')->where('is_default', true)->count());

        $found = $this->supplier->defaultBankDetailFor('EUR');
        $this->assertSame($second->id, $found->id);
    }

    public function test_editing_a_row_to_become_default_demotes_the_other_current_default(): void
    {
        $user = $this->userWithPermission();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $this->validPayload([
            'currency' => 'EUR', 'bank_name' => 'Bank One', 'swift_bic' => 'DEUTDEFF', 'is_default' => '1',
        ]));
        $first = SupplierBankDetail::where('bank_name', 'Bank One')->firstOrFail();

        // Second row added as NON-default.
        $payloadTwo = $this->validPayload(['currency' => 'EUR', 'bank_name' => 'Bank Two', 'swift_bic' => 'ABCDEFGH']);
        unset($payloadTwo['is_default']);
        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $payloadTwo);
        $second = SupplierBankDetail::where('bank_name', 'Bank Two')->firstOrFail();

        $this->assertTrue((bool) $first->fresh()->is_default);
        $this->assertFalse((bool) $second->fresh()->is_default);

        // Now PUT the second row to become the default via update().
        $this->actingAs($user)->put(route('suppliers.bank-details.update', $second->id), array_merge(
            $this->validPayload(['currency' => 'EUR', 'bank_name' => 'Bank Two', 'swift_bic' => 'ABCDEFGH']),
            ['is_default' => '1']
        ))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue((bool) $second->fresh()->is_default);
        $this->assertFalse((bool) $first->fresh()->is_default, 'Editing a row to default must demote the previous default.');
    }

    // ---------------------------------------------------------------------------------------
    // Format validation (structure/length only -- see the ValidIban/ValidSwiftBic unit tests for
    // the exhaustive rule behaviour; these prove the controller actually wires the rules in and
    // surfaces a friendly validation redirect, never a 500).
    // ---------------------------------------------------------------------------------------

    public function test_store_rejects_a_bad_iban_checksum_length(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $this->validPayload([
            'iban' => 'DE00370400440532013000', // correct shape/length, corrupted checksum
        ]));

        $response->assertSessionHasErrors('iban');
        $this->assertSame(0, SupplierBankDetail::count());
    }

    public function test_store_rejects_a_malformed_swift_code(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $this->validPayload([
            'swift_bic' => 'NOTASWIFT123',
        ]));

        $response->assertSessionHasErrors('swift_bic');
        $this->assertSame(0, SupplierBankDetail::count());
    }

    // ---------------------------------------------------------------------------------------
    // Audit trail
    // ---------------------------------------------------------------------------------------

    public function test_create_update_deactivate_each_write_an_accounting_audit_log_row(): void
    {
        $user = $this->userWithPermission();

        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $this->validPayload());
        $row = SupplierBankDetail::where('supplier_id', $this->supplier->id)->firstOrFail();

        $createLog = AccountingAuditLog::where('subject_type', 'supplier_bank_detail')
            ->where('subject_id', $row->id)->where('action', 'create')->first();
        $this->assertNotNull($createLog);
        $this->assertNull($createLog->transaction_id, 'Master data -- never a ledger document.');
        $this->assertSame($this->company->id, $createLog->company_id);

        $this->actingAs($user)->put(route('suppliers.bank-details.update', $row->id), $this->validPayload(['bank_name' => 'Renamed Bank']));
        $updateLog = AccountingAuditLog::where('subject_type', 'supplier_bank_detail')
            ->where('subject_id', $row->id)->where('action', 'update')->first();
        $this->assertNotNull($updateLog);

        $this->actingAs($user)->post(route('suppliers.bank-details.deactivate', $row->id));
        $deactivateLog = AccountingAuditLog::where('subject_type', 'supplier_bank_detail')
            ->where('subject_id', $row->id)->where('action', 'deactivate')->first();
        $this->assertNotNull($deactivateLog);
    }

    // ---------------------------------------------------------------------------------------
    // The supplier master screen itself renders the bank-detail-card partial without error
    // ---------------------------------------------------------------------------------------

    public function test_supplier_master_screen_renders_the_bank_detail_card(): void
    {
        SupplierCompany::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'is_active' => true,
        ]);

        $user = $this->userWithPermission();
        $this->actingAs($user)->post(route('suppliers.bank-details.store', $this->supplier->id), $this->validPayload());

        $response = $this->actingAs($user)->get(route('suppliers.show', $this->supplier->id));

        $response->assertOk();
        $response->assertSee('Bank details for remittance');
        $response->assertSee('Deutsche Bank');
    }
}
