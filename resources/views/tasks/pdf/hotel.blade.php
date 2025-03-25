<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <title>Hotel Booking</title>
    <style>

        :root{
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
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 2.5em;
            color: var(--blue600);
            margin-bottom: 5px;
        }

        .header p {
            margin: 5px 0;
            font-size: 0.9em;
            color: #666;
        }

        .header a {
            text-decoration: none;
            text-decoration: none;
        }

        .booking-details, .booked-by {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .booking-details div, .booked-by div {
            flex: 1;
        }

        .booking-details h2, .booked-by h2 {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 10px;
        }

        .booking-details p, .booked-by p {
            margin: 5px 0;
            font-size: 0.9em;
            color: #666;
        }

        .booking-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .booking-table th, .booking-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .booking-table th {
            background-color: #f2f2f2;
        }

        .booking-table .amount {
            text-align: right;
        }

        .total-amount {
            text-align: right;
            margin-bottom: 10px;
        }

        .total-amount p {
            margin: 5px 0;
            font-weight: bold;
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
        <div class="header">
            <h1>{{ $hotelDetails->hotel->name }}</h1>
            <p>{{ $hotelDetails->hotel->address ?? $hotelDetails->hotel->name }}</p>
            <p>{{ $hotelDetails->hotel->phone ?? '+990'}} | <a href="mailto:villa.contentezza@gmail.com">{{ $hotelDetails->hotel->email ?? 'hotel@email.com' }}</a></p>
            @if($hotelDetails->hotel->website)
                <p><a href="{{ $hotelDetails->hotel->website }}">{{ $hotelDetails->hotel->website }}</a></p>
            @endif
        </div>

        <div class="booking-details">
            <div>
                <h2>Booking Details</h2>
                <p><strong>Check-in:</strong> {{ $hotelDetails->readable_check_in }}</p>
                <p><strong>Check-in:</strong> {{ $hotelDetails->readable_check_out }}</p>
                <p><strong>Guests:</strong> {{ $hotelDetails->room->adult_quantity }} Adults, {{ $hotelDetails->room->child_quantity }} Children</p>
            </div>
            <div>
                <h2>BOOKING</h2>
                <p><strong>Booking #:</strong> {{ $hotelDetails->room_reference }}</p>
                <p><strong>Booking Date:</strong> {{ date('d M, Y', strtotime($hotelDetails->booking_time)) }}</p>
                <p><strong>Status:</strong> {{ $task->status }}</p>
            </div>
        </div>

        <div class="booked-by">
            <div>
                <h2>Booked By</h2>
                <p> {{ $task->agent->name }}</p>
                @if($task->agent->email)
                    <p><a href="mailto:{{ $task->agent->email }}">{{ $task->agent->email }}</a></p>
                @endif
            </div>
        </div>

        <table class="booking-table">
            <thead>
                <tr>
                    <th>Quantity</th>
                    <th>Description</th>
                    <th>Unit Price</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
               <tr>
                    <td>1</td>
                    <td> {{ $hotelDetails->room->name }} </td>
                    <td>KD {{ $task->total }} </td>
                    <td>KD {{ $task->total }} </td>
               </tr> 
                <!-- <tr>
                    <td>7.00</td>
                    <td>Nights in apartment Lido</td>
                    <td>£ 100.00</td>
                    <td class="amount">£ 700.00</td>
                </tr>
                <tr>
                    <td>28.00</td>
                    <td>Breakfast</td>
                    <td>£ 10.00</td>
                    <td class="amount">£ 280.00</td>
                </tr>
                <tr>
                    <td>1.00</td>
                    <td>Airport pick-up</td>
                    <td>£ 80.00</td>
                    <td class="amount">£ 80.00</td>
                </tr> -->
            </tbody>
        </table>

        <div class="total-amount">
            <p>Subtotal: KD {{ $task->total }}</p>
            <p>VAT: KD {{ $task->tax ?? 0.00}}</p>
            <p><strong>Total: {{ $task->total }}</strong></p>
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