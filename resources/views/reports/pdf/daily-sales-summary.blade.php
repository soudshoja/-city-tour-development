<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Sales - Summary</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1,h2,h3 { margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; }
        th { background: #f2f6ff; text-align: left; }
        .right { text-align: right; }
        .muted { color: #666; font-size: 11px; }
        .total-row { background: #eef4ff; font-weight: bold; }
        .header { margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company->name ?? 'Company' }}</h1>
        <div class="muted">
            Daily Sales Report (Summary)<br>
            Period: {{ $from->format('d-M-Y') }} – {{ $to->format('d-M-Y') }}
            @if($filteredAgent) • Agent Filter Applied @endif
        </div>
    </div>

    <table style="margin-bottom:10px">
        <tr><th>Total Invoices</th><td class="right">{{ $summary['totalInvoices'] }}</td></tr>
        <tr><th>Total Invoiced (KWD)</th><td class="right">{{ number_format($summary['totalInvoiced'],3) }}</td></tr>
        <tr><th>Total Paid (KWD)</th><td class="right">{{ number_format($summary['totalPaid'],3) }}</td></tr>
        <tr><th>Profit (KWD)</th><td class="right">{{ number_format($summary['profit'],3) }}</td></tr>
        <tr><th>Refunds (KWD)</th><td class="right">{{ number_format($summary['refunds'] ?? 0,3) }}</td></tr>
    </table>

    <h3>Agent Summary</h3>
    <table>
        <thead>
            <tr>
                <th>Agent</th>
                <th class="right">Invoices</th>
                <th class="right">Invoiced</th>
                <th class="right">Paid</th>
                <th class="right">Unpaid</th>
                <th class="right">Profit</th>
                <th class="right">Commission</th>
            </tr>
        </thead>
        <tbody>
        @php
            $totInv=0; $totAmt=0; $totPaid=0; $totUnpaid=0; $totProfit=0; $totComm=0;
        @endphp
        @foreach($agents as $row)
            @php
                $totInv    += $row['totalInvoices'];
                $totAmt    += $row['totalInvoiced'];
                $totPaid   += $row['paid'];
                $totUnpaid += $row['unpaid'];
                $totProfit += $row['profit'];
                $totComm   += $row['commission'];
            @endphp
            <tr>
                <td>{{ $row['agent']->name }}</td>
                <td class="right">{{ $row['totalInvoices'] }}</td>
                <td class="right">{{ number_format($row['totalInvoiced'],3) }}</td>
                <td class="right">{{ number_format($row['paid'],3) }}</td>
                <td class="right">{{ number_format($row['unpaid'],3) }}</td>
                <td class="right">{{ number_format($row['profit'],3) }}</td>
                <td class="right">{{ number_format($row['commission'],3) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>Total</td>
            <td class="right">{{ $totInv }}</td>
            <td class="right">{{ number_format($totAmt,3) }}</td>
            <td class="right">{{ number_format($totPaid,3) }}</td>
            <td class="right">{{ number_format($totUnpaid,3) }}</td>
            <td class="right">{{ number_format($totProfit,3) }}</td>
            <td class="right">{{ number_format($totComm,3) }}</td>
        </tr>
        </tbody>
    </table>
</body>
</html>
