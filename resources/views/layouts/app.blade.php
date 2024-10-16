<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />

    <!-- CSS -->

    @vite(['resources/css/app.css', 'resources/css/style.css','resources/css/animate.css', 'resources/js/app.js'
    ,'resources/js/citycutom.js'])


</head>

<body class="overflow-y-auto font-nunito antialiased bg-gray-100">
    <div x-data="{ sidebarOpen: false }" class="flex md:h-screen">
        <!-- Sidebar -->
        @include('layouts.sidebar')

        <!-- Main Content Area -->
        <div :class="sidebarOpen ? 'ml-[260px]' : 'ml-0'" class="flex flex-col flex-1 transition-all duration-300">

            <!-- Header -->
            @include('layouts.navigation')

        </div>
    </div>

</body>

</html>