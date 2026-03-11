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
        <h1 class="text-3xl font-bold text-gray-700 mb-6">New Charges</h1>

        <!-- Edit Form -->
        <div class="bg-white shadow-md rounded-lg p-8">
            <form action="<?php echo e(route('charges.store')); ?>" method="POST">
                <?php echo csrf_field(); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Charge Name -->
                    <div>
                        <label for="name" class="block text-gray-700 font-semibold mb-2">Charge Name</label>
                        <input type="text" name="name" id="name" value="<?php echo e(old('name')); ?>" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
                        <input type="text" name="description" id="description" value="<?php echo e(old('description')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Type -->
                    <div>
                        <label for="type" class="block text-gray-700 font-semibold mb-2">Type</label>
                        <select name="type" id="type" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="Payment Gateway" selected>Payment Gateway</option>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-gray-700 font-semibold mb-2">Amount (KWD)</label>
                        <input type="number" step="0.01" name="amount" id="amount"
                            value="<?php echo e(old('amount', '0.25')); ?>" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                    <!-- Charges Type -->
                    <div>
                        <label for="charge_type" class="block text-gray-700 font-semibold mb-2">Charges Type</label>
                        <select name="charge_type" id="charge_type" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="Flat Rate" selected>Flat Rate</option>
                            <option value="Percent" selected>Percent</option>
                        </select>
                    </div>
                    <!-- Paid By -->
                    <div>
                        <label for="paid_by" class="block text-gray-700 font-semibold mb-2">Paid By</label>
                        <select name="paid_by" id="paid_by" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="Client" selected>Client</option>
                            <option value="Company" selected>Company</option>
                        </select>
                    </div>

                    



                    <!-- COA for Bank Account under Payment Gateway Fee -->
                    



                    <!-- COA for Bank Account -->
                    <div>
                        <label for="acc_bank_name" class="block text-gray-700 font-semibold mb-2">COA (Assets) for
                            Debited Bank Account</label>
                        <input list="bankAccountOptions" type="text" name="acc_bank_name" id="acc_bank_name"
                            value="<?php echo e(old('acc_bank_name')); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">

                        <datalist id="bankAccountOptions">
                            <?php $__currentLoopData = $coaBankAccount; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($account->name); ?>" data-id="<?php echo e($account->id); ?>"></option>
                                <!-- Accessing individual model's id -->
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </datalist>

                        <?php if($coaBankAccount->isEmpty()): ?>
                            <div class="mt-2 text-sm text-red-500">
                                No available records found. Please add via COA page.
                            </div>
                        <?php endif; ?>
                    </div>



                </div>

                <!-- Hidden input fields for COA IDs -->
                <input type="hidden" name="acc_fee_id" id="acc_fee_id" value="<?php echo e(old('acc_fee_id')); ?>">
                <input type="hidden" name="acc_bank_id" id="acc_bank_id" value="<?php echo e(old('acc_bank_id')); ?>">
                <input type="hidden" name="acc_fee_bank_id" id="acc_fee_bank_id" value="<?php echo e(old('acc_fee_bank_id')); ?>">
                <!-- Submit Button -->
                <div class="mt-4 flex space-x-4">
                    <a href="<?php echo e(route('charges.index')); ?>"
                        class="w-full text-center px-4 py-2 bg-gray-300 text-gray-800 font-semibold rounded-lg focus:outline-none focus:ring focus:ring-gray-100 focus:border-gray-300">
                        Cancel
                    </a>

                    <button type="submit"
                        class="w-full px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        Create Charge
                    </button>
                </div>
            </form>

            <!-- Error Message Display -->
            <?php if($errors->any()): ?>
                <div class="mt-4 text-red-500">
                    <ul>
                        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Bind the datalist inputs with hidden fields for IDs
            function bindDatalist(inputId, datalistId, hiddenId) {
                const input = document.getElementById(inputId);
                const hidden = document.getElementById(hiddenId);
                const datalist = document.getElementById(datalistId);

                if (!input || !hidden || !datalist) return; // Ensure elements exist

                // Find all the options in the datalist
                const options = datalist.querySelectorAll('option');

                // Function to update hidden input when a match is found
                function updateHiddenValue() {
                    const match = Array.from(options).find(option => option.value === input.value);
                    hidden.value = match ? match.getAttribute('data-id') :
                        ''; // If match found, update hidden input value
                }

                // Add event listeners to input to trigger hidden value update
                input.addEventListener('input', updateHiddenValue);
                input.addEventListener('change', updateHiddenValue);
            }

            // Initialize both bindings for the existing fields and the new field
            bindDatalist('acc_fee_name', 'paymentGatewayOptions', 'acc_fee_id');
            bindDatalist('acc_bank_name', 'bankAccountOptions', 'acc_bank_id');
            bindDatalist('acc_bank_fee_name', 'paymentGatewayBankAccOptions', 'acc_fee_bank_id');
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/charges/create.blade.php ENDPATH**/ ?>