<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 sm:px-8 py-5 relative overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" viewBox="0 0 400 200" fill="none">
                        <circle cx="350" cy="30" r="100" fill="white" />
                        <circle cx="50" cy="170" r="60" fill="white" />
                    </svg>
                </div>
                <div class="relative flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">Bulk Invoice Upload</h1>
                        <p class="text-blue-100 text-xs">Upload an Excel or CSV file to create multiple invoices at once</p>
                    </div>
                </div>
            </div>

            <div class="p-5 sm:p-6">
                @if(session('success'))
                <div class="flex items-center gap-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800/40 rounded-xl px-4 py-3 mb-5">
                    <div class="w-7 h-7 rounded-lg bg-green-100 dark:bg-green-800/40 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <p class="text-sm text-green-700 dark:text-green-300">{{ session('success') }}</p>
                </div>
                @endif

                <div class="flex flex-col sm:flex-row gap-4 mb-5">
                    <div class="flex-1 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/40 rounded-xl p-4">
                        <p class="text-[10px] font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider mb-2.5">How it works</p>
                        <div class="space-y-2">
                            @foreach(['Download the Excel template', 'Fill in your task details (one task per row)', 'Upload the completed file', 'Preview and approve invoices'] as $i => $step)
                            <div class="flex items-center gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-blue-600 dark:bg-blue-500 text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                                <span class="text-sm text-blue-800 dark:text-blue-300">{{ $step }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="sm:w-56 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-700/30 border border-gray-200 dark:border-gray-600 border-dashed rounded-xl p-5 text-center">
                        <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-800/30 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Start with our template</p>
                        <a href="{{ route('bulk-invoices.template') }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Download Template
                        </a>
                    </div>
                </div>

                <form action="{{ route('bulk-invoices.upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="border border-gray-200 dark:border-gray-700 rounded-xl divide-y divide-gray-200 dark:divide-gray-700">
                        <div class="p-4 sm:p-5">
                            @if($isAgent)
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Agent</label>
                            <div class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2.5 text-sm bg-gray-50 dark:bg-gray-700/30 text-gray-700 dark:text-gray-300 cursor-not-allowed">
                                {{ $agents->first()['name'] ?? 'Unknown Agent' }}
                            </div>
                            <input type="hidden" name="agent_id" value="{{ $selectedAgentId }}">
                            <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">Auto-selected based on your account</p>
                            @else
                            <x-searchable-dropdown
                                label="Agent *"
                                name="agent_id"
                                :items="json_encode($agents)"
                                placeholder="Select Agent"
                                :maxResults="10" />
                            <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">Select which agent these invoices should be created for</p>
                            @error('agent_id')
                            <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            @endif
                        </div>

                        <div class="p-4 sm:p-5">
                            <label for="file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Excel / CSV File <span class="text-red-500">*</span>
                            </label>
                            <input type="file"
                                id="file"
                                name="file"
                                accept=".xlsx,.xls,.csv"
                                required
                                class="block w-full text-sm text-gray-700 dark:text-gray-300
                                          file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                          file:text-sm file:font-medium
                                          file:bg-blue-50 file:text-blue-700
                                          dark:file:bg-blue-900/30 dark:file:text-blue-400
                                          hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50
                                          file:cursor-pointer file:transition-colors
                                          border border-gray-200 dark:border-gray-600 rounded-lg p-2
                                          bg-gray-50 dark:bg-gray-700/30
                                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">Accepted formats: .xlsx, .xls, .csv</p>
                            @error('file')
                            <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($errors->any())
                        <div class="p-4 sm:p-5 bg-red-50 dark:bg-red-900/20">
                            <div class="flex items-start gap-3">
                                <div class="w-7 h-7 rounded-lg bg-red-100 dark:bg-red-800/40 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-3.5 h-3.5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-1">Please fix the following:</p>
                                    <ul class="space-y-0.5">
                                        @foreach ($errors->all() as $error)
                                        <li class="text-xs text-red-600 dark:text-red-400">{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-4">
                        <a href="{{ route('dashboard') }}"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white hover:border-gray-300 dark:hover:border-gray-500 rounded-xl text-sm font-medium transition-all">
                            Cancel
                        </a>
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-sm font-medium transition-all shadow-sm hover:shadow-md">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Upload & Preview
                        </button>
                    </div>
                </form>

                <div class="mt-5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-7 h-7 rounded-lg bg-amber-100 dark:bg-amber-800/40 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-amber-800 dark:text-amber-300 mb-2">Important Guidelines</p>
                            <ul class="space-y-1 text-xs text-amber-700 dark:text-amber-400">
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 w-1 h-1 rounded-full bg-amber-500 shrink-0"></span>
                                    Make sure all required columns are filled
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 w-1 h-1 rounded-full bg-amber-500 shrink-0"></span>
                                    Client name and phone number must match an existing client in the system
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 w-1 h-1 rounded-full bg-amber-500 shrink-0"></span>
                                    Task statuses: pending, issued, confirmed, reissued, refund, void, emd
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 w-1 h-1 rounded-full bg-amber-500 shrink-0"></span>
                                    Amounts should be numbers without currency symbols
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 w-1 h-1 rounded-full bg-amber-500 shrink-0"></span>
                                    Use passenger_name to distinguish tasks with the same reference & status
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 w-1 h-1 rounded-full bg-amber-500 shrink-0"></span>
                                    Tasks that are already invoiced will be rejected
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="mt-1 w-1 h-1 rounded-full bg-amber-500 shrink-0"></span>
                                    Payment reference must match an existing completed payment
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>