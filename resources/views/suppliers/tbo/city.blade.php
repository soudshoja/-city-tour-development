<x-app-layout>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <ul class="flex space-x-2 rtl:space-x-reverse text-base md:text-lg sm:text-sm py-3">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <a href="{{ route('suppliers.index') }}" class="customBlueColor hover:underline">Suppliers List</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                <a href="{{ route('suppliers.tbo.index') }}" class="customBlueColor hover:underline">TBO Holidays</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                <span>City List</span>
            </li>
        </ul>
        <div class="">
            <div class="bg-white p-4 dark:bg-gray-600 overflow-hidden shadow-sm rounded-t-lg font-semibold">
                CITY LIST
            </div>
            <hr class="dark:border-gray-200">
            <div class="p-4 rounded-b-lg bg-white dark:bg-gray-600 overflow-auto shadow-sm max-h-160">
                <div class="px-2 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                    @foreach($cities as $city)
                    <a href="{{ route('suppliers.tbo.hotel-list', ['cityCode' => $city['Code']]) }}" class="p-2 bg-gradient-to-r from-gray-800 to-gray-500 dark:to-blue-600 rounded-md text-center text-white w-full">{{ $city['Name'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>