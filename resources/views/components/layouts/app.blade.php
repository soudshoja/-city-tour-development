<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ session('theme') === 'dark' ? 'dark' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">


    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>







    <title>{{ config('app.name', 'Laravel') }}</title>




    @include('layouts.links')
    <!-- CSS -->

    @vite(['resources/css/app.css', 'resources/css/cityCss.css', 'resources/css/style.css'])
    @vite(['resources/js/jsbyNisma.js', 'resources/js/app.js', 'resources/js/tools.js'])

    @livewireStyles

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
        </div>


    </main>

    @livewireScripts


</body>

</html>