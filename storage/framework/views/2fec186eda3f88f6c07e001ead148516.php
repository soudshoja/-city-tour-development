<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tasks Report</title>
    <style>
        @page {
            margin: 15mm 10mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }

        .header h1 {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 5px;
            color: #333;
        }

        .header .subtitle {
            font-size: 10px;
            color: #666;
        }

        .stats {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            background: #e8f4f8;
            padding: 8px;
            border-radius: 4px;
        }

        .stat-row {
            display: table-row;
        }

        .stat-cell {
            display: table-cell;
            padding: 4px 10px;
            text-align: center;
            vertical-align: middle;
        }

        .stat-label {
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-top: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        thead {
            background: #333;
            color: white;
        }

        th {
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            border: 1px solid #222;
        }

        td {
            padding: 5px 4px;
            border: 1px solid #ddd;
            font-size: 8px;
        }

        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        /* Payment voucher row styling */
        tbody tr.pv-row {
            background: #f3e8ff !important;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-void, .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-payment_voucher {
            background: #e9d5ff;
            color: #6b21a8;
        }

        .status-other {
            background: #d1ecf1;
            color: #0c5460;
        }

        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            color: #666;
            text-align: center;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Tasks Report</h1>
        <div class="subtitle">Generated on <?php echo e($generatedAt); ?></div>
    </div>

    <div class="stats">
        <div class="stat-row">
            <div class="stat-cell">
                <div class="stat-label">Total Tasks</div>
                <div class="stat-value"><?php echo e(number_format($totalTasks)); ?></div>
            </div>
            <div class="stat-cell">
                <div class="stat-label">Total Debit</div>
                <div class="stat-value"><?php echo e(number_format($totalDebit, 3)); ?> KWD</div>
            </div>
            <div class="stat-cell">
                <div class="stat-label">Total Credit</div>
                <div class="stat-value"><?php echo e(number_format($totalCredit, 3)); ?> KWD</div>
            </div>
            <div class="stat-cell">
                <div class="stat-label">Balance</div>
                <div class="stat-value"><?php echo e(number_format($netBalance, 3)); ?> KWD</div>
            </div>
        </div>
    </div>

    <?php if($tasks->count() > 0): ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Reference</th>
                    <th style="width: 10%;">Original Ref</th>
                    <th style="width: 18%;">Client/Name</th>
                    <th style="width: 12%;">Supplier</th>
                    <th style="width: 10%;">Agent</th>
                    <th style="width: 9%;">Pay Date</th>
                    <th style="width: 8%;">Issued By</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 7%;">Debit</th>
                    <th style="width: 8%;">Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="<?php echo e($task->type === 'transaction' ? 'pv-row' : ''); ?>">
                        <td><?php echo e($task->reference ?? 'N/A'); ?></td>
                        <td><?php echo e($task->original_reference ?? 'N/A'); ?></td>
                        <td><?php echo e($task->passenger_name ?? 'N/A'); ?></td>
                        <td><?php echo e($task->supplier_name ?? 'N/A'); ?></td>
                        <td><?php echo e($task->agent_name ?? 'N/A'); ?></td>
                        <td class="text-center"><?php echo e($task->date ? \Carbon\Carbon::parse($task->date)->format('Y-m-d') : 'N/A'); ?></td>
                        <td><?php echo e($task->issued_by ?? 'N/A'); ?></td>
                        <td class="text-center">
                            <span class="status-badge 
                                <?php if($task->status === 'completed'): ?> status-completed
                                <?php elseif($task->status === 'pending'): ?> status-pending
                                <?php elseif(in_array($task->status, ['cancelled', 'void'])): ?> status-void
                                <?php elseif($task->status === 'payment_voucher'): ?> status-payment_voucher
                                <?php else: ?> status-other
                                <?php endif; ?>">
                                <?php echo e($task->status === 'payment_voucher' ? 'Payment' : ucfirst($task->status ?? 'N/A')); ?>

                            </span>
                        </td>
                        <td class="text-right">
                            <?php if($task->debit > 0): ?>
                                <?php echo e(number_format($task->debit, 3)); ?>

                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if($task->credit > 0): ?>
                                <?php echo e(number_format($task->credit, 3)); ?>

                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No records found matching the selected filters.</div>
    <?php endif; ?>

    <div class="footer">
        <div>Total Tasks: <?php echo e(number_format($totalTasks)); ?> | Debit: <?php echo e(number_format($totalDebit, 3)); ?> KWD | Credit: <?php echo e(number_format($totalCredit, 3)); ?> KWD | Net: <?php echo e(number_format($netBalance, 3)); ?> KWD</div>
        <div style="margin-top: 3px;">This report was automatically generated on <?php echo e($generatedAt); ?></div>
    </div>
</body>
</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/pdf/tasks.blade.php ENDPATH**/ ?>