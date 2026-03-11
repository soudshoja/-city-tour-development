<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Sales - Summary</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1,h2,h3 { margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; }
        th { background: #f2f6ff; text-align: left; }
        .right { text-align: right; }
        .muted { color: #666; font-size: 11px; }
        .total-row { background: #eef4ff; font-weight: bold; }
        .header { margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo e($company->name ?? 'Company'); ?></h1>
        <div class="muted">
            Daily Sales Report (Summary)<br>
            Period: <?php echo e($from->format('d-M-Y')); ?> – <?php echo e($to->format('d-M-Y')); ?>

            <?php if($filteredAgent): ?> • Agent Filter Applied <?php endif; ?>
        </div>
    </div>

    <table style="margin-bottom:10px">
        <tr><th>Total Invoices</th><td class="right"><?php echo e($summary['totalInvoices']); ?></td></tr>
        <tr><th>Total Invoiced (KWD)</th><td class="right"><?php echo e(number_format($summary['totalInvoiced'],3)); ?></td></tr>
        <tr><th>Total Paid (KWD)</th><td class="right"><?php echo e(number_format($summary['totalPaid'],3)); ?></td></tr>
        <tr><th>Profit (KWD)</th><td class="right"><?php echo e(number_format($summary['profit'],3)); ?></td></tr>
        <tr><th>Refunds (KWD)</th><td class="right"><?php echo e(number_format($summary['refunds'] ?? 0,3)); ?></td></tr>
    </table>

    <h3>Agent Summary</h3>
    <table>
        <thead>
            <tr>
                <th>Agent</th>
                <th class="right">Invoices</th>
                <th class="right">Invoiced</th>
                <th class="right">Paid</th>
                <th class="right">Unpaid</th>
                <th class="right">Profit</th>
                <th class="right">Commission</th>
            </tr>
        </thead>
        <tbody>
        <?php
            $totInv=0; $totAmt=0; $totPaid=0; $totUnpaid=0; $totProfit=0; $totComm=0;
        ?>
        <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $totInv    += $row['totalInvoices'];
                $totAmt    += $row['totalInvoiced'];
                $totPaid   += $row['paid'];
                $totUnpaid += $row['unpaid'];
                $totProfit += $row['profit'];
                $totComm   += $row['commission'];
            ?>
            <tr>
                <td><?php echo e($row['agent']->name); ?></td>
                <td class="right"><?php echo e($row['totalInvoices']); ?></td>
                <td class="right"><?php echo e(number_format($row['totalInvoiced'],3)); ?></td>
                <td class="right"><?php echo e(number_format($row['paid'],3)); ?></td>
                <td class="right"><?php echo e(number_format($row['unpaid'],3)); ?></td>
                <td class="right"><?php echo e(number_format($row['profit'],3)); ?></td>
                <td class="right"><?php echo e(number_format($row['commission'],3)); ?></td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr class="total-row">
            <td>Total</td>
            <td class="right"><?php echo e($totInv); ?></td>
            <td class="right"><?php echo e(number_format($totAmt,3)); ?></td>
            <td class="right"><?php echo e(number_format($totPaid,3)); ?></td>
            <td class="right"><?php echo e(number_format($totUnpaid,3)); ?></td>
            <td class="right"><?php echo e(number_format($totProfit,3)); ?></td>
            <td class="right"><?php echo e(number_format($totComm,3)); ?></td>
        </tr>
        </tbody>
    </table>
</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/pdf/daily-sales-summary.blade.php ENDPATH**/ ?>