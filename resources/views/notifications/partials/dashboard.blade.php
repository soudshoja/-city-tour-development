<div class="notification-partials h-96 overflow-y-auto">
    <style>
        .unread {
            background-color: #fa3c3c;
        }
        .read{
            background-color: #f0f0f0;
        }
    </style>
    <div class="notification-partials__header ">
        <h3 class="bg-black text-white p-2 w-auto rounded-md my-2">Notifications</h3>
    </div>
    <div class="notification-partials__body">
        @if($notifications->count() > 0)
        @foreach($notifications as $notification)
        @php
        if($notification->status == 'unread'){
            $notiStatus = 'unread';
        } else if($notification->status == 'read'){
            $notiStatus = 'read';
        } 
        @endphp
        <div class="notification-partials__item flex justify-between p-2 my-2 bg-gray-200 rounded-md shadow-md {{ $notiStatus }}">
            <div class="notification-partials__item__content">
                <p class="overflow-x-auto line-clamp-1">{{ $notification['title']}}</p>
            </div>
            <div class="notification-partials__item__action bg-black p-2 rounded-full">
                <a href="" class="rounded-full text-white">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 12C2 16.714 2 19.0711 3.46447 20.5355C4.92893 22 7.28595 22 12 22C16.714 22 19.0711 22 20.5355 20.5355C22 19.0711 22 16.714 22 12V10.5M13.5 2H12C7.28595 2 4.92893 2 3.46447 3.46447C2.49073 4.43821 2.16444 5.80655 2.0551 8" stroke="#fff" stroke-width="1.5" stroke-linecap="round" />
                        <circle cx="19" cy="5" r="3" stroke="#fff" stroke-width="1.5" />
                    </svg>

                </a>
            </div>
        </div>
        @endforeach
        @else
        <div class="notification-partials__item">
            <div class="notification-partials__item__content">
                <p>No notifications found.</p>
            </div>
        </div>
        @endif
    </div>
    <div class="notification-partials__footer">
        <a href="{{ route('notifications.index') }}" class="btn btn-primary">View All</a>
    </div>
</div>