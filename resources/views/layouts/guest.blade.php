<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />

    <link rel="stylesheet" type="text/css" media="screen" href="{{ asset('css/perfect-scrollbar.min.css') }}" />
    <link rel="stylesheet" type="text/css" media="screen" href="{{ asset('css/style.css') }}" />
    <link rel="stylesheet" type="text/css" media="screen" href="{{ asset('css/animate.css') }}" />
    <link rel="stylesheet" type="text/css" media="screen" href="{{ asset('css/app.css') }}" />

    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>


    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/css/cityCss.css'])
</head>

<body>
    @if($errors->any())
    @foreach($errors->all() as $error)
    <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
        {{ $error }}
        <button type="button" class="close text-white ml-2" aria-label="Close"
            onclick="this.parentElement.style.display='none';">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endforeach
    @endif

    @if(session('success'))
    <div class="alert alert-success fixed mt-5 top-1 right-4 bg-green-500 text-white p-4 rounded shadow-lg">
        {{ session('success') }}
        <button type="button" class="close text-white ml-2" aria-label="Close"
            onclick="this.parentElement.style.display='none';">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @elseif(session('error'))
    <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
        {{ session('error') }}
        <button type="button" class="close text-white ml-2" aria-label="Close"
            onclick="this.parentElement.style.display='none';">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif

    <div>
        {{ $slot }}
    </div>

</body>

</html>