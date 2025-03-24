<x-app-layout>
    <div class="container mx-auto p-4">

        <div id="receivables" class="tab-content">
            <div class="text-center font-bold text-2xl mb-4">
                <h1>Receivable Detail</h1>
            </div>

            <!-- Search and Receivable Table -->
            <div class="grid grid-cols-12 gap-4">
                <!-- Search Receivable Section -->
                <div class="col-span-12 sm:col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
                    <h2 class="text-lg font-semibold mb-2">LIST OF RECEIVABLE RECORD</h2>

                    <!-- Collapsible Filters -->
                    <div class="max-h-[500px] overflow-y-auto border border-gray-300 rounded-md p-2">
                        @if ($generalLedgersReceivable->isNotEmpty())
                            @foreach ($generalLedgersReceivable as $type => $ledgers)
                                <h2 class="text-lg font-bold mt-4  text-green-600">{{ ucfirst($type) }}</h2>
                                <table class="w-full text-sm border-collapse border border-gray-300 mt-2">
                                    <thead>
                                        <tr class="border-b bg-gray-200">
                                            <th width="45%" class="text-left py-2 px-2">Description</th>
                                            <th width="13%" class="text-left py-2 px-2">Debit</th>
                                            <th width="13%" class="text-right py-2 px-2">Credit</th>
                                            <th width="13%" class="text-right py-2 px-2">Balance</th>
                                            <th width="26%" class="text-right py-2 px-2">Agent/Client</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($ledgers as $ledger)
                                            <tr class="border-b hover:bg-gray-100">
                                                <td class="py-2 px-2">
                                                    <small>{{ $ledger->transaction_date }}</small>
                                                    <p>{{ $ledger->description }}</p>
                                                    <p>Ref:
                                                        {{ !empty($ledger->type_reference_id) ? $ledger->type_reference_id : $ledger->invoice->invoice_number ?? '' }}
                                                        @if ($ledger->invoice && $ledger->invoice->invoice_number)
                                                            <a target="_blank"
                                                                href="{{ route('invoice.show', ['invoiceNumber' => $ledger->invoice->invoice_number]) }}"
                                                                class="text-blue-500 ml-0">
                                                                🔍
                                                            </a>
                                                        @endif
                                                    </p>
                                                </td>
                                                <td class="py-2 px-2 text-red-600">
                                                    {{ number_format($ledger->debit, 2) }}
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
                        @else
                            <p class="text-red-500">No transactions found.</p>
                        @endif
                    </div>
                </div>

                <!-- Receivable Details Section -->
                <div class="col-span-12 sm:col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
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

                    <form action="{{ route('receivable-details.receivable-store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Company Name -->
                            <div>
                                <label class="block font-medium text-sm">Company Name</label>
                                @if (auth()->user()->role_id == 1)
                                    <select id="company_id_receivable" name="company_id"
                                        class="w-full p-2 border rounded">
                                        <option selected value="">Select Company</option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="hidden" id="company_id_receivable" name="company_id"
                                        value="{{ auth()->user()->company->id }}">
                                    <input type="text" class="w-full p-2 border rounded bg-gray-100"
                                        value="{{ auth()->user()->company->name }}" readonly>
                                @endif
                            </div>


                            <!-- Branch Name -->
                            <div>
                                <label class="block font-medium text-sm">Branch Name</label>
                                <select id="branch_id_receivable" name="branch_id" class="w-full p-2 border rounded">
                                    <option value="">Select Branch</option>
                                </select>
                            </div>

                            <!-- Account Name -->
                            <div>
                                <label class="block font-medium text-sm">Account Name</label>
                                <select id="account_id_receivable" name="account_id" class="w-full p-2 border rounded">
                                    <option value="">Select Account</option>
                                </select>
                            </div>

                            <!-- Agent/ Client Name -->
                            <div>
                                <label class="block font-medium text-sm">Agent/ Client Name</label>
                                <select required id="agent_id_receivable" required name="name"
                                    class="w-full p-2 border rounded">
                                    <option value="">Select Agent/ Client</option>
                                </select>
                            </div>

                            <!-- Bank Account -->
                            <div>
                                <label class="block font-medium text-sm">Company's Bank Account</label>
                                <select required id="bank_account_id_receivable" name="bank_account"
                                    class="w-full p-2 border rounded">
                                    <option value="">Select Bank Account</option>
                                </select>
                            </div>

                            <!-- Invoice Number -->
                            <div>
                                <label class="block font-medium text-sm">Invoice Number</label>
                                <select required id="invoice_id_receivable" name="invoice_id"
                                    class="w-full p-2 border rounded">
                                    <option value="">Select Invoice</option>
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
            function setupAccountDropdownReceivable(companySelectId, accountSelectId, branchSelectId,
                agentClientSelectId, bankAccountSelectId, invoiceSelectId, userRole, userCompanyId) {

                const companySelect = document.getElementById(companySelectId);
                const accountSelect = document.getElementById(accountSelectId);
                const branchSelect = document.getElementById(branchSelectId);
                const agentClientSelect = document.getElementById(agentClientSelectId);
                const bankAccountSelect = document.getElementById(bankAccountSelectId);
                const invoiceSelect = document.getElementById(invoiceSelectId);

                // Check if the user is not an admin
                if (userRole !== '1') {
                    companySelect.value = userCompanyId; // Set company select to user's company
                    companySelect.disabled = true; // Make it read-only
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
                    agentClientSelect.innerHTML = '<option value="">Loading...</option>';
                    bankAccountSelect.innerHTML = '<option value="">Loading...</option>';
                    invoiceSelect.innerHTML = '<option value="">Loading...</option>';

                    // Fetch accounts
                    fetch(`{{ route('get.accounts.by.company.receivable') }}?company_id=${companyId}`)
                        .then(response => response.json())
                        .then(data => {
                            accountSelect.innerHTML = '<option value="">Select Account</option>';
                            if (data.accounts && data.accounts.length > 0) {
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
                            if (data.branches && data.branches.length > 0) {
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

                    // Fetch agent clients
                    fetch(`{{ route('get.agents.clients.by.company') }}?company_id=${companyId}`)
                        .then(response => response.json())
                        .then(data => {
                            agentClientSelect.innerHTML = '<option value="">Select Agent / Client</option>';
                            if (data.agents && data.agents.length > 0) {
                                data.agents.forEach(agent => {
                                    const option = document.createElement("option");
                                    option.value = agent.name;
                                    option.textContent = agent.name;
                                    agentClientSelect.appendChild(option);
                                });
                            } else {
                                agentClientSelect.innerHTML =
                                    '<option value="">No agents or clients found</option>';
                            }
                        })
                        .catch(error => {
                            console.error("Fetch Error (Agent Clients):", error);
                            agentClientSelect.innerHTML =
                                '<option value="">Error loading agents/clients</option>';
                        });

                    // Fetch bank accounts
                    fetch(`{{ route('get.bank.accounts.by.company') }}?company_id=${companyId}`)
                        .then(response => response.json())
                        .then(data => {
                            bankAccountSelect.innerHTML = '<option value="">Select Bank Account</option>';
                            if (data.bankaccounts && data.bankaccounts.length > 0) {
                                data.bankaccounts.forEach(bankAccount => {
                                    const option = document.createElement("option");
                                    option.value = bankAccount.id;
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

                    // Fetch invoices
                    fetch(`{{ route('get.invoices.by.generalledger') }}?company_id=${companyId}`)
                        .then(response => response.json())
                        .then(data => {
                            invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
                            if (data.invoices && data.invoices.length > 0) {
                                data.invoices.forEach(invoice => {
                                    const option = document.createElement("option");
                                    option.value = invoice.id;
                                    option.textContent =
                                        `Invoice #${invoice.invoice_number} - $${invoice.amount}`;
                                    invoiceSelect.appendChild(option);
                                });
                            } else {
                                invoiceSelect.innerHTML = '<option value="">No invoices found</option>';
                            }
                        })
                        .catch(error => {
                            console.error("Fetch Error (Invoices):", error);
                            invoiceSelect.innerHTML = '<option value="">Error loading invoices</option>';
                        });
                }
            }

            // Pass Laravel variables into JavaScript
            const userRole = `{{ auth()->user()->role_id }}`; // Get user role
            const userCompanyId = `{{ auth()->user()->company->id }}`; // Get user's company ID

            // Initialize dropdowns
            setupAccountDropdownReceivable(
                "company_id_receivable",
                "account_id_receivable",
                "branch_id_receivable",
                "agent_id_receivable",
                "bank_account_id_receivable",
                "invoice_id_receivable",
                userRole,
                userCompanyId
            );
        });
    </script>
</x-app-layout>
