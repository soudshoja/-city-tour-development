<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Client Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #3b82f6;
        }
        .header h1 {
            font-size: 22px;
            color: #1e40af;
            margin-bottom: 8px;
        }
        .header .date-range {
            font-size: 12px;
            color: #4b5563;
            font-weight: bold;
        }
        .header .generated {
            font-size: 9px;
            color: #9ca3af;
            margin-top: 5px;
        }

        .client-section {
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .client-header {
            background-color: #f8fafc;
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        .client-header table {
            width: 100%;
        }
        .client-header td {
            vertical-align: middle;
        }
        .client-avatar {
            width: 36px;
            height: 36px;
            background-color: #3b82f6;
            border-radius: 50%;
            color: white;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            line-height: 36px;
        }
        .client-name {
            font-size: 13px;
            font-weight: bold;
            color: #1f2937;
        }
        .client-contact {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }
        .client-stats-row {
            margin-top: 8px;
        }
        .client-stats-row td {
            padding: 4px 0;
        }
        .stat-box {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 8px;
            margin-right: 8px;
        }
        .stat-tasks { background-color: #dbeafe; color: #1e40af; }
        .stat-invoices { background-color: #e0e7ff; color: #3730a3; }
        .stat-owed { background-color: #fee2e2; color: #991b1b; }
        .stat-paid { background-color: #dbeafe; color: #1e40af; }
        .stat-due-to-client { background-color: #d1fae5; color: #065f46; }
        .stat-due-from-client { background-color: #fee2e2; color: #991b1b; }
        .stat-credit { background-color: #dbeafe; color: #1e40af; }

        .balance-owing { background-color: #fee2e2; color: #991b1b; }
        .balance-overpaid { background-color: #d1fae5; color: #065f46; }
        .balance-settled { background-color: #f3f4f6; color: #374151; }

        table.tasks {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        table.tasks th {
            background-color: #374151;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.tasks th.text-right { text-align: right; }
        table.tasks th.text-center { text-align: center; }
        table.tasks td {
            padding: 7px 6px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        table.tasks tr:nth-child(even) {
            background-color: #f9fafb;
        }
        table.tasks .text-right { text-align: right; }
        table.tasks .text-center { text-align: center; }
        table.tasks .reference {
            font-weight: bold;
            color: #1f2937;
        }
        table.tasks .passenger {
            font-size: 7px;
            color: #6b7280;
            margin-top: 2px;
        }

        .text-debit { color: #dc2626; }
        .text-credit { color: #059669; }
        .text-balance-positive { color: #dc2626; }
        .text-balance-negative { color: #059669; }
        .text-balance-zero { color: #6b7280; }
        .text-muted { color: #9ca3af; }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 7px;
            font-weight: bold;
        }
        .badge-type { background-color: #dbeafe; color: #1e40af; }
        .badge-issued { background-color: #d1fae5; color: #065f46; }
        .badge-reissued { background-color: #dbeafe; color: #1e40af; }
        .badge-confirmed { background-color: #ccfbf1; color: #0f766e; }
        .badge-void { background-color: #fee2e2; color: #991b1b; }
        .badge-refund { background-color: #ede9fe; color: #5b21b6; }

        .badge-paid { background-color: #d1fae5; color: #065f46; }
        .badge-partial { background-color: #fef3c7; color: #92400e; }
        .badge-unpaid { background-color: #fee2e2; color: #991b1b; }
        .badge-refunded { background-color: #ede9fe; color: #5b21b6; }
        .badge-completed { background-color: #ede9fe; color: #5b21b6; }
        .badge-not-invoiced { background-color: #f3f4f6; color: #6b7280; }

        .no-tasks {
            padding: 20px;
            text-align: center;
            color: #9ca3af;
            font-style: italic;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
        }
        .footer p {
            font-size: 8px;
            color: #9ca3af;
            margin-bottom: 3px;
        }

        .totals-row {
            background-color: #f3f4f6 !important;
            font-weight: bold;
        }
        .totals-row td {
            border-top: 2px solid #d1d5db;
            padding-top: 10px;
            padding-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Client Report</h1>
        <div class="date-range">
            <?php if($dateFrom && $dateTo): ?>
                <?php echo e(\Carbon\Carbon::parse($dateFrom)->format('d M Y')); ?> – <?php echo e(\Carbon\Carbon::parse($dateTo)->format('d M Y')); ?>

            <?php else: ?>
                All Time
            <?php endif; ?>
        </div>
        <div class="generated">Generated: <?php echo e($generatedAt); ?></div>
    </div>

    <?php $__currentLoopData = $allClients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $runningBalance = 0;
            $taskRows = [];
            $sortedTasks = $item['tasks']->sortBy('supplier_pay_date');

            foreach ($sortedTasks as $task) {
                $debit = 0;
                $credit = 0;

                if (strtolower($task->status) === 'refund' || $task->refundDetail) {
                    if ($task->refundDetail) {
                        $credit = $task->refundDetail->total_refund_to_client ?? $task->total ?? 0;
                    } else {
                        $credit = $task->total ?? 0;
                    }
                } else {
                    $invoicePaid = false;
                    if ($task->invoiceDetail && $task->invoiceDetail->invoice) {
                        $invoiceStatus = strtolower($task->invoiceDetail->invoice->status ?? '');
                        if (in_array($invoiceStatus, ['paid', 'paid by refund', 'refunded'])) {
                            $invoicePaid = true;
                        }
                    }

                    if (!$invoicePaid) {
                        $debit = $task->invoiceDetail->task_price ?? $task->total ?? 0;
                    }
                }
                
                $runningBalance = $runningBalance + $debit - $credit;
                
                $taskRows[] = [
                    'task' => $task,
                    'debit' => $debit,
                    'credit' => $credit,
                    'running_balance' => $runningBalance,
                ];
            }
            
            $totalDebit = collect($taskRows)->sum('debit');
            $totalCredit = collect($taskRows)->sum('credit');
            $finalBalance = $runningBalance;
        ?>

        <div class="client-section">
            <div class="client-header">
                <table>
                    <tr>
                        <td>
                            <div class="client-name"><?php echo e($item['client']->full_name ?: $item['client']->name); ?></div>
                            <div class="client-contact">
                                <?php if($item['client']->phone): ?>
                                    <?php echo e(($item['client']->country_code ?? '+965') . $item['client']->phone); ?>

                                <?php endif; ?>
                                <?php if($item['client']->email): ?>
                                    <?php if($item['client']->phone): ?> | <?php endif; ?>
                                    <?php echo e($item['client']->email); ?>

                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <span class="stat-box stat-owed">Owed: <strong><?php echo e(number_format($item['total_owed'], 3)); ?></strong></span>
                            <span class="stat-box stat-paid">Paid: <strong><?php echo e(number_format($item['total_paid'], 3)); ?></strong></span>
                            <span class="stat-box <?php echo e($finalBalance > 0 ? 'balance-owing' : ($finalBalance < 0 ? 'balance-overpaid' : 'balance-settled')); ?>">
                                Balance: <strong><?php echo e(number_format($finalBalance, 3)); ?></strong> KWD
                            </span>
                        </td>
                    </tr>
                </table>
                <table class="client-stats-row">
                    <tr>
                        <td>
                            <span class="stat-box stat-tasks">
                                <strong><?php echo e($item['total_tasks']); ?></strong> Tasks
                                (<?php echo e($item['invoiced_tasks_count']); ?> invoiced, <?php echo e($item['uninvoiced_tasks_count']); ?> uninvoiced, <?php echo e($item['refunded_tasks_count']); ?> refunded)
                            </span>
                            <span class="stat-box stat-invoices">
                                <strong><?php echo e($item['invoices_count']); ?></strong> Invoices
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <span class="stat-box stat-due-to-client">Due to Client: <strong><?php echo e(number_format($item['refund_credit'], 3)); ?></strong></span>
                            <span class="stat-box stat-due-from-client">Due from Client: <strong><?php echo e(number_format($item['refund_owed'], 3)); ?></strong></span>
                            <span class="stat-box stat-credit">Client Credit: <strong><?php echo e(number_format($item['client_credit'], 3)); ?></strong></span>
                        </td>
                    </tr>
                </table>
            </div>

            <?php if(count($taskRows) > 0): ?>
                <table class="tasks">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Reference</th>
                            <th style="width: 12%;">Supplier</th>
                            <th style="width: 8%;" class="text-center">Type</th>
                            <th style="width: 10%;" class="text-center">Date</th>
                            <th style="width: 8%;" class="text-center">Status</th>
                            <th style="width: 17%;" class="text-center">Billing</th>
                            <th style="width: 10%;" class="text-right">Debit</th>
                            <th style="width: 10%;" class="text-right">Credit</th>
                            <th style="width: 10%;" class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $taskRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $task = $row['task'];
                            $taskStatus = strtolower($task->status ?? '');
                            $statusBadges = [
                                'issued' => 'badge-issued',
                                'reissued' => 'badge-reissued',
                                'confirmed' => 'badge-confirmed',
                                'void' => 'badge-void',
                                'refund' => 'badge-refund',
                            ];
                            $statusBadge = $statusBadges[$taskStatus] ?? 'badge-type';
                        ?>
                        <tr>
                            <td>
                                <div class="reference"><?php echo e($task->reference); ?></div>
                                <?php if($task->passenger_name): ?>
                                    <div class="passenger"><?php echo e($task->passenger_name); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($task->supplier->name ?? '—'); ?></td>
                            <td class="text-center">
                                <span class="badge badge-type"><?php echo e(ucfirst($task->type ?? '—')); ?></span>
                            </td>
                            <td class="text-center">
                                <?php echo e($task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('d-m-Y') : '—'); ?>

                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo e($statusBadge); ?>"><?php echo e(ucfirst($task->status ?? '—')); ?></span>
                            </td>
                            <td class="text-center">
                                <?php if($task->refundDetail && $task->refundDetail->refund): ?>
                                    <?php 
                                        $refund = $task->refundDetail->refund;
                                        $refundStatus = strtolower($refund->status ?? '');
                                    ?>
                                    <span class="badge <?php echo e($refundStatus === 'completed' ? 'badge-completed' : 'badge-partial'); ?>">
                                        <?php echo e($refund->refund_number); ?> (<?php echo e(ucfirst($refund->status)); ?>)
                                    </span>
                                <?php elseif($taskStatus === 'refund'): ?>
                                    <span class="badge badge-not-invoiced">Not Refunded</span>
                                <?php elseif($task->invoiceDetail && $task->invoiceDetail->invoice): ?>
                                    <?php 
                                        $invoice = $task->invoiceDetail->invoice;
                                        $invoiceStatus = strtolower($invoice->status ?? '');
                                        if (in_array($invoiceStatus, ['paid', 'paid by refund', 'refunded'])) {
                                            $invBadge = 'badge-paid';
                                        } elseif (in_array($invoiceStatus, ['partial', 'partial refund'])) {
                                            $invBadge = 'badge-partial';
                                        } elseif ($invoiceStatus === 'unpaid') {
                                            $invBadge = 'badge-unpaid';
                                        } else {
                                            $invBadge = 'badge-not-invoiced';
                                        }
                                    ?>
                                    <span class="badge <?php echo e($invBadge); ?>">
                                        <?php echo e($invoice->invoice_number); ?> (<?php echo e(ucfirst($invoice->status)); ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-not-invoiced">Not Invoiced</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right <?php echo e($row['debit'] > 0 ? 'text-debit' : 'text-muted'); ?>">
                                <strong><?php echo e($row['debit'] > 0 ? number_format($row['debit'], 3) : '—'); ?></strong>
                            </td>
                            <td class="text-right <?php echo e($row['credit'] > 0 ? 'text-credit' : 'text-muted'); ?>">
                                <strong><?php echo e($row['credit'] > 0 ? number_format($row['credit'], 3) : '—'); ?></strong>
                            </td>
                            <td class="text-right <?php echo e($row['running_balance'] > 0 ? 'text-balance-positive' : ($row['running_balance'] < 0 ? 'text-balance-negative' : 'text-balance-zero')); ?>">
                                <strong><?php echo e(number_format($row['running_balance'], 3)); ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                        <tr class="totals-row">
                            <td colspan="6" class="text-right"><strong>TOTALS:</strong></td>
                            <td class="text-right text-debit"><strong><?php echo e(number_format($totalDebit, 3)); ?></strong></td>
                            <td class="text-right text-credit"><strong><?php echo e(number_format($totalCredit, 3)); ?></strong></td>
                            <td class="text-right <?php echo e($finalBalance > 0 ? 'text-balance-positive' : ($finalBalance < 0 ? 'text-balance-negative' : 'text-balance-zero')); ?>">
                                <strong><?php echo e(number_format($finalBalance, 3)); ?></strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-tasks">No tasks found for this client in the selected date range</div>
            <?php endif; ?>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <div class="footer">
        <p>City Tours Travel Agency • Confidential Report • <?php echo e($generatedAt); ?></p>
    </div>
</body>

</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/pdf/client.blade.php ENDPATH**/ ?>