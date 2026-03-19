<x-app-layout>
    <h1 class="text-center mb-2 font-semibold text-xl">{{ __('general.paid_account_receivable_report') }}</h1>

    <div class="flex justify-center items-center bg-gray-100">
        <form method="GET" action="{{ route('reports.paid-report') }}"
            class="p-6 my-2 w-full md:w-full lg:w-full flex flex-col gap-4 bg-white rounded shadow">

            <!-- Input Fields Section -->
            <div class="grid grid-cols-12 gap-4">
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="start_date" class="font-medium text-sm mb-1">{{ __('report.start_date') }}:</label>
                    <input type="date" name="start_date" id="start_date"
                        value="{{ $startDate ? date('Y-m-d', strtotime($startDate)) : '' }}"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="end_date" class="font-medium text-sm mb-1">{{ __('report.end_date') }}:</label>
                    <input type="date" name="end_date" id="end_date"
                        value="{{ $endDate ? date('Y-m-d', strtotime($endDate)) : '' }}"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="branch_id" class="font-medium text-sm mb-1">{{ __('report.filter_by_branch') }}:</label>
                    <select name="branch_id" id="branch_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="">{{ __('report.all_branches') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ $branchId == $branch->id ? 'selected' : '' }}>
                                {{ ucfirst($branch->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="account_id" class="font-medium text-sm mb-1">{{ __('report.filter_by_account') }}:</label>
                    <select name="account_id" id="account_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        @foreach ($allAccounts as $account)
                            <option value="{{ $account->id }}" {{ $accountId == $account->id ? 'selected' : '' }}>
                                {{ ucfirst($account->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @php
                    $selectedType = request()->input('type_id', '');
                @endphp
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="type_id" class="font-medium text-sm mb-1">{{ __('report.filter_by_type') }}:</label>
                    <select name="type_id" id="type_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="" disabled {{ empty($selectedType) ? 'selected' : '' }}>{{ __('report.select_report_type') }}
                        </option>
                        <option value="payable" {{ $selectedType == 'payable' ? 'selected' : '' }}>{{ __('report.payable_only') }}
                        </option>
                        <option value="receivable" {{ $selectedType == 'receivable' ? 'selected' : '' }}>{{ __('report.receivable_only') }}</option>
                    </select>
                </div>
            </div>

           <!-- Button Section (Centered) -->
           <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="resetReportFilters()"
                    class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-md hover:bg-gray-100 transition-all duration-150">
                    {{ __('general.reset') }}
                </button>
                <button id="submit-account-filter" type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-all duration-150">
                    {{ __('general.filter') }}
                </button>
            </div>


            <div class="flex gap-2">
                <div class="border w-full p-2 rounded">

                    @if ($startDate && $endDate)
                        <p>{{ __('report.report_for_period') }}: {{ $startDate }} to {{ $endDate }}</p>
                    @elseif (!$startDate && !$endDate)
                        <p>{{ __('report.showing_all_transactions') }} ({{ __('report.no_date_filter_applied') }}).</p>
                    @endif

                    @if ($branchId)
                        <p>{{ __('report.filtered_by_branch') }}: {{ \App\Models\Branch::find($branchId)->name ?? 'Unknown Branch' }}</p>
                    @endif
                    @if ($supplierId)
                        <p>{{ __('report.filtered_by_supplier') }}:
                            {{ \App\Models\Supplier::find($supplierId)->name ?? 'Unknown Supplier' }}
                        </p>
                    @endif
                    @if ($selectedType)
                        <p>{{ __('report.filtered_by_type') }}: {{ ucfirst($selectedType) }}</p>
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
            <h2 class="font-bold">{{ __('report.accounts_payable_transactions') }} <span class="font-normal">({{ __('report.account_id') }}:
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
                            <th style="width:220px; style=" padding: 8px; border: 1px solid #ddd;">{{ __('general.transaction_date') }}</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">{{ __('general.description') }}</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">{{ __('general.debit') }}</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">{{ __('general.credit') }}</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">{{ __('general.balance') }}</th>
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
                                    {{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d-M-Y') : '' }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <p><strong>{{ $transaction->description }}</strong>
                                    </p>
                                    @if (!empty($transaction->task?->additional_info))
                                        <p>{{ __('general.additional_info') }}: {{ $transaction->task->additional_info }}</p>
                                    @endif

                                    @if (!empty($transaction->task?->reference))
                                        <p>{{ __('general.reference') }}: {{ $transaction->task->reference }}</p>
                                    @endif

                                    @if (!empty($transaction->task?->client_name))
                                        <p>{{ __('general.client') }}: {{ $transaction->task->client_name }}</p>
                                    @endif

                                    @if (!empty($transaction->task?->flightDetails?->departure_time))
                                        <p>{{ __('general.flight_details') }}:
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
                                        <p><strong>{{ __('general.hotel_details') }}:</strong></p>
                                        <ul>
                                            <li>{{ __('general.name') }}: {{ $roomDetails['name'] ?? 'n/a' }}</li>
                                            <li>{{ __('general.info') }}: {{ $roomDetails['info'] ?? 'n/a' }}</li>
                                            <li>{{ __('general.type') }}: {{ $roomDetails['type'] ?? 'n/a' }}</li>
                                            <li>{{ __('general.check_in') }}: {{ $hotelDetails->check_in ?? 'n/a' }}</li>
                                            <li>{{ __('general.check_out') }}: {{ $hotelDetails->check_out ?? 'n/a' }}
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
                <p class="text-red-500">{{ __('report.no_account_payable') }}.</p>
            @endif
        </div>
        <div id="account_receivable"
            class="{{ $selectedType == 'receivable' ? '' : ($selectedType == 'payable' ? 'hidden' : '') }} p-3 mt-4 border shadow">
            <h2 class="font-bold">{{ __('report.accounts_receivable_transaction') }} <span class="font-normal">({{ __('report.account_id') }}:
                    {{ $receivableAccount->code ?? 'CI12301' }})</span></h2>
            @if ($receivableTransactions->isNotEmpty())
                <table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">
                    <thead>
                        <tr>
                            <th style="width:220px; style=" padding: 8px; border: 1px solid #ddd;">{{ __('general.transaction_date') }}</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">{{ __('general.description') }}</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">{{ __('general.debit') }}</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">{{ __('general.credit') }}</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">{{ __('general.balance') }}</th>
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
                                    {{ $transaction->transaction_date ? Carbon\Carbon::parse($transaction->transaction_date)->format('d-M-Y') : '' }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <p>{{ $transaction->description }}
                                    </p>
                                    @if ($transaction->invoice && !empty($transaction->invoice->invoice_number))
                                        <p>
                                            <small>{{ __('general.reference') }}:
                                                {{ $transaction->type_reference_id ?? $transaction->invoice->invoice_number }}
                                                <a target="_blank"
                                                    href="{{ route('invoice.show', ['companyId' => $transaction->company_id, 'invoiceNumber' => $transaction->invoice->invoice_number]) }}"
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
                <p class="text-red-500">{{ __('report.no_account_receivable') }}.</p>
            @endif
        </div>

        <div class="p-3 mt-4 border shadow">
            <h2 class="flex justify-start">
                <h2 class="font-bold">{{ __('report.outstanding_balance') }}s</h2>
            </h2>
            <div class="flex gap-2">
                <div class="border w-full p-2 rounded">
                    <h3>{{ __('report.accounts_payable') }}</h3>
                    <p><strong>{{ __('report.outstanding_balance') }}: {{ number_format($totalAllPayable, 2) }}</strong></p>
                </div>
                <div class="border w-full p-2 rounded">
                    <h3>{{ __('report.accounts_receivable') }}</h3>
                    <p><strong>{{ __('report.outstanding_balance') }}: {{ number_format($totalAllReceivable, 2) }}</strong></p>
                </div>
            </div>
        </div>
    </div>
    <script>
        const __translations = {
            loading: "{{ __('general.loading') }}",
            filter: "{{ __('general.filter') }}",
            no_accounts: "{{ __('general.no_accounts_available') }}",
            error_loading: "{{ __('general.error_loading_accounts') }}"
        };

        let filterType = document.getElementById('type_id');
        let filterButton = document.getElementById('submit-account-filter');
        let accountSelect = document.getElementById('account_id');

        filterType.addEventListener('change', (event) => {
            let type_id = event.target.value;

            // Show loading while fetching
            accountSelect.innerHTML = '<option value="" disabled>' + __translations.loading + '</option>';
            filterButton.innerHTML = __translations.loading;
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
                    accountSelect.innerHTML = ''; // Clear all existing options

                    if (data.length === 0) {
                        accountSelect.innerHTML = '<option value="">' + __translations.no_accounts + '</option>';
                        return;
                    }

                    data.forEach((account, index) => {
                        const option = document.createElement('option');
                        option.value = account.id;
                        option.textContent = account.name;

                        // Select the first account by default if user hasn't chosen manually
                        if (index === 0) {
                            option.selected = true;
                        }

                        accountSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    accountSelect.innerHTML = '<option value="">' + __translations.error_loading + '</option>';
                    console.error(error);
                })
                .finally(() => {
                    filterButton.innerHTML = __translations.filter;
                    filterButton.classList.remove('cursor-not-allowed');
                    filterButton.disabled = false;
                });
        });
        function resetReportFilters() {
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value = '';
        document.getElementById('branch_id').selectedIndex = 0;
        document.getElementById('type_id').selectedIndex = 0;

        // Trigger type change so account list resets too
        document.getElementById('type_id').dispatchEvent(new Event('change'));

        // Wait a bit before submitting to allow accounts to reload
        setTimeout(() => {
            document.getElementById('submit-account-filter').click();
        }, 300); // adjust delay if needed
    }
    </script>
</x-app-layout>
