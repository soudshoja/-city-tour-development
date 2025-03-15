  <!-- Income Section overview -->
  <div
      class="IncomeToggleButton main-container cursor-pointer items-center justify-between p-4  flex w-full rounded-lg BoxShadow coa-partials">
      <div class="flex items-center space-x-3 ">

          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path opacity="0.5"
                  d="M22 12C22 13.9778 21.4135 15.9112 20.3147 17.5557C19.2159 19.2002 17.6541 20.4819 15.8268 21.2388C13.9996 21.9957 11.9889 22.1937 10.0491 21.8079C8.10929 21.422 6.32746 20.4696 4.92893 19.0711C3.53041 17.6725 2.578 15.8907 2.19215 13.9509C1.80629 12.0111 2.00433 10.0004 2.7612 8.17317C3.51808 6.3459 4.79981 4.78412 6.4443 3.6853C8.08879 2.58649 10.0222 2 12 2"
                  stroke="#1e40af" stroke-width="1.5" stroke-linecap="round" />
              <path d="M15 12L12 12M12 12L9 12M12 12L12 9M12 12L12 15" stroke="#1e40af" stroke-width="1.5"
                  stroke-linecap="round" />
              <path d="M14.5 2.31494C18.014 3.21939 20.7805 5.98588 21.685 9.4999" stroke="#1e40af" stroke-width="1.5"
                  stroke-linecap="round" />
          </svg>
          <h3 class="font-semibold text-lg text-[#1e40af]">Income</h3>
      </div>
      <!-- Status Badge -->
      <span class="px-2 py-1 text-xs font-semibold text-blue-600 bg-blue-100 rounded-full">Code</span>

      <!-- Integration Type -->
      <span class="font-semibold text-lg text-[#1e40af]">Actual Balance</span>

      <button class="hover:text-gray-700">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round" />
              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white"/>
          </svg>
      </button>
  </div>


 <!-- incomes Details -->
<div id="incomesDetails" class="rounded-lg shadow-sm" x-data="incomeManager()">
    <div class="mb-5">
        <!-- Vertical layout for top-level incomes -->
        <ul class="space-y-2 w-full">
            <!-- Level 2 - Top-Level incomes as Tabs -->

            @foreach ($incomes as $index => $income)
            <li x-data="{ showAddCategoryForm: false }" class="relative w-full flex items-center">
                <a href="javascript:;"
                    class="flex items-center px-4 py-2 w-full hover:text-[#1e40af] transition-all"
                    :class="{'border-l-4 border-[#1e40af] text-[#000000]': openLevels['{{ $income->id }}']}"
                    @click="openLevels['{{ $income->id }}'] = !openLevels['{{ $income->id }}']">

                    <span class="flex-1">{{ $income->name }}</span>
                    <div class="flex items-center gap-2">

                    <svg x-show="!openLevels['{{ $income->id }}']" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
                    </svg>
                    <svg x-show="openLevels['{{ $income->id }}']" width="24" height="24" viewBox="0 0 24 24"
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
                        newCategoryLevel: {{ $income->level }},
                        newCategoryParentId: {{ $income->id }},
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

            <!-- Level 3 - Nested content opens below each top-level income when clicked -->
            <div x-show="openLevels['{{ $income->id }}']" class="mt-2 space-y-2 p-4 w-full">
                @if ($income->level3income->isEmpty())
                <p class="text-danger">No income here yet!</p>
                @endif
                @foreach ($income->level3income as $level3income)
                <div x-data="{ showAddCategoryForm: false }" class="relative w-full flex items-center">
                    <a href="javascript:;"
                        class="flex items-center px-4 py-2 w-full hover:text-[#1e40af] transition-all gap-x-2"
                        :class="{'border-l-4 border-[#1e40af80] text-[#000000]': openLevels['{{ $level3income->id }}']}"
                        @click="openLevels['{{ $level3income->id }}'] = !openLevels['{{ $level3income->id }}']">
                        
                        <!-- Text on the left -->
                        <span class="flex-1">{{ $level3income->name }}</span>
                        <div class="flex items-center gap-2">

                        <!-- Icons -->
                        <svg x-show="!openLevels['{{ $level3income->id }}']" width="24" height="24"
                            viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
                        </svg>
                        <svg x-show="openLevels['{{ $level3income->id }}']" width="24" height="24"
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
                            newCategoryLevel: {{ $level3income->level }},
                            newCategoryParentId: {{ $level3income->id }},
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

                <!-- Level 4 - Nested under each level 3 income -->
                <div x-show="openLevels['{{ $level3income->id }}']" class="ml-6 space-y-2 mt-2">
                    @if ($level3income->level4incomes->isEmpty())
                    <p class="text-danger">No income here yet!</p>
                    @endif

                    @foreach ($level3income->level4incomes as $level4income)
                    <div class="flex items-center justify-between p-4 rounded-lg shadow-sm w-full">
                        <!-- Name -->
                        <span class="text-gray-800 dark:text-gray-600 font-medium">{{ $level4income->name }}</span>

                        <input type="text" name="code"
                            class="text-center border-none focus:outline-none focus:ring-0 px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full editable-cell"
                            value="{{ $level4income->code }}" id="editable-code-{{ $level4income->id }}"
                            onblur="saveCode({{ $level4income->id }}, this.value)"
                            onkeypress="checkEnter(event, {{ $level4income->id }}, this.value)">

                        <!-- Actual Balance -->
                        <div @click="window.location='{{ route('coa.transaction') }}?level4Id={{ $level4income->id }}'">
                            <input type="text" name="actual_balance"
                                class="text-center border-none focus:outline-none focus:ring-0 px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full"
                                value="{{ number_format($level4income->actual_balance, 2) }}" readonly
                                style="pointer-events: none; cursor: default;">
                        </div>

                        <!-- Action Icons -->
                        <div class="flex items-center space-x-3 text-gray-500">
                            <button class="hover:text-blue-500" >
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

  <!--./incomes Details-->
  <script>
      document.addEventListener('alpine:init', () => {
          Alpine.data('incomeManager', () => ({
              showAddCategoryForm: false,
              newCategoryName: '',
              newCategoryCode: '', // Add this line
              newCategoryLevel: '',
              newCategoryParentId: '',
              variance: '',
              budgetBalance: '',
              actualBalance: '',

              openLevels: {},
              incomes: @json($incomes), // Initialize the incomes array with the existing incomes
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
                              this.incomes.push({
                                  id: data.id,
                                  name: this.newCategoryName,
                                  code: this.newCategoryCode,
                                  level: this.newCategoryLevel,
                                  parent_id: this.newCategoryParentId,
                                  variance: this.variance,
                                  level3incomes: [] // Initialize with an empty array for level 3 incomes
                              });
                              this.newCategoryName;
                              this.newCategoryCode;
                              this.newCategoryLevel;
                              this.newCategoryParentId;
                              this.variance;
                              this.budgetBalance; // Reset budgetBalance
                              this.actualBalance; // Reset actualBalance
                              this.showAddCategoryForm = false;
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
      // Get the button and content div

      // ...existing code...

const IncomeToggleButton = document.querySelectorAll('.IncomeToggleButton');
const contentIncomeDiv = document.getElementById('incomesDetails'); // Corrected ID
// console.log(IncomeToggleButton);
// Initially hide the content div
contentIncomeDiv.style.display = 'none';

// Add click event listener to the button
IncomeToggleButton.forEach(function(button) {
    // console.log(button);
    button.addEventListener('click', function() {
        // Toggle the content div visibility
        if (contentIncomeDiv.style.display === 'none' || contentIncomeDiv.style.display === '') {
            contentIncomeDiv.style.display = 'block'; // Show the content
        } else {
            contentIncomeDiv.style.display = 'none'; // Hide the content
        }
    });
});
  </script>