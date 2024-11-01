<x-app-layout>

    @if(Auth()->user()->role_id === Role::ADMIN)

    <div>


    </div>



    @elseif(Auth()->user()->role == Role::COMPANY)
    <div>
        @include('companies.index')
    </div>

    @elseif(Auth()->user()->role == Role::AGENT)
    <div>
        @include('items.index')
    </div>
    @endif


</x-app-layout>