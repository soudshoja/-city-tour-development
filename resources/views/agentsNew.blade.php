<x-app-layout>
    <div class="flex justify-center mt-10">
        <div class="w-full max-w-lg bg-white shadow-md rounded-lg p-8">
            <h2 class="text-2xl font-semibold text-gray-700 text-center mb-6">Register New Travel Agent</h2>

            <form method="POST" action="{{ route('agents.store') }}">
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
                        placeholder="Agent Name"
                    />
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Agent Email"
                    />
                </div>

                <!-- Phone Number -->
                <div class="mb-4">
                    <label for="phone_number" class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                    <input
                        id="phone_number"
                        name="phone_number"
                        type="text"
                        required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Phone Number"
                    />
                </div>

                <!-- Company -->
                <div class="mb-4">
                    <label for="company_id" class="block text-gray-700 text-sm font-bold mb-2">Company</label>
                    <select
                        id="company_id"
                        name="company_id"
                        required
                        class="block appearance-none w-full bg-white border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded leading-tight focus:outline-none focus:shadow-outline"
                    >
                        <option value="1">Company A</option>
                        <option value="2">Company B</option>
                        <option value="3">Company C</option>
                    </select>
                </div>

                <!-- Type -->
                <div class="mb-4">
                    <label for="type" class="block text-gray-700 text-sm font-bold mb-2">Type</label>
                    <select
                        id="type"
                        name="type"
                        required
                        class="block appearance-none w-full bg-white border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded leading-tight focus:outline-none focus:shadow-outline"
                    >
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-center">
                    <button
                        type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                    >
                        Register Agent
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
