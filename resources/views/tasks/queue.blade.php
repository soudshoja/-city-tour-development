<x-app-layout>
    <!-- Breadcrumbs -->
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('tasks.index') }}" class="customBlueColor hover:underline"> Tasks</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <span>Queue</span>
        </li>
    </ul>
    <!-- ./Breadcrumbs -->

    @if($queueTasks->isEmpty())
    <p class="text-center text-gray-500 dark:text-gray-300">No tasks in the queue</p>
    @else
    @foreach($queueTasks as $task)
    <div class="p-2 bg-white dark:bg-gray-700 rounded-md shadow-md mb-2">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $task->reference }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-300">{{ $task->agent->name }}</p>
            </div>
            <div>
                <a href="{{ route('tasks.show', $task->id) }}" class="text-blue-600 dark:text-blue-500">View</a>
                <!-- <a href="" class="text-blue-500 dark:text-blue-400">View</a> -->
            </div>
        </div>
    </div>
    @endforeach
    @endif
</x-app-layout>