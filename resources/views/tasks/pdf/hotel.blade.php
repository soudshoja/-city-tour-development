<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <title>Hotel Booking</title>
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

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table thead {
            background: var(--accent-bg);
        }

        table th,
        table td {
            padding: 12px;
            border: 1px solid var(--border);
            text-align: left;
            color: var(--text-dark);
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
            <img src="{{ asset('images/CityLogo.png')}}" alt="City Travelers Logo">
            <h1>Booking Voucher: <strong>{{ $hotelDetails->room->reference }}</strong></h1>
        </header>

        <main>
            <div class="section">
                <h2>Guest & Agent Information</h2>
                <div class="info-grid">
                    <div class="info-box">
                        <p><strong>Client Name:</strong> {{ $task->client->name }}</p>
                        <p><strong>Email: </strong><a href="mailto:{{ $task->client->email }}">{{ $task->client->email }}</a></p>
                    </div>
                    <div class="info-box">
                        <p><strong>Booked By:</strong> {{ $task->agent->name }}</p>
                        <p><strong>Email: </strong><a href="mailto:{{ $task->agent->email }}">{{ $task->agent->email }}</a></p>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>Room Details</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Room Amount</th>
                            <th>Description</th>
                            <th>Adult Quantity</th>
                            <th>Child Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>{{ $hotelDetails->room->name ?? 'n/a' }}</td>
                            <td>{{ $hotelDetails->room->adult_quantity ?? 0 }}</td>
                            <td>{{ $hotelDetails->room->child_quantity ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h2>Additional Information</h2>
                <ul style="padding-left: 18px; margin: 0; list-style-type: disc; color: #1f2937;">
                    <li style="margin-bottom: 4px;">Check-in from <strong>1 PM</strong>, check-out by <strong>11 AM</strong>.</li>
                    <li>Free parking is available on site.</li>
                </ul>
            </div>
        </main>

        <footer>
            For inquiries, contact our support team or visit <a href="https://google.com">citytravelers.com</a>.
        </footer>
    </div>
</body>

</html>