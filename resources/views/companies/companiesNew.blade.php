<x-app-layout>

    <!-- Form and Image Section -->
    <div class="flex justify-center items-center">
        <div
            class="mt-20  flex flex-col lg:flex-row justify-between items-stretch w-full max-w-6xl bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">

            <!-- Image Section -->
            <div class="w-full lg:w-2/5 h-96 lg:h-auto">
                <img src="{{ asset('images/registeruser.jpg') }}" alt="User Registration"
                    class="w-full h-full object-cover" />
            </div>

            <!-- Form Section -->
            <div class="w-full lg:w-3/5 p-8 flex items-center justify-center">
                <div class="w-full">
                    <h2 class="text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">Add New
                        Company</h2>

                    <!-- Registration Form -->
                    <form method="POST" action="{{ route('companies.store') }}">
                        @csrf

                        <!-- Name Field -->
                        <div class="mb-4">
                            <label for="name"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                            <input id="name" name="name" type="text" :value="old('name')" required autofocus
                                autocomplete="name"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Name" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Email Field -->
                        <div class="mb-4">
                            <label for="email"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                            <input id="email" type="email" name="email" :value="old('email')" required
                                autocomplete="username"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Email" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <!-- phone Field -->
                        <div class="mb-4">
                            <label for="phone"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone
                                Number</label>
                            <input id="phone" name="phone" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Contact" />
                        </div>
                        <!-- country Field -->
                        <div class="mb-4">
                            <label for="nationality"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Country
                            </label>
                            <input id="nationality" name="nationality" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company country" />
                        </div>
                        <!-- Address Field -->
                        <div class="mb-4">
                            <label for="address"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Address</label>
                            <input id="address" name="address" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Address" />
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <!-- iata Field -->
                        <div class="mb-4">
                            <label for="iata_status"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Does this company
                                has an
                                IATA ID?</label>
                            <div class="flex items-center">
                                <input type="radio" id="iata_yes" name="iata_status" value="yes" class="mr-2"
                                    onclick="toggleIataInput(true)">
                                <label for="iata_yes" class="mr-4 text-gray-700 dark:text-gray-300">Yes</label>
                                <input type="radio" id="iata_no" name="iata_status" value="no" class="mr-2"
                                    onclick="toggleIataInput(false)">
                                <label for="iata_no" class="text-gray-700 dark:text-gray-300">No</label>
                            </div>
                        </div>

                        <div class="mb-4" id="iata_input" style="display: none;">
                            <label for="iata" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">IATA
                                ID</label>
                            <input id="iata" name="iata" type="text"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Enter IATA ID" />
                        </div>

                        <script>
                        function toggleIataInput(show) {
                            var iataInput = document.getElementById('iata_input');
                            if (show) {
                                iataInput.style.display = 'block';
                            } else {
                                iataInput.style.display = 'none';
                            }
                        }
                        </script>
                        <!-- Commercial License Field -->
                        <div class="mb-4">
                            <label for="commercial_license"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">
                                Commercial License (PDF)
                            </label>
                            <!-- Hidden File Input -->
                            <input id="commercial_license" name="commercial_license" type="file"
                                accept="application/pdf" class="hidden">
                            <!-- Custom Button to Trigger File Input -->
                            <div class="flex items-center">
                                <button type="button" onclick="document.getElementById('commercial_license').click()"
                                    class="flex items-center px-4 py-2 border border-gray-300 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Choose file...
                                </button>
                                <span id="file-name" class="ml-3 text-sm text-gray-500">No file chosen</span>
                            </div>
                            <x-input-error :messages="$errors->get('commercial_license')" class="mt-2" />
                        </div>

                        <script>
                        document.getElementById('commercial_license').addEventListener('change', function() {
                            const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                            document.getElementById('file-name').textContent = fileName;
                        });
                        </script>






                        <!-- Already Registered Link -->
                        <div class="flex items-center justify-between mt-4">

                            <!-- Submit Button -->
                            <x-primary-button class="px-8">
                                {{ __('Register') }}
                            </x-primary-button>


                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
    function togglePasswordVisibility() {
        var passwordField = document.getElementById('password');
        var eyeIcon = document.getElementById('eyeIcon');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            // Change the icon to indicate password is visible
        } else {
            passwordField.type = 'password';
            // Change the icon back to indicate password is hidden
        }
    }
    </script>
</x-app-layout>