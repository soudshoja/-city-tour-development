@props(['action'])

<form class="flex justify-between items-center gap-2 w-full sm:w-3/4 md:w-1/2" action="{{ $action }}" method="GET">
    @csrf
    <div class="relative w-full">
        <input type="text" name="search" value="{{ request('search') }}"
            id="search-client"
            placeholder=""
            oninput=""
            class="block px-3 pb-2.5 pt-4 w-full text-sm text-gray-900 bg-transparent border-b-2 border-gray-300 appearance-none
                                    dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer rounded-full" />

        <label for="search-client"
            class="absolute text-md text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-4 scale-75 top-2 origin-[0]
                                    bg-white dark:bg-gray-900 px-2 peer-focus:text-blue-600 peer-focus:dark:text-blue-500
                                    peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2
                                    peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 start-1">
            Quick search for client
        </label>
    </div>
    <button type="submit"
        class="DarkBGcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 flex items-center justify-center h-10 w-12 rounded-full p-0">
        <svg class="mx-auto" width="18" height="18" viewBox="0 0 24 24" fill="none"
            xmlns="http://www.w3.org/2000/svg">
            <circle cx="11.5" cy="11.5" r="9.5" stroke="#fff" stroke-width="1.5"
                opacity="0.5" class="dark:stroke-gray-300"></circle>
            <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round"
                class="dark:stroke-gray-300"></path>
        </svg>
    </button>
    @if(request('search'))
    <button type="button" id="" onclick="window.location.href = '{{ $action }}'"
        class="bg-red-600 dark:!bg-gray-700 dark:!hover:bg-gray-600 flex items-center justify-center h-10 w-12 rounded-full p-0">
        <svg class="mx-auto" width="18" height="18" viewBox="0 0 24 24" fill="none"
            xmlns="http://www.w3.org/2000/svg">
            <path d="M6 18L18 6M6 6L18 18" stroke="#fff" stroke-width="1.5"
                class="dark:stroke-gray-300"></path>
        </svg>
    </button>
    @endif
</form>