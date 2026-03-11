<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Creditor Report - <?php echo e($selectedSupplier['supplier_name']); ?></title>
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
        .supplier-name {
            font-size: 16px;
            color: #4c51bf;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-info {
            font-size: 12px;
            color: #666;
        }
        .summary-section {
            margin-bottom: 25px;
            background-color: #f8f9fa;
            border: 2px solid #667eea;
            padding: 20px;
            border-radius: 8px;
        }
        .summary-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-grid td {
            width: 25%;
            text-align: center;
            vertical-align: top;
            padding: 10px;
            border: none;
        }
        .summary-label {
            font-size: 11px;
            margin-bottom: 5px;
            color: #666;
            font-weight: normal;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #1a365d;
        }
        .outstanding {
            color: #dc2626;
        }
        .creditor-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #4c51bf;
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
        .payment-notice {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
        .payment-notice h4 {
            color: #dc2626;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .highlight-amount {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name"><?php echo e($company->name); ?></div>
        <div class="report-title">Creditor Statement</div>
        <div class="supplier-name"><?php echo e($selectedSupplier['supplier_name']); ?></div>
        <div class="report-info">
            Account: <?php echo e($accountForReport->name); ?>

            <br>
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
        <h4 style="margin-top: 0; margin-bottom: 15px; color: #1a365d; text-align: center;">Summary Overview</h4>
        <table class="summary-grid">
            <tr>
                <td>
                    <div class="summary-label">Total Transactions</div>
                    <div class="summary-value"><?php echo e($selectedSupplier['entries_count']); ?></div>
                </td>
                <td>
                    <div class="summary-label">Total Credits</div>
                    <div class="summary-value">KD<?php echo e(number_format($selectedSupplier['total_credit'], 2)); ?></div>
                </td>
                <td>
                    <div class="summary-label">Total Debits</div>
                    <div class="summary-value">KD<?php echo e(number_format($selectedSupplier['total_debit'], 2)); ?></div>
                </td>
                <td>
                    <div class="summary-label">Outstanding Balance</div>
                    <div class="summary-value outstanding">KD<?php echo e(number_format($selectedSupplier['balance'], 2)); ?></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Creditor Information -->
    <div class="creditor-info">
        <h4 style="margin-top: 0; color: #1a365d;">Account Information</h4>
        <p><strong>Creditor Account:</strong> <?php echo e($accountForReport->name); ?></p>
        <p><strong>Supplier:</strong> <?php echo e($selectedSupplier['supplier_name']); ?></p>
        <p><strong>Current Outstanding Amount:</strong> 
            <span class="highlight-amount">KD<?php echo e(number_format($selectedSupplier['balance'], 2)); ?></span>
        </p>
    </div>

    <!-- Transaction Details Table -->
    <?php if(count($selectedSupplier['entries']) > 0): ?>
    <h3 style="color: #1a365d; margin-bottom: 15px;">Transaction Details</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Date</th>
                <th style="width: 25%;">Description</th>
                <th style="width: 18%;">Task Details</th>
                <th style="width: 7%;" class="text-center">Task Status</th>
                <th style="width: 12%;" class="text-right">Debit</th>
                <th style="width: 12%;" class="text-right">Credit</th>
                <th style="width: 14%;" class="text-right">Running Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php $runningBalance = 0; ?>
            <?php $__currentLoopData = $selectedSupplier['entries']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $runningBalance += ($entry->credit - $entry->debit); ?>
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
                        <?php if($entry->task->amount): ?>
                            <br><small style="color: #059669;">Task Amount: KD<?php echo e(number_format($entry->task->amount, 2)); ?></small>
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
            <tr class="total-row">
                <td colspan="4" class="text-right"><strong>Totals:</strong></td>
                <td class="text-right"><strong>KD<?php echo e(number_format($selectedSupplier['total_debit'], 2)); ?></strong></td>
                <td class="text-right"><strong>KD<?php echo e(number_format($selectedSupplier['total_credit'], 2)); ?></strong></td>
                <td class="text-right">
                    <strong style="color: <?php echo e($selectedSupplier['balance'] > 0 ? '#dc2626' : '#059669'); ?>;">
                        KD<?php echo e(number_format($selectedSupplier['balance'], 2)); ?>

                    </strong>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 50px; color: #666;">
        <p>No transactions found for <?php echo e($selectedSupplier['supplier_name']); ?> in the selected period.</p>
    </div>
    <?php endif; ?>

    <!-- Payment Notice -->
    <?php if($selectedSupplier['balance'] > 0): ?>
    <div class="payment-notice">
        <h4>Payment Notice</h4>
        <p>
            <strong>Amount Due:</strong> <span class="highlight-amount">KD<?php echo e(number_format($selectedSupplier['balance'], 2)); ?></span>
        </p>
        <p>This amount represents the outstanding balance owed to <strong><?php echo e($selectedSupplier['supplier_name']); ?></strong> through our creditor account <strong><?php echo e($accountForReport->name); ?></strong>.</p>
        <p>Please remit payment according to your agreed terms and conditions.</p>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <p>This statement was generated automatically by <?php echo e($company->name); ?> on <?php echo e($generatedAt); ?>.</p>
        <p>For questions about this statement or payment arrangements, please contact our accounting department.</p>
        <p style="margin-top: 10px; font-style: italic;">
            This is a computer-generated document and does not require a signature.
        </p>
    </div>
</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/pdf/creditors-single-supplier.blade.php ENDPATH**/ ?>