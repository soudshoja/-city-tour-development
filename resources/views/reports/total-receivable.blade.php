<x-app-layout>
    <div class="p-2 bg-gray-100 dark:bg-gray-800 rounded shadow mb-2 text-center text-xl font-semibold dark:text-gray-50">
        Accounts Receivable
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
        @foreach($childAccountsReceivable->childAccounts as $account)
        @include('reports.account-child', ['account' => $account])
        @endforeach
    </div>

    <script>
        function toggleTable(tableId, accountId) {
            const table = document.getElementById(tableId);
            const arrow = document.getElementById('arrow-' + accountId);
            if (table.classList.contains('hidden')) {
                table.classList.remove('hidden');
                arrow.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />';
            } else {
                table.classList.add('hidden');
                arrow.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />';
            }
        }
    </script>
</x-app-layout>