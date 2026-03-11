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
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Agent Accounting Report</h1>

    <!-- Agent Summary Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Agent Name</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Total Transactions</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Total Debit</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Total Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-50">
                    <td class="py-2 px-4 border-b"><?php echo e($agent->agent_name); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo e($agent->total_transactions); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo e($agent->total_debit); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo e($agent->total_credit); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>

    <!-- Agent Ledger Details Table -->
    <h2 class="text-xl font-bold text-gray-800 mt-10 mb-6">Agent Ledger Details</h2>
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Agent Name</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Transaction Date</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Description</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Debit</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Credit</th>
                    <th class="py-2 px-4 bg-gray-100 text-gray-700 font-semibold text-left border-b border-gray-200">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $agentLedgers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ledger): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-50">
                    <td class="py-2 px-4 border-b"><?php echo e($ledger->agent_name); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo e($ledger->transaction_date); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo e($ledger->description); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo e($ledger->debit); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo e($ledger->credit); ?></td>
                    <td class="py-2 px-4 border-b"><?php echo e($ledger->balance); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
</div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/agent.blade.php ENDPATH**/ ?>