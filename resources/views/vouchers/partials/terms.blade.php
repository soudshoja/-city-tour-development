{{--
    Terms & conditions — always the LAST section on the voucher (plan
    §14.5: "voucher_templates.term_id, falling back to the company default
    term in the voucher's language; rendered as the last section"). Every
    field is already resolved by VoucherDataRepository::termsBlock() — this
    partial only renders; it never queries.

    Expects: $terms (array{title,content,language}|null).
--}}
@if(!empty($terms) && !empty($terms['content']))
<div class="v-section" style="margin-bottom:0;">
    <p class="v-section-title">{{ $terms['title'] ?: (($lang ?? 'EN') === 'ARB' ? 'الشروط والأحكام' : 'Terms & Conditions') }}</p>
    <div style="font-size:9.5px;color:#475569;white-space:pre-line;">{!! nl2br(e($terms['content'])) !!}</div>
</div>
@endif
