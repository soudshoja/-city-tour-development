<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unassigned Task Reminder</title>
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
                                                <img src="{{ public_path('storage/' . $company->logo) }}" alt="{{ $company->name ?? 'Company' }}" style="max-height:50px;max-width:150px;margin-bottom:10px;">
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
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '20px' : '28px' }};font-weight:bold;color:#004c9e;letter-spacing:1px;">REMINDER</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:{{ ($isPdf ?? false) ? '10px' : '15px' }};margin-left:auto;">
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Date:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;">{{ now()->format('d/m/Y H:i') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 10px 3px 0' : '4px 15px 4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#666;text-align:right;">Total Tasks:</td>
                                                <td style="padding:{{ ($isPdf ?? false) ? '3px 0' : '4px 0' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};font-weight:bold;color:#333;">{{ count($tasks) }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Alert Banner --}}
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '15px 25px 5px' : '25px 40px 10px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fff3cd;border-left:4px solid #ffc107;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;' }}">
                                <tr>
                                    <td style="padding:{{ ($isPdf ?? false) ? '10px 15px' : '15px 20px' }};">
                                        <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '12px' : '15px' }};font-weight:bold;color:#856404;">Unassigned Task Reminder</p>
                                        <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#856404;">The following {{ count($tasks) }} task(s) have not been assigned to any agent. Please assign an agent as soon as possible.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Window Label --}}
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '10px 25px 3px' : '15px 40px 5px' }};">
                            <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '11px' : '14px' }};font-weight:bold;color:#004c9e;">{{ $windowLabel ?? '1 - 7 days' }} ({{ count($tasks) }} task{{ count($tasks) > 1 ? 's' : '' }})</p>
                        </td>
                    </tr>

                    {{-- Task Table --}}
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '3px 25px 10px' : '5px 40px 15px' }};">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e0e0e0;{{ ($isPdf ?? false) ? '' : 'border-radius:4px;overflow:hidden;' }}">
                                <tr>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;width:30px;">#</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Reference</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Type</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Supplier</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Client</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Status</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:left;text-transform:uppercase;">Created</th>
                                    <th style="background-color:#004c9e;padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '9px' : '12px' }};font-weight:bold;color:#fff;text-align:right;text-transform:uppercase;">Days</th>
                                </tr>
                                @foreach($tasks as $index => $task)
                                @php
                                    $bgColor = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
                                    $daysUnassigned = $task->created_at ? (int) \Carbon\Carbon::parse($task->created_at)->diffInDays(now()) : null;
                                @endphp
                                <tr style="background-color:{{ $bgColor }};">
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $index + 1 }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;font-weight:bold;">{{ $task->reference ?? 'N/A' }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ ucfirst($task->type ?? 'N/A') }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $task->supplier->name ?? 'Not Set' }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $task->client->full_name ?? $task->client_name ?? 'Not Set' }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ ucfirst($task->status ?? 'N/A') }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};color:#333;border-bottom:1px solid #e0e0e0;">{{ $task->created_at ? \Carbon\Carbon::parse($task->created_at)->format('d/m/Y') : 'N/A' }}</td>
                                    <td style="padding:{{ ($isPdf ?? false) ? '8px 10px' : '12px 15px' }};font-size:{{ ($isPdf ?? false) ? '10px' : '13px' }};border-bottom:1px solid #e0e0e0;text-align:right;">
                                        @if($daysUnassigned !== null)
                                        <span style="display:inline-block;padding:2px {{ ($isPdf ?? false) ? '6px' : '10px' }};background-color:{{ $daysUnassigned > 14 ? '#f8d7da' : '#fff3cd' }};color:{{ $daysUnassigned > 14 ? '#721c24' : '#856404' }};font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};font-weight:bold;border-radius:10px;">{{ $daysUnassigned }}d</span>
                                        @else
                                        <span style="color:#999;">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:{{ ($isPdf ?? false) ? '10px 25px' : '20px 40px' }};border-top:1px solid #e0e0e0;text-align:center;">
                            <p style="margin:0;font-size:{{ ($isPdf ?? false) ? '9px' : '11px' }};color:#999;">This is an automated reminder from {{ $company->name ?? 'City Travelers' }}.</p>
                            <p style="margin:5px 0 0 0;font-size:{{ ($isPdf ?? false) ? '8px' : '10px' }};color:#bbb;">&copy; {{ date('Y') }} {{ $company->name ?? 'City Travelers' }}. All rights reserved.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
