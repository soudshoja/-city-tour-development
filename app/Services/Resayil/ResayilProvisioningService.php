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
 * CREDENTIAL OWNERSHIP (2026-08-25 -- see
 * .planning/RESAYIL-INTEGRATION-WORKAROUNDS.md sections 2.3/3.1): TravelERP
 * generates every Resayil password and is the source of truth for it. The
 * secret set via POST /customers (admin/workspace row) or
 * POST /devices/{id}/team (team-member row) is persisted encrypted on that
 * row's resayil_secret column (Model-level `encrypted` cast + `$hidden`) so
 * it can be shown once to the company admin during onboarding and
 * re-asserted later to heal drift. It previously was generated, sent, and
 * immediately discarded (unset()) -- which meant nobody could ever log in
 * to the account TravelERP had just created for them. Guard rails: this
 * value must never be written to a log line, an exception message, or a
 * stored `meta` blob -- see redactSecret() below, applied to every
 * logged/stored API response body since we cannot guarantee Resayil never
 * echoes request fields back.
 *
 * IDEMPOTENT CUSTOMER CREATION (2026-08-25, Fix 2): provisionAdmin() now
 * looks up an existing Resayil customer by email before creating one, and
 * treats a 409 from POST /customers as "go look it up" rather than a
 * failure -- see lookupOrCreateCustomer() below. A retried onboarding, a
 * resumed wizard, or a repaired company therefore reuses the existing
 * customer instead of failing with a duplicate-email conflict.
 *
 * HONEST LIMITATION (see the Module 5 report for full detail): step 2 -- the
 * actual API call to create a per-user Resayil "team member"
 * (POST /v1/devices/{deviceId}/team) -- additionally requires (a) a WhatsApp
 * number already connected for the company (a human QR-scan pairing step
 * that cannot be automated via API) and (b) an ACCOUNT-scoped Resayil API
 * token for that company. (b) IS NOW SOLVED -- see ACCOUNT KEY CAPTURE
 * below. (a) still is not: until a number is paired and its id is on the
 * admin row (resayil_device_id), ensureUserProvisioned() returns
 * status=pending_device rather than fabricating success.
 *
 * ACCOUNT KEY CAPTURE (2026-08-26, wave 2 -- plan
 * .planning/specs/RESAYIL-ADMIN-CENTER.md sections 2.1-2.3 and slice 2 of
 * section 10). Resayil auto-generates a per-customer account API key at
 * customer-creation time. It is READABLE by the reseller, but ONLY from the
 * DETAIL endpoint:
 *
 *     GET /v1/resellers/customers/{id}   -> full object, apiKeys[] included
 *     GET /v1/resellers/customers        -> SLIM projection, NO apiKeys AT ALL
 *
 * Both re-verified live on 2026-08-26 against a throwaway customer that was
 * created, read and then DELETEd. The POST /customers response does not
 * carry apiKeys either, so the re-read is mandatory, not an optimisation.
 * Reading the list endpoint and concluding "this customer has no key" is the
 * single easiest way to get this wrong.
 *
 * A candidate key is NEVER stored on the strength of its shape. captureAccountKey()
 * proves it first with GET {account_base_url}/devices under `Token: {candidate}`:
 * a 2xx (even an empty array, which is what a brand-new customer returns)
 * is the only thing that promotes a string to a stored credential. A failed
 * validation stores NOTHING and records a redacted reason in meta, which the
 * Admin Center renders as the plan's FETCH RULE fallback state.
 *
 * The captured value lands on resayil_accounts.resayil_account_token, which
 * has an `encrypted` cast and is in the model's $hidden list, with
 * key_source='auto'. It must never be logged, echoed, returned to a browser,
 * put in a queue payload, or written into meta.
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

    /**
     * Provision (or repair) ONE company's Resayil workspace, with the
     * company stated explicitly rather than derived from the user.
     *
     * This is the entry point for anything that runs OUTSIDE an HTTP
     * request — the ProvisionResayilWorkspace job and the
     * resayil:provision-company command. ensureUserProvisioned() derives
     * the company with getCompanyId(), and that helper returns
     * `session('company_id', 1)` for a role-1 ADMIN user. There is no
     * session in a queue worker, so an admin-owned company provisioned
     * through that path would silently be attributed to COMPANY 1. Taking
     * the id as an argument removes the whole class of mistake.
     *
     * Idempotent: an existing admin row is never re-created — it is only
     * asked whether its account key still needs capturing.
     */
    public function provisionCompanyAdmin(int $companyId, User $owner): ResayilAccount
    {
        if (! Schema::hasTable('resayil_accounts')) {
            return $this->transientResult($owner, $companyId, ResayilAccount::STATUS_NOT_CONFIGURED, [
                'reason' => 'resayil_accounts table does not exist yet (migration not run).',
            ]);
        }

        $adminRow = ResayilAccount::adminFor($companyId);

        if ($adminRow) {
            // The workspace already exists. The only thing that may still
            // be outstanding is the account key — capture is a no-op when
            // one is already stored.
            return $this->captureAccountKey($adminRow);
        }

        $existing = ResayilAccount::query()
            ->forCompany($companyId)
            ->where('user_id', $owner->id)
            ->first();

        return $this->provisionAdmin($owner, $companyId, $existing);
    }

    protected function provisionAdmin(User $user, int $companyId, ?ResayilAccount $existing): ResayilAccount
    {
        if (! $this->resellerClient->configured() || config('resayil.test_mode')) {
            return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_ADMIN, [
                'status' => ResayilAccount::STATUS_NOT_CONFIGURED,
            ]);
        }

        // Fix 2: lookup-then-create. Any re-run of provisioning for an
        // email that already exists as a Resayil customer (retried
        // onboarding, resumed wizard, repaired company) must adopt that
        // customer instead of blind-POSTing and hitting a 409.
        $found = $this->findCustomerByEmail($user->email);

        if ($found !== null) {
            $row = $this->save($existing, $user, $companyId, ResayilAccount::ROLE_ADMIN, [
                'resayil_customer_id' => $found['id'],
                'resayil_email' => $user->email,
                'status' => ResayilAccount::STATUS_PROVISIONED,
                'provisioned_at' => now(),
                'meta' => $this->mergeMeta($existing, ['adopted' => true]),
            ]);

            // An adopted customer already has its auto-generated key; it is
            // exactly as capturable as a freshly created one.
            return $this->captureAccountKey($row);
        }

        // Server-generated secret — never the TravelERP password, never
        // returned to the browser. TravelERP owns this credential: it is
        // persisted encrypted below (resayil_secret) rather than discarded,
        // so it can be shown once during onboarding and re-asserted later.
        $secret = Str::password(32);

        // `companyName` is MANDATORY for accountType=business. Without it
        // the API answers 400 "Company name is required for business
        // accounts" — reproduced live on 2026-08-26, and the reason every
        // new company's provisioning was landing in status=error before
        // this wave. The documented Customer model lists companyName as an
        // optional field; the live API disagrees.
        try {
            $response = $this->resellerClient->post('/customers', [
                'displayName' => $user->name ?? $user->email,
                'email' => $user->email,
                'accountType' => 'business',
                'companyName' => $this->companyNameFor($companyId, $user),
                'password' => $secret,
                'country' => config('resayil.default_country', 'KW'),
            ]);
        } catch (\Throwable $e) {
            // retry(throw: false) suppresses throwing on a failed HTTP
            // *response* only — a DNS/connect failure still throws. This is
            // reached from a queued job AND from an embed page render, and
            // the app has no custom error pages, so it must degrade rather
            // than escape. A transient state, deliberately NOT written as
            // status=error: the row stays retryable on the next run.
            unset($secret);

            Log::warning('resayil.provisioning.admin_exception', [
                'company_id' => $companyId,
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_ADMIN, [
                'status' => ResayilAccount::STATUS_PENDING,
                'meta' => $this->mergeMeta($existing, ['unreachable_at' => now()->toIso8601String()]),
            ]);
        }

        // Race: two requests both found no existing customer and both
        // POSTed. The loser gets a 409 back — resolve it by re-querying
        // rather than surfacing a failure, so the whole method stays
        // safely re-runnable under concurrency.
        if ($response->status() === 409) {
            $adopted = $this->findCustomerByEmail($user->email);

            if ($adopted !== null) {
                unset($secret);

                $row = $this->save($existing, $user, $companyId, ResayilAccount::ROLE_ADMIN, [
                    'resayil_customer_id' => $adopted['id'],
                    'resayil_email' => $user->email,
                    'status' => ResayilAccount::STATUS_PROVISIONED,
                    'provisioned_at' => now(),
                    'meta' => $this->mergeMeta($existing, ['adopted' => true, 'adopted_after_conflict' => true]),
                ]);

                return $this->captureAccountKey($row);
            }
            // Fall through to the generic failure branch below if the
            // 409-causing customer still can't be found (transient lookup
            // failure) — retryable on the next call.
        }

        if ($response->failed()) {
            unset($secret);

            Log::warning('resayil.provisioning.admin_failed', [
                'company_id' => $companyId,
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $this->redactSecret($response->json() ?? $response->body()),
            ]);

            return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_ADMIN, [
                'status' => ResayilAccount::STATUS_ERROR,
                'meta' => $this->mergeMeta($existing, [
                    'http_status' => $response->status(),
                    'body' => $this->redactSecret($response->json()),
                ]),
            ]);
        }

        $customerId = $response->json('id');

        $account = $this->save($existing, $user, $companyId, ResayilAccount::ROLE_ADMIN, [
            'resayil_customer_id' => $customerId,
            'resayil_email' => $user->email,
            'resayil_secret' => $secret,
            'status' => ResayilAccount::STATUS_PROVISIONED,
            'provisioned_at' => now(),
        ]);

        unset($secret);

        // The whole point of wave 2: the customer exists, so its
        // auto-generated account key exists too — go and prove it, then
        // store it. Never inside the same call as the create: the POST
        // response does not carry apiKeys (verified live), so this is a
        // second, separate read of the DETAIL endpoint.
        return $this->captureAccountKey($account);
    }

    /**
     * Read, VALIDATE and store this company's Resayil account API key.
     *
     * Idempotent and safe to re-run: a row that already holds a token is
     * returned untouched unless $force is set. Never throws — a failure is
     * recorded on the row (redacted) and surfaced by the Admin Center as
     * the FETCH RULE fallback, because a company must never be left half
     * provisioned by an exception escaping into a page render or a queue
     * worker's retry loop.
     *
     * SECURITY: the captured value is written straight onto the encrypted
     * column and is never returned, logged, or copied into meta. The only
     * things that reach meta are the key's non-secret id/alias and a
     * failure reason.
     */
    public function captureAccountKey(ResayilAccount $row, bool $force = false): ResayilAccount
    {
        if (! $row->exists) {
            return $row;
        }

        if ($row->resayil_account_token && ! $force) {
            return $row;
        }

        if (! $row->resayil_customer_id) {
            return $row;
        }

        if (! $this->resellerClient->configured() || config('resayil.test_mode')) {
            return $row;
        }

        $result = $this->fetchAccountKey($row->resayil_customer_id);

        if (! ($result['ok'] ?? false) || ! is_string($result['key'] ?? null) || $result['key'] === '') {
            Log::warning('resayil.provisioning.key_capture_failed', [
                'company_id' => $row->company_id,
                'reason' => $result['reason'] ?? 'unknown',
                'http_status' => $result['http_status'] ?? null,
            ]);

            $meta = is_array($row->meta) ? $row->meta : [];
            $meta['key_capture_failed'] = [
                'at' => now()->toIso8601String(),
                'reason' => $result['reason'] ?? 'unknown',
                'http_status' => $result['http_status'] ?? null,
            ];
            unset($meta['key_captured_at']);

            $row->forceFill(['meta' => $meta])->save();

            return $row;
        }

        $meta = is_array($row->meta) ? $row->meta : [];
        unset($meta['key_capture_failed'], $meta['reconnect_needed']);
        $meta['key_captured_at'] = now()->toIso8601String();
        // Non-secret identifiers only. NEVER the value.
        $meta['key_id'] = $result['key_id'] ?? null;
        $meta['key_alias'] = $result['alias'] ?? null;

        $row->forceFill([
            'resayil_account_token' => $result['key'],
            'key_source' => ResayilAccount::KEY_SOURCE_AUTO,
            'meta' => $meta,
        ])->save();

        // Log the EVENT, never the credential — not even a prefix or a
        // length, which would narrow a brute force.
        Log::info('resayil.provisioning.key_captured', [
            'company_id' => $row->company_id,
            'key_id' => $result['key_id'] ?? null,
            'alias' => $result['alias'] ?? null,
        ]);

        return $row;
    }

    /**
     * Fetch a customer's account API key from the DETAIL endpoint and prove
     * it works before handing it back.
     *
     * @return array{ok:bool,key:?string,key_id:?string,alias:?string,reason:?string,http_status:?int}
     */
    public function fetchAccountKey(string $customerId): array
    {
        $fail = fn (string $reason, ?int $status = null): array => [
            'ok' => false, 'key' => null, 'key_id' => null, 'alias' => null,
            'reason' => $reason, 'http_status' => $status,
        ];

        try {
            // DETAIL endpoint. The list endpoint omits apiKeys entirely —
            // see the class docblock. Do not "optimise" this into the list.
            $response = $this->resellerClient->get('/customers/'.$customerId);
        } catch (\Throwable $e) {
            // Connection-level failure: ResayilClient::retry(throw: false)
            // suppresses throwing only for failed HTTP *responses*.
            Log::warning('resayil.provisioning.key_detail_exception', [
                'customer_id' => $customerId,
                'exception' => $e->getMessage(),
            ]);

            return $fail('detail_read_exception');
        }

        if ($response->failed()) {
            Log::warning('resayil.provisioning.key_detail_failed', [
                'customer_id' => $customerId,
                'status' => $response->status(),
                'body' => $this->redactSecret($response->json()),
            ]);

            return $fail('detail_read_failed', $response->status());
        }

        $body = $response->json();
        $keys = is_array($body) ? ($body['apiKeys'] ?? null) : null;

        if (! is_array($keys) || $keys === []) {
            // A deleted (status 20) customer returns an empty apiKeys[] —
            // observed live. So does a customer whose keys were all
            // revoked. Both are "no credential to capture", not an error to
            // retry forever.
            return $fail('no_api_keys');
        }

        $entry = static::selectApiKey($keys);

        if ($entry === null) {
            return $fail('no_usable_api_key');
        }

        $candidate = (string) $entry['value'];

        // NEVER store an unproven string as a credential. A 2xx here —
        // including the empty `[]` a brand-new customer returns — is the
        // only proof that this value is a working ACCOUNT key.
        try {
            $probe = new ResayilClient(
                config('resayil.account_base_url', 'https://api.resayil.io/v1'),
                $candidate,
            );

            $validation = $probe->get('/devices');
        } catch (\Throwable $e) {
            Log::warning('resayil.provisioning.key_validation_exception', [
                'customer_id' => $customerId,
                'exception' => $e->getMessage(),
            ]);

            return $fail('validation_exception');
        }

        if (! $validation->successful()) {
            Log::warning('resayil.provisioning.key_validation_failed', [
                'customer_id' => $customerId,
                'status' => $validation->status(),
            ]);

            return $fail('validation_failed', $validation->status());
        }

        return [
            'ok' => true,
            'key' => $candidate,
            'key_id' => isset($entry['id']) ? (string) $entry['id'] : null,
            'alias' => isset($entry['alias']) ? (string) $entry['alias'] : null,
            'reason' => null,
            'http_status' => $validation->status(),
        ];
    }

    /**
     * Choose which apiKeys[] entry to adopt (plan §2.3): the `isDefault`
     * one, falling back to the first with `status: 50` (= active), falling
     * back to the first entry that carries a value at all.
     *
     * Static and pure so it can be unit-tested without a database, a
     * company, or a network.
     *
     * Real customers accumulate many keys — City Travelers holds 13, one
     * per integration. Borrowing `Default` is the plan's deliberate choice:
     * the reseller API offers no way to MINT a dedicated key (V-1b, still
     * open), and revoking or rotating someone's Default key would break
     * their other integrations. So we read it and never touch it.
     *
     * @param  array<int,mixed>  $apiKeys
     * @return array<string,mixed>|null
     */
    public static function selectApiKey(array $apiKeys): ?array
    {
        $usable = [];

        foreach ($apiKeys as $entry) {
            if (is_array($entry) && is_string($entry['value'] ?? null) && $entry['value'] !== '') {
                $usable[] = $entry;
            }
        }

        if ($usable === []) {
            return null;
        }

        $active = fn (array $e): bool => (int) ($e['status'] ?? 0) === 50;
        $default = fn (array $e): bool => ($e['isDefault'] ?? false) === true;

        foreach ($usable as $entry) {
            if ($default($entry) && $active($entry)) {
                return $entry;
            }
        }

        foreach ($usable as $entry) {
            if ($default($entry)) {
                return $entry;
            }
        }

        foreach ($usable as $entry) {
            if ($active($entry)) {
                return $entry;
            }
        }

        return $usable[0];
    }

    /**
     * The company name Resayil requires on a business account. Falls back
     * through the user's own name to their email so a company row with a
     * blank name can never produce the 400 this field exists to avoid.
     */
    protected function companyNameFor(int $companyId, User $user): string
    {
        $name = Company::query()->whereKey($companyId)->value('name');

        $name = is_string($name) ? trim($name) : '';

        if ($name !== '') {
            return $name;
        }

        return trim((string) ($user->name ?? '')) ?: $user->email;
    }

    /**
     * Merge new diagnostic keys into an existing row's meta instead of
     * replacing it wholesale. The admin row's meta is a small audit trail
     * (admin_contact_phone, subscription_actions, key_captured_at) that a
     * re-run of provisioning must not silently erase.
     *
     * @param  array<string,mixed>  $additions
     * @return array<string,mixed>
     */
    protected function mergeMeta(?ResayilAccount $existing, array $additions): array
    {
        $current = is_array($existing?->meta) ? $existing->meta : [];

        return array_merge($current, $additions);
    }

    /**
     * Look up an existing Resayil customer by exact email match against the
     * reseller API. Returns the raw customer array (at least `id`) or null
     * if none exists / the lookup itself failed (treated as "not found" so
     * the caller falls back to create — never blocks provisioning on a
     * flaky lookup).
     *
     * THE FILTER PARAMETER IS `search`, NOT `email` (established live,
     * 2026-08-26). `?email=`, `?q=`, `?query=`, `?filter=` and `?term=` are
     * all silently IGNORED — each returns the same unfiltered first 20 of
     * ~97 customers, which made this method a coin flip: a customer that
     * happened to fall outside page 1 was reported "not found", the caller
     * blind-POSTed, and the API answered 409. `?search=<email>` returns
     * exactly the matching row (and `[]` for no match), so lookup-then-create
     * is now genuinely idempotent rather than accidentally so for recent
     * customers only.
     *
     * `search` is a fuzzy match, so the exact strcasecmp() check below is
     * load-bearing, not belt-and-braces: it stops a substring match on a
     * different customer's address from being adopted as this one.
     *
     * @return array<string,mixed>|null
     */
    protected function findCustomerByEmail(string $email): ?array
    {
        try {
            $response = $this->resellerClient->get('/customers', ['search' => $email]);
        } catch (\Throwable $e) {
            Log::warning('resayil.provisioning.customer_lookup_exception', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $body = $response->json();

        // The endpoint may return either a bare array of customers or a
        // paginated envelope (e.g. {"data": [...]} / {"items": [...]}) —
        // handle both defensively rather than assuming one shape.
        $candidates = $body['data'] ?? $body['items'] ?? $body['results'] ?? $body;

        if (! is_array($candidates)) {
            return null;
        }

        // A single-object response (not a list) also counts as a match.
        if (isset($candidates['id']) && ! isset($candidates[0])) {
            return $candidates;
        }

        foreach ($candidates as $candidate) {
            if (is_array($candidate)
                && isset($candidate['email'])
                && strcasecmp((string) $candidate['email'], $email) === 0
            ) {
                return $candidate;
            }
        }

        return null;
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

        // Server-generated secret — same ownership model as provisionAdmin:
        // persisted encrypted (resayil_secret) below, never discarded.
        $secret = Str::password(32);
        $colors = ['blue', 'azure', 'indigo', 'purple', 'pink', 'red', 'orange', 'yellow', 'lime', 'green', 'teal', 'cyan'];

        $response = $accountClient->post("/devices/{$adminRow->resayil_device_id}/team", [
            'displayName' => $user->name ?? $user->email,
            'email' => $user->email,
            'password' => $secret,
            'role' => 'agent',
            'color' => $colors[array_rand($colors)],
        ]);

        if ($response->failed()) {
            unset($secret);

            Log::warning('resayil.provisioning.team_member_failed', [
                'company_id' => $companyId,
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $this->redactSecret($response->json() ?? $response->body()),
            ]);

            return $this->save($existing, $user, $companyId, ResayilAccount::ROLE_AGENT, [
                'status' => ResayilAccount::STATUS_ERROR,
                'resayil_customer_id' => $adminRow->resayil_customer_id,
                'meta' => $this->mergeMeta($existing, [
                    'http_status' => $response->status(),
                    'body' => $this->redactSecret($response->json()),
                ]),
            ]);
        }

        $account = $this->save($existing, $user, $companyId, ResayilAccount::ROLE_AGENT, [
            'resayil_customer_id' => $adminRow->resayil_customer_id,
            'resayil_user_id' => $response->json('id'),
            'resayil_email' => $user->email,
            'resayil_secret' => $secret,
            'status' => ResayilAccount::STATUS_PROVISIONED,
            'provisioned_at' => now(),
        ]);

        unset($secret);

        return $account;
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

    /**
     * Defense-in-depth: strip any password-shaped field from an API
     * response body before it is logged or persisted into the `meta`
     * column. We do not control what Resayil echoes back in an error body,
     * and a generated secret must never reach a log line, an exception
     * message, or a stored diagnostic blob.
     *
     * @param  mixed  $body
     * @return mixed
     */
    protected function redactSecret(mixed $body): mixed
    {
        if (! is_array($body)) {
            return $body;
        }

        // Extended for wave 2 (plan §9.1): this class now reads a response
        // body that legitimately CONTAINS a live account key
        // (apiKeys[].value), so `value`, `token`, `key` and friends join the
        // list. Over-redacting a diagnostic is always cheaper than
        // under-redacting a credential.
        $redactKeys = [
            'password', 'secret', 'resayil_secret', 'token',
            'apikey', 'api_key', 'key', 'authcode', 'value',
        ];

        array_walk_recursive($body, function (&$value, $key) use ($redactKeys) {
            if (is_string($key) && in_array(strtolower($key), $redactKeys, true)) {
                $value = '[redacted]';
            }
        });

        return $body;
    }
}
