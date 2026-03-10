<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">

            @if($bulkUpload->status === 'completed')
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 sm:px-8 py-5 relative overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" viewBox="0 0 400 200" fill="none">
                        <circle cx="350" cy="30" r="100" fill="white" />
                        <circle cx="50" cy="170" r="60" fill="white" />
                    </svg>
                </div>
                <div class="relative flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">Invoices Created Successfully</h1>
                        <p class="text-green-100 text-xs">{{ $bulkUpload->original_filename }}</p>
                    </div>
                </div>
            </div>
            @elseif($bulkUpload->status === 'failed')
            <div class="bg-gradient-to-r from-red-500 to-rose-600 px-6 sm:px-8 py-5 relative overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" viewBox="0 0 400 200" fill="none">
                        <circle cx="350" cy="30" r="100" fill="white" />
                        <circle cx="50" cy="170" r="60" fill="white" />
                    </svg>
                </div>
                <div class="relative flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">Invoice Creation Failed</h1>
                        <p class="text-red-100 text-xs">{{ $bulkUpload->original_filename }}</p>
                    </div>
                </div>
            </div>
            @elseif($bulkUpload->status === 'processing')
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 sm:px-8 py-5 relative overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" viewBox="0 0 400 200" fill="none">
                        <circle cx="350" cy="30" r="100" fill="white" />
                        <circle cx="50" cy="170" r="60" fill="white" />
                    </svg>
                </div>
                <div class="relative flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                        <svg class="animate-spin w-5 h-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-white">Creating Invoices...</h1>
                        <p class="text-blue-100 text-xs">Please wait, this may take a moment</p>
                    </div>
                </div>
            </div>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-gray-100 dark:divide-gray-700 border-b border-gray-100 dark:border-gray-700">
                <div class="px-6 py-5 text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $bulkUpload->total_rows }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 uppercase tracking-wider font-medium">Total Rows</p>
                </div>
                <div class="px-6 py-5 text-center">
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $bulkUpload->valid_rows }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 uppercase tracking-wider font-medium">Valid</p>
                </div>
                <div class="px-6 py-5 text-center">
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $invoiceCount }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 uppercase tracking-wider font-medium">Invoices</p>
                </div>
                <div class="px-6 py-5 text-center">
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $clientCount }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 uppercase tracking-wider font-medium">Clients</p>
                </div>
            </div>

            <div class="p-6 sm:p-8">
                @if($bulkUpload->status === 'failed')
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/40 rounded-xl p-5 mb-6">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-800/40 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"></path>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-red-800 dark:text-red-300 text-sm">Error Details</p>
                            @if(is_array($bulkUpload->error_summary) && isset($bulkUpload->error_summary['job_failure']))
                            <p class="text-sm text-red-600 dark:text-red-400 mt-1 break-words">{{ $bulkUpload->error_summary['job_failure'] }}</p>
                            @endif
                            <p class="text-xs text-red-400 dark:text-red-500 mt-3">Please contact support or try uploading the file again.</p>
                        </div>
                    </div>
                </div>
                @endif

                @if($bulkUpload->status === 'processing')
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/40 rounded-xl p-5 mb-6" id="processing-alert">
                    <div class="flex items-center gap-3">
                        <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p class="text-blue-800 dark:text-blue-300 text-sm">
                            Invoices are being created in the background. Page will refresh in <span id="countdown" class="font-bold">5</span>s...
                        </p>
                    </div>
                </div>

                <script>
                    (function() {
                        const MAX_REFRESHES = 12;
                        const KEY = 'bulk_refresh_{{ $bulkUpload->id }}';
                        let refreshCount = parseInt(sessionStorage.getItem(KEY) || '0');
                        const alertEl = document.getElementById('processing-alert');

                        if (refreshCount >= MAX_REFRESHES) {
                            alertEl.innerHTML =
                                '<div class="flex items-center gap-3">' +
                                '<svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"></path></svg>' +
                                '<p class="text-amber-800 dark:text-amber-300 text-sm">Processing is taking longer than expected. Please run <code class="bg-amber-100 dark:bg-amber-800/40 px-1.5 py-0.5 rounded text-xs font-mono">php artisan queue:work --queue=invoices</code> in your terminal, then refresh this page.</p>' +
                                '</div>';
                            alertEl.className = 'bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/40 rounded-xl p-5 mb-6';
                            return;
                        }

                        sessionStorage.setItem(KEY, refreshCount + 1);

                        let countdown = 5;
                        const el = document.getElementById('countdown');
                        const interval = setInterval(() => {
                            countdown--;
                            el.textContent = countdown;
                            if (countdown <= 0) {
                                clearInterval(interval);
                                window.location.reload();
                            }
                        }, 1000);
                    })();
                </script>
                @endif

                @if($invoices->isNotEmpty())
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Created Invoices</h2>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-800/30 text-green-700 dark:text-green-400">
                            {{ $invoices->count() }} invoice{{ $invoices->count() > 1 ? 's' : '' }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        @foreach($invoices as $invoice)
                        @php
                        $companyId = $invoice->agent->branch->company_id ?? $bulkUpload->company_id;
                        @endphp
                        <div class="group border border-gray-100 dark:border-gray-700 rounded-xl p-4 hover:border-blue-200 dark:hover:border-blue-700 hover:shadow-sm transition-all bg-white dark:bg-gray-800/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4 min-w-0">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-400 to-indigo-600 text-white flex items-center justify-center text-sm font-bold shrink-0 shadow-sm">
                                        {{ strtoupper(substr($invoice->client->full_name ?? '?', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ $invoice->invoice_number }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium
                                                        {{ $invoice->status === 'paid' ? 'bg-green-50 dark:bg-green-800/30 text-green-700 dark:text-green-400' : ($invoice->status === 'partial' ? 'bg-yellow-50 dark:bg-yellow-800/30 text-yellow-700 dark:text-yellow-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400') }}">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                            {{ $invoice->client->full_name ?? 'Unknown' }}
                                            <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                                            {{ $invoice->invoice_date }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 shrink-0">
                                    <span class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">{{ $invoice->currency }} {{ number_format($invoice->amount, 3) }}</span>
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('invoice.show', [$companyId, $invoice->invoice_number]) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 dark:text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"
                                            title="View Invoice">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        <a href="{{ route('invoice.pdf', [$companyId, $invoice->invoice_number]) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 dark:text-gray-500 hover:text-green-600 dark:hover:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30 transition-colors"
                                            title="Download PDF">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <script>
                    sessionStorage.removeItem('bulk_refresh_{{ $bulkUpload->id }}');
                </script>
                @endif

                @if($bulkUpload->error_rows > 0 || $bulkUpload->flagged_rows > 0)
                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/30 rounded-xl px-4 py-3 mb-6">
                    <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @if($bulkUpload->error_rows > 0)
                        <span class="font-medium text-red-600 dark:text-red-400">{{ $bulkUpload->error_rows }}</span> error row(s) skipped{{ $bulkUpload->flagged_rows > 0 ? ',' : '.' }}
                        @endif
                        @if($bulkUpload->flagged_rows > 0)
                        <span class="font-medium text-amber-600 dark:text-amber-400">{{ $bulkUpload->flagged_rows }}</span> flagged row(s) skipped.
                        @endif
                    </p>
                </div>
                @endif

                <div class="border-t border-gray-100 dark:border-gray-700 pt-5 mb-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 uppercase tracking-wider font-medium">Upload ID</p>
                            <p class="text-gray-700 dark:text-gray-300 mt-1 font-mono">#{{ $bulkUpload->id }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 uppercase tracking-wider font-medium">Uploaded</p>
                            <p class="text-gray-700 dark:text-gray-300 mt-1">{{ $bulkUpload->created_at->format('d M Y, H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 uppercase tracking-wider font-medium">Status</p>
                            <p class="mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium
                                    {{ $bulkUpload->status === 'completed' ? 'bg-green-50 dark:bg-green-800/30 text-green-700 dark:text-green-400' : ($bulkUpload->status === 'failed' ? 'bg-red-50 dark:bg-red-800/30 text-red-700 dark:text-red-400' : 'bg-blue-50 dark:bg-blue-800/30 text-blue-700 dark:text-blue-400') }}">
                                    {{ ucfirst($bulkUpload->status) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-500 uppercase tracking-wider font-medium">File</p>
                            <p class="text-gray-700 dark:text-gray-300 mt-1 truncate" title="{{ $bulkUpload->original_filename }}">{{ $bulkUpload->original_filename }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center gap-3 pt-2">
                    <a href="{{ route('dashboard') }}"
                        class="inline-flex items-center gap-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white hover:border-gray-300 dark:hover:border-gray-500 px-5 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Dashboard
                    </a>
                    <a href="{{ route('bulk-invoices.index') }}"
                        class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all shadow-sm hover:shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Upload Another File
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>