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
     <?php $__env->slot('header', null, []); ?> 
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            <?php echo e(__('Edit Invoice')); ?> - <?php echo e($invoice->invoice_number); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">

                    <!-- Status Badge -->
                    <div class="mb-6">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            <?php echo e($invoice->status == 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                               ($invoice->status == 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200')); ?>">
                            <?php echo e(ucfirst($invoice->status)); ?>

                        </span>
                    </div>

                    <form method="POST" action="<?php echo e(route('invoice.accountant.update')); ?>" class="space-y-8">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PUT'); ?>

                        <input type="hidden" name="invoice_id" value="<?php echo e($invoice->id); ?>">
                        <input type="hidden" name="company_id" value="<?php echo e(auth()->user()->accountant->branch->company_id); ?>">

                        <!-- Basic Information -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Basic Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                                <!-- Invoice Number -->
                                <div>
                                    <label for="invoice_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Invoice Number
                                    </label>
                                    <input id="invoice_number"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text"
                                        name="invoice_number"
                                        value="<?php echo e(old('invoice_number', $invoice->invoice_number)); ?>"
                                        required />
                                    <?php $__errorArgs = ['invoice_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <!-- Client -->
                                <div>
                                    <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Client
                                    </label>
                                    <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'client_id','items' => $clients->map(fn($client) => ['id' => $client->id, 'name' => $client->full_name . ' - ' . $client->phone]),'placeholder' => 'Select Client'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'client_id','selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoice->client ? $invoice->client->full_name . ' - ' . $invoice->client->phone : null),'selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoice->client ? $invoice->client_id : null)]); ?>
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
                                    <?php $__errorArgs = ['client_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <!-- Agent -->
                                <div>
                                    <label for="agent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Agent
                                    </label>
                                    <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'agent_id','items' => $agents->map(fn($agent) => ['id' => $agent->id, 'name' => $agent->name]),'placeholder' => 'Select Agent'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoice->agent_id),'selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($invoice->agent ? $invoice->agent->name : null),'id' => 'agent_id']); ?>
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
                                    <?php $__errorArgs = ['agent_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <!-- Currency -->
                                <div>
                                    <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Currency
                                    </label>
                                    <input class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text" name="currency" value="<?php echo e(old('currency', $invoice->currency)); ?>" />
                                    <?php $__errorArgs = ['currency'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <!-- Status -->
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Status
                                    </label>
                                    <select id="status"
                                        name="status"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                        <option value="unpaid" <?php echo e(old('status', $invoice->status) == 'unpaid' ? 'selected' : ''); ?>>Unpaid</option>
                                        <option value="paid" <?php echo e(old('status', $invoice->status) == 'paid' ? 'selected' : ''); ?>>Paid</option>
                                    </select>
                                    <?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <!-- Label -->
                                <div>
                                    <label for="label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Label
                                    </label>
                                    <input id="label"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text"
                                        name="label"
                                        value="<?php echo e(old('label', $invoice->label)); ?>" />
                                    <?php $__errorArgs = ['label'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <div class="grid grid-cols-1 gap-3 items-left bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Financial Information</h3>
                            <div class="grid grid-cols-1 gap-4">
                                <?php if($invoice->invoiceDetails->isNotEmpty()): ?>
                                <div class="space-y-6">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">
                                        Invoice Details & Task Information
                                    </label>
                                    
                                    <?php $__currentLoopData = $invoice->invoiceDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $task = $detail->task;
                                    ?>
                                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-sm">
                                        <!-- Task Header -->
                                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 rounded-t-lg">
                                            <div class="flex justify-between items-start">
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 flex-1">
                                                    <div>
                                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Reference</h4>
                                                        <p class="text-base font-semibold text-gray-900 dark:text-white"><?php echo e($task->reference); ?></p>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Passenger</h4>
                                                        <p class="text-base text-gray-900 dark:text-white"><?php echo e($task->passenger_name ?? 'N/A'); ?></p>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Agent</h4>
                                                        <p class="text-base text-gray-900 dark:text-white"><?php echo e($task->agent->name ?? 'N/A'); ?></p>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                        <?php if($task->status == 'confirmed'): ?> bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                        <?php elseif($task->status == 'pending'): ?> bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                                        <?php elseif($task->status == 'cancelled'): ?> bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                                        <?php else: ?> bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200
                                                        <?php endif; ?>">
                                                        <?php echo e(ucfirst($task->status)); ?>

                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-3 flex items-center justify-between">
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Task Type</h4>
                                                    <p class="text-base font-medium text-gray-900 dark:text-white"><?php echo e(ucfirst(str_replace('_', ' ', $task->type))); ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <input type="number"
                                                        oninput="updateTotalAmount(this)"
                                                        onblur="formatToThreeDecimals(this)"
                                                        step="0.001"
                                                        name="invoice_details[<?php echo e($detail->task_id); ?>][amount]"
                                                        value="<?php echo e(number_format(old('invoice_details.' . $detail->id . '.amount', $detail->task_price),3)); ?>"
                                                        class="block w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white text-right font-semibold" 
                                                        placeholder="Amount" />
                                                    <?php $__errorArgs = ['invoice_details.' . $detail->id . '.amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Task Details -->
                                        <div class="px-6 py-4">
                                            <?php if($task->type == 'flight' && $task->flightDetails): ?>
                                                <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Flight Details</h5>
                                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                                    <?php $__currentLoopData = $task->flightDetail; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $flightDetail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md border">
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                                            <div>
                                                                <span class="font-medium text-gray-600 dark:text-gray-400">Flight Number:</span>
                                                                <span class="text-gray-900 dark:text-white ml-2"><?php echo e($flightDetail->flight_number ?? 'N/A'); ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="font-medium text-gray-600 dark:text-gray-400">Class:</span>
                                                                <span class="text-gray-900 dark:text-white ml-2"><?php echo e(ucfirst($flightDetail->class_type ?? 'N/A')); ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="font-medium text-gray-600 dark:text-gray-400">From:</span>
                                                                <span class="text-gray-900 dark:text-white ml-2"><?php echo e($flightDetail->airport_from ?? 'N/A'); ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="font-medium text-gray-600 dark:text-gray-400">To:</span>
                                                                <span class="text-gray-900 dark:text-white ml-2"><?php echo e($flightDetail->airport_to ?? 'N/A'); ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="font-medium text-gray-600 dark:text-gray-400">Departure:</span>
                                                                <span class="text-gray-900 dark:text-white ml-2"><?php echo e($flightDetail->departure_time ? $flightDetail->departure_time->format('M d, Y H:i') : 'N/A'); ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="font-medium text-gray-600 dark:text-gray-400">Arrival:</span>
                                                                <span class="text-gray-900 dark:text-white ml-2"><?php echo e($flightDetail->arrival_time ? $flightDetail->arrival_time->format('M d, Y H:i') : 'N/A'); ?></span>
                                                            </div>
                                                            <?php if($flightDetail->ticket_number): ?>
                                                            <div class="md:col-span-2">
                                                                <span class="font-medium text-gray-600 dark:text-gray-400">Ticket Number:</span>
                                                                <span class="text-gray-900 dark:text-white ml-2"><?php echo e($flightDetail->ticket_number); ?></span>
                                                            </div>
                                                            <?php endif; ?>
                                                            <?php if($flightDetail->baggage_allowed): ?>
                                                            <div class="md:col-span-2">
                                                                <span class="font-medium text-gray-600 dark:text-gray-400">Baggage:</span>
                                                                <span class="text-gray-900 dark:text-white ml-2"><?php echo e($flightDetail->baggage_allowed); ?></span>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </div>
                                            <?php elseif($task->type == 'hotel' && $task->hotelDetails): ?>
                                                <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Hotel Details</h5>
                                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md border">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Check-in:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->hotelDetails->check_in ? date('M d, Y', strtotime($task->hotelDetails->check_in)) : 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Check-out:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->hotelDetails->check_out ? date('M d, Y', strtotime($task->hotelDetails->check_out)) : 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Nights:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->hotelDetails->nights ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Room Type:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->hotelDetails->room_type ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Room Number:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->hotelDetails->room_number ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Meal Type:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->hotelDetails->meal_type ?? 'N/A'); ?></span>
                                                        </div>
                                                        <?php if($task->hotelDetails->room_reference): ?>
                                                        <div class="md:col-span-2">
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Room Reference:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->hotelDetails->room_reference); ?></span>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php if($task->hotelDetails->is_refundable !== null): ?>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Refundable:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->hotelDetails->is_refundable ? 'Yes' : 'No'); ?></span>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php elseif($task->type == 'visa' && $task->visaDetails): ?>
                                                <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Visa Details</h5>
                                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md border">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Visa Type:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->visaDetails->visa_type ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Issuing Country:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->visaDetails->issuing_country ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Entries:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->visaDetails->number_of_entries ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Stay Duration:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->visaDetails->stay_duration ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Expiry Date:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->visaDetails->expiry_date ? date('M d, Y', strtotime($task->visaDetails->expiry_date)) : 'N/A'); ?></span>
                                                        </div>
                                                        <?php if($task->visaDetails->application_number): ?>
                                                        <div class="md:col-span-2">
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Application Number:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->visaDetails->application_number); ?></span>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php elseif($task->type == 'insurance' && $task->insuranceDetails): ?>
                                                <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Insurance Details</h5>
                                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md border">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Insurance Type:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->insuranceDetails->insurance_type ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Plan Type:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->insuranceDetails->plan_type ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Destination:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->insuranceDetails->destination ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Duration:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->insuranceDetails->duration ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Package:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->insuranceDetails->package ?? 'N/A'); ?></span>
                                                        </div>
                                                        <?php if($task->insuranceDetails->document_reference): ?>
                                                        <div class="md:col-span-2">
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Document Reference:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e($task->insuranceDetails->document_reference); ?></span>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php if($task->insuranceDetails->date): ?>
                                                        <div>
                                                            <span class="font-medium text-gray-600 dark:text-gray-400">Date:</span>
                                                            <span class="text-gray-900 dark:text-white ml-2"><?php echo e(date('M d, Y', strtotime($task->insuranceDetails->date))); ?></span>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md border">
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">No detailed information available for this task type.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Invoice Charge -->
                                <div>
                                    <label for="invoice_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Invoice Charge
                                    </label>
                                    <input id="invoice_charge"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="number"
                                        step="0.001"
                                        name="invoice_charge"
                                        oninput="updateTotalAmount(this)"
                                        onblur="formatToThreeDecimals(this)"
                                        value="<?php echo e(number_format(old('invoice_charge', $invoice->invoice_charge), 3)); ?>" />
                                    <?php $__errorArgs = ['invoice_charge'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <!-- Amount -->
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Total Amount
                                    </label>
                                    <input id="amount"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="number"
                                        step="0.001"
                                        name="amount"
                                        oninput="updateInvoiceCharge(this)"
                                        onblur="formatToThreeDecimals(this)"
                                        value="<?php echo e(number_format(old('amount', $invoice->amount),3)); ?>"
                                        required />
                                    <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <!-- Tax -->
                                <div>
                                    <label for="tax" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tax
                                    </label>
                                    <input id="tax"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="number"
                                        step="0.001"
                                        name="tax"
                                        value="<?php echo e(old('tax', $invoice->tax)); ?>" />
                                    <?php $__errorArgs = ['tax'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                            </div>
                        </div>

                        <!-- Dates -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Date Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                                <!-- Invoice Date -->
                                <div>
                                    <label for="invoice_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Invoice Date
                                    </label>
                                    <input id="invoice_date"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="date"
                                        name="invoice_date"
                                        value="<?php echo e(old('invoice_date', $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '')); ?>" />
                                    <?php $__errorArgs = ['invoice_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <!-- Due Date -->
                                <div>
                                    <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Due Date
                                    </label>
                                    <input id="due_date"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="date"
                                        name="due_date"
                                        value="<?php echo e(old('due_date', $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d') : '')); ?>" />
                                    <?php $__errorArgs = ['due_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <!-- Paid Date -->
                                <div>
                                    <label for="paid_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Paid Date & Time
                                    </label>
                                    <input id="paid_date"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="datetime-local"
                                        name="paid_date"
                                        value="<?php echo e(old('paid_date', $invoice->paid_date ? \Carbon\Carbon::parse($invoice->paid_date)->format('Y-m-d\TH:i') : '')); ?>" />
                                    <?php $__errorArgs = ['paid_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Payment Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <div class="flex gap-2 items-end">
                                    <div class="w-full">
                                        <label for="payment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Payment Type
                                            <?php if($invoice->status === 'paid'): ?>
                                                <span class="text-xs text-gray-500">(Current: <?php echo e(ucfirst($invoice->payment_type ?? 'None')); ?>)</span>
                                            <?php endif; ?>
                                        </label>
                                        <select name="payment_type" id="payment_type" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                            <?php $__currentLoopData = $invoicePaymentTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($key); ?>" 
                                                <?php echo e(old('payment_type', $invoice->payment_type) == $key ? 'selected' : ''); ?>

                                                data-payment-type="<?php echo e($key); ?>">
                                                <?php echo e(ucfirst($type)); ?>

                                            </option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                        <?php $__errorArgs = ['payment_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                        
                                        <!-- Payment Type Change Warning -->
                                        <div id="payment-type-warning" class="hidden mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                            <div class="flex">
                                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                                <div class="ml-3">
                                                    <p class="text-sm text-yellow-800" id="payment-warning-text">
                                                        <!-- Warning text will be inserted here -->
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Payment Type Restrictions Info -->
                                        <?php if($invoice->status === 'paid'): ?>
                                        <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                            <div class="flex">
                                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                                <div class="ml-3">
                                                    <p class="text-sm text-blue-800">
                                                        <strong>Payment Type Change Rules:</strong><br>
                                                        • Only Credit, Cash, Full changes are supported<br>
                                                        • External gateway payments (MyFatoorah, Tap, etc.) cannot be changed<br>
                                                        • Credit changes require sufficient client balance
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if(strtolower(old('payment_type', $invoice->payment_type)) == ''): ?>
                                    <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'client_credit_id','items' => $clients->map(fn($client) => ['id' => $client->id, 'name' => $client->full_name . ' - ' . $client->phone . ' (' . $client->total_credit . ')' ]),'placeholder' => 'Select Client for Credit'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'client_credit_id','selectedName' => '$invoice->client->fullname . \' - \' . $invoice->client->phone','selectedId' => '$invoice->client_id']); ?>
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
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="external_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        External URL
                                    </label>
                                    <input id="external_url"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="url"
                                        name="external_url"
                                        value="<?php echo e(old('external_url', $invoice->external_url)); ?>" />
                                    <?php $__errorArgs = ['external_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <span class="text-red-500 text-sm mt-1"><?php echo e($message); ?></span>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-600">
                            <a href="<?php echo e(route('invoices.index')); ?>"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Invoices
                            </a>

                            <button type="submit"
                                class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Update Invoice
                            </button>
                        </div>
                    </form>

                    <!-- Credit Shortage Payment Link Section -->
                    <?php if(session('shortage_info')): ?>
                    <div class="mt-8 p-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-4">
                            <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            Payment Type Changed - Credit Shortage Detected
                        </h3>
                        
                        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded">
                            <p class="text-green-800 dark:text-green-200 text-sm">
                                ✓ Payment type has been successfully changed to Credit. The client's credit balance will go negative.
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Available Credit</h4>
                                <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                                    <?php echo e(number_format(session('shortage_info')['available_credit'], 3)); ?> <?php echo e($invoice->currency); ?>

                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Invoice Amount</h4>
                                <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                                    <?php echo e(number_format(session('shortage_info')['required_amount'], 3)); ?> <?php echo e($invoice->currency); ?>

                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Credit Shortage</h4>
                                <p class="text-lg font-semibold text-red-600 dark:text-red-400">
                                    <?php echo e(number_format(session('shortage_info')['shortage_amount'], 3)); ?> <?php echo e($invoice->currency); ?>

                                </p>
                            </div>
                        </div>

                        <div class="mb-4 p-3 bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded">
                            <p class="text-yellow-800 dark:text-yellow-200 text-sm">
                                <strong>Optional:</strong> You can create a payment link for the shortage amount to help the client top up their credit balance.
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <form method="POST" action="<?php echo e(route('invoice.accountant.create.payment.link.shortage')); ?>" class="flex-1">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="invoice_id" value="<?php echo e(session('shortage_info')['invoice_id']); ?>">
                                <input type="hidden" name="client_id" value="<?php echo e(session('shortage_info')['client_id']); ?>">
                                <input type="hidden" name="shortage_amount" value="<?php echo e(session('shortage_info')['shortage_amount']); ?>">
                                
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <div class="flex-1">
                                        <label for="payment_gateway" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Payment Gateway
                                        </label>
                                        <select name="payment_gateway" id="payment_gateway" required
                                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                            onchange="togglePaymentMethods()">
                                            <option value="">Select Gateway</option>
                                            <?php $__currentLoopData = $charges->where('can_generate_link', true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $charge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($charge->name); ?>" data-gateway="<?php echo e(strtolower($charge->name)); ?>"><?php echo e($charge->name); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    
                                    <div class="flex-1" id="payment_method_section" style="display: none;">
                                        <label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Payment Method
                                        </label>
                                        <select name="payment_method" id="payment_method"
                                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                            <option value="">Select Method</option>
                                            <?php $__currentLoopData = $paymentMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($method->id); ?>" 
                                                data-type="<?php echo e(strtolower($method->type)); ?>"
                                                data-charge-id="<?php echo e($method->charge_id); ?>">
                                                <?php echo e($method->english_name ?? $method->arabic_name); ?>

                                            </option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </select>
                                    </div>
                                    
                                    <div class="flex items-end">
                                        <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                            Create Payment Link
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="flex items-end">
                                <a href="<?php echo e(route('invoice.accountant.edit', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Skip Payment Link
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Timestamp Information -->
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-600">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-500 dark:text-gray-400">
                            <div>
                                <strong>Created:</strong> <?php echo e($invoice->created_at ? $invoice->created_at->format('M d, Y H:i') : 'N/A'); ?>

                            </div>
                            <div>
                                <strong>Last Updated:</strong> <?php echo e($invoice->updated_at ? $invoice->updated_at->format('M d, Y H:i') : 'N/A'); ?>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const totalAmountInput = document.getElementById('amount');
        const invoiceChargeInput = document.getElementById('invoice_charge');
        const paymentTypeSelect = document.getElementById('payment_type');
        const paymentWarning = document.getElementById('payment-type-warning');
        const paymentWarningText = document.getElementById('payment-warning-text');

        // Store original payment type
        const originalPaymentType = '<?php echo e($invoice->payment_type); ?>';
        const invoiceStatus = '<?php echo e($invoice->status); ?>';
        const clientCredit = parseFloat('<?php echo e($clientCredit); ?>');
        const invoiceAmount = parseFloat('<?php echo e($invoice->amount); ?>');

        function formatToThreeDecimals(inputElement) {
            let value = parseFloat(inputElement.value) || 0;
            inputElement.value = value.toFixed(3);
        }

        function calculateTotal() {
            let invoiceChargeValue = parseFloat(invoiceChargeInput.value) || 0;

            let detailsTotal = 0;
            const detailInputs = document.querySelectorAll('input[name^="invoice_details"]');
            detailInputs.forEach(input => {
                detailsTotal += parseFloat(input.value) || 0;
            });

            let finalTotal = detailsTotal + invoiceChargeValue;
            totalAmountInput.value = finalTotal.toFixed(3);
        }

        function updateTotalAmount(inputElement) {
            calculateTotal(); // Calculate immediately without debounce
        }

        // Simple form submission handler 
        document.querySelector('form').addEventListener('submit', function(e) {
            // Ensure final calculation before submission
            calculateTotal();
        });

        function updateInvoiceCharge(inputElement) {
            let changedValue = parseFloat(inputElement.value) || 0;
            let invoiceChargeInput = document.getElementById('invoice_charge');

            let detailsTotal = 0;
            const detailInputs = document.querySelectorAll('input[name^="invoice_details"]');
            detailInputs.forEach(input => {
                detailsTotal += parseFloat(input.value) || 0;
            });

            let finalTotal = changedValue - detailsTotal;

            invoiceChargeInput.value = finalTotal.toFixed(3);
        }

        // Payment type change validation
        function handlePaymentTypeChange() {
            const selectedPaymentType = paymentTypeSelect.value;
            
            // Hide warning by default
            paymentWarning.classList.add('hidden');
            
            // Only show warnings for paid invoices
            if (invoiceStatus !== 'paid') {
                return;
            }

            // If no change, hide warning
            if (selectedPaymentType === originalPaymentType) {
                return;
            }

            // Validate payment type changes
            if (originalPaymentType === 'credit' && selectedPaymentType === 'cash') {
                paymentWarningText.innerHTML = 'Changing from Credit to Cash will refund the amount back to client\'s credit balance.';
                paymentWarning.classList.remove('hidden');
            } else if (originalPaymentType === 'cash' && selectedPaymentType === 'credit') {
                if (clientCredit < invoiceAmount) {
                    const shortage = invoiceAmount - clientCredit;
                    paymentWarningText.innerHTML = `Insufficient client credit! Available: ${clientCredit.toFixed(3)}, Required: ${invoiceAmount.toFixed(3)}, Shortage: ${shortage.toFixed(3)}`;
                    paymentWarning.classList.remove('hidden');
                } else {
                    paymentWarningText.innerHTML = 'Changing from Cash to Credit will deduct the amount from client\'s credit balance.';
                    paymentWarning.classList.remove('hidden');
                }
            } else if (!['credit', 'cash', 'full'].includes(originalPaymentType) || !['credit', 'cash', 'full'].includes(selectedPaymentType)) {
                paymentWarningText.innerHTML = 'Only changes between Credit, Cash, and Full are supported for paid invoices.';
                paymentWarning.classList.remove('hidden');
            }
        }

        // Add event listener for payment type changes
        if (paymentTypeSelect) {
            paymentTypeSelect.addEventListener('change', handlePaymentTypeChange);
            // Run validation on page load
            handlePaymentTypeChange();
        }

        // Payment method visibility toggle for shortage payment link
        function togglePaymentMethods() {
            const gatewaySelect = document.getElementById('payment_gateway');
            const methodSection = document.getElementById('payment_method_section');
            const methodSelect = document.getElementById('payment_method');
            
            if (!gatewaySelect || !methodSection || !methodSelect) return;
            
            const selectedGateway = gatewaySelect.value.toLowerCase();
            const requiresMethod = ['myfatoorah', 'hesabe'].includes(selectedGateway);
            
            if (requiresMethod) {
                methodSection.style.display = 'block';
                methodSelect.setAttribute('required', 'required');
                
                // Filter payment methods by gateway
                const allOptions = methodSelect.querySelectorAll('option[data-type]');
                allOptions.forEach(option => {
                    const optionType = option.getAttribute('data-type');
                    if (optionType === selectedGateway) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
                
                // Reset selection
                methodSelect.value = '';
            } else {
                methodSection.style.display = 'none';
                methodSelect.removeAttribute('required');
                methodSelect.value = '';
            }
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/accountant/edit.blade.php ENDPATH**/ ?>