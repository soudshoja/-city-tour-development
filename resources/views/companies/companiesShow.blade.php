<x-app-layout>
    <!--img sec-->
    <div class="mt-5 text-center">
        <img src="{{ asset('images/TravelAgencyImage.png') }}" alt="City Tour"
            class="rounded-full mx-auto mb-4 w-32 h-32 object-cover">
        <h2 class="text-xl sm:text-2xl md:text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">
            Edit Company Details
        </h2>

    </div>
    <div class="flex items-center justify-center">
        <form method="POST" action="{{ route('companies.update', $company->id) }}" class="panel w-1/2">
            @csrf
            @method('PUT')

            <!-- Name Field -->
            <div class="mb-4">
                <label for="name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                <input id="name" name="name" type="text" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    value="{{ old('name', $company->name) }}" />
            </div>

            <!-- Code Field -->
            <div class="mb-4">
                <label for="code" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Code</label>
                <input id="code" name="code" type="text" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                    value="{{ old('code', $company->code) }}" />
            </div>

            <!-- Nationality Field -->
            <div class="mb-4">
                <label for="nationality"
                    class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Nationality</label>
                <select id="nationality" name="nationality" required
                    class="block appearance-none w-full bg-white dark:bg-gray-700 border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded leading-tight focus:outline-none focus:shadow-outline">
                    <option value="Malaysia" {{ $company->nationality == 'Malaysia' ? 'selected' : '' }}>Malaysia
                    </option>
                    <option value="Kuwait" {{ $company->nationality == 'Kuwait' ? 'selected' : '' }}>Kuwait</option>
                    <option value="Indonesia" {{ $company->nationality == 'Indonesia' ? 'selected' : '' }}>Indonesia
                    </option>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-center">
                <button type="submit"
                    class="p-2 btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                    Update Company
                </button>
            </div>
        </form>
    </div>



</x-app-layout>