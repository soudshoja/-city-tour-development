<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        // Check localStorage for the dark mode setting before the page is fully loaded
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

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

    @vite(['resources/css/app.css'])

    <style>
    </style>

</head>

<body class="overflow-y-auto font-nunito antialiased bg-gray-100 flex justify-center items-center">
    <div>
        @if (session('status'))
        <div class="bg-green-100 text-green-700 p-4 rounded mb-4">
            {{ session('status') }}
        </div>
        @endif

        @if (session('error'))
        <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
            {{ session('error') }}
        </div>
        @endif

        <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">PAYMENT</h1>
                    <p class="text-sm text-gray-600">Payment Voucher #{{$payment->voucher_number}}</p>
                    <p class="text-sm text-gray-600">Date: {{ $payment->created_at->format('d M, Y') }}</p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-bold text-gray-800"></h2>
                    <p class="text-sm text-gray-600"></p>
                    <p class="text-sm text-gray-600"></p>
                    <p class="text-sm text-gray-600"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-8">
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-gray-800">Bill To:</h3>
                    <p class="text-sm text-gray-600"> {{ $payment->client->name }} </p>
                    <p class="text-sm text-gray-600"> {{ $payment->client->email }} </p>
                    <p class="text-sm text-gray-600"> {{ $payment->client->phone }} </p>
                </div>

                <div>
                    <h3>
                        Notes from <span class="font-semibold"> Agent {{ $payment->agent->name }}</span>
                    </h3>
                    <div class="text-sm text-gray-600 mt-2">
                        {{ $payment->notes ?? 'No Notes' }}
                    </div>
                </div>

            </div>

            <!-- Payment Details -->
            <div class="mb-8 inline-flex gap-2">
                @if(auth()->user())
                <form action="" method="POST">
                    @csrf
                    <input type="hidden" name="client" value=''>
                    <input type="hidden" name="invoiceNumber" value=''>
                    <button type="submit"
                        class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                        Send Payment To Client
                    </button>
                </form>
                @endif
                <form id=""
                    action="{{ route('payment.link.initiate') }}"
                    method="POST">
                    @csrf

                    <input type="hidden" id="payment_id" name="payment_id" value="{{ $payment->id }}">

                    <div class="flex items-center gap-2">
                        <button type="submit" id="payNowBtn"
                            class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                            Pay Now
                        </button>
                        <span id="" class="text-lg font-semibold text-gray-800">
                            {{ number_format($finalAmount, 2) }} {{ $payment->currency }}
                        </span>
                    </div>
                </form>

                @if (auth()->user())
                <div class="flex gap-2 mt-2" id="invoice-link">
                    <button
                        onclick="copyToClipboard('{{ route('payment.link.show', $payment->id) }}')">
                        <img src="{{ asset('images/svg/copy.svg') }}" alt="Copy Link" class="w-4 h-4">
                    </button>

                </div>
                @endif
                <!-- <span class="text-green-600 font-bold">PAID</span> -->
            </div>
            <div class="flex justify-between items-center">
                <div class="text-sm">
                    <p class="text-gray-600">If you have any questions about this invoice, please contact:</p>
                    <p class="text-gray-600">
                        <span>
                            {{ $payment->agent->name}}:
                        </span>
                        <span>
                            {{ $payment->agent->email }}
                        </span>
                        @if($payment->agent->phone)
                        <span>
                            || {{ $payment->agent->phone }}
                        </span>
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <p class="font-bold text-gray-800">Thank you for your business!</p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>