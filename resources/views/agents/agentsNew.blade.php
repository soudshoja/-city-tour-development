<x-app-layout>

    <!-- Second Section: Form and Image -->
    <div class="flex justify-center items-center">
        <div
            class="mt-5 panel p-0 flex flex-col lg:flex-row justify-between items-stretch w-full max-w-4xl bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">

            <!-- Image Section -->
            <div class="w-full lg:w-2/5 h-80 lg:h-auto">
                <img src="{{ asset('images/registeruser.jpg') }}" alt="" class="w-full h-full object-cover" />
            </div>

            <!-- Form Section -->
            <div class="w-full lg:w-3/5 p-8 flex items-center justify-center">
                <div class="w-full">
                    <h2 class="text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">Register New
                        Agent</h2>

                    <!-- Registration Form -->
                    <form method="POST" action="{{ route('agents.store') }}">
                        @csrf
                        <!-- Name Field -->
                        <div class="mb-4">
                            <label for="name"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                            <input id="name" name="name" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Agent Name" />
                        </div>

                        <!-- Email Address -->
                        <div class="mb-4">
                            <label for="email"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                            <input id="email" name="email" type="email" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Agent Email" />
                        </div>

                        <!-- Phone Field -->
                        <div class="mb-4">
                            <label for="phone_number"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone
                                Number</label>
                            <input id="phone_number" name="phone_number" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Phone Number" />
                        </div>

                        @if(Auth()->user()->role === 'admin')
                        <!-- Company Selection -->
                        <div class="mb-4">
                            <label for="company_id"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Company</label>
                            <select id="company_id" name="company_id" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="" disabled selected>Select a company</option>
                                @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <!-- Agent Type -->
                        <div class="mb-4">
                            <label for="type"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Type</label>
                            <select id="type" name="type" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="staff">Staff</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <!-- Already Registered Link -->
                        <div class="flex items-center justify-between mt-4">
                            <!-- Submit Button -->
                            <x-primary-button>
                                {{ __('Register') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>





</x-app-layout>