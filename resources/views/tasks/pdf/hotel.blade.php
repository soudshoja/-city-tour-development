<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <title>Hotel Booking</title>
    <style>
        :root {
            --gray100: #f4f4f4;
            --gray200: #ddd;
            --gray300: #888;
            --blue50: #eff6ff;
            --blue100: #dbeafe;
            --blue200: #bfdbfe;
            --blue300: #93c5fd;
            --blue400: #60a5fa;
            --blue500: #3b82f6;
            --blue600: #2563eb;
            --blue700: #1d4ed8;
            --blue800: #1e40af;
            --blue900: #1e3a8a;
            --blue950: #172554;

        }
        header {
            background-color: var(--gray200);
            padding: 0.8em;
            text-align: center;
            display: flex;
            justify-content: space-between;
            margin-bottom: 1em;
        }
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4;
        }

        .container {
            width: 600px;
            background-color: white;
            padding: 1em;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .voucher-details {
            padding: 0.5em;
            display: flex;
            justify-content: space-between;
            gap: 2;
            border: 1px solid var(--gray200);
            border-radius: 8px;
            margin-bottom: 0.5em;
        }

        .booking-details,

        .booking-details div,
        .client-agent-info div {
            flex: 1;
        }

        .booking-details h2,
        .client-agent-info h2 {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 10px;
        }

        .booking-details p,
        .client-agent-info p {
            margin: 5px 0;
            font-size: 0.9em;
            color: #666;
        }

        .booking-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .booking-table th,
        .booking-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .booking-table th {
            background-color: #f2f2f2;
        }
        .additional-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">

        <header>
            <img src="{{ $companyLogoSrc }}" alt="City Travelers" width="100">
            <h3 class="voucher-title">Booking Voucher:<strong>{{ $hotelDetails->room_reference }}</strong></h3>
        </header>
        <main>
            <div class="voucher-details">
                <div class="client-agent-info">
                    <h4>Client Information</h4>
                    <p> {{ $task->client->name }}</p>
                    @if($task->client->email)
                    <p><a href="mailto:{{ $task->client->email }}">{{ $task->client->email }}</a></p>
                    @endif
                </div>
                <div class="client-agent-info">
                    <h4>Booked By</h4>
                    <p> {{ $task->agent->name }}</p>
                    @if($task->agent->email)
                    <p><a href="mailto:{{ $task->agent->email }}">{{ $task->agent->email }}</a></p>
                    @endif
                </div>
            </div>
        </main>

        <table class="booking-table">
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
                    <td> {{ $hotelDetails->room->name }} </td>
                    <td> {{ $hotelDetails->room->adult_quantity }} </td>
                    <td> {{ $hotelDetails->room->child_quantity }} </td>
                </tr>
            </tbody>
        </table>

        <div class="total-amount">
            <!-- <p style="font-size: 0.8em;">*VAT: 7.50%</p> -->
        </div>

        <div class="additional-info">
            <h2>Additional Information</h2>
            <p>Check in from 1 PM, check-out until 11 AM.</p>
            <p>Free parking on site.</p>
        </div>
    </div>
</body>

</html>