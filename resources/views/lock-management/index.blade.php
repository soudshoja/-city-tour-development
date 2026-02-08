<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <h2 class="text-2xl md:text-3xl font-bold">Lock Management</h2>
            <div data-tooltip="Financial Record Locking"
                class="relative w-10 h-10 md:w-12 md:h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white">
                    <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3zm0 10c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>
                </svg>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('invoices.index') }}" class="px-4 py-2 text-sm rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 border">
                ← Back to Invoices
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="flex items-center gap-3 rounded-lg p-4 shadow-sm bg-blue-50 border border-blue-200">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs font-medium text-blue-600">Total Invoices</div>
                <div class="text-xl font-bold text-blue-700">{{ number_format($totalInvoices) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg p-4 shadow-sm bg-red-50 border border-red-200">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs font-medium text-red-600">Locked</div>
                <div class="text-xl font-bold text-red-700">{{ number_format($lockedInvoices) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg p-4 shadow-sm bg-green-50 border border-green-200">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 text-green-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs font-medium text-green-600">Unlocked</div>
                <div class="text-xl font-bold text-green-700">{{ number_format($unlockedInvoices) }}</div>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg p-4 shadow-sm bg-amber-50 border border-amber-200">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <div>
                <div class="text-xs font-medium text-amber-600">Paid & Unlocked</div>
                <div class="text-xl font-bold text-amber-700">{{ number_format($paidUnlocked) }}</div>
            </div>
        </div>
    </div>

    {{-- Main Content: Tabs --}}
    <div x-data="{ activeTab: 'monthly' }" class="panel rounded-lg">
        {{-- Tab Headers --}}
        <div class="flex border-b">
            <button @click="activeTab = 'monthly'"
                :class="activeTab === 'monthly' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-3 text-sm transition-colors">
                Monthly Closing
            </button>
            <button @click="activeTab = 'period'"
                :class="activeTab === 'period' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-3 text-sm transition-colors">
                Lock by Period
            </button>
            <button @click="activeTab = 'invoices'"
                :class="activeTab === 'invoices' ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-3 text-sm transition-colors">
                Invoice List
            </button>
        </div>

        {{-- TAB 1: Monthly Closing --}}
        <div x-show="activeTab === 'monthly'" x-cloak class="p-4 md:p-6">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Monthly Period Closing</h3>
                <p class="text-sm text-gray-500 mt-1">Lock or unlock all invoices for a specific month. This is the recommended way to protect financial records after reconciliation.</p>
            </div>

            <div class="space-y-3">
                @forelse($monthlySummary as $month)
                    @php
                        $monthDate = \Carbon\Carbon::parse($month->month . '-01');
                        $isFullyLocked = $month->unlocked == 0 && $month->total > 0;
                        $isPartiallyLocked = $month->locked > 0 && $month->unlocked > 0;
                        $percentage = $month->total > 0 ? round(($month->locked / $month->total) * 100) : 0;
                    @endphp
                    <div class="border rounded-lg {{ $isFullyLocked ? 'border-red-200 bg-red-50/30' : ($isPartiallyLocked ? 'border-amber-200 bg-amber-50/30' : 'border-gray-200 bg-white') }}">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 p-4">
                            {{-- Month Info --}}
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="flex-shrink-0 w-14 h-14 rounded-lg flex flex-col items-center justify-center {{ $isFullyLocked ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                    <span class="text-xs font-medium uppercase">{{ $monthDate->format('M') }}</span>
                                    <span class="text-lg font-bold leading-none">{{ $monthDate->format('Y') }}</span>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-semibold text-gray-800">{{ $monthDate->format('F Y') }}</h4>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 mt-1">
                                        <span>{{ $month->total }} invoices</span>
                                        <span class="text-green-600 font-medium">{{ $month->locked }} locked</span>
                                        <span class="text-gray-400">{{ $month->unlocked }} unlocked</span>
                                        @if($month->paid_unlocked > 0)
                                            <span class="text-amber-600 font-medium">{{ $month->paid_unlocked }} paid & unlocked</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-400 mt-0.5">Total: {{ number_format($month->total_amount, 3) }} KWD</div>
                                </div>
                            </div>

                            {{-- Progress Bar --}}
                            <div class="flex-shrink-0 w-full md:w-40">
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>Locked</span>
                                    <span class="font-medium">{{ $percentage }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="{{ $isFullyLocked ? 'bg-red-500' : ($isPartiallyLocked ? 'bg-amber-500' : 'bg-gray-300') }} h-2 rounded-full transition-all" 
                                         style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if(!$isFullyLocked)
                                    <form action="{{ route('lock-management.lock-by-month') }}" method="POST" 
                                        onsubmit="return confirm('Lock ALL {{ $month->unlocked }} unlocked invoices for {{ $monthDate->format('F Y') }}?')">
                                        @csrf
                                        <input type="hidden" name="month" value="{{ $month->month }}">
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                                            </svg>
                                            Lock Month
                                        </button>
                                    </form>
                                @endif

                                @if($month->locked > 0)
                                    <div x-data="{ showUnlockModal: false }">
                                        <button @click="showUnlockModal = true" 
                                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                            </svg>
                                            Unlock
                                        </button>

                                        {{-- Unlock Modal --}}
                                        <div x-cloak x-show="showUnlockModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                                            <div @click.away="showUnlockModal = false" class="bg-white rounded-xl w-full max-w-md shadow-xl">
                                                <div class="p-4 border-b">
                                                    <h3 class="font-semibold text-gray-800">Unlock {{ $monthDate->format('F Y') }}</h3>
                                                    <p class="text-sm text-gray-500 mt-1">This will unlock {{ $month->locked }} invoice(s). Please provide a reason.</p>
                                                </div>
                                                <form action="{{ route('lock-management.unlock-by-month') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="month" value="{{ $month->month }}">
                                                    <div class="p-4">
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for unlocking *</label>
                                                        <textarea name="reason" rows="3" required placeholder="e.g., Found error in invoice INV-2026-00345, need to correct amount..."
                                                            class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                                                    </div>
                                                    <div class="p-4 border-t bg-gray-50 flex justify-end gap-2 rounded-b-xl">
                                                        <button type="button" @click="showUnlockModal = false" class="px-4 py-2 text-sm rounded-lg border hover:bg-gray-100">Cancel</button>
                                                        <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-amber-600 text-white hover:bg-amber-700">Unlock Month</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if($isFullyLocked)
                                    <span class="inline-flex items-center gap-1 px-3 py-2 text-xs font-medium rounded-lg bg-green-100 text-green-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Closed
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-400">
                        <p>No invoices found for monthly summary.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- TAB 2: Lock by Period --}}
        <div x-show="activeTab === 'period'" x-cloak class="p-4 md:p-6">
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Lock by Date Range</h3>
                <p class="text-sm text-gray-500 mt-1">Lock all invoices with an invoice date on or before the selected date. You can choose which statuses to lock.</p>
            </div>

            <form action="{{ route('lock-management.lock-by-period') }}" method="POST" 
                class="max-w-xl bg-gray-50 rounded-xl p-6 border"
                onsubmit="return confirm('Are you sure? This will lock ALL matching invoices before the selected date.')">
                @csrf

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lock all invoices on or before *</label>
                        <input type="date" name="lock_before_date" required 
                            value="{{ old('lock_before_date', now()->subMonth()->endOfMonth()->format('Y-m-d')) }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-400 mt-1">Tip: Set to end of last month to close the previous period</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lock invoices with status:</label>
                        <div class="flex flex-wrap gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="lock_status[]" value="paid" checked 
                                    class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                <span class="text-sm text-gray-700">Paid</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="lock_status[]" value="unpaid"
                                    class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                <span class="text-sm text-gray-700">Unpaid</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="lock_status[]" value="partial"
                                    class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                <span class="text-sm text-gray-700">Partial</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.89 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                            </svg>
                            Lock Invoices
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- TAB 3: Invoice List --}}
        <div x-show="activeTab === 'invoices'" x-cloak class="p-4 md:p-6" 
             x-data="{ selectedIds: [], selectAll: false, showBulkUnlockModal: false, unlockReason: '' }">

            {{-- Filters --}}
            <div class="flex flex-col md:flex-row md:items-end gap-3 mb-4">
                <form method="GET" action="{{ route('lock-management.index') }}" class="flex flex-wrap items-end gap-2 w-full">
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Status</label>
                        <select name="filter" class="border rounded-lg px-3 py-2 text-sm min-w-[130px]" onchange="this.form.submit()">
                            <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All</option>
                            <option value="locked" {{ $filter === 'locked' ? 'selected' : '' }}>Locked Only</option>
                            <option value="unlocked" {{ $filter === 'unlocked' ? 'selected' : '' }}>Unlocked Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Invoice # or client..." 
                            class="border rounded-lg px-3 py-2 text-sm min-w-[180px]">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700">Filter</button>
                    <a href="{{ route('lock-management.index') }}" class="px-4 py-2 text-sm rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 border">Clear</a>
                </form>
            </div>

            {{-- Bulk Actions Bar --}}
            <div x-show="selectedIds.length > 0" x-cloak
                class="flex items-center gap-3 mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <span class="text-sm font-medium text-blue-700" x-text="selectedIds.length + ' invoice(s) selected'"></span>
                <button @click="bulkLock()" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded bg-red-600 text-white hover:bg-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.89 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                    </svg>
                    Lock Selected
                </button>
                <button @click="showBulkUnlockModal = true" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100">
                    Unlock Selected
                </button>
                <button @click="selectedIds = []; selectAll = false" class="text-xs text-gray-500 hover:text-gray-700 underline">Clear</button>
            </div>

            {{-- Invoice Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 text-gray-600">
                        <tr>
                            <th class="px-3 py-3 text-left w-10">
                                <input type="checkbox" x-model="selectAll" @change="toggleAll()" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                            </th>
                            <th class="px-3 py-3 text-left">Invoice</th>
                            <th class="px-3 py-3 text-left">Client</th>
                            <th class="px-3 py-3 text-left">Agent</th>
                            <th class="px-3 py-3 text-left">Date</th>
                            <th class="px-3 py-3 text-right">Amount</th>
                            <th class="px-3 py-3 text-center">Status</th>
                            <th class="px-3 py-3 text-center">Lock</th>
                            <th class="px-3 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($invoices as $invoice)
                            <tr class="hover:bg-gray-50 {{ $invoice->is_locked ? 'bg-red-50/30' : '' }}">
                                <td class="px-3 py-3">
                                    <input type="checkbox" value="{{ $invoice->id }}" x-model="selectedIds" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                </td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('invoice.details', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number]) }}" 
                                        class="font-medium text-blue-600 hover:underline" target="_blank">
                                        {{ $invoice->invoice_number }}
                                    </a>
                                </td>
                                <td class="px-3 py-3 text-gray-700">{{ $invoice->client?->full_name ?? '-' }}</td>
                                <td class="px-3 py-3 text-gray-700">{{ $invoice->agent?->name ?? '-' }}</td>
                                <td class="px-3 py-3 text-gray-500">{{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') : '-' }}</td>
                                <td class="px-3 py-3 text-right font-medium">{{ number_format($invoice->amount, 3) }}</td>
                                <td class="px-3 py-3 text-center">
                                    @if($invoice->status === 'paid')
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">Paid</span>
                                    @elseif($invoice->status === 'partial')
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700">Partial</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">{{ ucfirst($invoice->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($invoice->is_locked)
                                        <div data-tooltip="Locked by {{ $invoice->lockedByUser?->name ?? 'Unknown' }} on {{ $invoice->locked_at?->format('d M Y H:i') }}">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 font-medium">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.89 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                                                </svg>
                                                Locked
                                            </span>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">
                                            Open
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($invoice->is_locked)
                                        <form action="{{ route('invoice.unlock', $invoice->id) }}" method="POST" class="inline"
                                            onsubmit="return confirm('Unlock this invoice?')">
                                            @csrf
                                            <button type="submit" data-tooltip="Unlock" class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('invoice.lock', $invoice->id) }}" method="POST" class="inline"
                                            onsubmit="return confirm('Lock this invoice?')">
                                            @csrf
                                            <button type="submit" data-tooltip="Lock" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-50 hover:text-red-600 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-gray-400">No invoices found matching your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $invoices->links() }}
            </div>

            {{-- Bulk Unlock Modal --}}
            <div x-cloak x-show="showBulkUnlockModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                <div @click.away="showBulkUnlockModal = false" class="bg-white rounded-xl w-full max-w-md shadow-xl">
                    <div class="p-4 border-b">
                        <h3 class="font-semibold text-gray-800">Unlock Selected Invoices</h3>
                        <p class="text-sm text-gray-500 mt-1" x-text="'Unlocking ' + selectedIds.length + ' invoice(s). Please provide a reason.'"></p>
                    </div>
                    <div class="p-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for unlocking *</label>
                        <textarea x-model="unlockReason" rows="3" required placeholder="e.g., Need to correct payment details..."
                            class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div class="p-4 border-t bg-gray-50 flex justify-end gap-2 rounded-b-xl">
                        <button @click="showBulkUnlockModal = false" class="px-4 py-2 text-sm rounded-lg border hover:bg-gray-100">Cancel</button>
                        <button @click="bulkUnlock()" class="px-4 py-2 text-sm rounded-lg bg-amber-600 text-white hover:bg-amber-700">Unlock</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAll() {
            const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
            const selectAll = document.querySelector('thead input[type="checkbox"]');
            const component = document.querySelector('[x-data]').__x.$data;
            
            if (selectAll.checked) {
                component.selectedIds = Array.from(checkboxes).map(cb => cb.value);
            } else {
                component.selectedIds = [];
            }
        }

        async function bulkLock() {
            const component = document.querySelector('[x-data]').__x.$data;
            if (!component.selectedIds.length) return;
            if (!confirm(`Lock ${component.selectedIds.length} invoice(s)?`)) return;

            try {
                const response = await fetch('{{ route("lock-management.bulk-lock") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ invoice_ids: component.selectedIds })
                });
                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Failed to lock invoices.');
                }
            } catch (e) {
                alert('Something went wrong.');
            }
        }

        async function bulkUnlock() {
            const component = document.querySelector('[x-data]').__x.$data;
            if (!component.selectedIds.length || !component.unlockReason.trim()) {
                alert('Please provide a reason for unlocking.');
                return;
            }

            try {
                const response = await fetch('{{ route("lock-management.bulk-unlock") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        invoice_ids: component.selectedIds,
                        reason: component.unlockReason
                    })
                });
                const result = await response.json();
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Failed to unlock invoices.');
                }
            } catch (e) {
                alert('Something went wrong.');
            }
        }
    </script>
</x-app-layout>
