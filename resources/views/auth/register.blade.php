<x-guest-layout>
    <div class="bg-slate-400 h-screen flex items-center justify-center">
        <div class="rounded-lg flex items-stretch w-[80%] justify-center">
            <!-- Role Selection -->
            <div id="roleSelectionDiv" class="w-full lg:w-1/2 p-8 bg-primary text-white flex flex-col justify-center items-center mx-auto rounded-md custom-rounded-left">
                <h2 class="text-center text-2xl font-semibold mb-4 text-left w-3/4 max-w-sm">Hey There!</h2>
                <p class="text-center text-sm mb-8 text-left w-3/4 max-w-sm">What Role Would You Like To Register For Today?</p>
                <div class="flex justify-center space-x-4">
                    <button onclick="showForm('admin')"
                        class="justify-center text-center text-gray-700 hover:text-white bg-[#F7BE38] hover:bg-[#F7BE38]/80 focus:ring-4 focus:outline-none focus:ring-[#F7BE38]/50 font-medium rounded-lg px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-[#F7BE38]/50 mb-2 w-32 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                        Admin
                    </button>
                    <button onclick="showForm('company')"
                        class="justify-center text-center text-gray-700 hover:text-white bg-[#F7BE38] hover:bg-[#F7BE38]/80 focus:ring-4 focus:outline-none focus:ring-[#F7BE38]/50 font-medium rounded-lg px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-[#F7BE38]/50 mb-2 w-32 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                        Company
                    </button>
                </div>
                <p class="text-center text-sm mt-8 text-left w-3/4 max-w-sm">Already have an account? <a href="{{ route('login') }}" class="text-[#F7BE38] underline pl-1">Login Here</a></p>
            </div>

            <!-- Admin Form -->
            <div id="adminFormDiv" class="hidden w-full p-8 bg-primary text-white flex flex-col justify-center items-center mx-auto rounded-md custom-rounded-left">
                <h2 class="text-center text-2xl font-semibold mb-4 text-left w-3/4 max-w-sm">Join us today!</h2>
                <p class="text-center text-sm mb-8 text-left w-3/4 max-w-sm">We're excited to have you onboard...</p>
                <form id="adminForm" method="POST" action="{{ route('register.admin') }}" class="w-full flex flex-col items-center">
                    @csrf
                    <input type="hidden" name="role_id" value="admin">
                    <!-- Name -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="name" name="name" type="text" placeholder="Your Name"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            :value="old('name')" required autofocus />
                    </div>
                    <x-input-error :messages="$errors->get('name')" class="my-2" />

                    <!-- Email -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="email" type="email" name="email" placeholder="Your Email"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            :value="old('email')" required />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="my-2" />

                    <!-- Password -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="password" type="password" name="password" placeholder="Your Password"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="my-2" />

                    <!-- Confirm Password -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm Password"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="my-2" />
                    <!-- Register Button -->
                    <div class="mb-4 w-3/4 max-w-sm">
                        <button type="submit"
                            class="w-full bg-[#F7BE38] hover:bg-[#F7BE38]/80 font-medium rounded-lg px-5 py-2.5 shadow">
                            {{ __('Register') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Company Form -->
            <div id="companyFormDiv" class="hidden w-full lg:w-1/2 p-8 bg-primary text-white flex flex-col justify-center items-center mx-auto rounded-md custom-rounded-left">
                <h2 class="text-center text-2xl font-semibold mb-4 text-left w-3/4 max-w-sm">Company Registration</h2>
                <form id="companyForm" method="POST" action="{{ route('register.company') }}" class="w-full flex flex-col items-center">
                    @csrf
                    <input type="hidden" name="role_id" value="company">
                    <!-- Company Name -->
                    <!-- Name -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="name" name="name" type="text" placeholder="Your Name"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            :value="old('name')" required autofocus />
                    </div>
                    <x-input-error :messages="$errors->get('name')" class="my-2" />

                    <!-- Email -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="email" type="email" name="email" placeholder="Your Email"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            :value="old('email')" required />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="my-2" />

                    <!-- Password -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="password" type="password" name="password" placeholder="Your Password"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="my-2" />

                    <!-- Confirm Password -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm Password"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="my-2" />

                    <!-- Company Code -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="companyCode" name="code" type="text" placeholder="Company Code"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500">
                    </div>
                    <!-- Company Address -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <input id="address" name="address" type="text" placeholder="Company Address"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <!-- Company country -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <select id="nationality" name="nationality_id" required
                            class="block w-full py-3 px-4 text-gray-700 bg-white border border-gray-300 rounded-md">
                            <option value="" disabled selected>Choose Nationality</option>
                            @foreach($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <!-- Company Status -->
                    <div class="relative text-white-dark mb-2 w-3/4 max-w-sm">
                        <select id="status" name="status" required
                            class="block w-full py-3 px-4 text-gray-700 bg-white border border-gray-300 rounded-md">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <!-- Register Button -->

                    <div class="mb-4 w-3/4 max-w-sm">
                        <button type="submit"
                            class="w-full bg-[#F7BE38] hover:bg-[#F7BE38]/80 font-medium rounded-lg px-5 py-2.5 shadow">
                            {{ __('Register') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Side - Image -->
            <div class="hidden sm:rounded-lg lg:flex w-full lg:w-1/2 bg-white items-center justify-center custom-rounded-right">
                <img src="{{ asset('images/registerPic.svg') }}" alt="city app" class="-mt-10 object-contain max-h-[500px]">
            </div>
        </div>
    </div>

    <script>
        function showForm(role) {
            // Hide all forms and show the selected form
            document.getElementById('roleSelectionDiv').classList.add('hidden');
            document.getElementById('adminFormDiv').classList.add('hidden');
            document.getElementById('companyFormDiv').classList.add('hidden');

            if (role === 'admin') {
                document.getElementById('adminFormDiv').classList.remove('hidden');
            } else if (role === 'company') {
                document.getElementById('companyFormDiv').classList.remove('hidden');
            }
        }
    </script>
</x-guest-layout>