  <!-- Liabilities Section overview -->


  <div
      class="LiabilitiesToggleButton main-container cursor-pointer items-center justify-between p-4  flex w-full rounded-lg BoxShadow coa-partials">
      <div class="flex items-center space-x-3 ">

          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path
                  d="M4.97883 9.68508C2.99294 8.89073 2 8.49355 2 8C2 7.50645 2.99294 7.10927 4.97883 6.31492L7.7873 5.19153C9.77318 4.39718 10.7661 4 12 4C13.2339 4 14.2268 4.39718 16.2127 5.19153L19.0212 6.31492C21.0071 7.10927 22 7.50645 22 8C22 8.49355 21.0071 8.89073 19.0212 9.68508L16.2127 10.8085C14.2268 11.6028 13.2339 12 12 12C10.7661 12 9.77318 11.6028 7.7873 10.8085L4.97883 9.68508Z"
                  stroke="#ffc107" stroke-width="1.5" />
              <path opacity="0.5"
                  d="M5.76613 10L4.97883 10.3149C2.99294 11.1093 2 11.5065 2 12C2 12.4935 2.99294 12.8907 4.97883 13.6851L7.7873 14.8085C9.77318 15.6028 10.7661 16 12 16C13.2339 16 14.2268 15.6028 16.2127 14.8085L19.0212 13.6851C21.0071 12.8907 22 12.4935 22 12C22 11.5065 21.0071 11.1093 19.0212 10.3149L18.2339 10M5.76613 14L4.97883 14.3149C2.99294 15.1093 2 15.5065 2 16C2 16.4935 2.99294 16.8907 4.97883 17.6851L7.7873 18.8085C9.77318 19.6028 10.7661 20 12 20C13.2339 20 14.2268 19.6028 16.2127 18.8085L19.0212 17.6851C21.0071 16.8907 22 16.4935 22 16C22 15.5065 21.0071 15.1093 19.0212 14.3149L18.2339 14"
                  stroke="#000" stroke-width="1.5" />
          </svg>
          <h3 class="font-semibold text-lg text-[#ffc107]">Liabilities</h3>
      </div>
      <!-- Status Badge -->
      <span class="px-2 py-1 text-xs font-semibold text-yellow-600 bg-yellow-100 rounded-full">Code</span>

      <!-- Integration Type -->
      <span class="font-semibold text-lg text-[#ffc107]">Actual Balance</span>

      <button class="hover:text-gray-700">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round"/>
              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round"  class="stroke-gray-600 dark:stroke-white"/>
          </svg>
      </button>
  </div>



<!-- Liabilities Section overview -->
<div id="liabilitiesDetails" class="rounded-lg shadow-sm" x-data="liabilityManager()">
    <div class="mb-5">
        <!-- Vertical layout for top-level liabilities -->
        <ul class="space-y-2 w-full">
            <!-- Level 2 - Top-Level liabilities as Tabs -->

            @foreach ($liabilities as $index => $liability)
            <li x-data="{ showAddCategoryForm: false }" class="relative w-full flex items-center">
                <a href="javascript:;"
                    class="flex items-center px-4 py-2 w-full hover:text-[#ffc107] transition-all"
                    :class="{'border-l-4 border-[#ffc107] text-[#000000]': openLevels['{{ $liability->id }}']}"
                    @click="openLevels['{{ $liability->id }}'] = !openLevels['{{ $liability->id }}']">

                    <span class="flex-1">{{ $liability->name }}</span>

                    <div class="flex items-center gap-2">

                    <svg x-show="!openLevels['{{ $liability->id }}']" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                            stroke-linejoin="round" />
                        <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
                    </svg>
                    <svg x-show="openLevels['{{ $liability->id }}']" width="24" height="24" viewBox="0 0 24 24"
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
                        newCategoryLevel: {{ $liability->level }},
                        newCategoryParentId: {{ $liability->id }},
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

            <!-- Level 3 - Nested content opens below each top-level liability when clicked -->
            <div x-show="openLevels['{{ $liability->id }}']" class="mt-2 space-y-2 p-4 w-full">
                @if ($liability->level3liabilities->isEmpty())
                <p class="text-danger">No liability here yet!</p>
                @endif
                @foreach ($liability->level3liabilities as $level3liability)
                <div x-data="{ showAddCategoryForm: false }" class="relative w-full flex items-center">
                    <a href="javascript:;"
                        class="flex items-center px-4 py-2 w-full hover:text-[#ffc107] transition-all gap-x-2"
                        :class="{'border-l-4 border-[#ffc10780] text-[#000000]': openLevels['{{ $level3liability->id }}']}"
                        @click="openLevels['{{ $level3liability->id }}'] = !openLevels['{{ $level3liability->id }}']">
                        
                        <!-- Text on the left -->
                        <span class="flex-1">{{ $level3liability->name }}</span>
                        <div class="flex items-center gap-2">

                        <!-- Icons -->
                        <svg x-show="!openLevels['{{ $level3liability->id }}']" width="24" height="24"
                            viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
                        </svg>
                        <svg x-show="openLevels['{{ $level3liability->id }}']" width="24" height="24"
                            viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
                        </svg>
                          <!-- Add "+" icon button for each level 3 category -->
                    <button @click.stop="showAddCategoryForm = !showAddCategoryForm"
                        class="text-green-600 hover:text-green-800 ">
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
                            newCategoryLevel: {{ $level3liability->level }},
                            newCategoryParentId: {{ $level3liability->id }},
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

                <!-- Level 4 - Nested under each level 3 liability -->
                <div x-show="openLevels['{{ $level3liability->id }}']" class="ml-6 space-y-2 mt-2">
                    @if ($level3liability->level4liabilities->isEmpty())
                    <p class="text-danger">No liabilities here yet!</p>
                    @endif

                    @foreach ($level3liability->level4liabilities as $level4liability)
                    <div class="flex items-center justify-between p-4 rounded-lg shadow-sm w-full">
                        <!-- Name -->
                        <span class="text-gray-800 dark:text-gray-600 font-medium">{{ $level4liability->name }}</span>

                        <input type="text" name="code"
                            class="text-center border-none focus:outline-none focus:ring-0 px-2 py-1 text-xs font-semibold text-yellow-600 bg-yellow-100 rounded-full editable-cell"
                            value="{{ $level4liability->code }}" id="editable-code-{{ $level4liability->id }}"
                            onblur="saveCode({{ $level4liability->id }}, this.value)"
                            onkeypress="checkEnter(event, {{ $level4liability->id }}, this.value)">

                        <!-- Actual Balance -->
                        <div @click="window.location='{{ route('coa.transaction') }}?level4Id={{ $level4liability->id }}'">
                            <input type="text" name="actual_balance"
                                class="text-center border-none focus:outline-none focus:ring-0 px-2 py-1 text-xs font-semibold text-yellow-600 bg-yellow-100 rounded-full"
                                value="{{ number_format($level4liability->actual_balance, 2) }}" readonly
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

  <!--./liabilities Details-->
  <script>
      document.addEventListener('alpine:init', () => {
          Alpine.data('liabilityManager', () => ({
              showAddCategoryForm: false,
              newCategoryName: '',
              newCategoryCode: '', // Add this line
              newCategoryLevel: '',
              newCategoryParentId: '',
              variance: '',
              budgetBalance: '',
              actualBalance: '',

              openLevels: {},
              liabilities: @json($liabilities), // Initialize the liabilities array with the existing liabilities
              addCategory() {
                  if (this.newCategoryName.trim() === '' || this.newCategoryCode.trim() === '') {
                      alert('Category name and code cannot be empty');
                      return;
                  }
                  console.log(this.newCategoryLevel);

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
                              this.liabilities.push({
                                  id: data.id,
                                  name: this.newCategoryName,
                                  code: this.newCategoryCode,
                                  level: this.newCategoryLevel,
                                  parent_id: this.newCategoryParentId,
                                  variance: this.variance,
                                  level3assets: [] // Initialize with an empty array for level 3 liabilities
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
      // open and close the liabilities section details function

      const LiabilitiesToggleButton = document.querySelectorAll('.LiabilitiesToggleButton');
      const contentLiabilitiesDiv = document.getElementById('liabilitiesDetails');
      console.log(LiabilitiesToggleButton);

      // Initially hide the content div
      contentLiabilitiesDiv.style.display = 'none';

      // Add click event listener to the button
      LiabilitiesToggleButton.forEach(function(button) {
          console.log(button);
          button.addEventListener('click', function() {
              // Toggle the content div visibility
              if (contentLiabilitiesDiv.style.display === 'none' || contentLiabilitiesDiv.style.display ===
                  '') {
                  contentLiabilitiesDiv.style.display = 'block'; // Show the content
              } else {
                  contentLiabilitiesDiv.style.display = 'none'; // Hide the content
              }
          });
      });


      // update the liabilities code function

      function checkEnter(event, liabilityId, value) {
          if (event.key === 'Enter') {
              event.preventDefault(); // Prevent form submission if it's in a form
              saveLiabilityCode(liabilityId, value);
          }
      }

      function saveLiabilityCode(liabilityId, value) {
          if (value.trim() === '') {
              showMessage('Code cannot be empty!');
              return; // Prevent saving if the input is empty
          }

          // Make an AJAX request to save the new code
          fetch(`/updateCode/${liabilityId}`, { // Update with your save route
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

      function showMessage(message) {
          const messageArea = document.getElementById('message-area');
          const messageDiv = document.getElementById('message');

          messageDiv.innerText = message; // Set the message text
          messageArea.classList.remove('hidden'); // Make the message area visible

          // Optionally, set a timeout to hide the message after a few seconds
          setTimeout(() => {
              messageArea.classList.add('hidden');
          }, 3000); // Adjust the duration as needed (3000ms = 3 seconds)
      }
  </script>