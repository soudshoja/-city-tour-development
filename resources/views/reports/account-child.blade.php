<div id="account-{{ $account->id }}" class="rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 ease-in-out transform hover:translate-y-1" data-level="{{ $account->level }}">
    <div class="p-4 flex justify-between items-center text-base font-semibold cursor-pointer shadow-sm hover:bg-gray-100 dark:hover:bg-gray-600 transition-all duration-300 ease-in-out rounded-t-lg"
        onclick="toggleTable('table-{{ $account->id }}', '{{ $account->id }}')">
        <div class="flex items-center gap-2">
            <span class="text-gray-900 dark:text-white">{{ $account->name }}</span>
            <svg id="arrow-{{ $account->id }}" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </div>
        <p class="@if($account->balance > 0) text-red-500 @else text-green-500 @endif">
            {{ number_format($account->balance, 2) }}
        </p>
    </div>
    <div id="table-{{ $account->id }}" class="hidden px-4 pt-4 pb-4">
        <div class="space-y-3">
            @if(isset($account->childAccounts) && !empty($account->childAccounts))
            @foreach($account->childAccounts as $subChild)
            @include('reports.account-child', ['account' => $subChild])
            @endforeach
            @endif

            @if($account->journalEntries->isEmpty() && empty($account->childAccounts))
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left border border-gray-800 dark:border-gray-600">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Transaction</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Date</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Description</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Debit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Credit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 dark:text-gray-100">
                        <tr>
                            <td colspan="6" class="text-center px-4 py-2 border border-gray-300 dark:border-gray-600">No transactions available</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left border border-gray-300 dark:border-gray-600">
                    @if($account->journalEntries->isNotEmpty())
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6">Transaction</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6">Date</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/4">Description</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6">Debit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6">Credit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 whitespace-nowrap">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 dark:text-gray-100">
                        @foreach($account->journalEntries as $journalEntry)
                        @if($journalEntry->transaction !== null)
                        <tr class="hover:bg-gray-200 dark:hover:bg-gray-900 transition">
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-gray-900 dark:text-white font-semibold">{{ $journalEntry->transaction->id }}</span>
                                    <a href="{{ route('journal-entries.index', $journalEntry->transaction->id) }}"
                                        class="inline-flex items-center bg-blue-500 hover:bg-blue-700 text-white font-semibold py-1 px-2 rounded-lg transition duration-300 ease-in-out transform hover:scale-105"
                                        target="_blank" rel="noopener noreferrer" title="View Transaction">
                                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M15 12H9M12 9l3 3-3 3" />
                                        </svg>
                                        View Transaction
                                    </a>
                                </div>
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">{{ $journalEntry->transaction->created_at }}</td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">{{ $journalEntry->description }}</td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">{{ number_format($journalEntry->debit, 2) }}</td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">{{ number_format($journalEntry->credit, 2) }}</td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">{{ number_format($journalEntry->balance, 2) }}</td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                    @endif
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
    function applyBackgroundClass(accountDiv, level) {
        const lightClasses = [
            'bg-white',
            'bg-gray-100',
            'bg-gray-200',
            'bg-gray-300',
            'bg-gray-400'
        ];

        const darkClasses = [
            'dark:bg-gray-800',
            'dark:bg-gray-700',
            'dark:bg-gray-600',
            'dark:bg-gray-500',
            'dark:bg-gray-400'
        ];

        const lightClass = lightClasses[Math.min(level - 3, lightClasses.length - 3)] || 'bg-gray-100';
        const darkClass = darkClasses[Math.min(level - 3, darkClasses.length - 3)] || 'dark:bg-gray-700';
        accountDiv.classList.add(lightClass, darkClass);
    }

    document.addEventListener("DOMContentLoaded", function() {
        let accountDivs = document.querySelectorAll('[id^="account-"]');

        accountDivs.forEach(function(accountDiv) {
            let level = accountDiv.getAttribute('data-level');
            applyBackgroundClass(accountDiv, level);
        });
    });
</script>