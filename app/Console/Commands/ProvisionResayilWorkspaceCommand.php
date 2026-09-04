<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionResayilWorkspace;
use App\Models\Company;
use App\Models\ResayilAccount;
use App\Models\User;
use App\Services\Resayil\ResayilProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Retrofit an existing company with a Resayil workspace + account key.
 *
 * Plan: .planning/specs/RESAYIL-ADMIN-CENTER.md slice 2 (§10), W-§7.13.
 *
 * New companies get this automatically at creation (CompanyProvisioner
 * dispatches ProvisionResayilWorkspace after commit). Every company that
 * predates that wiring needs one deliberate run — this command — and so
 * does any company whose key capture failed and has since been fixed
 * upstream.
 *
 *   php artisan resayil:provision-company 3
 *   php artisan resayil:provision-company ACME --sync
 *   php artisan resayil:provision-company 3 --dry-run
 *   php artisan resayil:provision-company 3 --sync --recapture-key
 *
 * Safe to run repeatedly against the same company: everything underneath
 * is idempotent (see the job's docblock). It NEVER creates a WhatsApp
 * number — that costs money and belongs to a later slice.
 */
class ProvisionResayilWorkspaceCommand extends Command
{
    protected $signature = 'resayil:provision-company
                            {company : Company id, or company code}
                            {--sync : Run now in this process instead of queueing}
                            {--dry-run : Report what would happen and change nothing}
                            {--recapture-key : Re-read and re-validate the account key even if one is already stored}
                            {--confirm-adoption : Link a pending adoption candidate (status=adoption_pending) and capture its key. Run ONLY after verifying OUTSIDE this app that the candidate really belongs to this company — see class docblock, blocker 2b}';

    protected $description = 'Create (or repair) a company Resayil workspace and capture its account API key';

    public function handle(ResayilProvisioningService $provisioning): int
    {
        if (! Schema::hasTable('resayil_accounts')) {
            $this->error('The resayil_accounts table does not exist in this environment. Run its migration first.');

            return self::FAILURE;
        }

        $company = $this->resolveCompany((string) $this->argument('company'));

        if (! $company) {
            $this->error('No company matched "'.$this->argument('company').'".');

            return self::FAILURE;
        }

        Company::forgetModuleCache();

        $owner = $company->user_id ? User::find($company->user_id) : null;
        $row = ResayilAccount::adminFor($company->id);

        $this->line('');
        $this->line("Company        : #{$company->id} {$company->name}".($company->code ? " ({$company->code})" : ''));
        $this->line('Owner user     : '.($owner ? "#{$owner->id} {$owner->email}" : 'MISSING'));
        $this->line('module.resayil : '.($company->hasModule('resayil') ? 'enabled' : 'DISABLED'));
        $this->line('Admin row      : '.($row ? "#{$row->id} status={$row->status}" : 'none'));
        $this->line('Workspace id   : '.($row?->resayil_customer_id ?: '—'));
        // A boolean, never the value and never its length.
        $this->line('Account key    : '.($row?->resayil_account_token ? 'linked ('.($row->key_source ?: 'unknown source').')' : 'not linked'));
        $this->line('');

        // Security fix (2026-08-26, blocker 2b): an adoption-pending row
        // (see ResayilProvisioningService::recordAdoptionCandidate()) is
        // handled entirely separately, and requires no owner-email check —
        // confirmAdoption() works purely from the company id and the
        // candidate already recorded in meta.
        if ($this->option('confirm-adoption')) {
            return $this->handleConfirmAdoption($company, $row, $provisioning);
        }

        if (! $owner) {
            $this->error('This company has no owner user (companies.user_id), so there is no email to open a workspace under.');

            return self::FAILURE;
        }

        if (! $company->hasModule('resayil')) {
            $this->warn('module.resayil is disabled for this company — the job would skip it. Enable the module first.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->comment($this->plannedAction($row));
            $this->comment('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('sync')) {
            ProvisionResayilWorkspace::dispatch($company->id, $owner->id);
            $this->info('Queued ProvisionResayilWorkspace for company #'.$company->id.'.');

            return self::SUCCESS;
        }

        $this->comment($this->plannedAction($row));

        $account = $provisioning->provisionCompanyAdmin($company->id, $owner);

        if ($this->option('recapture-key') && $account->exists) {
            $account = $provisioning->captureAccountKey($account, force: true);
        } elseif ($account->exists && ! $account->resayil_account_token) {
            $account = $provisioning->captureAccountKey($account);
        }

        $this->line('');
        $this->line('Result         : status='.$account->status);
        $this->line('Workspace id   : '.($account->resayil_customer_id ?: '—'));
        $this->line('Account key    : '.($account->resayil_account_token ? 'LINKED ('.($account->key_source ?: 'unknown source').')' : 'NOT linked'));

        $failure = is_array($account->meta) ? ($account->meta['key_capture_failed'] ?? null) : null;

        if (is_array($failure)) {
            $this->warn('Key capture failed: '.($failure['reason'] ?? 'unknown')
                .(isset($failure['http_status']) ? ' (HTTP '.$failure['http_status'].')' : ''));
        }

        return $account->resayil_customer_id ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Handle `--confirm-adoption` — the ONLY path that links a candidate
     * recorded by recordAdoptionCandidate() and captures its key. See
     * ResayilProvisioningService::confirmAdoption() and the class docblock
     * (blocker 2b): this is a deliberate human checkpoint, not something
     * any automatic path in this app ever reaches on its own.
     */
    protected function handleConfirmAdoption(Company $company, ?ResayilAccount $row, ResayilProvisioningService $provisioning): int
    {
        if (! $row || $row->status !== ResayilAccount::STATUS_ADOPTION_PENDING) {
            $this->error('Nothing to confirm — this company\'s admin row is not in status=adoption_pending.');

            return self::FAILURE;
        }

        $candidate = is_array($row->meta) ? ($row->meta['adoption_candidate'] ?? []) : [];

        $this->comment('Pending adoption candidate:');
        $this->line('  Resayil customer id    : '.($candidate['customer_id'] ?? '—'));
        $this->line('  Resayil customer email : '.($candidate['email'] ?? '—'));
        $this->line('  Found at               : '.($candidate['found_at'] ?? '—'));
        $this->line('');
        $this->warn('This will link the candidate above as this company\'s permanent Resayil workspace and capture its LIVE account API key.');
        $this->warn('Proceed only if you have verified, outside this app, that it belongs to this company.');

        if ($this->option('dry-run')) {
            $this->comment('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $account = $provisioning->confirmAdoption($company->id);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->line('Result         : status='.$account->status);
        $this->line('Workspace id   : '.($account->resayil_customer_id ?: '—'));
        $this->line('Account key    : '.($account->resayil_account_token ? 'LINKED ('.($account->key_source ?: 'unknown source').')' : 'NOT linked'));

        return $account->resayil_customer_id ? self::SUCCESS : self::FAILURE;
    }

    protected function plannedAction(?ResayilAccount $row): string
    {
        if ($row?->status === ResayilAccount::STATUS_ADOPTION_PENDING) {
            return 'A Resayil customer matching this email was already found but NOT adopted — ownership was unproven (see meta.adoption_candidate). '
                .'Re-run with --confirm-adoption after verifying, outside this app, that it belongs to this company.';
        }

        if (! $row?->resayil_customer_id) {
            return 'Would look up the owner email on Resayil. If no existing customer is found, would create one and capture its key. '
                .'If one IS found, it will be recorded for review — NOT auto-adopted (security fix, blocker 2b) — and needs --confirm-adoption afterwards.';
        }

        if ($this->option('recapture-key')) {
            return 'Workspace exists. Would RE-READ and re-validate the account key, replacing the stored one.';
        }

        if ($row->resayil_account_token) {
            return 'Workspace and key are both already in place. Nothing to do (use --recapture-key to force a re-read).';
        }

        return 'Workspace exists but no account key is stored. Would read the customer detail endpoint, validate the key, and store it.';
    }

    protected function resolveCompany(string $needle): ?Company
    {
        if (ctype_digit($needle)) {
            $byId = Company::find((int) $needle);

            if ($byId) {
                return $byId;
            }
        }

        return Company::where('code', $needle)->first();
    }
}
