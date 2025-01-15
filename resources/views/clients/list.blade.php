<x-app-layout>



    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Clients List</h2>
            <!-- total client number -->
            <div data-tooltip="number of clients" class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $clientsCount }}</span>
            </div>
        </div>
        <!-- add new client & refresh page -->
        <div class="flex items-center gap-5">
            <div data-tooltip="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>


            <!-- add new client -->
            <a href="{{ route('companies.showCreateOptions') }}">
                <div data-tooltip="Create new Client" class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>

                </div>
            </a>

        </div>


    </div>
    <!-- ./page title -->

    <!-- page content -->
    <div class="tableCon">
        <div class="content-70">
            <div class="panel BoxShadow rounded-lg">

                <!-- search & filter buttons -->
                <div class="customResponsiveClass flex flex-col md:flex-row justify-between p-2 gap-3">
                    <!-- Search icon -->
                    <div class="relative w-full">
                        <!-- Search Input -->
                        <input type="text" placeholder="Find fast and search here..." class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider" id="searchInput">

                        <!-- Search Button with SVG Icon -->
                        <button data-tooltip="start searching" type="button" class="DarkBGcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1"
                            id="searchButton">
                            <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="11.5" cy="11.5" r="9.5" stroke="#fff" stroke-width="1.5" opacity="0.5" class="dark:stroke-gray-300"></circle>
                                <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round" class="dark:stroke-gray-300"></path>
                            </svg>
                        </button>
                    </div>
                    <!-- ./search icon -->

                    <!-- Filter & export buttons -->
                    <div class="flex customCenter gap-5 w-full justify-end">
                        <!-- Customize -->
                        <button class="flex px-3 py-2 gap-2 city-light-yellow rounded-lg shadow-sm items-center text-xs md:text-sm">
                            <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
                            </svg>
                            <span class="dark:text-black">Customize</span>
                        </button>
                        <!-- ./customize -->

                        <!-- Filter -->
                        <button id="toggleFilters" class="flex px-3 py-2 gap-2 city-light-yellow rounded-lg shadow-sm items-center text-xs md:text-sm">
                            <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                <path fill="#333333" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                            </svg>
                            <span class="text-xs md:text-sm dark:text-black">Filters</span>
                        </button>
                        <!-- ./filter -->

                        <!-- Export -->
                        <button class="flex px-3 py-2 gap-2 city-light-yellow rounded-lg shadow-sm items-center text-xs md:text-sm">
                            <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="#333333" d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                            </svg>
                            <span class="text-xs md:text-sm dark:text-black">Export</span>
                        </button>
                        <!-- ./export -->
                    </div>
                    <!-- ./filter & export buttons -->
                </div>

                <!-- ./search & filter buttons -->




                <!-- Table -->
                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>

                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" id="selectAll" class="text-gray-300 hidden">
                                            <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" rx="4" />
                                            </svg>
                                        </label>
                                    </th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Actions</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Name</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Email</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Phone</th>

                                </tr>
                            </thead>
                            <tbody>
                                @if ($clients->isEmpty())
                                <tr>
                                    <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">No data for now.... Create new!</td>
                                </tr>
                                @else
                                @foreach ($clients as $client)
                                <tr class="taskRow">
                                    <td>
                                        <label class="custom-checkbox" data-tooltip="select client">
                                            <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox text-gray-900 dark:text-gray-300" data-id="{{ $client->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" rx="4" />
                                            </svg>
                                        </label>
                                    </td>
                                    <td class="p-3 text-sm flex gap-3">
                                        <a href="javascript:void(0);"
                                            class="viewClient text-blue-600"
                                            data-id="{{ $client->id }}"
                                            data-name="{{ $client->name }}"
                                            data-email="{{ $client->email }}"
                                            data-phone="{{ $client->phone }}">
                                            View Details
                                        </a>

                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                            <path fill="none" stroke="#e11d48" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12H8m-6 0c0 5.523 4.477 10 10 10s10-4.477 10-10S17.523 2 12 2M4.649 5.079q.207-.22.427-.428M7.947 2.73q.273-.122.553-.229M2.732 7.942q-.124.275-.232.558" color="#e11d48" />
                                        </svg>
                                    </td>


                                    <td class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $client->name }}
                                    </td>
                                    <td class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $client->email }}
                                    </td>
                                    <td class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $client->phone }}
                                    </td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>

                    </div>



                    <!-- pagination -->
                    <div class="dataTable-bottom justify-center">
                        <nav class="dataTable-pagination">
                            <ul class="dataTable-pagination-list flex gap-2 mt-4">
                                <li class="pager" id="prevPage">
                                    <a href="#">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                            <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                </li>
                                <!-- Dynamic page numbers will be injected here -->
                                <li class="pager" id="nextPage">
                                    <a href="#">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                            <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                </li>
                            </ul>


                        </nav>
                    </div>
                    <!-- ./pagination -->
                </div>
                <!-- ./Table  -->

            </div>
        </div>


        <!-- right -->
        <!-- Client Details Container -->
        <div class="w-[30%] hidden" id="showClientRightDiv">
            <div id="clientDetails" class="panel w-full xl:mt-0 rounded-lg h-auto">
                <!-- Client details will be rendered here -->
            </div>
        </div>
        <!-- ./right -->
    </div>
    <!--./page content-->

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const viewClientLinks = document.querySelectorAll(".viewClient");
            const showClientRightDiv = document.getElementById("showClientRightDiv"); // Correct element ID
            const clientDetailsDiv = document.getElementById("clientDetails");

            viewClientLinks.forEach((link) => {
                link.addEventListener("click", function(event) {
                    event.preventDefault();

                    // Extract client data from the clicked link
                    const clientId = this.getAttribute("data-id");
                    const clientName = this.getAttribute("data-name");
                    const clientEmail = this.getAttribute("data-email");
                    const clientPhone = this.getAttribute("data-phone");

                    // Populate the sidebar with client details
                    clientDetailsDiv.innerHTML = `
                <h3 class="text-lg font-bold mb-4">Client Details</h3>
                <p><strong>ID:</strong> ${clientId}</p>
                <p><strong>Name:</strong> ${clientName}</p>
                <p><strong>Email:</strong> ${clientEmail}</p>
                <p><strong>Phone:</strong> ${clientPhone}</p>
            `;

                    // Show the sidebar
                    showClientRightDiv.classList.remove("hidden"); // Correctly reference the element
                });
            });
        });
    </script>
</x-app-layout>