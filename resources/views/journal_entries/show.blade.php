<x-app-layout>
    <nav>
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('coa.index') }}" class="customBlueColor hover:underline">Chart of Account</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <a href="{{ route('coa.transaction') }}" class="customBlueColor hover:underline">Transactions</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>Ledger</span>
            </li>
        </ul>
    </nav>
    <header class="p-2 bg-white rounded shadow my-2 text-xl font-bold mb-4">
        Ledger
    </header>
    <div class="p-2 bg-white rounded shadow">
        <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-md">
            <thead class="bg-gray-100 text-gray-700">
                <tr class="border-b border-gray-300">
                    <th class="text-center px-4 py-3 text-left text-base font-semibold">Transaction ID</th>
                    <th class="text-center px-4 py-3 text-left text-base font-semibold">Date/Time</th>
                    <th class="text-center px-4 py-3 text-left text-base font-semibold">Description</th>
                    <th class="text-center px-4 py-3 text-left text-base font-semibold">Account</th>
                    <th class="text-center px-4 py-3 text-left text-base font-semibold">Debit</th>
                    <th class="text-center px-4 py-3 text-left text-base font-semibold">Credit</th>
                    <th class="text-center px-4 py-3 text-left text-base font-semibold">Running Balance</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                @foreach ($journalEntries as $entry)
                <tr class="hover:bg-gray-50 transition-all">
                    <td class="text-center border-b border-gray-200 px-4 py-3 text-base font-medium">
                        <a href="{{ route('journal-entries.index', $entry->transaction_id) }}" class="text-blue-500 hover:underline">
                            {{ $entry->transaction->id }}
                        </a>
                    </td>
                    <td class="text-center border-b border-gray-200 px-4 py-3 text-base">
                        {{ $entry->created_at }}
                    </td>
                    <td class="text-center border-b border-gray-200 px-4 py-3 text-base">
                        {{ $entry->description }}
                    </td>
                    <td class="text-center border-b border-gray-200 px-4 py-3 text-base">
                        {{ $entry->account->name }}
                    </td>
                    <td class="text-center border-b border-gray-200 px-4 py-3 text-base">
                        {{ $entry->debit }}
                    </td>
                    <td class="text-center border-b border-gray-200 px-4 py-3 text-base">
                        {{ $entry->credit }}
                    </td>
                    <td class="text-center border-b border-gray-200 px-4 py-3 text-base">
                        {{ $entry->running_balance }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>