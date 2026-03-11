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
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        
        <div class="text-center mb-6">
            <svg class="w-16 h-16 text-green-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Upload Approved</h2>
            <?php if(session('message')): ?>
                <p class="text-green-700 text-lg"><?php echo e(session('message')); ?></p>
            <?php endif; ?>
        </div>

        
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-6">
            <h3 class="font-semibold text-lg mb-4 text-gray-900">Upload Summary</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-600">File:</span>
                    <span class="font-semibold"><?php echo e($bulkUpload->original_filename); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Status:</span>
                    <span class="font-semibold text-blue-600"><?php echo e(ucfirst($bulkUpload->status)); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Invoices to create:</span>
                    <span class="font-semibold text-green-600"><?php echo e($invoiceCount); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Clients:</span>
                    <span class="font-semibold"><?php echo e($clientCount); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total valid tasks:</span>
                    <span class="font-semibold"><?php echo e($bulkUpload->valid_rows); ?></span>
                </div>
                <?php if($bulkUpload->error_rows > 0): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Skipped errors:</span>
                        <span class="font-semibold text-red-600"><?php echo e($bulkUpload->error_rows); ?></span>
                    </div>
                <?php endif; ?>
                <?php if($bulkUpload->flagged_rows > 0): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Skipped flagged:</span>
                        <span class="font-semibold text-yellow-600"><?php echo e($bulkUpload->flagged_rows); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        
        <?php if($bulkUpload->status === 'processing'): ?>
            
            <div class="bg-blue-50 border border-blue-200 p-4 rounded mb-6">
                <div class="flex items-center gap-3">
                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <p class="text-blue-900 font-semibold">Invoices are being created in the background. Page will auto-refresh in <span id="countdown">5</span> seconds...</p>
                </div>
            </div>

            
            <script>
                let countdown = 5;
                const countdownEl = document.getElementById('countdown');

                const interval = setInterval(() => {
                    countdown--;
                    countdownEl.textContent = countdown;

                    if (countdown <= 0) {
                        clearInterval(interval);
                        window.location.reload();
                    }
                }, 1000);
            </script>
        <?php elseif($bulkUpload->status === 'failed'): ?>
            
            <div class="bg-red-50 border border-red-200 p-4 rounded mb-6">
                <p class="text-red-900 font-semibold mb-2">Invoice creation failed.</p>
                <?php if(is_array($bulkUpload->error_summary) && isset($bulkUpload->error_summary['job_failure'])): ?>
                    <p class="text-sm text-red-700">Error: <?php echo e($bulkUpload->error_summary['job_failure']); ?></p>
                <?php endif; ?>
                <p class="text-sm text-red-600 mt-2">Please contact support or try uploading again.</p>
            </div>
        <?php elseif($bulkUpload->status === 'completed'): ?>
            
            <div class="bg-green-50 border border-green-200 p-4 rounded mb-6">
                <p class="text-green-900 font-semibold">All invoices have been created successfully.</p>
                <p class="text-sm text-green-700 mt-1">Invoice PDFs are being emailed to the company accountant and uploading agent.</p>
            </div>
        <?php endif; ?>

        
        <?php if($invoices->isNotEmpty()): ?>
            <h3 class="font-semibold mb-3 text-lg">Created Invoices (<?php echo e($invoices->count()); ?>)</h3>
            <ul class="space-y-2">
                <?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="border rounded p-3 flex justify-between items-center">
                        <div>
                            <p class="font-semibold"><?php echo e($invoice->invoice_number); ?></p>
                            <p class="text-sm text-gray-600"><?php echo e($invoice->client->full_name ?? 'Unknown Client'); ?></p>
                            <p class="text-xs text-gray-400"><?php echo e($invoice->invoice_date); ?> &middot; <?php echo e($invoice->currency); ?> <?php echo e(number_format($invoice->amount, 3)); ?></p>
                        </div>
                        <div class="flex gap-2">
                            <a href="<?php echo e(route('invoice.show', [$invoice->company_id, $invoice->invoice_number])); ?>" class="text-blue-600 hover:underline text-sm">View</a>
                            <a href="<?php echo e(route('invoice.pdf', [$invoice->company_id, $invoice->invoice_number])); ?>" class="text-green-600 hover:underline text-sm">Download PDF</a>
                        </div>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        <?php endif; ?>

        
        <div class="mt-6 text-center">
            <a href="<?php echo e(route('dashboard')); ?>" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                Upload Another File
            </a>
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/bulk-invoice/success.blade.php ENDPATH**/ ?>