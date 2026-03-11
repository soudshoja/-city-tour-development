<div class="flex flex-col gap-4" @change="updateInsuranceDetail($event)">
    <!-- Insurance Type & Plan Type -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Insurance Type</label>
            <input type="text"
                name="insurance_type"
                value="<?php echo e($task->insuranceDetails->insurance_type ?? ''); ?>"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Plan Type</label>
            <input type="text"
                name="plan_type"
                value="<?php echo e($task->insuranceDetails->plan_type ?? ''); ?>"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
    </div>

    <!-- Destination & Duration -->
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Destination</label>
            <input type="text"
                name="destination"
                value="<?php echo e($task->insuranceDetails->destination ?? ''); ?>"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Duration</label>
            <input type="text"
                name="duration"
                value="<?php echo e($task->insuranceDetails->duration ?? ''); ?>"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
        </div>
    </div>

    <!-- Package -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Package</label>
        <input type="text"
            name="package"
            value="<?php echo e($task->insuranceDetails->package ?? ''); ?>"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
    </div>
</div>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/tasks/partial/insurance-details-form.blade.php ENDPATH**/ ?>