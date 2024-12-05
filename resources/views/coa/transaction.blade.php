<x-app-layout>

    <div class="container mx-auto p-6 bg-white rounded-lg shadow-lg mt-6">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">All Transaction Records</h2>

        <!-- Filters -->
        <div class="container mx-auto p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Level 1 Selection -->
                <div>
                    <label for="level1" class="block text-lg font-semibold mb-2 text-gray-700">Level 1 (Root Accounts):</label>
                    <select id="level1" onchange="updateLevel2Options()" class="w-full p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Level 1</option>
                    </select>
                </div>

                <!-- Level 2 Selection -->
                <div>
                    <label for="level2" class="block text-lg font-semibold mb-2 text-gray-700">Level 2 (Child Accounts):</label>
                    <select id="level2" onchange="updateLevel3Options()" class="w-full p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Level 2</option>
                    </select>
                </div>

                <!-- Level 3 Selection -->
                <div>
                    <label for="level3" class="block text-lg font-semibold mb-2 text-gray-700">Level 3 (Sub-Child Accounts):</label>
                    <select id="level3" onchange="updateLevel4Options()" class="w-full p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Level 3</option>
                    </select>
                </div>
            </div>

               <!-- Level 4 Selection -->
                <div>
                    <label for="level4" class="block text-lg font-semibold mb-2 text-gray-700">Level 4 (Final Accounts):</label>
                    <div class="flex items-center space-x-4">
                    <select id="level4" class="w-3/4 p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Level 4</option>
                    </select>
                    <button type="button" onclick="filterTransactions(document.getElementById('level4').value)"  class=" w-1/4 btn btn-primary">Apply Filter</button>
                   </div>
                </div>
        </div>

        <!-- Table to display transactions -->
        <div class="overflow-x-auto bg-white shadow rounded-lg">
            <table id="transactionsTable" class="min-w-full table-auto border-collapse">
                <thead class="bg-gray-100 text-gray-600">
                    <tr>
                        <th class="px-4 py-2 text-left">Transaction Date</th>
                        <th class="px-4 py-2 text-left">Description</th>
                        <th class="px-4 py-2 text-left">Agent</th>
                        <th class="px-4 py-2 text-left">Credit</th>
                        <th class="px-4 py-2 text-left">Debit</th>
                        <th class="px-4 py-2 text-left">Balance</th>
                    </tr>
                </thead>
                <tbody id="transactionsBody" class="text-gray-800">
                    @foreach ($transactions as $transaction)
                       <tr class="transaction-row hover:bg-gray-50" data-level4="{{ $transaction->account_id }}">
                            <td class="px-4 py-2 border-b">{{ $transaction->created_at }}</td>
                            <td class="px-4 py-2 border-b">
                                {{ $transaction->description }}
                            </td>
                            <td class="px-4 py-2 border-b">{{ $transaction->invoice->agent->name ?? 'N/A' }}</td>
                            <td class="px-4 py-2 border-b text-right">{{ $transaction->credit }}</td>
                            <td class="px-4 py-2 border-b text-right">{{ $transaction->debit }}</td>
                            <td class="px-4 py-2 border-b text-right">{{ $transaction->balance }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Automatically set Level 4 filter if level4Id is provided
         document.addEventListener('DOMContentLoaded', function() {
            const level4Id = @json($level4Id);
            const level4Select = document.getElementById("level4");

                if (level4Id) {
                    level4Select.value = level4Id;  
                        filterTransactions(level4Id);  // Now filter transactions based on the selected value after a delay
                }

             
        });

   // Fetch Level 1 options
   fetch('/get-level1-accounts')
            .then(response => response.json())
            .then(data => {
                const level1Select = document.getElementById("level1");
                data.forEach(account => {
                    const opt = document.createElement("option");
                    opt.value = account.id;
                    opt.textContent = account.name;
                    level1Select.appendChild(opt);
                });
            });

        // Fetch and populate Level 2 options based on Level 1 selection
        function updateLevel2Options() {
            const level1Id = document.getElementById("level1").value;
            const level2Select = document.getElementById("level2");
            level2Select.innerHTML = "<option value=''>Select Level 2</option>"; // Reset Level 2

            if (level1Id) {
                fetch(`/get-level2-accounts/${level1Id}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(account => {
                            const opt = document.createElement("option");
                            opt.value = account.id;
                            opt.textContent = account.name;
                            level2Select.appendChild(opt);
                        });
                    });
            }
            updateLevel3Options(); // Reset Level 3 when Level 2 changes
        }

        // Fetch and populate Level 3 options based on Level 2 selection
        function updateLevel3Options() {
            const level2Id = document.getElementById("level2").value;
            const level3Select = document.getElementById("level3");
            level3Select.innerHTML = "<option value=''>Select Level 3</option>"; // Reset Level 3

            if (level2Id) {
                fetch(`/get-level3-accounts/${level2Id}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(account => {
                            const opt = document.createElement("option");
                            opt.value = account.id;
                            opt.textContent = account.name;
                            level3Select.appendChild(opt);
                        });
                    });
            }
            updateLevel4Options(); // Reset Level 4 when Level 3 changes
        }

        // Fetch and populate Level 4 options based on Level 3 selection
        function updateLevel4Options() {
            const level3Id = document.getElementById("level3").value;
            const level4Select = document.getElementById("level4");
            level4Select.innerHTML = "<option value=''>Select Level 4</option>"; // Reset Level 4

            if (level3Id) {
                fetch(`/get-level4-accounts/${level3Id}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(account => {
                            const opt = document.createElement("option");
                            opt.value = account.id;
                            opt.textContent = account.name;
                            level4Select.appendChild(opt);
                        });
                    });
            }
        }


        function filterTransactions(selectedLevel4) {

                    console.log('two', selectedLevel4)
                    if (!selectedLevel4) {
                        console.log("Please select a Level 4 account.");
                        return;
                    }

                    // Fetch transactions filtered by Level 4 account ID
                    fetch(`/get-account?level4_id=${selectedLevel4}`)
                        .then(response => response.json())
                        .then(data => {
                            const transactionsContainer = document.getElementById('transactionsBody');
                            transactionsContainer.innerHTML = ''; // Clear existing transactions

                            // Check if data is available
                            if (data.length === 0) {
                                transactionsContainer.innerHTML = '<tr><td colspan="6" class="text-center">No transactions found for this account.</td></tr>';
                                return;
                            }

                            // Loop through each transaction and create a row
                            data.forEach(transaction => {
                                const row = document.createElement('tr');
                                row.className = 'transaction-row hover:bg-gray-50';
                                row.setAttribute('data-level4', transaction.account_id);

                                row.innerHTML = `
                                    <td class="px-4 py-2 border-b">${transaction.created_at}</td>
                                    <td class="px-4 py-2 border-b">${transaction.description}</td>
                                    <td class="px-4 py-2 border-b">${transaction.invoice?.agent?.name ?? 'N/A'}</td>
                                    <td class="px-4 py-2 border-b text-right">${transaction.credit}</td>
                                    <td class="px-4 py-2 border-b text-right">${transaction.debit}</td>
                                    <td class="px-4 py-2 border-b text-right">${transaction.balance}</td>
                                `;

                                transactionsContainer.appendChild(row);
                            });
                        })
                        .catch(error => console.error("Error fetching transactions:", error));
                }


    </script>


</x-app-layout>