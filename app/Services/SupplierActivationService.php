<?php
// app/Services/SupplierActivationService.php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use App\Services\Accounting\AccountCodeGenerator;
use App\Services\Accounting\PurposeHealthService;
use Illuminate\Support\Facades\Artisan;
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
     *
     * CT-D2b — the order of the last three steps is load-bearing and is the coordinator's
     * resolution of the PR #14 / PR #15 conflict on this class:
     *
     *   1. reshape the chart (mint each supplier's payable + cost leaf), recording — BEFORE each
     *      mint, because it is unanswerable afterwards — every group that was a LEAF carrying a
     *      direct purpose mapping and is about to gain its first child (PR #14's detection);
     *   2. repair: {@see self::remapPurposesAfterChartReshape()} re-runs
     *      `accounting:coa-linkage --apply` (PR #15 / R3-7);
     *   3. assert: PR #14's detail is emitted ONLY if a purpose still resolves to a NON-LEAF after
     *      the repair, at ERROR, as one signal.
     *
     * Both PRs' logic is kept in full. What is removed is #14's UNCONDITIONAL pre-repair WARNING:
     * on the merged head it fired on every successful activation, naming a breakage that #15
     * repaired ten lines later in the same call, which is how a real unrepaired mapping gets
     * ignored.
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

        /** @var array<int, array{account_id: int, account_name: string, role: string, purposes: array<int, string>}> */
        $mintedUnderMappedLeaf = [];

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

            // CT-D2b: PR #14's detection, unchanged in substance, moved off the log and onto a
            // record. It MUST run here — "was this account a leaf, with a purpose mapped directly
            // onto it, immediately before this activation?" cannot be asked once the child exists.
            foreach ([[$accountPayable, 'payable'], [$costAccount, 'cost']] as [$group, $role]) {
                $candidate = $this->detectMintingUnderMappedLeaf($group, (int) $company->id, (string) $role);

                if ($candidate !== null) {
                    $mintedUnderMappedLeaf[] = $candidate;
                }
            }

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

        $this->remapPurposesAfterChartReshape($company, $mintedUnderMappedLeaf);

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
     * CT-D2b — this DETECTS and returns; it no longer logs. PR #14 logged here, unconditionally,
     * before the mint. PR #15 (R3-7) then repaired exactly this mapping a few lines later in the
     * same `activate()` call, so on the merged head every successful activation emitted a WARNING
     * naming a breakage that no longer existed by the time the call returned — and a real,
     * unrepaired non-leaf mapping became indistinguishable from that noise. The detection is kept
     * in full and is now the POST-REPAIR assertion; see
     * {@see self::remapPurposesAfterChartReshape()}, which carries #14's operator guidance
     * verbatim into the one ERROR it emits when the repair did not clear the mapping.
     *
     * It is deliberately NOT replaced by `PurposeHealthService::inspect()['non_leaf']`: that answer
     * is bounded by `requiredPurposes()` (the engine's own vocabulary, anchors excluded), while
     * this reads `system_accounts` directly, so a purpose row outside that vocabulary — the exact
     * kind of drift nobody is looking for — is still caught.
     *
     * @return array{account_id: int, account_name: string, role: string, purposes: array<int, string>}|null
     */
    private function detectMintingUnderMappedLeaf(Account $group, int $companyId, string $role): ?array
    {
        if ($group->children()->exists()) {
            // Already a group before this activation — whatever purpose points at it (if any)
            // was already refusing to resolve leaf-only, and is not a NEW regression this call
            // introduces.
            return null;
        }

        $mappedPurposes = DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('account_id', $group->id)
            ->pluck('purpose_code');

        if ($mappedPurposes->isEmpty()) {
            return null;
        }

        return [
            'account_id' => (int) $group->id,
            'account_name' => (string) $group->name,
            'role' => $role,
            'purposes' => array_values(array_map('strval', $mappedPurposes->all())),
        ];
    }

    /**
     * Of the groups this activation minted under (each of which WAS a purpose-mapped leaf), those
     * that are still both a non-leaf AND directly named by a `system_accounts` row after the
     * repair. An empty result means the repair moved every mapping off the account this activation
     * turned into a group — i.e. #14's warning would have been noise.
     *
     * @param  array<int, array{account_id: int, account_name: string, role: string, purposes: array<int, string>}>  $candidates
     * @return array<int, array{account_id: int, account_name: string, role: string, purposes: array<int, string>}>
     */
    private function candidatesStillMappedOntoANonLeaf(array $candidates, int $companyId): array
    {
        $still = [];

        foreach ($candidates as $candidate) {
            $childCount = Account::query()
                ->withoutGlobalScopes()
                ->where('parent_id', $candidate['account_id'])
                ->count();

            if ($childCount === 0) {
                continue;
            }

            $purposes = DB::table('system_accounts')
                ->where('company_id', $companyId)
                ->where('account_id', $candidate['account_id'])
                ->pluck('purpose_code');

            if ($purposes->isEmpty()) {
                continue;
            }

            $still[] = [
                'account_id' => $candidate['account_id'],
                'account_name' => $candidate['account_name'],
                'role' => $candidate['role'],
                'purposes' => array_values(array_map('strval', $purposes->all())),
            ];
        }

        return $still;
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
     *
     * CT-D2b — PR #14's detail is folded into that single ERROR branch (see
     * {@see self::detectMintingUnderMappedLeaf()}). The repair is attempted whenever EITHER health
     * reports a non-leaf purpose OR this activation minted under a purpose-mapped leaf, so the
     * assertion below is never reached without a repair having been tried first.
     *
     * @param  array<int, array{account_id: int, account_name: string, role: string, purposes: array<int, string>}>  $mintedUnderMappedLeaf
     */
    private function remapPurposesAfterChartReshape(Company $company, array $mintedUnderMappedLeaf = []): void
    {
        $companyId = (int) $company->id;

        $before = app(PurposeHealthService::class)->inspect($companyId);

        if ($before['non_leaf'] === [] && $mintedUnderMappedLeaf === []) {
            return;
        }

        $exit = Artisan::call('accounting:coa-linkage', [
            '--company' => $company->id,
            '--apply' => true,
        ]);

        $after = app(PurposeHealthService::class)->inspect($companyId);
        $stillMapped = $this->candidatesStillMappedOntoANonLeaf($mintedUnderMappedLeaf, $companyId);

        if ($after['non_leaf'] === [] && $stillMapped === []) {
            Log::info('SupplierActivationService: purposes re-mapped after chart reshape', [
                'company_id' => $company->id,
                'repaired' => count($before['non_leaf']),
                'minted_under_mapped_leaf' => count($mintedUnderMappedLeaf),
            ]);

            return;
        }

        Log::error('accounting.supplier_activation.non_leaf_purpose_mapping', [
            'company_id' => $company->id,
            'coa_linkage_exit' => $exit,
            'purposes' => array_map(
                fn (array $n) => $n['purpose'].($n['service_type'] !== null ? '/'.$n['service_type'] : '')
                    .' (#'.$n['account_id'].' '.$n['account_name'].', '.$n['child_count'].' children)',
                $after['non_leaf']
            ),
            'groups_this_activation_minted_under' => array_map(
                fn (array $c) => $c['account_name'].' (#'.$c['account_id'].', '.$c['role'].')',
                $stillMapped
            ),
            'purposes_now_pointing_at_a_non_leaf' => array_values(array_unique(array_merge(
                ...array_map(fn (array $c) => $c['purposes'], $stillMapped)
            ))),
            'message' => 'Activating this supplier gave a purpose-mapped account its first child, and '
                ."accounting:coa-linkage --company={$companyId} --apply did NOT clear the mapping. The "
                .'purpose(s) above are mapped directly onto an account that is now a GROUP and will throw '
                .'NonLeafAccountException at posting time. Re-map them by hand, or resolve whatever made the '
                ."repair refuse and re-run accounting:coa-linkage --company={$companyId} --apply. "
                .'accounting:purpose-health --company=all reports the same state on demand.',
        ]);
    }
}
