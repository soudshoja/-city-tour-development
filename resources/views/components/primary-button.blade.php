<!-- <button {{ $attributes->merge([
        'type' => 'submit', 
        'class' => 'CustomButton text-white bg-gradient-to-r from-[#b3a170] to-[#3572b1] hover:bg-gradient-to-bl focus:ring-4 focus:outline-none focus:ring-yellow-200 dark:focus:ring-blue-800 font-medium rounded-lg text-center mb-2 transition ease-in-out duration-150'
    ]) }}>
    {{ $slot }}
</button> -->

<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'h-full flex items-center px-6 py-2 bg-black text-white rounded-lg hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none'
    ]) }}>
    {{ $slot }}
</button>