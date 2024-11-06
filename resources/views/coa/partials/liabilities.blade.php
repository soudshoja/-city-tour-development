  <!-- Liabilities Section overview -->


  <div
      class="LiabilitiesToggleButton main-container cursor-pointer items-center justify-between bg-white p-4  flex w-full rounded-lg shadow-sm border border-gray-200">
      <div class="flex items-center space-x-3 ">

          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path
                  d="M4.97883 9.68508C2.99294 8.89073 2 8.49355 2 8C2 7.50645 2.99294 7.10927 4.97883 6.31492L7.7873 5.19153C9.77318 4.39718 10.7661 4 12 4C13.2339 4 14.2268 4.39718 16.2127 5.19153L19.0212 6.31492C21.0071 7.10927 22 7.50645 22 8C22 8.49355 21.0071 8.89073 19.0212 9.68508L16.2127 10.8085C14.2268 11.6028 13.2339 12 12 12C10.7661 12 9.77318 11.6028 7.7873 10.8085L4.97883 9.68508Z"
                  stroke="#f7c157" stroke-width="1.5" />
              <path opacity="0.5"
                  d="M5.76613 10L4.97883 10.3149C2.99294 11.1093 2 11.5065 2 12C2 12.4935 2.99294 12.8907 4.97883 13.6851L7.7873 14.8085C9.77318 15.6028 10.7661 16 12 16C13.2339 16 14.2268 15.6028 16.2127 14.8085L19.0212 13.6851C21.0071 12.8907 22 12.4935 22 12C22 11.5065 21.0071 11.1093 19.0212 10.3149L18.2339 10M5.76613 14L4.97883 14.3149C2.99294 15.1093 2 15.5065 2 16C2 16.4935 2.99294 16.8907 4.97883 17.6851L7.7873 18.8085C9.77318 19.6028 10.7661 20 12 20C13.2339 20 14.2268 19.6028 16.2127 18.8085L19.0212 17.6851C21.0071 16.8907 22 16.4935 22 16C22 15.5065 21.0071 15.1093 19.0212 14.3149L18.2339 14"
                  stroke="#f7c157" stroke-width="1.5" />
          </svg>
          <h3 class="font-semibold text-lg text-[#FCCD2A]">Liabilities</h3>
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



  <!-- Liabilities Section overview -->
  <div id="LiabilitiesDetails" class="rounded-lg shadow-sm ">
      <div class="mb-5" x-data="{ openLevels: {} }">
          <!-- Vertical layout for top-level Liabilities -->
          <div>
              <ul class="space-y-2 w-full">
                  <!-- Level 2 - Top-Level Liabilities as Tabs -->
                  @foreach ($liabilities as $liability)
                  <li class="relative w-full">
                      <a href="javascript:;"
                          class="flex items-center justify-between px-4 py-2 w-full hover:text-secondary transition-all"
                          :class="{'border-l-4 border-secondary text-secondary': openLevels['{{ $liability->id }}']}"
                          @click="openLevels = { ['{{ $liability->id }}']: !openLevels['{{ $liability->id }}'] }">
                          <span>{{ $liability->name }}</span>
                          <svg x-show="!openLevels['{{ $liability->id }}']" width="24" height="24" viewBox="0 0 24 24"
                              fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                  stroke-linejoin="round" />
                              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round" />
                          </svg>
                          <svg x-show="openLevels['{{ $liability->id }}']" width="24" height="24" viewBox="0 0 24 24"
                              fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                                  stroke-linejoin="round" />
                              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round" />
                          </svg>

                      </a>

                      <!-- Level 3 - Nested content opens below each top-level liability when clicked -->
                      <div x-show="openLevels['{{ $liability->id }}']"
                          class="mt-2 space-y-2 bg-gray-100 rounded-lg p-4 w-full">
                          @foreach ($liability->level3liabilities as $level3liability)
                          <a href="javascript:;"
                              class="flex items-center justify-between px-4 py-2 w-full hover:text-secondary transition-all"
                              :class="{'border-l-4 border-secondary text-secondary': openLevels['{{ $liability->id }}']}"
                              @click="openLevels['{{ $level3liability->id }}'] = !openLevels['{{ $level3liability->id }}']">
                              <span>{{ $level3liability->name }}</span>
                              <svg x-show="!openLevels['{{ $level3liability->id }}']" width="24" height="24"
                                  viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                                  <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                              </svg>
                              <svg x-show="openLevels['{{ $level3liability->id }}']" width="24" height="24"
                                  viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                  <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                                  <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                                      stroke-linecap="round" stroke-linejoin="round" />
                              </svg>

                          </a>




                          <!-- Level 4 - Nested under each level 3 liability -->
                          <div x-show="openLevels['{{ $level3liability->id }}']" class="ml-6 space-y-2 mt-2">
                              @if ($level3liability->level4liabilities->isEmpty())
                              <p class="text-danger">No Liabilities here yet!</p>
                              @endif
                              @foreach ($level3liability->level4liabilities as $level4liability)
                              <div
                                  class="flex items-center justify-between bg-white p-4 rounded-lg shadow-sm border border-gray-200 w-full">
                                  <!-- Name -->
                                  <span class="text-gray-800 font-medium">{{ $level4liability->name }}</span>

                                  <!-- code -->
                                  <span
                                      class="px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full editable-cell"
                                      contenteditable="true">
                                      {{ $level4liability->code }}</span>

                                  <!-- Actual Balance -->
                                  <span class="text-gray-500 text-sm editable-cell" contenteditable="true">
                                      {{number_format($level4liability->actual_balance, 2)}}</span>


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
// open and close the liabilities section details function

const LiabilitiesToggleButton = document.querySelectorAll('.LiabilitiesToggleButton');
const contentLiabilitiesDiv = document.getElementById('LiabilitiesDetails');
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