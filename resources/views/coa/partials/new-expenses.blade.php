<div
    class="ExpensesToggleButton main-container cursor-pointer items-center justify-between p-4  flex w-full rounded-lg BoxShadow coa-partials">

    <div class="flex items-center space-x-3">

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
    <span class="px-2 py-1 text-xs font-semibold text-red-600 bg-red-100 rounded-full">Code</span>

    <span class="font-semibold text-lg text-[#AF1740]">Actual Balance</span>

    <button class="hover:text-gray-700">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" />
            <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="" stroke-width="1.5" stroke-linecap="round"
                stroke-linejoin="round" class="stroke-gray-600 dark:stroke-white" />
        </svg>
    </button>
</div>
<div id="expensesDetails" class="rounded-lg shadow-sm">
    <div>
        <ul class="w-full">
            @foreach ($expenses->childAccounts as $expense)
            @include('coa.partials.child-account', ['account' => $expense, 'color' => 'red'])
            @endforeach
        </ul>
    </div>
</div>
<script>
    const contentExpensesDiv = document.getElementById('expensesDetails');
    const ExpensesToggleButton = document.querySelectorAll('.ExpensesToggleButton');

    contentExpensesDiv.style.display = 'none';

    function toggleExpensesVisibility() {
        contentExpensesDiv.style.display = contentExpensesDiv.style.display === 'none' || contentExpensesDiv.style.display === '' ? 'block' : 'none';
    }

    ExpensesToggleButton.forEach(button => {
        button.addEventListener('click', toggleExpensesVisibility);
    });
</script>