<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Report PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f3f3f3; text-align: left; }
        td.amount, th.amount { text-align: right; }
        tr.header { background: #f3f3f3; }
        tr.positive { background: #fde2e2; }
        tr.negative { background: #e2f7e2; }
        tr.zero { background: #f9f9f9; }
    </style>
</head>
<body>
    <h2>Client Report</h2>
    <p>
        @if($dateFrom || $dateTo)
            <strong>Date Range:</strong>
            {{ $dateFrom ? $dateFrom : '...' }} - {{ $dateTo ? $dateTo : '...' }}
        @endif
    </p>
    <table>
        <thead>
            <tr class="header">
                <th>Client Name</th>
                <th class="amount">Total Owed (KWD)</th>
                <th class="amount">Total Paid (KWD)</th>
                <th class="amount">Balance (KWD)</th>
            </tr>
        </thead>
        <tbody>
        @foreach($allClients as $item)
            <tr class="{{ $item['balance'] > 0 ? 'positive' : ($item['balance'] < 0 ? 'negative' : 'zero') }}">
                <td>{{ $item['client']->full_name ?: $item['client']->name }}</td>
                <td class="amount">{{ number_format($item['total_owed'], 3) }}</td>
                <td class="amount">{{ number_format($item['total_paid'], 3) }}</td>
                <td class="amount"><strong>{{ number_format($item['balance'], 3) }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
