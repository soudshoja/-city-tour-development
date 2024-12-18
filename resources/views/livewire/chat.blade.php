<div
    wire:init="getMessage"
    class="bg-white rounded-lg h-auto overflow-y-auto chat-container">
    @if($error)
    <div class="z-20 fixed top-5 right-10 bg-red-500 text-white p-4 text-center rounded-md font-semibold">
        <p>{{ $error }}</p>
    </div>
    @endif
    <div class="flex flex-col-reverse gap-8 px-2 pb-8 pt-4">
        @if(count($messages) == 0)
        <div class="chat-box chat-box-assistant">
            <div class="rounded-full translate-y-6 h-10 p-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="6" r="4" stroke="#000000" stroke-width="1.5" />
                    <path d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18" stroke="#000000" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </div>
            <div class="chat-message">
                Hi! I'm an AI assistant. How can I help you today?
            </div>
        </div>
        @endif
        @foreach($messages as $message)

        <div class="chat-box {{ $message['role'] == 'user' ? 'chat-box-user ' : 'chat-box-assistant' }}">
            <div class="rounded-full translate-y-6 h-10 p-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="6" r="4" stroke="{{ $message['role'] == 'user' ? '#fff' : '#000000' }}" stroke-width="1.5" />
                    <path d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18" stroke="{{ $message['role'] == 'user' ? '#fff' : '#000000' }}" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </div>
            <div class="chat-message">
                {{$message['content'][0]['text']['value']}}
            </div>
        </div>
        @endforeach
    </div>
    <div class="chat p-2 w-full bg-white rounded-b-lg flex justify-between sticky bottom-0">
        <form wire:submit.prevent="sendMessage" class="w-full flex gap-2">
            <div class="flex justify-center items-center">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-gray-900 invisible" wire:loading.class.remove="invisible"></div>
            </div>
            <input type="text" wire:model='prompt' name="" id="" class="w-full p-2 border-none bg-gray-200 rounded-lg" placeholder="Type a message...">
            <button type="submit" class="send p-2 rounded-lg">Send</button>
        </form>
    </div>
    <script>
    </script>
</div>