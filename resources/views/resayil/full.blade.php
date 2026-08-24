<x-app-layout>
    {{--
        Resayil full-page view (Module 5b). TravelERP's own header/menu stay
        visible (this is NOT a new tab, NOT an overlay) — Resayil fills the
        content area at full size. Gated end-to-end by the `module:resayil`
        route middleware; a company without the module never reaches this
        view (404 before ResayilEmbedController runs).
    --}}
    <div class="flex h-[calc(100vh-220px)] min-h-[560px] flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Resayil</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">WhatsApp conversations, connected to this company.</p>
            </div>
        </div>

        @if($capReached)
            {{--
                Cap-warning state: a normal product limit, not a failure.
                Calm/informational palette (not red), a plain explanation,
                and a next step — never a raw alert().
            --}}
            <div
                x-data="{ visible: true }"
                x-show="visible"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="flex items-start gap-3 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200"
            >
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-indigo-500 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 8v5m0 3h.01" />
                </svg>
                <div class="flex-1">
                    <p class="font-medium">Your company has reached its {{ $maxAutoUsers }} included Resayil users</p>
                    <p class="mt-0.5 text-indigo-800/80 dark:text-indigo-200/80">
                        Additional Resayil logins beyond {{ $maxAutoUsers }} carry an extra charge from Resayil. Contact your Resayil account manager to add more seats — new users beyond this limit aren't created automatically for now.
                    </p>
                </div>
                <button
                    type="button"
                    @click="visible = false"
                    aria-label="Dismiss"
                    class="grid h-7 w-7 shrink-0 cursor-pointer place-items-center rounded-md text-indigo-500 transition hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:text-indigo-300 dark:hover:bg-white/10"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        <div class="min-h-0 flex-1">
            <x-resayil-frame :embed-url="$embedUrl" :not-configured="$notConfigured" class="h-full" />
        </div>
    </div>
</x-app-layout>
