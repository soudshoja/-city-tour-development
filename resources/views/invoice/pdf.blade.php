<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Invoice - {{ $invoice->invoice_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background: #fff;
            padding: 20px;
            color: #333;
        }

        .invoice-container {
            max-width: 800px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
        }

        .header,
        .footer {
            text-align: center;
            margin-bottom: 20px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .invoice-table th {
            background: #f4f4f4;
        }

        .text-right {
            text-align: right;
        }

        .total-section {
            margin-top: 20px;
        }

        .btn-print {
            display: block;
            width: 100px;
            margin: 20px auto;
            padding: 10px;
            text-align: center;
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        @media print {
            .btn-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="invoice-container">
        <div class="header">
            <img src="{{ url()->previous() }}/images/CityLogo.png" width="100px" alt="Company Logo">

            <h2>INVOICE</h2>
            <p>Invoice #{{ $invoice->invoice_number }}</p>
            <p>Date: {{ $invoice->created_at->format('d M, Y') }}</p>
        </div>

        <div>
            <strong>Bill To:</strong>
            <p>{{ $invoice->client->name ?? 'N/A' }}<br>
                {{ $invoice->client->address ?? 'N/A' }}<br>
                {{ $invoice->client->email ?? 'N/A' }}</p>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoiceDetails as $detail)
                    <tr>
                        <td>{{ $detail->task_description ?? 'N/A' }}</td>
                        <td>{{ $detail->quantity ?? 0 }}</td>
                        <td>{{ number_format($detail->task_price ?? 0, 2) }}</td>
                        <td>{{ number_format(($detail->quantity ?? 0) * ($detail->task_price ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-section text-right">
            <p><strong>Subtotal:</strong> {{ number_format($invoice->amount, 2) }}</p>
            <p><strong>Tax ({{ $invoice->tax_rate }}%):</strong> {{ number_format($invoice->tax, 2) }}</p>
            <h3><strong>Total:</strong> {{ $invoice->currency }}
                {{ number_format($invoice->amount + $invoice->tax, 2) }}</h3>
        </div>

        <div class="footer">
            <p>If you have any questions, contact:</p>
            <p>{{ $invoice->agent->branch->company->name }}<br>
                {{ $invoice->agent->branch->company->phone }}<br>
                {{ $invoice->agent->branch->company->email }}</p>
        </div>

    </div>
</body>

</html>
