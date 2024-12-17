<x-app-layout>

    <!-- page wrapper -->
    <div class="mx-auto">


        <!-- page title -->
        <div class="flex justify-between items-center gap-5 my-3 ">


            <div class="flex items-center gap-5 ">
                <h2 class="text-3xl font-bold">Tasks List</h2>
                <!-- total task number -->
                <div class="relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                    <span class="text-xl font-bold text-slate-700">{{ $taskCount }}</span>
                </div>
            </div>
            <!-- add new task & refresh page -->
            <div class="flex items-center gap-5">
                <div class="relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                        <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                    </svg>
                </div>
                <div class="relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#333333" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </div>


        </div>
        <!-- ./page title -->


        <!-- actions -->
        <div class="w-full justify-between flex flex-col gap-5 mt-5 md:flex-row">
            <div class="w-[70%]">
                <!-- Table  -->
                <div class="panel oxShadow rounded-lg">
                    <!--  search icon -->
                    <div class="!pl-0 w-full h-12 border border-gray-200 rounded-full flex items-center ">
                        <div class=" relative w-10 h-10 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                            <svg class="w-6 h-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 15 16">
                                <path fill="currentColor" d="M6.5 13.02a5.5 5.5 0 0 1-3.89-1.61C1.57 10.37 1 8.99 1 7.52s.57-2.85 1.61-3.89c2.14-2.14 5.63-2.14 7.78 0C11.43 4.67 12 6.05 12 7.52s-.57 2.85-1.61 3.89a5.5 5.5 0 0 1-3.89 1.61m0-10c-1.15 0-2.3.44-3.18 1.32C2.47 5.19 2 6.32 2 7.52s.47 2.33 1.32 3.18a4.51 4.51 0 0 0 6.36 0C10.53 9.85 11 8.72 11 7.52s-.47-2.33-1.32-3.18A4.48 4.48 0 0 0 6.5 3.02"></path>
                                <path fill="currentColor" d="M13.5 15a.47.47 0 0 1-.35-.15l-3.38-3.38c-.2-.2-.2-.51 0-.71s.51-.2.71 0l3.38 3.38c.2.2.2.51 0 .71c-.1.1-.23.15-.35.15Z"></path>
                            </svg>
                        </div>
                    </div>
                    <!-- ./search icon -->
                    <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                        <div class="dataTable-top"></div>
                        <div class="dataTable-container">

                            <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                                <thead>
                                    <tr>
                                        <th><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Client Name</th>
                                        @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Agent Name</th>
                                        @endif
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Type</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Price</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Status</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Supplier</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tasks as $task)
                                    <tr>
                                        <td>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </td>
                                        <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->client_name }}</td>
                                        @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
                                        <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->agent_name }}</td>
                                        @endif
                                        <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->type }}</td>
                                        <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->price }}</td>
                                        <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->status }}</td>
                                        <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->supplier->name }}</td>
                                        <td class="p-3 text-sm">
                                            <a href="#" class="text-blue-500 hover:underline">View</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="dataTable-bottom justify-between">

                            <div class="flex items-center gap-5">

                                <div class="dataTable-info">Showing 1 to 10 of 25 entries</div>
                                <div class="dataTable-dropdown">
                                    <label>
                                        <select class="dataTable-selector">
                                            <option value="10" selected="">10</option>
                                            <option value="20">20</option>
                                            <option value="30">30</option>
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                        </select>
                                    </label>

                                </div>

                            </div>
                            <nav class="dataTable-pagination">
                                <ul class="dataTable-pagination-list">
                                    <li class="pager">
                                        <a href="#" data-page="1"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                                <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg></a>
                                    </li>
                                    <li class="active"><a href="#" data-page="1">1</a></li>
                                    <li class=""><a href="#" data-page="2">2</a></li>
                                    <li class=""><a href="#" data-page="3">3</a></li>
                                    <li class="pager"><a href="#" data-page="2"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                                <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg></a></li>
                                    <li class="pager"><a href="#" data-page="3"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                                <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg></a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- ./Table  -->

            </div>
            <!-- right -->
            <div class="w-[30%]">

                <div class="flex flex-col md:flex-row justify-center text-center gap-5">
                    <!-- customize -->
                    <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 32 32">
                            <path fill="#333333" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                        </svg>
                        <span class="text-sm">Customize</span>
                    </button>
                    <!-- ./customize -->

                    <!-- filter -->
                    <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                            <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
                        </svg>
                        <span class="text-sm">Filter</span>
                    </button>
                    <!-- ./filter -->

                    <!-- export -->
                    <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                            <path fill="#333333" d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                        </svg>
                        <span class="text-sm">Export</span>
                    </button>
                    <!-- ./export -->
                </div>
                <div class="mt-5 ">
                    <!-- display task details here-->
                    <div class="panel w-full xl:mt-0  rounded-lg h-96"></div>
                    <!-- display task details here-->

                </div>
            </div>
            <!-- ./right -->
        </div>
        <!--./actions-->

        <!-- page content -->
        <div class="flex flex-col gap-2.5 xl:flex-row mt-5">








        </div>
        <!-- ./page content -->


    </div>
    <!-- ./page wrapper -->




</x-app-layout>