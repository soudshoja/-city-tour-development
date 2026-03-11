<div>
    <h3>Task List</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Description</th>
                <th>Agent</th>
                <th>Client</th>
                <th>Supplier</th>
                <th>Price</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($task->id); ?></td>
                    <td><?php echo e($task->description); ?></td>
                    <td><?php echo e($task->agentName); ?></td>
                    <td><?php echo e($task->clientName); ?></td>
                    <td><?php echo e($task->supplierName); ?></td>
                    <td><?php echo e($task->price); ?></td>
                    <td><?php echo e($task->status); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/task-table.blade.php ENDPATH**/ ?>