@push('styles')
    @vite(['resources/css/refund.css'])
@endpush
<x-app-layout>
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

    <div class="main-page-header">
        <div class="main-page-header-left">
            <h2 class="main-page-title">Refund</h2>
        </div>
    </div>

    <div class="main-panel" x-data="{ activeTab: 'task'}">
        <div class="main-tabs-bar">
            <!-- Task Refund -->
            <button
                @click="activeTab = 'task'"
                class="main-tab-shape main-tab"
                :class="activeTab === 'task' ? 'main-tab-active' : 'main-tab-inactive'">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Task
                    <span class="main-tab-badge main-tab-badge-blue">{{ $totalRefunds }}</span>
                </div>
            </button>

            <!-- Client Credit Refund -->
            <button
                @click="activeTab = 'credit'"
                class="main-tab-shape main-tab"
                :class="activeTab === 'credit' ? 'main-tab-active' : 'main-tab-inactive'">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18V19a2 2 0 01-2 2H5a2 2 0 01-2-2V10zm0 0V6a2 2 0 012-2h14a2 2 0 012 2v4M7 15h.01M12 15h2m-6 0a1 1 0 100-2 1 1 0 000 2z"></path>
                    </svg>
                    Client Credit
                    <span class="main-tab-badge main-tab-badge-blue">0</span>
                </div>
            </button>
        </div>

        <!-- Tab Content: Task Refund -->
        <div x-show="activeTab === 'task'" class="main-tab-content">
            <div class="main-section-header">
                <div>
                    <h3 class="main-section-title">Task Refund</h3>
                    <p class="main-section-subtitle">{{ $totalRefunds }} {{ Str::plural('task', $totalRefunds) }} that require refund</p>
                </div>

                <button type="button" onclick="openTaskSelectionModal()" data-tooltip-left="Add Task Refund" class="main-action-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                </button>
            </div>

            <div class="main-table-container">
                <table class="main-table">
                    <thead>
                        <tr>
                            <!-- Refund Number - Sortable -->
                            <th class="main-table-th">
                                <a href="{{ getSortUrl('refund_number') }}" class="main-sort-link">
                                    Refund Number
                                    <span class="main-sort-icons">
                                        <svg class="main-sort-icon {{ $currentSort === 'refund_number' && $currentDirection === 'asc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5l8 10H4l8-10z" />
                                        </svg>
                                        <svg class="main-sort-icon main-sort-icon-down {{ $currentSort === 'refund_number' && $currentDirection === 'desc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 19L4 9h16l-8 10z" />
                                        </svg>
                                    </span>
                                </a>
                            </th>
                            <!-- Client - Sortable -->
                            <th class="main-table-th">
                                <a href="{{ getSortUrl('client_name') }}" class="main-sort-link">
                                    Client
                                    <span class="main-sort-icons">
                                        <svg class="main-sort-icon {{ $currentSort === 'client_name' && $currentDirection === 'asc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5l8 10H4l8-10z" />
                                        </svg>
                                        <svg class="main-sort-icon main-sort-icon-down {{ $currentSort === 'client_name' && $currentDirection === 'desc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 19L4 9h16l-8 10z" />
                                        </svg>
                                    </span>
                                </a>
                            </th>
                            <!-- Total Refund -->
                             <th class="main-table-th-right">Total Refund</th>
                            <!-- Description -->
                            <th class="main-table-th-center">Description</th>
                            <!-- Registered Date - Sortable -->
                            <th class="main-table-th">
                                <a href="{{ getSortUrl('created_at') }}" class="main-sort-link">
                                    Registered Date
                                    <span class="main-sort-icons">
                                        <svg class="main-sort-icon {{ $currentSort === 'created_at' && $currentDirection === 'asc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5l8 10H4l8-10z" />
                                        </svg>
                                        <svg class="main-sort-icon main-sort-icon-down {{ $currentSort === 'created_at' && $currentDirection === 'desc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 19L4 9h16l-8 10z" />
                                        </svg>
                                    </span>
                                </a>
                            </th>
                            <!-- Status -->
                             <th class="main-table-th-center">Status</th>
                            <!-- Actions -->
                             <th class="main-table-th-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($refunds->isEmpty())
                        <tr>
                            <td colspan="7" class="main-table-empty">No data for now.... Create new!</td>
                        </tr>
                        @else
                        @foreach ($refunds as $refund)
                        <tr class="main-table-row-static">
                            <td class="main-table-td">
                                <a href="{{ route('refunds.show', [$refund->company_id, $refund->refund_number]) }}" target="_blank" class="main-table-td-link" data-tooltip="View Refund Statement">{{ $refund->refund_number }}</a>
                            </td>
                            <td class="main-table-td" style="max-width: 250px; white-space: normal; word-wrap: break-word;">
                                @php
                                    $uniqueClients = $refund->refundDetails->pluck('client.full_name')->unique()->values()->toArray();
                                @endphp
                                {{ implode(', ', $uniqueClients) }}
                            </td>
                            <td class="main-table-td-right">{{ number_format($refund->total_nett_refund, 3) }} KWD</td>
                            <td class="main-table-td-center">{{ $refund->remarks }}</td>
                            <td class="main-table-td">{{ $refund->created_at }}</td>
                            <td class="main-table-td-center">
                                <span class="badge whitespace-nowrap px-2 py-1 rounded-full text-sm font-medium
                                    {{ $refund->status === 'completed' ? 'badge-outline-success' : '' }}
                                    {{ $refund->status === 'processed' ? 'badge-outline-assigned' : '' }}
                                    {{ $refund->status === 'approved' ? 'badge-outline-success' : '' }}
                                    {{ $refund->status === 'declined' ? 'badge-outline-danger' : '' }}
                                    {{ $refund->status === 'pending' ? 'badge-outline-warning' : '' }}
                                    {{ $refund->status === null ? 'badge-outline-danger' : '' }}">
                                    {{ $refund->status === null ? 'Not Set' : ucwords($refund->status) }}
                                </span>
                            </td>
                            <td class="main-table-td-right">
                                <div class="main-action-icons">
                                    @if (!$refund->invoice && $refund->status !== 'completed')
                                        <button type="button" data-tooltip-left="Mark as Completed" onclick="confirmProcessCompleted({{ $refund->id }})" class="main-action-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="9 11 12 14 22 4"/>
                                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                            </svg>
                                        </button>
                                    @elseif($refund->invoice)
                                        <a href="{{ route('invoice.show', ['companyId' => $refund->company_id, 'invoiceNumber' => $refund->invoice->invoice_number]) }}" data-tooltip-left="View Invoice" class="main-action-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        </a>
                                    @endif

                                    <a data-tooltip-left="Edit refund" href="{{ route('refunds.edit', [$refund->id]) }}" class="main-action-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                            <path fill="none" stroke="#00ab55" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42" opacity=".5" />
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
            @if ($refunds->hasPages())
                <div style="padding: 16px 24px; border-top: 1px solid #e5e7eb;">
                    {{ $refunds->links() }}
                </div>
            @endif
        </div>

        <!-- Tab Content: Client Credit -->
        <div x-show="activeTab === 'credit'" class="main-tab-content">
            <div class="main-section-header">
                <div>
                    <h3 class="main-section-title">Client Credit Refund</h3>
                    <p class="main-section-subtitle">0 client that require refund</p>
                </div>
            </div>
            
            <div class="main-table-container">
                <table class="main-table">
                    <thead>
                        <tr>
                            <!-- Refund Number - Sortable -->
                             <th class="main-table-th">Refund Number</th>
                            <!-- Client - Sortable -->
                            <th class="main-table-th">Client</th>
                            <!-- Total Refund -->
                            <th class="main-table-th-right">Total Refund</th>
                            <!-- Description -->
                            <th class="main-table-th-center">Description</th>
                            <!-- Registered Date - Sortable -->
                            <th class="main-table-th">Registered Date</th>
                             <!-- Status -->
                            <th class="main-table-th-center">Status</th>
                            <!-- Actions -->
                             <th class="main-table-th-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="main-table-empty">No data available</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Task Selection Modal -->
        <div id="taskSelectionModal" class="refund-modal hidden">
            <div class="refund-modal-overlay" onclick="closeTaskSelectionModal()"></div>
            <div class="refund-modal-container">
                <div class="refund-modal-content">
                    <!-- Header -->
                    <div class="refund-modal-header">
                        <h3 class="refund-modal-title">Select Tasks for Refund</h3>
                        <button onclick="closeTaskSelectionModal()" class="refund-modal-close">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="refund-modal-search">
                        <div class="refund-modal-search-row">
                            <input type="text" id="taskSearchInput" placeholder="Search by reference, client name..." class="refund-modal-search-input" onkeyup="filterTasks()">
                            <select id="taskTypeFilter" onchange="filterTasks()" class="refund-modal-select">
                                <option value="">All Types</option>
                                <option value="flight">Flight</option>
                                <option value="hotel">Hotel</option>
                                <option value="visa">Visa</option>
                                <option value="insurance">Insurance</option>
                                <option value="car">Car</option>
                                <option value="tour">Tour</option>
                                <option value="rail">Rail</option>
                                <option value="cruise">Cruise</option>
                                <option value="esim">eSIM</option>
                            </select>
                        </div>
                    </div>

                    <div class="refund-task-table-container">
                        <table class="refund-task-table">
                            <thead class="refund-task-thead">
                                <tr>
                                    <th class="refund-task-th" style="width: 3rem;">
                                        <input type="checkbox" id="selectAllTasks" onchange="toggleSelectAll()" class="refund-task-checkbox">
                                    </th>
                                    <th class="refund-task-th" style="width: 7rem;">Reference</th>
                                    <th class="refund-task-th" style="width: 5rem;">Type</th>
                                    <th class="refund-task-th" style="width: 12rem;">Client</th>
                                    <th class="refund-task-th" style="width: 8rem;">Invoice Number</th>
                                    <th class="refund-task-th refund-task-th-center" style="width: 7rem;">Invoice Status</th>
                                    <th class="refund-task-th refund-task-th-right" style="width: 7rem;">Amount</th>
                                </tr>
                            </thead>
                        </table>
                        
                        <div class="refund-task-body-container">
                            <div id="taskListLoading" class="refund-loading">
                                <svg class="refund-loading-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="refund-loading-text">Loading tasks...</span>
                            </div>

                            <div id="taskListEmpty" class="hidden refund-task-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" class="refund-task-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p>No eligible tasks found for refund</p>
                            </div>

                            <table id="taskListTable" class="hidden refund-task-table">
                                <tbody id="taskListBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="refund-modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                        <button type="button" onclick="closeTaskSelectionModal()" class="refund-btn-cancel">Cancel</button>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span id="selectedCount" class="refund-modal-footer-count">0 tasks selected</span>
                            <button type="button" id="proceedToRefundBtn" onclick="proceedToRefund()" disabled class="refund-btn-proceed">Proceed to Refund</button>
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
            const loading = document.getElementById('taskListLoading');
            const table = document.getElementById('taskListTable');
            const empty = document.getElementById('taskListEmpty');

            tbody.innerHTML = '';

            // Check if no tasks after filtering
            if (tasks.length === 0) {
                loading.style.display = 'none';
                table.style.display = 'none';
                empty.style.display = 'block';
                
                // Update empty state message for search results
                const searchValue = document.getElementById('taskSearchInput').value;
                const typeFilter = document.getElementById('taskTypeFilter').value;
                
                if (searchValue || typeFilter) {
                    empty.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="refund-task-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <p class="refund-task-empty-title">No results found</p>
                        <p class="refund-task-empty-subtitle">Try adjusting your search or filter</p>
                    `;
                } else {
                    empty.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="refund-task-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="refund-task-empty-title">No eligible tasks found</p>
                        <p class="refund-task-empty-subtitle">No tasks are available for refund</p>
                    `;
                }
                return;
            }

            tasks.forEach(task => {
                const isChecked = selectedTaskIds.has(task.id) ? 'checked' : '';
                const typeColors = {
                    'flight': 'refund-type-flight',
                    'hotel': 'refund-type-hotel',
                    'visa': 'refund-type-visa',
                    'insurance': 'refund-type-insurance',
                    'car': 'refund-type-car',
                    'tour': 'refund-type-tour',
                    'rail': 'refund-type-rail',
                    'cruise': 'refund-type-cruise',
                    'esim': 'refund-type-esim'
                };
                const statusColors = {
                    'paid': 'main-badge-green',
                    'unpaid': 'main-badge-red',
                    'partial': 'main-badge-yellow',
                    'partial refund': 'main-badge-blue'
                };

                const typeColor = typeColors[task.type?.toLowerCase()] || '';
                const invoiceStatusColor = statusColors[task.invoice_status?.toLowerCase()] || 'main-badge-gray';

                const row = `
                    <tr class="refund-task-row" onclick="toggleTaskSelection(${task.id})">
                        <td class="refund-task-td" style="width: 3rem;" onclick="event.stopPropagation()">
                            <input type="checkbox" class="task-checkbox refund-task-checkbox" value="${task.id}" ${isChecked} onchange="toggleTaskSelection(${task.id})">
                        </td>
                        <td class="refund-task-td refund-task-td-bold" style="width: 7rem;">${task.reference}</td>
                        <td class="refund-task-td" style="width: 5rem;">
                            <span class="refund-type-badge ${typeColor}">${task.type ? task.type.charAt(0).toUpperCase() + task.type.slice(1) : ''}</span>
                        </td>
                        <td class="refund-task-td" style="width: 12rem;">${task.client_name || ''}</td>
                        <td class="refund-task-td" style="width: 8rem;">${task.invoice_number || ''}</td>
                        <td class="refund-task-td refund-task-td-center" style="width: 7rem;">
                            <span class="main-badge ${invoiceStatusColor}">${task.invoice_status ? task.invoice_status.charAt(0).toUpperCase() + task.invoice_status.slice(1) : ''}</span>
                        </td>
                        <td class="refund-task-td refund-task-td-bold refund-task-td-right" style="width: 7rem;">${task.amount || 0} KWD</td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', row);
            });

            // Show table, hide loading and empty state
            loading.style.display = 'none';
            table.style.display = 'table';
            empty.style.display = 'none';
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