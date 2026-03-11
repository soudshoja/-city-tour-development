<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e(config('app.name', 'Laravel')); ?></title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="<?php echo e(asset('images/City0logo.svg')); ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css']); ?>
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        h1, h2, h3 {
            color: #1a1a1a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 0.75rem;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            text-transform: uppercase;
            font-size: 0.85rem;
            color: #4b5563;
        }
        .totals td {
            font-weight: bold;
        }
        .highlight {
            background: #fff9c4;
        }
    </style>
</head>

<body class="overflow-y-auto font-nunito antialiased bg-gray-100">
    <?php if($invoice->status === 'paid'): ?>
    <div
        class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg">
        <div class="flex items-center justify-between text-white">
            <p class="text-3xl">PAID</p>
            <h5 class="text-2xl ltr:mr-auto rtl:mr-auto"></h5>
        </div>
    </div>
    <?php endif; ?>

    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">REFUND INVOICE</h1>
                <p class="text-sm text-gray-600"><?php echo e($invoice->invoice_number); ?></p>
                <p class="text-sm text-gray-600">Date: <?php echo e(\Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y')); ?></p>
                <?php
                    $refund = \App\Models\Refund::where('refund_invoice_id', $invoice->id)->first();
                ?>
                <!-- <p class="text-gray-600">Generated from Refund: <?php echo e($refund->refund_number); ?></p> -->
            </div>
            <div>
                <img class="w-auto h-[85px] object-contain" src="<?php echo e($invoice->agent->branch->company->logo ? Storage::url($invoice->agent->branch->company->logo) : asset('images/UserPic.svg')); ?>" alt="Company logo" />
            </div>
        </div>

        <div class="flex justify-between items-center mb-8">
            <div>
                <h3 class="text-lg font-bold text-gray-800">Billed To</h3>
                <p class="text-sm text-gray-600"><?php echo e($invoice->client->full_name); ?></p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:<?php echo e($invoice->client->email); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoice->client->email); ?>

                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:<?php echo e(($invoice->client->country_code ?? '+965')); ?> <?php echo e($invoice->client->phone ?? 'N/A'); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e(($invoice->client->country_code ?? '+965')); ?> <?php echo e($invoice->client->phone ?? 'N/A'); ?>

                    </a>
                </p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold text-gray-800"><?php echo e($invoice->agent->branch->company->name); ?></h2>
                <p class="text-sm text-gray-600"><?php echo e($invoice->agent->branch->company->address); ?></p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:<?php echo e($invoice->agent->branch->company->email); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoice->agent->branch->company->email); ?>

                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:<?php echo e($invoice->agent->branch->company->phone); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoice->agent->branch->company->phone); ?>

                    </a>
                </p>
            </div>
        </div>

        <?php
            // Make sure relationships are loaded
            $refund->loadMissing('refundDetails.task.originalTask', 'originalInvoice.invoiceDetails');

            // Collect all original task IDs for the refunded tasks
            $refundedTaskIds = $refund->refundDetails
                ->map(fn($detail) => $detail->task?->originalTask?->id)
                ->filter()
                ->unique()
                ->toArray();

            // Calculate the total task price for those original tasks
            $refundedTaskTotal = $refund->originalInvoice
                ? $refund->originalInvoice->invoiceDetails
                    ->whereIn('task_id', $refundedTaskIds)
                    ->sum('task_price')
                : 0;
        ?>
        <div class="mb-6">
            <h2 class="text-xl font-semibold">Refund Summary</h2>
            <table class="min-w-full border border-gray-200 text-sm">
                <thead class="bg-gray-100 text-gray-700 uppercase">
                    <tr>
                        <th class="p-2 border">Original Invoice</th>
                        <th class="p-2 border">Original Amount</th>
                        <th class="p-2 border">Original Refund</th>
                        <th class="p-2 border">Refund Charges</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="p-2 border"><?php echo e($refund->originalInvoice?->invoice_number ?? 'N/A'); ?></td>
                        <td class="p-2 border"><?php echo e(number_format($refund->originalInvoice?->amount ?? 0, 3)); ?></td>
                        <td class="p-2 border"><?php echo e(number_format($refundedTaskTotal, 3)); ?></td>
                        <td class="p-2 border font-bold text-green-700"><?php echo e(number_format($refund->total_nett_refund ?? 0, 3)); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Task Refund Details</h2>
            <?php $__currentLoopData = $refund->refundDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $task = $detail->task;
                    $originalDetail = $detail->refund->originalInvoice ? $detail->refund->originalInvoice->invoiceDetails->firstWhere('task_id', $task->originalTask?->id ?? $task->id) : null;
                ?>
                <div class="mb-3 p-5 border border-gray-200 rounded-lg shadow-sm bg-gray-50 hover:bg-gray-100 transition">
                    <div class="flex justify-between items-center">
                        <h3 class="font-semibold text-base text-gray-800">Reference: <?php echo e($task->reference ?? 'N/A'); ?></h3>
                        <span class="text-sm px-3 py-1 rounded-full bg-blue-100 text-blue-700"><?php echo e(ucfirst($task->type ?? 'N/A')); ?></span>
                    </div>
                    <div class="grid md:grid-cols-2 gap-3 text-sm text-gray-700 leading-relaxed">
                        <?php if($task->type === 'hotel'): ?>
                            <?php
                                $roomDetails = json_decode($task->hotelDetails->room_details ?? '{}', true);
                                $passengerCount = count($roomDetails['passengers'] ?? []);
                            ?>
                            <div>
                                <p><strong>Client:</strong> <?php echo e($task->client_name ?? ($invoice->client->full_name ?? 'N/A')); ?></p>
                                <p><strong>Passenger:</strong> <?php echo e($task->passenger_name ?? 'N/A'); ?></p>
                                <p><strong>Hotel Name:</strong> <?php echo e($task->hotelDetails->hotel->name ?? 'N/A'); ?></p>
                                <p><strong>Room Category:</strong> <?php echo e($task->hotelDetails->room_type ?? $task->hotelDetails->room_category ?? 'N/A'); ?></p>
                                <p><strong>Number of Pax:</strong> <?php echo e($passengerCount ?? $task->number_of_pax ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p><strong>Check In:</strong> <?php echo e($task->hotelDetails->check_in ?? 'N/A'); ?></p>
                                <p><strong>Check Out:</strong> <?php echo e($task->hotelDetails->check_out ?? 'N/A'); ?></p>
                                <p><strong>Original Task Price:</strong> <?php echo e(number_format($originalDetail->task_price ?? 0, 3)); ?></p>
                                <p><strong>Refund Charge:</strong> <?php echo e(number_format($detail->total_refund_to_client ?? 0, 3)); ?></p>
                            </div>

                        <?php elseif($task->type === 'flight'): ?>
                            <div>
                                <p><strong>Client:</strong> <?php echo e($task->client_name ?? ($invoice->client->full_name ?? 'N/A')); ?></p>
                                <p><strong>Passenger:</strong> <?php echo e($task->passenger_name ?? 'N/A'); ?></p>
                                <p><strong>GDS Ref:</strong> <?php echo e($task->gds_reference ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p><strong>Route:</strong>
                                    <?php echo e($task->flightDetails->countryFrom->name ?? ''); ?>

                                    (<?php echo e($task->flightDetails->airport_from ?? ''); ?>)
                                    →
                                    <?php echo e($task->flightDetails->countryTo->name ?? ''); ?>

                                    (<?php echo e($task->flightDetails->airport_to ?? ''); ?>)
                                </p>
                                <p><strong>Original Task Price:</strong> <?php echo e(number_format($originalDetail->task_price ?? 0, 3)); ?></p>
                                <p><strong>Refund Charge:</strong> <?php echo e(number_format($detail->total_refund_to_client ?? 0, 3)); ?></p>
                            </div>

                        <?php elseif($task->type === 'visa'): ?>
                            <div>
                                <p><strong>Client:</strong> <?php echo e($task->client_name ?? ($invoice->client->full_name ?? 'N/A')); ?></p>
                                <p><strong>Passenger:</strong> <?php echo e($task->passenger_name ?? 'N/A'); ?></p>
                                <p><strong>Visa Type:</strong> <?php echo e($task->visaDetails->visa_type ?? 'N/A'); ?></p>
                                <p><strong>Application #:</strong> <?php echo e($task->visaDetails->application_number ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p><strong>Entries:</strong> <?php echo e($task->visaDetails->number_of_entries ?? 'N/A'); ?></p>
                                <p><strong>Issuing Country:</strong> <?php echo e($task->visaDetails->issuing_country ?? 'N/A'); ?></p>
                                <p><strong>Stay Duration:</strong> <?php echo e($task->visaDetails->stay_duration ?? 'N/A'); ?></p>
                                <p><strong>Original Task Price:</strong> <?php echo e(number_format($originalDetail->task_price ?? 0, 3)); ?></p>
                                <p><strong>Refund Charge:</strong> <?php echo e(number_format($detail->total_refund_to_client ?? 0, 3)); ?></p>
                            </div>

                        <?php elseif($task->type === 'insurance'): ?>
                            <div>
                                <p><strong>Client:</strong> <?php echo e($task->client_name ?? ($invoice->client->full_name ?? 'N/A')); ?></p>
                                <p><strong>Passenger:</strong> <?php echo e($task->passenger_name ?? 'N/A'); ?></p>
                                <p><strong>Insurance Type:</strong> <?php echo e($task->insuranceDetails->insurance_type ?? 'N/A'); ?></p>
                                <p><strong>Destination:</strong> <?php echo e($task->insuranceDetails->destination ?? 'N/A'); ?></p>
                                <p><strong>Plan Type:</strong> <?php echo e($task->insuranceDetails->plan_type ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p><strong>Duration:</strong> <?php echo e($task->insuranceDetails->duration ?? 'N/A'); ?></p>
                                <p><strong>Package:</strong> <?php echo e($task->insuranceDetails->package ?? 'N/A'); ?></p>
                                <p><strong>Document Ref:</strong> <?php echo e($task->insuranceDetails->document_reference ?? 'N/A'); ?></p>
                                <p><strong>Original Task Price:</strong> <?php echo e(number_format($originalDetail->task_price ?? 0, 3)); ?></p>
                                <p><strong>Refund Charge:</strong> <?php echo e(number_format($detail->total_refund_to_client ?? 0, 3)); ?></p>
                            </div>

                        <?php else: ?>
                            <div class="col-span-2">
                                <p><strong>Client:</strong> <?php echo e($task->client_name ?? $invoice->client->full_name); ?></p>
                                <p><strong>Passenger:</strong> <?php echo e($task->passenger_name ?? 'N/A'); ?></p>
                                <p><strong>Original Task Price:</strong> <?php echo e(number_format($originalDetail->task_price ?? 0, 3)); ?></p>
                                <p><strong>Refund Charge:</strong> <?php echo e(number_format($detail->total_refund_to_client ?? 0, 3)); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <?php
            // find all refunded tasks
            $refundedTaskIds = $refund->refundDetails
                ->map(fn($d) => $d->task?->originalTask?->id ?? $d->task_id)
                ->filter()
                ->toArray();

            // find unrefunded ones
            $unrefundedTasks = $refund->originalInvoice ? $refund->originalInvoice->invoiceDetails()->whereNotIn('task_id', $refundedTaskIds)->get() : collect();
        ?>
        <?php if($unrefundedTasks->isNotEmpty()): ?>
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Unrefunded Items from Original Invoice</h2>
                <table class="min-w-full mb-8 border border-gray-200">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                            <th class="px-4 py-2 border">Item Description</th>
                            <th class="px-4 py-2 border text-center">Quantity</th>
                            <th class="px-4 py-2 border text-right">Price (KWD)</th>
                            <th class="px-4 py-2 border text-right">Total (KWD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $unrefundedTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php $task = $detail->task; ?>
                            <tr class="text-sm text-gray-700">
                                <td class="px-4 py-2 border align-top">
                                    <div class="font-semibold text-gray-900"><?php echo e(ucfirst($task->type ?? 'N/A')); ?></div>

                                    <?php if($task->type === 'flight'): ?>
                                        <?php if(!empty($task->reference)): ?> Reference: <?php echo e($task->reference); ?> <br> <?php endif; ?>
                                        <?php if(!empty($task->gds_reference)): ?> GDS Ref: <?php echo e($task->gds_reference); ?> <br> <?php endif; ?>
                                        Passenger: <?php echo e($task->passenger_name ?? 'N/A'); ?> <br>
                                        Route:
                                        <?php echo e($task->flightDetails->countryFrom->name ?? ''); ?>

                                        (<?php echo e($task->flightDetails->airport_from ?? ''); ?>) →
                                        <?php echo e($task->flightDetails->countryTo->name ?? ''); ?>

                                        (<?php echo e($task->flightDetails->airport_to ?? ''); ?>)

                                    <?php elseif($task->type === 'hotel'): ?>
                                        Hotel: <?php echo e($task->hotelDetails->hotel->name ?? 'N/A'); ?> <br>
                                        Check-In: <?php echo e($task->hotelDetails->check_in ?? 'N/A'); ?> <br>
                                        Check-Out: <?php echo e($task->hotelDetails->check_out ?? 'N/A'); ?> <br>
                                        Room: <?php echo e($task->hotelDetails->room_type ?? 'N/A'); ?>


                                    <?php elseif($task->type === 'visa'): ?>
                                        Visa Type: <?php echo e($task->visaDetails->visa_type ?? 'N/A'); ?> <br>
                                        Passenger: <?php echo e($task->passenger_name ?? 'N/A'); ?> <br>
                                        Country: <?php echo e($task->visaDetails->issuing_country ?? 'N/A'); ?>


                                    <?php elseif($task->type === 'insurance'): ?>
                                        Insurance: <?php echo e($task->insuranceDetails->insurance_type ?? 'N/A'); ?> <br>
                                        Plan: <?php echo e($task->insuranceDetails->plan_type ?? 'N/A'); ?> <br>
                                        Destination: <?php echo e($task->insuranceDetails->destination ?? 'N/A'); ?>


                                    <?php else: ?>
                                        <?php echo e($task->reference ?? 'N/A'); ?>

                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 border text-center">1</td>
                                <td class="px-4 py-2 border text-right"><?php echo e(number_format($detail->task_price ?? 0, 3)); ?></td>
                                <td class="px-4 py-2 border text-right">
                                    <?php echo e(number_format(($detail->quantity ?? 1) * ($detail->task_price ?? 0), 3)); ?>

                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        

        <?php
            $originalInvoice = $refund->originalInvoice;
            $totalPaidOnOriginal = $originalInvoice ? $originalInvoice->invoicePartials->where('status', 'paid')->sum('amount') : 0;

            $unrefundedTotal = $unrefundedTasks->sum('task_price');

            // Payment balance = what they paid - what they're keeping
            // Positive = overpayment (credit to client)
            // Negative = underpayment (client owes more)
            $paymentBalance = $totalPaidOnOriginal - $unrefundedTotal;

            // Show when subtotal differs from refund charges (meaning adjustment was applied)
            $adjustmentApplied = abs($invoice->sub_amount - $refund->total_nett_refund) > 0.001;
        ?>
        <div class="flex flex-col md:flex-row justify-between items-end gap-6 border-t pt-6 mt-8 mb-10">
            <div class="flex gap-2 justify-end md:justify-start w-full md:w-auto">
                <?php if($invoice->status !== 'paid'): ?>
                    <form id="whatsappForm" action="<?php echo e(route('resayil.share-invoice-link')); ?>" method="POST" onsubmit="showSpinner()">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="client_id" id="clientid" value="<?php echo e($invoice->client->id); ?>">
                        <input type="hidden" name="invoiceNumber" value="<?php echo e($invoice->invoice_number); ?>">

                        <button id="submitButton" type="submit"
                            class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                            <span id="buttonText">Send Invoice To Client</span>
                            <span id="spinner" class="hidden ml-2">
                                <svg class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 0-8-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"></path>
                                </svg>
                            </span>
                        </button>
                    </form>
                    <form id="paymentForm" action="<?php echo e(route('payment.create', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number])); ?>"
                        method="POST">
                        <?php echo csrf_field(); ?>

                        <input type="hidden" name="total_amount" value="<?php echo e($totalGatewayFee['finalAmount'] ?? $invoice->amount); ?>">
                        <input type="hidden" name="client_email" value="<?php echo e($invoice->client->email); ?>">
                        <input type="hidden" name="client_name" value="<?php echo e($invoice->client->full_name); ?>">
                        <input type="hidden" name="client_phone" value="<?php echo e($invoice->client->phone); ?>">
                        <input type="hidden" name="payment_gateway" value="<?php echo e($invoice->invoicePartials->first()->payment_gateway); ?>">
                        <input type="hidden" name="payment_method" value="<?php echo e($invoice->invoicePartials->first()->payment_method); ?>">
                        <input type="hidden" name="invoice_partial_id" value="<?php echo e($invoice->invoicePartials->first()->id); ?>">

                        <button type="submit"
                            class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                            Pay Now
                        </button>
                        <div id="loadingSpinner" class="hidden mt-2">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            Processing...
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-green-600 font-bold text-lg">PAID</p>
                <?php endif; ?>
            </div>
            <div class="w-full md:w-1/3 text-sm text-black">
                <?php if($invoice->refund && $invoice->refund->originalInvoice): ?>
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span>Original Invoice:</span>
                        <span><?php echo e(number_format($invoice->refund->originalInvoice->amount, 3)); ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Refund Charges:</span>
                    <span><?php echo e(number_format($refund->total_nett_refund ?? 0, 3)); ?></span>
                </div>
                <?php if($adjustmentApplied && $unrefundedTasks->isNotEmpty()): ?>
                    <?php if($paymentBalance > 0): ?>
                        
                        <div class="flex justify-between py-2 border-b border-gray-200 text-green-600">
                            <span>Overpayment Credit:</span>
                            <span>-<?php echo e(number_format($paymentBalance, 3)); ?></span>
                        </div>
                    <?php elseif($paymentBalance < 0): ?>
                        
                        <div class="flex justify-between py-2 border-b border-gray-200 text-red-600">
                            <span>Outstanding Balance:</span>
                            <span>+<?php echo e(number_format(abs($paymentBalance), 3)); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Subtotal:</span>
                    <span><?php echo e(number_format($invoice->sub_amount, 3)); ?></span>
                </div>
                <!-- <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Tax (<?php echo e($invoice->tax); ?>%):</span>
                    <span><?php echo e(number_format($invoice->tax, 3)); ?></span>
                </div> -->

                <?php if($invoice->status === 'paid' || $invoice->payment_type === 'split'): ?>
                    <?php
                        $paidServiceCharge = $invoice->invoicePartials->sum('service_charge');
                    ?>
                    <?php if($paidServiceCharge > 0): ?>
                        <div class="flex justify-between py-2 border-b border-gray-200">
                            <span>Service Charge:</span>
                            <span><?php echo e(number_format($paidServiceCharge, 3)); ?></span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if(isset($totalGatewayFee['paid_by']) && $totalGatewayFee['paid_by'] !== 'Company' && $totalGatewayFee['gatewayFee'] > 0): ?>
                        <div class="flex justify-between py-2 border-b border-gray-200">
                            <span>Service Charge <?php if(isset($totalGatewayFee['charge_type']) && $totalGatewayFee['charge_type'] === 'Percent'): ?> (%): <?php else: ?>: <?php endif; ?></span>
                            <span><?php echo e(number_format($totalGatewayFee['gatewayFee'], 3)); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="flex justify-between py-2 font-bold text-gray-800 text-base md:text-lg">
                    <span>Total:</span>
                    <span>
                        <?php echo e(number_format($totalGatewayFee['finalAmount'] ?? $invoice->amount, 3)); ?>

                    </span>
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center">
            <div class="text-sm">
                <p class="text-gray-600">If you have any questions about this invoice, please contact:</p>
                <p class="text-gray-600"><?php echo e($invoice->agent->branch->company->name); ?>,
                    <?php echo e($invoice->agent->branch->company->phone); ?>, <?php echo e($invoice->agent->branch->company->email); ?>

                </p>
            </div>
            <div class="text-right">
                <p class="font-bold text-gray-800">Thank you for your business!</p>
            </div>
        </div>
    </div>

    <?php if($invoice->status === 'paid'): ?>
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg mt-6">
        <div class="invoice">
            <div class="payment-status bg-green-100 p-6 rounded-lg mt-4">
                <h3 class="text-xl font-semibold text-green-700 mb-2">Payment Receipt</h3>
            </div>

            <table class="min-w-full mb-8 border border-gray-200">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                        <th class="px-4 py-2 border">Receipt #</th>
                        <th class="px-4 py-2 border">Reference</th>
                        <th class="px-4 py-2 border">Payment Date</th>
                        <th class="px-4 py-2 border">Payment Gateway</th>
                        <th class="px-4 py-2 border">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $paidPartials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $partial): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="text-sm text-gray-700">
                        <td class="px-4 py-2 border">
                            <a href="<?php echo e(route('payment.link.show', ['companyId' => $companyId, 'voucherNumber' => $partial->payment->voucher_number])); ?>"
                                class="text-blue-500 underline" target="_blank"><?php echo e($partial->payment->voucher_number); ?>

                            </a>
                        </td>
                        <td class="px-4 py-2 border">
                            <?php if($partial->payment->payment_gateway === 'MyFatoorah'): ?>
                                <?php echo e($partial->payment->myfatoorahPayment->invoice_ref ?? $partial->payment->myfatoorahPayment->payload['Data']['InvoiceReference'] ?? 'N/A'); ?>

                            <?php else: ?>
                                <?php echo e($partial->payment->payment_reference ?? 'N/A'); ?>

                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2 border">
                            <?php echo e($partial->payment ? \Carbon\Carbon::parse($partial->payment->payment_date)->format('d M, Y H:i') : \Carbon\Carbon::parse($partial->updated_at)->format('d M, Y H:i')); ?>

                        </td>
                        <td class="px-4 py-2 border"><?php echo e($partial->payment_gateway); ?></td>
                        <td class="px-4 py-2 border">
                            <?php echo e(number_format($partial->amount ?? 0, 3)); ?>

                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>

            <div class="flex justify-end mb-8">
                <div class="w-1/3 text-sm">
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span>Balance:</span>
                        <span id="balance"></span>
                    </div>
                </div>
            </div>

            <div class="thank-you mt-6 bg-gray-100 p-6 rounded-lg">
                <h4 class="text-xl font-semibold text-gray-800 mb-2">Thank You for Your Payment!</h4>
                <p class="text-lg text-gray-600">We appreciate your business! A confirmation email has been sent to your address.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <script>
        let invoice = <?php echo json_encode($invoice, 15, 512) ?>;
        let invoicePartials = <?php echo json_encode($invoicePartials, 15, 512) ?>;

        // Calculate the total paid amount from invoicePartials
        let totalPaidAmount = invoicePartials.filter(partial => partial.status === 'paid')
            .reduce((sum, partial) => sum + parseFloat(partial.amount), 0);

        let totalPaidServiceCharge = invoicePartials.filter(partial => partial.status === 'paid')
            .reduce((sum, partial) => sum + parseFloat(partial.service_charge), 0);

        // Calculate balance
        let balance = invoice.amount - totalPaidAmount + totalPaidServiceCharge;

        let balanceElement = document.getElementById('balance');
        if (balanceElement) {
            balanceElement.textContent = balance.toFixed(3);
        }
    </script>
</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/show-refund.blade.php ENDPATH**/ ?>