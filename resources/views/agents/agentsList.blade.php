<x-app-layout>
    <div x-data="exportTable">
        <ul class="flex space-x-2 rtl:space-x-reverse">
            <li>
                <a href="{{ route('dashboard') }}" class="text-primary hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-1 rtl:before:ml-1">
                <span>Agents List</span>
            </li>
        </ul>


        <div class="mt-5 panel">

            <div class="flex mb-5">
                <p>Click <a href="#" class="text-primary">here</a> to download the excel template</p>
            </div>
            <!-- Flex container for buttons and search input, with responsive handling for mobile -->
            <div class="mb-5 flex flex-col md:flex-row justify-between items-center w-full space-y-4 md:space-y-0">

                <!-- Buttons on the left -->
                <div class="flex space-x-2">
                    <x-primary-button>Upload Excel</x-primary-button>
                    <x-primary-button>PRINT</x-primary-button>
                    <x-primary-button>Export CSV</x-primary-button>
                </div>

                <!-- Search input on the right -->
                <div class="w-full md:w-auto">
                    <input type="text" placeholder="Search..."
                        class="w-full md:w-auto pr-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder:text-gray-500" />
                </div>
            </div>
        </div>


        <div class="mt-5 panel">
            <div class="overflow-x-auto">
                <table class="CityMobileTable table-fixed">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Type</th>
                            <th>Actions</th>

                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agents as $agent)
                        <tr>
                            <td>{{ $agent->name }}</td>
                            <td>{{ $agent->email }}</td>
                            <td>{{ $agent->phone_number }}</td>
                            <td>{{ $agent->type }}</td>
                            <td class="flex">

                                <a href="{{ route('agentsshow.show', $agent->id) }}}">
                                    <button type="button"
                                        class="text-white bg-gradient-to-r from-teal-400 via-teal-500 to-teal-600 focus:ring-4 focus:outline-none focus:ring-teal-300 dark:focus:ring-teal-800 shadow-lg shadow-teal-500/50 dark:shadow-lg dark:shadow-teal-800/80 font-medium rounded-lg text-xs px-3 py-1.5 text-center me-2 mb-2">
                                        Agent Details
                                    </button>


                                </a>

                                <a href="{{ route('tasks.index', $agent->id) }}">
                                    <button type="button"
                                        class="text-white bg-gradient-to-r from-teal-400 via-teal-500 to-teal-600 focus:ring-4 focus:outline-none focus:ring-teal-300 dark:focus:ring-teal-800 shadow-lg shadow-teal-500/50 dark:shadow-lg dark:shadow-teal-800/80 font-medium rounded-lg text-xs px-3 py-1.5 text-center me-2 mb-2">
                                        Agent Tasks
                                    </button>

                                </a>


                            </td>

                        </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>

        </div>
    </div>

</x-app-layout>