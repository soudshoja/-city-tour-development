<x-guest-layout>
    <!-- Form and Image Section -->
    <div class="bg-slate-400 h-screen flex items-center justify-center">
        <div
            class=" mt-20 panel flex flex-col lg:flex-row justify-between items-stretch w-full max-w-6xl bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">

            <!-- Image Section -->
            <div class="w-full lg:w-2/5 h-96 lg:h-auto">
                <img src="{{ asset('images/registeruser.jpg') }}" alt="User Registration"
                    class="w-full h-full object-cover" />
            </div>

            <!-- Form Section -->
            <div class="w-full lg:w-3/5 p-8 flex items-center justify-center">
                <div class="w-full">
                    <h2 class="text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">Register New
                        User</h2>

                    <!-- Registration Form -->
                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        <!-- Name Field -->
                        <div class="mb-4">
                            <label for="name"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                            <input id="name" name="name" type="text" :value="old('name')" required autofocus
                                autocomplete="name"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Your Name" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Email Field -->
                        <div class="mb-4">
                            <label for="email"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                            <input id="email" type="email" name="email" :value="old('email')" required
                                autocomplete="username"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Your Email" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <!-- Password Field -->
                        <div class="mb-4 relative">
                            <label for="password"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Password</label>

                            <input id="password" type="password" name="password" required autocomplete="new-password"
                                class="shadow appearance-none border rounded w-full py-2 pr-10 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Your Password" />

                            <!-- Eye Icon for Toggle -->
                            <span class="mt-4 absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer"
                                onclick="togglePasswordVisibility()">
                                <svg id="eyeIcon" class="blackCity" width="18" height="18" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>

                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>



                        <!-- Confirm Password Field -->
                        <div class="mb-4">
                            <label for="password_confirmation"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Confirm
                                Password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required
                                autocomplete="new-password"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Confirm Your Password" />

                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>

                        <!-- Already Registered Link -->
                        <div class="flex items-center justify-between mt-4">


                            <!-- Submit Button -->
                            <button
                                class="w-full px-8 py-2 bg-black text-white font-bold rounded hover:bg-blue-700 focus:outline-none focus:shadow-outline">
                                {{ __('Register') }}
                            </button>


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
</x-guest-layout>