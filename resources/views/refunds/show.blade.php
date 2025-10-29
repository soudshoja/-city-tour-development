<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
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
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css'])
        <script src="//unpkg.com/alpinejs" defer></script>
        <script src="https://code.jquery.com/jquery-3.7.1.slim.js" integrity="sha256-UgvvN8vBkgO0luPSUl2s8TIlOSYRoGFAX4jlCIm9Adc=" crossorigin="anonymous"></script>
    </head>

    <body class="bg-gray-100 font-nunito antialiased">
        <div class="max-w-5xl mx-auto bg-white shadow-lg rounded-lg mt-10 p-8">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Refund Summary</h1>
                    <p class="text-sm text-gray-600">Refund #: {{ $refund->refund_number }}</p>
                    <p class="text-sm text-gray-600">Date: {{ optional($refund->refund_date)->format('d M Y') }}</p>
                    <p class="text-sm text-gray-600">Status:
                        <span class="font-semibold {{ $refund->status === 'processed' ? 'text-orange-600' : 'text-green-600' }}">
                            {{ ucfirst($refund->status) }}
                        </span>
                    </p>
                </div>
                <div class="text-right">
                    <img class="w-auto h-[85px] object-contain" src="{{ $company->logo ? Storage::url($company->logo) : asset('images/UserPic.svg') }}" alt="Company logo" />
                    <p class="font-semibold">{{ $company->name }}</p>
                    <p class="text-sm text-gray-600">{{ $company->address }}</p>
                    <p class="text-sm text-gray-600">{{ $company->email }}</p>
                    <p class="text-sm text-gray-600">{{ $company->phone }}</p>
                </div>
            </div>

            <!-- Clients -->
            @if($groupedByClient->count() > 1)
                <h3 class="text-lg font-semibold mb-3 text-gray-800">Billed To</h3>
                @foreach ($groupedByClient as $clientId => $details)
                    @php $client = $details->first()->client; @endphp
                    <p class="text-sm text-gray-700 mb-1">
                        {{ $client->full_name }} 
                        <span class="text-gray-500">({{ $client->email ?? 'No email' }})</span>
                    </p>
                @endforeach
            @else
                @php $client = $refundDetails->first()->client; @endphp
                <div class="flex justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Billed To</h3>
                        <p class="text-sm text-gray-700">{{ $client->full_name }}</p>
                        <p class="text-sm text-gray-600">{{ $client->email }}</p>
                        <p class="text-sm text-gray-600">{{ $client->phone }}</p>
                    </div>
                </div>
            @endif

            <h3 class="text-lg font-semibold mt-8 mb-3 text-gray-800">Refund Breakdown</h3>
            @foreach ($groupedByInvoice as $invoiceId => $items)
                @php 
                    $invoice = $items->first()->invoice;
                    $refundTotal = $items->sum('total_refund_to_client');
                    $refundCharges = $items->where('total_refund_to_client', '<', 0)->sum('total_refund_to_client');
                @endphp

                <div class="mb-8">
                    <div class="bg-gray-100 p-4 rounded-lg mb-3">
                        <h4 class="font-bold text-gray-800 text-lg">
                            Original Invoice: {{ $invoice->invoice_number ?? 'N/A' }}
                        </h4>
                        <p class="text-sm text-gray-600">Invoice Date: {{ optional($invoice->created_at)->format('d M Y') ?? '—' }}</p>
                        <p class="text-sm text-gray-600">Total Invoice: {{ number_format($invoice->amount ?? 0, 2) }} KWD</p>
                    </div>

                    <table class="min-w-full border border-gray-200 text-sm mb-3">
                        <thead class="bg-gray-100 text-gray-600">
                        <tr>
                            <th class="border px-4 py-2 text-left">Task Details</th>
                            <th class="border px-4 py-2 text-center">Invoice Price</th>
                            <th class="border px-4 py-2 text-center">Refund / Charge</th>
                            <th class="border px-4 py-2 text-center">Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $detail)
                            <tr>
                                <td class="px-4 py-2 border align-top">
                                    @if ($detail->task->type === 'hotel')
                                        @php
                                            $roomDetails = json_decode($detail->task->hotelDetails->room_details, true);
                                            $passengerCount = count($roomDetails['passengers'] ?? []);
                                        @endphp
                                        <p>
                                            @if(!empty($detail->task->reference))
                                                Reference: {{ $detail->task->reference }}<br>
                                            @endif
                                            Client Name: {{ $detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}<br>
                                            Passenger Name: {{ $detail->task->passenger_name ?? 'N/A' }}<br>
                                            Hotel Name: {{ $detail->task->hotelDetails->hotel->name ?? 'N/A' }}<br>
                                            Check In: {{ $detail->task->hotelDetails->check_in ?? 'N/A' }}<br>
                                            Check Out: {{ $detail->task->hotelDetails->check_out ?? 'N/A' }}<br>
                                            Number of Pax: {{ $passengerCount ?? $detail->task->number_of_pax ?? 'N/A' }}<br>
                                            Room Category: {{ $detail->task->hotelDetails->room_type ?? $detail->task->hotelDetails->room_category ?? 'N/A' }}
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
                                            Passenger Name: {{ $detail->task->passenger_name ?? 'N/A' }}<br>
                                            Route:
                                            {{ $detail->task->flightDetails->countryFrom->name ?? '' }}
                                            ({{ $detail->task->flightDetails->airport_from ?? '' }})
                                            →
                                            {{ $detail->task->flightDetails->countryTo->name ?? '' }}
                                            ({{ $detail->task->flightDetails->airport_to ?? '' }})<br>
                                            Class of Travel: {{ ucfirst($detail->task->flightDetails->class_type ?? 'N/A') }}
                                        </p>

                                    @elseif ($detail->task->type === 'visa')
                                        <p>
                                            @if(!empty($detail->task->reference))
                                                Reference: {{ $detail->task->reference }}<br>
                                            @endif
                                            Client Name: {{ $detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}<br>
                                            Passenger Name: {{ $detail->task->passenger_name ?? 'N/A' }}<br>
                                            Visa Type: {{ $detail->task->visaDetails->visa_type ?? 'N/A' }}<br>
                                            Application #: {{ $detail->task->visaDetails->application_number ?? 'N/A' }}<br>
                                            Expiry Date: 
                                            {{ !empty($detail->task->visaDetails?->expiry_date) ? \Carbon\Carbon::parse($detail->task->visaDetails->expiry_date)->format('d M Y') : 'N/A' }}<br>
                                            Entries: {{ $detail->task->visaDetails->number_of_entries ?? 'N/A' }}<br>
                                            Stay Duration: {{ $detail->task->visaDetails->stay_duration ?? 'N/A' }}<br>
                                            Issuing Country: {{ $detail->task->visaDetails->issuing_country ?? 'N/A' }}
                                        </p>

                                    @elseif ($detail->task->type === 'insurance')
                                        <p>
                                            @if(!empty($detail->task->reference))
                                                Reference: {{ $detail->task->reference }}<br>
                                            @endif
                                            Client Name: {{ $detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A') }}<br>
                                            Passenger Name: {{ $detail->task->passenger_name ?? 'N/A' }}<br>
                                            Insurance Type: {{ $detail->task->insuranceDetails->insurance_type ?? 'N/A' }}<br>
                                            Destination: {{ $detail->task->insuranceDetails->destination ?? 'N/A' }}<br>
                                            Plan Type: {{ $detail->task->insuranceDetails->plan_type ?? 'N/A' }}<br>
                                            Duration: {{ $detail->task->insuranceDetails->duration ?? 'N/A' }}<br>
                                            Package: {{ $detail->task->insuranceDetails->package ?? 'N/A' }}<br>
                                            Document Reference: {{ $detail->task->insuranceDetails->document_reference ?? 'N/A' }}<br>
                                            Paid Leaves: {{ $detail->task->insuranceDetails->paid_leaves ?? 'N/A' }}
                                        </p>

                                    @else
                                        <p class="text-gray-500 italic">No specific task type details available.</p>
                                    @endif
                                </td>

                                <td class="border px-4 py-2 text-center align-top">
                                    {{ number_format($detail->original_invoice_price, 2) }}
                                </td>
                                <td class="border px-4 py-2 text-center align-top
                                    {{ $detail->total_refund_to_client >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ number_format($detail->total_refund_to_client, 2) }}
                                </td>
                                <td class="border px-4 py-2 text-center align-top">{{ $detail->remarks ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <div class="flex justify-end text-sm">
                        <div class="w-1/3">
                            <div class="flex justify-between py-1 border-b border-gray-200">
                                <span>Refund Total:</span>
                                <span class="font-bold text-green-600">{{ number_format($refundTotal, 2) }} KWD</span>
                            </div>
                            @if($refundCharges < 0)
                                <div class="flex justify-between py-1 border-b border-gray-200">
                                    <span>Charges (Client Pays):</span>
                                    <span class="font-bold text-red-600">
                                        {{ number_format(abs($refundCharges), 2) }} KWD
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="mt-8 text-center border-t pt-4">
                <p class="text-gray-600 text-sm">
                    If you have any questions about this refund, please contact:
                </p>
                <p class="text-sm text-gray-700 font-semibold">{{ $company->email }} | {{ $company->phone }}</p>
            </div>

        </div>
    </body>
</html>