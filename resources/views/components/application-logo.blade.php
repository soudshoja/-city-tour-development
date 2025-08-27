@props([
    'companyLogo' => asset('images/UserPic.svg'),
    'width' => '100',
    'height' => '50',
    'class' => '',
    'alt' => 'City App Logo'
])

<img 
    id="logo" 
    src="{{ $companyLogo }}" 
    alt="{{ $alt }}" 
    width="{{ $width }}" 
    height="{{ $height }}"
    {{ $attributes->merge(['class' => $class]) }}
>
