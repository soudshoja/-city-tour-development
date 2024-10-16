<!-- <button {{ $attributes->merge([
        'type' => 'submit', 
        'class' => 'CustomButton text-white bg-gradient-to-r from-[#b3a170] to-[#3572b1] hover:bg-gradient-to-bl focus:ring-4 focus:outline-none focus:ring-yellow-200 dark:focus:ring-blue-800 font-medium rounded-lg text-center mb-2 transition ease-in-out duration-150'
    ]) }}>
    {{ $slot }}
</button> -->

<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'btn btn-primary'
    ]) }}>
    {{ $slot }}
</button>