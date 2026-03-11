<div>
    <h3>Invoice List</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Invoice Number</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Agent</th>
                <th>Client</th>
            </tr>
        </thead>
        <tbody>
            
            <?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($invoice['id']); ?></td>
                    <td><?php echo e($invoice['invoice_number']); ?></td>
                    <td>$<?php echo e(number_format($invoice['total_amount'], 2)); ?></td>
                    <td><?php echo e(ucfirst($invoice['status'])); ?></td>
                    <td><?php echo e($invoice['agentId']); ?></td>
                    <td><?php echo e($invoice['clientId']); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/invoice-table.blade.php ENDPATH**/ ?>