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
    <div class="container mx-auto p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100">Tasks Report</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    <?php if($dateFrom && $dateTo): ?>
                        Date Range: <span class="font-semibold"><?php echo e(\Carbon\Carbon::parse($dateFrom)->format('d-m-Y')); ?> – <?php echo e(\Carbon\Carbon::parse($dateTo)->format('d-m-Y')); ?></span>
                    <?php else: ?>
                        <span class="font-semibold">All Time</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-xs font-medium uppercase tracking-wide">Total Tasks</p>
                        <p class="text-3xl font-bold mt-1"><?php echo e(number_format($totalTasks)); ?></p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-300 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-emerald-100 text-xs font-medium uppercase tracking-wide">Total Debit</p>
                        <p class="text-3xl font-bold mt-1"><?php echo e(number_format($totalDebit, 3)); ?> <span class="text-base font-semibold">KWD</span></p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-rose-500 to-rose-300 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-rose-100 text-xs font-medium uppercase tracking-wide">Total Credit</p>
                        <p class="text-3xl font-bold mt-1"><?php echo e(number_format($totalCredit, 3)); ?> <span class="text-base font-semibold">KWD</span></p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 mb-6">
            <form method="POST" action="<?php echo e(route('reports.tasks')); ?>" id="filterForm">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-2">Quick Date Filter</label>
                    <div class="flex flex-wrap gap-2">
                        <?php $__currentLoopData = ['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year', 'january' => 'January', 'february' => 'February', 'march' => 'March', 'april' => 'April', 'may' => 'May', 'june' => 'June', 'july' => 'July', 'august' => 'August', 'september' => 'September', 'october' => 'October', 'november' => 'November', 'december' => 'December']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $preset => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button type="button" onclick="setDatePreset('<?php echo e($preset); ?>')"
                                class="preset-btn px-3 py-1.5 text-xs rounded-md transition <?php echo e($datePreset === $preset ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'); ?>">
                                <?php echo e($label); ?>

                            </button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <input type="hidden" name="date_preset" id="date_preset" value="<?php echo e($datePreset ?? ''); ?>">
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Date Range</label>
                        <input type="text" id="date-range" 
                            class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 cursor-pointer" 
                            placeholder="Select date range" autocomplete="off" readonly />
                        <input type="hidden" name="date_from" id="date_from" value="<?php echo e($dateFrom ?? ''); ?>">
                        <input type="hidden" name="date_to" id="date_to" value="<?php echo e($dateTo ?? ''); ?>">
                    </div>

                    <?php if (isset($component)) { $__componentOriginalca22bd07186d77d4a177532dc60413c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalca22bd07186d77d4a177532dc60413c3 = $attributes; } ?>
<?php $component = App\View\Components\MultiPicker::resolve(['label' => 'Suppliers','name' => 'supplier_ids','items' => $suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->toArray(),'preselected' => collect(request('supplier_ids', []))->map(fn($v) => (int)$v)->all(),'allLabel' => 'All Suppliers','placeholder' => 'Search suppliers...'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('multi-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\MultiPicker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'flex-1 min-w-[180px]']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $attributes = $__attributesOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__attributesOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $component = $__componentOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__componentOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>

                    <?php if (isset($component)) { $__componentOriginalca22bd07186d77d4a177532dc60413c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalca22bd07186d77d4a177532dc60413c3 = $attributes; } ?>
<?php $component = App\View\Components\MultiPicker::resolve(['label' => 'Statuses','name' => 'statuses','items' => collect($availableStatuses)->map(fn($s) => ['id' => $s, 'name' => $s === 'payment_voucher' ? 'Payment Voucher' : ucfirst($s)])->toArray(),'preselected' => $statuses,'allLabel' => 'All Statuses','placeholder' => 'Search statuses...'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('multi-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\MultiPicker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'flex-1 min-w-[180px]']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $attributes = $__attributesOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__attributesOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $component = $__componentOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__componentOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>

                    <?php if (isset($component)) { $__componentOriginalca22bd07186d77d4a177532dc60413c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalca22bd07186d77d4a177532dc60413c3 = $attributes; } ?>
<?php $component = App\View\Components\MultiPicker::resolve(['label' => 'Issued By','name' => 'issued_by','items' => collect($availableIssuedBy)->map(fn($i) => ['id' => $i, 'name' => $i])->toArray(),'preselected' => $issuedBy,'allLabel' => 'All Issuers','placeholder' => 'Search issuers...'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('multi-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\MultiPicker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'flex-1 min-w-[180px]']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $attributes = $__attributesOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__attributesOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $component = $__componentOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__componentOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>

                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            Filter
                        </button>
                        <a href="<?php echo e(route('reports.tasks')); ?>" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </a>
                        <button type="submit" formaction="<?php echo e(route('reports.tasks.pdf')); ?>" formtarget="_blank" 
                            class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-purple-600 hover:bg-purple-700 text-white transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            PDF
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Original Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Passenger Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Supplier</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Debit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Credit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php $runningBalance = 0; ?>
                        <?php $__empty_1 = true; $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php $runningBalance = $runningBalance + $item->debit - $item->credit; ?>
                        <tr class="bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors <?php echo e($item->type === 'transaction' ? 'bg-purple-50 dark:bg-purple-900/20' : ''); ?>">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100"><?php echo e($item->reference); ?></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><?php echo e($item->original_reference ?? '—'); ?></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><?php echo e($item->passenger_name ?? '—'); ?></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><?php echo e($item->supplier_name ?? '—'); ?></td>
                            <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">
                                <?php echo e($item->date ? \Carbon\Carbon::parse($item->date)->format('d-m-Y') : '—'); ?>

                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php
                                    $statusColors = [
                                        'issued' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                        'reissued' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                        'void' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                        'refund' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                        'confirmed' => 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
                                        'void' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                        'payment_voucher' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                    ];
                                    $statusColor = $statusColors[$item->status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($statusColor); ?>">
                                    <?php echo e($item->status === 'payment_voucher' ? 'Payment' : ucfirst($item->status)); ?>

                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold <?php echo e($item->debit > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400'); ?>">
                                <?php echo e($item->debit > 0 ? number_format($item->debit, 3) : '—'); ?>

                            </td>
                            <td class="px-4 py-3 text-right font-semibold <?php echo e($item->credit > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'); ?>">
                                <?php echo e($item->credit > 0 ? number_format($item->credit, 3) : '—'); ?>

                            </td>
                            <td class="px-4 py-3 text-right font-semibold <?php echo e($runningBalance > 0 ? 'text-rose-600 dark:text-rose-400' : ($runningBalance < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400')); ?>">
                                <?php echo e(number_format($runningBalance, 3)); ?>

                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-500">
                                    <svg class="w-12 h-12 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <p class="text-lg font-medium">No tasks found</p>
                                    <p class="text-sm">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <?php if (isset($component)) { $__componentOriginal41032d87daf360242eb88dbda6c75ed1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41032d87daf360242eb88dbda6c75ed1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pagination','data' => ['data' => $tasks]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tasks)]); ?>
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
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script>
        function setDatePreset(preset) {
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            document.getElementById('date_preset').value = preset;
            document.getElementById('filterForm').submit();
        }

        function clearPreset() {
            document.getElementById('date_preset').value = '';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const fromDate = document.getElementById('date_from').value;
            const toDate = document.getElementById('date_to').value;
            
            flatpickr("#date-range", {
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: (fromDate && toDate) ? [fromDate, toDate] : null,
                showMonths: 1,
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        document.getElementById('date_from').value = instance.formatDate(selectedDates[0], "Y-m-d");
                        document.getElementById('date_to').value = instance.formatDate(selectedDates[1], "Y-m-d");
                        clearPreset();
                    }
                },
                onReady: function(selectedDates, dateStr, instance) {
                    if (fromDate && toDate) {
                        instance.element.value = fromDate + ' to ' + toDate;
                    }
                }
            });
        });
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/tasks.blade.php ENDPATH**/ ?>