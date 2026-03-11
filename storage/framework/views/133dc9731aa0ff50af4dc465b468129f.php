<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Creditors Report - <?php echo e($accountForReport->name); ?></title>
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
            width: 33.33%;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            font-size: 10px;
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
            font-size: 9px;
            font-weight: bold;
        }
        .status-issued { background-color: #d1fae5; color: #065f46; }
        .status-confirmed { background-color: #dbeafe; color: #1e40af; }
        .status-reissued { background-color: #fef3c7; color: #92400e; }
        .status-refund { background-color: #fed7aa; color: #9a3412; }
        .status-void { background-color: #fecaca; color: #991b1b; }
        .status-emd { background-color: #e9d5ff; color: #7c2d12; }
        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
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
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name"><?php echo e($company->name); ?></div>
        <div class="report-title">Creditors Report - <?php echo e($accountForReport->name); ?></div>
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

    <!-- Summary Section -->
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Transactions</div>
                <div class="summary-value"><?php echo e(count($journalEntries)); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Credits</div>
                <div class="summary-value">KD<?php echo e(number_format($journalEntries->sum('credit'), 2)); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Outstanding Balance</div>
                <div class="summary-value outstanding">KD<?php echo e(number_format($accountForReport->final_balance, 2)); ?></div>
            </div>
        </div>
    </div>

    <!-- Journal Entries Table -->
    <?php if(count($journalEntries) > 0): ?>
    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 25%;">Description</th>
                <th style="width: 20%;">Task Details</th>
                <th style="width: 7%;" class="text-center">Task Status</th>
                <th style="width: 12%;" class="text-right">Debit</th>
                <th style="width: 12%;" class="text-right">Credit</th>
                <th style="width: 12%;" class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $journalEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e(\Carbon\Carbon::parse($entry->transaction_date)->format('M d, Y')); ?></td>
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
                            <br><small>Ref: <?php echo e($entry->task->reference); ?></small>
                        <?php endif; ?>
                        <?php if($entry->task->client_name): ?>
                            <br><small style="color: #2563eb;">Client: <?php echo e($entry->task->client_name); ?></small>
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
                    <?php if($entry->debit > 0): ?>
                        KD<?php echo e(number_format($entry->debit, 2)); ?>

                    <?php else: ?>
                        KD0.00
                    <?php endif; ?>
                </td>
                <td class="text-right">
                    <?php if($entry->credit > 0): ?>
                        <strong style="color: #dc2626;">KD<?php echo e(number_format($entry->credit, 2)); ?></strong>
                    <?php else: ?>
                        KD0.00
                    <?php endif; ?>
                </td>
                <td class="text-right">
                    <strong style="color: <?php echo e($entry->balance > 0 ? '#dc2626' : '#059669'); ?>;">
                        KD<?php echo e(number_format($entry->balance, 2)); ?>

                    </strong>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" class="text-right"><strong>Final Outstanding Balance:</strong></td>
                <td class="text-right"><strong>KD<?php echo e(number_format($journalEntries->sum('credit'), 2)); ?></strong></td>
                <td class="text-right">
                    <strong style="color: <?php echo e($accountForReport->final_balance > 0 ? '#dc2626' : '#059669'); ?>;">
                        KD<?php echo e(number_format($accountForReport->final_balance, 2)); ?>

                    </strong>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 50px; color: #666;">
        <p>No journal entries found for the selected criteria.</p>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated automatically by <?php echo e($company->name); ?> on <?php echo e($generatedAt); ?>.</p>
        <p>For questions about this report, please contact our accounting department.</p>
    </div>
</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/pdf/creditors-all.blade.php ENDPATH**/ ?>