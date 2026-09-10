<?php

namespace App\Services;

use App\Jobs\ProvisionResayilWorkspace;
use App\Models\Company;
use App\Models\CompanyGdsPcc;
use App\Models\CompanyInvite;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\PurposeHealthService;
use App\Support\CompanyRegistrationData;
use App\Support\Entitlements\ApplyCompanyModulePreset;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CompanyProvisioner
{
    public const TEMPLATE_COMPANY_ID = 1;

    public const ROLE_NAMES = ['admin', 'company', 'branch', 'agent', 'accountant', 'client'];

    /**
     * Agent welcome-mail payloads collected during createAgents(), sent AFTER
     * the DB transaction commits (see provision()) so an SMTP hiccup mid-loop
     * can never roll back company provisioning.
     *
     * @var array<int, array{name: string, email: string, tempPassword: string}>
     */
    private array $pendingAgentMails = [];

    public function __construct(
        private readonly SupplierActivationService $supplierActivation,
        private readonly ApplyCompanyModulePreset $modulePreset,
    ) {}

    public function provision(CompanyRegistrationData $data, ?CompanyInvite $invite = null): Company
    {
        $this->pendingAgentMails = [];

        $company = DB::transaction(function () use ($data, $invite) {
            $company = $this->createOwnerAndCompany($data);

            // Package-preset entitlement is a brand-new-company thing, not a
            // company:provision --repair thing. firstOrCreate() inside
            // createOwnerAndCompany() sets wasRecentlyCreated=true only when
            // this call actually inserted the row; --repair always targets an
            // EXISTING company (ProvisionCompany::handle() requires --company=ID
            // to already resolve), so it always finds, never creates, and this
            // stays false there. Without this guard, running --repair against
            // one of the 3 pre-Phase-1 companies would silently flip them from
            // fail-open (everything visible) onto the package preset
            // (accounting off) — exactly what they must NOT do.
            $isNewCompany = $company->wasRecentlyCreated;

            $this->createGdsPccs($company, $data);
            $this->createRolesAndPermissions($company);
            $this->seedChartOfAccounts($company);

            if ($isNewCompany) {
                $this->modulePreset->apply($company);
            }

            $this->createMainBranch($company);
            $this->createSequencesRow($company);
            $this->copyDefaultSettings($company);
            $this->createAgents($company, $data);
            $this->activateSuppliers($company, $data);
            $this->createStorageDirectories($company);
            $this->createGatewayCharges($company, $data);

            // CT-A5a (§0.5, raised by the travelerp TE-1 port). MUST run here and nowhere
            // earlier: see mapAccountingPurposes()'s own docblock.
            $this->mapAccountingPurposes($company);

            $this->finalize($company, $invite);

            Log::info('Company provisioned', ['company_id' => $company->id, 'name' => $company->name]);

            return $company;
        });

        // Mail sent only after commit — one SMTP failure must not abort/roll
        // back a company that has otherwise been fully provisioned.
        foreach ($this->pendingAgentMails as $mail) {
            try {
                Mail::to($mail['email'])->send(
                    new \App\Mail\AgentWelcomeMail($mail['name'], $mail['email'], $mail['tempPassword'], route('login'))
                );
            } catch (\Throwable $e) {
                Log::warning('CompanyProvisioner: agent welcome mail failed', [
                    'company_id' => $company->id, 'email' => $mail['email'], 'error' => $e->getMessage(),
                ]);
            }
        }

        // Module 5 (plan .planning/specs/RESAYIL-ADMIN-CENTER.md section 3.1):
        // create this company's Resayil WhatsApp workspace and capture its
        // account API key. Dispatched AFTER the commit for exactly the same
        // reason the welcome mail above is: it makes an external HTTP call,
        // and an external HTTP call must never be able to roll back a company
        // that has otherwise been fully provisioned. The job is idempotent
        // and re-runnable (php artisan resayil:provision-company), so a queue
        // that happens to be down right now costs a delay, not a broken
        // company.
        try {
            ProvisionResayilWorkspace::dispatch($company->id, $company->user_id);
        } catch (\Throwable $e) {
            Log::warning('CompanyProvisioner: could not queue Resayil workspace provisioning', [
                'company_id' => $company->id, 'error' => $e->getMessage(),
            ]);
        }

        return $company;
    }

    private function createOwnerAndCompany(CompanyRegistrationData $data): Company
    {
        $owner = User::firstOrCreate(
            ['email' => $data->ownerEmail],
            [
                'name' => $data->ownerName,
                'password' => Hash::make($data->ownerPassword),
                'role_id' => Role::COMPANY,
                'remember_token' => Str::random(10),
                'first_login' => 1,
            ]
        );

        $company = Company::firstOrCreate(
            ['code' => $data->companyCode],
            [
                'name' => $data->companyName,
                'email' => $data->companyEmail,
                'currency' => $data->currency,
                'country_id' => $data->countryId,
                'address' => $data->address,
                'phone' => $data->phone,
                'user_id' => $owner->id,
                'status' => 1,
                'iata_code' => $data->iataCode,
                'gds_office_id' => $data->gdsOfficeId,
                'iata_client_id' => $data->iataClientId,
                'iata_client_secret' => $data->iataClientSecret,
                'logo' => $data->logoPath,
            ]
        );

        return $company;
    }

    private function createGdsPccs(Company $company, CompanyRegistrationData $data): void
    {
        if (empty($data->gdsPccs)) {
            return;
        }

        foreach ($data->gdsPccs as $row) {
            if (empty($row['gds']) || empty($row['pcc'])) {
                continue;
            }

            CompanyGdsPcc::firstOrCreate([
                'company_id' => $company->id,
                'gds' => $row['gds'],
                'pcc' => $row['pcc'],
            ]);
        }

        // Legacy single-field compatibility: companies.gds_office_id predates
        // the multi-PCC table. Backfill it from the first Amadeus PCC only if
        // it's still unset — never clobber a value already captured on step 3.
        if ($company->gds_office_id === null) {
            $firstAmadeus = CompanyGdsPcc::where('company_id', $company->id)
                ->where('gds', 'Amadeus')
                ->orderBy('id')
                ->first();

            if ($firstAmadeus) {
                $company->update(['gds_office_id' => $firstAmadeus->pcc]);
            }
        }
    }

    private function createRolesAndPermissions(Company $company): void
    {
        foreach (self::ROLE_NAMES as $roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'company_id' => $company->id],
                ['description' => ucfirst($roleName).' role for '.$company->name]
            );

            // Copy permission grants from the template company's same-named role.
            $templateRole = Role::where('name', $roleName)
                ->where('guard_name', 'web')
                ->where('company_id', self::TEMPLATE_COMPANY_ID)
                ->first();

            if ($templateRole) {
                $role->syncPermissions($templateRole->permissions);
            }
        }

        // Owner gets THIS company's 'company' role (never bare assignRole by NAME:
        // that resolves by name only and attaches company 1's role — Role.php:120
        // bug). Passing the Role MODEL instance to assignRole attaches that exact
        // row, no name-resolution ambiguity.
        $companyRole = Role::where('name', 'company')
            ->where('guard_name', 'web')
            ->where('company_id', $company->id)
            ->first();

        // company:provision --repair can target companies created the "old broken
        // way" with no owner user linked (user_id null, or a dangling id). Skip the
        // owner role sync rather than fataling on User::find(null)->syncRoles().
        $owner = $company->user_id ? User::find($company->user_id) : null;
        if (! $owner) {
            Log::warning('CompanyProvisioner: no owner user for company, skipping owner role sync', [
                'company_id' => $company->id, 'user_id' => $company->user_id,
            ]);

            return;
        }

        // Additive + idempotent: never syncRoles (that DETACHES every other role
        // the user holds, which is destructive on a --repair rerun).
        if ($companyRole && ! $owner->hasRole($companyRole)) {
            $owner->assignRole($companyRole);
        }
    }

    private function seedChartOfAccounts(Company $company): void
    {
        CoaSeeder::run($company->id);   // idempotent: updateOrCreate keyed on name+parent+company+root
    }

    private function createMainBranch(Company $company): void
    {
        $branch = \App\Models\Branch::firstOrCreate(
            ['company_id' => $company->id, 'name' => $company->name.' - Main Branch'],
            [
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'user_id' => $company->user_id,
                'gds_office_id' => $company->gds_office_id,
            ]
        );

        // Branch receivable account — COMPANY-SCOPED lookups (BranchController@store:117-118
        // does this unscoped and would attach the branch under company 1's tree).
        $exists = \App\Models\Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('reference_id', $branch->id)
            ->where('branch_id', $branch->id)
            ->exists();
        if ($exists) {
            return;
        }

        $asset = \App\Models\Account::withoutGlobalScopes()
            ->where('name', 'Assets')->where('company_id', $company->id)->first();
        $receivable = \App\Models\Account::withoutGlobalScopes()
            ->where('name', 'like', '%Receivable%')->where('company_id', $company->id)
            ->orderBy('level')->first();

        if (! $asset || ! $receivable) {
            throw new \Exception('Assets / Receivable group accounts missing — COA must be seeded first.');
        }

        // accounts.reference_id is a self-referencing FK to accounts.id (see
        // 2025_03_17_091543_create_accounts_table.php), but BranchController@store
        // (and this mirror of it) stuffs the BRANCH id in there — an existing
        // schema/usage mismatch. Toggle FK checks off for this single insert,
        // same idiom already used for account rows in SeedCompanyCoaCommand.
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        try {
            \App\Models\Account::create([
                'name' => $branch->name,
                'level' => 3,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'company_id' => $company->id,
                'root_id' => $asset->id,
                'parent_id' => $receivable->id,
                'branch_id' => $branch->id,
                'reference_id' => $branch->id,
                'code' => 'BRN-'.rand(1000000, 9999999),
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    private function createSequencesRow(Company $company): void
    {
        \App\Models\Sequence::firstOrCreate(
            ['company_id' => $company->id],
            ['sequence_for' => null, 'current_sequence' => 0]
        );
    }

    private function copyDefaultSettings(Company $company): void
    {
        // Reject secrets AND tenant-routing values (email/phone/whatsapp/notification.*)
        // — otherwise a tenant would inherit City Travelers' notification recipients
        // (e.g. notification.invoice_created.email would mail CT's accountant).
        $templates = \App\Models\Setting::where('company_id', self::TEMPLATE_COMPANY_ID)
            ->where('key', 'not like', 'aicfg.%')
            ->get()
            ->reject(fn ($s) => preg_match('/key|secret|token|password|email|phone|whatsapp|mobile|notification\./i', $s->key));

        foreach ($templates as $setting) {
            \App\Models\Setting::firstOrCreate(
                ['key' => $setting->key, 'company_id' => $company->id],
                ['value' => $setting->value, 'type' => $setting->type, 'description' => $setting->description]
            );
        }
    }

    private function createAgents(Company $company, CompanyRegistrationData $data): void
    {
        if (empty($data->agents)) {
            return;
        }

        $branch = \App\Models\Branch::where('company_id', $company->id)->firstOrFail();
        $agentRole = Role::where('name', 'agent')->where('guard_name', 'web')
            ->where('company_id', $company->id)->firstOrFail();
        $defaultTypeId = \App\Models\AgentType::query()->value('id');

        foreach ($data->agents as $row) {
            if (\App\Models\Agent::where('email', $row['email'])->exists()) {
                continue;   // idempotency
            }

            $tempPassword = Str::random(12);

            $user = User::firstOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make($tempPassword),
                    'role_id' => Role::AGENT,
                    'remember_token' => Str::random(10),
                    'first_login' => 1,
                ]
            );
            $user->syncRoles([$agentRole]);

            \App\Models\Agent::create([
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'name' => $row['name'],
                'email' => $row['email'],
                'phone_number' => $row['phone'] ?? null,
                'type_id' => $defaultTypeId,
                'amadeus_id' => $row['amadeus_id'] ?? null,
            ]);

            // Not sent here — collected and mailed AFTER the transaction commits
            // (see provision()), so an SMTP failure mid-loop can't roll back
            // provisioning that already succeeded.
            $this->pendingAgentMails[] = [
                'name' => $row['name'], 'email' => $row['email'], 'tempPassword' => $tempPassword,
            ];
        }
    }

    private function activateSuppliers(Company $company, CompanyRegistrationData $data): void
    {
        foreach ($data->supplierIds as $supplierId) {
            $supplier = \App\Models\Supplier::find($supplierId);
            if (! $supplier) {
                continue;
            }
            $this->supplierActivation->activate($supplier, $company);

            // TaskRuleSeeder::run() is a no-op without a $supplierId (its whole
            // body is guarded by `if ($supplierId)`) — it seeds per (company,
            // supplier) pair, same as app/Console/Commands/updateTaskRuleSeeder.php.
            // updateOrCreate-keyed, so safe to call again on a re-activation.
            (new \Database\Seeders\TaskRuleSeeder)->run($company->id, $supplier->id);
        }
    }

    private function createStorageDirectories(Company $company): void
    {
        $companySlug = strtolower(preg_replace('/\s+/', '_', $company->name));
        $suppliers = $company->suppliers()->get();

        foreach ($suppliers as $supplier) {
            $supplierSlug = strtolower(preg_replace('/\s+/', '_', $supplier->name));
            foreach (['files_unprocessed', 'files_processed', 'files_error'] as $dir) {
                $path = storage_path("app/{$companySlug}/{$supplierSlug}/{$dir}");
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
            }
        }
    }

    private function createGatewayCharges(Company $company, CompanyRegistrationData $data): void
    {
        foreach ($data->gateways as $gw) {
            if (empty($gw['api_key'])) {
                continue;
            }
            if (\App\Models\Charge::where('name', $gw['name'])->where('company_id', $company->id)->exists()) {
                continue;   // idempotency
            }

            $template = \App\Models\Charge::where('name', $gw['name'])
                ->where('company_id', self::TEMPLATE_COMPANY_ID)->first();

            if ($template) {
                $new = $template->replicate();
                $new->company_id = $company->id;
                $new->api_key = $gw['api_key'];
                $new->is_active = 1;
                // Never inherit City Travelers' gateway credentials, GL accounts,
                // or branch — payment flows resolve these ids UNSCOPED, so leaking
                // them would let tenant payments post into company 1's ledger and
                // use company 1's gateway auth.
                $new->tran_portal_id = null;
                $new->tran_portal_password = null;
                $new->terminal_resource_key = null;
                $new->branch_id = null;
                $new->acc_bank_id = null;
                $new->acc_fee_id = null;
                $new->acc_fee_bank_id = null;
                $new->save();
            } else {
                \App\Models\Charge::create([
                    'name' => $gw['name'],
                    'type' => 'gateway',
                    'api_key' => $gw['api_key'],
                    'is_active' => 1,
                    'company_id' => $company->id,
                ]);
            }
        }
    }

    /**
     * CT-A5a (`.planning/phases/citytravelers-accounting-audit/PLAN.md` §0.5) — the onboarding
     * defect, closed.
     *
     * ── What was wrong ───────────────────────────────────────────────────────────────────────
     * `provision()` seeded a CHART (`seedChartOfAccounts()` -> `CoaSeeder::run()`) and **zero
     * purpose mappings**. This class never referenced `SystemAccountsSeeder`,
     * `accounting:ensure-system-leaves`, `accounting:coa-linkage` or the `system_accounts` table
     * at all. Every engine action a new company then took resolved a purpose that was not there —
     * `UnmappedPurposeException` on its first sale. The fingerprint is visible on the dev database
     * today: company 3 carries 38 `system_accounts` rows written 2026-09-05 pointing at account
     * ids 1702–1739, none of which ever existed (CT-D1 §0.4i) — a company provisioned with no
     * purpose mapper and then hand-patched by pointing purposes at ids somebody expected to be
     * minted.
     *
     * ── Why HERE, and not next to `seedChartOfAccounts()` ────────────────────────────────────
     * Because supplier activation CHANGES THE SHAPE OF THE CHART. On a bare chart
     * `Suppliers (Flights)` is a leaf; `activateSuppliers()` (step 9) mints children under the
     * supplier pools, and a mapping written at step 3 would then name a GROUP. `AccountResolver`
     * refuses a non-leaf by design, so the mapping would resolve on the day it was written and
     * stop resolving minutes later. TE-1 measured exactly that on travelerp's onboarding E2E:
     * *"system_accounts row #42 maps purpose_code=SERVICE_PAYABLE to accounts.id=51
     * (Suppliers (Flights)), which is not a leaf account."* It is the same G1 shape CT-A4 spent a
     * whole lane repairing on company 1's real chart, arriving by a different road.
     *
     * `createGatewayCharges()` is the other chart-shaping step (it is what decides which gateway
     * instruments a company has), so this runs after that too — mapping LAST is the rule, not
     * "mapping after suppliers" specifically.
     *
     * ── Why `accounting:coa-linkage` and not `SystemAccountsSeeder` ──────────────────────────
     * §0.5 states it as the rule: it is the only entry point that both maps every purpose AND
     * repairs the chart shape supplier activation has just created (it delegates the named-leaf
     * and remap work to `accounting:ensure-system-leaves` and adds what that cannot do).
     * `--sweep-dangling` is deliberately NOT passed: a company created seconds ago cannot have
     * dangling rows of its own, and sweeping is a cross-tenant write this method has no business
     * performing. If the command REFUSES because some OTHER tenant has dangling rows, that
     * refusal is correct and surfaces here as a failed provision — CT-A3 R3 §3.4 proved that
     * minting accounts while a dangling row exists can hand another tenant's mapping a live
     * pointer, so provisioning through that hazard is not something to do quietly.
     *
     * ── Fails loudly ─────────────────────────────────────────────────────────────────────────
     * After the mapping, every engine purpose is asserted to resolve. A blocking gap throws, which
     * rolls back the whole `DB::transaction()` in `provision()`. A half-provisioned company that
     * looks finished and then throws `UnmappedPurposeException` at its first sale is strictly
     * worse than a registration that fails with a sentence saying which purposes are missing.
     *
     * @throws \RuntimeException when the chart cannot carry the engine after the repair
     */
    private function mapAccountingPurposes(Company $company): void
    {
        $exit = Artisan::call('accounting:coa-linkage', [
            '--company' => $company->id,
            '--apply' => true,
        ]);

        $health = app(PurposeHealthService::class)->inspect((int) $company->id);

        if ($exit !== 0 || $health['blocking'] > 0) {
            $names = array_merge(
                array_map(
                    fn (array $n) => $n['purpose'].($n['service_type'] !== null ? '/'.$n['service_type'] : '').' (non-leaf: #'.$n['account_id'].' '.$n['account_name'].', '.$n['child_count'].' children)',
                    $health['non_leaf']
                ),
                array_map(
                    fn (array $u) => $u['purpose'].($u['service_type'] !== null ? '/'.$u['service_type'] : '').' ('.$u['exception'].')',
                    array_values(array_filter($health['unresolved'], fn (array $u) => ! $u['deliberate']))
                )
            );

            Log::error('CompanyProvisioner: accounting purpose mapping incomplete', [
                'company_id' => $company->id,
                'coa_linkage_exit' => $exit,
                'blocking' => $health['blocking'],
                'purposes' => $names,
            ]);

            throw new \RuntimeException(sprintf(
                'Company %d cannot be provisioned: the posting engine has %d unresolvable purpose(s) after the chart repair (accounting:coa-linkage exited %d). %s',
                $company->id,
                $health['blocking'],
                $exit,
                $names === [] ? '' : 'Unresolvable: '.implode('; ', array_slice($names, 0, 12)).(count($names) > 12 ? ' …' : '')
            ));
        }

        Log::info('CompanyProvisioner: accounting purposes mapped', [
            'company_id' => $company->id,
            'resolved' => $health['resolved'],
            'of' => $health['total'],
            'deliberate_gaps' => count(array_filter($health['unresolved'], fn (array $u) => $u['deliberate'])),
        ]);
    }

    private function finalize(Company $company, ?CompanyInvite $invite): void
    {
        if ($invite) {
            $invite->markUsed($company->id);
        }
    }
}
