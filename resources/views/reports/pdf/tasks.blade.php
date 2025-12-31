<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tasks Report</title>
    <style>
        @page {
            margin: 15mm 10mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }

        .header h1 {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 5px;
            color: #333;
        }

        .header .subtitle {
            font-size: 10px;
            color: #666;
        }

        .summary-box {
            background: #f7f7f7;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-row {
            display: table-row;
        }

        .summary-cell {
            display: table-cell;
            padding: 4px 8px;
            vertical-align: top;
        }

        .summary-label {
            font-weight: bold;
            color: #333;
            width: 120px;
        }

        .summary-value {
            color: #555;
        }

        .stats {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            background: #e8f4f8;
            padding: 8px;
            border-radius: 4px;
        }

        .stat-row {
            display: table-row;
        }

        .stat-cell {
            display: table-cell;
            padding: 4px 10px;
            text-align: center;
            vertical-align: middle;
        }

        .stat-label {
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-top: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        thead {
            background: #333;
            color: white;
        }

        th {
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            border: 1px solid #222;
        }

        td {
            padding: 5px 4px;
            border: 1px solid #ddd;
            font-size: 8px;
        }

        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-void, .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-other {
            background: #d1ecf1;
            color: #0c5460;
        }

        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            color: #666;
            text-align: center;
        }

        .page-break {
            page-break-after: always;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Tasks Report</h1>
        <div class="subtitle">Generated on {{ $generatedAt }}</div>
    </div>

    <div class="stats">
        <div class="stat-row">
            <div class="stat-cell">
                <div class="stat-label">Total Tasks</div>
                <div class="stat-value">{{ number_format($totalTasks) }}</div>
            </div>
            <div class="stat-cell">
                <div class="stat-label">Total Amount</div>
                <div class="stat-value">{{ number_format($totalAmount, 3) }} KWD</div>
            </div>
        </div>
    </div>

    @if($tasks->count() > 0)
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Reference</th>
                    <th style="width: 10%;">Original Reference</th>
                    <th style="width: 16%;">Client</th>
                    <th style="width: 14%;">Supplier</th>
                    <th style="width: 12%;">Agent</th>
                    <th style="width: 10%;">Pay Date</th>
                    <th style="width: 10%;">Issued By</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 10%;" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $index => $task)
                    <tr>
                        <td>{{ $task->reference ?? 'N/A' }}</td>
                        <td>{{ $task->original_reference ?? 'N/A' }}</td>
                        <td>{{ $task->passenger_name ?? 'N/A' }}</td>
                        <td>{{ $task->supplier->name ?? 'N/A' }}</td>
                        <td>{{ $task->agent->name ?? 'N/A' }}</td>
                        <td class="text-center">{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : 'N/A' }}</td>
                        <td>{{ $task->issued_by ?? 'N/A' }}</td>
                        <td class="text-center">
                            <span class="status-badge 
                                @if($task->status === 'completed') status-completed
                                @elseif($task->status === 'pending') status-pending
                                @elseif(in_array($task->status, ['cancelled', 'void'])) status-void
                                @else status-other
                                @endif">
                                {{ ucfirst($task->status ?? 'N/A') }}
                            </span>
                        </td>
                        <td class="text-right">{{ number_format(($task->price ?? 0) + ($task->tax ?? 0) + ($task->supplier_surcharge ?? 0), 3) }} KWD</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">No tasks found matching the selected filters.</div>
    @endif

    <div class="footer">
        <div>Total Tasks: {{ number_format($totalTasks) }} | Total Amount: {{ number_format($totalAmount, 3) }} KWD</div>
        <div style="margin-top: 3px;">This report was automatically generated on {{ $generatedAt }}</div>
    </div>
</body>
</html>
