<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Sales - Details</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h2,h3 { margin: 0 0 6px; }
        .muted { font-size: 11px; margin-bottom: 6px; }
        table { width:100%; border-collapse: collapse; margin-bottom: 10px; }
        th,td { border:1px solid #ddd; padding:5px 6px; }
        th { background:#f2f6ff; }
        .right { text-align: right; }
        .agent-header { margin-top: 10px; margin-bottom: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <h2><?php echo e($company->name ?? 'Company'); ?></h2>
    <div class="muted">Daily Sales Report (Details) • Period: <?php echo e($from->format('d-M-Y')); ?> – <?php echo e($to->format('d-M-Y')); ?></div>

    <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="agent-header">Agent: <?php echo e($row['agent']->name); ?></div>
        <?php if($row['invoices']->isEmpty()): ?>
            <table>
                <tr>
                    <td style="text-align:center; background:#f2f6ff; color:#666; padding:8px; font-style:italic;">
                        No invoices found for this agent within the selected date range.
                    </td>
                </tr>
            </table>
            <?php continue; ?>
        <?php endif; ?>
        <table>
            <thead>
            <tr>
                <th style="width: 16%">Invoice No</th>
                <th style="width: 10%">Date</th>
                <th style="width: 18%">Bill To</th>
                <th style="width: 10%">Amount</th>
                <th style="width: 10%">Profit</th>
                <th style="width: 10%">Commission</th>
                <th style="width: 10%">Paid</th>
                <th style="width: 10%">Unpaid</th>
            </tr>
            </thead>
            <tbody>
            <?php $__currentLoopData = $row['invoices']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($invoice->invoice_number); ?></td>
                    <td><?php echo e(\Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')); ?></td>
                    <td><?php echo e($invoice->client?->full_name ?? '—'); ?></td>
                    <td class="right"><?php echo e(number_format($invoice->amount, 3)); ?></td>
                    <td class="right"><?php echo e(number_format($invoice->computed_profit ?? 0, 3)); ?></td>
                    <td class="right"><?php echo e(number_format($invoice->computed_commission ?? 0, 3)); ?></td>
                    <td class="right"><?php echo e(number_format($invoice->paid_amount ?? 0, 3)); ?></td>
                    <td class="right"><?php echo e(number_format($invoice->unpaid_amount ?? 0, 3)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <th colspan="3" class="right">Totals</th>
                <th class="right"><?php echo e(number_format($row['totalInvoiced'], 3)); ?></th>
                <th class="right"><?php echo e(number_format($row['profit'], 3)); ?></th>
                <th class="right"><?php echo e(number_format($row['commission'], 3)); ?></th>
                <th class="right"><?php echo e(number_format($row['paid'], 3)); ?></th>
                <th class="right"><?php echo e(number_format($row['unpaid'], 3)); ?></th>
            </tr>
            </tbody>
        </table>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <?php if(in_array($type, ['all','refund']) && $refunds->isNotEmpty()): ?>
    <h3 style="margin-top:12px;">Refunds</h3>
    <table>
        <thead>
        <tr>
            <th>Refund Date</th>
            <th>Refund #</th>
            <th>Original Invoice</th>
            <th>Client</th>
            <th>Agent</th>
            <th>Type</th>
            <th class="right">Amount</th>
        </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $refunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $refund): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e(\Carbon\Carbon::parse($refund->created_at)->format('d-m-Y')); ?></td>
                <td><?php echo e($refund->refund_number); ?></td>
                <td><?php echo e($refund->original_invoice_number ?? 'N/A'); ?></td>
                <td><?php echo e($refund->invoice?->client?->full_name ?? $refund->task?->client?->full_name ?? 'N/A'); ?></td>
                <td><?php echo e($refund->invoice?->agent?->name ?? $refund->task?->agent?->name ?? 'N/A'); ?></td>
                <td><?php echo e($refund->refund_type); ?></td>
                <td class="right"><?php echo e(number_format($refund->total_nett_refund, 3)); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <th colspan="6" class="right">Total Refunds</th>
                <th class="right">
                <?php echo e(number_format($refunds->sum('total_nett_refund'), 3)); ?>

                </th>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if(in_array($type, ['all','supplier']) && !empty($groups)): ?>
    <h3 style="margin-top:12px;">Supplier Performance</h3>
        <?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupName => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="muted"><?php echo e($groupName); ?></div>
            <table>
                <thead>
                    <tr>
                    <th>Supplier</th>
                    <th class="right">Tasks</th>
                    <th class="right">Total Task Price</th>
                    <th class="right">Paid</th>
                    <th class="right">Unpaid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = ($group['rows'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($row['supplier']->name ?? ($row['supplier_account_name'] ?? '—')); ?></td>
                        <td class="right"><?php echo e($row['totalTasks']); ?></td>
                        <td class="right"><?php echo e(number_format($row['totalTaskPrice'], 3)); ?></td>
                        <td class="right"><?php echo e(number_format($row['paid'], 3)); ?></td>
                        <td class="right"><?php echo e(number_format($row['unpaid'], 3)); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <th class="right">Totals</th>
                        <th class="right"><?php echo e(number_format($group['totals']['totalTasks'] ?? 0)); ?></th>
                        <th class="right"><?php echo e(number_format($group['totals']['totalTaskPrice'] ?? 0, 3)); ?></th>
                        <th class="right"><?php echo e(number_format($group['totals']['paid'] ?? 0, 3)); ?></th>
                        <th class="right"><?php echo e(number_format($group['totals']['unpaid'] ?? 0, 3)); ?></th>
                    </tr>
                </tbody>
            </table>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/pdf/daily-sales-details.blade.php ENDPATH**/ ?>