<x-app-layout>
    <div class="container mx-auto p-6">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">Accounting Summary</h2>

        <div class="bg-white shadow-lg rounded-lg p-6">
            <h3 class="text-2xl font-semibold text-blue-600 mb-4">{{ $company->name }} (Company)</h3>

            <!-- Branches and Transactions -->
            <div class="space-y-6">
                @foreach ($companySummary as $branch)
                    <div class="branch border border-gray-200 p-4 rounded-lg" x-data="{ open: false }">
                        <div class="flex justify-between items-center">
                            <h4 class="text-xl font-semibold text-blue-700 cursor-pointer" @click="open = !open">
                                {{ $branch['branch_name'] }} (Branch)
                            </h4>
                            <div class="text-sm text-gray-700 flex space-x-4">
                                <p>Credits: <span class="font-semibold text-green-500">${{ number_format($branch['total_credits'], 2) }}</span></p>
                                <p>Debits: <span class="font-semibold text-red-500">${{ number_format($branch['total_debits'], 2) }}</span></p>
                                <p>Balance: <span class="font-semibold text-blue-500">${{ number_format($branch['balance'], 2) }}</span></p>
                            </div>
                        </div>

                        <!-- Agents -->
                        <div class="ml-4 mt-4 space-y-3" x-show="open" x-transition>
                            @foreach ($branch['agents'] as $agent)
                                <div class="agent bg-gray-50 border border-gray-100 rounded-lg p-4" x-data="{ openAgent: false }">
                                    <div class="flex justify-between items-center">
                                        <h5 class="text-lg font-semibold text-green-600 cursor-pointer" @click="openAgent = !openAgent">
                                            {{ $agent['agent_name'] }} (Agent)
                                        </h5>
                                        <div class="text-sm text-gray-700 flex space-x-4">
                                            <p>Credits: <span class="font-semibold text-green-500">${{ number_format($agent['total_credits'], 2) }}</span></p>
                                            <p>Debits: <span class="font-semibold text-red-500">${{ number_format($agent['total_debits'], 2) }}</span></p>
                                            <p>Balance: <span class="font-semibold text-blue-500">${{ number_format($agent['balance'], 2) }}</span></p>
                                        </div>
                                    </div>

                                    <!-- Clients -->
                                    <div class="ml-4 mt-4 space-y-3" x-show="openAgent" x-transition>
                                        @foreach ($agent['clients'] as $client)
                                            <div class="client bg-white border border-gray-100 rounded-lg p-4" x-data="{ openClient: false }">
                                                <div class="flex justify-between items-center">
                                                    <p class="text-md font-medium text-gray-700 cursor-pointer" @click="openClient = !openClient">
                                                        {{ $client['client_name'] }} (Client)
                                                    </p>
                                                    <div class="text-sm text-gray-700 flex space-x-4">
                                                        <p>Credits: <span class="font-semibold text-green-500">${{ number_format($client['total_credits'], 2) }}</span></p>
                                                        <p>Debits: <span class="font-semibold text-red-500">${{ number_format($client['total_debits'], 2) }}</span></p>
                                                        <p>Balance: <span class="font-semibold text-blue-500">${{ number_format($client['balance'], 2) }}</span></p>
                                                    </div>
                                                </div>

                                                <!-- Transactions Table -->
                                                <div class="mt-3" x-show="openClient" x-transition>
                                                    <h6 class="text-sm font-semibold mb-2 text-gray-600">Transactions:</h6>
                                                    <table class="table-auto w-full text-sm bg-gray-50 border rounded-lg">
                                                        <thead>
                                                            <tr class="bg-gray-200 text-gray-600">
                                                                <th class="px-4 py-2 text-left">Date</th>
                                                                <th class="px-4 py-2 text-left">Type</th>
                                                                <th class="px-4 py-2 text-left">Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($client['transactions'] as $transaction)
                                                                <tr class="border-b text-gray-600">
                                                                    <td class="px-4 py-2">{{ $transaction->date }}</td>
                                                                    <td class="px-4 py-2">{{ ucfirst($transaction->transaction_type) }}</td>
                                                                    <td class="px-4 py-2 text-right">${{ number_format($transaction->amount, 2) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
