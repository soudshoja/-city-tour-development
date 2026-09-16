<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\Supplier;
use App\Services\Accounting\TaskPayablePositionResolver;
use App\Services\CompanyProvisioner;
use App\Support\CompanyRegistrationData;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7 ROUND 2, finding **F5** — a defect THIS PR introduced.
 *
 * CT-A7-2 added `'supplier_id' => $supplier->id` to `SupplierActivationService::activate()`'s
 * `$data` array, which is spread into **both** the payable leaf and the **cost** leaf. But
 * `App\Models\Supplier::payableAccount()` is declared `hasOne(Account::class, 'supplier_id')` with
 * no root, parent or company constraint — so with the column on two rows per supplier per company
 * it matches two, and `hasOne` returns whichever the database hands back first. It can return the
 * **expense** account where a payable account is meant.
 *
 * There are no call sites today, so it is latent rather than live; it is still this PR's to fix,
 * and "no call sites" is exactly the state in which a wrong relation is adopted by the next one.
 *
 * ── The fix, and why it is this one ────────────────────────────────────────────────────────────
 * `accounts.supplier_id` is stamped on the PAYABLE leaf only. The alternative — constraining the
 * relation by root — was rejected because the column's meaning is the problem, not the query:
 * `accounts.supplier_id` is read by `BankPaymentController::resolveSupplierBankDetail()` to answer
 * "is this payment target a supplier's account?" and, since CT-A7-2, by `voucherPartyRef()` to
 * decide the PARTY on a payable line. A cost account answers neither question — it is where that
 * supplier's cost is EXPENSED, not what is owed to them — so the honest fix is to stop claiming it
 * does. Constraining `payableAccount()` would have left the column lying to its other two readers.
 *
 * `accounts.supplier_company_id` stays on both leaves: it is a pre-existing "this account belongs
 * to that supplier-company pairing" link, it is not what `payableAccount()` keys on, and removing
 * it would change behaviour this finding does not name. Instead `voucherPartyRef()`'s
 * `supplier_company_id` fallback is now constrained to accounts inside the AP subtree, so it can
 * never derive a supplier party from a cost leaf either.
 */
class SupplierLeafIdentityF5Test extends AccountingTestCase
{
    /** @return array{0: Supplier, 1: Company} */
    private function activateSupplierOnAFreshCompany(): array
    {
        $supplier = Supplier::factory()->create(['has_flight' => true, 'has_hotel' => false]);

        $unique = uniqid();
        $company = app(CompanyProvisioner::class)->provision(
            CompanyRegistrationData::fromArray([
                'company_name' => 'CtA7 F5 Co '.$unique,
                'company_code' => 'CTA7F5-'.$unique,
                'country_id' => Country::factory()->create()->id,
                'company_email' => "owner-{$unique}@example.test",
                'owner_name' => 'Test Owner',
                'owner_email' => "owner-{$unique}@example.test",
                'owner_password' => 'password12345',
                'currency' => 'KWD',
                'supplier_ids' => [$supplier->id],
            ])
        );

        $this->trackCompanyForInvariants((int) $company->id);

        return [$supplier, $company];
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The defect itself: exactly ONE account may carry this supplier's id, and it must be the
     * payable leaf.
     */
    public function test_only_the_payable_leaf_carries_the_supplier_id(): void
    {
        [$supplier, $company] = $this->activateSupplierOnAFreshCompany();

        $stamped = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('supplier_id', $supplier->id)
            ->whereNull('deleted_at')
            ->get();

        $this->assertCount(
            1,
            $stamped,
            'F5: exactly one account per supplier per company may carry accounts.supplier_id — '
            .'CT-A7-2 spread it into the cost leaf as well, which makes '
            .'Supplier::payableAccount() (an unconstrained hasOne on this column) non-deterministic. '
            .'Found: '.$stamped->pluck('name')->implode(', ')
        );

        $apSubtree = (new TaskPayablePositionResolver)->apSubtreeIds((int) $company->id);

        $this->assertContains(
            (int) $stamped->first()->id,
            $apSubtree,
            'and the one account carrying it must be inside Accounts Payable — not the cost leaf'
        );
    }

    /**
     * The consequence, through the relation the finding names.
     */
    public function test_the_payable_account_relation_returns_the_payable_leaf(): void
    {
        [$supplier, $company] = $this->activateSupplierOnAFreshCompany();

        $resolved = $supplier->fresh()->payableAccount;

        $this->assertNotNull($resolved, 'Supplier::payableAccount() must resolve after activation');
        $this->assertContains(
            (int) $resolved->id,
            (new TaskPayablePositionResolver)->apSubtreeIds((int) $company->id),
            'Supplier::payableAccount() must return a PAYABLE account — with the column on two rows '
            .'this unconstrained hasOne can hand back the expense account instead'
        );
    }

    /**
     * The cost leaf keeps its `supplier_company_id` pairing link (untouched by this finding) but
     * must no longer claim to BE the supplier's account.
     */
    public function test_the_cost_leaf_keeps_its_pairing_link_but_not_the_supplier_id(): void
    {
        [$supplier, $company] = $this->activateSupplierOnAFreshCompany();

        $apSubtree = (new TaskPayablePositionResolver)->apSubtreeIds((int) $company->id);

        $costLeaf = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', $supplier->name)
            ->whereNotIn('id', $apSubtree)
            ->whereNull('deleted_at')
            ->first();

        $this->assertNotNull($costLeaf, 'the activation must still mint a cost leaf for this supplier');
        $this->assertNull($costLeaf->supplier_id, 'the cost leaf must NOT carry accounts.supplier_id');
        $this->assertNotNull(
            $costLeaf->supplier_company_id,
            'but it keeps the supplier_companies pairing link, which is not what payableAccount() keys on'
        );
    }
}
