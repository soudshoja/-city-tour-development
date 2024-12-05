<x-guest-layout>
    <div class="bg-slate-400 h-screen flex items-center justify-center">
        <div class="grid grid-cols-1 lg:grid-cols-2 rounded-lg items-stretch justify-center">
            <!-- Left Side - Form -->
            <div class="w-full p-8 bg-primary text-white flex flex-col justify-center items-center mx-auto rounded-md custom-rounded-left">
                <h2 class="text-center text-2xl font-semibold mb-4 w-3/4 max-w-sm">Hey Pal!</h2>
                <p class="text-center text-sm mb-8 w-3/4 max-w-sm">We're excited to have you onboard...</p>
                <form id="adminForm" method="POST" action="{{ route('register.admin') }}" class="w-full flex flex-col items-center">
                    @csrf
                    <!-- Name -->
                    <div class="relative text-white-dark mb-4 w-3/4 max-w-sm">
                        <input id="name" name="name" type="text" placeholder="Your Name"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            :value="old('name')" required autofocus />
                    </div>

                    <!-- Email -->
                    <div class="relative text-white-dark mb-4 w-3/4 max-w-sm">
                        <input id="email" type="email" name="email" placeholder="Your Email"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            :value="old('email')" required />
                    </div>

                    <!-- Password -->
                    <div class="relative text-white-dark mb-4 w-3/4 max-w-sm">
                        <input id="password" type="password" name="password" placeholder="Your Password"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>

                    <!-- Confirm Password -->
                    <div class="relative text-white-dark mb-4 w-3/4 max-w-sm">
                        <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm Password"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="my-2" />

                    <!-- Register Button -->
                    <div class="mb-4 w-3/4 max-w-sm">
                        <button type="submit"
                            class="text-black w-full bg-[#F7BE38] hover:bg-[#F7BE38]/80 font-medium rounded-lg px-5 py-2.5 shadow">
                            {{ __('Register') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Side - Image -->
            <div class="flex items-center justify-center bg-white rounded-lg lg:rounded-none custom-rounded-right">
                <img src="{{ asset('images/registerPic.svg') }}" alt="city app" class="-mt-5 object-contain max-h-[500px]">
            </div>
        </div>

    </div>


</x-guest-layout>