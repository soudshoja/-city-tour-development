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
    @php
    $firstTask = $filteredTasks->first();
    $supplierType = $firstTask ? $firstTask->type : null;
    @endphp

    @if($supplierType === 'flight')
    <table>
        <thead>
            <tr>
                <th>Task Ref</th>
                <th>GDS Ref</th>
                <th>Agent</th>
                <th>Issued Date</th>
                <th>Passenger Name</th>
                <th>Net Price</th>
                <th>Departure</th>
                <th>Arrival</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filteredTasks as $task)
            <tr>
                <td>{{ $task->reference }}</td>
                <td>{{ $task->gds_reference ?? '-' }}</td>
                <td>{{ $task->agent ? $task->agent->name : '-' }}</td>
                <td>{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-' }}</td>
                <td>{{ $task->passenger_name ?? '-' }}</td>
                <td>{{ $task->price ?? '-' }}</td>
                <td>
                    @if ($task->flightDetails)
                    {{ $task->flightDetails->airport_from ?? '-' }}<br>
                    {{ $task->flightDetails->departure_time ?? '-' }}
                    @else
                    -
                    @endif
                </td>
                <td>
                    @if ($task->flightDetails)
                    {{ $task->flightDetails->airport_to ?? '-' }}<br>
                    {{ $task->flightDetails->arrival_time ?? '-' }}
                    @else
                    -
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10">No entries found for selected dates.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @elseif($supplierType === 'hotel')
    <table>
        <thead>
            <tr>
                <th>Task Ref</th>
                <th>Agent</th>
                <th>Issued Date</th>
                <th>Passenger Name</th>
                <th>Net Price</th>
                <th>Check-in</th>
                <th>Check-out</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filteredTasks as $task)
            <tr>
                <td>{{ $task->reference }}</td>
                <td>{{ $task->agent ? $task->agent->name : '-' }}</td>
                <td>{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-' }}</td>
                <td>{{ $task->passenger_name ?? '-' }}</td>
                <td>{{ $task->price ?? '-' }}</td>
                <td>
                    @if ($task->hotelDetails)
                    {{ $task->hotelDetails->hotel->name ?? '-' }}<br>
                    {{ $task->hotelDetails->check_in ?? '-' }}
                    @else
                    -
                    @endif
                </td>
                <td>
                    @if ($task->hotelDetails)
                    {{ $task->hotelDetails->hotel->name ?? '-' }}<br>
                    {{ $task->hotelDetails->check_out ?? '-' }}
                    @else
                    -
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9">No entries found for selected dates.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @else
    <table>
        <thead>
            <tr>
                <th>Task Ref</th>
                <th>Agent</th>
                <th>Issued Date</th>
                <th>Passenger Name</th>
                <th>Net Price</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filteredTasks as $task)
            <tr>
                <td>{{ $task->reference }}</td>
                <td>{{ $task->agent ? $task->agent->name : '-' }}</td>
                <td>{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-' }}</td>
                <td>{{ $task->passenger_name ?? '-' }}</td>
                <td>{{ $task->price ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="12">No entries found for selected dates.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @endif
</body>

</html>