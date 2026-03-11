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
<?php if($invoices->isEmpty()): ?>
    <p class="text-gray-600">No invoices for this agent.</p>
<?php else: ?>
    <table class="min-w-full bg-white border border-gray-300 mt-4">
        <thead>
            <tr>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">invoice Number</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">invoice Date</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Status</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Client</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="py-4 px-6 border-b"><?php echo e($invoice->invoice_number); ?></td>
                    <td class="py-4 px-6 border-b"><?php echo e($invoice->created_at->format('Y-m-d')); ?></td>
                    <td class="py-4 px-6 border-b"><?php echo e($invoice->status); ?></td>
                    <td class="py-4 px-6 border-b"><?php echo e($invoice->client->full_name); ?></td>
                    <td class="py-4 px-6 border-b">
                        <a href="#" class="text-indigo-500">View</a>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <div class="mt-4">
        <?php echo e($invoices->appends(['section' => 'invoices'])->links()); ?>

    </div>
<?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/agents/partials/invoices.blade.php ENDPATH**/ ?>