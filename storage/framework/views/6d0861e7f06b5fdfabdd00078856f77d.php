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
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Account Summary</h1>

    <h2 class="text-xl font-semibold mt-6 mb-2">Accounts</h2>
    <table class="min-w-full border border-gray-300">
        <thead class="bg-gray-200">
            <tr>
                <th class="border px-4 py-2">Account Name</th>
                <th class="border px-4 py-2">Balance</th>
                <th class="border px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="border px-4 py-2"><?php echo e($account->name); ?></td>
                    <td class="border px-4 py-2">$<?php echo e(number_format($account->balance, 2)); ?></td>
                    <td class="border px-4 py-2">
                        <a href="<?php echo e(route('clients.show', ['id' => $account->company_id])); ?>" class="text-blue-500 hover:underline">View Clients</a>
                        <span> | </span>
                        <a href="" class="text-blue-500 hover:underline">View Suppliers</a>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <h2 class="text-xl font-semibold mt-6 mb-2">Accounts Receivable</h2>
    <ul class="list-disc list-inside">
        <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li>
                <a href="<?php echo e(route('clients.show', ['id' => $client->id])); ?>" class="text-blue-500 hover:underline"><?php echo e($client->full_name); ?></a>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>

    <h2 class="text-xl font-semibold mt-6 mb-2">Accounts Payable</h2>
    <ul class="list-disc list-inside">
        <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li>
                <a href="<?php echo e(route('clients.show', ['id' => $supplier->id])); ?>" class="text-blue-500 hover:underline"><?php echo e($supplier->name); ?></a>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/accsummary.blade.php ENDPATH**/ ?>