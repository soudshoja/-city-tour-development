<x-app-layout>
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
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white p-4 dark:bg-gray-600 overflow-hidden shadow-sm rounded-t-lg font-semibold">
            COUNTRY AVAILABLE
        </div>
        <hr class="dark:border-gray-200">
        <div class="p-4 rounded-b-lg bg-white dark:bg-gray-600 overflow-hidden shadow-sm">
            <div class="px-2 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                @if($countries->isEmpty())
                <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">No Country Found</div>
                @else
                @foreach($countries as $country)
                <a href="{{ route('suppliers.tbo.city-list', [ 'countryCode' => $country['Code']]) }}" class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">{{ $country['Name'] }}</a>
                @endforeach
                @endif
            </div>
            <div class="mt-4">
                {{ $countries->links('pagination::tailwind') }}
            </div>
        </div>
    </div>
</x-app-layout>