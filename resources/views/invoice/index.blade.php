<x-app-layout>
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 6px 10px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 7px;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
    </style>

    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Invoices List</h2>
            <!-- total Invoice number -->
            <div data-tooltip="number of invoices"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $totalInvoices }}</span>
            </div>
        </div>
        <!-- add new Invoice & refresh page -->
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload"
                class="refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>

            <!-- add new invoice -->
            @can('create', App\Models\Invoice::class)
            <a href="{{ route('invoices.create') }}">
                <div data-tooltip-left="Create new Invoice"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
            @endcan
        </div>
    </div>

    <div class="tableCon">
        <div class="content-70">
            <!-- Table  -->
            <div class="panel BoxShadow rounded-lg">
                <div>
                    <div class="flex items-center p-4 gap-3 md:flex-nowrap">
                        <x-search
                            :action="route('invoices.index')"
                            searchParam="search"
                            placeholder="Quick search for invoices" />
                        <!-- <button @click="openFilters = !openFilters" class="shrink-0 inline-flex items-center gap-2 rounded-full bg-amber-100 px-4 py-2 text-sm text-amber-800 ring-1 ring-amber-200 hover:bg-amber-200 transition dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800 dark:hover:bg-amber-900/60">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M4 6h16M7 12h10M10 18h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Filters
                        </button> -->
                    </div>
                    <!-- <div x-show="openFilters" x-cloak x-transition
                        class="mt-3 rounded-xl border border-gray-200 bg-gray-50/70 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
                        <form action="{{ route('invoices.index') }}" method="GET" class="px-4 pt-4">
                            <input type="hidden" name="sortBy" value="{{ request('sortBy', 'created_at') }}">
                            <input type="hidden" name="sortOrder" value="{{ request('sortOrder', 'desc') }}">
                            <input type="hidden" name="search" value="{{ request('search') }}">
                            <div class="grid grid-cols-1 sm:grid-cols-3 sm:gap-10 items-end">
                                <div>
                                    <label for="date_field" class="block text-xs font-medium text-gray-600 dark:text-slate-300">Filter by</label>
                                    <div class="relative mt-1">
                                        <select name="date_field" id="date_field"
                                            class="h-10 w-full appearance-none rounded-lg border border-gray-300 bg-white pr-9 pl-3 text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-blue-400 dark:focus:ring-blue-900/40"
                                        >
                                            <option value="created_at" {{ request('date_field') == 'created_at' ? 'selected' : '' }}>Created At</option>
                                            <option value="invoice_date" {{ request('date_field') == 'invoice_date' ? 'selected' : '' }}>Invoice Date</option>
                                        </select>
                                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <label for="from_date" class="block text-xs font-medium text-gray-600 dark:text-slate-300">From date</label>
                                    <div class="relative mt-1">
                                        <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white pl-10 pr-3 text-sm shadow-sm outline-none ring-0 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-blue-400 dark:focus:ring-blue-900/40"
                                        />
                                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <label for="to_date" class="block text-xs font-medium text-gray-600 dark:text-slate-300">To date</label>
                                    <div class="relative mt-1">
                                        <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}"
                                            class="h-10 w-full rounded-lg border border-gray-300 bg-white pl-10 pr-3 text-sm shadow-sm outline-none ring-0 transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-blue-400 dark:focus:ring-blue-900/40"
                                        />
                                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="sticky bottom-0 -mx-4 mt-4 flex items-center justify-end gap-2 border-t border-gray-200 bg-white/80 px-4 py-3 backdrop-blur dark:border-slate-700 dark:bg-slate-900/60">
                                <a href="{{ route('invoices.index') }}" class="rounded-full bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                                    Clear
                                </a>
                                <button type="submit" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500">
                                    Apply Filters
                                </button>
                            </div>
                        </form>
                    </div> -->
                </div>

                <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="flex items-center gap-3 rounded-lg p-4 shadow-sm bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 18.75V5.25a2.25 2.25 0 0 1 2.25-2.25h15a2.25 2.25 0 0 1 2.25 2.25v13.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25zM18 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-blue-600 dark:text-blue-300">Total Net</div>
                            <div class="text-lg font-semibold text-blue-700 dark:text-blue-200">{{ number_format($totalNet, 3) }} KWD</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg p-4 shadow-sm bg-emerald-50 border border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 7h6M9 11h6m-8 4h10M5 21l1.5-1.5L8 21l1.5-1.5L11 21l1.5-1.5L14 21l1.5-1.5L17 21l1.5-1.5L20 21V3a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v18z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-300">Total Sales</div>
                            <div class="text-lg font-semibold text-emerald-700 dark:text-emerald-200">{{ number_format($totalSales, 3) }} KWD</div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end">
                        <div class="p-4 w-full max-w-xs">
                            <form method="GET" action="{{ route('invoices.index') }}" class="flex flex-row items-end gap-2" id="invoice-filter-form">
                                <!-- Dropdown -->
                                <div class="flex flex-col justify-end">
                                    <label class="text-xs font-semibold text-gray-600 mb-1">Filter By</label>
                                    <select name="date_field" class="border rounded px-2 py-1 text-sm min-w-[150px]">
                                        <option value="created_at" {{ request('date_field') == 'created_at' ? 'selected' : '' }}>Created Date</option>
                                        <option value="invoice_date" {{ request('date_field') == 'invoice_date' ? 'selected' : '' }}>Invoice Date</option>
                                    </select>
                                </div>
                                <!-- Date Range -->
                                <div class="flex flex-col justify-end">
                                    <label class="text-xs font-semibold text-gray-600 mb-1">Date Range</label>
                                    <input type="text" id="date-range" class="border rounded px-2 py-1 text-sm min-w-[240px]" placeholder="Select date range" autocomplete="off" />
                                    <input type="hidden" name="from_date" id="from_date" value="{{ request('from_date') }}">
                                    <input type="hidden" name="to_date" id="to_date" value="{{ request('to_date') }}">
                                </div>
                                <!-- Buttons -->
                                <div class="flex flex-row items-end gap-1 pt-5">
                                    <a href="{{ route('invoices.index') }}" class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs hover:bg-gray-200 border border-gray-300 flex items-center">Clear</a>
                                    <button type="submit" class="px-2 py-1 rounded bg-blue-600 text-white text-xs hover:bg-blue-700 border border-blue-700 flex items-center">Apply</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <!-- table -->
                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <th class="p-3 text-center text-md font-bold text-gray-500">Actions</th>
                                    <th class="p-3 text-center text-md font-bold text-gray-500">Invoice Number</th>
                                    <th class="p-3 text-center text-md font-bold text-gray-500">Agent name</th>
                                    <th class="p-3 text-center text-md font-bold text-gray-500">Client name</th>
                                    <th class="p-3 text-center text-md font-bold text-gray-500">Status</th>
                                    <th class="p-3 text-center text-md font-bold text-gray-500">Payment Type</th>
                                    <th class="p-3 text-center text-md font-bold text-gray-500">Net Amount</th>
                                    <th class="p-3 text-center text-md font-bold text-gray-500">Profit</th>
                                    <th class="p-3 text-center text-md font-bold text-gray-500">Invoice Amount</th>
                                    <th>
                                        <a href="{{ request()->fullUrlWithQuery([
                                            'sortBy' => 'created_at',
                                            'sortOrder' => (request('sortBy') === 'created_at' && request('sortOrder') === 'asc') ? 'desc' : 'asc'
                                        ]) }}"
                                            class="flex items-center gap-2 p-3  text-md font-bold text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-gray-100 cursor-pointer transition-all duration-200">
                                            Created Date
                                            @if(request('sortBy') !== 'created_at')
                                            <svg class="w-4 h-4 opacity-70 hover:opacity-100 transform hover:scale-110 transition-all duration-200"
                                                fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                <path stroke-width="2" d="M6 9l6-6 6 6M6 15l6 6 6-6" />
                                            </svg>
                                            @else
                                            <svg class="w-3 h-3 transform hover:scale-110 transition-all duration-200"
                                                fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                                @if(request('sortOrder', 'desc') === 'asc')
                                                <path stroke-width="3" d="m26.71 10.29-10-10a1 1 0 0 0-1.41 0l-10 10 1.41 1.41L15 3.41V32h2V3.41l8.29 8.29z" />
                                                @else
                                                <path stroke-width="3" d="M26.29 20.29 18 28.59V0h-2v28.59l-8.29-8.3-1.42 1.42 10 10a1 1 0 0 0 1.41 0l10-10z" />
                                                @endif
                                            </svg>
                                            @endif
                                        </a>
                                    </th>
                                    <th>
                                        <a href="{{ request()->fullUrlWithQuery([
                                            'sortBy' => 'invoice_date',
                                            'sortOrder' => (request('sortBy') === 'invoice_date' && request('sortOrder') === 'asc') ? 'desc' : 'asc'
                                        ]) }}"
                                            class="flex items-center gap-2 p-3 text-left text-md font-bold text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-gray-100 cursor-pointer transition-all duration-200">
                                            Invoice Date
                                            @if(request('sortBy') !== 'invoice_date')
                                            <svg class="w-4 h-4 opacity-70 hover:opacity-100 transform hover:scale-110 transition-all duration-200"
                                                fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                <path stroke-width="2" d="M6 9l6-6 6 6M6 15l6 6 6-6" />
                                            </svg>
                                            @else
                                            <svg class="w-3 h-3 transform hover:scale-110 transition-all duration-200"
                                                fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                                @if(request('sortOrder', 'desc') === 'asc')
                                                <path stroke-width="3" d="m26.71 10.29-10-10a1 1 0 0 0-1.41 0l-10 10 1.41 1.41L15 3.41V32h2V3.41l8.29 8.29z" />
                                                @else
                                                <path stroke-width="3" d="M26.29 20.29 18 28.59V0h-2v28.59l-8.29-8.3-1.42 1.42 10 10a1 1 0 0 0 1.41 0l10-10z" />
                                                @endif
                                            </svg>
                                            @endif
                                        </a>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($invoices->isEmpty())
                                <tr>
                                    <td colspan="11" class="text-center p-3 text-sm font-semibold text-gray-500 ">
                                        No data for now.... Create new!</td>
                                </tr>
                                @else
                                @foreach ($invoices as $invoice)
                                @php
                                $invoiceDetail = ($invoice->invoiceDetails ?? collect())->first();
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
                                <tr data-price="{{ $invoice->total }}"
                                    data-tasks='@json($tasksPayload)'
                                    data-supplier-id="{{ $invoiceDetail?->task?->supplier?->id ?? '' }}"
                                    data-branch-id="{{ $invoice->agent?->branch?->id ?? '' }}"
                                    data-agent-id="{{ $invoice->agent_id ?? '' }}"
                                    data-status="{{ $invoice->status ?? '' }}"
                                    data-type="{{ $invoiceDetail?->task?->type ?? '' }}"
                                    data-client-id="{{ $invoice->client?->id ?? '' }}"
                                    data-task-id="{{ $invoice->id ?? '' }}" class="taskRow">
                                    <td class="p-3 text-center text-sm flex gap-2">
                                        <a data-tooltip="View Invoice" target="_blank"
                                            href="{{ route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                            class="viewInvoice {{ $invoice->payment_type ? 'text-blue-500 hover:underline' : 'text-gray-400 cursor-not-allowed' }}"
                                            @unless($invoice->payment_type) onclick="return false;" @endunless>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                height="20" viewBox="0 0 24 24">
                                                <g fill="none" stroke="currentColor" stroke-width="1">
                                                    <path
                                                        d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z"
                                                        opacity=".5"></path>
                                                    <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path>
                                                </g>
                                            </svg>
                                        </a>
                                        @can('accountantEdit', $invoice)
                                        @if($invoice->status !== 'unpaid')
                                        <a data-tooltip="Edit Invoice"
                                            href="{{ route('invoice.accountant.edit', ['companyId' => auth()->user()->accountant->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                            class="text-sm font-medium text-blue-600 hover:underline">

                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-green-500 dark:stroke-green-700">
                                                <path d="M18.18 8.03933L18.6435 7.57589C19.4113 6.80804 20.6563 6.80804 21.4241 7.57589C22.192 8.34374 22.192 9.58868 21.4241 10.3565L20.9607 10.82M18.18 8.03933C18.18 8.03933 18.238 9.02414 19.1069 9.89309C19.9759 10.762 20.9607 10.82 20.9607 10.82M18.18 8.03933L13.9194 12.2999C13.6308 12.5885 13.4865 12.7328 13.3624 12.8919C13.2161 13.0796 13.0906 13.2827 12.9882 13.4975C12.9014 13.6797 12.8368 13.8732 12.7078 14.2604L12.2946 15.5L12.1609 15.901M20.9607 10.82L16.7001 15.0806C16.4115 15.3692 16.2672 15.5135 16.1081 15.6376C15.9204 15.7839 15.7173 15.9094 15.5025 16.0118C15.3203 16.0986 15.1268 16.1632 14.7396 16.2922L13.5 16.7054L13.099 16.8391M13.099 16.8391L12.6979 16.9728C12.5074 17.0363 12.2973 16.9867 12.1553 16.8447C12.0133 16.7027 11.9637 16.4926 12.0272 16.3021L12.1609 15.901M13.099 16.8391L12.1609 15.901" stroke="" stroke-width="1.5" />
                                                <path d="M8 13H10.5"  stroke-width="1.5" stroke-linecap="round" />
                                                <path d="M8 9H14.5" stroke-width="1.5" stroke-linecap="round" />
                                                <path d="M8 17H9.5" stroke-width="1.5" stroke-linecap="round" />
                                                <path d="M19.8284 3.17157C18.6569 2 16.7712 2 13 2H11C7.22876 2 5.34315 2 4.17157 3.17157C3 4.34315 3 6.22876 3 10V14C3 17.7712 3 19.6569 4.17157 20.8284C5.34315 22 7.22876 22 11 22H13C16.7712 22 18.6569 22 19.8284 20.8284C20.7715 19.8853 20.9554 18.4796 20.9913 16" stroke-width="1.5" stroke-linecap="round" />
                                            </svg>

                                        </a>
                                        @endif
                                        @endcan
                                        @if ($invoice->refund)
                                        <a data-tooltip="View/Edit Refund"
                                            href="{{ route('refunds.edit', [$invoice->refund->task_id, $invoice->refund->id]) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                height="20" viewBox="0 0 24 24">
                                                <path fill="none" stroke="#00ab55"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42"
                                                    opacity=".5" />
                                            </svg>
                                        </a>
                                        @elseif (in_array($invoice->status, ['unpaid', 'partial'], true))
                                        <a data-tooltip="View/Edit Invoice"
                                            href="{{ route('invoice.edit', ['companyId' => $invoice->agent?->branch?->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                            class="text-sm font-medium text-blue-600 hover:underline">

                                            <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                                height="20" viewBox="0 0 24 24">
                                                <path fill="none" stroke="#00ab55"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42"
                                                    opacity=".5" />
                                            </svg>
                                        </a>
                                        @endif
                                        @if ($invoice->status === 'paid')
                                        <div x-data="{ viewVoucherModal_{{ $invoice->id }}: false }" class="group">
                                            <div data-tooltip="View Voucher">
                                                <svg @click="viewVoucherModal_{{ $invoice->id }} = true"
                                                    width="20" height="20" viewBox="0 0 24 24"
                                                    fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M15.3929 4.05365L14.8912 4.61112L15.3929 4.05365ZM19.3517 7.61654L18.85 8.17402L19.3517 7.61654ZM21.654 10.1541L20.9689 10.4592V10.4592L21.654 10.1541ZM3.17157 20.8284L3.7019 20.2981H3.7019L3.17157 20.8284ZM20.8284 20.8284L20.2981 20.2981L20.2981 20.2981L20.8284 20.8284ZM14 21.25H10V22.75H14V21.25ZM2.75 14V10H1.25V14H2.75ZM21.25 13.5629V14H22.75V13.5629H21.25ZM14.8912 4.61112L18.85 8.17402L19.8534 7.05907L15.8947 3.49618L14.8912 4.61112ZM22.75 13.5629C22.75 11.8745 22.7651 10.8055 22.3391 9.84897L20.9689 10.4592C21.2349 11.0565 21.25 11.742 21.25 13.5629H22.75ZM18.85 8.17402C20.2034 9.3921 20.7029 9.86199 20.9689 10.4592L22.3391 9.84897C21.9131 8.89241 21.1084 8.18853 19.8534 7.05907L18.85 8.17402ZM10.0298 2.75C11.6116 2.75 12.2085 2.76158 12.7405 2.96573L13.2779 1.5653C12.4261 1.23842 11.498 1.25 10.0298 1.25V2.75ZM15.8947 3.49618C14.8087 2.51878 14.1297 1.89214 13.2779 1.5653L12.7405 2.96573C13.2727 3.16993 13.7215 3.55836 14.8912 4.61112L15.8947 3.49618ZM10 21.25C8.09318 21.25 6.73851 21.2484 5.71085 21.1102C4.70476 20.975 4.12511 20.7213 3.7019 20.2981L2.64124 21.3588C3.38961 22.1071 4.33855 22.4392 5.51098 22.5969C6.66182 22.7516 8.13558 22.75 10 22.75V21.25ZM1.25 14C1.25 15.8644 1.24841 17.3382 1.40313 18.489C1.56076 19.6614 1.89288 20.6104 2.64124 21.3588L3.7019 20.2981C3.27869 19.8749 3.02502 19.2952 2.88976 18.2892C2.75159 17.2615 2.75 15.9068 2.75 14H1.25ZM14 22.75C15.8644 22.75 17.3382 22.7516 18.489 22.5969C19.6614 22.4392 20.6104 22.1071 21.3588 21.3588L20.2981 20.2981C19.8749 20.7213 19.2952 20.975 18.2892 21.1102C17.2615 21.2484 15.9068 21.25 14 21.25V22.75ZM21.25 14C21.25 15.9068 21.2484 17.2615 21.1102 18.2892C20.975 19.2952 20.7213 19.8749 20.2981 20.2981L21.3588 21.3588C22.1071 20.6104 22.4392 19.6614 22.5969 18.489C22.7516 17.3382 22.75 15.8644 22.75 14H21.25ZM2.75 10C2.75 8.09318 2.75159 6.73851 2.88976 5.71085C3.02502 4.70476 3.27869 4.12511 3.7019 3.7019L2.64124 2.64124C1.89288 3.38961 1.56076 4.33855 1.40313 5.51098C1.24841 6.66182 1.25 8.13558 1.25 10H2.75ZM10.0298 1.25C8.15538 1.25 6.67442 1.24842 5.51887 1.40307C4.34232 1.56054 3.39019 1.8923 2.64124 2.64124L3.7019 3.7019C4.12453 3.27928 4.70596 3.02525 5.71785 2.88982C6.75075 2.75158 8.11311 2.75 10.0298 2.75V1.25Z"
                                                        class="fill-black group-hover:fill-blue-600" />
                                                    <path opacity="0.5"
                                                        d="M13 2.5V5C13 7.35702 13 8.53553 13.7322 9.26777C14.4645 10 15.643 10 18 10H22"
                                                        stroke="#1C274C" stroke-width="1.5" />
                                                </svg>
                                            </div>
                                            <div x-cloak x-show="viewVoucherModal_{{ $invoice->id }}"
                                                class="fixed inset-0 z-20 bg-gray-800 bg-opacity-50 flex items-center justify-center overflow-y-auto p-4">
                                                <div @click.away="viewVoucherModal_{{ $invoice->id }}=false"
                                                    class="bg-white rounded-md border-2 max-w-4xl max-h-[80vh] overflow-y-auto shadow-lg">
                                                    <div class="flex justify-between gap-4 p-4">
                                                        <p class="text-lg font-semibold">
                                                            Voucher
                                                        </p>
                                                        <button type="button"
                                                            @click="viewVoucherModal_{{ $invoice->id }} = false"
                                                            class="text-red-500 font-bold text-xl">
                                                            &times;
                                                        </button>
                                                    </div>
                                                    <hr>
                                                    <div class="py-6 px-10 flex flex-col gap-4">
                                                        @foreach ($invoice->invoiceDetails as $invoiceDetail)
                                                        @if (strtolower($invoiceDetail->task->type) === 'flight')
                                                        <a href="{{ route('tasks.pdf.flight', ['taskId' => $invoiceDetail->task->id]) }}" target="_blank">
                                                            <div class="w-full max-w-2xl mx-auto bg-white rounded-2xl overflow-hidden shadow-lg border-2 border-blue-700 flex flex-row">
                                                                <div class="bg-blue-700 text-white w-1/4 p-4 flex flex-col justify-between">
                                                                    <div>
                                                                        <h2 class="text-xl font-bold">{{ $invoice->currency }} {{ $invoiceDetail->task_price }}</h2>
                                                                        <p class="text-xs uppercase font-semibold">Travel Voucher</p>
                                                                    </div>
                                                                    <div class="text-sm">
                                                                        <p class="font-medium">Flight Booking Issued</p>
                                                                        <!-- <p class="text-xs italic block leading-tight break-words w-full max-w-[120px] whitespace-normal">
                                                                            "Generated by City Tour" </p> -->
                                                                        <p class="text-xs italic block leading-tight">"Generated by City Tour"</p>
                                                                    </div>
                                                                </div>
                                                                <div class="relative w-0 z-20">
                                                                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-white rounded-full z-30"></div>
                                                                    <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-white rounded-full z-30"></div>
                                                                </div>
                                                                <div class="flex-1 bg-gradient-to-r from-blue-100 via-white to-koromiko-100 p-6 space-y-4">
                                                                    <div class="flex justify-between items-center">
                                                                        <div class="text-left">
                                                                            <h3 class="text-xl font-bold tracking-wider">
                                                                                {{ $invoiceDetail->task->flightDetails->airport_from ?? 'N/A' }}
                                                                                <span class="mx-2 text-blue-700">
                                                                                    ✈
                                                                                </span>
                                                                                {{ $invoiceDetail->task->flightDetails->airport_to ?? 'N/A' }}
                                                                            </h3>
                                                                        </div>
                                                                    </div>
                                                                    <div class="border-t-2 border-dashed border-gray-400"></div>
                                                                    <div class="grid grid-cols-1 sm:grid-cols-2 text-sm gap-y-2 gap-x-10 text-gray-800">
                                                                        <div><strong>Name:</strong> {{ $invoiceDetail->task->client->full_name }}</div>
                                                                        <div><strong>Flight:</strong> {{ $invoiceDetail->task->flightDetails->flight_number ?? 'N/A' }}</div>
                                                                        <div><strong>Date:</strong> {{ $invoiceDetail->task->flightDetails->readable_departure_time ?? 'N/A' }}</div>
                                                                        <div><strong>Reference:</strong> {{ $invoiceDetail->task->reference }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </a>
                                                        @elseif(strtolower($invoiceDetail->task->type) === 'hotel')
                                                        <a href="{{ route('tasks.pdf.hotel', ['taskId' => $invoiceDetail->task->id]) }}" target="_blank">
                                                            <div class="bg-[#fdfaf6] rounded-xl border-[3px] border-[#d4b996] shadow-md p-5 max-w-lg mx-auto relative font-[Georgia,serif]">
                                                                <h2 class="text-center text-2xl text-[#355070] tracking-wide font-semibold mb-2">Hotel Reservation</h2>
                                                                <p class="text-center text-sm text-gray-700 italic mb-4">A gift from <span class="text-koromiko-700">{{ $invoiceDetail->task->supplier->name }}</span></p>
                                                                <div class="text-center text-lg font-bold text-[#355070] border-y border-dashed border-gray-400 py-2 uppercase whitespace-normal break-words px-2 max-w-full">
                                                                    {{ $invoiceDetail->task->hotelDetails->hotel->name ?? 'n/a' }}
                                                                </div>
                                                                <div class="flex justify-between items-center mt-6 gap-6">
                                                                    <div class="bg-white rounded-xl shadow px-4 py-3 text-center border border-gray-300 flex flex-col justify-center items-center">
                                                                        <p class="text-xs text-gray-500 mb-1 leading-none">{{ $invoiceDetail->task->hotelDetails->date_check_in }}</p>
                                                                        <p class="text-4xl font-bold text-blue-900 leading-tight">{{ $invoiceDetail->task->hotelDetails->day_check_in }}</p>
                                                                        <p class="text-xs text-gray-500 mt-1 leading-none">{{ $invoiceDetail->task->hotelDetails->year_check_in }}</p>
                                                                    </div>
                                                                    <div class="h-24 border-l border-gray-300"></div>
                                                                    <div class="relative flex-1 rounded-md bg-[#fffaf2] border border-koromiko-200 p-4 shadow-inner overflow-hidden">
                                                                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-koromiko-400 rounded-l-md"></div>
                                                                        <div class="relative z-10 text-sm text-gray-800 space-y-1">
                                                                            <p><span class="font-semibold">Client:</span> {{ $invoiceDetail->task->client->full_name }}</p>
                                                                            <p><span class="font-semibold">Reference:</span> {{ $invoiceDetail->task->reference ?? 'N/A' }}</p>
                                                                            <p><span class="font-semibold">Room:</span> {{ $invoiceDetail->task->hotelDetails->room_type ?? 'N/A' }}</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </a>
                                                        @else
                                                        <div>
                                                            <p>Task type not supported</p>
                                                        </div>
                                                        @endif
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        <div id="viewInvoiceModal"
                                            class="fixed z-10 inset-0 flex items-center justify-center backdrop-blur-sm hidden">
                                            <div class="relative">
                                                <!-- Modal Content -->
                                                <div class="w-full">

                                                </div>
                                                <div id="invoiceInvoiceContent" class="">
                                                    <!-- Invoice content will be loaded here dynamically -->
                                                </div>
                                            </div>
                                        </div>
                                        @if (in_array(Auth()->user()->role_id, [\App\Models\Role::ADMIN, \App\Models\Role::ACCOUNTANT, \App\Models\Role::COMPANY]))
                                        <form action="{{ route('invoice.delete', $invoice->id) }}" method="POST" class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" data-tooltip="Delete Invoice" class="group p-1 rounded hover:bg-red-50" @click.stop onclick="return confirm('Are you sure you want to delete this invoice? This action cannot be undone.')">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-red-500 group-hover:stroke-red-700">
                                                    <path d="M20.5001 6H3.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                                    <path d="M18.8332 8.5L18.3732 15.3991C18.1962 18.054 18.1077 19.3815 17.2427 20.1907C16.3777 21 15.0473 21 12.3865 21H11.6132C8.95235 21 7.62195 21 6.75694 20.1907C5.89194 19.3815 5.80344 18.054 5.62644 15.3991L5.1665 8.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                                    <path d="M9.5 11L10 16" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                                    <path d="M14.5 11L14 16" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                                    <path d="M6.5 6C6.55588 6 6.58382 6 6.60915 5.99936C7.43259 5.97849 8.15902 5.45491 8.43922 4.68032C8.44784 4.65649 8.45667 4.62999 8.47434 4.57697L8.57143 4.28571C8.65431 4.03708 8.69575 3.91276 8.75071 3.8072C8.97001 3.38607 9.37574 3.09364 9.84461 3.01877C9.96213 3 10.0932 3 10.3553 3H13.6447C13.9068 3 14.0379 3 14.1554 3.01877C14.6243 3.09364 15.03 3.38607 15.2493 3.8072C15.3043 3.91276 15.3457 4.03708 15.4286 4.28571L15.5257 4.57697C15.5433 4.62992 15.5522 4.65651 15.5608 4.68032C15.841 5.45491 16.5674 5.97849 17.3909 5.99936C17.4162 6 17.4441 6 17.5 6" stroke="" stroke-width="1.5" />
                                                </svg>
                                            </button>
                                        </form>
                                        @endif
                                    </td>

                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        @if (auth()->check() && in_array(auth()->user()->role_id, [\App\Models\Role::COMPANY, \App\Models\Role::ACCOUNTANT]))
                                        <a href="{{ route('invoice.details', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                            class="text-sm font-medium text-blue-600 hover:underline" target="_blank"> {{ $invoice->invoice_number }}
                                        </a>
                                        @else
                                        {{ $invoice->invoice_number }}
                                        @endif
                                    </td>

                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        @if (auth()->check() && in_array(auth()->user()->role_id, [\App\Models\Role::COMPANY, \App\Models\Role::AGENT]))
                                        <a href="{{ route('agents.show', $invoice->agent->id) }}" class="text-sm font-medium text-blue-600 hover:underline" target="_blank">
                                            {{ $invoice->agent->name }}
                                        </a>
                                        @else
                                        {{ $invoice->agent->name }}
                                        @endif
                                    </td>
                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        @if (auth()->check() && in_array(auth()->user()->role_id, [\App\Models\Role::COMPANY, \App\Models\Role::AGENT]))
                                        <a href="{{ route('clients.show', $invoice->client->id) }}" class="text-sm font-medium text-blue-600 hover:underline" target="_blank">
                                            {{ $invoice->client->full_name }}
                                        </a>
                                        @else
                                        {{ $invoice->client->full_name }}
                                        @endif
                                    </td>
                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        @if ($invoice->refund)
                                        <span class="relative inline-flex cursor-default" data-tooltip="Invoice Refund">
                                            <span class="badge badge-outline-success">{{ $invoice->status }}</span>
                                        </span>
                                        @elseif (in_array($invoice->status, ['paid']))
                                         <a href="{{ route('tasks.pdf.receipt', ['taskId' => $invoiceDetail->task->id]) }}" target="_blank"> 
                                            <span class="badge badge-outline-success">{{ $invoice->status }}</span>
                                         </a> 
                                        @elseif ($invoice->status === 'paid by refund')
                                        <span class="badge badge-outline-success">{{ $invoice->status }}</span>
                                        @else
                                        <span class="badge badge-outline-danger">{{ $invoice->status }}</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        {{ $invoice->payment_type ? ucwords($invoice->payment_type) : 'N/A' }}
                                    </td>
                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        {{ number_format($invoice->invoicedetails->sum('supplier_price'), 3) }} {{ $invoice->currency }}
                                    </td>
                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        {{ number_format($invoice->invoicedetails->sum('markup_price'), 3) }} {{ $invoice->currency }}
                                    </td>
                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        @if ($invoice->status === 'paid' && !$invoice->refund && ($invoice->payment_type === 'full' || $invoice->payment_type === 'cash'))
                                        <button type="button" class="underline text-blue-600 hover:text-blue-800"
                                            data-number="{{ $invoice->invoice_number }}" data-amount="{{ $invoice->amount }}" onclick="openEditModal('amount', this)">
                                            {{ $invoice->amount }} {{ $invoice->currency }}
                                        </button>
                                        @else
                                        {{ number_format($invoice->amount, 3) }} {{ $invoice->currency }}
                                        @endif
                                    </td>
                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        {{ $invoice->created_at }}
                                    </td>

                                    <td class="p-3 text-center text-sm font-semibold text-gray-500">
                                        @if ($invoice->status === 'paid')
                                        <button type="button" class="underline text-blue-600 hover:text-blue-800" data-number="{{ $invoice->invoice_number }}"
                                            data-date="{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d') }}" onclick="openEditModal('date', this)">
                                            {{ $invoice->invoice_date }}
                                        </button>
                                        @else
                                        {{ $invoice->invoice_date }}
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/30">
                        <div class="bg-white rounded-md shadow-lg w-full max-w-md">
                            <div class="flex items-center justify-between p-4 border-b">
                                <h3 id="editModalTitle" class="font-semibold">Edit</h3>
                                <button type="button" class="text-gray-500" onclick="closeEditModal()">&times;</button>
                            </div>
                            <form id="editForm" method="POST" action="">
                                @csrf
                                @method('PUT')
                                <div class="p-4 space-y-4">
                                    <p id="editLabel" class="text-sm text-gray-600">Amount per Task</p>
                                    <div id="taskAmountsContainer"></div>
                                    <div class="flex items-center justify-between border-t pt-3">
                                        <div class="text-xs text-gray-500">New invoice total</div>
                                        <div class="text-base font-bold"><span id="total-payment-display">0.00</span></div>
                                    </div>
                                </div>
                                <div class="p-4 border-t bg-gray-50 flex justify-end gap-2 sticky bottom-0">
                                    <button type="button" class="px-3 py-2 text-sm rounded border hover:bg-gray-100" onclick="closeEditModal()">Cancel</button>
                                    <button type="submit" class="px-4 py-2 text-sm rounded bg-blue-600 text-white hover:bg-blue-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <x-pagination :data="$invoices" />

                    <!-- ./pagination -->
                </div>
            </div>
        </div>
    </div>
    <script>
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
    </script>
    <script>
        const companyId = "{{ auth()->user()->company_id ?? optional(auth()->user()->branch)->company_id ?? optional(auth()->user()->agent?->branch)->company_id ?? optional(auth()->user()->accountant?->branch)->company_id }}";
        const updateDateUrl = "{{ route('invoice.updateDate',   ['companyId' => 'COMPANY_ID', 'invoiceNumber' => 'INVOICE_NUM']) }}";
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
                labelEl.textContent = 'Amount per Invoice';

                const invoiceNumber = btn.dataset.number;
                const tasks = JSON.parse(btn.closest('tr').dataset.tasks);
                container.innerHTML = '';

                let total = 0;
                let gridWrapper = `<div class="grid grid-cols-1 md:grid-cols-2 gap-3">`;
                for (const t of tasks) {
                    total += parseFloat(t.amount || 0);
                    gridWrapper += `
                        <div class="rounded-lg border shadow-sm p-3 bg-white hover:shadow-md transition">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-semibold">${t.reference}</div>
                                <span class="inline-flex items-center text-xs px-2 py-0.5 rounded-full bg-gray-100">${t.type}</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-600">
                                <div><span class="font-medium">Client:</span> ${t.client}</div>
                                <div><span class="font-medium">Supplier:</span> ${t.supplier}</div>
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs text-gray-600 mb-1">Amount (${t.currency})</label>
                                <input type="number" name="tasks[${t.id}]" value="${t.amount}"
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
                if (totalEl) totalEl.textContent = total.toFixed(2);

                form.action = updateAmountUrl.replace('COMPANY_ID', encodeURIComponent(companyId)).replace('INVOICE_NUM', encodeURIComponent(invoiceNumber));
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
            if (totalEl) totalEl.textContent = total.toFixed(2);
        }
    </script>
</x-app-layout>