<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Trial Balance Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
        }

        .header .company {
            font-size: 12px;
            font-weight: bold;
        }

        .header .period {
            font-size: 11px;
            color: #666;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 9px;
        }

        .balance-status {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
        }

        .balance-status.balanced {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .balance-status.unbalanced {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section-title {
            background-color: #f0f0f0;
            font-weight: bold;
            padding: 8px;
            margin-top: 10px;
            border-top: 1px solid #ddd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th {
            background-color: #333;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #333;
        }

        td {
            padding: 6px 8px;
            border: 1px solid #ddd;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .root-header {
            background-color: #e8e8e8;
            font-weight: bold;
        }

        .grand-total {
            background-color: #333;
            color: white;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .currency {
            font-family: 'Courier New', monospace;
        }

        .code-badge {
            background-color: #e8e8e8;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            text-align: center;
            color: #666;
        }

        .page-break {
            page-break-after: always;
        }

        .unbalanced-section {
            margin-top: 20px;
            border: 2px solid #ff9800;
            padding: 10px;
            background-color: #fff3cd;
        }

        .unbalanced-section h3 {
            margin: 0 0 10px 0;
            color: #ff6600;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>TRIAL BALANCE REPORT</h1>
        <div class="company">{{ $company->name }}</div>
        <div class="period">Period: {{ \Carbon\Carbon::parse($dateFrom)->format('B d, Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('B d, Y') }}</div>
    </div>

    <!-- Meta Information -->
    <div class="meta">
        <div>Generated: {{ now()->format('F d, Y \a\t h:i A') }}</div>
        <div>Status: @if($trialBalance['totals']['is_balanced']) ✓ BALANCED @else ✗ OUT OF BALANCE @endif</div>
    </div>

    <!-- Balance Status -->
    <div class="balance-status @if($trialBalance['totals']['is_balanced']) balanced @else unbalanced @endif">
        @if($trialBalance['totals']['is_balanced'])
            ✓ BALANCED — Total Debits = Total Credits = {{ number_format($trialBalance['totals']['debit'], 3) }}
        @else
            ✗ OUT OF BALANCE — Difference: {{ number_format($trialBalance['totals']['difference'], 3) }} KWD
        @endif
    </div>

    <!-- Trial Balance Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 8%">Code</th>
                <th style="width: 42%">Account Name</th>
                <th style="width: 15%" class="text-right">Debit</th>
                <th style="width: 15%" class="text-right">Credit</th>
                <th style="width: 12%" class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($trialBalance['grouped'] as $rootName => $group)
                <!-- Root Category Header -->
                <tr class="root-header">
                    <td colspan="5">{{ strtoupper($rootName) }}</td>
                </tr>

                <!-- Accounts -->
                @forelse($group['accounts'] as $account)
                    <tr>
                        <td><span class="code-badge">{{ $account->code }}</span></td>
                        <td>{{ $account->name }}</td>
                        <td class="text-right currency">
                            @if($account->total_debit > 0)
                                {{ number_format($account->total_debit, 3) }}
                            @endif
                        </td>
                        <td class="text-right currency">
                            @if($account->total_credit > 0)
                                {{ number_format($account->total_credit, 3) }}
                            @endif
                        </td>
                        <td class="text-right currency">
                            @php
                                if ($rootName === 'Assets' || $rootName === 'Expenses') {
                                    $balance = $account->total_debit - $account->total_credit;
                                } else {
                                    $balance = $account->total_credit - $account->total_debit;
                                }
                            @endphp
                            {{ number_format($balance, 3) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: #999;">No accounts</td>
                    </tr>
                @endforelse

                <!-- Root Subtotal -->
                <tr class="root-header">
                    <td colspan="2">{{ $rootName }} Subtotal</td>
                    <td class="text-right currency">{{ number_format($group['subtotal_debit'], 3) }}</td>
                    <td class="text-right currency">{{ number_format($group['subtotal_credit'], 3) }}</td>
                    <td class="text-right currency">
                        @php
                            if ($rootName === 'Assets' || $rootName === 'Expenses') {
                                $subtotalBalance = $group['subtotal_debit'] - $group['subtotal_credit'];
                            } else {
                                $subtotalBalance = $group['subtotal_credit'] - $group['subtotal_debit'];
                            }
                        @endphp
                        {{ number_format($subtotalBalance, 3) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #999; padding: 20px;">No data available</td>
                </tr>
            @endforelse

            <!-- Grand Total -->
            @if(count($trialBalance['grouped']) > 0)
                <tr class="grand-total">
                    <td colspan="2">GRAND TOTAL</td>
                    <td class="text-right currency">{{ number_format($trialBalance['totals']['debit'], 3) }}</td>
                    <td class="text-right currency">{{ number_format($trialBalance['totals']['credit'], 3) }}</td>
                    <td class="text-right currency">
                        @php
                            $gtBalance = $trialBalance['totals']['debit'] - $trialBalance['totals']['credit'];
                        @endphp
                        {{ number_format($gtBalance, 3) }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Difference Row -->
    @if(count($trialBalance['grouped']) > 0)
        <div style="text-align: right; margin-top: 10px; font-weight: bold;">
            Difference: <span class="currency">{{ number_format($trialBalance['totals']['difference'], 3) }}</span>
        </div>
    @endif

    <!-- Unbalanced Transactions Section -->
    @if($unbalancedTransactions->count() > 0)
        <div class="page-break"></div>
        <div class="unbalanced-section">
            <h3>⚠ UNBALANCED TRANSACTIONS DETECTED ({{ $unbalancedTransactions->count() }})</h3>
            <table>
                <thead>
                    <tr>
                        <th>Transaction Date</th>
                        <th>Name</th>
                        <th>Reference</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Imbalance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($unbalancedTransactions as $txn)
                        <tr>
                            <td>{{ $txn->transaction_date ? \Carbon\Carbon::parse($txn->transaction_date)->format('M d, Y') : 'N/A' }}</td>
                            <td>{{ $txn->name }}</td>
                            <td>{{ $txn->reference_number ?? 'N/A' }}</td>
                            <td class="text-right currency">{{ number_format($txn->total_debit, 3) }}</td>
                            <td class="text-right currency">{{ number_format($txn->total_credit, 3) }}</td>
                            <td class="text-right currency" style="background-color: #ffcccc; font-weight: bold;">{{ number_format($txn->imbalance, 3) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This is an official record of the Trial Balance for {{ $company->name }}. Total Debits must equal Total Credits for a balanced Trial Balance.</p>
        <p>Document generated automatically by the accounting system on {{ now()->format('F d, Y \a\t h:i A') }}</p>
    </div>
</body>
</html>
