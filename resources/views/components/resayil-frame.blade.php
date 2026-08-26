@props([
    'embedUrl' => null,
    'notConfigured' => false,
    'bare' => false,
])

{{--
    Shared Resayil CRM iframe with a load/blocked state machine. Reused by
    the drawer (components/resayil-drawer.blade.php) and the full-page view
    (resayil/full.blade.php). Pattern ported from aircon's ResayilFrame.tsx,
    adapted from React state to an Alpine.js component since TravelERP is a
    traditional Blade multi-page app (each navigation is a real page load,
    not client-side routing) — so there is no cross-navigation iframe
    persistence to build here; every open of the drawer/page mounts fresh,
    same as a hard refresh in aircon, which is the case aircon itself falls
    back to "the Resayil session resumes via its cookie" for.

    Framing only works if Resayil's own frame-ancestors policy allows our
    origin and our CSP frame-src whitelists the Resayil origin
    (App\Http\Middleware\ResayilFrameHeaders). "Loaded" below means the
    iframe's document finished loading — NOT that the visitor is signed in.
    See the Module 5 report: Resayil exposes no documented auto-login/SSO
    token handoff, so the FIRST time anyone opens this, Resayil's own login
    screen is what actually renders inside the frame.
--}}
<div
    x-data="{
        state: {{ $notConfigured ? "'not_configured'" : "'loading'" }},
        timer: null,
        init() {
            if (this.state !== 'loading') return;
            this.timer = setTimeout(() => {
                if (this.state === 'loading') this.state = 'blocked';
            }, 6000);
        },
        ready() { clearTimeout(this.timer); this.state = 'ready'; },
        blocked() { clearTimeout(this.timer); this.state = 'blocked'; },
        popOut() { window.open(@js($embedUrl), '_blank', 'noopener'); },
    }"
    {{ $attributes->merge(['class' => 'flex h-full min-h-0 flex-col overflow-hidden bg-white dark:bg-gray-800' . ($bare ? '' : ' rounded-lg border border-gray-200 shadow-sm dark:border-gray-700')]) }}
>
    @if($notConfigured)
        {{-- Graceful "not configured" state — never a broken iframe. --}}
        <div class="flex flex-1 flex-col items-center justify-center gap-3 px-6 py-14 text-center">
            <img src="{{ asset('images/ResayilLogoFull.png') }}" alt="Resayil" width="420" height="362" class="h-auto w-[180px]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resayil isn't connected yet</h3>
            <p class="max-w-xs text-sm text-gray-500 dark:text-gray-400">
                An administrator needs to set the Resayil embed URL for this environment before WhatsApp conversations can show up here.
            </p>
        </div>
    @else
        <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900/40">
            <span class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-white ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-600">
                    <x-icons.chat class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                </span>
                WhatsApp CRM
            </span>
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1"
                    :class="{
                        'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30': state === 'ready',
                        'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-gray-600': state === 'loading',
                        'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30': state === 'blocked',
                    }"
                >
                    <span x-show="state === 'ready'">Loaded</span>
                    <span x-show="state === 'loading'">Loading&hellip;</span>
                    <span x-show="state === 'blocked'">Not loading</span>
                </span>
                <button
                    type="button"
                    @click="popOut()"
                    aria-label="Open Resayil in a new tab"
                    title="Open in a new tab"
                    class="grid h-8 w-8 cursor-pointer place-items-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-emerald-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:text-gray-400 dark:hover:bg-white/5"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                        <path d="M15 3h6v6" />
                        <path d="M10 14 21 3" />
                    </svg>
                </button>
            </div>
        </div>

        <div
            x-show="state === 'blocked'"
            x-cloak
            role="alert"
            class="flex flex-col gap-2 border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
        >
            <span>Resayil hasn't finished loading here. It may be blocking this page from framing it.</span>
            <button
                type="button"
                @click="popOut()"
                class="inline-flex w-fit cursor-pointer items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
            >
                Open in a new tab
            </button>
        </div>

        <iframe
            src="{{ $embedUrl }}"
            title="Resayil WhatsApp CRM"
            @load="ready()"
            x-on:error="blocked()"
            class="min-h-0 flex-1 border-0"
            allow="clipboard-read; clipboard-write"
        ></iframe>
    @endif
</div>
