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
    <?php if(session('success') || session('error')): ?>
    <div id="flash-message" class="alert 
        <?php if(session('success')): ?> alert-success 
        <?php elseif(session('error')): ?> alert-danger 
        <?php endif; ?>
        fixed-top-right">
        <?php echo e(session('success') ?? session('error')); ?>

    </div>
    <?php endif; ?>
    <div class="item-details bg-white rounded-lg shadow-md p-5">
        <div class="flex justify-between items-center w-full mb-3">
            <div class="bg-gray-200 p-2.5 rounded flex-grow">
                <h1><strong>Item Details</strong></h1>
            </div>
            <a href="<?php echo e(route('dashboard')); ?>" class="btn btn-success ml-2">Back to Item List</a>
        </div>
        <div class="mb-3 p-4 bg-white rounded-lg shadow-md">
            <h5 class="text-lg font-bold">Item Ref: <?php echo e($item->item_ref); ?></h5>
            <p class="text-gray-700"><strong>Description:</strong> <?php echo e($item->description); ?></p>
            <p class="text-gray-700"><strong>Item Type:</strong> <?php echo e($item->item_type); ?></p>
            <p class="text-gray-700"><strong>Client ID:</strong> <?php echo e($item->client_id); ?></p>
            <p class="text-gray-700"><strong>Item Status:</strong> <?php echo e($item->item_status); ?></p>
            <p class="text-gray-700"><strong>Item ID:</strong> <?php echo e($item->item_id); ?></p>
            <p class="text-gray-700"><strong>Item Code:</strong> <?php echo e($item->item_code); ?></p>
            <p class="text-gray-700"><strong>Time Signed:</strong> <?php echo e($item->time_signed); ?></p>
            <p class="text-gray-700"><strong>Client Email:</strong> <?php echo e($item->client_email); ?></p>
            <p class="text-gray-700"><strong>Agent Email:</strong> <?php echo e($item->agent_email); ?></p>
            <p class="text-gray-700"><strong>Total Price:</strong> <?php echo e($item->total_price); ?></p>
            <p class="text-gray-700"><strong>Payment Date:</strong> <?php echo e($item->payment_date); ?></p>
            <p class="text-gray-700"><strong>Paid:</strong> <?php echo e($item->paid ? 'Yes' : 'No'); ?></p>
            <p class="text-gray-700"><strong>Payment Time:</strong> <?php echo e($item->payment_time); ?></p>
            <p class="text-gray-700"><strong>Payment Amount:</strong> <?php echo e($item->payment_amount); ?></p>
            <p class="text-gray-700"><strong>Refunded:</strong> <?php echo e($item->refunded ? 'Yes' : 'No'); ?></p>
            <p class="text-gray-700"><strong>Trip Name:</strong> <?php echo e($item->trip_name); ?></p>
            <p class="text-gray-700"><strong>Trip Code:</strong> <?php echo e($item->trip_code); ?></p>
        </div>
    </div>
    <div class="tasks bg-white rounded-lg shadow-md p-5 mt-4">
        <div class="flex justify-between items-center w-full mb-3">
            <div class="bg-gray-200 p-2.5 rounded flex-grow">
                <h2 class="text-lg font-bold">Associated Tasks</h2>
            </div>
            <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded ml-2 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" onclick="createInvoice()">Create Invoice</button>
        </div>
        <?php if(!empty($tasks)): ?>
        <div class="divide-y divide-gray-200">
            <?php $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="flex justify-between items-center py-2">
                <div class="flex-grow">
                    <p class="text-gray-700 font-semibold"><?php echo e($task['reference']); ?></p>
                    <small class="text-gray-500"><?php echo e($task['description']); ?></small>
                </div>
                <div>
                    <input type="checkbox" class="task-checkbox h-5 w-5 text-blue-600" value="<?php echo e($task['id']); ?>" id="task-<?php echo e($task['id']); ?>" name="tasks[]">
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php else: ?>
        <p class="text-gray-500">No tasks associated with this item.</p>
        <?php endif; ?>
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

<script>
    function createInvoice() {
        const checkboxes = document.querySelectorAll(".task-checkbox:checked");
        const taskIds = [];

        checkboxes.forEach((checkbox) => {
            taskIds.push(checkbox.value);
        });

        if (taskIds.length === 0) {
            alert("Please select at least one task to create an invoice.");
            return;
        }

        // Construct the URL with query parameters
        const url = new URL(window.location.origin + "/invoices/create");
        url.searchParams.append("task_ids", JSON.stringify(taskIds));

        // Redirect to the constructed URL
        window.location.href = url.toString();
    }
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/items/show.blade.php ENDPATH**/ ?>