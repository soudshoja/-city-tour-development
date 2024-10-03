<x-app-layout>

    @if (session('success') || session('error'))
    <div id="flash-message" class="alert 
                @if (session('success')) alert-success 
                @elseif (session('error')) alert-danger 
                @endif
                fixed-top-right">
        {{ session('success') ?? session('error') }}
    </div>
    @endif
    <!-- Second Section: Form -->
    <div class="flex justify-center items-center mt-10 px-4 md:px-0 mb-5">
        <div class="flex flex-col w-full max-w-6xl bg-white dark:bg-gray-800 shadow-lg rounded-lg p-4 md:p-8">
            <h2 class="text-2xl md:text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">
                Register New Client
            </h2>

            <form method="POST" action="{{ route('clients.store') }}">
                @csrf

                <!-- Name Field -->
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                    <input id="name" name="name" type="text" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Client Name" />
                </div>

                <!-- Email Field -->
                <div class="mb-4">
                    <label for="email"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                    <input id="email" name="email" type="email" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Client Email" />
                </div>

                <!-- Phone Field -->
                <div class="mb-4">
                    <label for="phone"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone</label>
                    <input id="phone" name="phone" type="text" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Client Phone" />
                </div>

                <!-- Address Field -->
                <div class="mb-4">
                    <label for="address"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Address</label>
                    <input id="address" name="address" type="text"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Client Address" />
                </div>

                <!-- Address Field -->
                <div class="mb-4">
                    <label for="passport_no"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Passport Number</label>
                    <input id="passport_no" name="passport_no" type="text" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Passport Number" />
                </div>

               <!-- Email Field -->
                 <div class="mb-4">
                    <label for="agent_email"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Agent Email</label>
                    <input id="agent_email" name="agent_email" type="email" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Agent Email" />
                </div>

                <!-- Status Field -->
                <div class="mb-4">
                    <label for="status"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Status</label>
                    <select id="status" name="status"
                        class="block appearance-none w-full bg-white dark:bg-gray-700 border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded leading-tight focus:outline-none focus:shadow-outline">
                        <option value="1">Active</option>
                        <option value="2">Inactive</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-center">
                    <button type="submit"
                        class="p-2 btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                        Register Client
                    </button>
                </div>
            </form>
        </div>
    </div>

</x-app-layout>