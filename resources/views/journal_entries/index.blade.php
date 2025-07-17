<x-app-layout>
    <div class="container mx-auto p-4">
        <h1 class="text-center font-semibold text-xl mb-4">Ledger</h1>

        <!-- Breadcrumb Navigation -->
        <nav class="mb-6">
            <ul class="flex space-x-2 rtl:space-x-reverse text-base md:text-lg sm:text-sm justify-center">
                <li>
                    <a href="{{ route('coa.index') }}" class="customBlueColor hover:underline">Chart of Account</a>
                </li>
                <li class="before:content-['/'] before:mr-1">
                    <a href="{{ route('coa.transaction') }}" class="customBlueColor hover:underline">Transactions</a>
                </li>
                <li class="before:content-['/'] before:mr-1">
                    <span>Ledger</span>
                </li>
            </ul>
        </nav>

        <!-- Journal Entries Table -->
        <div class="bg-white p-4 rounded shadow">
            @if($journalEntries->isEmpty())
                <p class="text-center text-gray-600">No journal entries found.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full border text-sm">
                        <thead>
                            <tr class="bg-gray-200 text-left text-sm text-gray-700">
                                <th class="py-2 px-4 text-center">Transaction ID</th>
                                <th class="py-2 px-4 text-center">Date</th>
                                <th class="py-2 px-4 text-left">Description</th>
                                <th class="py-2 px-4 text-center">Account</th>
                                <th class="py-2 px-4 text-center">Debit</th>
                                <th class="py-2 px-4 text-center">Credit</th>
                                <th class="py-2 px-4 text-center">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($journalEntries as $entry)
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="py-2 px-4 text-center font-medium">
                                        {{ $entry->transaction_id }}
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        {{ \Carbon\Carbon::parse($entry->created_at)->format('Y-m-d') }}
                                    </td>
                                    <td class="py-2 px-4 text-left">
                                        {{ $entry->description ?? '-' }}
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <a href="{{ route('journal-entries.show', ['accountId' => $entry->account->id]) }}"
                                           class="text-blue-600 hover:underline">
                                            {{ $entry->account->name }}
                                        </a>
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        {{ number_format($entry->debit, 2) }}
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        {{ number_format($entry->credit, 2) }}
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        {{ $entry->running_balance !== null ? number_format($entry->running_balance, 2) : 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
