<x-app-layout>
    <div x-data="{ showFilter: false }">
        <!-- Page Heading -->
        <div class="flex justify-between items-center gap-5 my-3 mb-4">
            <div class="flex items-center space-x-4">
                <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center heartbeat">
                    <a href="javascript:history.back()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                            <path fill="#FFC107" fill-rule="evenodd"
                                d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                        </svg>
                    </a>
                </div>
                <h2 class="text-3xl font-bold dark:text-white">All Transaction Records</h2>
            </div>

            <!-- Filter + Export -->
            <div class="flex items-center space-x-4 mb-6">
                <!-- Filter Button & Modal -->
                <div class="relative" @click.outside="showFilter = false">
                    <button @click="showFilter = !showFilter"
                        class="dark:text-white flex px-5 py-3 gap-2 city-light-yellow rounded-lg BoxShadow items-center text-xs md:text-sm">
                        <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                            <path fill="currentColor"
                                d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                        </svg>
                        <span class="text-xs md:text-sm dark:text-white">Filters</span>
                    </button>

                    <div x-show="showFilter" x-transition
                        class="absolute right-0 mt-2 w-72 bg-white shadow-md p-4 rounded-lg border border-gray-300 z-50">
                        <form method="GET" action="{{ route('coa.transaction') }}" class="flex flex-col space-y-4">
                            <div class="flex items-center space-x-2">
                                <label for="start_date" class="text-sm">Start Date</label>
                                <input type="date" name="start_date" class="form-control p-2 border rounded-md w-full" value="{{ request('start_date') }}">
                            </div>
                            <div class="flex items-center space-x-2">
                                <label for="end_date" class="text-sm">End Date</label>
                                <input type="date" name="end_date" class="form-control p-2 border rounded-md w-full" value="{{ request('end_date') }}">
                            </div>
                            <div class="flex justify-between space-x-2">
                                <a href="{{ route('coa.transaction') }}" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 text-sm">
                                    Reset
                                </a>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-full">Apply Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Export Button -->
                <button class="dark:text-white flex px-5 py-3 gap-2 city-light-yellow rounded-lg BoxShadow items-center text-xs md:text-sm">
                    <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="currentColor"
                            d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                    </svg>
                    <span class="text-xs md:text-sm">Export</span>
                </button>
            </div>
        </div>

        <!-- Transaction List -->
        <div class="panel overflow-y-auto max-h-screen mt-4">
            @php
            $hasData = false;
            foreach ($transactionsByDate->items() as $group) {
            if (!$group->isEmpty()) {
            $hasData = true;
            break;
            }
            }
            @endphp

            @if (!$hasData)
            <div class="text-center text-gray-600 py-20">
                <svg class="mx-auto w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-lg font-semibold">No transactions found</h3>
                <p class="text-sm text-gray-500 mt-1">Try adjusting your filter or date range.</p>
                <a href="{{ route('coa.transaction') }}" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-full hover:bg-blue-700">Reset Filter</a>
            </div>
            @else
            @foreach ($transactionsByDate->items() as $date => $transactions)
            <div class="date-group mb-6" x-data="{ open: false, descriptionOpen: null, showMenu: null }">
                <div class="flex items-center space-x-4">
                    <button @click="open = !open"
                        class="text-white hover:text-gray-700 flex items-center justify-center w-4 h-4 rounded-full mb-2"
                        :class="open ? 'bg-red-600' : 'bg-blue-600'">
                        <span x-show="!open" class="text-xs font-bold">+</span>
                        <span x-show="open" class="text-xs font-bold">-</span>
                    </button>
                    <h2 class="text-lg font-bold mb-2 cursor-pointer" @click="open = !open"
                        :class="open ? 'text-blue-600' : 'text-gray-700'">
                        {{ \Carbon\Carbon::parse($date)->format('d F Y') }}
                    </h2>
                </div>
                <div class="border-b-2 border-gray-300 mb-2" :class="open ? 'border-blue-600' : ''"></div>
                <div x-show="open" x-transition>
                    @if ($transactions->isEmpty())
                    <p class="text-gray-500">No transactions available.</p>
                    @else
                    <ul class="transaction-list space-y-4">
                        @foreach ($transactions as $transaction)
                        <li class="transaction-item flex items-center justify-between p-4 rounded-lg bg-gray-50 shadow-sm">
                            <div class="text-sm icon bg-gray-200 rounded-full p-2 w-8 h-8 flex items-center justify-center text-center">
                                @if ($transaction->credit > 0)
                                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 512 512">
                                    <path d="M448 224H288V64h-64v160H64v64h160v160h64V288h160z" fill="#00ab55" />
                                </svg>
                                @else
                                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 512 512">
                                    <path d="M64 224h384v64H64z" fill="#e11d48" />
                                </svg>
                                @endif
                            </div>
                            <div class="transaction-details flex-grow px-4" x-data="{ openSections: [] }">
                                <p class="text-gray-800 font-semibold cursor-pointer text-grey-500 hover:text-blue-500"
                                    @click="openSections.includes({{ $transaction->id }}) ? openSections = openSections.filter(id => id !== {{ $transaction->id }}) : openSections.push({{ $transaction->id }})">
                                    {{ $transaction->description ?? 'N/A' }}
                                </p>
                                <div x-show="openSections.includes({{ $transaction->id }})" x-transition class="mt-2 space-y-2">
                                    <p class="text-gray-600 text-sm">Transaction ID: {{ $transaction->id }}</p>
                                    <p class="text-gray-600 text-sm">Type: {{ ucwords($transaction->transaction_type ?? 'N/A') }}</p>
                                    <p class="text-gray-600 text-sm">Date: {{ $transaction->created_at }}</p>
                                    @if ($transaction->payment)
                                    <p class="text-gray-600 text-sm">
                                        Payment Reference: 
                                        <a href="{{ route('payment.link.index') }}" class="text-blue-500 hover:underline">
                                            {{ $transaction->payment_reference }}
                                        </a>
                                    </p>
                                    @endif
                                </div>
                            </div>
                            <div x-data="{ showMenu: null }" class="relative" @click.outside="showMenu = null">
                                @unless($transaction->journalEntries->isEmpty())
                                <button @click="showMenu = (showMenu === {{ $transaction->id }} ? null : {{ $transaction->id }})"
                                    class="text-black hover:text-gray-700 pl-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M12 6h.01M12 12h.01M12 18h.01" />
                                    </svg>
                                </button>
                                <div x-show="showMenu === {{ $transaction->id }}" x-transition
                                    class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 border border-gray-200 z-10">
                                    <a href="{{ route('journal-entries.index', $transaction->id) }}"
                                        class="text-center block px-4 py-2 text-gray-700 hover:bg-blue-200">
                                        View Ledger
                                    </a>
                                </div>
                                @endunless
                            </div>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>
            @endforeach
            @if ($transactionsByDate->total() > $transactionsByDate->perPage())
            <div class="mt-6">
                {{ $transactionsByDate->appends(request()->query())->links() }}
            </div>
            @endif

            @endif
        </div>
    </div>

    <!-- Javascript -->
    <script>
        flatpickr("#datepicker", {
            mode: "single",
            dateFormat: "d F Y",
            onChange: function(selectedDates, dateStr, instance) {
                const selectedDate = selectedDates[0] ? selectedDates[0].toISOString().split('T')[0] : '';
                if (selectedDate) {
                    window.location.href = `?date=${selectedDate}`;
                }
            }

        });
    </script>
</x-app-layout>