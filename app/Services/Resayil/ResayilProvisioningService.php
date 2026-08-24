<?php

namespace App\Services\Resayil;

use App\Models\Company;
use App\Models\ResayilAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Resayil account provisioning (Module 5), per the owner's 2026-08-24 spec:
 *
 *  1. First user = the admin user. When a company needs Resayil access, its
 *     first user provisions the company's Resayil "customer" account via the
 *     reseller API (POST /v1/resellers/customers), with a server-generated
 *     secret — NEVER the user's TravelERP password, never exposed to the
 *     front-end.
 *  2. Every subsequent TravelERP user is auto-provisioned as a Resayil team
 *     member, no manual step, no second password — up to
 *     config('resayil.max_auto_users') (default 9).
 *  3. Beyond the cap, auto-creation stops; the UI must show a clear
 *     "contact support" state instead of silently failing or over-billing.
 *  4. Idempotent: a user who already has a provisioned Resayil account is
 *     never re-created; every state transition is persisted so this can be
 *     called cheaply and repeatedly (e.g. on every embed page visit).
 *
 * HONEST LIMITATION (see the Module 5 report for full detail): step 2 — the
 * actual API call to create a per-user Resayil "team member"
 * (POST /v1/devices/{deviceId}/team) — additionally requires (a) a WhatsApp
 * number already connected for the company (a human QR-scan pairing step
 * that cannot be automated via API) and (b) an ACCOUNT-scoped Resayil API
 * token for that company, which no documented reseller endpoint returns.
 * Neither is obtainable automatically today. Until both are set on the
 * company's admin ResayilAccount row (resayil_device_id /
 * resayil_account_token — populated manually), ensureUserProvisioned()
 * returns status=pending_device rather than fabricating success.
 */
class ResayilProvisioningService
{
    public function __construct(
        protected ?ResayilClient $resellerClient = null,
    ) {
        $this->resellerClient ??= new ResayilClient(
            config('resayil.reseller_base_url'),
            config('resayil.reseller_token'),
        );
    }

    /**
     * Ensure the given user has a Resayil identity, creating one if this
     * company/user combination has never been provisioned. Safe to call on
     * every embed page visit — already-provisioned or already-terminal
     * (limit_reached / not_configured / awaiting_admin) rows short-circuit
     * with no HTTP call.
     */
    public function ensureUserProvisioned(User $user): ResayilAccount
    {
        // The resayil_accounts migration is deliberately NOT run on every
        // environment yet (dev: file created, migration not executed — see
        // the Module 5 report). Degrade gracefully rather than 500 the
        // embed routes when the table isn't there.
        if (! Schema::hasTable('resayil_accounts')) {
            return $this->transientResult($user, getCompanyId($user), ResayilAccount::STATUS_NOT_CONFIGURED, [
                'reason' => 'resayil_accounts table does not exist yet (migration not run).',
            ]);
        }

        $companyId = getCompanyId($user);

        if (! $companyId) {
            return $this->transientResult($user, null, ResayilAccount::STATUS_ERROR, [
                'reason' => 'User has no resolvable company.',
            ]);
        }

        $existing = ResayilAccount::query()
            ->forCompany($companyId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && in_array($existing->status, [
            ResayilAccount::STATUS_PROVISIONED,
            ResayilAccount::STATUS_LIMIT_REACHED,
        ], true)) {
            return $existing;
        }

        $adminRow = ResayilAccount::query()
            ->forCompany($companyId)
            ->where('role', ResayilAccount::ROLE_ADMIN)
            ->first();

        if (! $adminRow) {
            // This user becomes the company's admin.
            return $this->provisionAdmin($user, $companyId, $existing);
        }

        if ($adminRow->user_id === $user->id) {
            // The admin themself — already covered by $adminRow.
            return $adminRow;
        }

        return $this->provisionTeamMember($user, $companyId, $adminRow, $existing);
    }

    protected function provisionAdmin(User $user, int $companyId, ?ResayilAccount $existing): ResayilAccount
    {
        if (! $this->resellerClient->configured() || config('resayil.test_mode')) {
            return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_ADMIN, [
                'status' => ResayilAccount::STATUS_NOT_CONFIGURED,
            ]);
        }

        // Server-generated secret — never the TravelERP password, never
        // returned to the browser.
        $secret = Str::password(32);

        $response = $this->resellerClient->post('/customers', [
            'displayName' => $user->name ?? $user->email,
            'email' => $user->email,
            'accountType' => 'business',
            'password' => $secret,
            'country' => config('resayil.default_country', 'KW'),
        ]);

        unset($secret);

        if ($response->failed()) {
            Log::warning('resayil.provisioning.admin_failed', [
                'company_id' => $companyId,
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_ADMIN, [
                'status' => ResayilAccount::STATUS_ERROR,
                'meta' => ['http_status' => $response->status(), 'body' => $response->json()],
            ]);
        }

        $customerId = $response->json('id');

        return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_ADMIN, [
            'resayil_customer_id' => $customerId,
            'resayil_email' => $user->email,
            'status' => ResayilAccount::STATUS_PROVISIONED,
            'provisioned_at' => now(),
        ]);
    }

    protected function provisionTeamMember(
        User $user,
        int $companyId,
        ResayilAccount $adminRow,
        ?ResayilAccount $existing,
    ): ResayilAccount {
        $cap = (int) config('resayil.max_auto_users', 9);

        $provisionedCount = ResayilAccount::query()
            ->forCompany($companyId)
            ->provisioned()
            ->count();

        if ($provisionedCount >= $cap) {
            return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_AGENT, [
                'status' => ResayilAccount::STATUS_LIMIT_REACHED,
                'resayil_customer_id' => $adminRow->resayil_customer_id,
            ]);
        }

        if (! $adminRow->resayil_device_id || ! $adminRow->resayil_account_token) {
            // Blocked on a manual, company-level setup step — see class
            // docblock. Deliberately NOT attempting the API call: we have
            // neither a connected WhatsApp number id nor an account-scoped
            // token to call POST /devices/{id}/team with.
            return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_AGENT, [
                'status' => ResayilAccount::STATUS_PENDING_DEVICE,
                'resayil_customer_id' => $adminRow->resayil_customer_id,
            ]);
        }

        $accountClient = new ResayilClient(
            config('resayil.account_base_url', 'https://api.resayil.io/v1'),
            $adminRow->resayil_account_token,
        );

        $secret = Str::password(32);
        $colors = ['blue', 'azure', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'lime', 'green', 'teal', 'cyan'];

        $response = $accountClient->post("/devices/{$adminRow->resayil_device_id}/team", [
            'displayName' => $user->name ?? $user->email,
            'email' => $user->email,
            'password' => $secret,
            'role' => 'agent',
            'color' => $colors[array_rand($colors)],
        ]);

        unset($secret);

        if ($response->failed()) {
            Log::warning('resayil.provisioning.team_member_failed', [
                'company_id' => $companyId,
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_AGENT, [
                'status' => ResayilAccount::STATUS_ERROR,
                'resayil_customer_id' => $adminRow->resayil_customer_id,
                'meta' => ['http_status' => $response->status(), 'body' => $response->json()],
            ]);
        }

        return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_AGENT, [
            'resayil_customer_id' => $adminRow->resayil_customer_id,
            'resayil_user_id' => $response->json('id'),
            'resayil_email' => $user->email,
            'status' => ResayilAccount::STATUS_PROVISIONED,
            'provisioned_at' => now(),
        ]);
    }

    /**
     * Whether the company has hit its included auto-create seat count —
     * used by the embed views to render the cap-warning state.
     */
    public function capReached(Company $company): bool
    {
        if (! Schema::hasTable('resayil_accounts')) {
            return false;
        }

        $cap = (int) config('resayil.max_auto_users', 9);

        return ResayilAccount::query()
            ->forCompany($company->id)
            ->provisioned()
            ->count() >= $cap;
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    protected function save(
        ?ResayilAccount $existing,
        User $user,
        int $companyId,
        string $role,
        array $attributes,
    ): ResayilAccount {
        $row = $existing ?? new ResayilAccount([
            'company_id' => $companyId,
            'user_id' => $user->id,
        ]);

        $row->role = $existing?->role ?? $role;
        $row->fill($attributes);
        $row->save();

        return $row;
    }

    /**
     * @param  array<string,mixed>  $meta
     */
    protected function transientResult(User $user, ?int $companyId, string $status, array $meta): ResayilAccount
    {
        return new ResayilAccount([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'status' => $status,
            'meta' => $meta,
        ]);
    }
}
