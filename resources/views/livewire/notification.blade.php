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
    <div class="bg-grey-500 border-gray-800 rounded-md p-2"
        wire:key="notification-{{ $notification->id }}">
        <p class="text-sm text-gray-800">{{ $notification->title }}</p>
    </div>
    @endforeach
</div>