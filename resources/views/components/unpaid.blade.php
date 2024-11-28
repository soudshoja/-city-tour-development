
<div {{ $attributes->merge(['class' => 'bg-gradient-to-r from-red-500 to-red-700 text-white font-bold rounded-md shadow-md p-2 w-full']) }}>
    <p class="text-center">{{ $slot }}</p>
</div>