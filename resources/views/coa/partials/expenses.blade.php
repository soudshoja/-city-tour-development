<!-- Expenses Section Overview -->
<div
    class="ExpensesToggleButton main-container cursor-pointer items-center justify-between bg-white p-4  flex w-full rounded-lg BoxShadow border border-gray-200">
    <div class="flex items-center space-x-3">
        <!-- Expenses Icon -->
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M6 11C6 8.17157 6 6.75736 6.87868 5.87868C7.75736 5 9.17157 5 12 5H15C17.8284 5 19.2426 5 20.1213 5.87868C21 6.75736 21 8.17157 21 11V16C21 18.8284 21 20.2426 20.1213 21.1213C19.2426 22 17.8284 22 15 22H12C9.17157 22 7.75736 22 6.87868 21.1213C6 20.2426 6 18.8284 6 16V11Z"
                stroke="#AF1740" stroke-width="1.5" />
            <path opacity="0.5"
                d="M6 19C4.34315 19 3 17.6569 3 16V10C3 6.22876 3 4.34315 4.17157 3.17157C5.34315 2 7.22876 2 11 2H15C16.6569 2 18 3.34315 18 5"
                stroke="#AF1740" stroke-width="1.5" />
        </svg>
        <h3 class="font-semibold text-lg text-[#AF1740]">Expenses</h3>
    </div>
    <!-- Status Badge -->
    <span class="px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full">Code</span>

    <!-- Integration Type -->
    <span class="text-gray-500 text-sm">Actual Balance</span>

    <!-- Expand/Collapse Button -->
    <button class="hover:text-gray-700">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
        </svg>
    </button>
</div>

<!-- ./Expenses Section Overview -->



<div id="ExpensesDetails" class="rounded-lg shadow-sm">
    <div class="mb-5" x-data="{ openLevels: {} }">
        <!-- Vertical layout for top-level expenses -->
        <div>
            <ul class="space-y-2 w-full">
                <!-- Level 2 - Top-Level Expenses as Tabs -->
                @foreach ($expenses as $expense)
                <li class="relative w-full">
                    <a href="javascript:;"
                        class="flex items-center justify-between px-4 py-2 w-full  transition-all"
                        :class="{'border-l-4 expenseBorder expenseText': openLevels['{{ $expense->id }}']}"
                        @click="openLevels = { ['{{ $expense->id }}']: !openLevels['{{ $expense->id }}'] }">
                        <span>{{ $expense->name }}</span>
                        <svg x-show="!openLevels['{{ $expense->id }}']" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <svg x-show="openLevels['{{ $expense->id }}']" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>

                    </a>

                    <!-- Level 3 - Nested content opens below each top-level expense when clicked -->
                    <div x-show="openLevels['{{ $expense->id }}']"
                        class="mt-2 space-y-2 bg-gray-100 rounded-lg p-4 w-full">
                        @foreach ($expense->level3expenses as $level3expense)
                        <a href="javascript:;"
                            class="flex items-center justify-between px-4 py-2 w-full transition-all"
                            :class="{'border-l-4 expenseBorder expenseText': openLevels['{{ $expense->id }}']}"
                            @click="openLevels['{{ $level3expense->id }}'] = !openLevels['{{ $level3expense->id }}']">
                            <span>{{ $level3expense->name }}</span>
                            <svg x-show="!openLevels['{{ $level3expense->id }}']" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <svg x-show="openLevels['{{ $level3expense->id }}']" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                        </a>




                        <!-- Level 4 - Nested under each level 3 expense -->
                        <div x-show="openLevels['{{ $level3expense->id }}']" class="ml-6 space-y-2 mt-2">
                            @if ($level3expense->level4expenses->isEmpty())
                            <p class="text-danger">No expenses here yet!</p>
                            @else
                            @foreach ($level3expense->level4expenses as $level4expense)
                            <div
                                class="flex items-center justify-between bg-white p-4 rounded-lg shadow-sm border border-gray-200 w-full">
                                <!-- Name -->
                                <span class="text-gray-800 font-medium">{{ $level4expense->name }}</span>

                                <!-- Code -->
                                <span
                                    class="px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full editable-cell"
                                    contenteditable="true">
                                    {{ $level4expense->code }}</span>

                                <!-- Actual Balance -->
                                <span class="text-gray-500 text-sm editable-cell" contenteditable="true">
                                    {{number_format($level4expense->actual_balance, 2) }}</span>

                                <!-- Action Icons -->
                                <div class="flex items-center space-x-3 text-gray-500">
                                    <!-- Icon (Delete) -->
                                    <button class="hover:text-red-500">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path
                                                d="M9.878 4.25C10.187 3.375 11.022 2.75 12 2.75c.978 0 1.813.625 2.122 1.5.138.391.567.596.957.458s.675-.567.537-.957c-.514-1.456-1.902-2.5-3.536-2.5-1.634 0-3.022 1.044-3.536 2.5-.138.391.067.82.457.957.391.139.82-.066.957-.457z"
                                                fill="red" />
                                            <path
                                                d="M2.75 6c0-.414.336-.75.75-.75h17c.414 0 .75.336.75.75 0 .414-.336.75-.75.75H3.5a.75.75 0 01-.75-.75z"
                                                fill="red" />
                                            <path
                                                d="M5.117 7.752c.413-.028.77.284.798.698L6.375 15.35c.09 1.348.154 2.286.295 2.992.136.684.326 1.046.599 1.302.273.255.647.421 1.34.511.713.093 1.653.095 3.003.095h.773c1.35 0 2.29-.002 3.004-.095.692-.09 1.066-.256 1.34-.511.273-.256.463-.618.599-1.302.141-.706.205-1.644.295-2.992L18.085 8.45c.028-.414.385-.726.798-.698.413.027.726.384.698.798L19.118 15.5c-.085 1.283-.154 2.319-.317 3.132-.168.845-.454 1.551-1.046 2.105-.592.554-1.316.792-2.171.904-.822.107-1.86.107-3.145.107H11.56c-1.285 0-2.323 0-3.146-.107-.855-.112-1.579-.35-2.171-.904-.592-.554-.878-1.26-1.046-2.105-.163-.813-.232-1.849-.317-3.132l-.462-6.952c-.027-.414.285-.771.698-.798z"
                                                fill="red" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            @endforeach
                            @endif

                        </div>
                        @endforeach
                    </div>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<script>
    // Toggle Expenses Details
    const ExpensesToggleButton = document.querySelectorAll('.ExpensesToggleButton');
    const contentExpensesDiv = document.getElementById('ExpensesDetails');

    contentExpensesDiv.style.display = 'none'; // Initially hide

    ExpensesToggleButton.forEach(button => {
        button.addEventListener('click', () => {
            contentExpensesDiv.style.display = (contentExpensesDiv.style.display === 'none') ? 'block' :
                'none';
        });
    });





    function saveCode(income, value) {
        if (value.trim() === '') {
            showMessage('Code cannot be empty!');
            return; // Prevent saving if the input is empty
        }

        // Make an AJAX request to save the new code
        fetch(`/updateCode/${income}`, { // Update with your save route
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' // Include CSRF token for security
                },
                body: JSON.stringify({
                    code: value
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                showMessage(data.message);
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
    }
</script>