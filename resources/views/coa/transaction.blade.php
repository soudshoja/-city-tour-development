<x-app-layout>


    <!-- Page Heading -->
    <div class="flex justify-between items-center gap-5 my-3">
        <!-- title -->
        <div class="flex items-center space-x-4">
            <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center heartbeat">
                <!-- SVG Icon -->
                <a href="javascript:history.back()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                        <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                    </svg>
                </a>
            </div>
            <h2 class="text-3xl font-bold dark:text-white">All Transaction Records</h2>
        </div>
        <!--/ title -->

        <!-- Filter, Date Picker, Export Button -->
        <div class="flex items-center space-x-4">

            <!-- Date Picker -->
            <div class="relative">
                <input id="datepicker" type="text" placeholder="Select rang date"
                    class="w-80 px-3 py-2 text-gray-800 bg-transparent border border-[#1e40af] rounded-lg BoxShadow
                           dark:bg-gray-700 dark:text-white dark:border-gray-600" style="outline: none;">

            </div>
            <script>
                // Initialize Flatpickr
                flatpickr("#datepicker", {
                    mode: "range", // Select a range of dates
                    dateFormat: "F j, Y", // Date format
                    defaultDate: ["2023-03-11", "2023-03-18"], // Default date
                    onChange: function(selectedDates, dateStr, instance) {
                        console.log("Selected Dates: ", selectedDates);
                    }
                });
            </script>
            <!-- ./Date Picker -->


            <!-- Filter Button -->
            <button class="dark:text-white flex px-5 py-3 gap-2 city-light-yellow rounded-lg BoxShadow items-center text-xs md:text-sm">
                <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                    <path fill="currentColor" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3"></path>
                </svg>
                <span class="text-xs md:text-sm dark:text-white">Filters</span>
            </button>



            <!-- Export Button -->
            <button class="dark:text-white flex px-5 py-3 gap-2 city-light-yellow rounded-lg BoxShadow items-center text-xs md:text-sm">
                <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1"></path>
                </svg>
                <span class="text-xs md:text-sm">Export</span>
            </button>
        </div>
        <!-- ./Filter, Date Picker, Export Button -->
    </div>
    <!-- ./Page Heading -->

    <!-- page content -->
    <div class="panel overflow-y-auto max-h-screen">
        @foreach ($transactionsByDate as $date => $transactions)
        <div class="date-group mb-6">
            <!-- Date Heading -->
            <h2 class="text-lg font-bold border-b-2 border-gray-300 mb-2">
                {{ \Carbon\Carbon::parse($date)->format('d F Y') }}
            </h2>

            @if ($transactions->isEmpty())
            <p class="text-gray-500">No transactions available.</p>
            @else
            <!-- Transactions List -->
            <ul class="transaction-list space-y-4">
                @foreach ($transactions as $transaction)
                <li class="transaction-item flex items-center justify-between p-4 rounded-lg bg-gray-50 shadow-sm">
                    <div class="icon bg-gray-200 rounded-full p-2">
                        <!-- Show appropriate icon for credit/debit -->
                        @if ($transaction->credit > 0)
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 512 512">
                            <path d="M448 224H288V64h-64v160H64v64h160v160h64V288h160z" fill="#00ab55" />
                        </svg>
                        @else
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 512 512">
                            <path d="M64 224h384v64H64z" fill="#e11d48" />
                        </svg>
                        @endif
                    </div>
                    <div class="transaction-details flex-grow px-4">
                        <p class="text-gray-800 font-semibold">{{ $transaction->description ?? 'N/A' }}</p>
                    </div>
                    <div class="transaction-amount text-right">
                        @if ($transaction->credit > 0)
                        <span class="text-green-600 font-bold">+ {{ number_format($transaction->credit, 2) }} KWD</span>
                        @else
                        <span class="text-red-600 font-bold">- {{ number_format($transaction->debit, 2) }} KWD</span>
                        @endif
                    </div>
                </li>
                @endforeach
            </ul>
            @endif
        </div>
        @endforeach
    </div>
    <!-- ./page content -->



</x-app-layout>