<x-app-layout>
<div x-data="reportingData()" x-init="fetchReports()" class="p-6">
    <h1 class="text-2xl font-bold mb-4">Account Reporting</h1>

    <div class="mb-6">
        <h2 class="text-xl font-semibold mb-2">Transactions by Agent</h2>
        <table class="min-w-full border border-gray-300">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border px-4 py-2">Agent Name</th>
                    <th class="border px-4 py-2">Total Transactions</th>
                    <th class="border px-4 py-2">Total Credit</th>
                    <th class="border px-4 py-2">Total Debit</th>
                    <th class="border px-4 py-2">Net Amount</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="agent in agents" :key="agent.id">
                    <tr>
                        <td class="border px-4 py-2" x-text="agent.name"></td>
                        <td class="border px-4 py-2" x-text="agent.transactions.total_transactions"></td>
                        <td class="border px-4 py-2" x-text="agent.transactions.total_credit"></td>
                        <td class="border px-4 py-2" x-text="agent.transactions.total_debit"></td>
                        <td class="border px-4 py-2" x-text="(agent.transactions.total_credit - agent.transactions.total_debit).toFixed(2)"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div>
        <h2 class="text-xl font-semibold mb-2">Transactions by Client</h2>
        <table class="min-w-full border border-gray-300">
            <thead>
                <tr class="bg-gray-100">
                    <th class="border px-4 py-2">Client Name</th>
                    <th class="border px-4 py-2">Invoice Count</th>
                    <th class="border px-4 py-2">Total Paid</th>
                    <th class="border px-4 py-2">Total Unpaid</th>
                    <th class="border px-4 py-2">Total Credit</th>
                    <th class="border px-4 py-2">Total Debit</th>
                    <th class="border px-4 py-2">Income Account</th>
                    <th class="border px-4 py-2">Accounts Receivable</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="client in clients" :key="client.id">
                    <tr>
                        <td class="border px-4 py-2" x-text="client.name"></td>
                        <td class="border px-4 py-2" x-text="client.invoices.invoice_count"></td>
                        <td class="border px-4 py-2" x-text="client.invoices.paid_amount"></td>
                        <td class="border px-4 py-2" x-text="client.invoices.unpaid_amount"></td>
                        <td class="border px-4 py-2" x-text="client.invoices.credit_amount"></td>
                        <td class="border px-4 py-2" x-text="client.invoices.debit_amount"></td>
                        <td class="border px-4 py-2" x-text="client.income_account"></td>
                        <td class="border px-4 py-2" x-text="client.accounts_receivable"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

<script>
function reportingData() {
    return {
        agents: [],
        clients: [],
        async fetchReports() {
            try {
                const response = await fetch('/reporting');
                const data = await response.json();
                this.agents = data.agents;
                this.clients = data.clients;
            } catch (error) {
                console.error('Error fetching reports:', error);
            }
        }
    }
}
</script>

<style>
.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 8px 12px;
    border: 1px solid #ddd;
    text-align: left;
}
</style>

</x-app-layout>