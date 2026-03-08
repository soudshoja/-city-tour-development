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

        .service-badge {
            background: hsl(var(--hue) 60% 93%);
            color: hsl(var(--hue) 50% 35%);
            border: 1px solid hsl(var(--hue) 40% 80%);
        }

        @media (prefers-color-scheme: dark) {
            .service-badge {
                background: hsl(var(--hue) 40% 20%);
                color: hsl(var(--hue) 60% 75%);
                border-color: hsl(var(--hue) 30% 35%);
            }
        }
    </style>

    <nav class="flex items-center space-x-2 rtl:space-x-reverse text-sm mb-4 sm:mb-6 overflow-x-auto">
        <a href="{{ route('suppliers.index') }}" class="text-gray-500 hover:text-gray-700 transition whitespace-nowrap">Suppliers</a>
        <span class="text-gray-400">&gt;</span>
        <span class="text-blue-600 font-medium truncate max-w-[200px] sm:max-w-none">{{ $supplier->name }}</span>
    </nav>

    @php
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

        $issuedTasks = $filteredTasks->filter(fn($task) => strtolower($task->status) === 'issued');
        $totalDebit = $issuedTasks->flatMap->journalEntries->sum('debit');
        $totalCredit = $issuedTasks->flatMap->journalEntries->sum('credit');

        $filteredTasks = $filteredTasks->sortByDesc(function($task) use ($dateField) {
            return $task[$dateField] ? \Carbon\Carbon::parse($task[$dateField])->timestamp : 0;
        });

        $firstTask = $supplier->tasks->first();
        $supplierType = $firstTask ? $firstTask->type : null;
    @endphp

    <div class="flex flex-col gap-4">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $supplier->name }}</h2>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <form method="GET" action="{{ route('suppliers.show', ['suppliersId' => $supplier->id]) }}" class="flex flex-wrap items-end gap-4" id="task-filter-form">
                <div class="flex flex-col">
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Filter By</label>
                    <select name="date_field" class="h-9 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 text-sm min-w-[150px] focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                        <option value="created_at" {{ request('date_field') == 'created_at' ? 'selected' : '' }}>Created Date</option>
                        <option value="supplier_pay_date" {{ request('date_field') == 'supplier_pay_date' ? 'selected' : '' }}>Issued Date</option>
                    </select>
                </div>
                <div class="flex flex-col">
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Date Range</label>
                    <input type="text" id="task-date-range" class="h-9 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 text-sm min-w-[220px] focus:ring-2 focus:ring-blue-400 focus:border-blue-400" placeholder="Select date range" autocomplete="off" />
                    <input type="hidden" name="from_date" id="task_from_date" value="{{ request('from_date') }}">
                    <input type="hidden" name="to_date" id="task_to_date" value="{{ request('to_date') }}">
                </div>
                <div class="flex items-end gap-2">
                    <a href="{{ route('suppliers.show', ['suppliersId' => $supplier->id]) }}" class="h-9 px-3 inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm hover:bg-gray-200 dark:hover:bg-gray-600 border border-gray-300 dark:border-gray-600">Clear</a>
                    <button type="submit" class="h-9 px-3 inline-flex items-center rounded-md bg-blue-600 text-white text-sm hover:bg-blue-700">Apply</button>
                    <button type="button" id="export-pdf-btn" class="h-9 px-3 inline-flex items-center rounded-md bg-red-600 text-white text-sm hover:bg-red-700">Export PDF</button>
                    <button type="button" id="export-excel-btn" class="h-9 px-3 inline-flex items-center rounded-md bg-green-600 text-white text-sm hover:bg-green-700">Export Excel</button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <p class="text-xs font-medium text-green-600 dark:text-green-400 uppercase tracking-wide">Total Debit</p>
                <p class="text-xl font-bold text-green-700 dark:text-green-300 mt-1">{{ number_format($totalDebit, 3) }}</p>
            </div>
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <p class="text-xs font-medium text-red-600 dark:text-red-400 uppercase tracking-wide">Total Credit</p>
                <p class="text-xl font-bold text-red-700 dark:text-red-300 mt-1">{{ number_format($totalCredit, 3) }}</p>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <p class="text-xs font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wide">Balance</p>
                <p class="text-xl font-bold text-blue-700 dark:text-blue-300 mt-1">{{ number_format($totalDebit - $totalCredit, 3) }}</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto" style="max-height: 550px; overflow-y: auto;">
                <table class="table-hover whitespace-nowrap w-full">
                    <thead class="sticky top-0 z-10">
                        <tr>
                            <th>Created Date</th>
                            <th>Task Ref</th>
                            @if($supplierType === 'flight')<th>GDS Ref</th>@endif
                            <th>Agent</th>
                            <th>Status</th>
                            <th>Issued Date</th>
                            <th>{{ $supplierType === 'hotel' ? 'Info' : 'Passenger Name' }}</th>
                            <th>Price</th>
                            @if($supplierType === 'flight')
                                <th>Departure</th>
                                <th>Arrival</th>
                            @elseif($supplierType === 'hotel')
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Balance</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $balance = 0;
                            $displayTasks = $filteredTasks->take(20);
                        @endphp
                        @forelse($displayTasks as $task)
                            @php
                                $status = strtolower($task->status);
                                $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                if ($supplierType === 'hotel') {
                                    $debit = $task->journalEntries->first()->debit ?? 0;
                                    $credit = $task->journalEntries->first()->credit ?? 0;
                                    $balance += $debit - $credit;
                                }
                            @endphp
                            <tr class="text-center">
                                <td>{{ $task->created_at ? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d') : '-' }}</td>
                                <td>{{ $task->reference }}</td>
                                @if($supplierType === 'flight')<td>{{ $task->gds_reference ?? '-' }}</td>@endif
                                <td>{{ $task->agent ? $task->agent->name : 'Not Set' }}</td>
                                <td>
                                    <span class="inline-block px-2 py-1 rounded border font-bold text-xs {{ $colorClass }}">
                                        {{ ucfirst($task->status) }}
                                    </span>
                                </td>
                                <td>{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('d-m-Y') : '-' }}</td>
                                @if($supplierType === 'hotel')
                                    <td>
                                        {{ $task->passenger_name ?? '-' }}<br>
                                        <span class="text-gray-500 text-xs">{{ $task->hotelDetails?->hotel->name ?? '-' }}</span><br>
                                        <span class="text-gray-400 text-xs">{{ $task->hotelDetails?->check_in ?? '-' }} to {{ $task->hotelDetails?->check_out ?? '-' }}</span>
                                    </td>
                                @else
                                    <td>{{ $task->passenger_name ?? '-' }}</td>
                                @endif
                                <td>{{ $task->price ?? '-' }}</td>
                                @if($supplierType === 'flight')
                                    <td>
                                        @if($task->type === 'flight' && $task->flightDetails)
                                            <strong>From:</strong> {{ $task->flightDetails->airport_from ?? '-' }}<br>
                                            {{ optional($task->flightDetails->departure_time)->format('d-m-Y H:i') ?? '-' }}
                                        @else - @endif
                                    </td>
                                    <td>
                                        @if($task->type === 'flight' && $task->flightDetails)
                                            <strong>To:</strong> {{ $task->flightDetails->airport_to ?? '-' }}<br>
                                            {{ optional($task->flightDetails->arrival_time)->format('d-m-Y H:i') ?? '-' }}
                                        @else - @endif
                                    </td>
                                @elseif($supplierType === 'hotel')
                                    <td>{{ $debit ?: '-' }}</td>
                                    <td>{{ $credit ?: '-' }}</td>
                                    <td>{{ $balance }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($supplierType === 'flight' || $supplierType === 'hotel') ? 10 : 7 }}" class="text-center text-gray-500 py-8">No entries found for selected dates.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-6 mt-6">
        <div class="bg-white rounded-md shadow-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center">Supplier Details</h2>
                <div class="flex flex-wrap gap-2">
                    @if($supplier->has_flight)
                    <span class="px-2 py-1 text-xs bg-sky-100 text-sky-700 rounded-full border border-sky-300 flex items-center gap-1">
                        <i class="fa-solid fa-plane"></i> Flight
                    </span>
                    @endif
                    @if($supplier->has_hotel)
                    <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-700 rounded-full border border-yellow-300 flex items-center gap-1">
                        <i class="fa-solid fa-bed"></i> Hotel
                    </span>
                    @endif
                    @if($supplier->has_visa)
                    <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full border border-green-300 flex items-center gap-1">
                        <i class="fa-solid fa-passport"></i> Visa
                    </span>
                    @endif
                    @if($supplier->has_insurance)
                    <span class="px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded-full border border-purple-300 flex items-center gap-1">
                        <i class="fa-solid fa-shield-heart"></i> Insurance
                    </span>
                    @endif
                    @if($supplier->has_car)
                    <span class="px-2 py-1 text-xs bg-orange-100 text-orange-700 rounded-full border border-orange-300 flex items-center gap-1">
                        <i class="fa-solid fa-car"></i> Car
                    </span>
                    @endif
                    @if($supplier->has_tour)
                    <span class="px-2 py-1 text-xs bg-pink-100 text-pink-700 rounded-full border border-pink-300 flex items-center gap-1">
                        <i class="fa-solid fa-map-location-dot"></i> Tour
                    </span>
                    @endif
                </div>
            </div>
            <div class="grid md:grid-cols-2 gap-6 text-sm text-gray-700">
                <div class="space-y-2">
                    <p><i class="fa-regular fa-id-badge text-blue-500 w-5 inline-block"></i>
                        <strong>Name:</strong> {{ $supplier->name ?? 'Not Set' }}
                    </p>
                    <p><i class="fa-solid fa-user-tie text-blue-500 w-5 inline-block"></i>
                        <strong>Contact Person:</strong> {{ $supplier->contact_person ?? 'Not Set' }}
                    </p>
                    <p><i class="fa-regular fa-envelope text-blue-500 w-5 inline-block"></i>
                        <strong>Email:</strong> {{ $supplier->email ?? 'Not Set' }}
                    </p>
                    <p><i class="fa-solid fa-phone text-blue-500 w-5 inline-block"></i>
                        <strong>Phone:</strong> {{ $supplier->phone ?? 'Not Set' }}
                    </p>
                    <p><i class="fa-solid fa-map-marker-alt text-blue-500 w-5 inline-block"></i>
                        <strong>Address:</strong> {{ $supplier->address ?? 'Not Set' }}
                    </p>
                    <p><i class="fa-solid fa-city text-blue-500 w-5 inline-block"></i>
                        <strong>City:</strong> {{ $supplier->city ?? 'Not Set' }}
                    </p>
                </div>

                <div class="space-y-2">
                    <p><i class="fa-solid fa-location-dot text-blue-500 w-5 inline-block"></i>
                        <strong>State:</strong> {{ $supplier->state ?? 'Not Set' }}
                    </p>
                    <p><i class="fa-solid fa-mail-bulk text-blue-500 w-5 inline-block"></i>
                        <strong>Postal Code:</strong> {{ $supplier->postal_code ?? 'Not Set' }}
                    </p>
                    <p><i class="fa-solid fa-flag text-blue-500 w-5 inline-block"></i>
                        <strong>Country:</strong> {{ $supplier->country->name ?? 'Not Set' }}
                    </p>
                    <p><i class="fa-solid fa-file-contract text-blue-500 w-5 inline-block"></i>
                        <strong>Payment Terms:</strong> {{ $supplier->payment_terms ?? 'Not Set' }}
                    </p>
                    <p><i class="fa-solid fa-lock text-blue-500 w-5 inline-block"></i>
                        <strong>Auth Type:</strong> {{ ucfirst($supplier->auth_type) }}
                    </p>
                    <p><i class="fa-solid fa-clipboard-check text-blue-500 w-5 inline-block"></i>
                        <strong>Manual Supplier:</strong> {{ $supplier->is_manual ? 'Yes' : 'No' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-coins text-blue-500"></i>
                    Auto Extra Surcharge
                </h2>
            </div>
            @if ($supplierCompany && $supplierCompany->supplierSurcharges->count())
                <div class="overflow-hidden border border-gray-200 rounded-lg divide-y divide-gray-100">
                    @foreach($supplierCompany->supplierSurcharges as $surcharge)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between px-4 py-3 hover:bg-blue-50 transition duration-150 ease-in-out">
                            <div class="flex items-center gap-3 mb-2 sm:mb-0">
                                <span class="inline-flex items-center justify-center bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full w-7 h-7">
                                    {{ strtoupper(substr($surcharge->label, 0, 2)) }}
                                </span>
                                <div>
                                    <p class="text-gray-800 font-semibold">{{ ucwords(str_replace('_', ' ', $surcharge->label)) }}</p>
                                    <div class="flex flex-wrap gap-1 mt-1 text-xs">
                                        <span class="px-2 py-0.5 rounded-full border border-gray-300 bg-gray-50 text-gray-700">
                                            Mode: <strong class="text-blue-600">{{ ucfirst($surcharge->charge_mode) }}</strong>
                                        </span>
                                        @php
                                            $activeStatuses = collect([
                                                'issued' => $surcharge->is_issued,
                                                'refund' => $surcharge->is_refund,
                                                'reissued' => $surcharge->is_reissued,
                                                'void' => $surcharge->is_void,
                                                'confirmed' => $surcharge->is_confirmed,
                                            ])->filter();
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-full border border-gray-300 bg-gray-50 text-gray-700">
                                            Status:
                                            @if($activeStatuses->isNotEmpty())
                                                <strong class="text-green-700">
                                                    {{ $activeStatuses->keys()->map(fn($s)=>ucfirst($s))->implode(', ') }}
                                                </strong>
                                            @else
                                                <strong class="text-gray-400">None</strong>
                                            @endif
                                        </span>
                                    </div>
                                    @if ($surcharge->charge_mode === 'reference' && $surcharge->references->count())
                                        <div class="mt-2 ml-1 text-xs text-gray-600">
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($surcharge->references as $ref)
                                                    <span class="px-2 py-0.5 bg-gray-100 border border-gray-200 rounded-full">
                                                        <strong>{{ $ref->reference }}</strong>
                                                        <span class="text-[10px] text-gray-500 ml-1">
                                                            (
                                                            {{ $ref->charge_behavior === 'single' 
                                                                ? 'Single charge — applied once per reference' 
                                                                : 'Charge applies to all tasks with this reference' 
                                                            }}
                                                            )
                                                        </span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-blue-700 font-semibold text-sm tracking-wide">
                                    {{ number_format($surcharge->amount, 3) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if (Auth()->user()->role_id == \App\Models\Role::COMPANY)
                    <div class="text-sm text-amber-700 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 mt-4">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>If you need to modify or remove an existing surcharge, please contact your system administrator.</span>
                    </div>
                @endif
            @else
                <div class="text-sm text-gray-500 italic">No surcharges added for this supplier</div>
                @if (Auth()->user()->role_id == \App\Models\Role::COMPANY)
                    <div class="text-sm text-amber-700 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 mt-3">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>To request a new surcharge, please contact your system administrator.</span>
                    </div>
                @endif
            @endif
        </div>

        <!-- <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <h2 class="ttext-lg font-semibold text-gray-800">Auto Extra Surcharge</h2>
                <span class="text-xs text-gray-500">Manage additional surcharges applied to supplier tasks</span>
            </div>
            @if ($supplierCompany && $supplierCompany->supplierSurcharges->count())
                <form action="{{ route('suppliers.update.surcharges', $supplierCompany->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div id="surcharge-container" class="divide-y divide-gray-100 rounded-lg border border-gray-200 overflow-hidden bg-gray-50/30">
                        @foreach($supplierCompany->supplierSurcharges as $index => $surcharge)
                            <input type="hidden" name="surcharge_id[]" value="{{ $surcharge->id }}">
                            <div class="flex items-center gap-3 px-4 py-3 bg-white hover:bg-blue-50 transition duration-150 ease-in-out" data-surcharge-id="{{ $surcharge->id }}">
                                <span class="inline-flex items-center justify-center bg-blue-100 text-blue-700 text-xs font-bold w-7 h-7 rounded-full">
                                    {{ strtoupper(substr($surcharge->label, 0, 2)) }}
                                </span>
                                <input type="text" name="surcharge_label[]" value="{{ $surcharge->label }}" 
                                    class="flex-1 border-gray-300 focus:border-blue-400 focus:ring focus:ring-blue-200 text-sm rounded-md px-3 py-1.5"
                                    placeholder="Enter surcharge name" />
                                <input type="number" step="0.001" name="surcharge_amount[]" value="{{ $surcharge->amount }}" 
                                    class="w-28 border-gray-300 focus:border-blue-400 focus:ring focus:ring-blue-200 text-sm rounded-md px-2 py-1.5 text-right font-medium text-blue-700" 
                                    placeholder="0.000" />
                                <button type="button" class="text-gray-400 hover:text-red-500" onclick="removeSurchargeRow(this)" title="Remove">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-between items-center mt-5">
                        <p class="text-xs text-gray-500 italic">
                            *Updating surcharges will automatically update all non-invoiced related tasks.
                        </p>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="addSurchargeRow()" class="bg-blue-100 text-blue-700 hover:bg-blue-200 font-medium text-xs px-3 py-1.5 rounded-lg transition">
                                + Add Surcharge
                            </button>
                            <input type="hidden" id="deleted_surcharges" name="deleted_surcharges" value="">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm px-4 py-2 rounded-lg shadow-sm transition">
                                <i class="fa-solid fa-save mr-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            @else
                <div class="text-sm text-gray-500 italic">
                    No surcharges added for this supplier
                </div>
            @endif
        </div> -->

        <div class="bg-white rounded-md shadow-md p-5">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Supplier Policy</h2>
            @include('suppliers.partials.add_procedure')
            @include('suppliers.partials.list_procedure', ['companyId' => $companyId, 'supplierCompany' => $supplierCompany])
        </div>
    </div>
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

</x-app-layout>