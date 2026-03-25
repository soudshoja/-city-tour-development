@php
    $typeId = optional($user->agent)->type_id;
    $showProfit = true; // All types show profit
    $showCommission = in_array($typeId, [2, 3, 4]); // Types 2, 3, 4 show commission
    $viewType = request('view_type', 'invoice');

    $title = match($typeId) {
    1 => 'Profit & Loss',
    2,3,4 => 'Commission, Profit & Loss',
    default => 'Commission, Profit & Loss'
    };
@endphp
<section>
    <header class="flex flex-col md:flex-row justify-between mb-4 gap-4 overflow-y-auto">
        <div class="flex-1">
            <div class="flex items-center justify-between mb-2">
                <div class="">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $title }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        @if($viewType === 'task')
                            Review your earned {{ strtolower($title) }} along with details of the associated tasks
                        @else
                            Review your earned {{ strtolower($title) }} grouped by invoices
                        @endif
                    </p>
                </div>

                <!-- View Type Toggle -->
                <div class="flex bg-gray-100 rounded-lg p-1">
                    <a href="{{ route('profile.edit', array_merge(request()->only(['month']), ['tab' => 'Commission', 'view_type' => 'task'])) }}"
                        class="px-3 py-1 rounded text-sm transition-colors {{ $viewType === 'task' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-blue-600' }}">
                        By Task
                    </a>
                    <a href="{{ route('profile.edit', array_merge(request()->only(['month']), ['tab' => 'Commission', 'view_type' => 'invoice'])) }}"
                        class="px-3 py-1 rounded text-sm transition-colors {{ $viewType === 'invoice' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-blue-600' }}">
                        By Invoice
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="flex justify-end mt-10 mb-5">
        <div class="flex items-center gap-2">
            <form method="GET" id="commissionFilterForm" action="{{ route('profile.edit') }}"
                class="flex items-center gap-1 bg-white/60 z-20 dark:bg-gray-800/40 px-4 py-2 rounded-full shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">

                <input type="hidden" name="tab" value="Commission">
                <input type="hidden" name="view_type" value="{{ $viewType }}">

                <div x-data="{ 
                        open: false, 
                        selected: {{ request('filter_month', request('month') ? (int)\Carbon\Carbon::parse(request('month'))->format('m') : now()->month) }},
                        months: ['January','February','March','April','May','June','July','August','September','October','November','December']
                    }" class="relative">

                    <input type="hidden" name="filter_month" x-model="selected">

                    <button type="button" @click="open = !open" @click.outside="open = false"
                        class="text-sm text-gray-700 dark:text-gray-100 cursor-pointer">
                        <span x-text="months[selected - 1]"></span>
                    </button>

                    <div x-show="open" x-cloak
                        class="absolute top-8 left-0 z-9999 bg-white dark:bg-gray-800 rounded-xl shadow-lg ring-1 ring-gray-100 dark:ring-gray-700 py-2 min-w-[140px]">
                        <template x-for="(month, index) in months" :key="index">
                            <button type="button"
                                @click="selected = index + 1; open = false"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                                :class="selected === index + 1 ? 'text-blue-600 font-semibold bg-blue-50 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-200'"
                                x-text="month">
                            </button>
                        </template>
                    </div>
                </div>

                <span class="text-gray-400 text-sm">/</span>

                <div x-data="{ 
                        open: false, 
                        selected: {{ request('filter_year', request('month') ? (int)\Carbon\Carbon::parse(request('month'))->format('Y') : now()->year) }},
                        years: {{ json_encode(range(now()->year, now()->year - 5)) }}
                    }" class="relative">

                    <input type="hidden" name="filter_year" x-model="selected">

                    <button type="button" @click="open = !open" @click.outside="open = false"
                        class="text-sm text-gray-700 dark:text-gray-100 cursor-pointer">
                        <span x-text="selected"></span>
                    </button>

                    <div x-show="open" x-cloak
                        class="absolute top-8 left-0 z-100 bg-white dark:bg-gray-800 rounded-xl shadow-lg ring-1 ring-gray-100 dark:ring-gray-700 py-2 min-w-[90px]">
                        <template x-for="year in years" :key="year">
                            <button type="button"
                                @click="selected = year; open = false"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                                :class="selected === year ? 'text-blue-600 font-semibold bg-blue-50 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-200'"
                                x-text="year">
                            </button>
                        </template>
                    </div>
                </div>
            </form>

            <!-- Filter Icon Button -->
            <button type="submit" form="commissionFilterForm"
                class="w-8 h-8 inline-flex items-center justify-center rounded-full bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm"
                title="Filter">
                <i class="fas fa-filter text-sm"></i>
            </button>

            <!-- Reset Icon Button -->
            @php
                $isFiltered = request('filter_month') && (
                    (int)request('filter_month') !== now()->month || 
                    (int)request('filter_year', now()->year) !== now()->year
                );
            @endphp

            @if($isFiltered)
            <a href="{{ route('profile.edit', ['tab' => 'Commission', 'view_type' => $viewType]) }}"
                class="w-8 h-8 inline-flex items-center justify-center rounded-full bg-gray-600 text-white hover:bg-gray-700 transition shadow-sm"
                title="Reset">
                <i class="fas fa-rotate-left text-sm"></i>
            </a>
            @endif
        </div>
    </div>

    @if($commissions && $commissions->count() > 0)
    <!-- Summary Totals -->
    <div class="mb-10">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">
            Summary for {{ \Carbon\Carbon::createFromDate(
                request('filter_year', now()->year),
                request('filter_month', now()->month),
                1
            )->format('F Y') }}
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-{{ $showCommission ? '3' : '1' }} gap-4">
            @if($showProfit)
            <div class="bg-blue-100 dark:bg-gray-800 rounded-lg p-4 border border-blue-200 dark:border-gray-700 shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-300 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-800 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total Profit
                        </p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $totalProfit }} KWD</p>
                    </div>
                </div>
            </div>
             <div class="bg-red-100 dark:bg-gray-800 rounded-lg p-4 border border-red-200 dark:border-gray-700 shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-300 dark:bg-red-900 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Loss</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $totalLoss }} KWD</p>
                    </div>
                </div>
            </div>
            @endif
            
            @if($showCommission)
            <div class="bg-green-100 dark:bg-gray-800 rounded-lg p-4 border border-green-200 dark:border-gray-700 shadow-md">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-300 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Commission</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $totalCommission }} KWD</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    
    <div class="overflow-x-auto" x-data="{ openRow: null }">
        <table class="min-w-full text-sm border border-gray-300 dark:border-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 uppercase tracking-wide">
                <tr>
                    @if($viewType === 'task')
                        <th class="py-3 px-4 border-b text-center">Task Reference</th>
                        <th class="py-3 px-4 border-b text-center">Passenger</th>
                        @if($showProfit)
                        <th class="py-3 px-4 border-b text-center">Total Profit (KWD)</th>                        
                        <th class="py-3 px-4 border-b text-center">Total Loss (KWD)</th>
                        @endif
                        @if($showCommission)
                        <th class="py-3 px-4 border-b text-center">Total Commission (KWD)</th>
                        @endif
                        <th class="py-3 px-4 border-b text-center">Description</th>
                        <th class="py-3 px-4 border-b text-center">Date</th>
                    @else
                        <th class="py-3 px-4 border-b text-center">Invoice Number</th>
                        <th class="py-3 px-4 border-b text-center">Task Count</th>
                        @if($showProfit)
                        <th class="py-3 px-4 border-b text-center">Total Profit (KWD)</th>
                        <th class="py-3 px-4 border-b text-center">Total Loss    (KWD)</th>
                        @endif
                        @if($showCommission)
                        <th class="py-3 px-4 border-b text-center">Total Commission (KWD)</th>
                        @endif
                        <th class="py-3 px-4 border-b text-center">Invoice Date</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($commissions as $index => $item)
                @if($viewType === 'task')
                    {{-- Task-based view --}}
                    <tr class="cursor-pointer text-center"
                        :class="openRow === {{ $index }} ? 'bg-blue-50 hover:bg-gray-50 dark:bg-blue-900 hover:dark:bg-blue-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200'" 
                        @click="openRow === {{ $index }} ? openRow = null : openRow = {{ $index }}">
                        <td class="py-3 px-4 border-b">{{ $item['task_reference'] }}</td>
                        <td class="py-3 px-4 border-b">{{ $item['passenger_name'] }}</td>
                        @if($showProfit)
                        <td class="py-3 px-4 border-b text-blue-700 font-semibold">
                            {{ number_format($item['task_profit'], 3) }}
                        </td>
                        <td class="py-3 px-4 border-b text-red-700 font-semibold">
                            {{ number_format($item['task_loss'], 3) }}
                        </td>
                        @endif
                        @if($showCommission)
                        <td class="py-3 px-4 border-b text-green-700 font-semibold">
                            {{ number_format($item['net_commission'], 3) }}
                        </td>
                        @endif
                        @php
                        if ($showCommission && ! $showProfit) {
                        $description = "Commission on Task {$item['task_reference']}";
                        } elseif ($showProfit && ! $showCommission) {
                        $description = "Profit on Task {$item['task_reference']}";
                        } else {
                        $description = "Commission & Profit for Task {$item['task_reference']}";
                        }
                        @endphp
                        <td class="py-3 px-4 border-b">{{ $description }}</td>
                        <td class="py-3 px-4 border-b">
                            {{ \Carbon\Carbon::parse($item['transaction_date'])->format('d-m-Y') }}
                        </td>
                    </tr>
                    <tr x-show="openRow === {{ $index }}" x-cloak>
                        <td colspan="{{ $showCommission ? '6' : '5' }}" class="bg-sky-50 dark:bg-gray-900 px-6 py-4 border-b dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 rounded-b-lg shadow-inner">
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <p class="text-base font-semibold text-gray-800">Task: {{ $item['task_reference'] }}</p>
                                    <a href="{{ route('invoice.details', [ 'companyId' => $item['invoice']['company_id'], 'invoiceNumber' => $item['invoice']['number']]) }}" class="text-blue-500 hover:underline" target="_blank">
                                        View Invoice {{ $item['invoice']['number'] }}
                                    </a>
                                </div>
                                <hr class="my-2">
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-1">
                                    @if($item['task_details'])
                                    <div><strong>Client:</strong> {{ $item['task_details']['client_name'] ?? 'N/A' }}</div>
                                    <div><strong>Supplier Pay Date:</strong> 
                                        {{ $item['task_details']['supplier_pay_date'] ? \Carbon\Carbon::parse($item['task_details']['supplier_pay_date'])->format('d-m-Y') : 'N/A' }}
                                    </div>
                                    @endif
                                    <div><strong>Passenger:</strong> {{ $item['passenger_name'] }}</div>
                                    <div><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($item['invoice']['date'])->format('d-m-Y') }}</div>
                                    <div><strong>Payment Type:</strong> {{ $item['invoice']['payment_type'] }}</div>
                                    <div><strong>Invoice Total Profit:</strong> {{ number_format($item['invoice']['total_profit'], 3) }} KWD</div>
                                </div>

                                @if(isset($item['task_details']['flight_details']))
                                <div class="mt-4 p-4 rounded-lg bg-sky-100 dark:bg-gray-800 shadow-inner">
                                    <h4 class="font-semibold mb-2">Flight Details</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-1 text-sm">
                                        <div><strong>Flight No:</strong> {{ $item['task_details']['flight_details']['flight_number'] ?? 'N/A' }}</div>
                                        <div><strong>From:</strong> {{ $item['task_details']['flight_details']['airport_from'] ?? 'N/A' }}</div>
                                        <div><strong>To:</strong> {{ $item['task_details']['flight_details']['airport_to'] ?? 'N/A' }}</div>
                                        <div><strong>Departure:</strong> 
                                            {{ isset($item['task_details']['flight_details']['departure_time']) ? \Carbon\Carbon::parse($item['task_details']['flight_details']['departure_time'])->format('d-m-Y H:i') : 'N/A' }}
                                        </div>
                                        <div><strong>Arrival:</strong> 
                                            {{ isset($item['task_details']['flight_details']['arrival_time']) ? \Carbon\Carbon::parse($item['task_details']['flight_details']['arrival_time'])->format('d-m-Y H:i') : 'N/A' }}
                                        </div>
                                    </div>
                                </div>
                                @endif

                                @if(isset($item['task_details']['hotel_details']))
                                <div class="mt-4 p-4 rounded-lg bg-sky-100 dark:bg-gray-800 shadow-inner">
                                    <h4 class="font-semibold mb-2">Hotel Details</h4>
                                    <div class="grid grid-cols-2 gap-1 text-sm">
                                        <div><strong>Hotel Name:</strong> {{ $item['task_details']['hotel_details']['hotel']['name'] ?? 'N/A' }}</div>
                                        <div><strong>Check-in:</strong> 
                                            {{ isset($item['task_details']['hotel_details']['check_in']) ? \Carbon\Carbon::parse($item['task_details']['hotel_details']['check_in'])->format('d-m-Y') : 'N/A' }}
                                        </div>
                                        <div><strong>Check-out:</strong> 
                                            {{ isset($item['task_details']['hotel_details']['check_out']) ? \Carbon\Carbon::parse($item['task_details']['hotel_details']['check_out'])->format('d-m-Y') : 'N/A' }}
                                        </div>
                                        <div><strong>Nights:</strong> {{ $item['task_details']['hotel_details']['nights'] ?? 'N/A' }}</div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @else
                    {{-- Invoice-based view --}}
                    <tr class="cursor-pointer text-center"
                        :class="openRow === {{ $index }} ? 'bg-blue-50 hover:bg-gray-50 dark:bg-blue-900 hover:dark:bg-blue-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200'" 
                        @click="openRow === {{ $index }} ? openRow = null : openRow = {{ $index }}">
                        <td class="py-3 px-4 border-b">
                            <a href="{{ route('invoice.details', ['companyId' => $item['company_id'], 'invoiceNumber' => $item['invoice_number']]) }}" class="text-blue-500 hover:underline" target="_blank">
                                {{ $item['invoice_number'] }}
                            </a>
                        </td>
                        <td class="py-3 px-4 border-b">{{ $item['task_count'] }}</td>
                        @if($showProfit)
                        <td class="py-3 px-4 border-b text-blue-700 font-semibold">
                            {{ number_format($item['total_profit'], 3) }}
                        </td>
                         <td class="py-3 px-4 border-b text-blue-700 font-semibold">
                            {{ number_format($item['total_loss'], 3) }}
                        </td>
                        @endif
                        @if($showCommission)
                        <td class="py-3 px-4 border-b text-green-700 font-semibold">
                            {{ number_format($item['total_commission'], 3) }}
                        </td>
                        @endif
                        <td class="py-3 px-4 border-b">
                            {{ \Carbon\Carbon::parse($item['invoice_date'])->format('d-m-Y') }}
                        </td>
                    </tr>
                    <tr x-show="openRow === {{ $index }}" x-cloak>
                        <td colspan="{{ $showCommission ? '5' : '4' }}" class="bg-sky-50 dark:bg-gray-900 px-6 py-4 border-b dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 rounded-b-lg shadow-inner">
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <p class="text-base font-semibold text-gray-800">Invoice {{ $item['invoice_number'] }}</p>
                                    <a href="{{ route('invoice.edit', ['companyId' => $item['company_id'], 'invoiceNumber' => $item['invoice_number']]) }}" class="text-blue-500 hover:underline" target="_blank">Edit Invoice</a>
                                </div>
                                <hr class="my-2">
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-1 mb-4">
                                    <div><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($item['invoice_date'])->format('d-m-Y') }}</div>
                                    <div><strong>Total Tasks:</strong> {{ $item['task_count'] }}</div>
                                    <div><strong>Total Profit:</strong> {{ number_format($item['total_profit'], 3) }} KWD</div>
                                    @if($showCommission)
                                    <div><strong>Total Commission:</strong> {{ number_format($item['total_commission'], 3) }} KWD</div>
                                    @endif
                                </div>

                                <div class="mt-4">
                                    <h4 class="font-semibold mb-2">Tasks in this Invoice</h4>
                                    <div class="bg-white dark:bg-gray-800 rounded border">
                                        @foreach($item['tasks'] as $task)
                                        <div class="p-3 border-b last:border-b-0 text-sm">
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                <div><strong>Task:</strong> {{ $task['task_reference'] }}</div>
                                                <div><strong>Passenger:</strong> {{ $task['passenger_name'] }}</div>
                                                <div><strong>Price:</strong> {{ number_format($task['task_price'], 3) }} KWD</div>
                                                <div><strong>Profit:</strong> {{ number_format($task['markup_price'], 3) }} KWD</div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endif
                @endforeach
            </tbody>
        </table>
        <div class="mt-6 px-6">
            {{ $commissions->appends(['tab' => 'Commission', 'view_type' => $viewType, 'month' => request('month')])->links() }}
        </div>
    </div>
    @else
    <div class="text-center py-8">
        <p class="text-gray-500 dark:text-gray-400">No commission data found for the selected month.</p>
    </div>
    @endif
</section>
