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
    <?php $__env->startPush('styles'); ?>
        <link rel="stylesheet" href="<?php echo e(asset('css/outstanding.css')); ?>">
    <?php $__env->stopPush(); ?>

    <?php
        function sortUrl($type, $field, $currentSort, $currentDirection) {
            $newDirection = ($currentSort === $field && $currentDirection === 'asc') ? 'desc' : 'asc';
            $params = request()->query();
            
            if ($type === 'pl') {
                $params['ps'] = $field;
                $params['pd'] = $newDirection;
            } else {
                $params['is'] = $field;
                $params['id'] = $newDirection;
            }
            
            return request()->url() . '?' . http_build_query($params);
        }
    ?>

    <div class="main-page-header">
        <div class="main-page-header-left">
            <h2 class="main-page-title">Outstanding</h2>
        </div>
    </div>

    <div class="main-panel" 
         x-data="{ 
            activeTab: localStorage.getItem('outstanding_tab') || 'payment_links',
            setTab(tab) {
                this.activeTab = tab;
                localStorage.setItem('outstanding_tab', tab);
            }
         }">
        <div class="main-tabs-bar">
            <button
                @click="setTab('payment_links')"
                class="main-tab-shape main-tab"
                :class="activeTab === 'payment_links' ? 'main-tab-active' : 'main-tab-inactive'">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                    Payment Links
                    <span class="main-tab-badge main-tab-badge-amber"><?php echo e($totalPaymentLinks); ?></span>
                </div>
            </button>

            <button
                @click="setTab('invoices')"
                class="main-tab-shape main-tab"
                :class="activeTab === 'invoices' ? 'main-tab-active' : 'main-tab-inactive'">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Invoices
                    <span class="main-tab-badge main-tab-badge-red"><?php echo e($totalInvoices); ?></span>
                </div>
            </button>
        </div>

        <!-- Tab Content: Payment Links -->
        <div x-show="activeTab === 'payment_links'" x-cloak class="main-tab-content">
            <div class="main-section-header">
                <div>
                    <h3 class="main-section-title">Pending Payment Links</h3>
                    <p class="main-section-subtitle"><?php echo e($totalPaymentLinks); ?> payment <?php echo e(Str::plural('link', $totalPaymentLinks)); ?> awaiting completion</p>
                </div>
            </div>

            <!-- Search Component -->
            <div class="mb-4">
                <?php if (isset($component)) { $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.search','data' => ['action' => route('payment.outstanding'),'searchParam' => 'search','placeholder' => 'Search by voucher number, client name, or agent name']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('search'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('payment.outstanding')),'searchParam' => 'search','placeholder' => 'Search by voucher number, client name, or agent name']); ?>
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
            </div>

            <div class="main-table-container">
                <div class="main-table-scroll">
                    <table class="main-table">
                        <thead>
                            <tr class="main-table-thead">
                                <th class="main-table-th">
                                    <a href="<?php echo e(sortUrl('pl', 'voucher_number', $plSort, $plDirection)); ?>" class="main-sort-link">
                                        Voucher Number
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon <?php echo e($plSort === 'voucher_number' && $plDirection === 'asc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down <?php echo e($plSort === 'voucher_number' && $plDirection === 'desc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th">Agent</th>
                                <th class="main-table-th">
                                    <a href="<?php echo e(sortUrl('pl', 'client_name', $plSort, $plDirection)); ?>" class="main-sort-link">
                                        Client
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon <?php echo e($plSort === 'client_name' && $plDirection === 'asc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down <?php echo e($plSort === 'client_name' && $plDirection === 'desc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th">Contact</th>
                                <th class="main-table-th">Payment Type</th>
                                <th class="main-table-th">Amount</th>
                                <th class="main-table-th">Client Pay</th>
                                <th class="main-table-th">Created By</th>
                                <th class="main-table-th">Reference</th>
                                <th class="main-table-th-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($paymentLinks->isEmpty()): ?>
                            <tr>
                                <td colspan="10" class="main-table-empty">
                                    <div class="flex flex-col items-center">
                                        <svg class="main-table-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <span class="main-table-empty-title"><?php echo e(request('search') ? 'No results found' : 'All caught up!'); ?></span>
                                        <span class="main-table-empty-subtitle"><?php echo e(request('search') ? 'Try a different search term' : 'No pending payment links'); ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php $__currentLoopData = $paymentLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="main-table-row" onclick="window.location.href='<?php echo e(route('payment.link.index', ['q' => $payment->voucher_number])); ?>'">
                                <td class="main-table-td">
                                    <span class="main-table-td-link"><?php echo e($payment->voucher_number); ?></span>
                                </td>
                                <td class="main-table-td"><?php echo e($payment->agent?->name ?? 'Not Set'); ?></td>
                                <td class="main-table-td-bold"><?php echo e($payment->client?->full_name ?? 'Not Set'); ?></td>
                                <td class="main-table-td"><?php echo e($payment->client ? $payment->client->country_code . $payment->client->phone : 'Not Set'); ?></td>
                                <td class="main-table-td">
                                    <?php
                                        $gateway = $payment->payment_gateway ?? 'Not Set';
                                        $method = $payment->paymentMethod->english_name ?? null;
                                    ?>
                                    <?php echo e($method ? "$gateway - $method" : $gateway); ?>

                                </td>
                                <td class="main-table-td-bold"><?php echo e(number_format($payment->amount, 3)); ?> KWD</td>
                                <td class="main-table-td-bold"><?php echo e(number_format($payment->amount + $payment->service_charge, 3)); ?> KWD</td>
                                <td class="main-table-td"><?php echo e($payment->createdBy ? $payment->createdBy->name : 'N/A'); ?></td>
                                <td class="main-table-td whitespace-nowrap">
                                    <?php
                                        $payment_reference = match(true) {
                                            !empty($payment->myFatoorahPayment?->invoice_ref) => $payment->myFatoorahPayment->invoice_ref,
                                            !empty($payment->hesabePayment?->invoice_id) => $payment->hesabePayment->invoice_id,
                                            !empty($payment->payment_reference) => $payment->payment_reference,
                                            default => 'N/A'
                                        };
                                        $isTrimmed = strlen($payment_reference) > 15;
                                        $trimmedValue = \Illuminate\Support\Str::limit($payment_reference, 15);
                                    ?>
                                    <?php if($isTrimmed): ?>
                                        <span x-data="{ showFullData: false }">
                                            <span x-show="!showFullData" @click.stop="showFullData = !showFullData" class="cursor-pointer hover:text-purple-700" data-tooltip-left="Click to expand"><?php echo e($trimmedValue); ?></span>
                                            <span x-show="showFullData" @click.stop="showFullData = !showFullData" class="cursor-pointer hover:text-purple-500"><?php echo e($payment_reference); ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span><?php echo e($payment_reference); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="main-table-td-center">
                                    <?php
                                        $statusClass = match(strtolower($payment->status)) {
                                            'pending' => 'main-badge-yellow',
                                            'initiate' => 'main-badge-blue',
                                            'failed' => 'main-badge-red',
                                            'cancelled' => 'main-badge-gray',
                                            default => 'main-badge-gray'
                                        };
                                    ?>
                                    <span class="main-badge <?php echo e($statusClass); ?>"><?php echo e(ucfirst($payment->status)); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($paymentLinks->hasPages()): ?>
                <div class="main-table-pagination">
                    <?php echo e($paymentLinks->withQueryString()->links()); ?>

                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab Content: Invoices -->
        <div x-show="activeTab === 'invoices'" x-cloak class="main-tab-content">
            <div class="main-section-header">
                <div>
                    <h3 class="main-section-title">Unpaid Invoices</h3>
                    <p class="main-section-subtitle"><?php echo e($totalInvoices); ?> <?php echo e(Str::plural('invoice', $totalInvoices)); ?> awaiting payment</p>
                </div>
            </div>

            <!-- Search Component -->
            <div class="mb-4">
                <?php if (isset($component)) { $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.search','data' => ['action' => route('payment.outstanding'),'searchParam' => 'search','placeholder' => 'Search by invoice number, client name, or agent name']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('search'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('payment.outstanding')),'searchParam' => 'search','placeholder' => 'Search by invoice number, client name, or agent name']); ?>
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
            </div>

            <div class="main-table-container">
                <div class="main-table-scroll">
                    <table class="main-table">
                        <thead>
                            <tr class="main-table-thead">
                                <th class="main-table-th">
                                    <a href="<?php echo e(sortUrl('inv', 'invoice_number', $invSort, $invDirection)); ?>" class="main-sort-link">
                                        Invoice Number
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon <?php echo e($invSort === 'invoice_number' && $invDirection === 'asc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down <?php echo e($invSort === 'invoice_number' && $invDirection === 'desc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th">Agent</th>
                                <th class="main-table-th">Client</th>
                                <th class="main-table-th">Payment Type</th>
                                <th class="main-table-th">Net Amount</th>
                                <th class="main-table-th">Profit</th>
                                <th class="main-table-th">Invoice Amount</th>
                                <th class="main-table-th">Service Charges</th>
                                <th class="main-table-th">Client Pay</th>
                                <th class="main-table-th">
                                    <a href="<?php echo e(sortUrl('inv', 'created_at', $invSort, $invDirection)); ?>" class="main-sort-link">
                                        Created Date
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon <?php echo e($invSort === 'created_at' && $invDirection === 'asc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down <?php echo e($invSort === 'created_at' && $invDirection === 'desc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th">
                                    <a href="<?php echo e(sortUrl('inv', 'invoice_date', $invSort, $invDirection)); ?>" class="main-sort-link">
                                        Invoice Date
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon <?php echo e($invSort === 'invoice_date' && $invDirection === 'asc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down <?php echo e($invSort === 'invoice_date' && $invDirection === 'desc' ? 'main-sort-icon-active' : ''); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($invoices->isEmpty()): ?>
                            <tr>
                                <td colspan="12" class="main-table-empty">
                                    <div class="flex flex-col items-center">
                                        <svg class="main-table-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <span class="main-table-empty-title"><?php echo e(request('search') ? 'No results found' : 'All caught up!'); ?></span>
                                        <span class="main-table-empty-subtitle"><?php echo e(request('search') ? 'Try a different search term' : 'No unpaid invoices'); ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="main-table-row" onclick="window.location.href='<?php echo e(route('invoices.index', ['search' => $invoice->invoice_number])); ?>'">
                                <td class="main-table-td">
                                    <span class="main-table-td-link"><?php echo e($invoice->invoice_number); ?></span>
                                </td>
                                <td class="main-table-td-bold"><?php echo e($invoice->agent?->name ?? 'Not Set'); ?></td>
                                <td class="main-table-td-bold"><?php echo e($invoice->client?->full_name ?? 'Not Set'); ?></td>
                                <td class="main-table-td"><?php echo e($invoice->payment_type ? ucwords($invoice->payment_type) : 'Not Set'); ?></td>
                                <td class="main-table-td-bold"><?php echo e(number_format($invoice->invoiceDetails->sum('supplier_price'), 3)); ?> <?php echo e($invoice->currency); ?></td>
                                <td class="main-table-td-bold"><?php echo e(number_format($invoice->invoiceDetails->sum('profit'), 3)); ?> <?php echo e($invoice->currency); ?></td>
                                <td class="main-table-td-bold"><?php echo e(number_format($invoice->amount, 3)); ?> <?php echo e($invoice->currency); ?></td>
                                <td class="main-table-td-bold"><?php echo e(number_format($invoice->invoicePartials->sum('service_charge'), 3)); ?> <?php echo e($invoice->currency); ?></td>
                                <td class="main-table-td-bold"><?php echo e(number_format($invoice->client_pay, 3)); ?> <?php echo e($invoice->currency); ?></td>
                                <td class="main-table-td"><?php echo e($invoice->created_at->format('d-m-Y H:i')); ?></td>
                                <td class="main-table-td"><?php echo e($invoice->invoice_date); ?></td>
                                <td class="main-table-td-center">
                                    <?php
                                        $statusClass = match(strtolower($invoice->status)) {
                                            'unpaid' => 'main-badge-red',
                                            'partial' => 'main-badge-amber',
                                            default => 'main-badge-gray'
                                        };
                                    ?>
                                    <span class="main-badge <?php echo e($statusClass); ?>"><?php echo e(ucfirst($invoice->status)); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($invoices->hasPages()): ?>
                <div class="main-table-pagination">
                    <?php echo e($invoices->withQueryString()->links()); ?>

                </div>
                <?php endif; ?>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/payment/outstanding.blade.php ENDPATH**/ ?>