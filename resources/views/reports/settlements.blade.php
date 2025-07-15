<x-app-layout>
    <div class="container mx-auto p-4" x-data="{ open: false, entries: [], loadEntries(url) {
        fetch(url)
            .then(res => res.json())
            .then(data => {
                this.entries = data.entries;
                this.open = true;
            });
    }}">
        <h1 class="text-center font-semibold text-xl mb-4">Settlement Transactions Report</h1>

        <div class="bg-gray-100 p-6 rounded shadow">
            <form method="GET" action="{{ route('reports.settlements') }}"
                class="flex flex-wrap items-end justify-center gap-4">
                
                <div class="flex flex-col flex-shrink-0" style="min-width: 12rem; max-width: 18rem; width: 100%;">
                    <label for="from" class="block text-sm font-medium">Date From:</label>
                    <input type="date" name="from" id="from" value="{{ request('from') }}"
                        class="border border-gray-300 rounded px-2 py-1 h-10 w-full">
                </div>

                <div class="flex flex-col flex-shrink-0" style="min-width: 12rem; max-width: 18rem; width: 100%;">
                    <label for="to" class="block text-sm font-medium">Date To:</label>
                    <input type="date" name="to" id="to" value="{{ request('to') }}"
                        class="border border-gray-300 rounded px-2 py-1 h-10 w-full">
                </div>

                <div class="flex flex-col flex-shrink-0" style="min-width: 12rem; max-width: 18rem; width: 100%;">
                    <label for="reference_type" class="block text-sm font-medium">Reference Type:</label>
                    <select name="reference_type" id="reference_type"
                            class="border border-gray-300 rounded px-2 py-1 h-10 w-full">
                        <option value="">All</option>
                        <option value="invoice" {{ request('reference_type') == 'invoice' ? 'selected' : '' }}>Invoice</option>
                        <option value="payment" {{ request('reference_type') == 'payment' ? 'selected' : '' }}>Payment</option>
                    </select>
                </div>

                <div class="flex gap-3 items-center">
                    <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition w-28">
                        Filter
                    </button>
                    <a href="{{ route('reports.settlements') }}"
                    class="bg-gray-300 text-black px-4 py-2 rounded hover:bg-gray-400 transition w-28 text-center">
                        Reset
                    </a>
                </div>

            </form>
        </div>


        <div class="mt-6 bg-white p-4 rounded shadow">
            @if($transactions->isEmpty())
                <p class="text-gray-600">No transactions found for the selected criteria.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full border text-sm">
                        <thead>
                            <tr class="bg-gray-200 text-left text-sm text-gray-700">
                                <th class="py-2 px-4">Date</th>
                                <th class="py-2 px-4">Reference Type</th>
                                <th class="py-2 px-4">Company</th>
                                <th class="py-2 px-4">Description</th>
                                <th class="py-2 px-4 text-right">Amount (KWD)</th>
                                <th class="py-2 px-4 text-center">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transactions as $tx)
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="py-2 px-4">{{ \Carbon\Carbon::parse($tx->created_at)->format('Y-m-d') }}</td>
                                    <td class="py-2 px-4 capitalize">{{ $tx->reference_type ?? '-' }}</td>
                                    <td class="py-2 px-4">{{ $tx->company->name ?? '-' }}</td>
                                    <td class="py-2 px-4">{{ $tx->description }}</td>
                                    <td class="py-2 px-4 text-right">{{ number_format($tx->amount, 2) }}</td>
                                    <td class="py-2 px-4 text-center">
                                        <a href="#"
                                           @click.prevent="loadEntries('{{ route('reports.settlements.entries', $tx->id) }}')"
                                           class="text-blue-600 hover:underline">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Modal -->
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded shadow-lg max-w-3xl w-full max-h-[400px] overflow-y-auto p-6 relative flex flex-col">
                <!-- Close (X) button on top right -->
                <button @click="open = false"
                        class="absolute top-2 right-3 text-gray-500 hover:text-gray-800 text-xl font-bold">
                    &times;
                </button>

                <h2 class="text-xl font-semibold mb-4">Journal Entries</h2>

                <!-- No entries -->
                <template x-if="entries.length === 0">
                    <p class="text-gray-600">No journal entries found.</p>
                </template>

                <!-- Table of entries -->
                <template x-if="entries.length > 0">
                    <table class="min-w-full border text-sm mb-4">
                        <thead>
                            <tr class="bg-gray-200 text-left text-sm text-gray-700">
                                <th class="py-2 px-4">Account</th>
                                <th class="py-2 px-4">Debit</th>
                                <th class="py-2 px-4">Credit</th>
                                <th class="py-2 px-4">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="entry in entries" :key="entry.id">
                                <tr class="border-t">
                                    <td class="py-2 px-4" x-text="entry.account_name"></td>
                                    <td class="py-2 px-4" x-text="entry.debit"></td>
                                    <td class="py-2 px-4" x-text="entry.credit"></td>
                                    <td class="py-2 px-4" x-text="entry.description ?? '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </template>

                <!-- Bottom Close Button -->
                <div class="mt-auto pt-2 text-right">
                    <button @click="open = false"
                        class="bg-gray-200 hover:bg-gray-300 text-sm font-medium px-4 py-2 rounded">
                        Close
                    </button>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
