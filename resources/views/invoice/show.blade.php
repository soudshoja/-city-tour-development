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

    <!-- CSS -->
    @vite(['resources/css/app.css'])

    <style>
        input[type="checkbox"].disabled-checkbox {
            cursor: not-allowed;
            /* Change cursor to indicate it's not clickable */
            opacity: 0.6;
            /* Reduce opacity to indicate it's disabled */
            background-color: #e2e8f0;
            /* Light gray background to show it's disabled */
        }

        tr.disabled-row {
            cursor: not-allowed;
            /* Change cursor to indicate the row is not clickable */
            opacity: 0.6;
            /* Reduce opacity to indicate it's disabled */
            background-color: #e2e8f0;
            /* Light gray background to show the row is disabled */
        }

        /* Make the disabled checkbox also look like it's disabled */
        tr.disabled-row input[type="checkbox"] {
            cursor: not-allowed;
            /* Prevent interaction */
            opacity: 1;
            /* Keep checkbox opacity full */
        }
    </style>
    <!-- Scripts -->
    <script src="//unpkg.com/alpinejs" defer></script>

    <script src="https://code.jquery.com/jquery-3.7.1.slim.js"
        integrity="sha256-UgvvN8vBkgO0luPSUl2s8TIlOSYRoGFAX4jlCIm9Adc=" crossorigin="anonymous"></script>

</head>

<body class="overflow-y-auto font-nunito antialiased bg-gray-100">

    @if(app()->environment('local'))
    @if($errors->any())
    <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    @endif

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
    @if (in_array($invoice->status, ['paid', 'paid by refund']))
    <div
        class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg">
        <div class="flex items-center justify-between text-white">
            <p class="text-3xl">PAID</p>
            <h5 class="text-2xl ltr:mr-auto rtl:mr-auto"></h5>
        </div>
    </div>


    @endif
    @if ($invoice->status === 'partial')
    <div class="max-w-4xl mx-auto rounded-lg border border-yellow-300 bg-yellow-100 p-6 flex items-center rounded-lg">
        <div class="flex items-center gap-2 text-yellow-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zM9 5h2v5H9V5zm0 6h2v2H9v-2z" clip-rule="evenodd" />
            </svg>
            <div class="font-semibold">Invoice is partially paid.</div>
            <div class="text-sm">Some installments are paid, some are pending. You can continue below.</div>
        </div>
    </div>
    @endif
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        <!-- Header -->
        <div class="flex justify-between items-center mb-10">
            <div class="text-left">
                <h1 class="text-2xl font-bold text-gray-800">INVOICE</h1>
                @if ($invoice->refundDetails->isNotEmpty())
                    <p class="text-sm text-gray-600">Generated from Refund {{ $invoice->refundDetails->first()->refund->refund_number }}</p>
                @endif
                <p class="text-sm text-gray-600">{{ $invoice->invoice_number }}</p>
                <p class="text-sm text-gray-600">Date: {{ $invoice->created_at->format('d M, Y') }}</p>
            </div>
        
            <div>
                <x-application-logo class="w-auto h-[90px] object-contain" companyLogo="{{ $company->logo }}" />
                <p class="text-base font-semibold">{{ $invoice->agent->branch->company->name }}</p>
            </div>
        </div>

        <!-- Header Ends -->

        <div class="flex justify-between items-center mb-8">
            <div class="text-left">
                <h3 class="text-lg font-bold text-gray-800">Billed To</h3>
                <p class="text-sm text-gray-600">{{ $invoice->client->full_name }}</p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:{{ $invoice->client->email}}" class="hover:underline hover:text-blue-600">
                        {{ $invoice->client->email ?? 'N/A' }}
                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:{{ $invoice->client->country_code }}{{ $invoice->client->phone }}" class="hover:underline hover:text-blue-600">
                        {{ $invoice->client->country_code ?? ''}}{{ $invoice->client->phone ?? 'N/A' }}
                    </a>
                </p>
            </div>
            <div class="text-right max-w-xs">
                <h2 class="text-xl font-bold text-gray-800">{{ $invoice->agent->branch->company->name }}</h2>
                <p class="text-sm text-gray-600">{{ $invoice->agent->branch->company->address }}</p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:{{ $invoice->agent->branch->company->email }}" class="hover:underline hover:text-blue-600">
                        {{ $invoice->agent->branch->company->email }}
                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:{{ $invoice->agent->branch->company->phone }}" class="hover:underline hover:text-blue-600">
                        {{ $invoice->agent->branch->company->phone }}
                    </a>
                </p>
            </div>
        </div>

        @if (in_array($invoice->payment_type, ['full', 'credit', 'cash'], true))
        <h3 class="text-lg font-bold text-gray-800 mb-4">{{ ucfirst($invoice->payment_type )}} Payment ({{ $invoice->currency }})</h3>
        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">Item Description</th>
                    <th class="px-4 py-2 border">Quantity</th>
                    <th class="px-4 py-2 border">Price</th>
                    <th class="px-4 py-2 border">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoiceDetails as $detail)
                <tr class="text-sm text-gray-700">
                    <td class="px-4 py-2 border">
                        @if ($detail->task->type === 'hotel')
                            @php
                                $roomDetails = json_decode($detail->task->hotelDetails->room_details, true);
                                $passengerCount = count($roomDetails['passengers'] ?? []);
                            @endphp
                        <p>
                            @if(!empty($detail->task->reference))
                                Reference: {{ $detail->task->reference }}
                            @endif
                            <br>Client Name: {{ $detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}
                            <br>Passenger Name: {{ $detail->task->passenger_name ?? 'N/A' }}
                            <br>Hotel Name: {{ $detail->task->hotelDetails->hotel->name ?? 'N/A' }}
                            <br>Check In: {{ $detail->task->hotelDetails->check_in ?? 'N/A' }}
                            <br>Check Out: {{ $detail->task->hotelDetails->check_out ?? 'N/A' }}
                            <br>Number of Pax: {{ $passengerCount ?? $detail->task->number_of_pax ?? 'N/A' }}
                            <br>Room Category: {{ $detail->task->hotelDetails->room_type ?? $detail->task->hotelDetails->room_category ?? 'N/A' }}
                        </p>
                        @elseif ($detail->task->type === 'flight')
                        <p>
                            @if(!empty($detail->task->reference))
                                Reference: {{ $detail->task->reference }}<br>
                            @endif
                            @if(!empty($detail->task->gds_reference))
                                GDS Reference: {{ $detail->task->gds_reference }}<br>
                            @endif
                            Client Name: {{ $detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}<br>
                            Passenger Name: {{ $detail->task->passenger_name ?? 'N/A' }}
                            <br>Route:
                            {{ $detail->task->flightDetails->countryFrom->name ?? '' }}
                            ({{ $detail->task->flightDetails->airport_from ?? '' }})
                            →
                            {{ $detail->task->flightDetails->countryTo->name ?? '' }}
                            ({{ $detail->task->flightDetails->airport_to ?? '' }})
                            <br>Class of Travel: {{ ucfirst($detail->task->flightDetails->class_type ?? 'N/A') }}
                        </p>
                        @elseif ($detail->task->type === 'visa')
                        <p>
                            @if(!empty($detail->task->reference))
                                Reference: {{ $detail->task->reference }}<br>
                            @endif
                            Client Name: {{ $detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}<br>
                            Passenger Name: {{ $detail->task->passenger_name ?? 'N/A' }}
                            <br>Visa Type: {{ $detail->task->visaDetails->visa_type ?? 'N/A' }}
                            <br>Application #: {{ $detail->task->visaDetails->application_number ?? 'N/A' }}
                            <br>Expiry Date: {{ !empty($visa?->expiry_date) ? \Carbon\Carbon::parse($visa->expiry_date)->format('d M Y') : 'N/A' }}
                            <br>Entries: {{ $detail->task->visaDetails->number_of_entries ?? 'N/A' }}
                            <br>Stay Duration: {{ $detail->task->visaDetails->stay_duration ?? 'N/A' }}
                            <br>Issuing Country: {{ $detail->task->visaDetails->issuing_country ?? 'N/A' }}
                        </p>
                        @elseif ($detail->task->type === 'insurance')
                        <p>
                            @if(!empty($detail->task->reference))
                                Reference: {{ $detail->task->reference }}<br>
                            @endif
                            Client Name: {{ $detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}<br>
                            Passenger Name: {{ $detail->task->passenger_name ?? 'N/A' }}
                            <br>Insurance Type: {{ $detail->task->insuranceDetails->insurance_type ?? 'N/A' }}
                            <br>Destination: {{ $detail->task->insuranceDetails->destination ?? 'N/A' }}
                            <br>Plan Type: {{ $detail->task->insuranceDetails->plan_type ?? 'N/A' }}
                            <br>Duration: {{ $detail->task->insuranceDetails->duration ?? 'N/A' }}
                            <br>Package: {{ $detail->task->insuranceDetails->package ?? 'N/A' }}
                            <br>Document Reference: {{ $detail->task->insuranceDetails->document_reference ?? 'N/A' }}
                            <br>Paid Leaves: {{ $detail->task->insuranceDetails->paid_leaves ?? 'N/A' }}
                        </p>
                        @endif
                    </td>
                    <td class="px-4 py-2 border">{{ $detail->quantity ?? 1 }}</td>
                    <td class="px-4 py-2 border">{{ number_format($detail->task_price ?? 0, 2) }}</td>
                    <td class="px-4 py-2 border">
                        {{ number_format(($detail->quantity ?? 1) * ($detail->task_price ?? 0), 2, '.', ',') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Partial Payment of Different Gateway -->
        @if ($invoice->payment_type === 'partial')
        <h3 class="text-lg font-bold text-gray-800 mb-4">Partial Payment ({{ $invoice->currency }})</h3>

        <div class="mb-4">
            <h4 class="text-lg font-bold text-gray-800">Task Descriptions</h4>
            <ul class="list-disc pl-6">
                @foreach ($invoiceDetails as $detail)
                <li class="text-sm text-gray-700">
                    <strong>{{ $detail->task_description ?? 'N/A' }}</strong>:
                    {{ $detail->quantity ?? 0 }}
                </li>
                @endforeach
            </ul>
        </div>

        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">Payment Gateway</th>
                    <th class="px-4 py-2 border">Link</th>
                    <th class="px-4 py-2 border">Expiry Date</th>
                    <th class="px-4 py-2 border">Status</th>
                    <th class="px-4 py-2 border">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php
                $count = 1;
                @endphp
                @foreach ($invoicePartials as $partial)
                @php
                $creditBalance = \App\Models\Credit::getTotalCreditsByClient($partial->client->id);
                @endphp

                <tr x-data="{ open: false }" class="text-sm text-gray-700 text-center">
                    <td class="px-4 py-2 border">{{ $partial->payment_gateway ?? 'N/A'}}</td>
                    <td class="px-4 py-2 border">
                        <a href="{{ route('invoice.split', ['invoiceNumber' => $partial->invoice_number, 'clientId' => $partial->client_id, 'partialId' => $partial->id]) }}"
                            class="text-blue-500 underline" target="_blank">
                            View Details
                        </a>
                    </td>
                    <td class="px-4 py-2 border">
                        {{ \Carbon\Carbon::parse($partial->expiry_date)->format('d M, Y') ?? 'N/A' }}
                    </td>
                    <td class="px-4 py-2 border"> {{$partial->status}}</td>
                    <td class="px-4 py-2 border">
                        @if ($partial->status !== 'paid')
                        {{ number_format($partial->final_amount ?? $partial->amount, 2) }}
                        @else
                        {{ number_format($partial->amount, 2) }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if ($invoice->payment_type === 'split')
        <!-- Split Payment Table -->
        <h3 class="text-lg font-bold text-gray-800 mb-4">Split Payment ({{ $invoice->currency }})</h3>

        <div class="mb-4">
            <h4 class="text-lg font-bold text-gray-800">Task Descriptions</h4>
            <ul class="list-disc pl-6">
                @foreach ($invoiceDetails as $detail)
                <li class="text-sm text-gray-700">
                    <strong>{{ $detail->task_description ?? 'N/A' }}</strong>:
                    {{ $detail->quantity ?? 0 }}
                </li>
                @endforeach
            </ul>
        </div>

        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">Split #</th>
                    <th class="px-4 py-2 border">Link</th>
                    <th class="px-4 py-2 border">Client</th>
                    <th class="px-4 py-2 border">Expiry Date</th>
                    <th class="px-4 py-2 border">Payment Gateway</th>
                    <th class="px-4 py-2 border">Status</th>
                    <th class="px-4 py-2 border">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php
                $count = 1;
                @endphp
                @foreach ($invoicePartials as $partial)
                @php
                $creditBalance = \App\Models\Credit::getTotalCreditsByClient($partial->client->id);
                @endphp

                <tr x-data="{ open: false }" class="text-sm text-gray-700">
                    <td class="px-4 py-2 border">
                        {{ $count }}
                    </td>
                    <td class="px-4 py-2 border">
                        <a href="{{ route('invoice.split', ['invoiceNumber' => $partial->invoice_number, 'clientId' => $partial->client_id, 'partialId' => $partial->id]) }}"
                            class="text-blue-500 underline" target="_blank">
                            View Details
                        </a>
                    </td>

                    <td class="px-4 py-2 border">
                        {{ $partial->client->full_name }}

                        <!-- @if ($creditBalance > 0 && $partial->status === 'unpaid')
                        <br>Credit Balance: {{ number_format($creditBalance, 2) }} |
                        <button @click="open = true" type="button" class="text-blue-600 underline text">
                            Use now to pay this payment split?
                        </button>
                        @endif -->

                        <!-- Modal -->
                        <div x-show="open" x-cloak
                            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                            <div @click.away="open = false"
                                class="bg-white p-6 rounded shadow max-w-md w-full">
                                <h2 class="text-lg font-semibold mb-4">Confirm Credit Use</h2>
                                <p class="text-sm mb-6">Use credit balance to pay this invoice split?</p>
                                <div class="flex justify-end space-x-3">
                                    <button @click="open = false"
                                        class="px-4 py-2 text-sm bg-gray-300 rounded">No</button>
                                    @php
                                    $checkBalance =
                                    $partial->amount >= $creditBalance
                                    ? $creditBalance
                                    : $partial->amount;
                                    @endphp

                                    <form method="POST"
                                        action="{{ route('credits.useCreditNow', [
                                                    'invoice' => $partial->invoice_id,
                                                    'invoicePartial' => $partial->id,
                                                    'balanceCredit' => $checkBalance,
                                                ]) }}">
                                        @csrf
                                        <button type="submit"
                                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded">
                                            Yes
                                        </button>
                                    </form>

                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2 border">
                        {{ \Carbon\Carbon::parse($partial->expiry_date)->format('d M, Y') ?? 'N/A' }}
                    </td>
                    <td class="px-4 py-2 border">{{ $partial->payment_gateway }}</td>
                    <td class="px-4 py-2 border">{{ $partial->status }}</td>
                    <td class="px-4 py-2 border">
                        @if ($partial->status !== 'paid')
                        {{ number_format($partial->final_amount ?? $partial->amount, 2) }}
                        @else
                        {{ number_format($partial->amount, 2) }}
                        @endif
                    </td>
                </tr>
                @php
                $count++;
                @endphp
                @endforeach
            </tbody>



        </table>
        @endif

        <!-- Totals Section -->
        <div class="flex justify-end mb-8">
            <div class="w-1/3 text-sm">
                @if ($invoice->refundDetails->isNotEmpty() && $invoice->refundDetails->first()->invoice)
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span>
                            Original Invoice
                            <span class="text-xs text-gray-500">
                                ({{ $invoice->refundDetails->first()->invoice->invoice_number }})
                            </span>
                        </span>
                        <span>{{ number_format($invoice->refundDetails->first()->invoice->amount, 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Subtotal:</span>
                    <span>{{ number_format($invoice->sub_amount, 2) }}</span>
                </div>
                @if ($checkUtilizeCredit && $checkUtilizeCredit->count())
                @foreach ($checkUtilizeCredit as $credit)
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Client's Credit ({{ $credit->created_at->format('d M Y') }}):</span>

                    <span>{{ number_format($credit->amount, 2) }}</span>
                </div>
                @endforeach
                @endif

                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Tax ({{ $invoice->tax_rate }}%):</span>
                    <span>{{ number_format($invoice->tax, 2) }}</span>
                </div>

                @if ($invoice->status === 'paid' || $invoice->payment_type === 'split')
                @php
                $paidServiceCharge = $invoice->invoicePartials->sum('service_charge');
                $paidTotalAmount = $invoice->invoicePartials->sum('amount');
                @endphp
                @if ($paidServiceCharge > 0)
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Service Charge:</span>
                    <span>{{ number_format($paidServiceCharge, 2) }}</span>
                </div>
                @endif
                @else
                @if(isset($totalGatewayFee['paid_by']) || $totalGatewayFee['paid_by'] !== 'Company')
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Service Charge @if(isset($totalGatewayFee['charge_type']) && $totalGatewayFee['charge_type'] === 'Percent') (%): @else: @endif</span>
                    <span>{{ number_format($totalGatewayFee['fee'], 2) }}</span>
                </div>
                @endif
                @endif
                <div class="flex justify-between py-2 font-bold text-gray-800">
                    <span>Total:</span>
                    <span>
                        {{ number_format( (isset($totalGatewayFee['finalAmount']) ? $totalGatewayFee['finalAmount'] : $invoice->sub_amount) - abs($checkUtilizeCredit->sum('amount')), 2) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="mb-8 inline-flex gap-2">
            @if ($invoice->status === 'unpaid' || $invoice->status === 'partial' || $invoice->payment_type === 'partial')
            @if (auth()->check())

            <form id="whatsappForm" action="{{ route('resayil.share-invoice-link') }}" method="POST" onsubmit="showSpinner()">
                @csrf
                <!-- Hidden Inputs -->
                <input type="hidden" name="client_id" id="clientid" value="{{ $invoice->client->id ?? '' }}">
                <input type="hidden" name="invoiceNumber" value="{{ $invoice->invoice_number }}">

                <button id="submitButton" type="submit"
                    class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                    <span id="buttonText">Send Invoice To Client</span>
                    <span id="spinner" class="hidden ml-2">
                        <svg class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 0-8-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"></path>
                        </svg>
                    </span>
                </button>
            </form>
            @endif
            <form id="paymentForm"
                action="{{ route('payment.create', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                method="POST">
                @csrf

                <input type="hidden" id="totalAmountInput" name="total_amount"
                    value="{{ isset($totalGatewayFee['finalAmount']) ? $totalGatewayFee['finalAmount'] : $invoice->sub_amount) - abs($checkUtilizeCredit->sum('amount') }}">
                <input type="hidden" name="client_email" value="{{ $invoice->client->email }}">
                <input type="hidden" name="client_name" value="{{ $invoice->client->full_name }}">
                <input type="hidden" name="client_phone" value="{{ $invoice->client->phone }}">
                <input type="hidden" name="payment_gateway" value="{{ $invoice->invoicePartials->first()->payment_gateway }}">
                <input type="hidden" name="payment_method" value="{{ $invoice->invoicePartials->first()->payment_method }}">

                @if (!in_array($invoice->payment_type, ['split', 'partial'], true))
                    @if ($canGenerateLink)
                        <div class="flex items-center gap-2">
                            <button type="submit" id="payNowBtn"
                                class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                                Pay Now
                            </button>
                        </div>
                    @else
                        <div class="p-2 rounded-lg border border-gray-300 text-gray-700 flex items-center gap-2 text-xs sm:text-sm">
                            This invoice is {{ strtolower($invoice->invoicePartials->first()->payment_gateway) }} payment.
                            Please contact your agent for assistance.
                        </div>
                    @endif
                @endif

                <div id="loadingSpinner" class="hidden mt-2">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Processing...
                </div>
            </form>

            @if (auth()->user() &&
            (auth()->user()->role === 'admin' || auth()->user()->role === 'company' || auth()->user()->role === 'agent'))
            <div class="flex gap-2 mt-2" id="invoice-link">
                <p>
                    {{ route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}
                </p>
                <button
                    onclick="copyToClipboard('{{ route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}')">
                    <img src="{{ asset('images/svg/copy.svg') }}" alt="Copy Link" class="w-4 h-4">
                </button>

            </div>
            @endif
            @else
            <div class="flex items-center gap-2">
                <p><span class="text-green-600 font-bold">PAID</span></p>
            </div>

            @endif
        </div>
        <!-- Signatdiure Section -->
        <div class="flex justify-between items-center">
            <div class="text-sm">
                <p class="text-gray-600">If you have any questions about this invoice, please contact:</p>
                <p class="text-gray-600">{{ $invoice->agent->branch->company->name }},
                    {{ $invoice->agent->branch->company->phone }}, {{ $invoice->agent->branch->company->email }}
                </p>
            </div>
            <div class="text-right">
                <div class="flex justify-end mb-4">
                    <button
                        onclick="window.open('{{ route('invoice.show-arabic', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}', '_blank')"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        عرض الفاتورة بالعربية
                    </button>
                </div>
                <p class="font-bold text-gray-800">Thank you for your business!</p>
            </div>
        </div>
    </div>
    @if ($invoice->is_client_credit == 1)
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg mt-6 text-center">
        <p class="text-lg font-semibold text-green-500">
            This invoice has been applied with the client credit.
        </p>
    </div>
    @endif
    @if ($invoice->status === 'paid' || $invoice->status === 'partial')
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg mt-6">
        <div class="invoice">
            <div class="payment-status bg-green-100 p-6 rounded-lg mt-4">
                <h3 class="text-xl font-semibold text-green-700 mb-2">Payment Receipt</h3>
            </div>

            <table class="min-w-full mb-8 border border-gray-200">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                        <th class="px-4 py-2 border">Receipt #</th>
                        <th class="px-4 py-2 border">Reference</th>
                        <th class="px-4 py-2 border">Payment Date</th>
                        <th class="px-4 py-2 border">Payment Gateway</th>
                        <th class="px-4 py-2 border">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paidPartials as $partial)
                    <tr class="text-sm text-gray-700">
                        <td class="px-4 py-2 border">
                            @if(optional($partial->payment)->voucher_number)
                            <a href="{{ route('payment.link.show', ['companyId' => $companyId, 'voucherNumber' => $partial->payment->voucher_number]) }}"
                                class="text-blue-500 underline" target="_blank">{{ $partial->payment->voucher_number }}
                            </a>
                            @else
                            <a href="{{ route('clients.credits', $partial->client_id) }}" class="text-blue-500 underline" target="_blank">Credit</a>
                            @endif
                        </td>
                        @php
                        $paymentReferenceCredit = \App\Models\Credit::getTotalUtilizeCreditsByClientPartial($partial->client_id, $partial->id);
                        @endphp
                        @if ($paymentReferenceCredit)
                        <td class="px-4 py-2 border">Client Credit by {{ $partial->client->full_name }}
                            ({{ $paymentReferenceCredit }})
                        </td>
                        @else
                        <td class="px-4 py-2 border">{{ $partial->payment->payment_reference ?? 'N/A' }}</td>
                        @endif
                        <td class="px-4 py-2 border">
                            {{ $partial->payment ? \Carbon\Carbon::parse($partial->payment->payment_date)->format('d M, Y H:i') : \Carbon\Carbon::parse($partial->updated_at)->format('d M, Y H:i') }}
                        </td>
                        @if ($paymentReferenceCredit)
                        <td class="px-4 py-2 border">Client Credit</td>
                        @else
                        <td class="px-4 py-2 border">{{ $partial->payment_gateway }}</td>
                        @endif
                        <td class="px-4 py-2 border">
                            {{ number_format($partial->amount ?? 0, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="flex justify-end mb-8">
                <div class="w-1/3 text-sm">
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span>Balance:</span>
                        <span id="balance"></span>
                    </div>
                </div>
            </div>

            <div class="thank-you mt-6 bg-gray-100 p-6 rounded-lg">
                <h4 class="text-xl font-semibold text-gray-800 mb-2">Thank You for Your Payment!</h4>
                <p class="text-lg text-gray-600">We appreciate your business! A confirmation email has been
                    sent to
                    your address.</p>
            </div>
        </div>



    </div>
    @endif


    <script>
        let invoice = @json($invoice);
        let invoicePartials = @json($invoicePartials);

        console.log('invoice', invoice);
        console.log('invoicePartials', invoicePartials);

        // Calculate the total paid amount from invoicePartials
        let totalPaidAmount = invoicePartials.filter(partial => partial.status === 'paid')
            .reduce((sum, partial) => sum + parseFloat(partial.amount), 0);

        let totalPaidServiceCharge = invoicePartials.filter(partial => partial.status === 'paid')
            .reduce((sum, partial) => sum + parseFloat(partial.service_charge), 0);

        // Calculate balance
        let balance = invoice.amount - totalPaidAmount + totalPaidServiceCharge;

        let balanceElement = document.getElementById('balance');
        if (balanceElement) {
            balanceElement.textContent = balance.toFixed(2);
        }

        const totalAmountDisplay = document.getElementById("totalAmountDisplay");
        const paymentForm = document.getElementById('paymentForm');
        const totalAmountInput = document.getElementById("totalAmountInput");
        const checkboxes = document.querySelectorAll(".partial-checkbox");

        if (invoice.payment_type === 'full') {

            console.log('full');
            // Ensure there’s only one hidden input for the 'full' payment type
            addHiddenInput("invoice_partial_id", invoicePartials[0]?.id, paymentForm);
        } else if (invoice.payment_type === 'partial' || invoice.payment_type === 'split') {

            console.log('partials');


            checkboxes.forEach((checkbox) => {
                const partialId = checkbox.value;

                if (checkbox.disabled) {
                    console.log('disable');
                    checkbox.checked = false; // Disabled checkboxes should remain checked
                } else {
                    console.log('cheked');
                    checkbox.checked = true; // Set all non-disabled checkboxes to checked by default
                    addHiddenInput("invoice_partial_id", partialId, paymentForm); // Add hidden input
                }

                ///addHiddenInput("invoice_partial_id", partialId, paymentForm); // Add corresponding hidden input

                calculateTotal();

                checkbox.addEventListener("change", (event) => {
                    const partialId = event.target.value;
                    console.log(partialId);
                    if (event.target.checked) {
                        // Add hidden input if checkbox is checked
                        addHiddenInput("invoice_partial_id", partialId, paymentForm);
                    } else {
                        // Remove hidden input if checkbox is unchecked
                        removeHiddenInput("invoice_partial_id", partialId, paymentForm);
                    }

                    calculateTotal();
                });
            });

        }


        function addHiddenInput(name, value, form) {
            // Check if the hidden input already exists
            console.log(name);
            let existingInput = form.querySelector(`input[name="${name}"][value="${value}"]`);
            if (!existingInput) {
                const hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = name;
                hiddenInput.value = value;
                form.appendChild(hiddenInput);
            }
        }


        // Utility to remove hidden inputs
        function removeHiddenInput(name, value, form) {
            let existingInput = form.querySelector(`input[name="${name}"][value="${value}"]`);
            if (existingInput) {
                existingInput.remove();
            }
        }

        function calculateTotal() {
            let totalForSubmission = 0;
            let totalForDisplay = 0;

            checkboxes.forEach((checkbox) => {
                if (checkbox.checked && !checkbox.disabled) {
                    totalForSubmission += parseFloat(checkbox.dataset.amount || 0);
                    totalForDisplay += parseFloat(checkbox.dataset.finalAmount || 0);
                }
            });

            totalAmountInput.value = totalForSubmission.toFixed(2);

            if (totalAmountDisplay) {
                totalAmountDisplay.textContent = totalForDisplay.toFixed(2);
            }

            console.log("Amount for submission (backend):", totalAmountInput.value);
            console.log("Amount for display (frontend):", totalForDisplay.toFixed(2));
        }

        $(document).ready(function() {
            let selectedTotal = 0;
            const selectedItems = [];

            $('.item-select').change(function() {
                const itemId = $(this).data('id');
                const itemTotal = parseFloat($(this).data('total'));

                if (this.checked) {
                    selectedTotal += itemTotal;
                    selectedItems.push(itemId);
                } else {
                    selectedTotal -= itemTotal;
                    const index = selectedItems.indexOf(itemId);
                    if (index > -1) selectedItems.splice(index, 1);
                }

                $('#selectedTotal').text(selectedTotal.toFixed(2));
                $('#selectedItems').val(selectedItems.join(','));
                $('#totalAmount').val(selectedTotal.toFixed(2));
            });
        });

        function showSpinner() {
            document.getElementById("submitButton").disabled = true;
            document.getElementById("buttonText").textContent = "Sending...";
            document.getElementById("spinner").classList.remove("hidden");
        }
    </script>

</body>

</html>