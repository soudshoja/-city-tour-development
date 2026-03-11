<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Creditors Report by Supplier - <?php echo e($accountForReport->name); ?></title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #1a365d;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2d3748;
        }
        .report-info {
            font-size: 12px;
            color: #666;
        }
        .summary-section {
            margin-bottom: 25px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            vertical-align: top;
        }
        .summary-label {
            font-size: 11px;
            color: #666;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #1a365d;
        }
        .outstanding {
            color: #dc2626;
        }
        .supplier-section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        .supplier-header {
            background-color: #4c51bf;
            color: white;
            padding: 15px;
            font-size: 16px;
            font-weight: bold;
        }
        .supplier-info {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #ddd;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        td {
            font-size: 9px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .status-badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }
        .status-issued { background-color: #d1fae5; color: #065f46; }
        .status-confirmed { background-color: #dbeafe; color: #1e40af; }
        .status-reissued { background-color: #fef3c7; color: #92400e; }
        .status-refund { background-color: #fed7aa; color: #9a3412; }
        .status-void { background-color: #fecaca; color: #991b1b; }
        .status-emd { background-color: #e9d5ff; color: #7c2d12; }
        .supplier-total-row {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 10px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .page-break {
            page-break-before: always;
        }
        .suppliers-summary {
            margin-bottom: 25px;
        }
        .suppliers-summary table {
            margin-bottom: 0;
        }
        .suppliers-summary th, .suppliers-summary td {
            padding: 8px;
        }
        .suppliers-summary td {
            font-size: 11px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name"><?php echo e($company->name); ?></div>
        <div class="report-title">Creditors Report by Supplier - <?php echo e($accountForReport->name); ?></div>
        <div class="report-info">
            <?php if($startDate && $endDate): ?>
                Period: <?php echo e(\Carbon\Carbon::parse($startDate)->format('M d, Y')); ?> - <?php echo e(\Carbon\Carbon::parse($endDate)->format('M d, Y')); ?>

            <?php elseif($startDate): ?>
                From: <?php echo e(\Carbon\Carbon::parse($startDate)->format('M d, Y')); ?>

            <?php elseif($endDate): ?>
                Until: <?php echo e(\Carbon\Carbon::parse($endDate)->format('M d, Y')); ?>

            <?php else: ?>
                All Transactions
            <?php endif; ?>
            <br>
            Generated on: <?php echo e($generatedAt); ?>

        </div>
    </div>

    <!-- Overall Summary Section -->
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Suppliers</div>
                <div class="summary-value"><?php echo e(count($supplierGroups)); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Transactions</div>
                <div class="summary-value"><?php echo e(collect($supplierGroups)->sum('entries_count')); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Credits</div>
                <div class="summary-value">KD<?php echo e(number_format(collect($supplierGroups)->sum('total_credit'), 2)); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Outstanding Balance</div>
                <div class="summary-value outstanding">KD<?php echo e(number_format(collect($supplierGroups)->sum('balance'), 2)); ?></div>
            </div>
        </div>
    </div>

    <!-- Suppliers Summary Table -->
    <div class="suppliers-summary">
        <h3 style="margin-bottom: 10px; color: #1a365d;">Suppliers Summary</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 40%;">Supplier Name</th>
                    <th style="width: 15%;" class="text-center">Transactions</th>
                    <th style="width: 15%;" class="text-right">Total Credit</th>
                    <th style="width: 15%;" class="text-right">Total Debit</th>
                    <th style="width: 15%;" class="text-right">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $supplierGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><strong><?php echo e($group['supplier_name']); ?></strong></td>
                    <td class="text-center"><?php echo e($group['entries_count']); ?></td>
                    <td class="text-right">KD<?php echo e(number_format($group['total_credit'], 2)); ?></td>
                    <td class="text-right">KD<?php echo e(number_format($group['total_debit'], 2)); ?></td>
                    <td class="text-right">
                        <strong style="color: #dc2626;">KD<?php echo e(number_format($group['balance'], 2)); ?></strong>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>

    <!-- Detailed Supplier Sections -->
    <?php $__currentLoopData = $supplierGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="supplier-section">
        <div class="supplier-header">
            <?php echo e($group['supplier_name']); ?>

        </div>
        <div class="supplier-info">
            <strong>Transactions:</strong> <?php echo e($group['entries_count']); ?> | 
            <strong>Total Outstanding:</strong> <span style="color: #dc2626;">KD<?php echo e(number_format($group['balance'], 2)); ?></span>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Date</th>
                    <th style="width: 22%;">Description</th>
                    <th style="width: 16%;">Task Details</th>
                    <th style="width: 8%;" class="text-center">Task Status</th>
                    <th style="width: 11%;" class="text-right">Debit</th>
                    <th style="width: 11%;" class="text-right">Credit</th>
                    <th style="width: 12%;" class="text-right">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php $runningBalance = 0; ?>
                <?php $__currentLoopData = $group['entries']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $runningBalance += ($entry->credit - $entry->debit); ?>
                <tr>
                    <td><?php echo e(\Carbon\Carbon::parse($entry->transaction_date)->format('M d')); ?></td>
                    <td>
                        <?php echo e($entry->description); ?>

                        <?php if($entry->name): ?>
                            <br><small style="color: #666;"><?php echo e($entry->name); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($entry->task): ?>
                            <strong><?php echo e($entry->task->title ?? 'Task #' . $entry->task->id); ?></strong>
                            <?php if($entry->task->reference): ?>
                                <br><small><?php echo e($entry->task->reference); ?></small>
                            <?php endif; ?>
                            <?php if($entry->task->client_name): ?>
                                <br><small style="color: #2563eb;"><?php echo e($entry->task->client_name); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <small style="color: #999;">No task linked</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if($entry->task && $entry->task->status): ?>
                            <span class="status-badge status-<?php echo e($entry->task->status); ?>">
                                <?php echo e(ucfirst($entry->task->status)); ?>

                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        KD<?php echo e(number_format($entry->debit, 2)); ?>

                    </td>
                    <td class="text-right">
                        KD<?php echo e(number_format($entry->credit, 2)); ?>

                    </td>
                    <td class="text-right">
                        <strong style="color: <?php echo e($runningBalance > 0 ? '#dc2626' : '#059669'); ?>;">
                            KD<?php echo e(number_format($runningBalance, 2)); ?>

                        </strong>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
            <tfoot>
                <tr class="supplier-total-row">
                    <td colspan="5" class="text-right"><strong>Supplier Total:</strong></td>
                    <td class="text-right"><strong>KD<?php echo e(number_format($group['total_debit'], 2)); ?></strong></td>
                    <td class="text-right"><strong>KD<?php echo e(number_format($group['total_credit'], 2)); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php if(!$loop->last): ?>
        <div style="margin-bottom: 20px;"></div>
    <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated automatically by <?php echo e($company->name); ?> on <?php echo e($generatedAt); ?>.</p>
        <p>For questions about this report, please contact our accounting department.</p>
    </div>
</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/pdf/creditors-grouped.blade.php ENDPATH**/ ?>