{{--
    Sent to the CLIENT when a gateway charge fails. The agency's own name leads,
    falling back to the platform when the payment has no company resolved.

    Deliberately plain about what happened and what to do next: a failed card or
    KNET attempt is usually recoverable by simply trying again, and the same link
    stays valid until its expiry date, so `$paymentUrl` is passed only while it
    still is.
--}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payment could not be completed</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            color: #333;
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }

        .brand-name {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #0f1c59;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status {
            text-align: center;
            background-color: #fdf0f1;
            border: 1px solid #f3c9cd;
            color: #a11d2c;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            margin: 26px 0;
        }

        p {
            font-size: 15px;
            line-height: 1.6;
            margin: 15px 0;
        }

        table.detail {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }

        table.detail td {
            padding: 8px 0;
            border-bottom: 1px solid #eef1f8;
        }

        table.detail td.label {
            color: #656d94;
            width: 45%;
        }

        .button {
            display: inline-block;
            background-color: #0f1c59;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
        }

        .footer {
            margin-top: 40px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="brand-name">{{ $company->name ?? config('app.name') }}</div>

        <p>Hello{{ $payment->from ? ' ' . $payment->from : '' }},</p>

        <div class="status">Your payment did not go through</div>

        <p>The bank or card issuer did not complete this payment. No money has been taken.</p>

        <table class="detail">
            @if ($payment->voucher_number)
                <tr>
                    <td class="label">Reference</td>
                    <td>{{ $payment->voucher_number }}</td>
                </tr>
            @endif
            @if ($payment->invoice_reference)
                <tr>
                    <td class="label">Invoice</td>
                    <td>{{ $payment->invoice_reference }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Amount</td>
                <td>{{ number_format((float) $payment->amount, 3) }} {{ $payment->currency }}</td>
            </tr>
        </table>

        @if ($paymentUrl)
            <p>You can try again using the same link:</p>
            <p><a class="button" href="{{ $paymentUrl }}" target="_blank">Try the payment again</a></p>
            <p style="word-break: break-all; font-size: 13px; color: #656d94;">{{ $paymentUrl }}</p>
        @else
            <p>Please contact us and we will send you a new payment link.</p>
        @endif

        @if ($company?->phone)
            <p>Questions? Call us on {{ $company->phone }}.</p>
        @endif

        <div class="footer">
            &copy; {{ date('Y') }} {{ $company->name ?? config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>

</html>
