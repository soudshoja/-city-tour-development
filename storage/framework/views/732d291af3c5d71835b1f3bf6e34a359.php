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
    <div class="container">
    <div class="mb-5 flex flex-col md:flex-row justify-between items-center w-full space-y-4 md:space-y-0">
        <h3 class="text-2xl font-bold text-gray-700 mb-4">Agent Invoices Detail</h3>
        <a href="<?php echo e(route('agents.show', ['id' => $agent->id])); ?>" class="text-blue-500 text-xs underline hover:text-blue-700">
            Back to Agent Overview
        </a>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p><strong>Name:</strong> <?php echo e($agent->name); ?></p>
                        <p><strong>Email:</strong> <?php echo e($agent->email); ?></p>
                    </div>
                    <div>
                        <p><strong>Phone:</strong> <?php echo e($agent->phone_number); ?></p>
                        <p><strong>Company:</strong> <?php echo e($agent->branch->company->name); ?></p>
                    </div>
                    <div>
                        <p><strong>Type:</strong> <?php echo e($agent->type); ?></p>
                    </div>
                </div>

            <!-- Search input on the right -->
            <div class="w-full md:w-auto">
                <input type="text" placeholder="Search..."
                    class="w-full md:w-auto pr-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder:text-gray-500" />
            </div>
        </div>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Invoice Number</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($invoice->invoice_number); ?></td>
                    <td><?php echo e($invoice->client->full_name); ?></td>
                    <td><?php echo e($invoice->amount); ?></td>
                    <td>
                        <span class="badge <?php echo e($invoice->status == 'unpaid' ? 'badge-danger' : ($invoice->status == 'paid' ? 'badge-success' : 'badge-warning')); ?>">
                            <?php echo e(ucfirst($invoice->status)); ?>

                        </span>
                    </td>
                    <td><?php echo e($invoice->created_at); ?></td>
                    <td>
                       <button  href="/invoice/<?php echo e($invoice->invoice_number); ?>" class="btn btn-primary mt-2">View</button>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

              <div class="mt-4">
                    <?php echo e($invoices->appends(['section' => 'invoices'])->links()); ?>

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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/list.blade.php ENDPATH**/ ?>