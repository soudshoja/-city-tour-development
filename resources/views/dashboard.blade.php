<x-app-layout>

    @if(Auth()->user()->role === 'admin')

    <div class="p-3">


    </div>



    @elseif(Auth()->user()->role == 'company')
    <div>
        @include('companies.index')
    </div>

    @elseif(Auth()->user()->role == 'agent')
    <div>
        @include('items.index')
    </div>
    @endif


</x-app-layout>