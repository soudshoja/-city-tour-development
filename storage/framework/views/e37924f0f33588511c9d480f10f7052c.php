<div class="ExpensesToggleButton group main-container cursor-pointer rounded-lg BoxShadow coa-partials overflow-hidden relative
    hover:shadow-lg hover:shadow-red-500/10 transition-all duration-300">

    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-red-400 to-red-600 rounded-r opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-red-50/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>

    <div class="grid grid-cols-12 gap-2 items-center py-4 px-4 relative z-10">
        <div class="col-span-5 flex items-center gap-3">
            <svg class="w-6 h-6 group-hover:scale-110 transition-transform duration-300" viewBox="0 0 24 24" fill="none">
                <path d="M6 11C6 8.17157 6 6.75736 6.87868 5.87868C7.75736 5 9.17157 5 12 5H15C17.8284 5 19.2426 5 20.1213 5.87868C21 6.75736 21 8.17157 21 11V16C21 18.8284 21 20.2426 20.1213 21.1213C19.2426 22 17.8284 22 15 22H12C9.17157 22 7.75736 22 6.87868 21.1213C6 20.2426 6 18.8284 6 16V11Z"
                    stroke="#AF1740" stroke-width="1.5" />
                <path opacity="0.5" d="M6 19C4.34315 19 3 17.6569 3 16V10C3 6.22876 3 4.34315 4.17157 3.17157C5.34315 2 7.22876 2 11 2H15C16.6569 2 18 3.34315 18 5"
                    stroke="#AF1740" stroke-width="1.5" />
            </svg>
            <h3 class="text-lg font-semibold text-[#AF1740] group-hover:text-red-700 transition-colors duration-300">Expenses</h3>
        </div>

        <div class="col-span-2 flex justify-center">
            <span class="px-4 py-1 text-xs font-semibold text-red-600 bg-red-100 rounded-full group-hover:bg-red-200 transition-colors duration-300">Code</span>
        </div>

        <div class="col-span-3 flex justify-end">
            <span class="text-lg font-semibold text-[#AF1740] group-hover:text-red-700 transition-colors duration-300">Actual Balance</span>
        </div>

        <div class="col-span-2 flex justify-end">
            <svg class="w-6 h-6 text-gray-400 group-hover:text-red-500 transition-colors duration-300" viewBox="0 0 24 24" fill="none">
                <path d="M10 4L10 20L4 14.5" stroke="#AF1740" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
    </div>
</div>

<div id="expensesDetails" class="rounded-lg shadow-sm bg-white dark:bg-gray-800 mt-1" style="display: none;">
    <ul class="w-full">
        <?php $__currentLoopData = $expenses->childAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $expense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make('coa.partials.child-account', ['account' => $expense, 'color' => 'red'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
</div>

<script>
    const contentExpensesDiv = document.getElementById('expensesDetails');
    const ExpensesToggleButton = document.querySelectorAll('.ExpensesToggleButton');

    function toggleExpensesVisibility() {
        contentExpensesDiv.style.display = contentExpensesDiv.style.display === 'none' || contentExpensesDiv.style.display === '' ? 'block' : 'none';
    }

    ExpensesToggleButton.forEach(button => {
        button.addEventListener('click', toggleExpensesVisibility);
    });
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/coa/partials/expenses.blade.php ENDPATH**/ ?>