<x-app-layout>
    <div class="flex justify-between">
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 px-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <a href="{{ route('suppliers.index') }}" class="customBlueColor hover:underline">Suppliers List</a>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <span>TBO Holidays</span>
        </li>
    </ul>
    <div class="bg-blue-500 text-white font-semibold p-2 my-2 rounded-md text-center">
        <a href="{{ route('suppliers.tbo.search.index') }}"> Book Rooms </a>
    </div>
    </div>
    @include('suppliers.tbo.past_booking', ['pastBookings' => $pastBookings])
    <div class="">
        <div class="bg-white p-4 dark:bg-gray-600 overflow-hidden shadow-sm rounded-lg font-semibold">
            COUNTRY AVAILABLE
            <div class="px-2 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                @if($countries->isEmpty())
                <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">No Country Found</div>
                @else
                @foreach($countries as $country)
                <a href="{{ route('suppliers.tbo.city-list', [ 'countryCode' => $country['Code']]) }}" class="p-2 bg-gradient-to-r from-gray-800 to-gray-500  dark:to-blue-600 rounded-md text-center text-sm text-white w-full">{{ $country['Name'] }}</a>
                @endforeach
                @endif
            </div>
            <div class="mt-4">
                {{ $countries->links('pagination::tailwind') }}
            </div>
        </div>
    </div>
</x-app-layout>