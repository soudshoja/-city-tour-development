@php
    $partyName = match(true) {
        $partyType === 'client' => $party->full_name ?? $party->name,
        default => $party->name,
    };
    $partyNumberLabel = match($partyType) {
        'client' => 'Client',
        'supplier' => 'Supplier',
        'agent' => 'Agent',
    };
    $isOpenItems = $statement['mode'] === 'open_items';
    $netOutstanding = $statement['totals']['net_outstanding'] ?? $statement['totals']['closing_balance'] ?? 0;
    // P2.5.H provenance caption (build-report fix, §MAJOR): states in the acting user's own view
    // which records a balance is derived from, so the source is disclosed at the point of use
    // rather than only in a code comment. See config('accounting.statements')'s own docblock for
    // the full note on why the supplier line is a read-time ledger projection rather than a
    // stored open-item table.
    $sourceCaption = match ($partyType) {
        'client' => 'Derived from posted invoices and applied receipts.',
        'agent' => 'Derived from agent settlement records.',
        'supplier' => 'Derived from posted ledger entries on the supplier payable account (open-item projection).',
        default => null,
    };
    $bucketSeverity = ['bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                        'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                        'bg-orange-50 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
                        'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
                        'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200'];
@endphp
<x-app-layout>
    <div class="my-3">
        <!-- Page heading -->
        <div class="flex justify-between items-center gap-5 mb-6 flex-wrap">
            <div class="flex items-center space-x-4">
                <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center">
                    <a href="javascript:history.back()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                            <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                        </svg>
                    </a>
                </div>
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold dark:text-white">{{ $partyName }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $partyNumberLabel }} statement &middot; as of {{ $asOf->format('d M Y') }}</p>
                    @if ($sourceCaption)
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $sourceCaption }}</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <span class="inline-flex flex-col items-end">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $isOpenItems ? 'Net outstanding' : 'Closing balance' }}</span>
                    <span @class([
                        'text-xl font-bold px-3 py-1 rounded-full',
                        'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' => $netOutstanding > 0.001,
                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $netOutstanding <= 0.001,
                    ])>{{ number_format($netOutstanding, 3) }}</span>
                </span>

                <a href="{{ route('accounting.statements.pdf', ['partyType' => $partyType, 'partyId' => $party->id, 'mode' => $statement['mode'], 'as_of' => $asOf->toDateString()]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700">
                    Download PDF
                </a>
            </div>
        </div>

        <!-- Mode toggle -->
        <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
            <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden text-sm">
                <a href="{{ request()->fullUrlWithQuery(['mode' => 'open_items']) }}"
                   @class([
                       'px-4 py-2 font-medium',
                       'bg-slate-800 text-white' => $isOpenItems,
                       'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' => !$isOpenItems,
                   ])>Open items</a>
                <a href="{{ request()->fullUrlWithQuery(['mode' => 'full_activity']) }}"
                   @class([
                       'px-4 py-2 font-medium border-l border-gray-300 dark:border-gray-600',
                       'bg-slate-800 text-white' => !$isOpenItems,
                       'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' => $isOpenItems,
                   ])>Full activity</a>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500">Company default: {{ $companyMode === 'open_items' ? 'Open items' : 'Full activity' }}</p>
        </div>

        @if ($isOpenItems)
            <!-- Ageing summary strip -->
            <div class="grid gap-3 mb-6" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                @foreach ($statement['ageing'] as $i => $bucket)
                    <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $bucket['label'] }} days</span>
                            <span @class(['inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-semibold', $bucketSeverity[$i] ?? $bucketSeverity[4]])>{{ $bucket['count'] }}</span>
                        </div>
                        <div class="text-lg font-bold dark:text-white">{{ number_format($bucket['total'], 3) }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Documents table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow overflow-x-auto mb-6">
            <table class="min-w-full table-auto border-collapse text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Document</th>
                        <th class="px-4 py-3 text-left">Date</th>
                        @if ($isOpenItems)
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Settled</th>
                            <th class="px-4 py-3 text-right">Outstanding</th>
                            <th class="px-4 py-3 text-right">Age (days)</th>
                        @else
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Settled</th>
                            <th class="px-4 py-3 text-right">Running balance</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($statement['items'] as $item)
                        <tr class="text-gray-800 dark:text-gray-100">
                            <td class="px-4 py-3 font-medium">{{ $item['document_number'] }}
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $item['description'] }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $item['document_date'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($item['amount'], 3) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($item['settled_amount'], 3) }}</td>
                            @if ($isOpenItems)
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($item['outstanding'], 3) }}</td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ $item['age_days'] }}</td>
                            @else
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($item['running_balance'], 3) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                                @if ($isOpenItems)
                                    Fully settled — no open items as of {{ $asOf->format('d M Y') }}.
                                @else
                                    No activity in this period.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($isOpenItems && count($statement['unapplied']) > 0)
            <!-- Unapplied receipts & credits -->
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow overflow-x-auto">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold dark:text-white">Unapplied receipts &amp; credits</h3>
                </div>
                <table class="min-w-full table-auto border-collapse text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">Reference</th>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-right">Amount available</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($statement['unapplied'] as $item)
                            <tr class="text-gray-800 dark:text-gray-100">
                                <td class="px-4 py-3 font-medium">{{ $item['document_number'] }}</td>
                                <td class="px-4 py-3">{{ $item['document_date'] }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $item['description'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($item['amount'], 3) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
