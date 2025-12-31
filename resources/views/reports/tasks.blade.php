<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Tasks Report</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Total Tasks</p>
                        <p class="text-3xl font-bold mt-2">{{ number_format($totalTasks) }}</p>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 rounded-full p-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium uppercase tracking-wide">Total Amount</p>
                        <p class="text-3xl font-bold mt-2">{{ number_format($totalAmount, 3) }} <span class="text-lg">KWD</span></p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-full p-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4 mb-4">
            <form method="GET" action="{{ route('reports.tasks') }}" class="space-y-4" id="filterForm">
                <!-- Date Preset Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quick Date Filter</label>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="setDatePreset('this_week')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'this_week' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            This Week
                        </button>
                        <button type="button" onclick="setDatePreset('this_month')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'this_month' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            This Month
                        </button>
                        <button type="button" onclick="setDatePreset('this_year')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'this_year' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            This Year
                        </button>
                        <button type="button" onclick="setDatePreset('january')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'january' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            January
                        </button>
                        <button type="button" onclick="setDatePreset('february')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'february' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            February
                        </button>
                        <button type="button" onclick="setDatePreset('march')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'march' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            March
                        </button>
                        <button type="button" onclick="setDatePreset('april')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'april' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            April
                        </button>
                        <button type="button" onclick="setDatePreset('may')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'may' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            May
                        </button>
                        <button type="button" onclick="setDatePreset('june')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'june' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            June
                        </button>
                        <button type="button" onclick="setDatePreset('july')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'july' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            July
                        </button>
                        <button type="button" onclick="setDatePreset('august')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'august' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            August
                        </button>
                        <button type="button" onclick="setDatePreset('september')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'september' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            September
                        </button>
                        <button type="button" onclick="setDatePreset('october')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'october' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            October
                        </button>
                        <button type="button" onclick="setDatePreset('november')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'november' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            November
                        </button>
                        <button type="button" onclick="setDatePreset('december')" 
                            class="preset-btn px-3 py-2 text-sm rounded-md {{ $datePreset === 'december' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            December
                        </button>
                    </div>
                    <input type="hidden" name="date_preset" id="date_preset" value="{{ $datePreset ?? '' }}">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" name="date_from" id="date_from" value="{{ $dateFrom ?? '' }}" 
                            class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                            onchange="clearPreset()">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" name="date_to" id="date_to" value="{{ $dateTo ?? '' }}" 
                            class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                            onchange="clearPreset()">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Suppliers</label>
                        <div class="relative">
                            <input type="text" id="supplierSearch" placeholder="Search suppliers..." 
                                class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer"
                                onclick="toggleDropdown('supplierDropdown')" readonly>
                            <div id="supplierDropdown" class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                <div class="p-2 border-b">
                                    <input type="text" id="supplierSearchInput" placeholder="Type to search..." 
                                        class="w-full border border-gray-300 rounded px-2 py-1 text-sm" 
                                        onkeyup="filterOptions('supplierSearchInput', 'supplierOptions')">
                                </div>
                                <div id="supplierOptions" class="p-2">
                                    @foreach($suppliers as $supplier)
                                        <label class="flex items-center px-2 py-1.5 hover:bg-gray-50 cursor-pointer rounded">
                                            <input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" 
                                                {{ in_array($supplier->id, $supplierIds) ? 'checked' : '' }}
                                                class="mr-2 rounded text-indigo-600 focus:ring-indigo-500" 
                                                onchange="updateDisplay('supplierSearch', 'supplier_ids[]', 'suppliers')">
                                            <span class="text-sm">{{ $supplier->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ count($supplierIds) > 0 ? count($supplierIds) . ' selected' : 'Click to select' }}</p>
                    </div>

                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Statuses</label>
                        <div class="relative">
                            <input type="text" id="statusSearch" placeholder="Select statuses..." 
                                class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer"
                                onclick="toggleDropdown('statusDropdown')" readonly>
                            <div id="statusDropdown" class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                <div class="p-2">
                                    @foreach($availableStatuses as $status)
                                        <label class="flex items-center px-2 py-1.5 hover:bg-gray-50 cursor-pointer rounded">
                                            <input type="checkbox" name="statuses[]" value="{{ $status }}" 
                                                {{ in_array($status, $statuses) ? 'checked' : '' }}
                                                class="mr-2 rounded text-indigo-600 focus:ring-indigo-500" 
                                                onchange="updateDisplay('statusSearch', 'statuses[]', 'statuses')">
                                            <span class="text-sm capitalize">{{ ucfirst($status) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ count($statuses) > 0 ? count($statuses) . ' selected' : 'Click to select' }}</p>
                    </div>

                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Issued By</label>
                        <div class="relative">
                            <input type="text" id="issuedBySearch" placeholder="Select issued by..." 
                                class="w-full border border-gray-300 rounded-md shadow-sm px-3 py-2 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer"
                                onclick="toggleDropdown('issuedByDropdown')" readonly>
                            <div id="issuedByDropdown" class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                <div class="p-2">
                                    @foreach($availableIssuedBy as $issuer)
                                        <label class="flex items-center px-2 py-1.5 hover:bg-gray-50 cursor-pointer rounded">
                                            <input type="checkbox" name="issued_by[]" value="{{ $issuer }}" 
                                                {{ in_array($issuer, $issuedBy) ? 'checked' : '' }}
                                                class="mr-2 rounded text-indigo-600 focus:ring-indigo-500" 
                                                onchange="updateDisplay('issuedBySearch', 'issued_by[]', 'issued_by')">
                                            <span class="text-sm">{{ $issuer }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ count($issuedBy) > 0 ? count($issuedBy) . ' selected' : 'Click to select' }}</p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" 
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Apply Filters
                    </button>
                    <a href="{{ route('reports.tasks') }}" 
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Clear Filters
                    </a>
                    <button type="submit" formaction="{{ route('reports.tasks.pdf') }}" formtarget="_blank"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Generate PDF
                    </button>
                </div>
            </form>
        </div>

        <div class="p-4 overflow-x-auto bg-white rounded-lg shadow-md grid gap-4">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr>
                        <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Task Reference</th>
                        <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Original Reference</th>
                        <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Passenger Name</th>
                        <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Supplier</th>
                        <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Supplier Pay Date</th>
                        <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Status</th>
                        <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                    <tr class="hover:bg-gray-50">
                        <td class="py-2 px-4 border-b">{{ $task->reference }}</td>
                        <td class="py-2 px-4 border-b">{{ $task->original_reference ?? 'N/A' }}</td>
                        <td class="py-2 px-4 border-b">{{ $task->passenger_name ?? 'N/A' }}</td>
                        <td class="py-2 px-4 border-b">{{ $task->supplier->name ?? 'N/A' }}</td>
                        <td class="py-2 px-4 border-b">{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : 'N/A' }}</td>
                        <td class="py-2 px-4 border-b">
                            <span class="px-2 py-1 text-xs rounded-full 
                                @if($task->status === 'completed') bg-green-100 text-green-800
                                @elseif($task->status === 'pending') bg-yellow-100 text-yellow-800
                                @elseif($task->status === 'cancelled' || $task->status === 'void') bg-red-100 text-red-800
                                @else bg-blue-100 text-blue-800
                                @endif">
                                {{ ucfirst($task->status) }}
                            </span>
                        </td>
                        <td class="py-2 px-4 border-b">{{ number_format($task->total, 3) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-4 px-4 text-center text-gray-500">
                            No tasks found matching the selected filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <x-pagination :data="$tasks" />
        </div>

    <script>
        function setDatePreset(preset) {
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            document.getElementById('date_preset').value = preset;
            document.getElementById('filterForm').submit();
        }

        function clearPreset() {
            document.getElementById('date_preset').value = '';
        }

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            const allDropdowns = document.querySelectorAll('[id$="Dropdown"]');
            
            allDropdowns.forEach(d => {
                if (d.id !== dropdownId) {
                    d.classList.add('hidden');
                }
            });
            
            dropdown.classList.toggle('hidden');
        }

        function updateDisplay(inputId, checkboxName, type) {
            const checkboxes = document.querySelectorAll(`input[name="${checkboxName}"]:checked`);
            const input = document.getElementById(inputId);
            const count = checkboxes.length;
            
            if (count > 0) {
                input.value = `${count} selected`;
            } else {
                if (type === 'suppliers') {
                    input.value = 'Search suppliers...';
                } else if (type === 'statuses') {
                    input.value = 'Select statuses...';
                } else if (type === 'issued_by') {
                    input.value = 'Select issued by...';
                }
            }
        }

        function filterOptions(searchInputId, optionsId) {
            const searchValue = document.getElementById(searchInputId).value.toLowerCase();
            const options = document.getElementById(optionsId);
            const labels = options.getElementsByTagName('label');
            
            for (let i = 0; i < labels.length; i++) {
                const text = labels[i].textContent.toLowerCase();
                if (text.includes(searchValue)) {
                    labels[i].style.display = '';
                } else {
                    labels[i].style.display = 'none';
                }
            }
        }

        document.addEventListener('click', function(event) {
            const dropdowns = document.querySelectorAll('[id$="Dropdown"]');
            const isClickInside = event.target.closest('.relative');
            
            if (!isClickInside) {
                dropdowns.forEach(dropdown => {
                    dropdown.classList.add('hidden');
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            updateDisplay('supplierSearch', 'supplier_ids[]', 'suppliers');
            updateDisplay('statusSearch', 'statuses[]', 'statuses');
            updateDisplay('issuedBySearch', 'issued_by[]', 'issued_by');
        });
    </script>
</x-app-layout>