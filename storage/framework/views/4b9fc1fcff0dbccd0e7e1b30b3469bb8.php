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

    <style>
        .supplier-details {
            text-transform: uppercase;
            display: flex;
            justify-content: space-between;
        }

        .supplier-details>div>div {
            width: 100%;
            margin: 0.5rem 0;
            padding: 0.5rem;
            border-radius: 5px;
        }

        .loading {
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
    </style>

    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li class="hover:underline">
                <a href="<?php echo e(route('suppliers.index')); ?>">Suppliers</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                <?php echo e($supplier->name); ?>

            </li>
        </ul>
    </div>

    <div class="flex flex-col gap-2">

        <div class="grid bg-gradient-to-r from-blue-600 to-gray-800 p-4 rounded-md shadow-md w-full">
            <div class="flex justify-between items-center gap-4 mb-4">
                <div class="flex items-center justify-center rounded-full bg-black/50 font-semibold text-white p-2">
                    <?php if (isset($component)) { $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72 = $attributes; } ?>
<?php $component = App\View\Components\ApplicationLogo::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('application-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ApplicationLogo::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['style' => 'width:32px;height:32px;']); ?>
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
                    <h3 class="ml-2"><?php echo e($supplier->name); ?></h3>
                </div>
                <div class="flex items-center justify-end mb-4">
                    <form method="GET" action="<?php echo e(route('suppliers.show', ['suppliersId' => $supplier->id])); ?>" class="flex flex-row items-end gap-2" id="task-filter-form">
                        <!-- Dropdown -->
                        <div class="flex flex-col justify-end">
                            <label class="text-xs font-semibold text-white mb-1">Filter By</label>
                            <select name="date_field" class="border rounded px-2 py-1 text-sm min-w-[150px]">
                                <option value="created_at" <?php echo e(request('date_field') == 'created_at' ? 'selected' : ''); ?>>Created Date</option>
                                <option value="supplier_pay_date" <?php echo e(request('date_field') == 'supplier_pay_date' ? 'selected' : ''); ?>>Issued Date</option>
                            </select>
                        </div>
                        <!-- Date Range -->
                        <div class="flex flex-col justify-end">
                            <label class="text-xs font-semibold text-white mb-1">Date Range</label>
                            <input type="text" id="task-date-range" class="border rounded px-2 py-1 text-sm min-w-[240px]" placeholder="Select date range" autocomplete="off" />
                            <input type="hidden" name="from_date" id="task_from_date" value="<?php echo e(request('from_date')); ?>">
                            <input type="hidden" name="to_date" id="task_to_date" value="<?php echo e(request('to_date')); ?>">
                        </div>
                        <!-- Buttons -->
                        <div class="flex flex-row items-end gap-1 pt-5">
                            <a href="<?php echo e(route('suppliers.show', ['suppliersId' => $supplier->id])); ?>" class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs hover:bg-gray-200 border border-gray-300 flex items-center">Clear</a>
                            <button type="submit" class="px-2 py-1 rounded bg-blue-600 text-white text-xs hover:bg-blue-700 border border-blue-700 flex items-center">Apply</button>
                            <button type="button" id="export-pdf-btn" class="px-2 py-1 rounded bg-red-600 text-white text-xs hover:bg-red-700 border border-red-700 flex items-center">Export PDF</button>
                            <button type="button" id="export-excel-btn" class="px-2 py-1 rounded bg-green-600 text-white text-xs hover:bg-green-700 border border-green-700 flex items-center">Export Excel</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php
            $dateField = request('date_field', 'created_at');
            $fromDate = request('from_date');
            $toDate = request('to_date');
            $filteredTasks = $supplier->tasks;

            // Apply date filter
            if ($fromDate && $toDate) {
            $filteredTasks = $filteredTasks->filter(function($task) use ($dateField, $fromDate, $toDate) {
            $date = $task[$dateField];
            if (!$date) return false;
            $date = \Carbon\Carbon::parse($date)->format('Y-m-d');
            return $date >= $fromDate && $date <= $toDate;
                });
                }

                // Only "issued" status
                $filteredTasks=$filteredTasks->filter(function($task) {
                return strtolower($task->status) === 'issued';
                });

                // Calculate totals for filtered "issued" tasks
                $totalDebit = $filteredTasks->flatMap->journalEntries->sum('debit');
                $totalCredit = $filteredTasks->flatMap->journalEntries->sum('credit');
                ?>
                <div class="flex gap-4 mb-2">
                    <div class="bg-green-100 text-green-800 px-4 py-2 rounded">Total Debit: <?php echo e($totalDebit); ?></div>
                    <div class="bg-red-100 text-red-800 px-4 py-2 rounded">Total Credit: <?php echo e($totalCredit); ?></div>
                    <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded">Balance: <?php echo e($totalDebit - $totalCredit); ?></div>
                </div>

                <div id="debit-credit" class="bg-white rounded-md shadow-md w-full overflow-x-auto">
                    <?php
                    // Determine supplier type based on tasks (assuming all tasks are same type for this supplier)
                    $firstTask = $supplier->tasks->first();
                    $filteredTasks = $filteredTasks->take(20);
                    $supplierType = $firstTask ? $firstTask->type : null;
                    ?>

                    <div class="min-w-max">
                        <?php if($supplierType === 'flight'): ?>
                        <div class="grid grid-cols-10 font-bold bg-gray-100 p-2 text-center rounded-t border-b border-gray-300 sticky top-0">
                            <div class="w-[120px]">Created Date</div>
                            <div class="w-[120px]">Task Ref</div>
                            <div class="w-[120px]">GDS Ref</div>
                            <div class="w-[140px]">Agent</div>
                            <div class="w-[110px]">Status</div>
                            <div class="w-[120px]">Issued Date</div>
                            <div class="w-[150px]">Passenger Name</div>
                            <div class="w-[90px]">Price</div>
                            <div class="w-[180px]">Departure</div>
                            <div class="w-[180px]">Arrival</div>
                        </div>
                        <?php elseif($supplierType === 'hotel'): ?>
                        <div class="grid grid-cols-10 font-bold bg-gray-100 p-2 text-center rounded-t border-b border-gray-300 sticky top-0 z-10">
                            <div class="w-[120px]">Created Date</div>
                            <div class="w-[120px]">Task Ref</div>
                            <div class="w-[140px]">Agent</div>
                            <div class="w-[110px]">Status</div>
                            <div class="w-[120px]">Issued Date</div>
                            <div class="w-[150px]">Info</div>
                            <div class="w-[50px]">Price</div>
                            <div class="w-[50px]">Debit</div>
                            <div class="w-[50px]">Credit</div>
                            <div class="w-[50px]">Balance</div>
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-12 font-bold bg-gray-100 p-2 text-center rounded-t border-b border-gray-300 sticky top-0 z-10">
                            <div class="w-[120px]">Created Date</div>
                            <div class="w-[120px]">Task Ref</div>
                            <div class="w-[140px]">Agent</div>
                            <div class="w-[110px]">Status</div>
                            <div class="w-[120px]">Issued Date</div>
                            <div class="w-[150px]">Passenger Name</div>
                            <div class="w-[90px]">Price</div>

                        </div>
                        <?php endif; ?>

                        <?php
                        $dateField = request('date_field', 'created_at');
                        $fromDate = request('from_date');
                        $toDate = request('to_date');
                        $filteredTasks = $supplier->tasks;

                        if ($fromDate && $toDate) {
                        $filteredTasks = $filteredTasks->filter(function($task) use ($dateField, $fromDate, $toDate) {
                        $date = $task[$dateField];
                        if (!$date) return false;
                        $date = \Carbon\Carbon::parse($date)->format('Y-m-d');
                        return $date >= $fromDate && $date <= $toDate;
                            });
                            }

                            // Sort by selected date field, newest first
                            $filteredTasks=$filteredTasks->sortByDesc(function($task) use ($dateField) {
                            return $task[$dateField] ? \Carbon\Carbon::parse($task[$dateField])->timestamp : 0;
                            });
                            ?>
                            <div style="max-height: 550px; overflow-y: auto;">

                                <?php $__empty_1 = true; $__currentLoopData = $filteredTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <?php if($supplierType === 'flight'): ?>
                                <div class="general-ledger-rows grid grid-cols-10 gap-2 p-2 text-center border-b">
                                    <div class="w-[120px]"><?php echo e($task->created_at ? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d') : '-'); ?></div>
                                    <div class="w-[120px]"><?php echo e($task->reference); ?></div>
                                    <div class="w-[120px]"><?php echo e($task->gds_reference ?? '-'); ?></div>
                                    <div class="w-[140px]"><?php echo e($task->agent ? $task->agent->name : '-'); ?></div>
                                    <div class="w-[110px]">
                                        <?php
                                        $status = strtolower($task->status);
                                        $statusColors = [
                                        'issued' => 'bg-green-100 text-green-700 border-green-400',
                                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-400',
                                        'cancelled' => 'bg-red-100 text-red-700 border-red-400',
                                        'confirmed' => 'bg-blue-100 text-blue-700 border-blue-400',
                                        'reissued' => 'bg-purple-100 text-purple-700 border-purple-400',
                                        'void' => 'bg-gray-200 text-gray-700 border-gray-400',
                                        'refund' => 'bg-pink-100 text-pink-700 border-pink-400',
                                        'emd' => 'bg-indigo-100 text-indigo-700 border-indigo-400',
                                        ];
                                        $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                        ?>
                                        <span class="inline-block px-2 py-1 rounded border font-bold text-xs <?php echo e($colorClass); ?>">
                                            <?php echo e(ucfirst($task->status)); ?>

                                        </span>
                                    </div>
                                    <div class="w-[120px]"><?php echo e($task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('d-m-Y') : '-'); ?></div>
                                    <div class="w-[150px]"><?php echo e($task->passenger_name ?? '-'); ?></div>
                                    <div class="w-[110px]"><?php echo e($task->price ?? '-'); ?></div>
                                    <div class="w-[180px]">
                                        <?php if($task->type === 'flight' && $task->flightDetails): ?>
                                        <strong>From:</strong> <?php echo e($task->flightDetails->airport_from ?? '-'); ?><br>
                                        <?php echo e(optional($task->flightDetails->departure_time)->format('d-m-Y H:i') ?? '-'); ?>

                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </div>
                                    <div class="w-[180px]">
                                        <?php if($task->type === 'flight' && $task->flightDetails): ?>
                                        <strong>To:</strong> <?php echo e($task->flightDetails->airport_to ?? '-'); ?><br>
                                        <?php echo e(optional($task->flightDetails->arrival_time)->format('d-m-Y H:i') ?? '-'); ?>

                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php elseif($supplierType === 'hotel'): ?>
                                <?php
                                $balance = 0;
                                $hotelTasks = $filteredTasks->take(20);
                                ?>
                                <?php $__currentLoopData = $hotelTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                $debit = $task->journalEntries->first()->debit ?? 0;
                                $credit = $task->journalEntries->first()->credit ?? 0;
                                $balance += $debit - $credit;
                                ?>
                                <div class="general-ledger-rows grid grid-cols-10 gap-2 p-2 text-center border-b">
                                    <div class="w-[120px]"><?php echo e($task->created_at ? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d') : '-'); ?></div>
                                    <div class="w-[120px]"><?php echo e($task->reference); ?></div>
                                    <div class="w-[140px]"><?php echo e($task->agent ? $task->agent->name : '-'); ?></div>
                                    <div class="w-[110px]">
                                        <?php
                                        $status = strtolower($task->status);
                                        $statusColors = [
                                        'issued' => 'bg-green-100 text-green-700 border-green-400',
                                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-400',
                                        'cancelled' => 'bg-red-100 text-red-700 border-red-400',
                                        'confirmed' => 'bg-blue-100 text-blue-700 border-blue-400',
                                        'reissued' => 'bg-purple-100 text-purple-700 border-purple-400',
                                        'void' => 'bg-gray-200 text-gray-700 border-gray-400',
                                        'refund' => 'bg-pink-100 text-pink-700 border-pink-400',
                                        'emd' => 'bg-indigo-100 text-indigo-700 border-indigo-400',
                                        ];
                                        $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                        ?>
                                        <span class="inline-block px-2 py-1 rounded border font-bold text-xs <?php echo e($colorClass); ?>">
                                            <?php echo e(ucfirst($task->status)); ?>

                                        </span>
                                    </div>
                                    <div class="w-[120px]"><?php echo e($task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-'); ?></div>
                                    <div class="w-[150px]"><?php echo e($task->passenger_name ?? '-'); ?> <br>
                                        <?php echo e($task->hotelDetails->hotel->name ?? '-'); ?><br>
                                        <?php echo e($task->hotelDetails->check_in ?? '-'); ?> to <?php echo e($task->hotelDetails->check_out ?? '-'); ?>

                                    </div>
                                    <div class="w-[50px]"><?php echo e($task->price ?? '-'); ?></div>
                                    <div class="w-[50px]"><?php echo e($debit ?: '-'); ?></div>
                                    <div class="w-[50px]"><?php echo e($credit ?: '-'); ?></div>
                                    <div class="w-[50px]"><?php echo e($balance); ?></div>
                                </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php else: ?>
                                <div class="general-ledger-rows grid grid-cols-7 gap-2 p-2 text-center border-b">
                                    <div class="w-[120px]"><?php echo e($task->created_at ? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d') : '-'); ?></div>
                                    <div class="w-[120px]"><?php echo e($task->reference); ?></div>
                                    <div class="w-[140px]"><?php echo e($task->agent ? $task->agent->name : '-'); ?></div>
                                    <div class="w-[110px]">
                                        <?php
                                        $status = strtolower($task->status);
                                        $statusColors = [
                                        'issued' => 'bg-green-100 text-green-700 border-green-400',
                                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-400',
                                        'cancelled' => 'bg-red-100 text-red-700 border-red-400',
                                        'confirmed' => 'bg-blue-100 text-blue-700 border-blue-400',
                                        'reissued' => 'bg-purple-100 text-purple-700 border-purple-400',
                                        'void' => 'bg-gray-200 text-gray-700 border-gray-400',
                                        'refund' => 'bg-pink-100 text-pink-700 border-pink-400',
                                        'emd' => 'bg-indigo-100 text-indigo-700 border-indigo-400',
                                        ];
                                        $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                                        ?>
                                        <span class="inline-block px-2 py-1 rounded border font-bold text-xs <?php echo e($colorClass); ?>">
                                            <?php echo e(ucfirst($task->status)); ?>

                                        </span>
                                    </div>
                                    <div class="w-[120px]"><?php echo e($task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '-'); ?></div>
                                    <div class="w-[150px]"><?php echo e($task->passenger_name ?? '-'); ?></div>
                                    <div class="w-[110px]"><?php echo e($task->price ?? '-'); ?></div>

                                </div>
                                <?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <div class="general-ledger-rows grid grid-cols-12 gap-2 p-2 text-center text-gray-500">
                                    <div colspan="10">No entries found for selected dates.</div>
                                </div>
                                <?php endif; ?>
                            </div>
                    </div>
                </div>
        </div>
    </div>

    <div class="space-y-6 mt-6">
        <div class="bg-white rounded-md shadow-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center">Supplier Details</h2>
                <div class="flex flex-wrap gap-2">
                    <?php if($supplier->has_flight): ?>
                    <span class="px-2 py-1 text-xs bg-sky-100 text-sky-700 rounded-full border border-sky-300 flex items-center gap-1">
                        <i class="fa-solid fa-plane"></i> Flight
                    </span>
                    <?php endif; ?>
                    <?php if($supplier->has_hotel): ?>
                    <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-700 rounded-full border border-yellow-300 flex items-center gap-1">
                        <i class="fa-solid fa-bed"></i> Hotel
                    </span>
                    <?php endif; ?>
                    <?php if($supplier->has_visa): ?>
                    <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full border border-green-300 flex items-center gap-1">
                        <i class="fa-solid fa-passport"></i> Visa
                    </span>
                    <?php endif; ?>
                    <?php if($supplier->has_insurance): ?>
                    <span class="px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded-full border border-purple-300 flex items-center gap-1">
                        <i class="fa-solid fa-shield-heart"></i> Insurance
                    </span>
                    <?php endif; ?>
                    <?php if($supplier->has_car): ?>
                    <span class="px-2 py-1 text-xs bg-orange-100 text-orange-700 rounded-full border border-orange-300 flex items-center gap-1">
                        <i class="fa-solid fa-car"></i> Car
                    </span>
                    <?php endif; ?>
                    <?php if($supplier->has_tour): ?>
                    <span class="px-2 py-1 text-xs bg-pink-100 text-pink-700 rounded-full border border-pink-300 flex items-center gap-1">
                        <i class="fa-solid fa-map-location-dot"></i> Tour
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="grid md:grid-cols-2 gap-6 text-sm text-gray-700">
                <div class="space-y-2">
                    <p><i class="fa-regular fa-id-badge text-blue-500 w-5 inline-block"></i>
                        <strong>Name:</strong> <?php echo e($supplier->name ?? 'Not Set'); ?>

                    </p>
                    <p><i class="fa-solid fa-user-tie text-blue-500 w-5 inline-block"></i>
                        <strong>Contact Person:</strong> <?php echo e($supplier->contact_person ?? 'Not Set'); ?>

                    </p>
                    <p><i class="fa-regular fa-envelope text-blue-500 w-5 inline-block"></i>
                        <strong>Email:</strong> <?php echo e($supplier->email ?? 'Not Set'); ?>

                    </p>
                    <p><i class="fa-solid fa-phone text-blue-500 w-5 inline-block"></i>
                        <strong>Phone:</strong> <?php echo e($supplier->phone ?? 'Not Set'); ?>

                    </p>
                    <p><i class="fa-solid fa-map-marker-alt text-blue-500 w-5 inline-block"></i>
                        <strong>Address:</strong> <?php echo e($supplier->address ?? 'Not Set'); ?>

                    </p>
                    <p><i class="fa-solid fa-city text-blue-500 w-5 inline-block"></i>
                        <strong>City:</strong> <?php echo e($supplier->city ?? 'Not Set'); ?>

                    </p>
                </div>

                <div class="space-y-2">
                    <p><i class="fa-solid fa-location-dot text-blue-500 w-5 inline-block"></i>
                        <strong>State:</strong> <?php echo e($supplier->state ?? 'Not Set'); ?>

                    </p>
                    <p><i class="fa-solid fa-mail-bulk text-blue-500 w-5 inline-block"></i>
                        <strong>Postal Code:</strong> <?php echo e($supplier->postal_code ?? 'Not Set'); ?>

                    </p>
                    <p><i class="fa-solid fa-flag text-blue-500 w-5 inline-block"></i>
                        <strong>Country:</strong> <?php echo e($supplier->country->name ?? 'Not Set'); ?>

                    </p>
                    <p><i class="fa-solid fa-file-contract text-blue-500 w-5 inline-block"></i>
                        <strong>Payment Terms:</strong> <?php echo e($supplier->payment_terms ?? 'Not Set'); ?>

                    </p>
                    <p><i class="fa-solid fa-lock text-blue-500 w-5 inline-block"></i>
                        <strong>Auth Type:</strong> <?php echo e(ucfirst($supplier->auth_type)); ?>

                    </p>
                    <p><i class="fa-solid fa-clipboard-check text-blue-500 w-5 inline-block"></i>
                        <strong>Manual Supplier:</strong> <?php echo e($supplier->is_manual ? 'Yes' : 'No'); ?>

                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-coins text-blue-500"></i>
                    Auto Extra Surcharge
                </h2>
            </div>
            <?php if($supplierCompany && $supplierCompany->supplierSurcharges->count()): ?>
                <div class="overflow-hidden border border-gray-200 rounded-lg divide-y divide-gray-100">
                    <?php $__currentLoopData = $supplierCompany->supplierSurcharges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $surcharge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between px-4 py-3 hover:bg-blue-50 transition duration-150 ease-in-out">
                            <div class="flex items-center gap-3 mb-2 sm:mb-0">
                                <span class="inline-flex items-center justify-center bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full w-7 h-7">
                                    <?php echo e(strtoupper(substr($surcharge->label, 0, 2))); ?>

                                </span>
                                <div>
                                    <p class="text-gray-800 font-semibold"><?php echo e(ucwords(str_replace('_', ' ', $surcharge->label))); ?></p>
                                    <div class="flex flex-wrap gap-1 mt-1 text-xs">
                                        <span class="px-2 py-0.5 rounded-full border border-gray-300 bg-gray-50 text-gray-700">
                                            Mode: <strong class="text-blue-600"><?php echo e(ucfirst($surcharge->charge_mode)); ?></strong>
                                        </span>
                                        <?php
                                            $activeStatuses = collect([
                                                'issued' => $surcharge->is_issued,
                                                'refund' => $surcharge->is_refund,
                                                'reissued' => $surcharge->is_reissued,
                                                'void' => $surcharge->is_void,
                                                'confirmed' => $surcharge->is_confirmed,
                                            ])->filter();
                                        ?>
                                        <span class="px-2 py-0.5 rounded-full border border-gray-300 bg-gray-50 text-gray-700">
                                            Status:
                                            <?php if($activeStatuses->isNotEmpty()): ?>
                                                <strong class="text-green-700">
                                                    <?php echo e($activeStatuses->keys()->map(fn($s)=>ucfirst($s))->implode(', ')); ?>

                                                </strong>
                                            <?php else: ?>
                                                <strong class="text-gray-400">None</strong>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if($surcharge->charge_mode === 'reference' && $surcharge->references->count()): ?>
                                        <div class="mt-2 ml-1 text-xs text-gray-600">
                                            <div class="flex flex-wrap gap-2">
                                                <?php $__currentLoopData = $surcharge->references; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ref): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <span class="px-2 py-0.5 bg-gray-100 border border-gray-200 rounded-full">
                                                        <strong><?php echo e($ref->reference); ?></strong>
                                                        <span class="text-[10px] text-gray-500 ml-1">
                                                            (
                                                            <?php echo e($ref->charge_behavior === 'single' 
                                                                ? 'Single charge — applied once per reference' 
                                                                : 'Charge applies to all tasks with this reference'); ?>

                                                            )
                                                        </span>
                                                    </span>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-blue-700 font-semibold text-sm tracking-wide">
                                    <?php echo e(number_format($surcharge->amount, 3)); ?>

                                </span>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php if(Auth()->user()->role_id == \App\Models\Role::COMPANY): ?>
                    <div class="text-sm text-amber-700 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 mt-4">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>If you need to modify or remove an existing surcharge, please contact your system administrator.</span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-sm text-gray-500 italic">No surcharges added for this supplier</div>
                <?php if(Auth()->user()->role_id == \App\Models\Role::COMPANY): ?>
                    <div class="text-sm text-amber-700 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 mt-3">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>To request a new surcharge, please contact your system administrator.</span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100">
            <div class="flex items-center justify-between mb-4">
                <h2 class="ttext-lg font-semibold text-gray-800">Auto Extra Surcharge</h2>
                <span class="text-xs text-gray-500">Manage additional surcharges applied to supplier tasks</span>
            </div>
            <?php if($supplierCompany && $supplierCompany->supplierSurcharges->count()): ?>
                <form action="<?php echo e(route('suppliers.update.surcharges', $supplierCompany->id)); ?>" method="POST" class="space-y-4">
                    <?php echo csrf_field(); ?>
                    <div id="surcharge-container" class="divide-y divide-gray-100 rounded-lg border border-gray-200 overflow-hidden bg-gray-50/30">
                        <?php $__currentLoopData = $supplierCompany->supplierSurcharges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $surcharge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <input type="hidden" name="surcharge_id[]" value="<?php echo e($surcharge->id); ?>">
                            <div class="flex items-center gap-3 px-4 py-3 bg-white hover:bg-blue-50 transition duration-150 ease-in-out" data-surcharge-id="<?php echo e($surcharge->id); ?>">
                                <span class="inline-flex items-center justify-center bg-blue-100 text-blue-700 text-xs font-bold w-7 h-7 rounded-full">
                                    <?php echo e(strtoupper(substr($surcharge->label, 0, 2))); ?>

                                </span>
                                <input type="text" name="surcharge_label[]" value="<?php echo e($surcharge->label); ?>" 
                                    class="flex-1 border-gray-300 focus:border-blue-400 focus:ring focus:ring-blue-200 text-sm rounded-md px-3 py-1.5"
                                    placeholder="Enter surcharge name" />
                                <input type="number" step="0.001" name="surcharge_amount[]" value="<?php echo e($surcharge->amount); ?>" 
                                    class="w-28 border-gray-300 focus:border-blue-400 focus:ring focus:ring-blue-200 text-sm rounded-md px-2 py-1.5 text-right font-medium text-blue-700" 
                                    placeholder="0.000" />
                                <button type="button" class="text-gray-400 hover:text-red-500" onclick="removeSurchargeRow(this)" title="Remove">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <div class="flex justify-between items-center mt-5">
                        <p class="text-xs text-gray-500 italic">
                            *Updating surcharges will automatically update all non-invoiced related tasks.
                        </p>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="addSurchargeRow()" class="bg-blue-100 text-blue-700 hover:bg-blue-200 font-medium text-xs px-3 py-1.5 rounded-lg transition">
                                + Add Surcharge
                            </button>
                            <input type="hidden" id="deleted_surcharges" name="deleted_surcharges" value="">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm px-4 py-2 rounded-lg shadow-sm transition">
                                <i class="fa-solid fa-save mr-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-sm text-gray-500 italic">
                    No surcharges added for this supplier
                </div>
            <?php endif; ?>
        </div> -->

        <div class="bg-white rounded-md shadow-md p-5">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Supplier Policy</h2>
            <?php echo $__env->make('suppliers.partials.add_procedure', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('suppliers.partials.list_procedure', ['companyId' => $companyId, 'supplierCompany' => $supplierCompany], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </div>
    <script>
        if (document.getElementById('customize-columns-btn')) {
            document.getElementById('customize-columns-btn').addEventListener('click', function(e) {
                const dropdown = document.getElementById('columns-dropdown');
                dropdown.classList.toggle('hidden');
                dropdown.style.left = (e.target.getBoundingClientRect().left) + 'px';
            });
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('columns-dropdown');
            if(dropdown){
                if (!dropdown.contains(e.target) && e.target.id !== 'customize-columns-btn') {
                    dropdown.classList.add('hidden');
                }
            }
        });

        document.querySelectorAll('.column-toggle').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const colIndex = parseInt(this.dataset.col);
                const rows = document.querySelectorAll('#debit-credit .grid.grid-cols-12');
                rows.forEach(row => {
                    if (row.children[colIndex]) {
                        row.children[colIndex].style.display = this.checked ? '' : 'none';
                    }
                });
                const dataRows = document.querySelectorAll('#debit-credit .general-ledger-rows');
                dataRows.forEach(row => {
                    if (row.children[colIndex]) {
                        row.children[colIndex].style.display = this.checked ? '' : 'none';
                    }
                });
            });
        });

        function addSurchargeRow() {
            const container = document.getElementById('surcharge-container');
            const newRow = document.createElement('div');
            newRow.className = 'flex items-center gap-3 px-4 py-3 bg-white hover:bg-blue-50 transition duration-150 ease-in-out';
            newRow.innerHTML = `
                <input type="hidden" name="surcharge_id[]" value="">
                <span class="inline-flex items-center justify-center bg-gray-200 text-gray-600 text-xs font-bold w-7 h-7 rounded-full">--</span>
                <input type="text" name="surcharge_label[]" value="" 
                    class="flex-1 border-gray-300 focus:border-blue-400 focus:ring focus:ring-blue-200 text-sm rounded-md px-3 py-1.5"
                    placeholder="Enter surcharge name" />
                <input type="number" step="0.001" name="surcharge_amount[]" value="" 
                    class="w-28 border-gray-300 focus:border-blue-400 focus:ring focus:ring-blue-200 text-sm rounded-md px-2 py-1.5 text-right font-medium text-blue-700" 
                    placeholder="0.000" />
                <button type="button" class="text-gray-400 hover:text-red-500" onclick="removeSurchargeRow(this)" title="Remove">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            `;

            container.appendChild(newRow);
        }

        function removeSurchargeRow(button) {
            const row = button.closest('div[data-surcharge-id]');
            const id = row ? row.getAttribute('data-surcharge-id') : null;
            const input = document.getElementById('deleted_surcharges');

            if (id) {
                const current = input.value ? input.value.split(',') : [];
                if (!current.includes(id)) {
                    current.push(id);
                    input.value = current.join(',');
                }
            }

            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        }
    </script>
    <script>
        document.getElementById('export-pdf-btn').addEventListener('click', function() {
            const form = document.getElementById('task-filter-form');
            const range = document.getElementById('task-date-range').value.split(' to ');
            document.getElementById('task_from_date').value = range[0] ? range[0].trim() : '';
            document.getElementById('task_to_date').value = range[1] ? range[1].trim() : range[0];

            form.action = "<?php echo e(route('suppliers.suppliers.export.pdf', ['suppliersId' => $supplier->id])); ?>";
            form.method = "GET";
            form.submit();

            setTimeout(() => {
                form.action = "<?php echo e(route('suppliers.show', ['suppliersId' => $supplier->id])); ?>";
            }, 1000);
        });
        document.getElementById('export-excel-btn').addEventListener('click', function() {
            const form = document.getElementById('task-filter-form');
            const range = document.getElementById('task-date-range').value.split(' to ');
            document.getElementById('task_from_date').value = range[0] ? range[0].trim() : '';
            document.getElementById('task_to_date').value = range[1] ? range[1].trim() : range[0];

            form.action = "<?php echo e(route('suppliers.suppliers.export.excel', ['suppliersId' => $supplier->id])); ?>";
            form.method = "GET";
            form.submit();

            setTimeout(() => {
                form.action = "<?php echo e(route('suppliers.show', ['suppliersId' => $supplier->id])); ?>";
            }, 1000);
        });
    </script>

    <script>
        flatpickr("#task-date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [
                "<?php echo e(request('from_date')); ?>",
                "<?php echo e(request('to_date')); ?>"
            ].filter(Boolean)
        });

        document.getElementById('task-filter-form').addEventListener('submit', function(e) {
            const range = document.getElementById('task-date-range').value.split(' to ');
            document.getElementById('task_from_date').value = range[0] ? range[0].trim() : '';
            document.getElementById('task_to_date').value = range[1] ? range[1].trim() : range[0];
        });
    </script>

    <script>
        let supplierId = "<?php echo e(json_encode($supplier->id)); ?>";

        const filterBtn = document.getElementById('filter-btn');
        const clearBtn = document.getElementById('clear-btn');
        const loadingSpinner = document.getElementById('loading-spinner');
        const dateRangeInput = document.getElementById('date-range');

        flatpickr(dateRangeInput, {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [new Date().toISOString().split('T')[0], new Date().toISOString().split('T')[0]]
        });

        filterBtn.addEventListener('click', function() {
            updateRows();
        });

        clearBtn.addEventListener('click', function() {
            dateRangeInput.value = '';
            let ledgerBody = document.getElementById('debit-credit');
            let rows = ledgerBody.querySelectorAll('.general-ledger-rows');
            rows.forEach(row => row.remove());
        });

        function updateRows() {
            let dates = dateRangeInput.value.split(' to ');
            let fromDate = dates[0] ? dates[0].trim() : '';
            let toDate = dates[1] ? dates[1].trim() : dates[0];

            if (!fromDate || !toDate) return;

            let url = `<?php echo e(route('suppliers.suppliers.ledger-by-date', ['supplierId' => '__supplierId__'])); ?>?fromDate=${fromDate} 00:00:00&toDate=${toDate} 23:59:59`;
            url = url.replace('__supplierId__', supplierId);

            filterBtn.disabled = true;
            clearBtn.disabled = true;
            loadingSpinner.classList.remove('hidden');

            let ledgerBody = document.getElementById('debit-credit');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    // Remove old rows except header
                    let rows = ledgerBody.querySelectorAll('.general-ledger-rows');
                    rows.forEach(row => row.remove());

                    if (data.entries.length === 0) {
                        let emptyRow = document.createElement('div');
                        emptyRow.className = 'general-ledger-rows grid grid-cols-8 gap-2 p-2 text-center text-gray-500';
                        emptyRow.innerHTML = `<div colspan="8">No entries found for selected dates.</div>`;
                        ledgerBody.appendChild(emptyRow);
                    } else {
                        data.entries.sort((a, b) => {
                            const dateA = a.supplier_pay_date ? new Date(a.supplier_pay_date) : new Date(0);
                            const dateB = b.supplier_pay_date ? new Date(b.supplier_pay_date) : new Date(0);
                            return dateB - dateA;
                        });
                        data.entries.forEach(task => {
                            let info = '-';
                            if (task.type === 'flight' && task.flight_details) {
                                const f = task.flight_details;
                                info = `${f.airport_from ?? '-'} → ${f.airport_to ?? '-'}<br>${f.departure_time ?? '-'} - ${f.arrival_time ?? '-'}`;
                            } else if (task.type === 'hotel' && task.hotel_details) {
                                const h = task.hotel_details;
                                info = `${h.hotel?.name ?? '-'}<br>${h.check_in ?? '-'} - ${h.check_out ?? '-'}`;
                            } else if (task.additional_info) {
                                info = task.additional_info;
                            }

                            let row = document.createElement('div');
                            row.className = 'general-ledger-rows grid grid-cols-8 gap-2 p-2 text-center';
                            row.innerHTML = `
                            <div>${task.created_at.substring(0, 10)}</div>
                            <div>${task.reference ?? '-'}</div>
                            <div>${task.type ?? '-'}</div>
                            <div>${task.agent ? task.agent.name ?? '-' : '-'}</div>
                            <div>${task.status ? task.status.charAt(0).toUpperCase() + task.status.slice(1) : '-'}</div>
                            <div>${task.supplier_pay_date ? task.supplier_pay_date.substring(0, 10) : '-'}</div>
                            <div>${task.passenger_name ?? '-'}</div>
                            <div class="text-xs">${info}</div>
                        `;
                            ledgerBody.appendChild(row);
                        });
                    }
                })
                .finally(() => {
                    filterBtn.disabled = false;
                    clearBtn.disabled = false;
                    loadingSpinner.classList.add('hidden');
                });
        }

        // Initial load
        updateRows();
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/suppliers/show.blade.php ENDPATH**/ ?>