{{--
    Shared voucher footer (plan §16 step 3): copyright line + the optional
    `settings` rows voucher.duty_phone / voucher.footer_note (plan §14.4,
    §14.10 — companies is frozen to us, §2.5, so any branding beyond
    logo/address/phone/email lives in `settings`, never a new column).
    Both render only when actually set for the company; nothing here is a
    placeholder invented for the demo.

    Expects: $company (array), $lang ('EN'|'ARB').
--}}
<table class="vf-table" role="presentation">
    <tr>
        <td>
            @if(!empty($company['footer_note']))
                <div style="opacity:1;font-weight:bold;margin-bottom:2px;">{{ $company['footer_note'] }}</div>
            @endif
            <div>
                &copy; {{ date('Y') }} {{ $company['name'] ?? ($lang === 'ARB' ? 'شركة السفر' : 'Travel Company') }}.
                {{ $lang === 'ARB' ? 'هذا السند صالح للحجز المحدد فقط.' : 'This voucher is valid for the specified booking only.' }}
                @if(!empty($company['duty_phone']))
                    {{ $lang === 'ARB' ? 'للطوارئ:' : 'Emergency:' }} {{ $company['duty_phone'] }}
                @endif
            </div>
        </td>
    </tr>
</table>
