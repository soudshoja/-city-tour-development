<x-app-layout>

    <div class="grid grid-cols-3 gap-4 p-8">
        <!-- First Div: Add New Company -->
        <div class="col-span-2 bg-white shadow-lg rounded-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold mb-4">Add New Company</h2>
                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                    <img src="{{ asset('images/registeruser.jpg') }}" alt="User Registration"
                        class="w-full h-full object-cover rounded-full" />
                </div>

            </div>

            <form method="POST" action="{{ route('companies.store') }}" class="p-2 bg-gray-200 rounded-lg">
                @csrf

                <!-- company name -->
                <input type="text" name="name" placeholder="Company Name"
                    class="mb-5 w-full border border-gray-300 rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-gray-400" />

                <!-- company email  -->
                <input type="email" name="email" placeholder="Company email"
                    class="mb-5 w-full border border-gray-300 rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-gray-400" />

                <!-- code and country  -->
                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div class="mb-6">
                        <input type="text" name="code" placeholder="Company Code"
                            class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400"
                            required>
                    </div>

                    <div class="mb-6">
                        <select id="nationality_id" name="nationality_id"
                            class=" form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400"
                            required>
                            <option value="" disabled selected>Select a country</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <!-- company info -->
                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <input type="text" name="address" placeholder="Address"
                            class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400">
                    </div>
                    <div>
                        <input type="text" name="phone" placeholder="Phone"
                            class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400">
                    </div>
                </div>

                <!-- Set Password -->
                <h2 class="text-lg font-semibold mb-4 mt-8">Set Password</h2>

                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <input type="password" name="password" placeholder="password"
                            class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400"
                            required>
                    </div>
                    <div>
                        <input type="password" name="password_confirmation" placeholder="confirm password"
                            class="form-control w-full rounded-full border border-gray-300 p-3 focus:outline-none focus:ring-2 focus:ring-gray-400"
                            required>
                    </div>
                </div>
                <!-- status -->

                <div class="mb-6">

                    <div class="flex flex-col">
                        <div class="flex items-center space-x-4">
                            <label class="text-lg font-semibold mb-2">Select a status:</label>

                            <!-- Active Radio Button -->
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="status" value="1" class="status-radio peer hidden"
                                    id="active" />
                                <span
                                    class="flex items-center justify-center w-6 h-6 border border-gray-500 rounded-full peer-checked:border-[#00ab55] peer-checked:bg-[#00ab55] peer-checked:text-white peer-checked:font-semibold">
                                    <span class="w-3 h-3 bg-transparent rounded-full"></span>
                                </span>
                                <span
                                    class="ml-2 text-lg text-gray-700 peer-checked:text-[#00ab55] peer-checked:font-semibold">Active</span>
                            </label>

                            <!-- Inactive Radio Button -->
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="status" value="0" class="status-radio peer hidden"
                                    id="inactive" />
                                <span
                                    class="flex items-center justify-center w-6 h-6 border border-gray-500 rounded-full peer-checked:border-[#e7515a] peer-checked:bg-[#e7515a] peer-checked:text-white peer-checked:font-semibold">
                                    <span class="w-3 h-3 bg-transparent rounded-full"></span>
                                </span>
                                <span
                                    class="ml-2 text-lg text-gray-700 peer-checked:text-[#e7515a] peer-checked:font-semibold">Inactive</span>
                            </label>
                        </div>
                    </div>




                </div>

                <div class="flex items-center justify-between mt-8">
                    <button type="submit"
                        class="justify-center text-center text-black CirtbgYellow hover:bg-[#F7BE38]/90 focus:ring-4 focus:outline-none focus:ring-[#F7BE38]/50 font-medium rounded-lg px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-[#F7BE38]/50 City-me-2 mb-2 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                        Add Company
                    </button>
                </div>
                <!-- ./Registration Form -->




            </form>
        </div>

        <!-- Second Div: Company Settings
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-2xl font-semibold mb-4">Company Settings</h2>
        </div>
        -->
    </div>

</x-app-layout>
