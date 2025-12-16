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

@if ($payment->status === 'completed')
<div
    class="mb-2 max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg">
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

        <!-- THIS IS FOR NEW MULTI PAYMENT METHOD -->
        <div class="flex justify-between items-center mb-10">
            {{-- Left: Invoice Details --}}
            <div class="text-left">
                <h1 class="text-2xl font-bold text-gray-800">PAYMENT VOUCHER</h1>
                <p class="text-sm text-gray-600">{{ $payment->voucher_number }}</p>
                <p class="text-sm text-gray-600">Date: {{ $payment->created_at->format('d M Y') }}</p>
            </div>

            {{-- Right: Company Logo --}}
            <div>
                <img class="w-auto h-[95px] object-contain" src="{{ $payment->agent->branch->company->logo ? Storage::url($payment->agent->branch->company->logo) : asset('images/UserPic.svg') }}" alt="Company logo" />
            </div>

        </div>
        <!-- Header Ends -->

        <div class="flex justify-between items-start mb-8">
            <div class="text-left">
                <h3 class="text-lg font-bold text-gray-800 mb-1">Billed To</h3>
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
            <div class="max-w-xs text-right">
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

        <table class="w-full text-sm text-left text-gray-700 border border-gray-300 mb-5">
            <thead class="bg-gray-100">
                <tr>
                    <th colspan="2" class="py-3 px-4 text-lg font-semibold">Payment Details</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="py-3 px-4">Client Name</td>
                    <td class="py-3 px-4 text-right">{{ $payment->client->full_name }}</td>
                </tr>
                @if($payment->status === 'completed' && $payment->paymentMethod)
                <tr>
                    <td class="py-3 px-4">Payment Method</td>
                    <td class="py-3 px-4 text-right">{{ $payment->paymentMethod->english_name ?? '-' }}</td>
                </tr>
                @endif
                @if(!empty($payment->payment_reference))
                <tr>
                    @if ($payment->payment_gateway === 'MyFatoorah')
                    @if(empty($payment->invoice_reference) && empty($payment->auth_code) && empty($invoiceRef))
                    <td class="py-3 px-4">Invoice ID</td>
                    @else
                    <td class="py-3 px-4">Payment Reference</td>
                    @endif
                    @else
                    <td class="py-3 px-4">Payment Reference</td>
                    @endif
                    <td class="py-3 px-4 text-right">{{ $payment->payment_reference }}</td>
                </tr>
                @if($payment->payment_gateway === 'MyFatoorah' && $payment->status === 'completed' && !empty($invoiceRef))
                <tr>
                    <td class="py-3 px-4">Invoice Reference</td>
                    <td class="py-3 px-4 text-right">{{ $invoiceRef }}</td>
                </tr>
                @endif
                @endif
            </tbody>

        </table>

        @if ($payment->status !== 'completed' && $payment->availablePaymentMethods && $payment->availablePaymentMethods->isNotEmpty())
        <div class="border rounded-lg overflow-hidden">
            <div class="bg-gray-100 p-4 font-semibold text-lg border-b border-gray-300">
                Choose Payment Method
            </div>
            <div class="p-4" id="payment-methods-container">
                @foreach ($payment->availablePaymentMethods as $index => $method)
                <label class="flex items-center p-4 border rounded-lg mb-3 cursor-pointer hover:bg-gray-50 transition-colors {{ $method->is_active ? 'border-gray-300' : 'border-red-300 bg-red-50' }}"
                    for="payment_method_{{ $method->id }}">
                    <input
                        type="radio"
                        name="selected_payment_method"
                        id="payment_method_{{ $method->id }}"
                        value="{{ $method->id }}"
                        data-final-amount="{{ number_format($method->final_amount, 2, '.', '') }}"
                        {{ $index === 0 ? 'checked' : '' }}
                        {{ !$method->is_active ? 'disabled' : '' }}
                        class="w-4 h-4 text-blue-600 focus:ring-blue-500 mr-3">
                    <div>
                        <span class="font-medium text-gray-800">
                            {{ $method->paymentMethodGroup ? $method->paymentMethodGroup->name : 'unknown' }}
                        </span>
                        @if (!$method->is_active)
                        <span class="block text-xs text-red-600">Currently unavailable</span>
                        @endif
                    </div>
                </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start mb-8 mt-10">
            <div class="md:col-span-2">
                @if ($payment->status === 'completed')
                <span class="inline-flex items-center px-3 py-1 text-green-700 font-semibold text-lg">
                    PAID
                </span>
                @else
                @if($payment->notes && $payment->notes !== '')
                <div class="text-left max-w-xs">
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
                <div class="flex justify-between items-center py-2 font-bold text-gray-800">
                    <span>Total:</span>
                    <span id="total-amount">
                        @if ($payment->availablePaymentMethods && $payment->availablePaymentMethods->isNotEmpty())
                        {{ number_format($payment->availablePaymentMethods->first()->final_amount, 2) }} {{ $payment->currency }}
                        @else
                        {{ number_format($payment->amount, 2) }} {{ $payment->currency }}
                        @endif
                    </span>
                </div>
            </div>
        </div>


        <!-- MOBILE -->
        <div class="mt-10 md:hidden space-y-3 w-full">
            @unless ($payment->status === 'completed' || $payment->is_disabled)
            <div class="mb-10">
                <form action="{{ route('payment.link.initiate') }}" method="POST" class="w-full" id="payment-form-mobile">
                    @csrf
                    <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                    <input type="hidden" name="payment_method_id" id="payment_method_input_mobile">
                    <button type="submit"
                        class="city-light-yellow hover:text-white hover:bg-[#004c9e] rounded-full border border-gray-300 px-5 py-2 shadow-md font-semibold w-[180px] text-left">
                        Pay Now
                    </button>
                </form>
            </div>
            @endunless

            <div class="space-y-2 text-center w-full">
                <p class="text-lg font-bold text-gray-800">Thank you for your business!</p>
                <div class="text-sm text-gray-600 w-full overflow-x-auto">
                    <p class="whitespace-nowrap">If you have any questions about this voucher, please contact:</p>
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
            <div class="space-y-2 text-left">
                <p class="text-lg font-bold text-gray-800">Thank you for your business!</p>
                <div class="text-sm text-gray-600 w-full overflow-x-auto">
                    <p class="whitespace-nowrap">If you have any questions about this voucher, please contact:</p>
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

            @unless ($payment->status === 'completed' || $payment->is_disabled)
            <form action="{{ route('payment.link.multi-initiate') }}" method="POST" class="w-full max-w-xs text-right" id="payment-form-desktop">
                @csrf
                <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                <input type="hidden" name="payment_method_id" id="payment_method_input_desktop">
                <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                <button type="submit"
                    class="city-light-yellow hover:text-white hover:bg-[#004c9e] rounded-full border border-gray-300 px-6 py-2 shadow-md font-semibold">
                    Pay Now
                </button>
            </form>
            @endunless
            <div class="flex justify-end mb-4">
                <button
                    onclick="window.open('{{ route('payment.link.show-arabic', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}', '_blank')"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    عرض القسيمة بالعربية
                </button>
            </div>

        </div>

        <script>
            // Sync selected payment method to hidden inputs in both forms
            document.addEventListener('DOMContentLoaded', function() {
                const radioButtons = document.querySelectorAll('input[name="selected_payment_method"]');
                const mobileInput = document.getElementById('payment_method_input_mobile');
                const desktopInput = document.getElementById('payment_method_input_desktop');

                // Set initial value from checked radio
                const checkedRadio = document.querySelector('input[name="selected_payment_method"]:checked');
                if (checkedRadio) {
                    if (mobileInput) mobileInput.value = checkedRadio.value;
                    if (desktopInput) desktopInput.value = checkedRadio.value;
                }

                // Update hidden inputs and total amount when radio selection changes
                radioButtons.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (mobileInput) mobileInput.value = this.value;
                        if (desktopInput) desktopInput.value = this.value;

                        // Update the total amount display
                        const finalAmount = this.getAttribute('data-final-amount');
                        const totalAmountElement = document.getElementById('total-amount');
                        if (totalAmountElement && finalAmount) {
                            const formattedAmount = parseFloat(finalAmount).toFixed(2);
                            totalAmountElement.textContent = formattedAmount + ' {{ $payment->currency }}';
                        }
                    });
                });

                // Validate on form submit
                const forms = [
                    document.getElementById('payment-form-mobile'),
                    document.getElementById('payment-form-desktop')
                ];

                forms.forEach(form => {
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            const selectedMethod = document.querySelector('input[name="selected_payment_method"]:checked');
                            if (!selectedMethod) {
                                e.preventDefault();
                                alert('Please select a payment method before proceeding.');
                                return false;
                            }
                        });
                    }
                });
            });
        </script>
    </div>
</body>

</html>