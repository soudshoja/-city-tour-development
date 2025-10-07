<x-app-layout>
    <div class="container mx-auto p-4">
        <h1 class="text-center font-semibold text-xl mb-4">Ledger</h1>

        @php
        $defaultFrom = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultTo = \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d');
        $dateFrom = request('date_from', $defaultFrom);
        $dateTo = request('date_to', $defaultTo);

        $showIssueColumn = $journalEntries->contains(function ($entry) {
        return $entry->type === 'payable' && !is_null($entry->task);
        });
        @endphp

        <div class="bg-gray-100 p-6 rounded shadow">
            <form method="GET" action="{{ route('journal-entries.show', $accountId) }}"
                class="flex flex-wrap items-end justify-center gap-4">

                <div class="flex flex-col w-64">
                    <label for="date_range" class="text-sm font-medium">Date Range:</label>
                    <input type="text" id="date_range" name="date_range"
                        value="{{ $dateFrom }} - {{ $dateTo }}"
                        class="border border-gray-300 rounded px-2 py-1 h-10 w-full" autocomplete="off">
                    <input type="hidden" name="date_from" id="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" id="date_to" value="{{ $dateTo }}">
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
                    <button type="button" id="export-pdf-btn"
                        class="px-4 py-2 rounded bg-red-600 text-white text-s hover:bg-red-700 flex items-center">
                        Export PDF
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-4 text-right text-sm text-gray-600">
            <strong>Report Period:</strong> {{ $dateFrom }} to {{ $dateTo }}
        </div>

        <div class="mt-4 bg-white p-4 rounded shadow">
            @if($journalEntries->isEmpty())
            <p class="text-gray-600">No journal entries found for this account and date range.</p>
            @else
            <div class="overflow-x-auto">
                <table class="min-w-full border text-sm">
                    <thead>
                        <tr class="bg-gray-200 text-left text-sm text-gray-700">
                            <th class="py-2 px-4 text-center">Transaction ID</th>
                            <th class="py-2 px-4 text-center">Transaction Date</th>
                            @if($showIssueColumn)
                            <th class="py-2 px-4 text-center">Task Date</th>
                            @endif
                            <th class="py-2 px-4 text-left">Reference</th>
                            <th class="py-2 px-4 text-left">Client Name</th>
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
                                    {{ $entry->transaction_id }}
                                </a>
                            </td>
                            <td class="py-2 px-4 text-center">
                                {{ \Carbon\Carbon::parse($entry->transaction_date)->format('Y-m-d') }}
                            </td>
                            @if($showIssueColumn)
                            <td class="py-2 px-4 text-center">
                                {{ $entry->task ? $entry->task->issued_date?->format('Y-m-d') ?? '-' : '-' }}
                            </td>
                            @endif
                            <td class="py-2 px-4 text-left">
                                {{ $entry->task?->reference ?? $entry->transaction?->reference_number ?? $entry->voucher_number ?? '-' }}
                            </td>
                            <td class="py-2 px-4 text-left">
                                {{ $entry->task?->client_name ?? $entry->transaction?->name ?? $entry->name ?? '-' }}
                            </td>
                            <td class="py-2 px-4 text-left">
                                @if ($entry->task && $entry->task->type === 'flight')
                                <div class="flex justify-between items-center gap-4 text-center text-sm">
                                    <div class="flex flex-col items-center">
                                        <span class="font-bold text-base">
                                            {{ $entry->task?->flightDetails?->departure_time ? \Carbon\Carbon::parse($entry->task->flightDetails->departure_time)->format('H:i') : '-' }}
                                        </span>
                                        <span class="text-gray-600 text-sm">
                                            {{ $entry->task->flightDetails->airport_from ?? '-' }}
                                        </span>
                                    </div>
                                    <div class="text-blue-700 text-lg"> ✈ </div>
                                    <div class="flex flex-col items-center">
                                        <span class="font-bold text-base">
                                            {{ $entry->task?->flightDetails?->arrival_time ? \Carbon\Carbon::parse($entry->task->flightDetails->arrival_time)->format('H:i') : '-' }}
                                        </span>
                                        <span class="text-gray-600 text-sm">
                                            {{ $entry->task->flightDetails->airport_to ?? '-' }}
                                        </span>
                                    </div>
                                </div>
                                @elseif ($entry->task && $entry->task->type === 'hotel')
                                <div class="flex items-start gap-2 text-sm text-left">
                                    <div class="pt-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path d="M8 21V7a1 1 0 011-1h6a1 1 0 011 1v14M3 21v-4a1 1 0 011-1h4a1 1 0 011 1v4m10 0v-6a1 1 0 011-1h2a1 1 0 011 1v6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <div class="flex flex-col truncate">
                                        <div class="truncate max-w-[140px]" title="{{ $entry->task->hotelDetails->hotel->name ?? '-' }}">
                                            {{ $entry->task->hotelDetails->hotel->name ?? '-' }}
                                        </div>
                                        <div class="text-sm text-gray-500 whitespace-nowrap">
                                            {{ $entry->task->hotelDetails->check_in ?? '-' }} - {{ $entry->task->hotelDetails->check_out ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                                @else
                                <div>{{ $entry->task?->additional_info ?? $entry->transaction?->description ?? '-' }}</div>
                                @endif
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    flatpickr("#date_range", {
        mode: "range",
        dateFormat: "Y-m-d",
        defaultDate: [
            "{{ $dateFrom }}",
            "{{ $dateTo }}"
        ].filter(Boolean),
        onClose: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                document.getElementById('date_from').value = instance.formatDate(selectedDates[0], "Y-m-d");
                document.getElementById('date_to').value = instance.formatDate(selectedDates[1], "Y-m-d");
            } else if (selectedDates.length === 1) {
                document.getElementById('date_from').value = instance.formatDate(selectedDates[0], "Y-m-d");
                document.getElementById('date_to').value = instance.formatDate(selectedDates[0], "Y-m-d");
            }
        }
    });

    document.querySelector('form').addEventListener('submit', function(e) {
        const range = document.getElementById('date_range').value.split(' to ');
        document.getElementById('date_from').value = range[0] ? range[0].trim() : '';
        document.getElementById('date_to').value = range[1] ? range[1].trim() : range[0];
    });

    document.getElementById('export-pdf-btn').addEventListener('click', function() {
        const form = this.closest('form');
        const originalAction = form.action;
        form.action = "{{ route('journal-entries.export.pdf', ['accountId' => $accountId]) }}";
        form.method = "GET";
        form.submit();
        setTimeout(() => {
            form.action = originalAction;
        }, 1000);
    });
</script>