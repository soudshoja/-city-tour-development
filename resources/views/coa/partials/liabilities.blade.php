  <!-- Liabilities Section overview -->

  <div class="main-container flex w-full rounded-lg shadow-sm border border-gray-200">
      <!-- Left Div (90%) -->
      <div class="left-div LiabilitiesToggleButton cursor-pointer flex items-center justify-between bg-white p-4 ">
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

      <!-- Right Div (10%) -->
      <div class="right-div flex justify-center items-center p-4">
          <!-- Refresh Button with Rotation Animation -->
          <!-- Refresh Button for Liabilities with Rotation Animation -->
          <button id="refreshLiabilitiesButton">
              <svg class="refreshLiabilitiesIcon " width="24" height="24" viewBox="0 0 24 24" fill="none"
                  xmlns="http://www.w3.org/2000/svg">
                  <path
                      d="M12.0789 3V2.25V3ZM3.67981 11.3333H2.92981H3.67981ZM3.67981 13L3.15157 13.5324C3.44398 13.8225 3.91565 13.8225 4.20805 13.5324L3.67981 13ZM5.88787 11.8657C6.18191 11.574 6.18377 11.0991 5.89203 10.8051C5.60029 10.511 5.12542 10.5092 4.83138 10.8009L5.88787 11.8657ZM2.52824 10.8009C2.2342 10.5092 1.75933 10.511 1.46759 10.8051C1.17585 11.0991 1.17772 11.574 1.47176 11.8657L2.52824 10.8009ZM18.6156 7.39279C18.8325 7.74565 19.2944 7.85585 19.6473 7.63892C20.0001 7.42199 20.1103 6.96007 19.8934 6.60721L18.6156 7.39279ZM12.0789 2.25C7.03155 2.25 2.92981 6.3112 2.92981 11.3333H4.42981C4.42981 7.15072 7.84884 3.75 12.0789 3.75V2.25ZM2.92981 11.3333L2.92981 13H4.42981L4.42981 11.3333H2.92981ZM4.20805 13.5324L5.88787 11.8657L4.83138 10.8009L3.15157 12.4676L4.20805 13.5324ZM4.20805 12.4676L2.52824 10.8009L1.47176 11.8657L3.15157 13.5324L4.20805 12.4676ZM19.8934 6.60721C18.287 3.99427 15.3873 2.25 12.0789 2.25V3.75C14.8484 3.75 17.2727 5.20845 18.6156 7.39279L19.8934 6.60721Z"
                      fill="#1C274C" />
                  <path opacity="0.5"
                      d="M11.8825 21V21.75V21ZM20.3137 12.6667H21.0637H20.3137ZM20.3137 11L20.8409 10.4666C20.5487 10.1778 20.0786 10.1778 19.7864 10.4666L20.3137 11ZM18.1002 12.1333C17.8056 12.4244 17.8028 12.8993 18.094 13.1939C18.3852 13.4885 18.86 13.4913 19.1546 13.2001L18.1002 12.1333ZM21.4727 13.2001C21.7673 13.4913 22.2421 13.4885 22.5333 13.1939C22.8245 12.8993 22.8217 12.4244 22.5271 12.1332L21.4727 13.2001ZM5.31769 16.6061C5.10016 16.2536 4.63806 16.1442 4.28557 16.3618C3.93307 16.5793 3.82366 17.0414 4.0412 17.3939L5.31769 16.6061ZM11.8825 21.75C16.9448 21.75 21.0637 17.6915 21.0637 12.6667H19.5637C19.5637 16.8466 16.133 20.25 11.8825 20.25V21.75ZM21.0637 12.6667V11H19.5637V12.6667H21.0637ZM19.7864 10.4666L18.1002 12.1333L19.1546 13.2001L20.8409 11.5334L19.7864 10.4666ZM19.7864 11.5334L21.4727 13.2001L22.5271 12.1332L20.8409 10.4666L19.7864 11.5334ZM4.0412 17.3939C5.65381 20.007 8.56379 21.75 11.8825 21.75V20.25C9.09999 20.25 6.6656 18.7903 5.31769 16.6061L4.0412 17.3939Z"
                      fill="#1C274C" />
              </svg>
          </button>


      </div>

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
                                      {{$level4liability->actual_balance}}</span>


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

// Add an event listener to the refresh button for liabilities
document.getElementById("refreshLiabilitiesButton").addEventListener("click", function() {
    const svgIcon = document.querySelector(".refreshLiabilitiesIcon ");
    svgIcon.classList.toggle("rotate");
    // Delay to allow the rotation animation to complete before refreshing
    setTimeout(() => {
        location.reload();
    }, 300); // Match this duration with the CSS transition duration
});

// Add an event listener to the refresh button for liabilities
document.getElementById('refreshLiabilitiesButton').addEventListener('click', function() {
    refreshLiabilitiesData();
});

// Function to refresh liabilities data
function refreshLiabilitiesData() {
    console.log("Fetching new liabilities data...");

    // Fetch new data from the server
    fetch('/api/liabilities') // Replace with your actual API endpoint
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            // Update your UI with the new data here.
            console.log("Liabilities data refreshed:", data);
            // Example: Update some HTML element with the new data
            // document.getElementById('liabilitiesInfo').innerText = data.info;
        })
        .catch(error => {
            console.error('Error fetching liabilities:', error);
        });
}

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


  <style>
#LiabilitiesDetails {
    display: none;
    /* Initially hidden */
    margin-top: 10px;
    padding: 10px;
    border: 1px solid #ccc;

    background-color: #f9f9f9;
}
  </style>