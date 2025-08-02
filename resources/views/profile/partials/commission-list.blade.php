@php
    $typeId = optional($user->agent)->type_id;
    $showProfit = in_array($typeId, [1, 3, 4]);
    $showCommission = in_array($typeId, [2, 3, 4]);

    $title = match($typeId) {
        1 => 'Profit',
        2 => 'Commission',
        3,4 => 'Commission & Profit',
        default => 'Commission & Profit'
    };
    $currentTab = request('tab', 'Commission');
@endphp
<section class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-md text-gray-900 dark:text-gray-200">
    <header class="flex flex-col md:flex-row justify-between mb-4 gap-4">
        <div class="flex-1">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $user->name }}’s {{ $title }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                Review your earned {{ strtolower($title) }} along with details of the associated tasks.
            </p>

            <form method="GET" action="{{ route('profile.edit') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="Commission">
                <label for="month" class="sr-only">Month</label>
                <div class="flex items-center gap-1">
                <input type="month" name="month" id="month" value="{{ request('month', now()->format('Y-m')) }}"
                    class="bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded px-2 py-1 text-sm text-gray-900 dark:text-white">
                <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                    Filter
                </button>
                @if(request()->has('month'))
                    <a href="{{ route('profile.edit', ['tab' => 'Commission']) }}"
                    class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded text-sm">
                    Clear
                    </a>
                @endif
                </div>
            </form>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 items-start sm:items-center">
            @if($showCommission)
                <div class="bg-green-100 text-green-800 font-bold px-4 py-2 rounded shadow text-sm">
                Total Commission: {{ $totalCommission }} KWD
                </div>
            @endif
            @if($showProfit)
                <div class="bg-blue-100 text-blue-800 font-bold px-4 py-2 rounded shadow text-sm">
                Total Profit: {{ $totalProfit }} KWD
                </div>
            @endif
        </div>
    </header>

    @if($commissions->isEmpty())
        <p class="text-gray-500 text-sm">No {{ lcfirst($title) }} records found.</p>
    @else
        <div class="overflow-x-auto" x-data="{ openRow: null }">
            <table class="min-w-full text-sm border border-gray-300 dark:border-gray-700">
                <thead class="bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    <tr>
                        <th class="py-3 px-4 border-b text-center">Task Reference</th>
                        @if($user->role_id == 4 && optional($user->agent)->type_id == 2 || optional($user->agent)->type_id == 3)
                            <th class="py-3 px-4 border-b text-center">Commission (KWD)</th>
                        @endif
                        @if($user->role_id == 4 && optional($user->agent)->type_id != 2)
                            <th class="py-3 px-4 border-b text-center">Profit (KWD)</th>
                        @endif
                        <th class="py-3 px-4 border-b text-center">Description</th>
                        <th class="py-3 px-4 border-b text-center">Transaction Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($commissions as $index => $commission)
                        @php
                            $entry = \App\Models\JournalEntry::with('invoiceDetail')->find($commission['entry_id']);
                            $invoice = $entry->invoice;
                            $invoiceDetail = $entry->invoiceDetail;
                            $task = \App\Models\Task::with(['flightDetails', 'hotelDetails', 'invoiceDetail.invoice'])->find($invoiceDetail->task_id);
                        @endphp
                        <tr class="cursor-pointer text-center"
                            :class="openRow === {{ $index }} ? 'bg-blue-50 hover:bg-gray-50 dark:bg-blue-900 hover:dark:bg-blue-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200'" @click="openRow === {{ $index }} ? openRow = null : openRow = {{ $index }}">
                            <td class="py-3 px-4 border-b">{{ $task->reference ?? '-' }}</td>
                            @if($user->role_id == 4 && optional($user->agent)->type_id == 2 || optional($user->agent)->type_id == 3)
                                <td class="py-3 px-4 border-b text-green-700 font-semibold">
                                    {{ number_format($commission['credit'], 2) }}
                                </td>
                            @endif
                            @if($user->role_id == 4 && optional($user->agent)->type_id != 2)
                                <td class="py-3 px-4 border-b text-blue-700 font-semibold">
                                    {{ number_format($invoiceDetail->markup_price, 2) }}
                                </td>
                            @endif
                            @php
                                if ($task) {
                                    if ($showCommission && ! $showProfit) {
                                        $description = "Commission on Task {$task->reference}";
                                    } elseif ($showProfit && ! $showCommission) {
                                        $description = "Profit on Task {$task->reference}";
                                    } else {
                                        $description = "Commission & Profit for Task {$task->reference}";
                                    }
                                } else {
                                    $description = $entry->name ?? '-';
                                }
                            @endphp
                            <td class="py-3 px-4 border-b">{{ $description }}</td>
                            <td class="py-3 px-4 border-b">
                                {{ \Carbon\Carbon::parse($entry->transaction_date)->format('d-m-Y H:i:s') }}
                            </td>
                        </tr>
                        <tr x-show="openRow === {{ $index }}" x-cloak>
                            <td colspan="4" class="bg-sky-50 dark:bg-gray-900 px-6 py-4 border-b dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 rounded-b-lg shadow-inner">
                                @if($task)
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <p class="text-base font-semibold text-gray-800">Transaction #{{ $entry->transaction_id }}</p>
                                    </div>
                                    <hr class="my-2">
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-1">
                                        <div><strong>Branch:</strong> {{ $entry->branch->name ?? '-' }}</div>
                                        <div><strong>Issued:</strong> {{ \Carbon\Carbon::parse($task->created_at)->format('d-m-Y H:i:s') }}</div>
                                        <div><strong>Invoice:</strong><a href="{{ url('/invoice/' . $invoice->invoice_number) }}" class="text-blue-500 hover:underline"> {{ $invoice->invoice_number ?? '-' }}</a></div>
                                        <div><strong>Client:</strong> {{ $task->client_name }}</div>
                                        <div>
                                            <strong>Payment Type:</strong>
                                            @if($invoice->is_client_credit == 1)
                                                Client Credit
                                            @elseif($invoice->payment_type === 'full')
                                                Full Payment
                                            @elseif($invoice->payment_type === 'partial')
                                                Partial Payment
                                            @elseif($invoice->payment_type === 'split')
                                                Split Payment
                                            @else
                                                Unknown
                                            @endif
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-1 text-sm bg-sky-100 dark:bg-gray-800 rounded-lg p-3 shadow border mt-4">
                                        <div><strong>Net Price:</strong> {{ number_format($invoiceDetail->task_price, 2) }} KWD</div>
                                        <div><strong>Cost Price:</strong> {{ number_format($invoiceDetail->supplier_price, 2) }} KWD</div>
                                        @if($user->role_id == 4 && optional($user->agent)->type_id != 2)
                                        <div><strong>Profit Margin:</strong> {{ number_format($invoiceDetail->markup_price, 2) }} KWD</div>
                                        @endif
                                    </div>

                                    @if($task->flightDetails)
                                    <div class="mt-4 p-4 rounded-lg bg-sky-100 dark:bg-gray-800 shadow-inner">
                                        <!-- <div class="text-sm text-gray-700 mb-2 font-semibold">Flight Details</div> -->
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-1 text-sm">
                                            <div><strong>Flight No:</strong> {{ $task->flightDetails->flight_number }}</div>
                                            <div><strong>From:</strong> {{ $task->flightDetails->airport_from ?? '-' }}</div>
                                            <div><strong>To:</strong> {{ $task->flightDetails->airport_to ?? '-' }}</div>
                                            <div><strong>Created By:</strong> {{ $task->created_by ?? 'Not Set' }}</div>
                                            <div><strong>Airline Reference:</strong> {{ $task->airline_reference ?? 'Not Available' }}</div>
                                            <div><strong>Ticket Number:</strong> {{ $task->ticket_number ?? '-' }}</div>
                                            <div><strong>Departure Time:</strong> {{ \Carbon\Carbon::parse($task->flightDetails->departure_time)->format('d-m-Y H:i:s') }}</div>
                                            <div><strong>Arrival Time:</strong> {{ \Carbon\Carbon::parse($task->flightDetails->arrival_time)->format('d-m-Y H:i:s') }}</div>
                                            <div><strong>Passenger:</strong> {{ $task->passenger_name }}</div>
                                        </div>
                                    </div>
                                    @endif
                                    @if($task->hotelDetails)
                                    <div class="mt-4 p-4 rounded-lg bg-sky-100 dark:bg-gray-800 shadow-inner">
                                        <!-- <div class="text-sm text-gray-700 mb-2 font-semibold">Hotel Details</div> -->
                                        <div class="grid grid-cols-2 gap-1 text-sm">
                                            <div><strong>Hotel Name:</strong> {{ $task->hotelDetails->hotel->name ?? '-' }}</div>
                                            <div><strong>Passenger:</strong> {{ $task->passenger_name }}</div>
                                            <div><strong>Check-in:</strong> {{ \Carbon\Carbon::parse($task->hotelDetails->check_in)->format('d-m-Y') ?? '-' }}</div>
                                            <div><strong>Check-out:</strong> {{ \Carbon\Carbon::parse($task->hotelDetails->check_out)->format('d-m-Y') ?? '-' }}</div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                @else
                                    <p class="text-gray-500">Task not found.</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-6 px-6">
                {{ $commissions->appends(['tab' => 'Commission'])->links() }}
            </div>
        </div>
    @endif
</section>