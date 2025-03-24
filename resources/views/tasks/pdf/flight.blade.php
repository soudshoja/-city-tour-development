<html>

<head>
</head>

<body>
    <header>
        <h1>Flight Details</h1>
    </header>
    <main>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Flight</th>
                    <th>Departure</th>
                    <th>Arrival</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $flight->name }}</td>
                    <td>{{ $flight->departure }}</td>
                    <td>{{ $flight->arrival }}</td>
                    <td>{{ $flight->price }}</td>
                </tr>
            </tbody>
        </table>
    </main>
    <section>
        <p class="text-right"><strong>Total:</strong> {{ $flight->price }}</p>
    </section>
</body>

</html>