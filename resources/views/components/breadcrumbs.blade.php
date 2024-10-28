{{-- resources/views/components/breadcrumbs.blade.php --}}
@php
// Check if "Dashboard" is already in the breadcrumbs, and add it only if it’s missing
$breadcrumbs = collect($breadcrumbs)->prepend(['label' => 'Dashboard', 'url' =>
route('dashboard')])->unique('label')->toArray();
@endphp

<ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
    @foreach ($breadcrumbs as $breadcrumb)
    <li class="{{ !$loop->first ? 'before:content-[\'/\'] before:mr-1' : '' }}">
        @if (isset($breadcrumb['url']))
        <a href="{{ $breadcrumb['url'] }}" class="customBlueColor hover:underline">{{ $breadcrumb['label'] }}</a>
        @else
        <span>{{ $breadcrumb['label'] }}</span>
        @endif
    </li>
    @endforeach
</ul>