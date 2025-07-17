<x-app-layout>
    <div class="container mx-auto p-4">
        <h1 class="text-center font-semibold text-xl mb-4">Ledger</h1>

        @php
            $defaultFrom = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');
            $defaultTo = \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d');
            $dateFrom = request('date_from', $defaultFrom);
            $dateTo = request('date_to', $defaultTo);
        @endphp

        <!-- Filter Form -->
        <div class="bg-gray-100 p-6 rounded shadow">
            <form method="GET" action="{{ route('journal-entries.show', $accountId) }}"
                  class="flex flex-wrap items-end justify-center gap-4">

                <div class="flex flex-col w-48">
                    <label for="date_from" class="text-sm font-medium">Date From:</label>
                    <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}"
                           class="border border-gray-300 rounded px-2 py-1 h-10 w-full">
                </div>

                <div class="flex flex-col w-48">
                    <label for="date_to" class="text-sm font-medium">Date To:</label>
                    <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}"
                           class="border border-gray-300 rounded px-2 py-1 h-10 w-full">
                </div>

                <div class="flex gap-3 items-center">
                    <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition w-28">
                        Filter
                    </button>
                    <a href="{{ route('journal-entries.show', $accountId) }}"
                       class="bg-gray-300 text-black px-4 py-2 rounded hover:bg-gray-400 transition w-28 text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Report Period -->
        <div class="mt-4 text-right text-sm text-gray-600">
            <strong>Report Period:</strong> {{ $dateFrom }} to {{ $dateTo }}
        </div>

        <!-- Journal Entries Table -->
        <div class="mt-4 bg-white p-4 rounded shadow">
            @if($journalEntries->isEmpty())
                <p class="text-gray-600">No journal entries found for this account and date range.</p>
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
                                    <td class="py-2 px-4 text-center">
                                        <a href="{{ route('journal-entries.index', $entry->transaction_id) }}"
                                           class="text-blue-600 hover:underline">
                                            {{ $entry->transaction->id }}
                                        </a>
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        {{ \Carbon\Carbon::parse($entry->created_at)->format('Y-m-d') }}
                                    </td>
                                    <td class="py-2 px-4 text-left">
                                        {{ $entry->description }}
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        {{ $entry->account->name }}
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        {{ number_format($entry->debit, 2) }}
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        {{ number_format($entry->credit, 2) }}
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        {{ number_format($entry->running_balance, 2) }}
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
