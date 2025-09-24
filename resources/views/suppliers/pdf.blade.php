<?php

use Barryvdh\DomPDF\Facade\Pdf;
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Supplier Tasks PDF</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 6px;
            text-align: left;
        }

        th {
            background: #eee;
        }
    </style>
</head>

<body>
    <h2>Supplier: {{ $supplier->name }}</h2>
    <p>Email: {{ $supplier->email }} | Phone: {{ $supplier->phone }}</p>
    <p>Country: {{ $supplier->country->name ?? '-' }}</p>
    <hr>
    <h3>Filtered Tasks</h3>
    <table>
        <thead>
            <tr>
                <th>Created Date</th>
                <th>Reference</th>
                <th>Type</th>
                <th>Agent</th>
                <th>Status</th>
                <th>Issued Date</th>
                <th>Passenger Name</th>
                <th>Info</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filteredTasks as $task)
            <tr>
                <td>{{ $task->created_at ? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d') : '-' }}</td>
                <td>{{ $task->reference }}</td>
                <td>{{ $task->type }}</td>
                <td>{{ $task->agent ? $task->agent->name : '-' }}</td>
                <td>{{ ucfirst($task->status) }}</td>
                <td>{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-' }}</td>
                <td>{{ $task->passenger_name ?? '-' }}</td>
                <td>
                    @if ($task->type === 'flight' && $task->flightDetails)
                    {{ $task->flightDetails->airport_from ?? '-' }} → {{ $task->flightDetails->airport_to ?? '-' }}<br>
                    {{ $task->flightDetails->departure_time ?? '-' }} - {{ $task->flightDetails->arrival_time ?? '-' }}
                    @elseif ($task->type === 'hotel' && $task->hotelDetails)
                    {{ $task->hotelDetails->hotel->name ?? '-' }}<br>
                    {{ $task->hotelDetails->check_in ?? '-' }} - {{ $task->hotelDetails->check_out ?? '-' }}
                    @else
                    {{ $task->additional_info ?? '-' }}
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8">No entries found for selected dates.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>