@props([
    'embedUrl' => null,
    'notConfigured' => false,
])

{{--
    Global Resayil slide-out drawer (Module 5a). Included once from
    components/layouts/app.blade.php, gated by $hasResayilModule there, so
    it is available from anywhere in the authenticated app. Compact chat
    working surface — the full interface lives at the "Resayil" full-page
    view (menu item -> resayil.index).

    Open/closed state remembers per user via localStorage (TravelERP is a
    traditional multi-page app, so there is no cross-navigation client
    router to keep a live iframe mounted across page loads — each open is a
    fresh mount, same as a hard refresh would be in an SPA).
--}}
<style>[x-cloak]{display:none!important}</style>
<div
    x-data="{
        open: false,
        mounted: false,
        init() {
            try { this.open = localStorage.getItem('resayil:drawer:open') === '1'; } catch (e) {}
            this.mounted = this.open;
        },
        show() {
            this.open = true;
            this.mounted = true;
            try { localStorage.setItem('resayil:drawer:open', '1'); } catch (e) {}
        },
        hide() {
            this.open = false;
            try { localStorage.setItem('resayil:drawer:open', '0'); } catch (e) {}
        },
        toggle() { this.open ? this.hide() : this.show(); },
    }"
    @keydown.escape.window="open && hide()"
>
    {{-- Floating launcher bubble --}}
    <button
        type="button"
        x-show="!open"
        x-cloak
        @click="show()"
        aria-label="Open Resayil"
        title="Resayil — WhatsApp conversations"
        class="fixed bottom-5 right-5 z-40 grid h-14 w-14 cursor-pointer place-items-center rounded-full border border-gray-200 bg-white shadow-lg ring-1 ring-black/5 transition duration-200 hover:-translate-y-0.5 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800"
    >
        <x-icons.chat class="h-7 w-7 text-emerald-600 dark:text-emerald-400" />
    </button>

    {{-- Outside-click catcher --}}
    <div
        x-show="open"
        x-cloak
        @click="hide()"
        aria-hidden="true"
        class="fixed inset-0 z-40"
    ></div>

    <aside
        role="dialog"
        aria-modal="true"
        aria-label="Resayil WhatsApp CRM"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-[cubic-bezier(0.22,1,0.36,1)] duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-[cubic-bezier(0.22,1,0.36,1)] duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @click.outside="hide()"
        class="fixed inset-y-0 right-0 z-50 flex w-[420px] max-w-[92vw] flex-col border-l border-gray-200 bg-gray-50 shadow-2xl dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <div class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-600">
                    <x-icons.chat class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                </span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Resayil</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">WhatsApp conversations</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <a
                    href="{{ route('resayil.index') }}"
                    aria-label="Open Resayil full page"
                    title="Open full page"
                    class="grid h-9 w-9 cursor-pointer place-items-center rounded-lg text-gray-500 transition hover:bg-gray-200/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:text-gray-400 dark:hover:bg-white/10"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3" />
                    </svg>
                </a>
                <button
                    type="button"
                    @click="hide()"
                    aria-label="Close Resayil drawer"
                    class="grid h-9 w-9 cursor-pointer place-items-center rounded-lg text-gray-500 transition hover:bg-gray-200/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:text-gray-400 dark:hover:bg-white/10"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 p-3">
            <template x-if="mounted">
                <x-resayil-frame :embed-url="$embedUrl" :not-configured="$notConfigured" bare class="h-full" />
            </template>
        </div>
    </aside>
</div>
