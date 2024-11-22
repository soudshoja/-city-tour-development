<x-app-layout>
    <div class="container mx-auto p-6">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">Accounting Summary</h2>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <h3 class="text-2xl font-semibold text-blue-600 mb-4">{{ $company->name }} (Company)</h3>
            <div class="container mx-auto p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Level 1 Selection (Branch) -->
                    <div>
                        <label for="level1" class="block text-lg font-semibold mb-2 text-gray-700">Branch:</label>
                        <select id="level1" onchange="updateLevel2Options()" class="w-full p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch['id'] }}">{{ $branch['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Level 2 Selection (Agent) -->
                    <div>
                        <label for="level2" class="block text-lg font-semibold mb-2 text-gray-700">Agent:</label>
                        <select id="level2" onchange="updateLevel3Options()" class="w-full p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Agent</option>
                        </select>
                    </div>

                    <!-- Level 3 Selection (Client) -->
                    <div>
                        <label for="level3" class="block text-lg font-semibold mb-2 text-gray-700">Client:</label>
                        <select id="level3" onchange="updateLevel4Options()" class="w-full p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Client</option>
                        </select>
                    </div>
                </div>

                <!-- Level 4 Selection (Invoice) -->
                <div>
                    <label for="level4" class="block text-lg font-semibold mb-2 text-gray-700">Invoice:</label>
                    <select id="level4" onchange="updateLevel5Options()" class="w-full p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Invoice</option>
                    </select>
                </div>

                <!-- Level 5 Selection (General Ledger) -->
                <div>
                    <label for="level5" class="block text-lg font-semibold mb-2 text-gray-700">General Ledger:</label>
                    <select id="level5" class="w-full p-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select General Ledger</option>
                    </select>
                </div>
            </div>

            <!-- General Ledger Table -->
            <div class="space-y-6">
                <div class="overflow-x-auto bg-white shadow rounded-lg">
                    <table id="generalLedgersTable" class="min-w-full table-auto border-collapse">
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
                        <tbody id="generalLedgersBody" class="text-gray-800">
                            @foreach ($generalLedgers as $generalLedger)
                            <tr class="general-ledger-row hover:bg-gray-50" data-level5="{{ $generalLedger['generalLedger_id'] }}">
                                <td class="px-4 py-2 border-b">{{ $generalLedger['transaction_date'] }}</td>
                                <td class="px-4 py-2 border-b">{{ $generalLedger['description'] }}</td>
                                <td class="px-4 py-2 border-b">{{ $generalLedger['agent_name'] }}</td>
                                <td class="px-4 py-2 border-b text-right">{{ $generalLedger['credit'] }}</td>
                                <td class="px-4 py-2 border-b text-right">{{ $generalLedger['debit'] }}</td>
                                <td class="px-4 py-2 border-b text-right">{{ $generalLedger['balance'] }}</td>
                            </tr>
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
