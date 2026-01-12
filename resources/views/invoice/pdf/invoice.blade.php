<!DOCTYPE html>
<html lang="{{ $language ?? 'en' }}" dir="{{ ($language ?? 'en') === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
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
                                        <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->address ?? 'Kuwait City' }}</p>
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->phone ?? '' }}</p>
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->email ?? '' }}</p>
                                    </td>
                                    <td width="50%" valign="top" align="right">
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '28px' : '36px' }};font-weight:bold;color:#004c9e;letter-spacing:2px;">INVOICE</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:{{ ($isPdf ?? false) ? '10px' : '15px' }};margin-left:auto;">
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Invoice #:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#333;">{{ $invoice->invoice_number }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Date:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Due Date:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') : 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Status:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};">
                                                    @if($invoice->status === 'paid')
                                                        <span style="display:inline-block;padding:3px {{ ($isPdf ?? false) ? '8px' : '12px' }};background-color:#d4edda;color:#155724;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;border-radius:12px;text-transform:uppercase;">PAID</span>
                                                    @elseif($invoice->status === 'partial')
                                                        <span style="display:inline-block;padding:3px {{ ($isPdf ?? false) ? '8px' : '12px' }};background-color:#fff3cd;color:#856404;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;border-radius:12px;text-transform:uppercase;">PARTIAL</span>
                                                    @else
                                                        <span style="display:inline-block;padding:3px {{ ($isPdf ?? false) ? '8px' : '12px' }};background-color:#f8d7da;color:#721c24;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;border-radius:12px;text-transform:uppercase;">UNPAID</span>
                                                    @endif
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
                                        <p style="margin:0 0 {{ ($isPdf ?? false) ? '6px' : '10px' }} 0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;color:#004c9e;text-transform:uppercase;letter-spacing:1px;">Bill To</p>
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#333;text-transform:uppercase;">{{ $invoice->client->full_name ?? 'N/A' }}</p>
                                        <p style="margin:{{ ($isPdf ?? false) ? '3px' : '5px' }} 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $invoice->client->email ?? 'N/A' }}</p>
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ ($invoice->client->country_code ?? '+965') }} {{ $invoice->client->phone ?? 'N/A' }}</p>
                                    </td>
                                    <td width="50%" valign="top" style="text-align:right;">
                                        <p style="margin:0 0 {{ ($isPdf ?? false) ? '6px' : '10px' }} 0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;color:#004c9e;text-transform:uppercase;letter-spacing:1px;">Agent</p>
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#333;text-transform:uppercase;">{{ $invoice->agent->name ?? 'N/A' }}</p>
                                        <p style="margin:{{ ($isPdf ?? false) ? '3px' : '5px' }} 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $invoice->agent->email ?? 'N/A' }}</p>
                                        <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $invoice->agent->phone_number ?? 'N/A' }}</p>
                                        @if($invoice->agent->branch ?? null)
                                            <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">Branch: {{ $invoice->agent->branch->name ?? 'N/A' }}</p>
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
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;width:30px;">#</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Description</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;width:{{ ($isPdf ?? false) ? '50px' : '70px' }};">Type</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;width:{{ ($isPdf ?? false) ? '70px' : '90px' }};">Supplier</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:right;text-transform:uppercase;width:{{ ($isPdf ?? false) ? '80px' : '100px' }};">Amount</th>
                                </tr>
                                @forelse($invoiceDetails ?? [] as $index => $detail)
                                @php
                                    $task = $detail->task;
                                    $bgColor = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
                                @endphp
                                <tr style="background-color:{{ $bgColor }};">
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $index + 1 }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">
                                        <strong>{{ $detail->task_description ?? $task->reference ?? 'N/A' }}</strong>
                                        @if($task && $task->passenger_name)
                                            <br><span style="font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};color:#666;">Passenger: {{ $task->passenger_name }}</span>
                                        @endif
                                        @if($task && $task->ticket_number)
                                            <br><span style="font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};color:#666;">Ticket: {{ $task->ticket_number }}</span>
                                        @endif
                                        @if($task && $task->type === 'flight' && $task->flightDetails)
                                            <br><span style="font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};color:#004c9e;">
                                                {{ ($isPdf ?? false) ? '' : '✈ ' }}{{ $task->flightDetails->airport_from ?? '' }} {{ ($isPdf ?? false) ? '-' : '→' }} {{ $task->flightDetails->airport_to ?? '' }}
                                                @if($task->flightDetails->departure_time)
                                                | {{ $task->flightDetails->departure_time->format('d M Y H:i') }}
                                                @endif
                                            </span>
                                        @endif
                                        @if($task && $task->type === 'hotel' && $task->hotelDetails)
                                        <br><span style="font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};color:#004c9e;">
                                            {{ ($isPdf ?? false) ? '' : '🏨 ' }}{{ $task->hotelDetails->hotel->name ?? 'Hotel' }}
                                            @if($task->hotelDetails->check_in && $task->hotelDetails->check_out)
                                                | {{ \Carbon\Carbon::parse($task->hotelDetails->check_in)->format('d M') }} - {{ \Carbon\Carbon::parse($task->hotelDetails->check_out)->format('d M Y') }}
                                                ({{ $task->hotelDetails->nights ?? '' }} nights)
                                            @endif
                                        </span>
                                        @endif
                                        @if($task && $task->type === 'visa' && $task->visaDetails)
                                            <br><span style="font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};color:#004c9e;">
                                                {{ ($isPdf ?? false) ? '' : '📋 ' }}{{ $task->visaDetails->visa_type ?? 'Visa' }} - {{ $task->visaDetails->issuing_country ?? '' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ ucfirst($task->type ?? 'N/A') }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $task->supplier->name ?? 'N/A' }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:right;font-weight:bold;">{{ number_format($detail->task_price, 2) }} {{ $invoice->currency ?? 'KWD' }}</td>
                                </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="padding:20px;text-align:center;color:#666;">No items found</td>
                                    </tr>
                                @endforelse
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 {{ ($isPdf ?? false) ? '25px 20px 25px' : '40px 30px 40px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="50%" valign="top">
                                        @if($invoice->invoicePartials && $invoice->invoicePartials->count() > 0)
                                            <p style="margin:0 0 {{ ($isPdf ?? false) ? '6px' : '10px' }} 0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;color:#004c9e;text-transform:uppercase;letter-spacing:1px;">Payment Information</p>

                                            @php
                                                $clientIds = $invoice->invoicePartials->pluck('client_id')->unique();
                                                $isSplitPayment = $clientIds->count() > 1;
                                            @endphp

                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;">
                                                <tr>
                                                    <td style="padding:3px {{ ($isPdf ?? false) ? '10px' : '15px' }} 3px 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">Payment Type:</td>
                                                    <td style="padding:3px 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">
                                                        @if($isSplitPayment)
                                                            Split Payment
                                                        @elseif($invoice->invoicePartials->count() > 1)
                                                            Partial Payment
                                                        @else
                                                            {{ ucfirst(($invoice->payment_type ?? 'Full') . ' Payment') }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            </table>
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin-top:{{ ($isPdf ?? false) ? '8px' : '12px' }};border:1px solid #e0e0e0;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;' }}">
                                                <tr style="background-color:#f9fafb;">
                                                    @if($isSplitPayment)
                                                        <th style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};font-weight:bold;color:#666;text-align:left;border-bottom:1px solid #e0e0e0;">Payer</th>
                                                    @endif
                                                    <th style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};font-weight:bold;color:#666;text-align:left;border-bottom:1px solid #e0e0e0;">Gateway</th>
                                                    <th style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};font-weight:bold;color:#666;text-align:left;border-bottom:1px solid #e0e0e0;">Method</th>
                                                    <th style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};font-weight:bold;color:#666;text-align:right;border-bottom:1px solid #e0e0e0;">Amount</th>
                                                    <th style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};font-weight:bold;color:#666;text-align:center;border-bottom:1px solid #e0e0e0;">Status</th>
                                                </tr>
                                                @foreach($invoice->invoicePartials as $partial)
                                                    <tr>
                                                        @if($isSplitPayment)
                                                            <td style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};color:#333;border-bottom:1px solid #e0e0e0;">
                                                                {{ $partial->client->full_name ?? 'N/A' }}
                                                            </td>
                                                        @endif
                                                        <td style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};color:#333;border-bottom:1px solid #e0e0e0;">
                                                            {{ $partial->payment_gateway ?? '-' }}
                                                        </td>
                                                        <td style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};color:#333;border-bottom:1px solid #e0e0e0;">
                                                            {{ $partial->paymentMethod->english_name ?? '-' }}
                                                        </td>
                                                        <td style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:right;font-weight:bold;">
                                                            {{ number_format($partial->amount, 2) }} {{ $invoice->currency ?? 'KWD' }}
                                                        </td>
                                                        <td style="padding:{{ ($isPdf ?? false) ? '6px 8px' : '8px 10px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};border-bottom:1px solid #e0e0e0;text-align:center;">
                                                            @if($partial->status === 'paid')
                                                                <span style="display:inline-block;padding:2px {{ ($isPdf ?? false) ? '6px' : '8px' }};background-color:#d4edda;color:#155724;font-size:{{ ($isPdf ?? false) ? '8px' : '9px' }};font-weight:bold;border-radius:8px;text-transform:uppercase;">Paid</span>
                                                            @else
                                                                <span style="display:inline-block;padding:2px {{ ($isPdf ?? false) ? '6px' : '8px' }};background-color:#f8d7da;color:#721c24;font-size:{{ ($isPdf ?? false) ? '8px' : '9px' }};font-weight:bold;border-radius:8px;text-transform:uppercase;">Unpaid</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        @else
                                            @if($invoice->payment_type)
                                                <p style="margin:0 0 {{ ($isPdf ?? false) ? '6px' : '10px' }} 0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;color:#004c9e;text-transform:uppercase;letter-spacing:1px;">Payment Type</p>
                                                <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ ucfirst($invoice->payment_type) }}</p>
                                            @endif
                                        @endif
                                    </td>
                                    <td width="50%" valign="top">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-left:auto;min-width:{{ ($isPdf ?? false) ? '200px' : '250px' }};">
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '4px 15px 4px 0' : '6px 20px 6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Subtotal:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '4px 0' : '6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;text-align:right;">{{ number_format($invoice->sub_amount ?? 0, 2) }} {{ $invoice->currency ?? 'KWD' }}</td>
                                            </tr>
                                            @if(($invoice->tax ?? 0) > 0)
                                                <tr>
                                                    <td style="padding:{{ ($isPdf ?? false) ? '4px 15px 4px 0' : '6px 20px 6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Tax:</td>
                                                    <td style="padding:{{ ($isPdf ?? false) ? '4px 0' : '6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;text-align:right;">{{ number_format($invoice->tax, 2) }} {{ $invoice->currency ?? 'KWD' }}</td>
                                                </tr>
                                            @endif
                                            @if(($invoice->invoice_charge ?? 0) > 0)
                                                <tr>
                                                    <td style="padding:{{ ($isPdf ?? false) ? '4px 15px 4px 0' : '6px 20px 6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Service Charge:</td>
                                                    <td style="padding:{{ ($isPdf ?? false) ? '4px 0' : '6px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;text-align:right;">{{ number_format($invoice->invoice_charge, 2) }} {{ $invoice->currency ?? 'KWD' }}</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <td colspan="2" style="padding:{{ ($isPdf ?? false) ? '6px' : '10px' }} 0 0 0;border-top:2px solid #004c9e;"></td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '6px 15px 6px 0' : '8px 20px 8px 0' }};font-size:{{ ($isPdf ?? false) ? '12px' : '16px' }};font-weight:bold;color:#004c9e;text-align:right;">Total Invoice:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '6px 0' : '8px 0' }};font-size:{{ ($isPdf ?? false) ? '14px' : '18px' }};font-weight:bold;color:#004c9e;text-align:right;">{{ number_format($invoice->amount ?? 0, 2) }} {{ $invoice->currency ?? 'KWD' }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @if($invoice->status !== 'paid' && !($isPdf ?? false) && $invoice->payment_type)
                        <tr>
                            <td align="center" style="padding:0 40px 30px 40px;">
                                <a href="{{ route('invoice.show', ['companyId' => $company->id ?? 1, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                    style="display:inline-block;background-color:#004c9e;color:#ffffff;padding:14px 40px;font-size:14px;font-weight:bold;text-decoration:none;border-radius:4px;text-transform:uppercase;letter-spacing:1px;">
                                    View & Pay Invoice
                                </a>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '15px 25px' : '25px 40px' }};background-color:#f9fafb;border-top:1px solid #e0e0e0;">
                            <p style="margin:0 0 {{ ($isPdf ?? false) ? '6px' : '10px' }} 0;font-size:{{ ($isPdf ?? false) ? '10px' : '12px' }};color:#666;text-align:center;">
                                Thank you for your business! If you have any questions about this invoice, please contact us.
                            </p>
                            <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '10px' : '12px' }};color:#999;text-align:center;">
                                {{ $company->name ?? 'City Travelers' }} | {{ $company->email ?? '' }} | {{ $company->phone ?? '' }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>