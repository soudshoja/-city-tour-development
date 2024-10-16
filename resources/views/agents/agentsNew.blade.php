<x-app-layout>

    <!-- First Section: Tips and Breadcrumbs -->
    <div class="flex justify-center items-center mt-5 px-4 md:px-0">
        <div
            class="flex flex-col w-full max-w-6xl bg-white dark:bg-gray-800 shadow-lg rounded-lg p-4 md:p-8 space-y-4 md:space-y-6">

            <!-- Title -->
            <h2 class="text-xl sm:text-lg font-semibold text-gray-700 dark:text-gray-200">
                Here are some tips to add a new agent to <span class="text-primary">City Travelers App</span> ...
            </h2>

            <!-- Description -->
            <p class="text-sm md:text-base text-gray-600 dark:text-gray-400">
                Adding an agent to the City Travelers app will enable them to fully automate their activities, manage
                their clients, and much more!

            </p>

            <!-- Breadcrumb Section -->
            <div class="flex flex-col md:flex-row w-full space-y-4 md:space-y-0 md:space-x-4 text-sm">
                <!-- First Breadcrumb -->
                <div
                    class="flex items-center bg-[#e3e7fc] dark:bg-gray-700 text-black dark:text-white py-2 px-4 rounded-lg">
                    <div class="flex items-center">
                        <span
                            class="flex items-center justify-center w-6 h-6 bg-[#888ea8] dark:bg-gray-500 rounded-full">
                            <svg class="w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round"></path>
                                <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor"
                                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </span>
                        <span class="ml-2 font-semibold">Add agent details here</span>
                    </div>
                </div>

                <!-- Second Breadcrumb -->
                <div
                    class="flex items-center bg-[#deeffd] dark:bg-gray-700 text-black dark:text-white py-2 px-4 rounded-lg">
                    <div class="flex items-center">
                        <span
                            class="flex items-center justify-center w-6 h-6 bg-[#888ea8] dark:bg-gray-500 rounded-full">
                            <svg class="w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round"></path>
                                <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor"
                                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </span>
                        <span class="ml-2 font-semibold">Ask the agent to use the Email you have entered to
                            access the system</span>
                    </div>
                </div>

                <!-- Third Breadcrumb -->
                <div
                    class="flex items-center bg-[#d9f2e6] dark:bg-gray-700 text-black dark:text-white py-2 px-4 rounded-lg">
                    <div class="flex items-center">
                        <span
                            class="flex items-center justify-center w-6 h-6 bg-[#888ea8] dark:bg-gray-500 rounded-full">
                            <svg class="w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round"></path>
                                <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor"
                                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </span>
                        <span class="ml-2 font-semibold">You All Set!</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Second Section: Form and Image -->
    <div class="flex justify-center items-center">
        <div
            class="mt-5 panel flex flex-col lg:flex-row justify-between items-stretch w-full max-w-6xl bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">

            <!-- Image Section -->
            <div class="w-full  h-96 lg:h-auto">
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
                            <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                            <input id="name" name="name" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Agent Name" />
                        </div>

                        <!-- Email Address -->
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                            <input id="email" name="email" type="email" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Agent Email" />
                        </div>




                        <!-- phone Field -->
                        <div class="mb-4">
                            <label for="phone_number" class="block text-gray-700 text-sm font-bold mb-2">Phone
                                Number</label>
                            <input id="phone_number" name="phone_number" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Phone Number" />
                        </div>


                        @if(Auth()->user()->role === 'admin')
                        <!-- agent -->
                        <div class="mb-4">
                            <label for="agent_id" class="block text-gray-700 text-sm font-bold mb-2">Company</label>
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
                            <label for="type" class="block text-gray-700 text-sm font-bold mb-2">Type</label>
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
                            <x-primary-button class="px-8">
                                {{ __('Register') }}
                            </x-primary-button>

                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



</x-app-layout>