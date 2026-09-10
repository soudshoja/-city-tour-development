<?php
// app/Services/SupplierActivationService.php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use App\Services\Accounting\AccountCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierActivationService
{
    public function __construct(
        private readonly AccountCodeGenerator $codes,
    ) {}

    /**
     * Activate a supplier for a company. Caller owns the DB transaction.
     * Mirrors the legacy SupplierCompanyController::activateSupplierProcess.
     */
    public function activate(Supplier $supplier, Company $company): array
    {
        $supplierCompany = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)
            ->first();

        if ($supplierCompany) {
            $supplierCompany->is_active = true;
            $supplierCompany->update();

            return ['status' => 'success', 'message' => 'Supplier is already activated for this company.'];
        }

        $credentials = SupplierCredential::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)
            ->exists();

        if (!$credentials) {
            SupplierCredential::create([
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'environment' => env('APP_ENV') == 'production' ? 'production' : 'sandbox',
                'type' => 'basic',
                'username' => 'test',
                'password' => 'test',
                'client_id' => null,
                'client_secret' => null,
                'access_token' => null,
                'refresh_token' => null,
                'expires_at' => null,
            ]);
        }

        $types = [
            'has_flight' => ['payable' => 'Suppliers (Flights)', 'cost' => 'Flights Cost'],
            'has_hotel' => ['payable' => 'Suppliers (Hotels)', 'cost' => 'Hotels Cost'],
            'has_visa' => ['payable' => 'Suppliers (Visas)', 'cost' => 'Visa Cost'],
            'has_insurance' => ['payable' => 'Suppliers (Insurance)', 'cost' => 'Insurance Cost'],
            'has_tour' => ['payable' => 'Suppliers (Tour)', 'cost' => 'Tour Cost'],
            'has_cruise' => ['payable' => 'Suppliers (Cruise)', 'cost' => 'Cruise Cost'],
            'has_car' => ['payable' => 'Suppliers (Car)', 'cost' => 'Car Cost'],
            'has_rail' => ['payable' => 'Suppliers (Rail)', 'cost' => 'Rail Cost'],
            'has_esim' => ['payable' => 'Suppliers (Esim)', 'cost' => 'Esim Cost'],
            'has_event' => ['payable' => 'Suppliers (Event)', 'cost' => 'Event Cost'],
            'has_lounge' => ['payable' => 'Suppliers (Lounge)', 'cost' => 'Lounge Cost'],
            'has_ferry' => ['payable' => 'Suppliers (Ferry)', 'cost' => 'Ferry Cost'],
        ];

        $hasAtLeastOne = false;

        foreach ($types as $field => $accounts) {
            if (!$supplier->$field) {
                continue;
            }

            $hasAtLeastOne = true;

            $accountPayable = Account::where('name', $accounts['payable'])
                ->where('company_id', $company->id)
                ->first();

            if (!$accountPayable) {
                throw new \Exception("Account Payable group '{$accounts['payable']}' not found.");
            }

            $costAccount = Account::where('name', $accounts['cost'])
                ->where('company_id', $company->id)
                ->first();

            if (!$costAccount) {
                throw new \Exception("Supplier cost account '{$accounts['cost']}' not found.");
            }

            $supplierCompany = SupplierCompany::firstOrCreate([
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'is_active' => true,
            ]);

            $data = [
                'name' => $supplier->name,
                'level' => 4,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'company_id' => $company->id,
                'supplier_company_id' => $supplierCompany->id,
            ];

            $this->warnIfMintingUnderMappedLeaf($accountPayable, $company->id, 'payable');
            $this->warnIfMintingUnderMappedLeaf($costAccount, $company->id, 'cost');

            // CT-A4b fix: was `(int) $accountPayable->code + 1` / `(int) $costAccount->code + 1` —
            // "parent code + 1" with no collision check, which is the exact arithmetic CT-A4 (§1.6)
            // and CT-A5a (§5.5) both found minting duplicate account codes ("3 accounts sharing
            // code 2121"). Routed through the single AccountCodeGenerator allocator instead: it
            // looks at the parent's existing numeric children (same width/prefix), and its
            // `codeExists()` guard refuses any code already taken ANYWHERE in the company's chart,
            // active or not. When a pool has no numeric children yet, generate() returns null and
            // the BUG-H1 documented fallback applies — the newly persisted row's own id becomes its
            // code (see AccountService::create(), the other consumer of this same contract).
            $payable = new Account($data + [
                'parent_id' => $accountPayable->id,
                'root_id' => $accountPayable->root_id,
            ]);
            $payableCode = $this->codes->generate($accountPayable, $company->id);
            $payable->code = $payableCode ?? 'PENDING';
            $payable->save();
            if ($payableCode === null) {
                $payable->code = $this->codes->fallbackCode($payable);
                $payable->save();
            }

            $cost = new Account($data + [
                'parent_id' => $costAccount->id,
                'root_id' => $costAccount->root_id,
            ]);
            $costCode = $this->codes->generate($costAccount, $company->id);
            $cost->code = $costCode ?? 'PENDING';
            $cost->save();
            if ($costCode === null) {
                $cost->code = $this->codes->fallbackCode($cost);
                $cost->save();
            }
        }

        if (!$hasAtLeastOne) {
            throw new \Exception('Supplier must have at least one category checked.');
        }

        return ['status' => 'success', 'message' => 'Supplier activated successfully.'];
    }

    /**
     * CT-A4b coordinator finding — same defect family as the code collisions this class was fixed
     * for, one purpose-mapping step over: `accounting:coa-linkage --apply` was found minting
     * gateway-clearing leaves under an account another purpose already mapped to directly,
     * silently turning that mapping's target into a non-leaf (fixed there by making
     * NON_LEAF_PURPOSE_MAPPING a blocking finding — see CoaLinkage::verifyPurposes()). Activating
     * a supplier under a payable/cost GROUP has the identical shape: if `$group` is currently a
     * leaf (no children yet) AND some purpose is already mapped directly onto it in
     * `system_accounts`, minting this supplier's child under it turns that mapping's target into
     * a non-leaf too.
     *
     * Logged, not refused: unlike coa-linkage (an explicit, operator-invoked repair command where
     * refusing a run is the safe default), supplier activation is a routine, frequent, user-facing
     * action — refusing it outright on a mapping this service does not own or control would block
     * legitimate onboarding for a chart shape it did not create. The log line gives an operator
     * the same "AccountResolver will now throw NonLeafAccountException for purpose X" warning
     * `accounting:coa-linkage --company= --apply` would refuse outright, so the drift is visible
     * and fixable (re-map the purpose, or run `accounting:coa-linkage` to have it caught as
     * blocking) rather than discovered later as a silent posting failure.
     */
    private function warnIfMintingUnderMappedLeaf(Account $group, int $companyId, string $role): void
    {
        if ($group->children()->exists()) {
            // Already a group before this activation — whatever purpose points at it (if any)
            // was already refusing to resolve leaf-only, and is not a NEW regression this call
            // introduces.
            return;
        }

        $mappedPurposes = DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('account_id', $group->id)
            ->pluck('purpose_code');

        if ($mappedPurposes->isEmpty()) {
            return;
        }

        Log::warning('accounting.supplier_activation.non_leaf_purpose_mapping', [
            'company_id' => $companyId,
            'group_account_id' => $group->id,
            'group_account_name' => $group->name,
            'role' => $role,
            'purposes_now_pointing_at_a_non_leaf' => $mappedPurposes->all(),
            'message' => "Activating a supplier under '{$group->name}' (#{$group->id}) is about to give it its "
                .'first child. The purpose(s) above are currently mapped directly onto this account and will '
                .'start throwing NonLeafAccountException at posting time. Re-map them, or run '
                ."accounting:coa-linkage --company={$companyId} --apply to have this caught as a blocking finding.",
        ]);
    }
}
