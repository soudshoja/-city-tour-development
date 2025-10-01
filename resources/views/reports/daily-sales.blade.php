<x-app-layout>
    <div class="mb-6">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100">Daily Sales Report</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Date:
                    <span class="font-semibold">
                        {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}
                    </span>
                </p>
            </div>

            <div class="flex items-end gap-2">
                <a href="{{ route('reports.daily-sales.pdf', ['date' => \Carbon\Carbon::parse($date)->format('Y-m-d')]) }}" target="_blank"
                    class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md text-sm font-medium bg-slate-600 hover:bg-slate-700 active:bg-slate-800 text-white transition-all
                        focus:outline-none focus:ring-2 focus:ring-slate-400/40">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    View PDF
                </a>
                <a href="{{ route('reports.daily-sales.pdf.download', ['date' => \Carbon\Carbon::parse($date)->format('Y-m-d')]) }}"
                    class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white transition-all
                        focus:outline-none focus:ring-2 focus:ring-blue-400/40">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                    </svg>
                    Download PDF
                </a>
                <form method="GET" action="{{ route('reports.daily-sales') }}"
                    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-2.5 rounded-lg flex items-end gap-2">
                    <div>
                        <label for="date" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                            Select date
                        </label>
                        <input type="date" id="date" name="date" value="{{ \Carbon\Carbon::parse($date)->format('Y-m-d') }}"
                            class="w-44 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100
                                text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button type="submit" class="h-9 px-3 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white
                            transition-all focus:outline-none focus:ring-2 focus:ring-blue-400/40">
                        Apply
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 print:grid-cols-4">
        @php
        $cards = [
            ['label' => 'Total Invoices', 'value' => $summary['totalInvoices'], 'suffix' => null],
            ['label' => 'Total Invoiced', 'value' => number_format($summary['totalInvoiced'], 3), 'suffix' => 'KWD'],
            ['label' => 'Total Paid', 'value' => number_format($summary['totalPaid'], 3), 'suffix' => 'KWD'],
            ['label' => 'Total Profit', 'value' => number_format($summary['profit'], 3), 'suffix' => 'KWD'],
        ];
        @endphp

        @foreach($cards as $card)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
            <div class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-gray-100">
                {{ $card['value'] }} @if($card['suffix']) <span class="text-base font-semibold">{{ $card['suffix'] }}</span> @endif
            </div>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Collections Breakdown</h3>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full border text-sm border-emerald-300/60 dark:border-emerald-700/60 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-200">
                    Cash: <strong>{{ number_format($summary['cashSum'] ?? 0, 3) }}</strong> KWD
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full border text-sm border-indigo-300/60 dark:border-indigo-700/60 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-200">
                    Gateway: <strong>{{ number_format($summary['gatewaySum'] ?? 0, 3) }}</strong> KWD
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full border text-sm border-amber-300/60 dark:border-amber-700/60 bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-200">
                    Client Credit: <strong>{{ number_format($summary['creditSum'] ?? 0, 3) }}</strong> KWD
                </span>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full border text-sm border-rose-300/60 dark:border-rose-700/60 bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-200">
                    Refunds: <strong>{{ number_format($summary['refunds'] ?? 0, 3) }}</strong> KWD
                </span>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Top Performing Agent</h3>
            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">
                {{ $summary['topAgent'] ?? '-' }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                Paid today: <span class="font-semibold">{{ number_format($summary['topAgentAmount'] ?? 0, 3) }}</span> KWD
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-1">Top Supplier</h3>
            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">
                {{ $summary['topSupplier'] ?? '-' }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                Invoiced today: <span class="font-semibold">{{ number_format($summary['topSupplierAmount'] ?? 0, 3) }}</span> KWD
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 mb-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-3">Agent Performance</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-gray-900 dark:text-gray-100">
                <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-200">
                    <tr class="px-3 py-2 text-center">
                        <th>Agent</th>
                        <th>Total Invoices</th>
                        <th>Total Invoiced (KWD)</th>
                        <th>Total Paid (KWD)</th>
                        <th>Unpaid (KWD)</th>
                        <th>Profit (KWD)</th>
                        <th>Commission (KWD)</th>
                        <th>Top-ups (KWD)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/80 dark:divide-gray-700">
                    @foreach($agents as $row)
                    <tr class="bg-white/70 dark:bg-gray-800/70 hover:bg-gray-100 dark:hover:bg-gray-700 px-3 py-2 text-center">
                        <td class="font-semibold">{{ $row['agent']->name }}</td>
                        <td>{{ $row['totalInvoices'] }}</td>
                        <td>{{ number_format($row['totalInvoiced'], 3) }}</td>
                        <td>{{ number_format($row['paid'], 3) }}</td>
                        <td>{{ number_format($row['unpaid'], 3) }}</td>
                        <td>{{ number_format($row['profit'], 3) }}</td>
                        <td>{{ number_format($row['commission'], 3) }}</td>
                        <td>{{ number_format($row['topupCollected'], 3) }}</td>
                        <td class="px-3 py-2 text-center">
                            <button type="button"
                                onclick="toggleAgentRow('{{ $row['agent']->id }}')"
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 text-sm">
                                <svg id="agent-caret-{{ $row['agent']->id }}" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                View
                            </button>
                        </td>
                    </tr>
                    <tr id="agent-details-{{ $row['agent']->id }}" class="hidden">
                        <td colspan="9" class="p-0">
                            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                                @if($row['invoices']->isEmpty())
                                <div class="text-sm text-gray-500 dark:text-gray-400">No invoices created today.</div>
                                @else
                                <div class="space-y-3">
                                    @foreach($row['invoices'] as $invoice)
                                    <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                        <div onclick="toggleInvoiceTasks('{{ $row['agent']->id }}','{{ $invoice->id }}')"
                                            class="p-3 flex items-center justify-between cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                            <div class="flex items-center gap-2">
                                                <svg id="invoice-caret-{{ $row['agent']->id }}-{{ $invoice->id }}" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                                <div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">Invoice</div>
                                                    <div class="font-semibold tracking-wide">{{ $invoice->invoice_number }}</div>
                                                    <div class="mt-0.5 flex items-center gap-2">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                                            bg-gray-200 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                            {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}
                                                        </span>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                                            {{ $invoice->status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200'
                                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200' }}">
                                                            {{ ucfirst($invoice->status) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-4 gap-4 text-right">
                                                <div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">Amount</div>
                                                    <div class="font-semibold">{{ number_format($invoice->amount, 3) }} KWD</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">Paid Invoice</div>
                                                    <div class="font-semibold">
                                                        {{ $invoice->status === 'paid' ? number_format($invoice->amount, 3) : number_format($invoice->paid_amount ?? 0, 3) }} KWD
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">Profit</div>
                                                    <div class="font-semibold text-emerald-600">{{ number_format($invoice->computed_profit ?? 0, 3) }} KWD</div>
                                                </div>
                                                <div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">Commission</div>
                                                    <div class="font-semibold text-blue-600">
                                                        {{ number_format($invoice->computed_commission ?? 0, 3) }} KWD
                                                        @if(($row['agent']->type_id ?? null) == 3)
                                                            <span class="text-[11px] text-gray-500">rate part</span>
                                                        @endif
                                                        @if(($row['agent']->type_id ?? null) == 4)
                                                            <span class="text-[11px] text-gray-500">prorated</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="invoice-tasks-{{ $row['agent']->id }}-{{ $invoice->id }}" class="hidden px-3 pb-3">
                                            @forelse($invoice->invoiceDetails as $detail)
                                            @continue(!$detail->task)
                                            <div class="mt-2 rounded border border-gray-200 dark:border-gray-700 p-3">
                                                <div class="flex flex-col md:flex-row md:justify-between items-start md:items-center gap-3 border-b border-gray-200 dark:border-gray-700 pb-2">
                                                    <div class="space-y-1">
                                                        <div class="text-sm">
                                                            <span class="text-gray-500 dark:text-gray-400">Task:</span>
                                                            <span class="font-semibold text-gray-800 dark:text-gray-100">
                                                                #{{ $detail->task->reference ?? $detail->task->id }}
                                                            </span>
                                                        </div>
                                                        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-[13px] text-gray-700 dark:text-gray-300">
                                                            @if(!empty($detail->task->passenger_name))
                                                            <div><span class="font-medium">Passenger:</span> {{ $detail->task->passenger_name }}</div>
                                                            @endif
                                                            @if(!empty($detail->task->ticket_number))
                                                            <div><span class="font-medium">Ticket:</span> {{ $detail->task->ticket_number }}</div>
                                                            @endif

                                                            <div><span class="font-medium">Type:</span> {{ ucfirst($detail->task->type ?? '—') }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-4 text-right">
                                                        <div>
                                                            <div class="text-[11px] text-gray-700 dark:text-gray-400 tracking-wide">Task Price</div>
                                                            <div class="font-semibold text-gray-900 dark:text-gray-100">
                                                                {{ number_format($detail->task_price, 3) }} KWD
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="text-[11px] text-gray-700 dark:text-gray-400 tracking-wide">Cost</div>
                                                            <div class="font-semibold text-gray-900 dark:text-gray-100">
                                                                {{ number_format($detail->supplier_price, 3) }} KWD
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                @if($detail->task->flightDetail->isNotEmpty())
                                                <div class="mt-2 p-2 rounded-md bg-blue-50 dark:bg-blue-900/20">
                                                    <div class="text-xs font-semibold mb-2 text-blue-700 dark:text-blue-300">Flight Details</div>
                                                    @foreach($detail->task->flightDetail as $flightDetail)
                                                    <div class="mb-2 last:mb-0 border border-blue-100 dark:border-blue-800 rounded-md p-2 bg-white/40 dark:bg-blue-950/10">
                                                        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-1 text-gray-700 dark:text-gray-200 text-sm leading-tight">
                                                            @if($flightDetail->departure_time)
                                                            <div>
                                                                <span class="font-semibold">Departure:</span> {{ \Carbon\Carbon::parse($flightDetail->departure_time)->format('d-m-Y H:i') }}
                                                            </div>
                                                            @endif
                                                            @if($flightDetail->airport_from)
                                                                <div><span class="font-semibold">Airport From:</span> {{ $flightDetail->airport_from }}</div>
                                                            @endif
                                                            @if($flightDetail->terminal_from)
                                                                <div><span class="font-semibold">Terminal From:</span> (T{{ $flightDetail->terminal_from }})</div>
                                                            @endif
                                                            @if($flightDetail->arrival_time)
                                                                <div><span class="font-semibold">Arrival:</span> {{ \Carbon\Carbon::parse($flightDetail->arrival_time)->format('d-m-Y H:i') }}</div>
                                                            @endif
                                                            @if($flightDetail->airport_to)
                                                                <div><span class="font-semibold">Airport To:</span> {{ $flightDetail->airport_to }}</div>
                                                            @endif
                                                            @if($flightDetail->terminal_to)
                                                                <div><span class="font-semibold">Terminal To:</span> (T{{ $flightDetail->terminal_to }})</div>
                                                            @endif
                                                            @if($flightDetail->duration_time)
                                                                <div><span class="font-semibold">Duration:</span> {{ $flightDetail->duration_time }}</div>
                                                            @endif
                                                            @if($flightDetail->flight_number)
                                                                <div><span class="font-semibold">Flight No:</span> {{ $flightDetail->flight_number }}</div>
                                                            @endif
                                                            @if($flightDetail->class_type)
                                                                <div><span class="font-semibold">Class:</span> {{ ucfirst($flightDetail->class_type) }}</div>
                                                            @endif
                                                            @if($flightDetail->baggage_allowed)
                                                                <div><span class="font-semibold">Baggage:</span> {{ $flightDetail->baggage_allowed }}</div>
                                                            @endif
                                                            @if($flightDetail->equipment)
                                                                <div><span class="font-semibold">Equipment:</span> {{ $flightDetail->equipment }}</div>
                                                            @endif
                                                            @if($flightDetail->flight_meal)
                                                                <div><span class="font-semibold">Meal:</span> {{ $flightDetail->flight_meal }}</div>
                                                            @endif
                                                            @if($flightDetail->seat_no)
                                                                <div><span class="font-semibold">Seat:</span> {{ $flightDetail->seat_no }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @endforeach
                                                </div>
                                                @endif

                                                @php
                                                    $hotelDetails = $detail->task->hotelDetails ?? null;
                                                    $room = null;
                                                    if (!empty($detail->task->hotelDetails->room_details)) {
                                                        $decoded = json_decode($detail->task->hotelDetails->room_details, true);
                                                        if (is_array($decoded)) {
                                                            $room = isset($decoded[0]) ? $decoded[0] : $decoded;
                                                        }
                                                    }
                                                @endphp
                                                @if($hotelDetails)
                                                <div class="mt-3 p-3 rounded-md bg-amber-50 dark:bg-amber-900/20">
                                                    <div class="text-xs font-semibold mb-2 text-amber-700 dark:text-amber-300">Hotel Details</div>
                                                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2 text-sm">
                                                        @if($hotelDetails?->hotel?->name)<div>Hotel: {{ $hotelDetails->hotel->name }}</div>@endif
                                                        @if($hotelDetails?->check_in)<div>Check-in: {{ $hotelDetails->check_in }}</div>@endif
                                                        @if($hotelDetails?->check_out)<div>Check-out: {{ $hotelDetails->check_out }}</div>@endif
                                                        @if($hotelDetails?->booking_time)<div>Booking Time: {{ $hotelDetails->booking_time }}</div>@endif
                                                        @if(!empty($room))
                                                            @if(!empty($room['name']))<div>Room: {{ $room['name'] }}</div>@endif
                                                            @if(!empty($room['board']))<div>Board: {{ $room['board'] }}</div>@endif
                                                            @if(!empty($room['passengers']))
                                                                <div>Passengers:
                                                                    @if(is_array($room['passengers']))
                                                                        {{ implode(', ', $room['passengers']) }}
                                                                    @else
                                                                        {{ $room['passengers'] }}
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </div>
                                                @endif

                                                @if($detail->task->visaDetails)
                                                <div class="mt-3 p-3 rounded-md bg-purple-50 dark:bg-purple-900/20">
                                                    <div class="text-xs font-semibold mb-2 text-purple-700 dark:text-purple-300">Visa Details</div>
                                                    <div class="grid sm:grid-cols-2 gap-2 lg:grid-cols-4 text-sm">
                                                        @if($detail->task->visaDetails->issuing_country)<div>Issuing Country: {{ $detail->task->visaDetails->issuing_country }}</div>@endif
                                                        @if($detail->task->visaDetails->stay_duration)<div>Duration of Stay: {{ $detail->task->visaDetails->stay_duration }} days</div>@endif
                                                        @if($detail->task->visaDetails->number_of_entries)
                                                            <div>Number of Entries: {{ $detail->task->visaDetails->number_of_entries }}</div>
                                                        @endif
                                                        @if($detail->task->visaDetails->expiry_date)<div>Expiry Date: {{ $detail->task->visaDetails->expiry_date }}</div>@endif
                                                        @if($detail->task->visaDetails->application_number)
                                                            <div>Application Number: {{ $detail->task->visaDetails->application_number }}</div>
                                                        @endif
                                                        @if($detail->task->visaDetails->visa_type)<div>Type: {{ $detail->task->visaDetails->visa_type }}</div>@endif
                                                    </div>
                                                </div>
                                                @endif

                                                @if($detail->task->insuranceDetails)
                                                <div class="mt-3 p-3 rounded-md bg-sky-50 dark:bg-sky-900/20">
                                                    <div class="text-xs font-semibold mb-2 text-sky-700 dark:text-sky-300">Insurance Details</div>
                                                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2 text-sm">
                                                        @if($detail->task->insuranceDetails->paid_leaves)<div>Paid Leaves: {{ $detail->task->insuranceDetails->paid_leaves }}</div>@endif
                                                        @if($detail->task->insuranceDetails->document_reference)
                                                            <div>Document Reference: {{ $detail->task->insuranceDetails->document_reference }}</div>
                                                        @endif
                                                        @if($detail->task->insuranceDetails->insurance_type)<div>Type: {{ $detail->task->insuranceDetails->insurance_type }}</div>@endif
                                                        @if($detail->task->insuranceDetails->destination)<div>Destination: {{ $detail->task->insuranceDetails->destination }}</div>@endif
                                                        @if($detail->task->insuranceDetails->plan_type)<div>Plan Type: {{ $detail->task->insuranceDetails->plan_type }}</div>@endif
                                                        @if($detail->task->insuranceDetails->duration)<div>Duration: {{ $detail->task->insuranceDetails->duration }}</div>@endif
                                                        @if($detail->task->insuranceDetails->package)<div>Package: {{ $detail->task->insuranceDetails->package }}</div>@endif
                                                    </div>
                                                </div>
                                                @endif
                                            </div>
                                            @empty
                                            <div class="text-sm text-gray-500 dark:text-gray-400">No tasks in this invoice.</div>
                                            @endforelse
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 mb-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-3">Refunds</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-gray-900 dark:text-gray-100">
                <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-200">
                    <tr class="px-3 py-2 text-left">
                        <th>Refund Date</th>
                        <th>Refund Number</th>
                        <th>Original Invoice</th>
                        <th>Client</th>
                        <th>Agent</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>New Invoice</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/80 dark:divide-gray-700">
                    @forelse($refunds as $refund)
                    <tr class="bg-white/70 dark:bg-gray-800/70 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer px-3 py-2"
                        onclick="toggleRefundRow('{{ $refund->id }}')">
                        <td>{{ \Carbon\Carbon::parse($refund->created_at)->format('d-m-Y') }}</td>
                        <td>
                            <a href="{{ $refund->links['view_refund'] }}" class="text-blue-400 font-medium hover:text-blue-500 hover:underline" target="_blank"
                                onclick="event.stopPropagation()">{{ $refund->refund_number }}</a>
                        </td>
                        <td>
                            @if($refund->original_invoice_number)
                                <a href="{{ $refund->links['view_original'] }}" class="text-blue-500 font-medium hover:text-blue-600 hover:underline" target="_blank"
                                    onclick="event.stopPropagation()">{{ $refund->original_invoice_number }}</a>
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                    {{ $refund->original_invoice_status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200'
                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200' }}">
                                    {{ $refund->original_invoice_status ?? 'N/A' }}
                                </span>
                            @else
                                <span class="text-gray-500">N/A</span>
                            @endif
                        </td>
                        <td>{{ $refund->invoice?->client?->full_name ?? $refund->task?->client?->full_name ?? 'N/A' }}</td>
                        <td>{{ $refund->invoice?->agent?->name ?? $refund->task?->agent?->name ?? 'N/A' }}</td>
                        <td>{{ $refund->refund_type }}</td>
                        <td>{{ number_format($refund->total_nett_refund, 3) }}</td>
                        <td>
                            @if($refund->refund_invoice_number)
                                <a href="{{ $refund->links['view_refund_inv'] }}" class="text-blue-500 font-medium hover:text-blue-600 hover:underline" target="_blank"
                                    onclick="event.stopPropagation()">{{ $refund->refund_invoice_number }}</a>
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                    {{ $refund->original_invoice_status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200'
                                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200' }}">
                                    {{ ucfirst($refund->refund_invoice_status) }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-200">
                                    Not Applicable
                                </span>
                            @endif
                        </td>
                    </tr>
                    <tr id="refund-details-{{ $refund->id }}" class="hidden">
                        <td colspan="8" class="p-0">
                            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
                                <div class="grid gap-4 lg:grid-cols-12 text-sm">
                                    <div class="lg:col-span-3 space-y-2">

                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Method</div>
                                        <div class="font-medium">{{ $refund->method }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Reference</div>
                                        <div class="font-medium">{{ $refund->reference }}</div>
                                    </div>
                                    </div>
                                    <div class="lg:col-span-5 grid sm:grid-cols-2 gap-3">
                                    <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-white/60 dark:bg-gray-800/60 p-3">
                                        <div class="text-[11px] tracking-wide uppercase text-gray-500 dark:text-gray-400">Original Invoice Price</div>
                                        <div class="font-semibold">{{ number_format($refund->airline_nett_fare, 3) }}</div>
                                    </div>
                                    <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-white/60 dark:bg-gray-800/60 p-3">
                                        <div class="text-[11px] tracking-wide uppercase text-gray-500 dark:text-gray-400">Original Task Cost</div>
                                        <div class="font-semibold">{{ number_format($refund->task->originalTask->total, 3) }}</div>
                                    </div>
                                    <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-white/60 dark:bg-gray-800/60 p-3">
                                        <div class="text-[11px] tracking-wide uppercase text-gray-500 dark:text-gray-400">Original Profit</div>
                                        <div class="font-semibold text-blue-600">{{ number_format($refund->original_task_profit, 3) }}</div>
                                    </div>
                                    <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-white/60 dark:bg-gray-800/60 p-3">
                                        <div class="text-[11px] tracking-wide uppercase text-gray-500 dark:text-gray-400">Refund Fee to Client</div>
                                        <div class="font-semibold">{{ number_format($refund->service_charge, 3) }}</div>
                                    </div>
                                    </div>
                                    <div class="lg:col-span-4 grid sm:grid-cols-2 gap-3">
                                    <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-white/60 dark:bg-gray-800/60 p-3">
                                        <div class="text-[11px] tracking-wide uppercase text-gray-500 dark:text-gray-400">Supplier Charge</div>
                                        <div class="font-semibold text-rose-600">{{ number_format($refund->refund_airline_charge, 3) }}</div>
                                    </div>
                                    <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-white/60 dark:bg-gray-800/60 p-3">
                                        <div class="text-[11px] tracking-wide uppercase text-gray-500 dark:text-gray-400">New Profit</div>
                                        <div class="font-semibold text-emerald-600">{{ number_format($refund->new_task_profit, 3) }}</div>
                                    </div>
                                    <div class="sm:col-span-2 rounded-md border border-indigo-200 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-900/20 p-3">
                                        <div class="text-[11px] tracking-wide uppercase text-gray-600 dark:text-gray-300">Total Refund</div>
                                        <div class="text-lg font-bold">{{ number_format($refund->total_nett_refund, 3) }}</div>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                            No refunds for the selected date.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-3">Supplier Performance</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-gray-900 dark:text-gray-100">
                <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left">Supplier</th>
                        <th class="px-3 py-2 text-center">Total Tasks</th>
                        <th class="px-3 py-2 text-right">Total Task Price</th>
                        <th class="px-3 py-2 text-right">Paid Invoice</th>
                        <th class="px-3 py-2 text-right">Account Payable</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/80 dark:divide-gray-700">
                    @foreach($suppliers as $row)
                    <tr class="bg-white/70 dark:bg-gray-800/70">
                        <td class="px-3 py-2 font-medium">{{ $row['supplier']->name }}</td>
                        <td class="px-3 py-2 text-center">{{ $row['totalTasks'] }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($row['totalTaskPrice'], 3) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($row['paid'], 3) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($row['accountPayable'], 3) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <style>
        @media print {

            html,
            body {
                color-scheme: light !important;
            }

            .shadow-sm,
            .shadow,
            .shadow-md,
            .shadow-lg {
                box-shadow: none !important;
            }

            .rounded-xl,
            .rounded-lg,
            .rounded-md {
                border-radius: 8px !important;
            }

            table {
                page-break-inside: avoid;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
    <script>
        function toggleAgentRow(agentId) {
            const row = document.getElementById('agent-details-' + agentId);
            const caret = document.getElementById('agent-caret-' + agentId);
            row.classList.toggle('hidden');
            caret.classList.toggle('rotate-180');
        }

        function toggleInvoiceTasks(agentId, invoiceId) {
            const wrap = document.getElementById(`invoice-tasks-${agentId}-${invoiceId}`);
            const caret = document.getElementById(`invoice-caret-${agentId}-${invoiceId}`);
            wrap.classList.toggle('hidden');
            caret.classList.toggle('rotate-180');
        }

        function toggleRefundRow(id) {
            const row = document.getElementById('refund-details-' + id);
            if (row) row.classList.toggle('hidden');
        }
    </script>
</x-app-layout>