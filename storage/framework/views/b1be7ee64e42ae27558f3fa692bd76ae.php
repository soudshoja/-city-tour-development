<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="p-2 bg-gray-100 dark:bg-gray-800 rounded shadow mb-2 text-center text-xl font-semibold dark:text-gray-50">
        Accounts Receivable
    </div>
    <div class="mt-2 mb-4 flex justify-end">
        <div class="px-4 py-2 bg-blue-50 dark:bg-gray-700 border border-blue-200 dark:border-gray-600 rounded shadow text-xs text-gray-700 dark:text-gray-300">
            <div class="font-medium mb-1 text-center">
                <span class="inline-block bg-blue-200 dark:bg-gray-600 text-blue-700 dark:text-gray-200 px-2 py-0.5 rounded">
                    Info
                </span>
            </div>
            <div class="flex justify-end gap-4">
                <div class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full"></span>
                    <span>Amount Owed</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="inline-block w-2 h-2 bg-red-500 rounded-full"></span>
                    <span>Amount to Pay</span>
                </div>
            </div>
        </div>
    </div>
    <div class="space-y-4">
        <?php $__currentLoopData = $childAccountsReceivable->childAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php echo $__env->make('reports.account-child', ['account' => $account], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <script>
        function toggleTable(tableId, accountId) {
            const table = document.getElementById(tableId);
            const arrow = document.getElementById('arrow-' + accountId);
            if (table.classList.contains('hidden')) {
                table.classList.remove('hidden');
                arrow.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />';
            } else {
                table.classList.add('hidden');
                arrow.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />';
            }
        }
    </script>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/total-receivable.blade.php ENDPATH**/ ?>