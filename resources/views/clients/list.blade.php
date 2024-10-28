<x-app-layout>



    <style>
    .mt07 {
        margin-top: 0.7rem !important;
    }

    @media screen and (min-width: 1024px) {
        .mt07 {
            margin-top: 0 !important;
        }

    }
    </style>


    <div>
        <!-- Breadcrumbs -->
        <x-breadcrumbs :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Clients List']
]" />

        <!-- ./Breadcrumbs -->
        <!-- session status -->
        @if (session('success'))
        <div class="my-5 flex items-center rounded bg-success-light p-3.5 text-success dark:bg-success-dark-light">
            <span class="ltr:pr-2 rtl:pl-2"><strong class="ltr:mr-1 rtl:ml-1">Success!
                </strong>{{ session('success') }}</span>
            <button type="button" class="hover:opacity-80 ltr:ml-auto rtl:mr-auto">
                <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                    class="h-5 w-5">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        @endif
        <!-- ./session status -->
        <!-- Controls Section -->
        <div
            class="flex flex-col md:flex-row items-center justify-between p-3 bg-white dark:bg-gray-800 shadow rounded-lg space-y-3 md:space-y-0">

            <!-- left side -->
            <div
                class="flex items-start md:items-center border border-gray-300 rounded-lg p-2 space-y-3 md:space-y-0 md:space-x-3">
                <!-- left side -->
                <div class="flex gap-2 mr-2">
                    <a
                        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                        <span
                            class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Total
                            clients</span>


                    </a>
                    <a
                        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-info-light dark:bg-gray-700"><span
                            id="totalClients"></span>
                    </a>
                </div>

            </div>


            <!-- right side -->
            <div class="flex items-center gap-3 space-y-3 md:space-y-0 md:space-x-2">
                <!-- Search Box -->
                <div class="mt07 relative flex items-center h-12">
                    <input id="searchInput" type="text" placeholder="Search"
                        class="w-full h-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M9.5 17A7.5 7.5 0 109.5 2a7.5 7.5 0 000 15z" />
                    </svg>
                </div>

                <!-- Add User Button -->
                <button type="button" onclick="addClient()"
                    class="h-full flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none">
                    <svg class="w-5 h-5 mr-2 text-white dark:text-gray-300" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Client
                </button>

                <!-- add  modal -->
                <div id="addClientModal" onclick="closeModalIbgC(event)"
                    class="fixed z-10 inset-0 flex items-center justify-center backdrop-blur-sm hidden">
                    <div class="bg-white rounded-lg shadow-lg max-w-xl w-full relative">

                        <div class="h-24 bg-black bg-cover bg-center"
                            style="background-image: url('{{ asset('images/registeruser.jpg') }}');">
                            <!-- Close Button (Top Right) -->
                            <button onclick="closeAddClientModal()"
                                class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="p-6">

                            <!-- Modal Title -->
                            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 text-center">Register
                                New
                                Client
                            </h2>

                            <!-- Modal Form -->
                            <!-- Registration Form -->
                            <form method="POST" action="{{ route('clients.store') }}">
                                @csrf

                                <!-- Name Field -->
                                <div class="mb-4">
                                    <label for="name"
                                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                                    <input id="name" name="name" type="text" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Client Name" />
                                </div>

                                <!-- Email and Phone Fields -->
                                <div class="mb-4 flex space-x-4">
                                    <!-- Email Field -->
                                    <div class="w-1/2">
                                        <label for="email"
                                            class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                                        <input id="email" name="email" type="email" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                            placeholder="Client Email" />
                                    </div>

                                    <!-- Phone Field -->
                                    <div class="w-1/2">
                                        <label for="phone"
                                            class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone</label>
                                        <input id="phone" name="phone" type="text" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                            placeholder="Client Phone" />
                                    </div>
                                </div>



                                <!-- Passport Field -->
                                <div class="mb-4">
                                    <label for="passport_no"
                                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Passport
                                        Number</label>
                                    <input id="passport_no" name="passport_no" type="text" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Passport Number" />
                                </div>

                                <!-- Email Field -->
                                <div class="mb-4">
                                    <label for="agent_email"
                                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Agent
                                        Email</label>
                                    <input id="agent_email" name="agent_email" type="email" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Agent Email" />
                                </div>


                                <!-- Submit Button -->
                                <div class="flex items-center justify-center">
                                    <x-primary-button class="px-8 text-center">
                                        {{ __('Register') }}
                                    </x-primary-button>
                                </div>
                            </form>
                            <!-- ./Registration Form -->
                        </div>
                    </div>
                </div>
                <!-- ./add  modal -->
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
                                    xmlns="http://www.w3.org/2000/svg" class="dark:fill-white">
                                    <path
                                        d="M8.0374 14.1437C7.78266 14.2711 7.47314 14.1602 7.35714 13.9001L3.16447 4.49844C2.49741 3.00261 3.97865 1.45104 5.36641 2.19197L11.2701 5.344C11.7293 5.58915 12.2697 5.58915 12.7289 5.344L18.6326 2.19197C20.0204 1.45104 21.5016 3.00261 20.8346 4.49844L19.2629 8.02275C19.0743 8.44563 18.7448 8.78997 18.3307 8.99704L8.0374 14.1437Z"
                                        fill="#1C274C" class="dark:fill-white" />
                                    <path opacity="0.5"
                                        d="M8.6095 15.5342C8.37019 15.6538 8.26749 15.9407 8.37646 16.185L10.5271 21.0076C11.1174 22.3314 12.8818 22.3314 13.4722 21.0076L17.4401 12.1099C17.6313 11.6812 17.1797 11.2491 16.7598 11.459L8.6095 15.5342Z"
                                        fill="#1C274C" class="dark:fill-white" />
                                </svg>

                                <input type="checkbox" id="selectAll" class="form-checkbox CheckBoxColor hidden">
                            </th>
                            <th class="flex px-4 py-2 cursor-pointer" id="nameHeader">

                                <svg id="sortIcon" class="mr-1 w-5 h-5" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M13 7L3 7" class="stroke-current text-dark-mode-color" stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path d="M10 12H3" class="stroke-current text-dark-mode-color" stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path d="M8 17H3" class="stroke-current text-dark-mode-color" stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path
                                        d="M11.3161 16.6922C11.1461 17.07 11.3145 17.514 11.6922 17.6839C12.07 17.8539 12.514 17.6855 12.6839 17.3078L11.3161 16.6922ZM16.5 7L17.1839 6.69223C17.0628 6.42309 16.7951 6.25 16.5 6.25C16.2049 6.25 15.9372 6.42309 15.8161 6.69223L16.5 7ZM20.3161 17.3078C20.486 17.6855 20.93 17.8539 21.3078 17.6839C21.6855 17.514 21.8539 17.07 21.6839 16.6922L20.3161 17.3078ZM19.3636 13.3636L20.0476 13.0559L19.3636 13.3636ZM13.6364 12.6136C13.2222 12.6136 12.8864 12.9494 12.8864 13.3636C12.8864 13.7779 13.2222 14.1136 13.6364 14.1136V12.6136ZM12.6839 17.3078L17.1839 7.30777L15.8161 6.69223L11.3161 16.6922L12.6839 17.3078ZM21.6839 16.6922L20.0476 13.0559L18.6797 13.6714L20.3161 17.3078L21.6839 16.6922ZM20.0476 13.0559L17.1839 6.69223L15.8161 7.30777L18.6797 13.6714L20.0476 13.0559ZM19.3636 12.6136H13.6364V14.1136H19.3636V12.6136Z"
                                        class="fill-current text-dark-mode-color" />
                                </svg>

                                <span>Name</span>
                            </th>
                            <th class="px-4 py-2">Email</th>
                            <th class="px-4 py-2">Contact</th>
                            <th class="px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-300">
                        @foreach($clients as $client)
                        <tr>
                            <td class="px-4 py-2">
                                <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox">
                            </td>
                            <td class="px-4 py-2">{{ $client->name }}</td>
                            <td class="px-4 py-2">{{ $client->email }}</td>
                            <td class="px-4 py-2">{{ $client->phone }}</td>


                            <td class="px-4 py-2 flex gap-2">
                                <a href="{{ route('clients.show', $client->id) }}">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="dark:fill-current dark:text-white">
                                        <path
                                            d="M9.75 12C9.75 10.7574 10.7574 9.75 12 9.75C13.2426 9.75 14.25 10.7574 14.25 12C14.25 13.2426 13.2426 14.25 12 14.25C10.7574 14.25 9.75 13.2426 9.75 12Z"
                                            fill="#1C274C" class="dark:fill-current dark:text-white" />
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M2 12C2 13.6394 2.42496 14.1915 3.27489 15.2957C4.97196 17.5004 7.81811 20 12 20C16.1819 20 19.028 17.5004 20.7251 15.2957C21.575 14.1915 22 13.6394 22 12C22 10.3606 21.575 9.80853 20.7251 8.70433C19.028 6.49956 16.1819 4 12 4C7.81811 4 4.97196 6.49956 3.27489 8.70433C2.42496 9.80853 2 10.3606 2 12ZM12 8.25C9.92893 8.25 8.25 9.92893 8.25 12C8.25 14.0711 9.92893 15.75 12 15.75C14.0711 15.75 15.75 14.0711 15.75 12C15.75 9.92893 14.0711 8.25 12 8.25Z"
                                            fill="#1C274C" class="dark:fill-current dark:text-white" />
                                    </svg>
                                </a>
                                <a href="{{ route('clients.edit', ['id' => $client->id]) }}">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="dark:fill-current dark:text-white">
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M14.2788 2.15224C13.9085 2 13.439 2 12.5 2C11.561 2 11.0915 2 10.7212 2.15224C10.2274 2.35523 9.83509 2.74458 9.63056 3.23463C9.53719 3.45834 9.50065 3.7185 9.48635 4.09799C9.46534 4.65568 9.17716 5.17189 8.69017 5.45093C8.20318 5.72996 7.60864 5.71954 7.11149 5.45876C6.77318 5.2813 6.52789 5.18262 6.28599 5.15102C5.75609 5.08178 5.22018 5.22429 4.79616 5.5472C4.47814 5.78938 4.24339 6.1929 3.7739 6.99993C3.30441 7.80697 3.06967 8.21048 3.01735 8.60491C2.94758 9.1308 3.09118 9.66266 3.41655 10.0835C3.56506 10.2756 3.77377 10.437 4.0977 10.639C4.57391 10.936 4.88032 11.4419 4.88029 12C4.88026 12.5581 4.57386 13.0639 4.0977 13.3608C3.77372 13.5629 3.56497 13.7244 3.41645 13.9165C3.09108 14.3373 2.94749 14.8691 3.01725 15.395C3.06957 15.7894 3.30432 16.193 3.7738 17C4.24329 17.807 4.47804 18.2106 4.79606 18.4527C5.22008 18.7756 5.75599 18.9181 6.28589 18.8489C6.52778 18.8173 6.77305 18.7186 7.11133 18.5412C7.60852 18.2804 8.2031 18.27 8.69012 18.549C9.17714 18.8281 9.46533 19.3443 9.48635 19.9021C9.50065 20.2815 9.53719 20.5417 9.63056 20.7654C9.83509 21.2554 10.2274 21.6448 10.7212 21.8478C11.0915 22 11.561 22 12.5 22C13.439 22 13.9085 22 14.2788 21.8478C14.7726 21.6448 15.1649 21.2554 15.3694 20.7654C15.4628 20.5417 15.4994 20.2815 15.5137 19.902C15.5347 19.3443 15.8228 18.8281 16.3098 18.549C16.7968 18.2699 17.3914 18.2804 17.8886 18.5412C18.2269 18.7186 18.4721 18.8172 18.714 18.8488C19.2439 18.9181 19.7798 18.7756 20.2038 18.4527C20.5219 18.2105 20.7566 17.807 21.2261 16.9999C21.6956 16.1929 21.9303 15.7894 21.9827 15.395C22.0524 14.8691 21.9088 14.3372 21.5835 13.9164C21.4349 13.7243 21.2262 13.5628 20.9022 13.3608C20.4261 13.0639 20.1197 12.558 20.1197 11.9999C20.1197 11.4418 20.4261 10.9361 20.9022 10.6392C21.2263 10.4371 21.435 10.2757 21.5836 10.0835C21.9089 9.66273 22.0525 9.13087 21.9828 8.60497C21.9304 8.21055 21.6957 7.80703 21.2262 7C20.7567 6.19297 20.522 5.78945 20.2039 5.54727C19.7799 5.22436 19.244 5.08185 18.7141 5.15109C18.4722 5.18269 18.2269 5.28136 17.8887 5.4588C17.3915 5.71959 16.7969 5.73002 16.3099 5.45096C15.8229 5.17191 15.5347 4.65566 15.5136 4.09794C15.4993 3.71848 15.4628 3.45833 15.3694 3.23463C15.1649 2.74458 14.7726 2.35523 14.2788 2.15224ZM12.5 15C14.1695 15 15.5228 13.6569 15.5228 12C15.5228 10.3431 14.1695 9 12.5 9C10.8305 9 9.47716 10.3431 9.47716 12C9.47716 13.6569 10.8305 15 12.5 15Z"
                                            class="fill-current text-dark-mode-color" />
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


    <script>
    // BSZ95 New code
    document.addEventListener("DOMContentLoaded", function() {
        // Access the data passed from the controller
        const clientsNo = @json($clientsNo);
        document.getElementById("totalClients").innerText = clientsNo;
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



    <script>
    function addClient() {

        document.getElementById('addClientModal').classList.remove('hidden');
    }

    function closeAddClientModal() {
        // Hide the modal when "Cancel" is clicked
        document.getElementById('addClientModal').classList.add('hidden');
    }

    function closeModalIbgC(event) {
        // Close the modal if the user clicks outside of the modal content
        const modalContent = document.querySelector('#addClientModal > div');
        if (!modalContent.contains(event.target)) {
            closeAddClientModal();
        }
    }
    </script>

</x-app-layout>