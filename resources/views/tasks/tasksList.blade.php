<x-app-layout>
    <ul class="flex space-x-2 rtl:space-x-reverse">
        <li>
            <a href="{{ route('dashboard') }}" class="text-primary hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] ltr:before:mr-1 rtl:before:ml-1">
            <span>Tasks List</span>
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
                        <th>Agent Email</th>
                        <th>Task</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                    <tr>
                        <td>{{ $task->agent_email }}</td>
                        <td>{{ $task->description }}</td>
                        <td class="py-3 px-4">
                            @if($task->status == 'completed')
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm">Completed</span>
                            @elseif($task->status == 'pending')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">Pending</span>
                            @elseif($task->status == 'inprogress')
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">In Progress</span>
                            @else
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-sm">Overdue</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <a href="#" class="ml-4 text-red-600 hover:text-red-900">Delete</a>
                        </td>
                    </tr>
                    @endforeach

                </tbody>
            </table>
        </div>

    </div>

</x-app-layout>