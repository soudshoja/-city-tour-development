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

@if(session('success'))
<div class="alert flex items-center justify-between rounded bg-green-500 p-3.5 text-white " role="alert">
    {{ session('success') }}
    <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.remove()">X</button>
</div>
@endif

@if(session('error'))
<div class="alert flex items-center justify-between rounded bg-red-500 p-3.5 text-white dark:bg-danger-dark-light" role="alert">
    {{ session('error') }}
    <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.remove()">X</button>
</div>
@endif