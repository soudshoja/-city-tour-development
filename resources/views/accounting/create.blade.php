<x-app-layout>
    <div class="container mx-auto p-4">

        <div class="border-b mb-4">
            <ul class="flex justify-center space-x-4" id="tabs">
                <li>
                    <button
                        class="tab-button text-gray-600 font-semibold pb-2 px-4 border-b-2 border-transparent hover:text-blue-500 hover:border-blue-500 active"
                        data-tab="payables">Payables</button>
                </li>
                <li>
                    <button
                        class="tab-button text-gray-600 font-semibold pb-2 px-4 border-b-2 border-transparent hover:text-blue-500 hover:border-blue-500"
                        data-tab="receivables">Receivables</button>
                </li>
            </ul>
        </div>

        <div id="payables" class="tab-content">
            <div class="text-center font-bold text-2xl mb-4">
                <h1>Payable Ledger</h1>
            </div>

            <!-- Search and Payables Table -->
            <div class="grid grid-cols-12 gap-4">
                <!-- Search Payables Section -->
                <div class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
                    <h2 class="text-lg font-semibold mb-2">LIST OF PAYABLES RECORD</h2>
                    <input type="hidden" name="active_tab" id="active_tab" value="payables">
                    <!-- Collapsible Filters -->

                    @foreach ($generalLedgers2 as $type => $ledgers)
                        <h2 class="text-lg font-bold mt-4 text-red-600">{{ ucfirst($type) }}</h2>
                        <table class="w-full text-sm border-collapse border border-gray-300 mt-2">
                            <thead>
                                <tr class="border-b bg-gray-200">
                                    <th class="text-left py-2 px-2">Description</th>
                                    <th class="text-left py-2 px-2">Debit</th>
                                    <th class="text-right py-2 px-2">Credit</th>
                                    <th class="text-right py-2 px-2">Balance</th>
                                    <th class="text-right py-2 px-2">Supplier</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ledgers as $ledger)
                                    <tr class="border-b hover:bg-gray-100">
                                        <td class="py-2 px-2">
                                            <small>{{ $ledger->transaction_date }}</small><br>{{ $ledger->description }}
                                        </td>
                                        <td class="py-2 px-2 text-red-600">{{ number_format($ledger->debit, 2) }}</td>
                                        <td class="py-2 px-2 text-right text-green-600">
                                            {{ number_format($ledger->credit, 2) }}</td>
                                        <td class="py-2 px-2 text-right font-bold">
                                            {{ number_format($ledger->balance, 2) }}</td>
                                        <td class="py-2 px-2 text-right">{{ $ledger->name ?? 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                </div>

                <!-- Payable Details Section -->
                <div class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
                    <h2 class="text-lg font-semibold mb-2">ADD PAYABLE RECORD</h2>

                    @if (session('success'))
                        <div class="mb-4 p-3 text-green-800 bg-green-200 rounded">{{ session('success') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 p-3 text-red-800 bg-red-200 rounded">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('general-ledgers.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Company Name -->
                            <div>
                                <label class="block font-medium text-sm">Company Name</label>
                                <select id="company_id_payable" name="company_id" class="w-full p-2 border rounded">
                                    <option selected value="">Select Company</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Account Name -->
                            <div>
                                <label class="block font-medium text-sm">Account Name</label>
                                <select id="account_id_payable" name="account_id" class="w-full p-2 border rounded">
                                    <option value="">Select Account</option>
                                </select>
                            </div>

                            <!-- Branch Name -->
                            <div>
                                <label class="block font-medium text-sm">Branch Name</label>
                                <select name="branch_id" class="w-full p-2 border rounded">
                                    <option value="">Select Branch</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Invoice Number -->
                            <div>
                                <label class="block font-medium text-sm">Invoice Number</label>
                                <select name="invoice_id" class="w-full p-2 border rounded">
                                    <option value="">Select Invoice</option>
                                    @foreach ($invoices as $invoice)
                                        <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Supplier Name -->
                            <div>
                                <label class="block font-medium text-sm">Supplier Name</label>
                                <select name="name" class="w-full p-2 border rounded">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->name }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Transaction Date -->
                            <div>
                                <label class="block font-medium text-sm">Transaction Date</label>
                                <input type="datetime-local" name="transaction_date" class="w-full p-2 border rounded"
                                    required>
                            </div>

                            <!-- Description -->
                            <div class="col-span-2">
                                <label class="block font-medium text-sm">Description</label>
                                <input type="text" name="description" class="w-full p-2 border rounded" required>
                            </div>

                            <!-- Debit -->
                            <div>
                                <label class="block font-medium text-sm">Debit</label>
                                <input type="number" step="0.01" value="0.00" name="debit"
                                    class="w-full p-2 border rounded">
                            </div>

                            <!-- Credit -->
                            <div>
                                <label class="block font-medium text-sm">Credit</label>
                                <input type="number" step="0.01" value="0.00" name="credit"
                                    class="w-full p-2 border rounded">
                            </div>

                            <!-- Balance -->
                            <div>
                                <label class="block font-medium text-sm">Balance</label>
                                <input type="number" step="0.01" value="0.00" name="balance"
                                    class="w-full p-2 border rounded">
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="block font-medium text-sm">Type</label>
                                <select name="type" class="w-full p-2 border rounded">
                                    <option value="payable">Payable</option>
                                    <option value="expenses">Expenses</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit"
                            class="mt-4 w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded">
                            Submit
                        </button>
                    </form>

                </div>
            </div>
        </div>




        <div id="receivables" class="tab-content hidden">
            <div class="text-center font-bold text-2xl mb-4">
                <h1>Receivable Ledger</h1>
            </div>

            <!-- Search and Payables Table -->
            <div class="grid grid-cols-12 gap-4">
                <!-- Search Payables Section -->
                <div class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
                    <h2 class="text-lg font-semibold mb-2">LIST OF RECEIVABLE RECORD</h2>
                    <input type="hidden" name="active_tab" id="active_tab" value="receivables">
                    <!-- Collapsible Filters -->


                    @foreach ($generalLedgers as $type => $ledgers)
                        <h2 class="text-lg font-bold mt-4  text-green-600">{{ ucfirst($type) }}</h2>
                        <table class="w-full text-sm border-collapse border border-gray-300 mt-2">
                            <thead>
                                <tr class="border-b bg-gray-200">
                                    <th class="text-left py-2 px-2">Description</th>
                                    <th class="text-left py-2 px-2">Debit</th>
                                    <th class="text-right py-2 px-2">Credit</th>
                                    <th class="text-right py-2 px-2">Balance</th>
                                    <th class="text-right py-2 px-2">Agent/Client</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ledgers as $ledger)
                                    <tr class="border-b hover:bg-gray-100">
                                        <td class="py-2 px-2">
                                            <small>{{ $ledger->transaction_date }}</small><br>{{ $ledger->description }}
                                        </td>
                                        <td class="py-2 px-2 text-red-600">{{ number_format($ledger->debit, 2) }}</td>
                                        <td class="py-2 px-2 text-right text-green-600">
                                            {{ number_format($ledger->credit, 2) }}</td>
                                        <td class="py-2 px-2 text-right font-bold">
                                            {{ number_format($ledger->balance, 2) }}</td>
                                        <td class="py-2 px-2 text-right">{{ $ledger->name ?? 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                </div>

                <!-- Payable Details Section -->
                <div class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
                    <h2 class="text-lg font-semibold mb-2">ADD RECEIVABLE RECORD</h2>
                    @if (session('success'))
                        <div class="mb-4 p-3 text-green-800 bg-green-200 rounded">{{ session('success') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 p-3 text-red-800 bg-red-200 rounded">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('general-ledgers.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Company Name -->
                            <div>
                                <label class="block font-medium text-sm">Company Name</label>
                                <select id="company_id_receivable" name="company_id"
                                    class="w-full p-2 border rounded">
                                    <option selected value="">Select Company</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Account Name -->
                            <div>
                                <label class="block font-medium text-sm">Account Name</label>
                                <select id="account_id_receivable" name="account_id"
                                    class="w-full p-2 border rounded">
                                    <option value="">Select Account</option>
                                </select>
                            </div>



                            <!-- Branch Name -->
                            <div>
                                <label class="block font-medium text-sm">Branch Name</label>
                                <select name="branch_id" class="w-full p-2 border rounded">
                                    <option value="">Select Branch</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Invoice Number -->
                            <div>
                                <label class="block font-medium text-sm">Invoice Number</label>
                                <select name="invoice_id" class="w-full p-2 border rounded">
                                    <option value="">Select Invoice</option>
                                    @foreach ($invoices as $invoice)
                                        <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Supplier Name -->
                            <div>
                                <label class="block font-medium text-sm">Agent/ Client Name</label>
                                <select required name="name" class="w-full p-2 border rounded">
                                    <option value="">Select Agent/ Client</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->name }}">{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Transaction Date -->
                            <div>
                                <label class="block font-medium text-sm">Transaction Date</label>
                                <input type="datetime-local" name="transaction_date"
                                    class="w-full p-2 border rounded" required>
                            </div>

                            <!-- Description -->
                            <div class="col-span-2">
                                <label class="block font-medium text-sm">Description</label>
                                <input type="text" name="description" class="w-full p-2 border rounded" required>
                            </div>

                            <!-- Debit -->
                            <div>
                                <label class="block font-medium text-sm">Debit</label>
                                <input type="number" step="0.01" value="0.00" name="debit"
                                    class="w-full p-2 border rounded">
                            </div>

                            <!-- Credit -->
                            <div>
                                <label class="block font-medium text-sm">Credit</label>
                                <input type="number" step="0.01" value="0.00" name="credit"
                                    class="w-full p-2 border rounded">
                            </div>

                            <!-- Balance -->
                            <div>
                                <label class="block font-medium text-sm">Balance</label>
                                <input type="number" step="0.01" value="0.00" name="balance"
                                    class="w-full p-2 border rounded">
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="block font-medium text-sm">Type</label>
                                <select name="type" class="w-full p-2 border rounded">
                                    <option value="receivable">Receivable</option>
                                    <option value="income">Income</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit"
                            class="mt-4 w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded">
                            Submit
                        </button>
                    </form>

                </div>
            </div>
        </div>



    </div>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const tabs = document.querySelectorAll('.tab-button');
            const contents = document.querySelectorAll('.tab-content');
            const activeTabInput = document.getElementById("active_tab");

            // Get active tab from Laravel session OR sessionStorage OR default to "payables"
            let activeTab = "{{ session('active_tab', 'payables') }}";
            activeTab = sessionStorage.getItem("activeTab") || activeTab || "payables"; // Default to "payables"

            // Hide all tabs initially
            contents.forEach(c => c.classList.add('hidden'));

            // Set active class to the stored tab and show its content
            const activeTabButton = document.querySelector(`.tab-button[data-tab="${activeTab}"]`);
            const activeTabContent = document.getElementById(activeTab);

            if (activeTabButton) activeTabButton.classList.add('active');
            if (activeTabContent) activeTabContent.classList.remove('hidden');

            // Tab click event
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and hide all content
                    tabs.forEach(t => t.classList.remove('active'));
                    contents.forEach(c => c.classList.add('hidden'));

                    // Add active class to clicked tab and show its content
                    tab.classList.add('active');
                    document.getElementById(tab.getAttribute('data-tab')).classList.remove(
                        'hidden');

                    // Store the selected tab in sessionStorage
                    sessionStorage.setItem("activeTab", tab.getAttribute("data-tab"));
                });
            });

            // Ensure active_tab input gets the correct value before submitting
            document.querySelector("form").addEventListener("submit", function() {
                activeTabInput.value = sessionStorage.getItem("activeTab") || "payables";
            });



            function setupAccountDropdownPayable(companySelectId, accountSelectId) {
                const companySelect = document.getElementById(companySelectId);
                const accountSelect = document.getElementById(accountSelectId);

                companySelect.addEventListener("change", function() {
                    const companyId = this.value;
                    accountSelect.innerHTML = '<option value="">Loading...</option>';

                    if (companyId) {
                        fetch("{{ route('get.accounts.by.company.payable') }}?company_id=" + companyId)
                            .then(response => response.json())
                            .then(data => {
                                console.log("Response:", data);
                                accountSelect.innerHTML = '<option value="">Select Account</option>';

                                if (data.accounts.length > 0) {
                                    data.accounts.forEach(account => {
                                        const option = document.createElement("option");
                                        option.value = account.id;
                                        option.textContent =
                                            `${account.name} (Level ${account.level})`;
                                        accountSelect.appendChild(option);
                                    });
                                } else {
                                    accountSelect.innerHTML =
                                        '<option value="">No accounts found</option>';
                                }
                            })
                            .catch(error => {
                                console.error("Fetch Error:", error);
                            });
                    } else {
                        accountSelect.innerHTML = '<option value="">Select Account</option>';
                    }
                });
            }

            // Initialize dropdowns for both tabs
            setupAccountDropdownPayable("company_id_payable", "account_id_payable");



            function setupAccountDropdownReceivable(companySelectId, accountSelectId) {
                const companySelect = document.getElementById(companySelectId);
                const accountSelect = document.getElementById(accountSelectId);

                companySelect.addEventListener("change", function() {
                    const companyId = this.value;
                    accountSelect.innerHTML = '<option value="">Loading...</option>';

                    if (companyId) {
                        fetch("{{ route('get.accounts.by.company.receivable') }}?company_id=" + companyId)
                            .then(response => response.json())
                            .then(data => {
                                console.log("Response:", data);
                                accountSelect.innerHTML = '<option value="">Select Account</option>';

                                if (data.accounts.length > 0) {
                                    data.accounts.forEach(account => {
                                        const option = document.createElement("option");
                                        option.value = account.id;
                                        option.textContent =
                                            `${account.name} (Level ${account.level})`;
                                        accountSelect.appendChild(option);
                                    });
                                } else {
                                    accountSelect.innerHTML =
                                        '<option value="">No accounts found</option>';
                                }
                            })
                            .catch(error => {
                                console.error("Fetch Error:", error);
                            });
                    } else {
                        accountSelect.innerHTML = '<option value="">Select Account</option>';
                    }
                });
            }

            // Initialize dropdowns for both tabs
            setupAccountDropdownReceivable("company_id_receivable", "account_id_receivable");





        });
    </script>



</x-app-layout>
