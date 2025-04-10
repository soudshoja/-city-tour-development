<x-app-layout>
    <div class="flex justify-between p-2 bg-white rounded shadow mb-2">
        Account Ledger
        <div class="flex items-center gap-4 mt-2">
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 bg-green-500 rounded-full"></span>
                <p>Amount Owed</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 bg-red-500 rounded-full"></span>
                <p>Amount to Pay</p>
            </div>
        </div>
    </div>
    <div class="p-2 bg-white rounded shadow">
        @foreach($childAccountsPayable as $account)
        <div class="mb-2">
            <div class="p-2 flex justify-between text-lg font-semibold cursor-pointer hover:bg-gray-100"  onclick="toggleTable('table-{{ $account->id }}')">
                <p> {{ $account->name }} </p>
                <p class="@if($account->balance > 0) text-red-500 @else text-green-500 @endif">
                    {{ $account->balance }}
                </p>
            </div>
            <div id="table-{{ $account->id }}" class="hidden">
                <table class="min-w-full border-collapse border border-gray-200">
                    <thead>
                        <tr>
                            <th class="border border-gray-300 px-4 py-2">Transaction</th>
                            <th class="border border-gray-300 px-4 py-2">Date</th>
                            <th class="border border-gray-300 px-4 py-2">Description</th>
                            <th class="border border-gray-300 px-4 py-2">Debit</th>
                            <th class="border border-gray-300 px-4 py-2">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($account->journalEntries->isEmpty())
                        <tr>
                            <td colspan="5" class="border border-gray-300 px-4 py-2 text-center">No transactions available</td>
                        </tr>
                        @else
                        @foreach($account->journalEntries as $journalEntry)
                        <tr>
                            <td class="flex gap-2 justify-between border border-gray-300 px-4 py-2">
                                <p>
                                    {{ $journalEntry->transaction->id }}
                                </p>
                                <a class="text-blue-500 hover:text-blue-700" target="_blank" rel="noopener noreferrer"
                                    href="{{ route('journal-entries.index', $journalEntry->transaction->id) }}"
                                    title="View Transaction"
                                    data-tooltip-target="tooltip-default"
                                    data-tooltip-placement="top"
                                    data-tooltip-trigger="hover"
                                >
                                    View Transaction
                                </a>
                            </td>
                            <td class="border border-gray-300 px-4 py-2">{{ $journalEntry->transaction->date }}</td>
                            <td class="border border-gray-300 px-4 py-2">{{ $journalEntry->description }}</td>
                            <td class="border border-gray-300 px-4 py-2">{{ number_format($journalEntry->debit, 2) }}</td>
                            <td class="border border-gray-300 px-4 py-2">{{ number_format($journalEntry->credit, 2) }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
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