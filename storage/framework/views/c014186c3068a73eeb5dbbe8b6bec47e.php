<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Journal Entries Ledger PDF</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 6px; text-align: center; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Ledger</h2>
    <p><strong>Report Period:</strong> <?php echo e($dateFrom); ?> to <?php echo e($dateTo); ?></p>
    <table>
        <thead>
            <tr>
                <th>Transaction ID</th>
                <th>Transaction Date</th>
                <?php
                    $showIssueColumn = $journalEntries->contains(function ($entry) {
                        return $entry->type === 'payable' && !is_null($entry->task);
                    });
                ?>
                <?php if($showIssueColumn): ?>
                    <th>Task Date</th>
                <?php endif; ?>
                <th>Reference</th>
                <th>Client Name</th>
                <th>Description</th>
                <th>Account</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Running Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $journalEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($entry->transaction_id); ?></td>
                    <td><?php echo e(\Carbon\Carbon::parse($entry->transaction_date)->format('Y-m-d')); ?></td>
                    <?php if($showIssueColumn): ?>
                        <td><?php echo e($entry->task ? $entry->task->issued_date?->format('Y-m-d') ?? '-' : '-'); ?></td>
                    <?php endif; ?>
                    <td><?php echo e($entry->task ? $entry->task->reference ?? '-' : '-'); ?></td>
                    <td><?php echo e($entry->task ? $entry->task->client_name ?? '-' : '-'); ?></td>
                    <td>
                        <?php if($entry->task && $entry->task->type === 'flight'): ?>
                            Departure: <?php echo e($entry->task?->flightDetails?->departure_time ? \Carbon\Carbon::parse($entry->task->flightDetails->departure_time)->format('H:i') : '-'); ?>,
                            From: <?php echo e($entry->task->flightDetails->airport_from ?? '-'); ?>,
                            Arrival: <?php echo e($entry->task?->flightDetails?->arrival_time ? \Carbon\Carbon::parse($entry->task->flightDetails->arrival_time)->format('H:i') : '-'); ?>,
                            To: <?php echo e($entry->task->flightDetails->airport_to ?? '-'); ?>

                        <?php elseif($entry->task && $entry->task->type === 'hotel'): ?>
                            Hotel: <?php echo e($entry->task->hotelDetails->hotel->name ?? '-'); ?>,
                            Check-in: <?php echo e($entry->task->hotelDetails->check_in ?? '-'); ?>,
                            Check-out: <?php echo e($entry->task->hotelDetails->check_out ?? '-'); ?>

                        <?php else: ?>
                            <?php echo e($entry->task->additional_info ?? '-'); ?>

                        <?php endif; ?>
                    </td>
                    <td><?php echo e($entry->account->name); ?></td>
                    <td><?php echo e(number_format($entry->debit, 2)); ?></td>
                    <td><?php echo e(number_format($entry->credit, 2)); ?></td>
                    <td><?php echo e(number_format($entry->running_balance, 2)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</body>
</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/journal_entries/pdf.blade.php ENDPATH**/ ?>