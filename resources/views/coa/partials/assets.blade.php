  <div
      class="AssetsToggleButton main-container cursor-pointer items-center justify-between p-4 flex w-full rounded-lg BoxShadow coa-partials"
      x-data="{ showAddCategoryForm: false }">
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
                  stroke="#00ab55" stroke-width="1.5"></path>
              <path
                  d="M13.5 6.5C13.5 4.29086 15.2909 2.5 17.5 2.5C19.7091 2.5 21.5 4.29086 21.5 6.5C21.5 8.70914 19.7091 10.5 17.5 10.5H14.6429C14.5102 10.5 14.4438 10.5 14.388 10.4937C13.9244 10.4415 13.5585 10.0756 13.5063 9.61196C13.5 9.55616 13.5 9.48982 13.5 9.35714V6.5Z"
                  stroke="#00ab55" stroke-width="1.5"></path>
          </svg>
          <h3 class="font-semibold text-lg text-[#00ab55]">Assets</h3>
      </div>
      <span class="px-2 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full">Code</span>

      <span class="font-semibold text-lg text-[#00ab55]">Actual Balance</span>
      <div>
          <button class="hover:text-gray-700">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                      stroke-linejoin="round" />
                  <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="" stroke-width="1.5" stroke-linecap="round"
                      stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
              </svg>
          </button>

      </div>


  </div>

  <div id="AssetsDetails" class="rounded-lg shadow-sm" x-data="assetManager()">
      <div>
          <ul class="w-full">
              @foreach ($assets->childAccounts as $asset)
              @include('coa.partials.asset-item', ['account' => $asset])
              @endforeach
          </ul>
      </div>
  </div>

  <!-- <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script> -->
  <script>
      document.addEventListener('alpine:init', () => {
          Alpine.data('assetManager', () => ({
              showAddCategoryForm: false,
              newCategoryName: '',
              newCategoryCode: '', // Add this line
              newCategoryType: '',
              newCategoryEntity: '',
              newCategoryLevel: '',
              newCategoryParentId: '',
              variance: '',
              budgetBalance: '',
              actualBalance: '',
              errors: {},

              resetForm() {
                  this.newCategoryName = '';
                  this.newCategoryCode = '';
                  this.newCategoryType = '';
                  this.newCategoryEntity = '';
                  this.errors = {};
              },

              openLevels: {},
              assets: @json($assets), // Initialize the assets array with the existing assets
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
                              this.assets.push({
                                  id: data.id,
                                  name: this.newCategoryName,
                                  code: this.newCategoryCode,
                                  level: this.newCategoryLevel,
                                  parent_id: this.newCategoryParentId,
                                  variance: this.variance,
                                  level3assets: [] // Initialize with an empty array for level 3 assets
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
      // open and close assets details function
      const AssetsToggleButton = document.querySelectorAll('.AssetsToggleButton');
      const contentAssetsDiv = document.getElementById('AssetsDetails');
      //   console.log(AssetsToggleButton);
      // Initially hide the content div
      contentAssetsDiv.style.display = 'none';

      // Add click event listener to the button
      AssetsToggleButton.forEach(function(button) {
          //   console.log(button);
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

  <style>
      .ts-control {
          border: 1px solid;
          font-size: 0.875rem;
          line-height: 1.25rem;
          padding-top: 0.5rem;
          padding-bottom: 0.5rem;
      }
  </style>