<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ session('theme') === 'dark' ? 'dark' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{--
        Resayil drawer: apply the saved lock/width state to <html> before
        first paint so a locked-open reflow doesn't flash unreflowed and
        then jump on load. Only matters for a real (non wire:navigate) page
        load — see components/resayil-drawer.blade.php for the store that
        keeps this in sync afterwards.
    --}}
    <script>
        (function () {
            try {
                var locked = localStorage.getItem('resayil:drawer:locked') === '1';
                var open = locked || localStorage.getItem('resayil:drawer:open') === '1';
                var width = parseInt(localStorage.getItem('resayil:drawer:width'), 10);
                if (isNaN(width)) width = 420;
                width = Math.min(720, Math.max(320, width));
                document.documentElement.style.setProperty('--resayil-drawer-width', width + 'px');
                if (locked && open && window.matchMedia && window.matchMedia('(min-width: 1024px)').matches) {
                    document.documentElement.classList.add('resayil-reflow');
                }
            } catch (e) { /* localStorage unavailable — default to unlocked overlay */ }
        })();
    </script>

    @include('layouts.links')

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- CSS -->
    @vite(['resources/css/app.css'])
    @vite(['resources/js/jsbyNisma.js', 'resources/js/app.js', 'resources/js/tools.js'])

    @stack('styles')

    @livewireStyles
    <script src="{{ asset('js/nice-select2.js') }}"></script>
    <!-- Scripts -->

    {!! RecaptchaV3::initJs() !!}
</head>

<body>
    @include('layouts.alert')

    <!-- Top Navigation -->
    <div>
        @include('layouts.navigation')

    </div>
    <!-- ./Top Navigation -->

    <!-- Page Content -->
    <main>
        <div class="container mx-auto max-w-screen overflow-hidden">
            <div class="flex flex-col lg:flex-row md:flex-row">
                
                @include('layouts.sidebar')

                <!-- Main Content -->
                <div class="Main p-5">
                    {{ $slot }}
                    @include('layouts.footer')
                </div>
            </div>
        </div>


    </main>

    @if(session('tbo.url') === null && request()->routeIs('suppliers.tbo.index'))
    @include('suppliers.credential-modal')
    @endif

    <!-- Global Duplicate Client Warning Modal -->
    <x-duplicate-client-warning />

    {{--
        Module 5 — Resayil WhatsApp CRM drawer. Available from anywhere in
        the authenticated app, gated on the same module flag the menu item
        uses. Guest/login pages never include this layout, so no extra
        @auth check is needed beyond the module lookup itself.
    --}}
    @php
        $resayilDrawerUser = auth()->user();
        $resayilDrawerCompanyId = $resayilDrawerUser ? getCompanyId($resayilDrawerUser) : null;
        $resayilDrawerCompany = $resayilDrawerCompanyId ? \App\Models\Company::find($resayilDrawerCompanyId) : null;
        $hasResayilDrawerModule = $resayilDrawerCompany && $resayilDrawerCompany->hasModule(\App\Support\Modules::RESAYIL);
    @endphp
    {{--
        Wrapped in @persist so that on a wire:navigate transition between
        two pages that both render this block, Livewire moves this exact
        DOM node (including the live <iframe> inside it once opened) into
        the new page instead of tearing it down and remounting it. See
        components/resayil-drawer.blade.php for the full explanation and
        components/layouts/documentation.blade.php note: pages using a
        different layout that doesn't render this block are NOT part of
        the persisted set — navigating to one destroys the persisted node
        as normal (no @persist match on the far side), same as it would
        without wire:navigate at all.
    --}}
    @if($hasResayilDrawerModule)
        @persist('resayil-drawer')
            <x-resayil-drawer :embed-url="config('resayil.embed_url')" :not-configured="empty(config('resayil.embed_url'))" />
        @endpersist
    @endif

    @livewireScripts


</body>

</html>