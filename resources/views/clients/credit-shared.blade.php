<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <title>{{ config('app.name', 'Laravel') }} - Credit Ledger</title>

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />

    @vite(['resources/css/app.css'])
</head>

<body class="font-sans antialiased">
    {{--
        Public, signed-URL-only counterpart of clients/credit.blade.php --
        see ClientController::showCreditShared() and
        Client::creditStatementUrl(). Deliberately does NOT use
        <x-app-layout>: that layout's menu (resources/views/layouts/menu.blade.php)
        reads auth()->user()->role_id, which is null for the unauthenticated
        visitor this route is built for. showPaginationLinks is false here
        because a signed URL only authorizes the exact query string it was
        generated with -- see the partial's own docblock.
    --}}
    @include('clients.partials.credit-ledger', ['showPaginationLinks' => false])
</body>

</html>
