<x-app-layout>
    <h1 class="text-center mb-2 font-semibold text-xl">Accounts Payable & Receivable Report</h1>

    <div class="flex justify-center items-center bg-gray-100">
        <form method="GET" action="{{ route('reports.new-report') }}"
            class="p-6 my-2 w-full md:w-full lg:w-full flex flex-col gap-4 bg-white rounded shadow">

            <!-- Input Fields Section -->
            <div class="grid grid-cols-12 gap-4">
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="start_date" class="font-medium text-sm mb-1">Start Date:</label>
                    <input type="date" name="start_date" id="start_date"
                        value="{{ $startDate ? date('Y-m-d', strtotime($startDate)) : '' }}"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="end_date" class="font-medium text-sm mb-1">End Date:</label>
                    <input type="date" name="end_date" id="end_date"
                        value="{{ $endDate ? date('Y-m-d', strtotime($endDate)) : '' }}"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="branch_id" class="font-medium text-sm mb-1">Filter by Branch:</label>
                    <select name="branch_id" id="branch_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ $branchId == $branch->id ? 'selected' : '' }}>
                                {{ ucfirst($branch->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="account_id" class="font-medium text-sm mb-1">Filter by Account:</label>
                    <select name="account_id" id="account_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="all" {{ $supplierId == 'all' ? 'selected' : '' }}>All Accounts</option>
                        @foreach ($allAccounts as $account)
                            <option value="{{ $account->id }}" {{ $accountId == $account->id ? 'selected' : '' }}>
                                {{ ucfirst($account->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @php
                    $selectedType = request()->input('type_id', 'All');
                @endphp
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="type_id" class="font-medium text-sm mb-1">Filter by Type:</label>
                    <select name="type_id" id="type_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="all" {{ $selectedType == 'all' ? 'selected' : '' }}>All Payable & Receivable
                        </option>
                        <option value="payable" {{ $selectedType == 'payable' ? 'selected' : '' }}>Payable only
                        </option>
                        <option value="receivable" {{ $selectedType == 'receivable' ? 'selected' : '' }}>Receivable
                            only</option>
                    </select>
                </div>
            </div>

            <!-- Button Section (Centered) -->
            <div class="flex justify-center">
                <button id="submit-account-filter" type="submit"
                    class="w-6/12 md:w-6/12 lg:w-4/12 flex justify-center flex items-center px-2 py-2 bg-black text-white rounded-md hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none">
                    Filter
                </button>
            </div>
            <div class="flex gap-2">
                <div class="border w-full p-2 rounded">

                    @if ($startDate && $endDate)
                        <p>Report for the period: {{ $startDate }} to {{ $endDate }}</p>
                    @elseif (!$startDate && !$endDate)
                        <p>Showing all transactions (no date filter applied).</p>
                    @endif

                    @if ($branchId)
                        <p>Filtered by Branch: {{ \App\Models\Branch::find($branchId)->name ?? 'Unknown Branch' }}</p>
                    @endif
                    @if ($supplierId)
                        <p>Filtered by Supplier:
                            {{ \App\Models\Supplier::find($supplierId)->name ?? 'Unknown Supplier' }}
                        </p>
                    @endif
                    @if ($selectedType)
                        <p>Filtered by Type: {{ ucfirst($selectedType) }}</p>
                    @endif


                </div>
            </div>

        </form>
    </div>


    <div class="p-4 bg-white rounded shadow">
        {{-- <header class="p-3 flex flex-col gap-2">
        </header> --}}
        <div id="account_payable"
            class="{{ $selectedType == 'payable' ? '' : ($selectedType == 'receivable' ? 'hidden' : '') }} p-3 mt-4 border shadow">
            <h2 class="font-bold">Accounts Payable Transactions <span class="font-normal">(Account ID:
                    {{ $accountPayable->code ?? 'CI12300' }})</span></h2>

            @php
                $totalDebitPayable = 0;
                $totalCreditPayable = 0;
                $totalAllPayable = 0;
                $totalDebitReceivable = 0;
                $totalCreditReceivable = 0;
                $totalAllReceivable = 0;
            @endphp

            @if ($payableTransactions->isNotEmpty())
                <table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">
                    <thead>
                        <tr>
                            <th style="width:220px; style=" padding: 8px; border: 1px solid #ddd;">Transaction Date</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Description</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Debit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Credit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payableTransactions as $transaction)
                            @php
                                $totalDebitPayable += $transaction->debit;
                                $totalCreditPayable += $transaction->credit;
                                $totalAllPayable = $totalDebitPayable - $totalCreditPayable;
                            @endphp
                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ $transaction->transaction_date }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <p><strong>{{ $transaction->description }}</strong>
                                    </p>
                                    @if (!empty($transaction->task?->additional_info))
                                        <p>Additional info: {{ $transaction->task->additional_info }}</p>
                                    @endif

                                    @if (!empty($transaction->task?->reference))
                                        <p>Ref: {{ $transaction->task->reference }}</p>
                                    @endif

                                    @if (!empty($transaction->task?->client_name))
                                        <p>Client: {{ $transaction->task->client_name }}</p>
                                    @endif

                                    @if (!empty($transaction->task?->flightDetails?->departure_time))
                                        <p>Flight details:
                                            {{ \Carbon\Carbon::parse($transaction->task->flightDetails->departure_time)->format('Y-m-d H:i') }}
                                            -
                                            {{ \Carbon\Carbon::parse($transaction->task->flightDetails->arrival_time)->format('Y-m-d H:i') }}
                                        </p>
                                    @endif

                                    @php
                                        $hotelDetails = $transaction->task?->hotelDetails;
                                        $roomDetails =
                                            $hotelDetails && $hotelDetails->room_details
                                                ? json_decode($hotelDetails->room_details, true)
                                                : null;
                                    @endphp

                                    @if (!empty($roomDetails))
                                        <p><strong>Hotel details:</strong></p>
                                        <ul>
                                            <li>Name: {{ $roomDetails['name'] ?? 'n/a' }}</li>
                                            <li>Info: {{ $roomDetails['info'] ?? 'n/a' }}</li>
                                            <li>Type: {{ $roomDetails['type'] ?? 'n/a' }}</li>
                                            <li>Extra Services:
                                                @if (
                                                    !empty($roomDetails['extraServices']) &&
                                                        is_array($roomDetails['extraServices']) &&
                                                        count($roomDetails['extraServices']) > 0)
                                                    {{ implode(', ', $roomDetails['extraServices']) }}
                                                @else
                                                    n/a
                                                @endif
                                            </li>
                                            <li>Check-in: {{ $hotelDetails->check_in ?? 'n/a' }}</li>
                                            <li>Check-out: {{ $hotelDetails->check_out ?? 'n/a' }}
                                            </li>
                                        </ul>
                                    @endif

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->debit, 2) }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->credit, 2) }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($totalAllPayable, 2) }}
                                    {{-- {{ number_format($transaction->balance, 2) }} --}}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-red-500">No Accounts Payable transactions found for the selected period.</p>
            @endif
        </div>
        <div id="account_receivable"
            class="{{ $selectedType == 'receivable' ? '' : ($selectedType == 'payable' ? 'hidden' : '') }} p-3 mt-4 border shadow">
            <h2 class="font-bold">Accounts Receivable Transactions <span class="font-normal">(Account ID:
                    {{ $receivableAccount->code ?? 'CI12301' }})</span></h2>
            @if ($receivableTransactions->isNotEmpty())
                <table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">
                    <thead>
                        <tr>
                            <th style="width:220px; style=" padding: 8px; border: 1px solid #ddd;">Transaction Date</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Description</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Debit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Credit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receivableTransactions as $transaction)
                            @php
                                $totalDebitReceivable += $transaction->debit;
                                $totalCreditReceivable += $transaction->credit;
                                $totalAllReceivable = $totalDebitReceivable - $totalCreditReceivable;
                            @endphp

                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ $transaction->transaction_date }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <p>{{ $transaction->description }}
                                    </p>
                                    @if ($transaction->invoice && !empty($transaction->invoice->invoice_number))
                                        <p>
                                            <small>Ref:
                                                {{ $transaction->type_reference_id ?? $transaction->invoice->invoice_number }}
                                                <a target="_blank"
                                                    href="{{ route('invoice.show', ['invoiceNumber' => $transaction->invoice->invoice_number]) }}"
                                                    class="text-blue-500 ml-0">
                                                    🔍
                                                </a>
                                            </small>
                                        </p>
                                    @endif

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->debit, 2) }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->credit, 2) }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($totalAllReceivable, 2) }}
                                    {{-- {{ number_format($transaction->balance, 2) }} --}}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-red-500">No Accounts Receivable transactions found for the selected period.</p>
            @endif
        </div>

        <div class="p-3 mt-4 border shadow">
            <h2 class="flex justify-start">
                <h2 class="font-bold">Outstanding Balances</h2>
            </h2>
            <div class="flex gap-2">
                <div class="border w-full p-2 rounded">
                    <h3>Accounts Payable</h3>
                    <p><strong>Outstanding Balance: {{ number_format($totalAllPayable, 2) }}</strong></p>
                </div>
                <div class="border w-full p-2 rounded">
                    <h3>Accounts Receivable</h3>
                    <p><strong>Outstanding Balance: {{ number_format($totalAllReceivable, 2) }}</strong></p>
                </div>
            </div>
        </div>
    </div>
    <script>
        let filterType = document.getElementById('type_id');
        let filterButton = document.getElementById('submit-account-filter');

        filterType.addEventListener('change', (event) => {
            type_id = event.target.value;

            let accountSelect = document.getElementById('account_id');
            accountSelect.innerHTML = '<option value="all">Loading...</option>'; // Show loading indication

            // Disable the filter button while fetching data
            filterButton.innerHTML = 'Loading...';
            filterButton.classList.add('cursor-not-allowed');
            filterButton.disabled = true;

            fetch(`{{ route('reports.account-list') }}?type_id=${type_id}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                })
                .then(response => response.json())
                .then(data => {

                    accountSelect.innerHTML = '<option value="all">All Account</option>'; // Reset options

                    // Convert the received data into a select option
                    let option = document.createElement('option');

                    for (let i = 0; i < data.length; i++) {
                        option = document.createElement('option');
                        option.value = data[i].id;
                        option.textContent = data[i].name;
                        accountSelect.appendChild(option);
                    }

                    // Re-enable the filter button after data is loaded
                    filterButton.disabled = false;
                })
                .catch(error => {

                    accountSelect.innerHTML =
                        '<option value="all">Error loading accounts</option>'; // Show error message

                    // Re-enable the filter button in case of an error
                    filterButton.disabled = false;
                })
                .finally(() => {
                    // Reset the button text and remove the loading class
                    filterButton.innerHTML = 'Filter';
                    filterButton.classList.remove('cursor-not-allowed');
                });
        });
    </script>
</x-app-layout>
