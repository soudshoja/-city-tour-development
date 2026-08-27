{{--
    BLOCKER B3 -- bidirectional reissue/refund cross-reference banner
    (§13-BIS.A). Owner's own words: "original voucher shows re-issued and
    shows new reference, and the new one should have old and new
    reference." Either document alone must tell the whole story, so the
    two blocks below are independent, not mutually exclusive -- a voucher
    reissued twice is simultaneously "the new one" relative to its
    predecessor AND "the original" relative to whatever replaced it next,
    and both facts render if both are true.

    Deliberately calm and factual (owner: a traveller reading this should
    understand the document's standing, not be alarmed) -- plain
    statements, no warning colours, no exclamation punctuation.

    $voucherStatus/$crossReference are PRESENTATION state computed fresh
    at render time (TravelVoucher::crossReferenceContext()) -- never part
    of the frozen $payload snapshot. Both are optional/absent for every
    caller that doesn't pass them (the Settings-gallery preview, sample
    fixtures) -- the PHP block just below defaults them to nothing, so
    this partial renders NOTHING for the ordinary, most-common case of a
    voucher with no reissue/refund history at all.

    NOTE ON THIS COMMENT ITSELF: never spell a directive keyword with its
    leading at-sign inside a Blade comment in this codebase -- this
    file's own first draft named the PHP-block directive that way in the
    paragraph above and it broke the whole partial. Laravel's
    storePhpBlocks() pre-extracts everything between the FIRST such open
    directive and the FIRST matching close directive found ANYWHERE in
    the raw source, comment or not, BEFORE Blade comments ever get
    compiled away -- so a stray directive word in prose here paired with
    the real closing directive two dozen lines down and swallowed this
    entire comment, corrupting the compiled view. Same family of gotcha
    as this project's own documented one ("an @-word inside a // JS
    comment compiles as a directive and 500s the page") -- it reaches
    into Blade comments too, not just JS comments.

    Path B (void -> same details re-issued, VoucherService::
    updateInPlaceAfterVoid()) never sets superseded_by_id, so
    $crossReference is empty for it and NOTHING renders here -- exactly
    the "no trace of the void reaches any client-facing surface"
    behaviour §13-BIS.B requires. Do not add a status branch for
    STATUS_VOID_PENDING/STATUS_CANCELLED/STATUS_SUPERSEDED here: those
    statuses never reach a template at all (TravelVoucher::
    PUBLICLY_DEAD_STATUSES; the public route 404s neutrally before
    resolving a view, per BLOCKER-fix #3's unavailable page).

    Expects: $voucherStatus (?string), $crossReference (?array{
        supersededByReference: ?string, supersededByUrl: ?string,
        previousReference: ?string, previousUrl: ?string,
    }), $lang ('EN'|'ARB').
--}}
@php
    $vs = $voucherStatus ?? null;
    $cr = $crossReference ?? [];
    $isRefunded = $vs === \App\Models\TravelVoucher::STATUS_REFUNDED;
    $isReissued = $vs === \App\Models\TravelVoucher::STATUS_REISSUED;
    $showSupersededBy = ($isReissued || $isRefunded) && ! empty($cr['supersededByReference']);
    $showPrevious = ! empty($cr['previousReference']);
    $arb = ($lang ?? 'EN') === 'ARB';
@endphp
@if($showSupersededBy)
    <div class="v-status-note v-status-note-{{ $isRefunded ? 'refunded' : 'reissued' }}">
        <p class="v-status-note-title">
            {{ $arb
                ? ($isRefunded ? 'تم استرداد هذا السند.' : 'تم إعادة إصدار هذا السند.')
                : ($isRefunded ? 'This voucher has been refunded.' : 'This voucher has been re-issued.') }}
        </p>
        <p class="v-status-note-body">
            {{ $arb ? 'السند الجديد رقم:' : 'The new voucher is:' }}
            @if(!empty($cr['supersededByUrl']))
                <a href="{{ $cr['supersededByUrl'] }}">{{ $cr['supersededByReference'] }}</a>
            @else
                {{ $cr['supersededByReference'] }}
            @endif
        </p>
    </div>
@endif
@if($showPrevious)
    <div class="v-status-note v-status-note-replaces">
        <p class="v-status-note-body">
            {{ $arb ? 'هذا السند يحل محل السند رقم:' : 'This voucher replaces:' }}
            @if(!empty($cr['previousUrl']))
                <a href="{{ $cr['previousUrl'] }}">{{ $cr['previousReference'] }}</a>
            @else
                {{ $cr['previousReference'] }}
            @endif
        </p>
    </div>
@endif
