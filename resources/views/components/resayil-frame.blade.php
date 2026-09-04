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
    (App\Http\Middleware\ResayilFrameHeaders).

    HONESTY FIX (2026-08-26). This used to mark the badge "Loaded" (green)
    on the iframe's `load` event alone. That is not proof of anything: a
    frame REJECTED by Resayil's own frame-ancestors CSP still fires `load`
    — the browser commits an empty/blocked document and dispatches the
    event on it exactly as it would on a real page — so the badge was
    reporting success while the frame showed a broken-page icon. Verified
    live: wa.resayil.io's frame-ancestors list does not include
    citycommerce.group (dev), so this was not a hypothetical.

    DETECTION, AND ITS HONEST LIMITS. There is no supported way to inspect
    a genuinely cross-origin frame's document, so this is a heuristic, not
    a certainty — treat it as such if you touch this again:
      - A rejected navigation never commits: the frame is left on its
        ORIGINAL "about:blank" document, which still belongs to OUR origin
        (it inherits the origin of whoever created the frame). Reading
        that document's location does not throw, and reads back literally
        "about:blank" — that combination is the one confident signal this
        code acts on ('blocked').
      - A frame that genuinely navigated to Resayil is now cross-origin to
        us, so reading its location throws a SecurityError. That exception
        is the best evidence available that SOMETHING not-us loaded — it
        does NOT prove the visitor is signed in, or even that Resayil
        served its real app rather than one of its own error pages. That
        state is therefore labelled 'unverified', not a green "success".
      - Belt-and-suspenders: if `load` never fires at all within 6s, the
        timeout below still calls this 'blocked' the same as before.
    "Cannot tell" always resolves to the neutral state, never the
    confident one — see the AI Slop / honesty note in the redesign report
    for the same rule applied elsewhere on this panel.
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
        ready(frame) {
            clearTimeout(this.timer);
            this.state = this.looksBlocked(frame) ? 'blocked' : 'unverified';
        },
        blocked() { clearTimeout(this.timer); this.state = 'blocked'; },
        looksBlocked(frame) {
            try {
                // Succeeds only while the frame is still OUR origin — i.e.
                // it never actually navigated away. See the honesty note
                // above for why this, and not a try/catch on its own, is
                // the signal.
                return frame.contentWindow.location.href === 'about:blank';
            } catch (e) {
                // Cross-origin: something else loaded. Not proof of
                // success, only proof it isn't stuck on our own blank page.
                return false;
            }
        },
        popOut() { window.open(@js($embedUrl), '_blank', 'noopener'); },
    }"
    {{ $attributes->merge(['class' => 'flex h-full min-h-0 flex-col overflow-hidden bg-white dark:bg-gray-800' . ($bare ? '' : ' rounded-lg border border-gray-200 shadow-sm dark:border-gray-700')]) }}
>
    @if($notConfigured)
        {{-- Graceful "not configured" state — never a broken iframe. --}}
        <div class="flex flex-1 flex-col items-center justify-center gap-3 px-6 py-14 text-center">
            <img src="{{ asset('images/ResayilLogoFull.png') }}" alt="Resayil" width="420" height="362" class="h-auto max-w-full" style="width:180px">
            {{-- Was "Resayil isn't connected yet" — the same word "Connected"
                 elsewhere on this screen (the Overview panel's WhatsApp
                 session pill) means the live WhatsApp number is online, a
                 completely different fact. This state is about OUR embed
                 setup, not the number, so it talks about availability. --}}
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">The WhatsApp inbox isn't set up here yet</h3>
            <p class="max-w-xs text-sm text-gray-500 dark:text-gray-400">
                An administrator needs to finish the WhatsApp embed setup for this environment before conversations can show up here.
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
                        'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/30': state === 'unverified',
                        'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-gray-600': state === 'loading',
                        'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30': state === 'blocked',
                    }"
                >
                    {{-- Deliberately not green: "loaded" here only means the
                         frame committed a document, not that sign-in
                         succeeded or that it's really Resayil's app. See the
                         honesty note above the x-data block. --}}
                    <span x-show="state === 'unverified'" title="Finished loading. We can't confirm you're signed in from here.">Loaded</span>
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
            <span>Resayil isn't loading here — it's most likely blocking this address from displaying it.</span>
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
            @load="ready($el)"
            x-on:error="blocked()"
            class="min-h-0 flex-1 border-0"
            allow="clipboard-read; clipboard-write"
        ></iframe>
    @endif
</div>
