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
    <!-- Breadcrumb -->
    <nav class="flex space-x-2 text-sm text-gray-500 mb-6">
        <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-blue-600 transition-colors">Dashboard</a>
        <span class="text-gray-400">/</span>
        <span class="text-gray-700">Reports</span>
        <span class="text-gray-400">/</span>
        <span class="text-gray-900 font-medium">Creditors</span>
    </nav>

    <!-- Page Title -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Creditors Report</h1>
        <p class="mt-2 text-gray-600">Manage and track amounts owed to creditors</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Outstanding</p>
                    <p class="text-2xl font-bold text-gray-900">
                        KD<?php echo e(number_format(collect($creditorsSummary)->sum('balance'), 2)); ?>

                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Active Creditors</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo e(count($creditorsSummary)); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Current Account</p>
                    <p class="text-lg font-bold text-gray-900"><?php echo e($accountForReport->name); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Filters</h3>
        </div>
        <div class="p-6">
            <form method="GET" action="<?php echo e(route('reports.creditors')); ?>" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="account_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Creditor Account
                        </label>
                        <select id="account_id" name="account_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <?php $__currentLoopData = $childOfCreditors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $childAccount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($childAccount->id); ?>" <?php echo e(request('account_id') == $childAccount->id ? 'selected' : ''); ?>>
                                <?php echo e($childAccount->name); ?>

                            </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Start Date
                        </label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo e($startDate); ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">
                            End Date
                        </label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo e($endDate); ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>

                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"></path>
                            </svg>
                            Apply Filters
                        </button>
                    </div>
                </div>

                <!-- Group by Supplier Option -->
                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <input type="checkbox" id="group_by_supplier" name="group_by_supplier" value="1"
                            <?php echo e(request('group_by_supplier') ? 'checked' : ''); ?>

                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="group_by_supplier" class="text-sm font-medium text-gray-700">
                            Group by Supplier
                        </label>
                        <div class="text-xs text-gray-500">
                            Group journal entries by supplier to see individual amounts owed to each supplier
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if(count($creditorsSummary) > 0): ?>
    <!-- Creditors Summary -->
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Creditors Summary</h3>
            <p class="text-sm text-gray-600">Overview of all creditors with outstanding balances</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Creditor Name
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Outstanding Amount
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Transactions
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Priority
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $__currentLoopData = $creditorsSummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $creditor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo e($creditor['name']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="text-lg font-bold text-red-600">
                                KD<?php echo e(number_format($creditor['balance'], 2)); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?php echo e($creditor['entries_count']); ?> entries
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <?php if($creditor['balance'] > 10000): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                High
                            </span>
                            <?php elseif($creditor['balance'] > 5000): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                Medium
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Low
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <a href="<?php echo e(route('reports.creditors', ['account_id' => $creditor['id']])); ?>"
                                class="text-blue-600 hover:text-blue-800 font-medium text-sm transition-colors">
                                View Details
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if(request('group_by_supplier') && count($supplierGroups ?? []) > 0): ?>
    <!-- Supplier Groups Section -->
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Supplier Breakdown</h3>
                    <p class="text-sm text-gray-600">Amount owed to <?php echo e($accountForReport->name); ?> grouped by supplier</p>
                </div>
                <div class="text-right">
                    <span class="text-sm text-gray-500">Total Suppliers: </span>
                    <span class="font-bold text-gray-900"><?php echo e(count($supplierGroups)); ?></span>
                </div>
                <a href="<?php echo e(route('reports.creditors.pdf', request()->all())); ?>"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download PDF
                </a>
            </div>
        </div>

        <div class="space-y-6 p-6">
            <?php $__currentLoopData = $supplierGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <!-- Supplier Header -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900"><?php echo e($group['supplier_name']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo e($group['entries_count']); ?> transaction(s)</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <div class="text-2xl font-bold text-red-600">
                                    KD<?php echo e(number_format($group['balance'], 2)); ?>

                                </div>
                                <div class="text-sm text-gray-500">
                                    Amount Owed
                                </div>
                            </div>
                            <a href="<?php echo e(route('reports.creditors.pdf', array_merge(request()->all(), ['supplier_name' => urlencode($group['supplier_name'])]))); ?>"
                                class="inline-flex items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition-colors">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                PDF
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Supplier Entries -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Debit</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php $__currentLoopData = $group['entries']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gIndex => $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <!-- Main Row -->
                            <tr class="hover:bg-gray-50 transition-colors <?php echo e($entry->task ? 'cursor-pointer' : ''); ?>"
                                <?php if($entry->task): ?> onclick="toggleSupplierTaskDetails('<?php echo e($group['supplier_id']); ?>', <?php echo e($gIndex); ?>)" <?php endif; ?>>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo e(\Carbon\Carbon::parse($entry->transaction_date)->format('M d, Y')); ?>

                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900"><?php echo e($entry->description); ?></div>
                                    <?php if($entry->name): ?>
                                    <div class="text-xs text-gray-500"><?php echo e($entry->name); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if($entry->task): ?>
                                    <div class="flex items-center space-x-2">
                                        <div class="text-sm">
                                            <div class="font-medium text-gray-900"><?php echo e($entry->task->title ?? 'Task #' . $entry->task->id); ?></div>
                                            <?php if($entry->task->client_name): ?>
                                            <div class="text-xs text-blue-600"><?php echo e($entry->task->client_name); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Click for details
                                        </span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-400">No task linked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                    <span class="font-medium text-gray-900">KD<?php echo e(number_format($entry->credit, 2)); ?></span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                    <?php if($entry->debit > 0): ?>
                                    <span class="font-medium text-gray-900">KD<?php echo e(number_format($entry->debit, 2)); ?></span>
                                    <?php else: ?>
                                    <span class="text-gray-400">KD0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center text-sm">
                                    <?php if($entry->task): ?>
                                    <button class="text-blue-600 hover:text-blue-800 transition-colors"
                                        onclick="event.stopPropagation(); toggleSupplierTaskDetails('<?php echo e($group['supplier_id']); ?>', <?php echo e($gIndex); ?>)">
                                        <svg id="supplier-arrow-<?php echo e($group['supplier_id']); ?>-<?php echo e($gIndex); ?>"
                                            class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Collapsible Task Details Row -->
                            <?php if($entry->task): ?>
                            <tr id="supplier-task-details-<?php echo e($group['supplier_id']); ?>-<?php echo e($gIndex); ?>" class="hidden bg-gray-50">
                                <td colspan="6" class="px-4 py-4">
                                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <!-- Task Information -->
                                            <div>
                                                <h5 class="text-md font-semibold text-gray-900 mb-3 flex items-center">
                                                    <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                    </svg>
                                                    Task Information
                                                </h5>

                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Task ID</label>
                                                        <p class="text-sm text-gray-900"><?php echo e($entry->task->id); ?></p>
                                                    </div>

                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Reference</label>
                                                        <p class="text-sm text-gray-900"><?php echo e($entry->task->reference); ?></p>
                                                    </div>

                                                    <?php if($entry->task->status): ?>
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Status</label>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium 
                                                                    <?php switch($entry->task->status):
                                                                        case ('issued'): ?>
                                                                            bg-green-100 text-green-800
                                                                            <?php break; ?>
                                                                        <?php case ('confirmed'): ?>
                                                                            bg-blue-100 text-blue-800
                                                                            <?php break; ?>
                                                                        <?php case ('reissued'): ?>
                                                                            bg-yellow-100 text-yellow-800
                                                                            <?php break; ?>
                                                                        <?php case ('refund'): ?>
                                                                            bg-orange-100 text-orange-800
                                                                            <?php break; ?>
                                                                        <?php case ('void'): ?>
                                                                            bg-red-100 text-red-800
                                                                            <?php break; ?>
                                                                        <?php case ('emd'): ?>
                                                                            bg-purple-100 text-purple-800
                                                                            <?php break; ?>
                                                                        <?php default: ?>
                                                                            bg-gray-100 text-gray-800
                                                                    <?php endswitch; ?>">
                                                            <?php echo e(ucfirst(str_replace('_', ' ', $entry->task->status))); ?>

                                                        </span>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Client & Financial Information -->
                                            <div>
                                                <h5 class="text-md font-semibold text-gray-900 mb-3 flex items-center">
                                                    <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                    </svg>
                                                    Client & Details
                                                </h5>

                                                <div class="space-y-2">
                                                    <?php if($entry->task->client_name): ?>
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Client Name</label>
                                                        <p class="text-sm text-gray-900"><?php echo e($entry->task->client_name); ?></p>
                                                    </div>
                                                    <?php endif; ?>

                                                    <?php if($entry->task->amount): ?>
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Task Amount</label>
                                                        <p class="text-sm font-semibold text-green-600">KD<?php echo e(number_format($entry->task->amount, 2)); ?></p>
                                                    </div>
                                                    <?php endif; ?>

                                                    <?php if($entry->task->created_at): ?>
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-700">Created Date</label>
                                                        <p class="text-sm text-gray-900"><?php echo e(\Carbon\Carbon::parse($entry->task->created_at)->format('M d, Y H:i')); ?></p>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-right text-sm font-medium text-gray-900">
                                    Supplier Total:
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-red-600">
                                    KD<?php echo e(number_format($group['total_credit'], 2)); ?>

                                </td>
                                <td class="px-4 py-2 text-right text-sm font-bold text-gray-900">
                                    KD<?php echo e(number_format($group['total_debit'], 2)); ?>

                                </td>
                                <td class="px-4 py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detailed Journal Entries -->
    <?php if(!request('group_by_supplier')): ?>
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">
                        Journal Entries - <?php echo e($accountForReport->name); ?>

                    </h3>
                    <p class="text-sm text-gray-600">
                        Detailed transaction history
                        <?php if($accountForReport->final_balance > 0): ?>
                        | <span class="font-medium text-red-600">Outstanding: KD<?php echo e(number_format($accountForReport->final_balance, 2)); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if(count($journalEntries) > 0): ?>
                    <div class="text-right">
                        <span class="text-sm text-gray-500">Total Entries: </span>
                        <span class="font-bold text-gray-900"><?php echo e(count($journalEntries)); ?></span>
                    </div>
                    <a href="<?php echo e(route('reports.creditors.pdf', request()->all())); ?>"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download PDF
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if(count($journalEntries) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Task
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Debit
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Credit
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Running Balance
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $__currentLoopData = $journalEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <!-- Main Row -->
                    <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="toggleTaskDetails(<?php echo e($index); ?>)">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo e(\Carbon\Carbon::parse($entry->transaction_date)->format('M d, Y')); ?>

                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900"><?php echo e($entry->description); ?></div>
                            <?php if($entry->name): ?>
                            <div class="text-xs text-gray-500"><?php echo e($entry->name); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if($entry->task): ?>
                            <div class="flex items-center space-x-2">
                                <div class="text-sm">
                                    <div class="font-medium text-gray-900"><?php echo e($entry->task->title ?? 'Task #' . $entry->task->id); ?></div>
                                    <?php if($entry->task->client_name): ?>
                                    <div class="text-xs text-blue-600"><?php echo e($entry->task->client_name); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Click for details
                                </span>
                            </div>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">No task linked</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <?php if($entry->debit > 0): ?>
                            <span class="font-medium text-gray-900">KD<?php echo e(number_format($entry->debit, 2)); ?></span>
                            <?php else: ?>
                            <span class="text-gray-900">KD0.00</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <?php if($entry->credit > 0): ?>
                            <span class="font-medium text-gray-900">KD<?php echo e(number_format($entry->credit, 2)); ?></span>
                            <?php else: ?>
                            <span class="text-gray-900">KD0.00</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <span class="font-bold <?php echo e($entry->balance > 0 ? 'text-red-600' : 'text-green-600'); ?>">
                                KD<?php echo e(number_format($entry->balance, 2)); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                            <?php if($entry->task): ?>
                            <button class="text-blue-600 hover:text-blue-800 transition-colors" onclick="event.stopPropagation(); toggleTaskDetails(<?php echo e($index); ?>)">
                                <svg id="arrow-<?php echo e($index); ?>" class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Collapsible Task Details Row -->
                    <?php if($entry->task): ?>
                    <tr id="task-details-<?php echo e($index); ?>" class="hidden bg-gray-50">
                        <td colspan="7" class="px-6 py-4">
                            <div class="bg-white rounded-lg border border-gray-200 p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Task Information -->
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                            </svg>
                                            Task Information
                                        </h4>

                                        <div class="space-y-3 grid md:grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Task ID</label>
                                                <p class="text-sm text-gray-900"><?php echo e($entry->task->id); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Reference</label>
                                                <p class="text-sm text-gray-900"><?php echo e($entry->task->reference); ?></p>
                                            </div>

                                            <?php if($entry->task->description): ?>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                                <p class="text-sm text-gray-900"><?php echo e($entry->task->description); ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if($entry->task->status): ?>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                            <?php switch($entry->task->status):
                                                                case ('issued'): ?>
                                                                    bg-green-100 text-green-800
                                                                    <?php break; ?>
                                                                <?php case ('confirmed'): ?>
                                                                    bg-blue-100 text-blue-800
                                                                    <?php break; ?>
                                                                <?php case ('reissued'): ?>
                                                                    bg-yellow-100 text-yellow-800
                                                                    <?php break; ?>
                                                                <?php case ('refund'): ?>
                                                                    bg-orange-100 text-orange-800
                                                                    <?php break; ?>
                                                                <?php case ('void'): ?>
                                                                    bg-red-100 text-red-800
                                                                    <?php break; ?>
                                                                <?php case ('emd'): ?>
                                                                    bg-purple-100 text-purple-800
                                                                    <?php break; ?>
                                                                <?php default: ?>
                                                                    bg-gray-100 text-gray-800
                                                            <?php endswitch; ?>">
                                                    <?php echo e(ucfirst(str_replace('_', ' ', $entry->task->status))); ?>

                                                </span>
                                            </div>
                                            <?php endif; ?>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Supplier</label>
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-sm text-gray-900"><?php echo e($entry->task->supplier->name); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Client & Financial Information -->
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                            Client & Details
                                        </h4>

                                        <div class="space-y-3">
                                            <?php if($entry->task->client_name): ?>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Client Name</label>
                                                <p class="text-sm text-gray-900"><?php echo e($entry->task->client_name); ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if($entry->task->amount): ?>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Task Amount</label>
                                                <p class="text-sm font-semibold text-green-600">KD<?php echo e(number_format($entry->task->amount, 2)); ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if($entry->task->created_at): ?>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Created Date</label>
                                                <p class="text-sm text-gray-900"><?php echo e(\Carbon\Carbon::parse($entry->task->created_at)->format('M d, Y H:i')); ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if($entry->task->due_date): ?>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Due Date</label>
                                                <p class="text-sm text-gray-900"><?php echo e(\Carbon\Carbon::parse($entry->task->due_date)->format('M d, Y')); ?></p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Additional Details -->
                                <?php if($entry->task->notes || $entry->task->priority): ?>
                                <div class="mt-6 pt-6 border-t border-gray-200">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <?php if($entry->task->priority): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Priority</label>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                        <?php echo e($entry->task->priority == 'high' ? 'bg-red-100 text-red-800' : 
                                                           ($entry->task->priority == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800')); ?>">
                                                <?php echo e(ucfirst($entry->task->priority)); ?> Priority
                                            </span>
                                        </div>
                                        <?php endif; ?>

                                        <?php if($entry->task->notes): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                                            <p class="text-sm text-gray-900"><?php echo e($entry->task->notes); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="6" class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                            Final Outstanding Balance:
                        </td>
                        <td class="px-6 py-3 text-right text-lg font-bold <?php echo e($accountForReport->final_balance > 0 ? 'text-red-600' : 'text-green-600'); ?>">
                            KD<?php echo e(number_format($accountForReport->final_balance, 2)); ?>

                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="px-6 py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No journal entries found</h3>
            <p class="mt-1 text-sm text-gray-500">
                No transactions found for the selected creditor and date range.
            </p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <script>
        function toggleTaskDetails(index) {
            const detailsRow = document.getElementById('task-details-' + index);
            const arrow = document.getElementById('arrow-' + index);

            if (detailsRow.classList.contains('hidden')) {
                detailsRow.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                detailsRow.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }

        function toggleSupplierTaskDetails(supplierId, index) {
            const detailsRow = document.getElementById('supplier-task-details-' + supplierId + '-' + index);
            const arrow = document.getElementById('supplier-arrow-' + supplierId + '-' + index);

            if (detailsRow.classList.contains('hidden')) {
                detailsRow.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                detailsRow.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }

        // Add click event listeners to all rows with tasks
        document.addEventListener('DOMContentLoaded', function() {
            // Make rows with tasks more visually distinct
            const taskRows = document.querySelectorAll('tr[onclick*="toggleTaskDetails"]');
            taskRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8fafc';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });

            // Make supplier rows with tasks more visually distinct
            const supplierTaskRows = document.querySelectorAll('tr[onclick*="toggleSupplierTaskDetails"]');
            supplierTaskRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8fafc';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/creditors.blade.php ENDPATH**/ ?>