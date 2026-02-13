<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h2 class="text-2xl font-bold mb-6">Preview Bulk Upload</h2>

        {{-- Flash message --}}
        @if(session('message'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded mb-6">
                {{ session('message') }}
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

        {{-- Section 4: Action Buttons (placeholder for Plan 02-02) --}}
        <div class="flex gap-4 mt-6 justify-end">
            <button
                disabled
                class="bg-green-600 text-white px-6 py-2 rounded opacity-50 cursor-not-allowed"
                title="Will be activated in Plan 02-02">
                Approve All ({{ count($invoiceGroups) }} invoice(s))
            </button>
            <button
                disabled
                class="bg-gray-200 text-gray-600 px-6 py-2 rounded opacity-50 cursor-not-allowed"
                title="Will be activated in Plan 02-02">
                Reject Upload
            </button>
        </div>

        {{-- Approve/Reject modals will be added in Plan 02-02 --}}
    </div>
</x-app-layout>
