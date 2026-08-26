<x-app-layout>
    {{--
        Settings -> WhatsApp — the Resayil Admin Center (slice 1).
        Plan: .planning/specs/RESAYIL-ADMIN-CENTER.md §5.1 (Overview),
        §5.2 (Billing), §5.5 (operator pause/resume), §8 (states).

        STYLING NOTE — read before touching this file.
        `npm run build` is banned on this project and public/build is months
        stale, so ANY class added to resources/css/** would never reach the
        browser, and Tailwind arbitrary values (w-[180px]) do not exist in
        the compiled bundle either. This page therefore ships its own
        stylesheet inline via the layout's @stack('styles') — a plain
        <style> block needs no build step, is scoped by the rsa- prefix, and
        cannot collide with the app's utilities. Add new styles HERE, not to
        a css file.

        Dark mode follows the app convention: <html class="dark">, so every
        dark rule is written as `.dark .rsa-*`.
    --}}
    @push('styles')
    <style>
        [x-cloak] { display: none !important; }

        .rsa-wrap { max-width: 1180px; margin: 0 auto; padding: 1.25rem 1rem 3rem; }
        .rsa-crumb { display: flex; align-items: center; gap: .5rem; font-size: .8125rem; color: #6b7280; margin-bottom: 1rem; }
        .rsa-crumb a { color: #2563eb; text-decoration: none; }
        .rsa-crumb a:hover { text-decoration: underline; }
        .dark .rsa-crumb { color: #9ca3af; }
        .dark .rsa-crumb a { color: #93c5fd; }

        .rsa-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
        .rsa-title { display: flex; align-items: center; gap: .75rem; }
        .rsa-title img { width: 2rem; height: auto; }
        .rsa-title h1 { font-size: 1.5rem; font-weight: 700; color: #111827; line-height: 1.2; }
        .rsa-title p { margin-top: .25rem; font-size: .875rem; color: #6b7280; }
        .dark .rsa-title h1 { color: #f9fafb; }
        .dark .rsa-title p { color: #9ca3af; }

        .rsa-headside { display: flex; align-items: center; gap: .625rem; }
        .rsa-stamp { font-size: .75rem; color: #9ca3af; }

        .rsa-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem .8rem; border-radius: .5rem; border: 1px solid #d1d5db; background: #fff; color: #374151; font-size: .8125rem; font-weight: 500; cursor: pointer; transition: background .15s, border-color .15s; }
        .rsa-btn:hover { background: #f9fafb; }
        .rsa-btn:disabled { opacity: .55; cursor: not-allowed; }
        .dark .rsa-btn { background: #374151; border-color: #4b5563; color: #e5e7eb; }
        .dark .rsa-btn:hover { background: #4b5563; }
        .rsa-btn-danger { border-color: #fca5a5; color: #b91c1c; background: #fff; }
        .rsa-btn-danger:hover { background: #fef2f2; }
        .dark .rsa-btn-danger { background: #7f1d1d33; border-color: #b91c1c; color: #fca5a5; }
        .rsa-btn-primary { border-color: #2563eb; background: #2563eb; color: #fff; }
        .rsa-btn-primary:hover { background: #1d4ed8; }
        .rsa-btn-sm { padding: .3rem .6rem; font-size: .75rem; }

        .rsa-tabs { display: flex; gap: .25rem; border-bottom: 1px solid #e5e7eb; margin-bottom: 1.25rem; }
        .dark .rsa-tabs { border-color: #374151; }
        .rsa-tab { padding: .6rem .95rem; font-size: .875rem; font-weight: 500; color: #6b7280; background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; }
        .rsa-tab:hover { color: #374151; }
        .rsa-tab-active { color: #2563eb; border-bottom-color: #2563eb; }
        .dark .rsa-tab { color: #9ca3af; }
        .dark .rsa-tab:hover { color: #e5e7eb; }
        .dark .rsa-tab-active { color: #93c5fd; border-bottom-color: #60a5fa; }

        .rsa-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
        .rsa-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; padding: 1.1rem 1.15rem; }
        .dark .rsa-card { background: #1f2937; border-color: #374151; }
        .rsa-card h3 { font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #6b7280; margin-bottom: .75rem; }
        .dark .rsa-card h3 { color: #9ca3af; }
        .rsa-card-lead { font-size: 1.0625rem; font-weight: 600; color: #111827; word-break: break-word; }
        .dark .rsa-card-lead { color: #f9fafb; }

        .rsa-kv { display: flex; justify-content: space-between; gap: 1rem; padding: .3rem 0; font-size: .8125rem; }
        .rsa-kv dt { color: #6b7280; flex: 0 0 auto; }
        .rsa-kv dd { color: #111827; text-align: right; word-break: break-word; }
        .dark .rsa-kv dt { color: #9ca3af; }
        .dark .rsa-kv dd { color: #e5e7eb; }

        .rsa-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .18rem .55rem; border-radius: 999px; font-size: .6875rem; font-weight: 600; letter-spacing: .01em; }
        .rsa-pill::before { content: ''; width: .45rem; height: .45rem; border-radius: 999px; background: currentColor; }
        .rsa-pill-ok { background: #dcfce7; color: #15803d; }
        .rsa-pill-warning { background: #fef3c7; color: #b45309; }
        .rsa-pill-danger { background: #fee2e2; color: #b91c1c; }
        .rsa-pill-info { background: #dbeafe; color: #1d4ed8; }
        .rsa-pill-neutral { background: #f3f4f6; color: #4b5563; }
        .dark .rsa-pill-ok { background: #14532d66; color: #86efac; }
        .dark .rsa-pill-warning { background: #78350f66; color: #fcd34d; }
        .dark .rsa-pill-danger { background: #7f1d1d66; color: #fca5a5; }
        .dark .rsa-pill-info { background: #1e3a8a66; color: #93c5fd; }
        .dark .rsa-pill-neutral { background: #37415188; color: #d1d5db; }

        .rsa-note { display: flex; gap: .7rem; align-items: flex-start; border-radius: .625rem; padding: .8rem .95rem; font-size: .8125rem; margin-bottom: 1rem; border: 1px solid; }
        .rsa-note strong { display: block; font-weight: 600; margin-bottom: .15rem; }
        .rsa-note-warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .rsa-note-danger { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .rsa-note-info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
        .dark .rsa-note-warning { background: #78350f33; border-color: #b4530999; color: #fcd34d; }
        .dark .rsa-note-danger { background: #7f1d1d33; border-color: #b91c1c99; color: #fca5a5; }
        .dark .rsa-note-info { background: #1e3a8a33; border-color: #2563eb99; color: #bfdbfe; }

        .rsa-score { display: flex; align-items: baseline; gap: .4rem; }
        .rsa-score b { font-size: 2rem; font-weight: 700; line-height: 1; color: #111827; }
        .dark .rsa-score b { color: #f9fafb; }
        .rsa-score span { font-size: .8125rem; color: #6b7280; }
        .dark .rsa-score span { color: #9ca3af; }

        .rsa-meter { height: .4rem; border-radius: 999px; background: #e5e7eb; overflow: hidden; margin: .5rem 0 .35rem; }
        .dark .rsa-meter { background: #374151; }
        .rsa-meter i { display: block; height: 100%; border-radius: 999px; background: #22c55e; }
        .rsa-meter-warn i { background: #f59e0b; }
        .rsa-meter-full i { background: #6366f1; }

        .rsa-usage { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: .75rem; }
        .rsa-usage div { border: 1px solid #e5e7eb; border-radius: .55rem; padding: .6rem .7rem; }
        .dark .rsa-usage div { border-color: #374151; }
        .rsa-usage b { display: block; font-size: 1.125rem; font-weight: 700; color: #111827; }
        .dark .rsa-usage b { color: #f9fafb; }
        .rsa-usage span { font-size: .6875rem; color: #6b7280; }
        .dark .rsa-usage span { color: #9ca3af; }

        .rsa-check { list-style: none; margin: 0; padding: 0; }
        .rsa-check li { display: flex; gap: .65rem; align-items: flex-start; padding: .5rem 0; border-bottom: 1px solid #f3f4f6; }
        .dark .rsa-check li { border-color: #37415188; }
        .rsa-check li:last-child { border-bottom: none; }
        .rsa-check-mark { flex: 0 0 auto; width: 1.15rem; height: 1.15rem; border-radius: 999px; display: grid; place-items: center; font-size: .7rem; font-weight: 700; background: #f3f4f6; color: #9ca3af; margin-top: .1rem; }
        .rsa-check-done .rsa-check-mark { background: #dcfce7; color: #15803d; }
        .dark .rsa-check-mark { background: #374151; color: #9ca3af; }
        .dark .rsa-check-done .rsa-check-mark { background: #14532d; color: #86efac; }
        .rsa-check-label { font-size: .8125rem; color: #111827; font-weight: 500; }
        .dark .rsa-check-label { color: #e5e7eb; }
        .rsa-check-hint { font-size: .75rem; color: #6b7280; margin-top: .1rem; }
        .dark .rsa-check-hint { color: #9ca3af; }

        .rsa-tablewrap { overflow-x: auto; }
        .rsa-table { width: 100%; border-collapse: collapse; font-size: .8125rem; min-width: 480px; }
        .rsa-table th { text-align: left; font-size: .6875rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; font-weight: 600; padding: .5rem .65rem; border-bottom: 1px solid #e5e7eb; }
        .rsa-table td { padding: .6rem .65rem; border-bottom: 1px solid #f3f4f6; color: #111827; }
        .dark .rsa-table th { color: #9ca3af; border-color: #374151; }
        .dark .rsa-table td { color: #e5e7eb; border-color: #37415188; }

        .rsa-empty { text-align: center; padding: 1.75rem 1rem; color: #6b7280; font-size: .8125rem; }
        .dark .rsa-empty { color: #9ca3af; }

        .rsa-state { max-width: 560px; margin: 2rem auto; text-align: center; background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; padding: 2.25rem 1.75rem; }
        .dark .rsa-state { background: #1f2937; border-color: #374151; }
        .rsa-state img { width: 4rem; height: auto; margin: 0 auto .9rem; opacity: .85; }
        .rsa-state h2 { font-size: 1.0625rem; font-weight: 600; color: #111827; margin-bottom: .4rem; }
        .dark .rsa-state h2 { color: #f9fafb; }
        .rsa-state p { font-size: .875rem; color: #6b7280; line-height: 1.55; }
        .dark .rsa-state p { color: #9ca3af; }

        .rsa-op { margin-top: 1.25rem; border: 1px dashed #c7d2fe; background: #eef2ff; border-radius: .75rem; padding: 1rem 1.1rem; }
        .dark .rsa-op { background: #312e8133; border-color: #4f46e5; }
        .rsa-op h3 { font-size: .6875rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; color: #4338ca; margin-bottom: .6rem; }
        .dark .rsa-op h3 { color: #a5b4fc; }
        .rsa-op code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; background: #ffffffcc; padding: .1rem .35rem; border-radius: .25rem; color: #3730a3; word-break: break-all; }
        .dark .rsa-op code { background: #1e1b4b99; color: #c7d2fe; }
        .rsa-op-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .85rem; }

        .rsa-modal-back { position: fixed; inset: 0; background: rgba(17,24,39,.55); display: grid; place-items: center; padding: 1rem; z-index: 60; }
        .rsa-modal { background: #fff; border-radius: .75rem; max-width: 460px; width: 100%; padding: 1.4rem 1.5rem; }
        .dark .rsa-modal { background: #1f2937; }
        .rsa-modal h3 { font-size: 1rem; font-weight: 600; color: #111827; margin-bottom: .5rem; }
        .dark .rsa-modal h3 { color: #f9fafb; }
        .rsa-modal p { font-size: .8125rem; color: #4b5563; line-height: 1.55; }
        .dark .rsa-modal p { color: #d1d5db; }
        .rsa-modal-confirm { display: flex; gap: .5rem; align-items: flex-start; margin-top: .9rem; font-size: .8125rem; color: #374151; }
        .dark .rsa-modal-confirm { color: #d1d5db; }
        .rsa-modal-actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1.1rem; }

        .rsa-sub { font-size: .75rem; color: #6b7280; margin-top: .35rem; line-height: 1.5; }
        .dark .rsa-sub { color: #9ca3af; }
        .rsa-section-title { font-size: .9375rem; font-weight: 600; color: #111827; margin: 1.5rem 0 .75rem; }
        .dark .rsa-section-title { color: #f9fafb; }
    </style>
    @endpush

    @php
        /** @var array<string,mixed>|null $overview */
        $state = $overview['state'] ?? null;
        $device = $overview['device'] ?? null;
        $subscription = $overview['subscription'] ?? null;
        $workspace = $overview['workspace'] ?? null;
        $health = $overview['health'] ?? null;
        $seats = $overview['seats'] ?? ['used' => 0, 'cap' => 0, 'reached' => false];
        $ready = $state === \App\Services\Resayil\ResayilAdminService::STATE_READY;

        $fmt = function ($value, $withTime = false) {
            if (empty($value)) {
                return '—';
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone'))
                    ->format($withTime ? 'j M Y, H:i' : 'j M Y');
            } catch (\Throwable $e) {
                return '—';
            }
        };
    @endphp

    <div class="rsa-wrap"
         x-data="resayilAdmin({
            panel: '{{ $activePanel }}',
            pollUrl: '{{ route('resayil-admin.overview') }}',
            paymentsUrl: '{{ route('resayil-admin.billing.payments') }}',
            pauseUrl: '{{ route('resayil-admin.device.pause') }}',
            resumeUrl: '{{ route('resayil-admin.device.resume') }}',
            ready: {{ $ready ? 'true' : 'false' }}
         })">

        <nav class="rsa-crumb">
            <a href="{{ route('dashboard') }}">{{ __('general.dashboard') }}</a>
            <span>&gt;</span>
            <a href="{{ route('settings.index') }}">{{ __('general.settings') }}</a>
            <span>&gt;</span>
            <span>WhatsApp</span>
        </nav>

        <div class="rsa-head">
            <div class="rsa-title">
                <img src="{{ asset('images/ResayilLogoIcon.png') }}" alt="" width="160" height="149">
                <div>
                    <h1>WhatsApp</h1>
                    <p>Your WhatsApp business workspace, number and subscription.</p>
                </div>
            </div>
            @if($ready)
                <div class="rsa-headside">
                    <span class="rsa-stamp" x-text="stamp"></span>
                    <button type="button" class="rsa-btn" x-on:click="refresh()" x-bind:disabled="busy">
                        <span x-show="!busy">Refresh</span>
                        <span x-show="busy" x-cloak>Refreshing…</span>
                    </button>
                </div>
            @endif
        </div>

        @if($companyId === null)
            {{-- A role whose company cannot be resolved (getCompanyId returns
                 null). Explained, not a 500 and not a blank page. --}}
            <div class="rsa-state">
                <img src="{{ asset('images/ResayilLogoIcon.png') }}" alt="">
                <h2>No company selected</h2>
                <p>Your account isn't linked to a company yet, so there's no WhatsApp workspace to show. Ask your administrator to finish setting up your access.</p>
            </div>

        @elseif($state === \App\Services\Resayil\ResayilAdminService::STATE_NOT_CONFIGURED)
            {{-- State N-0: the platform-wide reseller connection is unset.
                 An operator problem — the copy never blames the client, and
                 only an ADMIN sees the real reason. --}}
            <div class="rsa-state">
                <img src="{{ asset('images/ResayilLogoIcon.png') }}" alt="">
                <h2>WhatsApp administration isn't available yet</h2>
                <p>The platform connection is still being set up. Nothing is wrong with your account — please check back shortly.</p>
            </div>
            @if($isOperator)
                <div class="rsa-op">
                    <h3>Operator</h3>
                    <p style="font-size:.8125rem;color:#4338ca;">{{ $overview['operator_note'] ?? 'Reseller client reports not configured.' }}</p>
                </div>
            @endif

        @elseif($state === \App\Services\Resayil\ResayilAdminService::STATE_NOT_PROVISIONED)
            {{-- No admin row yet. Honest copy: slice 1 does NOT trigger
                 provisioning (see ResayilAdminService::buildOverview for
                 why), so this must not pretend to be a spinner that will
                 resolve on its own within this page's lifetime. --}}
            <div class="rsa-state">
                <img src="{{ asset('images/ResayilLogoIcon.png') }}" alt="">
                <h2>Your WhatsApp workspace isn't set up yet</h2>
                <p>WhatsApp service is arranged for your company by our team — there's nothing for you to configure here. Once your workspace and number are live, this page will show your connection status, plan and usage.</p>
                <p style="margin-top:.75rem;">Need it sooner? Contact your account manager.</p>
            </div>
            @if($isOperator)
                <div class="rsa-op">
                    <h3>Operator</h3>
                    <p style="font-size:.8125rem;color:#4338ca;">{{ $overview['operator_note'] }}</p>
                    <p style="font-size:.75rem;color:#4338ca;margin-top:.4rem;">
                        Automatic provisioning (ProvisionResayilWorkspace) ships in slice 2. Until then an admin row is created by an operator.
                    </p>
                </div>
            @endif

        @elseif($state === \App\Services\Resayil\ResayilAdminService::STATE_PROVISIONING)
            {{-- State N-1 — a row exists but the workspace id hasn't landed.
                 A real in-flight state, so a real auto-refresh. --}}
            <div class="rsa-state" x-init="setTimeout(() => window.location.reload(), 8000)">
                <img src="{{ asset('images/ResayilLogoIcon.png') }}" alt="">
                <h2>Setting up your WhatsApp workspace…</h2>
                <p>This usually takes less than a minute. This page refreshes itself — you don't need to do anything.</p>
            </div>

        @elseif($state === \App\Services\Resayil\ResayilAdminService::STATE_ERROR)
            {{-- State N-2. No Retry button: the retry path is the
                 provisioning service, which slice 1 deliberately does not
                 call. A dead button would be worse than an honest CTA. --}}
            <div class="rsa-state">
                <img src="{{ asset('images/ResayilLogoIcon.png') }}" alt="">
                <h2>We couldn't set up your WhatsApp workspace</h2>
                <p>Your account is fine — this is on our side. Please contact your account manager and we'll get it sorted.</p>
            </div>
            @if($isOperator)
                <div class="rsa-op">
                    <h3>Operator</h3>
                    <p style="font-size:.8125rem;"><code>{{ \Illuminate\Support\Str::limit((string) ($overview['operator_note'] ?? '—'), 600) }}</code></p>
                </div>
            @endif

        @else
            {{-- READY. Everything below renders from reseller reads only —
                 no company account key is involved anywhere in this slice. --}}

            @if($overview['degraded'] ?? false)
                {{-- State D-1: reseller API unreachable. Last-known-good
                     values plus an explicit staleness line, never an error
                     page and never silently-wrong "live" data. --}}
                <div class="rsa-note rsa-note-warning">
                    <div>
                        <strong>Showing last known status{{ ($overview['stale_since'] ?? null) ? ' from '.$fmt($overview['stale_since'], true) : '' }}</strong>
                        We couldn't reach the WhatsApp service just now, so these figures may be out of date. We'll retry automatically.
                    </div>
                </div>
            @endif

            @foreach(($overview['banners'] ?? []) as $banner)
                <div class="rsa-note rsa-note-{{ $banner['tone'] }}">
                    <div>
                        <strong>{{ $banner['title'] }}</strong>
                        {{ $banner['body'] }}
                    </div>
                </div>
            @endforeach

            <div class="rsa-tabs">
                <button type="button" class="rsa-tab" x-bind:class="panel === 'overview' && 'rsa-tab-active'"
                        x-on:click="show('overview')">Overview</button>
                <button type="button" class="rsa-tab" x-bind:class="panel === 'billing' && 'rsa-tab-active'"
                        x-on:click="show('billing')">Billing</button>
            </div>

            {{-- ============================ PANEL 1 — OVERVIEW ============================ --}}
            <div x-show="panel === 'overview'">
                <div class="rsa-grid">

                    <div class="rsa-card">
                        <h3>Workspace</h3>
                        <div class="rsa-card-lead">{{ $workspace['name'] ?? ($workspace['company_name'] ?? '—') }}</div>
                        <dl style="margin-top:.6rem;">
                            <div class="rsa-kv"><dt>Email</dt><dd>{{ $workspace['email'] ?? '—' }}</dd></div>
                            <div class="rsa-kv"><dt>Account</dt><dd>{{ ucfirst($workspace['account_type'] ?? '—') }}</dd></div>
                            <div class="rsa-kv"><dt>Created</dt><dd>{{ $fmt($workspace['created_at'] ?? null) }}</dd></div>
                            <div class="rsa-kv">
                                <dt>Status</dt>
                                <dd>
                                    @if(($workspace['verified'] ?? null) === true)
                                        <span class="rsa-pill rsa-pill-ok">Active</span>
                                    @elseif(($workspace['verified'] ?? null) === false)
                                        <span class="rsa-pill rsa-pill-warning">Pending</span>
                                    @else
                                        <span class="rsa-pill rsa-pill-neutral">Unknown</span>
                                    @endif
                                </dd>
                            </div>
                            @if($workspace['admin_contact_phone'] ?? null)
                                <div class="rsa-kv"><dt>Admin contact</dt><dd>{{ $workspace['admin_contact_phone'] }}</dd></div>
                            @endif
                        </dl>
                    </div>

                    <div class="rsa-card">
                        <h3>WhatsApp number</h3>
                        @if($device)
                            <div class="rsa-card-lead">{{ $device['phone'] ?? '—' }}</div>
                            <div class="rsa-sub">{{ $device['alias'] ?? '' }}</div>
                            <div style="margin-top:.7rem;">
                                <span class="rsa-pill rsa-pill-{{ $device['session_tone'] ?? 'neutral' }}"
                                      data-rsa-session-pill>{{ $device['session_label'] ?? 'Unknown' }}</span>
                            </div>
                            <dl style="margin-top:.7rem;">
                                <div class="rsa-kv"><dt>Linked devices</dt><dd>{{ $device['linked_devices'] ?? '—' }}</dd></div>
                                <div class="rsa-kv"><dt>Last sync</dt><dd>{{ $fmt($device['last_sync_at'] ?? null, true) }}</dd></div>
                            </dl>
                        @else
                            {{-- State N-5: workspace exists, no number yet.
                                 Number activation is completed with the
                                 support team for now (§5.4, V-4 unresolved),
                                 so this points at a person, not a button
                                 that would spend money. --}}
                            <div class="rsa-card-lead">Not connected yet</div>
                            <p class="rsa-sub">No WhatsApp number is linked to this workspace. Number activation is completed with our support team — contact your account manager to get started.</p>
                        @endif
                    </div>

                    <div class="rsa-card">
                        <h3>Plan</h3>
                        @if($subscription)
                            <div class="rsa-card-lead">{{ $subscription['plan_label'] }}</div>
                            <div style="margin-top:.55rem;">
                                @php
                                    $subStatus = strtolower((string) ($subscription['billing_status'] ?? $subscription['status'] ?? ''));
                                    $subTone = match ($subStatus) {
                                        'active' => 'ok',
                                        'trialing' => 'info',
                                        'past_due', 'unpaid' => 'danger',
                                        'canceled', 'cancelled', 'disabled', 'paused' => 'warning',
                                        default => 'neutral',
                                    };
                                @endphp
                                <span class="rsa-pill rsa-pill-{{ $subTone }}">{{ $subStatus !== '' ? ucfirst(str_replace('_', ' ', $subStatus)) : 'Unknown' }}</span>
                                @if($subscription['is_trial'] ?? false)
                                    <span class="rsa-pill rsa-pill-info" style="margin-left:.3rem;">Trial</span>
                                @endif
                            </div>
                            <dl style="margin-top:.7rem;">
                                <div class="rsa-kv"><dt>Included agents</dt><dd>{{ $subscription['agents'] ?? '—' }}</dd></div>
                                <div class="rsa-kv"><dt>Billing period</dt><dd>{{ ucfirst($subscription['interval'] ?? '—') }}</dd></div>
                                <div class="rsa-kv"><dt>Renews</dt><dd>{{ $fmt($subscription['ends_at'] ?? null) }}</dd></div>
                                @if($subscription['trial_ends_at'] ?? null)
                                    <div class="rsa-kv"><dt>Trial ends</dt><dd>{{ $fmt($subscription['trial_ends_at']) }}</dd></div>
                                @endif
                            </dl>
                            {{-- No price is rendered here, or anywhere
                                 client-facing (owner decision D-1). --}}
                        @else
                            <div class="rsa-card-lead">No active plan</div>
                            <p class="rsa-sub">A plan starts once your WhatsApp number is activated.</p>
                        @endif
                    </div>

                    <div class="rsa-card">
                        <h3>Connection health</h3>
                        @if($health && $health['score'] !== null)
                            <div class="rsa-score">
                                <b data-rsa-health-score>{{ $health['score'] }}</b><span>/ 100</span>
                                <span class="rsa-pill rsa-pill-{{ ($health['tier'] ?? '') === 'normal' ? 'ok' : 'warning' }}" style="margin-left:.4rem;">{{ ucfirst($health['tier'] ?? 'unknown') }}</span>
                            </div>
                            @php $hs = (int) $health['score']; @endphp
                            <div class="rsa-meter {{ $hs < 70 ? 'rsa-meter-warn' : '' }}"><i style="width: {{ max(2, min(100, $hs)) }}%"></i></div>
                            <div class="rsa-sub">Checked {{ $fmt($health['evaluated_at'] ?? null, true) }}</div>

                            @if(!empty($health['reasons']))
                                <p class="rsa-sub" style="margin-top:.55rem;">
                                    Watch: {{ collect($health['reasons'])->pluck('metric')->filter()->implode(', ') }}
                                </p>
                            @endif

                            <dl style="margin-top:.6rem;">
                                @foreach(array_slice($health['metrics'], 0, 4) as $metric)
                                    <div class="rsa-kv">
                                        <dt>{{ $metric['label'] }}</dt>
                                        <dd>{{ is_scalar($metric['value']) ? $metric['value'] : '—' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <div class="rsa-card-lead">—</div>
                            <p class="rsa-sub">A health score appears once a number is connected and has been running for a while.</p>
                        @endif
                    </div>
                </div>

                @if($subscription)
                    <h2 class="rsa-section-title">This billing period</h2>
                    <div class="rsa-card">
                        <div class="rsa-usage">
                            <div><b>{{ number_format($subscription['usage']['text_messages']) }}</b><span>Text messages</span></div>
                            <div><b>{{ number_format($subscription['usage']['media_messages']) }}</b><span>Media messages</span></div>
                            <div><b>{{ number_format($subscription['usage']['failed_messages']) }}</b><span>Failed</span></div>
                            <div><b>{{ number_format($subscription['usage']['number_checks']) }}</b><span>Number checks</span></div>
                            <div><b>{{ number_format($subscription['usage']['campaigns']) }}</b><span>Campaigns</span></div>
                        </div>
                    </div>
                @endif

                <div class="rsa-grid" style="margin-top:1rem;">
                    <div class="rsa-card">
                        <h3>Team seats</h3>
                        @php
                            $cap = max(1, (int) $seats['cap']);
                            $pct = min(100, (int) round(($seats['used'] / $cap) * 100));
                        @endphp
                        <div class="rsa-card-lead">{{ $seats['used'] }} of {{ $seats['cap'] }} included seats</div>
                        <div class="rsa-meter {{ $seats['reached'] ? 'rsa-meter-full' : '' }}"><i style="width: {{ max(2, $pct) }}%"></i></div>
                        @if($seats['reached'])
                            {{-- State N-13 — a normal product limit, not a
                                 failure, so calm indigo rather than red
                                 (matching the shipped cap card on /resayil). --}}
                            <p class="rsa-sub">You've used all included WhatsApp seats. Contact your account manager to add more.</p>
                        @else
                            <p class="rsa-sub">Staff are given WhatsApp access automatically, up to your included seat count.</p>
                        @endif
                    </div>

                    <div class="rsa-card" style="grid-column: span 2; min-width: 0;">
                        <h3>Setup</h3>
                        <ul class="rsa-check">
                            @foreach(($overview['checklist'] ?? []) as $item)
                                <li class="{{ $item['done'] ? 'rsa-check-done' : '' }}">
                                    <span class="rsa-check-mark">{{ $item['done'] ? '✓' : '' }}</span>
                                    <span>
                                        <span class="rsa-check-label">{{ $item['label'] }}</span>
                                        @if($item['hint'])
                                            <span class="rsa-check-hint" style="display:block;">{{ $item['hint'] }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                @if($isOperator)
                    {{-- OPERATOR STRIP (§5.1 Roles). Raw ids and the
                         collections lever. Never rendered for a COMPANY
                         user — this whole block is inside the role-1 check
                         and the endpoints re-check the role themselves. --}}
                    <div class="rsa-op">
                        <h3>Operator</h3>
                        <dl>
                            <div class="rsa-kv"><dt>Resayil customer</dt><dd><code>{{ $workspace['customer_id'] ?? '—' }}</code></dd></div>
                            <div class="rsa-kv"><dt>Device</dt><dd><code>{{ $device['id'] ?? '—' }}</code></dd></div>
                            <div class="rsa-kv"><dt>Device status</dt><dd><code>{{ $device['status'] ?? '—' }}</code></dd></div>
                            <div class="rsa-kv"><dt>Session</dt><dd><code>{{ $device['session_status'] ?? '—' }}</code></dd></div>
                            <div class="rsa-kv"><dt>Plan code</dt><dd><code>{{ $subscription['plan_code'] ?? '—' }}</code></dd></div>
                            <div class="rsa-kv"><dt>Snapshot</dt><dd><code>{{ ($overview['cached'] ?? false) ? 'cached' : 'live' }} · {{ $fmt($overview['fetched_at'] ?? null, true) }}</code></dd></div>
                        </dl>

                        @if($device)
                            <div class="rsa-op-actions">
                                @if($device['paused'] ?? false)
                                    <button type="button" class="rsa-btn rsa-btn-sm" x-on:click="openModal('resume')">Resume WhatsApp service</button>
                                @else
                                    <button type="button" class="rsa-btn rsa-btn-danger rsa-btn-sm" x-on:click="openModal('pause')">Pause WhatsApp service</button>
                                @endif
                            </div>
                            <p class="rsa-sub" style="margin-top:.5rem;">
                                Pausing stops this number sending and receiving. Conversations, settings and history are preserved. Clients never see this control.
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ============================ PANEL 4 — BILLING ============================ --}}
            <div x-show="panel === 'billing'" x-cloak>
                <div class="rsa-card">
                    <h3>Subscription</h3>
                    @if($subscription)
                        <dl>
                            <div class="rsa-kv"><dt>Plan</dt><dd>{{ $subscription['plan_label'] }}</dd></div>
                            <div class="rsa-kv"><dt>Status</dt><dd>{{ ucfirst(str_replace('_', ' ', (string) ($subscription['billing_status'] ?? $subscription['status'] ?? '—'))) }}</dd></div>
                            <div class="rsa-kv"><dt>Included agents</dt><dd>{{ $subscription['agents'] ?? '—' }}</dd></div>
                            <div class="rsa-kv"><dt>Billing period</dt><dd>{{ ucfirst($subscription['interval'] ?? '—') }}</dd></div>
                            <div class="rsa-kv"><dt>Started</dt><dd>{{ $fmt($subscription['started_at'] ?? null) }}</dd></div>
                            <div class="rsa-kv"><dt>Current period</dt><dd>{{ $fmt($subscription['starts_at'] ?? null) }} – {{ $fmt($subscription['ends_at'] ?? null) }}</dd></div>
                            @if($subscription['trial_ends_at'] ?? null)
                                <div class="rsa-kv"><dt>Trial ends</dt><dd>{{ $fmt($subscription['trial_ends_at']) }}</dd></div>
                            @endif
                        </dl>
                        <p class="rsa-sub" style="margin-top:.8rem;">
                            WhatsApp service is billed to you on your regular invoice from us — not separately by WhatsApp or by Resayil.
                        </p>
                    @else
                        <div class="rsa-empty">No subscription yet. One starts when your WhatsApp number is activated.</div>
                    @endif
                </div>

                <h2 class="rsa-section-title">Payment history</h2>
                <div class="rsa-card">
                    <div x-show="paymentsState === 'loading'" class="rsa-empty">Loading payments…</div>

                    {{-- State D-1 for this table specifically. --}}
                    <div x-show="paymentsState === 'error'" x-cloak class="rsa-note rsa-note-warning" style="margin:0;">
                        <div>
                            <strong>We couldn't load your payment history</strong>
                            Nothing is wrong with your account. Try again in a moment.
                        </div>
                    </div>

                    {{-- State E-1 — an empty list is not an error. --}}
                    <div x-show="paymentsState === 'empty'" x-cloak class="rsa-empty">
                        No payments recorded against this workspace.<br>
                        Your WhatsApp service is settled on your regular invoice from us.
                    </div>

                    <div x-show="paymentsState === 'rows'" x-cloak class="rsa-tablewrap">
                        <table class="rsa-table">
                            <thead>
                                <tr><th>Date</th><th>Description</th><th>Amount</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <template x-for="row in payments" x-bind:key="row.id">
                                    <tr>
                                        <td x-text="row.dateLabel"></td>
                                        <td x-text="row.description || '—'"></td>
                                        <td x-text="row.amountLabel"></td>
                                        <td x-text="row.status || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div x-show="paymentsNext" x-cloak style="margin-top:.75rem;">
                            <button type="button" class="rsa-btn rsa-btn-sm" x-on:click="loadPayments(paymentsNext)">Load more</button>
                        </div>
                    </div>
                </div>

                <h2 class="rsa-section-title">Invoices</h2>
                <div class="rsa-card">
                    {{-- THE FETCH RULE (§0 design law #4): invoice PDFs live
                         behind the account API and need this company's own
                         workspace key, which slice 1 never has. Say so here,
                         in plain words, at the point the client looks for
                         them — rather than showing an empty table that
                         implies there are none. --}}
                    <div class="rsa-empty">
                        Invoice downloads become available once your workspace access is linked.<br>
                        We're completing that for you — no action needed on your side.
                    </div>
                </div>
            </div>
        @endif

        {{-- Operator confirm modal for pause/resume (§9.4 destructive-action ladder). --}}
        @if($isOperator)
        <div x-show="modal" x-cloak class="rsa-modal-back" x-on:click.self="modal = null">
            <div class="rsa-modal">
                <h3 x-text="modal === 'pause' ? 'Pause WhatsApp service?' : 'Resume WhatsApp service?'"></h3>
                <p x-show="modal === 'pause'">
                    This company's WhatsApp number stops sending and receiving immediately. Conversations, contacts and settings are preserved and come back on resume. The client is not notified by us.
                </p>
                <p x-show="modal === 'resume'" x-cloak>
                    Service is restored. Resayil may charge against the credit balance, and the client may need to re-link WhatsApp on their phone afterwards.
                </p>
                <label class="rsa-modal-confirm">
                    <input type="checkbox" x-model="confirmed">
                    <span x-text="modal === 'pause' ? 'I understand this takes a live business number offline.' : 'I understand this may trigger a charge.'"></span>
                </label>
                <p x-show="modalError" x-cloak style="color:#b91c1c;margin-top:.6rem;" x-text="modalError"></p>
                <div class="rsa-modal-actions">
                    <button type="button" class="rsa-btn" x-on:click="modal = null" x-bind:disabled="busy">Cancel</button>
                    <button type="button" class="rsa-btn rsa-btn-danger" x-bind:disabled="!confirmed || busy" x-on:click="runAction()">
                        <span x-show="!busy" x-text="modal === 'pause' ? 'Pause service' : 'Resume service'"></span>
                        <span x-show="busy" x-cloak>Working…</span>
                    </button>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{--
        Plain inline <script>, matching settings/index.blade.php: the app
        layout exposes only a `styles` stack, so a @push('scripts') here
        would be silently dropped. It runs during parse, before Alpine's
        own DOMContentLoaded init, so resayilAdmin() is defined by the time
        x-data is evaluated.
    --}}
    <script>
        function resayilAdmin(config) {
            return {
                panel: config.panel || 'overview',
                busy: false,
                modal: null,
                confirmed: false,
                modalError: '',
                stamp: 'updated just now',
                lastAt: Date.now(),
                payments: [],
                paymentsNext: null,
                paymentsState: 'loading',
                paymentsLoaded: false,

                init() {
                    this.tick();
                    setInterval(() => this.tick(), 30000);
                    if (config.ready) {
                        setInterval(() => this.poll(), 60000);
                    }
                    if (this.panel === 'billing') {
                        this.loadPayments(null);
                    }
                },

                show(panel) {
                    this.panel = panel;
                    try {
                        var url = new URL(window.location.href);
                        url.searchParams.set('panel', panel);
                        window.history.replaceState({}, '', url);
                    } catch (e) { /* history unavailable - harmless */ }
                    if (panel === 'billing' && !this.paymentsLoaded) {
                        this.loadPayments(null);
                    }
                },

                tick() {
                    var secs = Math.round((Date.now() - this.lastAt) / 1000);
                    if (secs < 45) { this.stamp = 'updated just now'; }
                    else if (secs < 5400) { this.stamp = 'updated ' + Math.round(secs / 60) + ' min ago'; }
                    else { this.stamp = 'updated ' + Math.round(secs / 3600) + ' h ago'; }
                },

                headers() {
                    var el = document.querySelector('meta[name="csrf-token"]');
                    return {
                        'X-CSRF-TOKEN': el ? el.getAttribute('content') : '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    };
                },

                /* Background poll - updates only the few live bits in place,
                   so the page never jumps under the reader. A failure is
                   swallowed: the server already renders the degraded state
                   on the next full load, and a transient blip must not
                   produce a scary UI. */
                poll() {
                    if (document.hidden) { return; }
                    fetch(config.pollUrl, { headers: this.headers(), credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then((data) => {
                            if (!data || !data.success || !data.overview) { return; }
                            var device = data.overview.device;
                            var pill = document.querySelector('[data-rsa-session-pill]');
                            if (pill && device) {
                                pill.textContent = device.session_label || 'Unknown';
                                pill.className = 'rsa-pill rsa-pill-' + (device.session_tone || 'neutral');
                            }
                            var score = document.querySelector('[data-rsa-health-score]');
                            if (score && data.overview.health && data.overview.health.score !== null) {
                                score.textContent = data.overview.health.score;
                            }
                            this.lastAt = Date.now();
                            this.tick();
                        })
                        .catch(function () { /* degraded state is rendered server-side */ });
                },

                refresh() {
                    this.busy = true;
                    fetch(config.pollUrl + '?refresh=1', { headers: this.headers(), credentials: 'same-origin' })
                        .then(() => { window.location.reload(); })
                        .catch(() => { this.busy = false; });
                },

                loadPayments(cursor) {
                    this.paymentsLoaded = true;
                    if (!cursor) { this.paymentsState = 'loading'; }
                    var url = config.paymentsUrl + (cursor ? ('?cursor=' + encodeURIComponent(cursor)) : '');
                    fetch(url, { headers: this.headers(), credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then((data) => {
                            if (!data || !data.success) { this.paymentsState = 'error'; return; }
                            if (data.degraded) { this.paymentsState = 'error'; return; }
                            var rows = (data.rows || []).map(function (row, i) {
                                var label = '—';
                                if (row.date) {
                                    var d = new Date(row.date);
                                    if (!isNaN(d.getTime())) { label = d.toLocaleDateString(); }
                                }
                                var amount = row.amount === null || row.amount === undefined
                                    ? '—'
                                    : (row.amount + (row.currency ? (' ' + String(row.currency).toUpperCase()) : ''));
                                row.id = row.id || ('row-' + i + '-' + label);
                                row.dateLabel = label;
                                row.amountLabel = amount;
                                return row;
                            });
                            this.payments = cursor ? this.payments.concat(rows) : rows;
                            this.paymentsNext = data.next || null;
                            this.paymentsState = this.payments.length ? 'rows' : 'empty';
                        })
                        .catch(() => { this.paymentsState = 'error'; });
                },

                openModal(kind) {
                    this.modal = kind;
                    this.confirmed = false;
                    this.modalError = '';
                },

                runAction() {
                    if (!this.confirmed || this.busy) { return; }
                    this.busy = true;
                    this.modalError = '';
                    var url = this.modal === 'pause' ? config.pauseUrl : config.resumeUrl;
                    fetch(url, {
                        method: 'POST',
                        headers: this.headers(),
                        credentials: 'same-origin',
                        body: JSON.stringify({ confirmed: true })
                    })
                        .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                        .then((res) => {
                            this.busy = false;
                            if (res.ok && res.body.success) {
                                window.location.reload();
                            } else {
                                this.modalError = (res.body && res.body.message) || 'That did not work. Nothing was changed.';
                            }
                        })
                        .catch(() => {
                            this.busy = false;
                            this.modalError = 'We could not reach the server. Nothing was changed.';
                        });
                }
            };
        }
    </script>
</x-app-layout>
