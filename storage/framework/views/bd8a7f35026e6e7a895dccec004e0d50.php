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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <h2 class="text-2xl font-bold mb-6">Preview Bulk Upload</h2>

        
        <?php if(session('message')): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded mb-6">
                <?php echo e(session('message')); ?>

            </div>
        <?php endif; ?>

        
        <?php if($errors->any()): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded mb-4">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <p><?php echo e($error); ?></p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>

        
        <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6">
            <h3 class="font-semibold text-lg mb-3">Upload Summary</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Filename</p>
                    <p class="font-semibold"><?php echo e($bulkUpload->original_filename); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Total Rows</p>
                    <p class="font-semibold"><?php echo e($bulkUpload->total_rows); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Valid Rows</p>
                    <p class="font-semibold text-green-600"><?php echo e($bulkUpload->valid_rows); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Flagged Rows</p>
                    <p class="font-semibold text-yellow-600"><?php echo e($bulkUpload->flagged_rows); ?></p>
                </div>
            </div>
            <div class="mt-3 pt-3 border-t border-blue-200">
                <p class="text-lg font-bold text-blue-900">
                    <?php echo e(count($invoiceGroups)); ?> invoice(s) for <?php echo e($clientCount); ?> client(s)
                </p>
            </div>
            <?php if($bulkUpload->error_rows > 0): ?>
                <div class="mt-3">
                    <a href="<?php echo e(route('bulk-invoices.error-report', $bulkUpload->id)); ?>"
                       class="text-blue-600 hover:text-blue-800 underline text-sm">
                        Download Error Report (<?php echo e($bulkUpload->error_rows); ?> errors)
                    </a>
                </div>
            <?php endif; ?>
        </div>

        
        <div class="mb-6">
            <h3 class="text-xl font-bold mb-4">Invoices to Create (<?php echo e(count($invoiceGroups)); ?>)</h3>

            <?php if($invoiceGroups->isEmpty()): ?>
                <div class="bg-gray-50 border border-gray-200 rounded p-6 text-center text-gray-600">
                    No valid invoices to create.
                </div>
            <?php else: ?>
                <?php $__currentLoopData = $invoiceGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupKey => $rows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $firstRow = $rows->first();
                        $clientName = $firstRow->client->full_name ?? 'Unknown';
                        $clientPhone = $firstRow->client->phone ?? '';
                        $invoiceDate = $firstRow->raw_data['invoice_date'] ?? date('Y-m-d');
                        $taskCount = $rows->count();
                    ?>

                    <div class="border border-gray-200 rounded-lg shadow-sm p-4 mb-4 bg-white">
                        
                        <div class="flex justify-between items-start mb-3 pb-3 border-b border-gray-100">
                            <div>
                                <h4 class="font-bold text-lg"><?php echo e($clientName); ?></h4>
                                <?php if($clientPhone): ?>
                                    <p class="text-gray-600 text-sm"><?php echo e($clientPhone); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-600"><?php echo e($taskCount); ?> task(s)</p>
                                <p class="text-sm text-gray-500">Invoice Date: <?php echo e($invoiceDate); ?></p>
                            </div>
                        </div>

                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Row #</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Task Reference</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Task Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Selling Price</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Payment Reference</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php
                                            $task = \App\Models\Task::find($row->matched['task_id'] ?? null);
                                            $payment = \App\Models\Payment::find($row->matched['payment_id'] ?? null);
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2"><?php echo e($row->row_number); ?></td>
                                            <td class="px-3 py-2">
                                                <div><?php echo e($row->raw_data['task_reference'] ?? '-'); ?></div>
                                                <?php if($task): ?>
                                                    <div class="text-xs text-gray-500">ID: <?php echo e($task->id); ?> | Type: <?php echo e(ucfirst($task->type)); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                                    <?php echo e(ucfirst($row->raw_data['task_status'] ?? '-')); ?>

                                                </span>
                                            </td>
                                            <td class="px-3 py-2 font-semibold"><?php echo e(number_format($row->raw_data['selling_price'] ?? 0, 3)); ?> KWD</td>
                                            <td class="px-3 py-2">
                                                <div><?php echo e($row->raw_data['payment_reference'] ?? '-'); ?></div>
                                                <?php if($payment): ?>
                                                    <div class="text-xs text-gray-500"><?php echo e($payment->voucher_number ?? 'N/A'); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2 text-gray-600"><?php echo e($row->raw_data['notes'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </div>

        
        <?php if($flaggedRows->isNotEmpty()): ?>
            <div class="mb-6">
                <h3 class="text-xl font-bold mb-4">Flagged Rows - Requires Review (<?php echo e($flaggedRows->count()); ?>)</h3>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-yellow-800 mb-3 font-semibold">
                        ⚠ These rows have unknown clients and will NOT be included in invoice creation.
                    </p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-yellow-100 border-b border-yellow-300">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Row #</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Client Mobile</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Task Reference</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Task Status</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Selling Price</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Flag Reason</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-yellow-100 bg-white">
                                <?php $__currentLoopData = $flaggedRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="hover:bg-yellow-50">
                                        <td class="px-3 py-2"><?php echo e($row->row_number); ?></td>
                                        <td class="px-3 py-2"><?php echo e($row->raw_data['client_mobile'] ?? '-'); ?></td>
                                        <td class="px-3 py-2"><?php echo e($row->raw_data['task_reference'] ?? '-'); ?></td>
                                        <td class="px-3 py-2"><?php echo e($row->raw_data['task_status'] ?? '-'); ?></td>
                                        <td class="px-3 py-2"><?php echo e(number_format($row->raw_data['selling_price'] ?? 0, 3)); ?> KWD</td>
                                        <td class="px-3 py-2 text-yellow-700 font-medium"><?php echo e($row->flag_reason); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        
        <div x-data="{ showApproveModal: false, showRejectModal: false }" class="flex gap-4 mt-6 justify-end">
            <?php if($invoiceGroups->count() > 0): ?>
                <button @click="showApproveModal = true" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded">
                    Approve All (<?php echo e(count($invoiceGroups)); ?> invoices)
                </button>
            <?php endif; ?>
            <button @click="showRejectModal = true" class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-6 py-2 rounded">
                Reject Upload
            </button>

            <!-- Approve Confirmation Modal -->
            <div x-show="showApproveModal" x-cloak
                 @keydown.escape.window="showApproveModal = false"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div @click.outside="showApproveModal = false"
                     class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
                    <h3 class="text-lg font-bold mb-4">Confirm Invoice Creation</h3>
                    <p class="mb-2">This will create <strong><?php echo e(count($invoiceGroups)); ?> invoices</strong> for <strong><?php echo e($clientCount); ?> clients</strong> from <strong><?php echo e($bulkUpload->valid_rows); ?> tasks</strong>.</p>
                    <p class="text-sm text-gray-600">This action cannot be undone.</p>
                    <?php if($flaggedRows->isNotEmpty()): ?>
                        <p class="text-sm text-yellow-700 mt-2">Note: <?php echo e($flaggedRows->count()); ?> flagged row(s) will NOT be included.</p>
                    <?php endif; ?>
                    <div class="mt-6 flex gap-3 justify-end">
                        <button @click="showApproveModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">Cancel</button>
                        <form method="POST" action="<?php echo e(route('bulk-invoices.approve', $bulkUpload->id)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded font-semibold">Confirm Approval</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reject Confirmation Modal -->
            <div x-show="showRejectModal" x-cloak
                 @keydown.escape.window="showRejectModal = false"
                 class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div @click.outside="showRejectModal = false"
                     class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
                    <h3 class="text-lg font-bold mb-4">Confirm Rejection</h3>
                    <p class="mb-2">This will discard the upload. No invoices will be created.</p>
                    <p class="text-sm text-gray-600">File: <?php echo e($bulkUpload->original_filename); ?></p>
                    <div class="mt-6 flex gap-3 justify-end">
                        <button @click="showRejectModal = false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded">Cancel</button>
                        <form method="POST" action="<?php echo e(route('bulk-invoices.reject', $bulkUpload->id)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded font-semibold">Reject Upload</button>
                        </form>
                    </div>
                </div>
            </div>
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
<?php endif; ?>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/bulk-invoice/preview.blade.php ENDPATH**/ ?>