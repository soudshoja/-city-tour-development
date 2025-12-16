<!DOCTYPE html>
<html lang="ar" dir="rtl">

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

@if ($payment->status === 'completed')
<div
    class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg">
    <div class="flex items-center justify-between text-white">
        <p class="text-3xl">PAID</p>
        <h5 class="text-2xl ltr:mr-auto rtl:mr-auto"></h5>
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
            {{-- Left: Invoice Details --}}
            <div class="text-right">
                <h1 class="text-2xl font-bold text-gray-800">قسيمة الدفع</h1>
                <p class="text-sm text-gray-600">{{ $payment->voucher_number }}</p>
                <p class="text-sm text-gray-600">التاريخ: {{ $payment->created_at->format('d M Y') }}</p>
            </div>

            {{-- Right: Company Logo --}}
            <div>
                <x-application-logo class="w-auto h-[90px] object-contain" companyLogo="{{ $payment->agent->branch->company->logo }}" />
            </div>
        </div>
        <!-- Header Ends -->

        <div class="flex justify-between items-start mb-8">
            <div>
                <h3 class="text-lg font-bold text-gray-800 mb-1">الفاتورة مرسلة إلى:</h3>
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
            <div class="max-w-xs text-left">
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

        <table class="w-full text-sm text-gray-700 border border-gray-300 mb-5">
            <thead class="bg-gray-100 text-right">
                <tr>
                    <th colspan="2" class="py-3 px-4 text-lg font-semibold">معلومات الدفع</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="py-3 px-4">اسم العميل</td>
                    <td class="py-3 px-4 text-left">{{ $payment->client->full_name }}</td>
                </tr>
                <tr>
                    <td class="py-3 px-4">بوابة الدفع</td>
                    <td class="py-3 px-4 text-left">{{ $payment->payment_gateway }}</td>
                </tr>
                @if($payment->payment_gateway === 'MyFatoorah')
                <tr>
                    <td class="py-3 px-4">طريقة الدفع</td>
                    <td class="py-3 px-4 text-left">{{ $payment->paymentMethod->english_name ?? '-' }}</td>
                </tr>
                @endif
                @if($payment->payment_gateway !== 'Tabby' && $payment->payment_reference != '')
                <tr>
                    @if ($payment->payment_gateway !== 'MyFatoorah')
                    <td class="py-3 px-4">Payment Reference</td>
                    @elseif ($payment->invoice_reference == '' && $payment->auth_code == '')
                    <td class="py-3 px-4">رمز الفاتورة</td>
                    @endif
                    <td class="py-3 px-4 text-left">{{ $payment->payment_reference }}</td>
                </tr>
                @if($payment->payment_gateway === 'MyFatoorah' && $payment->status === 'completed')
                <tr>
                    <td class="py-3 px-4">مرجع الفاتورة</td>
                    <td class="py-3 px-4 text-left">{{ $invoiceRef }}</td>
                </tr>
                <tr>
                    <td class="py-3 px-4">رمز التحقق</td>
                    <td class="py-3 px-4 text-left">{{ $authorizationId }}</td>
                </tr>
                @endif
                @endif
            </tbody>
        </table>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start mb-8 mt-10">
            <div class="md:col-span-2">
                @if ($payment->status === 'completed')
                <span class="inline-flex items-center px-3 py-1 text-green-700 font-semibold text-lg">
                    تم الدفع
                </span>
                @else
                @if($payment->notes && $payment->notes !== '')
                <div class="max-w-xs">
                    <h3 class="text-lg text-gray-800" dir="rtl">
                        ملاحظات من <span class="font-semibold">الوكيل {{ $payment->agent->name }}</span>
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

            {{-- Right slot: Totals --}}
            <div class="md:col-span-1 w-full text-sm">
                @php
                $serviceCharge = $payment->service_charge ?? $gatewayFee;
                $baseAmount = $payment->amount - $serviceCharge;
                @endphp

                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>المبلغ:</span>
                    <!--  <span>{{ number_format($payment->amount, 2) }}</span> -->
                    <span>{{ number_format(!empty($finalAmount) ? $finalAmount : $payment->amount, 2) }} {{ $payment->currency }}</span>
                </div>

                <!-- @if ($serviceCharge > 0)
                <tr>
                    <td class="py-1 px-4">
                        <div class="ml-5">Service Charge</div>
                    </td>
                    <td class="py-1 px-4 text-right">
                        {{ number_format($serviceCharge, 2) }} {{ $payment->currency }}
                    </td>
                </tr>
                @endif -->

                <div class="flex justify-between items-center py-2 font-bold text-gray-800">
                    <span>المجموع:</span>
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
                        <label for="agree-modal" class="text-sm text-gray-700" dir="rtl">
                            لقد قرأت
                            <button type="button" @click="TNCModal = true" class="text-blue-600 hover:underline font-medium">
                                الشروط والأحكام
                            </button>
                            وأوافق عليها
                        </label>
                    </div>

                    @unless ($payment->status === 'completed' || $payment->is_disabled)
                    <form action="{{ route('payment.link.initiate') }}" method="POST" class="flex-shrink-0">
                        @csrf
                        <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                        <button type="submit"
                            :disabled="!agreed"
                            :class="agreed ? 'city-light-yellow hover:text-white hover:bg-[#004c9e]' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                            class="w-full md:w-auto rounded-full border border-gray-300 px-6 py-2 shadow-md font-semibold transition-colors">
                            ادفع الآن
                        </button>
                    </form>
                    @endunless
                </div>
            </div>

            <!-- Modal -->
            <div x-show="TNCModal" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.away="TNCModal = false">
                <div class="bg-white rounded-2xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col shadow-2xl">
                    <div class="px-6 pt-5 pb-4 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">الشروط والأحكام</h3>
                            <p class="text-xs text-gray-500 italic mt-0.5">يرجى قراءة الشروط والأحكام بعناية قبل إتمام عملية الدفع</p>
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
                            أغلق
                        </button>
                        <button type="button" @click="agreed = true; TNCModal = false" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-full shadow-md hover:bg-blue-700">
                            أنا موافق
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
                    ادفع الآن
                </button>
            </form>
        </div>
        @endunless
        @endif

        <div class="space-y-2 text-center w-full mt-6" dir="rtl">
            <div class="text-sm text-gray-600 w-full overflow-x-auto">
                <p>لأي استفسار حول هذه القسيمة، يرجى التواصل مع الوكيل
                    <span class="font-semibold">{{ $payment->agent->name }}</span> عبر
                </p>
                <p>
                    <a href="mailto:{{ $payment->agent->email }}" class="font-semibold hover:underline hover:text-blue-600">
                        {{ $payment->agent->email }}
                    </a>
                    @if ($payment->agent->phone_number)
                    أو <span class="font-semibold">{{ $payment->agent->phone_number }}</span>
                    @endif
                </p>
            </div>
        </div>

        <!-- <div class="flex justify-end mb-4">
                <a target="_self" href="{{ route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}">
                    <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        View Voucher in English
                    </button>
                </a>
            </div> -->

    </div>

</body>

</html>