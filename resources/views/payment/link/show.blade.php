<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

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

@php
    $isRtl = app()->getLocale() === 'ar';
    $textAlign = $isRtl ? 'text-right' : 'text-left';
    $textAlignReverse = $isRtl ? 'text-left' : 'text-right';
@endphp

@if ($payment->status === 'completed')
<div class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg">
    <div class="flex items-center justify-between text-white">
        <p class="text-3xl">{{ __('invoice.paid') }}</p>
    </div>
</div>
@endif

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
            <div class="{{ $textAlign }}">
                <h1 class="text-2xl font-bold text-gray-800">{{ __('invoice.payment_voucher') }}</h1>
                <p class="text-sm text-gray-600">{{ $payment->voucher_number }}</p>
                <p class="text-sm text-gray-600">{{ __('invoice.date') }}: {{ $payment->created_at->format('d M Y') }}</p>
            </div>

            <div>
                <img class="w-auto h-[95px] object-contain" src="{{ $payment->agent->branch->company->logo ? Storage::url($payment->agent->branch->company->logo) : asset('images/UserPic.svg') }}" alt="Company logo" />
            </div>
        </div>

        <!-- Billed To & Company Info -->
        <div class="flex justify-between items-start mb-8">
            <div class="{{ $textAlign }}">
                <h3 class="text-lg font-bold text-gray-800 mb-1">{{ __('invoice.billed_to') }}</h3>
                <p class="text-sm text-gray-600">{{ $payment->client->full_name }}</p>
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
            <div class="max-w-xs {{ $textAlignReverse }}">
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
        </div>

        <!-- Payment Details Table -->
        <table class="w-full text-sm {{ $textAlign }} text-gray-700 border border-gray-300 mb-5">
            <thead class="bg-gray-100">
                <tr>
                    <th colspan="2" class="py-3 px-4 text-lg font-semibold {{ $textAlign }}">{{ __('invoice.payment_details') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="py-3 px-4">{{ __('invoice.client_name') }}</td>
                    <td class="py-3 px-4 {{ $textAlignReverse }}">{{ $payment->client->full_name }}</td>
                </tr>
                <tr>
                    <td class="py-3 px-4">{{ __('invoice.payment_gateway') }}</td>
                    <td class="py-3 px-4 {{ $textAlignReverse }}">{{ $payment->payment_gateway }}</td>
                </tr>
                @if($payment->paymentMethod)
                <tr>
                    <td class="py-3 px-4">{{ __('invoice.payment_method') }}</td>
                    <td class="py-3 px-4 {{ $textAlignReverse }}">{{ $payment->paymentMethod->english_name ?? '-' }}</td>
                </tr>
                @endif
                @if(!empty($payment->payment_reference))
                <tr>
                    @if ($payment->payment_gateway === 'MyFatoorah')
                        @if(empty($payment->invoice_reference) && empty($payment->auth_code) && empty($invoiceRef))
                        <td class="py-3 px-4">{{ __('invoice.invoice_id') }}</td>
                        @else
                        <td class="py-3 px-4">{{ __('invoice.payment_reference') }}</td>
                        @endif
                    @else
                    <td class="py-3 px-4">{{ __('invoice.payment_reference') }}</td>
                    @endif
                    <td class="py-3 px-4 {{ $textAlignReverse }}">{{ $payment->payment_reference }}</td>
                </tr>
                @if($payment->payment_gateway === 'MyFatoorah' && $payment->status === 'completed' && !empty($invoiceRef))
                <tr>
                    <td class="py-3 px-4">{{ __('invoice.invoice_reference') }}</td>
                    <td class="py-3 px-4 {{ $textAlignReverse }}">{{ $invoiceRef }}</td>
                </tr>
                @endif
                @endif
            </tbody>
        </table>

        <!-- Notes & Amounts -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start mb-8 mt-10">
            <div class="md:col-span-2">
                @if ($payment->status === 'completed')
                <span class="inline-flex items-center px-3 py-1 text-green-700 font-semibold text-lg">
                    {{ __('invoice.paid') }}
                </span>
                @else
                    @if($payment->notes && $payment->notes !== '')
                    <div class="{{ $textAlign }} max-w-xs">
                        <h3 class="text-lg text-gray-800">
                            {{ __('invoice.notes_from_agent', ['name' => $payment->agent->name]) }}
                        </h3>
                        <p class="text-sm text-gray-600 mt-1 break-words">
                            {{ $payment->notes }}
                        </p>
                    </div>
                    @else
                    <p class="text-sm text-gray-600 mt-1"></p>
                    @endif
                @endif
            </div>

            <div class="md:col-span-1 w-full text-sm">
                @php
                $serviceCharge = $payment->service_charge ?? $gatewayFee;
                $baseAmount = $payment->amount - $serviceCharge;
                @endphp

                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>{{ __('invoice.amount') }}:</span>
                    <span>{{ number_format(!empty($finalAmount) ? $finalAmount : $payment->amount, 2) }} {{ $payment->currency }}</span>
                </div>

                <div class="flex justify-between items-center py-2 font-bold text-gray-800">
                    <span>{{ __('invoice.total') }}:</span>
                    <span>{{ number_format(!empty($finalAmount) ? $finalAmount : $payment->amount, 2) }} {{ $payment->currency }}</span>
                </div>
            </div>
        </div>

        <!-- TnC & Pay Now -->
        @if (!empty($payment->terms_conditions) && $payment->status != 'completed')
        <div class="md:col-span-3 w-full mt-2" x-data="{ TNCModal: false, agreed: false }">
            <div class="rounded-xl p-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            id="agree-modal"
                            x-model="agreed"
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm text-gray-700">
                            {{ __('invoice.tnc_read_agree') }}
                            <button type="button" @click.stop.prevent="TNCModal = true" class="text-blue-600 hover:underline font-medium">
                                {{ __('invoice.tnc_title') }}
                            </button>
                        </span>
                    </div>

                    @unless ($payment->status === 'completed' || $payment->is_disabled)
                    <form action="{{ route('payment.link.initiate') }}" method="POST" class="flex-shrink-0">
                        @csrf
                        <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                        <button type="submit"
                            :disabled="!agreed"
                            :class="agreed ? 'city-light-yellow hover:text-white hover:bg-[#004c9e]' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                            class="w-full md:w-auto rounded-full border border-gray-300 px-6 py-2 shadow-md font-semibold transition-colors">
                            {{ __('invoice.pay_now') }}
                        </button>
                    </form>
                    @endunless
                </div>
            </div>

            <div x-show="TNCModal" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.away="TNCModal = false">
                <div class="bg-white rounded-2xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col shadow-2xl">
                    <div class="px-6 pt-5 pb-4 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('invoice.tnc_title') }}</h3>
                            <p class="text-xs text-gray-500 italic mt-0.5">{{ __('invoice.tnc_subtitle') }}</p>
                        </div>
                        <button type="button" @click="TNCModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="px-6 py-4 overflow-y-auto flex-1 border-t border-gray-200">
                        <div class="prose prose-sm text-gray-600 whitespace-pre-wrap">{{ $payment->terms_conditions }}</div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-between">
                        <button type="button" @click="TNCModal = false" class="px-4 py-2 text-sm bg-gray-100 text-gray-600 font-medium rounded-full shadow-md hover:text-gray-800">
                            {{ __('invoice.close') }}
                        </button>
                        <button type="button" @click="agreed = true; TNCModal = false" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-full shadow-md hover:bg-blue-700">
                            {{ __('invoice.agree') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @else
        @unless ($payment->status === 'completed' || $payment->is_disabled)
        <div class="md:col-span-3 w-full mt-2 flex justify-end">
            <form action="{{ route('payment.link.initiate') }}" method="POST" class="w-full md:w-auto">
                @csrf
                <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                <button type="submit"
                    class="w-full md:w-auto city-light-yellow hover:text-white hover:bg-[#004c9e] rounded-full border border-gray-300 px-6 py-2 shadow-md font-semibold">
                    {{ __('invoice.pay_now') }}
                </button>
            </form>
        </div>
        @endunless
        @endif

        <div class="space-y-2 text-center w-full mt-6">
            <div class="text-sm text-gray-600 w-full overflow-x-auto">
                <p>{{ __('invoice.questions', ['name' => $payment->agent->name]) }}</p>
                <p>
                    <a href="mailto:{{ $payment->agent->email }}" class="font-semibold hover:underline hover:text-blue-600">
                        {{ $payment->agent->email }}
                    </a>
                    @if ($payment->agent->phone_number)
                    {{ __('invoice.or') }} <span class="font-semibold">{{ $payment->agent->phone_number }}</span>
                    @endif
                </p>
            </div>
        </div>

    </div>
</body>

</html>