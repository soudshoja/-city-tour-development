<!-- Expenses Section Overview -->
<div
    class="ExpensesToggleButton main-container cursor-pointer items-center justify-between p-4  flex w-full rounded-lg BoxShadow coa-partials">

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
    <span class="px-2 py-1 text-xs font-semibold text-red-600 bg-red-100 rounded-full">Code</span>

    <!-- Integration Type -->
    <span class="font-semibold text-lg text-[#AF1740]">Actual Balance</span>

    <!-- Expand/Collapse Button -->
    <button class="hover:text-gray-700">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
        </svg>
    </button>
</div>

<!-- ./Expenses Section Overview -->
<div id="expensesDetails" class="rounded-lg shadow-sm" x-data="expenseManager()">
    <div class="mb-5">
        <!-- Vertical layout for top-level expenses -->
        <ul class="space-y-2 w-full">
            <!-- Level 2 - Top-Level expenses as Tabs -->

            @foreach ($expenses as $index => $expense)
            <li
                x-data="{showAddCategoryForm: false}"
                class="level2 relative w-full flex items-center">
                <a
                    href="javascript:;"
                    class="flex items-center px-4 py-2 w-full hover:text-[#AF1740] transition-all"
                    :class="{'border-l-4 border-[#AF1740] text-[#000000]': openLevels['{{ $expense->id }}']} "
                    @click="openLevels['{{ $expense->id }}'] = !openLevels['{{ $expense->id }}']">


                    <span class="flex-1">{{ $expense->name }}</span>
                    <div class="flex items-center gap-2">

                        <svg x-show="!openLevels['{{ $expense->id }}']" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
                        </svg>
                        <svg x-show="openLevels['{{ $expense->id }}']" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
                        </svg>

                        <!-- Add "+" icon button for each category -->
                        <button @click.stop="showAddCategoryForm = !showAddCategoryForm"
                            class="text-green-600 hover:text-green-800">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </a>
                <!-- Add form for each category -->
                <div x-data="{
                              newCategoryName: '',
                              newCategoryCode: '',
                              newCategoryLevel: {{ $expense->level }},
                              newCategoryParentId: {{ $expense->id }},
                              variance: 0,
                              budgetBalance: 0,
                              actualBalance: 0,
                          }"
                    x-show="showAddCategoryForm" class="flex items-center ml-2">
                    <input type="text" x-model="newCategoryName" placeholder="Enter Category Name" class="border p-2 rounded">
                    <input type="text" x-model="newCategoryCode" placeholder="Enter Code" class="border p-2 rounded ml-2">
                    <input type="hidden" x-model="newCategoryLevel">
                    <input type="hidden" x-model="newCategoryParentId">
                    <input type="hidden" x-model="variance">
                    <input type="hidden" x-model="budgetBalance">
                    <input type="hidden" x-model="actualBalance">
                    <button @click="addCategory" type="submit" class="ml-2 text-green-600 hover:text-green-800">
                        Add
                    </button>
                    <button @click="showAddCategoryForm = false" class="ml-2 text-red-600 hover:text-red-800">
                        Close
                    </button>
                </div>

            </li>

            <!-- Level 3 - Nested content opens below each top-level expense when clicked -->
            <div x-show="openLevels['{{ $expense->id }}']"
                class="mt-2 space-y-2 p-4 w-full">
                @if ($expense->level3expenses->isEmpty())
                <p class="text-danger">No expense here yet!</p>
                @endif
                @foreach ($expense->level3expenses as $level3expense)
                <div x-data="{ showAddCategoryForm: false }" class="level3 relative w-full flex items-center">
                    <a href="javascript:;"
                        class="flex items-center px-4 py-2 w-full hover:text-[#AF1740] transition-all gap-x-2"
                        :class="{'border-l-4 border-[#af174080] text-[#000000]': openLevels['{{ $level3expense->id }}']}"
                        @click="openLevels['{{ $level3expense->id }}'] = !openLevels['{{ $level3expense->id }}']">

                        <!-- Text on the left -->
                        <span class="flex-1">{{ $level3expense->name }}</span>
                        <div class="flex items-center gap-2">

                            <!-- Icons -->
                            <svg x-show="!openLevels['{{ $level3expense->id }}']" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
                            </svg>
                            <svg x-show="openLevels['{{ $level3expense->id }}']" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
                            </svg>

                            <!-- Add "+" icon button for each level 3 category -->
                            <button @click.stop="showAddCategoryForm = !showAddCategoryForm"
                                class="text-green-600 hover:text-green-800">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </a>



                    <!-- Add form for each level 3 category -->
                    <div x-data="{
                              newCategoryName: '',
                              newCategoryCode: '',
                              newCategoryLevel: {{ $level3expense->level }},
                              newCategoryParentId: {{ $level3expense->id }},
                              variance: 0,
                              budgetBalance: 0,
                              actualBalance: 0,
                          }"
                        x-show="showAddCategoryForm" class="flex items-center ml-2">
                        <input type="text" x-model="newCategoryName" placeholder="Enter Category Name" class="border p-2 rounded">
                        <input type="text" x-model="newCategoryCode" placeholder="Enter Code" class="border p-2 rounded ml-2">
                        <input type="hidden" x-model="newCategoryLevel">
                        <input type="hidden" x-model="newCategoryParentId">
                        <input type="hidden" x-model="variance">
                        <input type="hidden" x-model="budgetBalance">
                        <input type="hidden" x-model="actualBalance">
                        <button @click="addCategory" type="submit" class="ml-2 text-green-600 hover:text-green-800">
                            Add
                        </button>
                        <button @click="showAddCategoryForm = false" class="ml-2 text-red-600 hover:text-red-800">
                            Close
                        </button>
                    </div>
                </div>

                <!-- Level 4 - Nested under each level 3 expense -->
                <div x-show="openLevels['{{ $level3expense->id }}']" class="ml-6 space-y-2 mt-2">
                    @if ($level3expense->level4expenses->isEmpty())
                    <p class="text-danger">No expense here yet!</p>
                    @endif

                    @foreach ($level3expense->level4expenses as $level4expense)
                    <div
                        class="level4 flex items-center justify-between p-4 rounded-lg shadow-sm  w-full">
                        <!-- Name -->
                        <span class="text-gray-800 dark:text-gray-600 font-medium">{{ $level4expense->name }}</span>

                        <input type="text" name="code"
                            class="text-center border-none focus:outline-none focus:ring-0 px-2 py-1 text-xs font-semibold text-red-600 bg-red-100 rounded-full editable-cell"
                            value="{{ $level4expense->code }}" id="editable-code-{{ $level4expense->id }}"
                            onblur="saveCode({{ $level4expense->id }}, this.value)"
                            onkeypress="checkEnter(event, {{ $level4expense->id }}, this.value)">

                        <!-- Actual Balance -->
                        <div @click="window.location='{{ route('coa.transaction') }}?level4Id={{ $level4expense->id }}'">
                            <input type="text" name="actual_balance"
                                class="text-center border-none focus:outline-none focus:ring-0 px-2 py-1 text-xs font-semibold text-red-600 bg-red-100 rounded-full"
                                value="{{ number_format($level4expense->actual_balance, 2) }}" readonly
                                style="pointer-events: none; cursor: default;">
                        </div>

                        <!-- Action Icons -->
                        <div class="flex items-center space-x-3 text-gray-500">
                            <button class="hover:text-blue-500">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" fill="blue" />
                                </svg>
                            </button>

                            <!-- Icon  (Delete) -->
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
                </div>
                @endforeach
            </div>
            @endforeach

        </ul>

    </div>
</div>



<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('expenseManager', () => ({
            showAddCategoryFromExpenses: false,
            newCategoryName: '',
            newCategoryCode: '', // Add this line
            newCategoryLevel: '',
            newCategoryParentId: '',
            variance: '',
            budgetBalance: '',
            actualBalance: '',

            openLevels: {},
            expenses: @json($expenses), // Initialize the expenses array with the existing expenses
            addCategory() {
                if (this.newCategoryName.trim() === '' || this.newCategoryCode.trim() === '') {
                    alert('Category name and code cannot be empty');
                    return;
                }
                //   console.log(this.newCategoryLevel);

                fetch('/addCategory', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            name: this.newCategoryName,
                            code: this.newCategoryCode,
                            level: this.newCategoryLevel + 1,
                            parent_id: this.newCategoryParentId,
                            variance: this.variance,
                            budget_balance: this.budgetBalance,
                            actual_balance: this.actualBalance,
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Add the new category to the list without reloading the page
                            this.expenses.push({
                                id: data.id,
                                name: this.newCategoryName,
                                code: this.newCategoryCode,
                                level: this.newCategoryLevel,
                                parent_id: this.newCategoryParentId,
                                variance: this.variance,
                                level3expenses: [] // Initialize with an empty array for level 3 expenses
                            });
                            this.newCategoryName;
                            this.newCategoryCode;
                            this.newCategoryLevel;
                            this.newCategoryParentId;
                            this.variance;
                            this.budgetBalance; // Reset budgetBalance
                            this.actualBalance; // Reset actualBalance
                            this.showAddCategoryFromExpenses = false;
                        } else {
                            alert('Failed to add category');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    }).finally(() => {
                        console.log('Add category request completed');
                        window.location.reload();
                    });

            }
        }));
    });
</script>
<script>
    // Toggle Expenses Details
    const ExpensesToggleButton = document.querySelectorAll('.ExpensesToggleButton');
    const contentExpensesDiv = document.getElementById('expensesDetails');

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