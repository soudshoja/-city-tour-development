<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-info {
            flex: 1;
        }

        .company-info img {
            max-height: 80px;
            margin-bottom: 10px;
        }

        .company-info h3 {
            margin: 10px 0 5px 0;
            font-size: 18px;
        }

        .company-info p {
            margin: 2px 0;
            font-size: 11px;
        }

        .invoice-info {
            text-align: right;
            flex: 1;
        }

        .invoice-info h2 {
            color: #0066cc;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .invoice-info p {
            margin: 3px 0;
            font-size: 11px;
        }

        .client-section {
            margin-bottom: 30px;
        }

        .client-section h5 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #333;
        }

        .client-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .client-info strong {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .table th,
        .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }

        .table th {
            background-color: #333;
            color: white;
            font-weight: bold;
            text-align: center;
        }

        .table td:last-child,
        .table th:last-child {
            text-align: right;
        }

        .table tfoot th {
            background-color: #f8f9fa;
            color: #333;
            border-top: 2px solid #333;
        }

        .terms {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .terms h6 {
            font-size: 12px;
            margin-bottom: 10px;
        }

        .terms small {
            font-size: 10px;
            color: #666;
            line-height: 1.5;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <img src="{{ $companyLogoSrc }}" alt="Company Logo">
                <h3>{{ $company->name }}</h3>
                <p>{{ $company->address }}</p>
                <p>{{ $company->phone }}</p>
                <p>{{ $company->email }}</p>
            </div>
            <div class="invoice-info">
                <h2>PROFORMA INVOICE</h2>
                <p><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Date:</strong> {{ $invoice->created_at->format('d/m/Y') }}</p>
                <p><strong>Agent:</strong> {{ $invoice->agent->name }}</p>
            </div>
        </div>

        <!-- Client Information -->
        <div class="client-section">
            <h5>Bill To:</h5>
            <div class="client-info">
                <strong>{{ $invoice->client->name }}</strong>
                {{ $invoice->client->address }}<br>
                <span>{{ $invoice->client->country_code }}</span>{{ $invoice->client->phone }}
            </div>
        </div>

        <!-- Invoice Details Table -->
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Details</th>
                    <th style="width: 20%;">Supplier</th>
                    <th style="width: 15%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @php $grandTotal = 0; @endphp
                @foreach($invoiceDetails as $index => $detail)
                @php $grandTotal += $detail->task_price; @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    @if($detail->task->flightDetails)
                    <td>
                        <strong>Flight:</strong> {{ $detail->task->flightDetails->ticket_number }}<br>
                        <p>{{ $detail->task->reference }}</p>
                        <p>{{ $detail->task->additional_info ?? 'No additional information available' }}</p>
                    </td>
                    @elseif($detail->task->hotelDetails)
                    <td>
                        <strong>Hotel:</strong> {{ $detail->task->hotelDetails->hotel->name }}<br>
                        <strong>Check-in:</strong> {{ $detail->task->hotelDetails->readable_check_in }}<br>
                        <strong>Check-out:</strong> {{ $detail->task->hotelDetails->readable_check_out }}<br>
                        <strong>Room Name:</strong> {{ $detail->task->hotelDetails->room_name }}<br>
                        <strong>Nights:</strong> {{ $detail->task->hotelDetails->nights }}
                    </td>
                    @else
                    <td>
                        <p>-</p>
                    </td>
                    @endif
                    <td>{{ $detail->task->supplier->name ?? 'N/A' }}</td>
                    <td class="text-right">KWD{{ number_format($detail->task_price, 3) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Grand Total:</th>
                    <th class="text-right">KWD{{ number_format($grandTotal, 2) }}</th>
                </tr>
            </tfoot>
        </table>

        <!-- Terms and Conditions -->
        <div class="terms">
            <h6>Terms and Conditions:</h6>
            <small>
                This is a proforma invoice and serves as a quotation. Payment terms and conditions apply as per company policy.
            </small>
        </div>
    </div>
</body>

</html>