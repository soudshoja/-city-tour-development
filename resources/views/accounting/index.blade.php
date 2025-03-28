<x-app-layout>

    <!-- Page Heading -->
    <div class="flex justify-between items-center gap-5 my-3">
        <!-- Title -->
        <div class="flex items-center space-x-4">
            <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center heartbeat">
                <!-- Back Button SVG -->
                <a href="javascript:history.back()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                        <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                    </svg>
                </a>
            </div>
            <h2 class="text-3xl font-bold dark:text-white">All Transaction Records</h2>
        </div>
        <!--/ Title -->

        <!-- Filter, Date Picker, Export Button -->
        <div class="flex items-center space-x-4">
            <!-- Date Picker -->
            <div class="relative">
                <input id="datepicker" type="text" placeholder="Select date range"
                    class="w-80 px-3 py-2 text-gray-800 bg-transparent border border-[#1e40af] rounded-lg BoxShadow
                           dark:bg-gray-700 dark:text-white dark:border-gray-600" style="outline: none;">
            </div>

            <!-- Filter Button -->
            <button id="filter-button" class="dark:text-white flex px-5 py-3 gap-2 city-light-yellow rounded-lg BoxShadow items-center text-xs md:text-sm">
                <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                    <path fill="currentColor" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3"></path>
                </svg>
                <span class="text-xs md:text-sm dark:text-white">Filters</span>
            </button>

            <!-- Export Button -->
            <button id="excel-report-btn" class="dark:text-white flex px-5 py-3 gap-2 city-light-yellow rounded-lg BoxShadow items-center text-xs md:text-sm">
                <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1"></path>
                </svg>
                <span class="text-xs md:text-sm">Export</span>
            </button>
        </div>
        <!-- ./Filter, Date Picker, Export Button -->
    </div>
    <!-- ./Page Heading -->

    <!-- Page Content -->
    <div class="tableCon">
        <!-- Left Panel (Transactions Table) -->
        <div class="content-70 panel BoxShadow rounded-lg">
            <!-- General Ledger Table -->
            <div class="mt-8">
                <h3 class="text-xl font-semibold text-gray-700 mb-4">Ledgers Report</h3>
                <div class="overflow-x-auto bg-white shadow rounded-lg">
                    <table id="payablesTable" class="min-w-full table-auto border-collapse">
                        <thead class="bg-gray-100 text-gray-600 text-xs">
                            <tr>
                                <th class="px-4 py-3 text-left">Invoice Number</th>
                                <th class="px-4 py-3 text-left">Transaction Date</th>
                                <th class="px-4 py-3 text-left">Description</th>
                                <th class="px-4 py-3 text-left">Branch</th>
                                <th class="px-4 py-3 text-left">Agent</th>
                                <th class="px-4 py-3 text-left">Name</th>
                                <th class="px-4 py-3 text-left">Debit</th>
                                <th class="px-4 py-3 text-left">Credit</th>
                            </tr>
                        </thead>
                        <tbody id="payablesBody" class="text-gray-800">
                            @foreach ($groupedJournalEntrys as $taskName => $ledgers)
                            @foreach ($ledgers as $JournalEntry)
                            <tr class="general-ledger-row hover:bg-gray-50 text-xs">
                                <td class="px-4 py-3 border-b">{{ $JournalEntry['invoice_number'] }}</td>
                                <td class="px-4 py-3 border-b">{{ $JournalEntry['transaction_date'] }}</td>
                                <td class="px-4 py-3 border-b">{{ $JournalEntry['description'] }}</td>
                                <td class="px-4 py-3 border-b">{{ $JournalEntry['branch_name'] }}</td>
                                <td class="px-4 py-3 border-b">{{ $JournalEntry['agent_name'] }}</td>
                                <td class="px-4 py-3 border-b">{{ $JournalEntry['JournalEntry_name'] }}</td>
                                <td class="px-4 py-3 border-b text-right">{{ $JournalEntry['debit'] }}</td>
                                <td class="px-4 py-3 border-b text-right">{{ $JournalEntry['credit'] }}</td>
                            </tr>
                            @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Panel (Filters) -->
        <div class="content-30" id="TransCsFilterZ">
            <div class="panel w-full xl:mt-0 rounded-lg h-auto p-6 bg-gray-50 shadow-sm">
                <form class="space-y-4">
                    <!-- Account Name -->
                    <div>
                        <label for="account" class="block text-sm font-medium text-gray-700 mb-1">Account Name *</label>
                        <select id="account" name="account" class="w-full p-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @foreach($accounts as $account)
                            <option value="{{ $account['id'] }}">{{ $account['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Branch -->
                    <div>
                        <label for="branch" class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                        <select id="branch" class="w-full p-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- ./Page Content -->


    <script>
        // Initialize Flatpickr for Date Range Picker
        flatpickr("#datepicker", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [new Date().setDate(1), new Date()], // Default to current month
            onChange: function(selectedDates, dateStr, instance) {
                console.log("Selected Dates: ", selectedDates);
            }
        });

        // Toggle Filters Panel
        document.getElementById('filter-button').addEventListener('click', function() {
            const filtersPanel = document.getElementById('TransCsFilterZ');
            filtersPanel.classList.toggle('hidden');
        });

        // Export Button Functionality
        document.getElementById('excel-report-btn').addEventListener('click', function() {
            const fromDate = document.getElementById('datepicker')._flatpickr.selectedDates[0];
            const toDate = document.getElementById('datepicker')._flatpickr.selectedDates[1];
            const accountId = document.getElementById('account').value;
            const branchId = document.getElementById('branch').value;

            // Send AJAX request to export data
            fetch('/export-excel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        from_date: fromDate ? fromDate.toISOString().split('T')[0] : null,
                        to_date: toDate ? toDate.toISOString().split('T')[0] : null,
                        account: accountId,
                        branch: branchId
                    }),
                })
                .then(response => response.blob())
                .then(blob => {
                    const link = document.createElement('a');
                    const url = window.URL.createObjectURL(blob);
                    link.href = url;
                    link.download = 'ledger_report.xlsx';
                    link.click();
                    window.URL.revokeObjectURL(url);
                })
                .catch(error => {
                    console.error('Error exporting data:', error);
                    alert('An error occurred while exporting the data.');
                });
        });
    </script>


</x-app-layout>