<x-app-layout>
    <div class="flex items-start justify-center min-h-screen bg-gray-100">
        <div class="text-center bg-white p-10 shadow-lg rounded-lg mt-10">
            <h1 class="text-3xl font-bold text-red-600">🚧 Under Maintenance 🚧</h1>
            <p class="text-gray-700 mt-4">We are currently performing maintenance on this page.</p>
            <p class="text-gray-700">Please check back later.</p>

            <div class="mt-5">
                <a href="{{ url('/') }}" class="px-5 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Go to Homepage
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
