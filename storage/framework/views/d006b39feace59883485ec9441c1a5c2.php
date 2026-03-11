<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proforma Invoice - <?php echo e($invoice->invoice_number); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-info {
            flex: 1;
        }

        .company-info img {
            max-height: 80px;
            margin-bottom: 10px;
        }

        .company-info h3 {
            margin: 10px 0 5px 0;
            font-size: 18px;
        }

        .company-info p {
            margin: 2px 0;
            font-size: 11px;
        }

        .invoice-info {
            text-align: right;
            flex: 1;
        }

        .invoice-info h2 {
            color: #0066cc;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .invoice-info p {
            margin: 3px 0;
            font-size: 11px;
        }

        .client-section {
            margin-bottom: 30px;
        }

        .client-section h5 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #333;
        }

        .client-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .client-info strong {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .table th,
        .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }

        .table th {
            background-color: #333;
            color: white;
            font-weight: bold;
            text-align: center;
        }

        .table td:last-child,
        .table th:last-child {
            text-align: right;
        }

        .table tfoot th {
            background-color: #f8f9fa;
            color: #333;
            border-top: 2px solid #333;
        }

        .terms {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .terms h6 {
            font-size: 12px;
            margin-bottom: 10px;
        }

        .terms small {
            font-size: 10px;
            color: #666;
            line-height: 1.5;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <?php if (isset($component)) { $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72 = $attributes; } ?>
<?php $component = App\View\Components\ApplicationLogo::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('application-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ApplicationLogo::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'custom-logo-size']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $attributes = $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $component = $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
                <h3><?php echo e($company->name); ?></h3>
                <p><?php echo e($company->address); ?></p>
                <p><?php echo e($company->phone); ?></p>
                <p><?php echo e($company->email); ?></p>
            </div>
            <div class="invoice-info">
                <h2>PROFORMA INVOICE</h2>
                <p><strong>Invoice #:</strong> <?php echo e($invoice->invoice_number); ?></p>
                <p><strong>Date:</strong> <?php echo e($invoice->created_at->format('d/m/Y')); ?></p>
                <p><strong>Agent:</strong> <?php echo e($invoice->agent->name); ?></p>
            </div>
        </div>

        <!-- Client Information -->
        <div class="client-section">
            <h5>Bill To:</h5>
            <div class="client-info">
                <strong><?php echo e($invoice->client->full_name); ?></strong>
                <?php echo e($invoice->client->address); ?><br>
                <span><?php echo e($invoice->client->country_code); ?></span><?php echo e($invoice->client->phone); ?>

            </div>
        </div>

        <!-- Invoice Details Table -->
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Details</th>
                    <th style="width: 15%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $grandTotal = 0; ?>
                <?php $__currentLoopData = $invoiceDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $grandTotal += $detail->task_price; ?>
                <tr>
                    <td><?php echo e($index + 1); ?></td>
                    <?php if($detail->task->flightDetails): ?>
                    <td>
                        <strong>Flight:</strong> <?php echo e($detail->task->flightDetails->ticket_number); ?><br>
                        <p><?php echo e($detail->task->reference); ?></p>
                        <p><?php echo e($detail->task->additional_info ?? 'No additional information available'); ?></p>
                    </td>
                    <?php elseif($detail->task->hotelDetails): ?>
                    <td>
                        <strong>Hotel:</strong> <?php echo e($detail->task->hotelDetails->hotel->name); ?><br>
                        <strong>Check-in:</strong> <?php echo e($detail->task->hotelDetails->readable_check_in); ?><br>
                        <strong>Check-out:</strong> <?php echo e($detail->task->hotelDetails->readable_check_out); ?><br>
                        <strong>Room Name:</strong> <?php echo e($detail->task->hotelDetails->room_name); ?><br>
                        <strong>Nights:</strong> <?php echo e($detail->task->hotelDetails->nights); ?>

                    </td>
                    <?php else: ?>
                    <td>
                        <p>-</p>
                    </td>
                    <?php endif; ?>
                    <td class="text-right">KWD<?php echo e(number_format($detail->task_price, 3)); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Grand Total:</th>
                    <th class="text-right">KWD<?php echo e(number_format($grandTotal, 2)); ?></th>
                </tr>
            </tfoot>
        </table>

        <!-- Terms and Conditions -->
        <div class="terms">
            <h6>Terms and Conditions:</h6>
            <small>
                This is a proforma invoice and serves as a quotation. Payment terms and conditions apply as per company policy.
            </small>
        </div>
    </div>
</body>

</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/proforma-pdf.blade.php ENDPATH**/ ?>