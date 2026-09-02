<!DOCTYPE html>
{{--
    P2.5.I prod-drift port (verbatim from /home/citycomm/tour.citycommerce.group
    resources/views/notifications/pdf/uninvoiced-payment-links.blade.php, 2026-08-31).
    Rendered both as a WhatsApp-attached PDF (App\Console\Commands\SendAgentUninvoicedPaymentLinkReminders,
    isPdf=true) and as the HTML body of App\Mail\UninvoicedPaymentLinkReminderMail (isPdf=false).
--}}
@php
    $locale = $locale ?? 'en';
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
    $oppositeAlign = $rtl ? 'left' : 'right';
@endphp
<html lang="{{ $locale }}" @if($rtl) dir="rtl" @endif>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ trans('payment_link_reminder.pdf.title', [], $locale) }}</title>
    @if($isPdf ?? false)
    <style>
        @page { margin: 25px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; }
    </style>
    @endif
</head>
<body style="margin:0;padding:0;font-family:{{ ($isPdf ?? false) ? 'DejaVu Sans,' : '' }}Arial,Helvetica,sans-serif;background-color:{{ ($isPdf ?? false) ? '#ffffff' : '#f5f5f5' }};">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ ($isPdf ?? false) ? '#ffffff' : '#f5f5f5' }};">
    <tr>
        <td align="center" style="padding:{{ ($isPdf ?? false) ? '0' : '30px 20px' }};">
            <table role="presentation" width="{{ ($isPdf ?? false) ? '100%' : '800' }}" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.1);' }}">

                {{-- Header --}}
                <tr>
                    <td style="padding:{{ ($isPdf ?? false) ? '20px 25px' : '30px 40px' }};border-bottom:3px solid #004c9e;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td width="50%" valign="top" align="{{ $align }}">
                                    @if($company && $company->logo)
                                        @if($isPdf ?? false)
                                            <img src="{{ public_path('storage/' . $company->logo) }}" alt="{{ $company->name ?? 'Company' }}" style="max-height:50px;max-width:150px;margin-bottom:10px;">
                                        @else
                                            <img src="{{ asset('storage/' . $company->logo) }}" alt="{{ $company->name ?? 'Company' }}" style="max-height:60px;max-width:180px;margin-bottom:15px;">
                                        @endif
                                    @endif
                                    <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '16px' : '20px' }};font-weight:bold;color:#004c9e;">{{ $company->name ?? 'City Travelers' }}</p>
                                    <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->address ?? '' }}</p>
                                </td>
                                <td width="50%" valign="top" align="{{ $oppositeAlign }}">
                                    <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '20px' : '28px' }};font-weight:bold;color:#004c9e;letter-spacing:1px;">{{ strtoupper(trans('payment_link_reminder.pdf.title', [], $locale)) }}</p>
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:{{ ($isPdf ?? false) ? '10px' : '15px' }};{{ $rtl ? 'margin-right:auto;' : 'margin-left:auto;' }}">
                                        <tr>
                                            <td style="padding:3px 10px 3px 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:{{ $oppositeAlign }};">{{ trans('payment_link_reminder.pdf.window', [], $locale) }}:</td>
                                            <td style="padding:3px 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ $windowLabel ?? '' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:3px 10px 3px 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:{{ $oppositeAlign }};">{{ trans('payment_link_reminder.pdf.agent', [], $locale) }}:</td>
                                            <td style="padding:3px 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#333;">{{ $agent->name ?? '' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:3px 10px 3px 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:{{ $oppositeAlign }};">{{ trans('payment_link_reminder.pdf.count', [], $locale) }}:</td>
                                            <td style="padding:3px 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#333;">{{ count($payments) }}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Banner --}}
                <tr>
                    <td style="padding:{{ ($isPdf ?? false) ? '15px 25px 5px' : '25px 40px 10px' }};">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#eff6ff;border-{{ $rtl ? 'right' : 'left' }}:4px solid #3b82f6;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;' }}">
                            <tr>
                                <td style="padding:{{ ($isPdf ?? false) ? '10px 15px' : '15px 20px' }};text-align:{{ $align }};">
                                    <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#1e40af;">{{ trans('payment_link_reminder.pdf.title', [], $locale) }}</p>
                                    <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#1e40af;">{{ trans('payment_link_reminder.email.intro', ['count' => count($payments)], $locale) }}</p>
                                    <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#1e40af;">{{ trans('payment_link_reminder.pdf.note', [], $locale) }}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Table --}}
                <tr>
                    <td style="padding:{{ ($isPdf ?? false) ? '10px 25px' : '15px 40px' }};">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e0e0e0;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;overflow:hidden;' }}">
                            <tr>
                                <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:{{ $align }};text-transform:uppercase;width:30px;">#</th>
                                <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:{{ $align }};text-transform:uppercase;">{{ trans('payment_link_reminder.pdf.voucher', [], $locale) }}</th>
                                <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:{{ $align }};text-transform:uppercase;">{{ trans('payment_link_reminder.pdf.client', [], $locale) }}</th>
                                <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:{{ $oppositeAlign }};text-transform:uppercase;">{{ trans('payment_link_reminder.pdf.amount', [], $locale) }}</th>
                                <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:{{ $align }};text-transform:uppercase;">{{ trans('payment_link_reminder.pdf.currency', [], $locale) }}</th>
                                <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:{{ $align }};text-transform:uppercase;">{{ trans('payment_link_reminder.pdf.gateway', [], $locale) }}</th>
                                <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:{{ $align }};text-transform:uppercase;">{{ trans('payment_link_reminder.pdf.paid_at', [], $locale) }}</th>
                                <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:{{ $align }};text-transform:uppercase;">{{ trans('payment_link_reminder.pdf.reference', [], $locale) }}</th>
                            </tr>
                            @foreach($payments as $index => $payment)
                            @php $bgColor = $index % 2 === 0 ? '#ffffff' : '#f9fafb'; @endphp
                            <tr style="background-color:{{ $bgColor }};">
                                <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:{{ $align }};">{{ $index + 1 }}</td>
                                <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:{{ $align }};font-weight:bold;">{{ $payment->voucher_number ?? 'N/A' }}</td>
                                <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:{{ $align }};">{{ $payment->client->full_name ?? '—' }}</td>
                                <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:{{ $oppositeAlign }};font-weight:bold;">{{ number_format($payment->amount ?? 0, 3) }}</td>
                                <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:{{ $align }};">{{ $payment->currency ?? '' }}</td>
                                <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:{{ $align }};">{{ ucfirst($payment->payment_gateway ?? '—') }}</td>
                                <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:{{ $align }};">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : 'N/A' }}</td>
                                <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:{{ $align }};">{{ $payment->payment_reference ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:{{ ($isPdf ?? false) ? '10px 25px' : '20px 40px' }};border-top:1px solid #e0e0e0;text-align:center;">
                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};color:#999;">{{ trans('payment_link_reminder.email.footer', ['company' => $company->name ?? 'City Travelers'], $locale) }}</p>
                        <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};color:#bbb;">&copy; {{ date('Y') }} {{ $company->name ?? 'City Travelers' }}.</p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
