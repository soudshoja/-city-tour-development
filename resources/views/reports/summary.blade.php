<x-app-layout>
<div class="container mx-auto my-10">
    <!-- Agent Summary Section -->
    <h2 class="text-2xl font-bold mb-6">Agent Summary</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full border-collapse bg-white">
            <thead>
                <tr class="text-left border-b">
                    <th class="px-6 py-3 font-semibold">Agent Name</th>
                    <th class="px-6 py-3 font-semibold">Total Transactions</th>
                    <th class="px-6 py-3 font-semibold">Total Debit</th>
                    <th class="px-6 py-3 font-semibold">Total Credit</th>
                    <th class="px-6 py-3 font-semibold">Net Balance</th>
                    <th class="px-6 py-3 font-semibold">Average Transaction</th>
                    <th class="px-6 py-3 font-semibold">Profit Margin</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($agents as $agent)
                    <tr class="border-b">
                        <td class="px-6 py-3">{{ $agent->agent_name }}</td>
                        <td class="px-6 py-3">{{ $agent->total_transactions }}</td>
                        <td class="px-6 py-3">${{ number_format($agent->total_debit, 2) }}</td>
                        <td class="px-6 py-3">${{ number_format($agent->total_credit, 2) }}</td>
                        <td class="px-6 py-3">${{ number_format($agent->net_balance, 2) }}</td>
                        <td class="px-6 py-3">${{ number_format($agent->avg_transaction_value, 2) }}</td>
                        <td class="px-6 py-3">{{ number_format($agent->profit_margin * 100, 2) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Client Summary Section -->
    <h2 class="text-2xl font-bold mt-10 mb-6">Client Summary</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full border-collapse bg-white">
            <thead>
                <tr class="text-left border-b">
                    <th class="px-6 py-3 font-semibold">Client Name</th>
                    <th class="px-6 py-3 font-semibold">Total Transactions</th>
                    <th class="px-6 py-3 font-semibold">Total Debit</th>
                    <th class="px-6 py-3 font-semibold">Total Credit</th>
                    <th class="px-6 py-3 font-semibold">Outstanding Balance</th>
                    <th class="px-6 py-3 font-semibold">Average Transaction</th>
                    <th class="px-6 py-3 font-semibold">Credit Score</th>
                    <th class="px-6 py-3 font-semibold">Last Transaction</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clients as $client)
                    <tr class="border-b">
                        <td class="px-6 py-3">{{ $client->client_name }}</td>
                        <td class="px-6 py-3">{{ $client->total_transactions }}</td>
                        <td class="px-6 py-3">${{ number_format($client->total_debit, 2) }}</td>
                        <td class="px-6 py-3">${{ number_format($client->total_credit, 2) }}</td>
                        <td class="px-6 py-3">${{ number_format($client->outstanding_balance, 2) }}</td>
                        <td class="px-6 py-3">${{ number_format($client->avg_transaction_value, 2) }}</td>
                        <td class="px-6 py-3">{{ $client->credit_score }}/5</td>
                        <td class="px-6 py-3">{{ $client->last_transaction_date }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

</x-app-layout>