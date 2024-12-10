<div>
    <div class="flex justify-evenly gap-2">
        <button class="border border-gray-500 p-2 inline-flex justify-between align-middle rounded-md w-full {{$filter =='all' ? 'bg-green-500 text-white' : '' }}" wire:click="updateFilter('all')">
            <p>All</p>
        </button>
        <button class="border border-gray-500 p-2 inline-flex justify-between align-middle rounded-md w-full {{$filter =='read' ? 'bg-green-500 text-white' : '' }}" wire:click="updateFilter('read')">
            <p>Read</p>
        </button>
        <button class="border border-gray-500 p-2 inline-flex justify-between align-middle rounded-md w-full {{$filter =='unread' ? 'bg-green-500 text-white' : '' }}" wire:click="updateFilter('unread')">
            <p>Unread</p>
        </button>
    </div>
    @foreach($notifications as $notification)
    <div class="px-2 py-4  my-2 rounded-md {{ $notification->status == 'read' ? 'bg-green-300 border border-green-600' : 'bg-gray-300' }}"
        wire:key="notification-{{ $notification->id }}">
        <p class="text-sm font-semibold ">{{ $notification->title }}</p>
    </div>
    @endforeach
    <div class="see-all">
        <a href="{{ route('notifications.index') }}" class="text-black font-semibold">
            See All Notifications
        <a>
    </div>
</div>