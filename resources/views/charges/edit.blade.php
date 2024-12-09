<x-app-layout>
    <div class="container mx-auto p-6">
        <!-- Page Title -->
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Edit Charges Details</h1>

        <!-- Edit Form -->
        <div class="bg-white shadow-md rounded-lg p-8">
            <form action="{{ route('charges.update', $charge->id) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Name -->
                <div class="mb-6">
                    <label for="name" class="block text-gray-700 font-semibold mb-2">Charge Name</label>
                    <input type="text" name="name" id="name" value="{{ $charge->name }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Email -->
                <div class="mb-6">
                    <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
                    <input type="description" name="description" id="description" value="{{ $charge->description }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Phone Number -->
                <div class="mb-6">
                    <label for="type" class="block text-gray-700 font-semibold mb-2">Type</label>
                    <input type="text" name="type" id="type" value="{{ $charge->type }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <div class="mb-6">
                    <label for="amount" class="block text-gray-700 font-semibold mb-2">Amount</label>
                    <input type="number" name="amount" id="amount" value="{{ $charge->amount }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Submit Button -->
                <div class="text-right">
                    <button type="submit"
                        class="bg-indigo-500 text-white py-2 px-6 rounded-lg shadow hover:bg-indigo-600 transition duration-200">
                        Update charge
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
