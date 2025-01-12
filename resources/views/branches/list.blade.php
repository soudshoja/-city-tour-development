<x-app-layout>



    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Branches List</h2>
            <!-- total branch number -->
            <div data-tooltip="number of branches" class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $branchesCount }}</span>
            </div>
        </div>
        <!-- add new branch & refresh page -->
        <div class="flex items-center gap-5">
            <div data-tooltip="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>

            <!-- add new branch -->
            <a href="{{ route('companies.showCreateOptions') }}">
                <div data-tooltip="Create new branch" class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">


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
            <!-- Table  -->
            <div class="panel oxShadow rounded-lg">
                <!--  search icon -->
                <div class="relative">
                    <!-- Search Input -->
                    <input type="text" placeholder="Find fast and search here..." class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider" id="searchInput">

                    <!-- Search Button with SVG Icon -->
                    <button type="button" class="btn DarkBGcolor absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1"
                        id="searchButton">
                        <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11.5" cy="11.5" r="9.5" stroke="#fff" stroke-width="1.5" opacity="0.5"></circle>
                            <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round"></path>
                        </svg>
                    </button>
                </div>

                <!-- ./search icon -->
                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <!-- table -->
                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" id="selectAll" class="form-checkbox hidden">
                                            <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Branch Name</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Branch Email</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">phone number</th>

                                </tr>
                            </thead>
                            <tbody>
                                @if ($branches->isEmpty())
                                <tr>
                                    <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">No data for now.... Create new!</td>
                                </tr>
                                @else
                                @foreach ($branches as $branch)
                                <tr>
                                    <td>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox" value="{{ $branch->id }}" {{ $branch-> branchDetail ? 'disabled' : '' }}>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </td>
                                    <td class="p-3 text-sm">
                                        <a href="javascript:void(0);" class="viewBranch text-blue-500 hover:underline" data-branch-id="{{ $branch->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                <g fill="none" stroke="#333333" stroke-width="1.5">
                                                    <path d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z" opacity=".5" />
                                                    <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z" />
                                                </g>
                                            </svg>
                                        </a>
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $branch->name }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $branch->email }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500"> {{ $branch->phone }}</td>

                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>

                    </div>
                    <!-- ./table -->


                    <!-- pagination -->
                    @if ($branches->count() > 15)
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
                    @endif
                    <!-- ./pagination -->
                </div>
            </div>

            <!-- ./Table  -->

        </div>
        <!-- right -->
        <div class="content-30">

            <div class="flex lg:flex-col md:flex-row justify-center text-center gap-5">
                <!-- customize -->
                <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                        <path fill="#333333" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                    </svg>
                    <span class="text-sm">Customize</span>
                </button>
                <!-- ./customize -->

                <!-- filter -->
                <button class="flex px-5 py-3 gap-2 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
                    </svg>
                    <span class="text-sm">Filter</span>
                </button>
                <!-- ./filter -->

                <!-- export -->
                <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="#333333" d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                    </svg>
                    <span class="text-sm">Export</span>
                </button>
                <!-- ./export -->
            </div>
            <div class="mt-5 ">
                <!-- display branch details here-->
                <div id="branchDetails" class="panel w-full xl:mt-0 rounded-lg h-auto hidden"></div> <!-- display branch details here-->

            </div>
        </div>
        <!-- ./right -->
    </div>
    <!--./page content-->

    <!-- Floating Actions div-->
    <div>
        <div id="floatingActions" class="hidden flex justify-between gap-5 fixed CuzPostion bg-[#f6f8fa] shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)]  rounded-lg w-auto h-auto z-50 p-3">

            <div class="flex justify-between gap-5 items-center h-full">

                <button class="flex px-5 py-3 gap-3 btn-danger hover:bg-[#e7515aa8] rounded-lg shadow-sm items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#ffffff" d="M12 2c5.53 0 10 4.47 10 10s-4.47 10-10 10S2 17.53 2 12S6.47 2 12 2m5 5h-2.5l-1-1h-3l-1 1H7v2h10zM9 18h6a1 1 0 0 0 1-1v-7H8v7a1 1 0 0 0 1 1" />
                    </svg>
                    <span class="text-sm">Delete</span>
                </button>
            </div>
            <div id="closeFloatingActions" class="flex cursor-pointer items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 12 12">
                    <path fill="#E53935" d="M1.757 10.243a6.001 6.001 0 1 1 8.488-8.486a6.001 6.001 0 0 1-8.488 8.486M6 4.763l-2-2L2.763 4l2 2l-2 2L4 9.237l2-2l2 2L9.237 8l-2-2l2-2L8 2.763Z" />
                </svg>
            </div>
        </div>

    </div>

    <!-- ./Floating Actions div -->

    <!-- select all & create invoice script -->
    <script>
        const floatingActions = document.getElementById("floatingActions");
        const closeFloatingActions = document.getElementById("closeFloatingActions");
        const selectAllCheckbox = document.getElementById("selectAll");
        const rowCheckboxes = document.querySelectorAll(".rowCheckbox");

        // Select/Deselect all checkboxes
        selectAllCheckbox.addEventListener("change", function() {
            rowCheckboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
            toggleFloatingActions(); // Update floating actions visibility
        });

        // Function to toggle the visibility of the floating actions div
        function toggleFloatingActions() {
            const anyChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
            if (anyChecked) {
                floatingActions.classList.remove("hidden");
            } else {
                floatingActions.classList.add("hidden");
            }
        }

        // Add change event to each row checkbox
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener("change", function() {
                // Update the "Select All" checkbox state
                const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
                toggleFloatingActions(); // Update floating actions visibility
            });
        });

        // Close the floating div when the "X" button is clicked
        closeFloatingActions.addEventListener("click", function() {
            floatingActions.classList.add("hidden");
        });
    </script>






    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const viewBranchLinks = document.querySelectorAll(".viewBranch");
            const branchDetailsDiv = document.getElementById("branchDetails");

            viewBranchLinks.forEach(link => {
                link.addEventListener("click", function(event) {
                    event.preventDefault();
                    const branchId = this.getAttribute("data-branch-id");

                    fetch(`/branches/${branchId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Failed to fetch branch details. Status: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data && data.id) {
                                branchDetailsDiv.innerHTML = `
                                    <h3 class='text-lg font-bold mb-2'>Branch Details</h3>
                                    <div class='flex flex-col rounded-md border border-[#e0e6ed] dark:border-[#1b2e4b]'>
                                        <div class='border-b px-4 py-4 hover:bg-gray-200 dark:hover:bg-[#eee]/10'>
                                            <p><strong>Branch Name:</strong> ${data.name}</p>
                                        </div>
                                        <div class='border-b px-4 py-4 hover:bg-gray-200 dark:hover:bg-[#eee]/10'>
                                            <p><strong>Email:</strong> ${data.email}</p>
                                        </div>
                                        <div class='border-b px-4 py-4 hover:bg-gray-200 dark:hover:bg-[#eee]/10'>
                                            <p><strong>Phone Number:</strong> ${data.phone}</p>
                                        </div>
                                        <div class='border-b px-4 py-4 hover:bg-gray-200 dark:hover:bg-[#eee]/10'>
                                            <p><strong>Address:</strong> ${data.address}</p>
                                        </div>
                                    </div>
                                `;
                                branchDetailsDiv.classList.remove('hidden');
                            } else {
                                branchDetailsDiv.innerHTML = "<p class='text-red-500'>Invalid branch data received.</p>";
                                branchDetailsDiv.classList.remove('hidden');
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching branch details:", error);
                            branchDetailsDiv.innerHTML = "<p class='text-red-500'>Failed to load branch details.</p>";
                            branchDetailsDiv.classList.remove('hidden');
                        });
                });
            });
        });
    </script>





</x-app-layout>