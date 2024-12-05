<x-app-layout>
    <div>
        <!-- Breadcrumbs -->
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>Suppliers List</span>
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
                        <span class="text-black dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Total
                            Suppliers </span>


                    </a>
                    <a
                        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-info-light dark:bg-gray-700"><span
                            id="SuppliersData"></span>
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

                <a
                    class="h-full flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none">
                    <svg class="w-5 h-5 mr-2 text-white dark:text-gray-300" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-white dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Add
                        Supplier</span>

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

                            <th class="px-4 py-2">Supplier Name</th>

                            <th class="px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suppliers as $supplier)
                        <tr>

                            <td class="px-4 py-2 border">
                                <span class="font-bold">» {{ $supplier->name }}</span><br>
                                <span class="text-sm text-gray-500">Activate supplier to allow the system users to
                                    request API from the supplier</span>
                            </td>
                            <td class="px-4 py-2 border text-center space-x-2">
                                <button class="bg-green-500 text-white px-2 py-1 rounded">Activate</button>
                                <button class="bg-gray-300 text-gray-700 px-2 py-1 rounded">Deactivate</button>
                                <button class="bg-gray-300 text-gray-700 px-2 py-1 rounded">Configure</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>


        <!-- ./Table Section -->

    </div>


    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // Handle task count display
        const SuppliersCount = @json($SuppliersCount);
        document.getElementById("SuppliersData").innerText = SuppliersCount;
    });
    </script>
</x-app-layout>