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
<div class="container mx-auto my-10">
    <!-- Agent Summary Section -->
    <h2 class="text-2xl font-bold mb-6">Agent Summary</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full border-collapse bg-white">
            <thead>
                <tr class="text-left border-b">
                    <th class="px-6 py-3 font-semibold">Agent Name</th>
                    <th class="px-6 py-3 font-semibold">Total Transactions</th>
                    <th class="px-6 py-3 font-semibold">Total Debit</th>
                    <th class="px-6 py-3 font-semibold">Total Credit</th>
                    <th class="px-6 py-3 font-semibold">Net Balance</th>
                    <th class="px-6 py-3 font-semibold">Average Transaction</th>
                    <th class="px-6 py-3 font-semibold">Profit Margin</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="border-b">
                        <td class="px-6 py-3"><?php echo e($agent->agent_name); ?></td>
                        <td class="px-6 py-3"><?php echo e($agent->total_transactions); ?></td>
                        <td class="px-6 py-3">$<?php echo e(number_format($agent->total_debit, 2)); ?></td>
                        <td class="px-6 py-3">$<?php echo e(number_format($agent->total_credit, 2)); ?></td>
                        <td class="px-6 py-3">$<?php echo e(number_format($agent->net_balance, 2)); ?></td>
                        <td class="px-6 py-3">$<?php echo e(number_format($agent->avg_transaction_value, 2)); ?></td>
                        <td class="px-6 py-3"><?php echo e(number_format($agent->profit_margin * 100, 2)); ?>%</td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>

    <!-- Client Summary Section -->
    <h2 class="text-2xl font-bold mt-10 mb-6">Client Summary</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full border-collapse bg-white">
            <thead>
                <tr class="text-left border-b">
                    <th class="px-6 py-3 font-semibold">Client Name</th>
                    <th class="px-6 py-3 font-semibold">Total Transactions</th>
                    <th class="px-6 py-3 font-semibold">Total Debit</th>
                    <th class="px-6 py-3 font-semibold">Total Credit</th>
                    <th class="px-6 py-3 font-semibold">Outstanding Balance</th>
                    <th class="px-6 py-3 font-semibold">Average Transaction</th>
                    <th class="px-6 py-3 font-semibold">Credit Score</th>
                    <th class="px-6 py-3 font-semibold">Last Transaction</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="border-b">
                        <td class="px-6 py-3"><?php echo e($client->client_name); ?></td>
                        <td class="px-6 py-3"><?php echo e($client->total_transactions); ?></td>
                        <td class="px-6 py-3">$<?php echo e(number_format($client->total_debit, 2)); ?></td>
                        <td class="px-6 py-3">$<?php echo e(number_format($client->total_credit, 2)); ?></td>
                        <td class="px-6 py-3">$<?php echo e(number_format($client->outstanding_balance, 2)); ?></td>
                        <td class="px-6 py-3">$<?php echo e(number_format($client->avg_transaction_value, 2)); ?></td>
                        <td class="px-6 py-3"><?php echo e($client->credit_score); ?>/5</td>
                        <td class="px-6 py-3"><?php echo e($client->last_transaction_date); ?></td>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/summary.blade.php ENDPATH**/ ?>