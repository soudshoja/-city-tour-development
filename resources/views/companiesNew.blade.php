<x-app-layout>
    <div class="flex justify-center mt-10">
        <div class="w-full max-w-lg bg-white shadow-md rounded-lg p-8">
            <h2 class="text-2xl font-semibold text-gray-700 text-center mb-6">Register New Company</h2>

            <form method="POST" action="{{ route('companies.store') }}">
                @csrf

                <!-- Name -->
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Company Name"
                    />
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <label for="code" class="block text-gray-700 text-sm font-bold mb-2">Code</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Company Code"
                    />
                </div>

                <!-- Type -->
                <div class="mb-4">
                    <label for="nationality" class="block text-gray-700 text-sm font-bold mb-2">Nationality</label>
                    <select
                        id="nationality"
                        name="nationality"
                        required
                        class="block appearance-none w-full bg-white border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded leading-tight focus:outline-none focus:shadow-outline"
                    >
                        <option value="Malaysia">Malaysia</option>
                        <option value="Kuwait">Kuwait</option>
                        <option value="Indonesia">Indonesia</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-center">
                    <button
                        type="submit"
                        class="bg-green-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                    >
                        Register Company
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
