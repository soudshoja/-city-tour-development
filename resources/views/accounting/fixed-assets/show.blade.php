<x-app-layout>
    <div class="my-3">

        <!-- Page heading -->
        <div class="flex justify-between items-center gap-5 mb-6 flex-wrap">
            <div class="flex items-center space-x-4">
                <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center">
                    <a href="{{ route('accounting.fixed-assets.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                            <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                        </svg>
                    </a>
                </div>
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold dark:text-white">{{ $asset->name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $classLabel }}
                        @if ($asset->code) &middot; {{ $asset->code }} @endif
                        &middot;
                        <span @class([
                            'font-semibold',
                            'text-slate-600 dark:text-slate-300' => $asset->status === 'draft',
                            'text-emerald-600 dark:text-emerald-300' => $asset->status === 'active',
                            'text-sky-600 dark:text-sky-300' => $asset->status === 'fully_depreciated',
                            'text-gray-500 dark:text-gray-400' => $asset->status === 'disposed',
                        ])>{{ ucwords(str_replace('_', ' ', $asset->status)) }}</span>
                    </p>
                </div>
            </div>

            @if ($canManage && $asset->status !== 'disposed')
                <a href="{{ route('accounting.fixed-assets.edit', $asset) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Edit
                </a>
            @endif
        </div>

        @unless ($engineEnabled)
            <div class="mb-6 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 px-4 py-3 text-sm">
                <strong>Engine disabled</strong> for this company — nothing below will post. Capitalise, disposal, and depreciation runs are shown as normal so you can prepare them, but every posting action is a logged no-op until the engine is enabled.
            </div>
        @endunless

        <!-- Value tiles -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-400">Cost</div>
                <div class="text-xl font-semibold dark:text-white">{{ number_format($asset->cost, 3) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-400">Accumulated depreciation</div>
                <div class="text-xl font-semibold dark:text-white">{{ number_format($accumulated, 3) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-400">Net book value</div>
                <div class="text-xl font-semibold dark:text-white">{{ number_format($nbv, 3) }}</div>
                <div class="text-[11px] text-gray-400 mt-1">Derived live from posted journal lines — never cached.</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Schedule -->
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg BoxShadow overflow-x-auto">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-semibold dark:text-white">Straight-line schedule</h3>
                    <span class="text-xs text-gray-400">Useful life: {{ $asset->useful_life_months }} months &middot; Salvage: {{ number_format($asset->salvage, 3) }}</span>
                </div>
                <table class="min-w-full table-auto border-collapse text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">Period</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-left">Posted?</th>
                            <th class="px-4 py-3 text-left">Document</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($schedule as $row)
                            <tr class="text-gray-800 dark:text-gray-100">
                                <td class="px-4 py-3">{{ $row['period'] }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format($row['amount'], 3) }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['posted'])
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Posted</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">Due</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row['posted'] && $row['transaction_id'])
                                        <a href="{{ route('journal-entries.index', $row['transaction_id']) }}" class="text-sm text-slate-700 dark:text-slate-300 hover:underline">#{{ $row['transaction_id'] }}</a>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Side panels -->
            <div class="space-y-6">

                @if ($asset->status === 'draft')
                    <!-- Capitalise panel -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                        <h3 class="font-semibold dark:text-white mb-1">Capitalise</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            Posts the acquisition document (Dr cost, Cr the bank/cash account below) and activates the asset for depreciation. If this asset's cost was already posted through an existing payment/journal voucher, leave it as a draft register row — there is no "activate without posting" action in this screen.
                        </p>
                        @if ($canManage)
                            <form method="POST" action="{{ route('accounting.fixed-assets.capitalise', $asset) }}">
                                @csrf
                                <label for="bank_account_id" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Paid from</label>
                                <select id="bank_account_id" name="bank_account_id" required
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm mb-3">
                                    <option value="">Select a bank/cash account</option>
                                    @foreach ($bankLeaves as $leaf)
                                        <option value="{{ $leaf->id }}">{{ $leaf->name }} ({{ $leaf->code }})</option>
                                    @endforeach
                                </select>
                                <button type="submit"
                                        class="w-full px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700 disabled:opacity-40"
                                        @disabled($bankLeaves->isEmpty())>
                                    {{ $engineEnabled ? 'Capitalise' : 'Capitalise (engine disabled — nothing will post)' }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($asset->isDisposable())
                    <!-- Disposal panel -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                        <h3 class="font-semibold dark:text-white mb-1">Dispose</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            Posts one balanced document: clears cost and accumulated depreciation, records proceeds, and books the resulting gain or loss against NBV ({{ number_format($nbv, 3) }} today). This is a one-way action.
                        </p>
                        @if ($canManage)
                            <form method="POST" action="{{ route('accounting.fixed-assets.dispose', $asset) }}"
                                  onsubmit="return confirm('Dispose {{ addslashes($asset->name) }}? This posts a balanced document and cannot be undone from this screen.');">
                                @csrf
                                <label for="disposal_date" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Disposal date</label>
                                <input type="date" id="disposal_date" name="disposal_date" required
                                       value="{{ date('Y-m-d') }}"
                                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm mb-3">

                                <label for="proceeds" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Proceeds (KWD)</label>
                                <input type="number" id="proceeds" name="proceeds" step="0.001" min="0" required value="0.000"
                                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm mb-3">

                                <label for="proceeds_account_id" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Received into <span class="text-gray-400">(optional — cash if left blank)</span></label>
                                <select id="proceeds_account_id" name="proceeds_account_id"
                                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm mb-3">
                                    <option value="">Cash in hand</option>
                                    @foreach ($bankLeaves as $leaf)
                                        <option value="{{ $leaf->id }}">{{ $leaf->name }} ({{ $leaf->code }})</option>
                                    @endforeach
                                </select>

                                <button type="submit" class="w-full px-4 py-2 rounded-lg text-sm font-medium bg-red-700 text-white hover:bg-red-800">
                                    {{ $engineEnabled ? 'Dispose asset' : 'Dispose (engine disabled — nothing will post)' }}
                                </button>
                            </form>
                        @endif
                    </div>
                @else
                    @if ($asset->status === 'disposed')
                        <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                            <h3 class="font-semibold dark:text-white mb-1">Disposed</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ optional($asset->disposal_date)->format('Y-m-d') }} for {{ number_format((float) $asset->disposal_proceeds, 3) }} KWD.
                                @if ($asset->disposal_transaction_id)
                                    <a href="{{ route('journal-entries.index', $asset->disposal_transaction_id) }}" class="text-slate-700 dark:text-slate-300 hover:underline">View document #{{ $asset->disposal_transaction_id }}</a>
                                @endif
                            </p>
                        </div>
                    @endif
                @endif

                <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                    <h3 class="font-semibold dark:text-white mb-1">Details</h3>
                    <dl class="text-sm space-y-1">
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Acquisition date</dt><dd class="dark:text-gray-200">{{ optional($asset->acquisition_date)->format('Y-m-d') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">In-service date</dt><dd class="dark:text-gray-200">{{ optional($asset->in_service_date)->format('Y-m-d') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Method</dt><dd class="dark:text-gray-200">{{ ucwords(str_replace('_', ' ', $asset->method)) }}</dd></div>
                        @if ($asset->acquisition_transaction_id)
                            <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Capitalisation doc</dt><dd><a href="{{ route('journal-entries.index', $asset->acquisition_transaction_id) }}" class="text-slate-700 dark:text-slate-300 hover:underline">#{{ $asset->acquisition_transaction_id }}</a></dd></div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
