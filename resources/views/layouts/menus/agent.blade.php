<!-- tasks list -->
<div x-data="{ open: false }" x-cloak class="relative">
    <a @mouseenter="open = true" @mouseleave="open = false"
        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
        href="#">

        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M2 17.5C2 16.5654 2 16.0981 2.20096 15.75C2.33261 15.522 2.52197 15.3326 2.75 15.201C3.09808 15 3.56538 15 4.5 15C5.43462 15 5.90192 15 6.25 15.201C6.47803 15.3326 6.66739 15.522 6.79904 15.75C7 16.0981 7 16.5654 7 17.5C7 18.4346 7 18.9019 6.79904 19.25C6.66739 19.478 6.47803 19.6674 6.25 19.799C5.90192 20 5.43462 20 4.5 20C3.56538 20 3.09808 20 2.75 19.799C2.52197 19.6674 2.33261 19.478 2.20096 19.25C2 18.9019 2 18.4346 2 17.5Z"
                stroke="#1C274C" stroke-width="1.5" />
            <path opacity="0.5"
                d="M9.5 17.5C9.5 16.5654 9.5 16.0981 9.70096 15.75C9.83261 15.522 10.022 15.3326 10.25 15.201C10.5981 15 11.0654 15 12 15C12.9346 15 13.4019 15 13.75 15.201C13.978 15.3326 14.1674 15.522 14.299 15.75C14.5 16.0981 14.5 16.5654 14.5 17.5C14.5 18.4346 14.5 18.9019 14.299 19.25C14.1674 19.478 13.978 19.6674 13.75 19.799C13.4019 20 12.9346 20 12 20C11.0654 20 10.5981 20 10.25 19.799C10.022 19.6674 9.83261 19.478 9.70096 19.25C9.5 18.9019 9.5 18.4346 9.5 17.5Z"
                stroke="#1C274C" stroke-width="1.5" />
            <path opacity="0.7"
                d="M17 17.5C17 16.5654 17 16.0981 17.201 15.75C17.3326 15.522 17.522 15.3326 17.75 15.201C18.0981 15 18.5654 15 19.5 15C20.4346 15 20.9019 15 21.25 15.201C21.478 15.3326 21.6674 15.522 21.799 15.75C22 16.0981 22 16.5654 22 17.5C22 18.4346 22 18.9019 21.799 19.25C21.6674 19.478 21.478 19.6674 21.25 19.799C20.9019 20 20.4346 20 19.5 20C18.5654 20 18.0981 20 17.75 19.799C17.522 19.6674 17.3326 19.478 17.201 19.25C17 18.9019 17 18.4346 17 17.5Z"
                stroke="#1C274C" stroke-width="1.5" />
            <path
                d="M4.5 15V9C4.5 6.64298 4.5 5.46447 5.23223 4.73223C5.96447 4 7.14298 4 9.5 4H14.5C16.857 4 18.0355 4 18.7678 4.73223C19.5 5.46447 19.5 6.64298 19.5 9V12M19.5 12L21.5 10M19.5 12L17.5 10"
                stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Tasks</span>
        <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clip-rule="evenodd" />
        </svg>
    </a>
    <!-- Dropdown Menu -->
    <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
        class="absolute  z-10 mt-2 w-32 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
        x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="py-1">

            <a href="{{ route('tasks.index') }}"
                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Tasks
                List</a>

        </div>
    </div>
</div>
<!-- ./ tasks list -->



<!-- users list -->
<div x-data="{ open: false }" x-cloak class="relative">
    <a @mouseenter="open = true" @mouseleave="open = false"
        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
        href="#">

        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="6" r="4" stroke="#1C274C" stroke-width="1.5" />
            <path opacity="0.5" d="M18 9C19.6569 9 21 7.88071 21 6.5C21 5.11929 19.6569 4 18 4" stroke="#1C274C"
                stroke-width="1.5" stroke-linecap="round" />
            <path opacity="0.5" d="M6 9C4.34315 9 3 7.88071 3 6.5C3 5.11929 4.34315 4 6 4" stroke="#1C274C"
                stroke-width="1.5" stroke-linecap="round" />
            <ellipse cx="12" cy="17" rx="6" ry="4" stroke="#1C274C" stroke-width="1.5" />
            <path opacity="0.5" d="M20 19C21.7542 18.6153 23 17.6411 23 16.5C23 15.3589 21.7542 14.3847 20 14"
                stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
            <path opacity="0.5" d="M4 19C2.24575 18.6153 1 17.6411 1 16.5C1 15.3589 2.24575 14.3847 4 14"
                stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
        </svg>

        <span class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Users</span>
        <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clip-rule="evenodd" />
        </svg>
    </a>
    <!-- Dropdown Menu -->
    <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
        class="absolute  z-10 mt-2 w-32 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
        x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="py-1">

            <a href="{{ route('clients.list') }}"
                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Clients
                List</a>
        </div>
    </div>
</div>
<!-- ./ users list -->

<!-- finances list-->
<div x-data="{ open: false }" x-cloak class="relative">
    <a @mouseenter="open = true" @mouseleave="open = false"
        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
        href="#">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M2 14C2 10.2288 2 8.34315 3.17157 7.17157C4.34315 6 6.22876 6 10 6H14C17.7712 6 19.6569 6 20.8284 7.17157C22 8.34315 22 10.2288 22 14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14Z"
                stroke="#1C274C" stroke-width="1.5" />
            <path opacity="0.5"
                d="M16 6C16 4.11438 16 3.17157 15.4142 2.58579C14.8284 2 13.8856 2 12 2C10.1144 2 9.17157 2 8.58579 2.58579C8 3.17157 8 4.11438 8 6"
                stroke="#1C274C" stroke-width="1.5" />
            <path opacity="0.5"
                d="M12 17.3333C13.1046 17.3333 14 16.5871 14 15.6667C14 14.7462 13.1046 14 12 14C10.8954 14 10 13.2538 10 12.3333C10 11.4129 10.8954 10.6667 12 10.6667M12 17.3333C10.8954 17.3333 10 16.5871 10 15.6667M12 17.3333V18M12 10V10.6667M12 10.6667C13.1046 10.6667 14 11.4129 14 12.3333"
                stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
        </svg>

        <span class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Finances</span>
        <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clip-rule="evenodd" />
        </svg>
    </a>
    <!-- Dropdown Menu -->
    <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
        class="absolute  z-10 mt-2 w-36 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
        x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
        <div class="py-1">

            <a href="#"
                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                Invoices List</a>

            <a href="{{ route('charges.index') }}"
                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                Manage Charges
            </a>
        </div>
    </div>
</div>
<!-- ./ finances list-->


<!--  create invoice button -->
<div class="w-full flex justify-end">
    <a href="{{ route('invoice.create') }}" class="btn btn-success">Create Invoice</a>
</div>