@php
$fmt = fn($n) => number_format((float)($n ?? 0), 2);
$generatedAt = now()->format('d-m-Y H:i');
$companyName = $company->name ?? 'Company';

// Format date range string
$dateRangeStr = '';
if ($startDate && $endDate) {
$dateRangeStr = \Carbon\Carbon::parse($startDate)->format('d-m-Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('d-m-Y');
} elseif ($startDate) {
$dateRangeStr = 'From ' . \Carbon\Carbon::parse($startDate)->format('d-m-Y');
} elseif ($endDate) {
$dateRangeStr = 'Until ' . \Carbon\Carbon::parse($endDate)->format('d-m-Y');
} else {
$dateRangeStr = 'All Records';
}
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Payment Gateways Report</title>
    <style>
        :root {
            --ink: #111;
            --muted: #666;
            --line: #e6e6e6;
            --bg-head: #f7f7f7;
            --bg-subtle: #fbfbfb;
        }

        @page {
            margin: 15mm 10mm 18mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: var(--ink);
        }

        .cover-header {
            text-align: center;
            margin: 0 0 10px;
        }

        .cover-name {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .2px;
        }

        .cover-sub {
            font-size: 10px;
            color: var(--muted);
            margin-top: 2px;
        }

        .cover-title {
            font-size: 14px;
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
            font-size: 16px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        h2 {
            font-size: 12px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
            margin-top: 10px;
        }

        h3 {
            font-size: 11px;
            margin-top: 8px;
        }

        .muted {
            color: var(--muted);
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid var(--line);
            padding: 4px 5px;
        }

        th {
            background: var(--bg-head);
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .2px;
        }

        td {
            font-size: 9.5px;
        }

        .tight td,
        .tight th {
            padding-top: 3px;
            padding-bottom: 3px;
        }

        .subtle {
            background: var(--bg-subtle);
        }

        .chip {
            display: inline-block;
            padding: 1px 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 8.5px;
            background: #f5f5f5;
            color: #333;
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
            font-size: 9px;
            color: var(--muted);
        }

        .section {
            margin-top: 10px;
        }

        .totals td {
            font-weight: 700;
            background: #f4f4f4;
        }

        .num {
            font-variant-numeric: tabular-nums;
        }

        .text-red {
            color: #111;
        }

        .text-green {
            color: #111;
        }

        .text-blue {
            color: #111;
        }

        .summary-box {
            border: 1px solid #ddd;
            padding: 8px;
            margin-bottom: 10px;
            background: #fafafa;
        }

        .summary-box h3 {
            margin: 0 0 6px;
            font-size: 11px;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
        }

        .filter-info {
            font-size: 9px;
            color: #666;
            margin-bottom: 8px;
            padding: 4px 6px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #666;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="cover-header no-break">
        @if(!empty($company?->logo_url))
        <div style="margin-bottom:6px;">
            <img src="{{ $company->logo_url }}" alt="Logo" style="height:28px;">
        </div>
        @endif
        <div class="cover-name">{{ $companyName }}</div>
        <div class="cover-title">Payment Gateways Report</div>
        <div class="cover-sub">
            Period: {{ $dateRangeStr }} &nbsp;&nbsp;|&nbsp;&nbsp;
            Generated: {{ $generatedAt }}
        </div>
    </div>

    @if($selectedClient || $selectedPaymentGateway)
    <div class="filter-info">
        <strong>Filters Applied:</strong>
        @if($selectedClient)
        Client: {{ $selectedClient->full_name ?? $selectedClient->name ?? 'Unknown' }}
        @endif
        @if($selectedPaymentGateway)
        @if($selectedClient) | @endif
        Gateway: {{ ucfirst($selectedPaymentGateway) }}
        @endif
    </div>
    @endif

    <!-- Gateway Summary Section -->
    <div class="section no-break">
        <h2>Gateway Summary</h2>
        <table class="tight">
            <thead>
                <tr>
                    <th>Gateway</th>
                    <th class="center">Transactions</th>
                    <th class="right">Gross Amount (KWD)</th>
                    <th class="right">Total Charges (KWD)</th>
                    <th class="right">Net to Receive (KWD)</th>
                    <th class="right">Avg Charge %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($gatewaySummary['gateways'] as $gateway => $data)
                <tr>
                    <td>{{ ucfirst($gateway) }}</td>
                    <td class="center num">{{ $data['transactions'] }}</td>
                    <td class="right num">{{ $fmt($data['gross_amount']) }}</td>
                    <td class="right num">{{ $fmt($data['total_charges']) }}</td>
                    <td class="right num">{{ $fmt($data['net_to_receive']) }}</td>
                    <td class="right num">{{ number_format($data['avg_charge_percent'], 2) }}%</td>
                </tr>
                @endforeach
                <tr class="totals">
                    <td>TOTAL</td>
                    <td class="center num">{{ $gatewaySummary['totals']['transactions'] }}</td>
                    <td class="right num">{{ $fmt($gatewaySummary['totals']['gross_amount']) }}</td>
                    <td class="right num">{{ $fmt($gatewaySummary['totals']['total_charges']) }}</td>
                    <td class="right num">{{ $fmt($gatewaySummary['totals']['net_to_receive']) }}</td>
                    <td class="right num">{{ number_format($gatewaySummary['totals']['avg_charge_percent'], 2) }}%</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Detailed Transactions Section -->
    <div class="section">
        <h2>Transaction Details</h2>
        <table class="tight">
            <thead>
                <tr>
                    <th style="width:14%;">Reference</th>
                    <th style="width:8%;">Type</th>
                    <th class="right" style="width:12%;">Amount (KWD)</th>
                    <th style="width:22%;">Payment Source</th>
                    <th class="right" style="width:14%;">Charges (KWD)</th>
                    <th class="right" style="width:14%;">Net to Receive (KWD)</th>
                    <th style="width:12%;">Date</th>
                </tr>
            </thead>
            <tbody>
                {{-- Paid Invoices --}}
                @foreach($paidInvoices as $invoice)
                @php
                $invoiceAmount = $invoice->amount;
                $totalCharges = $invoice->invoicePartials->sum('gateway_fee') ?? 0;
                $paymentGateway = 'N/A';
                $paymentMethods = collect();

                if($invoice->invoicePartials->count() > 0) {
                foreach($invoice->invoicePartials as $partial) {
                if($paymentGateway === 'N/A' && !empty($partial->payment_gateway)) {
                $paymentGateway = $partial->payment_gateway;
                }
                if($partial->paymentMethod) {
                $paymentMethods->push($partial->paymentMethod);
                }
                }
                }

                $paymentMethods = $paymentMethods->unique('id');
                $amountToReceive = $invoiceAmount - $totalCharges;

                // Format charge display
                $chargeDisplay = $fmt($totalCharges);
                if($totalCharges > 0 && $invoiceAmount > 0) {
                $calculatedPercent = ($totalCharges / $invoiceAmount) * 100;
                if($calculatedPercent >= 0.1) {
                $chargeDisplay .= ' (' . number_format($calculatedPercent, 2) . '%)';
                } else {
                $chargeDisplay .= ' (fixed)';
                }
                }

                // Format payment source
                $paymentMethodName = '';
                if($paymentMethods->count() > 0) {
                $methodNames = $paymentMethods->map(function($method) {
                return $method->english_name ?? $method->arabic_name ?? ucfirst($method->type ?? 'Unknown');
                })->implode(', ');
                $paymentMethodName = $methodNames;
                }
                $paymentSource = ucfirst($paymentGateway);
                if($paymentMethodName) {
                $paymentSource .= ' (' . $paymentMethodName . ')';
                }
                @endphp
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>Invoice</td>
                    <td class="right num">{{ $fmt($invoiceAmount) }}</td>
                    <td>{{ $paymentSource }}</td>
                    <td class="right num">{{ $chargeDisplay }}</td>
                    <td class="right num">{{ $fmt($amountToReceive) }}</td>
                    <td>{{ $invoice->paid_date ? \Carbon\Carbon::parse($invoice->paid_date)->format('d-m-Y') : 'N/A' }}</td>
                </tr>
                @endforeach

                {{-- Wallet Top-Ups / Credit Payments --}}
                @foreach($walletTopUps as $payment)
                @php
                $paymentAmount = $payment->amount;
                $gatewayCharges = $payment->gateway_fee ?? 0;
                $amountToReceive = $paymentAmount - $gatewayCharges;
                $paymentGatewayName = $payment->payment_gateway ?? 'N/A';

                // Format payment source
                $walletMethodName = '';
                if($payment->paymentMethod) {
                $walletMethodName = $payment->paymentMethod->english_name ?? $payment->paymentMethod->arabic_name ?? ucfirst($payment->paymentMethod->type ?? 'Unknown');
                }
                $walletPaymentSource = ucfirst($paymentGatewayName);
                if($walletMethodName) {
                $walletPaymentSource .= ' (' . $walletMethodName . ')';
                }

                // Format charge display
                $walletChargeDisplay = $fmt($gatewayCharges);
                if($gatewayCharges > 0 && $paymentAmount > 0) {
                $walletPercent = ($gatewayCharges / $paymentAmount) * 100;
                if($walletPercent >= 0.1) {
                $walletChargeDisplay .= ' (' . number_format($walletPercent, 2) . '%)';
                } else {
                $walletChargeDisplay .= ' (fixed)';
                }
                }
                @endphp
                <tr>
                    <td>{{ $payment->voucher_number }}</td>
                    <td>Payment</td>
                    <td class="right num">{{ $fmt($paymentAmount) }}</td>
                    <td>{{ $walletPaymentSource }}</td>
                    <td class="right num">{{ $walletChargeDisplay }}</td>
                    <td class="right num">{{ $fmt($amountToReceive) }}</td>
                    <td>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d-m-Y') : 'N/A' }}</td>
                </tr>
                @endforeach

                {{-- Grand Totals Row --}}
                <tr class="totals">
                    <td colspan="2">GRAND TOTAL</td>
                    <td class="right num">{{ $fmt($grandTotals['amount']) }}</td>
                    <td></td>
                    <td class="right num">{{ $fmt($grandTotals['charges']) }}</td>
                    <td class="right num">{{ $fmt($grandTotals['net_to_receive']) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Summary Statistics -->
    <div class="section no-break">
        <h2>Summary Statistics</h2>
        <table class="tight" style="width: 50%;">
            <tbody>
                <tr>
                    <td style="width: 60%;">Total Invoices</td>
                    <td class="right num">{{ count($paidInvoices) }}</td>
                </tr>
                <tr>
                    <td>Invoices Amount</td>
                    <td class="right num">{{ $fmt($paidInvoices->sum('amount')) }} KWD</td>
                </tr>
                <tr>
                    <td>Total Credit Payments</td>
                    <td class="right num">{{ count($walletTopUps) }}</td>
                </tr>
                <tr>
                    <td>Credit Payments Amount</td>
                    <td class="right num">{{ $fmt($walletTopUps->sum('amount')) }} KWD</td>
                </tr>
                <tr class="totals">
                    <td>Combined Total</td>
                    <td class="right num">{{ $fmt($paidInvoices->sum('amount') + $walletTopUps->sum('amount')) }} KWD</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        Report generated on {{ $generatedAt }} | {{ $companyName }} | Payment Gateways Report
    </div>

</body>

</html>