<x-app-layout>
    <div class="container mx-auto p-4">

        <div id="payables" class="tab-content">
            <div class="text-center font-bold text-2xl mb-6">
                <h1>Payable Details</h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md border border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        List of Payables Record
                    </h2>

                    <div class="max-h-[600px] overflow-y-auto overflow-x-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                        @if ($JournalEntrysPayable->isNotEmpty())
                            @foreach ($JournalEntrysPayable as $type => $ledgers)
                                <div class="sticky top-0 bg-red-50 dark:bg-red-900/30 px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                                    <h3 class="text-md font-bold text-red-600 dark:text-red-400">{{ ucfirst($type) }}</h3>
                                </div>

                                <div class="hidden sm:block">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-10">
                                            <tr class="border-b border-gray-200 dark:border-gray-600">
                                                <th width="40%" class="text-left py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Description</th>
                                                <th width="12%" class="text-right py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Debit</th>
                                                <th width="12%" class="text-right py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Credit</th>
                                                <th width="12%" class="text-right py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Balance</th>
                                                <th width="24%" class="text-right py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Supplier</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            @foreach ($ledgers as $ledger)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                                    <td class="py-3 px-3">
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $ledger->transaction_date }}</p>
                                                        <p class="text-sm text-gray-800 dark:text-gray-200 mt-1">{{ $ledger->description }}</p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            Ref: 
                                                            @if (!empty($ledger->type_reference_id))
                                                                <span class="text-blue-600 dark:text-blue-400">{{ $ledger->referenceAccount->name ?? 'N/A' }}</span>
                                                            @elseif ($ledger->invoice && $ledger->invoice->invoice_number)
                                                                <span class="text-blue-600 dark:text-blue-400">{{ $ledger->invoice->invoice_number }}</span>
                                                                <a target="_blank"
                                                                    href="{{ route('invoice.show', ['companyId' => $ledger->company_id, 'invoiceNumber' => $ledger->invoice->invoice_number]) }}"
                                                                    class="text-blue-500 hover:text-blue-700 ml-1">🔍</a>
                                                            @else
                                                                <span class="text-gray-400">N/A</span>
                                                            @endif
                                                        </p>
                                                    </td>
                                                    <td class="py-3 px-3 text-right text-red-600 dark:text-red-400 font-medium">
                                                        {{ number_format($ledger->debit, 2) }}
                                                    </td>
                                                    <td class="py-3 px-3 text-right text-green-600 dark:text-green-400 font-medium">
                                                        {{ number_format($ledger->credit, 2) }}
                                                    </td>
                                                    <td class="py-3 px-3 text-right font-bold text-gray-800 dark:text-gray-200">
                                                        @if ($ledger->balance > 0)
                                                            -{{ number_format($ledger->balance, 2) }}
                                                        @elseif ($ledger->balance < 0)
                                                            {{ number_format(abs($ledger->balance), 2) }}
                                                        @else
                                                            {{ number_format($ledger->balance, 2) }}
                                                        @endif
                                                    </td>
                                                    <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400">
                                                        {{ $ledger->name ?? 'N/A' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($ledgers as $ledger)
                                        <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $ledger->transaction_date }}</p>
                                            <p class="text-sm text-gray-800 dark:text-gray-200 mt-1">{{ $ledger->description }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                Ref: 
                                                @if (!empty($ledger->type_reference_id))
                                                    <span class="text-blue-600">{{ $ledger->referenceAccount->name ?? 'N/A' }}</span>
                                                @elseif ($ledger->invoice && $ledger->invoice->invoice_number)
                                                    <span class="text-blue-600">{{ $ledger->invoice->invoice_number }}</span>
                                                    <a target="_blank" href="{{ route('invoice.show', ['companyId' => $ledger->company_id, 'invoiceNumber' => $ledger->invoice->invoice_number]) }}" class="text-blue-500 ml-1">🔍</a>
                                                @else
                                                    <span class="text-gray-400">N/A</span>
                                                @endif
                                            </p>
                                            <div class="flex justify-between items-center mt-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                                                <div class="flex gap-4 text-sm">
                                                    <span class="text-red-600 font-medium">D: {{ number_format($ledger->debit, 2) }}</span>
                                                    <span class="text-green-600 font-medium">C: {{ number_format($ledger->credit, 2) }}</span>
                                                </div>
                                                <div class="text-right">
                                                    <p class="font-bold text-gray-800 dark:text-gray-200">
                                                        @if ($ledger->balance > 0)
                                                            -{{ number_format($ledger->balance, 2) }}
                                                        @elseif ($ledger->balance < 0)
                                                            {{ number_format(abs($ledger->balance), 2) }}
                                                        @else
                                                            {{ number_format($ledger->balance, 2) }}
                                                        @endif
                                                    </p>
                                                    <p class="text-xs text-gray-500">{{ $ledger->name ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        @else
                            <div class="p-8 text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400 mt-2">No transactions found.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 h-fit">
                    <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Payable Record
                    </h2>

                    @if ($errors->any())
                        <div class="mb-4 p-4 text-red-800 bg-red-100 dark:bg-red-900/30 dark:text-red-400 rounded-lg">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('payable-details.payable-store') }}" method="POST" class="space-y-6">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $companyId }}">

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Branch Name <span class="text-red-500">*</span>
                            </label>
                            <x-searchable-dropdown
                                name="branch_id"
                                :items="$branches->map(fn($b) => [
                                    'id' => $b->id,
                                    'name' => $b->name . ($b->address ? ' (' . $b->address . ')' : '')
                                ])->values()"
                                :selectedId="null"
                                placeholder="Select Branch" />
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Supplier Account <span class="text-red-500">*</span>
                            </label>
                            <x-searchable-dropdown
                                name="account_id"
                                :items="$accounts->map(fn($a) => ['id' => $a->id, 'name' => $a->name . ' (Level ' . $a->level . ')'])->values()"
                                :selectedId="null"
                                placeholder="Search Account" />
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Bank Account <span class="text-red-500">*</span>
                            </label>
                            <x-searchable-dropdown
                                name="bank_account"
                                :items="$bankAccounts->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values()"
                                :selectedId="null"
                                placeholder="Select Bank Account" />
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Transaction Date <span class="text-red-500">*</span>
                            </label>
                            <input type="datetime-local" name="transaction_date"
                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                required>
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="description" placeholder="Enter payment description"
                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                required>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                    Amount (KWD) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-sm">KWD</span>
                                    <input type="number" step="0.001" value="0.000" name="amount"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg pl-12 pr-3 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                        required>
                                </div>
                            </div>

                            <div>
                                <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                    Type <span class="text-red-500">*</span>
                                </label>
                                <x-searchable-dropdown
                                    name="type"
                                    :items="collect([
                                        ['id' => 'payable', 'name' => 'Payable'],
                                        ['id' => 'expenses', 'name' => 'Expenses']
                                    ])"
                                    selectedId="payable"
                                    selectedName="Payable"
                                    placeholder="Select Type" />
                            </div>
                        </div>

                        <button type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white py-3 rounded-lg text-sm font-medium transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Submit Payable Record
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>