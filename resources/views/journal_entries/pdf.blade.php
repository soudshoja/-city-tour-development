<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Journal Entries Ledger PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 6px; text-align: center; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Ledger</h2>
    <p><strong>Report Period:</strong> {{ $dateFrom }} to {{ $dateTo }}</p>
    <table>
        <thead>
            <tr>
                <th>Transaction ID</th>
                <th>Transaction Date</th>
                @php
                    $showIssueColumn = $journalEntries->contains(function ($entry) {
                        return $entry->type === 'payable' && !is_null($entry->task);
                    });
                @endphp
                @if($showIssueColumn)
                    <th>Task Date</th>
                @endif
                <th>Reference</th>
                <th>Client Name</th>
                <th>Description</th>
                <th>Account</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Running Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($journalEntries as $entry)
                <tr>
                    <td>{{ $entry->transaction_id }}</td>
                    <td>{{ \Carbon\Carbon::parse($entry->transaction_date)->format('Y-m-d') }}</td>
                    @if($showIssueColumn)
                        <td>{{ $entry->task ? $entry->task->issued_date?->format('Y-m-d') ?? '-' : '-' }}</td>
                    @endif
                    <td>{{ $entry->task ? $entry->task->reference ?? '-' : '-' }}</td>
                    <td>{{ $entry->task ? $entry->task->client_name ?? '-' : '-' }}</td>
                    <td>
                        @if ($entry->task && $entry->task->type === 'flight')
                            Departure: {{ $entry->task?->flightDetails?->departure_time ? \Carbon\Carbon::parse($entry->task->flightDetails->departure_time)->format('H:i') : '-' }},
                            From: {{ $entry->task->flightDetails->airport_from ?? '-' }},
                            Arrival: {{ $entry->task?->flightDetails?->arrival_time ? \Carbon\Carbon::parse($entry->task->flightDetails->arrival_time)->format('H:i') : '-' }},
                            To: {{ $entry->task->flightDetails->airport_to ?? '-' }}
                        @elseif ($entry->task && $entry->task->type === 'hotel')
                            Hotel: {{ $entry->task->hotelDetails->hotel->name ?? '-' }},
                            Check-in: {{ $entry->task->hotelDetails->check_in ?? '-' }},
                            Check-out: {{ $entry->task->hotelDetails->check_out ?? '-' }}
                        @else
                            {{ $entry->task->additional_info ?? '-' }}
                        @endif
                    </td>
                    <td>{{ $entry->account->name }}</td>
                    <td>{{ number_format($entry->debit, 2) }}</td>
                    <td>{{ number_format($entry->credit, 2) }}</td>
                    <td>{{ number_format($entry->running_balance, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>