<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <h2 class="text-2xl md:text-3xl font-bold">Invoices List</h2>
            <div data-tooltip="Number of invoices"
                class="relative w-10 h-10 md:w-12 md:h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-lg md:text-xl font-bold text-white">{{ $invoices->total() }}</span>
            </div>
        </div>
        <div class="flex items-center gap-3 md:gap-5">
            <div data-tooltip-left="Reload"
                class="refresh-icon relative w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>

            @can('create', App\Models\Invoice::class)
            <a href="{{ route('invoices.create') }}">
                <div data-tooltip-left="Create new invoice"
                    class="relative w-10 h-10 md:w-12 md:h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
            @endcan
        </div>
    </div>

    <div class="panel rounded-lg">
        <x-search
            :action="route('invoices.index')"
            searchParam="search"
            placeholder="Quick search for invoices" />

        <div class="my-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
            <div class="flex items-center gap-3 rounded-lg p-4 shadow-sm bg-blue-50 border border-blue-200">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-blue-600">Total Net</div>
                    <div class="text-base md:text-lg font-semibold text-blue-700">{{ number_format($totalNet, 3) }} KWD</div>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg p-4 shadow-sm bg-emerald-50 border border-emerald-200">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6M9 11h6m-8 4h10M5 21l1.5-1.5L8 21l1.5-1.5L11 21l1.5-1.5L14 21l1.5-1.5L17 21l1.5-1.5L20 21V3a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v18z" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-emerald-600">Total Sales</div>
                    <div class="text-base md:text-lg font-semibold text-emerald-700">{{ number_format($totalSales, 3) }} KWD</div>
                </div>
            </div>
            <div class="col-span-1 md:col-span-2">
                <div class="p-2 md:p-4 w-full">
                    <form method="GET" action="{{ route('invoices.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2 flex-wrap" id="invoice-filter-form">
                        <div class="flex flex-col justify-end">
                            <label class="text-sm font-semibold text-gray-600 mb-1">Filter By</label>
                            <select name="date_field" class="border rounded px-2 py-1.5 text-sm w-full sm:min-w-[120px] sm:w-auto">
                                <option value="created_at" {{ request('date_field') == 'created_at' ? 'selected' : '' }}>Created Date</option>
                                <option value="invoice_date" {{ request('date_field') == 'invoice_date' ? 'selected' : '' }}>Invoice Date</option>
                            </select>
                        </div>
                        <div class="flex flex-col justify-end">
                            <label class="text-sm font-semibold text-gray-600 mb-1">Date Range</label>
                            <input type="text" id="date-range" class="border rounded px-2 py-1.5 text-sm w-full sm:min-w-[180px] sm:w-auto" placeholder="Select date range" autocomplete="off" />
                            <input type="hidden" name="from_date" id="from_date" value="{{ request('from_date') }}">
                            <input type="hidden" name="to_date" id="to_date" value="{{ request('to_date') }}">
                        </div>
                        <div class="flex flex-row items-end gap-1 pt-3 sm:pt-5">
                            <a href="{{ route('invoices.index') }}" class="px-3 py-1.5 rounded bg-gray-100 text-gray-700 text-sm hover:bg-gray-200 border border-gray-300">Clear</a>
                            <button type="submit" class="px-3 py-1.5 rounded bg-blue-600 text-white text-sm hover:bg-blue-700 border border-blue-700">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="hidden xl:grid xl:grid-cols-12 gap-4 px-4 py-3 bg-gray-100 rounded-t-lg border-b border-gray-200 text-base font-semibold text-gray-600">
            <div class="col-span-3">Invoice Details</div>
            <div class="col-span-2">Customer & Agent</div>
            <div class="col-span-2">Payment Gateways</div>
            <div class="col-span-3">Amount Details</div>
            <div class="col-span-2 text-center">Actions</div>
        </div>

        <div class="space-y-0">
            @forelse ($invoices as $index => $invoice)
            @php
                $invoiceDetail = ($invoice->invoiceDetails ?? collect())->first();
                $gateways = $invoice->invoicePartials->groupBy('payment_gateway');
                $taskTypes = $invoice->invoiceDetails->pluck('task.type')->unique()->filter();
                
                $totalAmount = $invoice->client_pay > 0 ? $invoice->client_pay : $invoice->amount;
                $paidAmount = $invoice->invoicePartials->where('status', 'paid')->sum(function($p) {
                    return $p->amount + $p->service_charge + ($p->invoice_charge ?? 0);
                });
                $paidPercentage = $totalAmount > 0 ? min(100, ($paidAmount / $totalAmount) * 100) : 0;
                
                $tasksPayload = ($invoice->invoiceDetails ?? collect())->map(function ($detail) use ($invoice) {
                    $task = $detail->task;
                    return [
                        'id' => $task?->id,
                        'reference' => $task?->reference ? 'Task #'.$task->reference : '-',
                        'type' => $task?->type ? ucfirst($task->type) : '-',
                        'client' => $task?->client?->full_name ?? '-',
                        'supplier' => $task?->supplier?->name ?? '-',
                        'amount' => $detail->task_price ?? 0,
                        'currency' => $invoice->currency ?? '-',
                    ];
                })->values()->toArray();
            @endphp
            <div class="border-b border-gray-200 hover:bg-blue-50/30 transition-colors {{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50/50' }}"
                data-tasks='@json($tasksPayload)'>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-3 md:gap-4 p-3 md:p-4">
                    <div class="md:col-span-1 xl:col-span-3 space-y-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if (auth()->check() && in_array(auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::COMPANY, \App\Models\Role::ACCOUNTANT]))
                                <a href="{{ route('invoice.details', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                    class="text-sm md:text-base font-bold text-blue-600 hover:underline" target="_blank">
                                    {{ $invoice->invoice_number }}
                                </a>
                            @else
                                <span class="text-sm md:text-base font-bold text-gray-800">{{ $invoice->invoice_number }}</span>
                            @endif
                            
                            <button type="button" onclick="copyToClipboard('{{ $invoice->invoice_number }}')" 
                                class="p-1 text-gray-400 hover:text-gray-600" data-tooltip="Copy">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>

                            @if (in_array($invoice->status, ['paid', 'refunded']))
                                <span class="px-2 py-0.5 text-xs md:text-sm rounded-full bg-green-100 text-green-700 font-medium">{{ ucfirst($invoice->status) }}</span>
                            @elseif ($invoice->status === 'paid by refund')
                                <span class="px-2 py-0.5 text-xs md:text-sm rounded-full bg-green-100 text-green-700 font-medium">Settled</span>
                            @elseif ($invoice->status === 'partial')
                                <span class="px-2 py-0.5 text-xs md:text-sm rounded-full bg-yellow-100 text-yellow-700 font-medium">Partial</span>
                            @else
                                <span class="px-2 py-0.5 text-xs md:text-sm rounded-full bg-red-100 text-red-700 font-medium">{{ ucfirst($invoice->status) }}</span>
                            @endif

                            @if($invoice->is_locked)
                                <span data-tooltip="Locked by {{ $invoice->lockedByUser?->name ?? 'Unknown' }} on {{ $invoice->locked_at?->format('d M Y H:i') }}"
                                    class="px-2 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600 font-medium flex items-center gap-1 cursor-help">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                                    </svg>
                                    Locked
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            @foreach($taskTypes as $type)
                                @if(strtolower($type) === 'flight')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded bg-sky-100 text-sky-700 text-xs md:text-sm" data-tooltip="Flight">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                                        </svg>
                                    </span>
                                @elseif(strtolower($type) === 'hotel')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded bg-amber-100 text-amber-700 text-xs md:text-sm" data-tooltip="Hotel">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V5H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z"/>
                                        </svg>
                                    </span>
                                @elseif(strtolower($type) === 'visa')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded bg-purple-100 text-purple-700 text-xs md:text-sm" data-tooltip="Visa">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
                                        </svg>
                                    </span>
                                @elseif(strtolower($type) === 'insurance')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded bg-teal-100 text-teal-700 text-xs md:text-sm" data-tooltip="Insurance">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                                        </svg>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded bg-gray-100 text-gray-700 text-xs md:text-sm" data-tooltip="{{ ucfirst($type) }}">
                                        {{ ucfirst($type) }}
                                    </span>
                                @endif
                            @endforeach
                            <span class="text-xs md:text-sm text-gray-400">({{ $invoice->invoiceDetails->count() }} {{ Str::plural('task', $invoice->invoiceDetails->count()) }})</span>
                        </div>

                        <div class="text-xs md:text-sm text-gray-500 space-y-1">
                            <div><span class="font-medium">Payment:</span> {{ $invoice->payment_type ? ucwords($invoice->payment_type) : 'N/A' }}</div>
                            <div><span class="font-medium">Created:</span> {{ $invoice->created_at->format('d M Y H:i') }}</div>
                            <div>
                                <span class="font-medium">Invoice Date:</span>
                                @if ($invoice->status === 'paid' && !$invoice->is_locked)
                                    <button type="button" class="text-blue-600 hover:underline"
                                        data-number="{{ $invoice->invoice_number }}"
                                        data-date="{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d') }}"
                                        onclick="openEditModal('date', this)">
                                        {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}
                                    </button>
                                @else
                                    {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-1 xl:col-span-2 space-y-3">
                        <div>
                            <div class="flex items-center gap-1 text-gray-500 mb-1 text-xs md:text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span class="font-medium">Client</span>
                            </div>
                            @if (auth()->check() && in_array(auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::COMPANY, \App\Models\Role::AGENT]))
                                <a href="{{ route('clients.show', $invoice->client->id) }}" class="text-sm md:text-base text-blue-600 hover:underline font-medium" target="_blank">
                                    {{ $invoice->client->full_name }}
                                </a>
                            @else
                                <span class="text-sm md:text-base font-medium text-gray-800">{{ $invoice->client->full_name }}</span>
                            @endif
                        </div>
                        <div>
                            <div class="flex items-center gap-1 text-gray-500 mb-1 text-xs md:text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                                <span class="font-medium">Agent</span>
                            </div>
                            @if (auth()->check() && in_array(auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::COMPANY, \App\Models\Role::AGENT]))
                                <a href="{{ route('agents.show', $invoice->agent->id) }}" class="text-sm md:text-base text-blue-600 hover:underline font-medium" target="_blank">
                                    {{ $invoice->agent->name }}
                                </a>
                            @else
                                <span class="text-sm md:text-base font-medium text-gray-800">{{ $invoice->agent->name }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="md:col-span-1 xl:col-span-2">
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($gateways as $gatewayName => $partials)
                                @php
                                    $gatewayColors = [
                                        'Cash' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'Credit' => 'bg-purple-50 text-purple-700 border-purple-200',
                                        'Deema' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'Tabby Como' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                        'MyFatoorah' => 'bg-orange-50 text-orange-700 border-orange-200',
                                        'Tap' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                                    ];
                                    $colorClass = $gatewayColors[$gatewayName] ?? 'bg-gray-50 text-gray-700 border-gray-200';
                                    $gatewayTotal = $partials->sum(fn($p) => $p->amount + $p->service_charge + ($p->invoice_charge ?? 0));
                                    $gatewayPaid = $partials->where('status', 'paid')->count();
                                    $gatewayUnpaid = $partials->where('status', 'unpaid')->count();
                                @endphp
                                <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded border {{ $colorClass }} text-xs md:text-sm">
                                    <span class="font-medium">{{ $gatewayName }}</span>
                                    @if($gatewayPaid > 0 && $gatewayUnpaid === 0)
                                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                    @elseif($gatewayUnpaid > 0 && $gatewayPaid === 0)
                                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                                    @endif
                                    <span class="font-semibold">{{ number_format($gatewayTotal, 3) }}</span>
                                </div>
                            @empty
                                <span class="text-xs md:text-sm text-gray-400 italic">No gateway selected</span>
                            @endforelse
                        </div>
                        
                        @if($invoice->status === 'partial' && $paidPercentage > 0)
                            <div class="mt-3 max-w-[160px]">
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>Progress</span>
                                    <span>{{ number_format($paidPercentage, 0) }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $paidPercentage }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="md:col-span-1 xl:col-span-3">
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs md:text-sm">
                            <div class="text-gray-500">Net Amount:</div>
                            <div class="font-medium text-gray-800">{{ number_format($invoice->invoiceDetails->sum('supplier_price'), 3) }} {{ $invoice->currency }}</div>
                            
                            <div class="text-gray-500">Profit:</div>
                            <div class="font-semibold {{ $invoice->invoiceDetails->sum('profit') >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $invoice->invoiceDetails->sum('profit') >= 0 ? '+' : '' }}{{ number_format($invoice->invoiceDetails->sum('profit'), 3) }} {{ $invoice->currency }}
                            </div>
                            
                            <div class="text-gray-500">Invoice Amount:</div>
                            <div class="font-medium text-gray-800">
                                @if ($invoice->status === 'paid' && $invoice->payment_type === 'full' && !$invoice->refund && !$invoice->is_locked)
                                    <button type="button" class="text-blue-600 hover:underline"
                                        data-number="{{ $invoice->invoice_number }}" data-amount="{{ $invoice->amount }}" onclick="openEditModal('amount', this)">
                                        {{ number_format($invoice->amount, 3) }} {{ $invoice->currency }}
                                    </button>
                                @else
                                    {{ number_format($invoice->amount, 3) }} {{ $invoice->currency }}
                                @endif
                            </div>

                            <div class="text-gray-500">Service Charges:</div>
                            <div class="font-medium text-gray-800">{{ number_format($invoice->invoicePartials->sum('service_charge'), 3) }} {{ $invoice->currency }}</div>
                            
                            <div class="col-span-2 border-t border-gray-200 my-1"></div>
                            
                            <div class="text-gray-700 font-semibold">Client Pay:</div>
                            <div class="font-bold text-base md:text-lg text-blue-700">{{ number_format($invoice->client_pay, 3) }} <span class="text-xs md:text-sm font-normal">{{ $invoice->currency }}</span></div>
                        </div>
                    </div>

                    <div class="md:col-span-2 xl:col-span-2 flex items-center justify-start xl:justify-center gap-1 flex-wrap">
                        @if(in_array(auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::ACCOUNTANT]))
                            @if($invoice->is_locked)
                                <form action="{{ route('invoice.unlock', $invoice->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" data-tooltip="Unlock invoice"
                                        class="p-2 rounded-lg bg-yellow-50 text-yellow-600 hover:bg-yellow-100 hover:shadow-sm transition-all"
                                        onclick="return confirm('Are you sure you want to unlock this invoice?')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M7 10V7a5 5 0 0 1 9.33-2.5M5 10h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2z"/>
                                        </svg>
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('invoice.lock', $invoice->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" data-tooltip="Lock invoice"
                                        class="p-2 rounded-lg bg-gray-50 text-gray-400 hover:bg-gray-100 hover:shadow-sm transition-all"
                                        onclick="return confirm('Lock this invoice? It will become read-only for users without lock management permission.')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        @endif

                        <a data-tooltip="View invoice" target="_blank"
                            href="{{ route('invoice.show', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number]) }}"
                            class="p-2 rounded-lg {{ $invoice->payment_type ? 'bg-blue-50 text-blue-600 hover:bg-blue-100 hover:shadow-sm' : 'bg-gray-50 text-gray-400 cursor-not-allowed' }} transition-all"
                            @unless($invoice->payment_type) onclick="return false;" @endunless>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                <path d="M12 4c-4.182 0-7.028 2.5-8.725 4.704C2.425 9.81 2 10.361 2 12c0 1.64.425 2.191 1.275 3.296C4.972 17.5 7.818 20 12 20s7.028-2.5 8.725-4.704C21.575 14.19 22 13.639 22 12c0-1.64-.425-2.191-1.275-3.296C19.028 6.5 16.182 4 12 4Z"/>
                            </svg>
                        </a>

                        @if ($invoice->status === 'paid' && !$invoice->refund)
                            <div x-data="{ viewVoucherModal: false }">
                                <button type="button" data-tooltip="View voucher" @click="viewVoucherModal = true"
                                    class="p-2 rounded-lg bg-purple-50 text-purple-600 hover:bg-purple-100 hover:shadow-sm transition-all">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                </button>

                                <div x-cloak x-show="viewVoucherModal" class="fixed inset-0 z-50 bg-gray-800 bg-opacity-50 flex items-center justify-center overflow-y-auto p-4">
                                    <div @click.away="viewVoucherModal = false" class="bg-white rounded-xl border-2 w-full max-w-4xl max-h-[85vh] overflow-y-auto shadow-xl">
                                        <div class="flex justify-between items-center gap-4 p-4 border-b sticky top-0 bg-white z-10">
                                            <p class="text-lg font-semibold">Voucher - {{ $invoice->invoice_number }}</p>
                                            <button type="button" @click="viewVoucherModal = false" class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                                        </div>
                                        <div class="py-6 px-4 md:px-10 flex flex-col gap-4">
                                            @foreach ($invoice->invoiceDetails as $invoiceDetail)
                                                @if (strtolower($invoiceDetail->task->type ?? '') === 'flight')
                                                <a href="{{ route('tasks.pdf.flight', ['taskId' => $invoiceDetail->task->id]) }}" target="_blank" class="block">
                                                    <div class="w-full max-w-2xl mx-auto bg-white rounded-2xl overflow-hidden shadow-lg border-2 border-blue-700 flex flex-col md:flex-row">
                                                        <div class="bg-blue-700 text-white md:w-1/4 p-4 flex flex-row md:flex-col justify-between md:justify-between gap-2">
                                                            <div>
                                                                <h2 class="text-lg md:text-xl font-bold">{{ $invoice->currency }} {{ $invoiceDetail->task_price }}</h2>
                                                                <p class="text-xs uppercase font-semibold">Travel Voucher</p>
                                                            </div>
                                                            <div class="text-sm text-right md:text-left">
                                                                <p class="font-medium">Flight Booking Issued</p>
                                                                <p class="text-xs italic leading-tight hidden md:block">"Generated by City Tour"</p>
                                                            </div>
                                                        </div>
                                                        <div class="relative w-full md:w-0 h-0 md:h-auto z-20">
                                                            <div class="hidden md:block absolute -top-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-white rounded-full z-30"></div>
                                                            <div class="hidden md:block absolute -bottom-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-white rounded-full z-30"></div>
                                                        </div>
                                                        <div class="flex-1 bg-gradient-to-r from-blue-100 via-white to-amber-50 p-4 md:p-6 space-y-3 md:space-y-4">
                                                            <div class="flex justify-between items-center">
                                                                <div class="text-left">
                                                                    <h3 class="text-lg md:text-xl font-bold tracking-wider">
                                                                        {{ $invoiceDetail->task->flightDetails->airport_from ?? 'N/A' }}
                                                                        <span class="mx-1 md:mx-2 text-blue-700">✈</span>
                                                                        {{ $invoiceDetail->task->flightDetails->airport_to ?? 'N/A' }}
                                                                    </h3>
                                                                </div>
                                                            </div>
                                                            <div class="border-t-2 border-dashed border-gray-400"></div>
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 text-sm gap-y-2 gap-x-6 md:gap-x-10 text-gray-800">
                                                                <div><strong>Name:</strong> {{ $invoiceDetail->task->client?->full_name ?? 'N/A' }}</div>
                                                                <div><strong>Flight:</strong> {{ $invoiceDetail->task->flightDetails->flight_number ?? 'N/A' }}</div>
                                                                <div><strong>Date:</strong> {{ $invoiceDetail->task->flightDetails->readable_departure_time ?? 'N/A' }}</div>
                                                                <div><strong>Reference:</strong> {{ $invoiceDetail->task->reference }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                                @elseif(strtolower($invoiceDetail->task->type ?? '') === 'hotel')
                                                <a href="{{ route('tasks.pdf.hotel', ['taskId' => $invoiceDetail->task->id]) }}" target="_blank" class="block">
                                                    <div class="bg-[#fdfaf6] rounded-xl border-[3px] border-[#d4b996] shadow-md p-4 md:p-5 max-w-lg mx-auto relative font-[Georgia,serif]">
                                                        <h2 class="text-center text-xl md:text-2xl text-[#355070] tracking-wide font-semibold mb-2">Hotel Reservation</h2>
                                                        <p class="text-center text-sm text-gray-700 italic mb-4">A gift from <span class="text-amber-700">{{ $invoiceDetail->task->supplier->name ?? 'N/A' }}</span></p>
                                                        <div class="text-center text-base md:text-lg font-bold text-[#355070] border-y border-dashed border-gray-400 py-2 uppercase whitespace-normal break-words px-2 max-w-full">
                                                            {{ $invoiceDetail->task->hotelDetails->hotel->name ?? 'N/A' }}
                                                        </div>
                                                        <div class="flex flex-col sm:flex-row justify-between items-center mt-4 md:mt-6 gap-4 md:gap-6">
                                                            <div class="bg-white rounded-xl shadow px-4 py-3 text-center border border-gray-300 flex flex-col justify-center items-center min-w-[100px]">
                                                                <p class="text-xs text-gray-500 mb-1 leading-none">{{ $invoiceDetail->task->hotelDetails->date_check_in ?? '' }}</p>
                                                                <p class="text-3xl md:text-4xl font-bold text-blue-900 leading-tight">{{ $invoiceDetail->task->hotelDetails->day_check_in ?? '' }}</p>
                                                                <p class="text-xs text-gray-500 mt-1 leading-none">{{ $invoiceDetail->task->hotelDetails->year_check_in ?? '' }}</p>
                                                            </div>
                                                            <div class="hidden sm:block h-24 border-l border-gray-300"></div>
                                                            <div class="relative flex-1 w-full sm:w-auto rounded-md bg-[#fffaf2] border border-amber-200 p-3 md:p-4 shadow-inner overflow-hidden">
                                                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-amber-400 rounded-l-md"></div>
                                                                <div class="relative z-10 text-sm text-gray-800 space-y-1 pl-2">
                                                                    <p><span class="font-semibold">Client:</span> {{ $invoiceDetail->task->client->full_name ?? 'N/A' }}</p>
                                                                    <p><span class="font-semibold">Reference:</span> {{ $invoiceDetail->task->reference ?? 'N/A' }}</p>
                                                                    <p><span class="font-semibold">Room:</span> {{ $invoiceDetail->task->hotelDetails->room_type ?? 'N/A' }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                                @else
                                                <div class="p-4 bg-gray-100 rounded-lg text-gray-500 text-center">
                                                    Task type "{{ $invoiceDetail->task->type ?? 'Unknown' }}" voucher not available
                                                </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @can('accountantEdit', $invoice)
                            @if($invoice->status !== 'unpaid')
                                <a data-tooltip="Accountant edit"
                                    href="{{ route('invoice.accountant.edit', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                    class="p-2 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 hover:shadow-sm transition-all">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="m4.144 16.735.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281 5.1 5.1 0 0 1 2.346 1.372 5.1 5.1 0 0 1 1.384 2.346 1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184"/>
                                    </svg>
                                </a>
                            @endif
                        @endcan

                        @if ($invoice->refund && $invoice->status !== 'paid')
                            <a data-tooltip="View/Edit refund"
                                href="{{ route('refunds.edit', [$invoice->refund->id]) }}"
                                class="p-2 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 hover:shadow-sm transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M3 10h7m-7 4h4m6-4v8m4-8v8M7 4h10l4 4v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                                </svg>
                            </a>
                        @endif

                        @if (in_array($invoice->status, ['unpaid', 'partial'], true))
                            @if($invoice->is_locked && !in_array(auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::ACCOUNTANT]))
                                <span data-tooltip="Invoice is locked" class="p-2 rounded-lg bg-gray-50 text-gray-300 cursor-not-allowed">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="m4.144 16.735.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281 5.1 5.1 0 0 1 2.346 1.372 5.1 5.1 0 0 1 1.384 2.346 1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184"/>
                                    </svg>
                                </span>
                            @else
                            <a data-tooltip="Edit invoice"
                                href="{{ route('invoice.edit', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                class="p-2 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 hover:shadow-sm transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="m4.144 16.735.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281 5.1 5.1 0 0 1 2.346 1.372 5.1 5.1 0 0 1 1.384 2.346 1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184"/>
                                </svg>
                            </a>
                        @endif
                        @endif

                        @if(auth()->check())
                            <button type="button" data-tooltip="Send email" 
                                data-invoice-number="{{ $invoice->invoice_number }}"
                                data-company-id="{{ $companyId }}" 
                                data-agent-email="{{ $invoice->agent->email ?? '' }}"
                                data-client-email="{{ $invoice->client->email ?? '' }}" 
                                onclick="openQuickEmailModal(this)" 
                                class="p-2 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 hover:shadow-sm transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        @endif

                        @if (in_array(Auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::ACCOUNTANT, \App\Models\Role::COMPANY]))
                            <form action="{{ route('invoice.delete', $invoice->id) }}" method="POST" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" data-tooltip="Delete invoice" 
                                    class="p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 hover:shadow-sm transition-all"
                                    onclick="return confirm('Are you sure you want to delete this invoice?')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M20.5 6H3.5M18.833 8.5l-.46 6.9c-.177 2.654-.265 3.981-1.13 4.79-.865.81-2.196.81-4.856.81h-.774c-2.66 0-3.991 0-4.856-.81-.865-.809-.954-2.136-1.13-4.79L5.166 8.5M9.5 11l.5 5M14.5 11l-.5 5"/>
                                        <path d="M6.5 6c.056 0 .084 0 .11-.001.823-.021 1.55-.544 1.83-1.319.008-.024.017-.05.035-.103l.097-.29a1.77 1.77 0 0 1 .18-.48c.219-.42.625-.713 1.094-.788.117-.018.248-.018.51-.018h3.29c.261 0 .392 0 .51.018.468.075.874.367 1.093.788.07.134.112.258.18.48l.097.29.035.103c.28.775 1.006 1.298 1.83 1.32.025 0 .053 0 .109 0"/>
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div class="p-8 text-center text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-lg font-medium">No invoices found</p>
                <p class="text-sm">Create a new invoice to get started</p>
            </div>
            @endforelse
        </div>

        <x-pagination :data="$invoices" />

        <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30 p-4">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-4 border-b sticky top-0 bg-white">
                    <h3 id="editModalTitle" class="text-lg font-semibold">Edit</h3>
                    <button type="button" class="text-gray-500 hover:text-red-500 text-2xl" onclick="closeEditModal()">&times;</button>
                </div>
                <form id="editForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <div class="p-4 space-y-4">
                        <p id="editLabel" class="text-sm text-gray-600">Amount per Task</p>
                        <div id="taskAmountsContainer"></div>
                        <div class="flex items-center justify-between border-t pt-3">
                            <div class="text-sm text-gray-500">New invoice total</div>
                            <div class="text-lg font-bold"><span id="total-payment-display">0.00</span></div>
                        </div>
                    </div>
                    <div class="p-4 border-t bg-gray-50 flex justify-end gap-2 sticky bottom-0">
                        <button type="button" class="px-4 py-2 text-sm rounded border hover:bg-gray-100" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm rounded bg-blue-600 text-white hover:bg-blue-700">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="quickEmailModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50 hidden p-4" onclick="handleQuickEmailBackdropClick(event)">
            <div class="bg-white rounded-lg shadow-lg p-4 md:p-6 w-full max-w-md max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800">Send Invoice via Email</h2>
                    <button type="button" onclick="closeQuickEmailModal()" class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                </div>

                <form id="quickEmailForm" class="space-y-4">
                    @csrf
                    <input type="hidden" id="quickEmailInvoiceNumber" name="invoice_number">
                    <input type="hidden" id="quickEmailCompanyId" name="company_id">
                    
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600">Select recipients for invoice <strong id="quickEmailInvoiceDisplay"></strong></p>
                        <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="send_to_agent" id="quick_send_to_agent" value="1" 
                                class="form-checkbox h-5 w-5 text-indigo-600 rounded" checked>
                            <div class="ml-3">
                                <span class="block font-medium text-gray-800">Agent</span>
                                <span class="block text-sm text-gray-500" id="quickAgentEmail">-</span>
                            </div>
                        </label>
                        <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="send_to_client" id="quick_send_to_client" value="1"
                                class="form-checkbox h-5 w-5 text-indigo-600 rounded">
                            <div class="ml-3">
                                <span class="block font-medium text-gray-800">Client</span>
                                <span class="block text-sm text-gray-500" id="quickClientEmail">-</span>
                            </div>
                        </label>
                        <div class="pt-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Additional Email Addresses</label>
                            <input type="text" name="custom_emails" id="quick_custom_emails" 
                                class="w-full border border-gray-300 rounded-lg p-2 text-sm"
                                placeholder="email1@example.com, email2@example.com">
                            <p class="text-xs text-gray-500 mt-1">Separate multiple emails with commas</p>
                        </div>
                    </div>
                    <div id="quickEmailSuccessMessage" class="hidden p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm"></div>
                    <div id="quickEmailErrorMessage" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm"></div>
                    <div class="flex justify-between pt-4">
                        <button type="button" onclick="closeQuickEmailModal()"
                            class="px-4 py-2 border border-gray-300 rounded-full text-gray-700 hover:bg-gray-100 transition">
                            Cancel
                        </button>
                        <button type="submit" id="quickSubmitSendEmail"
                            class="px-5 py-2 bg-indigo-600 text-white rounded-full hover:bg-indigo-700 transition flex items-center">
                            <span id="quickSendEmailBtnText">Send Email</span>
                            <span id="quickSendEmailSpinner" class="hidden ml-2">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-fade-in';
                toast.textContent = 'Copied: ' + text;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            });
        }

        flatpickr("#date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [
                "{{ request('from_date') }}",
                "{{ request('to_date') }}"
            ].filter(Boolean)
        });

        document.getElementById('invoice-filter-form').addEventListener('submit', function(e) {
            const range = document.getElementById('date-range').value.split(' to ');
            document.getElementById('from_date').value = range[0] ? range[0].trim() : '';
            document.getElementById('to_date').value = range[1] ? range[1].trim() : range[0];
        });

        const companyId = "{{ $companyId ?? '' }}";
        const updateDateUrl = "{{ route('invoice.updateDate', ['companyId' => 'COMPANY_ID', 'invoiceNumber' => 'INVOICE_NUM']) }}";
        const updateAmountUrl = "{{ route('invoice.updateAmount', ['companyId' => 'COMPANY_ID', 'invoiceNumber' => 'INVOICE_NUM']) }}";

        function openEditModal(kind, btn) {
            const modal = document.getElementById('editModal');
            const form = document.getElementById('editForm');
            const titleEl = document.getElementById('editModalTitle');
            const labelEl = document.getElementById('editLabel');
            const container = document.getElementById('taskAmountsContainer');
            const totalRow = document.getElementById('total-payment-display')?.closest('.flex');
            const number = btn.dataset.number;

            if (kind === 'date') {
                titleEl.textContent = 'Update Invoice Date';
                labelEl.textContent = 'Invoice Date';
                container.innerHTML = `
                    <input type="date" name="invdate" class="w-full border rounded px-3 py-2 text-sm" value="${btn.dataset.date}" required>`;
                if (totalRow) totalRow.classList.add('hidden');
                form.action = updateDateUrl.replace('COMPANY_ID', encodeURIComponent(companyId)).replace('INVOICE_NUM', encodeURIComponent(number));
            } else if (kind === 'amount') {
                titleEl.textContent = 'Update Invoice Amounts';
                labelEl.textContent = 'Amount per Task';

                const invoiceCard = btn.closest('[data-tasks]');
                const tasks = JSON.parse(invoiceCard.dataset.tasks);
                container.innerHTML = '';

                let total = 0;
                let gridWrapper = `<div class="grid grid-cols-1 gap-3">`;
                for (const t of tasks) {
                    total += parseFloat(t.amount || 0);
                    gridWrapper += `
                        <div class="rounded-lg border shadow-sm p-3 bg-white hover:shadow-md transition">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div class="text-sm font-semibold">${t.reference}</div>
                                <span class="inline-flex items-center text-xs px-2 py-0.5 rounded-full bg-gray-100">${t.type}</span>
                            </div>
                            <div class="mt-2 text-xs text-gray-600 space-y-1">
                                <div><span class="font-medium">Client:</span> ${t.client}</div>
                                <div><span class="font-medium">Supplier:</span> ${t.supplier}</div>
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs text-gray-600 mb-1">Amount (${t.currency})</label>
                                <input type="number" step="0.001" name="tasks[${t.id}]" value="${t.amount}"
                                    class="task-input w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                    oninput="calculateTotalPayment()" required>
                            </div>
                        </div>
                    `;
                }
                gridWrapper += `</div>`;
                container.insertAdjacentHTML('beforeend', gridWrapper);
                
                if (totalRow) totalRow.classList.remove('hidden');
                const totalEl = document.getElementById('total-payment-display');
                if (totalEl) totalEl.textContent = total.toFixed(3);

                form.action = updateAmountUrl.replace('COMPANY_ID', encodeURIComponent(companyId)).replace('INVOICE_NUM', encodeURIComponent(number));
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.onclick = (e) => {
                if (e.target === modal) closeEditModal();
            };
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function calculateTotalPayment() {
            let total = 0;
            document.querySelectorAll('.task-input').forEach(input => {
                const v = parseFloat(input.value);
                if (!isNaN(v)) total += v;
            });
            const totalEl = document.getElementById('total-payment-display');
            if (totalEl) totalEl.textContent = total.toFixed(3);
        }

        function openQuickEmailModal(btn) {
            const modal = document.getElementById('quickEmailModal');
            const invoiceNumber = btn.dataset.invoiceNumber;
            const companyId = btn.dataset.companyId;
            const agentEmail = btn.dataset.agentEmail || '';
            const clientEmail = btn.dataset.clientEmail || '';

            document.getElementById('quickEmailInvoiceNumber').value = invoiceNumber;
            document.getElementById('quickEmailCompanyId').value = companyId;
            document.getElementById('quickEmailInvoiceDisplay').textContent = invoiceNumber;
            document.getElementById('quickAgentEmail').textContent = agentEmail || 'No email available';
            document.getElementById('quickClientEmail').textContent = clientEmail || 'No email available';

            resetQuickEmailForm();
            modal.classList.remove('hidden');
        }

        function closeQuickEmailModal() {
            const modal = document.getElementById('quickEmailModal');
            modal.classList.add('hidden');
            resetQuickEmailForm();
        }

        function resetQuickEmailForm() {
            const agentEmail = document.getElementById('quickAgentEmail').textContent;
            document.getElementById('quick_send_to_agent').checked = agentEmail && agentEmail !== 'No email available';
            document.getElementById('quick_send_to_client').checked = false;
            document.getElementById('quick_custom_emails').value = '';
            document.getElementById('quickEmailSuccessMessage').classList.add('hidden');
            document.getElementById('quickEmailErrorMessage').classList.add('hidden');

            const submitBtn = document.getElementById('quickSubmitSendEmail');
            const btnText = document.getElementById('quickSendEmailBtnText');
            const spinner = document.getElementById('quickSendEmailSpinner');
            
            if (submitBtn) submitBtn.disabled = false;
            if (btnText) btnText.textContent = 'Send Email';
            if (spinner) spinner.classList.add('hidden');
        }

        function handleQuickEmailBackdropClick(event) {
            if (event.target.id === 'quickEmailModal') {
                closeQuickEmailModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const quickEmailForm = document.getElementById('quickEmailForm');
            if (quickEmailForm) {
                quickEmailForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const invoiceNumber = document.getElementById('quickEmailInvoiceNumber').value;
                    const companyId = document.getElementById('quickEmailCompanyId').value;
                    const sendToAgent = document.getElementById('quick_send_to_agent').checked;
                    const sendToClient = document.getElementById('quick_send_to_client').checked;
                    const customEmails = document.getElementById('quick_custom_emails').value;
                    
                    const agentEmail = document.getElementById('quickAgentEmail').textContent;
                    const clientEmail = document.getElementById('quickClientEmail').textContent;
                    
                    const recipients = [];
                    if (sendToAgent && agentEmail && agentEmail !== 'No email available') {
                        recipients.push(agentEmail);
                    }
                    if (sendToClient && clientEmail && clientEmail !== 'No email available') {
                        recipients.push(clientEmail);
                    }
                    if (customEmails) {
                        customEmails.split(',').forEach(email => {
                            const trimmed = email.trim();
                            if (trimmed && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
                                recipients.push(trimmed);
                            }
                        });
                    }
                    
                    const successMsg = document.getElementById('quickEmailSuccessMessage');
                    const errorMsg = document.getElementById('quickEmailErrorMessage');
                    const submitBtn = document.getElementById('quickSubmitSendEmail');
                    const btnText = document.getElementById('quickSendEmailBtnText');
                    const spinner = document.getElementById('quickSendEmailSpinner');
                    
                    if (recipients.length === 0) {
                        errorMsg.textContent = 'Please select at least one recipient or enter a valid email address.';
                        errorMsg.classList.remove('hidden');
                        successMsg.classList.add('hidden');
                        return;
                    }

                    submitBtn.disabled = true;
                    btnText.textContent = 'Sending...';
                    spinner.classList.remove('hidden');
                    successMsg.classList.add('hidden');
                    errorMsg.classList.add('hidden');
                    
                    try {
                        const url = `/invoice/${companyId}/${invoiceNumber}/send-email`;
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                recipients: recipients,
                                send_to_agent: sendToAgent,
                                send_to_client: sendToClient,
                                custom_emails: customEmails
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            successMsg.textContent = result.message;
                            successMsg.classList.remove('hidden');
                            errorMsg.classList.add('hidden');
                            setTimeout(() => closeQuickEmailModal(), 2000);
                        } else {
                            throw new Error(result.message || 'Failed to send email');
                        }
                    } catch (error) {
                        errorMsg.textContent = error.message || 'An error occurred while sending the email.';
                        errorMsg.classList.remove('hidden');
                        successMsg.classList.add('hidden');
                    } finally {
                        submitBtn.disabled = false;
                        btnText.textContent = 'Send Email';
                        spinner.classList.add('hidden');
                    }
                });
            }
        });
    </script>

    <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fade-in 0.2s ease-out;
        }
    </style>
</x-app-layout>