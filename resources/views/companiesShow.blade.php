<x-app-layout>
    <div class="container mx-auto p-6">
        <!-- company Info Section -->
        <div class="bg-white shadow-md rounded-lg p-8 mb-8">
            <h1 class="text-3xl font-bold text-gray-700 mb-6">Company Detail</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Name</h2>
                    <p class="text-gray-800">{{ $Company->name }}</p>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Code</h2>
                    <p class="text-gray-800">{{ $Company->code }}</p>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Country</h2>
                    <p class="text-gray-800 capitalize">{{ $Company->nationality }}</p>
                </div>
            </div>

            <div class="mt-8 text-right">
                <a href="{{ route('companies.edit', $Company->id) }}" class="bg-blue-500 text-white py-2 px-6 rounded-lg shadow hover:bg-blue-600 transition duration-200">Update Details</a>
            </div>
        </div>

    </div>
</x-app-layout>
