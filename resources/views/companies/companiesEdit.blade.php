<x-app-layout>
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <nav class="flex items-center space-x-2 rtl:space-x-reverse text-sm mb-4 sm:mb-6 overflow-x-auto">
            <a href="{{ route('companies.list') }}" class="text-gray-500 hover:text-gray-700 transition whitespace-nowrap">Companies</a>
            <span class="text-gray-400">&gt;</span>
            <span class="text-blue-600 font-medium truncate max-w-[200px] sm:max-w-none">{{ $company->name }}</span>
        </nav>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-8 lg:p-10">
            <div class="flex items-center gap-3 mb-8 pb-6 border-b border-gray-100">
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 sm:w-7 sm:h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-semibold text-gray-800">Company Information</h2>
                    <p class="text-sm sm:text-base text-gray-500">Update company details</p>
                </div>
            </div>

            <form action="{{ route('companies.update', $company->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $company->name) }}" required
                            class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                            placeholder="Enter company name">
                        @error('name')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 sm:gap-6">
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Company Code</label>
                            <input type="text" name="code" id="code" value="{{ old('code', $company->code) }}" required
                                class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                placeholder="e.g., KWIKT2727">
                            @error('code')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="country_id" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <x-searchable-dropdown
                                name="country_id"
                                :items="$countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()"
                                :selectedId="old('country_id', $company->country_id)"
                                :selectedName="$company->nationality->name ?? null"
                                placeholder="Select Country" />
                            @error('country_id')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $company->phone) }}"
                            class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                            placeholder="e.g., +965 94136799">
                        <p class="mt-2 text-xs text-gray-400 flex items-center gap-1.5">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Primary contact number for this company</span>
                        </p>
                        @error('phone')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Business Address</label>
                        <textarea name="address" id="address" rows="3"
                            class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors resize-none"
                            placeholder="Enter full business address">{{ old('address', $company->address) }}</textarea>
                        @error('address')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-10 pt-6 border-t border-gray-100 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                    <a href="{{ route('companies.list') }}" 
                        class="w-full sm:w-auto px-6 py-2.5 text-center text-sm text-gray-600 hover:text-gray-800 font-medium transition border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow transition duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Update Company
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>