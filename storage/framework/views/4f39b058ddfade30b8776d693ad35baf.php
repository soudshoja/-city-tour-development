<div>
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">WhatsApp</h2>
        <p class="text-xs text-gray-500 mt-1 italic">Powered By Resayil</p>
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

    <form action="<?php echo e(route('system-settings.send-whatsapp-pdf')); ?>" method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>

        <div class="grid grid-cols-1 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                    Select Payment
                </label>
                <div class="relative">
                    <input type="text" id="whatsappPaymentSearchDisplay" 
                        placeholder="Search payment..." 
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 cursor-pointer bg-white"
                        onclick="toggleWhatsAppPaymentDropdown()" readonly>
                    <input type="hidden" name="payment_id" id="whatsappPaymentId" required>
                    <div class="absolute right-3 top-3 pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div id="whatsappPaymentDropdown" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-72 overflow-hidden">
                        <div class="p-2 border-b bg-gray-50">
                            <input type="text" id="whatsappPaymentSearchInput" placeholder="Type to search..."
                                class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                onkeyup="filterWhatsAppPaymentOptions()">
                        </div>
                        <div id="whatsappPaymentOptions" class="overflow-y-auto max-h-56">
                            <?php $__currentLoopData = $payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="whatsapp-payment-option px-4 py-3 hover:bg-green-50 cursor-pointer border-b border-gray-100"
                                data-id="<?php echo e($payment->id); ?>"
                                data-voucher="<?php echo e(strtolower($payment->voucher_number)); ?>"
                                data-client="<?php echo e(strtolower($payment->client->full_name ?? '')); ?>"
                                data-phone="<?php echo e($payment->client->phone ?? ''); ?>"
                                data-country-code="<?php echo e($payment->client->country_code ?? '+60'); ?>"
                                onclick="selectWhatsAppPayment('<?php echo e($payment->id); ?>', '<?php echo e($payment->voucher_number); ?>', '<?php echo e($payment->client->phone ?? ''); ?>', '<?php echo e($payment->client->country_code ?? '+60'); ?>')">
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
        </div>

        <div id="fileStatusContainer" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <div class="flex items-start gap-3">
                <svg id="fileStatusIcon" class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="flex-1">
                    <h4 id="fileStatusTitle" class="text-sm font-semibold text-blue-900 mb-1">File Status</h4>
                    <div id="fileStatusContent" class="text-sm text-blue-800">
                        <div class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Checking file status...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

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

<script>
    function toggleWhatsAppPaymentDropdown() {
        const dropdown = document.getElementById('whatsappPaymentDropdown');
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            document.getElementById('whatsappPaymentSearchInput').focus();
        }
    }

    function filterWhatsAppPaymentOptions() {
        const searchValue = document.getElementById('whatsappPaymentSearchInput').value.toLowerCase();
        const options = document.querySelectorAll('.whatsapp-payment-option');
        
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

    function selectWhatsAppPayment(id, voucher, phone, countryCode) {
        document.getElementById('whatsappPaymentId').value = id;
        document.getElementById('whatsappPaymentSearchDisplay').value = voucher;
        document.getElementById('whatsappPaymentDropdown').classList.add('hidden');
        
        // Auto-fill phone number and country code
        if (phone) {
            document.getElementById('phoneNumber').value = phone;
        }
        if (countryCode) {
            document.getElementById('countryCode').value = countryCode;
        }

        // Check file status
        checkFileStatus(id);
    }

    function checkFileStatus(paymentId) {
        const container = document.getElementById('fileStatusContainer');
        const content = document.getElementById('fileStatusContent');
        
        // Show loading state
        container.classList.remove('hidden');
        content.innerHTML = `
            <div class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Checking file status...</span>
            </div>
        `;

        // Make AJAX request
        fetch('<?php echo e(route("system-settings.check-file-status")); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
            },
            body: JSON.stringify({ payment_id: paymentId })
        })
        .then(response => response.json())
        .then(data => {
            let html = `<p class="mb-2">${data.message}</p>`;
            
            if (data.has_file) {
                const statusColor = data.is_active ? 'text-green-700' : 'text-orange-700';
                const statusBadge = data.is_active 
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">' + (data.status || 'Inactive') + '</span>';
                
                html += `
                    <div class="mt-3 space-y-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">Status:</span>
                            ${statusBadge}
                        </div>
                        <div><span class="font-medium">File ID:</span> <code class="px-1.5 py-0.5 bg-gray-100 rounded">${data.file_id}</code></div>
                        ${data.file_data.filename ? `<div><span class="font-medium">Filename:</span> ${data.file_data.filename}</div>` : ''}
                        ${data.file_data.size ? `<div><span class="font-medium">Size:</span> ${(data.file_data.size / 1024).toFixed(2)} KB</div>` : ''}
                        ${data.file_data.deliveries !== undefined ? `<div><span class="font-medium">Deliveries:</span> ${data.file_data.deliveries}</div>` : ''}
                        <div><span class="font-medium">Cached at:</span> ${new Date(data.created_at).toLocaleString()}</div>
                        <div><span class="font-medium">Expires at:</span> ${new Date(data.expiry_date).toLocaleString()}</div>
                    </div>
                `;
            }
            
            content.innerHTML = html;
            
            // Update container, icon, and title colors based on status
            const icon = document.getElementById('fileStatusIcon');
            const title = document.getElementById('fileStatusTitle');
            
            if (data.is_active) {
                container.className = 'bg-green-50 border border-green-200 rounded-lg p-4 mb-4';
                icon.className = 'w-5 h-5 text-green-600 mt-0.5 flex-shrink-0';
                title.className = 'text-sm font-semibold text-green-900 mb-1';
                content.className = 'text-sm text-green-800';
            } else if (data.has_file) {
                container.className = 'bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4';
                icon.className = 'w-5 h-5 text-orange-600 mt-0.5 flex-shrink-0';
                title.className = 'text-sm font-semibold text-orange-900 mb-1';
                content.className = 'text-sm text-orange-800';
            } else {
                container.className = 'bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4';
                icon.className = 'w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0';
                title.className = 'text-sm font-semibold text-blue-900 mb-1';
                content.className = 'text-sm text-blue-800';
            }
        })
        .catch(error => {
            console.error('Error checking file status:', error);
            content.innerHTML = '<p class="text-red-700">Error checking file status. Please try again.</p>';
            container.className = 'bg-red-50 border border-red-200 rounded-lg p-4 mb-4';
        });
    }

    function downloadPdf() {
        const paymentId = document.getElementById('whatsappPaymentId').value;
        if (!paymentId) {
            alert('Please select a payment first');
            return;
        }
        window.open('<?php echo e(route("system-settings.download-pdf")); ?>?payment_id=' + paymentId, '_blank');
    }

    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('whatsappPaymentDropdown');
        const searchDisplay = document.getElementById('whatsappPaymentSearchDisplay');
        
        if (dropdown && searchDisplay && !dropdown.contains(event.target) && event.target !== searchDisplay) {
            dropdown.classList.add('hidden');
        }
    });
</script>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/system-settings/partials/whatsapp.blade.php ENDPATH**/ ?>