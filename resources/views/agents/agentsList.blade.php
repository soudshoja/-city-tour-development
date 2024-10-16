<x-app-layout>




    <style>
    #searchInput:focus {
        outline: none !important;
        /* Removes the blue outline */
        box-shadow: none !important;
        /* Removes any focus box-shadow */
        border-color: inherit !important;
        /* Keeps the border color unchanged */
    }

    .CheckBoxColor {
        color: #bec7e3 !important;
    }

    /* Custom scrollbar styling for webkit browsers */
    .custom-scrollbar {
        scrollbar-width: thin;
        /* For Firefox */
        scrollbar-color: #bec7e3 #edf2f7;
        /* Thumb color and track color for Firefox */
    }

    /* WebKit browsers (Chrome, Safari, etc.) */
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
        /* Width of the scrollbar */
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #bec7e3;
        /* Track color */
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #4fd1c5;
        /* Thumb color */
        border-radius: 10px;
        /* Rounded edges */
        border: 2px solid #edf2f7;
        /* Adds a little padding around the thumb */
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background-color: #000;
        /* Thumb color on hover */
    }

    .mt07 {
        margin-top: 0.7rem !important;
    }

    @media screen and (min-width: 1024px) {
        .mt07 {
            margin-top: 0 !important;
        }

    }
    </style>


    <div class="p-3">
        <!-- Breadcrumbs -->
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="text-primary hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>Agents List</span>
            </li>
        </ul>
        <!-- ./Breadcrumbs -->

        <!-- Controls Section -->
        <div
            class="flex flex-col md:flex-row items-center justify-between p-3 bg-white shadow rounded-lg space-y-3 md:space-y-0">

            <!-- left side -->
            <div
                class="flex items-start md:items-center border border-gray-300 rounded-lg p-2 space-y-3 md:space-y-0 md:space-x-3">
                <!-- left side -->
                <div class="flex gap-2 mr-2">
                    <a class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                        href="#">
                        <span
                            class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Total
                            Agents</span>


                    </a>
                    <a class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-info-light dark:bg-gray-700"
                        href="#"><span id="totalAgents"></span>
                    </a>
                </div>

                <div class="flex gap-2 !mt-0">
                    <input type="file" id="excelFileInput" class="hidden" name="excelFile" accept=".xlsx, .xls">
                    <!-- export data -->
                    <div x-data="{ open: false }" x-cloak class="relative">
                        <a @mouseenter="open = true" @mouseleave="open = false"
                            class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                            href="#">
                            <svg class="w-5 h-5 mr-2 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path opacity="0.5" fill-rule="evenodd" clip-rule="evenodd"
                                    d="M3 14.25C3.41421 14.25 3.75 14.5858 3.75 15C3.75 16.4354 3.75159 17.4365 3.85315 18.1919C3.9518 18.9257 4.13225 19.3142 4.40901 19.591C4.68577 19.8678 5.07435 20.0482 5.80812 20.1469C6.56347 20.2484 7.56459 20.25 9 20.25H15C16.4354 20.25 17.4365 20.2484 18.1919 20.1469C18.9257 20.0482 19.3142 19.8678 19.591 19.591C19.8678 19.3142 20.0482 18.9257 20.1469 18.1919C20.2484 17.4365 20.25 16.4354 20.25 15C20.25 14.5858 20.5858 14.25 21 14.25C21.4142 14.25 21.75 14.5858 21.75 15V15.0549C21.75 16.4225 21.75 17.5248 21.6335 18.3918C21.5125 19.2919 21.2536 20.0497 20.6517 20.6516C20.0497 21.2536 19.2919 21.5125 18.3918 21.6335C17.5248 21.75 16.4225 21.75 15.0549 21.75H8.94513C7.57754 21.75 6.47522 21.75 5.60825 21.6335C4.70814 21.5125 3.95027 21.2536 3.34835 20.6517C2.74643 20.0497 2.48754 19.2919 2.36652 18.3918C2.24996 17.5248 2.24998 16.4225 2.25 15.0549C2.25 15.0366 2.25 15.0183 2.25 15C2.25 14.5858 2.58579 14.25 3 14.25Z"
                                    fill="currentColor" />
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M12 2.25C12.2106 2.25 12.4114 2.33852 12.5535 2.49392L16.5535 6.86892C16.833 7.17462 16.8118 7.64902 16.5061 7.92852C16.2004 8.20802 15.726 8.18678 15.4465 7.88108L12.75 4.9318V16C12.75 16.4142 12.4142 16.75 12 16.75C11.5858 16.75 11.25 16.4142 11.25 16V4.9318L8.55353 7.88108C8.27403 8.18678 7.79963 8.20802 7.49393 7.92852C7.18823 7.64902 7.16698 7.17462 7.44648 6.86892L11.4465 2.49392C11.5886 2.33852 11.7894 2.25 12 2.25Z"
                                    fill="currentColor" />
                            </svg>
                            <span
                                class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Export</span>
                            <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                        <!-- Dropdown Menu -->
                        <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                            class="absolute z-10 mt-2 w-32 bg-black text-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95">
                            <div class="py-1">
                                <!-- export csv  -->

                                <a onclick="window.location='{{ route('agents.exportCsv') }}'"
                                    class="block px-4 py-2 text-sm bg-black text-white hover:bg-gray-800">CSV</a>
                                <a id="printPage" onclick="printPage()"
                                    class="block px-4 py-2 text-sm bg-black text-white hover:bg-gray-800">PRINT</a>
                            </div>
                        </div>
                    </div>

                    <!-- Excel -->
                    <div x-data="{ open: false }" x-cloak class="relative">
                        <a @mouseenter="open = true" @mouseleave="open = false"
                            class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                            href="#">

                            <svg class="w-5 h-5 mr-2 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path opacity="0.5"
                                    d="M22 14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22"
                                    stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M10 22C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14"
                                    stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path opacity="0.5"
                                    d="M10 2C6.22876 2 4.34315 2 3.17157 3.17157C2 4.34315 2 6.22876 2 10"
                                    stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M14 2C17.7712 2 19.6569 2 20.8284 3.17157C22 4.34315 22 6.22876 22 10"
                                    stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                            </svg>

                            <span
                                class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Excel</span>
                            <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                        <!-- Dropdown Menu -->
                        <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                            class="absolute z-10 mt-2 w-32 bg-black text-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95">
                            <div class="py-1">
                                <!-- export csv  -->

                                <a href="{{ route('download.agent') }}"
                                    class="block px-4 py-2 text-sm bg-black text-white hover:bg-gray-800">Get
                                    Template</a>
                                <a id="uploadExcelBtn"
                                    class="block px-4 py-2 text-sm bg-black text-white hover:bg-gray-800">Upload
                                    Excel</a>
                            </div>
                        </div>
                    </div>
                    <!-- Loading Spinner -->
                    <div id="loadingSpinner" class="hidden mt-4 flex justify-center items-center">
                        <span class="mr-2">Uploading...</span>
                        <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                            </path>
                        </svg>
                    </div>

                    <!-- Status Message -->
                    <div id="statusMessage" class="hidden mt-4"></div>
                </div>
            </div>


            <!-- right side -->
            <div class="flex items-center gap-3 space-y-3 md:space-y-0 md:space-x-2">
                <!-- Search Box -->
                <div class="mt07 relative flex items-center h-12">
                    <input id="searchInput" type="text" placeholder="Search"
                        class="w-full h-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm">
                    <svg class="w-5 h-5 text-gray-500 absolute left-3 top-1/2 transform -translate-y-1/2"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M9.5 17A7.5 7.5 0 109.5 2a7.5 7.5 0 000 15z" />
                    </svg>
                </div>

                <!-- Filter Agent Type Dropdown -->
                <div class="relative flex items-center h-12" x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                        class="h-full px-4 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-100 focus:outline-none flex items-center">
                        Filters by type
                    </button>
                    <div x-show="open" x-cloak
                        class="z-10 absolute top-full left-0 mt-2 w-44 bg-black text-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                        <div class="py-1">
                            <button @click="filterAgentType('')"
                                class="block w-full text-left px-4 py-2 text-sm bg-black text-white hover:bg-gray-800">All
                                Types</button>
                            <button @click="filterAgentType('staff')"
                                class="block w-full text-left px-4 py-2 text-sm bg-black text-white hover:bg-gray-800">Staff</button>
                            <button @click="filterAgentType('commission')"
                                class="block w-full text-left px-4 py-2 text-sm bg-black text-white hover:bg-gray-800">Commission</button>
                        </div>
                    </div>
                </div>


                <!-- Add User Button -->
                <a href="{{ route('agentsnew.new') }}" class="h-12">
                    <button type="button"
                        class="h-full flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 focus:outline-none">
                        <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add agent
                    </button>
                </a>
            </div>



        </div>
        <!-- ./Controls Section -->


        <!-- Table Section -->
        <div class="mt-5 overflow-x-auto bg-white shadow rounded-lg">
            <div class="max-h-96 overflow-y-auto custom-scrollbar">
                <table class="AgentTable CityMobileTable w-full">
                    <thead class="sticky top-0">
                        <tr>
                            <th class="px-4 py-2">
                                <svg id="selectAllSVG" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M8.0374 14.1437C7.78266 14.2711 7.47314 14.1602 7.35714 13.9001L3.16447 4.49844C2.49741 3.00261 3.97865 1.45104 5.36641 2.19197L11.2701 5.344C11.7293 5.58915 12.2697 5.58915 12.7289 5.344L18.6326 2.19197C20.0204 1.45104 21.5016 3.00261 20.8346 4.49844L19.2629 8.02275C19.0743 8.44563 18.7448 8.78997 18.3307 8.99704L8.0374 14.1437Z"
                                        fill="#1C274C" />
                                    <path opacity="0.5"
                                        d="M8.6095 15.5342C8.37019 15.6538 8.26749 15.9407 8.37646 16.185L10.5271 21.0076C11.1174 22.3314 12.8818 22.3314 13.4722 21.0076L17.4401 12.1099C17.6313 11.6812 17.1797 11.2491 16.7598 11.459L8.6095 15.5342Z"
                                        fill="#1C274C" />
                                </svg>

                                <input type="checkbox" id="selectAll" class="form-checkbox CheckBoxColor hidden">
                            </th>
                            <th class="flex px-4 py-2 cursor-pointer" id="nameHeader">

                                <svg id="sortIcon" class="mr-1 w-5 w-5" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M13 7L3 7" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M10 12H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M8 17H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                    <path
                                        d="M11.3161 16.6922C11.1461 17.07 11.3145 17.514 11.6922 17.6839C12.07 17.8539 12.514 17.6855 12.6839 17.3078L11.3161 16.6922ZM16.5 7L17.1839 6.69223C17.0628 6.42309 16.7951 6.25 16.5 6.25C16.2049 6.25 15.9372 6.42309 15.8161 6.69223L16.5 7ZM20.3161 17.3078C20.486 17.6855 20.93 17.8539 21.3078 17.6839C21.6855 17.514 21.8539 17.07 21.6839 16.6922L20.3161 17.3078ZM19.3636 13.3636L20.0476 13.0559L19.3636 13.3636ZM13.6364 12.6136C13.2222 12.6136 12.8864 12.9494 12.8864 13.3636C12.8864 13.7779 13.2222 14.1136 13.6364 14.1136V12.6136ZM12.6839 17.3078L17.1839 7.30777L15.8161 6.69223L11.3161 16.6922L12.6839 17.3078ZM21.6839 16.6922L20.0476 13.0559L18.6797 13.6714L20.3161 17.3078L21.6839 16.6922ZM20.0476 13.0559L17.1839 6.69223L15.8161 7.30777L18.6797 13.6714L20.0476 13.0559ZM19.3636 12.6136H13.6364V14.1136H19.3636V12.6136Z"
                                        fill="#1C274C" />
                                </svg>
                                <span>Name</span>
                            </th>
                            <th class="px-4 py-2">Amadeus (ID)</th>
                            <th class="px-4 py-2">Phone</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agents as $agent)
                        <tr>
                            <td class="px-4 py-2">
                                <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox">
                            </td>
                            <td class="px-4 py-2">{{ $agent->name }}</td>
                            <td class="px-4 py-2">Need To be Added In DB</td>
                            <td class="px-4 py-2">{{ $agent->phone_number }}</td>
                            <td class="px-4 py-2">
                                <span
                                    class="border rounded px-2 py-1 {{ $agent->type == 'staff' ? 'border-teal-600 text-teal-600' : ($agent->type == 'commission' ? 'border-sky-600 text-sky-600' : '') }}">
                                    {{ $agent->type }}
                                </span>
                            </td>
                            <td class="px-4 py-2 flex gap-2">
                                <a href="{{ route('agentsshow.show', $agent->id) }}}">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M9.75 12C9.75 10.7574 10.7574 9.75 12 9.75C13.2426 9.75 14.25 10.7574 14.25 12C14.25 13.2426 13.2426 14.25 12 14.25C10.7574 14.25 9.75 13.2426 9.75 12Z"
                                            fill="#1C274C" />
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M2 12C2 13.6394 2.42496 14.1915 3.27489 15.2957C4.97196 17.5004 7.81811 20 12 20C16.1819 20 19.028 17.5004 20.7251 15.2957C21.575 14.1915 22 13.6394 22 12C22 10.3606 21.575 9.80853 20.7251 8.70433C19.028 6.49956 16.1819 4 12 4C7.81811 4 4.97196 6.49956 3.27489 8.70433C2.42496 9.80853 2 10.3606 2 12ZM12 8.25C9.92893 8.25 8.25 9.92893 8.25 12C8.25 14.0711 9.92893 15.75 12 15.75C14.0711 15.75 15.75 14.0711 15.75 12C15.75 9.92893 14.0711 8.25 12 8.25Z"
                                            fill="#1C274C" />
                                    </svg>
                                </a>
                                <a href="{{ route('agents.edit', $agent->id) }}">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M14.2788 2.15224C13.9085 2 13.439 2 12.5 2C11.561 2 11.0915 2 10.7212 2.15224C10.2274 2.35523 9.83509 2.74458 9.63056 3.23463C9.53719 3.45834 9.50065 3.7185 9.48635 4.09799C9.46534 4.65568 9.17716 5.17189 8.69017 5.45093C8.20318 5.72996 7.60864 5.71954 7.11149 5.45876C6.77318 5.2813 6.52789 5.18262 6.28599 5.15102C5.75609 5.08178 5.22018 5.22429 4.79616 5.5472C4.47814 5.78938 4.24339 6.1929 3.7739 6.99993C3.30441 7.80697 3.06967 8.21048 3.01735 8.60491C2.94758 9.1308 3.09118 9.66266 3.41655 10.0835C3.56506 10.2756 3.77377 10.437 4.0977 10.639C4.57391 10.936 4.88032 11.4419 4.88029 12C4.88026 12.5581 4.57386 13.0639 4.0977 13.3608C3.77372 13.5629 3.56497 13.7244 3.41645 13.9165C3.09108 14.3373 2.94749 14.8691 3.01725 15.395C3.06957 15.7894 3.30432 16.193 3.7738 17C4.24329 17.807 4.47804 18.2106 4.79606 18.4527C5.22008 18.7756 5.75599 18.9181 6.28589 18.8489C6.52778 18.8173 6.77305 18.7186 7.11133 18.5412C7.60852 18.2804 8.2031 18.27 8.69012 18.549C9.17714 18.8281 9.46533 19.3443 9.48635 19.9021C9.50065 20.2815 9.53719 20.5417 9.63056 20.7654C9.83509 21.2554 10.2274 21.6448 10.7212 21.8478C11.0915 22 11.561 22 12.5 22C13.439 22 13.9085 22 14.2788 21.8478C14.7726 21.6448 15.1649 21.2554 15.3694 20.7654C15.4628 20.5417 15.4994 20.2815 15.5137 19.902C15.5347 19.3443 15.8228 18.8281 16.3098 18.549C16.7968 18.2699 17.3914 18.2804 17.8886 18.5412C18.2269 18.7186 18.4721 18.8172 18.714 18.8488C19.2439 18.9181 19.7798 18.7756 20.2038 18.4527C20.5219 18.2105 20.7566 17.807 21.2261 16.9999C21.6956 16.1929 21.9303 15.7894 21.9827 15.395C22.0524 14.8691 21.9088 14.3372 21.5835 13.9164C21.4349 13.7243 21.2262 13.5628 20.9022 13.3608C20.4261 13.0639 20.1197 12.558 20.1197 11.9999C20.1197 11.4418 20.4261 10.9361 20.9022 10.6392C21.2263 10.4371 21.435 10.2757 21.5836 10.0835C21.9089 9.66273 22.0525 9.13087 21.9828 8.60497C21.9304 8.21055 21.6957 7.80703 21.2262 7C20.7567 6.19297 20.522 5.78945 20.2039 5.54727C19.7799 5.22436 19.244 5.08185 18.7141 5.15109C18.4722 5.18269 18.2269 5.28136 17.8887 5.4588C17.3915 5.71959 16.7969 5.73002 16.3099 5.45096C15.8229 5.17191 15.5347 4.65566 15.5136 4.09794C15.4993 3.71848 15.4628 3.45833 15.3694 3.23463C15.1649 2.74458 14.7726 2.35523 14.2788 2.15224ZM12.5 15C14.1695 15 15.5228 13.6569 15.5228 12C15.5228 10.3431 14.1695 9 12.5 9C10.8305 9 9.47716 10.3431 9.47716 12C9.47716 13.6569 10.8305 15 12.5 15Z"
                                            fill="#1C274C" />
                                    </svg>

                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ./Table Section -->



    <div class="h-24"></div>
    <script>
    // BSZ95 New code
    document.addEventListener("DOMContentLoaded", function() {
        // Access the data passed from the controller
        const AgentsData = @json($AgentsData);
        document.getElementById("totalAgents").innerText = AgentsData.agentsCount;
    });

    document.addEventListener("DOMContentLoaded", function() {
        const selectAllSVG = document.getElementById("selectAllSVG");
        const rowCheckboxes = document.querySelectorAll(".rowCheckbox");

        // Toggle "Select All" functionality with the SVG
        selectAllSVG.addEventListener("click", function() {
            const allChecked = Array.from(rowCheckboxes).every(checkbox => checkbox.checked);
            rowCheckboxes.forEach(function(checkbox) {
                checkbox.checked = !allChecked;
            });
        });

        // Optional: Update the SVG color or style if all checkboxes are selected/deselected
        rowCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener("change", function() {
                const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                if (allChecked) {
                    selectAllSVG.style.fill = "#4fd1c5"; // Example color when all are selected
                } else {
                    selectAllSVG.style.fill = "#1C274C"; // Reset to original color
                }
            });
        });
    });

    // search functionality
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById("searchInput");
        const tableRows = document.querySelectorAll(".CityMobileTable tbody tr");

        searchInput.addEventListener("input", function() {
            const query = searchInput.value.toLowerCase();

            tableRows.forEach(row => {
                const cells = row.querySelectorAll("td");
                let rowContainsQuery = false;

                cells.forEach(cell => {
                    if (cell.innerText.toLowerCase().includes(query)) {
                        rowContainsQuery = true;
                    }
                });

                if (rowContainsQuery) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });
    });

    // Sorting functionality

    document.addEventListener("DOMContentLoaded", function() {
        const nameHeader = document.getElementById("nameHeader");
        const tableBody = document.querySelector(".AgentTable tbody");
        let sortAscending = true;

        nameHeader.addEventListener("click", function() {
            const rows = Array.from(tableBody.querySelectorAll("tr"));

            rows.sort((a, b) => {
                const nameA = a.querySelector("td:nth-child(2)").innerText.toLowerCase();
                const nameB = b.querySelector("td:nth-child(2)").innerText.toLowerCase();

                if (nameA < nameB) {
                    return sortAscending ? -1 : 1;
                } else if (nameA > nameB) {
                    return sortAscending ? 1 : -1;
                } else {
                    return 0;
                }
            });

            // Append the sorted rows back to the table body
            rows.forEach(row => tableBody.appendChild(row));

            // Toggle the sort order for next click
            sortAscending = !sortAscending;

            // Update the sort icon
            document.getElementById("sortIcon").innerText = sortAscending ? "⬆" : "⬇";
        });
    });



    // filter functionality
    document.addEventListener("DOMContentLoaded", function() {
        let currentFilter = "";

        // Function to filter the agents by type
        window.filterAgentType = function(agentType) {
            const tableRows = document.querySelectorAll(".CityMobileTable tbody tr");

            // If the same filter is clicked again, remove the filter
            if (currentFilter === agentType) {
                currentFilter = "";
                agentType = ""; // Reset the filter to "All Types"
            } else {
                currentFilter = agentType;
            }

            // Filter rows based on the selected agent type
            tableRows.forEach(row => {
                const typeCell = row.querySelector("td:nth-child(5) span");
                if (agentType === "" || (typeCell && typeCell.textContent.trim().toLowerCase() ===
                        agentType.toLowerCase())) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        };
    });





    // BSZ95 New code ./









    // Upload Excel functionality
    document.getElementById('uploadExcelBtn').addEventListener('click', function(event) {
        event.preventDefault();
        document.getElementById('excelFileInput').click(); // Trigger the file input click
    });

    // When a file is selected, submit via AJAX (or other method)
    document.getElementById('excelFileInput').addEventListener('change', function() {
        let file = this.files[0];
        if (file) {
            let formData = new FormData();
            formData.append('excel_file', file);

            // Show the loading spinner
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('statusMessage').classList.add('hidden'); // Hide previous messages


            // Use fetch or Axios to send the file via AJAX to the backend
            fetch("{{ route('agentsupload.import') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}", // Include CSRF token for security
                    },
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    // Hide the loading spinner
                    document.getElementById('loadingSpinner').classList.add('hidden');

                    // Show success message
                    document.getElementById('statusMessage').classList.remove('hidden');
                    document.getElementById('statusMessage').innerHTML =
                        `<p class="text-green-600">File uploaded successfully!</p>`;

                    alert('File uploaded successfully!');

                    // Refresh the page after a short delay
                    setTimeout(() => {
                        location.reload();
                    }, 1000); // Adjust the delay as needed (2000 ms = 2 seconds)

                })
                .catch(error => {

                    // Hide the loading spinner
                    document.getElementById('loadingSpinner').classList.add('hidden');

                    // Show error message
                    document.getElementById('statusMessage').classList.remove('hidden');
                    document.getElementById('statusMessage').innerHTML =
                        `<p class="text-red-600">Error uploading file: ${error.message}</p>`;

                    console.error('Error uploading file:', error);

                    // Refresh the page after a short delay
                    setTimeout(() => {
                        location.reload();
                    }, 1000); // Adjust the delay as needed (2000 ms = 2 seconds)
                });
        }
    });


    function printPage() {
        // Show the printable area temporarily
        var printableArea = document.getElementById('printableArea');
        printableArea.classList.remove('hidden');

        // Open a new window for printing
        var printWindow = window.open('', '_blank');

        // Get the content you want to print
        var content = printableArea.innerHTML;

        // Create the new document and write the content to it
        printWindow.document.write(`
        <html>
            <head>
                <title>Print</title>
                <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
            </head>
            <body>
                <div class="p-4">
                    ${content}
                </div>
            </body>
        </html>
    `);

        // Close the document for printing
        printWindow.document.close();

        // Wait for the content to be fully loaded
        printWindow.onload = function() {
            printWindow.print();
            printWindow.close();
        };

        // Hide the printable area again after the printing
        printableArea.classList.add('hidden');
    }
    </script>


</x-app-layout>