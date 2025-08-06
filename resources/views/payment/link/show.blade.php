<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css'])
</head>

<body class="overflow-y-auto font-nunito antialiased bg-gray-100 py-10">
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        @if (session('status'))
        <div class="bg-green-100 text-green-700 p-4 rounded mb-4">{{ session('status') }}</div>
        @endif

        @if (session('error'))
        <div class="bg-red-100 text-red-700 p-4 rounded mb-4">{{ session('error') }}</div>
        @endif

        <!-- Header -->
        <div class="flex justify-between items-center mb-10">
            {{-- Left: Company Logo --}}
            <div>
                <img src="{{ $companyLogoSrc }}" alt="Company Logo" class="h-16 w-auto inline-block">
            </div>

            {{-- Right: Invoice Details --}}
            <div class="text-right">
                <h1 class="text-2xl font-bold text-gray-800">PAYMENT VOUCHER</h1>
                <p class="text-sm text-gray-600">{{ $payment->voucher_number }}</p>
                <p class="text-sm text-gray-600">Date: {{ $payment->created_at->format('d M Y') }}</p>
            </div>
        </div>

        <!-- Header Ends -->

        <div class="flex justify-between items-start mb-8">
            <div class="max-w-xs">
                <h2 class="text-xl font-bold text-gray-800">{{ $payment->agent->branch->company->name }}</h2>
                <p class="text-sm text-gray-600 break-words">
                    {{ $payment->agent->branch->company->address }}
                </p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:{{ $payment->agent->branch->company->email }}" class="hover:underline hover:text-blue-600">
                        {{ $payment->agent->branch->company->email }}
                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:{{ $payment->agent->branch->company->phone }}" class="hover:underline hover:text-blue-600">
                        {{ $payment->agent->branch->company->phone }}
                    </a>
                </p>
            </div>

            <div class="text-right">
                <h3 class="text-lg font-bold text-gray-800 mb-1">Billed To</h3>
                <p class="text-sm text-gray-600">{{ $payment->client->name }}</p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:{{ $payment->client->email }}" class="hover:underline hover:text-blue-600">
                        {{ $payment->client->email }}
                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:{{ $payment->agent->branch->company->phone }}" class="hover:underline hover:text-blue-600">
                        {{ $payment->client->country_code }}{{ $payment->client->phone }}
                    </a>
                </p>
            </div>
        </div>

        <table class="w-full text-sm text-left text-gray-700 border border-gray-300 mb-5">
            <thead class="bg-gray-100">
                <tr>
                    <th colspan="2" class="py-3 px-4 text-lg font-semibold">Invoice Summary</th>
                </tr>
            </thead>
            <tbody>
                @php
                $serviceCharge = $payment->service_charge ?? $gatewayFee;
                $baseAmount = $payment->amount - $serviceCharge;
                @endphp

                <tr>
                    <td class="py-3 px-4">Amount</td>
                    <td class="py-3 px-4 text-right">{{ number_format($baseAmount, 2) }} {{ $payment->currency }}</td>
                </tr>

                @if ($serviceCharge > 0)
                <tr>
                    <td class="py-1 px-4">
                        <div class="ml-5">Service Charge</div>
                    </td>
                    <td class="py-1 px-4 text-right">
                        {{ number_format($serviceCharge, 2) }} {{ $payment->currency }}
                    </td>
                </tr>
                @endif

                <tr class="font-bold">
                    <td class="py-3 px-4">Total</td>
                    <td class="py-3 px-4 text-right">{{ number_format($finalAmount, 2) }} {{ $payment->currency }}</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-10 mb-5">
            @unless ($payment->status === 'completed')
            <div class="flex justify-between items-center">
                <!-- Notes Section -->
                @if($payment->notes && $payment->notes !== '')
                <div class="text-left">
                    <h3 class="text-lg text-gray-800">
                        Notes from <span class="font-semibold">Agent {{ $payment->agent->name }}</span>
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $payment->notes}}
                    </p>
                </div>
                @else
                <p class="text-sm text-gray-600 mt-1"></p>
                @endif

                <!-- Pay Now Button -->
                <form action="{{ route('payment.link.initiate') }}" method="POST" class="flex items-center gap-4">
                    @csrf
                    <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                    <button type="submit"
                        class="city-light-yellow hover:text-white hover:bg-[#004c9e] rounded-full border border-gray-300 px-4 py-2 shadow-md font-semibold">
                        Pay Now
                    </button>
                </form>
            </div>
            @endunless
        </div>

        @if ($payment->status === 'completed')
        <p class="text-green-600 font-bold">PAID</p>
        @endif

        <div class="mt-10 space-y-2">
            <p class="text-lg font-bold text-gray-800">Thank you for your business!</p>

            <div class="text-sm text-gray-600 w-full overflow-x-auto">
                <p class="whitespace-nowrap">
                    If you have any questions about this voucher, please contact:
                </p>
                <p>
                    {{ $payment->agent->name }} - <a href="mailto:{{ $payment->agent->email }}" class="hover:underline hover:text-blue-600">{{ $payment->agent->email }}</a>
                    @if ($payment->agent->phone)
                    || {{ $payment->agent->phone }}
                    @endif
                </p>
            </div>
        </div>
    </div>
</body>

</html>