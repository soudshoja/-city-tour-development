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
    <div class="flex justify-between items-center gap-5 my-3 ">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Receipt Voucher</h2>
            <div data-tooltip="Number of receipt voucher"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white"><?php echo e($totalRecords); ?></span>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>

            <a href="<?php echo e(route('receipt-voucher.create')); ?>">
                <div data-tooltip-left="Create new receipt voucher" class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <div class="panel rounded-lg">
        <?php if (isset($component)) { $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.search','data' => ['action' => route('receipt-voucher.index'),'searchParam' => 'q','placeholder' => 'Quick search for receipt voucher']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('search'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('receipt-voucher.index')),'searchParam' => 'q','placeholder' => 'Quick search for receipt voucher']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6)): ?>
<?php $attributes = $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6; ?>
<?php unset($__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6)): ?>
<?php $component = $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6; ?>
<?php unset($__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6); ?>
<?php endif; ?>

        <div class="dataTable-wrapper mt-4">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th class="p-3 text-left text-md font-bold text-gray-500">Receipt Ref</th>
                            <th class="p-3 text-left text-md font-bold text-gray-500">Type</th>
                            <th class="p-3 text-left text-md font-bold text-gray-500">Receive from</th>
                            <th class="p-3 text-left text-md font-bold text-gray-500">Doc Date</th>
                            <th class="p-3 text-left text-md font-bold text-gray-500">Description</th>
                            <th class="p-3 text-left text-md font-bold text-gray-500">Registered</th>
                            <th class="p-3 text-left text-md font-bold text-gray-500">Amount (KWD)</th>
                            <th class="p-3 text-left text-md font-bold text-gray-500">Status</th>
                            <th class="p-3 text-left text-md font-bold text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($invoicereceiptvouchers->isEmpty()): ?>
                        <tr>
                            <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">
                                No data for now.... Create new!</td>
                        </tr>
                        <?php else: ?>
                        <?php $__currentLoopData = $invoicereceiptvouchers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $receiptvoucher): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $invoicePartial = null;
                            if (!empty($receiptvoucher->invoice_id) && isset($invoicePartials[$receiptvoucher->invoice_id])) {
                                $invoicePartial = $invoicePartials[$receiptvoucher->invoice_id]->first();
                            }
                        ?>
                        <tr class="p-3 text-sm font-semibold text-gray-500">
                            <td>
                                <a data-tooltip="View receipt voucher" href="<?php echo e(route('receipt-voucher.edit', $receiptvoucher->id)); ?>"
                                    class="text-blue-500 hover:underline">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20"
                                        height="20" viewBox="0 0 24 24">
                                        <g fill="none" stroke="currentColor" stroke-width="1">
                                            <path
                                                d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z"
                                                opacity=".5"></path>
                                            <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path>
                                        </g>
                                    </svg>
                                </a>
                            </td>
                            <td>
                                <?php echo e($receiptvoucher->reference_number); ?>

                            </td>
                            <td>
                                <?php echo e($receiptvoucher->reference_type); ?>

                            </td>
                            <td class="whitespace-normal break-words w-[32rem]">
                                <?php echo e($receiptvoucher->name); ?>

                            </td>
                            <td>
                                <?php echo e(\Carbon\Carbon::parse($receiptvoucher->date)->format('Y-m-d')); ?>

                            </td>
                            <td class="whitespace-normal break-words w-[28rem]">
                                <?php echo e($receiptvoucher->description); ?>

                            </td>
                            <td>
                                <?php echo e($receiptvoucher->created_at); ?>

                            </td>
                            <td>
                                KWD <?php echo e(number_format($receiptvoucher->amount, 2)); ?>

                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <?php
                                        $receipt = $receiptvoucher->invoiceReceipt;
                                        $approved = $receipt?->status === 'approved';                                             
                                    ?>

                                    <?php if($receipt && $approved): ?>
                                        <span class="inline-flex items-center rounded-full border border-green-600/30 bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                            Paid
                                        </span>
                                    <?php elseif($receipt && ! $approved): ?>
                                        <span class="inline-flex items-center rounded-full border border-red-600/30 bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                            Unpaid
                                        </span>
                                    <?php else: ?>
                                        
                                        <span class="inline-flex items-center rounded-full border border-red-600/30 bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                            Unpaid
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center space-x-3">
                                    <?php if(! $approved): ?>
                                        <form method="POST" action="<?php echo e(route('receipt-voucher.approve', $receiptvoucher->id)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <button type="submit" 
                                                class="bg-green-600 hover:bg-green-700 text-white font-semibold py-1 px-3 rounded-md text-sm shadow-sm transition-all">
                                                Approve
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span>N/A</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/receipt-voucher/index.blade.php ENDPATH**/ ?>