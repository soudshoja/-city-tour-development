<x-app-layout>

    <div class="max-w-4xl mx-auto bg-white shadow-md rounded p-8">
        <!-- Title -->
        <h1 class="text-center text-2xl font-bold mb-4">PAYMENT VOUCHER</h1>
        <p class="text-center text-sm mb-4">{{ $company->name }}- {{ $company->address }} - {{ $company->phone }} - {{ $company->email }}</p>

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
                    <select id="level4" onchange="showBalanceInput()" class="w-full p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Level 4</option>
                    </select>

                </div>

        </div>

        <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />

        <!-- Voucher Info -->
        <div class="flex justify-between mb-4">
            <div class="flex items-center space-x-2">
                <label class="font-semibold">Voucher No:</label>
                <input type="text" id="voucher_no" class="border border-gray-300 rounded p-1 w-40" placeholder="Enter voucher no" value="{{$voucherNumber}}">
            </div>
            <div class="flex items-center space-x-2">
                <label class="font-semibold">Date:</label>
                <input type="date" id="voucher_date" class="border border-gray-300 rounded p-1 w-40">
            </div>
        </div>
        <div class="flex justify-between mb-4">
            <div class="flex items-center space-x-2">
                <label class="font-semibold">Payment Method:</label>
                <select id="payment_method" name="payment_method" class="form-select">
                        <option value="">Select Payment</option>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="paypal">Paypal</option>
                        <option value="other">Other</option>
                </select>
          </div>
            <div class="flex items-center space-x-2">
                <label class="font-semibold">Paid To:</label>
                <input type="text" id="pay_to" class="border border-gray-300 rounded p-1 w-40">
            </div>
        </div>


        <!-- Table -->
        <div class="border-t border-gray-400 mb-4">
            <form id="voucher-form">
                <table class="w-full text-left mt-4">
                    <thead>
                        <tr>
                            <th class="bg-blue-200 border border-gray-300 p-2">Account</th>
                            <th class="bg-blue-200 border border-gray-300 p-2">Particulars</th>
                            <th class="bg-blue-200 border border-gray-300 p-2">Debit (HK$)</th>
                            <th class="bg-blue-200 border border-gray-300 p-2">Credit (HK$)</th>
                        </tr>
                    </thead>
                    <tbody id="voucher-table-body">
                        <!-- Dynamic rows will be added here -->
                    </tbody>
                </table>

                <!-- Submit Button -->
                <div class="flex justify-end items-center my-4">
                    <button type="submit" class="w-full btn btn-primary">Submit Voucher</button>
                </div>
            </form>
        </div>

    </div>

    <script>
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

        // Show balance input when Level 4 is selected
        function showBalanceInput() {
            const level4Select = document.getElementById("level4");

            // Add a row to the table for Debit and Credit entries
            const level4Id = level4Select.value;
            const tableBody = document.getElementById("voucher-table-body");
            const selectedAccount = level4Select.selectedOptions[0]; 
            const accountName = selectedAccount ? selectedAccount.text : ''; 
            const accountId = selectedAccount ? selectedAccount.value : ''; 

            const row = document.createElement("tr");
            row.innerHTML = `
                <input type="hidden"  value="${accountId}" class="account-id">
                <td><input type="text" class="w-full border border-gray-300 rounded p-1" value="${accountName}" readonly></td>
                <td><input type="text" class="w-full border border-gray-300 rounded p-1" placeholder="Particulars"></td>
                <td><input type="number" class="w-full border border-gray-300 rounded p-1" placeholder="Debit"></td>
                <td><input type="number" class="w-full border border-gray-300 rounded p-1" placeholder="Credit"></td>
                <td><button type="button" class="text-red-500 font-bold p-1 bg-transparent hover:bg-red-100 rounded-full" onclick="deleteRow(this)">Delete</button></td>
            `;
            tableBody.appendChild(row);
        }

        // Handle form submission
        document.getElementById("voucher-form").addEventListener("submit", function(event) {
            event.preventDefault();

            const voucherNo = document.getElementById("voucher_no").value;
            const voucherDate = document.getElementById("voucher_date").value;
            const paymentMethod = document.getElementById("payment_method").value;
            const payTo = document.getElementById("pay_to").value;
            const tableRows = document.querySelectorAll("#voucher-table-body tr");
            const entries = [];

            tableRows.forEach(row => {
                const accountId = row.querySelector(".account-id").value;
                const accountName = row.cells[0].querySelector("input").value;
                const particulars = row.cells[1].querySelector("input").value;
                const debit = row.cells[2].querySelector("input").value;
                const credit = row.cells[3].querySelector("input").value;

                entries.push({
                    account_id: accountId, 
                    particulars: particulars,
                    debit: debit || 0,  
                    credit: credit || 0  
                });
            });
            console.log(entries);
            // Now send this data to the server for processing
            fetch('/submit-voucher', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    voucher_no: voucherNo,
                    voucher_date: voucherDate,
                    payment_method: paymentMethod,
                    pay_to: payTo,
                    entries
                })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
            })
            .catch(error => {
                console.error('Error submitting voucher:', error);
                alert('Error submitting voucher');
            });
        });

        function deleteRow(button) {
                // Find the row containing the delete button and remove it
                const row = button.closest('tr');
                row.remove();
            }
    </script>

</x-app-layout>
