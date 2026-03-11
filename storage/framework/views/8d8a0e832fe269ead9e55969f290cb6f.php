<div>
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Email</h2>
        <p class="text-sm text-gray-500 mt-1">Test sending payment emails</p>
    </div>

    <?php if(session('success')): ?>
    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
        <?php echo e(session('success')); ?>

    </div>
    <?php endif; ?>

    <?php if(session('error')): ?>
    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        <?php echo e(session('error')); ?>

    </div>
    <?php endif; ?>

    <form action="<?php echo e(route('system-settings.send-test-email')); ?>" method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                    Select Payment
                </label>
                <div class="relative">
                    <input type="text" id="paymentSearchDisplay" 
                        placeholder="Search payment..." 
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer bg-white"
                        onclick="togglePaymentDropdown()" readonly>
                    <input type="hidden" name="payment_id" id="selectedPaymentId" required>
                    <div class="absolute right-3 top-3 pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div id="paymentDropdown" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-72 overflow-hidden">
                        <div class="p-2 border-b bg-gray-50">
                            <input type="text" id="paymentSearchInput" placeholder="Type to search..."
                                class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                onkeyup="filterPaymentOptions()">
                        </div>
                        <div id="paymentOptions" class="overflow-y-auto max-h-56">
                            <?php $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="payment-option px-4 py-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100"
                                data-id="<?php echo e($payment->id); ?>"
                                data-voucher="<?php echo e(strtolower($payment->voucher_number)); ?>"
                                data-client="<?php echo e(strtolower($payment->client->full_name ?? '')); ?>"
                                data-email="<?php echo e($payment->client->email ?? ''); ?>"
                                onclick="selectPayment('<?php echo e($payment->id); ?>', '<?php echo e($payment->voucher_number); ?>', '<?php echo e($payment->client->email ?? ''); ?>')">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo e($payment->voucher_number); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo e($payment->client->full_name ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-green-600"><?php echo e(number_format($payment->amount, 2)); ?> <?php echo e($payment->currency); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo e($payment->created_at->format('d M Y')); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                </div>
                <?php $__errorArgs = ['payment_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="text-xs text-red-500 mt-1"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                    Email Type
                </label>
                <select name="email_type" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="payment_success">Payment Success</option>
                    <option value="payment_failure">Payment Failure</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                Recipient Email
            </label>
            <input type="email" name="email" id="recipientEmail" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Enter email address to receive test email"
                value="<?php echo e(old('email', auth()->user()->email)); ?>">
            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-xs text-red-500 mt-1"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                class="px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                Send Test Email
            </button>

            <button type="button" onclick="previewEmail()"
                class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                Preview Email
            </button>
        </div>
    </form>
</div>

<script>
    function togglePaymentDropdown() {
        const dropdown = document.getElementById('paymentDropdown');
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            document.getElementById('paymentSearchInput').focus();
        }
    }

    function filterPaymentOptions() {
        const searchValue = document.getElementById('paymentSearchInput').value.toLowerCase();
        const options = document.querySelectorAll('.payment-option');
        
        options.forEach(option => {
            const voucher = option.getAttribute('data-voucher');
            const client = option.getAttribute('data-client');
            if (voucher.includes(searchValue) || client.includes(searchValue)) {
                option.classList.remove('hidden');
            } else {
                option.classList.add('hidden');
            }
        });
    }

    function selectPayment(id, voucher, clientEmail) {
        document.getElementById('selectedPaymentId').value = id;
        document.getElementById('paymentSearchDisplay').value = voucher;
        document.getElementById('paymentDropdown').classList.add('hidden');
        
        if (clientEmail) {
            document.getElementById('recipientEmail').value = clientEmail;
        }
    }

    function previewEmail() {
        const paymentId = document.getElementById('selectedPaymentId').value;
        if (!paymentId) {
            alert('Please select a payment first');
            return;
        }
        window.open('<?php echo e(route("system-settings.preview-email")); ?>?payment_id=' + paymentId, '_blank');
    }

    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('paymentDropdown');
        const searchDisplay = document.getElementById('paymentSearchDisplay');
        
        if (dropdown && searchDisplay && !dropdown.contains(event.target) && event.target !== searchDisplay) {
            dropdown.classList.add('hidden');
        }
    });
</script>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/system-settings/partials/email.blade.php ENDPATH**/ ?>