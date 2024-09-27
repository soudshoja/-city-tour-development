<x-app-layout>
    <div class="flex justify-center items-center mt-10">
        <div
            class="flex flex-col lg:flex-row justify-between items-stretch w-full max-w-6xl bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">
            <!-- Image Section -->
            <div class="w-full lg:w-2/5 h-96 lg:h-auto">
                <img src="{{ asset('images/TravelAgencyImage.png') }}" alt="company Registration"
                    class="w-full h-full object-cover" />
            </div>

            <!-- Form Section -->
            <div class="w-full lg:w-3/5 p-8 flex items-center justify-center">
                <div class="w-full">
                    <h2 class="text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">Edit company
                        Profile</h2>

                    <form method="POST" action="{{ route('companies.store') }}">
                        @csrf

                        <!-- Name Field -->
                        <div class="mb-4">
                            <label for="name"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                            <input id="name" name="name" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="{{ $company->name }}" />
                        </div>

                        <!-- Code Field -->
                        <div class="mb-4">
                            <label for="code"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Code</label>
                            <input id="code" name="code" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="company Code" />
                        </div>

                        <!-- Nationality Field -->
                        <div class="mb-4">
                            <label for="nationality"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Nationality</label>
                            <select id="nationality" name="nationality" required
                                class="block appearance-none w-full bg-white dark:bg-gray-700 border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded leading-tight focus:outline-none focus:shadow-outline">
                                <option value="Malaysia">Malaysia</option>
                                <option value="Kuwait">Kuwait</option>
                                <option value="Indonesia">Indonesia</option>
                            </select>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-center">
                            <button type="submit"
                                class="p-2 btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                                update company
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>