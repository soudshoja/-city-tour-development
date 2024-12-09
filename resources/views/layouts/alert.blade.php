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