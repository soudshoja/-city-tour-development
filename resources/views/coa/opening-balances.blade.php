<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <a href="{{ route('coa.index') }}" class="text-gray-600 hover:text-gray-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h2 class="text-3xl font-bold">Opening Balances</h2>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-400 rounded-r-lg">
            <p class="text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-400 rounded-r-lg">
            <p class="text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <div class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-400 rounded-r-lg">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3 text-sm">
                <p class="text-blue-700">
                    Enter opening balances as <strong>positive numbers</strong>. The system will automatically determine debit/credit based on account type.
                </p>
            </div>
        </div>
    </div>

    <form action="{{ route('coa.opening-balances.save') }}" method="POST">
        @csrf

        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-4">
                    <label class="font-medium text-gray-700">Opening Balance Date:</label>
                    <input type="date" name="opening_balance_date" required
                        value="{{ old('opening_balance_date', $openingBalanceDate?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                        class="border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>

                <div class="relative">
                    <input type="text" id="account-search" placeholder="Search accounts..."
                        class="w-64 border rounded-lg pl-10 pr-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            @php
                $typeColors = [
                    'Assets' => 'green',
                    'Liabilities' => 'yellow',
                    'Income' => 'blue',
                    'Expenses' => 'red',
                    'Equity' => 'purple',
                ];
            @endphp

            @forelse ($accounts as $rootName => $groupedAccounts)
                @php $color = $typeColors[$rootName] ?? 'gray'; @endphp
                <div class="mb-8 account-section">
                    <h3 class="text-xl font-semibold mb-4 pb-2 border-b-2 border-{{ $color }}-500 text-{{ $color }}-700">
                        {{ $rootName }}
                        <span class="text-sm font-normal text-gray-500 ml-2">({{ $groupedAccounts->count() }} accounts)</span>
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="text-left px-4 py-2 font-medium text-gray-600">Code</th>
                                    <th class="text-left px-4 py-2 font-medium text-gray-600">Account Name</th>
                                    <th class="text-left px-4 py-2 font-medium text-gray-600">Currency</th>
                                    <th class="text-right px-4 py-2 font-medium text-gray-600">Opening Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($groupedAccounts as $account)
                                    <tr class="border-b hover:bg-gray-50 account-row"
                                        data-code="{{ strtolower($account->code ?? '') }}"
                                        data-name="{{ strtolower($account->name) }}">
                                        <td class="px-4 py-3 text-gray-600">{{ $account->code ?? '-' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="font-medium">{{ $account->name }}</span>
                                            @if ($account->client_id || $account->agent_id || $account->branch_id)
                                                <span class="text-xs text-gray-500 ml-2">
                                                    @if ($account->client_id) (Client) @endif
                                                    @if ($account->agent_id) (Agent) @endif
                                                    @if ($account->branch_id) (Branch) @endif
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $account->currency ?? 'KWD' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <input type="number" step="0.001"
                                                name="balances[{{ $account->id }}]"
                                                value="{{ old('balances.' . $account->id, $account->opening_balance != 0 ? $account->opening_balance : '') }}"
                                                placeholder="0.000"
                                                class="w-40 text-right border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500">
                    No accounts found. Please create accounts first.
                </div>
            @endforelse
        </div>

        @if ($accounts->isNotEmpty())
            <div class="sticky bottom-0 bg-white border-t shadow-lg p-4 flex justify-end gap-4">
                <a href="{{ route('coa.index') }}" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                    Save Opening Balances
                </button>
            </div>
        @endif
    </form>

    <script>
        document.getElementById('account-search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.account-row');
            const sections = document.querySelectorAll('.account-section');

            rows.forEach(row => {
                const code = row.dataset.code || '';
                const name = row.dataset.name || '';
                const matches = code.includes(searchTerm) || name.includes(searchTerm);
                row.style.display = matches ? '' : 'none';
            });

            sections.forEach(section => {
                const visibleRows = section.querySelectorAll('.account-row[style=""], .account-row:not([style])');
                const hasVisible = Array.from(section.querySelectorAll('.account-row')).some(row => row.style.display !== 'none');
                section.style.display = hasVisible ? '' : 'none';
            });
        });
    </script>
</x-app-layout>
