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
      <span class="ml-40 px-5 py-1 text-xs font-semibold text-blue-600 bg-blue-100 rounded-full">Code</span>
      <!-- Integration Type -->
      <span class="font-semibold text-lg text-[#1e40af] mr-20">Actual Balance</span>

      <button class="hover:text-gray-700">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round" />
              <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="" stroke-width="1.5" stroke-linecap="round"
                  stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
          </svg>
      </button>
  </div>
  <div id="incomeDetails" class="rounded-lg shadow-sm">
      <div>
          <ul class="w-full">
              @foreach ($incomes->childAccounts as $income)
                  @include('coa.partials.child-account', ['account' => $income, 'color' => 'blue'])
              @endforeach
          </ul>
      </div>
  </div>
  <script>
      const contentIncomeDiv = document.getElementById('incomeDetails');
      const IncomeToggleButton = document.querySelectorAll('.IncomeToggleButton');

      contentIncomeDiv.style.display = 'none';

      function toggleIncomeVisibility() {
          contentIncomeDiv.style.display = contentIncomeDiv.style.display === 'none' || contentIncomeDiv.style.display ===
              '' ? 'block' : 'none';
      }

      IncomeToggleButton.forEach(button => {
          button.addEventListener('click', toggleIncomeVisibility);
      });
  </script>
