<div class="flex flex-col gap-4" @change="updateHotelDetail($event)" @dropdown-select="updateHotelDetail($event)">
    <!-- Hotel Selection -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Hotel</label>
        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'hotel_id','items' => $hotels,'placeholder' => 'Select a hotel'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($task->hotelDetails->hotel_id ?? ''),'selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($task->hotelDetails->hotel->name ?? '')]); ?>
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

    <!-- Room Type & Room Number -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Room Type</label>
            <input type="text"
                name="room_type"
                value="<?php echo e($task->hotelDetails->room_type ?? ''); ?>"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Room Number</label>
            <input type="text"
                name="room_number"
                value="<?php echo e($task->hotelDetails->room_number ?? ''); ?>"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
    </div>

    <!-- Meal Type -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Meal Type</label>
        <input type="text"
            name="meal_type"
            value="<?php echo e($task->hotelDetails->meal_type ?? ''); ?>"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
    </div>

    <!-- Check In & Check Out -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Check In</label>
            <input type="date"
                name="check_in"
                value="<?php echo e($task->hotelDetails->check_in ?? ''); ?>"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Check Out</label>
            <input type="date"
                name="check_out"
                value="<?php echo e($task->hotelDetails->check_out ?? ''); ?>"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
    </div>
</div>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/tasks/partial/hotel-details-form.blade.php ENDPATH**/ ?>