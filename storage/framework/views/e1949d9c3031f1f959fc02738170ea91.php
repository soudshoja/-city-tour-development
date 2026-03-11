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
        <!-- Page Title -->
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Edit Charges Details</h1>

        <!-- Edit Form -->
        <div class="bg-white shadow-md rounded-lg p-8">
            <form method="POST" action="<?php echo e(route('charges.update', $charge->id)); ?>" class="space-y-6">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Charge Name -->
                    <div>
                        <label for="name" class="block text-gray-700 font-semibold mb-2">Charge Name</label>
                        <input type="text" name="name" id="name" value="<?php echo e($charge->name); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 cursor-not-allowed"
                            readonly>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
                        <input type="text" name="description" id="description" value="<?php echo e($charge->description); ?>"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Type -->
                    <div>
                        <label for="type" class="block text-gray-700 font-semibold mb-2">Type</label>

                        <select name="type" id="type" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="Payment Gateway"
                                <?php echo e(old('type', $charge->type ?? '') == 'Payment Gateway' ? 'selected' : ''); ?>>Payment
                                Gateway</option>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-gray-700 font-semibold mb-2">Amount (KWD)</label>
                        <input type="number" name="amount" id="amount" value="<?php echo e($charge->amount); ?>" required
                            min="0.01" step="0.01"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <div>
                        <label for="acc_fee_name" class="block text-gray-700 font-semibold mb-2">
                            COA (Expenses) for Payment Gateway
                            Fee
                        </label>
                        <input type="text" name="acc_fee_name" id="acc_fee_name"
                            value="<?php echo e(old('acc_fee_name', isset($accFee) && $accFee ? $accFee->name : '')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 cursor-not-allowed"
                            readonly>

                    </div>


                    <!-- Hidden input field to store the ID of the selected COA for Payment Gateway Fee -->
                    <input type="hidden" name="acc_fee_id" id="acc_fee_id"
                        value="<?php echo e(old('acc_fee_id', $charge->acc_fee_id ?? '')); ?>">



                    <div>
                        <label for="acc_bank_fee_name" class="block text-gray-700 font-semibold mb-2">
                            COA (Assets) for selected Payment Gateway
                        </label>
                        <input type="text" name="acc_bank_fee_name" id="acc_bank_fee_name"
                            value="<?php echo e(old('acc_bank_fee_name', isset($accBankFee) && $accBankFee ? $accBankFee->name : '')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 cursor-not-allowed"
                            readonly>
                    </div>


                    <!-- Hidden input field to store the ID of the selected COA for Bank Account -->
                    <input type="hidden" name="acc_fee_bank_id" id="acc_fee_bank_id"
                        value="<?php echo e(old('acc_fee_bank_id', $charge->acc_fee_bank_id ?? '')); ?>">


                    <div>
                        <label for="acc_bank_name" class="block text-gray-700 font-semibold mb-2">COA (Assets) for
                            Debited
                            Bank
                            Account</label>
                        <input required list="bankAccountOptions" type="text" name="acc_bank_name" id="acc_bank_name"
                            value="<?php echo e(old('acc_bank_name', isset($accBank) && $accBank ? $accBank->name : '')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 cursor-not-allowed"
                            readonly>

                        <datalist id="bankAccountOptions">
                            <?php $__currentLoopData = $coaBankAccount; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($account->name); ?>" data-id="<?php echo e($account->id); ?>"></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </datalist>

                        <?php if($coaBankAccount->isEmpty()): ?>
                            <div class="mt-2 text-sm text-red-500">
                                No available records found. Please add via COA page.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Hidden input field for COA Bank Fee ID -->
                    <input type="hidden" name="acc_bank_id" id="acc_bank_id"
                        value="<?php echo e(old('acc_bank_id', $charge->acc_bank_id ?? '')); ?>">

                </div>

                <!-- Submit Button -->
                <div class="mt-4 flex space-x-4">
                    <a href="<?php echo e(route('charges.index')); ?>"
                        class="w-full text-center px-4 py-2 bg-gray-300 text-gray-800 font-semibold rounded-lg focus:outline-none focus:ring focus:ring-gray-100 focus:border-gray-300">
                        Cancel
                    </a>

                    <button type="submit"
                        class="w-full px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        Update Charge
                    </button>
                </div>

            </form>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to update hidden fields based on the selected name
            function updateHiddenField(inputId, datalistId, hiddenId) {
                const input = document.getElementById(inputId);
                const hidden = document.getElementById(hiddenId);
                const options = document.querySelectorAll(`#${datalistId} option`);

                // If a value is pre-selected, populate the hidden field
                if (input.value) {
                    const selectedOption = [...options].find(option => option.value === input.value);
                    if (selectedOption) {
                        hidden.value = selectedOption.dataset.id;
                    }
                }

                // Listen for user input or changes and update the hidden field
                input.addEventListener('input', function() {
                    const selectedName = input.value;
                    hidden.value = ''; // Reset hidden field initially

                    options.forEach(option => {
                        if (option.value === selectedName) {
                            hidden.value = option.dataset
                                .id; // Update hidden field to matching option's ID
                        }
                    });
                });

                input.addEventListener('change', function() {
                    const selectedName = input.value;
                    hidden.value = ''; // Reset hidden field initially

                    options.forEach(option => {
                        if (option.value === selectedName) {
                            hidden.value = option.dataset
                                .id; // Update hidden field to matching option's ID
                        }
                    });
                });
            }

            // Initialize the update for all fields
            updateHiddenField('acc_fee_name', 'paymentGatewayOptions', 'acc_fee_id');
            updateHiddenField('acc_bank_fee_name', 'paymentGatewayBankAccOptions', 'acc_fee_bank_id');
            updateHiddenField('acc_bank_name', 'bankAccountOptions', 'acc_bank_id');

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
<?php endif; ?>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/charges/edit.blade.php ENDPATH**/ ?>