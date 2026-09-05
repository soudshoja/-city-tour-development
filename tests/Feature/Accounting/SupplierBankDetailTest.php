<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierBankDetail;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T14 "Supplier bank details per currency" (accounting-builds PLAN.md §5 T14; L18). Model/DB-level
 * behaviour: the generated-column "at most one DEFAULT per (supplier, currency)" guard, the
 * currency scope, and the never-select-a-soft-deleted/inactive-row selection rule
 * ({@see Supplier::defaultBankDetailFor()}). HTTP CRUD/authorization is covered by
 * SupplierBankDetailUiTest; the payment-voucher selection flow by
 * BankPaymentSupplierBankSelectionTest.
 */
class SupplierBankDetailTest extends TestCase
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
        $this->supplier = Supplier::factory()->create(['name' => 'T14 Bank Detail Supplier']);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function detailAttrs(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'currency' => 'EUR',
            'bank_name' => 'Deutsche Bank',
            'beneficiary_name' => 'T14 Bank Detail Supplier',
            'iban' => 'DE89370400440532013000',
            'swift_bic' => 'DEUTDEFF',
            'bank_country' => 'DE',
            'is_default' => true,
            'is_active' => true,
        ], $overrides);
    }

    // ---------------------------------------------------------------------------------------
    // DB-level "at most one default per (supplier, currency)" guard
    // ---------------------------------------------------------------------------------------

    public function test_db_level_constraint_rejects_a_second_default_active_row_for_the_same_supplier_and_currency(): void
    {
        SupplierBankDetail::create($this->detailAttrs());

        $this->expectException(QueryException::class);

        SupplierBankDetail::create($this->detailAttrs([
            'bank_name' => 'A Different Bank',
            'swift_bic' => 'ABCDEFGH',
        ]));
    }

    public function test_a_second_default_for_a_different_currency_is_fine(): void
    {
        SupplierBankDetail::create($this->detailAttrs(['currency' => 'EUR']));
        $usd = SupplierBankDetail::create($this->detailAttrs([
            'currency' => 'USD',
            'iban' => null,
            'account_number' => '1234567890',
            'swift_bic' => 'CHASUS33',
            'bank_country' => 'US',
        ]));

        $this->assertTrue($usd->wasRecentlyCreated);
        $this->assertSame(2, SupplierBankDetail::where('supplier_id', $this->supplier->id)->count());
    }

    public function test_a_second_default_for_a_different_supplier_is_fine(): void
    {
        SupplierBankDetail::create($this->detailAttrs());

        $otherSupplier = Supplier::factory()->create();
        $row = SupplierBankDetail::create($this->detailAttrs([
            'supplier_id' => $otherSupplier->id,
            'swift_bic' => 'ABCDEFGH',
        ]));

        $this->assertTrue($row->wasRecentlyCreated);
    }

    public function test_a_non_default_row_never_collides_with_the_constraint(): void
    {
        SupplierBankDetail::create($this->detailAttrs());

        $row = SupplierBankDetail::create($this->detailAttrs([
            'is_default' => false,
            'swift_bic' => 'ABCDEFGH',
        ]));

        $this->assertTrue($row->wasRecentlyCreated);
        $this->assertSame(2, SupplierBankDetail::where('supplier_id', $this->supplier->id)->where('currency', 'EUR')->count());
    }

    public function test_an_inactive_default_never_collides_with_the_constraint(): void
    {
        SupplierBankDetail::create($this->detailAttrs(['is_active' => false]));

        $row = SupplierBankDetail::create($this->detailAttrs(['swift_bic' => 'ABCDEFGH']));

        $this->assertTrue($row->wasRecentlyCreated);
    }

    // ---------------------------------------------------------------------------------------
    // Scopes / defaultBankDetailFor()
    // ---------------------------------------------------------------------------------------

    public function test_for_currency_scope_matches_case_insensitively(): void
    {
        SupplierBankDetail::create($this->detailAttrs(['currency' => 'EUR']));

        $this->assertSame(1, SupplierBankDetail::forCurrency('eur')->count());
        $this->assertSame(1, SupplierBankDetail::forCurrency('EUR')->count());
        $this->assertSame(0, SupplierBankDetail::forCurrency('USD')->count());
    }

    public function test_default_bank_detail_for_returns_the_matching_default_row(): void
    {
        $eur = SupplierBankDetail::create($this->detailAttrs(['currency' => 'EUR']));

        $found = $this->supplier->defaultBankDetailFor('EUR');

        $this->assertNotNull($found);
        $this->assertSame($eur->id, $found->id);
    }

    public function test_default_bank_detail_for_returns_null_when_no_row_exists_for_that_currency(): void
    {
        SupplierBankDetail::create($this->detailAttrs(['currency' => 'EUR']));

        $this->assertNull($this->supplier->defaultBankDetailFor('USD'), 'Must never silently fall back to another currency.');
    }

    public function test_default_bank_detail_for_never_returns_a_deactivated_row(): void
    {
        $row = SupplierBankDetail::create($this->detailAttrs());
        $row->update(['is_active' => false]);

        $this->assertNull($this->supplier->defaultBankDetailFor('EUR'));
    }

    public function test_default_bank_detail_for_never_returns_a_soft_deleted_row(): void
    {
        $row = SupplierBankDetail::create($this->detailAttrs());
        $row->delete();

        $this->assertNull($this->supplier->defaultBankDetailFor('EUR'));
        $this->assertNotNull(SupplierBankDetail::withTrashed()->find($row->id), 'Soft delete must not hard-remove the row.');
    }

    public function test_default_bank_detail_for_never_returns_a_non_default_row_even_if_it_is_the_only_one(): void
    {
        SupplierBankDetail::create($this->detailAttrs(['is_default' => false]));

        $this->assertNull($this->supplier->defaultBankDetailFor('EUR'));
    }
}
