<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Section 1: Success Icon + Header --}}
        <div class="text-center mb-6">
            <svg class="w-16 h-16 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Upload Approved</h2>
            @if(session('message'))
                <p class="text-green-700 text-lg">{{ session('message') }}</p>
            @endif
        </div>

        {{-- Section 2: Upload Summary Card --}}
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4 text-gray-900">Upload Summary</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-600">File:</span>
                    <span class="font-semibold">{{ $bulkUpload->original_filename }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span class="font-semibold text-blue-600">{{ ucfirst($bulkUpload->status) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Invoices to create:</span>
                    <span class="font-semibold text-green-600">{{ $invoiceCount }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Clients:</span>
                    <span class="font-semibold">{{ $clientCount }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total valid tasks:</span>
                    <span class="font-semibold">{{ $bulkUpload->valid_rows }}</span>
                </div>
                @if($bulkUpload->error_rows > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-600">Skipped errors:</span>
                        <span class="font-semibold text-red-600">{{ $bulkUpload->error_rows }}</span>
                    </div>
                @endif
                @if($bulkUpload->flagged_rows > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-600">Skipped flagged:</span>
                        <span class="font-semibold text-yellow-600">{{ $bulkUpload->flagged_rows }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Section 3: Status-aware processing message --}}
        @if($bulkUpload->status === 'processing')
            {{-- Processing state: job is still running --}}
            <div class="bg-blue-50 border border-blue-200 p-4 rounded mb-6">
                <div class="flex items-center gap-3">
                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <p class="text-blue-900 font-semibold">Invoices are being created in the background. Page will auto-refresh in <span id="countdown">5</span> seconds...</p>
                </div>
            </div>

            {{-- Auto-refresh script when processing --}}
            <script>
                let countdown = 5;
                const countdownEl = document.getElementById('countdown');

                const interval = setInterval(() => {
                    countdown--;
                    countdownEl.textContent = countdown;

                    if (countdown <= 0) {
                        clearInterval(interval);
                        window.location.reload();
                    }
                }, 1000);
            </script>
        @elseif($bulkUpload->status === 'failed')
            {{-- Failed state: job permanently failed --}}
            <div class="bg-red-50 border border-red-200 p-4 rounded mb-6">
                <p class="text-red-900 font-semibold mb-2">Invoice creation failed.</p>
                @if(is_array($bulkUpload->error_summary) && isset($bulkUpload->error_summary['job_failure']))
                    <p class="text-sm text-red-700">Error: {{ $bulkUpload->error_summary['job_failure'] }}</p>
                @endif
                <p class="text-sm text-red-600 mt-2">Please contact support or try uploading again.</p>
            </div>
        @elseif($bulkUpload->status === 'completed')
            {{-- Completed state: all invoices created --}}
            <div class="bg-green-50 border border-green-200 p-4 rounded mb-6">
                <p class="text-green-900 font-semibold">All invoices have been created successfully.</p>
                <p class="text-sm text-green-700 mt-1">Invoice PDFs are being emailed to the company accountant and uploading agent.</p>
            </div>
        @endif

        {{-- Section 4: Invoice list (real data when completed) --}}
        @if($invoices->isNotEmpty())
            <h3 class="font-semibold mb-3 text-lg">Created Invoices ({{ $invoices->count() }})</h3>
            <ul class="space-y-2">
                @foreach($invoices as $invoice)
                    <li class="border rounded p-3 flex justify-between items-center">
                        <div>
                            <p class="font-semibold">{{ $invoice->invoice_number }}</p>
                            <p class="text-sm text-gray-600">{{ $invoice->client->full_name ?? 'Unknown Client' }}</p>
                            <p class="text-xs text-gray-400">{{ $invoice->invoice_date }} &middot; {{ $invoice->currency }} {{ number_format($invoice->amount, 3) }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('invoice.show', [$invoice->company_id, $invoice->invoice_number]) }}" class="text-blue-600 hover:underline text-sm">View</a>
                            <a href="{{ route('invoice.pdf', [$invoice->company_id, $invoice->invoice_number]) }}" class="text-green-600 hover:underline text-sm">Download PDF</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Section 5: Navigation Link --}}
        <div class="mt-6 text-center">
            <a href="{{ route('dashboard') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                Upload Another File
            </a>
        </div>
    </div>
</x-app-layout>
