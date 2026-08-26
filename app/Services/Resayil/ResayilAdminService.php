<?php

namespace App\Services\Resayil;

use App\Jobs\ProvisionResayilWorkspace;
use App\Models\Company;
use App\Models\ResayilAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resayil Admin Center — the ONLY place the app talks to Resayil on behalf
 * of a company's Settings -> WhatsApp panels.
 *
 * Plan: .planning/specs/RESAYIL-ADMIN-CENTER.md, slice 1 (§10).
 *
 * WHAT THIS SLICE COVERS
 * Everything here runs on the RESELLER (operator) token alone — Panel 1
 * Overview, Panel 4 Billing (subscription + payment history), and the
 * operator pause/resume lever (§5.5). That is the whole point of slice 1:
 * a client's first login is never an empty screen, because the reseller
 * token can already see their workspace, device, subscription, health and
 * payments before any per-company account key exists.
 *
 * FOUR RULES THIS CLASS ENFORCES, AND WHY
 *
 * 1. TENANT ISOLATION (§9.3). The reseller token can read all 97 customers
 *    on the platform. Every method here takes a TravelERP company id,
 *    resolves that company's own admin row, and reads ids from the row.
 *    No method accepts a Resayil id, and the device list is re-filtered on
 *    `device.user.id` after the API's own `?user=` filter, so a server-side
 *    filter regression cannot leak another customer's number.
 *
 * 2. NO PRICE EVER REACHES A CLIENT (owner decision D-1, §5.2). The live
 *    `device.billing.subscription` carries a `price` field (e.g. 124.9).
 *    projectSubscription() is an explicit ALLOW-LIST, not a blocklist:
 *    price/fee/finalPrice are simply never copied out, so they cannot
 *    reach a view, the JSON poller, or the persisted subscription_cache.
 *    If you add a field here, add it to the allow-list deliberately.
 *
 * 3. NOTHING RAW EVER REACHES THE CLIENT (§8). The app has no custom error
 *    pages, so an uncaught exception IS what the client sees. ResayilClient
 *    uses `->retry(..., throw: false)`, which suppresses throwing only on a
 *    failed HTTP *response* — a DNS failure or connect timeout still throws
 *    ConnectionException straight out of retry(). Every outbound call here
 *    is therefore wrapped in catch(\Throwable) and degrades to the §8 D-1
 *    state: last-known-good `subscription_cache` plus an amber staleness
 *    line.
 *
 * 4. NO SECRET IS LOGGED OR CACHED (§9.1). The reseller token lives only in
 *    config and is injected by ResayilClient, which never logs. Responses
 *    are passed through redact() before any Log:: call, and the cached /
 *    persisted projection contains no token, key or password field.
 */
class ResayilAdminService
{
    /** Platform connection not configured — no reseller token (§8 N-0). */
    public const STATE_NOT_CONFIGURED = 'not_configured';

    /** No admin row for this company yet — slice 2's job creates it. */
    public const STATE_NOT_PROVISIONED = 'not_provisioned';

    /** Admin row exists but has not finished provisioning (§8 N-1). */
    public const STATE_PROVISIONING = 'provisioning';

    /** Admin row is in error (§8 N-2). */
    public const STATE_ERROR = 'error';

    /**
     * A Resayil customer matching this email was found but NOT adopted —
     * ownership was never proven (security fix, blocker 2b). Waiting on a
     * human operator to confirm via
     * `resayil:provision-company --confirm-adoption`.
     */
    public const STATE_ADOPTION_PENDING = 'adoption_pending';

    /** Workspace known — reseller reads drive the panels. */
    public const STATE_READY = 'ready';

    public function __construct(
        protected ?ResayilClient $reseller = null,
    ) {
        // ResayilClient's own defaults already point at the reseller
        // surface (config('resayil.reseller_base_url') + reseller_token).
        // Passing them explicitly keeps the dependency visible and lets a
        // test inject a differently-configured client.
        $this->reseller ??= new ResayilClient(
            config('resayil.reseller_base_url'),
            config('resayil.reseller_token'),
        );
    }

    /**
     * Is the platform-wide reseller connection configured at all? False
     * renders §8 state N-0 for the whole section — an operator problem,
     * never the client's.
     */
    public function platformConfigured(): bool
    {
        return $this->reseller->configured();
    }

    /**
     * The full Overview + Billing payload for one company.
     *
     * Cached per company (never globally — a global key would serve one
     * company's device to another). A healthy payload is cached for
     * config('resayil.admin_cache_ttl') seconds; a degraded one for a much
     * shorter window, so recovery is picked up quickly without letting a
     * 4-second poll hammer an API that is already failing.
     *
     * @return array<string,mixed>
     */
    public function overview(int $companyId, bool $fresh = false): array
    {
        $key = $this->cacheKey($companyId);

        if (! $fresh) {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                $cached['cached'] = true;

                return $cached;
            }
        }

        $payload = $this->buildOverview($companyId);
        $payload['cached'] = false;

        Cache::put(
            $key,
            $payload,
            ($payload['degraded'] ?? false) ? 15 : max(5, (int) config('resayil.admin_cache_ttl', 60))
        );

        return $payload;
    }

    /**
     * Drop this company's cached snapshot — called after any state-changing
     * action (pause/resume) so the panel reflects it immediately instead of
     * showing a stale pill for up to a minute.
     */
    public function forget(int $companyId): void
    {
        Cache::forget($this->cacheKey($companyId));
    }

    /**
     * Payment history for Panel 4 (§5.2).
     *
     * Reseller read — needs no company key. `start`/`end` are payment-id
     * CURSORS, not dates, so this is a "Load more" list, never numbered
     * pages (§5.2 / V-3c: `?page=` is ignored by this API).
     *
     * The payment object's field names were NOT captured by the 2026-08-26
     * probe (plan §13), and re-probing on 2026-08-26 found every customer
     * on the platform returns an EMPTY list — the owner pays the reseller
     * bill from a prepaid credit pool, so customer-level payments simply do
     * not exist in this reselling model. normalisePayment() is therefore
     * written defensively against several plausible key spellings and
     * degrades to "—" rather than guessing.
     *
     * @return array<string,mixed>
     */
    public function payments(int $companyId, ?string $cursor = null, int $size = 25): array
    {
        $row = ResayilAccount::adminFor($companyId);

        if (! $this->platformConfigured() || ! $row?->resayil_customer_id) {
            return ['ok' => true, 'rows' => [], 'next' => null, 'degraded' => false];
        }

        $query = ['size' => $size];
        if ($cursor !== null && $cursor !== '') {
            $query['start'] = $cursor;
        }

        try {
            $response = $this->reseller->get('/customers/'.$row->resayil_customer_id.'/payments', $query);

            if ($response->failed()) {
                Log::warning('resayil.admin.payments_failed', [
                    'company_id' => $companyId,
                    'status' => $response->status(),
                    'body' => $this->redact($response->json()),
                ]);

                return ['ok' => false, 'rows' => [], 'next' => null, 'degraded' => true];
            }

            $body = $response->json();
            $rows = is_array($body) ? ($body['data'] ?? $body) : [];
            $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];

            $normalised = array_map(fn (array $p) => $this->normalisePayment($p), $rows);

            return [
                'ok' => true,
                'rows' => $normalised,
                // Cursor for "Load more": the id of the last row we got.
                'next' => count($normalised) >= $size ? (end($normalised)['id'] ?? null) : null,
                'degraded' => false,
            ];
        } catch (\Throwable $e) {
            // Includes ConnectionException, which retry(throw: false) does
            // NOT suppress. Message only — never the exception's request
            // context, which carries the Token header.
            Log::warning('resayil.admin.payments_exception', [
                'company_id' => $companyId,
                'exception' => $e->getMessage(),
            ]);

            return ['ok' => false, 'rows' => [], 'next' => null, 'degraded' => true];
        }
    }

    /**
     * Pause a company's WhatsApp subscription — the owner's collections
     * lever (§5.5, owner decision D-2). The DEVICE pauses, not the API key:
     * the number stops working and all data is preserved.
     *
     * OPERATOR ONLY. This method does not check the caller's role — the
     * controller does, before calling — but nothing else in the app may
     * call it, and it must never be reachable by a COMPANY user: a client
     * pausing themselves would silently break receipts and reminders in
     * other modules (U-6, reversed to a hard no).
     *
     * @return array<string,mixed>
     */
    public function pauseDevice(int $companyId, int $actorUserId): array
    {
        return $this->deviceSubscriptionAction($companyId, $actorUserId, 'disable');
    }

    /**
     * Resume a paused subscription (§5.5). Resayil warns that a payment may
     * be triggered when the credit balance is insufficient, and the
     * customer re-pairs their phone after an enable.
     *
     * @return array<string,mixed>
     */
    public function resumeDevice(int $companyId, int $actorUserId): array
    {
        return $this->deviceSubscriptionAction($companyId, $actorUserId, 'enable');
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    protected function cacheKey(int $companyId): string
    {
        // Company-scoped, always. The cache driver on this deployment is
        // `database`, which has no tag support — so keys are flat and are
        // invalidated explicitly by forget().
        return "resayil:admin:overview:{$companyId}";
    }

    /**
     * @return array<string,mixed>
     */
    protected function buildOverview(int $companyId): array
    {
        $base = [
            'state' => self::STATE_NOT_CONFIGURED,
            'degraded' => false,
            'stale_since' => null,
            'fetched_at' => now()->toIso8601String(),
            'workspace' => null,
            'device' => null,
            'subscription' => null,
            'health' => null,
            'seats' => $this->seats($companyId),
            'checklist' => [],
            'banners' => [],
            'operator_note' => null,
        ];

        if (! $this->platformConfigured()) {
            // §8 N-0 — the reseller token is unset. An operator problem;
            // the client copy says so without blaming them.
            $base['operator_note'] = 'RESAYIL_RESELLER_TOKEN is not set in this environment.';
            $base['checklist'] = $this->checklist(null, null);

            return $base;
        }

        $row = ResayilAccount::adminFor($companyId);

        if (! $row) {
            // No admin row at all. Slice 1 deliberately did NOT trigger
            // provisioning here, because findCustomerByEmail() was relying
            // on `GET /customers?email=`, which the live API IGNORES — a
            // page load would have blind-POSTed real customers onto the
            // live platform on a broken idempotency check. Wave 2 fixed
            // that lookup (the working parameter is `search`), so the
            // plan's §3.1 safety net can finally be wired in.
            //
            // It is a DISPATCH, not an inline call: a Settings page render
            // must not wait on three round trips to an external API, and a
            // Resayil outage must not turn this page into a slow one. The
            // page keeps saying "not set up yet" until the worker lands the
            // row — honest, and with no auto-reload loop for a company
            // whose provisioning keeps failing.
            $base['state'] = self::STATE_NOT_PROVISIONED;
            $base['operator_note'] = "No resayil_accounts admin row for company #{$companyId}."
                .($this->dispatchProvisioning($companyId) ? ' ProvisionResayilWorkspace queued.' : '');
            $base['checklist'] = $this->checklist(null, null);

            return $base;
        }

        if ($row->status === ResayilAccount::STATUS_ERROR) {
            $base['state'] = self::STATE_ERROR;
            $base['operator_note'] = is_array($row->meta)
                ? json_encode($this->redact($row->meta))
                : null;
            $base['checklist'] = $this->checklist($row, null);

            return $base;
        }

        if ($row->status === ResayilAccount::STATUS_ADOPTION_PENDING) {
            // Security fix (blocker 2b): resayil_customer_id is
            // deliberately unset on this row, so nothing below can
            // accidentally render a stranger's workspace. Operator note
            // carries the candidate id/email — never a secret, this row
            // never had a key captured at all.
            $base['state'] = self::STATE_ADOPTION_PENDING;
            $base['operator_note'] = is_array($row->meta)
                ? json_encode($this->redact($row->meta['adoption_candidate'] ?? []))
                : null;
            $base['checklist'] = $this->checklist($row, null);

            return $base;
        }

        if (! $row->resayil_customer_id) {
            // Row exists but the workspace id hasn't landed yet.
            $base['state'] = self::STATE_PROVISIONING;
            $base['checklist'] = $this->checklist($row, null);

            return $base;
        }

        $base['state'] = self::STATE_READY;

        try {
            $customer = $this->fetchCustomer($row->resayil_customer_id);
            $device = $this->fetchPrimaryDevice($row->resayil_customer_id);

            $base['workspace'] = $this->projectWorkspace($customer, $row);
            $base['device'] = $device ? $this->projectDevice($device) : null;
            $base['subscription'] = $device ? $this->projectSubscription($device) : null;
            $base['health'] = $device ? $this->projectHealth($device) : null;
            $base['checklist'] = $this->checklist($row, $base['device']);
            $base['banners'] = array_merge(
                $this->banners($base['device'], $base['subscription']),
                $this->keyBanners($row),
            );

            $this->persistSnapshot($row, $base);

            return $base;
        } catch (\Throwable $e) {
            Log::warning('resayil.admin.overview_exception', [
                'company_id' => $companyId,
                'exception' => $e->getMessage(),
            ]);

            return $this->degraded($base, $row, $e->getMessage());
        }
    }

    /**
     * §8 state D-1 — the reseller API is unreachable or erroring. Render
     * the last-good snapshot with an amber "showing last known status from
     * {time}" line rather than an error page. With no snapshot to fall back
     * on, the panel says plainly that the status could not be loaded.
     *
     * @param  array<string,mixed>  $base
     * @return array<string,mixed>
     */
    protected function degraded(array $base, ResayilAccount $row, ?string $reason = null): array
    {
        $base['degraded'] = true;
        $base['operator_note'] = $reason;

        $cache = $row->subscription_cache;

        if (is_array($cache) && $cache !== []) {
            $base['workspace'] = $cache['workspace'] ?? null;
            $base['device'] = $cache['device'] ?? null;
            $base['subscription'] = $cache['subscription'] ?? null;
            $base['health'] = $cache['health'] ?? null;
            $base['stale_since'] = optional($row->health_checked_at)->toIso8601String();
        }

        $base['checklist'] = $this->checklist($row, $base['device']);
        $base['banners'] = $this->banners($base['device'], $base['subscription']);

        return $base;
    }

    /**
     * Persist the last-good snapshot so the D-1 degraded state has
     * something honest to show. Deliberately best-effort: a DB hiccup here
     * must not break a page that otherwise rendered fine.
     *
     * @param  array<string,mixed>  $payload
     */
    protected function persistSnapshot(ResayilAccount $row, array $payload): void
    {
        try {
            $row->forceFill([
                'subscription_cache' => [
                    'workspace' => $payload['workspace'],
                    'device' => $payload['device'],
                    'subscription' => $payload['subscription'],
                    'health' => $payload['health'],
                ],
                'device_health' => $payload['device']['session_status'] ?? null,
                'health_checked_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('resayil.admin.snapshot_persist_failed', [
                'company_id' => $row->company_id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function fetchCustomer(string $customerId): ?array
    {
        // DETAIL endpoint, by id (§2.2 — the list endpoint returns a slim
        // projection). We do NOT read apiKeys[] here: slice 1 needs no
        // account key, and reading a credential we are not about to store
        // would only widen the blast radius of a logged response body.
        $response = $this->reseller->get('/customers/'.$customerId);

        if ($response->failed()) {
            Log::warning('resayil.admin.customer_failed', [
                'status' => $response->status(),
                'body' => $this->redact($response->json()),
            ]);

            throw new \RuntimeException('Customer read failed with HTTP '.$response->status());
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    /**
     * The company's primary device, or null when no number exists yet
     * (§8 N-5).
     *
     * `?user=` DOES filter server-side on this endpoint (verified live —
     * unlike `?email=` on /customers, which is ignored). We still re-check
     * `device.user.id` locally: tenant isolation must not depend on a
     * remote filter staying correct.
     *
     * @return array<string,mixed>|null
     */
    protected function fetchPrimaryDevice(string $customerId): ?array
    {
        $response = $this->reseller->get('/devices', ['user' => $customerId]);

        if ($response->failed()) {
            Log::warning('resayil.admin.devices_failed', [
                'status' => $response->status(),
                'body' => $this->redact($response->json()),
            ]);

            throw new \RuntimeException('Device read failed with HTTP '.$response->status());
        }

        $body = $response->json();
        $rows = is_array($body) ? ($body['data'] ?? $body) : [];

        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $device) {
            if (! is_array($device)) {
                continue;
            }

            // Defence in depth (§9.3): never trust the remote filter.
            if (($device['user']['id'] ?? null) !== $customerId) {
                continue;
            }

            return $device;
        }

        return null;
    }

    /**
     * @param  array<string,mixed>|null  $customer
     * @return array<string,mixed>
     */
    protected function projectWorkspace(?array $customer, ResayilAccount $row): array
    {
        return [
            'name' => $customer['displayName'] ?? ($customer['billingProfile']['companyName'] ?? null),
            'email' => $customer['email'] ?? $row->resayil_email,
            'account_type' => $customer['accountType'] ?? null,
            'company_name' => $customer['billingProfile']['companyName'] ?? null,
            'country' => $customer['billingProfile']['country'] ?? null,
            'created_at' => $customer['createdAt'] ?? null,
            // Operator-only in the view; kept here so the ADMIN strip can
            // show the id it would need to open a support ticket.
            'customer_id' => $row->resayil_customer_id,
            'verified' => isset($customer['status']) ? ((int) $customer['status'] === 100) : null,
            'admin_contact_phone' => is_array($row->meta) ? ($row->meta['admin_contact_phone'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string,mixed>  $device
     * @return array<string,mixed>
     */
    protected function projectDevice(array $device): array
    {
        $sessionStatus = $device['session']['status'] ?? null;

        return [
            'id' => $device['id'] ?? null,
            'phone' => $device['phone'] ?? null,
            'alias' => $device['alias'] ?? null,
            'status' => $device['status'] ?? null,
            'connector' => $device['connector'] ?? null,
            'session_status' => $sessionStatus,
            'session_label' => $this->sessionLabel($sessionStatus),
            'session_tone' => $this->sessionTone($sessionStatus),
            'linked_devices' => $device['session']['linkedDevices'] ?? null,
            'last_sync_at' => $device['session']['lastSyncAt'] ?? null,
            'paused' => $this->isPaused($device),
        ];
    }

    /**
     * Subscription projection — an explicit ALLOW-LIST (rule 2 in the class
     * docblock). `price` exists on the live object and is NEVER copied:
     * owner decision D-1 forbids rendering any Resayil price to a client,
     * and the owner's real charge scales with `agents` anyway, so the
     * figure would be misleading even to an operator reading this panel.
     *
     * Read from `device.billing.subscription` — NOT `device.subscription`,
     * which is empty in the live API (§5.1).
     *
     * @param  array<string,mixed>  $device
     * @return array<string,mixed>|null
     */
    protected function projectSubscription(array $device): ?array
    {
        $subscription = $device['billing']['subscription'] ?? null;

        if (! is_array($subscription) || $subscription === []) {
            return null;
        }

        $usage = is_array($subscription['usage'] ?? null) ? $subscription['usage'] : [];

        return [
            'status' => $subscription['status'] ?? null,
            'billing_status' => $subscription['billingStatus'] ?? null,
            'plan_code' => $subscription['planCode'] ?? ($subscription['plan'] ?? null),
            'plan_label' => $this->planLabel($subscription['planCode'] ?? ($subscription['plan'] ?? null)),
            'product' => $subscription['product'] ?? null,
            'agents' => $subscription['agents'] ?? null,
            'interval' => $subscription['interval'] ?? null,
            'started_at' => $subscription['startedAt'] ?? null,
            'starts_at' => $subscription['startsAt'] ?? null,
            'ends_at' => $subscription['endsAt'] ?? null,
            'changed_at' => $subscription['changedAt'] ?? null,
            'is_trial' => (bool) ($subscription['isTrial'] ?? false),
            'trial_ends_at' => $this->trialEnd(
                $subscription['trialEndsAt'] ?? null,
                (bool) ($subscription['isTrial'] ?? false)
            ),
            'usage' => [
                'text_messages' => (int) ($usage['textMessages'] ?? 0),
                'media_messages' => (int) ($usage['mediaMessages'] ?? 0),
                'failed_messages' => (int) ($usage['failedMessages'] ?? 0),
                'number_checks' => (int) ($usage['numberChecks'] ?? 0),
                'catalog_queries' => (int) ($usage['catalogQueries'] ?? 0),
                'campaigns' => (int) ($usage['campaigns'] ?? 0),
            ],
            // price / fee / finalPrice: intentionally absent. See D-1.
        ];
    }

    /**
     * @param  array<string,mixed>  $device
     * @return array<string,mixed>|null
     */
    protected function projectHealth(array $device): ?array
    {
        $health = $device['health'] ?? null;

        if (! is_array($health) || $health === []) {
            return null;
        }

        $metrics = [];

        foreach ((array) ($health['metrics'] ?? []) as $metric) {
            if (! is_array($metric) || ($metric['available'] ?? true) === false) {
                continue;
            }

            $metrics[] = [
                'label' => $metric['label'] ?? ($metric['metric'] ?? '—'),
                'value' => $metric['value'] ?? null,
                'score' => isset($metric['score']) ? (int) round((float) $metric['score']) : null,
                'band' => $metric['band'] ?? null,
            ];
        }

        // Worst-scoring metrics first: the only ones a reader acts on.
        usort($metrics, fn ($a, $b) => ($a['score'] ?? 100) <=> ($b['score'] ?? 100));

        $reasons = [];

        foreach ((array) ($health['reasons'] ?? []) as $reason) {
            if (! is_array($reason)) {
                continue;
            }

            $reasons[] = [
                'code' => $reason['code'] ?? null,
                'metric' => $reason['metric'] ?? null,
                'band' => $reason['band'] ?? null,
                'value' => $reason['value'] ?? null,
            ];
        }

        return [
            'score' => isset($health['score']) ? (int) round((float) $health['score']) : null,
            'tier' => $health['tier'] ?? null,
            'evaluated_at' => $health['evaluatedAt'] ?? null,
            'reasons' => $reasons,
            'metrics' => array_slice($metrics, 0, 6),
        ];
    }

    /**
     * Seat meter (§5.1) — purely local: provisioned rows vs the configured
     * included-seat cap. No API call.
     *
     * @return array<string,int|bool>
     */
    protected function seats(int $companyId): array
    {
        $cap = (int) config('resayil.max_auto_users', 9);

        $used = ResayilAccount::query()
            ->forCompany($companyId)
            ->provisioned()
            ->count();

        return [
            'used' => $used,
            'cap' => $cap,
            'reached' => $cap > 0 && $used >= $cap,
        ];
    }

    /**
     * The setup checklist (§5.1). Each row states plainly what is missing.
     * The "not yet" copy is written for a client, and never links to a
     * panel that does not exist yet in this slice.
     *
     * @param  array<string,mixed>|null  $device
     * @return list<array<string,mixed>>
     */
    protected function checklist(?ResayilAccount $row, ?array $device): array
    {
        $hasWorkspace = (bool) $row?->resayil_customer_id;
        $hasKey = (bool) $row?->resayil_account_token;
        $hasDevice = (bool) ($device['id'] ?? null);
        $online = ($device['session_status'] ?? null) === 'online';

        return [
            [
                'key' => 'workspace',
                'label' => 'WhatsApp workspace created',
                'done' => $hasWorkspace,
                'hint' => $hasWorkspace
                    ? null
                    : 'Created automatically for your company — nothing for you to do.',
            ],
            [
                'key' => 'key',
                'label' => 'Workspace access linked',
                'done' => $hasKey,
                'hint' => $hasKey
                    ? null
                    : ($this->keyCaptureFailed($row)
                        // THE FETCH RULE (plan §0, design law #4): say so in
                        // plain words, at the exact point the client needs
                        // it, and never pretend a step is merely pending
                        // when it has actually failed.
                        ? "Automatic linking didn't complete. Our team finishes this for you — nothing is needed from you."
                        : 'Links automatically once your workspace finishes setting up.'),
            ],
            [
                'key' => 'number',
                'label' => 'WhatsApp number connected',
                'done' => $hasDevice,
                'hint' => $hasDevice
                    ? null
                    : 'Your number is activated with our support team.',
            ],
            [
                'key' => 'online',
                'label' => 'Number online and sending',
                'done' => $online,
                'hint' => $online
                    ? null
                    : ($hasDevice
                        ? 'Your number is set up but not currently connected.'
                        : 'Available once a number is connected.'),
            ],
        ];
    }

    /**
     * Has this company's silent account-key capture been tried and failed?
     * Wave 2 writes meta.key_capture_failed on every unsuccessful attempt
     * and clears it the moment a key is stored, so this is "failed AND
     * still not linked", not "failed once, ever".
     */
    protected function keyCaptureFailed(?ResayilAccount $row): bool
    {
        if (! $row || $row->resayil_account_token) {
            return false;
        }

        return is_array($row->meta) && isset($row->meta['key_capture_failed']);
    }

    /**
     * §8 state N-3 — the workspace exists but no account key is linked.
     *
     * Client-facing copy only: which endpoint failed and why is an operator
     * concern and stays in meta / the log. The client is told the truth
     * (some features are not available yet) and told they do not have to do
     * anything about it, which is accurate — the recovery path is ours.
     *
     * @return list<array<string,string>>
     */
    protected function keyBanners(?ResayilAccount $row): array
    {
        if (! $this->keyCaptureFailed($row)) {
            return [];
        }

        return [[
            'id' => 'N-3',
            'tone' => 'info',
            'title' => "We're still linking your workspace access.",
            'body' => 'Your WhatsApp status, plan and payment history below are live and correct. A few extras — invoices and team access — switch on once linking finishes. Our team completes this; nothing is needed from you.',
        ]];
    }

    /**
     * Queue the silent provisioning job for a company that has no Resayil
     * workspace yet, at most once every few minutes.
     *
     * The lock is the point: without it, one client sitting on this page
     * with its 60 s poller would queue a job a minute, forever. Cache::add()
     * is atomic on every driver this app uses, so two simultaneous page
     * loads still produce one dispatch. Returns whether it actually queued.
     */
    protected function dispatchProvisioning(int $companyId): bool
    {
        try {
            if (! Cache::add("resayil:admin:provision-dispatch:{$companyId}", 1, 300)) {
                return false;
            }

            $company = Company::find($companyId);

            if (! $company || ! $company->user_id || ! User::whereKey($company->user_id)->exists()) {
                return false;
            }

            ProvisionResayilWorkspace::dispatch($companyId, $company->user_id);

            return true;
        } catch (\Throwable $e) {
            // A page render must never fail because a queue write did.
            Log::warning('resayil.admin.provision_dispatch_failed', [
                'company_id' => $companyId,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * In-panel banners for the §8 N-8..N-12 conditions. Every one carries
     * plain-language copy and a next action; none of them is a raw status
     * string.
     *
     * @param  array<string,mixed>|null  $device
     * @param  array<string,mixed>|null  $subscription
     * @return list<array<string,string>>
     */
    protected function banners(?array $device, ?array $subscription): array
    {
        $banners = [];

        $billingStatus = strtolower((string) ($subscription['billing_status'] ?? $subscription['status'] ?? ''));

        if (in_array($billingStatus, ['past_due', 'unpaid'], true)) {
            $banners[] = [
                'id' => 'N-11',
                'tone' => 'danger',
                'title' => "There's a payment issue on your WhatsApp subscription.",
                'body' => 'Your number keeps working for now. Please contact your account manager to settle it before service is interrupted.',
            ];
        }

        if ($device !== null && ($device['paused'] ?? false)) {
            $banners[] = [
                'id' => 'N-12',
                'tone' => 'danger',
                'title' => 'Your WhatsApp service is paused.',
                'body' => 'Messages are not being sent or received. Your conversations and settings are safe. Contact your account manager to resume.',
            ];
        }

        $session = $device['session_status'] ?? null;

        if ($device !== null && $session !== null && $session !== 'online') {
            $banners[] = match ($session) {
                'conflict' => [
                    'id' => 'N-9',
                    'tone' => 'warning',
                    'title' => 'WhatsApp Web is open somewhere else.',
                    'body' => 'Close WhatsApp Web in other browsers, or re-link this number, to bring it back online.',
                ],
                'error' => [
                    'id' => 'N-10',
                    'tone' => 'danger',
                    'title' => 'This connection needs attention.',
                    'body' => 'We could not restore the WhatsApp session automatically. Please contact support.',
                ],
                'timeout' => [
                    'id' => 'N-8',
                    'tone' => 'warning',
                    'title' => 'The phone lost its internet connection.',
                    'body' => 'Reconnect the phone to Wi-Fi or mobile data. Messages queue meanwhile and send once it is back.',
                ],
                'offline' => [
                    'id' => 'N-8',
                    'tone' => 'warning',
                    'title' => 'The phone is offline, or WhatsApp is closed on it.',
                    'body' => 'Open WhatsApp on the linked phone and keep it connected. Messages queue meanwhile and send once it is back.',
                ],
                default => [
                    'id' => 'N-8',
                    'tone' => 'warning',
                    'title' => 'Your number is finishing its connection.',
                    'body' => 'This usually clears on its own within a couple of minutes.',
                ],
            };
        }

        return $banners;
    }

    /**
     * Shared implementation of pause (`disable`) and resume (`enable`).
     *
     * @return array<string,mixed>
     */
    protected function deviceSubscriptionAction(int $companyId, int $actorUserId, string $action): array
    {
        $row = ResayilAccount::adminFor($companyId);

        if (! $this->platformConfigured()) {
            return ['ok' => false, 'message' => 'The platform connection is not configured.'];
        }

        if (! $row?->resayil_customer_id) {
            return ['ok' => false, 'message' => 'This company has no Resayil workspace yet.'];
        }

        // The device id is resolved from the company's OWN workspace, live,
        // and never taken from the request (§9.3). Re-reading also means we
        // cannot act on a device that was deleted since the page rendered.
        try {
            $device = $this->fetchPrimaryDevice($row->resayil_customer_id);
        } catch (\Throwable $e) {
            Log::warning('resayil.admin.device_lookup_exception', [
                'company_id' => $companyId,
                'action' => $action,
                'exception' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => "Resayil didn't respond — nothing was changed. Try again."];
        }

        $deviceId = $device['id'] ?? null;

        if (! $deviceId) {
            return ['ok' => false, 'message' => 'This company has no WhatsApp number to '.($action === 'disable' ? 'pause' : 'resume').'.'];
        }

        try {
            $response = $this->reseller->post('/devices/'.$deviceId.'/'.$action, []);

            if ($response->failed()) {
                Log::warning('resayil.admin.device_action_failed', [
                    'company_id' => $companyId,
                    'actor_user_id' => $actorUserId,
                    'action' => $action,
                    'status' => $response->status(),
                    'body' => $this->redact($response->json()),
                ]);

                return ['ok' => false, 'message' => "Resayil rejected that (HTTP {$response->status()}). Nothing was changed."];
            }
        } catch (\Throwable $e) {
            Log::warning('resayil.admin.device_action_exception', [
                'company_id' => $companyId,
                'actor_user_id' => $actorUserId,
                'action' => $action,
                'exception' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => "Resayil didn't respond — nothing was changed. Try again."];
        }

        // Audit trail on the admin row. Operator-initiated service
        // interruption must be attributable after the fact.
        try {
            $meta = is_array($row->meta) ? $row->meta : [];
            $meta['subscription_actions'] = array_slice(
                array_merge($meta['subscription_actions'] ?? [], [[
                    'action' => $action,
                    'by' => $actorUserId,
                    'at' => now()->toIso8601String(),
                ]]),
                -20
            );
            $row->forceFill(['meta' => $meta])->save();
        } catch (\Throwable $e) {
            Log::warning('resayil.admin.device_action_audit_failed', [
                'company_id' => $companyId,
                'exception' => $e->getMessage(),
            ]);
        }

        Log::info('resayil.admin.device_action', [
            'company_id' => $companyId,
            'actor_user_id' => $actorUserId,
            'action' => $action,
        ]);

        $this->forget($companyId);

        return [
            'ok' => true,
            'message' => $action === 'disable'
                ? 'WhatsApp service paused. The number is offline; all data is preserved.'
                : 'WhatsApp service resumed. The number may need to be re-linked on the phone.',
        ];
    }

    /**
     * Normalise one payment row without betting on a field spelling. The
     * shape is genuinely unverified (plan §13) — every customer on the
     * platform currently returns an empty payments list, so no live example
     * exists to copy. Each field falls through several plausible names and
     * ends at null, which the view renders as "—".
     *
     * @param  array<string,mixed>  $payment
     * @return array<string,mixed>
     */
    protected function normalisePayment(array $payment): array
    {
        $amount = $payment['amount'] ?? $payment['total'] ?? $payment['value'] ?? null;

        return [
            'id' => $payment['id'] ?? $payment['_id'] ?? null,
            'date' => $payment['createdAt'] ?? $payment['date'] ?? $payment['paidAt'] ?? null,
            // Rendered as text, never used in arithmetic — we do not know
            // the unit (minor units vs decimal) and will not guess one.
            'amount' => is_scalar($amount) ? (string) $amount : null,
            'currency' => $payment['currency'] ?? null,
            'status' => $payment['status'] ?? $payment['state'] ?? null,
            'description' => $payment['description'] ?? $payment['reason'] ?? null,
        ];
    }

    protected function sessionLabel(?string $status): string
    {
        return match ($status) {
            'online' => 'Connected',
            'offline' => 'Phone offline',
            'timeout' => 'Phone lost connection',
            'conflict' => 'Open elsewhere',
            'error' => 'Needs attention',
            'authorize', 'connecting', 'loading', 'pairing' => 'Connecting',
            null, '' => 'Unknown',
            default => ucfirst($status),
        };
    }

    protected function sessionTone(?string $status): string
    {
        return match ($status) {
            'online' => 'ok',
            'error' => 'danger',
            'offline', 'timeout', 'conflict' => 'warning',
            null, '' => 'neutral',
            default => 'info',
        };
    }

    /**
     * A device whose subscription has been paused by the operator (§5.5).
     * The live API's exact paused-status spelling is not documented, so
     * this treats any non-operative device status in the known set as
     * paused rather than asserting one value.
     *
     * @param  array<string,mixed>  $device
     */
    protected function isPaused(array $device): bool
    {
        $status = strtolower((string) ($device['status'] ?? ''));

        if (in_array($status, ['disabled', 'paused', 'suspended', 'inactive'], true)) {
            return true;
        }

        $subscriptionStatus = strtolower((string) ($device['billing']['subscription']['status'] ?? ''));

        return in_array($subscriptionStatus, ['disabled', 'paused', 'suspended'], true);
    }

    /**
     * Client-facing plan wording. Deliberately generic: the offered product
     * is a single line (owner decision D-3), there is no picker, and no
     * price is ever shown — so the plan code itself carries no meaning for
     * a client and is shown only in the operator strip.
     */
    protected function planLabel(?string $planCode): string
    {
        if ($planCode === null || $planCode === '') {
            return 'WhatsApp Business';
        }

        $offered = (array) config('resayil.offered_plans', []);

        if (in_array($planCode, $offered, true)) {
            return 'WhatsApp Business — Enterprise';
        }

        return 'WhatsApp Business';
    }

    /**
     * A trial end date worth showing a client, or null.
     *
     * Two live quirks make the raw field unusable as-is: Resayil returns
     * the Unix epoch ("1970-01-01") when a subscription never had a trial,
     * and it keeps the ORIGINAL trial end forever on long-running
     * subscriptions (City Travelers still carries 2025-06-05 on an account
     * that has been paid monthly since). Rendering either would be noise
     * at best and misleading at worst, so a date only survives when the
     * subscription is actually in trial, or the date is still in the
     * future.
     */
    protected function trialEnd(?string $value, bool $isTrial): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        if ($date->year <= 2000) {
            return null;
        }

        return ($isTrial || $date->isFuture()) ? $value : null;
    }

    /**
     * Strip credential-shaped fields from anything about to be logged or
     * persisted (§9.1 — extends the provisioning service's key list with
     * token/apikey/key/authcode). We do not control what Resayil echoes in
     * an error body, and a key must never reach a log line.
     *
     * @param  mixed  $body
     * @return mixed
     */
    protected function redact(mixed $body): mixed
    {
        if (! is_array($body)) {
            return $body;
        }

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
