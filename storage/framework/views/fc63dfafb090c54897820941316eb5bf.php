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
            <h2 class="text-3xl font-bold">Payment Links</h2>
            <div data-tooltip="Number of payments"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white"><?php echo e($payments->total()); ?></span>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload"
                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>

            <a href="<?php echo e(route('payment.link.create')); ?>">
                <div data-tooltip-left="Create/import payment link"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <div class="panel rounded-lg">
        <div x-data="{ openFilters: false }">
            <div class="flex items-center gap-3 md:flex-nowrap">
                <?php if (isset($component)) { $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.search','data' => ['action' => route('payment.link.index'),'searchParam' => 'q','placeholder' => 'Quick search for payments']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('search'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('payment.link.index')),'searchParam' => 'q','placeholder' => 'Quick search for payments']); ?>
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

                <div class="shrink-0 flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-700 whitespace-nowrap">Select a date:</span>
                    <input type="text"
                        id="payment-date-range"
                        class="border-gray-300 rounded-full shadow-sm focus:ring-blue-500 focus:border-blue-500 px-4 py-2 text-sm cursor-pointer"
                        style="min-width: 240px;"
                        placeholder="Choose date range">
                </div>

                <button @click="openFilters = !openFilters"
                    class="shrink-0 inline-flex items-center gap-2 rounded-full bg-amber-100 px-4 py-2 text-sm text-amber-800 ring-1 ring-amber-200 hover:bg-amber-200 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Filters
                    <?php if(!empty($filters)): ?>
                    <span class="ml-1 rounded-full bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white">
                        <?php echo e(collect($filters)->filter()->count()); ?>

                    </span>
                    <?php endif; ?>
                </button>
            </div>

            <form id="date-filter-form" action="<?php echo e(route('payment.link.index')); ?>" method="GET" class="hidden">
                <input type="hidden" name="q" value="<?php echo e(request('q')); ?>" />
                <input type="hidden" name="filter[date_from]" id="date_from" value="<?php echo e(data_get($filters, 'date_from')); ?>">
                <input type="hidden" name="filter[date_to]" id="date_to" value="<?php echo e(data_get($filters, 'date_to')); ?>">
                <?php $__currentLoopData = request()->except(['filter', 'q']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>">
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php $__currentLoopData = request('filter', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filterKey => $filterValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(!in_array($filterKey, ['date_from', 'date_to'])): ?>
                <input type="hidden" name="filter[<?php echo e($filterKey); ?>]" value="<?php echo e($filterValue); ?>">
                <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </form>

            <div x-show="openFilters" x-cloak x-transition
                class="mt-3 rounded-xl border border-gray-200 bg-gray-50/70 shadow-sm">
                <div class="flex items-center justify-between gap-2 border-b border-dashed border-gray-200 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-700">Filter payments</span>
                    <button @click="openFilters = false" class="rounded-full px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition">
                        Hide
                    </button>
                </div>
                <form action="<?php echo e(route('payment.link.index')); ?>" method="GET" class="px-4 pt-4">
                    <input type="hidden" name="q" value="<?php echo e(request('q')); ?>" />
                    <input type="hidden" name="filter[date_from]" value="<?php echo e(data_get($filters, 'date_from')); ?>">
                    <input type="hidden" name="filter[date_to]" value="<?php echo e(data_get($filters, 'date_to')); ?>">

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'filter[client_id]','items' => $clients->map(fn($c) => [
                                'id' => $c->id, 
                                'name' => $c->full_name . ' - ' . $c->phone
                            ]),'placeholder' => 'Select clients','label' => 'Client'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(optional($clients->firstWhere('id', data_get($filters,'client_id')))->name)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>

                        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'filter[agent_id]','items' => $agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),'placeholder' => 'Select agents','label' => 'Agent'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(optional($agents->firstWhere('id', data_get($filters,'agent_id')))->name)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>

                        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'filter[created_by]','items' => $users->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),'placeholder' => 'Select users','label' => 'Created By'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(optional($users->firstWhere('id', data_get($filters,'created_by')))->name)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>

                        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'filter[payment_gateway]','items' => $paymentGateways->map(fn($g) => ['id' => $g->name, 'name' => $g->name]),'placeholder' => 'Select gateways','label' => 'Payment Gateway'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(data_get($filters,'payment_gateway'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>

                        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'filter[status]','items' => collect($status)->map(fn($s) => ['id' => $s, 'name' => ucfirst($s)]),'placeholder' => 'Select status','label' => 'Status'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(data_get($filters,'status') ? ucfirst(data_get($filters,'status')) : null)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
                    </div>
                    <div class="sticky bottom-0 -mx-4 mt-4 flex items-center justify-end gap-2 border-t border-gray-200 bg-white/80 px-4 py-3 backdrop-blur">
                        <a href="<?php echo e(route('payment.link.index', array_filter(['q' => request('q'), 'clear' => 1]))); ?>"
                            class="rounded-full bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                            Clear
                        </a>
                        <button type="submit"
                            class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm">
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="dataTable-wrapper mt-4">
            <div class="dataTable-container h-max">
                <table class="table-hover dataTable-table">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500 whitespace-nowrap">
                            <th>Invoice Link</th>
                            <th>Client</th>
                            <th>Client Contact</th>
                            <th>Agent</th>
                            <th>Payment Type</th>
                            <th>Notes</th>
                            <th>Amount</th>
                            <th>Client Pay</th>
                            <th>Created At</th>
                            <th>Created By</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th class="sticky right-0 bg-gray-50 shadow-[-2px_0_4px_rgba(0,0,0,0.1)]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($payments->isEmpty()): ?>
                        <tr>
                            <td class="p-4 text-center text-gray-500" colspan="13">
                                No payment links found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $paymentUrl = route('payment.show', $payment->id);
                            ?>
                            <tr class="p-3 text-sm font-semibold text-gray-600 group">
                                <td class="whitespace-nowrap">
                                    <a href="<?php echo e($paymentUrl); ?>" target="_blank"
                                        class="text-blue-500 hover:underline"><?php echo e($payment->voucher_number); ?></a>
                                </td>
                                <td class="break-words max-w-[350px] font-semibold">
                                    <a href="<?php echo e(route('clients.show', $payment->client_id)); ?>" class="hover:underline hover:text-blue-600">
                                        <?php echo e($payment->client ? $payment->client->full_name : 'N/A'); ?>

                                    </a>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php echo e($payment->client ? $payment->client->country_code . $payment->client->phone : 'N/A'); ?>

                                </td>
                                <td class="whitespace-nowrap">
                                    <?php echo e($payment->agent ? $payment->agent->name : 'N/A'); ?>

                                </td>
                                <td class="break-words">
                                    <?php
                                    $gateway = $payment->payment_gateway ?? 'N/A';
                                    $method = $payment->paymentMethod->english_name ?? null;
                                    ?>
                                    <?php echo e($method ? "$gateway - $method" : $gateway); ?>

                                </td>
                                <td class="break-words max-w-[350px]">
                                    <?php echo e($payment->notes ?? 'No Notes'); ?>

                                </td>
                                <td class="whitespace-nowrap">
                                    <?php echo e(number_format($payment->amount,3)); ?>

                                </td>
                                <td class="whitespace-nowrap">
                                    <?php echo e(number_format($payment->amount + $payment->service_charge,3)); ?>

                                </td>
                                <?php if(auth()->user()->role->name === 'admin' || auth()->user()->role->name === 'company'): ?>
                                <td class="whitespace-nowrap">
                                    <?php echo e($payment->created_at->format('d-m-Y H:i:s')); ?>

                                </td>
                                <?php else: ?>
                                <td class="break-words max-w-[200px]">
                                    <?php echo e($payment->created_at->format('D d M Y')); ?>

                                </td>
                                <?php endif; ?>
                                <td class="whitespace-nowrap">
                                    <?php echo e($payment->createdBy ? $payment->createdBy->name : 'N/A'); ?>

                                </td>
                                <td class="whitespace-nowrap">
                                    <?php
                                    $payment_reference = $payment->myFatoorahPayment ? $payment->myFatoorahPayment->invoice_ref : $payment->payment_reference;
                                    if($payment_reference === null) {
                                    $payment_reference = 'N/A';
                                    }

                                    $isTrimmed = strlen($payment_reference) > 15;
                                    $trimmedValue = \Illuminate\Support\Str::limit($payment_reference, 15);
                                    ?>
                                    <?php if($isTrimmed): ?>
                                    <span x-data="{ showFullData: false }">
                                        <span x-show="!showFullData" @click="showFullData = !showFullData"
                                            class="cursor-pointer hover:text-purple-700"
                                            data-tooltip-left="Click to expand">
                                            <?php echo e($trimmedValue); ?>

                                        </span>

                                        <span x-show="showFullData" @click="showFullData = !showFullData"
                                            class="cursor-pointer hover:text-purple-500">
                                            <?php echo e($payment_reference); ?>

                                        </span>
                                    </span>
                                    <?php else: ?>
                                    <span><?php echo e($payment_reference); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-600',
                                    'completed' => 'bg-green-100 text-green-800 border-green-600',
                                    'failed' => 'bg-red-100 text-red-800 border-red-600',
                                    'cancelled' => 'bg-gray-100 text-gray-600 border-gray-600',
                                    ];
                                    $status = strtolower($payment->status);
                                    $colorClass =
                                    $statusColors[$status] ??
                                    'bg-gray-100 text-gray-800 border-gray-600';
                                    ?>
                                    <span
                                        class="inline-block px-4 py-2 rounded-full font-semibold text-center <?php echo e($colorClass); ?> border-2 transition-all duration-200 ease-in-out transform hover:scale-105 hover:shadow-lg">
                                        <?php echo e(ucfirst($payment->status)); ?>

                                    </span>
                                </td>
                                <td class="whitespace-nowrap relative sticky right-0 bg-white group-hover:!bg-[#e0e1e4] shadow-[-2px_0_4px_rgba(0,0,0,0.1)]">
                                    <div x-data="{ open: false, editPaymentLink: false }" @keydown.escape.window="open = false; editPaymentLink = false" class="relative flex items-center justify-center h-full">
                                        <button @click="open = !open" x-ref="button" @click.outside="open = false" class="p-1 rounded hover:bg-gray-100">
                                            <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 13a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 20a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />
                                            </svg>
                                        </button>
                                        <template x-teleport="body">
                                            <div x-cloak x-show="open" x-transition x-anchor.bottom-start.offset.5="$refs.button" class="absolute w-34 rounded-md bg-white shadow-lg border border-gray-200">
                                                <form action="<?php echo e(route('resayil.share-payment-link')); ?>" method="POST" class="block">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="client_id" value="<?php echo e($payment->client_id); ?>">
                                                    <input type="hidden" name="payment_id" value="<?php echo e($payment->id); ?>">
                                                    <input type="hidden" name="voucher_number" value="<?php echo e($payment->voucher_number); ?>">
                                                    <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <svg class="h-5 w-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                        </svg>
                                                        Send Link
                                                    </button>
                                                </form>
                                                <button onclick="copyToClipboard('<?php echo e($paymentUrl); ?>')" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <svg class="h-5 w-5 mr-2 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 16h8M8 12h8m-6 8h6a2 2 0 002-2V7a2 2 0 00-2-2H9m-2 0H7a2 2 0 00-2 2v12a2 2 0 002 2h2V5z" />
                                                    </svg>
                                                    Copy Link
                                                </button>
                                                <a href="<?php echo e(route('payment.link.show', [ 'companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number, ])); ?>" target="_blank" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-green-500" 
                                                        viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                    View Invoice
                                                </a>
                                                <form action="<?php echo e(route('payment.link.payment.activation', $payment->id)); ?>" method="POST" class="block">
                                                    <?php echo csrf_field(); ?>
                                                    <?php if($payment->status !== 'completed' && !$payment->is_disabled): ?>
                                                    <div class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <button class="flex items-center gap-2 w-full">
                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                class="h-5 w-5 mr-2 text-purple-500"
                                                                fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M12 11c-1.657 0-3 1.343-3 3v3h6v-3c0-1.657-1.343-3-3-3z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 11V7a5 5 0 00-10 0v4" />
                                                                <rect x="5" y="11" width="14" height="10" rx="2" ry="2" />
                                                            </svg>
                                                            Disable Link
                                                        </button>
                                                    </div>
                                                    <?php elseif($payment->status !== 'completed' && $payment->is_disabled): ?>
                                                    <div class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <button class="flex items-center gap-2 w-full">
                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                class="h-5 w-5 mr-2 text-purple-500"
                                                                fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 8a5 5 0 10-10 0v1" />
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 8v-2" />
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M12 11c-1.657 0-3 1.343-3 3v3h6v-3c0-1.657-1.343-3-3-3z" />
                                                                <rect x="5" y="11" width="14" height="10" rx="2" ry="2" />
                                                            </svg>
                                                            Enable Link
                                                        </button>
                                                    </div>
                                                    <?php endif; ?>
                                                </form>
                                                <?php if($payment->status !== 'completed'): ?>
                                                <div class="border-t border-gray-200 my-1"></div>
                                                <button @click="editPaymentLink = true; open = false" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path d="M12 20h9M15 3l6 6-9 9H6v-6l9-9z" />
                                                    </svg>
                                                    Edit
                                                </button>
                                                <form action="<?php echo e(route('payment.link.delete', $payment->id)); ?>" method="POST" class="block">
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('DELETE'); ?>
                                                    <button type="submit"
                                                        class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </template>
                                        <template x-teleport="body">
                                            <div x-cloak x-show="editPaymentLink" class="fixed inset-0 z-10 flex items-center justify-center bg-gray-500 bg-opacity-50">
                                                <div
                                                    class="bg-white p-6 rounded shadow-lg w-full max-w-md relative">
                                                    <div class="flex items-center justify-between mb-6">
                                                        <div>
                                                            <h2 class="text-xl font-bold text-gray-800">Edit Payment Link Details</h2>
                                                            <p class="text-gray-600 italic text-xs mt-1">Please update the payment link details to ensure accurate information</p>
                                                        </div>
                                                        <button @click="editPaymentLink = false" class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                            &times;
                                                        </button>
                                                    </div>
                                                    <?php if($payment->status === 'initiate'): ?>
                                                    <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg shadow-sm">
                                                        <div class="flex items-start gap-3">
                                                            <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                                            </svg>
                                                            <div class="flex-1">
                                                                <p class="font-semibold text-yellow-900 mb-1">Payment Status: Initiate</p>
                                                                <p class="text-sm text-yellow-700 leading-relaxed mb-2">
                                                                    The following fields cannot be edited for initiate payments:
                                                                </p>
                                                                <ul class="text-sm text-yellow-700 list-disc list-inside space-y-1">
                                                                    <li>Client Info</li>
                                                                    <li>Payment Amount</li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <form action="<?php echo e(route('payment.link.update', $payment->id)); ?>" method="POST">
                                                        <?php echo csrf_field(); ?>
                                                        <?php echo method_field('PUT'); ?>
                                                        <?php if (! \Illuminate\Support\Facades\Blade::check('role', 'agent')): ?>
                                                        <?php
                                                        $selectedAgent = \App\Models\Agent::find($payment->agent_id);
                                                        $agentPlaceholder = $selectedAgent ? $selectedAgent->name : 'Select an Agent';
                                                        ?>

                                                        <div class="mb-4">
                                                            <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'agent_id','items' => $agents->map(
                                                                        fn($a) => [
                                                                            'id' => $a->id,
                                                                            'name' => $a->name,
                                                                        ],
                                                                    ),'placeholder' => $agentPlaceholder,'label' => 'Agent'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedAgent ? $selectedAgent->name : null)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
                                                        </div>
                                                        <?php else: ?>
                                                        <div class="mb-4">
                                                            <input type="hidden" name="agent_id" value="<?php echo e(auth()->user()->agent->id); ?>">
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php

                                                        $client = $payment->client;
                                                        $namePlaceholder = $client ? $client->full_name : 'Select a Client';
                                                        $dialPlaceholder = $client ? $client->country_code : 'Select Dial Code';
                                                        $phonePlaceholder = $client ? $client->phone : 'Enter Phone Number';

                                                        ?>

                                                        <?php if($payment->status === 'initiate'): ?>
                                                        <div class="mb-4">
                                                            <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                                                            <div class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-md">
                                                                <?php echo e($client ? $client->full_name . ' - ' . $client->phone : 'N/A'); ?>

                                                            </div>
                                                            <input type="hidden" name="client_id" value="<?php echo e($client ? $client->id : ''); ?>">
                                                        </div>
                                                        <?php else: ?>
                                                        <div class="mb-4">
                                                            <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'client_id','items' => $clients->map(
                                                                        fn($c) => [
                                                                            'id' => $c->id,
                                                                            'name' => $c->full_name . ' - ' . $c->phone
                                                                        ],
                                                                    ),'placeholder' => $namePlaceholder,'label' => 'Client'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($client ? $client->full_name : null)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
                                                            <input type="hidden" name="client_id_fallback" value="<?php echo e($client ? $client->id : ''); ?>">
                                                        </div>

                                                        <label for="phone_<?php echo e($payment->client_id); ?>" class="block text-sm font-medium text-gray-700">Phone Number</label>

                                                        <div class="flex gap-4 mb-4">
                                                            <div class="w-2/5">
                                                                <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'dial_code','items' => \App\Models\Country::all()->map(
                                                                            fn($country) => [
                                                                                'id' => $country->dialing_code,
                                                                                'name' => $country->dialing_code . ' ' . $country->name,
                                                                            ],
                                                                        ),'placeholder' => $dialPlaceholder] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($client ? $client->country_code : null),'showAllOnOpen' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
                                                                <input type="hidden" name="dial_code_fallback" value="<?php echo e($client ? $client->country_code : ''); ?>">
                                                            </div>

                                                            <div class="w-3/5">
                                                                <input type="text" name="phone" id="phone_<?php echo e($payment->client_id); ?>" value="<?php echo e($client ? $client->phone : ''); ?>"
                                                                    placeholder="Phone Number" class="form-input w-full border rounded px-3 py-2" required />
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php if($payment->paymentItems && $payment->paymentItems->isNotEmpty()): ?>
                                                        <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                                            <div class="flex items-start gap-3">
                                                                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                                <div class="flex-1">
                                                                    <p class="font-semibold text-blue-900 mb-1">Advance Payment Detected</p>
                                                                    <p class="text-sm text-blue-700 leading-relaxed">
                                                                        Amount modification is not available here. Please visit the
                                                                        <span class="font-semibold underline">payment details page</span> to update the amount.
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php else: ?>

                                                        <div class="mb-4">
                                                            <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                                                            <input type="text" name="amount" id="amount" value="<?php echo e($payment->amount); ?>"
                                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 <?php echo e($payment->status === 'initiate' ? 'bg-gray-100 cursor-not-allowed' : ''); ?>"
                                                                <?php echo e($payment->status === 'initiate' ? 'disabled' : ''); ?>>
                                                        </div>
                                                        <?php endif; ?>


                                                        <?php if($payment->availablePaymentMethodGroups && $payment->availablePaymentMethodGroups->isNotEmpty()): ?>
                                                        <?php
                                                        $prefill = session('prefill_data');
                                                        $selectedGateway = $prefill['payment_gateway'] ?? old('payment_gateway');
                                                        ?>
                                                        <div class="mb-4">
                                                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                                                            <div class="flex flex-wrap gap-8">
                                                                <?php
                                                                // Get existing payment method GROUP IDs from pivot table
                                                                $existingGroupIds = $payment->availablePaymentMethodGroups
                                                                ? $payment->availablePaymentMethodGroups->pluck('id')->toArray()
                                                                : [];
                                                                ?>
                                                                <?php $__currentLoopData = $paymentMethodChose; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chose): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <div class="flex items-center gap-4">
                                                                    <div class="flex">
                                                                        <input
                                                                            type="checkbox"
                                                                            name="payment_method_groups[]"
                                                                            value="<?php echo e($chose->paymentMethodGroup->id); ?>"
                                                                            id="edit_payment_method_group_<?php echo e($payment->id); ?>_<?php echo e($chose->paymentMethodGroup->id); ?>"
                                                                            <?php echo e(in_array($chose->paymentMethodGroup->id, old('payment_method_groups', $existingGroupIds)) ? 'checked' : ''); ?>

                                                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                        <label for="edit_payment_method_group_<?php echo e($payment->id); ?>_<?php echo e($chose->paymentMethodGroup->id); ?>" class="ml-2 text-sm text-gray-700">
                                                                            <?php echo e($chose->paymentMethodGroup->name); ?>

                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                            </div>
                                                        </div>
                                                        <?php else: ?>
                                                        <div class="mb-4" x-data="{ 
                                                                selectedGateway: '<?php echo e($payment->payment_gateway ?? ''); ?>', 
                                                                selectedMethod: '<?php echo e($payment->selected_method ?? ''); ?>',
                                                                gatewaysWithMethods: <?php echo \Illuminate\Support\Js::from($paymentGateways->filter(fn($g) => $g->methods->isNotEmpty())->pluck('name')->toArray())->toHtml() ?>,
                                                                hasMethod() {
                                                                    return this.gatewaysWithMethods.includes(this.selectedGateway);
                                                                }
                                                            }">
                                                            <div :class="hasMethod() ? 'grid grid-cols-1 md:grid-cols-2 gap-6 items-start' : 'block'">
                                                                <div>
                                                                    <label for="payment-gateway" class="block text-sm font-medium text-gray-700">Payment Gateway</label>
                                                                    <select name="payment_gateway" id="payment_gateway"
                                                                        class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                        x-model="selectedGateway">
                                                                        <option value="" disabled>Select Payment Gateway</option>
                                                                        <?php $__currentLoopData = $paymentGateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                        <option value="<?php echo e($gateway->name); ?>"
                                                                            <?php if($payment->payment_gateway === $gateway->name): ?> selected <?php endif; ?>>
                                                                            <?php echo e($gateway->name); ?>

                                                                        </option>
                                                                        <?php if($gateway->methods): ?>
                                                                        <template>
                                                                            <div>
                                                                                <label for="payment_method_<?php echo e($gateway->id); ?>">
                                                                                    <?php echo e($gateway->name); ?> Methods
                                                                                </label>
                                                                                <select name="payment_method_<?php echo e($gateway->id); ?>" id="payment_method_<?php echo e($gateway->id); ?>"
                                                                                    class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                                    x-show="selectedGateway === '<?php echo e($gateway->name); ?>'"
                                                                                    x-model="selectedMethod">
                                                                                    <option value="" disabled>Select Method</option>
                                                                                    <?php $__currentLoopData = $gateway->methods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                                    <option value="<?php echo e($method->id); ?>"
                                                                                        <?php if($payment->payment_method_id === $method->id): ?> selected <?php endif; ?>>
                                                                                        <?php echo e($method->english_name); ?>

                                                                                    </option>
                                                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                                            </div>
                                                                        </template>
                                                                        <?php endif; ?>
                                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                                    </select>
                                                                </div>

                                                                <?php $__currentLoopData = $paymentGateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <?php if($gateway->methods): ?>
                                                                <template x-if="selectedGateway === '<?php echo e($gateway->name); ?>'">
                                                                    <div x-cloak>
                                                                        <label for="payment_method_id" class="block text-sm font-medium text-gray-700"><?php echo e($gateway->name); ?> Methods</label>
                                                                        <select name="payment_method_id" id="payment_method"
                                                                            class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                            <option value="" disabled>Select Method</option>
                                                                            <?php $__currentLoopData = $gateway->methods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                            <option value="<?php echo e($method->id); ?>"
                                                                                <?php if($payment->payment_method_id === $method->id): ?> selected <?php endif; ?>>
                                                                                <?php echo e($method->english_name); ?>

                                                                            </option>
                                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                                        </select>
                                                                    </div>
                                                                </template>
                                                                <?php endif; ?>
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                        <!-- Language -->
                                                        <div class="mb-4">
                                                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Invoice Language</label>
                                                            <div x-data="{ language: '<?php echo e($payment->language ?? 'EN'); ?>' }" class="inline-flex rounded-lg border border-gray-300 p-1 bg-gray-100">
                                                                <input type="hidden" name="language" :value="language">

                                                                <button type="button"
                                                                    @click="language = 'EN'"
                                                                    :class="language === 'EN' 
                                                                        ? 'bg-white text-gray-900 shadow-sm' 
                                                                        : 'text-gray-500 hover:text-gray-700'"
                                                                    class="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all">
                                                                    <span>🇬🇧</span> English
                                                                </button>

                                                                <button type="button"
                                                                    @click="language = 'ARB'"
                                                                    :class="language === 'ARB' 
                                                                        ? 'bg-white text-gray-900 shadow-sm' 
                                                                        : 'text-gray-500 hover:text-gray-700'"
                                                                    class="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all">
                                                                    <span>🇸🇦</span> العربية
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="flex justify-between space-x-4">
                                                            <button type="button" @click="editPaymentLink = false"
                                                                class="rounded-full shadow-md border border-gray-200 hover:bg-gray-400 px-4 py-2">Cancel</button>
                                                            <button type="submit"
                                                                class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (isset($component)) { $__componentOriginal41032d87daf360242eb88dbda6c75ed1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41032d87daf360242eb88dbda6c75ed1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pagination','data' => ['data' => $payments]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($payments)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41032d87daf360242eb88dbda6c75ed1)): ?>
<?php $attributes = $__attributesOriginal41032d87daf360242eb88dbda6c75ed1; ?>
<?php unset($__attributesOriginal41032d87daf360242eb88dbda6c75ed1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41032d87daf360242eb88dbda6c75ed1)): ?>
<?php $component = $__componentOriginal41032d87daf360242eb88dbda6c75ed1; ?>
<?php unset($__componentOriginal41032d87daf360242eb88dbda6c75ed1); ?>
<?php endif; ?>
    </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const dateFromInput = document.getElementById('date_from');
                    const dateToInput = document.getElementById('date_to');
                    const dateFilterForm = document.getElementById('date-filter-form');
                    const dateFrom = dateFromInput ? dateFromInput.value : '';
                    const dateTo = dateToInput ? dateToInput.value : '';

                    const dateRangeInput = document.getElementById('payment-date-range');
                    if (dateRangeInput) {
                        const fp = flatpickr("#payment-date-range", {
                            mode: "range",
                            dateFormat: "Y-m-d",
                            defaultDate: (dateFrom && dateTo) ? [dateFrom, dateTo] : null,
                            onChange: function(selectedDates, dateStr, instance) {
                                if (selectedDates.length === 2) {
                                    dateFromInput.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                                    dateToInput.value = instance.formatDate(selectedDates[1], 'Y-m-d');

                                    // Auto-submit the form when date range is selected
                                    setTimeout(() => {
                                        dateFilterForm.submit();
                                    }, 100);
                                } else if (selectedDates.length === 0) {
                                    // Clear dates and submit when cleared
                                    dateFromInput.value = '';
                                    dateToInput.value = '';
                                    setTimeout(() => {
                                        dateFilterForm.submit();
                                    }, 100);
                                }
                            }
                        });
                    }
                });

                function copyToClipboard(text) {
                    navigator.clipboard.writeText(text).then(function() {
                        const toast = document.createElement('div');
                        toast.textContent = 'Link copied to clipboard!';
                        toast.className =
                            'alert alert-success fixed mt-5 top-1 right-4 bg-green-500 text-white p-4 rounded shadow-lg';
                        toast.innerHTML = `
                            <span class="mr-4">${toast.textContent}</span>
                            <button type="button" class="text-white font-bold" aria-label="Close" onclick="this.parentElement.remove()">
                                &times;
                            </button>
                        `;

                        document.body.appendChild(toast);

                        setTimeout(() => {
                            toast.style.opacity = '0';
                            setTimeout(() => {
                                toast.remove();
                            }, 300);
                        }, 2500);
                    }).catch(function(err) {
                        console.error('Copy failed:', err);
                        alert('Could not copy. Please try again.');
                    });
                }
            </script>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/payment/link/index.blade.php ENDPATH**/ ?>