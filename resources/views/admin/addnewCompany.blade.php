<x-app-layout>

    <div class="bg-white shadow-lg rounded-lg p-8 w-full text-center">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold mb-4">Add New Company</h2>
            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center ">
                <img src="{{ asset('images/registeruser.jpg') }}" alt="User Registration"
                    class="w-full h-full object-cover rounded-full" />
            </div>
        </div>
        <form method="POST" action="{{ route('companies.store') }}" class="p-2">
            @csrf

            <div class="mb-6">
                <input
                    type="text" name="name" id="name" placeholder="Company Name"
                    class="mb-5 w-full border border-gray-300 rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400" />

                <input
                    type="email" name="email" id="email" placeholder="Company email"
                    class="mb-5 w-full border border-gray-300 rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400" />


                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div class="mb-6">
                        <label for="code" class="block text-gray-700 text-sm font-medium mb-2">Company Code</label>
                        <input type="text" name="code" id="code" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                    </div>

                    <div class="mb-6">
                        <label for="nationality_id" class="block text-gray-700 text-sm font-medium mb-2">Select Country</label>
                        <select name="nationality_id" id="nationality_id" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                            <option value="" disabled selected>Select a country</option>
                            @foreach($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="address" class="block text-gray-700 text-sm font-medium mb-2">Address</label>
                        <input type="text" name="address" id="address" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label for="phone" class="block text-gray-700 text-sm font-medium mb-2">Phone</label>
                        <input type="text" name="phone" id="phone" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500">
                    </div>
                </div>
                <!-- Set Password -->
                <h2 class="text-lg font-semibold mb-4 mt-8">Set Password</h2>

                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="password" class="block text-gray-700 text-sm font-medium mb-2">Password</label>
                        <input type="password" name="password" id="password" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-gray-700 text-sm font-medium mb-2">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                    </div>
                </div>

                <!-- status -->
                <div class="mb-6">
                    <label for="status" class="block text-gray-700 text-sm font-medium mb-2">Status</label>
                    <select name="status" id="status" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                        <option value="" disabled selected>Select a status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div class="flex items-center justify-between mt-8">
                    <button type="submit"
                        class="justify-center text-center text-gray-700 CirtbgYellow hover:bg-[#F7BE38]/90 focus:ring-4 focus:outline-none focus:ring-[#F7BE38]/50 font-medium rounded-lg px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-[#F7BE38]/50 City-me-2 mb-2 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                        Add Company
                    </button>
                </div>
                <!-- ./Registration Form -->

            </div>
        </form>

    </div>

</x-app-layout>