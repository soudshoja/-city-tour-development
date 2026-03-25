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
                        <img class="h-16 w-auto mx-auto" src="{{ $company->logo ? Storage::url($company->logo) : asset('images/UserPic.svg') }}" alt="Company logo" />
                        <p class="text-base font-semibold">{{ $company->name }}</p>
                    </div>
                </div>
                <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm">
                    <div>
                        <p class="font-semibold text-gray-600 dark:text-slate-300">Billed To:</p>
                        <p class="text-gray-800 dark:text-white font-bold">{{ $invoice->client->full_name }}</p>
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
                                'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 shadow-sm',
                                'refunded' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 shadow-sm',
                                'unpaid' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 shadow-sm',
                                'partial' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 shadow-sm',
                                'paid by refund' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 shadow-sm',
                                'partial refund' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300 shadow-sm',
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
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Net Price (KWD)</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Profit (KWD)</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Invoice Price (KWD)</th>
                                </tr>
                            </thead>
                            @foreach($invoice->invoiceDetails as $index => $item )
                            <tbody x-data="{ open:false }" class="divide-y divide-gray-200 dark:divide-slate-700">
                                <tr class="cursor-pointer select-none focus:outline-none" @click="open = !open">
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-slate-400">{{ $index + 1 }}</td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white flex items-center" x-data="{ copied: false }">
                                            <p>
                                                {{ $item->task->reference }}
                                            </p>
                                            <button type="button" @click.stop="navigator.clipboard.writeText('{{ $item->task->reference }}').then(() => { copied = true; setTimeout(() => copied = false, 1500); })" class="ml-2 text-xs text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                                <template x-if="!copied">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-black dark:fill-white hover:fill-blue-600 dark:hover:fill-blue-300">
                                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M15 1.25H10.9436C9.10583 1.24998 7.65019 1.24997 6.51098 1.40314C5.33856 1.56076 4.38961 1.89288 3.64124 2.64124C2.89288 3.38961 2.56076 4.33856 2.40314 5.51098C2.24997 6.65019 2.24998 8.10582 2.25 9.94357V16C2.25 17.8722 3.62205 19.424 5.41551 19.7047C5.55348 20.4687 5.81753 21.1208 6.34835 21.6517C6.95027 22.2536 7.70814 22.5125 8.60825 22.6335C9.47522 22.75 10.5775 22.75 11.9451 22.75H15.0549C16.4225 22.75 17.5248 22.75 18.3918 22.6335C19.2919 22.5125 20.0497 22.2536 20.6517 21.6517C21.2536 21.0497 21.5125 20.2919 21.6335 19.3918C21.75 18.5248 21.75 17.4225 21.75 16.0549V10.9451C21.75 9.57754 21.75 8.47522 21.6335 7.60825C21.5125 6.70814 21.2536 5.95027 20.6517 5.34835C20.1208 4.81753 19.4687 4.55348 18.7047 4.41551C18.424 2.62205 16.8722 1.25 15 1.25ZM17.1293 4.27117C16.8265 3.38623 15.9876 2.75 15 2.75H11C9.09318 2.75 7.73851 2.75159 6.71085 2.88976C5.70476 3.02502 5.12511 3.27869 4.7019 3.7019C4.27869 4.12511 4.02502 4.70476 3.88976 5.71085C3.75159 6.73851 3.75 8.09318 3.75 10V16C3.75 16.9876 4.38624 17.8265 5.27117 18.1293C5.24998 17.5194 5.24999 16.8297 5.25 16.0549V10.9451C5.24998 9.57754 5.24996 8.47522 5.36652 7.60825C5.48754 6.70814 5.74643 5.95027 6.34835 5.34835C6.95027 4.74643 7.70814 4.48754 8.60825 4.36652C9.47522 4.24996 10.5775 4.24998 11.9451 4.25H15.0549C15.8297 4.24999 16.5194 4.24998 17.1293 4.27117ZM7.40901 6.40901C7.68577 6.13225 8.07435 5.9518 8.80812 5.85315C9.56347 5.75159 10.5646 5.75 12 5.75H15C16.4354 5.75 17.4365 5.75159 18.1919 5.85315C18.9257 5.9518 19.3142 6.13225 19.591 6.40901C19.8678 6.68577 20.0482 7.07435 20.1469 7.80812C20.2484 8.56347 20.25 9.56458 20.25 11V16C20.25 17.4354 20.2484 18.4365 20.1469 19.1919C20.0482 19.9257 19.8678 20.3142 19.591 20.591C19.3142 20.8678 18.9257 21.0482 18.1919 21.1469C17.4365 21.2484 16.4354 21.25 15 21.25H12C10.5646 21.25 9.56347 21.2484 8.80812 21.1469C8.07435 21.0482 7.68577 20.8678 7.40901 20.591C7.13225 20.3142 6.9518 19.9257 6.85315 19.1919C6.75159 18.4365 6.75 17.4354 6.75 16V11C6.75 9.56458 6.75159 8.56347 6.85315 7.80812C6.9518 7.07435 7.13225 6.68577 7.40901 6.40901Z" fill="" />
                                                    </svg>
                                                </template>
                                                <template x-if="copied">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-green-500">
                                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M21.5821 5.54289C22.3631 6.32389 22.3631 7.59055 21.5821 8.37156L10.2526 19.7011C9.46156 20.4821 8.19489 20.4821 7.41389 19.7011L2.41789 14.7051C1.63689 13.9241 1.63689 12.6574 2.41789 11.8764C3.19889 11.0954 4.46556 11.0954 5.24656 11.8764L8.83322 15.4631L18.7534 5.54289C19.5344 4.76189 20.8011 4.76189 21.5821 5.54289Z" fill="" />
                                                    </svg>
                                                </template>
                                            </button>
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-slate-300">
                                            {{ $item->task->ticket_number ?? $item->task_description }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($item->supplier_price, 3) }}
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($item->profit, 3) }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ number_format($item->task_price, 3) }}
                                            </span>
                                            <button type="button" @click.stop="open = !open" class="text-gray-400 hover:text-gray-600">
                                                <svg class="w-5 h-5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="open" x-cloak x-transition>
                                    <td colspan="5" class="px-6 py-4 bg-gray-50 dark:bg-slate-800">
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
                                            <!-- <div>
                                                <dt class="font-medium text-gray-500 dark:text-slate-400">Airline Reference</dt>
                                                <dd class="text-gray-900 dark:text-slate-200">{{ $item->task->airline_reference ?: 'N/A' }}</dd>
                                            </div> -->
                                            <div>
                                                <dt class="font-medium text-gray-500 dark:text-slate-400">Issued Date</dt>
                                                <dd class="text-gray-900 dark:text-slate-200">
                                                    {{ optional($item->task->issued_date)->format('d M Y') ?? 'N/A' }}
                                                </dd>
                                            </div>
                                            @if($item->task->paymentMethod)
                                            <div>
                                                <dt class="font-medium text-gray-500 dark:text-slate-400">Payment Method</dt>
                                                <dd class="text-gray-900 dark:text-slate-200">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                        {{ $item->task->paymentMethod->name }}
                                                    </span>
                                                </dd>
                                            </div>
                                            @endif
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
                                    </td>
                                </tr>
                                @if (! $loop->last)
                                <tr aria-hidden="true">
                                    <td colspan="5" class="p-0">
                                        <div class="my-1 border-t border-gray-200 dark:border-slate-700"></div>
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                            @endforeach
                        </table>
                    </div>
                </div>
            </section>
            <section class="px-8 py-6 bg-slate-100 dark:bg-slate-900/60">
                <div class="flex justify-end">
                    <div class="w-full max-w-sm">
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-slate-400">Total Net:</dt>
                                <dd class="font-medium text-gray-800 dark:text-slate-200">{{ number_format($invoice->invoiceDetails->sum('supplier_price'), 3) }} KWD</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-slate-400">Subtotal:</dt>
                                <dd class="font-medium text-gray-800 dark:text-slate-200">{{ number_format($invoice->sub_amount, 3) }} KWD</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-slate-400">Invoice Charges:</dt>
                                <dd class="font-medium text-gray-800 dark:text-slate-200">{{ number_format($invoice->invoicePartials->sum('invoice_charge') ?? 0, 3) }} KWD</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-slate-400">Service Charges:</dt>
                                <dd class="font-medium text-gray-800 dark:text-slate-200">{{ number_format($invoice->invoicePartials->sum('service_charge') ?? 0, 3) }} KWD</dd>
                            </div>
                            <div class="flex justify-between pt-3 border-t border-gray-200 dark:border-slate-700">
                                <dt class="text-base font-semibold text-gray-900 dark:text-white">Total Amount:</dt>
                                <dd class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($invoice->amount + $invoice->invoicePartials->sum('service_charge'), 3) }} KWD
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>
            @php
            $partials = $invoice->invoicePartials ?? collect();
            $typeBadgeClasses = [
            'full' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            'partial' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            'split' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            'credit' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
            'unpaid' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            ][$invoice->payment_type] ?? 'bg-gray-100 text-gray-800 dark:bg-slate-800/70 dark:text-slate-200';
            @endphp
            <section class="px-8 py-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Payment Summary</h2>
                    @if($partials->isNotEmpty())
                    <span class="inline-block px-3.5 py-1 rounded-full text-sm font-semibold shadow-sm {{ $typeBadgeClasses }}">{{ ucfirst($invoice->payment_type) }}</span>
                    @endif
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
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Invoice Charge</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 dark:text-slate-200 uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700 text-sm">
                            @foreach($partials as $partial)
                            @php
                                $voucher = trim(optional($partial->payment)->voucher_number ?? '');
                                $isCredit = (stripos($partial->payment_gateway ?? '', 'credit') !== false);
                                
                                // Check if this credit payment has PaymentApplication records (new audit trail system)
                                $paymentApps = $partial->paymentApplications()->with(['payment', 'credit.refund'])->get();
                                $hasPaymentApplications = $paymentApps->isNotEmpty();

                                $topupApps = $paymentApps->filter(fn($app) => $app->payment_id !== null);
                                $refundApps = $paymentApps->filter(fn($app) => $app->payment_id === null && $app->credit?->refund_id !== null);
                            @endphp
                            <tr>
                                <td class="px-6 py-3 text-gray-700 dark:text-slate-200">
                                    {{ optional($partial->created_at)->format('d M Y') }}
                                </td>
                                <td class="px-6 py-3 text-gray-800 dark:text-slate-100">
                                    {{ $partial->payment_gateway ?? '—' }}
                                </td>
                                <td class="px-6 py-3 text-gray-700 dark:text-slate-300">
                                    @if($hasPaymentApplications)
                                        @foreach($topupApps as $app)
                                            @if($app->payment)
                                                <a href="{{ route('payment.link.show', ['companyId' => $company->id, 'voucherNumber' => $app->payment->voucher_number]) }}"
                                                    class="text-blue-500 hover:text-blue-700" target="_blank">{{ $app->payment->voucher_number }}</a>
                                                <span class="text-xs text-gray-500">({{ number_format($app->amount, 3) }})</span>
                                                @if(!$loop->last || $refundApps->isNotEmpty())<br>@endif
                                            @endif
                                        @endforeach
                                        @foreach($refundApps as $app)
                                            @if($app->credit?->refund)
                                                <a href="{{ route('refunds.show', ['companyId' => $company->id, 'refundNumber' => $app->credit->refund->refund_number]) }}"
                                                    class="text-blue-500 hover:text-blue-700" target="_blank">{{ $app->credit->refund->refund_number }}</a>
                                                <span class="text-xs text-gray-500">({{ number_format($app->amount, 3) }})</span>
                                            @else
                                                <span class="text-gray-600 italic">TBA</span>
                                            @endif
                                            @if(!$loop->last)<br>@endif
                                        @endforeach
                                    @elseif($voucher)
                                        <a href="{{ route('payment.link.show', ['companyId' => $company->id, 'voucherNumber' => $voucher]) }}"
                                            class="text-blue-500 hover:text-blue-700" target="_blank">{{ $voucher }}</a>
                                    @elseif($partial->charge && !$partial->charge->is_system_default)
                                        @if($partial->invoiceReceipt?->transaction?->reference_number)
                                            <a href="{{ route('receipt-voucher.show', ['companyId' => $company->id, 'voucherNumber' => $partial->invoiceReceipt->transaction->reference_number]) }}" class="text-blue-500 hover:text-blue-700" target="_blank">
                                                {{ $partial->invoiceReceipt->transaction->reference_number }}
                                            </a>
                                        @else
                                            <span class="text-gray-600 italic">TBA</span>
                                        @endif
                                    @else
                                        {{ $isCredit ? 'Client Credit' : 'TBA' }}
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right font-medium text-gray-900 dark:text-white">
                                    {{ number_format($partial->amount, 3) }} KWD
                                </td>
                                <td class="px-6 py-3 text-right text-gray-900 dark:text-white">
                                    {{ number_format($partial->service_charge ?? 0, 3) }} KWD
                                </td>
                                <td class="px-6 py-3 text-right text-gray-900 dark:text-white">
                                    {{ number_format($partial->invoice_charge ?? 0, 3) }} KWD
                                </td>
                                <td class="px-6 py-3 text-right text-gray-900 dark:text-white">
                                    {{ number_format($partial->amount + $partial->invoice_charge + $partial->service_charge, 3) }} KWD
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-600 dark:text-slate-400">
                    @php
                        $paidPartials = $invoice->invoicePartials->filter(fn($p) => strtolower($p->status ?? '') === 'paid');
                        $paidTotal = $paidPartials->sum('amount') + $paidPartials->sum('service_charge') + $paidPartials->sum('invoice_charge');
                        $grandTotal = $invoice->amount + $partials->sum('service_charge');
                    @endphp
                    Paid {{ number_format($paidTotal, 3) }} KWD of {{ number_format($grandTotal, 3) }} KWD
                </p>
                @endif
            </section>
            @if($journalEntries->isNotEmpty())
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
                                        {{ number_format($entry->debit, 3) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <span class="font-semibold {{ $entry->credit > 0 ? 'text-green-700 dark:text-green-300' : 'text-gray-600 dark:text-slate-400' }}">
                                        {{ number_format($entry->credit, 3) }}
                                    </span>
                                </td>
                                <!-- <td class="px-6 py-4 text-right text-sm font-bold {{ $entry->running_balance >= 0 ? 'text-green-700 dark:text-green-300' : 'text-gray-900 dark:text-slate-100' }}">
                                        {{ $entry->running_balance !== null ? number_format($entry->running_balance, 3) : 'N/A' }}
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