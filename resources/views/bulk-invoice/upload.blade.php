<x-app-layout>
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Bulk Invoice Upload</h1>
                <p class="text-gray-600">Upload an Excel file to create multiple invoices at once</p>
            </div>

            <!-- Instructions Card -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                <h2 class="text-lg font-semibold text-blue-900 mb-4">How it works:</h2>
                <ol class="list-decimal list-inside space-y-2 text-blue-800">
                    <li>Download the Excel template using the button below</li>
                    <li>Fill in your task details (one task per row)</li>
                    <li>Upload the completed Excel file</li>
                    <li>Preview and approve the invoices to be created</li>
                </ol>
            </div>

            <!-- Download Template Button -->
            <div class="mb-8">
                <a href="{{ route('bulk-invoices.template') }}" 
                   class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg shadow transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download Excel Template
                </a>
            </div>

            <!-- Upload Form -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Upload Completed Excel File</h2>

                <form action="{{ route('bulk-invoices.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <!-- File Upload -->
                    <div>
                        <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                            Excel File *
                        </label>
                        <input type="file" 
                               id="file" 
                               name="file" 
                               accept=".xlsx,.xls"
                               required
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-3">
                        <p class="mt-2 text-sm text-gray-500">Accepted formats: .xlsx, .xls</p>
                        
                        @error('file')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Error Messages -->
                    @if ($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</h3>
                            <ul class="list-disc list-inside text-sm text-red-700">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Submit Button -->
                    <div class="flex items-center justify-end space-x-4">
                        <a href="{{ route('dashboard') }}" 
                           class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg transition">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Upload & Preview
                        </button>
                    </div>
                </form>
            </div>

            <!-- Help Section -->
            <div class="mt-8 bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Need Help?</h3>
                <div class="space-y-2 text-sm text-gray-700">
                    <p><strong>Common Issues:</strong></p>
                    <ul class="list-disc list-inside ml-4 space-y-1">
                        <li>Make sure all required columns are filled</li>
                        <li>Client phone numbers must match existing clients in the system</li>
                        <li>Task types must be one of: flight, hotel, visa, insurance, etc.</li>
                        <li>Amounts should be numbers without currency symbols</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
