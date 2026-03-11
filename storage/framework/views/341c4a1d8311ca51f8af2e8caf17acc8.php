<div class="flex flex-col gap-4" @change="updateFlightDetail($event)" @dropdown-select="updateFlightDetail($event)">
    <?php $__empty_1 = true; $__currentLoopData = $task->flightDetail; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $flight): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="border border-gray-200 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-gray-700 mb-3">Flight <?php echo e($index + 1); ?></h4>

        <div class="grid grid-cols-1 gap-4">
            <!-- Airport From & Terminal From -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Airport From</label>
                    <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'flights['.e($index).'][airport_from_id]','items' => $airports,'placeholder' => 'Select airport'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($flight->airport_from_id ?? ''),'selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($flight->airportFrom ? $flight->airportFrom->iata_code . ' - ' . $flight->airportFrom->name : ($flight->airport_from ?? ''))]); ?>
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
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Terminal From</label>
                    <input type="text"
                        name="flights[<?php echo e($index); ?>][terminal_from]"
                        value="<?php echo e($flight->terminal_from); ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <!-- Departure Time -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Departure Time</label>
                <input type="datetime-local"
                    name="flights[<?php echo e($index); ?>][departure_time]"
                    value="<?php echo e($flight->departure_time ? \Carbon\Carbon::parse($flight->departure_time)->format('Y-m-d\TH:i') : ''); ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>

            <!-- Airport To & Terminal To -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Airport To</label>
                    <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'flights['.e($index).'][airport_to_id]','items' => $airports,'placeholder' => 'Select airport'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($flight->airport_to_id ?? ''),'selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($flight->airportTo ? $flight->airportTo->iata_code . ' - ' . $flight->airportTo->name : ($flight->airport_to ?? ''))]); ?>
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
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Terminal To</label>
                    <input type="text"
                        name="flights[<?php echo e($index); ?>][terminal_to]"
                        value="<?php echo e($flight->terminal_to); ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <!-- Arrival Time -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Arrival Time</label>
                <input type="datetime-local"
                    name="flights[<?php echo e($index); ?>][arrival_time]"
                    value="<?php echo e($flight->arrival_time ? \Carbon\Carbon::parse($flight->arrival_time)->format('Y-m-d\TH:i') : ''); ?>"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>

            <!-- Airline -->
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Airline</label>
                <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'flights['.e($index).'][airline_id_new]','items' => $airlines,'placeholder' => 'Select airline'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($flight->airline_id_new ?? ''),'selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($flight->airline ? $flight->airline->iata_designator . ' - ' . $flight->airline->name : ($flight->airline_id ?? ''))]); ?>
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

            <!-- Flight Number & Class Type -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Flight Number</label>
                    <input type="text"
                        name="flights[<?php echo e($index); ?>][flight_number]"
                        value="<?php echo e($flight->flight_number); ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Class Type</label>
                    <select name="flights[<?php echo e($index); ?>][class_type]"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        <option value="">Select class</option>
                        <option value="economy" <?php echo e($flight->class_type === 'economy' ? 'selected' : ''); ?>>Economy</option>
                        <option value="business" <?php echo e($flight->class_type === 'business' ? 'selected' : ''); ?>>Business</option>
                        <option value="first" <?php echo e($flight->class_type === 'first' ? 'selected' : ''); ?>>First Class</option>
                    </select>
                </div>
            </div>

            <!-- Duration & Baggage -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Duration</label>
                    <input type="text"
                        name="flights[<?php echo e($index); ?>][duration_time]"
                        value="<?php echo e($flight->duration_time); ?>"
                        placeholder="2h 30m"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Baggage Allowed</label>
                    <input type="text"
                        name="flights[<?php echo e($index); ?>][baggage_allowed]"
                        value="<?php echo e($flight->baggage_allowed); ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <!-- Seat Number & Ticket Number -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Seat Number</label>
                    <input type="text"
                        name="flights[<?php echo e($index); ?>][seat_no]"
                        value="<?php echo e($flight->seat_no); ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Ticket Number</label>
                    <input type="text"
                        name="flights[<?php echo e($index); ?>][ticket_number]"
                        value="<?php echo e($flight->ticket_number); ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <!-- Hidden ID field to identify which flight record to update -->
            <input type="hidden" name="flights[<?php echo e($index); ?>][id]" value="<?php echo e($flight->id); ?>">
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <p class="text-sm text-gray-500 italic">No flight details available</p>
    <?php endif; ?>
</div>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/tasks/partial/flight-details-form.blade.php ENDPATH**/ ?>