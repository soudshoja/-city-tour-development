<!DOCTYPE html>
<html lang="{{ $language ?? 'en' }}" dir="{{ ($language ?? 'en') === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f3f4f6;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .success-banner {
            background: linear-gradient(to right, #1b3f20, #1d832a);
            color: #ffffff;
            padding: 12px 24px;
            text-align: center;
        }
        .success-banner h1 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 0;
            display: inline;
        }
        .success-banner .checkmark {
            width: 28px;
            height: 28px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            vertical-align: middle;
        }
        .success-banner .checkmark svg {
            width: 16px;
            height: 16px;
        }
        .content {
            padding: 16px 20px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            padding-bottom: 12px;
        }
        .header-cell {
            display: table-cell;
            width: 33.33%;
            vertical-align: middle;
        }
        .header-left {
            text-align: left;
        }
        .header-center {
            text-align: center;
        }
        .header-center h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }
        .header-center p {
            font-size: 14px;
            color: #6b7280;
        }
        .header-right {
            text-align: right;
        }
        .company-logo img {
            max-height: 50px;
            width: auto;
        }
        .billing-section {
            display: table;
            width: 100%;
            margin-bottom: 16px;
        }
        .billing-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .billing-box h3 {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .billing-box p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .billing-box a {
            color: #2563eb;
            text-decoration: none;
        }
        .billing-box a:hover {
            text-decoration: underline;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .details-table th {
            background-color: #f9fafb;
            padding: 8px 12px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
        }
        .details-table td {
            padding: 8px 12px;
            font-size: 13px;
            color: #4b5563;
        }
        .details-table tr:last-child td {
            border-bottom: 1px solid #e5e7eb;
        }
        .details-table .label {
            font-weight: 500;
            color: #374151;
        }
        .details-table .value {
            text-align: right;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .items-table th {
            background-color: #f9fafb;
            padding: 12px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        .items-table td {
            padding: 12px 16px;
            font-size: 14px;
            color: #4b5563;
            border-bottom: 1px solid #e5e7eb;
        }
        .items-table tfoot td {
            background-color: #f9fafb;
            font-weight: 700;
            color: #1f2937;
            border-top: 2px solid #e5e7eb;
        }
        .amount-wrapper {
            display: table;
            width: 100%;
            margin-bottom: 16px;
        }
        .amount-left {
            display: table-cell;
            width: 50%;
            vertical-align: middle;
        }
        .amount-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .amount-summary {
            border-radius: 8px;
            padding: 16px 20px;
            width: 250px;
            margin-left: auto;
        }
        .amount-row {
            font-size: 16px;
            display: table;
            width: 100%;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .amount-row:last-child {
            border-bottom: none;
            padding-top: 8px;
        }
        .amount-row .label {
            display: table-cell;
            font-size: 14px;
            color: #6b7280;
        }
        .amount-row .value {
            display: table-cell;
            text-align: right;
            font-size: 14px;
            color: #1f2937;
            font-weight: 600;
        }
        .amount-row.total .label,
        .amount-row.total .value {
            font-weight: 700;
            color: #1f2937;
        }
        .paid-badge {
            display: inline-block;
            background-color: #dcfce7;
            color: #166534;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .footer {
            text-align: center;
            padding: 16px 20px;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .footer a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 12px;
        }
        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }
            .content {
                padding: 20px;
            }
            .billing-box {
                display: block;
                width: 100%;
                margin-bottom: 20px;
            }
            .details-table td,
            .details-table th,
            .items-table td,
            .items-table th {
                padding: 10px 12px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="success-banner">
            <span class="checkmark">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </span>
            <h1>Payment Successful</h1>
        </div>

        <div class="content">
            <div class="header">
                <div class="header-cell header-left">
                    @if($payment->agent->branch->company->logo)
                    <div class="company-logo">
                        <img src="{{ asset('storage/' . $payment->agent->branch->company->logo) }}" alt="Company Logo">
                    </div>
                    @endif
                </div>
                <div class="header-cell header-center">
                    <h2>Payment Voucher</h2>
                    <p><strong>{{ $payment->voucher_number }}</strong></p>
                    <p>Date: {{ $payment->created_at->format('d M Y') }}</p>
                </div>
                <div class="header-cell header-right"></div>
            </div>

            <!-- Billing Section -->
            <div class="billing-section">
                <div class="billing-box">
                    <h3>Billed To</h3>
                    <p><strong>{{ $payment->client->full_name }}</strong></p>
                    <p><a href="mailto:{{ $payment->client->email }}">{{ $payment->client->email }}</a></p>
                    <p>{{ $payment->client->country_code }}{{ $payment->client->phone }}</p>
                </div>
                <div class="billing-box" style="text-align: right;">
                    <h3>{{ $payment->agent->branch->company->name }}</h3>
                    <p>{{ $payment->agent->branch->company->address }}</p>
                    <p><a href="mailto:{{ $payment->agent->branch->company->email }}">{{ $payment->agent->branch->company->email }}</a></p>
                    <p>{{ $payment->agent->branch->company->phone }}</p>
                </div>
            </div>

            <!-- Payment Details Table -->
            <table class="details-table">
                <thead>
                    <tr>
                        <th colspan="2">Payment Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="label">Client Name</td>
                        <td class="value">{{ $payment->client->full_name }}</td>
                    </tr>
                    @if($payment->paymentMethod)
                    <tr>
                        <td class="label">Payment Method</td>
                        <td class="value">{{ $payment->paymentMethod->english_name ?? '-' }}</td>
                    </tr>
                    @endif
                    @if(!empty($payment->payment_reference))
                    <tr>
                        <td class="label">Payment Reference</td>
                        <td class="value">{{ $payment->payment_reference }}</td>
                    </tr>
                    @endif
                    @if(!empty($invoiceRef))
                    <tr>
                        <td class="label">Invoice Reference</td>
                        <td class="value">{{ $invoiceRef }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>

            <!-- Payment Items (if available) -->
            @if($payment->paymentItems && $payment->paymentItems->count() > 0)
            <h3 class="section-title">Payment Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payment->paymentItems as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ number_format($item->quantity, 2) }}</td>
                        <td>{{ number_format($item->unit_price, 3) }} {{ $item->currency }}</td>
                        <td style="text-align: right; font-weight: 600;">{{ number_format($item->extended_amount, 3) }} {{ $item->currency }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;">Total:</td>
                        <td style="text-align: right;">{{ number_format($payment->paymentItems->sum('extended_amount'), 3) }} {{ $payment->currency }}</td>
                    </tr>
                </tfoot>
            </table>
            @endif

            <!-- Amount Summary -->
            <div class="amount-wrapper">
                <div class="amount-left">
                    <span class="paid-badge">PAID</span>
                </div>
                <div class="amount-right">
                    <div class="amount-summary">
                        <div class="amount-row">
                            <span class="label">Amount:</span>
                            <span class="value">{{ number_format($payment->amount, 3) }} {{ $payment->currency }}</span>
                        </div>
                        <div class="amount-row total">
                            <span class="label">Total:</span>
                            <span class="value">{{ number_format($payment->amount, 3) }} {{ $payment->currency }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>If you have any questions about this payment, please contact:</p>
            <p>
                <strong>{{ $payment->agent->name }}</strong><br>
                <a href="mailto:{{ $payment->agent->email }}">{{ $payment->agent->email }}</a>
                @if($payment->agent->phone_number)
                <br>{{ $payment->agent->phone_number }}
                @endif
            </p>
            <p style="margin-top: 16px; font-size: 12px; color: #9ca3af;">
                This is an automated email from {{ $payment->agent->branch->company->name }}
            </p>
        </div>
    </div>
</body>

</html>