<x-app-layout>
    <div class="container mx-auto p-6">
        <!-- Page Title -->
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Edit Agent Details</h1>

        <!-- Edit Form -->
        <div class="bg-white shadow-md rounded-lg p-8">
            <form action="{{ route('agents.update', $agent->id) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Name -->
                <div class="mb-6">
                    <label for="name" class="block text-gray-700 font-semibold mb-2">Name</label>
                    <input type="text" name="name" id="name" value="{{ $agent->name }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Email -->
                <div class="mb-6">
                    <label for="email" class="block text-gray-700 font-semibold mb-2">Email</label>
                    <input type="email" name="email" id="email" value="{{ $agent->email }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Phone Number -->
                <div class="mb-6">
                    <label for="phone_number" class="block text-gray-700 font-semibold mb-2">Phone Number</label>
                    <input type="text" name="phone_number" id="phone_number" value="{{ $agent->phone_number }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Company -->
                <div class="mb-6">
                    <label for="branch_id" class="block text-gray-700 font-semibold mb-2">Branch</label>
                    <select name="branch_id" id="branch_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ $agent->branch_id == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Type -->
                <div class="mb-6">
                    <label for="type" class="block text-gray-700 font-semibold mb-2">Type</label>
                    <select name="type" id="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        <option value="staff" {{ $agent->type == 'staff' ? 'selected' : '' }}>Staff</option>
                        <option value="manager" {{ $agent->type == 'manager' ? 'selected' : '' }}>Manager</option>
                        <option value="admin" {{ $agent->type == 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="text-right">
                    <button type="submit"
                        class="bg-black text-white py-2 px-6 rounded-lg shadow hover:bg-indigo-600 transition duration-200">
                        Update Agent
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>