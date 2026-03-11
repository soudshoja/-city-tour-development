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
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="<?php echo e(route('dashboard')); ?>" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <span>System Settings</span>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <span>Email Tester</span>
        </li>
    </ul>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">Email Tester</h2>
            <p class="text-sm text-gray-500 mt-1">Test sending payment emails before deploying to production</p>
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

        <div class="mt-8 pt-8 border-t border-gray-200">
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">WhatsApp PDF Sender</h3>
                <p class="text-sm text-gray-500 mt-1">Send payment receipt PDF via WhatsApp</p>
            </div>

            <form action="<?php echo e(route('system-settings.send-whatsapp-pdf')); ?>" method="POST" class="space-y-6">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="payment_id" id="whatsappPaymentId">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            Selected Payment
                        </label>
                        <input type="text" id="whatsappPaymentDisplay" readonly
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-gray-50 cursor-not-allowed"
                            placeholder="Select payment above first">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            Country Code
                        </label>
                        <input type="text" name="country_code" id="countryCode"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="+60" value="+60">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            Phone Number
                        </label>
                        <input type="text" name="phone" id="phoneNumber"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="193058463">
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit"
                        class="px-6 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Send PDF via WhatsApp
                    </button>

                    <button type="button" onclick="downloadPdf()"
                        class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Download PDF
                    </button>
                </div>
            </form>
        </div>
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
            
            document.getElementById('whatsappPaymentId').value = id;
            document.getElementById('whatsappPaymentDisplay').value = voucher;
            
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

        function downloadPdf() {
            const paymentId = document.getElementById('selectedPaymentId').value;
            if (!paymentId) {
                alert('Please select a payment first');
                return;
            }
            window.open('<?php echo e(route("system-settings.download-pdf")); ?>?payment_id=' + paymentId, '_blank');
        }

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('paymentDropdown');
            const searchDisplay = document.getElementById('paymentSearchDisplay');
            
            if (dropdown && searchDisplay && !dropdown.contains(event.target) && event.target !== searchDisplay) {
                dropdown.classList.add('hidden');
            }
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/system-settings/email-tester.blade.php ENDPATH**/ ?>