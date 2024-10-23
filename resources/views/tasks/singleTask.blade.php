<!-- singleTask.blade.php -->

<div class="m-5 flex items-center justify-between relative">
    <span class="
        @if($task->status == 'Pending') 
            border border-red-500 rounded-lg p-1 text-red-500 
        @elseif($task->status == 'Confirmed') 
            border border-blue-500 rounded-lg p-1 text-blue-500
        @elseif($task->status == 'Completed') 
            border border-green-500 rounded-lg p-1 text-green-500 
        @endif">
        {{ $task->status }}
    </span>

    <!-- Close Button -->
    <button onclick="closeTaskModal()" class="text-gray-500 hover:text-gray-700 rounded-lg bg-[#004B99]">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#fff"
            class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<div class="bg-gray-100 dark:bg-gray-700 p-2">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 text-center">Task Details</h2>
</div>
<div class="p-5">
    <div class="p-3 border border-gray-300 rounded-lg">
        <div class="flex items-center justify-between">
            <p class="text-lg">Client Name</p>
            <h5 class="text-base">{{ $task->client->name ?? 'No client' }}</h5>
        </div>
        <div class="flex items-center justify-between">
            <p class="text-lg">Type</p>
            <h5 class="text-base">{{ $task->type }}</h5>
        </div>
        <div class="flex items-center justify-between">
            <p class="text-lg">Total Price</p>
            <h5 class="text-base">{{ $task->total }}</h5>
        </div>
        <div class="flex items-center justify-between">
            <p class="text-lg">Task Name</p>
            <h5 class="text-base">{{ $task->additional_info }} - {{ $task->venue }}</h5>
        </div>
        <div class="flex items-center justify-between">
            <p class="text-lg">Assigned Agent</p>
            <h5 class="text-base">{{ $task->agent->name }}</h5>
        </div>
        <div class="flex items-center justify-between">
            <p class="text-lg">Reference</p>
            <h5 class="text-base">{{ $task->reference }}</h5>
        </div>
    </div>
</div>