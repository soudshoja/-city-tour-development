
<div {{ $attributes->merge(['class' => 'bg-red-500 text-white font-bold rounded-md shadow-md p-2 w-full']) }}>
    <p class="text-center">{{ $slot }}</p>
</div>