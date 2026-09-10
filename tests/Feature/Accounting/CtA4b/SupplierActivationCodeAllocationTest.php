<?php

namespace Tests\Feature\Accounting\CtA4b;

use App\Models\Account;
use App\Models\Country;
use App\Models\Supplier;
use App\Services\CompanyProvisioner;
use App\Support\CompanyRegistrationData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\AccountingTestCase;

/**
 * CT-A4b — reproduces, then closes, the finding CT-A5a §5.5 recorded and left open:
 * *"SupplierActivationService::activate() mints colliding account codes. Provisioning two
 * suppliers produced '3 accounts sharing code 2121: Suppliers (Visas), CtA5 Flight Supplier,
 * CtA5 Hotel Supplier'"*.
 *
 * Root cause (app/Services/SupplierActivationService.php, pre-fix): `$newPayableCode =
 * (int) $accountPayable->code + 1` — the parent GROUP's own code plus one, with no check that
 * anything else already holds it, computed fresh for every supplier activated. Every flight
 * supplier activated under '2120 Suppliers (Flights)' got '2121' — the code of the sibling GROUP
 * '2121 Suppliers (Visas)' — every time.
 *
 * Fixed by routing through the single AccountCodeGenerator allocator (same one AccountService::
 * create(), TaskController's currency/issued-by child accounts, and accounting:coa-duplicates
 * --renumber all use), whose codeExists() check refuses any code already taken anywhere in the
 * company's chart.
 */
class SupplierActivationCodeAllocationTest extends AccountingTestCase
{
    private function provisionWithSuppliers(array $supplierIds): \App\Models\Company
    {
        $unique = uniqid();

        $data = CompanyRegistrationData::fromArray([
            'company_name' => 'CtA4b Test Co '.$unique,
            'company_code' => 'CTA4B-'.$unique,
            'country_id' => Country::factory()->create()->id,
            'company_email' => "owner-{$unique}@example.test",
            'owner_name' => 'Test Owner',
            'owner_email' => "owner-{$unique}@example.test",
            'owner_password' => 'password12345',
            'currency' => 'KWD',
            'supplier_ids' => $supplierIds,
        ]);

        return app(CompanyProvisioner::class)->provision($data);
    }

    /**
     * The CT-A5a repro, generalised to 5 suppliers (the task brief's own reproduction target).
     */
    public function test_activating_five_flight_suppliers_produces_zero_duplicate_account_codes(): void
    {
        $suppliers = Supplier::factory()->count(5)->create([
            'has_flight' => true,
            'has_hotel' => false,
        ]);

        $company = $this->provisionWithSuppliers($suppliers->pluck('id')->all());
        $this->trackCompanyForInvariants($company->id);
        // tearDown() now runs AccountingInvariants::assertNoDuplicateAccountCodes($company->id)
        // (CT-A4b tightened it to tolerate NONE), which alone would catch a regression here. The
        // explicit assertions below additionally prove the SPECIFIC pre-fix symptom is gone.

        $codes = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->pluck('code');

        $duplicates = $codes->countBy()->filter(fn (int $n) => $n > 1);

        $this->assertTrue(
            $duplicates->isEmpty(),
            'Expected zero duplicate account codes after activating 5 suppliers, found: '
                .$duplicates->keys()->implode(', ')
        );

        // Every supplier got a distinct payable leaf AND a distinct cost leaf — 10 new accounts,
        // 10 distinct codes, none of them the pre-fix collision value '2121'.
        $payableCodes = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Suppliers (Flights)')
            ->first()
            ->children()
            ->pluck('code');

        $this->assertCount(5, $payableCodes->unique());
        $this->assertNotContains('2121', $payableCodes->all());
    }

    public function test_reactivating_an_already_active_supplier_mints_no_new_accounts(): void
    {
        $supplier = Supplier::factory()->create(['has_visa' => true]);

        $company = $this->provisionWithSuppliers([$supplier->id]);
        $this->trackCompanyForInvariants($company->id);

        $before = Account::withoutGlobalScopes()->where('company_id', $company->id)->count();

        app(\App\Services\SupplierActivationService::class)->activate(
            $supplier->fresh(),
            $company->fresh()
        );

        $after = Account::withoutGlobalScopes()->where('company_id', $company->id)->count();

        $this->assertSame($before, $after, 'Re-activating an already-active supplier must not mint duplicate accounts.');
    }

    /**
     * Coordinator finding, folded into this lane: the same "minting a child turns a mapped leaf
     * into a non-leaf" defect CoaLinkage::verifyPurposes() now catches as blocking, one call site
     * over. Supplier activation is routine/user-facing, so it logs rather than refuses.
     *
     * CT-D2b — RE-DERIVED, not deleted, on the merged CT-D2 head. This case originally asserted
     * that `activate()` emits a WARNING, UNCONDITIONALLY, before the mint. PR #15 (R3-7) lands on
     * the same head and repairs exactly that mapping ten lines later in the same call, so on the
     * union every successful activation warned about a breakage that no longer existed by the time
     * the call returned — and a real, unrepaired mapping became indistinguishable from that noise.
     *
     * The coordinator's resolution keeps #14's detection in full and makes it the POST-repair
     * assertion, at ERROR, emitted only when the repair did not clear the mapping. So the property
     * this case now pins is the invariant that survives both PRs and is stronger than either:
     *
     *   EXACTLY ONE of {silently repaired, one ERROR naming it} holds — never both, never neither —
     *   and a WARNING is never emitted for this event again.
     *
     * The two halves are each driven deterministically, with their own fixtures and their own
     * mutation proofs, in `Tests\Feature\Accounting\CtD2b\SupplierActivationPurposeRepairTest`.
     */
    public function test_activating_a_supplier_under_a_purpose_mapped_group_emits_one_signal_and_only_if_the_repair_failed(): void
    {
        Log::spy();

        $supplier = Supplier::factory()->create(['has_flight' => true]);
        $company = $this->provisionWithSuppliers([]);
        $this->trackCompanyForInvariants($company->id);

        $flightsPool = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Suppliers (Flights)')
            ->firstOrFail();

        // A purpose mapped directly onto the (currently leaf) group — same shape as
        // GATEWAY_CLEARING_HESABE pointing at 'Payment Gateway' before it grew children.
        DB::table('system_accounts')->insert([
            'company_id' => $company->id,
            'purpose_code' => 'PAYABLE_CONTROL',
            'service_type' => null,
            'account_id' => $flightsPool->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Services\SupplierActivationService::class)->activate($supplier, $company->fresh());

        $event = 'accounting.supplier_activation.non_leaf_purpose_mapping';

        // The activation really did reshape the chart — otherwise nothing below means anything.
        $childCount = Account::withoutGlobalScopes()->where('parent_id', $flightsPool->id)->count();
        $this->assertGreaterThan(0, $childCount, 'The activation minted no child under the mapped pool.');

        // Is the planted mapping STILL sitting on the account this activation turned into a group?
        $stillMapped = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('account_id', $flightsPool->id)
            ->exists();

        if ($stillMapped) {
            Log::shouldHaveReceived('error')
                ->once()
                ->withArgs(function (string $message, array $context) use ($event, $flightsPool) {
                    return $message === $event
                        && in_array('PAYABLE_CONTROL', $context['purposes_now_pointing_at_a_non_leaf'], true)
                        && collect($context['groups_this_activation_minted_under'])
                            ->contains(fn (string $g) => str_contains($g, '#'.$flightsPool->id));
                });
        } else {
            // Repaired. The correct amount of operator noise for a successful activation is none.
            Log::shouldNotHaveReceived('error', [$event, \Mockery::any()]);
        }

        // Never a WARNING for this event again: that was #14's unconditional pre-repair signal, and
        // it is what the CT-D2b resolution removed.
        Log::shouldNotHaveReceived('warning', [$event, \Mockery::any()]);
    }
}
