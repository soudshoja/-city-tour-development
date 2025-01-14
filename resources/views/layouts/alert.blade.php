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
<div class="alert flex items-center rounded bg-success-light p-3.5 text-success dark:bg-success-dark-light" role="alert" style="z-index: 1050;">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert flex items-center rounded bg-danger-light p-3.5 text-danger dark:bg-danger-dark-light" role="alert" style="z-index: 1050;">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif