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

    Open/closed/locked/width state remembers per user via localStorage
    (wrapped in try/catch — some browsers throw on storage access).

    State lives in a global Alpine store (resayilDrawer) rather than a
    local x-data scope so it is reachable from outside this component too
    (the reflow rule below targets .Main, which lives in the surrounding
    app layout, not in this file's markup).

    Cross-navigation persistence: the call site
    (components/layouts/app.blade.php) wraps this component in
    @persist('resayil-drawer'), so on a wire:navigate transition between
    two pages that both render this component, Livewire moves this exact
    DOM node — including the live, already-loaded <iframe> — into the new
    page instead of destroying and recreating it. The store registration
    below is guarded to run only once (on Alpine's first real boot), so a
    persisted node is never re-initialised and never resets the state it
    is carrying across navigations.
--}}
<style>
    [x-cloak] { display: none !important; }

    :root { --resayil-drawer-width: 420px; }

    /* Reflow: only applied while the drawer is locked open AND the
       viewport is wide enough (see the 1024px breakpoint note in the
       store below). Targets .Main in components/layouts/app.blade.php —
       a plain <style> tag applies globally regardless of where in the
       DOM it is written, so this is safe to keep colocated here. */
    html.resayil-reflow .Main {
        margin-right: var(--resayil-drawer-width, 420px);
        transition: margin-right 300ms cubic-bezier(0.22, 1, 0.36, 1);
    }

    #resayil-drawer-resize-handle:hover,
    #resayil-drawer-resize-handle:focus-visible {
        background-color: rgb(16 185 129 / 0.4);
    }

    #resayil-drawer-resize-handle.is-resizing {
        background-color: rgb(16 185 129 / 0.6);
    }

    @media (prefers-reduced-motion: reduce) {
        #resayil-drawer-root *,
        html.resayil-reflow .Main {
            transition-duration: .01ms !important;
            animation-duration: .01ms !important;
        }
    }
</style>
<script>
    document.addEventListener('alpine:init', () => {
        if (Alpine.store('resayilDrawer')) return;

        Alpine.store('resayilDrawer', {
            open: false,
            mounted: false,
            locked: false,
            width: 420,
            canReflow: (typeof window.matchMedia === 'function')
                ? window.matchMedia('(min-width: 1024px)').matches
                : false,

            init() {
                try {
                    this.open = localStorage.getItem('resayil:drawer:open') === '1';
                    this.locked = localStorage.getItem('resayil:drawer:locked') === '1';
                    const savedWidth = parseInt(localStorage.getItem('resayil:drawer:width'), 10);
                    if (!Number.isNaN(savedWidth)) {
                        this.width = Math.min(720, Math.max(320, savedWidth));
                    }
                } catch (e) { /* storage unavailable — fall back to defaults */ }

                if (this.locked) this.open = true;
                this.mounted = this.open;
                this._applyDomState();

                if (typeof window.matchMedia === 'function') {
                    const mq = window.matchMedia('(min-width: 1024px)');
                    const onChange = () => { this.canReflow = mq.matches; this._applyDomState(); };
                    if (mq.addEventListener) mq.addEventListener('change', onChange);
                    else if (mq.addListener) mq.addListener(onChange); // Safari <14 fallback
                }

                // A wire:navigate transition swaps in the new page's <html>
                // attributes, which wipes the .resayil-reflow class we put
                // there. This store survives the navigation (it is global and
                // the drawer node is persisted), so nothing here changes and
                // no watcher fires — the drawer would stay locked open while
                // the page silently stopped making room for it. Re-assert the
                // DOM state after every navigation so the two cannot drift.
                document.addEventListener('livewire:navigated', () => this._applyDomState());
            },

            // Reflow (page content shrinks to make room) only kicks in when
            // locked, open, and the viewport is wide enough to make a side
            // split usable. Below that breakpoint we deliberately fall back
            // to the existing overlay look — see resayil-drawer.blade.php
            // header comment / task notes for the 1024px rationale.
            get reflow() {
                return this.locked && this.open && this.canReflow;
            },

            _applyDomState() {
                document.documentElement.style.setProperty('--resayil-drawer-width', this.width + 'px');
                document.documentElement.classList.toggle('resayil-reflow', this.reflow);
            },

            _persist() {
                try {
                    localStorage.setItem('resayil:drawer:open', this.open ? '1' : '0');
                    localStorage.setItem('resayil:drawer:locked', this.locked ? '1' : '0');
                    localStorage.setItem('resayil:drawer:width', String(this.width));
                } catch (e) { /* ignore */ }
            },

            show() {
                this.open = true;
                this.mounted = true;
                this._persist();
                this._applyDomState();
            },

            // Used by the outside-click catcher and Escape — a no-op while
            // locked, since "locked" means the drawer stays open across
            // incidental page interaction.
            hide() {
                if (this.locked) return;
                this.open = false;
                this._persist();
                this._applyDomState();
            },

            // The explicit close (X) button always wins, even while locked —
            // deliberate intent overrides the pin.
            close() {
                this.locked = false;
                this.open = false;
                this._persist();
                this._applyDomState();
            },

            toggle() {
                this.open ? this.hide() : this.show();
            },

            toggleLock() {
                this.locked = !this.locked;
                if (this.locked) {
                    this.open = true;
                    this.mounted = true;
                }
                this._persist();
                this._applyDomState();
            },

            setWidth(px) {
                this.width = Math.min(720, Math.max(320, Math.round(px)));
                this._persist();
                this._applyDomState();
            },
        });
    });
</script>
<div
    id="resayil-drawer-root"
    x-data="{
        resizing: false,
        startResize(event) {
            if (event.button !== undefined && event.button !== 0) return;
            this.resizing = true;
            const pointerId = event.pointerId;
            event.currentTarget.setPointerCapture && event.currentTarget.setPointerCapture(pointerId);
            const onMove = (e) => {
                const fromRight = window.innerWidth - e.clientX;
                $store.resayilDrawer.setWidth(fromRight);
            };
            const onUp = () => {
                this.resizing = false;
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', onUp);
            };
            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp);
        },
        stepWidth(delta) {
            $store.resayilDrawer.setWidth($store.resayilDrawer.width + delta);
        },
    }"
    @keydown.escape.window="$store.resayilDrawer.open && $store.resayilDrawer.hide()"
>
    {{-- Floating launcher bubble --}}
    <button
        type="button"
        x-show="!$store.resayilDrawer.open"
        x-cloak
        @click="$store.resayilDrawer.show()"
        aria-label="Open Resayil"
        title="Resayil — WhatsApp conversations"
        class="fixed bottom-5 right-5 z-40 grid h-14 w-14 cursor-pointer place-items-center rounded-full border border-gray-200 bg-white shadow-lg ring-1 ring-black/5 transition duration-200 hover:-translate-y-0.5 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800"
    >
        <img src="{{ asset('images/ResayilLogoIcon.png') }}" alt="Resayil" width="160" height="149" class="h-7 w-auto">
    </button>

    {{-- Outside-click catcher — only needed in overlay mode. While the
         drawer is reflowing the page (locked open, wide viewport) the
         content beside it is fully live, so no full-screen catcher should
         sit on top of it. --}}
    <div
        x-show="$store.resayilDrawer.open && !$store.resayilDrawer.reflow"
        x-cloak
        @click="$store.resayilDrawer.hide()"
        aria-hidden="true"
        class="fixed inset-0 z-40"
    ></div>

    <aside
        role="dialog"
        aria-modal="true"
        aria-label="Resayil WhatsApp CRM"
        x-show="$store.resayilDrawer.open"
        x-cloak
        x-transition:enter="transition ease-[cubic-bezier(0.22,1,0.36,1)] duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-[cubic-bezier(0.22,1,0.36,1)] duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        @click.outside="$store.resayilDrawer.hide()"
        style="width: var(--resayil-drawer-width, 420px);"
        class="fixed inset-y-0 right-0 z-50 flex max-w-[92vw] flex-col border-l border-gray-200 bg-gray-50 shadow-2xl dark:border-gray-700 dark:bg-gray-900"
    >
        {{-- Resize handle — drag the left edge. Arrow keys work too when
             focused (20px per press). --}}
        <div
            id="resayil-drawer-resize-handle"
            role="separator"
            aria-orientation="vertical"
            aria-label="Resize Resayil drawer"
            tabindex="0"
            :aria-valuenow="$store.resayilDrawer.width"
            aria-valuemin="320"
            aria-valuemax="720"
            :class="{ 'is-resizing': resizing }"
            @pointerdown="startResize($event)"
            @keydown.left.prevent="stepWidth(20)"
            @keydown.right.prevent="stepWidth(-20)"
            class="absolute inset-y-0 left-0 z-10 w-1.5 -translate-x-1/2 cursor-col-resize touch-none rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
        ></div>

        <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <div class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-600">
                    <img src="{{ asset('images/ResayilLogoIcon.png') }}" alt="Resayil" width="160" height="149" class="h-5 w-auto">
                </span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Resayil</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">WhatsApp conversations</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    @click="$store.resayilDrawer.toggleLock()"
                    :aria-pressed="$store.resayilDrawer.locked"
                    :aria-label="$store.resayilDrawer.locked ? 'Unpin Resayil drawer' : 'Pin Resayil drawer open'"
                    :title="$store.resayilDrawer.locked ? 'Unpin — back to floating over the page' : 'Pin open — page content will resize to make room'"
                    class="grid h-9 w-9 cursor-pointer place-items-center rounded-lg text-gray-500 transition hover:bg-gray-200/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:text-gray-400 dark:hover:bg-white/10"
                    :class="{ 'text-emerald-600 dark:text-emerald-400': $store.resayilDrawer.locked }"
                >
                    {{-- Locked: filled/closed padlock --}}
                    <svg x-show="$store.resayilDrawer.locked" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    {{-- Unlocked: open padlock --}}
                    <svg x-show="!$store.resayilDrawer.locked" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                    </svg>
                </button>
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
                    @click="$store.resayilDrawer.close()"
                    aria-label="Close Resayil drawer"
                    class="grid h-9 w-9 cursor-pointer place-items-center rounded-lg text-gray-500 transition hover:bg-gray-200/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:text-gray-400 dark:hover:bg-white/10"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 p-3" :class="{ 'pointer-events-none': resizing }">
            <template x-if="$store.resayilDrawer.mounted">
                <x-resayil-frame :embed-url="$embedUrl" :not-configured="$notConfigured" bare class="h-full" />
            </template>
        </div>
    </aside>
</div>
