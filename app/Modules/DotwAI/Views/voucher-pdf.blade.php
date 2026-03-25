<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hotel Voucher — {{ $booking->confirmation_no ?? $booking->prebook_key }}</title>
    <style>
        /* ── Reset ── */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }

        /* ── Brand colours (matching existing hotel voucher) ── */
        :root { --brand: #2563eb; --accent: #f59e0b; --muted: #64748b; --border: #e2e8f0; --bg: #f8fafc; }

        /* ── Header ── */
        .header { background: #2563eb; color: #fff; padding: 18px 28px; }
        .header-inner { display: table; width: 100%; }
        .header-left  { display: table-cell; vertical-align: middle; width: 65%; }
        .header-right { display: table-cell; vertical-align: middle; width: 35%; text-align: right; }
        .company-name { font-size: 18px; font-weight: bold; }
        .company-meta { font-size: 9px; opacity: .85; margin-top: 4px; }
        .voucher-ref  { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
        .voucher-badge { display: inline-block; margin-top: 6px; padding: 3px 10px; border: 1px solid rgba(255,255,255,.4); border-radius: 12px; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; }
        .accent-bar { height: 4px; background: #f59e0b; }

        /* ── Content ── */
        .content { padding: 20px 28px; }
        .section { margin-bottom: 16px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; overflow: hidden; }
        .section-title { font-size: 12px; font-weight: bold; color: #1e3a8a; padding: 8px 14px; border-bottom: 1px solid #e2e8f0; background: #fff; }
        .section-body  { padding: 12px 14px; }

        /* ── Key-value rows ── */
        .kv-table { width: 100%; border-collapse: collapse; }
        .kv-table td { padding: 4px 0; vertical-align: top; }
        .kv-label { width: 38%; font-weight: bold; color: #64748b; font-size: 10px; text-transform: uppercase; }
        .kv-value { color: #1e293b; }

        /* ── Guest table ── */
        .guest-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .guest-table th { background: #eef2ff; text-align: left; padding: 5px 8px; font-size: 10px; color: #1e3a8a; border: 1px solid #e2e8f0; }
        .guest-table td { padding: 5px 8px; font-size: 11px; border: 1px solid #e2e8f0; }

        /* ── Total box ── */
        .total-box { background: #eef2ff; border: 1px solid #a5b4fc; border-radius: 6px; padding: 12px; text-align: center; margin-top: 8px; }
        .total-amount { font-size: 20px; font-weight: bold; color: #1e3a8a; }
        .total-label  { font-size: 9px; color: #64748b; margin-top: 2px; }

        /* ── Policy boxes ── */
        .policy-refundable { background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 6px; padding: 10px; }
        .policy-nonrefundable { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 10px; }
        .policy-title { font-weight: bold; font-size: 11px; }
        .policy-note  { font-size: 9px; color: #64748b; margin-top: 3px; }
        .policy-ar    { font-size: 10px; color: #64748b; margin-top: 2px; direction: rtl; text-align: right; }

        /* ── B2B agent section ── */
        .agent-section { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px; margin-top: 8px; }
        .agent-label { font-size: 9px; text-transform: uppercase; font-weight: bold; color: #92400e; margin-bottom: 4px; }

        /* ── Footer ── */
        .footer { text-align: center; padding: 16px 28px; border-top: 2px solid #2563eb; margin-top: 10px; }
        .footer-company { font-weight: bold; font-size: 12px; color: #1e3a8a; }
        .footer-ar { font-size: 11px; color: #64748b; }
        .footer-note { font-size: 9px; color: #94a3b8; margin-top: 6px; }
        .footer-generated { font-size: 8px; color: #cbd5e1; margin-top: 4px; }
    </style>
</head>
<body>

    {{-- ═══ Header ═══ --}}
    <div class="header">
        <div class="header-inner">
            <div class="header-left">
                <div class="company-name">{{ $company->name ?? 'City Travelers' }}</div>
                <div class="company-meta">
                    @if($company->address ?? null){{ $company->address }} &bull; @endif
                    @if($company->phone ?? null){{ $company->phone }} &bull; @endif
                    @if($company->email ?? null){{ $company->email }}@endif
                </div>
            </div>
            <div class="header-right">
                <div class="voucher-ref">{{ $booking->confirmation_no ?? 'PENDING' }}</div>
                <div class="voucher-badge">Hotel Voucher</div>
            </div>
        </div>
    </div>
    <div class="accent-bar"></div>

    <div class="content">

        {{-- ═══ Booking Reference ═══ --}}
        <div class="section">
            <div class="section-title">Booking Reference | الرقم المرجعي</div>
            <div class="section-body">
                <table class="kv-table">
                    <tr><td class="kv-label">Confirmation No</td><td class="kv-value">{{ $booking->confirmation_no ?? 'N/A' }}</td></tr>
                    <tr><td class="kv-label">Booking Code</td><td class="kv-value">{{ $booking->dotw_booking_code ?? $booking->prebook_key }}</td></tr>
                    <tr><td class="kv-label">Booking Date</td><td class="kv-value">{{ $booking->created_at?->format('d M Y') ?? 'N/A' }}</td></tr>
                    <tr><td class="kv-label">Status</td><td class="kv-value">{{ ucfirst($booking->status) }}</td></tr>
                </table>
            </div>
        </div>

        {{-- ═══ Hotel Details ═══ --}}
        <div class="section">
            <div class="section-title">Hotel Details | تفاصيل الفندق</div>
            <div class="section-body">
                <table class="kv-table">
                    <tr><td class="kv-label">Hotel</td><td class="kv-value">{{ $booking->hotel_name ?? 'N/A' }}</td></tr>
                    <tr><td class="kv-label">Check-in</td><td class="kv-value">{{ $booking->check_in?->format('d M Y (l)') ?? 'N/A' }}</td></tr>
                    <tr><td class="kv-label">Check-out</td><td class="kv-value">{{ $booking->check_out?->format('d M Y (l)') ?? 'N/A' }}</td></tr>
                    @php $nights = $booking->check_in && $booking->check_out ? $booking->check_in->diffInDays($booking->check_out) : 0; @endphp
                    <tr><td class="kv-label">Duration</td><td class="kv-value">{{ $nights }} night{{ $nights !== 1 ? 's' : '' }}</td></tr>
                    @if($booking->room_type_name)<tr><td class="kv-label">Room Type</td><td class="kv-value">{{ $booking->room_type_name }}</td></tr>@endif
                    @if($booking->meal_plan)<tr><td class="kv-label">Meal Plan</td><td class="kv-value">{{ $booking->meal_plan }}</td></tr>@endif
                </table>
            </div>
        </div>

        {{-- ═══ Guest Details ═══ --}}
        <div class="section">
            <div class="section-title">Guest Details | تفاصيل الضيوف</div>
            <div class="section-body">
                @php $guests = $booking->guest_details ?? []; @endphp
                @if(count($guests) > 0)
                <table class="guest-table">
                    <thead><tr><th>#</th><th>Name</th><th>Type</th></tr></thead>
                    <tbody>
                        @foreach($guests as $i => $guest)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ trim(($guest['salutation'] ?? '') . ' ' . ($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? '')) ?: 'Guest' }}</td>
                            <td>{{ ucfirst($guest['type'] ?? 'Adult') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <p style="color: #64748b;">Guest details not available</p>
                @endif
            </div>
        </div>

        {{-- ═══ Payment ═══ --}}
        <div class="section">
            <div class="section-title">Payment | الدفع</div>
            <div class="section-body">
                <div class="total-box">
                    <div class="total-amount">{{ $booking->display_currency ?? 'KWD' }} {{ number_format((float)($booking->display_total_fare ?? 0), 3) }}</div>
                    <div class="total-label">Total Amount | المبلغ الإجمالي</div>
                </div>
                @if($booking->payment_guaranteed_by)
                <table class="kv-table" style="margin-top: 8px;">
                    <tr><td class="kv-label">Payment Guaranteed By</td><td class="kv-value">{{ $booking->payment_guaranteed_by }}</td></tr>
                </table>
                @endif
            </div>
        </div>

        {{-- ═══ Cancellation Policy ═══ --}}
        <div class="section">
            <div class="section-title">Cancellation Policy | سياسة الإلغاء</div>
            <div class="section-body">
                @if(($booking->is_refundable ?? true) && !($booking->is_apr ?? false) && $booking->cancellation_deadline)
                <div class="policy-refundable">
                    <div class="policy-title">Free cancellation until {{ $booking->cancellation_deadline->format('d M Y') }}</div>
                    <div class="policy-ar">الإلغاء المجاني حتى {{ $booking->cancellation_deadline->format('d M Y') }}</div>
                    <div class="policy-note">Cancellation after this date may incur charges as per hotel policy.</div>
                </div>
                @elseif(!($booking->is_refundable ?? true) || ($booking->is_apr ?? false))
                <div class="policy-nonrefundable">
                    <div class="policy-title">Non-Refundable (APR)</div>
                    <div class="policy-ar">غير قابل للاسترداد</div>
                    <div class="policy-note">This booking cannot be cancelled or modified without full charges.</div>
                </div>
                @else
                <div class="policy-refundable">
                    <div class="policy-title">Please contact us for cancellation policy details.</div>
                </div>
                @endif
            </div>
        </div>

        {{-- ═══ B2B Agent / Company Info (only for B2B track) ═══ --}}
        @if(in_array($booking->track, ['b2b', 'b2b_gateway']) && isset($agent))
        <div class="section">
            <div class="section-title">Agent Details | تفاصيل الوكيل</div>
            <div class="section-body">
                <table class="kv-table">
                    <tr><td class="kv-label">Agent Name</td><td class="kv-value">{{ $agent->name ?? 'N/A' }}</td></tr>
                    @if($agent->phone ?? null)<tr><td class="kv-label">Agent Phone</td><td class="kv-value">{{ $agent->phone }}</td></tr>@endif
                    @if($agent->email ?? null)<tr><td class="kv-label">Agent Email</td><td class="kv-value">{{ $agent->email }}</td></tr>@endif
                </table>
                @if(isset($agentCompany) && $agentCompany->id !== ($company->id ?? null))
                <div class="agent-section">
                    <div class="agent-label">Agency</div>
                    <table class="kv-table">
                        <tr><td class="kv-label">Company</td><td class="kv-value">{{ $agentCompany->name }}</td></tr>
                        @if($agentCompany->phone)<tr><td class="kv-label">Phone</td><td class="kv-value">{{ $agentCompany->phone }}</td></tr>@endif
                        @if($agentCompany->email)<tr><td class="kv-label">Email</td><td class="kv-value">{{ $agentCompany->email }}</td></tr>@endif
                        @if($agentCompany->address)<tr><td class="kv-label">Address</td><td class="kv-value">{{ $agentCompany->address }}</td></tr>@endif
                    </table>
                </div>
                @endif
            </div>
        </div>
        @endif

    </div>

    {{-- ═══ Footer ═══ --}}
    <div class="footer">
        <div class="footer-company">{{ $company->name ?? 'City Travelers' }}</div>
        <div class="footer-ar">سيتي ترافلرز</div>
        <div class="footer-note">
            This voucher is your confirmation of booking. Please present it at the hotel upon check-in.<br>
            هذه القسيمة هي تأكيد حجزك. يرجى تقديمها في الفندق عند تسجيل الدخول.
        </div>
        <div class="footer-generated">Generated on {{ now()->format('d M Y H:i') }}</div>
    </div>

</body>
</html>
