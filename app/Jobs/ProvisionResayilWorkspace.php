<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\ResayilAccount;
use App\Models\User;
use App\Services\Resayil\ResayilAdminService;
use App\Services\Resayil\ResayilProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Create a company's Resayil workspace and capture its account API key,
 * with no manual step for the client.
 *
 * Plan: .planning/specs/RESAYIL-ADMIN-CENTER.md §3.1 / §3.3 / slice 2 of §10.
 *
 * WHY THIS IS A JOB AND NOT A METHOD CALL
 * Company creation is one DB::transaction (CompanyProvisioner::provision).
 * An external HTTP call must never be able to roll that back: a Resayil
 * outage would then destroy a company that was otherwise fully provisioned,
 * with a chart of accounts, branch, roles and agent users already seeded.
 * So this is dispatched AFTER commit, exactly like the agent welcome mail
 * that CompanyProvisioner already sends post-commit for the same reason.
 * The company exists whether or not Resayil answers.
 *
 * WHY IT TAKES IDS, NOT MODELS
 * Two reasons, both security. (1) A queued payload is written to the
 * `jobs` table in plaintext; a serialized ResayilAccount would put an
 * encrypted-at-rest token into a table that is not. Ids only, and the job
 * re-reads the row in-process. (2) SerializesModels would resolve the
 * models at dispatch time, so a company edited between dispatch and run
 * would be provisioned from stale values.
 *
 * WHY IT IS SAFE TO RUN AGAIN, ALWAYS
 * Every step underneath is idempotent: the customer is looked up by email
 * before being created (and a 409 race is adopted, not failed), and key
 * capture is a no-op once a token is stored. Re-running this job for a
 * fully provisioned company performs one cheap read and changes nothing.
 * That is what makes the retry policy below safe, and what lets
 * `resayil:provision-company` be pointed at any company at any time.
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 * It never creates a DEVICE (a WhatsApp number). POST /devices starts a
 * PAID subscription the moment it is called (plan §5.4, V-4) and is gated
 * behind config('resayil.device_creation_enabled') in a later slice. This
 * job only ever creates a customer record and reads a key — both free.
 */
class ProvisionResayilWorkspace implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Three attempts with a widening gap. A Resayil 5xx or a DNS blip is
     * the expected failure and clears on its own; anything that still
     * fails after ~6 minutes needs a human, not a fourth attempt.
     */
    public int $tries = 3;

    public function __construct(
        public readonly int $companyId,
        public readonly ?int $ownerUserId = null,
    ) {}

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ResayilProvisioningService $provisioning): void
    {
        if (! Schema::hasTable('resayil_accounts')) {
            Log::warning('resayil.workspace_job.skipped', [
                'company_id' => $this->companyId,
                'reason' => 'resayil_accounts table does not exist in this environment',
            ]);

            return;
        }

        $company = Company::find($this->companyId);

        if (! $company) {
            // A company deleted between dispatch and run. Not an error
            // worth retrying — retrying cannot make it exist.
            Log::warning('resayil.workspace_job.skipped', [
                'company_id' => $this->companyId,
                'reason' => 'company not found',
            ]);

            return;
        }

        // hasModule() memoises per process; a queue worker is a long-lived
        // process, so clear the memo before reading an entitlement that may
        // have been written by the request that dispatched this job.
        Company::forgetModuleCache();

        if (! $company->hasModule('resayil')) {
            // A company without the WhatsApp module has no use for a
            // Resayil workspace, and creating one would put a stranger's
            // record on the reseller account. Granting the module later
            // re-dispatches this (retrofit command / first Module-5 visit).
            Log::info('resayil.workspace_job.skipped', [
                'company_id' => $this->companyId,
                'reason' => 'module.resayil is disabled for this company',
            ]);

            return;
        }

        $owner = $this->resolveOwner($company);

        if (! $owner) {
            Log::warning('resayil.workspace_job.skipped', [
                'company_id' => $this->companyId,
                'reason' => 'no owner user to provision the workspace under',
            ]);

            return;
        }

        $before = ResayilAccount::adminFor($this->companyId);

        // Explicit company id — never getCompanyId($owner), which resolves
        // a role-1 ADMIN to session('company_id', 1) and would attribute
        // this workspace to company 1 inside a session-less worker.
        $account = $provisioning->provisionCompanyAdmin($this->companyId, $owner);

        // Second pass, deliberately: provisionCompanyAdmin() already
        // attempts capture, but an adopted row created before this wave
        // existed reaches here with a customer id and no token. This is the
        // no-op path for everything already linked.
        if ($account->exists && ! $account->resayil_account_token) {
            $account = $provisioning->captureAccountKey($account);
        }

        // The Admin Center caches its overview per company for 60 s. Drop
        // it so Settings -> WhatsApp reflects the new workspace at once
        // instead of showing "not set up yet" for another minute.
        try {
            app(ResayilAdminService::class)->forget($this->companyId);
        } catch (\Throwable $e) {
            Log::warning('resayil.workspace_job.cache_forget_failed', [
                'company_id' => $this->companyId,
                'exception' => $e->getMessage(),
            ]);
        }

        // Booleans and ids only. Never the token, never the secret, never
        // a response body.
        Log::info('resayil.workspace_job.done', [
            'company_id' => $this->companyId,
            'owner_user_id' => $owner->id,
            'status' => $account->status,
            'customer_id' => $account->resayil_customer_id,
            'had_workspace_before' => (bool) $before?->resayil_customer_id,
            'key_linked' => (bool) $account->resayil_account_token,
            'key_source' => $account->key_source,
        ]);
    }

    /**
     * The user whose email becomes the Resayil workspace login: the id
     * handed in at dispatch, else the company's owner of record.
     */
    protected function resolveOwner(Company $company): ?User
    {
        if ($this->ownerUserId) {
            $user = User::find($this->ownerUserId);

            if ($user) {
                return $user;
            }
        }

        return $company->user_id ? User::find($company->user_id) : null;
    }

    public function failed(\Throwable $e): void
    {
        // The message only — never the exception's request context, which
        // on an HTTP failure carries the Token header.
        Log::error('resayil.workspace_job.failed', [
            'company_id' => $this->companyId,
            'exception' => $e->getMessage(),
        ]);
    }
}
