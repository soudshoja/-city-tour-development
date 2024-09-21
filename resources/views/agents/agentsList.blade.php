<x-app-layout>
<div x-data="exportTable">
        <ul class="flex space-x-2 rtl:space-x-reverse">
            <li>
                <a href="{{ route('dashboard') }}" class="text-primary hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-1 rtl:before:ml-1">
                <span>Companies List</span>
            </li>
        </ul>
        <div class="panel mt-6">

            <div class="mb-5 flex justify-between items-center w-full">
                <!-- Buttons on the left -->
                <div class="flex space-x-2">
                <button class="btn btn-primary flex items-center">
                    Upload Excel
                    <svg class="ml-2" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L15 5H19V19H5V5H9L12 2Z" fill="white" />
                        <path d="M15 11H9V13H15V11Z" fill="white" />
                        <path d="M19 1H5C3.9 1 3 1.9 3 3V21C3 22.1 3.9 23 5 23H19C20.1 23 21 22.1 21 21V3C21 1.9 20.1 1 19 1ZM19 21H5V3H19V21Z" fill="white" />
                    </svg>
                </button>
                    <button class="btn btn-primary flex items-center">
                        Export CSV
                        <svg class="ml-2" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M18.25 15C18.25 15.4142 18.5858 15.75 19 15.75C19.4142 15.75 19.75 15.4142 19.75 15H18.25ZM11 2.75H13V1.25H11V2.75ZM13 21.25H11V22.75H13V21.25ZM5.75 16V8H4.25V16H5.75ZM11 21.25C9.56458 21.25 8.56347 21.2484 7.80812 21.1469C7.07434 21.0482 6.68577 20.8678 6.40901 20.591L5.34835 21.6517C5.95027 22.2536 6.70814 22.5125 7.60825 22.6335C8.48678 22.7516 9.60699 22.75 11 22.75V21.25ZM4.25 16C4.25 17.393 4.24841 18.5132 4.36652 19.3918C4.48754 20.2919 4.74643 21.0497 5.34835 21.6517L6.40901 20.591C6.13225 20.3142 5.9518 19.9257 5.85315 19.1919C5.75159 18.4365 5.75 17.4354 5.75 16H4.25ZM13 22.75C14.393 22.75 15.5132 22.7516 16.3918 22.6335C17.2919 22.5125 18.0497 22.2536 18.6517 21.6517L17.591 20.591C17.3142 20.8678 16.9257 21.0482 16.1919 21.1469C15.4365 21.2484 14.4354 21.25 13 21.25V22.75ZM13 2.75C14.4354 2.75 15.4365 2.75159 16.1919 2.85315C16.9257 2.9518 17.3142 3.13225 17.591 3.40901L18.6517 2.34835C18.0497 1.74643 17.2919 1.48754 16.3918 1.36652C15.5132 1.24841 14.393 1.25 13 1.25V2.75ZM19.75 8C19.75 6.60699 19.7516 5.48678 19.6335 4.60825C19.5125 3.70814 19.2536 2.95027 18.6517 2.34835L17.591 3.40901C17.8678 3.68577 18.0482 4.07434 18.1469 4.80812C18.2484 5.56347 18.25 6.56458 18.25 8H19.75ZM11 1.25C9.60699 1.25 8.48678 1.24841 7.60825 1.36652C6.70814 1.48754 5.95027 1.74643 5.34835 2.34835L6.40901 3.40901C6.68577 3.13225 7.07434 2.9518 7.80812 2.85315C8.56347 2.75159 9.56458 2.75 11 2.75V1.25ZM5.75 8C5.75 6.56458 5.75159 5.56347 5.85315 4.80812C5.9518 4.07434 6.13225 3.68577 6.40901 3.40901L5.34835 2.34835C4.74643 2.95027 4.48754 3.70814 4.36652 4.60825C4.24841 5.48678 4.25 6.60699 4.25 8H5.75ZM18.1717 18.9835C18.0801 19.8548 17.8926 20.2894 17.591 20.591L18.6517 21.6517C19.309 20.9944 19.5571 20.1512 19.6635 19.1404L18.1717 18.9835ZM18.25 8V15H19.75V8H18.25Z"
                                fill="white" />
                            <path
                                d="M19 19.5C19.4645 19.5 19.6968 19.5 19.8911 19.4692C20.9608 19.2998 21.7998 18.4608 21.9692 17.3911C22 17.1968 22 16.9645 22 16.5V7.5C22 7.0355 22 6.80325 21.9692 6.60891C21.7998 5.53918 20.9608 4.70021 19.8911 4.53078C19.6968 4.5 19.4645 4.5 19 4.5"
                                stroke="white" stroke-width="1.5" />
                            <path
                                d="M5 19.5C4.5355 19.5 4.30325 19.5 4.10891 19.4692C3.03918 19.2998 2.20021 18.4608 2.03078 17.3911C2 17.1968 2 16.9645 2 16.5V7.5C2 7.0355 2 6.80325 2.03078 6.60891C2.20021 5.53918 3.03918 4.70021 4.10891 4.53078C4.30325 4.5 4.5355 4.5 5 4.5"
                                stroke="white" stroke-width="1.5" />
                            <circle cx="14.5" cy="6.5" r="1.5" stroke="white" stroke-width="1.5" />
                            <path
                                d="M5 14.8159L6.29064 13.4917C6.9621 12.8028 7.9741 12.8423 8.60499 13.5821L11.7658 17.2884C12.2722 17.8822 13.0693 17.9632 13.6552 17.4804L13.875 17.2993C14.7181 16.6045 15.8588 16.685 16.6248 17.4933L19 19.5"
                                stroke="white" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </button>

                    <button class="btn btn-primary flex items-center">
                        PRINT
                        <svg class="ml-2" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M22 12C22 14.8284 22 16.2426 21.1213 17.1213C20.48 17.7626 19.5535 17.9359 18 17.9827M6 17.9827C4.44655 17.9359 3.51998 17.7626 2.87868 17.1213C2 16.2426 2 14.8284 2 12C2 9.17157 2 7.75736 2.87868 6.87868C3.75736 6 5.17157 6 8 6H16C18.8284 6 20.2426 6 21.1213 6.87868C21.4211 7.17848 21.6186 7.54062 21.7487 8"
                                stroke="white" stroke-width="1.5" stroke-linecap="round" />
                            <path d="M9 10H6" stroke="white" stroke-width="1.5" stroke-linecap="round" />
                            <path d="M19 15L5 15" stroke="white" stroke-width="1.5" stroke-linecap="round" />
                            <path
                                d="M17.9827 6C17.9359 4.44655 17.7626 3.51998 17.1213 2.87868C16.2426 2 14.8284 2 12 2C9.17157 2 7.75736 2 6.87868 2.87868C6.23738 3.51998 6.06413 4.44655 6.01732 6M18 15V16C18 18.8284 18 20.2426 17.1213 21.1213C16.48 21.7626 15.5535 21.9359 14 21.9827M6 15V16C6 18.8284 6 20.2426 6.87868 21.1213C7.51998 21.7626 8.44655 21.9359 10 21.9827"
                                stroke="white" stroke-width="1.5" stroke-linecap="round" />
                            <circle cx="17" cy="10" r="1" fill="white" />
                        </svg>
                    </button>



                </div>

                <!-- Search input on the right -->
                <div>
                    <input type="text" placeholder="Search..."
                        class="w-full pr-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder:text-gray-500" />
                </div>
            </div>

            <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded-lg">
                    <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border-b">Name</th>
                                <th class="py-2 px-4 border-b">Email</th>
                                <th class="py-2 px-4 border-b">Phone Number</th>
                                <th class="py-2 px-4 border-b">Type</th>
                                <th class="py-2 px-4 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($agents as $agent)
                            <tr class="hover:bg-gray-100">
                                    <td class="py-2 px-4 border-b">{{ $agent->name }}</td>
                                    <td class="py-2 px-4 border-b">{{ $agent->email }}</td>
                                    <td class="py-2 px-4 border-b">{{ $agent->phone_number }}</td>
                                    <td class="py-2 px-4 border-b">{{ $agent->type }}</td>
                                    <td class="py-2 px-4 border-b">
                                        <a href="{{ route('agentsshow.show', $agent->id) }}" class="bg-blue-500 text-white py-1 px-2 rounded">Show</a>
                                        <a href="{{ route('tasks.index', $agent->id) }}" class="bg-green-500 text-white py-1 px-2 rounded">See Task</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-between items-center pt-3 bg-white border-t border-gray-300">

<div class="flex items-center space-x-2">
    <!-- Left: Showing entries text -->
    <div class="text-sm text-gray-600">
        Showing 1 to 10 of 25 entries
    </div>

    <select class="dataTable-selector custom-select">
        <option value="10" selected>10</option>
        <option value="20">20</option>
        <option value="50">50</option>
        <option value="100">100</option>
    </select>
</div>

<div class="flex items-center space-x-3">

    <!-- Pagination controls -->
    <nav class="dataTable-pagination">
        <ul class="dataTable-pagination-list">
            <li class="pager"><a href="#" data-page="1"><svg width="24" height="24" viewBox="0 0 24 24"
                        fill="none" xmlns="http://www.w3.org/2000/svg"
                        class="w-4.5 h-4.5 rtl:rotate-180">
                        <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round"></path>
                        <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg></a></li>
            <li class="active"><a href="#" data-page="1">1</a></li>
            <li class=""><a href="#" data-page="2">2</a></li>
            <li class=""><a href="#" data-page="3">3</a></li>
            <li class="pager"><a href="#" data-page="2"><svg width="24" height="24" viewBox="0 0 24 24"
                        fill="none" xmlns="http://www.w3.org/2000/svg"
                        class="w-4.5 h-4.5 rtl:rotate-180">
                        <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg></a></li>
            <li class="pager"><a href="#" data-page="3"><svg width="24" height="24" viewBox="0 0 24 24"
                        fill="none" xmlns="http://www.w3.org/2000/svg"
                        class="w-4.5 h-4.5 rtl:rotate-180">
                        <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round"></path>
                        <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg></a></li>
        </ul>
    </nav>

    </div>
</div>

 </div>
</div>

</x-app-layout>
