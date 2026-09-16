<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Http\Controllers\AccountingController;
use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Services\SupplierActivationService;
use App\Support\CompanyRegistrationData;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Facades\View;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7 ROUND 2, findings **F6** and **F7**.
 *
 * **F6** — `AccountingController::createBankPayment()` built its payment-target dropdowns and its
 * journal list with no `company_id` at all, while its own siblings `createPayableDetail()` and
 * `createReceivableDetail()` scope by `getCompanyId($user)`. It matters more since CT-A7-2 than it
 * did before: that dropdown populates `bank_payments.target_account_id`, and
 * `BankPaymentController::voucherPartyRef()` now READS that account to decide the party stamped on
 * a payable line — a foreign account reachable from the picker is a foreign party reachable into
 * this company's ledger. The method is still unreachable in practice (no route, and its blade does
 * not exist), so this is a latent leak closed, not an exploited one; it is exercised here the same
 * way `BankPaymentIntegrityTest` already exercises it, by calling it directly.
 *
 * **F7 — DOES NOT REPRODUCE, and this file is the evidence plus the pin.**
 *
 * The finding was that `activate()` mints with `new Account(...)->save()` and no "does this
 * supplier already have a leaf?" check, so a deactivate → reactivate cycle would mint a second
 * payable leaf and a second cost leaf. A fix was written for it and then REVERTED, because the
 * cycle cannot reach the mint:
 *
 *   1. `activate()` begins with a lookup of `SupplierCompany` by (supplier, company) ALONE and,
 *      when one exists, sets `is_active = true` and RETURNS — before the credential block, before
 *      the category loop, before any account is created. `is_active` is not part of that lookup, so
 *      a deactivated pairing takes the same early return. The assertion in
 *      `test_reactivating_a_supplier_does_not_mint_a_second_pair_of_leaves()` pins that return
 *      value by its exact message.
 *   2. Reaching the mint a second time therefore needs the pivot ROW to be gone, and nothing in
 *      `app/` ever deletes one: `grep -rn "supplier_companies|SupplierCompany" app/ | grep -iE
 *      "delete|destroy|detach|truncate"` returns nothing.
 *
 * A mutation proves the fix was dead code rather than protection: with the leaf-reuse guard removed
 * the duplicate-leaf assertion still passed, because nothing ever got that far. Shipping an
 * unreachable branch would have contradicted this project's own stale-code rule, so the production
 * change was reverted and these three cases were kept as a REGRESSION PIN — if a future change
 * removes that early return, or adds a pivot deleter, they fail.
 *
 * NOT covered, and stated rather than implied: two concurrent `activate()` calls for the same
 * (supplier, company) could both pass the early return and both mint, because there is no unique
 * index on `supplier_companies (supplier_id, company_id)`. That is a different defect from the one
 * F7 describes and is not this lane's to fix.
 */
class ScopeAndIdempotencyF6F7Test extends AccountingTestCase
{
    private ?string $stubViewRoot = null;

    /**
     * `createBankPayment()` ends in `view('accounting.bank-payment.create', ...)`, and that blade
     * genuinely does not exist — which is half of why the method is unreachable. Rather than mock
     * the view layer (this suite has no mocks and keeps it that way), a REAL empty blade is placed
     * in a temp directory and that directory is registered as an extra view location, so the
     * controller runs unmodified and its view DATA can be asserted.
     */
    private function registerStubView(): void
    {
        $this->stubViewRoot = sys_get_temp_dir().'/cta7-f6-views-'.uniqid();
        mkdir($this->stubViewRoot.'/accounting/bank-payment', 0777, true);
        file_put_contents($this->stubViewRoot.'/accounting/bank-payment/create.blade.php', '');

        View::addLocation($this->stubViewRoot);
    }

    protected function tearDown(): void
    {
        if ($this->stubViewRoot !== null && is_dir($this->stubViewRoot)) {
            @unlink($this->stubViewRoot.'/accounting/bank-payment/create.blade.php');
            @rmdir($this->stubViewRoot.'/accounting/bank-payment');
            @rmdir($this->stubViewRoot.'/accounting');
            @rmdir($this->stubViewRoot);
            $this->stubViewRoot = null;
        }

        parent::tearDown();
    }

    // ── F6 ──────────────────────────────────────────────────────────────────────────────────────

    /**
     * The PRECISE shape of F6, measured rather than assumed.
     *
     * `App\Models\Account` does carry a `BelongsToCompany` global scope — but it binds only when
     * `Auth::check()` AND `getCompanyId()` returns non-null. Two earlier drafts of this test were
     * WRONG about when that happens, and the mutation not biting is what caught each of them: a
     * COMPANY user with a company is already protected by the global scope, and an ADMIN never
     * reaches the unscoped state either, because `getCompanyId()` falls back to
     * `session('company_id', 1)` for role 1 by design. The reachable unscoped state is a
     * COMPANY-role user who is linked to no company — then the scope no-ops and the three
     * unscoped queries in `createBankPayment()` returned EVERY tenant's `Accounts Payable` /
     * `Accounts Receivable` children and every tenant's payable and expense journal lines.
     * The finding is real and narrower than "unscoped queries" suggests; saying so is the point.
     *
     * Post-fix the screen offers NOTHING when no company is selected, which is the same failure
     * direction its siblings `createPayableDetail()` / `createReceivableDetail()` already take.
     */
    public function test_the_bank_payment_screen_offers_nothing_when_no_company_is_selected(): void
    {
        $this->registerStubView();

        $mine = Company::factory()->create();
        CoaSeeder::run((int) $mine->id);
        $this->trackCompanyForInvariants((int) $mine->id);

        $theirs = Company::factory()->create();
        CoaSeeder::run((int) $theirs->id);
        $this->trackCompanyForInvariants((int) $theirs->id);

        // A COMPANY-role user who is not linked to any company. `getCompanyId()` returns null only
        // here and for an unknown role — an ADMIN always falls back to `session('company_id', 1)`
        // by design (see the helper's own comment on why that fallback was reverted to non-null),
        // so an admin is NOT the unscoped state, which is worth stating because it was the first
        // thing this test tried.
        $stranded = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->actingAs($stranded);
        session()->forget('company_id');

        $this->assertNull(
            getCompanyId($stranded),
            'the fixture must reproduce the UNSCOPED state — BelongsToCompany no-ops when getCompanyId() is null'
        );

        $data = app(AccountingController::class)->createBankPayment()->getData();

        $this->assertCount(
            0,
            $data['suppliers'],
            'F6: with no resolvable company the payment-target dropdown must offer NOTHING — before the '
            .'fix it offered every other tenant Accounts Payable children too, and that list feeds '
            .'bank_payments.target_account_id, which voucherPartyRef() now reads'
        );
        $this->assertCount(0, $data['clients'], 'F6: nor every other tenant Accounts Receivable children');
        $this->assertCount(0, $data['JournalEntrysPayable'], 'F6: nor every other tenant journal lines');
    }

    /**
     * And the screen still works for the normal case: a company user sees their own accounts.
     */
    public function test_the_bank_payment_screen_still_offers_this_companys_accounts(): void
    {
        $this->registerStubView();

        $mine = Company::factory()->create();
        CoaSeeder::run((int) $mine->id);
        $this->trackCompanyForInvariants((int) $mine->id);

        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        Company::where('id', $mine->id)->update(['user_id' => $owner->id]);

        $this->actingAs($owner);
        session(['company_id' => $mine->id]);

        $data = app(AccountingController::class)->createBankPayment()->getData();

        $this->assertNotEmpty($data['suppliers'], 'the screen must still offer this company payable accounts');
        $this->assertSame(
            [(int) $mine->id],
            collect($data['suppliers'])->pluck('company_id')->unique()->map(fn ($id) => (int) $id)->values()->all()
        );
    }

    // ── F7 ──────────────────────────────────────────────────────────────────────────────────────

    /** @return array{0: Supplier, 1: Company} */
    private function provisionWithSupplier(): array
    {
        $supplier = Supplier::factory()->create(['has_flight' => true, 'has_hotel' => false]);

        $unique = uniqid();
        $company = app(CompanyProvisioner::class)->provision(
            CompanyRegistrationData::fromArray([
                'company_name' => 'CtA7 F7 Co '.$unique,
                'company_code' => 'CTA7F7-'.$unique,
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

    /**
     * Every account this supplier's activation minted, counted by the SUPPLIER rather than by a
     * pivot id. Keyed on the pivot id in a first draft, which hid half the defect: a
     * deactivate -> reactivate cycle used to create a SECOND `supplier_companies` row, so leaves
     * minted under the new pivot were simply not counted and the test passed while four accounts
     * existed.
     */
    private function leavesFor(int $companyId, Supplier $supplier): int
    {
        $pivotIds = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $companyId)->pluck('id');

        return Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($pivotIds, $supplier) {
                $q->whereIn('supplier_company_id', $pivotIds)
                    ->orWhere('supplier_id', $supplier->id);
            })
            ->count();
    }

    private function pivotRowsFor(int $companyId, Supplier $supplier): int
    {
        return SupplierCompany::where('supplier_id', $supplier->id)->where('company_id', $companyId)->count();
    }

    /**
     * The cycle: activate, deactivate, activate again. Exactly two leaves (one payable, one cost)
     * and exactly one pivot row must exist afterwards — and the early return is what guarantees it.
     */
    public function test_reactivating_a_supplier_does_not_mint_a_second_pair_of_leaves(): void
    {
        [$supplier, $company] = $this->provisionWithSupplier();

        $pivot = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)->firstOrFail();

        $afterFirst = $this->leavesFor((int) $company->id, $supplier);
        $this->assertSame(2, $afterFirst, 'the first activation mints one payable leaf and one cost leaf');

        // Deactivate the way the pivot models it, then re-activate through the real service.
        SupplierCompany::where('id', $pivot->id)->update(['is_active' => false]);

        $result = app(SupplierActivationService::class)->activate($supplier->fresh(), $company->fresh());

        $this->assertSame(
            'Supplier is already activated for this company.',
            $result['message'],
            'THE REASON F7 IS NOT REACHABLE: activate() returns here, before any mint, whenever a '
            .'supplier_companies row exists for (supplier, company) — regardless of is_active.'
        );

        $this->assertSame(
            2,
            $this->leavesFor((int) $company->id, $supplier),
            'F7: a deactivate -> reactivate cycle must NOT mint a second payable leaf and a second cost '
            .'leaf — two identical-looking accounts split that supplier\'s balance, and only one of them '
            .'is purpose-mapped'
        );

        $this->assertSame(
            1,
            $this->pivotRowsFor((int) $company->id, $supplier),
            'F7: and it must not create a second supplier_companies row either — (supplier, company) is '
            .'the natural key of that table; is_active is state ON the pairing, not part of its identity'
        );
    }

    /**
     * And the reused leaf is the SAME row, not a replacement — so nothing that already posted to it
     * is orphaned.
     */
    public function test_the_reactivation_reuses_the_same_account_rows(): void
    {
        [$supplier, $company] = $this->provisionWithSupplier();

        $pivot = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)->firstOrFail();

        $before = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('supplier_company_id', $pivot->id)
            ->whereNull('deleted_at')
            ->orderBy('id')->pluck('id')->all();

        $this->assertCount(2, $before, 'the first activation mints exactly two accounts');

        SupplierCompany::where('id', $pivot->id)->update(['is_active' => false]);
        app(SupplierActivationService::class)->activate($supplier->fresh(), $company->fresh());

        $after = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('supplier_company_id', $pivot->id)
            ->whereNull('deleted_at')
            ->orderBy('id')->pluck('id')->all();

        $this->assertSame($before, $after, 'the same account rows must be reused, not replaced');
    }

    /**
     * F5 still holds across the cycle: exactly one account carries `accounts.supplier_id`.
     */
    public function test_the_supplier_id_stays_on_exactly_one_account_across_the_cycle(): void
    {
        [$supplier, $company] = $this->provisionWithSupplier();

        $pivot = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)->firstOrFail();

        SupplierCompany::where('id', $pivot->id)->update(['is_active' => false]);
        app(SupplierActivationService::class)->activate($supplier->fresh(), $company->fresh());

        $this->assertSame(
            1,
            Account::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('supplier_id', $supplier->id)
                ->whereNull('deleted_at')
                ->count(),
            'F5 must survive F7\'s reuse path too'
        );
    }
}
