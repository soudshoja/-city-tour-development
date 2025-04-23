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
            margin: 0;
            padding: 32px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--accent-bg);
            display: flex;
            justify-content: center;
        }

        .container {
            width: 600px;
            background: var(--primary-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.08);
        }

        header {
            background: var(--highlight);
            color: white;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header img {
            height: 48px;
        }

        header h1 {
            font-size: 18px;
            font-weight: 500;
        }

        main {
            padding: 28px 32px;
        }

        .section {
            background: var(--section-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .section h2 {
            font-size: 16px;
            color: var(--text-dark);
            margin: 0 0 18px 0;
            font-weight: 600;
            text-align: center;
        }

        .section p,
        .items {
            margin: 6px 0;
            font-size: 14px;
            color: var(--text-muted);
        }

        .highlight {
            font-weight: 600;
            color: var(--text-dark);
        }

        .info-grid {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
        }

        .info-box {
            flex: 1;
        }

        .info-box p {
            margin: 4px 0;
            font-size: 14px;
            color: var(--text-muted);
        }

        .info-box strong {
            color: var(--text-dark);
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            row-gap: 10px;
            column-gap: 32px;
        }

        .details-grid div {
            font-size: 14px;
            color: var(--text-muted);
        }

        .details-grid div span {
            display: block;
        }

        .label {
            font-weight: 600;
            color: var(--text-dark);
        }

        .spacer {
            height: 16px;
            border-top: 1px dashed var(--border);
            margin: 16px 0;
        }

        .items {
            display: flex;
            justify-content: space-between;
        }

        footer {
            background: var(--accent-bg);
            font-size: 12px;
            padding: 16px;
            text-align: center;
            color: var(--text-muted);
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <img src="{{ asset('images/CityLogo.png')}}" alt="City Travelers">
            <h1>Flight Voucher: <strong>{{ $task->reference }}</strong></h1>
        </header>

        <main>
            <div class="section">
                <h2>Passenger Information</h2>
                <div class="info-grid">
                    <div class="info-box">
                        <p><strong>Name:</strong> {{ $task->client->name }}</p>
                    </div>
                    <div class="info-box">
                        <p><strong>Email: </strong><a href="mailto:{{ $task->client->email }}">{{ $task->client->email }}</a></p>
                    </div>
                </div>
            </div>
            <div class="section">
                <h2>Flight Information</h2>
                <div class="details-grid">
                    <div>
                        <span class="label">Airline:</span>
                        <span>Kuwait Airways</span>
                    </div>
                    <div>
                        <span class="label">Flight:</span>
                        <span>{{ $task->flightDetails->flight_number }}</span>
                    </div>

                    <div>
                        <span class="label">Departure:</span>
                        <span>{{ $task->flightDetails->departure_place_time }}</span>
                    </div>
                    <div>
                        <span class="label">Arrival:</span>
                        <span>{{ $task->flightDetails->arrival_place_time }}</span>
                    </div>
                </div>
                <div class="spacer"></div>
                <div class="details-grid">
                    <div>
                        <span class="label">Duration:</span>
                        <span>{{ $task->duration ?? $task->flightDetails->duration_by_calculate }}</span>
                    </div>
                    <div>
                        <span class="label">Booking Status:</span>
                        <span>Confirmed</span>
                    </div>
                    <div>
                        <span class="label">Class:</span>
                        <span>Economy</span>
                    </div>
                    <div>
                        <span class="label">Equipment:</span>
                        <span>AIRBUS A220-300</span>
                    </div>
                    <div style="grid-column: span 2;">
                        <span class="label">Flight Meal:</span>
                        <span>Food and beverages for purchase</span>
                    </div>
                </div>
            </div>
        </main>
        <footer>
            This voucher is valid for the specified flight only. Please present it at the check-in counter.
        </footer>
    </div>
</body>

</html>