<div class="LiabilitiesToggleButton group main-container cursor-pointer rounded-lg shadow-md coa-partials overflow-hidden relative hover:shadow-yellow-500/10 transition-all duration-300">

    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-yellow-400 to-yellow-600 rounded-r opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-yellow-50/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>

    <div class="grid grid-cols-12 gap-2 items-center py-4 px-4 relative z-10">
        <div class="col-span-5 flex items-center gap-3">
            <svg class="w-6 h-6 group-hover:scale-110 transition-transform duration-300" viewBox="0 0 24 24" fill="none">
                <path d="M4.97883 9.68508C2.99294 8.89073 2 8.49355 2 8C2 7.50645 2.99294 7.10927 4.97883 6.31492L7.7873 5.19153C9.77318 4.39718 10.7661 4 12 4C13.2339 4 14.2268 4.39718 16.2127 5.19153L19.0212 6.31492C21.0071 7.10927 22 7.50645 22 8C22 8.49355 21.0071 8.89073 19.0212 9.68508L16.2127 10.8085C14.2268 11.6028 13.2339 12 12 12C10.7661 12 9.77318 11.6028 7.7873 10.8085L4.97883 9.68508Z" stroke="#ffc107" stroke-width="1.5" />
                <path opacity="0.5" d="M5.76613 10L4.97883 10.3149C2.99294 11.1093 2 11.5065 2 12C2 12.4935 2.99294 12.8907 4.97883 13.6851L7.7873 14.8085C9.77318 15.6028 10.7661 16 12 16C13.2339 16 14.2268 15.6028 16.2127 14.8085L19.0212 13.6851C21.0071 12.8907 22 12.4935 22 12C22 11.5065 21.0071 11.1093 19.0212 10.3149L18.2339 10M5.76613 14L4.97883 14.3149C2.99294 15.1093 2 15.5065 2 16C2 16.4935 2.99294 16.8907 4.97883 17.6851L7.7873 18.8085C9.77318 19.6028 10.7661 20 12 20C13.2339 20 14.2268 19.6028 16.2127 18.8085L19.0212 17.6851C21.0071 16.8907 22 16.4935 22 16C22 15.5065 21.0071 15.1093 19.0212 14.3149L18.2339 14" stroke="#000" stroke-width="1.5" />
            </svg>
            <h3 class="text-lg font-semibold text-[#ffc107] group-hover:text-yellow-600 transition-colors duration-300">Liabilities</h3>
        </div>

        <div class="col-span-2 flex justify-center">
            <span class="px-4 py-1 text-xs font-semibold text-yellow-600 bg-yellow-100 rounded-full group-hover:bg-yellow-200 transition-colors duration-300">Code</span>
        </div>

        <div class="col-span-3 flex justify-end">
            <span class="text-lg font-semibold text-[#ffc107] group-hover:text-yellow-600 transition-colors duration-300">Actual Balance</span>
        </div>

        <div class="col-span-2 flex justify-end">
            <svg class="w-6 h-6 text-gray-400 group-hover:text-yellow-500 transition-colors duration-300" viewBox="0 0 24 24" fill="none">
                <path d="M10 4L10 20L4 14.5" stroke="#ffc107" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
    </div>
</div>

<div id="liabilitiesDetails" class="rounded-lg shadow-sm bg-white dark:bg-gray-800 mt-1" style="display: none;">
    <ul class="w-full">
        <?php $__currentLoopData = $liabilities->childAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $liability): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make('coa.partials.child-account', ['account' => $liability, 'color' => 'yellow'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
</div>

<script>
    const contentLiabilitiesDiv = document.getElementById('liabilitiesDetails');
    const LiabilitiesToggleButton = document.querySelectorAll('.LiabilitiesToggleButton');

    function toggleLiabilitiesVisibility() {
        contentLiabilitiesDiv.style.display = contentLiabilitiesDiv.style.display === 'none' || contentLiabilitiesDiv.style.display === '' ? 'block' : 'none';
    }

    LiabilitiesToggleButton.forEach(button => {
        button.addEventListener('click', toggleLiabilitiesVisibility);
    });
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/coa/partials/liabilities.blade.php ENDPATH**/ ?>