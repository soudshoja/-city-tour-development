<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Invoice Delivery</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9fafb;
            color: #333;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            padding: 35px 40px;
            border: 1px solid #e5e7eb;
        }
        .logo {
            display: block;
            margin: 0 auto 10px;
            max-height: 80px;
        }
        .brand {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #004c9e;
            margin-bottom: 20px;
        }
        h2 {
            text-align: center;
            color: #004c9e;
            font-size: 24px;
            margin-bottom: 25px;
        }
        .intro {
            background: #f0f7ff;
            padding: 15px 20px;
            border-left: 4px solid #004c9e;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 15px;
            line-height: 1.6;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .invoice-table thead {
            background-color: #004c9e;
            color: #ffffff;
        }
        .invoice-table th {
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .invoice-table th:last-child {
            text-align: right;
        }
        .invoice-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .invoice-table tbody tr:hover {
            background-color: #f0f7ff;
        }
        .invoice-table td:last-child {
            text-align: right;
            font-weight: 600;
            color: #004c9e;
        }
        .invoice-number {
            font-weight: 600;
            color: #004c9e;
        }
        .summary {
            background: #f9fafb;
            padding: 15px 20px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: center;
        }
        .summary-text {
            font-size: 15px;
            color: #666;
            margin: 5px 0;
        }
        .summary-highlight {
            font-size: 18px;
            font-weight: 700;
            color: #004c9e;
            margin: 10px 0;
        }
        .footer {
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            margin-top: 30px;
        }
        .footer-note {
            margin: 8px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        @if(isset($company) && $company->logo)
            <img src="{{ asset('storage/' . $company->logo) }}" alt="{{ $company->name ?? 'Company' }}" class="logo">
        @endif

        <div class="brand">{{ $company->name ?? 'City Travelers' }}</div>

        <h2>Invoice Delivery</h2>

        <div class="intro">
            <strong>{{ $invoices->count() }}</strong> invoice{{ $invoices->count() !== 1 ? 's have' : ' has' }} been created from bulk upload:
            <strong>{{ $bulkUpload->original_filename ?? 'N/A' }}</strong>
        </div>

        @if($invoices->isNotEmpty())
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td class="invoice-number">{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->client->full_name ?? 'N/A' }}</td>
                            <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
                            <td>{{ number_format($invoice->amount ?? 0, 2) }} {{ $invoice->currency ?? 'KWD' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="summary">
                <p class="summary-text">Total Amount</p>
                <p class="summary-highlight">
                    {{ number_format($invoices->sum('amount'), 2) }} {{ $invoices->first()->currency ?? 'KWD' }}
                </p>
            </div>
        @endif

        <div style="margin: 25px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 6px;">
            <p style="margin: 0; font-size: 14px; color: #856404;">
                <strong>Note:</strong> All invoice PDFs are attached to this email.
            </p>
        </div>

        <div class="footer">
            <p class="footer-note">
                This is an automated message. Please do not reply to this email.
            </p>
            <p class="footer-note">
                &copy; {{ date('Y') }} {{ $company->name ?? 'City Travelers' }}. All rights reserved.
            </p>
            <p class="footer-note">
                {{ $company->email ?? '' }} | {{ $company->phone ?? '' }}
            </p>
        </div>
    </div>
</body>
</html>
