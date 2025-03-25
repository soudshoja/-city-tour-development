<!DOCTYPE html>
<html>
<head lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <title>
        Flight Voucher: {{ $task->reference }}
    </title>
    <style>
        :root {
            --gray100: #f4f4f4;
            --gray200: #ddd;
            --gray300: #888;
        }

        html {
            margin: 0;
            padding: 0;
            width: 1200px;
            margin: 0 auto;
        }
        header {
            background-color: var(--gray200);
            padding: 20px;
            text-align: center;
            display: flex;
            justify-content: space-between;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        main {
            background-color: var(--gray100);
            padding: 1em;
            display: flex;
            flex-direction: column;
        }

        section {
            background-color: var(--gray200);
            padding: 0;
        }
        footer {
            background-color: var(--gray100);
            padding: 10px;
            text-align: center;
        }

        section > p {
            padding : 0;
        }
        .voucher-title {
            font-size: 24px;
            font-weight: 300;
        }

        .voucher-details {
            margin: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .voucher-details > .items {
            display: flex;
            justify-content: space-between;
        }

        .voucher-details h2 {
            margin-top: 0;
        }

        .voucher-details p {
            margin: 5px 0;
        }

        .text-right {
            padding: 1em;
            margin: 0px;
            text-align: right;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>

<body>
    <header>
        <img src="{{ asset('images/CityLogo.png')}}" alt="Company Logo" width="100">
        <h1 class="voucher-title">Flight Voucher:<strong>
                {{ $task->reference }}
            </strong></h1>
    </header>
    <main>
        <div class="voucher-details">
            <h2>Passenger Information</h2>
            <p><strong>Name:</strong> {{ $task->client->name }}</p>
            <p><strong>Email:</strong> {{ $task->client->email }}</p>
        </div>
        <div class="voucher-details">
            <h1>Flight Information</h1>
            <h2> Kuwait Airways </h2>
            <p><strong>Flight:</strong> Flight 123</p>
            <p><strong>Departure: </strong> {{ $task->flightDetails->departure_place_time }}</p>
            <p><strong>Arrival: </strong>{{ $task->flightDetails->arrival_place_time}}</p>
            <hr>
            <div class="items">
                <p>Duration </p>
                <p> {{ $task->duration ?? $task->flightDetails->duration_by_calculate }} </p>
            </div>
            <div class="items">
                <p>Booking Status</p>
                <p>Confirmed</p>
            </div>
            <div class="items">
                <p>Class</p>
                <p>Economy</p>
            </div>
            <div class="items">
                <p>Equipment</p>
                <p>AIRBUS A220-300</p>
            </div>
            <div class="items">
                <p> Flight Meal </p>
                <p> Food and beverages for purchase</p>
            </div>
        </div>

    </main>
    <section>
        <p class="text-right"><strong>Total:</strong> $350</p>
    </section>
    <footer class="footer">
        <p>This voucher is valid for the specified flight only. Please present it at the check-in counter.</p>
    </footer>
</body>

</html>