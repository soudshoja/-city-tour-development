<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('OpenAI - Steps') }}
        </h2>
    </x-slot>

    <div>
        @foreach($runs as $key => $run)
        <p class="w-full bg-gray-200 p-2 rounded-md my-2 text-lg">
            <strong>RUN: </strong> {{$key}}
        </p>
        @foreach($run as $step)
        <div class="bg-white shadow-md p-2 rounded-md my-2">

            <h3 class="text-2xl ">
                <strong>STEP:</strong>
                {{ $step['id'] }}
            </h3>
            <p class="text-lg">{{ $step['step_details']['type']}}</p>
            @if($step['step_details']['type'] == 'message_creation')
            <ul>
                @foreach($step['step_details']['message_creation'] as $message_creation)
                <li>{{$message_creation}}</li>
                @endforeach
            </ul>
            @elseif($step['step_details']['type'] == 'tool_calls')
            @foreach($step['step_details']['tool_calls'] as $key => $tool_call)
            <h3 class="text-xl font-bold">{{$tool_call['id']}}</h3>
            <ul>
                @foreach($tool_call['function'] as $function)
                <li>{{$function}}</li>
                @endforeach
            </ul>
            @endforeach
            @endif
        </div>
        @endforeach
        @endforeach
    </div>
</x-app-layout>