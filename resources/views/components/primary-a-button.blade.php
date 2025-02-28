
<a {{ $attributes->merge([
    'class' => 'flex items-center px-2 py-2 bg-black text-white rounded-md hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none'
    ]) }}>
    {{ $slot }}
</a>