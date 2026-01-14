<!DOCTYPE html>
<html lang="{{ $language ?? 'en' }}" dir="{{ ($language ?? 'en') === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Voucher {{ $payment->voucher_number }}</title>
    @if($isPdf ?? false)
    <style>
        @page {
            margin: 25px;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
        }
    </style>
    @endif
</head>

<body style="margin:0;padding:0;font-family:{{ ($isPdf ?? false) ? 'DejaVu Sans,' : '' }}Arial,Helvetica,sans-serif;background-color:{{ ($isPdf ?? false) ? '#ffffff' : '#f5f5f5' }};">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ ($isPdf ?? false) ? '#ffffff' : '#f5f5f5' }};">
        <tr>
            <td align="center" style="padding:{{ ($isPdf ?? false) ? '0' : '30px 20px' }};">
                <table role="presentation" width="{{ ($isPdf ?? false) ? '100%' : '700' }}" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.1);' }}">

                    <tr>
                        <td style="background: linear-gradient(135deg, #166534 0%, #15803d 50%, #22c55e 100%);padding:{{ ($isPdf ?? false) ? '12px 25px' : '16px 40px' }};text-align:center;{{ ($isPdf ?? false) ? '' : 'border-radius:4px 4px 0 0;' }}">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="vertical-align:middle;padding-right:10px;">
                                                    <div style="width:{{ ($isPdf ?? false) ? '24px' : '32px' }};height:{{ ($isPdf ?? false) ? '24px' : '32px' }};background-color:rgba(255,255,255,0.2);border-radius:50%;text-align:center;line-height:{{ ($isPdf ?? false) ? '24px' : '32px' }};">
                                                        <span style="color:#ffffff;font-size:{{ ($isPdf ?? false) ? '14px' : '18px' }};font-weight:bold;">&#10003;</span>
                                                    </div>
                                                </td>
                                                <td style="vertical-align:middle;">
                                                    <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '16px' : '22px' }};font-weight:bold;color:#ffffff;letter-spacing:1px;">Payment Successful</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '20px 25px' : '30px 40px' }};border-bottom:3px solid #166534;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="50%" valign="top">
                                        @if($payment->agent->branch->company->logo)
                                            @if($isPdf ?? false)
                                                <img src="{{ storage_path('app/public/' . $payment->agent->branch->company->logo) }}" alt="{{ $payment->agent->branch->company->name ?? 'Company' }}" style="max-height:50px;max-width:150px;margin-bottom:10px;">
                                            @else
                                                <img src="{{ asset('storage/' . $payment->agent->branch->company->logo) }}" alt="{{ $payment->agent->branch->company->name ?? 'Company' }}" style="max-height:60px;max-width:180px;margin-bottom:15px;">
                                            @endif
                                        @endif
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '16px' : '20px' }};font-weight:bold;color:#166534;">{{ $payment->agent->branch->company->name ?? 'Company' }}</p>
                                        <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $payment->agent->branch->company->address ?? '' }}</p>
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $payment->agent->branch->company->phone ?? '' }}</p>
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $payment->agent->branch->company->email ?? '' }}</p>
                                    </td>
                                    <td width="50%" valign="top" align="right">
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '28px' : '34px' }};font-weight:bold;color:#166534;">PAYMENT VOUCHER</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:{{ ($isPdf ?? false) ? '10px' : '15px' }};margin-left:auto;">
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Voucher #:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#333;">{{ $payment->voucher_number }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Date:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ $payment->created_at->format('d/m/Y') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Time:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ $payment->created_at->format('H:i') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Status:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};">
                                                    <span style="display:inline-block;padding:3px {{ ($isPdf ?? false) ? '8px' : '12px' }};background-color:#dcfce7;color:#166534;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;border-radius:12px;text-transform:uppercase;">PAID</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '15px 25px' : '25px 40px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="50%" valign="top">
                                        <p style="margin:0 0 {{ ($isPdf ?? false) ? '6px' : '10px' }} 0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;color:#166534;text-transform:uppercase;letter-spacing:1px;">Bill To</p>
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#333;text-transform:uppercase;">{{ $payment->client->full_name ?? 'N/A' }}</p>
                                        <p style="margin:{{ ($isPdf ?? false) ? '3px' : '5px' }} 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $payment->client->email ?? 'N/A' }}</p>
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $payment->client->country_code ?? '+965' }}{{ $payment->client->phone ?? 'N/A' }}</p>
                                    </td>
                                    <td width="50%" valign="top" style="text-align:right;">
                                        <p style="margin:0 0 {{ ($isPdf ?? false) ? '6px' : '10px' }} 0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;color:#166534;text-transform:uppercase;letter-spacing:1px;">Agent</p>
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#333;text-transform:uppercase;">{{ $payment->agent->name ?? 'N/A' }}</p>
                                        <p style="margin:{{ ($isPdf ?? false) ? '3px' : '5px' }} 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $payment->agent->email ?? 'N/A' }}</p>
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $payment->agent->phone_number ?? 'N/A' }}</p>
                                        @if($payment->agent->branch ?? null)
                                            <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">Branch: {{ $payment->agent->branch->name ?? 'N/A' }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 {{ ($isPdf ?? false) ? '25px 15px 25px' : '40px 25px 40px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e0e0e0;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;overflow:hidden;' }}">
                                <tr>
                                    <th colspan="2" style="background-color:#166534;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;letter-spacing:1px;">Payment Details</th>
                                </tr>
                                <tr style="background-color:#ffffff;">
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;border-bottom:1px solid #e0e0e0;width:40%;">Client Name</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;font-weight:bold;">{{ $payment->client->full_name ?? 'N/A' }}</td>
                                </tr>
                                @if($payment->paymentMethod->paymentMethodGroup)
                                <tr style="background-color:#f9fafb;">
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;border-bottom:1px solid #e0e0e0;">Payment Method</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;font-weight:bold;">{{ $payment->paymentMethod->paymentMethodGroup->name ?? '-' }}</td>
                                </tr>
                                @endif
                                @if(!empty($payment->payment_reference))
                                <tr style="background-color:#f9fafb;">
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;border-bottom:1px solid #e0e0e0;">Payment Reference</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;font-weight:bold;">{{ $payment->payment_reference }}</td>
                                </tr>
                                @endif
                                @if(!empty($invoiceRef))
                                <tr style="background-color:#ffffff;">
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;border-bottom:1px solid #e0e0e0;">Invoice Reference</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;font-weight:bold;">{{ $invoiceRef }}</td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    @if($payment->paymentItems && $payment->paymentItems->count() > 0)
                    <tr>
                        <td style="padding:0 {{ ($isPdf ?? false) ? '25px 15px 25px' : '40px 25px 40px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e0e0e0;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;overflow:hidden;' }}">
                                <tr>
                                    <th style="background-color:#166534;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;width:30px;">#</th>
                                    <th style="background-color:#166534;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Product Name</th>
                                    <th style="background-color:#166534;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:center;text-transform:uppercase;width:{{ ($isPdf ?? false) ? '50px' : '70px' }};">Qty</th>
                                    <th style="background-color:#166534;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:right;text-transform:uppercase;width:{{ ($isPdf ?? false) ? '80px' : '100px' }};">Unit Price</th>
                                    <th style="background-color:#166534;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:right;text-transform:uppercase;width:{{ ($isPdf ?? false) ? '100px' : '120px' }};">Amount</th>
                                </tr>
                                @foreach($payment->paymentItems as $index => $item)
                                @php
                                    $bgColor = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
                                @endphp
                                <tr style="background-color:{{ $bgColor }};">
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $index + 1 }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;font-weight:bold;">{{ $item->product_name }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:center;">{{ number_format($item->quantity, 2) }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:right;">{{ number_format($item->unit_price, 3) }} {{ $item->currency }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:right;font-weight:bold;">{{ number_format($item->extended_amount, 3) }} {{ $item->currency }}</td>
                                </tr>
                                @endforeach
                                <tr style="background-color:#f9fafb;">
                                    <td colspan="4" style="padding:{{ ($isPdf ?? false) ? '10px' : '14px 15px' }};font-size:{{ ($isPdf ?? false) ? '11px' : '14px' }};font-weight:bold;color:#333;text-align:right;border-top:2px solid #166534;">Items Total:</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '10px' : '14px 15px' }};font-size:{{ ($isPdf ?? false) ? '11px' : '14px' }};font-weight:bold;color:#166534;text-align:right;border-top:2px solid #166534;white-space:nowrap;">{{ number_format($payment->paymentItems->sum('extended_amount'), 3) }} {{ $payment->currency }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <tr>
                        <td style="padding:0 {{ ($isPdf ?? false) ? '25px 20px 25px' : '40px 30px 40px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="50%" valign="middle">
                                        <span style="display:inline-block;padding:{{ ($isPdf ?? false) ? '6px 16px' : '8px 24px' }};background-color:#dcfce7;color:#166534;font-size:{{ ($isPdf ?? false) ? '12px' : '14px' }};font-weight:bold;border-radius:20px;text-transform:uppercase;letter-spacing:1px;">
                                            PAID
                                        </span>
                                    </td>
                                    <td width="50%" valign="top">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-left:auto;min-width:{{ ($isPdf ?? false) ? '180px' : '220px' }};background-color:#f9fafb;{{ ($isPdf ?? false) ? '' : 'border-radius:8px;' }}padding:{{ ($isPdf ?? false) ? '12px' : '16px' }};">
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '4px 15px 4px 0' : '6px 20px 6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Amount:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '4px 0' : '6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;text-align:right;font-weight:bold;">{{ number_format($payment->amount, 3) }} {{ $payment->currency }}</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="padding:{{ ($isPdf ?? false) ? '6px' : '10px' }} 0 0 0;border-top:2px solid #166534;"></td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '6px 15px 6px 0' : '8px 20px 8px 0' }};font-size:{{ ($isPdf ?? false) ? '12px' : '16px' }};font-weight:bold;color:#166534;text-align:right;">Total Paid:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '6px 0' : '8px 0' }};font-size:{{ ($isPdf ?? false) ? '14px' : '18px' }};font-weight:bold;color:#166534;text-align:right;">{{ number_format($payment->amount, 3) }} {{ $payment->currency }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '15px 25px' : '25px 40px' }};background-color:#f9fafb;border-top:1px solid #e0e0e0;">
                            <p style="margin:0 0 {{ ($isPdf ?? false) ? '6px' : '10px' }} 0;font-size:{{ ($isPdf ?? false) ? '10px' : '12px' }};color:#666;text-align:center;">
                                If you have any questions about this payment, please contact:
                            </p>
                            <p style="margin:0 0 {{ ($isPdf ?? false) ? '4px' : '8px' }} 0;font-size:{{ ($isPdf ?? false) ? '11px' : '13px' }};color:#333;text-align:center;font-weight:bold;">
                                {{ $payment->agent->name ?? 'Agent' }}
                            </p>
                            <p style="margin:0 0 {{ ($isPdf ?? false) ? '4px' : '8px' }} 0;font-size:{{ ($isPdf ?? false) ? '10px' : '12px' }};text-align:center;">
                                <a href="mailto:{{ $payment->agent->email }}" style="color:#166534;text-decoration:none;">{{ $payment->agent->email ?? '' }}</a>
                                @if($payment->agent->phone_number)
                                    <span style="color:#999;"> | </span>
                                    <span style="color:#666;">{{ $payment->agent->phone_number }}</span>
                                @endif
                            </p>
                            <p style="margin:{{ ($isPdf ?? false) ? '12px' : '16px' }} 0 0 0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};color:#999;text-align:center;">
                                This is an automated email from {{ $payment->agent->branch->company->name ?? 'Company' }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>