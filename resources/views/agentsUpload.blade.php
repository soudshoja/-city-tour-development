<x-app-layout>
    <div class="mb-6 grid gap-6 xl:grid-cols-3">
        <div class="w-full md:w-96 md:max-w-full mx-auto">
            <div class="p-6 border border-gray-300 sm:rounded-md">
                <form method="POST" action="{{ route('agentsupload.import') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="block mb-6">
                        <span class="text-gray-700">Upload Agents Details</span>
                        <input
                            required
                            name="excel_file"
                            type="file"
                            class="block w-full mt-1 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                    </label>
                    <div class="mb-6">
                        <button type="submit" class="h-10 px-5 text-indigo-100 bg-indigo-700 rounded-lg transition-colors duration-150 focus:shadow-outline hover:bg-indigo-800">
                            Apply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
