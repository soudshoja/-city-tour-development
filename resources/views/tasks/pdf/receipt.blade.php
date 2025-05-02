<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <title>Payment Receipt: {{ $invoiceDetail->invoice->payment->voucher_number }}</title>
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
            padding: 16px;
            margin-bottom: 20px;
        }

        .section h2 {
            font-size: 16px;
            color: var(--text-dark);
            margin: 0 0 18px 0;
            font-weight: 600;
            text-align: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }

        .data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            row-gap: 12px;
            column-gap: 24px;
            font-size: 14px;
        }

        .label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .value {
            color: var(--text-dark);
            font-weight: 600;
            text-align: right;
        }

        .value a {
            color: var(--primary);
            text-decoration: none;
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
            <h1>Payment Receipt: <strong>{{ $invoiceDetail->invoice->payment->voucher_number }}</strong></h1>
        </header>
        <main>
            <div class="section" style="text-align: center; padding: 24px;">
                <h2 style="margin-bottom: 8px; font-size: 22px; font-weight: 700;">{{ $invoiceDetail->invoice->payment->currency }} {{ $invoiceDetail->invoice->payment->amount }}</h2>
                <!-- <p style="margin: 0; color: var(--text-muted); font-size: 14px;">Paid on {{ $invoiceDetail->invoice->payment->payment_date }}</p> -->
                <p style="margin: 0; color: var(--text-muted); font-size: 14px;">
                    Paid on {{ \Carbon\Carbon::parse($invoiceDetail->invoice->payment->payment_date)->format('d/m/Y \a\t H:i:s A') }}
                </p>
            </div>
            <div class="section">
                <h2>Payment Summary</h2>
                <div class="data-grid">
                    <div class="label">Payment Gateway:</div>
                    <div class="value">{{ $invoiceDetail->invoice->payment->payment_method }}</div>
                    <div class="label">Result:</div>
                    <div class="value">{{ $invoiceDetail->invoice->payment->status }}</div>
                    <div class="label">Authorization ID:</div>
                    <div class="value">{{ $invoiceDetail->invoice->payment->tapPayment->authorization_id }}</div>
                </div>
            </div>
            <div class="section">
                <h2>Reference Details</h2>
                <div class="data-grid">
                    <div class="label">Receipt ID:</div>
                    <div class="value">{{ $invoiceDetail->invoice->payment->tapPayment->receipt_id }}</div>
                    <div class="label">Merchant Track ID:</div>
                    <div class="value">847756_flight_22909</div>
                    <div class="label">Reference ID:</div>
                    <div class="value">510888027013</div>
                    <div class="label">Payment ID:</div>
                    <div class="value">{{ $invoiceDetail->invoice->payment->payment_reference }}</div>
                </div>
            </div>
            <div class="section">
                <h2>Customer Contact</h2>
                <div class="data-grid">
                    <div class="label">Mobile Number:</div>
                    <div class="value"><a href="https://wa.me/{{ $invoiceDetail->invoice->payment->client->phone }}">{{ $invoiceDetail->invoice->payment->client->phone }}</a></div>
                </div>
            </div>
        </main>
        <footer>
            If you have any questions or concerns. Call us at <a href="https://wa.me/+96522204264">+965 22204264</a>.<br>
            Thank you for choosing us. Visit us at <a href="https://citytour.com">citytour.com</a>.
        </footer>
    </div>
</body>

</html>