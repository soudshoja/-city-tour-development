<?php

use Barryvdh\DomPDF\Facade\Pdf;
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Supplier Tasks PDF</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 6px;
            text-align: left;
        }

        th {
            background: #eee;
        }
    </style>
</head>

<body>
    <h2>Supplier: <?php echo e($supplier->name); ?></h2>
    <p>Email: <?php echo e($supplier->email); ?> | Phone: <?php echo e($supplier->phone); ?></p>
    <p>Country: <?php echo e($supplier->country->name ?? '-'); ?></p>
    <hr>
    <h3>Filtered Tasks</h3>
    <?php
    $firstTask = $filteredTasks->first();
    $supplierType = $firstTask ? $firstTask->type : null;
    ?>

    <?php if($supplierType === 'flight'): ?>
    <table>
        <thead>
            <tr>
                <th>Task Ref</th>
                <th>GDS Ref</th>
                <th>Agent</th>
                <th>Issued Date</th>
                <th>Passenger Name</th>
                <th>Net Price</th>
                <th>Departure</th>
                <th>Arrival</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $filteredTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <td><?php echo e($task->reference); ?></td>
                <td><?php echo e($task->gds_reference ?? '-'); ?></td>
                <td><?php echo e($task->agent ? $task->agent->name : '-'); ?></td>
                <td><?php echo e($task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-'); ?></td>
                <td><?php echo e($task->passenger_name ?? '-'); ?></td>
                <td><?php echo e($task->price ?? '-'); ?></td>
                <td>
                    <?php if($task->flightDetails): ?>
                    <?php echo e($task->flightDetails->airport_from ?? '-'); ?><br>
                    <?php echo e($task->flightDetails->departure_time ?? '-'); ?>

                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($task->flightDetails): ?>
                    <?php echo e($task->flightDetails->airport_to ?? '-'); ?><br>
                    <?php echo e($task->flightDetails->arrival_time ?? '-'); ?>

                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
                <td colspan="10">No entries found for selected dates.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php elseif($supplierType === 'hotel'): ?>
    <table>
        <thead>
            <tr>
                <th>Task Ref</th>
                <th>Agent</th>
                <th>Issued Date</th>
                <th>Status</th>
                <th>Info</th>
                <th>Price Debit Credit Balance</th>
                <th>Debit</th>
                <th>Credit</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $filteredTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <td><?php echo e($task->reference); ?></td>
                <td><?php echo e($task->agent ? $task->agent->name : '-'); ?></td>
                <td><?php echo e($task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-'); ?></td>
                <td><?php echo e($task->status); ?></td>
                <td><?php echo e($task->passenger_name ?? '-'); ?>

                    <?php if($task->hotelDetails): ?>
                    <?php echo e($task->hotelDetails->hotel->name ?? '-'); ?><br>
                    <?php echo e($task->hotelDetails->check_in ?? '-'); ?> to <?php echo e($task->hotelDetails->check_out ?? '-'); ?>

                </td>
                <td><?php echo e($task->price ?? '-'); ?></td>
                <td>
                    
                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($task->hotelDetails): ?>
                    <?php echo e($task->hotelDetails->check_in ?? '-'); ?>

                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($task->hotelDetails): ?>
                    <?php echo e($task->hotelDetails->check_out ?? '-'); ?>

                    <?php else: ?>
                    -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
                <td colspan="9">No entries found for selected dates.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Task Ref</th>
                <th>Agent</th>
                <th>Issued Date</th>
                <th>Passenger Name</th>
                <th>Net Price</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $filteredTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <td><?php echo e($task->reference); ?></td>
                <td><?php echo e($task->agent ? $task->agent->name : '-'); ?></td>
                <td><?php echo e($task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-'); ?></td>
                <td><?php echo e($task->passenger_name ?? '-'); ?></td>
                <td><?php echo e($task->price ?? '-'); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
                <td colspan="12">No entries found for selected dates.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</body>

</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/suppliers/pdf.blade.php ENDPATH**/ ?>