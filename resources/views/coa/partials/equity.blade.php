<div class="equityToggleButton group main-container cursor-pointer rounded-lg BoxShadow coa-partials overflow-hidden relative
    hover:shadow-lg hover:shadow-purple-500/10 transition-all duration-300">

    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-purple-400 to-purple-600 rounded-r opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-purple-50/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>

    <div class="grid grid-cols-12 gap-2 items-center py-4 px-4 relative z-10">
        <div class="col-span-5 flex items-center gap-3">
            <svg class="w-6 h-6 group-hover:scale-110 transition-transform duration-300" viewBox="0 0 24 24" fill="none">
                <path d="M2 12L5 12M22 12L19 12M14 12L10 12" stroke-width="1.5" stroke-linecap="round" class="stroke-purple-500" />
                <path d="M7.5 5C6.56538 5 6.09808 5 5.75 5.20096C5.52197 5.33261 5.33261 5.52197 5.20096 5.75C5 6.09808 5 6.56538 5 7.5L5 16.5C5 17.4346 5 17.9019 5.20096 18.25C5.33261 18.478 5.52197 18.6674 5.75 18.799C6.09808 19 6.56538 19 7.5 19C8.43462 19 8.90192 19 9.25 18.799C9.47803 18.6674 9.66739 18.478 9.79904 18.25C10 17.9019 10 17.4346 10 16.5L10 7.5C10 6.56538 10 6.09808 9.79904 5.75C9.66739 5.52197 9.47803 5.33261 9.25 5.20096C8.90192 5 8.43462 5 7.5 5Z"
                    stroke="#1C274C" stroke-width="1.5" />
                <path d="M16.5 7C15.5654 7 15.0981 7 14.75 7.20096C14.522 7.33261 14.3326 7.52197 14.201 7.75C14 8.09808 14 8.56538 14 9.5L14 14.5C14 15.4346 14 15.9019 14.201 16.25C14.3326 16.478 14.522 16.6674 14.75 16.799C15.0981 17 15.5654 17 16.5 17C17.4346 17 17.9019 17 18.25 16.799C18.478 16.6674 18.6674 16.478 18.799 16.25C19 15.9019 19 15.4346 19 14.5V9.5C19 8.56538 19 8.09808 18.799 7.75C18.6674 7.52197 18.478 7.33261 18.25 7.20096C17.9019 7 17.4346 7 16.5 7Z"
                    stroke-width="1.5" class="stroke-purple-600" />
            </svg>
            <h3 class="text-lg font-semibold text-purple-600 group-hover:text-purple-700 transition-colors duration-300">Equity</h3>
        </div>

        <div class="col-span-2 flex justify-center">
            <span class="px-4 py-1 text-xs font-semibold text-purple-600 bg-purple-100 rounded-full group-hover:bg-purple-200 transition-colors duration-300">Code</span>
        </div>

        <div class="col-span-3 flex justify-end">
            <span class="text-lg font-semibold text-purple-600 group-hover:text-purple-700 transition-colors duration-300">Actual Balance</span>
        </div>

        <div class="col-span-2 flex justify-end">
            <svg class="w-6 h-6 text-gray-400 group-hover:text-purple-500 transition-colors duration-300" viewBox="0 0 24 24" fill="none">
                <path d="M10 4L10 20L4 14.5" stroke="#6a329f" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
    </div>
</div>

<div id="equityDetails" class="rounded-lg shadow-sm bg-white dark:bg-gray-800 mt-1" style="display: none;">
    <ul class="w-full">
        @foreach ($equities->childAccounts as $equityAccount)
            @include('coa.partials.child-account', ['account' => $equityAccount, 'color' => 'purple'])
        @endforeach
    </ul>
</div>

<script>
    const contentEquityDiv = document.getElementById('equityDetails');
    const equityToggleButton = document.querySelectorAll('.equityToggleButton');

    function toggleEquityVisibility() {
        contentEquityDiv.style.display = contentEquityDiv.style.display === 'none' || contentEquityDiv.style.display === '' ? 'block' : 'none';
    }

    equityToggleButton.forEach(button => {
        button.addEventListener('click', toggleEquityVisibility);
    });
</script>