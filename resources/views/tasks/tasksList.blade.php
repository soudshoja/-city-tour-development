<x-app-layout>

    <div class="p-3">
        <!-- Breadcrumbs -->
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>Tasks List</span>
            </li>
        </ul>
        <!-- ./Breadcrumbs -->
        <!-- Controls Section -->
        <div
            class="flex flex-col md:flex-row items-center justify-between p-3 bg-white dark:bg-gray-800 shadow rounded-lg space-y-3 md:space-y-0 text-gray-700 dark:text-gray-300">

            <!-- left side -->
            <div
                class="flex items-start md:items-center border border-gray-300 rounded-lg p-2 space-y-3 md:space-y-0 md:space-x-3">
                <!-- left side -->
                <div class="flex gap-2 mr-2">

                    <a
                        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                        <span
                            class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Total
                            Tasks </span>


                    </a>
                    <a
                        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-info-light dark:bg-gray-700"><span
                            id="TasksData"></span>
                    </a>
                </div>


            </div>


            <!-- right side -->
            <div class="flex items-center gap-3 space-y-3 md:space-y-0 md:space-x-2">
                <!-- Search Box -->
                <div class="mt07 relative flex items-center h-12">
                    <input id="searchInput" type="text" placeholder="Search"
                        class="w-full h-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-300">
                    <svg class="w-5 h-5 text-gray-500 absolute left-3 top-1/2 transform -translate-y-1/2"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M9.5 17A7.5 7.5 0 109.5 2a7.5 7.5 0 000 15z" />
                    </svg>
                </div>



                <!-- Add Task Button -->

                <div x-data="{ open: false }" x-cloak class="relative">
                    <a @mouseenter="open = true" @mouseleave="open = false"
                        class="h-full flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none">
                        <svg class="w-5 h-5 mr-2 text-white dark:text-gray-300" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="text-white dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Add
                            Task</span>

                    </a>
                    <!-- Dropdown Menu -->
                    <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                        class="absolute z-10 mt-2 w-32 bg-black text-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                        <div class="py-1">


                            <a class="block px-4 py-2 text-sm bg-black text-white hover:bg-gray-800">Hotels</a>
                            <a class="block px-4 py-2 text-sm bg-black text-white hover:bg-gray-800">Tickets</a>
                        </div>
                    </div>
                </div>


            </div>



        </div>
        <!-- ./Controls Section -->


        <!-- Table Section -->
        <div class="mt-5 overflow-x-auto bg-white shadow rounded-lg">
            <div class="max-h-96 overflow-y-auto custom-scrollbar">
                <table class="AgentTable CityMobileTable w-full">
                    <thead class="sticky top-0">
                        <tr>
                            <!-- select all icon -->
                            <th class="px-4 py-2">
                                <svg id="selectAllSVG" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg" class="dark:fill-white">
                                    <path
                                        d="M8.0374 14.1437C7.78266 14.2711 7.47314 14.1602 7.35714 13.9001L3.16447 4.49844C2.49741 3.00261 3.97865 1.45104 5.36641 2.19197L11.2701 5.344C11.7293 5.58915 12.2697 5.58915 12.7289 5.344L18.6326 2.19197C20.0204 1.45104 21.5016 3.00261 20.8346 4.49844L19.2629 8.02275C19.0743 8.44563 18.7448 8.78997 18.3307 8.99704L8.0374 14.1437Z"
                                        fill="#1C274C" class="dark:fill-white" />
                                    <path opacity="0.5"
                                        d="M8.6095 15.5342C8.37019 15.6538 8.26749 15.9407 8.37646 16.185L10.5271 21.0076C11.1174 22.3314 12.8818 22.3314 13.4722 21.0076L17.4401 12.1099C17.6313 11.6812 17.1797 11.2491 16.7598 11.459L8.6095 15.5342Z"
                                        fill="#1C274C" class="dark:fill-gray-400" />
                                </svg>

                                <input type="checkbox" id="selectAll" class="form-checkbox CheckBoxColor hidden">
                            </th>
                            <!-- Table Headers: Tasks Name and Agent Name -->
                            <th class="px-4 py-2 cursor-pointer" id="tasksNameHeader">
                                <div class="inline-flex items-center">
                                    <svg id="sortIcon" class="mr-1 w-5 h-5" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path d="M13 7L3 7" stroke="#1C274C" stroke-width="1.5"
                                            stroke-linecap="round" />
                                        <path d="M10 12H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="M8 17H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                        <path
                                            d="M11.3161 16.6922C11.1461 17.07 11.3145 17.514 11.6922 17.6839C12.07 17.8539 12.514 17.6855 12.6839 17.3078L11.3161 16.6922ZM16.5 7L17.1839 6.69223C17.0628 6.42309 16.7951 6.25 16.5 6.25C16.2049 6.25 15.9372 6.42309 15.8161 6.69223L16.5 7ZM20.3161 17.3078C20.486 17.6855 20.93 17.8539 21.3078 17.6839C21.6855 17.514 21.8539 17.07 21.6839 16.6922L20.3161 17.3078ZM19.3636 13.3636L20.0476 13.0559L19.3636 13.3636ZM13.6364 12.6136C13.2222 12.6136 12.8864 12.9494 12.8864 13.3636C12.8864 13.7779 13.2222 14.1136 13.6364 14.1136V12.6136ZM12.6839 17.3078L17.1839 7.30777L15.8161 6.69223L11.3161 16.6922L12.6839 17.3078ZM21.6839 16.6922L20.0476 13.0559L18.6797 13.6714L20.3161 17.3078L21.6839 16.6922ZM20.0476 13.0559L17.1839 6.69223L15.8161 7.30777L18.6797 13.6714L20.0476 13.0559ZM19.3636 12.6136H13.6364V14.1136H19.3636V12.6136Z"
                                            fill="#1C274C" />
                                    </svg>
                                    <span>Tasks Name</span>
                                </div>
                            </th>

                            <th class="px-4 py-2 cursor-pointer" id="agentNameHeader">
                                <div class="inline-flex items-center">
                                    <svg id="sortIcon" class="mr-1 w-5 h-5" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path d="M13 7L3 7" stroke="#1C274C" stroke-width="1.5"
                                            stroke-linecap="round" />
                                        <path d="M10 12H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="M8 17H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                        <path
                                            d="M11.3161 16.6922C11.1461 17.07 11.3145 17.514 11.6922 17.6839C12.07 17.8539 12.514 17.6855 12.6839 17.3078L11.3161 16.6922ZM16.5 7L17.1839 6.69223C17.0628 6.42309 16.7951 6.25 16.5 6.25C16.2049 6.25 15.9372 6.42309 15.8161 6.69223L16.5 7ZM20.3161 17.3078C20.486 17.6855 20.93 17.8539 21.3078 17.6839C21.6855 17.514 21.8539 17.07 21.6839 16.6922L20.3161 17.3078ZM19.3636 13.3636L20.0476 13.0559L19.3636 13.3636ZM13.6364 12.6136C13.2222 12.6136 12.8864 12.9494 12.8864 13.3636C12.8864 13.7779 13.2222 14.1136 13.6364 14.1136V12.6136ZM12.6839 17.3078L17.1839 7.30777L15.8161 6.69223L11.3161 16.6922L12.6839 17.3078ZM21.6839 16.6922L20.0476 13.0559L18.6797 13.6714L20.3161 17.3078L21.6839 16.6922ZM20.0476 13.0559L17.1839 6.69223L15.8161 7.30777L18.6797 13.6714L20.0476 13.0559ZM19.3636 12.6136H13.6364V14.1136H19.3636V12.6136Z"
                                            fill="#1C274C" />
                                    </svg>
                                    <span>Agent Name</span>
                                </div>
                            </th>

                            <th class="px-4 py-2">Client Name</th>
                            <th class="px-4 py-2">Actions</th>


                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-300">
                        @foreach($tasks as $task)
                        @php
                        // Calculate the delay in rounded days
                        $delay = round(\Carbon\Carbon::parse($task->created_at)->diffInDays(now()));
                        @endphp
                        <tr>
                            <td class="px-4 py-2">
                                <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox">
                            </td>
                            <td class="px-4 py-2">{{ $task->description }}</td>
                            <td class="px-4 py-2">{{ $task->agent->name }}</td>
                            <td class="px-4 py-2">{{ $task->client->name ?? 'No client' }}</td>
                            <td class="px-4 py-2">
                                <a onclick="ShowTask()">
                                    <svg class="dark:fill-white" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M9.75 12C9.75 10.7574 10.7574 9.75 12 9.75C13.2426 9.75 14.25 10.7574 14.25 12C14.25 13.2426 13.2426 14.25 12 14.25C10.7574 14.25 9.75 13.2426 9.75 12Z"
                                            fill="currentColor" />
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M2 12C2 13.6394 2.42496 14.1915 3.27489 15.2957C4.97196 17.5004 7.81811 20 12 20C16.1819 20 19.028 17.5004 20.7251 15.2957C21.575 14.1915 22 13.6394 22 12C22 10.3606 21.575 9.80853 20.7251 8.70433C19.028 6.49956 16.1819 4 12 4C7.81811 4 4.97196 6.49956 3.27489 8.70433C2.42496 9.80853 2 10.3606 2 12ZM12 8.25C9.92893 8.25 8.25 9.92893 8.25 12C8.25 14.0711 9.92893 15.75 12 15.75C14.0711 15.75 15.75 14.0711 15.75 12C15.75 9.92893 14.0711 8.25 12 8.25Z"
                                            fill="currentColor" />
                                    </svg>
                                </a>
                                <!-- Show Task modal -->
                                <div id="ShowTaskModal" onclick="closeModalIbgTask(event)"
                                    class="fixed z-10 inset-0 flex items-center justify-center backdrop-blur-sm hidden">
                                    <div class="bg-white rounded-lg shadow-lg max-w-md w-full relative">

                                        <div class="rounded-t-lg h-32 bg-cover bg-center"
                                            style="background-image: url('{{ asset('images/SingleTask.svg') }}');">
                                            <!-- Close Button (Top Right) -->
                                            <button onclick="closeShowTaskModal()"
                                                class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 rounded-lg bg-[#004B99]">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="2" stroke="#fff" class="w-5 h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="bg-gray-100 p-2">
                                            <h2
                                                class="text-xl font-semibold text-gray-800 dark:text-gray-200 text-center">
                                                Task Details
                                            </h2>
                                        </div>
                                        <div class="p-5">

                                            <div class="p-3 border border-gray-300 rounded-lg">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-lg">Task ID</p>
                                                    <h5 class="text-base">{{ $task->id }}</h5>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-lg">Client Name</p>
                                                    <h5 class="text-base">{{ $task->client->name ?? 'No client' }}</h5>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-lg">Description</p>
                                                    <h5 class="text-base">{{ $task->description }}</h5>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-lg">Assigned Agent</p>
                                                    <h5 class="text-base">{{ $task->agent->name }}</h5>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <!-- ./ View Task modal -->

                            </td>

                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div><!-- ./Table Section -->

    </div> <!-- ./p-3 -->






    <script>
    function ShowTask() {

        document.getElementById('ShowTaskModal').classList.remove('hidden');
    }

    function closeShowTaskModal() {
        // Hide the modal when "Cancel" is clicked
        document.getElementById('ShowTaskModal').classList.add('hidden');
    }

    function closeModalIbgTask(event) {
        // Close the modal if the user clicks outside of the modal content
        const modalContent = document.querySelector('#ShowTaskModal > div');
        if (!modalContent.contains(event.target)) {
            closeShowTaskModal();
        }
    }
















    document.addEventListener("DOMContentLoaded", function() {
        // Pass the task count from the controller to JavaScript
        const taskCount = @json($taskCount);

        // Display the task count in the span with ID "TasksData"
        document.getElementById("TasksData").innerText = taskCount;
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
    </script>









</x-app-layout>