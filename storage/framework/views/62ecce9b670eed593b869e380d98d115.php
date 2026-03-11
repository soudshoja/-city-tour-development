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
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Performance Summary</h1>

    <!-- Agents Performance Summary -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-700 mb-4">Agent Performance Summary</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800"><?php echo e($agent->agent_name); ?></h3>
                <p class="text-gray-500 text-sm mb-4">Agent ID: <?php echo e($agent->id); ?></p>
                <div class="mt-4">
                    <p class="text-gray-600"><strong>Total Transactions:</strong> <?php echo e($agent->total_transactions); ?></p>
                    <p class="text-gray-600"><strong>Total Debit:</strong> $<?php echo e(number_format($agent->total_debit, 2)); ?></p>
                    <p class="text-gray-600"><strong>Total Credit:</strong> $<?php echo e(number_format($agent->total_credit, 2)); ?></p>
                    <p class="text-gray-600"><strong>Balance:</strong> $<?php echo e(number_format($agent->balance, 2)); ?></p>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-sm text-gray-500"><?php echo e($agent->performance_score > 0 ? 'Good Performance' : 'Needs Improvement'); ?></span>
                    <div class="bg-blue-100 text-blue-500 text-xs font-semibold px-2 py-1 rounded">
                        Performance Score: <?php echo e($agent->performance_score); ?>

                    </div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </section>

    <!-- Clients Performance Summary -->
    <section>
        <h2 class="text-2xl font-semibold text-gray-700 mb-4">Client Performance Summary</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800"><?php echo e($client->client_name); ?></h3>
                <p class="text-gray-500 text-sm mb-4">Client ID: <?php echo e($client->id); ?></p>
                <div class="mt-4">
                    <p class="text-gray-600"><strong>Total Transactions:</strong> <?php echo e($client->total_transactions); ?></p>
                    <p class="text-gray-600"><strong>Total Debit:</strong> $<?php echo e(number_format($client->total_debit, 2)); ?></p>
                    <p class="text-gray-600"><strong>Total Credit:</strong> $<?php echo e(number_format($client->total_credit, 2)); ?></p>
                    <p class="text-gray-600"><strong>Balance:</strong> $<?php echo e(number_format($client->balance, 2)); ?></p>
                    <p class="text-gray-600"><strong>Payment Status:</strong> <?php echo e($client->is_good_payer ? 'Good' : 'Poor'); ?></p>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-sm text-gray-500"><?php echo e($client->is_good_payer ? 'Reliable Client' : 'Late Payments'); ?></span>
                    <div class="bg-green-100 text-green-500 text-xs font-semibold px-2 py-1 rounded">
                        Rating: <?php echo e($client->client_rating); ?>/5
                    </div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </section>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/performance.blade.php ENDPATH**/ ?>