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
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-700">Edit Refund #<?php echo e($refund->refund_number); ?></h1>
            <a href="<?php echo e(route('refunds.index')); ?>"
               class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-400 transition duration-200">
                ← Back
            </a>
        </div>

        <?php
            $isReadOnly = strtolower($refund->status) === 'completed';
            $isEditing = true;
        ?>

        <form action="<?php echo e(route('refunds.update', $refund->id)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="mt-8 p-6 border rounded-lg bg-white">
                <div class="mb-6 rounded-lg p-4 <?php echo e($isPaidInvoice ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'); ?>">
                    <div class="flex items-center gap-4 flex-wrap text-sm font-semibold">
                        <div class="<?php echo e($isPaidInvoice ? 'text-green-700' : 'text-red-800'); ?>">
                            Original Invoice: #<?php echo e($firstInvoice?->invoice_number ?? 'N/A'); ?>

                        </div>
                        <span class="text-gray-400">|</span>
                        <div class="<?php echo e($isPaidInvoice ? 'text-green-700' : 'text-red-800'); ?>">
                            Status: <?php echo e(ucfirst($firstInvoice?->status ?? 'N/A')); ?>

                        </div>
                    </div>
                    <?php if($isReadOnly): ?>
                        <div class="mt-2 text-sm text-gray-600 italic">
                            This refund is locked because the refund has been marked as <strong>Completed</strong>.
                            You can no longer edit it.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg border">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Client Info</h3>
                        <p><strong>Name:</strong> <?php echo e($firstTask->client->full_name ?? 'N/A'); ?></p>
                        <p><strong>Email:</strong> <?php echo e($firstTask->client->email ?? 'N/A'); ?></p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg border">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Agent Info</h3>
                        <p><strong>Name:</strong> <?php echo e($firstTask->agent->name ?? 'N/A'); ?></p>
                        <p><strong>Email:</strong> <?php echo e($firstTask->agent->email ?? 'N/A'); ?></p>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Refund Date</label>
                    <input type="date" name="date" id="date"
                           value="<?php echo e($refund->refund_date?->toDateString() ?? now()->toDateString()); ?>"
                           <?php echo e($isReadOnly ? 'readonly disabled' : ''); ?>

                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>

                <?php if($isPaidInvoice): ?>
                    <div class="mt-6 p-6 border rounded-lg bg-gray-50">
                        <h3 class="text-xl font-bold mb-4">Refund Method</h3>
                        <select name="method" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                            <option value="">Select</option>
                            <option value="Cash" <?php echo e($refund->method == 'Cash' ? 'selected' : ''); ?>>Cash</option>
                            <option value="Bank" <?php echo e($refund->method == 'Bank' ? 'selected' : ''); ?>>Bank</option>
                            <option value="Online" <?php echo e($refund->method == 'Online' ? 'selected' : ''); ?>>Online</option>
                            <option value="Credit" <?php echo e($refund->method == 'Credit' ? 'selected' : ''); ?>><?php echo e($firstTask->client->full_name); ?>'s Credit</option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="mt-6">
                        <?php echo $__env->make('refunds.partial.payment-gateway-selection', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-10">
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Remarks</label>
                        <input type="text" name="remarks" value="<?php echo e(old('remarks', $refund->remarks)); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">Internal Remarks</label>
                        <input type="text" name="remarks_internal" value="<?php echo e(old('remarks_internal', $refund->remarks_internal)); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-gray-700 font-semibold mb-2">Reason</label>
                    <textarea name="reason" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo e(old('reason', $refund->reason)); ?></textarea>
                </div>

                <?php if (! ($isReadOnly)): ?>
                    <button type="submit"
                            class="mt-6 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
                        Update Refund
                    </button>
                <?php endif; ?>
            </div>

            <?php $__currentLoopData = $refund->refundDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $task = $detail->task;
                    $sourceTask = $detail->computed_source_task;
                    $invoiceDetail = $detail->computed_invoice_detail;
                    $invoiceStatus = $detail->computed_invoice_status;
                ?>

                <div class="task-refund-section bg-gray-50 border p-6 mt-8 rounded-lg shadow-sm">
                    <h3 class="text-xl font-bold mb-4">Refund Task #<?php echo e($task->reference); ?></h3>
                    <input type="hidden" name="tasks[<?php echo e($loop->index); ?>][task_id]" value="<?php echo e($task->id); ?>">

                    <?php if(in_array($invoiceStatus, ['paid', 'refunded', 'partial refund'])): ?>
                        <?php echo $__env->make('refunds.partial.paid-invoice-section', [
                            'task' => $task,
                            'sourceTask' => $sourceTask,
                            'invoiceDetail' => $invoiceDetail,
                            'refundDetail' => $detail,
                            'loopIndex' => $loop->index,
                            'isEditing' => $isEditing,
                            'isReadOnly' => $isReadOnly,
                        ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    <?php else: ?>
                        <?php echo $__env->make('refunds.partial.unpaid-invoice-section', [
                            'task' => $task,
                            'sourceTask' => $sourceTask,
                            'invoiceDetail' => $invoiceDetail,
                            'refundDetail' => $detail,
                            'loopIndex' => $loop->index,
                            'isEditing' => $isEditing,
                            'isReadOnly' => $isReadOnly,
                        ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </form>
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
<?php endif; ?>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/refunds/edit.blade.php ENDPATH**/ ?>