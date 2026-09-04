{{--
    Diagonal SAMPLE watermark (plan §8: "a diagonal SAMPLE watermark" when
    the company has no booking of that type yet). Placed right after
    <div class="voucher-page"> opens so `position:absolute` anchors to it.

    Expects: $sample (bool), $lang ('EN'|'ARB').
--}}
@if($sample ?? false)
<div class="sample-ribbon">{{ ($lang ?? 'EN') === 'ARB' ? 'نموذج' : 'SAMPLE' }}</div>
@endif
