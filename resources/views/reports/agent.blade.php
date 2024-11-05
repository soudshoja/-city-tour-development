<x-app-layout>
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Agent Accounting Report</h1>

    <!-- Agent Summary Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Agent Name</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Total Transactions</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Total Debit</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Total Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($agents as $agent)
                <tr class="hover:bg-gray-50">
                    <td class="py-2 px-4 border-b">{{ $agent->agent_name }}</td>
                    <td class="py-2 px-4 border-b">{{ $agent->total_transactions }}</td>
                    <td class="py-2 px-4 border-b">{{ $agent->total_debit }}</td>
                    <td class="py-2 px-4 border-b">{{ $agent->total_credit }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Agent Ledger Details Table -->
    <h2 class="text-xl font-bold text-gray-800 mt-10 mb-6">Agent Ledger Details</h2>
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Agent Name</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Transaction Date</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Description</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Debit</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Credit</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($agentLedgers as $ledger)
                <tr class="hover:bg-gray-50">
                    <td class="py-2 px-4 border-b">{{ $ledger->agent_name }}</td>
                    <td class="py-2 px-4 border-b">{{ $ledger->transaction_date }}</td>
                    <td class="py-2 px-4 border-b">{{ $ledger->description }}</td>
                    <td class="py-2 px-4 border-b">{{ $ledger->debit }}</td>
                    <td class="py-2 px-4 border-b">{{ $ledger->credit }}</td>
                    <td class="py-2 px-4 border-b">{{ $ledger->balance }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
</x-app-layout>