{{--
    Hotel Voucher — vouchers.hotel-classic (plan §5 row 1, §16 step 3).

    Plain-inline-CSS, dompdf-safe port of the orphaned
    resources/views/tasks/pdf/hotel.blade.php (371 lines, Tailwind CDN —
    dead inside dompdf). Ported over `task_hotel_details` + `hotels`,
    same cancellation-policy JSON decode and board-code labels, minus its
    two things this plan deliberately drops:
      - the Tailwind CDN + Font Awesome CDN <script>/<link> tags — replaced
        by @include('vouchers.partials.styles'), plain CSS, no external
        network fetch of any kind (dompdf cannot fetch remote CSS anyway);
      - the <x-supplier-procedure> include — a shipped voucher NEVER
        prints the supplier (plan §14.11 / this step's own rule 6).

    Variable contract (plan §6 "hotel" row + common blocks) — the ONLY
    things this view may read. Every field can be null; render "—" or
    drop the row, never a fatal.
      $payload: [
        company: {name,logo,address,phone,email,whatsapp,socials,currency,duty_phone,footer_note},
        client: {name,phone,email}|null,
        agent: {name}|null,
        task: {reference,status,issued_date,cancellation_policy[],...},
        hotel: {hotel:{name,address,city,state,country,phone,rating,...},
                check_in,check_out,nights,booking_time,room_reference,
                room_type,room_name,room_amount,meal_type_label,
                is_refundable,supplements}|null,
        segment: {...}|null,   -- when hotel is null (no detail row, §7)
        voucher: {number,version,issued_at,language,qr_url},
        terms: {title,content}|null,
        money: {...}|null,     -- only when the template's show_price is on
        payment: {...}|null,   -- only when show_payment_status is on
      ]
      $isPdf (bool), $sample (bool)
--}}
@php
    $lang = $payload['voucher']['language'] ?? 'EN';
    $company = $payload['company'];
    $client = $payload['client'];
    $agent = $payload['agent'];
    $task = $payload['task'];
    $hotel = $payload['hotel'];
    $terms = $payload['terms'] ?? null;
    $isPdf = $isPdf ?? false;
    $sample = $sample ?? false;

    $L = $lang === 'ARB' ? [
        'title' => 'سند حجز فندقي',
        'badge' => 'سند حجز فندقي',
        'booking_ref' => 'مرجع الحجز',
        'client_info' => 'بيانات العميل',
        'name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'الهاتف',
        'stay' => 'تفاصيل الإقامة',
        'hotel_name' => 'الفندق', 'check_in' => 'تسجيل الوصول', 'check_out' => 'تسجيل المغادرة',
        'nights' => 'عدد الليالي', 'booked_on' => 'تاريخ الحجز',
        'room' => 'تفاصيل الغرفة',
        'room_ref' => 'رقم تأكيد الفندق', 'internal_ref' => 'مرجعنا الداخلي',
        'room_type' => 'نوع الغرفة', 'board' => 'نظام الإقامة', 'refundable' => 'قابل للاسترداد',
        'non_refundable' => 'غير قابل للاسترداد', 'issued' => 'أصدر بتاريخ',
        'cancellation' => 'سياسة الإلغاء', 'no_hotel_data' => 'لا تتوفر بيانات فندق مفصلة لهذا الحجز.',
        'booked_by' => 'وكيل الحجز',
        'nights_unit' => 'ليالي',
    ] : [
        'title' => 'Hotel Voucher',
        'badge' => 'Hotel Voucher',
        'booking_ref' => 'Booking Ref',
        'client_info' => 'Guest Information',
        'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone',
        'stay' => 'Stay Timeline',
        'hotel_name' => 'Hotel', 'check_in' => 'Check-In', 'check_out' => 'Check-Out',
        'nights' => 'Nights', 'booked_on' => 'Booked On',
        'room' => 'Room Details',
        'room_ref' => 'Hotel Confirmation No.', 'internal_ref' => 'Our Reference',
        'room_type' => 'Room Type', 'board' => 'Board Basis', 'refundable' => 'Refundable',
        'non_refundable' => 'Non-refundable', 'issued' => 'Issued',
        'cancellation' => 'Cancellation Policy', 'no_hotel_data' => 'No detailed hotel data is available for this booking.',
        'booked_by' => 'Booking Agent',
        'nights_unit' => 'nights',
    ];

    $fmtDate = function ($v) {
        if (empty($v)) return null;
        try { return \Carbon\Carbon::parse($v)->format('d M Y'); } catch (\Throwable $e) { return $v; }
    };
    $fmtDateTime = function ($v) {
        if (empty($v)) return null;
        try { return \Carbon\Carbon::parse($v)->format('d M Y, H:i'); } catch (\Throwable $e) { return $v; }
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
            <div class="v-section">
                <p class="v-section-title">{{ $L['client_info'] }}</p>
                <div class="v-card">
                    <table class="v-grid" role="presentation"><tr>
                        <td><p class="v-label">{{ $L['name'] }}</p><p class="v-value">{{ $client['name'] ?? '—' }}</p></td>
                        <td><p class="v-label">{{ $L['email'] }}</p><p class="v-value">{{ $client['email'] ?? '—' }}</p></td>
                        <td><p class="v-label">{{ $L['phone'] }}</p><p class="v-value">{{ $client['phone'] ?? '—' }}</p></td>
                    </tr></table>
                </div>
            </div>

            @if($hotel)
                <div class="v-section">
                    <p class="v-section-title">{{ $L['stay'] }}</p>
                    <div class="v-card">
                        <table class="v-grid" role="presentation"><tr>
                            <td><p class="v-label">{{ $L['hotel_name'] }}</p><p class="v-value">{{ $hotel['hotel']['name'] ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['check_in'] }}</p><p class="v-value">{{ $fmtDate($hotel['check_in']) ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['check_out'] }}</p><p class="v-value">{{ $fmtDate($hotel['check_out']) ?? '—' }}</p></td>
                            <td><p class="v-label">{{ $L['nights'] }}</p><p class="v-value">{{ $hotel['nights'] ? $hotel['nights'].' '.$L['nights_unit'] : '—' }}</p></td>
                        </tr></table>
                        @if($hotel['hotel'] && ($hotel['hotel']['address'] || $hotel['hotel']['city']))
                            <p style="margin:8px 0 0 0;font-size:10px;color:#64748b;">
                                {{ collect([$hotel['hotel']['address'] ?? null, $hotel['hotel']['city'] ?? null, $hotel['hotel']['country'] ?? null])->filter()->implode(', ') }}
                            </p>
                        @endif
                    </div>
                </div>

                <div class="v-section">
                    <p class="v-section-title">{{ $L['room'] }}</p>
                    <div class="v-card">
                        <table class="v-grid" role="presentation"><tr>
                            <td style="width:55%;">
                                <p class="v-label">{{ $L['room_type'] }}</p>
                                <p class="v-value">{{ $hotel['room_name'] ?? '—' }}</p>
                            </td>
                            <td>
                                <p class="v-label">{{ $L['board'] }}</p>
                                <span class="v-pill v-pill-blue">{{ $hotel['meal_type_label'] ?? '—' }}</span>
                                @if(!is_null($hotel['is_refundable']))
                                    <span class="v-pill {{ $hotel['is_refundable'] ? 'v-pill-green' : 'v-pill-red' }}">{{ $hotel['is_refundable'] ? $L['refundable'] : $L['non_refundable'] }}</span>
                                @endif
                            </td>
                        </tr></table>
                        <table class="v-grid" role="presentation" style="margin-top:6px;"><tr>
                            <td style="width:55%;">
                                <p class="v-label">{{ $L['room_ref'] }}</p>
                                <p class="v-value" style="font-size:13px;">{{ $hotel['room_reference'] ?? '—' }}</p>
                            </td>
                            <td>
                                <p class="v-label">{{ $L['internal_ref'] }}</p>
                                <p class="v-value v-value-muted">{{ $task['reference'] ?? '—' }}</p>
                            </td>
                        </tr></table>
                    </div>
                </div>

                @if(!empty($task['cancellation_policy']))
                    <div class="v-section">
                        <p class="v-section-title">{{ $L['cancellation'] }}</p>
                        <div class="v-card">
                            @foreach($task['cancellation_policy'] as $p)
                                @if(is_array($p))
                                    <table class="v-grid" role="presentation" style="margin-bottom:4px;"><tr>
                                        @foreach($p as $field => $value)
                                            @if(!is_array($value))
                                                <td><p class="v-label">{{ ucwords(str_replace('_', ' ', (string) $field)) }}</p><p class="v-value">{{ $value }}</p></td>
                                            @endif
                                        @endforeach
                                    </table>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <div class="v-section">
                    <div class="v-note">{{ $L['no_hotel_data'] }}</div>
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
