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
                background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 99%, #fad0c4 100%);
                color: white;
                text-align: center;
            }
            .welcome-container {
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
