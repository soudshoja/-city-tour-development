<div class="IncomeToggleButton group main-container cursor-pointer rounded-lg BoxShadow coa-partials overflow-hidden relative
            hover:shadow-lg hover:shadow-blue-500/10 transition-all duration-300">

    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-blue-400 to-blue-600 rounded-r opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-blue-50/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>

    <div class="grid grid-cols-12 gap-2 items-center py-4 px-4 relative z-10">
        <div class="col-span-5 flex items-center gap-3">
            <svg class="w-6 h-6 group-hover:scale-110 transition-transform duration-300" viewBox="0 0 24 24" fill="none">
                <path opacity="0.5" d="M22 12C22 13.9778 21.4135 15.9112 20.3147 17.5557C19.2159 19.2002 17.6541 20.4819 15.8268 21.2388C13.9996 21.9957 11.9889 22.1937 10.0491 21.8079C8.10929 21.422 6.32746 20.4696 4.92893 19.0711C3.53041 17.6725 2.578 15.8907 2.19215 13.9509C1.80629 12.0111 2.00433 10.0004 2.7612 8.17317C3.51808 6.3459 4.79981 4.78412 6.4443 3.6853C8.08879 2.58649 10.0222 2 12 2"
                    stroke="#1e40af" stroke-width="1.5" stroke-linecap="round" />
                <path d="M15 12L12 12M12 12L9 12M12 12L12 9M12 12L12 15" stroke="#1e40af" stroke-width="1.5" stroke-linecap="round" />
                <path d="M14.5 2.31494C18.014 3.21939 20.7805 5.98588 21.685 9.4999" stroke="#1e40af" stroke-width="1.5" stroke-linecap="round" />
            </svg>
            <h3 class="text-lg font-semibold text-[#1e40af] group-hover:text-blue-700 transition-colors duration-300">Income</h3>
        </div>

        <div class="col-span-2 flex justify-center">
            <span class="px-4 py-1 text-xs font-semibold text-blue-600 bg-blue-100 rounded-full group-hover:bg-blue-200 transition-colors duration-300">Code</span>
        </div>

        <div class="col-span-3 flex justify-end">
            <span class="text-lg font-semibold text-[#1e40af] group-hover:text-blue-700 transition-colors duration-300">Actual Balance</span>
        </div>

        <div class="col-span-2 flex justify-end">
            <svg class="w-6 h-6 text-gray-400 group-hover:text-blue-500 transition-colors duration-300" viewBox="0 0 24 24" fill="none">
                <path d="M10 4L10 20L4 14.5" stroke="#1e40af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
    </div>
</div>

<div id="incomeDetails" class="rounded-lg shadow-sm bg-white dark:bg-gray-800 mt-1" style="display: none;">
    <ul class="w-full">
        <?php $__currentLoopData = $incomes->childAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $income): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make('coa.partials.child-account', ['account' => $income, 'color' => 'blue'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
</div>

<script>
    const contentIncomeDiv = document.getElementById('incomeDetails');
    const IncomeToggleButton = document.querySelectorAll('.IncomeToggleButton');

    function toggleIncomeVisibility() {
        contentIncomeDiv.style.display = contentIncomeDiv.style.display === 'none' || contentIncomeDiv.style.display === '' ? 'block' : 'none';
    }

    IncomeToggleButton.forEach(button => {
        button.addEventListener('click', toggleIncomeVisibility);
    });
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/coa/partials/income.blade.php ENDPATH**/ ?>