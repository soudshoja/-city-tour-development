{{--
    Flight E-Ticket / Itinerary — vouchers.flight-classic (plan §5 row 2,
    §16 step 3).

    Plain-inline-CSS, dompdf-safe port of the orphaned
    resources/views/tasks/pdf/flight.blade.php (260 lines, Tailwind CDN —
    dead inside dompdf). Ported over `task_flight_details`, keeping its
    established shape: itinerary legs in order, then the passenger roster
    grouped by gds_reference (the plan's own §13-BIS/V9 correction already
    lives in VoucherDataRepository::flightRoster() — this view just
    renders whatever roster it is handed).

    Client sees PNR (gds_reference) + ticket number, never an internal
    task id and never the ticketing supplier/consolidator (plan §14.9 /
    this step's rule 6 — the payload has no supplier field to begin with).

    Variable contract (plan §6 "flight" row + common blocks):
      $payload: [
        company, client, agent, task: {reference,gds_reference,ticket_number,...},
        flight: {legs:[{departure_time,arrival_time,airport_from,airport_to,
                 airline,flight_number,ticket_number,class_type,
                 baggage_allowed,flight_meal,seat_no,...}],
                 ancillaries:[...], roster:[{passenger_name,ticket_number,
                 seat_no,baggage_allowed,flight_meal,class_type}]}|null,
        segment: {...}|null,  -- when flight is null (no detail rows, §7)
        voucher, terms, money|null, payment|null,
      ]
      $isPdf (bool), $sample (bool)
--}}
@php
    $lang = $payload['voucher']['language'] ?? 'EN';
    $company = $payload['company'];
    $client = $payload['client'];
    $agent = $payload['agent'];
    $task = $payload['task'];
    $flight = $payload['flight'];
    $terms = $payload['terms'] ?? null;
    $isPdf = $isPdf ?? false;
    $sample = $sample ?? false;

    $L = $lang === 'ARB' ? [
        'title' => 'سند حجز طيران', 'badge' => 'تذكرة إلكترونية',
        'pnr' => 'رقم الحجز (PNR)',
        'client_info' => 'بيانات المسافر الرئيسي', 'name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'phone' => 'الهاتف',
        'segments' => 'مسار الرحلة', 'depart' => 'المغادرة', 'arrive' => 'الوصول', 'duration' => 'المدة',
        'flight_no' => 'رقم الرحلة', 'class' => 'الدرجة', 'baggage' => 'الأمتعة', 'meal' => 'الوجبة', 'seat' => 'المقعد',
        'extras' => 'خدمات إضافية',
        'passengers' => 'الركاب', 'ticket_no' => 'رقم التذكرة',
        'no_flight_data' => 'لا تتوفر بيانات رحلة مفصلة لهذا الحجز.',
        'terminal' => 'الصالة',
    ] : [
        'title' => 'Flight Voucher', 'badge' => 'E-Ticket / Itinerary',
        'pnr' => 'Booking Ref (PNR)',
        'client_info' => 'Lead Traveller', 'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone',
        'segments' => 'Flight Itinerary', 'depart' => 'Depart', 'arrive' => 'Arrive', 'duration' => 'Duration',
        'flight_no' => 'Flight No.', 'class' => 'Class', 'baggage' => 'Baggage', 'meal' => 'Meal', 'seat' => 'Seat',
        'extras' => 'Extras',
        'passengers' => 'Passengers', 'ticket_no' => 'Ticket No.',
        'no_flight_data' => 'No detailed flight data is available for this booking.',
        'terminal' => 'Terminal',
    ];

    $fmtDateTime = function ($v) {
        if (empty($v)) return null;
        try { return \Carbon\Carbon::parse($v)->format('d M Y, H:i'); } catch (\Throwable $e) { return $v; }
    };

    $topReference = $task['gds_reference'] ?: ($task['reference'] ?? '—');
@endphp
<!DOCTYPE html>
<html lang="{{ $lang === 'ARB' ? 'ar' : 'en' }}" dir="{{ $lang === 'ARB' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $L['title'] }}: {{ $topReference }}</title>
    @include('vouchers.partials.styles', ['isPdf' => $isPdf, 'lang' => $lang])
</head>
<body>
    <div class="voucher-page">
        @include('vouchers.partials.sample_badge', ['sample' => $sample, 'lang' => $lang])
        @include('vouchers.partials.header', [
            'company' => $company, 'badge' => $L['badge'],
            'reference' => $topReference, 'isPdf' => $isPdf, 'lang' => $lang,
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
                        <td><p class="v-label">{{ $L['pnr'] }}</p><p class="v-value">{{ $task['gds_reference'] ?? '—' }}</p></td>
                    </tr></table>
                </div>
            </div>

            @if($flight && !empty($flight['legs']))
                <div class="v-section">
                    <p class="v-section-title">{{ $L['segments'] }}</p>
                    <table class="v-table">
                        <thead><tr>
                            <th>{{ $L['depart'] }}</th><th>{{ $L['arrive'] }}</th><th>{{ $L['flight_no'] }}</th>
                            <th>{{ $L['class'] }}</th><th>{{ $L['seat'] }}</th><th>{{ $L['baggage'] }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($flight['legs'] as $leg)
                            <tr>
                                <td>
                                    <strong>{{ $leg['airport_from'] ?? '—' }}</strong>
                                    @if(!empty($leg['departure_time']))<br><span style="color:#64748b;">{{ $fmtDateTime($leg['departure_time']) }}</span>@endif
                                    @if(!empty($leg['terminal_from']))<br><span style="color:#94a3b8;">{{ $L['terminal'] }} {{ $leg['terminal_from'] }}</span>@endif
                                </td>
                                <td>
                                    <strong>{{ $leg['airport_to'] ?? '—' }}</strong>
                                    @if(!empty($leg['arrival_time']))<br><span style="color:#64748b;">{{ $fmtDateTime($leg['arrival_time']) }}</span>@endif
                                    @if(!empty($leg['terminal_to']))<br><span style="color:#94a3b8;">{{ $L['terminal'] }} {{ $leg['terminal_to'] }}</span>@endif
                                </td>
                                <td>{{ $leg['airline'] ?? '—' }}<br>{{ $leg['flight_number'] ?? '' }}</td>
                                <td>{{ $leg['class_type'] ?? '—' }}</td>
                                <td>{{ $leg['seat_no'] ?? '—' }}</td>
                                <td>{{ $leg['baggage_allowed'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if(!empty($flight['ancillaries']))
                    <div class="v-section">
                        <p class="v-section-title">{{ $L['extras'] }}</p>
                        <div class="v-card">
                            @foreach($flight['ancillaries'] as $a)
                                <p class="v-value" style="margin-bottom:4px;">{{ $a['description'] ?? '—' }} @if(!empty($a['flight_number']))&middot; {{ $a['flight_number'] }}@endif</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($flight['roster']))
                    <div class="v-section">
                        <p class="v-section-title">{{ $L['passengers'] }}</p>
                        <table class="v-table">
                            <thead><tr>
                                <th>{{ $L['name'] }}</th><th>{{ $L['ticket_no'] }}</th><th>{{ $L['seat'] }}</th><th>{{ $L['meal'] }}</th>
                            </tr></thead>
                            <tbody>
                            @foreach($flight['roster'] as $p)
                                <tr>
                                    <td>{{ $p['passenger_name'] ?? '—' }}</td>
                                    <td>{{ $p['ticket_number'] ?? '—' }}</td>
                                    <td>{{ $p['seat_no'] ?? '—' }}</td>
                                    <td>{{ $p['flight_meal'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                <div class="v-section">
                    <div class="v-note">{{ $L['no_flight_data'] }}</div>
                </div>
            @endif

            @include('vouchers.partials.terms', ['terms' => $terms, 'lang' => $lang])
        </div>

        @include('vouchers.partials.footer', ['company' => $company, 'lang' => $lang])
    </div>
</body>
</html>
