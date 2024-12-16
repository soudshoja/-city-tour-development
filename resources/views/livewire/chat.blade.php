<div class="bg-white rounded-lg h-full overflow-y-auto">
    <div class="flex flex-col-reverse gap-8 ">
        @if($loading)
        <div class="flex justify-center items-center">
            <div class="animate-spin rounded-full h-32 w-32 border-b-2 border-gray-900"></div>
        </div>
        @endif
        @foreach($messages as $message)
        @php
            $class ='';
            $bgColor = '';
            if($message['role'] == 'user'){
                $class = 'flex-row-reverse';
                $bgColor = 'bg-blue-500';
            } else {
                $class = 'flex-row';
                $bgColor = 'bg-gray-400';
            }
        @endphp
        <div class="flex {{$class}} gap-2">
            <div class="{{$bgColor}} rounded-full translate-y-6 h-10 p-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="6" r="4" stroke="#1C274C" stroke-width="1.5" />
                    <path d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </div>
            <div class="p-2 {{$bgColor}} rounded-lg rounded-br-none">
                {{$message['content'][0]['text']['value']}}
            </div>
        </div>
        @endforeach 
    </div>
    <div class="chat p-2 w-full bg-gray-400 rounded-b-lg flex justify-between sticky bottom-0">
        <form wire:submit.prevent="sendMessage" class="w-full flex gap-2">
            <input type="text" wire:model='prompt' name="" id="" class="w-full p-2 border border-gray-200 rounded-lg" placeholder="Type a message...">
            <button type="submit" class="bg-blue-500 p-2 rounded-lg">Send</button>
        </form>
    </div>
</div>