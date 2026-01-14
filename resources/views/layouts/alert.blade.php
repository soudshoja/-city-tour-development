@if($errors->any())
@foreach($errors->all() as $error)
<div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
    {{ $error }}
    <button type="button" class="close text-white ml-2" aria-label="Close"
        onclick="this.parentElement.style.display='none';">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endforeach
@endif

<div class="alert grid gap-2">
    @if(session('success'))
    <div class="flex items-center justify-between rounded bg-green-500 p-3.5 text-white " role="alert">
        <div class="grid gap-2">
            <p>
                {{ session('success') }}
            </p>
            @if(session('data_success'))
            @foreach(session('data_success') as $data)
            @if(is_array($data))
            <div class="my-2">
                @foreach($data as $key => $value)
                <p class="text-sm text-white">
                    {{ $value }}
                </p>
                @endforeach
            </div>
            @else
            <p class="text-sm text-white">
                {{ $data }}
            </p>
            @endif
            @endforeach
            @endif
        </div>
        <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.remove()">X</button>
    </div>
    @endif

    @if(session('error'))
    <div class="flex items-center justify-between rounded bg-red-500 p-3.5 text-white dark:bg-danger-dark-light" role="alert">
        <div class="grid">
            <p>
                {{ session('error') }}
            </p>
            @if(session('data'))
            @foreach(session('data') as $data)
            @if(is_array($data))
            <div class="my-2">
                @foreach($data as $key => $value)
                <p class="text-sm text-white">
                    {{ $value }}
                </p>
                @endforeach
            </div>
            @else
            <p class="text-sm text-white">
                {{ $data }}
            </p>
            @endif
            @endforeach
            @endif
        </div>
        <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.remove()">X</button>
    </div>
    @endif
</div>
<!-- for ajax alert -->
<div
    id="custom-success-alert"
    class="alert flex items-center justify-between rounded bg-green-500 p-3.5 text-white hidden" role="alert">
    <p></p>
    <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.remove()">X</button>
</div>

<div
    id="custom-error-alert"
    class="alert flex items-center justify-between rounded bg-red-500 p-3.5 text-white hidden" role="alert">
    <p></p>
    <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.classList.add('hidden')">X</button>
</div>

<div
    id="custom-success-ajax-alert"
    class="absolute top-8 right-24 z-10 flex items-center justify-between rounded shadow bg-green-500 p-3.5 text-white hidden" role="alert">
    <p></p>
    <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.classList.add('hidden')">X</button>
</div>