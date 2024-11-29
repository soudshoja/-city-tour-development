<x-app-layout>
<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
    <div class="container mx-auto p-6">
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h3 class="text-2xl font-semibold text-blue-600 mb-4">{{ $company->name }}</h3>
         
            <div class="p-2 bg-gray-100 rounded-md shadow-md max-w-md mx-auto">
                <form class="space-y-3">
                    <!-- From and To Date Fields -->
                    <div class="grid grid-cols-2 gap-2">
                        <!-- From Date -->
                        <div>
                            <label for="from-date" class="text-sm font-medium text-gray-700">From *</label>
                            <div class="flex items-center mt-1">
                                <input type="date" id="from-date" name="from-date" class="flex-grow p-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <button type="button" class="ml-1 text-blue-500 hover:text-blue-700 text-sm">
                                    📅
                                </button>
                            </div>
                        </div>

                        <!-- To Date -->
                        <div>
                            <label for="to-date" class="text-sm font-medium text-gray-700">To *</label>
                            <div class="flex items-center mt-1">
                                <input type="date" id="to-date"  name="to-date" class="flex-grow p-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <button type="button" class="ml-1 text-blue-500 hover:text-blue-700 text-sm">
                                    📅
                                </button>
                            </div>
                        </div>
                    </div>

                  <!-- Account Name -->
                    <div>
                        <label for="account-name" class="text-sm font-medium text-gray-700">Account Name *</label>
                        <select id="account" name="account" class="mt-1 p-1 w-full border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 account-select" placeholder="Select Account Name">
                            @foreach($accounts as $account)
                                <option value="{{ $account['id'] }}">{{ $account['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Branch -->
                    <div>
                        <label for="branch" class="text-sm font-medium text-gray-700">Branch *</label>
                        <select id="branch" class="mt-1 p-1 w-full border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Buttons -->
                    <div class="flex space-x-2 justify-center">
                        <button type="button" class="px-3 py-1 bg-yellow-600 text-black rounded-md shadow hover:bg-blue-700 text-sm">
                            Onscreen
                        </button>
                        <button type="button" class="px-3 py-1 bg-green-600 text-black rounded-md shadow hover:bg-green-700 text-sm">
                            Excel Report
                        </button>
                    </div>
                </form>
            </div>
            <!-- General Ledger Table -->
                <div class="space-y-6">
                     <!-- Table for Receivables -->
                <div class="overflow-x-auto bg-white shadow rounded-lg">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Ledgers Report</h3>
                    <table id="payablesTable" class="min-w-full table-auto border-collapse">
                        <thead class="bg-gray-100 text-gray-600 text-xs">
                            <tr>
                                <th class="px-4 py-2 text-left">Invoice Number</th> 
                                <th class="px-4 py-2 text-left">Transaction Date</th>
                                <th class="px-4 py-2 text-left">Description</th>
                                <th class="px-4 py-2 text-left">Branch</th>
                                <th class="px-4 py-2 text-left">Agent</th>
                                <th class="px-4 py-2 text-left">Name</th>
                                <th class="px-4 py-2 text-left">Debit</th>
                                <th class="px-4 py-2 text-left">Credit</th>
                            </tr>
                        </thead>
                        <tbody id="payablesBody" class="text-gray-800">
                            @foreach ($groupedGeneralLedgers as $taskName => $ledgers)
                                @foreach ($ledgers as $generalLedger)
                                        <tr class="general-ledger-row hover:bg-gray-50 text-xs">
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['invoice_number'] }}</td> 
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['transaction_date'] }}</td>
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['description'] }}</td>
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['branch_name'] }}</td>
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['agent_name'] }}</td>
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['generalLedger_name'] }}</td>
                                            <td class="px-4 py-2 border-b text-right">{{ $generalLedger['debit'] }}</td>
                                            <td class="px-4 py-2 border-b text-right">{{ $generalLedger['credit'] }}</td>
                                        </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>


        </div>
    </div>

    <script>
         const branchesData = @json($branches); // Passing PHP data to JS

        const selectElements = document.querySelectorAll('.account-select');
        selectElements.forEach(selectElement => {
            new TomSelect(selectElement, {
                create: false,
                sortField: {
                    field: 'text',
                    direction: 'asc',
                },
            });
        });

        const currentDate = new Date();

        // Calculate the first day of the current month
        const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);

        // Format the dates to "YYYY-MM-DD"
        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        // Set the default values of the date fields
        document.getElementById('from-date').value = formatDate(firstDay);
        document.getElementById('to-date').value = formatDate(lastDay);


        document.querySelectorAll('.toggle-general-ledger').forEach(button => {
        button.addEventListener('click', function() {
            const payReceiveId = this.getAttribute('data-pay-receive-id');
            const generalLedgerRow = document.getElementById(`general-ledger-${payReceiveId}`);
            
            if (generalLedgerRow.style.display === 'none') {
                generalLedgerRow.style.display = 'table-row';
            } else {
                generalLedgerRow.style.display = 'none';
            }
        });
        });
       
    </script>
</x-app-layout>
