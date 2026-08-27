{{--
    Generic Service Voucher — vouchers.segment-generic (plan §5 row 5, §0,
    §7, §16 step 3).

    The honest design for car/rail/esim/event/tour — the five task types
    with NO detail table — and for any flight/hotel/visa/insurance task
    whose own detail row is simply missing (verified live: 78 flight + 8
    hotel tasks, plan §0). It never claims structured data it doesn't
    have: it prints `tasks.venue` / `tasks.additional_info` verbatim
    (verified live to be genuinely printable free text for transfers,
    plan §0) behind an honest type badge, and nothing else.

    Variable contract: $payload.{company,client,agent,task,
    segment:{type_label,venue,additional_info,date}, voucher, terms,
    money|null, payment|null}. $isPdf (bool), $sample (bool).
    (segment is ALWAYS present when this view is used — the repository
    guarantees it, plan §7 "always, never an empty hole".)
--}}
@php
    $lang = $payload['voucher']['language'] ?? 'EN';
    $company = $payload['company'];
    $client = $payload['client'];
    $agent = $payload['agent'];
    $task = $payload['task'];
    $segment = $payload['segment'] ?? ['type_label' => null, 'venue' => null, 'additional_info' => null, 'date' => null];
    $terms = $payload['terms'] ?? null;
    $isPdf = $isPdf ?? false;
    $sample = $sample ?? false;

    $L = $lang === 'ARB' ? [
        'title' => 'سند خدمة', 'badge' => 'سند خدمة',
        'client_info' => 'بيانات العميل', 'name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'الهاتف',
        'details' => 'تفاصيل الخدمة', 'route' => 'المسار / الموقع', 'date' => 'التاريخ', 'notes' => 'ملاحظات',
        'booked_by' => 'وكيل الحجز',
        'honest_note' => 'يعرض هذا السند من بيانات نصية عامة — لا تتوفر بيانات منظمة لهذا النوع من الخدمة بعد.',
        'no_data' => 'لا تتوفر تفاصيل إضافية لهذه الخدمة.',
        'travellers' => 'المسافرون',
    ] : [
        'title' => 'Service Voucher', 'badge' => 'Service Voucher',
        'client_info' => 'Client', 'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone',
        'details' => 'Service Details', 'route' => 'Route / Location', 'date' => 'Date', 'notes' => 'Notes',
        'booked_by' => 'Booking Agent',
        'honest_note' => 'This voucher is rendered from free-text booking notes — no structured data exists for this service type yet.',
        'no_data' => 'No further details are available for this service.',
        'travellers' => 'Travellers',
    ];

    $fmtDate = function ($v) {
        if (empty($v)) return null;
        try { return \Carbon\Carbon::parse($v)->format('d M Y'); } catch (\Throwable $e) { return $v; }
    };

    $typeLabel = $segment['type_label'] ?: $L['badge'];
    // Only rendered as its own section for more than one traveller sharing
    // this booking's reference (BLOCKER B1 owner memo).
    $roster = $segment['roster'] ?? [];
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
            'company' => $company, 'badge' => $typeLabel.' '.$L['badge'],
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
                        <td><p class="v-label">{{ $L['name'] }}</p><p class="v-value">{{ $client['name'] ?? ($task['passenger_name'] ?? '—') }}</p></td>
                        <td><p class="v-label">{{ $L['email'] }}</p><p class="v-value">{{ $client['email'] ?? '—' }}</p></td>
                        <td><p class="v-label">{{ $L['phone'] }}</p><p class="v-value">{{ $client['phone'] ?? '—' }}</p></td>
                    </tr></table>
                </div>
            </div>

            <div class="v-section">
                <p class="v-section-title">{{ $L['details'] }}</p>
                @if(!empty($segment['venue']) || !empty($segment['additional_info']) || !empty($segment['date']))
                    <div class="v-card">
                        <table class="v-grid" role="presentation"><tr>
                            <td style="width:35%;"><p class="v-label">{{ $L['date'] }}</p><p class="v-value">{{ $fmtDate($segment['date']) ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['route'] }}</p><p class="v-value">{{ $segment['venue'] ?? '—' }}</p></td>
                        </tr></table>
                        @if(!empty($segment['additional_info']))
                            <div style="margin-top:10px;">
                                <p class="v-label">{{ $L['notes'] }}</p>
                                <p style="font-size:11px;color:#334155;white-space:pre-line;margin:2px 0 0 0;">{!! nl2br(e($segment['additional_info'])) !!}</p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="v-note">{{ $L['no_data'] }}</div>
                @endif
                <p style="font-size:9.5px;color:#94a3b8;margin-top:8px;">{{ $L['honest_note'] }}</p>
            </div>

            @if(count($roster) > 1)
                <div class="v-section">
                    <p class="v-section-title">{{ $L['travellers'] }} ({{ count($roster) }})</p>
                    <div class="v-card">
                        @foreach($roster as $person)
                            <p class="v-value" style="margin-bottom:4px;">{{ $person['name'] ?? '—' }}</p>
                        @endforeach
                    </div>
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
