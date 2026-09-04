{{--
    Visa Confirmation — vouchers.visa-classic (plan §5 row 3, §16 step 3).

    New design — there was no orphaned pdf/visa.blade.php to port. Thin by
    necessity: task_visa_details carries exactly 7 real fields (plan §6).
    A one-page letter-style confirmation, honestly scoped to what exists.

    Variable contract: $payload.{company,client,agent,task,visa:{visa_type,
    application_number,appointment_date,expiry_date,number_of_entries,
    stay_duration,issuing_country}|null, segment|null, voucher, terms,
    money|null, payment|null}. $isPdf (bool), $sample (bool).
--}}
@php
    $lang = $payload['voucher']['language'] ?? 'EN';
    $company = $payload['company'];
    $client = $payload['client'];
    $agent = $payload['agent'];
    $task = $payload['task'];
    $visa = $payload['visa'];
    $terms = $payload['terms'] ?? null;
    $isPdf = $isPdf ?? false;
    $sample = $sample ?? false;
    // Only rendered as its own section for more than one applicant sharing
    // this booking's reference — a lone applicant already reads naturally
    // from the Applicant block above (BLOCKER B1 owner memo).
    $roster = $visa['roster'] ?? [];

    $L = $lang === 'ARB' ? [
        'title' => 'تأكيد تأشيرة', 'badge' => 'تأكيد تأشيرة',
        'client_info' => 'بيانات مقدم الطلب', 'name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'الهاتف',
        'details' => 'تفاصيل التأشيرة',
        'visa_type' => 'نوع التأشيرة', 'app_no' => 'رقم الطلب', 'appointment' => 'تاريخ الموعد',
        'expiry' => 'تاريخ الانتهاء', 'entries' => 'عدد مرات الدخول', 'stay' => 'مدة الإقامة المسموحة',
        'issuing_country' => 'الدولة المصدرة', 'booked_by' => 'وكيل الحجز',
        'no_data' => 'لا تتوفر بيانات تأشيرة مفصلة لهذا الحجز.',
        'days' => 'يوم',
        'travellers' => 'المتقدمون',
    ] : [
        'title' => 'Visa Confirmation', 'badge' => 'Visa Confirmation',
        'client_info' => 'Applicant', 'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone',
        'details' => 'Visa Details',
        'visa_type' => 'Visa Type', 'app_no' => 'Application No.', 'appointment' => 'Appointment Date',
        'expiry' => 'Expiry Date', 'entries' => 'No. of Entries', 'stay' => 'Permitted Stay',
        'issuing_country' => 'Issuing Country', 'booked_by' => 'Booking Agent',
        'no_data' => 'No detailed visa data is available for this booking.',
        'days' => 'days',
        'travellers' => 'Applicants',
    ];

    $fmtDate = function ($v) {
        if (empty($v)) return null;
        try { return \Carbon\Carbon::parse($v)->format('d M Y'); } catch (\Throwable $e) { return $v; }
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $lang === 'ARB' ? 'ar' : 'en' }}" dir="{{ $lang === 'ARB' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $L['title'] }}: {{ $task['reference'] ?? '' }}</title>
    @include('vouchers.partials.styles', ['isPdf' => $isPdf, 'lang' => $lang])
</head>
<body>
    <div class="voucher-page">
        @include('vouchers.partials.sample_badge', ['sample' => $sample, 'lang' => $lang])
        @include('vouchers.partials.header', [
            'company' => $company, 'badge' => $L['badge'],
            'reference' => $task['reference'] ?? '—', 'isPdf' => $isPdf, 'lang' => $lang,
        ])

        <div class="vb">
            @include('vouchers.partials.status_banner', [
                'voucherStatus' => $voucherStatus ?? null, 'crossReference' => $crossReference ?? null, 'lang' => $lang,
            ])
            <div class="v-section">
                <p class="v-section-title">{{ $L['client_info'] }}</p>
                <div class="v-card">
                    <table class="v-grid" role="presentation"><tr>
                        <td><p class="v-label">{{ $L['name'] }}</p><p class="v-value">{{ $client['name'] ?? $task['passenger_name'] ?? $task['client_name'] ?? '—' }}</p></td>
                        <td><p class="v-label">{{ $L['email'] }}</p><p class="v-value">{{ $client['email'] ?? '—' }}</p></td>
                        <td><p class="v-label">{{ $L['phone'] }}</p><p class="v-value">{{ $client['phone'] ?? '—' }}</p></td>
                    </tr></table>
                </div>
            </div>

            @if($visa)
                <div class="v-section">
                    <p class="v-section-title">{{ $L['details'] }}</p>
                    <div class="v-card">
                        <table class="v-grid" role="presentation"><tr>
                            <td><p class="v-label">{{ $L['visa_type'] }}</p><p class="v-value">{{ $visa['visa_type'] ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['app_no'] }}</p><p class="v-value">{{ $visa['application_number'] ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['issuing_country'] }}</p><p class="v-value">{{ $visa['issuing_country'] ?? '—' }}</p></td>
                        </tr></table>
                        <table class="v-grid" role="presentation" style="margin-top:6px;"><tr>
                            <td><p class="v-label">{{ $L['appointment'] }}</p><p class="v-value">{{ $fmtDate($visa['appointment_date']) ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['expiry'] }}</p><p class="v-value">{{ $fmtDate($visa['expiry_date']) ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['entries'] }}</p><p class="v-value">{{ $visa['number_of_entries'] ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['stay'] }}</p><p class="v-value">{{ $visa['stay_duration'] ? $visa['stay_duration'].' '.$L['days'] : '—' }}</p></td>
                        </tr></table>
                    </div>
                </div>

                @if(count($roster) > 1)
                    <div class="v-section">
                        <p class="v-section-title">{{ $L['travellers'] }} ({{ count($roster) }})</p>
                        <table class="v-table">
                            <thead><tr><th>{{ $L['name'] }}</th><th>{{ $L['app_no'] }}</th></tr></thead>
                            <tbody>
                            @foreach($roster as $person)
                                <tr>
                                    <td>{{ $person['name'] ?? '—' }}</td>
                                    <td>{{ $person['application_number'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                <div class="v-section">
                    <div class="v-note">{{ $L['no_data'] }}</div>
                </div>
            @endif

            @if(!empty($agent['name']))
                <p style="font-size:10px;color:#64748b;margin:0 0 18px 0;">{{ $L['booked_by'] }}: {{ $agent['name'] }}</p>
            @endif

            @include('vouchers.partials.terms', ['terms' => $terms, 'lang' => $lang])
        </div>

        @include('vouchers.partials.footer', ['company' => $company, 'lang' => $lang])
    </div>
</body>
</html>
