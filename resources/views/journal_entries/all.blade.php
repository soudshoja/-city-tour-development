<x-app-layout>
    <div class="container mx-auto p-4">
        <h1 class="text-center font-semibold text-xl mb-4">Journal Entries</h1>

        <nav class="mb-6">
            <ul class="flex space-x-2 rtl:space-x-reverse text-base md:text-lg sm:text-sm justify-center">
                <li>
                    <a href="{{ route('accounting.index') }}" class="customBlueColor hover:underline">Accounting</a>
                </li>
                <li class="before:content-['/'] before:mr-1">
                    <span>Journal Entries</span>
                </li>
            </ul>
        </nav>

        <form method="GET" action="{{ route('journal-entries.all') }}" class="bg-white p-4 rounded shadow mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">From</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full px-3 py-2 border rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">To</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full px-3 py-2 border rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Account</label>
                    <select name="account_id" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">All accounts</option>
                        @foreach($accounts as $a)
                            <option value="{{ $a->id }}" @selected((string)$accountId === (string)$a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Type</label>
                    <select name="type" class="w-full px-3 py-2 border rounded text-sm">
                        <option value="">All types</option>
                        @foreach($types as $t)
                            <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">Filter</button>
                    <a href="{{ route('journal-entries.all') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded text-sm hover:bg-gray-400">Reset</a>
                </div>
            </div>
        </form>

        <div class="bg-white p-4 rounded shadow">
            @if($journalEntries->isEmpty())
                <p class="text-center text-gray-600">No journal entries found in this range.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full border text-sm">
                        <thead>
                            <tr class="bg-gray-200 text-left text-sm text-gray-700">
                                <th class="py-2 px-3 text-center">Date</th>
                                <th class="py-2 px-3 text-center">Txn ID</th>
                                <th class="py-2 px-3 text-center">Type</th>
                                <th class="py-2 px-3 text-center">Account</th>
                                <th class="py-2 px-3 text-left">Description</th>
                                <th class="py-2 px-3 text-right">Debit</th>
                                <th class="py-2 px-3 text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($journalEntries as $entry)
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="py-2 px-3 text-center whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($entry->transaction_date)?->format('Y-m-d') ?? '-' }}
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        @if($entry->transaction_id)
                                            <a href="{{ route('journal-entries.index', ['transactionId' => $entry->transaction_id]) }}"
                                               class="text-blue-600 hover:underline">{{ $entry->transaction_id }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-center">{{ $entry->type ?? '-' }}</td>
                                    <td class="py-2 px-3 text-center">
                                        @if($entry->account)
                                            <a href="{{ route('journal-entries.show', ['accountId' => $entry->account->id]) }}"
                                               class="text-blue-600 hover:underline">{{ $entry->account->name }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="py-2 px-3 text-left">{{ $entry->description }}</td>
                                    <td class="py-2 px-3 text-right">
                                        @if($entry->debit > 0){{ number_format($entry->debit, 3) }}@endif
                                    </td>
                                    <td class="py-2 px-3 text-right">
                                        @if($entry->credit > 0){{ number_format($entry->credit, 3) }}@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $journalEntries->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
