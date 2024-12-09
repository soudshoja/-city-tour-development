@php
use App\Models\Role;
@endphp

<div class="space-y-4 m-5">
    <!-- company menu -->
    @if(Auth::user()->role_id === Role::COMPANY )
    @include('layouts.sidebars.company')
    @endif

    <!-- admin menu -->
    @if(Auth::user()->role_id === Role::ADMIN )
    @include('layouts.sidebars.admin')
    @endif


</div>