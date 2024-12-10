<x-app-layout>
    <div class="notifications-list">
        <div class="header w-full bg-black text-lg font-semibold text-white p-2 my-2 rounded-md shadow-md">
            <h2>Notifications</h2>
        </div>
        <div class="body bg-white rounded-md shadow-md my-2 p-2">
            @foreach($notifications as $notification)
            <div class="notification-item p-2 my-2 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div class="flex align-top">
                        <div class="rounded-full text-white p-2 h-auto m-2 {{ $notification->status == 'read' ? 'bg-green-500' : 'bg-black' }}">
                            <i class="fas fa-bell"></i>
                        </div>
                        <button class="notification-content text-start" wire:click="markAsRead({{$notification->id}})">
                            <p class="text-sm font-semibold">{{$notification->title}}</p>
                            <p class="text-xs text-gray-500">{{$notification->message}}</p>
                        </button>
                    </div>
                    <div class="notification-time text-xs text-gray-499">
                        {{ $notification->formatted_created_at }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</x-app-layout>