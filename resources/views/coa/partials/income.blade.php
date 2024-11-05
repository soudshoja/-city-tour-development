  <!-- Income Section overview -->
  <div
      class="IncomeToggleButton main-container cursor-pointer items-center justify-between bg-white p-4  flex w-full rounded-lg shadow-sm border border-gray-200">
      <div class="flex items-center space-x-3 ">

          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path opacity="0.5"
                  d="M22 12C22 13.9778 21.4135 15.9112 20.3147 17.5557C19.2159 19.2002 17.6541 20.4819 15.8268 21.2388C13.9996 21.9957 11.9889 22.1937 10.0491 21.8079C8.10929 21.422 6.32746 20.4696 4.92893 19.0711C3.53041 17.6725 2.578 15.8907 2.19215 13.9509C1.80629 12.0111 2.00433 10.0004 2.7612 8.17317C3.51808 6.3459 4.79981 4.78412 6.4443 3.6853C8.08879 2.58649 10.0222 2 12 2"
                  stroke="#004C9E" stroke-width="1.5" stroke-linecap="round" />
              <path d="M15 12L12 12M12 12L9 12M12 12L12 9M12 12L12 15" stroke="#004C9E" stroke-width="1.5"
                  stroke-linecap="round" />
              <path d="M14.5 2.31494C18.014 3.21939 20.7805 5.98588 21.685 9.4999" stroke="#004C9E" stroke-width="1.5"
                  stroke-linecap="round" />
          </svg>
          <h3 class="font-semibold text-lg IncomeColor">Income</h3>
      </div>
      <!-- Status Badge -->
      <span class="px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full">Code</span>

      <!-- Integration Type -->
      <span class="text-gray-500 text-sm ">Actual Balance</span>

      <button class="hover:text-gray-700">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round" />
              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round" />
          </svg>
      </button>
  </div>





  <!-- Income Details -->
  <div id="IncomeDetails" class="rounded-lg shadow-sm ">
      <div class="mb-5" x-data="{ openLevels: {} }">
          <!-- Vertical layout for top-level Income -->
          <div>
              <ul class="space-y-2 w-full">
                  <!-- Level 2 - Top-Level Income as Tabs -->
                  @foreach ($incomes as $income)
                  <li class="relative w-full">
                      <a href="javascript:;"
                          class="flex items-center justify-between px-4 py-2 w-full hover:text-secondary transition-all"
                          :class="{'border-l-4 border-secondary text-secondary': openLevels['{{ $income->id }}']}"
                          @click="openLevels = { ['{{ $income->id }}']: !openLevels['{{ $income->id }}'] }">
                          <span>{{ $income->name }}</span>
                          <svg x-show="!openLevels['{{ $income->id }}']" width="24" height="24" viewBox="0 0 24 24"
                              fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                  stroke-linejoin="round" />
                              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round" />
                          </svg>
                          <svg x-show="openLevels['{{ $income->id }}']" width="24" height="24" viewBox="0 0 24 24"
                              fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                  stroke-linejoin="round" />
                              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round" />
                          </svg>

                      </a>

                      <!-- Level 3 - Nested content opens below each top-level income when clicked -->
                      <div x-show="openLevels['{{ $income->id }}']"
                          class="mt-2 space-y-2 bg-gray-100 rounded-lg p-4 w-full">
                          @if ($income->level3income->isEmpty())
                          <p class="text-red-500">No Income here yet!</p>
                          @endif
                          @foreach ($income->level3income as $level3income)
                          <a href="javascript:;"
                              class="flex items-center justify-between px-4 py-2 w-full hover:text-secondary transition-all"
                              :class="{'border-l-4 border-secondary text-secondary': openLevels['{{ $income->id }}']}"
                              @click="openLevels['{{ $level3income->id }}'] = !openLevels['{{ $level3income->id }}']">
                              <span>{{ $level3income->name }}</span>
                              <svg x-show="!openLevels['{{ $level3income->id }}']" width="24" height="24"
                                  viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                                  <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                              </svg>
                              <svg x-show="openLevels['{{ $level3income->id }}']" width="24" height="24"
                                  viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                                  <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                              </svg>

                          </a>




                          <!-- Level 4 - Nested under each level 3 income -->
                          <div x-show="openLevels['{{ $level3income->id }}']" class="ml-6 space-y-2 mt-2">
                              @if ($level3income->level4incomes->isEmpty())
                              <p class="text-red-500">No Income here yet!</p>
                              @endif
                              @foreach ($level3income->level4incomes as $level4income)
                              <div
                                  class="flex items-center justify-between bg-white p-4 rounded-lg shadow-sm border border-gray-200 w-full">
                                  <!-- Name -->
                                  <span class="text-gray-800 font-medium">{{ $level4income->name }}</span>

                                  <!-- code -->
                                  <span
                                      class="px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full editable-cell"
                                      contenteditable="true">
                                      {{ $level4income->code }}</span>

                                  <!-- Actual Balance -->
                                  <span class="text-gray-500 text-sm editable-cell" contenteditable="true">
                                      {{$level4income->actual_balance}}</span>


                                  <!-- Action Icons -->
                                  <div class="flex items-center space-x-3 text-gray-500">
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
                  </li>
                  @endforeach
              </ul>
          </div>
      </div>

      <script src="//unpkg.com/alpinejs" defer></script>


  </div>



  <script>
// Get the button and content div

const IncomeToggleButton = document.querySelectorAll('.IncomeToggleButton');
const contentIncomeDiv = document.getElementById('IncomeDetails');
console.log(IncomeToggleButton);
// Initially hide the content div
contentIncomeDiv.style.display = 'none';

// Add click event listener to the button
IncomeToggleButton.forEach(function(button) {
    console.log(button);
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