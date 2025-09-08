<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Creditors Report - {{ $accountForReport->name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #1a365d;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2d3748;
        }
        .report-info {
            font-size: 12px;
            color: #666;
        }
        .summary-section {
            margin-bottom: 25px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            vertical-align: top;
        }
        .summary-label {
            font-size: 11px;
            color: #666;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #1a365d;
        }
        .outstanding {
            color: #dc2626;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .status-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }
        .status-issued { background-color: #d1fae5; color: #065f46; }
        .status-confirmed { background-color: #dbeafe; color: #1e40af; }
        .status-reissued { background-color: #fef3c7; color: #92400e; }
        .status-refund { background-color: #fed7aa; color: #9a3412; }
        .status-void { background-color: #fecaca; color: #991b1b; }
        .status-emd { background-color: #e9d5ff; color: #7c2d12; }
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $company->name }}</div>
        <div class="report-title">Creditors Report - {{ $accountForReport->name }}</div>
        <div class="report-info">
            @if($startDate && $endDate)
                Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
            @elseif($startDate)
                From: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}
            @elseif($endDate)
                Until: {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
            @else
                All Transactions
            @endif
            <br>
            Generated on: {{ $generatedAt }}
        </div>
    </div>

    <!-- Summary Section -->
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Transactions</div>
                <div class="summary-value">{{ count($journalEntries) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Credits</div>
                <div class="summary-value">KD{{ number_format($journalEntries->sum('credit'), 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Outstanding Balance</div>
                <div class="summary-value outstanding">KD{{ number_format($accountForReport->final_balance, 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Journal Entries Table -->
    @if(count($journalEntries) > 0)
    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 25%;">Description</th>
                <th style="width: 20%;">Task Details</th>
                <th style="width: 7%;" class="text-center">Task Status</th>
                <th style="width: 12%;" class="text-right">Debit</th>
                <th style="width: 12%;" class="text-right">Credit</th>
                <th style="width: 12%;" class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($journalEntries as $entry)
            <tr>
                <td>{{ \Carbon\Carbon::parse($entry->transaction_date)->format('M d, Y') }}</td>
                <td>
                    {{ $entry->description }}
                    @if($entry->name)
                        <br><small style="color: #666;">{{ $entry->name }}</small>
                    @endif
                </td>
                <td>
                    @if($entry->task)
                        <strong>{{ $entry->task->title ?? 'Task #' . $entry->task->id }}</strong>
                        @if($entry->task->reference)
                            <br><small>Ref: {{ $entry->task->reference }}</small>
                        @endif
                        @if($entry->task->client_name)
                            <br><small style="color: #2563eb;">Client: {{ $entry->task->client_name }}</small>
                        @endif
                    @else
                        <small style="color: #999;">No task linked</small>
                    @endif
                </td>
                <td class="text-center">
                    @if($entry->task && $entry->task->status)
                        <span class="status-badge status-{{ $entry->task->status }}">
                            {{ ucfirst($entry->task->status) }}
                        </span>
                    @else
                        -
                    @endif
                </td>
                <td class="text-right">
                    @if($entry->debit > 0)
                        KD{{ number_format($entry->debit, 2) }}
                    @else
                        KD0.00
                    @endif
                </td>
                <td class="text-right">
                    @if($entry->credit > 0)
                        <strong style="color: #dc2626;">KD{{ number_format($entry->credit, 2) }}</strong>
                    @else
                        KD0.00
                    @endif
                </td>
                <td class="text-right">
                    <strong style="color: {{ $entry->balance > 0 ? '#dc2626' : '#059669' }};">
                        KD{{ number_format($entry->balance, 2) }}
                    </strong>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" class="text-right"><strong>Final Outstanding Balance:</strong></td>
                <td class="text-right"><strong>KD{{ number_format($journalEntries->sum('credit'), 2) }}</strong></td>
                <td class="text-right">
                    <strong style="color: {{ $accountForReport->final_balance > 0 ? '#dc2626' : '#059669' }};">
                        KD{{ number_format($accountForReport->final_balance, 2) }}
                    </strong>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    @else
    <div style="text-align: center; padding: 50px; color: #666;">
        <p>No journal entries found for the selected criteria.</p>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated automatically by {{ $company->name }} on {{ $generatedAt }}.</p>
        <p>For questions about this report, please contact our accounting department.</p>
    </div>
</body>
</html>
