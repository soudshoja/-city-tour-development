<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Flight Voucher: {{ $task->reference }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <style>
        :root {
            --primary-bg: #ffffff;
            --accent-bg: #f4f6f8;
            --section-bg: #fbfbfb;
            --text-dark: #1f2937;
            --text-muted: #4b5563;
            --highlight: rgb(182, 196, 209);
            --border: #e5e7eb;
        }

        body {
            background: var(--accent-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 40px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: var(--primary-bg);
            border-radius: 12px;
            box-shadow: 0 2px 14px rgba(0, 0, 0, 0.07);
            overflow: hidden;
        }

        .header {
            background: var(--highlight);
            color: white;
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header img {
            height: 48px;
        }

        .header h1 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .section {
            padding: 24px 32px;
            border: 1px solid var(--border);
        }

        .section:last-child {
            border-bottom: none;
        }

        .section.gray {
            background: var(--section-bg);
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-dark);
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }

        .info-box {
            width: 48%;
            margin-bottom: 12px;
        }

        .info-box.full {
            width: 100%;
        }

        .label {
            font-weight: 600;
            font-size: 14px;
            color: #374151;
        }

        .value {
            font-size: 14px;
            color: #4b5563;
        }

        .inline-details {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 24px;
        }

        .inline-details div {
            font-size: 14px;
            color: #374151;
        }

        .inline-details>div {
            flex: 1 1 auto;
            min-width: 120px;
        }

        footer {
            background: var(--accent-bg);
            font-size: 12px;
            padding: 16px;
            text-align: center;
            color: var(--text-muted);
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/CityLogo.png') }}" alt="City Travelers">
            <h1>Flight Voucher: {{ $task->reference }}</h1>
        </div>

        <div class="section gray">
            <div class="section-title">Passenger Information</div>
            <div class="row">
                <div class="info-box">
                    <div class="label">Name</div>
                    <div class="value">{{ $task->client->name }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Email</div>
                    <div class="value"><a href="mailto:{{ $task->client->email }}">{{ $task->client->email }}</a></div>
                </div>
            </div>
        </div>
        <div class="section">
            <div class="section-title">Flight Details</div>
            <div class="row">
                <div class="info-box">
                    <div class="label">Airline</div>
                    <div class="value">{{ $task->flightDetails->airline->name ?? 'Kuwait Airways' }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Flight</div>
                    <div class="value">{{ $task->flightDetails->flight_number }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Class</div>
                    <div class="value">{{ $task->flightDetails->class_type }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Seat No</div>
                    <div class="value">{{ $task->flightDetails->seat_no }}</div>
                </div>
            </div>
        </div>
        <div class="section gray">
            <div class="section-title">Departure</div>
            <div class="inline-details">
                <div><strong>Time:</strong> {{ $task->flightDetails->departure_time }}</div>
                @if ($task->flightDetails->airport_from)
                <div><strong>Airport:</strong> {{ $task->flightDetails->airport_from }}</div>
                @endif
                @if ($task->flightDetails->terminal_from)
                <div><strong>Terminal:</strong> {{ $task->flightDetails->terminal_from }}</div>
                @endif
            </div>
        </div>
        <div class="section">
            <div class="section-title">Arrival</div>
            <div class="inline-details">
                <div><strong>Time:</strong> {{ $task->flightDetails->arrival_time }}</div>
                @if ($task->flightDetails->airport_to)
                <div><strong>Airport:</strong> {{ $task->flightDetails->airport_to }}</div>
                @endif
                @if ($task->flightDetails->terminal_to)
                <div><strong>Terminal:</strong> {{ $task->flightDetails->terminal_to }}</div>
                @endif
            </div>
        </div>
        <div class="section gray">
            <div class="section-title">Additional Information</div>
            <div class="inline-details">
                @if ($task->flightDetails->baggage_allowed)
                <div><strong>Baggage:</strong> {{ $task->flightDetails->baggage_allowed }}</div>
                @endif
                @if ($task->flightDetails->equipment)
                <div><strong>Equipment:</strong> {{ $task->flightDetails->equipment }}</div>
                @endif
                @if ($task->flightDetails->flight_meal)
                <div><strong>Meal:</strong> {{ $task->flightDetails->flight_meal }}</div>
                @endif
            </div>
        </div>
        <footer>
            This voucher is valid for the specified flight only. Please present it at the check-in counter.
        </footer>
    </div>
</body>

</html>