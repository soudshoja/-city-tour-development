<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'AutoBill Notification' }}</title>
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

@php
    $status = $status ?? 'success';
    $isFailed = $status === 'failed';
    $isWarning = $status === 'warning';
@endphp

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
                                        @if(isset($company) && $company->logo)
                                            @if($isPdf ?? false)
                                                <img src="{{ public_path('storage/' . $company->logo) }}" alt="{{ $company->name ?? 'Company' }}" style="max-height:50px;max-width:150px;margin-bottom:10px;">
                                            @else
                                                <img src="{{ asset('storage/' . $company->logo) }}" alt="{{ $company->name ?? 'Company' }}" style="max-height:60px;max-width:180px;margin-bottom:15px;">
                                            @endif
                                        @endif
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '16px' : '20px' }};font-weight:bold;color:#004c9e;">{{ $company->name ?? config('app.name', 'City Tour') }}</p>
                                        <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->address ?? '' }}</p>
                                        @if($company->phone ?? null)
                                            <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->phone }}</p>
                                        @endif
                                        @if($company->email ?? null)
                                            <p style="margin:3px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">{{ $company->email }}</p>
                                        @endif
                                    </td>
                                    <td width="50%" valign="top" align="right">
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '20px' : '28px' }};font-weight:bold;color:#004c9e;letter-spacing:1px;">NOTIFICATION</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:{{ ($isPdf ?? false) ? '10px' : '15px' }};margin-left:auto;">
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Date:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ now()->format('d/m/Y H:i') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Client:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#333;">{{ $clientName ?? 'N/A' }}</td>
                                            </tr>
                                            @if(!$isFailed && !$isWarning)
                                                <tr>
                                                    <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Invoice #:</td>
                                                    <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#333;">{{ $invoiceNumber ?? 'N/A' }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Amount:</td>
                                                    <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#333;">{{ $amount ?? 'N/A' }} {{ $currency ?? 'KWD' }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '15px 25px 5px' : '25px 40px 10px' }};">
                            @if($isWarning)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fefce8;border-left:4px solid #eab308;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;' }}">
                                    <tr>
                                        <td style="padding:{{ ($isPdf ?? false) ? '10px 15px' : '15px 20px' }};">
                                            <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#854d0e;">Tasks Need Attention</p>
                                            <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#854d0e;">The following tasks for {{ $clientName ?? 'N/A' }} could not be auto-invoiced because they are missing required information. Please fix them before the next AutoBill run on <strong>{{ $nextRunAt ?? 'N/A' }}</strong>.</p>
                                        </td>
                                    </tr>
                                </table>
                            @elseif($isFailed)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fef2f2;border-left:4px solid #ef4444;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;' }}">
                                    <tr>
                                        <td style="padding:{{ ($isPdf ?? false) ? '10px 15px' : '15px 20px' }};">
                                            <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#991b1b;">AutoBill Failed</p>
                                            <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#991b1b;">An error occurred during the AutoBilling process for {{ $clientName ?? 'N/A' }}. Please review the details below.</p>
                                        </td>
                                    </tr>
                                </table>
                            @else
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#eff6ff;border-left:4px solid #3b82f6;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;' }}">
                                    <tr>
                                        <td style="padding:{{ ($isPdf ?? false) ? '10px 15px' : '15px 20px' }};">
                                            <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#1e40af;">AutoBill Invoice Generated</p>
                                            <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#1e40af;">An automatic invoice has been generated via the AutoBilling system for {{ $clientName ?? 'N/A' }}.</p>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    @if($isWarning && isset($ineligibleTasks) && count($ineligibleTasks) > 0)
                        <tr>
                            <td style="padding:{{ ($isPdf ?? false) ? '10px 25px 3px' : '15px 40px 5px' }};">
                                <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '11px' : '14px' }};font-weight:bold;color:#854d0e;">Ineligible Tasks ({{ count($ineligibleTasks) }})</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:{{ ($isPdf ?? false) ? '3px 25px 10px' : '5px 40px 15px' }};">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e0e0e0;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;overflow:hidden;' }}">
                                    <tr>
                                        <th style="background-color:#854d0e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;width:60px;">Task ID</th>
                                        <th style="background-color:#854d0e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Reference</th>
                                        <th style="background-color:#854d0e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Issues</th>
                                    </tr>
                                    @foreach($ineligibleTasks as $index => $bad)
                                    @php
                                        $bgColor = $index % 2 === 0 ? '#ffffff' : '#fefce8';
                                    @endphp
                                    <tr style="background-color:{{ $bgColor }};">
                                        <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;font-weight:bold;">{{ $bad['task_id'] }}</td>
                                        <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $bad['reference'] ?? 'N/A' }}</td>
                                        <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#dc2626;border-bottom:1px solid #e0e0e0;">{{ $bad['issues'] }}</td>
                                    </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:{{ ($isPdf ?? false) ? '5px 25px 10px' : '10px 40px 15px' }};">
                                <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">Please assign the missing information so these tasks can be included in the next AutoBill run on <strong>{{ $nextRunAt ?? 'N/A' }}</strong>.</p>
                            </td>
                        </tr>
                    @elseif($isFailed)
                        <tr>
                            <td style="padding:{{ ($isPdf ?? false) ? '10px 25px 5px' : '15px 40px 10px' }};">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e0e0e0;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;overflow:hidden;' }}">
                                    <tr>
                                        <th colspan="2" style="background-color:#dc2626;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Error Details</th>
                                    </tr>
                                    <tr style="background-color:#ffffff;">
                                        <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;border-bottom:1px solid #e0e0e0;width:140px;font-weight:bold;">Client</td>
                                        <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $clientName ?? 'N/A' }}</td>
                                    </tr>
                                    <tr style="background-color:#f9fafb;">
                                        <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;border-bottom:1px solid #e0e0e0;font-weight:bold;">Error Message</td>
                                        <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#dc2626;border-bottom:1px solid #e0e0e0;">{{ $errorMessage ?? 'Unknown error occurred.' }}</td>
                                    </tr>
                                    <tr style="background-color:#ffffff;">
                                        <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;font-weight:bold;">Occurred At</td>
                                        <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ now()->format('d/m/Y H:i') }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:{{ ($isPdf ?? false) ? '5px 25px 10px' : '10px 40px 15px' }};">
                                <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;">Please review the AutoBilling logs for more details.</p>
                            </td>
                        </tr>
                    @else
                        @if(isset($tasks) && count($tasks) > 0)
                            <tr>
                                <td style="padding:{{ ($isPdf ?? false) ? '10px 25px 3px' : '15px 40px 5px' }};">
                                    <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '11px' : '14px' }};font-weight:bold;color:#004c9e;">Tasks Included ({{ count($tasks) }})</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:{{ ($isPdf ?? false) ? '3px 25px 10px' : '5px 40px 15px' }};">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e0e0e0;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;overflow:hidden;' }}">
                                        <tr>
                                            <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;width:30px;">#</th>
                                            <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Reference</th>
                                            <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Agent</th>
                                            <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Passenger</th>
                                            <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:right;text-transform:uppercase;">Total (KWD)</th>
                                        </tr>
                                        @foreach($tasks as $index => $task)
                                        @php
                                            $bgColor = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
                                        @endphp
                                        <tr style="background-color:{{ $bgColor }};">
                                            <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $index + 1 }}</td>
                                            <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;font-weight:bold;">{{ $task->reference ?? 'N/A' }}</td>
                                            <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $task->agent->name ?? 'N/A' }}</td>
                                            <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $task->passenger_name ?? 'N/A' }}</td>
                                            <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;text-align:right;font-weight:bold;">{{ number_format($task->invoice_price ?? $task->total ?? 0, 3) }}</td>
                                        </tr>
                                        @endforeach
                                        <tr style="background-color:#f0f4ff;">
                                            <td colspan="4" style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#004c9e;text-align:right;">Total:</td>
                                            <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#004c9e;text-align:right;">{{ $amount ?? number_format(collect($tasks)->sum('total'), 3) }} KWD</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        @endif

                        @if(isset($invoiceLink) && !($isPdf ?? false))
                            <tr>
                                <td align="center" style="padding:10px 40px 25px;">
                                    <a href="{{ $invoiceLink }}" target="_blank" style="display:inline-block;background-color:#004c9e;color:#ffffff;padding:14px 40px;font-size:14px;font-weight:bold;text-decoration:none;border-radius:4px;text-transform:uppercase;letter-spacing:1px;">View Invoice</a>
                                </td>
                            </tr>
                        @endif
                    @endif

                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '10px 25px' : '20px 40px' }};border-top:1px solid #e0e0e0;text-align:center;">
                            <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};color:#999;">This is an automated notification from {{ $company->name ?? config('app.name', 'City Tour') }}.</p>
                            <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};color:#bbb;">&copy; {{ date('Y') }} {{ $company->name ?? config('app.name', 'City Tour') }}. All rights reserved.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
