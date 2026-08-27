{{--
    Insurance Cover Note — vouchers.insurance-classic (plan §5 row 4, §16
    step 3).

    New design. Thin by necessity: task_insurance_details carries exactly
    8 real fields (plan §6). Deliberately labelled a COVER/CONFIRMATION
    note, not the insurer's own certificate — the plan is explicit this
    is not the same document (§5 row 4), and pretending otherwise would
    be a real problem if a client ever needed to make a claim.

    Variable contract: $payload.{company,client,agent,task,
    insurance:{insurance_type,plan_type,package,destination,duration,date,
    document_reference,paid_leaves}|null, segment|null, voucher, terms,
    money|null, payment|null}. $isPdf (bool), $sample (bool).
--}}
@php
    $lang = $payload['voucher']['language'] ?? 'EN';
    $company = $payload['company'];
    $client = $payload['client'];
    $agent = $payload['agent'];
    $task = $payload['task'];
    $insurance = $payload['insurance'];
    $terms = $payload['terms'] ?? null;
    $isPdf = $isPdf ?? false;
    $sample = $sample ?? false;
    // Only rendered as its own section for more than one insured person
    // sharing this booking's reference (BLOCKER B1 owner memo).
    $roster = $insurance['roster'] ?? [];

    $L = $lang === 'ARB' ? [
        'title' => 'إشعار تغطية تأمينية', 'badge' => 'إشعار تغطية تأمينية',
        'client_info' => 'بيانات المؤمن عليه', 'name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'الهاتف',
        'details' => 'تفاصيل التغطية',
        'type' => 'نوع التأمين', 'plan' => 'نوع الخطة', 'package' => 'الباقة', 'destination' => 'الوجهة',
        'duration' => 'المدة', 'date' => 'تاريخ السريان', 'doc_ref' => 'رقم الوثيقة', 'paid_leaves' => 'أيام مغطاة',
        'booked_by' => 'وكيل الحجز',
        'no_data' => 'لا تتوفر بيانات تأمين مفصلة لهذا الحجز.',
        'disclaimer' => 'هذا إشعار تأكيد صادر عن وكالتنا وليس وثيقة التأمين الرسمية الصادرة عن شركة التأمين. يرجى الاحتفاظ بالوثيقة الرسمية عند استلامها لأي مطالبة.',
        'days' => 'يوم',
        'travellers' => 'المؤمن عليهم',
    ] : [
        'title' => 'Insurance Cover Note', 'badge' => 'Insurance Cover Note',
        'client_info' => 'Insured', 'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone',
        'details' => 'Coverage Details',
        'type' => 'Insurance Type', 'plan' => 'Plan Type', 'package' => 'Package', 'destination' => 'Destination',
        'duration' => 'Duration', 'date' => 'Effective Date', 'doc_ref' => 'Document Reference', 'paid_leaves' => 'Covered Days',
        'booked_by' => 'Booking Agent',
        'no_data' => 'No detailed insurance data is available for this booking.',
        'disclaimer' => 'This is a confirmation note issued by our agency, not the insurer\'s official policy document. Please keep the official policy for any claim.',
        'days' => 'days',
        'travellers' => 'Insured Travellers',
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

            @if($insurance)
                <div class="v-section">
                    <p class="v-section-title">{{ $L['details'] }}</p>
                    <div class="v-card">
                        <table class="v-grid" role="presentation"><tr>
                            <td><p class="v-label">{{ $L['type'] }}</p><p class="v-value">{{ $insurance['insurance_type'] ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['plan'] }}</p><p class="v-value">{{ $insurance['plan_type'] ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['package'] }}</p><p class="v-value">{{ $insurance['package'] ?? '—' }}</p></td>
                        </tr></table>
                        <table class="v-grid" role="presentation" style="margin-top:6px;"><tr>
                            <td><p class="v-label">{{ $L['destination'] }}</p><p class="v-value">{{ $insurance['destination'] ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['date'] }}</p><p class="v-value">{{ $fmtDate($insurance['date']) ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['duration'] }}</p><p class="v-value">{{ $insurance['duration'] ? $insurance['duration'].' '.$L['days'] : '—' }}</p></td>
                            <td><p class="v-label">{{ $L['doc_ref'] }}</p><p class="v-value">{{ $insurance['document_reference'] ?? '—' }}</p></td>
                        </tr></table>
                    </div>
                    <p style="font-size:9.5px;color:#94a3b8;margin-top:8px;">{{ $L['disclaimer'] }}</p>
                </div>

                @if(count($roster) > 1)
                    <div class="v-section">
                        <p class="v-section-title">{{ $L['travellers'] }} ({{ count($roster) }})</p>
                        <table class="v-table">
                            <thead><tr><th>{{ $L['name'] }}</th><th>{{ $L['doc_ref'] }}</th></tr></thead>
                            <tbody>
                            @foreach($roster as $person)
                                <tr>
                                    <td>{{ $person['name'] ?? '—' }}</td>
                                    <td>{{ $person['document_reference'] ?? '—' }}</td>
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
