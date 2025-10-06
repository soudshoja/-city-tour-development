@php
    $fmt = fn($n) => number_format((float)($n ?? 0), 3);
    $dateStr = \Carbon\Carbon::parse($date)->format('d-m-Y');
    $generatedAt = now()->format('d-m-Y H:i');
    $companyName = $company->name ?? 'Company';
    $hasGroups = !empty($suppliers) && \Illuminate\Support\Arr::flatten($suppliers) !== [];
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Daily Sales Report ({{ $dateStr }})</title>
    <style>
        :root {
            --ink: #111;
            --muted: #666;
            --line: #e6e6e6;
            --bg-head: #f7f7f7;
            --bg-subtle: #fbfbfb;
        }

        @page {
            margin: 18mm 14mm 22mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: var(--ink);
        }

        .cover-header {
            text-align: center;
            margin: 0 0 10px;
        }

        .cover-name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: .2px;
        }

        .cover-sub {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }

        .cover-title {
            font-size: 15px;
            font-weight: 700;
            margin-top: 4px;
        }

        h1,
        h2,
        h3 {
            margin: 0 0 6px;
            line-height: 1.1;
        }

        h1 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        h2 {
            font-size: 13px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
            margin-top: 10px;
        }

        h3 {
            font-size: 12px;
            margin-top: 8px;
        }

        .muted {
            color: var(--muted);
        }

        .row {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .col {
            display: table-cell;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .soft {
            color: #333;
        }

        .kpis {
            margin: 6px 0 10px;
        }

        .kpi {
            display: inline-block;
            border: 1px solid #ddd;
            padding: 6px 8px;
            margin: 2px 4px 0 0;
            border-radius: 4px;
        }

        .kpi .lbl {
            font-size: 10px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .kpi .val {
            font-size: 13px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid var(--line);
            padding: 5px 6px;
        }

        th {
            background: var(--bg-head);
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .2px;
        }

        td {
            font-size: 10.5px;
        }

        .tight td,
        .tight th {
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .subtle {
            background: var(--bg-subtle);
        }

        .chip {
            display: inline-block;
            padding: 1px 6px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 9.5px;
        }

        .ok {
            background: #eef9f1;
            border-color: #cdebd7;
        }

        .warn {
            background: #fff6e6;
            border-color: #fde3b0;
        }

        .bad {
            background: #fde8ea;
            border-color: #f5c2c7;
        }

        tbody tr:nth-child(even) td {
            background: #fcfcfc;
        }

        .no-break,
        .table-block {
            page-break-inside: avoid;
        }

        tr {
            page-break-inside: avoid;
        }

        .note {
            font-size: 9.5px;
            color: var(--muted);
        }

        .section {
            margin-top: 8px;
        }

        .totals td {
            font-weight: 700;
            background: #f4f4f4;
        }

        .task-table th,
        .task-table td {
            font-size: 9.8px;
        }

        .num {
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>

<body>
    <div class="cover-header no-break">
        @if(!empty($company?->logo_url))
            <div style="margin-bottom:6px;">
                <img src="{{ $company->logo_url }}" alt="Logo" style="height:30px;">
            </div>
        @endif
        <div class="cover-name">{{ $companyName }}</div>
        <div class="cover-title">Daily Sales Report</div>
        <div class="cover-sub">
            Period: {{ $dateStr }} &nbsp;&nbsp;|&nbsp;&nbsp;
            <!-- Report ID: DS-{{ \Carbon\Carbon::parse($date)->format('Ymd') }} &nbsp;&nbsp;|&nbsp;&nbsp; -->
            Generated: {{ $generatedAt }}
        </div>
    </div>
    <div class="kpis">
        <span class="kpi">
            <div class="lbl">Total Paid</div>
            <div class="val num">{{ $fmt($summary['totalPaid'] ?? 0) }} KWD</div>
        </span>
        <span class="kpi">
            <div class="lbl">Profit</div>
            <div class="val num">{{ $fmt($summary['profit'] ?? 0) }} KWD</div>
        </span>
        <span class="kpi">
            <div class="lbl">Top Agent</div>
            <div class="val">{{ $summary['topAgent'] ?? '-' }} — <span class="num">{{ $fmt($summary['topAgentAmount'] ?? 0) }}</span> KWD</div>
        </span>
        <span class="kpi">
            <div class="lbl">Top Supplier</div>
            <div class="val">{{ $summary['topSupplier'] ?? '-' }} — <span class="num">{{ $fmt($summary['topSupplierAmount'] ?? 0) }}</span> KWD</div>
        </span>
    </div>

    <table class="tight no-break" style="margin-bottom:8px;">
        <thead>
            <tr>
                <th class="center">Invoices</th>
                <th class="right">Total Invoiced</th>
                <th class="right">Paid (Cash)</th>
                <th class="right">Paid (Gateway)</th>
                <th class="right">Client Credit</th>
                <th class="right">Refunds</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="center">{{ $summary['totalInvoices'] ?? 0 }}</td>
                <td class="right num">{{ $fmt($summary['totalInvoiced'] ?? 0) }}</td>
                <td class="right num">{{ $fmt($summary['cashSum'] ?? 0) }}</td>
                <td class="right num">{{ $fmt($summary['gatewaySum'] ?? 0) }}</td>
                <td class="right num">{{ $fmt($summary['creditSum'] ?? 0) }}</td>
                <td class="right num">{{ $fmt($summary['refunds'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section no-break">
        <h2>Agents</h2>
        <table class="tight">
            <thead>
                <tr>
                    <th>Agent</th>
                    <th class="center">Inv</th>
                    <th class="right">Invoiced</th>
                    <th class="right">Paid</th>
                    <th class="right">Unpaid</th>
                    <th class="right">Profit</th>
                    <th class="right">Commission</th>
                    <th class="right">Top-ups</th>
                </tr>
            </thead>
            <tbody>
                @foreach($agents as $row)
                <tr>
                    <td>{{ $row['agent']->name }}</td>
                    <td class="center">{{ $row['totalInvoices'] }}</td>
                    <td class="right num">{{ $fmt($row['totalInvoiced']) }}</td>
                    <td class="right num">{{ $fmt($row['paid']) }}</td>
                    <td class="right num">{{ $fmt($row['unpaid']) }}</td>
                    <td class="right num">{{ $fmt($row['profit']) }}</td>
                    <td class="right num">{{ $fmt($row['commission']) }}</td>
                    <td class="right num">{{ $fmt($row['topupCollected']) }}</td>
                </tr>
                @endforeach

                @php
                    $tInv=$tInvo=$tPaid=$tUnp=$tProf=$tComm=$tTop=0;
                    foreach($agents as $_r){
                        $tInv += $_r['totalInvoices'];
                        $tInvo += $_r['totalInvoiced'];
                        $tPaid += $_r['paid'];
                        $tUnp += $_r['unpaid'];
                        $tProf += $_r['profit'];
                        $tComm += $_r['commission'];
                        $tTop += $_r['topupCollected'];
                    }
                @endphp
                <tr class="totals">
                    <td>Total</td>
                    <td class="center">{{ $tInv }}</td>
                    <td class="right num">{{ $fmt($tInvo) }}</td>
                    <td class="right num">{{ $fmt($tPaid) }}</td>
                    <td class="right num">{{ $fmt($tUnp) }}</td>
                    <td class="right num">{{ $fmt($tProf) }}</td>
                    <td class="right num">{{ $fmt($tComm) }}</td>
                    <td class="right num">{{ $fmt($tTop) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @foreach($agents as $row)
        @if(!$row['invoices']->isEmpty())
        <div class="section no-break">
            <h3>Invoices — {{ $row['agent']->name }}</h3>
            <table class="tight">
                <thead>
                    <tr>
                        <th style="width:24%">Invoice / Client</th>
                        <th class="center" style="width:8%">Status</th>
                        <th class="right" style="width:12%">Amount</th>
                        <th class="right" style="width:12%">Paid Inv</th>
                        <th class="right" style="width:12%">Profit</th>
                        <th class="right" style="width:12%">Commission</th>
                        <th class="right" style="width:12%">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($row['invoices'] as $invoice)
                    <tr class="subtle no-break">
                        <td>
                            <span class="soft">{{ $invoice->invoice_number }}</span>
                            <div class="soft">{{ $invoice->client->full_name ?? '—' }}</div>
                        </td>
                        <td class="center">
                            @php $st=strtolower($invoice->status); @endphp
                            <span class="chip {{ $st==='paid'?'ok':'warn' }}">{{ ucfirst($invoice->status) }}</span>
                        </td>
                        <td class="right num">{{ $fmt($invoice->amount) }}</td>
                        <td class="right num">
                            {{ $invoice->status==='paid' ? $fmt($invoice->amount) : $fmt($invoice->paid_amount ?? 0) }}
                        </td>
                        <td class="right num">{{ $fmt($invoice->computed_profit ?? 0) }}</td>
                        <td class="right num">{{ $fmt($invoice->computed_commission ?? 0) }}</td>
                        <td class="right">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</td>
                    </tr>

                    @if($invoice->invoiceDetails->isNotEmpty())
                    <tr class="no-break">
                        <td colspan="7" style="padding:0;">
                            <table class="task-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th style="width:30%;">Task</th>
                                        <th style="width:14%;" class="center">Type</th>
                                        <th style="width:14%;" class="right">Task Price</th>
                                        <th style="width:14%;" class="right">Cost</th>
                                        <th style="width:14%;" class="right">Markup</th>
                                        <th style="width:14%;" class="right">Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoice->invoiceDetails as $detail)
                                    @continue(!$detail->task)
                                    @php
                                    $mk = ($detail->task_price ?? 0) - ($detail->supplier_price ?? 0);
                                    $supplierName = $detail->task->supplier->name ?? '';
                                    $ref = $detail->task->reference ?? $detail->task->id;
                                    @endphp
                                    <tr>
                                        <td>#{{ $ref }} {{ $detail->task->passenger_name ? ' — '.$detail->task->passenger_name : '' }}</td>
                                        <td class="center">{{ ucfirst($detail->task->type ?? '—') }}</td>
                                        <td class="right num">{{ $fmt($detail->task_price) }}</td>
                                        <td class="right num">{{ $fmt($detail->supplier_price) }}</td>
                                        <td class="right num">{{ $fmt($mk) }}</td>
                                        <td class="right">{{ $supplierName ?: '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>

            <div class="note" style="margin-top:4px;">
                {{ $row['invoices']->count() }} invoice(s) — Amount:
                <span class="num">{{ $fmt($row['invoices']->sum('amount')) }}</span> KWD · Paid Inv:
                <span class="num">{{ $fmt($row['invoices']->where('status','paid')->sum('amount')) }}</span> KWD
            </div>
        </div>
        @endif
    @endforeach

    @if(!empty($refunds) && $refunds->count())
    <div class="section no-break">
        <h2>Refunds</h2>
        <table class="tight">
            <thead>
                <tr>
                    <th style="width:12%;">Date</th>
                    <th style="width:14%;">Refund #</th>
                    <th style="width:16%;">Original Inv</th>
                    <th style="width:18%;">Client</th>
                    <th style="width:14%;">Agent</th>
                    <th style="width:10%;">Type</th>
                    <th class="right" style="width:12%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($refunds as $rf)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($rf->created_at)->format('d-m-Y') }}</td>
                    <td>{{ $rf->refund_number }}</td>
                    <td>
                        {{ $rf->original_invoice_number ?? '—' }}
                        <span class="chip {{ ($rf->original_invoice_status ?? '')==='paid'?'ok':'warn' }}">
                            {{ $rf->original_invoice_status ?? 'N/A' }}
                        </span>
                    </td>
                    <td>{{ $rf->invoice?->client?->full_name ?? $rf->task?->client?->full_name ?? 'N/A' }}</td>
                    <td>{{ $rf->invoice?->agent?->name ?? $rf->task?->agent?->name ?? 'N/A' }}</td>
                    <td>{{ $rf->refund_type }}</td>
                    <td class="right num">{{ $fmt($rf->total_nett_refund) }}</td>
                </tr>
                @endforeach

                @php $refundTotal = $refunds->sum('total_nett_refund'); @endphp
                <tr class="totals">
                    <td colspan="6" class="right">Total Refunds</td>
                    <td class="right num">{{ $fmt($refundTotal) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    @if($hasGroups)
    <div class="section no-break">
        <h2>Suppliers — Grouped by Type</h2>

        @foreach($suppliers as $type => $group)
        @php
            $gt = $group['totals'] ?? ['totalTasks'=>0,'totalTaskPrice'=>0,'paid'=>0,'unpaid'=>0];
        @endphp
        <div class="table-block" style="margin-top:6px;">
            <table class="tight" style="margin-bottom:6px;">
                <thead>
                    <tr>
                        <th colspan="5" style="text-align:left;">
                            {{ $type ?? 'Uncategorized' }}
                            <span class="chip" style="margin-left:6px;">Tasks: {{ number_format($gt['totalTasks'] ?? 0) }}</span>
                        </th>
                    </tr>
                    <tr>
                        <th>Supplier</th>
                        <th class="center" style="width:12%;">Total Tasks</th>
                        <th class="right" style="width:18%;">Supplier Cost (Task Price)</th>
                        <th class="right" style="width:18%;">Paid</th>
                        <th class="right" style="width:18%;">Unpaid</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($group['rows'] ?? []) as $row)
                        <tr class="subtle">
                            <td>{{ $row['supplier_account_name'] ?? '—' }}</td>
                            <td class="center">{{ $row['totalTasks'] ?? 0 }}</td>
                            <td class="right num">{{ $fmt($row['totalTaskPrice'] ?? 0) }}</td>
                            <td class="right num">{{ $fmt($row['paid'] ?? 0) }}</td>
                            <td class="right num">{{ $fmt($row['unpaid'] ?? 0) }}</td>
                        </tr>

                        @if(!empty($row['accounts']))
                        <tr>
                            <td colspan="5" style="padding:0;">
                                <table class="task-table" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th style="width:20%;">Account</th>
                                            <th style="width:12%;" class="right">Debit</th>
                                            <th style="width:12%;" class="right">Credit</th>
                                            <th style="width:14%;">Transaction Date</th>
                                            <th style="width:14%;">Task Date</th>
                                            <th>Reference / Client</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($row['accounts'] as $acc)
                                            <tr>
                                                <td><strong>{{ $acc['account']['name'] ?? '—' }}</strong></td>
                                                <td class="right num">{{ $fmt($acc['debit'] ?? 0) }}</td>
                                                <td class="right num">{{ $fmt($acc['credit'] ?? 0) }}</td>
                                                <td colspan="3"></td>
                                            </tr>
                                            @foreach(($acc['entries'] ?? []) as $e)
                                                <tr>
                                                    <td class="soft">{{ $acc['account']['name'] ?? '—' }}</td>
                                                    <td class="right num">{{ $fmt($e['debit'] ?? 0) }}</td>
                                                    <td class="right num">{{ $fmt($e['credit'] ?? 0) }}</td>
                                                    <td>{{ $e['transaction_date'] ? \Carbon\Carbon::parse($e['transaction_date'])->format('d-m-Y') : '—' }}</td>
                                                    <td>{{ $e['supplier_pay_date'] ? \Carbon\Carbon::parse($e['supplier_pay_date'])->format('d-m-Y') : '—' }}</td>
                                                    <td>
                                                        <span class="soft">{{ $e['reference'] ?? '—' }}</span>
                                                        @if(!empty($e['client_name'])) — {{ $e['client_name'] }} @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    <tr class="totals">
                        <td>Total — {{ $type ?? 'Uncategorized' }}</td>
                        <td class="center">{{ number_format($gt['totalTasks'] ?? 0) }}</td>
                        <td class="right num">{{ $fmt($gt['totalTaskPrice'] ?? 0) }}</td>
                        <td class="right num">{{ $fmt($gt['paid'] ?? 0) }}</td>
                        <td class="right num">{{ $fmt($gt['unpaid'] ?? 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endforeach
    </div>
    @else
    @if(!empty($suppliers))
    <div class="section no-break">
        <h2>Suppliers</h2>
        <table class="tight">
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th class="center" style="width:12%;">Tasks</th>
                    <th class="right" style="width:18%;">Task Price</th>
                    <th class="right" style="width:18%;">Paid Inv</th>
                    <th class="right" style="width:18%;">Account Payable</th>
                </tr>
            </thead>
            <tbody>
                @foreach($suppliers as $row)
                <tr>
                    <td>{{ $row['supplier']->name }}</td>
                    <td class="center">{{ $row['totalTasks'] }}</td>
                    <td class="right num">{{ $fmt($row['totalTaskPrice']) }}</td>
                    <td class="right num">{{ $fmt($row['paid']) }}</td>
                    <td class="right num">{{ $fmt($row['accountPayable']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="section note">Suppliers: No data for the selected date.</div>
    @endif
    @endif

    <div class="note" style="margin-top:10px;">
        Notes: Compact PDF intended for daily review. Nested details show only essentials to keep size small.
    </div>

</body>

</html>