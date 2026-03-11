<div class="AssetsToggleButton group main-container cursor-pointer rounded-lg BoxShadow coa-partials overflow-hidden relative
    hover:shadow-lg hover:shadow-green-500/10 transition-all duration-300">

    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-green-400 to-green-600 rounded-r opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-green-50/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>

    <div class="grid grid-cols-12 gap-2 items-center py-4 px-4 relative z-10">
        <div class="col-span-5 flex items-center gap-3">
            <svg class="w-6 h-6 text-[#00ab55] group-hover:scale-110 transition-transform duration-300" viewBox="0 0 24 24" fill="none">
                <path opacity="0.5"
                    d="M2.5 6.5C2.5 4.29086 4.29086 2.5 6.5 2.5C8.70914 2.5 10.5 4.29086 10.5 6.5V9.16667C10.5 9.47666 10.5 9.63165 10.4659 9.75882C10.3735 10.1039 10.1039 10.3735 9.75882 10.4659C9.63165 10.5 9.47666 10.5 9.16667 10.5H6.5C4.29086 10.5 2.5 8.70914 2.5 6.5Z"
                    stroke="currentColor" stroke-width="1.5" />
                <path opacity="0.5"
                    d="M13.5 14.8333C13.5 14.5233 13.5 14.3683 13.5341 14.2412C13.6265 13.8961 13.8961 13.6265 14.2412 13.5341C14.3683 13.5 14.5233 13.5 14.8333 13.5H17.5C19.7091 13.5 21.5 15.2909 21.5 17.5C21.5 19.7091 19.7091 21.5 17.5 21.5C15.2909 21.5 13.5 19.7091 13.5 17.5V14.8333Z"
                    stroke="currentColor" stroke-width="1.5" />
                <path
                    d="M2.5 17.5C2.5 15.2909 4.29086 13.5 6.5 13.5H8.9C9.46005 13.5 9.74008 13.5 9.95399 13.609C10.1422 13.7049 10.2951 13.8578 10.391 14.046C10.5 14.2599 10.5 14.5399 10.5 15.1V17.5C10.5 19.7091 8.70914 21.5 6.5 21.5C4.29086 21.5 2.5 19.7091 2.5 17.5Z"
                    stroke="#00ab55" stroke-width="1.5" />
                <path
                    d="M13.5 6.5C13.5 4.29086 15.2909 2.5 17.5 2.5C19.7091 2.5 21.5 4.29086 21.5 6.5C21.5 8.70914 19.7091 10.5 17.5 10.5H14.6429C14.5102 10.5 14.4438 10.5 14.388 10.4937C13.9244 10.4415 13.5585 10.0756 13.5063 9.61196C13.5 9.55616 13.5 9.48982 13.5 9.35714V6.5Z"
                    stroke="#00ab55" stroke-width="1.5" />
            </svg>
            <h3 class="text-lg font-semibold text-[#00ab55] group-hover:text-green-700 transition-colors duration-300">Assets</h3>
        </div>

        <div class="col-span-2 flex justify-center">
            <span class="px-4 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full group-hover:bg-green-200 transition-colors duration-300">Code</span>
        </div>

        <div class="col-span-3 flex justify-end">
            <span class="text-lg font-semibold text-[#00ab55] group-hover:text-green-700 transition-colors duration-300">Actual Balance</span>
        </div>

        <div class="col-span-2 flex justify-end">
            <svg class="w-6 h-6 text-gray-400 group-hover:text-green-500 transition-colors duration-300" viewBox="0 0 24 24" fill="none">
                <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
    </div>
</div>

<div id="AssetsDetails" class="rounded-lg shadow-sm bg-white dark:bg-gray-800 mt-1" style="display: none;">
    <ul class="w-full">
        <?php $__currentLoopData = $assets->childAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $asset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make('coa.partials.child-account', ['account' => $asset, 'color' => 'green'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
</div>

<script>
    const contentAssetsDiv = document.getElementById('AssetsDetails');
    const AssetsToggleButton = document.querySelectorAll('.AssetsToggleButton');

    function toggleAssetsVisibility() {
        contentAssetsDiv.style.display = (contentAssetsDiv.style.display === 'none' || contentAssetsDiv.style.display === '') ? 'block' : 'none';
    }

    AssetsToggleButton.forEach(button => {
        button.addEventListener('click', toggleAssetsVisibility);
    });

    async function saveCode(assetId, value) {
        if (value.trim() === '') {
            showMessage('Code cannot be empty!');
            return;
        }

        let url = "<?php echo e(route('coa.updateCode', '__id__')); ?>".replace('__id__', assetId);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                },
                body: JSON.stringify({
                    code: value
                })
            });

            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();
            showMessage(data.message);
        } catch (error) {
            console.error('Error updating code:', error);
        }
    }

    function showMessage(message) {
        const messageArea = document.getElementById('message-area');
        const messageDiv = document.getElementById('message');

        messageDiv.innerText = message;
        messageArea.classList.remove('hidden');

        setTimeout(() => {
            messageArea.classList.add('hidden');
        }, 3000);
    }

    document.addEventListener('keydown', event => {
        if (event.target.matches('.code-input') && event.key === 'Enter') {
            event.preventDefault();
            const assetId = event.target.dataset.assetId;
            const value = event.target.value;
            saveCode(assetId, value);
        }
    });
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/coa/partials/assets.blade.php ENDPATH**/ ?>