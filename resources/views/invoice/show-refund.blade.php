<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
        body {
            font-family: 'Nunito', sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        h1, h2, h3 {
            color: #1a1a1a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 0.75rem;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            text-transform: uppercase;
            font-size: 0.85rem;
            color: #4b5563;
        }
        .totals td {
            font-weight: bold;
        }
        .highlight {
            background: #fff9c4;
        }
    </style>
</head>

<body class="overflow-y-auto font-nunito antialiased bg-gray-100">
    @if ($invoice->status === 'paid')
    <div
        class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg">
        <div class="flex items-center justify-between text-white">
            <p class="text-3xl">PAID</p>
            <h5 class="text-2xl ltr:mr-auto rtl:mr-auto"></h5>
        </div>
    </div>
    @endif

    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">REFUND INVOICE</h1>
                <p class="text-sm text-gray-600">{{ $invoice->invoice_number }}</p>
                <p class="text-sm text-gray-600">Date: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</p>
                @php
                    $refund = \App\Models\Refund::where('refund_invoice_id', $invoice->id)->first();
                @endphp
                <!-- <p class="text-gray-600">Generated from Refund: {{ $refund->refund_number }}</p> -->
            </div>
            <div>
                <img class="w-auto h-[85px] object-contain" src="{{ $invoice->agent->branch->company->logo ? Storage::url($invoice->agent->branch->company->logo) : asset('images/UserPic.svg') }}" alt="Company logo" />
            </div>
        </div>

        <div class="flex justify-between items-center mb-8">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Billed To</h3>
                <p class="text-sm text-gray-600">{{ $invoice->client->full_name }}</p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:{{ $invoice->client->email }}" class="hover:underline hover:text-blue-600">
                        {{ $invoice->client->email }}
                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:{{ ($invoice->client->country_code ?? '+965') }} {{ $invoice->client->phone ?? 'N/A' }}" class="hover:underline hover:text-blue-600">
                        {{ ($invoice->client->country_code ?? '+965') }} {{ $invoice->client->phone ?? 'N/A' }}
                    </a>
                </p>
            </div>
            <div class="text-right">
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

        @php
            // Make sure relationships are loaded
            $refund->loadMissing('refundDetails.task.originalTask', 'originalInvoice.invoiceDetails');

            // Collect the source task IDs (originalTask if refund status, otherwise the task itself)
            $refundedTaskIds = $refund->refundDetails
                ->map(function ($detail) {
                    $task = $detail->task;
                    $isRefundStatus = strtolower($task->status ?? '') === 'refund';
                    return $isRefundStatus ? ($task->originalTask?->id) : $task->id;
                })
                ->filter()
                ->unique()
                ->toArray();

            // Calculate the total task price for those tasks from the original invoice
            $refundedTaskTotal = $refund->originalInvoice
                ? $refund->originalInvoice->invoiceDetails
                    ->whereIn('task_id', $refundedTaskIds)
                    ->sum('task_price')
                : 0;
        @endphp
        <div class="mb-6">
            <h2 class="text-xl font-semibold">Refund Summary</h2>
            <table class="min-w-full border border-gray-200 text-sm">
                <thead class="bg-gray-100 text-gray-700 uppercase">
                    <tr>
                        <th class="p-2 border">Original Invoice</th>
                        <th class="p-2 border">
                            <span class="inline-flex items-center gap-1">
                                Original Amount
                                <span data-tooltip="The total amount of the original invoice" class="cursor-help text-gray-400 font-normal normal-case">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="p-2 border">
                            <span class="inline-flex items-center gap-1">
                                Original Refund
                                <span data-tooltip="The selling price of the refunded tasks from the original invoice" class="cursor-help text-gray-400 font-normal normal-case">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th class="p-2 border">
                            <span class="inline-flex items-center gap-1">
                                Refund Charges
                                <span data-tooltip="The fee charged to process the refund for the selected tasks" class="cursor-help text-gray-400 font-normal normal-case">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                    </svg>
                                </span>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="p-2 border">{{ $refund->originalInvoice?->invoice_number ?? 'N/A' }}</td>
                        <td class="p-2 border">{{ number_format($refund->originalInvoice?->amount ?? 0, 3) }}</td>
                        <td class="p-2 border">{{ number_format($refundedTaskTotal, 3) }}</td>
                        <td class="p-2 border font-bold text-green-700">{{ number_format($refund->total_nett_refund ?? 0, 3) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Task Refund Details</h2>
            @foreach ($refund->refundDetails as $detail)
                @php
                    $task = $detail->task;
                    $isRefundStatus = strtolower($task->status ?? '') === 'refund';
                    $sourceTaskId = $isRefundStatus ? ($task->originalTask?->id ?? $task->id) : $task->id;
                    $originalDetail = $detail->refund->originalInvoice ? $detail->refund->originalInvoice->invoiceDetails->firstWhere('task_id', $sourceTaskId) : null;
                @endphp
                <div class="mb-3 p-5 border border-gray-200 rounded-lg shadow-sm bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex justify-between items-center">
                        <h3 class="font-semibold text-base text-gray-800">Reference: {{ $task->reference ?? 'N/A' }}</h3>
                        <span class="text-sm px-3 py-1 rounded-full bg-blue-100 text-blue-700">{{ ucfirst($task->type ?? 'N/A') }}</span>
                    </div>
                    <div class="grid md:grid-cols-2 gap-3 text-sm text-gray-700 leading-relaxed">
                        @if ($task->type === 'hotel')
                            @php
                                $roomDetails = json_decode($task->hotelDetails->room_details ?? '{}', true);
                                $passengerCount = count($roomDetails['passengers'] ?? []);
                            @endphp
                            <div>
                                <p><strong>Client:</strong> {{ $task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}</p>
                                <p><strong>Passenger:</strong> {{ $task->passenger_name ?? 'N/A' }}</p>
                                <p><strong>Hotel Name:</strong> {{ $task->hotelDetails->hotel->name ?? 'N/A' }}</p>
                                <p><strong>Room Category:</strong> {{ $task->hotelDetails->room_type ?? $task->hotelDetails->room_category ?? 'N/A' }}</p>
                                <p><strong>Number of Pax:</strong> {{ $passengerCount ?? $task->number_of_pax ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p><strong>Check In:</strong> {{ $task->hotelDetails->check_in ?? 'N/A' }}</p>
                                <p><strong>Check Out:</strong> {{ $task->hotelDetails->check_out ?? 'N/A' }}</p>
                                <p><strong>Original Task Price:</strong> {{ number_format($originalDetail->task_price ?? 0, 3) }}</p>
                                <p><strong>Refund Charge:</strong> {{ number_format($detail->total_refund_to_client ?? 0, 3) }}</p>
                            </div>

                        @elseif ($task->type === 'flight')
                            <div>
                                <p><strong>Client:</strong> {{ $task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}</p>
                                <p><strong>Passenger:</strong> {{ $task->passenger_name ?? 'N/A' }}</p>
                                <p><strong>GDS Ref:</strong> {{ $task->gds_reference ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p><strong>Route:</strong>
                                    {{ $task->flightDetails->countryFrom->name ?? '' }}
                                    ({{ $task->flightDetails->airport_from ?? '' }})
                                    →
                                    {{ $task->flightDetails->countryTo->name ?? '' }}
                                    ({{ $task->flightDetails->airport_to ?? '' }})
                                </p>
                                <p><strong>Original Task Price:</strong> {{ number_format($originalDetail->task_price ?? 0, 3) }}</p>
                                <p><strong>Refund Charge:</strong> {{ number_format($detail->total_refund_to_client ?? 0, 3) }}</p>
                            </div>

                        @elseif ($task->type === 'visa')
                            <div>
                                <p><strong>Client:</strong> {{ $task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}</p>
                                <p><strong>Passenger:</strong> {{ $task->passenger_name ?? 'N/A' }}</p>
                                <p><strong>Visa Type:</strong> {{ $task->visaDetails->visa_type ?? 'N/A' }}</p>
                                <p><strong>Application #:</strong> {{ $task->visaDetails->application_number ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p><strong>Entries:</strong> {{ $task->visaDetails->number_of_entries ?? 'N/A' }}</p>
                                <p><strong>Issuing Country:</strong> {{ $task->visaDetails->issuing_country ?? 'N/A' }}</p>
                                <p><strong>Stay Duration:</strong> {{ $task->visaDetails->stay_duration ?? 'N/A' }}</p>
                                <p><strong>Original Task Price:</strong> {{ number_format($originalDetail->task_price ?? 0, 3) }}</p>
                                <p><strong>Refund Charge:</strong> {{ number_format($detail->total_refund_to_client ?? 0, 3) }}</p>
                            </div>

                        @elseif ($task->type === 'insurance')
                            <div>
                                <p><strong>Client:</strong> {{ $task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}</p>
                                <p><strong>Passenger:</strong> {{ $task->passenger_name ?? 'N/A' }}</p>
                                <p><strong>Insurance Type:</strong> {{ $task->insuranceDetails->insurance_type ?? 'N/A' }}</p>
                                <p><strong>Destination:</strong> {{ $task->insuranceDetails->destination ?? 'N/A' }}</p>
                                <p><strong>Plan Type:</strong> {{ $task->insuranceDetails->plan_type ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p><strong>Duration:</strong> {{ $task->insuranceDetails->duration ?? 'N/A' }}</p>
                                <p><strong>Package:</strong> {{ $task->insuranceDetails->package ?? 'N/A' }}</p>
                                <p><strong>Document Ref:</strong> {{ $task->insuranceDetails->document_reference ?? 'N/A' }}</p>
                                <p><strong>Original Task Price:</strong> {{ number_format($originalDetail->task_price ?? 0, 3) }}</p>
                                <p><strong>Refund Charge:</strong> {{ number_format($detail->total_refund_to_client ?? 0, 3) }}</p>
                            </div>

                        @else
                            <div class="col-span-2">
                                <p><strong>Client:</strong> {{ $task->client_name ?? $invoice->client->full_name }}</p>
                                <p><strong>Passenger:</strong> {{ $task->passenger_name ?? 'N/A' }}</p>
                                <p><strong>Original Task Price:</strong> {{ number_format($originalDetail->task_price ?? 0, 3) }}</p>
                                <p><strong>Refund Charge:</strong> {{ number_format($detail->total_refund_to_client ?? 0, 3) }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @php
            // find all refunded tasks (use originalTask if refund status, otherwise the task itself)
            $refundedTaskIds = $refund->refundDetails
                ->map(function ($d) {
                    $task = $d->task;
                    $isRefundStatus = strtolower($task->status ?? '') === 'refund';
                    return $isRefundStatus ? ($task->originalTask?->id ?? $d->task_id) : $d->task_id;
                })
                ->filter()
                ->toArray();

            // find unrefunded ones
            $unrefundedTasks = $refund->originalInvoice ? $refund->originalInvoice->invoiceDetails()->whereNotIn('task_id', $refundedTaskIds)->get() : collect();
        @endphp
        @if ($unrefundedTasks->isNotEmpty())
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Unrefunded Items from Original Invoice</h2>
                <table class="min-w-full mb-8 border border-gray-200">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                            <th class="px-4 py-2 border">Item Description</th>
                            <th class="px-4 py-2 border text-center">Quantity</th>
                            <th class="px-4 py-2 border text-center">Price (KWD)</th>
                            <th class="px-4 py-2 border text-center">Total (KWD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($unrefundedTasks as $detail)
                            @php $task = $detail->task; @endphp
                            <tr class="text-sm text-gray-700">
                                <td class="px-4 py-2 border align-top">
                                        Reference: {{ $task->reference }} <br>
                                        Task Type: {{ ucfirst($task->type ?? 'N/A') }} <br>

                                    @if ($task->type === 'flight')
                                        @if(!empty($task->gds_reference)) GDS Ref: {{ $task->gds_reference }} <br> @endif
                                        Passenger: {{ $task->passenger_name ?? 'N/A' }} <br>
                                        Route:
                                        {{ $task->flightDetails->countryFrom->name ?? '' }}
                                        ({{ $task->flightDetails->airport_from ?? '' }}) →
                                        {{ $task->flightDetails->countryTo->name ?? '' }}
                                        ({{ $task->flightDetails->airport_to ?? '' }})

                                    @elseif ($task->type === 'hotel')
                                        Hotel: {{ $task->hotelDetails->hotel->name ?? 'N/A' }} <br>
                                        Check-In: {{ $task->hotelDetails->check_in ?? 'N/A' }} <br>
                                        Check-Out: {{ $task->hotelDetails->check_out ?? 'N/A' }} <br>
                                        Room: {{ $task->hotelDetails->room_type ?? 'N/A' }}

                                    @elseif ($task->type === 'visa')
                                        Visa Type: {{ $task->visaDetails->visa_type ?? 'N/A' }} <br>
                                        Passenger: {{ $task->passenger_name ?? 'N/A' }} <br>
                                        Country: {{ $task->visaDetails->issuing_country ?? 'N/A' }}

                                    @elseif ($task->type === 'insurance')
                                        Insurance: {{ $task->insuranceDetails->insurance_type ?? 'N/A' }} <br>
                                        Plan: {{ $task->insuranceDetails->plan_type ?? 'N/A' }} <br>
                                        Destination: {{ $task->insuranceDetails->destination ?? 'N/A' }}

                                    @else
                                        {{ $task->reference ?? 'N/A' }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 border text-center">1</td>
                                <td class="px-4 py-2 border text-center">{{ number_format($detail->task_price ?? 0, 3) }}</td>
                                <td class="px-4 py-2 border text-center">
                                    {{ number_format(($detail->quantity ?? 1) * ($detail->task_price ?? 0), 3) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- @if ($refund->originalInvoice?->invoicePartials->isNotEmpty())
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-2">Payments History from Original Invoice</h2>
            <table>
                <thead>
                    <tr>
                        <th>Payment Date</th>
                        <th>Gateway</th>
                        <th>Status</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($refund->originalInvoice->invoicePartials as $partial)
                        <tr class="text-sm">
                            <td>
                                {{ $partial->payment ? \Carbon\Carbon::parse($partial->payment->payment_date)->format('d M, Y H:i') : \Carbon\Carbon::parse($partial->updated_at)->format('d M, Y H:i') }}
                            </td>
                            <td>{{ $partial->payment_gateway }}</td>
                            <td>{{ ucfirst($partial->status) }}</td>
                            <td>{{ number_format($partial->amount, 3) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif --}}

        @php
            $originalInvoice = $refund->originalInvoice;
            $totalPaidOnOriginal = $originalInvoice ? $originalInvoice->invoicePartials->where('status', 'paid')->sum('amount') : 0;

            $unrefundedTotal = $unrefundedTasks->sum('task_price');

            // Payment balance = what they paid - what they're keeping
            // Positive = overpayment (credit to client)
            // Negative = underpayment (client owes more)
            $paymentBalance = $totalPaidOnOriginal - $unrefundedTotal;

            // Show when subtotal differs from refund charges (meaning adjustment was applied)
            $adjustmentApplied = abs($invoice->sub_amount - $refund->total_nett_refund) > 0.001;
        @endphp
        <div class="flex justify-end mb-8">
            <div class="w-1/3 text-sm">
                @if ($invoice->refund && $invoice->refund->originalInvoice)
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span>Original Invoice:</span>
                        <span>{{ number_format($invoice->refund->originalInvoice->amount, 3) }}</span>
                    </div>
                @endif
                <!-- <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Refund Charges:</span>
                    <span>{{ number_format($refund->total_nett_refund ?? 0, 3) }}</span>
                </div> -->
                @if ($adjustmentApplied && $unrefundedTasks->isNotEmpty())
                    @if ($paymentBalance > 0)
                        {{-- Overpayment: client has credit, reduce amount owed --}}
                        <div class="flex justify-between py-2 border-b border-gray-200 text-green-600">
                            <span>Overpayment Credit:</span>
                            <span>-{{ number_format($paymentBalance, 3) }}</span>
                        </div>
                    @elseif ($paymentBalance < 0)
                        {{-- Underpayment: client owes more for unrefunded items --}}
                        <div class="flex justify-between py-2 border-b border-gray-200 text-red-600">
                            <span>Outstanding Balance:</span>
                            <span>+{{ number_format(abs($paymentBalance), 3) }}</span>
                        </div>
                    @endif
                @endif
                @php
                    $subtotalWithServiceCharge = $invoice->sub_amount + ($totalGatewayFee['gatewayFee'] ?? 0);
                @endphp
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Subtotal:</span>
                    <span>{{ number_format($subtotalWithServiceCharge, 3) }}</span>
                </div>

                <div class="flex justify-between py-2 font-bold text-gray-800">
                    <span>Total:</span>
                    <span>
                        {{ number_format($totalGatewayFee['finalAmount'] ?? $invoice->amount, 3) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="mb-8 inline-flex gap-2">
            @if ($invoice->status !== 'paid')
                @if (auth()->check())
                    <form id="whatsappForm" action="{{ route('resayil.share-invoice-link') }}" method="POST" onsubmit="showSpinner()">
                        @csrf
                        <input type="hidden" name="client_id" id="clientid" value="{{ $invoice->client->id }}">
                        <input type="hidden" name="invoiceNumber" value="{{ $invoice->invoice_number }}">

                        <button id="submitButton" type="submit"
                            class="rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-gray-400 hover:shadow-xl hover:text-white">
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
                @if ($canGenerateLink)
                    <form id="paymentForm" action="{{ route('payment.create', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number]) }}"
                        method="POST">
                        @csrf

                        <input type="hidden" name="total_amount" value="{{ $totalGatewayFee['finalAmount'] ?? $invoice->amount }}">
                        <input type="hidden" name="client_email" value="{{ $invoice->client->email }}">
                        <input type="hidden" name="client_name" value="{{ $invoice->client->full_name }}">
                        <input type="hidden" name="client_phone" value="{{ $invoice->client->phone }}">
                        <input type="hidden" name="payment_gateway" value="{{ $invoice->invoicePartials->first()->payment_gateway }}">
                        <input type="hidden" name="payment_method" value="{{ $invoice->invoicePartials->first()->payment_method }}">
                        <input type="hidden" name="invoice_partial_id" value="{{ $invoice->invoicePartials->first()->id }}">

                        <button type="submit"
                            class="rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-gray-400 hover:shadow-xl hover:text-white">
                            Pay Now
                        </button>
                        <div id="loadingSpinner" class="hidden mt-2">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            Processing...
                        </div>
                    </form>
                @else
                    <div class="p-2 rounded-lg border border-gray-300 text-gray-700 flex items-center gap-2 text-xs sm:text-sm">
                        This invoice is {{ strtolower($invoice->invoicePartials->first()->payment_gateway) }} payment.
                        Please contact your agent for assistance.
                    </div>
                @endif
            @else
                <div class="flex items-center gap-2">
                    <p><span class="text-green-600 font-bold">PAID</span></p>
                </div>
            @endif
        </div>

        <div class="flex justify-between items-center">
            <div class="text-sm">
                <p class="text-gray-600">If you have any questions about this invoice, please contact:</p>
                <p class="text-gray-600">{{ $invoice->agent->branch->company->name }},
                    {{ $invoice->agent->branch->company->phone }}, {{ $invoice->agent->branch->company->email }}
                </p>
            </div>
            <div class="text-right">
                <p class="font-bold text-gray-800">Thank you for your business!</p>
            </div>
        </div>
    </div>

    @if ($invoice->status !== 'unpaid')
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
                    @php
                        // Check if this credit payment has PaymentApplication records (new audit trail system)
                        $paymentApps = $partial->paymentApplications()->with(['payment', 'credit.refund'])->get();
                        $hasPaymentApplications = $paymentApps->isNotEmpty();

                        $topupApps = $paymentApps->filter(fn($app) => $app->payment_id !== null);
                        $refundApps = $paymentApps->filter(fn($app) => $app->payment_id === null && $app->credit?->refund_id !== null);

                        // Old way: get credit utilization amount
                        $paymentReferenceCredit = \App\Models\Credit::getTotalUtilizeCreditsByClientPartial($partial->client_id, $partial->id);
                    @endphp
                    <tr class="text-sm text-gray-700">
                        <td class="px-4 py-2 border">
                            @if($hasPaymentApplications)
                                @foreach($topupApps as $app)
                                    @if($app->payment)
                                        <a href="{{ route('payment.link.show', ['companyId' => $companyId, 'voucherNumber' => $app->payment->voucher_number]) }}"
                                            class="text-blue-500 underline" target="_blank">{{ $app->payment->voucher_number }}</a>
                                        @if(!$loop->last || $refundApps->isNotEmpty())<br>@endif
                                    @endif
                                @endforeach
                                @foreach($refundApps as $app)
                                    @if($app->credit?->refund)
                                        <a href="{{ route('refunds.show', ['companyId' => $companyId, 'refundNumber' => $app->credit->refund->refund_number]) }}"
                                            class="text-blue-500 underline" target="_blank">
                                            {{ $app->credit->refund->refund_number }}
                                        </a>
                                    @else
                                        <span class="text-gray-700">Refund Credit</span>
                                    @endif
                                    @if(!$loop->last)<br>@endif
                                @endforeach
                            @elseif(optional($partial->payment)->voucher_number)
                                <a href="{{ route('payment.link.show', ['companyId' => $companyId, 'voucherNumber' => $partial->payment->voucher_number]) }}"
                                    class="text-blue-500 underline" target="_blank">{{ $partial->payment->voucher_number }}
                                </a>
                            @elseif ($partial->charge && !$partial->charge->is_system_default)
                                @if($partial->invoiceReceipt?->transaction?->reference_number)
                                    <a href="{{ route('receipt-voucher.show', ['companyId' => $companyId,
                                        'voucherNumber' => $partial->invoiceReceipt->transaction->reference_number]) }}" class="text-blue-500 underline" target="_blank">
                                        {{ $partial->invoiceReceipt->transaction->reference_number }}
                                    </a>
                                @else
                                    <span class="text-gray-600 italic">{{ $partial->payment_gateway }} (Receipt pending)</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-2 border">
                            @if ($hasPaymentApplications)
                                @foreach($topupApps as $app)
                                    @if($app->payment)
                                        {{ $app->payment->voucher_number }} ({{ number_format($app->amount, 3) }})
                                        @if(!$loop->last || $refundApps->isNotEmpty())<br>@endif
                                    @endif
                                @endforeach
                                @foreach($refundApps as $app)
                                    {{ $app->credit?->refund?->refund_number ?? 'RF-' . $app->credit?->refund_id }} ({{ number_format($app->amount, 3) }})
                                    @if(!$loop->last)<br>@endif
                                @endforeach
                            @elseif ($paymentReferenceCredit)
                                Client Credit by {{ $partial->client->full_name }}
                                ({{ $paymentReferenceCredit }})
                            @elseif ($partial->charge && !$partial->charge->is_system_default)
                                @if($partial->invoiceReceipt?->transaction?->reference_number)
                                    {{ $partial->invoiceReceipt->transaction->reference_number }}
                                @else
                                    <span class="italic">{{ $partial->payment_gateway }} (Receipt pending)</span>
                                @endif
                            @elseif ($partial->payment?->payment_gateway === 'MyFatoorah')
                                {{ $partial->payment->myfatoorahPayment->invoice_ref ?? $partial->payment->myfatoorahPayment->payload['Data']['InvoiceReference'] ?? 'N/A' }}
                            @else
                                {{ $partial->payment?->payment_reference ?? 'N/A' }}
                            @endif
                        </td>
                        <td class="px-4 py-2 border">
                            {{ $partial->payment ? \Carbon\Carbon::parse($partial->payment->payment_date)->format('d M, Y H:i') : \Carbon\Carbon::parse($partial->updated_at)->format('d M, Y H:i') }}
                        </td>
                        @if ($hasPaymentApplications || $paymentReferenceCredit)
                            <td class="px-4 py-2 border">Client Credit</td>
                        @else
                            <td class="px-4 py-2 border">{{ $partial->payment_gateway }}</td>
                        @endif
                        <td class="px-4 py-2 border">
                            {{ number_format(($partial->amount ?? 0) + ($partial->service_charge ?? 0) + ($partial->invoice_charge ?? 0), 3) }}
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
                <p class="text-lg text-gray-600">We appreciate your business! A confirmation email has been sent to your address.</p>
            </div>
        </div>
    </div>
    @endif

    <script>
        let invoice = @json($invoice);
        let invoicePartials = @json($invoicePartials);

        // Calculate balance from unpaid partials (same as show.blade.php)
        let balance = invoicePartials.filter(partial => partial.status !== 'paid')
            .reduce((sum, partial) => {
                return sum + parseFloat(partial.amount || 0)
                        + parseFloat(partial.service_charge || 0)
                        + parseFloat(partial.invoice_charge || 0);
            }, 0);

        let balanceElement = document.getElementById('balance');
        if (balanceElement) {
            balanceElement.textContent = balance.toFixed(3);
        }
    </script>
</body>
</html>
