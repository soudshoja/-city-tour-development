<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Creditor Report - {{ $selectedSupplier['supplier_name'] }}</title>
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
        .supplier-name {
            font-size: 16px;
            color: #4c51bf;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-info {
            font-size: 12px;
            color: #666;
        }
        .summary-section {
            margin-bottom: 25px;
            background-color: #f8f9fa;
            border: 2px solid #667eea;
            padding: 20px;
            border-radius: 8px;
        }
        .summary-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-grid td {
            width: 25%;
            text-align: center;
            vertical-align: top;
            padding: 10px;
            border: none;
        }
        .summary-label {
            font-size: 11px;
            margin-bottom: 5px;
            color: #666;
            font-weight: normal;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #1a365d;
        }
        .outstanding {
            color: #dc2626;
        }
        .creditor-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #4c51bf;
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
        .payment-notice {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
        .payment-notice h4 {
            color: #dc2626;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .highlight-amount {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $company->name }}</div>
        <div class="report-title">Creditor Statement</div>
        <div class="supplier-name">{{ $selectedSupplier['supplier_name'] }}</div>
        <div class="report-info">
            Account: {{ $accountForReport->name }}
            <br>
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
        <h4 style="margin-top: 0; margin-bottom: 15px; color: #1a365d; text-align: center;">Summary Overview</h4>
        <table class="summary-grid">
            <tr>
                <td>
                    <div class="summary-label">Total Transactions</div>
                    <div class="summary-value">{{ $selectedSupplier['entries_count'] }}</div>
                </td>
                <td>
                    <div class="summary-label">Total Credits</div>
                    <div class="summary-value">KD{{ number_format($selectedSupplier['total_credit'], 2) }}</div>
                </td>
                <td>
                    <div class="summary-label">Total Debits</div>
                    <div class="summary-value">KD{{ number_format($selectedSupplier['total_debit'], 2) }}</div>
                </td>
                <td>
                    <div class="summary-label">Outstanding Balance</div>
                    <div class="summary-value outstanding">KD{{ number_format($selectedSupplier['balance'], 2) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Creditor Information -->
    <div class="creditor-info">
        <h4 style="margin-top: 0; color: #1a365d;">Account Information</h4>
        <p><strong>Creditor Account:</strong> {{ $accountForReport->name }}</p>
        <p><strong>Supplier:</strong> {{ $selectedSupplier['supplier_name'] }}</p>
        <p><strong>Current Outstanding Amount:</strong> 
            <span class="highlight-amount">KD{{ number_format($selectedSupplier['balance'], 2) }}</span>
        </p>
    </div>

    <!-- Transaction Details Table -->
    @if(count($selectedSupplier['entries']) > 0)
    <h3 style="color: #1a365d; margin-bottom: 15px;">Transaction Details</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 25%;">Description</th>
                <th style="width: 18%;">Task Details</th>
                <th style="width: 7%;" class="text-center">Task Status</th>
                <th style="width: 12%;" class="text-right">Debit</th>
                <th style="width: 12%;" class="text-right">Credit</th>
                <th style="width: 14%;" class="text-right">Running Balance</th>
            </tr>
        </thead>
        <tbody>
            @php $runningBalance = 0; @endphp
            @foreach($selectedSupplier['entries'] as $entry)
            @php $runningBalance += ($entry->credit - $entry->debit); @endphp
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
                        @if($entry->task->amount)
                            <br><small style="color: #059669;">Task Amount: KD{{ number_format($entry->task->amount, 2) }}</small>
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
                    KD{{ number_format($entry->debit, 2) }}
                </td>
                <td class="text-right">
                    KD{{ number_format($entry->credit, 2) }}
                </td>
                <td class="text-right">
                    <strong style="color: {{ $runningBalance > 0 ? '#dc2626' : '#059669' }};">
                        KD{{ number_format($runningBalance, 2) }}
                    </strong>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" class="text-right"><strong>Totals:</strong></td>
                <td class="text-right"><strong>KD{{ number_format($selectedSupplier['total_debit'], 2) }}</strong></td>
                <td class="text-right"><strong>KD{{ number_format($selectedSupplier['total_credit'], 2) }}</strong></td>
                <td class="text-right">
                    <strong style="color: {{ $selectedSupplier['balance'] > 0 ? '#dc2626' : '#059669' }};">
                        KD{{ number_format($selectedSupplier['balance'], 2) }}
                    </strong>
                </td>
            </tr>
        </tfoot>
    </table>
    @else
    <div style="text-align: center; padding: 50px; color: #666;">
        <p>No transactions found for {{ $selectedSupplier['supplier_name'] }} in the selected period.</p>
    </div>
    @endif

    <!-- Payment Notice -->
    @if($selectedSupplier['balance'] > 0)
    <div class="payment-notice">
        <h4>Payment Notice</h4>
        <p>
            <strong>Amount Due:</strong> <span class="highlight-amount">KD{{ number_format($selectedSupplier['balance'], 2) }}</span>
        </p>
        <p>This amount represents the outstanding balance owed to <strong>{{ $selectedSupplier['supplier_name'] }}</strong> through our creditor account <strong>{{ $accountForReport->name }}</strong>.</p>
        <p>Please remit payment according to your agreed terms and conditions.</p>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This statement was generated automatically by {{ $company->name }} on {{ $generatedAt }}.</p>
        <p>For questions about this statement or payment arrangements, please contact our accounting department.</p>
        <p style="margin-top: 10px; font-style: italic;">
            This is a computer-generated document and does not require a signature.
        </p>
    </div>
</body>
</html>
