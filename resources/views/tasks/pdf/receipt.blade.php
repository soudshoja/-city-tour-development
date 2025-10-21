<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <title>Payment Receipt: {{ $invoiceDetail->invoice->invoice_number ?? 'N/A' }}</title>
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
            <x-application-logo
                :companyLogo="$selectedCompany?->logo ?? asset('images/UserPic.svg')"
                class="custom-logo-size inline-block" />
            <h1>Invoice Receipt: <strong>{{ $invoiceDetail->invoice->invoice_number ?? 'N/A' }}</strong></h1>
        </header>
        <main>
            <div class="section" style="text-align: center; padding: 24px;">
                <h2 style="margin-bottom: 8px; font-size: 22px; font-weight: 700;">KWD {{ $invoiceDetail->invoice->payment->amount ?? $invoiceDetail->invoice->amount }}</h2>
                <p style="margin: 0; color: var(--text-muted); font-size: 14px;">
                    Paid on {{ \Carbon\Carbon::parse($invoiceDetail->invoice->payment->payment_date ?? '1/1/2025')->format('d/m/Y \a\t H:i:s A') }}
                </p>
            </div>
            @if ($invoiceDetail->invoice->payment_type != 'credit')
                <div class="section">
                    <h2>Payment Summary</h2>
                    <div class="data-grid">
                        <div class="label">Payment Gateway:</div>
                        <div class="value">{{ $invoiceDetail->invoice->payment->payment_gateway ?? 'N/A' }}</div>

                        <div class="label">Status:</div>
                        <div class="value">{{ ucfirst($invoiceDetail->invoice?->payment?->status ?? 'Paid') }} </div>
                    </div>
                </div>
            @elseif ($invoiceDetail->invoice->payment_type == 'credit')
                <div class="section">
                    <h2>Payment Summary</h2>
                    <div class="data-grid">
                        <div class="label">Payment Type:</div>
                        <div class="value">{{ ucfirst($invoiceDetail->invoice?->payment_type) ?? 'N/A' }}</div>

                        <div class="label">Status:</div>
                        <div class="value">{{ ucfirst($invoiceDetail->invoice?->status) ?? 'N/A' }}</div>
                    </div>
                </div>
            @endif
            @if ($invoiceDetail->invoice->payment_type != 'credit')
                <div class="section">
                    <h2>Reference Details</h2>
                    <div class="data-grid">
                        <div class="label">Customer Name:</div>
                        <div class="value">
                        {{ $invoiceDetail->invoice?->payment?->from
                            ?? $invoiceDetail->invoice?->client?->name
                            ?? trim(
                            collect([
                                $invoiceDetail->invoice?->client?->first_name,
                                $invoiceDetail->invoice?->client?->middle_name,
                                $invoiceDetail->invoice?->client?->last_name
                            ])->filter()->join(' ')
                            )
                            ?? 'N/A' }}
                        </div>

                        <div class="label">Payment Receipt:</div>
                        <div class="value">{{ ucfirst($invoiceDetail->invoice->payment?->voucher_number) ?? 'N/A' }}</div>

                        <div class="label">Payment Reference:</div>
                        <div class="value">{{ $invoiceDetail->invoice->payment?->payment_reference ?? 'N/A' }}</div>

                        @if ($invoiceDetail->invoice->payment?->payment_gateway == 'Tap')
                        <div class="label">Authorization ID:</div>
                        <div class="value">{{ $invoiceDetail->invoice->payment->tapPayment->authorization_id ?? 'N/A' }}</div>
                        @elseif ($invoiceDetail->invoice->payment?->payment_gateway == 'MyFatoorah')
                        <div class="label">Invoice Reference:</div>
                        <div class="value">{{ $invoiceDetail->invoice->payment?->myFatoorahPayment->invoice_ref ?? 'N/A' }}</div>
                        @else
                        <div class="label">Credit:</div>
                        <div class="value">{{ 'N/A' }}</div>
                        @endif

                        <div class="label">Payment ID:</div>
                        @if ($invoiceDetail->invoice->payment?->payment_gateway == 'Tap')
                        <div class="value">{{ $invoiceDetail->invoice->payment?->tapPayment->tap_id ?? 'N/A' }}</div>
                        @elseif ($invoiceDetail->invoice->payment?->payment_gateway == 'MyFatoorah')
                        <div class="value">{{ $invoiceDetail->invoice->payment?->myFatoorahPayment->payment_id ?? 'N/A' }}</div>
                        @endif

                    </div>
                </div>
                <div class="section">
                    <h2>Customer Contact</h2>
                    <div class="data-grid">
                        <div class="label">Mobile Number:</div>
                        <div class="value"><a href="https://wa.me/{{ $invoiceDetail->invoice->client?->country_code}}{{ $invoiceDetail->invoice->client?->phone ?? 'N/A' }}">{{ $invoiceDetail->invoice->client?->country_code}}{{ $invoiceDetail->invoice->client?->phone ?? 'N/A' }}</a></div>
                    </div>
                </div>
            @elseif ($invoiceDetail->invoice->payment_type == 'credit')
                <div class="section">
                    <h2>Reference Details</h2>
                    <div class="data-grid">
                        <div class="label">Customer Name:</div>
                        <div class="value">
                        {{ $invoiceDetail->invoice?->payment?->from
                            ?? $invoiceDetail->invoice?->client?->name
                            ?? trim(
                            collect([
                                $invoiceDetail->invoice?->client?->first_name,
                                $invoiceDetail->invoice?->client?->middle_name,
                                $invoiceDetail->invoice?->client?->last_name
                            ])->filter()->join(' ')
                            )
                            ?? 'N/A' }}
                        </div>
                        <div class="label">Mobile Number:</div>
                        <div class="value"><a href="https://wa.me/{{ $invoiceDetail->invoice->client?->country_code}}{{ $invoiceDetail->invoice->client?->phone ?? 'N/A' }}">{{ $invoiceDetail->invoice->client?->country_code}}{{ $invoiceDetail->invoice->client?->phone ?? 'N/A' }}</a></div>
                    </div>
                </div>
            @endif
        </main>
        <footer style=" position: absolute; bottom: 0; left: 0; width: 100%; text-align: center; font-size: 14px; color: #555; padding: 20px 0;">           
            If you have any questions or concerns. Call us at <a href="https://wa.me/+96522204264">+965 22204264</a>.<br>
            Thank you for choosing us. Visit us at <a href="https://citytour.com">citytour.com</a>.
        </footer>
    </div>
</body>

</html>