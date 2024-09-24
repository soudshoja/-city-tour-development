<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Welcome</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <style>
    body {
        font-family: 'Figtree', sans-serif;
        margin: 0;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        color: white;
        text-align: center;
        overflow: hidden;
    }

    .video-background {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: -1;
    }

    .welcome-container {
        position: relative;
        z-index: 1;
        text-align: center;
    }

    .welcome-text {
        font-size: 4rem;
        font-weight: 600;
        margin-bottom: 2rem;
    }

    .btn {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        text-decoration: none;
        font-weight: 600;
        transition: background-color 0.3s;
    }

    .btn:hover {
        background-color: rgba(255, 255, 255, 0.4);
    }
    </style>
</head>

<body>
    <!-- Video background -->
    <video autoplay muted loop class="video-background">
        <source src="{{ asset('videos/techbg.mp4') }}" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <div class="welcome-container">
        <div class="welcome-text">Welcome</div>
        @if (Route::has('login'))
        <div>
            @auth
            <a href="{{ url('/dashboard') }}" class="btn">Dashboard</a>
            @else
            <a href="{{ route('login') }}" class="btn">Log in</a>
            @endif
        </div>
        @endif
    </div>
</body>

</html>