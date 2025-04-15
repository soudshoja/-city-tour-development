<x-app-layout>
    <div class="p-2 bg-gray-100 dark:bg-gray-800 rounded shadow mb-2 text-center text-xl font-semibold dark:text-gray-50">
        Account Ledger
    </div>
    <div class="mt-2 mb-4 flex justify-end">
        <div class="px-4 py-2 bg-blue-50 dark:bg-gray-700 border border-blue-200 dark:border-gray-600 rounded shadow text-xs text-gray-700 dark:text-gray-300">
            <div class="font-medium mb-1 text-center">
                <span class="inline-block bg-blue-200 dark:bg-gray-600 text-blue-700 dark:text-gray-200 px-2 py-0.5 rounded">
                    Info
                </span>
            </div>
            <div class="flex justify-end gap-4">
                <div class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full"></span>
                    <span>Amount Owed</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 bg-red-500 rounded-full"></span>
                    <span>Amount to Pay</span>
                </div>
            </div>
        </div>
    </div>
    <div class="space-y-4">
        @foreach($childAccountsPayable as $account)
        <div class="bg-white dark:bg-gray-800 shadow hover:shadow-lg transition">
            <div 
                class="p-4 flex justify-between items-center text-base font-semibold cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                onclick="toggleTable('table-{{ $account->id }}')">
                <p class="text-gray-900 dark:text-white">{{ $account->name }}</p>
                <p class="@if($account->balance > 0) text-red-500 @else text-green-500 @endif">
                    {{ $account->balance }}
                </p>
            </div>
            <div id="table-{{ $account->id }}" class="hidden px-4 pt-4 pb-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border border-gray-300 dark:border-gray-600">
                        <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Transaction</th>
                                <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Date</th>
                                <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Description</th>
                                <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Debit</th>
                                <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-900 dark:text-gray-100">
                            @if($account->journalEntries->isEmpty())
                            <tr>
                                <td colspan="5" class="text-center px-4 py-2 border border-gray-300 dark:border-gray-600">No transactions available</td>
                            </tr>
                            @else
                            @foreach($account->journalEntries as $journalEntry)
                            @if($journalEntry->transaction !== null)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <td class="flex justify-between items-center gap-2 px-4 py-2 border border-gray-300 dark:border-gray-600">
                                    <span>{{ $journalEntry->transaction->id }}</span>
                                    <a href="{{ route('journal-entries.index', $journalEntry->transaction->id) }}" 
                                       class="text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 text-sm" 
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       title="View Transaction">
                                       View Transaction
                                    </a>
                                </td>
                                <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">{{ $journalEntry->transaction->date }}</td>
                                <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">{{ $journalEntry->description }}</td>
                                <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">{{ number_format($journalEntry->debit, 2) }}</td>
                                <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">{{ number_format($journalEntry->credit, 2) }}</td>
                            </tr>
                            @endif
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <script>
        function toggleTable(tableId) {
            const table = document.getElementById(tableId);
            if (table.classList.contains('hidden')) {
                table.classList.remove('hidden');
            } else {
                table.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>