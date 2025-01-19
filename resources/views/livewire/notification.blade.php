<div>
    <!-- Tabs for Filter -->
    <div class="flex items-center space-x-8 border-b border-gray-300 dark:border-gray-700">
        <button
            class="relative pb-2 font-semibold transition-all duration-300 ease-in-out {{ $filter == 'all' ? 'text-black dark:text-white border-b-2 border-blue-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300' }}"
            wire:click="updateFilter('all')">
            All
            <span class="ml-2 text-xs bg-blue-100/50 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 px-2 py-0.5 rounded-full">
                {{ $totalCount }}
            </span>
        </button>

        <button
            class="relative pb-2 font-semibold transition-all duration-300 ease-in-out {{ $filter == 'read' ? 'text-black dark:text-white border-b-2 border-green-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300' }}"
            wire:click="updateFilter('read')">
            Read
            <span class="ml-2 text-xs bg-green-100/50 dark:bg-green-900/50 text-green-800 dark:text-green-300 px-2 py-0.5 rounded-full">
                {{ $readCount }}
            </span>
        </button>

        <button
            class="relative pb-2 font-semibold transition-all duration-300 ease-in-out {{ $filter == 'unread' ? 'text-black dark:text-white border-b-2 border-red-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300' }}"
            wire:click="updateFilter('unread')">
            Unread
            <span class="ml-2 text-xs bg-red-100/50 dark:bg-red-900/50 text-red-500 dark:text-red-400 px-2 py-0.5 rounded-full">
                {{ $unreadCount }}
            </span>
        </button>
    </div>



    <!-- Notification List -->
    @foreach ($notifications as $notification)
    <div
        class="mt-5 px-4 py-3 mb-3 rounded-md transition duration-200 
        {{ $notification->status == 'read' ? 'bg-green-100/50 dark:bg-green-900/50' : 'bg-red-100/50 dark:bg-red-900/50 text-red-500 dark:text-red-400' }}"
        wire:key="notification-{{ $notification->id }}">
        <p class="text-sm font-semibold text-gray-700 dark:text-white">
            {{ $notification->title }}
        </p>
    </div>
    @endforeach


</div>