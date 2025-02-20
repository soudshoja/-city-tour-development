<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ session('theme') === 'dark' ? 'dark' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
 
    @include('layouts.links')

    <title>{{ config('app.name', 'Laravel') }}</title>
    
    <!-- CSS -->
    @vite(['resources/css/app.css', 'resources/css/cityCss.css', 'resources/css/style.css'])
    @vite(['resources/js/jsbyNisma.js', 'resources/js/app.js', 'resources/js/tools.js'])

    @livewireStyles
    <script src="{{ asset('js/nice-select2.js') }}"></script>
    <!-- Scripts -->
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
                <!-- Sidebar -->
                <div class="Sidebar-Nos">
                    @include('layouts.sidebar')
                </div>

                <!-- Main Content -->
                <div class="Main p-5">
                    {{ $slot }}
                </div>
            </div>
            @include('layouts.footer')
        </div>


    </main>

    @if(Route::is('suppliers.tbo.*') && config('app.env') === 'local')
    @if(session('tbo.url') === null)
    @include('suppliers.credential-modal')
    @endif
    @endif
    @livewireScripts


</body>

</html>