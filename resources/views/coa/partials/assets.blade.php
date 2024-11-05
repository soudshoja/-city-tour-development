  <!-- Assets Section overview -->


  <div
      class="AssetsToggleButton main-container cursor-pointer items-center justify-between bg-white p-4  flex w-full rounded-lg shadow-sm border border-gray-200">
      <div class="flex items-center space-x-3 ">

          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path opacity="0.5"
                  d="M2.5 6.5C2.5 4.29086 4.29086 2.5 6.5 2.5C8.70914 2.5 10.5 4.29086 10.5 6.5V9.16667C10.5 9.47666 10.5 9.63165 10.4659 9.75882C10.3735 10.1039 10.1039 10.3735 9.75882 10.4659C9.63165 10.5 9.47666 10.5 9.16667 10.5H6.5C4.29086 10.5 2.5 8.70914 2.5 6.5Z"
                  stroke="currentColor" stroke-width="1.5"></path>
              <path opacity="0.5"
                  d="M13.5 14.8333C13.5 14.5233 13.5 14.3683 13.5341 14.2412C13.6265 13.8961 13.8961 13.6265 14.2412 13.5341C14.3683 13.5 14.5233 13.5 14.8333 13.5H17.5C19.7091 13.5 21.5 15.2909 21.5 17.5C21.5 19.7091 19.7091 21.5 17.5 21.5C15.2909 21.5 13.5 19.7091 13.5 17.5V14.8333Z"
                  stroke="currentColor" stroke-width="1.5"></path>
              <path
                  d="M2.5 17.5C2.5 15.2909 4.29086 13.5 6.5 13.5H8.9C9.46005 13.5 9.74008 13.5 9.95399 13.609C10.1422 13.7049 10.2951 13.8578 10.391 14.046C10.5 14.2599 10.5 14.5399 10.5 15.1V17.5C10.5 19.7091 8.70914 21.5 6.5 21.5C4.29086 21.5 2.5 19.7091 2.5 17.5Z"
                  stroke="#1A5319" stroke-width="1.5"></path>
              <path
                  d="M13.5 6.5C13.5 4.29086 15.2909 2.5 17.5 2.5C19.7091 2.5 21.5 4.29086 21.5 6.5C21.5 8.70914 19.7091 10.5 17.5 10.5H14.6429C14.5102 10.5 14.4438 10.5 14.388 10.4937C13.9244 10.4415 13.5585 10.0756 13.5063 9.61196C13.5 9.55616 13.5 9.48982 13.5 9.35714V6.5Z"
                  stroke="#1A5319" stroke-width="1.5"></path>
          </svg>
          <h3 class="font-semibold text-lg text-[#1A5319]">Assets</h3>
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







  <div id="AssetsDetails" class="rounded-lg shadow-sm ">
      <div class="mb-5" x-data="{ openLevels: {} }">
          <!-- Vertical layout for top-level assets -->
          <div>
              <ul class="space-y-2 w-full">
                  <!-- Level 2 - Top-Level Assets as Tabs -->

                  @foreach ($assets as $asset)
                  <li class="relative w-full">
                      <a href="javascript:;"
                          class="flex items-center justify-between px-4 py-2 w-full hover:text-[#508D4E] transition-all"
                          :class="{'border-l-4 border-[#508D4E] text-[#508D4E]': openLevels['{{ $asset->id }}']}"
                          @click="openLevels = { ['{{ $asset->id }}']: !openLevels['{{ $asset->id }}'] }">
                          <span>{{ $asset->name }}</span>
                          <svg x-show="!openLevels['{{ $asset->id }}']" width="24" height="24" viewBox="0 0 24 24"
                              fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                  stroke-linejoin="round" />
                              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round" />
                          </svg>
                          <svg x-show="openLevels['{{ $asset->id }}']" width="24" height="24" viewBox="0 0 24 24"
                              fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                  stroke-linejoin="round" />
                              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round" />
                          </svg>

                      </a>

                      <!-- Level 3 - Nested content opens below each top-level asset when clicked -->
                      <div x-show="openLevels['{{ $asset->id }}']"
                          class="mt-2 space-y-2 bg-gray-100 rounded-lg p-4 w-full">
                          @if ($asset->level3assets->isEmpty())
                          <p class="text-danger">No Asset here yet!</p>
                          @endif
                          @foreach ($asset->level3assets as $level3asset)
                          <a href="javascript:;"
                              class="flex items-center justify-between px-4 py-2 w-full hover:text-[#1c274c] transition-all"
                              :class="{'border-l-4 border-[#80AF81] text-[#1c274c]': openLevels['{{ $asset->id }}']}"
                              @click="openLevels['{{ $level3asset->id }}'] = !openLevels['{{ $level3asset->id }}']">
                              <span>{{ $level3asset->name }}</span>
                              <svg x-show="!openLevels['{{ $level3asset->id }}']" width="24" height="24"
                                  viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                                  <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                              </svg>
                              <svg x-show="openLevels['{{ $level3asset->id }}']" width="24" height="24"
                                  viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                                  <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                              </svg>

                          </a>




                          <!-- Level 4 - Nested under each level 3 asset -->
                          <div x-show="openLevels['{{ $level3asset->id }}']" class="ml-6 space-y-2 mt-2">
                              @if ($level3asset->level4assets->isEmpty())
                              <p class="text-danger">No Asset here yet!</p>
                              @endif

                              @foreach ($level3asset->level4assets as $level4asset)
                              <div
                                  class="flex items-center justify-between bg-white p-4 rounded-lg shadow-sm border border-gray-200 w-full">
                                  <!-- Name -->
                                  <span class="text-gray-800 font-medium">{{ $level4asset->name }}</span>

                                  <input type="text" name="code"
                                      class="text-center border-none focus:outline-none focus:ring-0 px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full editable-cell"
                                      value="{{ $level4asset->code }}" id="editable-code-{{ $level4asset->id }}"
                                      onblur="saveCode({{ $level4asset->id }}, this.value)"
                                      onkeypress="checkEnter(event, {{ $level4asset->id }}, this.value)">



                                  <!-- Actual Balance -->
                                  <input type="text" name="actual_balance"
                                      class="text-center border-none focus:outline-none focus:ring-0 px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full"
                                      value="{{ $level4asset->actual_balance }}" readonly
                                      style="pointer-events: none; cursor: default;">




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



  </div>



  <script>
// open and close assets details function
const AssetsToggleButton = document.querySelectorAll('.AssetsToggleButton');
const contentAssetsDiv = document.getElementById('AssetsDetails');
console.log(AssetsToggleButton);
// Initially hide the content div
contentAssetsDiv.style.display = 'none';

// Add click event listener to the button
AssetsToggleButton.forEach(function(button) {
    console.log(button);
    button.addEventListener('click', function() {
        // Toggle the content div visibility
        if (contentAssetsDiv.style.display === 'none' || contentAssetsDiv.style.display === '') {
            contentAssetsDiv.style.display = 'block'; // Show the content
        } else {
            contentAssetsDiv.style.display = 'none'; // Hide the content
        }
    });
});




// update code function

function checkEnter(event, assetId, value) {
    if (event.key === 'Enter') {
        event.preventDefault(); // Prevent form submission if it's in a form
        saveCode(assetId, value);
    }
}

function saveCode(assetId, value) {
    if (value.trim() === '') {
        showMessage('Code cannot be empty!');
        return; // Prevent saving if the input is empty
    }

    // Make an AJAX request to save the new code
    fetch(`/updateCode/${assetId}`, { // Update with your save route
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