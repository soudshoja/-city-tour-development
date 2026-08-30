@php
    $partyName = match(true) {
        $partyType === 'client' => $party->full_name ?? $party->name,
        default => $party->name,
    };
    $partyLabel = ['client' => 'Client', 'supplier' => 'Supplier', 'agent' => 'Agent'][$partyType] ?? ucfirst($partyType);
    $isOpenItems = $statement['mode'] === 'open_items';
    $netOutstanding = $statement['totals']['net_outstanding'] ?? $statement['totals']['closing_balance'] ?? 0;
    // P2.5.H provenance caption -- see show.blade.php's own note; same wording, same source map.
    $sourceCaption = match ($partyType) {
        'client' => 'Derived from posted invoices and applied receipts.',
        'agent' => 'Derived from agent settlement records.',
        'supplier' => 'Derived from posted ledger entries on the supplier payable account (open-item projection).',
        default => null,
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Statement — {{ $partyName }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; font-size: 10px; color: #1f2937; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1f2937; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { margin: 0; font-size: 16px; }
        .header .party { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .header .meta { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .total-pill { text-align: right; }
        .total-pill .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
        .total-pill .value { font-size: 16px; font-weight: bold; margin-top: 2px; }
        .value.owed { color: #b91c1c; }
        .value.clear { color: #047857; }
        .ageing { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .ageing td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: center; width: 20%; }
        .ageing .bucket-label { font-size: 8px; text-transform: uppercase; color: #6b7280; }
        .ageing .bucket-total { font-size: 12px; font-weight: bold; }
        table.doc-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.doc-table th { background-color: #1f2937; color: #fff; padding: 6px 8px; text-align: left; font-size: 9px; text-transform: uppercase; }
        table.doc-table td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        table.doc-table td.num, table.doc-table th.num { text-align: right; }
        .section-title { font-weight: bold; font-size: 11px; margin: 14px 0 6px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .empty-state { text-align: center; padding: 20px; color: #9ca3af; }
        .footer { position: fixed; bottom: -14px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
        .header .source { font-size: 8px; color: #9ca3af; margin-top: 3px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Account Statement</h1>
            <div class="party">{{ $partyLabel }}: {{ $partyName }}</div>
            <div class="meta">Mode: {{ $isOpenItems ? 'Open items' : 'Full activity' }} &middot; As of {{ $asOf->format('d M Y') }}</div>
            @if ($sourceCaption)
                <div class="source">{{ $sourceCaption }}</div>
            @endif
        </div>
        <div class="total-pill">
            <div class="label">{{ $isOpenItems ? 'Net outstanding' : 'Closing balance' }}</div>
            <div class="value {{ $netOutstanding > 0.001 ? 'owed' : 'clear' }}">{{ number_format($netOutstanding, 3) }}</div>
        </div>
    </div>

    @if ($isOpenItems)
        <table class="ageing">
            <tr>
                @foreach ($statement['ageing'] as $bucket)
                    <td>
                        <div class="bucket-label">{{ $bucket['label'] }} days ({{ $bucket['count'] }})</div>
                        <div class="bucket-total">{{ number_format($bucket['total'], 3) }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="doc-table">
        <thead>
            <tr>
                <th>Document</th>
                <th>Date</th>
                <th class="num">Amount</th>
                <th class="num">Settled</th>
                @if ($isOpenItems)
                    <th class="num">Outstanding</th>
                    <th class="num">Age (d)</th>
                @else
                    <th class="num">Running balance</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($statement['items'] as $item)
                <tr>
                    <td>{{ $item['document_number'] }}</td>
                    <td>{{ $item['document_date'] }}</td>
                    <td class="num">{{ number_format($item['amount'], 3) }}</td>
                    <td class="num">{{ number_format($item['settled_amount'], 3) }}</td>
                    @if ($isOpenItems)
                        <td class="num">{{ number_format($item['outstanding'], 3) }}</td>
                        <td class="num">{{ $item['age_days'] }}</td>
                    @else
                        <td class="num">{{ number_format($item['running_balance'], 3) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-state">
                        @if ($isOpenItems)
                            Fully settled — no open items as of {{ $asOf->format('d M Y') }}.
                        @else
                            No activity in this period.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($isOpenItems && count($statement['unapplied']) > 0)
        <div class="section-title">Unapplied receipts &amp; credits</div>
        <table class="doc-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="num">Amount available</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($statement['unapplied'] as $item)
                    <tr>
                        <td>{{ $item['document_number'] }}</td>
                        <td>{{ $item['document_date'] }}</td>
                        <td>{{ $item['description'] }}</td>
                        <td class="num">{{ number_format($item['amount'], 3) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Generated {{ $generatedAt->format('d M Y H:i') }}</div>
</body>
</html>
