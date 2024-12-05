<x-app-layout>
    <div class="container mx-auto p-6">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">Accounting Summary</h2>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <h3 class="text-2xl font-semibold text-blue-600 mb-4">{{ $company->name }} (Company)</h3>
         

            <!-- General Ledger Table -->
                <div class="space-y-6">
                     <!-- Table for Receivables -->
                <div class="overflow-x-auto bg-white shadow rounded-lg">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Receivables</h3>
                    <table id="payablesTable" class="min-w-full table-auto border-collapse">
                        <thead class="bg-gray-100 text-gray-600 text-xs">
                            <tr>
                                <th class="px-4 py-2 text-left">Invoice Number</th> 
                                <th class="px-4 py-2 text-left">Invoice Status</th> 
                                <th class="px-4 py-2 text-left">Transaction Date</th>
                                <th class="px-4 py-2 text-left">Description</th>
                                <th class="px-4 py-2 text-left">Branch</th>
                                <th class="px-4 py-2 text-left">Agent</th>
                                <th class="px-4 py-2 text-left">Supplier</th>
                                <th class="px-4 py-2 text-left">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="payablesBody" class="text-gray-800">
                            @foreach ($groupedGeneralLedgers as $taskName => $ledgers)
                                <tr class="task-row bg-gray-200 font-bold">
                                    <td colspan="9" class="px-4 py-2 border-b">{{ $taskName }}</td>
                                </tr>

                                @foreach ($ledgers as $generalLedger)
                                    @if($generalLedger['type'] == 'receivable' || $generalLedger['type'] == 'bank')
                                        <tr class="general-ledger-row hover:bg-gray-50 text-xs {{ $generalLedger['status'] == 'unpaid' ? 'bg-red-200' : 'bg-green-200' }}">
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['invoice_number'] }}</td> 
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['status'] }}</td>
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['transaction_date'] }}</td>
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['description'] }}</td>
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['branch_name'] }}</td>
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['agent_name'] }}</td>
                                            <td class="px-4 py-2 border-b">{{ $generalLedger['supplier_name'] }}</td>
                                            <td class="px-4 py-2 border-b text-right">{{ $generalLedger['balance'] }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>

                </div>

                <!-- Table for Payables -->
                <div class="overflow-x-auto bg-white shadow rounded-lg">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Payables</h3>
                    <table id="payablesTable" class="min-w-full table-auto border-collapse">
                        <thead class="bg-gray-100 text-gray-600 text-xs">
                            <tr>
                                <th class="px-4 py-2 text-left">Invoice Number</th> 
                                <th class="px-4 py-2 text-left">Invoice Status</th> 
                                <th class="px-4 py-2 text-left">Transaction Date</th>
                                <th class="px-4 py-2 text-left">Task Name</th>
                                <th class="px-4 py-2 text-left">Description</th>
                                <th class="px-4 py-2 text-left">Branch</th>
                                <th class="px-4 py-2 text-left">Agent</th>
                                <th class="px-4 py-2 text-left">Supplier</th>
                                <th class="px-4 py-2 text-left">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="payablesBody" class="text-gray-800">
                        @foreach ($groupedGeneralLedgers as $taskName => $ledgers)
                            @foreach ($ledgers as $generalLedger)
                                @if($generalLedger['type'] == 'payable')
                                <tr class="general-ledger-row hover:bg-gray-50 text-xs {{ $generalLedger['status'] == 'unpaid' ? 'bg-red-200' : 'bg-red-200' }}">
                                        <td class="px-4 py-2 border-b">{{ $generalLedger['invoice_number'] }}</td> 
                                        <td class="px-4 py-2 border-b">{{ $generalLedger['status'] }}</td>
                                        <td class="px-4 py-2 border-b">{{ $generalLedger['transaction_date'] }}</td>
                                        <td class="px-4 py-2 border-b">{{ $generalLedger['task_name'] }}</td>
                                        <td class="px-4 py-2 border-b">{{ $generalLedger['description'] }}</td>
                                        <td class="px-4 py-2 border-b">{{ $generalLedger['branch_name'] }}</td>
                                        <td class="px-4 py-2 border-b">{{ $generalLedger['agent_name'] }}</td>
                                        <td class="px-4 py-2 border-b">{{ $generalLedger['supplier_name'] }}</td>
                                        <td class="px-4 py-2 border-b text-right">{{ $generalLedger['balance'] }}</td>
                                    </tr>
                                @endif
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

        // Update agents based on branch selection
        function updateLevel2Options() {
            const branchId = document.getElementById('level1').value;
            const level2Select = document.getElementById('level2');
            const level3Select = document.getElementById('level3');
            const level4Select = document.getElementById('level4');
            const level5Select = document.getElementById('level5');

            // Clear previous selections
            level2Select.innerHTML = '<option value="">Select Agent</option>';
            level3Select.innerHTML = '<option value="">Select Client</option>';
            level4Select.innerHTML = '<option value="">Select Invoice</option>';
            level5Select.innerHTML = '<option value="">Select General Ledger</option>';

            if (branchId) {
                const branch = branchesData.find(b => b.id == branchId);
                branch.agents.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.id;
                    option.text = agent.name;
                    level2Select.appendChild(option);
                });
            }
        }

        // Update clients based on agent selection
        function updateLevel3Options() {
            const agentId = document.getElementById('level2').value;
            const level3Select = document.getElementById('level3');
            const level4Select = document.getElementById('level4');
            const level5Select = document.getElementById('level5');

            // Clear previous selections
            level3Select.innerHTML = '<option value="">Select Client</option>';
            level4Select.innerHTML = '<option value="">Select Invoice</option>';
            level5Select.innerHTML = '<option value="">Select General Ledger</option>';

            if (agentId) {
                const branchId = document.getElementById('level1').value;
                const branch = branchesData.find(b => b.id == branchId);
                const agent = branch.agents.find(a => a.id == agentId);
                agent.clients.forEach(client => {
                    const option = document.createElement('option');
                    option.value = client.id;
                    option.text = client.name;
                    level3Select.appendChild(option);
                });
            }
        }

        // Update invoices based on client selection
        function updateLevel4Options() {
            const clientId = document.getElementById('level3').value;
            const level4Select = document.getElementById('level4');
            const level5Select = document.getElementById('level5');

            // Clear previous selections
            level4Select.innerHTML = '<option value="">Select Invoice</option>';
            level5Select.innerHTML = '<option value="">Select General Ledger</option>';

            if (clientId) {
                const agentId = document.getElementById('level2').value;
                const branchId = document.getElementById('level1').value;
                const branch = branchesData.find(b => b.id == branchId);
                const agent = branch.agents.find(a => a.id == agentId);
                const client = agent.clients.find(c => c.id == clientId);
                client.invoices.forEach(invoice => {
                    const option = document.createElement('option');
                    option.value = invoice.id;
                    option.text = invoice.description;
                    level4Select.appendChild(option);
                });
            }
        }

        // Update general ledgers based on invoice selection
        function updateLevel5Options() {
            const invoiceId = document.getElementById('level4').value;
            const level5Select = document.getElementById('level5');

            // Clear previous selections
            level5Select.innerHTML = '<option value="">Select General Ledger</option>';

            if (invoiceId) {
                const agentId = document.getElementById('level2').value;
                const clientId = document.getElementById('level3').value;
                const branchId = document.getElementById('level1').value;
                const branch = branchesData.find(b => b.id == branchId);
                const agent = branch.agents.find(a => a.id == agentId);
                const client = agent.clients.find(c => c.id == clientId);
                const invoice = client.invoices.find(i => i.id == invoiceId);
                invoice.invoiceDetails.forEach(detail => {
                    detail.generalLedgers.forEach(gl => {
                        const option = document.createElement('option');
                        option.value = gl.id;
                        option.text = gl.name;
                        level5Select.appendChild(option);
                    });
                });
            }
        }
    </script>
</x-app-layout>
