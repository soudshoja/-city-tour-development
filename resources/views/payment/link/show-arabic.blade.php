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
                <img class="w-auto h-[95px] object-contain" src="{{ $payment->agent->branch->company->logo ? Storage::url($payment->agent->branch->company->logo) : asset('images/UserPic.svg') }}" alt="Company logo" />
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
            <thead class="bg-gray-100">
                <tr>
                    <th colspan="2" class="py-3 px-4 text-lg font-semibold">معلومات الدفع</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="py-3 px-4">اسم العميل</td>
                    <td class="py-3 px-4 text-right">{{ $payment->client->full_name }}</td>
                </tr>
                <tr>
                    <td class="py-3 px-4">بوابة الدفع</td>
                    <td class="py-3 px-4 text-right">{{ $payment->payment_gateway }}</td>
                </tr>
                @if($payment->payment_gateway === 'MyFatoorah')
                <tr>
                    <td class="py-3 px-4">طريقة الدفع</td>
                    <td class="py-3 px-4 text-right">{{ $payment->paymentMethod->english_name ?? '-' }}</td>
                </tr>
                @endif
                @if($payment->payment_gateway !== 'Tabby' && $payment->payment_reference != '')
                        <tr>
                            @if ($payment->payment_gateway !== 'MyFatoorah')
                                <td class="py-3 px-4">Payment Reference</td>
                            @elseif ($payment->invoice_reference == '' && $payment->auth_code == '')
                                <td class="py-3 px-4">رمز الفاتورة</td>
                            @endif
                            <td class="py-3 px-4 text-right">{{ $payment->payment_reference }}</td>
                        </tr>
                    @if($payment->payment_gateway === 'MyFatoorah' && $payment->status === 'completed')
                        <tr>
                            <td class="py-3 px-4">مرجع الفاتورة</td>
                            <td class="py-3 px-4 text-right">{{ $invoiceRef }}</td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4">رمز التحقق</td>
                            <td class="py-3 px-4 text-right">{{ $authorizationId }}</td>
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
                    <h3 class="text-lg text-gray-800">
                        Notes from <span class="font-semibold">Agent {{ $payment->agent->name }}</span>
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


        <!-- MOBILE -->
        <div class="mt-10 md:hidden space-y-3 w-full">
            @unless ($payment->status === 'completed')
            <div class="mb-10">
                <form action="{{ route('payment.link.initiate') }}" method="POST" class="w-full">
                    @csrf
                    <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                    <button type="submit"
                        class="city-light-yellow hover:text-white hover:bg-[#004c9e] rounded-full border border-gray-300 px-5 py-2 shadow-md font-semibold w-[180px]">
                        ادفع الآن
                    </button>
                </form>
            </div>
            @endunless

            <div class="space-y-2 text-center w-full">
                <p class="text-lg font-bold text-gray-800">شكراً لتعاونك معنا!</p>
                <div class="text-sm text-gray-600 w-full overflow-x-auto">
                    <p class="whitespace-nowrap">إذا كان لديك أي أسئلة بخصوص هذه الفاتورة، يرجى التواصل معنا:</p>
                    <p>
                        {{ $payment->agent->name }} -
                        <a href="mailto:{{ $payment->agent->email }}" class="hover:underline hover:text-blue-600">
                            {{ $payment->agent->email }}
                        </a>
                        @if ($payment->agent->phone)
                        || {{ $payment->agent->phone }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- DESKTOP -->
        <div class="mt-10 hidden md:flex items-start justify-between w-full">
            <div class="space-y-2">
                <p class="text-lg font-bold text-gray-800">شكراً لتعاونك معنا!</p>
                <div class="text-sm text-gray-600 w-full overflow-x-auto">
                    <p class="whitespace-nowrap">إذا كان لديك أي أسئلة بخصوص هذه الفاتورة، يرجى التواصل معنا:</p>
                    <p>
                        {{ $payment->agent->name }} -
                        <a href="mailto:{{ $payment->agent->email }}" class="hover:underline hover:text-blue-600">
                            {{ $payment->agent->email }}
                        </a>
                        @if ($payment->agent->phone)
                        || {{ $payment->agent->phone }}
                        @endif
                    </p>
                </div>
            </div>

            @unless ($payment->status === 'completed')
            <form action="{{ route('payment.link.initiate') }}" method="POST" class="flex-shrink-0">
                @csrf
                <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                <button type="submit"
                    class="city-light-yellow hover:text-white hover:bg-[#004c9e] rounded-full border border-gray-300 px-6 py-2 shadow-md font-semibold">
                    ادفع الآن
                </button>
            </form>
            @endunless
            <div class="flex justify-end mb-4">
    <a target="_self" href="{{ route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}">
        <button class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            View Voucher in English
        </button>
    </a>
</div>
        </div>


         
    </div>

</body>

</html>