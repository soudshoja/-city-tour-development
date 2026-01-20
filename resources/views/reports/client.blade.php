<x-app-layout>
    <div class="container mx-auto p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100">Client Report</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    @if($dateFrom && $dateTo)
                        Date Range: <span class="font-semibold">{{ \Carbon\Carbon::parse($dateFrom)->format('d-m-Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d-m-Y') }}</span>
                    @else
                        <span class="font-semibold">All Time</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-xs font-medium uppercase tracking-wide">Total Clients</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($clients->total()) }}</p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-rose-500 to-rose-300 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-rose-100 text-xs font-medium uppercase tracking-wide">Total Owed</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($totals['totalOwed'], 3) }} <span class="text-base font-semibold">KWD</span></p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-300 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-emerald-100 text-xs font-medium uppercase tracking-wide">Total Paid</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($totals['totalPaid'], 3) }} <span class="text-base font-semibold">KWD</span></p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-amber-500 to-amber-300 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-amber-100 text-xs font-medium uppercase tracking-wide">Outstanding Balance</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($totals['totalBalance'], 3) }} <span class="text-base font-semibold">KWD</span></p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 mb-6">
            <form method="POST" action="{{ route('reports.client') }}" id="filterForm">
                @csrf
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Date Range</label>
                        <input type="text" id="date-range" 
                            class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 cursor-pointer" 
                            placeholder="Select date range" autocomplete="off" readonly />
                        <input type="hidden" name="date_from" id="date_from" value="{{ $dateFrom ?? '' }}">
                        <input type="hidden" name="date_to" id="date_to" value="{{ $dateTo ?? '' }}">
                    </div>
                    <x-multi-picker 
                        label="Select Clients"
                        name="client_ids"
                        :items="$clientsList"
                        :preselected="collect(request('client_ids', []))->map(fn($v) => (int)$v)->all()"
                        allLabel="All Clients"
                        placeholder="Search clients..."
                        class="flex-1 min-w-[200px]"
                    />

                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            Filter
                        </button>
                        <a href="{{ route('reports.client') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </a>
                        <button type="submit" formaction="{{ route('reports.client.pdf') }}" formmethod="POST" formtarget="_blank" 
                            class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-purple-600 hover:bg-purple-700 text-white transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            PDF
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm table-fixed">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr class="px-4 py-3 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                            <th class="w-[26%] text-left">Client</th>
                            <th class="text-center">Tasks</th>
                            <th class="text-center">Invoices</th>
                            <th class="text-right">Total Owed</th>
                            <th class="text-right">Total Paid</th>
                            <th class="text-right">Balance</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($clients as $item)
                        <tbody x-data="{ open: false }" class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700/50 cursor-pointer transition-colors" @click="open = !open">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            {{ strtoupper(substr($item['client']->full_name ?: $item['client']->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $item['client']->full_name ?: $item['client']->name }}</div>
                                            @if($item['client']->phone)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $item['client']->phone ? ($item['client']->country_code ?? '+965') . $item['client']->phone : 'N/A' }}
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                        {{ $item['total_tasks'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ $item['paid_invoices_count'] }}/{{ $item['invoices_count'] }} paid
                                        </span>
                                        @if($item['invoices_count'] > 0)
                                        <div class="w-16 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-500 rounded-full" style="width: {{ ($item['paid_invoices_count'] / $item['invoices_count']) * 100 }}%"></div>
                                        </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-rose-600 dark:text-rose-400">
                                    {{ number_format($item['total_owed'], 3) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ number_format($item['total_paid'], 3) }}
                                </td>
                                <!-- <td class="px-4 py-3 text-right font-semibold {{ $item['balance'] > 0 ? 'text-amber-600 dark:text-amber-400' : ($item['balance'] < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400') }}">
                                    {{ number_format($item['balance'], 3) }}
                                </td> -->
                                <td class="px-4 py-3 text-right font-semibold {{ $item['balance'] > 0 ? 'text-rose-600 dark:text-rose-400' : ($item['balance'] < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400') }}">
                                    {{ number_format($item['balance'], 3) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md border border-gray-300 dark:border-gray-600 
                                        bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm transition"
                                        @click.stop="open = !open">
                                        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                        <span x-text="open ? 'Hide' : 'Details'"></span>
                                    </button>
                                </td>
                            </tr>

                            <tr x-show="open" x-collapse x-cloak>
                                <td colspan="7" class="p-0 bg-gray-50 dark:bg-gray-900/30">
                                    <div x-show="open" x-transition:enter="transition-all ease-out duration-300" x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100" x-transition:leave="transition-all ease-in duration-200"
                                        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="p-4">
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
                                            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                                <div class="text-[11px] uppercase text-gray-500 dark:text-gray-400 tracking-wide">Invoiced Tasks</div>
                                                <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $item['invoiced_tasks_count'] }}</div>
                                            </div>
                                            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                                <div class="text-[11px] uppercase text-gray-500 dark:text-gray-400 tracking-wide">Uninvoiced Tasks</div>
                                                <div class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ $item['uninvoiced_tasks_count'] }}</div>
                                            </div>
                                            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                                <div class="text-[11px] uppercase text-gray-500 dark:text-gray-400 tracking-wide">Refunded Tasks</div>
                                                <div class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $item['refunded_tasks_count'] }}</div>
                                            </div>
                                            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                                <div class="text-[11px] uppercase text-gray-500 dark:text-gray-400 tracking-wide">Due to Client</div>
                                                <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($item['refund_credit'], 3) }}</div>
                                                <div class="text-[10px] text-gray-400">Money we owe the client from cancellations</div>
                                            </div>
                                            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                                <div class="text-[11px] uppercase text-gray-500 dark:text-gray-400 tracking-wide">Due from Client</div>
                                                <div class="text-lg font-bold text-rose-600 dark:text-rose-400">{{ number_format($item['refund_owed'], 3) }}</div>
                                                <div class="text-[10px] text-gray-400">Money client owes us (refund charges)</div>
                                            </div>
                                            <a href="{{ route('clients.credits', $item['client']->id) }}" target="_blank"
                                                class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-blue-300 transition-colors cursor-pointer">
                                                <div class="text-[11px] uppercase text-gray-500 dark:text-gray-400 tracking-wide">Client Credit</div>
                                                <div class="text-lg font-bold text-blue-600 dark:text-blue-400 flex items-center gap-1">
                                                    {{ number_format($item['client_credit'], 3) }}
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </div>
                                                <div class="text-[10px] text-gray-400">Available credit balance</div>
                                            </a>
                                        </div>

                                        @if($item['tasks']->isNotEmpty())
                                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                                                <h4 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                                    </svg>
                                                    Tasks ({{ $item['tasks']->count() }})
                                                </h4>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full text-xs table-fixed">
                                                    <thead class="bg-gray-100 dark:bg-gray-800">
                                                        <tr class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400 text-center uppercase">
                                                            <th class="text-left">Reference</th>
                                                            <th>Supplier</th>
                                                            <th>Type</th>
                                                            <th>Date</th>
                                                            <th>Status</th>
                                                            <!-- <th>Total</th> -->
                                                            <th>Debit</th>
                                                            <th>Credit</th>
                                                            <th>Balance</th>
                                                            <th>Billing</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                        @foreach($item['task_rows'] as $row)
                                                        @php $task = $row['task']; @endphp
                                                        <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-center">
                                                            <td class="px-3 py-2.5 text-left">
                                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $task->reference }}</span>
                                                                @if($task->passenger_name)
                                                                    <div class="text-[10px] text-gray-500 mt-0.5">{{ $task->passenger_name }}</div>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400">
                                                                {{ $task->supplier->name ?? '—' }}
                                                            </td>
                                                            <td class="px-3 py-2.5">
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                                                    {{ ucfirst($task->type ?? '—') }}
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400">
                                                                {{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('d-m-Y') : '—' }}
                                                            </td>
                                                            <td class="px-3 py-2.5">
                                                                @php
                                                                    $statusColors = [
                                                                        'issued' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                                                        'reissued' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                                                        'void' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                                                        'refund' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                                                        'confirmed' => 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
                                                                    ];
                                                                    $statusColor = $statusColors[strtolower($task->status)] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                                                                @endphp
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium {{ $statusColor }}">
                                                                    {{ ucfirst($task->status ?? '—') }}
                                                                </span>
                                                            </td>
                                                            <!-- <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                                                @if($task->refundDetail)
                                                                    {{ number_format($task->refundDetail->total_refund_to_client ?? 0, 3) }}
                                                                @else
                                                                    {{ number_format($task->invoiceDetail->task_price ?? $task->total ?? 0, 3) }}
                                                                @endif
                                                            </td> -->
                                                            <td class="px-3 py-2.5 font-semibold {{ $row['debit'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                                                {{ $row['debit'] > 0 ? number_format($row['debit'], 3) : '—' }}
                                                            </td>
                                                            <td class="px-3 py-2.5 font-semibold {{ $row['credit'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                                {{ $row['credit'] > 0 ? number_format($row['credit'], 3) : '—' }}
                                                            </td>
                                                            <td class="px-3 py-2.5 font-semibold {{ $row['running_balance'] > 0 ? 'text-rose-600 dark:text-rose-400' : ($row['running_balance'] < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400') }}">
                                                                {{ number_format($row['running_balance'], 3) }}
                                                            </td>
                                                            <td class="px-3 py-2.5">
                                                                @if($task->refundDetail && $task->refundDetail->refund)
                                                                    @php $refund = $task->refundDetail->refund; @endphp
                                                                    <a href="{{ route('refunds.show',
                                                                        ['companyId' => $refund->company_id, 'refundNumber' => $refund->refund_number]) }}" 
                                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded font-medium 
                                                                        {{ $refund->status === 'completed' ? 'bg-purple-100 text-purple-700 hover:bg-purple-200' : 
                                                                        'bg-amber-100 text-amber-700 hover:bg-amber-200' }} transition-colors"
                                                                        @click.stop data-tooltip-left="{{ $refund->refund_number }}" target="_blank">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                                        </svg>
                                                                        {{ ucfirst($refund->status) }}
                                                                    </a>
                                                                @elseif(strtolower($task->status) === 'refund')
                                                                    <span class="inline-flex items-center px-2 py-1 rounded font-medium bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                                        Not Refunded
                                                                    </span>
                                                                @elseif($task->invoiceDetail && $task->invoiceDetail->invoice)
                                                                    @php 
                                                                        $invoice = $task->invoiceDetail->invoice;
                                                                        $invoiceStatusColors = [
                                                                            'unpaid' => 'bg-rose-100 text-rose-700 hover:bg-rose-200',
                                                                            'partial' => 'bg-amber-100 text-amber-700 hover:bg-amber-200',
                                                                            'partial refund' => 'bg-amber-100 text-amber-700 hover:bg-amber-200',
                                                                            'paid' => 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200',
                                                                            'paid by refund' => 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200',
                                                                            'refunded' => 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200',
                                                                        ];
                                                                        $statusColor = $invoiceStatusColors[strtolower($invoice->status)] ?? 'bg-gray-100 text-gray-700 hover:bg-gray-200';
                                                                    @endphp
                                                                    <a href="{{ route('invoice.show', ['companyId' => $invoice->agent->branch->company_id ?? 1, 'invoiceNumber' => $invoice->invoice_number]) }}" 
                                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded font-medium {{ $statusColor }} transition-colors"
                                                                        @click.stop data-tooltip-left="{{ $invoice->invoice_number }}" target="_blank">
                                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                        </svg>
                                                                        {{ ucfirst($invoice->status) }}
                                                                    </a>
                                                                @else
                                                                    <span class="inline-flex items-center px-2 py-1 rounded font-medium bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                                        Not Invoiced
                                                                    </span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        @else
                                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 text-center">
                                            <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <p class="text-gray-500 dark:text-gray-400">No tasks found for this client</p>
                                        </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-500">
                                    <svg class="w-12 h-12 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p class="text-lg font-medium">No clients found</p>
                                    <p class="text-sm">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <x-pagination :data="$clients" />
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fromDate = document.getElementById('date_from').value;
            const toDate = document.getElementById('date_to').value;
            
            flatpickr("#date-range", {
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: (fromDate && toDate) ? [fromDate, toDate] : null,
                showMonths: 1,
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        document.getElementById('date_from').value = instance.formatDate(selectedDates[0], "Y-m-d");
                        document.getElementById('date_to').value = instance.formatDate(selectedDates[1], "Y-m-d");
                    }
                },
                onReady: function(selectedDates, dateStr, instance) {
                    if (fromDate && toDate) {
                        instance.element.value = fromDate + ' to ' + toDate;
                    }
                }
            });
        });
    </script>
</x-app-layout>