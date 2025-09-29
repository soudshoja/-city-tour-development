<?php

use Barryvdh\DomPDF\Facade\Pdf;
?>
<x-app-layout>
    <!-- Add this in your <head> or before the closing </body> tag -->
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

    <!-- Breadcrumb -->
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
        <!-- Supplier Header -->
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
                <!-- <button type="button" class="flex h-9 w-9 items-center justify-between rounded-md bg-black text-white hover:opacity-80 ltr:ml-auto rtl:mr-auto">
                    <svg class="m-auto h-6 w-6" viewBox="0 0 24 24" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M12 8.25C9.92894 8.25 8.25 9.92893 8.25 12C8.25 14.0711 9.92894 15.75 12 15.75C14.0711 15.75 15.75 14.0711 15.75 12C15.75 9.92893 14.0711 8.25 12 8.25ZM9.75 12C9.75 10.7574 10.7574 9.75 12 9.75C13.2426 9.75 14.25 10.7574 14.25 12C14.25 13.2426 13.2426 14.25 12 14.25C10.7574 14.25 9.75 13.2426 9.75 12Z"
                            fill="#F5F5F5" />
                        <path fill-rule="evenodd" clip-rule="evenodd"
                            d="M11.9747 1.25C11.5303 1.24999 11.1592 1.24999 10.8546 1.27077C10.5375 1.29241 10.238 1.33905 9.94761 1.45933C9.27379 1.73844 8.73843 2.27379 8.45932 2.94762C8.31402 3.29842 8.27467 3.66812 8.25964 4.06996C8.24756 4.39299 8.08454 4.66251 7.84395 4.80141C7.60337 4.94031 7.28845 4.94673 7.00266 4.79568C6.64714 4.60777 6.30729 4.45699 5.93083 4.40743C5.20773 4.31223 4.47642 4.50819 3.89779 4.95219C3.64843 5.14353 3.45827 5.3796 3.28099 5.6434C3.11068 5.89681 2.92517 6.21815 2.70294 6.60307L2.67769 6.64681C2.45545 7.03172 2.26993 7.35304 2.13562 7.62723C1.99581 7.91267 1.88644 8.19539 1.84541 8.50701C1.75021 9.23012 1.94617 9.96142 2.39016 10.5401C2.62128 10.8412 2.92173 11.0602 3.26217 11.2741C3.53595 11.4461 3.68788 11.7221 3.68786 12C3.68785 12.2778 3.53592 12.5538 3.26217 12.7258C2.92169 12.9397 2.62121 13.1587 2.39007 13.4599C1.94607 14.0385 1.75012 14.7698 1.84531 15.4929C1.88634 15.8045 1.99571 16.0873 2.13552 16.3727C2.26983 16.6469 2.45535 16.9682 2.67758 17.3531L2.70284 17.3969C2.92507 17.7818 3.11058 18.1031 3.28089 18.3565C3.45817 18.6203 3.64833 18.8564 3.89769 19.0477C4.47632 19.4917 5.20763 19.6877 5.93073 19.5925C6.30717 19.5429 6.647 19.3922 7.0025 19.2043C7.28833 19.0532 7.60329 19.0596 7.8439 19.1986C8.08452 19.3375 8.24756 19.607 8.25964 19.9301C8.27467 20.3319 8.31403 20.7016 8.45932 21.0524C8.73843 21.7262 9.27379 22.2616 9.94761 22.5407C10.238 22.661 10.5375 22.7076 10.8546 22.7292C11.1592 22.75 11.5303 22.75 11.9747 22.75H12.0252C12.4697 22.75 12.8407 22.75 13.1454 22.7292C13.4625 22.7076 13.762 22.661 14.0524 22.5407C14.7262 22.2616 15.2616 21.7262 15.5407 21.0524C15.686 20.7016 15.7253 20.3319 15.7403 19.93C15.7524 19.607 15.9154 19.3375 16.156 19.1985C16.3966 19.0596 16.7116 19.0532 16.9974 19.2042C17.3529 19.3921 17.6927 19.5429 18.0692 19.5924C18.7923 19.6876 19.5236 19.4917 20.1022 19.0477C20.3516 18.8563 20.5417 18.6203 20.719 18.3565C20.8893 18.1031 21.0748 17.7818 21.297 17.3969L21.3223 17.3531C21.5445 16.9682 21.7301 16.6468 21.8644 16.3726C22.0042 16.0872 22.1135 15.8045 22.1546 15.4929C22.2498 14.7697 22.0538 14.0384 21.6098 13.4598C21.3787 13.1586 21.0782 12.9397 20.7378 12.7258C20.464 12.5538 20.3121 12.2778 20.3121 11.9999C20.3121 11.7221 20.464 11.4462 20.7377 11.2742C21.0783 11.0603 21.3788 10.8414 21.6099 10.5401C22.0539 9.96149 22.2499 9.23019 22.1547 8.50708C22.1136 8.19546 22.0043 7.91274 21.8645 7.6273C21.7302 7.35313 21.5447 7.03183 21.3224 6.64695L21.2972 6.60318C21.0749 6.21825 20.8894 5.89688 20.7191 5.64347C20.5418 5.37967 20.3517 5.1436 20.1023 4.95225C19.5237 4.50826 18.7924 4.3123 18.0692 4.4075C17.6928 4.45706 17.353 4.60782 16.9975 4.79572C16.7117 4.94679 16.3967 4.94036 16.1561 4.80144C15.9155 4.66253 15.7524 4.39297 15.7403 4.06991C15.7253 3.66808 15.686 3.2984 15.5407 2.94762C15.2616 2.27379 14.7262 1.73844 14.0524 1.45933C13.762 1.33905 13.4625 1.29241 13.1454 1.27077C12.8407 1.24999 12.4697 1.24999 12.0252 1.25H11.9747ZM10.5216 2.84515C10.5988 2.81319 10.716 2.78372 10.9567 2.76729C11.2042 2.75041 11.5238 2.75 12 2.75C12.4762 2.75 12.7958 2.75041 13.0432 2.76729C13.284 2.78372 13.4012 2.81319 13.4783 2.84515C13.7846 2.97202 14.028 3.21536 14.1548 3.52165C14.1949 3.61826 14.228 3.76887 14.2414 4.12597C14.271 4.91835 14.68 5.68129 15.4061 6.10048C16.1321 6.51968 16.9974 6.4924 17.6984 6.12188C18.0143 5.9549 18.1614 5.90832 18.265 5.89467C18.5937 5.8514 18.9261 5.94047 19.1891 6.14228C19.2554 6.19312 19.3395 6.27989 19.4741 6.48016C19.6125 6.68603 19.7726 6.9626 20.0107 7.375C20.2488 7.78741 20.4083 8.06438 20.5174 8.28713C20.6235 8.50382 20.6566 8.62007 20.6675 8.70287C20.7108 9.03155 20.6217 9.36397 20.4199 9.62698C20.3562 9.70995 20.2424 9.81399 19.9397 10.0041C19.2684 10.426 18.8122 11.1616 18.8121 11.9999C18.8121 12.8383 19.2683 13.574 19.9397 13.9959C20.2423 14.186 20.3561 14.29 20.4198 14.373C20.6216 14.636 20.7107 14.9684 20.6674 15.2971C20.6565 15.3799 20.6234 15.4961 20.5173 15.7128C20.4082 15.9355 20.2487 16.2125 20.0106 16.6249C19.7725 17.0373 19.6124 17.3139 19.474 17.5198C19.3394 17.72 19.2553 17.8068 19.189 17.8576C18.926 18.0595 18.5936 18.1485 18.2649 18.1053C18.1613 18.0916 18.0142 18.045 17.6983 17.8781C16.9973 17.5075 16.132 17.4803 15.4059 17.8995C14.68 18.3187 14.271 19.0816 14.2414 19.874C14.228 20.2311 14.1949 20.3817 14.1548 20.4784C14.028 20.7846 13.7846 21.028 13.4783 21.1549C13.4012 21.1868 13.284 21.2163 13.0432 21.2327C12.7958 21.2496 12.4762 21.25 12 21.25C11.5238 21.25 11.2042 21.2496 10.9567 21.2327C10.716 21.2163 10.5988 21.1868 10.5216 21.1549C10.2154 21.028 9.97201 20.7846 9.84514 20.4784C9.80512 20.3817 9.77195 20.2311 9.75859 19.874C9.72896 19.0817 9.31997 18.3187 8.5939 17.8995C7.86784 17.4803 7.00262 17.5076 6.30158 17.8781C5.98565 18.0451 5.83863 18.0917 5.73495 18.1053C5.40626 18.1486 5.07385 18.0595 4.81084 17.8577C4.74458 17.8069 4.66045 17.7201 4.52586 17.5198C4.38751 17.314 4.22736 17.0374 3.98926 16.625C3.75115 16.2126 3.59171 15.9356 3.4826 15.7129C3.37646 15.4962 3.34338 15.3799 3.33248 15.2971C3.28921 14.9684 3.37828 14.636 3.5801 14.373C3.64376 14.2901 3.75761 14.186 4.0602 13.9959C4.73158 13.5741 5.18782 12.8384 5.18786 12.0001C5.18791 11.1616 4.73165 10.4259 4.06021 10.004C3.75769 9.81389 3.64385 9.70987 3.58019 9.62691C3.37838 9.3639 3.28931 9.03149 3.33258 8.7028C3.34348 8.62001 3.37656 8.50375 3.4827 8.28707C3.59181 8.06431 3.75125 7.78734 3.98935 7.37493C4.22746 6.96253 4.3876 6.68596 4.52596 6.48009C4.66055 6.27983 4.74468 6.19305 4.81093 6.14222C5.07395 5.9404 5.40636 5.85133 5.73504 5.8946C5.83873 5.90825 5.98576 5.95483 6.30173 6.12184C7.00273 6.49235 7.86791 6.51962 8.59394 6.10045C9.31998 5.68128 9.72896 4.91837 9.75859 4.12602C9.77195 3.76889 9.80512 3.61827 9.84514 3.52165C9.97201 3.21536 10.2154 2.97202 10.5216 2.84515Z"
                            fill="#F5F5F5" />
                    </svg>
                </button> -->
            </div>

            <!-- Debit/Credit Filter & Summary -->
            <!-- <div class="flex flex-col md:flex-row gap-2 mb-4 justify-end">
                    <div class="flex gap-2 items-center">
                        <label for="date-range" class="text-white font-semibold flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M8 7V3M16 7V3M4 11H20M5 19H19M4 5H20" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Issued Date Range:
                        </label>
                        <input type="text" id="date-range" class="rounded p-1" style="width: 300px;" placeholder="Date" />
                        <button id="filter-btn" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 6h18v10a1 1 0 01-1 1H4a1 1 0 01-1-1V10z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Filter
                        </button>
                        <button id="clear-btn" class="bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500 flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Clear
                        </button>
                        <span id="loading-spinner" class="ml-2 hidden">
                            <svg class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                            </svg>
                        </span>
                    </div>
                </div> -->
            <!-- <div class="grid grid-cols-2 mb-4">
                    <div class="text-center bg-gradient-to-r from-green-400 to-green-800 p-2 rounded-md text-white font-semibold">
                        <div>Debit:</div>
                        <div id="total-debit" class="text-2xl font-bold">0.00</div>
                    </div>
                    <div class="text-center bg-gradient-to-r from-red-400 to-red-800 p-2 rounded-md text-white font-semibold">
                        <div>Credit:</div>
                        <div id="total-credit" class="text-2xl font-bold">0.00</div>
                    </div>
                </div> -->
            <!-- Filter Section (same style as invoice list) -->
            <!-- <div class="flex justify-end mb-2">
                <button id="customize-columns-btn" class="px-2 py-1 rounded bg-gray-700 text-white text-xs hover:bg-gray-800 border border-gray-700">
                    Customize Columns
                </button>
                <div id="columns-dropdown" class="absolute bg-white border rounded shadow-md p-2 mt-8 hidden z-20">
                    <label><input type="checkbox" class="column-toggle" data-col="0" checked> Created Date</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="1" checked> Task Ref</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="2" checked> GDS Ref</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="3" checked> Agent</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="4" checked> Status</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="5" checked> Issued Date</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="6" checked> Passenger Name</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="7" checked> Net Price</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="8" checked> Departure</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="9" checked> Arrival</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="10" checked> Check-in</label><br>
                    <label><input type="checkbox" class="column-toggle" data-col="11" checked> Check-out</label>
                </div>
            </div> -->
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

                <!-- Ledger Table -->
                <div id="debit-credit" class="bg-white rounded-md shadow-md w-full overflow-x-auto">
                    @php
                    // Determine supplier type based on tasks (assuming all tasks are same type for this supplier)
                    $firstTask = $supplier->tasks->first();
                    $filteredTasks = $filteredTasks->take(20);
                    $supplierType = $firstTask ? $firstTask->type : null;
                    @endphp

                    <div class="min-w-max">
                        @if($supplierType === 'flight')
                        <div class="grid grid-cols-10 font-bold bg-gray-100 p-2 text-center rounded-t border-b border-gray-300 sticky top-0 z-10">
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
        // Toggle dropdown
        document.getElementById('customize-columns-btn').addEventListener('click', function(e) {
            const dropdown = document.getElementById('columns-dropdown');
            dropdown.classList.toggle('hidden');
            dropdown.style.left = (e.target.getBoundingClientRect().left) + 'px';
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('columns-dropdown');
            if (!dropdown.contains(e.target) && e.target.id !== 'customize-columns-btn') {
                dropdown.classList.add('hidden');
            }
        });

        // Toggle columns (now for 12 columns)
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

            // Change form action to PDF export route
            form.action = "{{ route('suppliers.suppliers.export.pdf', ['suppliersId' => $supplier->id]) }}";
            form.method = "GET";
            form.submit();

            // Restore form action after submit (optional)
            setTimeout(() => {
                form.action = "{{ route('suppliers.show', ['suppliersId' => $supplier->id]) }}";
            }, 1000);
        });
        document.getElementById('export-excel-btn').addEventListener('click', function() {
            const form = document.getElementById('task-filter-form');
            const range = document.getElementById('task-date-range').value.split(' to ');
            document.getElementById('task_from_date').value = range[0] ? range[0].trim() : '';
            document.getElementById('task_to_date').value = range[1] ? range[1].trim() : range[0];

            // Change form action to Excel export route
            form.action = "{{ route('suppliers.suppliers.export.excel', ['suppliersId' => $supplier->id]) }}";
            form.method = "GET";
            form.submit();

            // Restore form action after submit (optional)
            setTimeout(() => {
                form.action = "{{ route('suppliers.show', ['suppliersId' => $supplier->id]) }}";
            }, 1000);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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