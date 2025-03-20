<x-app-layout>
    <div class="container mx-auto p-4">

        <div id="payables" class="tab-content">
            <div class="text-center font-bold text-2xl mb-4">
                <h1>Payable Details</h1>
            </div>

            <!-- Search and Payables Table -->
            <div class="grid grid-cols-12 gap-4">
                <!-- Search Payables Section -->
                <div class="col-span-12 sm:col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
                    <h2 class="text-lg font-semibold mb-2">LIST OF PAYABLES RECORD</h2>

                    <!-- Collapsible Filters -->
                    <div class="max-h-[500px] overflow-y-auto border border-gray-300 rounded-md p-2">
                        @foreach ($generalLedgers2 as $type => $ledgers)
                            <h2 class="text-lg font-bold mt-4 text-red-600">{{ ucfirst($type) }}</h2>
                            <table class="w-full text-sm border-collapse border border-gray-300 mt-2">
                                <thead>
                                    <tr class="border-b bg-gray-200">
                                        <th width="45%" class="text-left py-2 px-2">Description</th>
                                        <th width="13%" class="text-left py-2 px-2">Debit</th>
                                        <th width="13%" class="text-right py-2 px-2">Credit</th>
                                        <th width="13%" class="text-right py-2 px-2">Balance</th>
                                        <th width="26%" class="text-right py-2 px-2">Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($ledgers as $ledger)
                                        <tr class="border-b hover:bg-gray-100">
                                            <td class="py-2 px-2">
                                                <small>{{ $ledger->transaction_date }}</small><br>{{ $ledger->description }}
                                            </td>
                                            <td class="py-2 px-2 text-red-600">{{ number_format($ledger->debit, 2) }}
                                            </td>
                                            <td class="py-2 px-2 text-right text-green-600">
                                                {{ number_format($ledger->credit, 2) }}</td>
                                            <td class="py-2 px-2 text-right font-bold">
                                                @if ($ledger->balance > 0)
                                                    -{{ number_format($ledger->balance, 2) }}
                                                @elseif ($ledger->balance < 0)
                                                    {{ number_format(abs($ledger->balance), 2) }}
                                                @else
                                                    {{ number_format($ledger->balance, 2) }}
                                                @endif
                                            </td>
                                            <td class="py-2 px-2 text-right">{{ $ledger->name ?? 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endforeach
                    </div>


                </div>

                <!-- Payable Details Section -->
                <div class="col-span-12 sm:col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
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

                    <form action="{{ route('payable-details.payable-store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Company Name -->
                            <div>
                                <label class="block font-medium text-sm">Company Name</label>
                                @if (auth()->user()->role_id == 1)
                                    <select id="company_id_payable" name="company_id" class="w-full p-2 border rounded">
                                        <option selected value="">Select Company</option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="hidden" id="company_id_payable" name="company_id"
                                        value="{{ auth()->user()->company->id }}">
                                    <input type="text" class="w-full p-2 border rounded bg-gray-100"
                                        value="{{ auth()->user()->company->name }}" readonly>
                                @endif
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
                                <select id="branch_id_payable" name="branch_id" class="w-full p-2 border rounded">
                                    <option value="">Select Branch</option>
                                </select>
                            </div>

                            <!-- Supplier Name -->
                            <div>
                                <label class="block font-medium text-sm">Supplier Name</label>
                                <select id="supplier_id_payable" name="name" class="w-full p-2 border rounded">
                                    <option value="">Select Supplier</option>
                                </select>
                            </div>

                            <!-- Bank Account -->
                            <div>
                                <label class="block font-medium text-sm">Company's Bank Account</label>
                                <select required id="bank_account_id_payable" name="bank_account"
                                    class="w-full p-2 border rounded">
                                    <option value="">Select Bank Account</option>
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

                            <!-- Debit/ Credit -->
                            <div>
                                <label class="block font-medium text-sm">Amount</label>
                                <input required type="number" step="0.01" value="0.00" name="amount"
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
    </div>


    <script>
        document.addEventListener("DOMContentLoaded", function() {

            function setupAccountDropdownPayable(companySelectId, accountSelectId, branchSelectId, supplierSelectId,
                bankAccountSelectId, userRole, userCompanyId) {
                const companySelect = document.getElementById(companySelectId);
                const accountSelect = document.getElementById(accountSelectId);
                const branchSelect = document.getElementById(branchSelectId);
                const supplierSelect = document.getElementById(supplierSelectId);
                const bankAccountSelect = document.getElementById(bankAccountSelectId);

                // If user is NOT an admin, set company and disable dropdown
                if (userRole !== '1') {
                    companySelect.value = userCompanyId;
                    companySelect.disabled = true;
                    fetchData(userCompanyId);
                }

                companySelect.addEventListener("change", function() {
                    if (userRole === '1') {
                        fetchData(this.value);
                    }
                });

                function fetchData(companyId) {
                    if (!companyId) return;

                    accountSelect.innerHTML = '<option value="">Loading...</option>';
                    branchSelect.innerHTML = '<option value="">Loading...</option>';
                    supplierSelect.innerHTML = '<option value="">Loading...</option>';
                    bankAccountSelect.innerHTML = '<option value="">Loading...</option>';

                    // Fetch accounts
                    fetch(`{{ route('get.accounts.by.company.payable') }}?company_id=${companyId}`)
                        .then(response => response.json())
                        .then(data => {
                            accountSelect.innerHTML = '<option value="">Select Account</option>';
                            if (data.accounts && Array.isArray(data.accounts)) {
                                data.accounts.forEach(account => {
                                    const option = document.createElement("option");
                                    option.value = account.id;
                                    option.textContent = `${account.name} (Level ${account.level})`;
                                    accountSelect.appendChild(option);
                                });
                            } else {
                                accountSelect.innerHTML = '<option value="">No accounts found</option>';
                            }
                        })
                        .catch(error => {
                            console.error("Fetch Error (Accounts):", error);
                            accountSelect.innerHTML = '<option value="">Error loading accounts</option>';
                        });

                    // Fetch branches
                    fetch(`{{ route('get.branches.by.company') }}?company_id=${companyId}`)
                        .then(response => response.json())
                        .then(data => {
                            branchSelect.innerHTML = '<option value="">Select Branch</option>';
                            if (data.branches && Array.isArray(data.branches)) {
                                data.branches.forEach(branch => {
                                    const option = document.createElement("option");
                                    option.value = branch.id;
                                    option.textContent = branch.address ?
                                        `${branch.name} (${branch.address})` : branch.name;
                                    branchSelect.appendChild(option);
                                });
                            } else {
                                branchSelect.innerHTML = '<option value="">No branches found</option>';
                            }
                        })
                        .catch(error => {
                            console.error("Fetch Error (Branches):", error);
                            branchSelect.innerHTML = '<option value="">Error loading branches</option>';
                        });

                    // Fetch suppliers
                    fetch(`{{ route('get.suppliers.by.company') }}?company_id=${companyId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Failed to fetch suppliers');
                            }
                            return response.json();
                        })
                        .then(data => {
                            supplierSelect.innerHTML = '<option value="">Select Supplier</option>';
                            if (data.suppliers && Array.isArray(data.suppliers)) {
                                data.suppliers.forEach(supplier => {
                                    const option = document.createElement("option");
                                    option.value = supplier.name;
                                    option.textContent = supplier.name;
                                    supplierSelect.appendChild(option);
                                });
                            } else {
                                supplierSelect.innerHTML = '<option value="">No suppliers found</option>';
                            }
                        })
                        .catch(error => {
                            console.error("Fetch Error (Suppliers):", error);
                            supplierSelect.innerHTML = '<option value="">Error loading suppliers</option>';
                        });

                    // Fetch bank accounts
                    fetch(`{{ route('get.bank.accounts.by.company') }}?company_id=${companyId}`)
                        .then(response => response.json())
                        .then(data => {
                            bankAccountSelect.innerHTML = '<option value="">Select Bank Account</option>';
                            if (data.bankaccounts && Array.isArray(data.bankaccounts)) {
                                data.bankaccounts.forEach(bankAccount => {
                                    const option = document.createElement("option");
                                    option.value = bankAccount.name;
                                    option.textContent = `${bankAccount.name}`;
                                    bankAccountSelect.appendChild(option);
                                });
                            } else {
                                bankAccountSelect.innerHTML =
                                    '<option value="">No bank accounts found</option>';
                            }
                        })
                        .catch(error => {
                            console.error("Fetch Error (Bank Accounts):", error);
                            bankAccountSelect.innerHTML =
                                '<option value="">Error loading bank accounts</option>';
                        });
                }
            }

            // Pass Laravel variables into JavaScript
            const userRole = `{{ auth()->user()->role_id }}`; // Get user role
            const userCompanyId = `{{ auth()->user()->company->id }}`; // Get user's company ID

            // Initialize dropdowns
            setupAccountDropdownPayable(
                "company_id_payable",
                "account_id_payable",
                "branch_id_payable",
                "supplier_id_payable",
                "bank_account_id_payable",
                userRole,
                userCompanyId
            );

        });
    </script>
</x-app-layout>
