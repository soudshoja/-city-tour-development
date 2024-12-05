<div>
        <input type="text" 
            wire:model.live="filter"     
        >
        @foreach($notifications as $notification)
            <div class="bg-grey-500 border-gray-800 rounded-md p-2"
                wire:key = "notification-{{ $notification->id }}" 
            >
                <p class="text-sm text-gray-800">{{ $notification->title }}</p>
            </div>
        @endforeach
</div>