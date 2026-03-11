<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header my-2 flex justify-between align-items-center">
                        <h4 class="font-bold text-lg mb-0">Proforma Invoice</h4>
                        <div class="flex justify-end gap-2">
                            <a href="<?php echo e(route('invoice.proforma.pdf', ['companyId' => optional($invoice->agent->branch->company)->id, 'invoiceNumber' => $invoice->invoice_number])); ?>"
                                class="btn btn-primary">
                                <i class="fas fa-download"></i> Download PDF
                            </a>
                            <a href="<?php echo e(url()->previous()); ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Invoice Content -->
                        <div class="invoice-container">
                            <!-- Header -->
                            <div class="row mb-4">
                                <div class="col-5">
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
                                    <h3 class="mt-2"><?php echo e($company->name); ?></h3>
                                    <p class="mb-0"><?php echo e($company->address); ?></p>
                                    <p class="mb-0"><?php echo e($company->phone); ?></p>
                                    <p class="mb-0"><?php echo e($company->email); ?></p>
                                </div>
                                <div class="col-5 text-end">
                                    <h2 class="text-primary">PROFORMA INVOICE</h2>
                                    <p class="mb-1"><strong>Invoice #:</strong> <?php echo e($invoice->invoice_number); ?></p>
                                    <p class="mb-1"><strong>Date:</strong> <?php echo e($invoice->created_at->format('d/m/Y')); ?></p>
                                    <p class="mb-1"><strong>Agent:</strong> <?php echo e($invoice->agent->name); ?></p>
                                </div>
                            </div>

                            <!-- Client Information -->
                            <div class="row mb-4">
                                <div class="col-5">
                                    <h5>Bill To:</h5>
                                    <strong><?php echo e($invoice->client->full_name); ?></strong><br>
                                    <?php echo e($invoice->client->address); ?><br>
                                    <span><?php echo e($invoice->client->country_code); ?></span><?php echo e($invoice->client->phone); ?><br>
                                </div>
                            </div>

                            <!-- Invoice Details Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Details</th>
                                            <th>Total</th>
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
                                                <p>
                                                    <?php echo e($detail->task->reference); ?>

                                                </p>
                                                <p>
                                                    <?php echo e($detail->task->additional_info ?? 'No additional information available'); ?>

                                                </p>
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
                                            <td>KWD<?php echo e(number_format($detail->task_price, 3)); ?></td>
                                        </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2" class="text-end">Grand Total:</th>
                                            <th>KWD<?php echo e(number_format($grandTotal, 2)); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6>Terms and Conditions:</h6>
                                    <small class="text-muted">
                                        This is a proforma invoice and serves as a quotation. Payment terms and conditions apply as per company policy.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<style>
    .invoice-container {
        background: white;
        padding: 20px;
        border-radius: 5px;
    }

    @media print {

        .card-header,
        .btn {
            display: none !important;
        }
    }
</style>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/proforma.blade.php ENDPATH**/ ?>