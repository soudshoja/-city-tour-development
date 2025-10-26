<x-app-layout>

    <style>
        .supplier-details {
            text-transform: uppercase;
            display: flex;
            justify-content: space-between;
        }

        .supplier-details>div>div {
            width: 100%;
            margin: 0.5rem 0;
            padding: 0.5rem;
            border-radius: 5px;
        }

        .loading {
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
    </style>

    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li class="hover:underline">
                <a href="{{ route('suppliers.index') }}">Suppliers</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                {{ $supplier->name }}
            </li>
        </ul>
    </div>

    <div class="flex flex-col gap-2">

        <div class="grid bg-gradient-to-r from-blue-600 to-gray-800 p-4 rounded-md shadow-md w-full">
            <div class="flex justify-between items-center gap-4 mb-4">
                <div class="flex items-center justify-center rounded-full bg-black/50 font-semibold text-white p-2">
                    <x-application-logo style="width:32px;height:32px;" />
                    <h3 class="ml-2">{{ $supplier->name }}</h3>
                </div>
                <div class="flex items-center justify-end mb-4">
                    <form method="GET" action="{{ route('suppliers.show', ['suppliersId' => $supplier->id]) }}" class="flex flex-row items-end gap-2" id="task-filter-form">
                        <!-- Dropdown -->
                        <div class="flex flex-col justify-end">
                            <label class="text-xs font-semibold text-white mb-1">Filter By</label>
                            <select name="date_field" class="border rounded px-2 py-1 text-sm min-w-[150px]">
                                <option value="created_at" {{ request('date_field') == 'created_at' ? 'selected' : '' }}>Created Date</option>
                                <option value="supplier_pay_date" {{ request('date_field') == 'supplier_pay_date' ? 'selected' : '' }}>Issued Date</option>
                            </select>
                        </div>
                        <!-- Date Range -->
                        <div class="flex flex-col justify-end">
                            <label class="text-xs font-semibold text-white mb-1">Date Range</label>
                            <input type="text" id="task-date-range" class="border rounded px-2 py-1 text-sm min-w-[240px]" placeholder="Select date range" autocomplete="off" />
                            <input type="hidden" name="from_date" id="task_from_date" value="{{ request('from_date') }}">
                            <input type="hidden" name="to_date" id="task_to_date" value="{{ request('to_date') }}">
                        </div>
                        <!-- Buttons -->
                        <div class="flex flex-row items-end gap-1 pt-5">
                            <a href="{{ route('suppliers.show', ['suppliersId' => $supplier->id]) }}" class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs hover:bg-gray-200 border border-gray-300 flex items-center">Clear</a>
                            <button type="submit" class="px-2 py-1 rounded bg-blue-600 text-white text-xs hover:bg-blue-700 border border-blue-700 flex items-center">Apply</button>
                            <button type="button" id="export-pdf-btn" class="px-2 py-1 rounded bg-red-600 text-white text-xs hover:bg-red-700 border border-red-700 flex items-center">Export PDF</button>
                            <button type="button" id="export-excel-btn" class="px-2 py-1 rounded bg-green-600 text-white text-xs hover:bg-green-700 border border-green-700 flex items-center">Export Excel</button>
                        </div>
                    </form>
                </div>
            </div>

            @php
            $dateField = request('date_field', 'created_at');
            $fromDate = request('from_date');
            $toDate = request('to_date');
            $filteredTasks = $supplier->tasks;

            // Apply date filter
            if ($fromDate && $toDate) {
            $filteredTasks = $filteredTasks->filter(function($task) use ($dateField, $fromDate, $toDate) {
            $date = $task[$dateField];
            if (!$date) return false;
            $date = \Carbon\Carbon::parse($date)->format('Y-m-d');
            return $date >= $fromDate && $date <= $toDate;
                });
                }

                // Only "issued" status
                $filteredTasks=$filteredTasks->filter(function($task) {
                return strtolower($task->status) === 'issued';
                });

                // Calculate totals for filtered "issued" tasks
                $totalDebit = $filteredTasks->flatMap->journalEntries->sum('debit');
                $totalCredit = $filteredTasks->flatMap->journalEntries->sum('credit');
                @endphp
                <div class="flex gap-4 mb-2">
                    <div class="bg-green-100 text-green-800 px-4 py-2 rounded">Total Debit: {{ $totalDebit }}</div>
                    <div class="bg-red-100 text-red-800 px-4 py-2 rounded">Total Credit: {{ $totalCredit }}</div>
                    <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded">Balance: {{ $totalDebit - $totalCredit }}</div>
                </div>

                <div id="debit-credit" class="bg-white rounded-md shadow-md w-full overflow-x-auto">
                    @php
                    // Determine supplier type based on tasks (assuming all tasks are same type for this supplier)
                    $firstTask = $supplier->tasks->first();
                    $filteredTasks = $filteredTasks->take(20);
                    $supplierType = $firstTask ? $firstTask->type : null;
                    @endphp

                    <div class="min-w-max">
                        @if($supplierType === 'flight')
                        <div class="grid grid-cols-10 font-bold bg-gray-100 p-2 text-center rounded-t border-b border-gray-300 sticky top-0">
                            <div class="w-[120px]">Created Date</div>
                            <div class="w-[120px]">Task Ref</div>
                            <div class="w-[120px]">GDS Ref</div>
                            <div class="w-[140px]">Agent</div>
                            <div class="w-[110px]">Status</div>
                            <div class="w-[120px]">Issued Date</div>
                            <div class="w-[150px]">Passenger Name</div>
                            <div class="w-[90px]">Price</div>
                            <div class="w-[180px]">Departure</div>
                            <div class="w-[180px]">Arrival</div>
                        </div>
                        @elseif($supplierType === 'hotel')
                        <div class="grid grid-cols-10 font-bold bg-gray-100 p-2 text-center rounded-t border-b border-gray-300 sticky top-0 z-10">
                            <div class="w-[120px]">Created Date</div>
                            <div class="w-[120px]">Task Ref</div>
                            <div class="w-[140px]">Agent</div>
                            <div class="w-[110px]">Status</div>
                            <div class="w-[120px]">Issued Date</div>
                            <div class="w-[150px]">Info</div>
                            <div class="w-[50px]">Price</div>
                            <div class="w-[50px]">Debit</div>
                            <div class="w-[50px]">Credit</div>
                            <div class="w-[50px]">Balance</div>
                        </div>
                        @else
                        <div class="grid grid-cols-12 font-bold bg-gray-100 p-2 text-center rounded-t border-b border-gray-300 sticky top-0 z-10">
                            <div class="w-[120px]">Created Date</div>
                            <div class="w-[120px]">Task Ref</div>
                            <div class="w-[140px]">Agent</div>
                            <div class="w-[110px]">Status</div>
                            <div class="w-[120px]">Issued Date</div>
                            <div class="w-[150px]">Passenger Name</div>
                            <div class="w-[90px]">Price</div>

                        </div>
                        @endif

                        @php
                        $dateField = request('date_field', 'created_at');
                        $fromDate = request('from_date');
                        $toDate = request('to_date');
                        $filteredTasks = $supplier->tasks;

                        if ($fromDate && $toDate) {
                        $filteredTasks = $filteredTasks->filter(function($task) use ($dateField, $fromDate, $toDate) {
                        $date = $task[$dateField];
                        if (!$date) return false;
                        $date = \Carbon\Carbon::parse($date)->format('Y-m-d');
                        return $date >= $fromDate && $date <= $toDate;
                            });
                            }

                            // Sort by selected date field, newest first
                            $filteredTasks=$filteredTasks->sortByDesc(function($task) use ($dateField) {
                            return $task[$dateField] ? \Carbon\Carbon::parse($task[$dateField])->timestamp : 0;
                            });
                            @endphp
                            <div style="max-height: 550px; overflow-y: auto;">

                                @forelse($filteredTasks as $task)
                                @if($supplierType === 'flight')
                                <div class="general-ledger-rows grid grid-cols-10 gap-2 p-2 text-center border-b">
                                    <div class="w-[120px]">{{ $task->created_at ? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d') : '-' }}</div>
                                    <div class="w-[120px]">{{ $task->reference }}</div>
                                    <div class="w-[120px]">{{ $task->gds_reference ?? '-' }}</div>
                                    <div class="w-[140px]">{{ $task->agent ? $task->agent->name : '-' }}</div>
                                    <div class="w-[110px]">
                                        @php
                                        $status = strtolower($task->status);
                                        $statusColors = [
                                        'issued' => 'bg-green-100 text-green-700 border-green-400',
                                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-400',
                                        'cancelled' => 'bg-red-100 text-red-700 border-red-400',
                                        'confirmed' => 'bg-blue-100 text-blue-700 border-blue-400',
                                        'reissued' => 'bg-purple-100 text-purple-700 border-purple-400',
                                        'void' => 'bg-gray-200 text-gray-700 border-gray-400',
                                        'refund' => 'bg-pink-100 text-pink-700 border-pink-400',
                                        'emd' => 'bg-indigo-100 text-indigo-700 border-indigo-400',
                                        ];
                                        $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                        @endphp
                                        <span class="inline-block px-2 py-1 rounded border font-bold text-xs {{ $colorClass }}">
                                            {{ ucfirst($task->status) }}
                                        </span>
                                    </div>
                                    <div class="w-[120px]">{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('d-m-Y') : '-' }}</div>
                                    <div class="w-[150px]">{{ $task->passenger_name ?? '-' }}</div>
                                    <div class="w-[110px]">{{ $task->price ?? '-' }}</div>
                                    <div class="w-[180px]">
                                        @if ($task->type === 'flight' && $task->flightDetails)
                                        <strong>From:</strong> {{ $task->flightDetails->airport_from ?? '-' }}<br>
                                        {{ optional($task->flightDetails->departure_time)->format('d-m-Y H:i') ?? '-' }}
                                        @else
                                        -
                                        @endif
                                    </div>
                                    <div class="w-[180px]">
                                        @if ($task->type === 'flight' && $task->flightDetails)
                                        <strong>To:</strong> {{ $task->flightDetails->airport_to ?? '-' }}<br>
                                        {{ optional($task->flightDetails->arrival_time)->format('d-m-Y H:i') ?? '-' }}
                                        @else
                                        -
                                        @endif
                                    </div>
                                </div>
                                @elseif($supplierType === 'hotel')
                                @php
                                $balance = 0;
                                $hotelTasks = $filteredTasks->take(20);
                                @endphp
                                @foreach($hotelTasks as $task)
                                @php
                                $debit = $task->journalEntries->first()->debit ?? 0;
                                $credit = $task->journalEntries->first()->credit ?? 0;
                                $balance += $debit - $credit;
                                @endphp
                                <div class="general-ledger-rows grid grid-cols-10 gap-2 p-2 text-center border-b">
                                    <div class="w-[120px]">{{ $task->created_at ? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d') : '-' }}</div>
                                    <div class="w-[120px]">{{ $task->reference }}</div>
                                    <div class="w-[140px]">{{ $task->agent ? $task->agent->name : '-' }}</div>
                                    <div class="w-[110px]">
                                        @php
                                        $status = strtolower($task->status);
                                        $statusColors = [
                                        'issued' => 'bg-green-100 text-green-700 border-green-400',
                                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-400',
                                        'cancelled' => 'bg-red-100 text-red-700 border-red-400',
                                        'confirmed' => 'bg-blue-100 text-blue-700 border-blue-400',
                                        'reissued' => 'bg-purple-100 text-purple-700 border-purple-400',
                                        'void' => 'bg-gray-200 text-gray-700 border-gray-400',
                                        'refund' => 'bg-pink-100 text-pink-700 border-pink-400',
                                        'emd' => 'bg-indigo-100 text-indigo-700 border-indigo-400',
                                        ];
                                        $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                        @endphp
                                        <span class="inline-block px-2 py-1 rounded border font-bold text-xs {{ $colorClass }}">
                                            {{ ucfirst($task->status) }}
                                        </span>
                                    </div>
                                    <div class="w-[120px]">{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-' }}</div>
                                    <div class="w-[150px]">{{ $task->passenger_name ?? '-' }} <br>
                                        {{ $task->hotelDetails->hotel->name ?? '-' }}<br>
                                        {{ $task->hotelDetails->check_in ?? '-' }} to {{ $task->hotelDetails->check_out ?? '-' }}
                                    </div>
                                    <div class="w-[50px]">{{ $task->price ?? '-' }}</div>
                                    <div class="w-[50px]">{{ $debit ?: '-' }}</div>
                                    <div class="w-[50px]">{{ $credit ?: '-' }}</div>
                                    <div class="w-[50px]">{{ $balance }}</div>
                                </div>
                                @endforeach
                                @else
                                <div class="general-ledger-rows grid grid-cols-7 gap-2 p-2 text-center border-b">
                                    <div class="w-[120px]">{{ $task->created_at ? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d') : '-' }}</div>
                                    <div class="w-[120px]">{{ $task->reference }}</div>
                                    <div class="w-[140px]">{{ $task->agent ? $task->agent->name : '-' }}</div>
                                    <div class="w-[110px]">
                                        @php
                                        $status = strtolower($task->status);
                                        $statusColors = [
                                        'issued' => 'bg-green-100 text-green-700 border-green-400',
                                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-400',
                                        'cancelled' => 'bg-red-100 text-red-700 border-red-400',
                                        'confirmed' => 'bg-blue-100 text-blue-700 border-blue-400',
                                        'reissued' => 'bg-purple-100 text-purple-700 border-purple-400',
                                        'void' => 'bg-gray-200 text-gray-700 border-gray-400',
                                        'refund' => 'bg-pink-100 text-pink-700 border-pink-400',
                                        'emd' => 'bg-indigo-100 text-indigo-700 border-indigo-400',
                                        ];
                                        $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                        @endphp
                                        <span class="inline-block px-2 py-1 rounded border font-bold text-xs {{ $colorClass }}">
                                            {{ ucfirst($task->status) }}
                                        </span>
                                    </div>
                                    <div class="w-[120px]">{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-' }}</div>
                                    <div class="w-[150px]">{{ $task->passenger_name ?? '-' }}</div>
                                    <div class="w-[110px]">{{ $task->price ?? '-' }}</div>

                                </div>
                                @endif
                                @empty
                                <div class="general-ledger-rows grid grid-cols-12 gap-2 p-2 text-center text-gray-500">
                                    <div colspan="10">No entries found for selected dates.</div>
                                </div>
                                @endforelse
                            </div>
                    </div>
                </div>
        </div>
    </div>

    <div class="my-6 p-4 grid bg-white rounded-md shadow-md w-full overflow-x-auto">
        <p class="font-semibold">Supplier Policy</p>
        @include('suppliers.partials.add_procedure')
        @include('suppliers.partials.list_procedure', ['companyId' => $companyId, 'supplierCompany' => $supplierCompany])
    </div>

    <div class="body p-6 bg-white border-b border-gray-200 rounded-md shadow-md my-2">
        <div class="font-semibold text-lg">
            Supplier Details
        </div>
        <div class="supplier-details">
            <div class="overflow-hidden">
                <div>{{ $supplier->name }}</div>
                <div>{{ $supplier->contact_person }}</div>
                <div>{{ $supplier->email }}</div>
                <div>{{ $supplier->phone }}</div>
                <div>{{ $supplier->address }}</div>
                <div>{{ $supplier->city }}</div>
            </div>
            <div class="overflow-hidden">
                <div>{{ $supplier->state }}</div>
                <div>{{ $supplier->postal_code }}</div>
                <div>{{ $supplier->country->name }}</div>
                <div>{{ $supplier->payment_terms }}</div>
            </div>
        </div>
    </div>
    <script>
        if (document.getElementById('customize-columns-btn')) {
            document.getElementById('customize-columns-btn').addEventListener('click', function(e) {
                const dropdown = document.getElementById('columns-dropdown');
                dropdown.classList.toggle('hidden');
                dropdown.style.left = (e.target.getBoundingClientRect().left) + 'px';
            });
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('columns-dropdown');
            if(dropdown){
                if (!dropdown.contains(e.target) && e.target.id !== 'customize-columns-btn') {
                    dropdown.classList.add('hidden');
                }
            }
        });

        document.querySelectorAll('.column-toggle').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const colIndex = parseInt(this.dataset.col);
                const rows = document.querySelectorAll('#debit-credit .grid.grid-cols-12');
                rows.forEach(row => {
                    if (row.children[colIndex]) {
                        row.children[colIndex].style.display = this.checked ? '' : 'none';
                    }
                });
                const dataRows = document.querySelectorAll('#debit-credit .general-ledger-rows');
                dataRows.forEach(row => {
                    if (row.children[colIndex]) {
                        row.children[colIndex].style.display = this.checked ? '' : 'none';
                    }
                });
            });
        });
    </script>
    <script>
        document.getElementById('export-pdf-btn').addEventListener('click', function() {
            const form = document.getElementById('task-filter-form');
            const range = document.getElementById('task-date-range').value.split(' to ');
            document.getElementById('task_from_date').value = range[0] ? range[0].trim() : '';
            document.getElementById('task_to_date').value = range[1] ? range[1].trim() : range[0];

            form.action = "{{ route('suppliers.suppliers.export.pdf', ['suppliersId' => $supplier->id]) }}";
            form.method = "GET";
            form.submit();

            setTimeout(() => {
                form.action = "{{ route('suppliers.show', ['suppliersId' => $supplier->id]) }}";
            }, 1000);
        });
        document.getElementById('export-excel-btn').addEventListener('click', function() {
            const form = document.getElementById('task-filter-form');
            const range = document.getElementById('task-date-range').value.split(' to ');
            document.getElementById('task_from_date').value = range[0] ? range[0].trim() : '';
            document.getElementById('task_to_date').value = range[1] ? range[1].trim() : range[0];

            form.action = "{{ route('suppliers.suppliers.export.excel', ['suppliersId' => $supplier->id]) }}";
            form.method = "GET";
            form.submit();

            setTimeout(() => {
                form.action = "{{ route('suppliers.show', ['suppliersId' => $supplier->id]) }}";
            }, 1000);
        });
    </script>

    <script>
        flatpickr("#task-date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [
                "{{ request('from_date') }}",
                "{{ request('to_date') }}"
            ].filter(Boolean)
        });

        document.getElementById('task-filter-form').addEventListener('submit', function(e) {
            const range = document.getElementById('task-date-range').value.split(' to ');
            document.getElementById('task_from_date').value = range[0] ? range[0].trim() : '';
            document.getElementById('task_to_date').value = range[1] ? range[1].trim() : range[0];
        });
    </script>

    <script>
        let supplierId = "{{ json_encode($supplier->id) }}";

        const filterBtn = document.getElementById('filter-btn');
        const clearBtn = document.getElementById('clear-btn');
        const loadingSpinner = document.getElementById('loading-spinner');
        const dateRangeInput = document.getElementById('date-range');

        flatpickr(dateRangeInput, {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [new Date().toISOString().split('T')[0], new Date().toISOString().split('T')[0]]
        });

        filterBtn.addEventListener('click', function() {
            updateRows();
        });

        clearBtn.addEventListener('click', function() {
            dateRangeInput.value = '';
            let ledgerBody = document.getElementById('debit-credit');
            let rows = ledgerBody.querySelectorAll('.general-ledger-rows');
            rows.forEach(row => row.remove());
        });

        function updateRows() {
            let dates = dateRangeInput.value.split(' to ');
            let fromDate = dates[0] ? dates[0].trim() : '';
            let toDate = dates[1] ? dates[1].trim() : dates[0];

            if (!fromDate || !toDate) return;

            let url = `{{ route('suppliers.suppliers.ledger-by-date', ['supplierId' => '__supplierId__']) }}?fromDate=${fromDate} 00:00:00&toDate=${toDate} 23:59:59`;
            url = url.replace('__supplierId__', supplierId);

            filterBtn.disabled = true;
            clearBtn.disabled = true;
            loadingSpinner.classList.remove('hidden');

            let ledgerBody = document.getElementById('debit-credit');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    // Remove old rows except header
                    let rows = ledgerBody.querySelectorAll('.general-ledger-rows');
                    rows.forEach(row => row.remove());

                    if (data.entries.length === 0) {
                        let emptyRow = document.createElement('div');
                        emptyRow.className = 'general-ledger-rows grid grid-cols-8 gap-2 p-2 text-center text-gray-500';
                        emptyRow.innerHTML = `<div colspan="8">No entries found for selected dates.</div>`;
                        ledgerBody.appendChild(emptyRow);
                    } else {
                        data.entries.sort((a, b) => {
                            const dateA = a.supplier_pay_date ? new Date(a.supplier_pay_date) : new Date(0);
                            const dateB = b.supplier_pay_date ? new Date(b.supplier_pay_date) : new Date(0);
                            return dateB - dateA;
                        });
                        data.entries.forEach(task => {
                            let info = '-';
                            if (task.type === 'flight' && task.flight_details) {
                                const f = task.flight_details;
                                info = `${f.airport_from ?? '-'} → ${f.airport_to ?? '-'}<br>${f.departure_time ?? '-'} - ${f.arrival_time ?? '-'}`;
                            } else if (task.type === 'hotel' && task.hotel_details) {
                                const h = task.hotel_details;
                                info = `${h.hotel?.name ?? '-'}<br>${h.check_in ?? '-'} - ${h.check_out ?? '-'}`;
                            } else if (task.additional_info) {
                                info = task.additional_info;
                            }

                            let row = document.createElement('div');
                            row.className = 'general-ledger-rows grid grid-cols-8 gap-2 p-2 text-center';
                            row.innerHTML = `
                            <div>${task.created_at.substring(0, 10)}</div>
                            <div>${task.reference ?? '-'}</div>
                            <div>${task.type ?? '-'}</div>
                            <div>${task.agent ? task.agent.name ?? '-' : '-'}</div>
                            <div>${task.status ? task.status.charAt(0).toUpperCase() + task.status.slice(1) : '-'}</div>
                            <div>${task.supplier_pay_date ? task.supplier_pay_date.substring(0, 10) : '-'}</div>
                            <div>${task.passenger_name ?? '-'}</div>
                            <div class="text-xs">${info}</div>
                        `;
                            ledgerBody.appendChild(row);
                        });
                    }
                })
                .finally(() => {
                    filterBtn.disabled = false;
                    clearBtn.disabled = false;
                    loadingSpinner.classList.add('hidden');
                });
        }

        // Initial load
        updateRows();
    </script>
</x-app-layout>