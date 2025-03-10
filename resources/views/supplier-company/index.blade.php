<x-app-layout>
    <nav>
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <a href="{{ route('suppliers.index') }}" class="customBlueColor hover:underline">Supplier List</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>{{ $supplier->name }}</span>
            </li>
        </ul>
    </nav>
    <div class="p-2 bg-white rounded shadow">
        @foreach($companies as $company)
        <div class="flex justify-between items-center p-2 border-b border-gray-200">
            <div>
                <h2 class="text-lg font-semibold">{{ $company->name }}</h2>
                <p class="text-sm text-gray-500">{{ $company->address }}</p>
            </div>
            <div>
                @if($company->suppliers->isNotEmpty())
                <a href="{{ route('supplier-company.deactivate', [$supplier->id ,$company->id]) }}" class="p-2 bg-red-500 hover:bg-red-600 rounded shadow text-white">Deactivate</a>
                @else
                <a href="{{ route('supplier-company.activate', [$supplier->id, $company->id]) }}" class="p-2 bg-green-500 hover:bg-green-600 rounded shadow text-white">activate</a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</x-app-layout>