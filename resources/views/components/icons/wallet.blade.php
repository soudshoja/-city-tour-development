@props(['class' => 'w-5 h-5'])

<svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
    <path fill="currentColor" d="M21 7.28V5c0-1.1-.9-2-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-2.28A2 2 0 0 0 22 15V9a2 2 0 0 0-1-1.72M20 15H12V9h8zM5 19V5h14v2H12a2 2 0 0 0-2 2v6c0 1.1.9 2 2 2h7v2z" />
    <circle fill="currentColor" cx="16" cy="12" r="1.5" />
</svg>
