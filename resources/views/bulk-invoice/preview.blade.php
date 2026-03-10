<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" x-data="{ showApproveModal: false, showRejectModal: false }">

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-violet-600 to-indigo-600 px-6 sm:px-8 py-5 relative overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" viewBox="0 0 400 200" fill="none">
                        <circle cx="350" cy="30" r="100" fill="white" />
                        <circle cx="50" cy="170" r="60" fill="white" />
                    </svg>
                </div>
                <div class="relative flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-white">Preview Bulk Upload</h1>
                            <p class="text-violet-100 text-xs">Review your data before creating invoices</p>
                        </div>
                    </div>
                    <a href="{{ route('bulk-invoices.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white/15 hover:bg-white/25 backdrop-blur-sm text-white text-xs font-medium rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Upload
                    </a>
                </div>
            </div>

            @if(session('message'))
            <div class="px-6 py-3 bg-green-50 dark:bg-green-900/20 border-b border-green-100 dark:border-green-800/40 flex items-center gap-2">
                <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium text-green-700 dark:text-green-300">{{ session('message') }}</span>
            </div>
            @endif

            @if($errors->any())
            <div class="px-6 py-3 bg-red-50 dark:bg-red-900/20 border-b border-red-100 dark:border-red-800/40">
                @foreach($errors->all() as $error)
                <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                @endforeach
            </div>
            @endif

            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $bulkUpload->original_filename }}</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Uploaded {{ $bulkUpload->created_at->diffForHumans() }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-5 gap-3">
                    <div class="bg-gray-50 dark:bg-gray-700/30 rounded-xl px-4 py-3 text-center">
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $bulkUpload->total_rows }}</p>
                        <p class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-0.5">Total Rows</p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-xl px-4 py-3 text-center ring-1 ring-green-100 dark:ring-green-800/40">
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ $bulkUpload->valid_rows }}</p>
                        <p class="text-[10px] font-medium text-green-600 dark:text-green-400 uppercase tracking-wider mt-0.5">Valid</p>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-xl px-4 py-3 text-center ring-1 ring-red-100 dark:ring-red-800/40">
                        <p class="text-xl font-bold text-red-500 dark:text-red-400">{{ $bulkUpload->error_rows }}</p>
                        <p class="text-[10px] font-medium text-red-500 dark:text-red-400 uppercase tracking-wider mt-0.5">Errors</p>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl px-4 py-3 text-center ring-1 ring-amber-100 dark:ring-amber-800/40">
                        <p class="text-xl font-bold text-amber-500 dark:text-amber-400">{{ $bulkUpload->flagged_rows }}</p>
                        <p class="text-[10px] font-medium text-amber-500 dark:text-amber-400 uppercase tracking-wider mt-0.5">Flagged</p>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl px-4 py-3 text-center ring-1 ring-blue-100 dark:ring-blue-800/40">
                        <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ count($invoiceGroups) }}</p>
                        <p class="text-[10px] font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wider mt-0.5">{{ $clientCount }} Client(s)</p>
                    </div>
                </div>

                @if($bulkUpload->error_rows > 0)
                <a href="{{ route('bulk-invoices.error-report', $bulkUpload->id) }}"
                    class="mt-4 flex items-center gap-2.5 px-4 py-2.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/40 rounded-xl text-sm text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 font-medium transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download Error Report ({{ $bulkUpload->error_rows }} {{ Str::plural('error', $bulkUpload->error_rows) }})
                </a>
                @endif
            </div>
        </div>

        <div class="mb-6">
            <div class="flex items-center gap-2.5 mb-4">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Invoices to Create</h3>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">{{ count($invoiceGroups) }}</span>
            </div>

            @if($invoiceGroups->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-10 text-center">
                <div class="w-14 h-14 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <p class="text-gray-600 dark:text-gray-300 font-semibold text-sm">No valid invoices to create</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">All rows have errors. Download the error report to review.</p>
            </div>
            @else
            <div class="space-y-4">
                @foreach($invoiceGroups as $groupKey => $rows)
                @php
                $firstRow = $rows->first();
                $clientName = $firstRow->client->full_name ?? 'Unknown';
                $clientPhone = $firstRow->client->phone ?? '';
                $invoiceDate = $firstRow->raw_data['invoice_date'] ?? date('Y-m-d');
                $taskCount = $rows->count();
                $groupTotal = $rows->sum(fn($r) => (float)($r->raw_data['selling_price'] ?? 0));
                @endphp

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 bg-gradient-to-r from-gray-50 to-white dark:from-gray-750 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 flex items-center justify-center shadow-sm">
                                <span class="text-xs font-bold text-white">{{ strtoupper(substr($clientName, 0, 2)) }}</span>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $clientName }}</h4>
                                <div class="flex items-center gap-2 mt-0.5">
                                    @if($clientPhone)
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $clientPhone }}</span>
                                    <span class="text-gray-300 dark:text-gray-600">|</span>
                                    @endif
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $invoiceDate }}</span>
                                    <span class="text-gray-300 dark:text-gray-600">|</span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $taskCount }} {{ Str::plural('task', $taskCount) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-base font-bold text-gray-900 dark:text-white">{{ number_format($groupTotal, 3) }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-0.5">KWD</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50/50 dark:bg-gray-700/20 border-b border-gray-100 dark:border-gray-700">
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Row</th>
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Task Reference</th>
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-5 py-2.5 text-right text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Selling Price</th>
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payment</th>
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                                @foreach($rows as $row)
                                @php
                                $task = \App\Models\Task::find($row->task_id ?? null);
                                $payment = \App\Models\Payment::find($row->payment_id ?? null);
                                @endphp
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
                                    <td class="px-5 py-2.5 text-gray-400 dark:text-gray-500 font-mono text-xs">#{{ $row->row_number }}</td>
                                    <td class="px-5 py-2.5">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $row->raw_data['task_reference'] ?? '-' }}</div>
                                        @if($task)
                                        <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">ID: {{ $task->id }} &middot; {{ ucfirst($task->type) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 ring-1 ring-inset ring-blue-600/10 dark:ring-blue-500/20">
                                            {{ ucfirst($row->raw_data['task_status'] ?? '-') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-2.5 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($row->raw_data['selling_price'] ?? 0, 3) }}</td>
                                    <td class="px-5 py-2.5">
                                        <div class="text-sm text-gray-700 dark:text-gray-300">{{ $row->raw_data['payment_reference'] ?? '-' }}</div>
                                        @if($payment && $payment->voucher_number)
                                        <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $payment->voucher_number }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 text-xs text-gray-500 dark:text-gray-400 max-w-[150px] truncate">{{ $row->raw_data['notes'] ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        @if($flaggedRows->isNotEmpty())
        <div class="mb-6">
            <div class="flex items-center gap-2.5 mb-4">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Flagged Rows</h3>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">{{ $flaggedRows->count() }}</span>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-amber-200 dark:border-amber-800/40 shadow-sm overflow-hidden">
                <div class="px-5 py-2.5 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800/40 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-500 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    <span class="text-xs font-semibold text-amber-800 dark:text-amber-300">These rows require review and will NOT be included in invoice creation.</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50/50 dark:bg-gray-700/20 border-b border-gray-100 dark:border-gray-700">
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Row</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client Mobile</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Task Reference</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-2.5 text-right text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Selling Price</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            @foreach($flaggedRows as $row)
                            <tr class="hover:bg-amber-50/30 dark:hover:bg-amber-900/10 transition-colors">
                                <td class="px-5 py-2.5 text-gray-400 dark:text-gray-500 font-mono text-xs">#{{ $row->row_number }}</td>
                                <td class="px-5 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $row->raw_data['client_mobile'] ?? '-' }}</td>
                                <td class="px-5 py-2.5 text-sm font-medium text-gray-900 dark:text-white">{{ $row->raw_data['task_reference'] ?? '-' }}</td>
                                <td class="px-5 py-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                        {{ ucfirst($row->raw_data['task_status'] ?? '-') }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($row->raw_data['selling_price'] ?? 0, 3) }}</td>
                                <td class="px-5 py-2.5 text-xs font-medium text-amber-700 dark:text-amber-400 max-w-[250px]">{{ $row->flag_reason }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm px-5 py-4 flex items-center justify-between">
            <p class="text-xs text-gray-400 dark:text-gray-500">
                @if($invoiceGroups->count() > 0)
                Ready to create {{ count($invoiceGroups) }} {{ Str::plural('invoice', count($invoiceGroups)) }} for {{ $clientCount }} {{ Str::plural('client', $clientCount) }}
                @else
                No valid invoices to create
                @endif
            </p>
            <div class="flex items-center gap-3">
                @if($invoiceGroups->count() > 0)
                <button @click="showRejectModal = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-700 text-white font-medium rounded-xl text-sm shadow-sm hover:shadow-md transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Reject Upload
                </button>
                @else
                <a href="{{ route('bulk-invoices.index') }}"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white hover:border-gray-300 dark:hover:border-gray-500 font-medium rounded-xl text-sm transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Upload
                </a>
                @endif
                @if($invoiceGroups->count() > 0)
                <button @click="showApproveModal = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-medium rounded-xl text-sm shadow-sm hover:shadow-md transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Approve & Create {{ count($invoiceGroups) }} {{ Str::plural('Invoice', count($invoiceGroups)) }}
                </button>
                @endif
            </div>
        </div>

        <div x-show="showApproveModal" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @keydown.escape.window="showApproveModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); background: rgba(0,0,0,0.3);">
            <div @click.outside="showApproveModal = false"
                x-show="showApproveModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="w-14 h-14 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-center text-gray-900 dark:text-white mb-2">Confirm Invoice Creation</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-1">
                        This will create <strong class="text-gray-900 dark:text-white">{{ count($invoiceGroups) }} invoice(s)</strong> for
                        <strong class="text-gray-900 dark:text-white">{{ $clientCount }} client(s)</strong> from
                        <strong class="text-gray-900 dark:text-white">{{ $bulkUpload->valid_rows }} task(s)</strong>.
                    </p>
                    <p class="text-[11px] text-gray-400 dark:text-gray-500 text-center">This action cannot be undone.</p>
                    @if($flaggedRows->isNotEmpty())
                    <div class="mt-3 px-3 py-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-100 dark:border-amber-800/40">
                        <p class="text-xs text-amber-700 dark:text-amber-400 text-center font-medium">{{ $flaggedRows->count() }} flagged row(s) will NOT be included.</p>
                    </div>
                    @endif
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-100 dark:border-gray-700 flex gap-3 justify-end">
                    <button @click="showApproveModal = false" class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition">Cancel</button>
                    <form method="POST" action="{{ route('bulk-invoices.approve', $bulkUpload->id) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 rounded-xl shadow-sm transition">Confirm Approval</button>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="showRejectModal" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @keydown.escape.window="showRejectModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); background: rgba(0,0,0,0.3);">
            <div @click.outside="showRejectModal = false"
                x-show="showRejectModal"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-gray-200 dark:border-gray-700">
                <div class="p-6">
                    <div class="w-14 h-14 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-center text-gray-900 dark:text-white mb-2">Reject Upload</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-2">This will discard the upload. No invoices will be created.</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 text-center font-mono">{{ $bulkUpload->original_filename }}</p>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-100 dark:border-gray-700 flex gap-3 justify-end">
                    <button @click="showRejectModal = false" class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition">Cancel</button>
                    <form method="POST" action="{{ route('bulk-invoices.reject', $bulkUpload->id) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 rounded-xl shadow-sm transition">Reject Upload</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>