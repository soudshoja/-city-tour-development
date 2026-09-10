<?php
// app/Services/SupplierActivationService.php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use App\Services\Accounting\PurposeHealthService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SupplierActivationService
{
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

            $newPayableCode = (int) $accountPayable->code + 1;
            $newCostCode = (int) $costAccount->code + 1;

            Account::create($data + [
                'parent_id' => $accountPayable->id,
                'root_id' => $accountPayable->root_id,
                'code' => (string) $newPayableCode,
            ]);

            Account::create($data + [
                'parent_id' => $costAccount->id,
                'root_id' => $costAccount->root_id,
                'code' => (string) $newCostCode,
            ]);
        }

        if (!$hasAtLeastOne) {
            throw new \Exception('Supplier must have at least one category checked.');
        }

        $this->remapPurposesAfterChartReshape($company);

        return ['status' => 'success', 'message' => 'Supplier activated successfully.'];
    }

    /**
     * CT-A56 R3-7 — the other half of the CT-A5a §0.5 fix.
     *
     * `CompanyProvisioner::provision()` now runs `accounting:coa-linkage --apply` AFTER
     * `activateSuppliers()` and asserts every purpose resolves to a LEAF. That assertion runs
     * ONCE, at provisioning time. This method is callable at any time afterwards
     * (`SupplierCompanyController::activateSupplierProcess`), and every call reshapes the chart in
     * exactly the way the assertion exists to catch: a company provisioned with no FLIGHT supplier
     * leaves `Suppliers (Flights)` / `Flights Cost` as LEAVES, so the mapper correctly points
     * `SERVICE_PAYABLE/flight` and `SERVICE_COST/flight` at them — and the first flight supplier
     * activated later mints a CHILD under each, turning both mapped accounts into groups.
     *
     * Independent verification R3 reproduced it exactly: after one post-provisioning activation,
     * `SERVICE_PAYABLE/flight` -> #51 `Suppliers (Flights)` (1 child) and `SERVICE_COST/flight` ->
     * #119 `Flights Cost` (1 child), both `NonLeafAccountException`. The next flight sale for that
     * company cannot post.
     *
     * The repair is the same one provisioning uses and for the same reason (CT-A5 rule, §0.5):
     * `accounting:coa-linkage --apply` is the only entry point that both maps every purpose and
     * repairs the chart shape supplier activation has just created. `--sweep-dangling` is
     * deliberately NOT passed, matching `CompanyProvisioner::mapAccountingPurposes()`.
     *
     * Deliberately tolerant of a FAILED repair rather than aborting the activation: this runs
     * inside the caller's own transaction, `coa-linkage --apply` legitimately refuses while another
     * tenant has dangling `system_accounts` rows (CT-A3 R3 §3.4), and refusing to activate a
     * supplier because a DIFFERENT company's chart is dirty would be a worse failure than the one
     * being prevented. A repair that does not clear the non-leaf mapping is logged at ERROR naming
     * every purpose still broken — the same signal `accounting:purpose-health --company=all`
     * surfaces on demand.
     */
    private function remapPurposesAfterChartReshape(Company $company): void
    {
        $before = app(PurposeHealthService::class)->inspect((int) $company->id);

        if ($before['non_leaf'] === []) {
            return;
        }

        $exit = Artisan::call('accounting:coa-linkage', [
            '--company' => $company->id,
            '--apply' => true,
        ]);

        $after = app(PurposeHealthService::class)->inspect((int) $company->id);

        if ($after['non_leaf'] === []) {
            Log::info('SupplierActivationService: purposes re-mapped after chart reshape', [
                'company_id' => $company->id,
                'repaired' => count($before['non_leaf']),
            ]);

            return;
        }

        Log::error('SupplierActivationService: a purpose still names a NON-LEAF after supplier activation', [
            'company_id' => $company->id,
            'coa_linkage_exit' => $exit,
            'purposes' => array_map(
                fn (array $n) => $n['purpose'].($n['service_type'] !== null ? '/'.$n['service_type'] : '')
                    .' (#'.$n['account_id'].' '.$n['account_name'].', '.$n['child_count'].' children)',
                $after['non_leaf']
            ),
        ]);
    }
}
