<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />

    @vite(['resources/css/app.css'])
    <script src="//unpkg.com/alpinejs" defer></script>
</head>
<body class="overflow-y-auto font-nunito antialiased bg-gray-100">
    <div class="min-h-screen bg-gray-100 dark:bg-slate-900 p-4 sm:p-6 lg:p-8">
        <div class="max-w-5xl mx-auto bg-white dark:bg-slate-800 rounded-2xl shadow-lg overflow-hidden">
            <header class="px-8 py-6 bg-slate-50 dark:bg-slate-900/50 border-b border-gray-200 dark:border-slate-700">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Invoice</h1>
                        <p class="text-gray-600 dark:text-slate-400 mt-1">{{ $invoice->invoice_number }}</p>
                    </div>
                    <div>
                        <img class="h-16 w-auto mx-auto" src="{{ $company->logo ? Storage::url($company->logo) : asset('images/UserPic.svg') }}" alt="Company logo"/>
                        <p class="text-base font-semibold">{{ $company->name }}</p>
                    </div>
                </div>
                <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm">
                    <div>
                        <p class="font-semibold text-gray-600 dark:text-slate-300">Billed To:</p>
                        <p class="text-gray-800 dark:text-white font-bold">{{ $invoice->client->name }}</p>
                        <p class="text-gray-600 dark:text-slate-400">{{ $invoice->client->email }}</p>
                        <p class="text-gray-600 dark:text-slate-400">{{ $invoice->client->phone }}</p>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-600 dark:text-slate-300">Handled By:</p>
                        <p class="text-gray-800 dark:text-white font-bold">{{ $invoice->agent->name }}</p>
                        <p class="text-gray-600 dark:text-slate-400">{{ $invoice->agent->email }}</p>
                    </div>
                    <div>
                        <p><span class="font-semibold text-gray-600 dark:text-slate-300">Invoice Date:</span> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</p>
                        <p><span class="font-semibold text-gray-600 dark:text-slate-300">Due Date:</span> {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</p>
                        <p><span class="font-semibold text-gray-600 dark:text-slate-300">Paid Date:</span> {{ \Carbon\Carbon::parse($invoice->paid_date)->format('d M Y') }}</p>
                        @php
                            $status = strtolower($invoice->status ?? '');
                            $classes = [
                                'paid'    => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 shadow-sm',
                                'unpaid'  => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 shadow-sm',
                                'partial' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 shadow-sm',
                            ][$status] ?? 'bg-gray-100 text-gray-800 dark:bg-slate-800/70 dark:text-slate-200 shadow-sm';
                        @endphp
                        <span class="mt-2 inline-block px-3.5 py-1 rounded-full text-base font-semibold {{ $classes }}">
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </div>
                </div>
            </header>
            <section class="px-8 py-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Invoice Items</h2>
                <div class="border border-gray-300 dark:border-slate-600 rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-100 dark:bg-slate-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Task Details</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Invoice Price (KWD)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                @foreach($invoice->invoiceDetails as $index => $item )
                                    <tr x-data="{ open: false }">
                                        <td colspan="3" class="p-0">
                                            <div class="cursor-pointer" @click="open = !open">
                                                <div class="flex items-center px-6 py-4 hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                                    <div class="w-8 text-sm text-gray-500 dark:text-slate-400">{{ $index + 1 }}</div>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item->task->reference }}</p>
                                                        <p class="text-xs text-gray-600 dark:text-slate-300">{{ $item->task->ticket_number ?? $item->task_description }}</p>
                                                    </div>
                                                    <div class="w-32 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($item->task_price, 2) }}</div>
                                                    <div class="w-8 text-right pl-2">
                                                        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                    </div>
                                                </div>
                                            </div>
                                            <div x-show="open" x-transition class="bg-slate-50 dark:bg-slate-800/50 p-6 border-t border-gray-200 dark:border-slate-700">
                                                <h4 class="font-bold text-md mb-3 text-gray-800 dark:text-white">Task Breakdown</h4>
                                                @if($item->task)
                                                <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                                                    <div class="sm:col-span-2">
                                                        <dt class="font-medium text-gray-500 dark:text-slate-400">Passenger Name</dt>
                                                        <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->passenger_name ?: 'N/A' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="font-medium text-gray-500 dark:text-slate-400">Client Name</dt>
                                                        <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->client_name ?: 'N/A' }}</dd>
                                                    </div>
                                                    <hr class="sm:col-span-2 md:col-span-3 border-gray-200 dark:border-slate-700">
                                                    <div>
                                                        <dt class="font-medium text-gray-500 dark:text-slate-400">Supplier</dt>
                                                        <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->supplier->name ?? 'N/A' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="font-medium text-gray-500 dark:text-slate-400">GDS Reference</dt>
                                                        <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->gds_reference ?: 'N/A' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="font-medium text-gray-500 dark:text-slate-400">Airline Reference</dt>
                                                        <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->airline_reference ?: 'N/A' }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="font-medium text-gray-500 dark:text-slate-400">Issued Date</dt>
                                                        <dd class="text-gray-900 dark:text-slate-200">
                                                            {{ optional($item->task->issued_date)->format('d M Y') ?? 'N/A' }}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="font-medium text-gray-500 dark:text-slate-400">Supplier Price</dt>
                                                        <dd class="text-gray-900 dark:text-slate-200">{{ number_format($item->supplier_price, 2) }} KWD</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="font-medium text-gray-500 dark:text-slate-400">Markup</dt>
                                                        <dd class="text-gray-900 dark:text-slate-200">{{ number_format($item->markup_price, 2) }} KWD</dd>
                                                    </div>
                                                    <div class="sm:col-span-2 md:col-span-2">
                                                        <dt class="font-medium text-gray-500 dark:text-slate-400">Additional Info</dt>
                                                        <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->additional_info }}</dd>
                                                    </div>

                                                    @php
                                                        $taskType = strtolower($item->task->type ?? '');
                                                    @endphp
                                                    @if(in_array($taskType, ['flight', 'hotel', 'visa', 'insurance']))
                                                        <hr class="sm:col-span-2 md:col-span-3 border-gray-200 dark:border-slate-700">
                                                        <div class="sm:col-span-2 md:col-span-3">
                                                            <h5 class="font-bold text-gray-800 dark:text-white">{{ ucfirst($taskType) }} Details</h5>
                                                        </div>
                                                    @endif

                                                    @if($taskType === 'flight' && $flight = optional($item->task)->flightDetails)
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Class</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ ucfirst($flight->class_type) ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Flight No.</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $flight->flight_number ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Ticket No.</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $flight->ticket_number ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Departure</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">
                                                                {{ $flight->airport_from ?: 'N/A' }}
                                                                @if($flight->terminal_from) (T{{ $flight->terminal_from }}) @endif
                                                                @if($flight->departure_time) — {{ \Carbon\Carbon::parse($flight->departure_time)->format('d M Y, H:i') }} @endif
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Arrival</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">
                                                                {{ $flight->airport_to ?: 'N/A' }}
                                                                @if($flight->terminal_to) (T{{ $flight->terminal_to }}) @endif
                                                                @if($flight->arrival_time) — {{ \Carbon\Carbon::parse($flight->arrival_time)->format('d M Y, H:i') }} @endif
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Duration</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $flight->duration_time ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Baggage</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $flight->baggage_allowed ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Seat</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ trim($flight->seat_no) ? $flight->seat_no : 'TBA' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Meal / Equipment</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">
                                                                {{ trim($flight->flight_meal) ? $flight->flight_meal :  'TBA' }} {{ $flight->equipment ? " / {$flight->equipment}" : '' }}
                                                            </dd>
                                                        </div>
                                                    @endif
                                                    @if($taskType === 'hotel' && $hotel = optional($item->task)->hotelDetails)
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Check-in</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">
                                                                {{ $hotel->check_in ? \Carbon\Carbon::parse($hotel->check_in)->format('d M Y') : 'N/A' }}
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Check-out</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">
                                                                {{ $hotel->check_out ? \Carbon\Carbon::parse($hotel->check_out)->format('d M Y') : 'N/A' }}
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Booking Time</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">
                                                                {{ $hotel->booking_time ? \Carbon\Carbon::parse($hotel->booking_time)->format('d M Y, H:i') : 'N/A' }}
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Meal</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $hotel->meal_type ?? 'N/A' }}</dd>
                                                        </div>

                                                        @php
                                                            $roomDetails = collect(json_decode($hotel->room_details ?? '[]', true))
                                                                ->filter(function ($value) {
                                                                    if (is_array($value)) {
                                                                        return collect($value)->filter(fn($v) => !blank($v))->isNotEmpty();
                                                                    }
                                                                    return !blank($value);
                                                                });
                                                        @endphp
                                                        @if($roomDetails->isNotEmpty())
                                                            @foreach($roomDetails as $key => $value)
                                                                <div class="sm:col-span-1">
                                                                    <dt class="font-medium text-gray-500 dark:text-slate-400">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                                                                    <dd class="text-gray-900 dark:text-slate-200">
                                                                        @if(is_array($value))
                                                                            {{ collect($value)->filter(fn($v) => !blank($v))->implode(', ') }}
                                                                        @else
                                                                            {{ $value }}
                                                                        @endif
                                                                    </dd>
                                                                </div>
                                                            @endforeach
                                                        @endif

                                                    @endif
                                                    @if($taskType === 'visa' && $visa = optional($item->task)->visaDetails)
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Visa Type</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $visa->visa_type ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Application #</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $visa->application_number ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Expiry Date</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">
                                                                {{ $visa->expiry_date ? \Carbon\Carbon::parse($visa->expiry_date)->format('d M Y') : 'N/A' }}
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Entries</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $visa->number_of_entries ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Stay Duration</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $visa->stay_duration ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Issuing Country</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $visa->issuing_country ?? 'N/A' }}</dd>
                                                        </div>
                                                    @endif
                                                    @if($taskType === 'insurance' && $insurance = optional($item->task)->insuranceDetails)
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Insurance Type</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $insurance->insurance_type ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Destination</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $insurance->destination ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Plan Type</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $insurance->plan_type ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Duration</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $insurance->duration ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Package</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $insurance->package ?? 'N/A' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Document Reference</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $insurance->document_reference ?? '—' }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="font-medium text-gray-500 dark:text-slate-400">Paid Leaves</dt>
                                                            <dd class="text-gray-900 dark:text-slate-200">{{ $insurance->paid_leaves ?? '—' }}</dd>
                                                        </div>
                                                    @endif
                                                </dl>
                                                @else
                                                    <p class="text-gray-500 dark:text-slate-400">No associated task found for this item.</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <section class="px-8 py-6 bg-slate-100 dark:bg-slate-900/60">
                <div class="flex justify-end">
                    <div class="w-full max-w-sm">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-slate-400">Subtotal:</dt>
                                <dd class="font-medium text-gray-800 dark:text-slate-200">{{ number_format($invoice->sub_amount, 2) }} KWD</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-slate-400">Service Charges:</dt>
                                <dd class="font-medium text-gray-800 dark:text-slate-200">{{ number_format($invoice->invoicePartials->sum('service_charge') ?? 0, 2) }} KWD</dd>
                            </div>
                            <div class="flex justify-between pt-3 border-t border-gray-200 dark:border-slate-700">
                                <dt class="text-base font-semibold text-gray-900 dark:text-white">Total Amount:</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($invoice->amount + $invoice->invoicePartials->sum('service_charge'), 2) }} KWD</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>
            @php
                $partials = $invoice->invoicePartials ?? collect();
                $typeBadgeClasses = [
                    'full'    => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                    'partial' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
                    'split'   => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                    'credit'  => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                    'unpaid'  => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                ][$invoice->payment_type] ?? 'bg-gray-100 text-gray-800 dark:bg-slate-800/70 dark:text-slate-200';
            @endphp
            <section class="px-8 py-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Payment Summary</h2>
                    <span class="inline-block px-3.5 py-1 rounded-full text-sm font-semibold shadow-sm {{ $typeBadgeClasses }}">{{ ucfirst($invoice->payment_type) }}</span>
                </div>
                @if($partials->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-slate-400">No payments recorded.</p>
                @else
                    <div class="overflow-x-auto border border-gray-200 dark:border-slate-700 rounded-lg">
                        <table class="w-full">
                            <thead class="bg-gray-100 dark:bg-slate-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Gateway</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Reference</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Service Charge</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700 text-sm">
                                @foreach($partials as $partial)
                                    <tr>
                                        <td class="px-6 py-3 text-gray-700 dark:text-slate-200">
                                            {{ optional($partial->created_at)->format('d M Y') }}
                                        </td>
                                        <td class="px-6 py-3 text-gray-800 dark:text-slate-100">
                                            {{ $partial->payment_gateway ?? '—' }}
                                        </td>
                                        @php
                                            $voucher = trim(optional($partial->payment)->voucher_number ?? '');
                                            $isCredit = (stripos($partial->payment_gateway ?? '', 'credit') !== false);
                                        @endphp
                                        <td class="px-6 py-3 text-gray-700 dark:text-slate-300">
                                            @if($voucher)
                                                <a href="{{ route('payment.link.show', ['companyId' => $company->id, 'voucherNumber' => $voucher]) }}"
                                                class="text-blue-500 hover:text-blue-700" target="_blank">{{ $voucher }}</a>
                                            @else
                                                {{ $isCredit ? 'Client Credit' : 'TBA' }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-right font-medium text-gray-900 dark:text-white">
                                            {{ number_format($partial->status === 'unpaid' ? $partial->amount : $partial->amount - $partial->service_charge, 2) }} KWD
                                        </td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-white">
                                            {{ number_format($partial->service_charge ?? 0, 2) }} KWD
                                        </td>
                                        <td class="px-6 py-3 text-right text-gray-900 dark:text-white">
                                            {{ number_format($partial->status === 'unpaid' ? $partial->amount + $partial->service_charge : $partial->amount, 2) }} KWD
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-gray-600 dark:text-slate-400">
                        Paid {{ number_format($invoice->invoicePartials->filter(fn($p) => strtolower($p->status ?? '') === 'paid')->sum('amount'), 2) }} KWD
                        of {{ number_format($invoice->amount + $partials->sum('service_charge'), 2) }} KWD
                    </p>
                @endif
            </section>
            @if($invoice->payment_type)
            <section class="px-8 pt-2 pb-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Financial Ledger</h2>
                <div class="overflow-x-auto border border-gray-200 dark:border-slate-700 rounded-lg">
                    <table class="w-full">
                        <thead class="bg-gray-100 dark:bg-slate-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Debit</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Credit</th>
                                <!-- <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Balance</th> -->
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            @foreach($journalEntries as $entry)
                                @php
                                    $date = $entry->transaction_date ?? $entry->created_at;
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-slate-400">{{ \Carbon\Carbon::parse($entry->date)->format('d M Y') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-800 dark:text-slate-200">{{ $entry->description ?? '-' }}</td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <span class="font-semibold {{ $entry->debit > 0 ? 'text-red-700 dark:text-red-300' : 'text-gray-600 dark:text-slate-400' }}">
                                            {{ number_format($entry->debit, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <span class="font-semibold {{ $entry->credit > 0 ? 'text-green-700 dark:text-green-300' : 'text-gray-600 dark:text-slate-400' }}">
                                            {{ number_format($entry->credit, 2) }}
                                        </span>
                                    </td>
                                    <!-- <td class="px-6 py-4 text-right text-sm font-bold {{ $entry->running_balance >= 0 ? 'text-green-700 dark:text-green-300' : 'text-gray-900 dark:text-slate-100' }}">
                                        {{ $entry->running_balance !== null ? number_format($entry->running_balance, 2) : 'N/A' }}
                                    </td> -->
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @endif
            <footer class="px-8 py-6 text-center text-sm text-gray-500 dark:text-slate-400 border-t border-gray-200 dark:border-slate-700">
                <p>Thank you for your business!</p>
                <p class="mt-1">If you have any questions, please contact us at {{ $company->email }}</p>
            </footer>
        </div>
    </div>
    </body>
</html>
