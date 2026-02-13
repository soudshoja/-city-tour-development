<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h2 class="text-2xl font-bold mb-6">Preview Bulk Upload</h2>

        {{-- Flash message --}}
        @if(session('message'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded mb-6">
                {{ session('message') }}
            </div>
        @endif

        {{-- Error messages --}}
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded mb-4">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Section 1: Upload Summary Banner --}}
        <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6">
            <h3 class="font-semibold text-lg mb-3">Upload Summary</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Filename</p>
                    <p class="font-semibold">{{ $bulkUpload->original_filename }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Rows</p>
                    <p class="font-semibold">{{ $bulkUpload->total_rows }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Valid Rows</p>
                    <p class="font-semibold text-green-600">{{ $bulkUpload->valid_rows }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Flagged Rows</p>
                    <p class="font-semibold text-yellow-600">{{ $bulkUpload->flagged_rows }}</p>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t border-blue-200">
                <p class="text-lg font-bold text-blue-900">
                    {{ count($invoiceGroups) }} invoice(s) for {{ $clientCount }} client(s)
                </p>
            </div>
            @if($bulkUpload->error_rows > 0)
                <div class="mt-3">
                    <a href="{{ route('bulk-invoices.error-report', $bulkUpload->id) }}"
                       class="text-blue-600 hover:text-blue-800 underline text-sm">
                        Download Error Report ({{ $bulkUpload->error_rows }} errors)
                    </a>
                </div>
            @endif
        </div>

        {{-- Section 2: Invoice Group Cards --}}
        <div class="mb-6">
            <h3 class="text-xl font-bold mb-4">Invoices to Create ({{ count($invoiceGroups) }})</h3>

            @if($invoiceGroups->isEmpty())
                <div class="bg-gray-50 border border-gray-200 rounded p-6 text-center text-gray-600">
                    No valid invoices to create.
                </div>
            @else
                @foreach($invoiceGroups as $groupKey => $rows)
                    @php
                        $firstRow = $rows->first();
                        $clientName = $firstRow->client->name ?? 'Unknown';
                        $clientPhone = $firstRow->client->phone ?? '';
                        $invoiceDate = $firstRow->raw_data['invoice_date'] ?? date('Y-m-d');
                        $taskCount = $rows->count();
                    @endphp

                    <div class="border border-gray-200 rounded-lg shadow-sm p-4 mb-4 bg-white">
                        {{-- Card Header --}}
                        <div class="flex justify-between items-start mb-3 pb-3 border-b border-gray-100">
                            <div>
                                <h4 class="font-bold text-lg">{{ $clientName }}</h4>
                                @if($clientPhone)
                                    <p class="text-gray-600 text-sm">{{ $clientPhone }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-600">{{ $taskCount }} task(s)</p>
                                <p class="text-sm text-gray-500">Invoice Date: {{ $invoiceDate }}</p>
                            </div>
                        </div>

                        {{-- Task Details Table --}}
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Row #</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Task ID</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Task Type</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Supplier</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Currency</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($rows as $row)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2">{{ $row->row_number }}</td>
                                            <td class="px-3 py-2">{{ $row->raw_data['task_id'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $row->raw_data['task_type'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $row->supplier->name ?? $row->raw_data['supplier_name'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $row->raw_data['task_status'] ?? '-' }}</td>
                                            <td class="px-3 py-2">{{ $row->raw_data['currency'] ?? 'KWD' }}</td>
                                            <td class="px-3 py-2">{{ $row->raw_data['notes'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Section 3: Flagged Rows (conditional) --}}
        @if($flaggedRows->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-xl font-bold mb-4">Flagged Rows - Requires Review ({{ $flaggedRows->count() }})</h3>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-yellow-800 mb-3 font-semibold">
                        ⚠ These rows have unknown clients and will NOT be included in invoice creation.
                    </p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-yellow-100 border-b border-yellow-300">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Row #</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Client Mobile</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Task ID</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Task Type</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Supplier</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Flag Reason</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-yellow-100 bg-white">
                                @foreach($flaggedRows as $row)
                                    <tr class="hover:bg-yellow-50">
                                        <td class="px-3 py-2">{{ $row->row_number }}</td>
                                        <td class="px-3 py-2">{{ $row->raw_data['client_mobile'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row->raw_data['task_id'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row->raw_data['task_type'] ?? '-' }}</td>
                                        <td class="px-3 py-2">{{ $row->raw_data['supplier_name'] ?? '-' }}</td>
                                        <td class="px-3 py-2 text-yellow-700 font-medium">{{ $row->flag_reason }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Section 4: Action Buttons with Alpine.js Modals --}}
        <div x-data="{ showApproveModal: false, showRejectModal: false }" class="flex gap-4 mt-6 justify-end">
            @if($invoiceGroups->count() > 0)
                <button @click="showApproveModal = true" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded">
                    Approve All ({{ count($invoiceGroups) }} invoices)
                </button>
            @endif
            <button @click="showRejectModal = true" class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-6 py-2 rounded">
                Reject Upload
            </button>

            <!-- Approve Confirmation Modal -->
            <div x-show="showApproveModal" x-cloak
                 @keydown.escape.window="showApproveModal = false"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div @click.outside="showApproveModal = false"
                     class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
                    <h3 class="text-lg font-bold mb-4">Confirm Invoice Creation</h3>
                    <p class="mb-2">This will create <strong>{{ count($invoiceGroups) }} invoices</strong> for <strong>{{ $clientCount }} clients</strong> from <strong>{{ $bulkUpload->valid_rows }} tasks</strong>.</p>
                    <p class="text-sm text-gray-600">This action cannot be undone.</p>
                    @if($flaggedRows->isNotEmpty())
                        <p class="text-sm text-yellow-700 mt-2">Note: {{ $flaggedRows->count() }} flagged row(s) will NOT be included.</p>
                    @endif
                    <div class="mt-6 flex gap-3 justify-end">
                        <button @click="showApproveModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">Cancel</button>
                        <form method="POST" action="{{ route('bulk-invoices.approve', $bulkUpload->id) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded font-semibold">Confirm Approval</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reject Confirmation Modal -->
            <div x-show="showRejectModal" x-cloak
                 @keydown.escape.window="showRejectModal = false"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div @click.outside="showRejectModal = false"
                     class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
                    <h3 class="text-lg font-bold mb-4">Confirm Rejection</h3>
                    <p class="mb-2">This will discard the upload. No invoices will be created.</p>
                    <p class="text-sm text-gray-600">File: {{ $bulkUpload->original_filename }}</p>
                    <div class="mt-6 flex gap-3 justify-end">
                        <button @click="showRejectModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">Cancel</button>
                        <form method="POST" action="{{ route('bulk-invoices.reject', $bulkUpload->id) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded font-semibold">Reject Upload</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
