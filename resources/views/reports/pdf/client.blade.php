<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Client Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #3b82f6;
        }
        .header h1 {
            font-size: 22px;
            color: #1e40af;
            margin-bottom: 8px;
        }
        .header .date-range {
            font-size: 12px;
            color: #4b5563;
            font-weight: bold;
        }
        .header .generated {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 5px;
        }

        .client-section {
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .client-header {
            background-color: #f8fafc;
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        .client-header table {
            width: 100%;
        }
        .client-header td {
            vertical-align: middle;
        }
        .client-avatar {
            width: 36px;
            height: 36px;
            background-color: #3b82f6;
            border-radius: 50%;
            color: white;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            line-height: 36px;
        }
        .client-name {
            font-size: 13px;
            font-weight: bold;
            color: #1f2937;
        }
        .client-contact {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }
        .client-stats-row {
            margin-top: 8px;
        }
        .client-stats-row td {
            padding: 4px 0;
        }
        .stat-box {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 8px;
            margin-right: 8px;
        }
        .stat-tasks { background-color: #dbeafe; color: #1e40af; }
        .stat-invoices { background-color: #e0e7ff; color: #3730a3; }
        .stat-owed { background-color: #fee2e2; color: #991b1b; }
        .stat-paid { background-color: #d1fae5; color: #065f46; }
        .stat-due-to-client { background-color: #d1fae5; color: #065f46; }
        .stat-due-from-client { background-color: #fee2e2; color: #991b1b; }
        .stat-credit { background-color: #dbeafe; color: #1e40af; }

        .balance-owing { background-color: #fef3c7; color: #92400e; }
        .balance-overpaid { background-color: #d1fae5; color: #065f46; }
        .balance-settled { background-color: #f3f4f6; color: #374151; }

        table.tasks {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        table.tasks th {
            background-color: #374151;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.tasks th.text-right { text-align: right; }
        table.tasks th.text-center { text-align: center; }
        table.tasks td {
            padding: 7px 6px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        table.tasks tr:nth-child(even) {
            background-color: #f9fafb;
        }
        table.tasks .text-right { text-align: right; }
        table.tasks .text-center { text-align: center; }
        table.tasks .reference {
            font-weight: bold;
            color: #1f2937;
        }
        table.tasks .passenger {
            font-size: 7px;
            color: #6b7280;
            margin-top: 2px;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 7px;
            font-weight: bold;
        }
        .badge-type { background-color: #dbeafe; color: #1e40af; }
        .badge-issued { background-color: #d1fae5; color: #065f46; }
        .badge-reissued { background-color: #dbeafe; color: #1e40af; }
        .badge-confirmed { background-color: #ccfbf1; color: #0f766e; }
        .badge-void { background-color: #fee2e2; color: #991b1b; }
        .badge-refund { background-color: #ede9fe; color: #5b21b6; }

        .badge-paid { background-color: #d1fae5; color: #065f46; }
        .badge-partial { background-color: #fef3c7; color: #92400e; }
        .badge-unpaid { background-color: #fee2e2; color: #991b1b; }
        .badge-refunded { background-color: #ede9fe; color: #5b21b6; }
        .badge-completed { background-color: #ede9fe; color: #5b21b6; }
        .badge-not-invoiced { background-color: #f3f4f6; color: #6b7280; }

        /* No Tasks */
        .no-tasks {
            padding: 20px;
            text-align: center;
            color: #9ca3af;
            font-style: italic;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
        }
        .footer p {
            font-size: 8px;
            color: #9ca3af;
            margin-bottom: 3px;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Client Report</h1>
        <div class="date-range">
            @if($dateFrom && $dateTo)
                {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
            @else
                All Time
            @endif
        </div>
        <div class="generated">Generated: {{ $generatedAt }}</div>
    </div>

    @foreach($allClients as $index => $item)
        <div class="client-section">
            <div class="client-header">
                <table>
                    <tr>
                        <td>
                            <div class="client-name">{{ $item['client']->full_name ?: $item['client']->name }}</div>
                            <div class="client-contact">
                                @if($item['client']->phone)
                                    {{ ($item['client']->country_code ?? '+965') . $item['client']->phone }}
                                @endif
                                @if($item['client']->email)
                                    @if($item['client']->phone) | @endif
                                    {{ $item['client']->email }}
                                @endif
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <span class="stat-box stat-owed">Owed: <strong>{{ number_format($item['total_owed'], 3) }}</strong></span>
                            <span class="stat-box stat-paid">Paid: <strong>{{ number_format($item['total_paid'], 3) }}</strong></span>
                            <span class="stat-box {{ $item['balance'] > 0 ? 'balance-owing' : ($item['balance'] < 0 ? 'balance-overpaid' : 'balance-settled') }}">
                                Balance: {{ number_format($item['balance'], 3) }}
                            </span>
                        </td>
                    </tr>
                </table>
                <table class="client-stats-row">
                    <tr>
                        <td>
                            <span class="stat-box stat-tasks">
                                <strong>{{ $item['total_tasks'] }}</strong> Tasks
                                ({{ $item['invoiced_tasks_count'] }} invoiced, {{ $item['uninvoiced_tasks_count'] }} uninvoiced, {{ $item['refunded_tasks_count'] }} refunded)
                            </span>
                            <span class="stat-box stat-invoices">
                                <strong>{{ $item['invoices_count'] }}</strong> Invoices
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <span class="stat-box stat-due-to-client">Due to Client: <strong>{{ number_format($item['refund_credit'], 3) }}</strong></span>
                            <span class="stat-box stat-due-from-client">Due from Client: <strong>{{ number_format($item['refund_owed'], 3) }}</strong></span>
                            <span class="stat-box stat-credit">Client Credit: <strong>{{ number_format($item['client_credit'], 3) }}</strong></span>
                        </td>
                    </tr>
                </table>
            </div>

            @if($item['tasks']->isNotEmpty())
                <table class="tasks">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Reference</th>
                            <th style="width: 15%;">Supplier</th>
                            <th style="width: 10%;" class="text-center">Type</th>
                            <th style="width: 12%;" class="text-center">Date</th>
                            <th style="width: 10%;" class="text-center">Status</th>
                            <th style="width: 13%;" class="text-right">Total</th>
                            <th style="width: 20%;" class="text-center">Billing</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($item['tasks'] as $task)
                        @php
                            $taskStatus = strtolower($task->status ?? '');
                            $statusBadges = [
                                'issued' => 'badge-issued',
                                'reissued' => 'badge-reissued',
                                'confirmed' => 'badge-confirmed',
                                'void' => 'badge-void',
                                'refund' => 'badge-refund',
                            ];
                            $statusBadge = $statusBadges[$taskStatus] ?? 'badge-type';
                        @endphp
                        <tr>
                            <td>
                                <div class="reference">{{ $task->reference }}</div>
                                @if($task->passenger_name)
                                    <div class="passenger">{{ $task->passenger_name }}</div>
                                @endif
                            </td>
                            <td>{{ $task->supplier->name ?? '—' }}</td>
                            <td class="text-center">
                                <span class="badge badge-type">{{ ucfirst($task->type ?? '—') }}</span>
                            </td>
                            <td class="text-center">
                                {{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('d-m-Y') : '—' }}
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $statusBadge }}">{{ ucfirst($task->status ?? '—') }}</span>
                            </td>
                            <td class="text-right">
                                <strong>
                                    @if($task->refundDetail)
                                        {{ number_format($task->refundDetail->total_refund_to_client ?? 0, 3) }}
                                    @else
                                        {{ number_format($task->invoiceDetail->task_price ?? $task->total ?? 0, 3) }}
                                    @endif
                                </strong>
                            </td>
                            <td class="text-center">
                                @if($task->refundDetail && $task->refundDetail->refund)
                                    @php 
                                        $refund = $task->refundDetail->refund;
                                        $refundStatus = strtolower($refund->status ?? '');
                                    @endphp
                                    <span class="badge {{ $refundStatus === 'completed' ? 'badge-completed' : 'badge-partial' }}">
                                        {{ $refund->refund_number }} ({{ ucfirst($refund->status) }})
                                    </span>
                                @elseif($taskStatus === 'refund')
                                    <span class="badge badge-not-invoiced">Not Refunded</span>
                                @elseif($task->invoiceDetail && $task->invoiceDetail->invoice)
                                    @php 
                                        $invoice = $task->invoiceDetail->invoice;
                                        $invoiceStatus = strtolower($invoice->status ?? '');
                                        if (in_array($invoiceStatus, ['paid', 'paid by refund', 'refunded'])) {
                                            $invBadge = 'badge-paid';
                                        } elseif (in_array($invoiceStatus, ['partial', 'partial refund'])) {
                                            $invBadge = 'badge-partial';
                                        } elseif ($invoiceStatus === 'unpaid') {
                                            $invBadge = 'badge-unpaid';
                                        } else {
                                            $invBadge = 'badge-not-invoiced';
                                        }
                                    @endphp
                                    <span class="badge {{ $invBadge }}">
                                        {{ $invoice->invoice_number }} ({{ ucfirst($invoice->status) }})
                                    </span>
                                @else
                                    <span class="badge badge-not-invoiced">Not Invoiced</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="no-tasks">No tasks found for this client in the selected date range</div>
            @endif
        </div>
    @endforeach

    <div class="footer">
        <p>City Tours Travel Agency • Confidential Report • {{ $generatedAt }}</p>
    </div>
</body>

</html>