@php
$fmt = fn($n) => number_format((float)$n, 3);
$d = \Carbon\Carbon::parse($date)->format('d-m-Y');
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Daily Sales Report — {{ $d }}</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: DejaVu Sans, Arial, sans-serif;
        }

        body {
            margin: 20px;
            font-size: 12px;
            color: #111;
        }

        h1,
        h2,
        h3 {
            margin: 0 0 6px;
        }

        .muted {
            color: #555;
        }

        .grid {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .col {
            display: table-cell;
            vertical-align: top;
            padding-right: 10px;
        }

        .card {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .kpi {
            font-size: 20px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px 8px;
        }

        th {
            background: #f5f5f5;
            text-align: left;
        }

        .tright {
            text-align: right;
        }

        .tcenter {
            text-align: center;
        }

        .section {
            margin-top: 16px;
        }

        .subtle {
            background: #fafafa;
        }

        .pill {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 11px;
        }

        .mb4 {
            margin-bottom: 4px;
        }
    </style>
</head>

<body>

    {{-- Header --}}
    <div class="grid" style="margin-bottom:12px;">
        <div class="col" style="width:60%">
            <h1>Daily Sales Report</h1>
            <div class="muted">Date: <strong>{{ $d }}</strong></div>
            <div class="muted">Generated: {{ now()->format('d-m-Y H:i') }}</div>
        </div>
        <div class="col" style="width:40%">
            <div class="card">
                <div class="grid">
                    <div class="col">
                        <div class="muted">Total Invoices</div>
                        <div class="kpi">{{ $summary['totalInvoices'] }}</div>
                    </div>
                    <div class="col">
                        <div class="muted">Total Invoiced</div>
                        <div class="kpi">{{ $fmt($summary['totalInvoiced']) }} KWD</div>
                    </div>
                </div>
                <div class="grid" style="margin-top:6px;">
                    <div class="col">
                        <div class="muted">Total Paid</div>
                        <div class="kpi">{{ $fmt($summary['totalPaid']) }} KWD</div>
                    </div>
                    <div class="col">
                        <div class="muted">Profit</div>
                        <div class="kpi">{{ $fmt($summary['profit']) }} KWD</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Collections breakdown --}}
    <div class="card">
        <h3>Collections Breakdown</h3>
        <div class="mb4">
            <span class="pill">Cash: <strong>{{ $fmt($summary['cashSum'] ?? 0) }}</strong> KWD</span>
            <span class="pill">Gateway: <strong>{{ $fmt($summary['gatewaySum'] ?? 0) }}</strong> KWD</span>
            <span class="pill">Client Credit: <strong>{{ $fmt($summary['creditSum'] ?? 0) }}</strong> KWD</span>
            <span class="pill">Refunds: <strong>{{ $fmt($summary['refunds'] ?? 0) }}</strong> KWD</span>
        </div>
        <div>
            <span class="pill">Top Agent: <strong>{{ $summary['topAgent'] ?? '-' }}</strong> ({{ $fmt($summary['topAgentAmount'] ?? 0) }} KWD)</span>
            <span class="pill">Top Supplier: <strong>{{ $summary['topSupplier'] ?? '-' }}</strong> ({{ $fmt($summary['topSupplierAmount'] ?? 0) }} KWD)</span>
        </div>
    </div>

    <div class="section">
        <h2>Agent Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Agent</th>
                    <th class="tcenter">Total Invoices</th>
                    <th class="tright">Total Invoiced</th>
                    <th class="tright">Paid</th>
                    <th class="tright">Unpaid</th>
                    <th class="tright">Profit</th>
                    <th class="tright">Commission</th>
                    <th class="tright">Top-ups</th>
                </tr>
            </thead>
            <tbody>
                @foreach($agents as $row)
                <tr>
                    <td><strong>{{ $row['agent']->name }}</strong></td>
                    <td class="tcenter">{{ $row['totalInvoices'] }}</td>
                    <td class="tright">{{ $fmt($row['totalInvoiced']) }}</td>
                    <td class="tright">{{ $fmt($row['paid']) }}</td>
                    <td class="tright">{{ $fmt($row['unpaid']) }}</td>
                    <td class="tright">{{ $fmt($row['profit']) }}</td>
                    <td class="tright">{{ $fmt($row['commission']) }}</td>
                    <td class="tright">{{ $fmt($row['topupCollected']) }}</td>
                </tr>

                {{-- Show each agent's invoices with tasks immediately below --}}
                @foreach($row['invoices'] as $inv)
                <tr class="subtle">
                    <td colspan="8">
                        <div><strong>Invoice:</strong> {{ $inv->invoice_number ?? ('#'.$inv->id) }} • {{ \Carbon\Carbon::parse($inv->invoice_date)->format('d-m-Y') }} • {{ ucfirst($inv->status) }}</div>
                        <div class="grid">
                            <div class="col">Amount: <strong>{{ $fmt($inv->amount) }} KWD</strong></div>
                            <div class="col">Paid: <strong>{{ $inv->status === 'paid' ? $fmt($inv->amount) : $fmt($inv->paid_amount ?? 0) }} KWD</strong></div>
                            <div class="col">Profit: <strong>{{ $fmt($inv->computed_profit ?? 0) }} KWD</strong></div>
                        </div>

                        {{-- Tasks --}}
                        @forelse($inv->invoiceDetails as $detail)
                        @php $t = $detail->task; @endphp
                        @continue(!$t)
                        <div class="card" style="margin-top:6px;">
                            <div><strong>Task:</strong> {{ $t->reference ?? ('Task #'.$t->id) }}</div>
                            <div class="grid">
                                <div class="col">Task Price: <strong>{{ $fmt($detail->task_price) }}</strong> KWD</div>
                                <div class="col">Markup: <strong>{{ $fmt($detail->markup_price) }}</strong> KWD</div>
                                @if($t->supplier_price)
                                <div class="col">Cost: <strong>{{ $fmt($t->supplier_price) }}</strong> KWD</div>
                                @endif
                            </div>
                            <div class="muted">
                                @if($t->passenger_name) Passenger: {{ $t->passenger_name }} • @endif
                                @if($t->ticket_number) Ticket: {{ $t->ticket_number }} • @endif
                                @if($t->service_type) Type: {{ ucfirst($t->service_type) }} @endif
                            </div>

                            {{-- Flight details --}}
                            @if(optional($t->flightDetails)->departure_time || optional($t->flightDetails)->arrival_time || optional($t->flightDetails)->flight_number)
                            <div style="margin-top:4px;">
                                <strong>Flight</strong> —
                                @if($t->flightDetails?->departure_time) Departure: {{ $t->flightDetails->departure_time }}; @endif
                                @if($t->flightDetails?->arrival_time) Arrival: {{ $t->flightDetails->arrival_time }}; @endif
                                @if($t->flightDetails?->airport_from) From: {{ $t->flightDetails->airport_from }}@if($t->flightDetails?->terminal_from) (T{{ $t->flightDetails->terminal_from }})@endif; @endif
                                @if($t->flightDetails?->airport_to) To: {{ $t->flightDetails->airport_to }}@if($t->flightDetails?->terminal_to) (T{{ $t->flightDetails->terminal_to }})@endif; @endif
                                @if($t->flightDetails?->duration_time) Duration: {{ $t->flightDetails->duration_time }}; @endif
                                @if($t->flightDetails?->flight_number) Flight No: {{ $t->flightDetails->flight_number }}; @endif
                                @if($t->flightDetails?->class_type) Class: {{ $t->flightDetails->class_type }}; @endif
                                @if($t->flightDetails?->baggage_allowed) Baggage: {{ $t->flightDetails->baggage_allowed }}; @endif
                                @if($t->flightDetails?->equipment) Equipment: {{ $t->flightDetails->equipment }}; @endif
                                @if($t->flightDetails?->flight_meal) Meal: {{ $t->flightDetails->flight_meal }}; @endif
                                @if($t->flightDetails?->seat_no) Seat: {{ $t->flightDetails->seat_no }} @endif
                            </div>
                            @endif

                            {{-- Hotel details --}}
                            @if($t->hotelDetails)
                            @php
                            $room = null;
                            if (!empty($t->hotelDetails->room_details)) {
                            $decoded = json_decode($t->hotelDetails->room_details, true);
                            if (is_array($decoded)) { $room = $decoded[0] ?? $decoded; }
                            }
                            @endphp
                            @if($t->hotelDetails->name || $t->hotelDetails->check_in || $t->hotelDetails->check_out || $room)
                            <div style="margin-top:4px;">
                                <strong>Hotel</strong> —
                                @if($t->hotelDetails->name) Hotel: {{ $t->hotelDetails->name }}; @endif
                                @if($t->hotelDetails->check_in) Check-in: {{ $t->hotelDetails->check_in }}; @endif
                                @if($t->hotelDetails->check_out) Check-out: {{ $t->hotelDetails->check_out }}; @endif
                                @if($t->hotelDetails->booking_time) Booking Time: {{ $t->hotelDetails->booking_time }}; @endif
                                @if($room)
                                @if(!empty($room['name'])) Room: {{ $room['name'] }}; @endif
                                @if(!empty($room['board'])) Board: {{ $room['board'] }}; @endif
                                @if(!empty($room['passengers'])) Passengers:
                                @if(is_array($room['passengers'])) {{ implode(', ', $room['passengers']) }} @else {{ $room['passengers'] }} @endif;
                                @endif
                                @endif
                            </div>
                            @endif
                            @endif

                            {{-- Visa --}}
                            @if($t->visaDetails && (
                            $t->visaDetails->issuing_country || $t->visaDetails->stay_duration || $t->visaDetails->number_of_entries ||
                            $t->visaDetails->expiry_date || $t->visaDetails->application_number || $t->visaDetails->visa_type))
                            <div style="margin-top:4px;">
                                <strong>Visa</strong> —
                                @if($t->visaDetails->issuing_country) Issuing Country: {{ $t->visaDetails->issuing_country }}; @endif
                                @if($t->visaDetails->stay_duration) Stay: {{ $t->visaDetails->stay_duration }}; @endif
                                @if($t->visaDetails->number_of_entries) Entries: {{ $t->visaDetails->number_of_entries }}; @endif
                                @if($t->visaDetails->expiry_date) Expiry: {{ $t->visaDetails->expiry_date }}; @endif
                                @if($t->visaDetails->application_number) Application #: {{ $t->visaDetails->application_number }}; @endif
                                @if($t->visaDetails->visa_type) Type: {{ $t->visaDetails->visa_type }}; @endif
                            </div>
                            @endif

                            {{-- Insurance --}}
                            @if($t->insuranceDetails && (
                            $t->insuranceDetails->paid_leaves || $t->insuranceDetails->document_reference || $t->insuranceDetails->insurance_type ||
                            $t->insuranceDetails->destination || $t->insuranceDetails->plan_type || $t->insuranceDetails->duration || $t->insuranceDetails->package))
                            <div style="margin-top:4px;">
                                <strong>Insurance</strong> —
                                @if($t->insuranceDetails->paid_leaves) Paid Leaves: {{ $t->insuranceDetails->paid_leaves }}; @endif
                                @if($t->insuranceDetails->document_reference) Doc Ref: {{ $t->insuranceDetails->document_reference }}; @endif
                                @if($t->insuranceDetails->insurance_type) Type: {{ $t->insuranceDetails->insurance_type }}; @endif
                                @if($t->insuranceDetails->destination) Destination: {{ $t->insuranceDetails->destination }}; @endif
                                @if($t->insuranceDetails->plan_type) Plan: {{ $t->insuranceDetails->plan_type }}; @endif
                                @if($t->insuranceDetails->duration) Duration: {{ $t->insuranceDetails->duration }}; @endif
                                @if($t->insuranceDetails->package) Package: {{ $t->insuranceDetails->package }}; @endif
                            </div>
                            @endif
                        </div>
                        @empty
                        <div class="muted" style="margin-top:4px;">No tasks in this invoice.</div>
                        @endforelse
                    </td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Refunds --}}
    <div class="section">
        <h2>Refunds</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice</th>
                    <th>Client</th>
                    <th>Agent</th>
                    <th>Type</th>
                    <th class="tright">Amount</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse($refunds as $r)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($r->created_at)->format('d-m-Y') }}</td>
                    <td>{{ $r->invoice->invoice_number ?? 'N/A' }}</td>
                    <td>{{ $r->task->client->full_name ?? 'N/A' }}</td>
                    <td>{{ $r->agent->name ?? 'N/A' }}</td>
                    <td>{{ $r->refund_type ?? '-' }}</td>
                    <td class="tright">{{ $fmt($r->total_nett_refund) }}</td>
                    <td>{{ ucfirst($r->status ?? '-') }}</td>
                    <td>{{ $r->remarks ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="tcenter muted">No refunds for the selected date.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Supplier Performance --}}
    <div class="section">
        <h2>Supplier Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th class="tcenter">Total Tasks</th>
                    <th class="tright">Total Task Price</th>
                    <th class="tright">Total Paid</th>
                    <th class="tright">Account Payable</th>
                </tr>
            </thead>
            <tbody>
                @foreach($suppliers as $row)
                <tr>
                    <td>{{ $row['supplier']->name }}</td>
                    <td class="tcenter">{{ $row['totalTasks'] }}</td>
                    <td class="tright">{{ $fmt($row['totalTaskPrice']) }}</td>
                    <td class="tright">{{ $fmt($row['paid']) }}</td>
                    <td class="tright">{{ $fmt($row['accountPayable']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>

</html>