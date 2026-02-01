<x-app-layout>
    <style>
        .tab-shape {
            clip-path: polygon(12px 0, calc(100% - 4px) 0, 100% 100%, 0 100%);
        }

        #taskTypeFilter {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: none !important;
        }

        #taskTypeFilter::-ms-expand {
            display: none;
        }
    </style>

    @php
        $currentSort = request('sort', 'created_at');
        $currentDirection = request('direction', 'desc');

        function getSortUrl($field) {
        $currentSort = request('sort', 'created_at');
        $currentDirection = request('direction', 'desc');
        $direction = ($currentSort === $field && $currentDirection === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $field, 'direction' => $direction]);
        }
    @endphp

    <div class="flex justify-between items-center my-4">
        <div class="flex items-center gap-5">
            <h2 class="text-3xl font-bold">Refund</h2>
        </div>
    </div>

    <div class="panel bg-white rounded-lg shadow p-4" x-data="{ activeTab: 'task'}">
        <div class="flex gap-1 mb-0 bg-slate-100 px-2 pt-2 rounded-t-lg">
            <!-- Task Refund -->
            <button
                @click="activeTab = 'task'"
                class="tab-shape relative px-6 py-2.5 text-sm font-medium transition-all duration-200"
                :class="activeTab === 'task' 
                            ? 'bg-white text-blue-600 z-10 rounded-t-lg' 
                            : 'bg-slate-200 text-slate-500 hover:bg-slate-300 hover:text-slate-700 rounded-t-lg'">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Task
                    <span class="bg-blue-100 text-blue-600 text-xs font-semibold px-2 py-0.5 rounded-full">{{ $totalRefunds }}</span>
                </div>
            </button>

            <!-- Client Credit Refund -->
            <button
                @click="activeTab = 'credit'"
                class="tab-shape relative px-6 py-2.5 text-sm font-medium transition-all duration-200"
                :class="activeTab === 'credit' 
                            ? 'bg-white text-blue-600 z-10 rounded-t-lg' 
                            : 'bg-slate-200 text-slate-500 hover:bg-slate-300 hover:text-slate-700 rounded-t-lg'">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18V19a2 2 0 01-2 2H5a2 2 0 01-2-2V10zm0 0V6a2 2 0 012-2h14a2 2 0 012 2v4M7 15h.01M12 15h2m-6 0a1 1 0 100-2 1 1 0 000 2z"></path>
                    </svg>
                    Client Credit
                    <span class="bg-blue-100 text-blue-600 text-xs font-semibold px-2 py-0.5 rounded-full">0</span>
                </div>
            </button>
        </div>

        <!-- Tab Content: Task Refund -->
        <div x-show="activeTab === 'task'" class="bg-white dark:bg-gray- rounded-lg rounded-tl-none shadow-md p-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-slate-800">Task Refund</h3>
                    <p class="text-sm text-slate-500">{{ $totalRefunds }} {{ Str::plural('task', $totalRefunds) }} that require refund</p>
                </div>

                <button type="button" onclick="openTaskSelectionModal()" data-tooltip-left="Add Task Refund" class="w-10 h-10 inline-flex flex-shrink-0 items-center justify-center bg-blue-100 rounded-full shadow-md hover:shadow-lg transition-shadow duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                </button>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <!-- Refund Number - Sortable -->
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                <a href="{{ getSortUrl('refund_number') }}"
                                    class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                    Refund Number
                                    <span class="flex flex-col">
                                        <svg class="w-2.5 h-2.5 {{ $currentSort === 'refund_number' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5l8 10H4l8-10z" />
                                        </svg>
                                        <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'refund_number' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 19L4 9h16l-8 10z" />
                                        </svg>
                                    </span>
                                </a>
                            </th>

                            <!-- Client - Sortable -->
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                <a href="{{ getSortUrl('client_name') }}"
                                    class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                    Client
                                    <span class="flex flex-col">
                                        <svg class="w-2.5 h-2.5 {{ $currentSort === 'client_name' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5l8 10H4l8-10z" />
                                        </svg>
                                        <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'client_name' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 19L4 9h16l-8 10z" />
                                        </svg>
                                    </span>
                                </a>
                            </th>

                            <!-- Total Refund -->
                            <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Total Refund</th>

                            <!-- Description -->
                            <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Description</th>

                            <!-- Registered Date - Sortable -->
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                <a href="{{ getSortUrl('created_at') }}"
                                    class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                    Registered Date
                                    <span class="flex flex-col">
                                        <svg class="w-2.5 h-2.5 {{ $currentSort === 'created_at' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5l8 10H4l8-10z" />
                                        </svg>
                                        <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'created_at' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 19L4 9h16l-8 10z" />
                                        </svg>
                                    </span>
                                </a>
                            </th>

                            <!-- Status -->
                            <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>

                            <!-- Actions -->
                            <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($refunds->isEmpty())
                        <tr>
                            <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-600">No data for now.... Create new!</td>
                        </tr>
                        @else
                        @foreach ($refunds as $refund)
                        <tr class="p-3 text-sm font-semibold text-gray-600">
                            <td><a href="{{ route('refunds.show', [$refund->company_id, $refund->refund_number]) }}" target="_blank" class="text-blue-600 hover:underline" data-tooltip="View Refund Statement">{{ $refund->refund_number }}</a></td>
                            <td class="max-w-[250px] whitespace-normal break-words">
                                @php
                                $uniqueClients = $refund->refundDetails->pluck('client.full_name')->unique()->values()->toArray();
                                @endphp
                                {{ implode(', ', $uniqueClients) }}
                            </td>
                            <td>{{ number_format($refund->total_nett_refund, 3) }} KWD</td>
                            <td>{{ $refund->remarks }}</td>
                            <td>{{ $refund->created_at }}</td>
                            <td>
                                <span
                                    class="badge whitespace-nowrap px-2 py-1 rounded-full text-sm font-medium
                                            {{ $refund->status === 'completed' ? 'badge-outline-success' : '' }}
                                            {{ $refund->status === 'processed' ? 'badge-outline-assigned' : '' }}
                                            {{ $refund->status === 'approved' ? 'badge-outline-success' : '' }}
                                            {{ $refund->status === 'declined' ? 'badge-outline-danger' : '' }}
                                            {{ $refund->status === 'pending' ? 'badge-outline-warning' : '' }}
                                            {{ $refund->status === null ? 'badge-outline-danger' : '' }}">
                                    {{ $refund->status === null ? 'Not Set' : ucwords($refund->status) }}
                                </span>
                            </td>
                            <td class="items-center">
                                <div class="flex items-center space-x-2">
                                    @if (!$refund->invoice && $refund->status !== 'completed')
                                        <button type="button" 
                                                data-tooltip-left="Mark as Completed" 
                                                onclick="confirmProcessCompleted({{ $refund->id }})"
                                                class="text-sm font-medium hover:opacity-80 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="9 11 12 14 22 4"/>
                                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                            </svg>
                                        </button>

                                    @elseif($refund->invoice)
                                        <a href="{{ route('invoice.show', ['companyId' => $refund->company_id, 'invoiceNumber' => $refund->invoice->invoice_number]) }}"
                                        data-tooltip-left="View Invoice"
                                        class="text-sm font-medium hover:opacity-80 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </a>
                                    @endif

                                    <a data-tooltip-left="Edit refund" href="{{ route('refunds.edit', [$refund->id]) }}"
                                        class="text-sm font-medium text-blue-600 hover:underline">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                            <path fill="none" stroke="#00ab55" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42"
                                                opacity=".5" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab Content: Client Credit -->
        <div x-show="activeTab === 'credit'" class="bg-white dark:bg-gray- rounded-lg rounded-tl-none shadow-md p-4">
            <div class="flex items-center justify-between mb-4">
                <div class="">
                    <h3 class="font-semibold text-slate-800">Client Credit Refund</h3>
                    <p class="text-sm text-slate-500">0 client that require refund</p>
                </div>
            </div>
            
          <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <!-- Refund Number - Sortable -->
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                <a href="{{ getSortUrl('refund_number') }}"
                                    class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                    Refund Number
                                    <span class="flex flex-col">
                                        <svg class="w-2.5 h-2.5 {{ $currentSort === 'refund_number' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5l8 10H4l8-10z" />
                                        </svg>
                                        <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'refund_number' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 19L4 9h16l-8 10z" />
                                        </svg>
                                    </span>
                                </a>
                            </th>

                            <!-- Client - Sortable -->
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                <a href="{{ getSortUrl('client_name') }}"
                                    class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                    Client
                                    <span class="flex flex-col">
                                        <svg class="w-2.5 h-2.5 {{ $currentSort === 'client_name' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5l8 10H4l8-10z" />
                                        </svg>
                                        <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'client_name' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 19L4 9h16l-8 10z" />
                                        </svg>
                                    </span>
                                </a>
                            </th>

                            <!-- Total Refund -->
                            <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Total Refund</th>

                            <!-- Description -->
                            <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Description</th>

                            <!-- Registered Date - Sortable -->
                            <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                <a href="{{ getSortUrl('created_at') }}"
                                    class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                    Registered Date
                                    <span class="flex flex-col">
                                        <svg class="w-2.5 h-2.5 {{ $currentSort === 'created_at' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5l8 10H4l8-10z" />
                                        </svg>
                                        <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'created_at' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 19L4 9h16l-8 10z" />
                                        </svg>
                                    </span>
                                </a>
                            </th>

                            <!-- Status -->
                            <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>

                            <!-- Actions -->
                            <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                   
                </table>
            </div>
        </div>

        <!-- Task Selection Modal -->
        <div id="taskSelectionModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
            <div class="fixed inset-0 backdrop-blur-sm bg-gray-500/50" onclick="closeTaskSelectionModal()"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-6xl max-h-[80vh] overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-xl font-bold text-gray-800">Select Tasks for Refund</h3>
                        <button onclick="closeTaskSelectionModal()" class="text-gray-400 hover:text-gray-600 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="px-6 py-4 bg-white border-b border-gray-200">
                        <div class="flex flex-col md:flex-row gap-4">
                            <div class="flex-1">
                                <input type="text"
                                    id="taskSearchInput"
                                    placeholder="Search by reference, client name..."
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    onkeyup="filterTasks()">
                            </div>
                            <div>
                                <select id="taskTypeFilter" onchange="filterTasks()" 
                                    style="-webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: none;"
                                    class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                                    <option value="">All Types</option>
                                    <option value="flight">Flight</option>
                                    <option value="hotel">Hotel</option>
                                    <option value="visa">Visa</option>
                                    <option value="insurance">Insurance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full table-fixed">
                            <thead class="bg-gray-50 border-b border-gray-200 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left w-12">
                                        <input type="checkbox" id="selectAllTasks" onchange="toggleSelectAll()" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-28">Reference</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-20">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-48">Client</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase w-32">Invoice Number</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase w-28">Invoice Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase w-28">Amount</th>
                                </tr>
                            </thead>
                        </table>
                        
                        <div class="overflow-y-auto max-h-[40vh]">
                            <div id="taskListLoading" class="flex items-center justify-center py-8">
                                <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="ml-2 text-gray-600">Loading tasks...</span>
                            </div>

                            <div id="taskListEmpty" class="hidden text-center py-8 text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p>No eligible tasks found for refund</p>
                            </div>

                            <table id="taskListTable" class="hidden w-full table-fixed">
                                <tbody id="taskListBody" class="divide-y divide-gray-200">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
                        <div>
                            <span id="selectedCount" class="text-sm text-gray-600">0 tasks selected</span>
                        </div>
                        <div class="flex gap-3">
                            <button type="button"
                                onclick="closeTaskSelectionModal()"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                Cancel
                            </button>
                            <button type="button"
                                id="proceedToRefundBtn"
                                onclick="proceedToRefund()"
                                disabled
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                Proceed to Refund
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmProcessCompleted(refundId) {
            if (confirm('Are you sure you want to mark this refund as completed?')) {
                if (confirm('This action cannot be undone. Do you want to proceed?')) {
                    processCompleted(refundId);
                }
            }
        }

        function processCompleted(refundId) {
            // Optional: show console log for debugging
            console.log("Processing refund with ID:", refundId);

            fetch(`/refunds/${refundId}/complete-process`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin' // ✅ ensures cookies/session are sent
            })
            .then(async response => {
                if (response.ok) {
                    // ✅ refund processed successfully
                    alert('Refund process completed successfully!');
                    window.location.href = '/refunds';
                } else {
                    // ❌ handle errors gracefully
                    const text = await response.text();
                    console.error('Server response:', text);
                    alert('Something went wrong. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Error processing refund:', error);
                alert('Error processing refund. Please try again.');
            });
        }
    
        let allTasks = [];
        let selectedTaskIds = new Set();

        function openTaskSelectionModal() {
            document.getElementById('taskSelectionModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            fetchTasks();
        }

        function closeTaskSelectionModal() {
            document.getElementById('taskSelectionModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            selectedTaskIds.clear();
            updateSelectedCount();
        }

        async function fetchTasks() {
            const loading = document.getElementById('taskListLoading');
            const table = document.getElementById('taskListTable');
            const empty = document.getElementById('taskListEmpty');

            loading.classList.remove('hidden');
            table.classList.add('hidden');
            empty.classList.add('hidden');

            try {
                const response = await fetch('{{ route("refunds.eligible-tasks") }}');
                const data = await response.json();

                allTasks = data.tasks || [];

                if (allTasks.length === 0) {
                    loading.classList.add('hidden');
                    empty.classList.remove('hidden');
                    return;
                }

                renderTasks(allTasks);
                loading.classList.add('hidden');
                table.classList.remove('hidden');

            } catch (error) {
                console.error('Error fetching tasks:', error);
                loading.classList.add('hidden');
                empty.classList.remove('hidden');
            }
        }

        function renderTasks(tasks) {
            const tbody = document.getElementById('taskListBody');
            tbody.innerHTML = '';

            tasks.forEach(task => {
                const isChecked = selectedTaskIds.has(task.id) ? 'checked' : '';
                const typeColors = {
                    'flight': 'bg-blue-100 text-blue-700',
                    'hotel': 'bg-purple-100 text-purple-700',
                    'visa': 'bg-green-100 text-green-700',
                    'insurance': 'bg-orange-100 text-orange-700'
                };
                const statusColors = {
                    'paid': 'bg-green-100 text-green-700',
                    'unpaid': 'bg-red-100 text-red-700',
                    'partial': 'bg-yellow-100 text-yellow-700',
                    'partial refund': 'bg-blue-100 text-blue-700'
                };

                const typeColor = typeColors[task.type?.toLowerCase()] || 'bg-gray-100 text-gray-700';
                const invoiceStatusColor = statusColors[task.invoice_status?.toLowerCase()] || 'bg-gray-100 text-gray-700';

                const row = `
                    <tr class="hover:bg-gray-50 cursor-pointer" onclick="toggleTaskSelection(${task.id})">
                        <td class="px-4 py-3 w-12" onclick="event.stopPropagation()">
                            <input type="checkbox" class="task-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="${task.id}" ${isChecked} onchange="toggleTaskSelection(${task.id})">
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 w-28">${task.reference}</td>
                        <td class="px-4 py-3 w-20">
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${typeColor}">${task.type ? task.type.charAt(0).toUpperCase() + task.type.slice(1) : ''}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 w-48">${task.client_name || ''}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 w-32">${task.invoice_number || ''}</td>
                        <td class="px-4 py-3 text-center w-28">
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${invoiceStatusColor}">${task.invoice_status ? task.invoice_status.charAt(0).toUpperCase() + task.invoice_status.slice(1) : ''}</span>
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 text-right w-28">${task.amount || 0} KWD</td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', row);
            });
        }

            function filterTasks() {
                const searchValue = document.getElementById('taskSearchInput').value.toLowerCase();
                const typeFilter = document.getElementById('taskTypeFilter').value.toLowerCase();

                const filtered = allTasks.filter(task => {
                    const matchesSearch = !searchValue ||
                        (task.reference && task.reference.toLowerCase().includes(searchValue)) ||
                        (task.client_name && task.client_name.toLowerCase().includes(searchValue)) ||
                        (task.invoice_number && task.invoice_number.toLowerCase().includes(searchValue));

                    const matchesType = !typeFilter || task.type === typeFilter;

                    return matchesSearch && matchesType;
                });

                renderTasks(filtered);
            }

            function toggleTaskSelection(taskId) {
                if (selectedTaskIds.has(taskId)) {
                    selectedTaskIds.delete(taskId);
                } else {
                    selectedTaskIds.add(taskId);
                }
                
                // Sync checkbox state
                const checkbox = document.querySelector(`.task-checkbox[value="${taskId}"]`);
                if (checkbox) {
                    checkbox.checked = selectedTaskIds.has(taskId);
                }
                
                updateSelectedCount();
            }

            function toggleSelectAll() {
                const selectAll = document.getElementById('selectAllTasks');
                const checkboxes = document.querySelectorAll('.task-checkbox');

                checkboxes.forEach(cb => {
                    const taskId = parseInt(cb.value);
                    if (selectAll.checked) {
                        selectedTaskIds.add(taskId);
                        cb.checked = true;
                    } else {
                        selectedTaskIds.delete(taskId);
                        cb.checked = false;
                    }
                });

                updateSelectedCount();
            }

            function updateSelectedCount() {
                const count = selectedTaskIds.size;
                document.getElementById('selectedCount').textContent = `${count} task${count !== 1 ? 's' : ''} selected`;
                document.getElementById('proceedToRefundBtn').disabled = count === 0;
            }

            function proceedToRefund() {
                if (selectedTaskIds.size === 0) {
                    alert('Please select at least one task');
                    return;
                }

                const taskIds = Array.from(selectedTaskIds).join(',');
                window.location.href = `{{ route('refunds.create') }}?task_ids=${taskIds}`;
            }

            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTaskSelectionModal();
            }
        });
    </script>
</x-app-layout>