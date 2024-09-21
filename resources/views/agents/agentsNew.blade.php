<x-app-layout>
     <!-- First Section: Tips and Breadcrumbs -->
     <div class="flex justify-center items-center mt-10">
        <div class="flex flex-col w-full max-w-6xl bg-white dark:bg-gray-800 shadow-lg rounded-lg p-8 space-y-6">

            <!-- Title -->
            <h2 class="text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">Here are some tips to
                add a new agent to
                City App...</h2>

            <!-- Description -->
            <p class="text-gray-600 dark:text-gray-400">
                This is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's
                standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it
                to make
                a type specimen book.
            </p>

            <!-- Breadcrumb Section -->
            <div class="flex w-full space-x-4">
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
                        <span class="ml-2 font-semibold">Ask the agent IT team to use the code you have entered to
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
    <div class="flex justify-center items-center mt-10">
        <div
            class="flex flex-col lg:flex-row justify-between items-stretch w-full max-w-6xl bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">
            <!-- Image Section -->
            <div class="w-full lg:w-2/5 h-96 lg:h-auto">
                <img src="{{ asset('images/TravelAgencyImage.png') }}" alt="agent Registration"
                    class="w-full h-full object-cover" />
            </div>   
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
 class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
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
 class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
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
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Phone Number"
                    />
                </div>

                <!-- agent -->
                <div class="mb-4">
                    <label for="agent_id" class="block text-gray-700 text-sm font-bold mb-2">agent</label>
                    <select
                        id="agent_id"
                        name="agent_id"
                        required
 class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    >
                        <option value="1">agent A</option>
                        <option value="2">agent B</option>
                        <option value="3">agent C</option>
                    </select>
                </div>

                <!-- Type -->
                <div class="mb-4">
                    <label for="type" class="block text-gray-700 text-sm font-bold mb-2">Type</label>
                    <select
                        id="type"
                        name="type"
                        required
 class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    >
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-center">
                            <button type="submit"
                            class="p-2 btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                                    Register Agent
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
         </div>
 </div>
</x-app-layout>
