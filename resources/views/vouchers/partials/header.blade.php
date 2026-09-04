{{--
    Shared voucher header (plan §16 step 3): company branding block +
    voucher-type badge + the ONE reference the recipient actually needs
    (never an internal id, never the supplier — plan §14.9 / §14.11).

    Expects: $company (array, VoucherDataRepository company block),
    $badge (string, localized voucher-type label), $reference (string),
    $isPdf (bool), $lang ('EN'|'ARB').

    Logo path (plan §12 "one source of truth, rendered identically by the
    public HTML route and the PDF route"): mirrors the proven working
    pattern already in resources/views/invoice/pdf/invoice.blade.php —
    dompdf needs a local filesystem path, a browser needs a public URL.
--}}
<table class="vh-table" role="presentation">
    <tr>
        <td class="vh-logo-cell">
            @if(!empty($company['logo']))
                @if($isPdf ?? false)
                    <img class="vh-logo" src="{{ storage_path('app/public/'.$company['logo']) }}" alt="{{ $company['name'] ?? 'Company' }}">
                @else
                    <img class="vh-logo" src="{{ asset('storage/'.$company['logo']) }}" alt="{{ $company['name'] ?? 'Company' }}">
                @endif
            @endif
        </td>
        <td>
            <p class="vh-company-name">{{ $company['name'] ?? ($lang === 'ARB' ? 'شركة السفر' : 'Travel Company') }}</p>
            @if(!empty($company['address']))<p class="vh-company-sub">{{ $company['address'] }}</p>@endif
            <div class="vh-company-contact">
                @if(!empty($company['phone']))<span>{{ $company['phone'] }}</span>@endif
                @if(!empty($company['phone']) && !empty($company['email']))<span> &middot; </span>@endif
                @if(!empty($company['email']))<span>{{ $company['email'] }}</span>@endif
            </div>
        </td>
        <td class="vh-badge-cell">
            <div class="vh-ref">{{ $reference }}</div>
            <span class="vh-badge">{{ $badge }}</span>
        </td>
    </tr>
</table>
