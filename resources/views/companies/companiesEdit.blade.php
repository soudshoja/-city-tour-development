<x-app-layout>
    <div class="container mx-auto p-6">
        <!-- Page Title -->
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Edit company Details</h1>

        <!-- Edit Form -->
        <div class="bg-white shadow-md rounded-lg p-8">
            <form action="{{ route('companies.update', $Company->id) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Name -->
                <div class="mb-6">
                    <label for="name" class="block text-gray-700 font-semibold mb-2">Name</label>
                    <input type="text" name="name" id="name" value="{{ $Company->name }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Email -->
                <div class="mb-6">
                    <label for="email" class="block text-gray-700 font-semibold mb-2">Code</label>
                    <input type="text" name="code" id="code" value="{{ $Company->code }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>


                <!-- Type -->
                <div class="mb-6">
                    <label for="nationality" class="block text-gray-700 font-semibold mb-2">Nationality</label>
                    <select name="nationality" id="nationality" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        <option value="Malaysia" {{ $Company->type == 'Malaysia' ? 'selected' : '' }}>Malaysia</option>
                        <option value="Kuwait" {{ $Company->type == 'Kuwait' ? 'selected' : '' }}>Kuwait</option>
                        <option value="Indonesia" {{ $Company->type == 'Indonesia' ? 'selected' : '' }}>Indonesia</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="text-right">
                    <button type="submit"
                        class="bg-indigo-500 text-white py-2 px-6 rounded-lg shadow hover:bg-indigo-600 transition duration-200">
                        Update company
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
