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
                        <button type="button" id="onscreen-btn" class="px-3 py-1 bg-yellow-600 text-black rounded-md shadow hover:bg-blue-700 text-sm">
                            Onscreen
                        </button>
                        <button type="button" id="excel-report-btn" class="px-3 py-1 bg-green-600 text-black rounded-md shadow hover:bg-green-700 text-sm">
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


        document.getElementById('onscreen-btn').addEventListener('click', function () {
            const fromDate = document.getElementById('from-date').value;
            const toDate = document.getElementById('to-date').value;
            const accountId = document.getElementById('account').value;
            const branchId = document.getElementById('branch').value;

            // Send AJAX request to fetch filtered data
            fetch('/filter-ledgers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, // Include CSRF token for security
                },
                body: JSON.stringify({ from_date: fromDate, to_date: toDate, account: accountId, branch: branchId }),
            })
                .then(response => response.json())
                .then(data => {
                    updateTable(data); // Call the function to update the table
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    alert('An error occurred while fetching the report. Please try again.');
                });
        });

// Function to dynamically update the table with filtered data
function updateTable(ledgers) {
    const payablesBody = document.getElementById('payablesBody');
    payablesBody.innerHTML = ''; // Clear existing rows

    if (ledgers.length === 0) {
        // If no records found, display a message
        payablesBody.innerHTML = '<tr><td colspan="8" class="text-center py-4">No records found</td></tr>';
        return;
    }

    let totalDebit = 0;
    let totalCredit = 0;

        // Loop through the ledgers and create rows
        ledgers.forEach(ledger => {

            totalDebit += parseFloat(ledger.debit || 0);
            totalCredit += parseFloat(ledger.credit || 0);

            const row = `
                <tr class="general-ledger-row hover:bg-gray-50 text-xs">
                    <td class="px-4 py-2 border-b">${ledger.invoice_number}</td>
                    <td class="px-4 py-2 border-b">${ledger.transaction_date}</td>
                    <td class="px-4 py-2 border-b">${ledger.description}</td>
                    <td class="px-4 py-2 border-b">${ledger.branch_name}</td>
                    <td class="px-4 py-2 border-b">${ledger.agent_name}</td>
                    <td class="px-4 py-2 border-b">${ledger.generalLedger_name}</td>
                    <td class="px-4 py-2 border-b text-right">${ledger.debit}</td>
                    <td class="px-4 py-2 border-b text-right">${ledger.credit}</td>
                </tr>
            `;
            payablesBody.insertAdjacentHTML('beforeend', row); // Add each row to the table
        });

        // Add a totals row
        const totalsRow = `
            <tr class="text-xs font-bold bg-gray-100">
                <td colspan="6" class="px-4 py-2 border-t text-right">Totals:</td>
                <td class="px-4 py-2 border-t text-right">${totalDebit.toFixed(2)}</td>
                <td class="px-4 py-2 border-t text-right">${totalCredit.toFixed(2)}</td>
            </tr>
        `;
        payablesBody.insertAdjacentHTML('beforeend', totalsRow); // Add totals row to the table

    }

    document.getElementById('excel-report-btn').addEventListener('click', () => {
            const fromDate = document.getElementById('from-date').value;
            const toDate = document.getElementById('to-date').value;
            const accountId = document.getElementById('account').value;
            const branchId = document.getElementById('branch').value;

            // Send AJAX request to fetch filtered data
            fetch('/filter-ledgers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, // Include CSRF token for security
                },
                body: JSON.stringify({ from_date: fromDate, to_date: toDate, account: accountId, branch: branchId }),
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch data');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data || data.length === 0) {
                        alert('No data available for export.');
                        return;
                    }

                    // Calculate totals
                    let totalDebit = 0;
                    let totalCredit = 0;

                    data.forEach(ledger => {
                        totalDebit += parseFloat(ledger.debit || 0);
                        totalCredit += parseFloat(ledger.credit || 0);
                    });

                    // Include totals in the export request
                    const exportData = {
                        ledgers: data,
                        totalDebit: totalDebit.toFixed(2),
                        totalCredit: totalCredit.toFixed(2),
                    };

                    exportExcel(data); // Call the function to export the data to Excel
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    alert('An error occurred while fetching the report. Please try again.');
                });
        });

        // Function to handle exporting the data
        function exportExcel(ledgers) {
            console.log('Ledgers:', ledgers);  // Check the structure of ledgers

                const totalDebit = Array.isArray(ledgers) ? ledgers.reduce((total, ledger) => total + parseFloat(ledger.debit || 0), 0) : 0;
                const totalCredit = Array.isArray(ledgers) ? ledgers.reduce((total, ledger) => total + parseFloat(ledger.credit || 0), 0) : 0;
                console.log('totalDebit', totalDebit);
                console.log('totalDebit', totalCredit);
                // Send the data to the backend with totals
                fetch('/export-excel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        ledgers: ledgers,
                        total_debit: totalDebit,
                        total_credit: totalCredit,
                    }),
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`Failed to export data: ${text}`);
                        });
                    }
                    return response.blob();
                })
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

                    // Log the response text to get more details
                    fetch('/export-excel', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({ ledgers: ledgers })
                    })
                    .then(response => {
                        return response.text();  // Read as text to see the error page or message
                    })
                    .then(text => {
                        console.log('Response body:', text);  // Log the error page or message
                    });

                    alert('An error occurred while exporting the data.');
                });
            }

    </script>
</x-app-layout>
