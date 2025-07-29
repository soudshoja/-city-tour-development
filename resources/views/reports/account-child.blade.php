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
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Transaction Date</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Issued Date</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Client Name</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Reference</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Status</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Description</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Debit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Credit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Running Balance</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 dark:text-gray-100">
                        <tr>
                            <td colspan="10" class="text-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">No transactions available</td>
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
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Transaction Date</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Client Name</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Reference</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Status</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/4 text-center">Description</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Debit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Credit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 whitespace-nowrap text-center">Running Balance</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 whitespace-nowrap text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 dark:text-gray-100">
                        @foreach($account->journalEntries as $journalEntry)
                        @if($journalEntry->transaction !== null)
                        <tr class="hover:bg-gray-200 dark:hover:bg-gray-900 transition">
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                <span class="text-gray-900 dark:text-white font-semibold">
                                    @if ($journalEntry->transaction->transaction_date)
                                        {{ $journalEntry->transaction->formatted_date }}
                                    @else
                                        Not Set
                                    @endif
                                </span>  
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                @if ($journalEntry->task && $journalEntry->task->client_name)
                                    {{ $journalEntry->task->client_name }}
                                @else
                                    Not Set
                                @endif
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                @if ($journalEntry->task && $journalEntry->task->reference)
                                    {{ $journalEntry->task->reference }}
                                @else
                                    Not Set
                                @endif
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                @if ($journalEntry->task && $journalEntry->task->status)
                                    {{ ucfirst($journalEntry->task->status) }}
                                @else
                                    Not Set
                                @endif
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                @if ($journalEntry->task)
                                    @if ($journalEntry->task->type === 'flight')
                                        <div class="flex justify-between items-center gap-4 text-center text-sm">
                                            <div class="flex flex-col items-center">
                                                <span class="font-bold text-base">
                                                    {{$journalEntry->task->flightDetails ? $journalEntry->task->flightDetails->departure_time : '-' }}
                                                </span>
                                                <span class="text-gray-600 text-sm">
                                                    {{$journalEntry->task->flightDetails ? $journalEntry->task->flightDetails->airport_from : '-'}}
                                                </span>
                                            </div>
                                            <div class="text-blue-700 text-lg"> ✈ </div>
                                            <div class="flex flex-col items-center">
                                                <span class="font-bold text-base">
                                                    {{$journalEntry->task->flightDetails ? $journalEntry->task->flightDetails->arrival_time : '-' }}
                                                </span>
                                                <span class="text-gray-600 text-sm">
                                                    {{$journalEntry->task->flightDetails ? $journalEntry->task->flightDetails->airport_to : '-'}}
                                                </span>
                                            </div>
                                        </div>
                                    @elseif ($journalEntry->task->type === 'hotel')
                                        <div class="flex items-start gap-2 text-sm text-left">
                                            <div class="pt-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path d="M8 21V7a1 1 0 011-1h6a1 1 0 011 1v14M3 21v-4a1 1 0 011-1h4a1 1 0 011 1v4m10 0v-6a1 1 0 011-1h2a1 1 0 011 1v6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </div>
                                            <div class="flex flex-col truncate">
                                                <div class="truncate max-w-[140px]" title="{{ $journalEntry->task->hotelDetails->hotel->name ?? '-' }}">
                                                    {{ $journalEntry->task->hotelDetails->hotel->name ?? '-' }}
                                                </div>
                                                <div class="text-sm text-gray-500 whitespace-nowrap">
                                                    {{ $journalEntry->task->hotelDetails->check_in ?? '-' }} - {{ $journalEntry->task->hotelDetails->check_out ?? '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div>{{ $journalEntry->task->additional_info ?? '-' }}</div>
                                    @endif
                                @else
                                    <div class="text-gray-500 italic">No task linked</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">{{ number_format($journalEntry->debit, 2) }}</td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">{{ number_format($journalEntry->credit, 2) }}</td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">{{ number_format($journalEntry->balance, 2) }}</td>
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">
                                <a href="{{ route('journal-entries.index', $journalEntry->transaction->id) }}"
                                    class="text-center inline-flex items-center bg-blue-500 hover:bg-blue-700 text-white font-semibold py-1 px-2 rounded-lg transition duration-300 ease-in-out transform hover:scale-105"
                                    target="_blank" rel="noopener noreferrer" title="View Transaction">    
                                    View Transaction
                                </a>                            
                            </td>
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