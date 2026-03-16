<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Invoice Delivery</title>
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
                <table role="presentation" width="{{ ($isPdf ?? false) ? '100%' : '800' }}" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.1);' }}">

                    {{-- Header --}}
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '20px 25px' : '30px 40px' }};border-bottom:3px solid #004c9e;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="50%" valign="top">
                                        @if($company && $company->logo)
                                            @if($isPdf ?? false)
                                                <img src="{{ storage_path('app/public/' . $company->logo) }}" alt="{{ $company->name ?? 'Company' }}" style="max-height:50px;max-width:150px;margin-bottom:10px;">
                                            @else
                                                <img src="{{ asset('storage/' . $company->logo) }}" alt="{{ $company->name ?? 'Company' }}" style="max-height:60px;max-width:180px;margin-bottom:15px;">
                                            @endif
                                        @endif
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '16px' : '20px' }};font-weight:bold;color:#004c9e;">{{ $company->name ?? 'City Travelers' }}</p>
                                        <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->address ?? '' }}</p>
                                        @if($company->phone ?? null)
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->phone }}</p>
                                        @endif
                                        @if($company->email ?? null)
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->email }}</p>
                                        @endif
                                    </td>
                                    <td width="50%" valign="top" align="right">
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '20px' : '28px' }};font-weight:bold;color:#004c9e;letter-spacing:1px;">BULK INVOICE</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:{{ ($isPdf ?? false) ? '10px' : '15px' }};margin-left:auto;">
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Date:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ now()->format('d/m/Y') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Total Invoices:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#333;">{{ $invoices->count() }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">File:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ $bulkInvoice->original_filename ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Summary Banner --}}
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '15px 25px 5px' : '25px 40px 10px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f0f7ff;border-left:4px solid #004c9e;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;' }}">
                                <tr>
                                    <td style="padding:{{ ($isPdf ?? false) ? '10px 15px' : '15px 20px' }};">
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#004c9e;">Invoice Delivery Summary</p>
                                        <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">
                                            <strong>{{ $invoices->count() }}</strong> invoice{{ $invoices->count() !== 1 ? 's have' : ' has' }} been created from the bulk invoice.
                                            All invoice PDFs are attached to this email.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Invoice Table --}}
                    @if($invoices->isNotEmpty())
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '10px 25px 5px' : '15px 40px 10px' }};">
                            <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '11px' : '14px' }};font-weight:bold;color:#004c9e;">Invoices Created ({{ $invoices->count() }})</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '3px 25px 10px' : '5px 40px 15px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e0e0e0;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;overflow:hidden;' }}">
                                <tr>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;width:30px;">#</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Invoice Number</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Client</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Date</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Status</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:right;text-transform:uppercase;">Amount</th>
                                </tr>
                                @foreach($invoices as $index => $invoice)
                                @php
                                    $bgColor = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
                                @endphp
                                <tr style="background-color:{{ $bgColor }};">
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $index + 1 }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#004c9e;border-bottom:1px solid #e0e0e0;font-weight:bold;">{{ $invoice->invoice_number }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $invoice->client->full_name ?? 'N/A' }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};border-bottom:1px solid #e0e0e0;">
                                        @if($invoice->status === 'paid')
                                            <span style="display:inline-block;padding:2px {{ ($isPdf ?? false) ? '6px' : '8px' }};background-color:#d4edda;color:#155724;font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};font-weight:bold;border-radius:8px;text-transform:uppercase;">Paid</span>
                                        @elseif($invoice->status === 'partial')
                                            <span style="display:inline-block;padding:2px {{ ($isPdf ?? false) ? '6px' : '8px' }};background-color:#fff3cd;color:#856404;font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};font-weight:bold;border-radius:8px;text-transform:uppercase;">Partial</span>
                                        @else
                                            <span style="display:inline-block;padding:2px {{ ($isPdf ?? false) ? '6px' : '8px' }};background-color:#f8d7da;color:#721c24;font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};font-weight:bold;border-radius:8px;text-transform:uppercase;">Unpaid</span>
                                        @endif
                                    </td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:right;font-weight:bold;">{{ number_format($invoice->amount ?? 0, 3) }} {{ $invoice->currency ?? 'KWD' }}</td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Totals --}}
                    <tr>
                        <td style="padding:0 {{ ($isPdf ?? false) ? '25px 15px 25px' : '40px 25px 40px' }};">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-left:auto;min-width:{{ ($isPdf ?? false) ? '200px' : '250px' }};">
                                <tr>
                                    <td style="padding:{{ ($isPdf ?? false) ? '4px 15px 4px 0' : '6px 20px 6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Total Invoices:</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '4px 0' : '6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;text-align:right;">{{ $invoices->count() }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding:{{ ($isPdf ?? false) ? '6px' : '10px' }} 0 0 0;border-top:2px solid #004c9e;"></td>
                                </tr>
                                <tr>
                                    <td style="padding:{{ ($isPdf ?? false) ? '6px 15px 6px 0' : '8px 20px 8px 0' }};font-size:{{ ($isPdf ?? false) ? '12px' : '16px' }};font-weight:bold;color:#004c9e;text-align:right;">Grand Total:</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '6px 0' : '8px 0' }};font-size:{{ ($isPdf ?? false) ? '14px' : '18px' }};font-weight:bold;color:#004c9e;text-align:right;">{{ number_format($invoices->sum('amount'), 3) }} {{ $invoices->first()->currency ?? 'KWD' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Note --}}
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '5px 25px 10px' : '10px 40px 20px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fff3cd;border-left:4px solid #ffc107;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;' }}">
                                <tr>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 12px' : '12px 16px' }};">
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#856404;">
                                            <strong>Note:</strong> All invoice PDFs are attached to this email for your records.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '10px 25px' : '20px 40px' }};background-color:#f9fafb;border-top:1px solid #e0e0e0;text-align:center;">
                            <p style="margin:0 0 {{ ($isPdf ?? false) ? '4px' : '8px' }} 0;font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};color:#666;">
                                Thank you for your business! If you have any questions, please contact us.
                            </p>
                            <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};color:#999;">
                                &copy; {{ date('Y') }} {{ $company->name ?? 'City Travelers' }}. All rights reserved.
                                @if($company->email ?? null) | {{ $company->email }} @endif
                                @if($company->phone ?? null) | {{ $company->phone }} @endif
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
