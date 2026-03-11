<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->getLocale() === 'ar' ? 'rtl' : 'ltr'); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <title><?php echo e(config('app.name', 'Laravel')); ?></title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="<?php echo e(asset('images/City0logo.svg')); ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css']); ?>
</head>

<?php
    $isRtl = app()->getLocale() === 'ar';
    $textAlign = $isRtl ? 'text-right' : 'text-left';
    $textAlignReverse = $isRtl ? 'text-left' : 'text-right';
    $marginStart = $isRtl ? 'ml-3' : 'mr-3';
?>

<?php if($payment->status === 'completed'): ?>
<div class="mb-2 max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg">
    <div class="flex items-center justify-between text-white">
        <p class="text-3xl"><?php echo e(__('invoice.paid')); ?></p>
    </div>
</div>
<?php endif; ?>

<body class="overflow-y-auto font-nunito antialiased bg-gray-100 py-10">
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        <?php if(session('status')): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded mb-4"><?php echo e(session('status')); ?></div>
        <?php endif; ?>

        <?php if(session('error')): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-4"><?php echo e(session('error')); ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div class="flex justify-between items-center mb-10">
            <div class="<?php echo e($textAlign); ?>">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo e(__('invoice.payment_voucher')); ?></h1>
                <p class="text-sm text-gray-600"><?php echo e($payment->voucher_number); ?></p>
                <p class="text-sm text-gray-600"><?php echo e(__('invoice.date')); ?>: <?php echo e($payment->created_at->format('d M Y')); ?></p>
            </div>

            <div>
                <img class="w-auto h-[95px] object-contain" src="<?php echo e($payment->agent->branch->company->logo ? Storage::url($payment->agent->branch->company->logo) : asset('images/UserPic.svg')); ?>" alt="Company logo" />
            </div>
        </div>

        <!-- Billed To & Company Info -->
        <div class="flex justify-between items-start mb-8">
            <div class="<?php echo e($textAlign); ?>">
                <h3 class="text-lg font-bold text-gray-800 mb-1"><?php echo e(__('invoice.billed_to')); ?></h3>
                <p class="text-sm text-gray-600"><?php echo e($payment->client->full_name); ?></p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:<?php echo e($payment->client->email); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($payment->client->email); ?>

                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:<?php echo e($payment->agent->branch->company->phone); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($payment->client->country_code); ?><?php echo e($payment->client->phone); ?>

                    </a>
                </p>
            </div>
            <div class="max-w-xs <?php echo e($textAlignReverse); ?>">
                <h2 class="text-xl font-bold text-gray-800"><?php echo e($payment->agent->branch->company->name); ?></h2>
                <p class="text-sm text-gray-600 break-words">
                    <?php echo e($payment->agent->branch->company->address); ?>

                </p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:<?php echo e($payment->agent->branch->company->email); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($payment->agent->branch->company->email); ?>

                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:<?php echo e($payment->agent->branch->company->phone); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($payment->agent->branch->company->phone); ?>

                    </a>
                </p>
            </div>
        </div>

        <!-- Payment Details Table -->
        <table class="w-full text-sm <?php echo e($textAlign); ?> text-gray-700 border border-gray-300 mb-5">
            <thead class="bg-gray-100">
                <tr>
                    <th colspan="2" class="py-3 px-4 text-lg font-semibold <?php echo e($textAlign); ?>"><?php echo e(__('invoice.payment_details')); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="py-3 px-4"><?php echo e(__('invoice.client_name')); ?></td>
                    <td class="py-3 px-4 <?php echo e($textAlignReverse); ?>"><?php echo e($payment->client->full_name); ?></td>
                </tr>
                <?php if($payment->status === 'completed' && $payment->paymentMethod): ?>
                <tr>
                    <td class="py-3 px-4"><?php echo e(__('invoice.payment_method')); ?></td>
                    <td class="py-3 px-4 <?php echo e($textAlignReverse); ?>"><?php echo e($payment->paymentMethod->english_name ?? '-'); ?></td>
                </tr>
                <?php endif; ?>
                <?php if(!empty($payment->payment_reference)): ?>
                <tr>
                    <?php if($payment->payment_gateway === 'MyFatoorah'): ?>
                        <?php if(empty($payment->invoice_reference) && empty($payment->auth_code) && empty($invoiceRef)): ?>
                        <td class="py-3 px-4"><?php echo e(__('invoice.invoice_id')); ?></td>
                        <?php else: ?>
                        <td class="py-3 px-4"><?php echo e(__('invoice.payment_reference')); ?></td>
                        <?php endif; ?>
                    <?php else: ?>
                    <td class="py-3 px-4"><?php echo e(__('invoice.payment_reference')); ?></td>
                    <?php endif; ?>
                    <td class="py-3 px-4 <?php echo e($textAlignReverse); ?>"><?php echo e($payment->payment_reference); ?></td>
                </tr>
                <?php if($payment->payment_gateway === 'MyFatoorah' && $payment->status === 'completed' && !empty($invoiceRef)): ?>
                <tr>
                    <td class="py-3 px-4"><?php echo e(__('invoice.invoice_reference')); ?></td>
                    <td class="py-3 px-4 <?php echo e($textAlignReverse); ?>"><?php echo e($invoiceRef); ?></td>
                </tr>
                <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if($payment->paymentItems && $payment->paymentItems->count() > 0): ?>
        <div class="mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-3 <?php echo e($textAlign); ?>"><?php echo e(__('invoice.payment_items')); ?></h3>
            <div class="overflow-x-auto border border-gray-300 rounded-lg">
                <table class="w-full text-sm <?php echo e($textAlign); ?> text-gray-700">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-3 px-4 font-semibold <?php echo e($textAlign); ?>"><?php echo e(__('invoice.product_name')); ?></th>
                            <th class="py-3 px-4 font-semibold <?php echo e($textAlign); ?>"><?php echo e(__('invoice.quantity')); ?></th>
                            <th class="py-3 px-4 font-semibold <?php echo e($textAlign); ?>"><?php echo e(__('invoice.unit_price')); ?></th>
                            <th class="py-3 px-4 font-semibold <?php echo e($textAlign); ?>"><?php echo e(__('invoice.extended_amount')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $payment->paymentItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="border-t border-gray-200">
                            <td class="py-3 px-4"><?php echo e($item->product_name); ?></td>
                            <td class="py-3 px-4"><?php echo e(number_format($item->quantity, 3)); ?></td>
                            <td class="py-3 px-4"><?php echo e(number_format($item->unit_price, 3)); ?> <?php echo e($item->currency); ?></td>
                            <td class="py-3 px-4 font-semibold"><?php echo e(number_format($item->extended_amount, 3)); ?> <?php echo e($item->currency); ?></td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                        <tr>
                            <td colspan="3" class="py-3 px-4 <?php echo e($textAlignReverse); ?> font-bold text-gray-800"><?php echo e(__('invoice.total')); ?>:</td>
                            <td class="py-3 px-4 font-bold text-gray-900">
                                <?php echo e(number_format($payment->paymentItems->sum('extended_amount'), 3)); ?> <?php echo e($payment->currency); ?>

                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if($payment->status !== 'completed' && $payment->availablePaymentMethods && $payment->availablePaymentMethods->isNotEmpty()): ?>
        <div class="border rounded-lg overflow-hidden">
            <div class="bg-gray-100 p-4 font-semibold text-lg border-b border-gray-300 <?php echo e($textAlign); ?>">
                <?php echo e(__('invoice.choose_payment_method')); ?>

            </div>
            <div class="p-4" id="payment-methods-container">
                <?php $__currentLoopData = $payment->availablePaymentMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="flex items-center p-4 border rounded-lg mb-3 cursor-pointer hover:bg-gray-50 transition-colors <?php echo e($method->is_active ? 'border-gray-300' : 'border-red-300 bg-red-50'); ?>"
                    for="payment_method_<?php echo e($method->id); ?>">
                    <input
                        type="radio"
                        name="selected_payment_method"
                        id="payment_method_<?php echo e($method->id); ?>"
                        value="<?php echo e($method->id); ?>"
                        data-final-amount="<?php echo e(number_format($method->final_amount, 3, '.', '')); ?>"
                        <?php echo e($index === 0 ? 'checked' : ''); ?>

                        <?php echo e(!$method->is_active ? 'disabled' : ''); ?>

                        class="w-4 h-4 text-blue-600 focus:ring-blue-500 <?php echo e($marginStart); ?>">
                    <div>
                        <span class="font-medium text-gray-800">
                            <?php echo e($method->paymentMethodGroup ? $method->paymentMethodGroup->name : 'unknown'); ?>

                        </span>
                        <?php if(!$method->is_active): ?>
                        <span class="block text-xs text-red-600"><?php echo e(__('invoice.currently_unavailable')); ?></span>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($payment->terms_conditions && $payment->status === 'completed'): ?>
        <!-- tnc modal -->
        <div class="mt-6 text-sm text-gray-600 <?php echo e($textAlign); ?>" x-data="{ TNCPreviewModal: false }">
            <span>
                <?php echo e(__('invoice.tnc_agreed')); ?>

            </span>
            <span type="button" @click="TNCPreviewModal = true" class="text-blue-600 hover:underline font-medium cursor-pointer">
                <?php echo e(__('invoice.tnc_title')); ?>

            </span>
            <span>
                <?php echo e(__('invoice.tnc_agreed_2')); ?>

            </span>
            <!-- Modal -->
            <div x-show="TNCPreviewModal" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.away="TNCPreviewModal = false">
                <div class="bg-white rounded-2xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col shadow-2xl">
                    <div class="px-6 pt-5 pb-4 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900"><?php echo e(__('invoice.tnc_title')); ?></h3>
                            <p class="text-xs text-gray-500 italic mt-0.5"><?php echo e(__('invoice.tnc_subtitle')); ?></p>
                        </div>
                        <button type="button" @click="TNCPreviewModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="px-6 py-4 overflow-y-auto flex-1 border-t border-gray-200">
                        <div class="prose prose-sm text-gray-600 whitespace-pre-wrap"><?php echo e($payment->terms_conditions); ?></div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                        <button type="button" @click="TNCPreviewModal = false" class="px-4 py-2 text-sm bg-gray-100 text-gray-600 font-medium rounded-full shadow-md hover:text-gray-800">
                            <?php echo e(__('invoice.close')); ?>

                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start mb-8 mt-10">
            <div class="md:col-span-2">
                <?php if($payment->status === 'completed'): ?>
                <span class="inline-flex items-center px-3 py-1 text-green-700 font-semibold text-lg">
                    <?php echo e(__('invoice.paid')); ?>

                </span>
                <?php else: ?>
                    <?php if($payment->notes && $payment->notes !== ''): ?>
                    <div class="<?php echo e($textAlign); ?> max-w-xs">
                        <h3 class="text-lg text-gray-800">
                            <?php echo e(__('invoice.notes_from_agent', ['name' => $payment->agent->name])); ?>

                        </h3>
                        <p class="text-sm text-gray-600 mt-1 break-words">
                            <?php echo e($payment->notes); ?>

                        </p>
                    </div>
                    <?php else: ?>
                    <p class="text-sm text-gray-600 mt-1"></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php
                if ($payment->status === 'completed') {
                    $formattedAmount = number_format($payment->amount + $payment->service_charge, 3) . ' ' . $payment->currency;
                } else {
                    $hasAvailablePaymentMethods = $payment->availablePaymentMethods && $payment->availablePaymentMethods->isNotEmpty();
                    $displayAmount = $hasAvailablePaymentMethods 
                        ? $payment->availablePaymentMethods->first()->final_amount 
                        : $payment->amount + $payment->service_charge;
                    $formattedAmount = number_format($displayAmount, 3) . ' ' . $payment->currency;
                }
            ?>

            <div class="md:col-span-1 w-full text-sm">
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span><?php echo e(__('invoice.amount')); ?>:</span>
                    <span id="display-amount"><?php echo e($formattedAmount); ?></span>
                </div>
                <div class="flex justify-between items-center py-2 font-bold text-gray-800">
                    <span><?php echo e(__('invoice.total')); ?>:</span>
                    <span id="total-amount"><?php echo e($formattedAmount); ?></span>
                </div>
            </div>
        </div>

        <!-- TnC & Pay Now -->
        <?php if($payment->terms_conditions && $payment->status != 'completed'): ?>
        <div class="md:col-span-3 w-full mt-2" x-data="{ TNCModal: false, agreed: false }">
            <div class="rounded-xl p-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            id="agree-modal"
                            x-model="agreed"
                            @click.prevent="TNCModal = true"
                            :class="agreed ? 'text-blue-600' : 'text-gray-400'"
                            class="w-4 h-4 border-gray-300 rounded focus:ring-blue-500 cursor-pointer">
                        <span class="text-sm text-gray-700">
                            <?php echo e(__('invoice.tnc_read_agree')); ?>

                            <button type="button" @click.stop.prevent="TNCModal = true" class="text-blue-600 hover:underline font-medium">
                                <?php echo e(__('invoice.tnc_title')); ?>

                            </button>
                        </span>
                    </div>

                    <?php if (! ($payment->status === 'completed' || $payment->is_disabled)): ?>
                    <form action="<?php echo e(route('payment.link.multi-initiate')); ?>" method="POST" class="flex-shrink-0">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="payment_id" value="<?php echo e($payment->id); ?>">
                        <input type="hidden" name="payment_method_id" id="payment_method_input_tnc">
                        <button :type="agreed ? 'submit' : 'button'"
                            @click="if(!agreed) TNCModal = true"
                            :class="agreed ? 'city-light-yellow hover:text-white hover:bg-[#004c9e]' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                            class="w-full md:w-auto rounded-full border border-gray-300 px-6 py-2 shadow-md font-semibold transition-colors">
                            <?php echo e(__('invoice.pay_now')); ?>

                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal -->
            <div x-show="TNCModal" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.away="TNCModal = false">
                <div class="bg-white rounded-2xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col shadow-2xl">
                    <div class="px-6 pt-5 pb-4 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900"><?php echo e(__('invoice.tnc_title')); ?></h3>
                            <p class="text-xs text-gray-500 italic mt-0.5"><?php echo e(__('invoice.tnc_subtitle')); ?></p>
                        </div>
                        <button type="button" @click="TNCModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="px-6 py-4 overflow-y-auto flex-1 border-t border-gray-200">
                        <div class="prose prose-sm text-gray-600 whitespace-pre-wrap"><?php echo e($payment->terms_conditions); ?></div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-between">
                        <button type="button" @click="TNCModal = false" class="px-4 py-2 text-sm bg-gray-100 text-gray-600 font-medium rounded-full shadow-md hover:text-gray-800">
                            <?php echo e(__('invoice.close')); ?>

                        </button>
                        <button type="button" @click="agreed = true; TNCModal = false" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-full shadow-md hover:bg-blue-700">
                            <?php echo e(__('invoice.agree')); ?>

                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <?php if (! ($payment->status === 'completed' || $payment->is_disabled)): ?>
        <div class="md:col-span-3 w-full mt-2 flex justify-end">
            <form action="<?php echo e(route('payment.link.multi-initiate')); ?>" method="POST" class="w-full md:w-auto">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="payment_id" value="<?php echo e($payment->id); ?>">
                <input type="hidden" name="payment_method_id" id="payment_method_input_non_tnc">
                <button type="submit"
                    class="w-full md:w-auto city-light-yellow hover:text-white hover:bg-[#004c9e] rounded-full border border-gray-300 px-6 py-2 shadow-md font-semibold">
                    <?php echo e(__('invoice.pay_now')); ?>

                </button>
            </form>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="space-y-2 text-center w-full mt-6">
            <div class="text-sm text-gray-600 w-full overflow-x-auto">
                <p><?php echo e(__('invoice.questions', ['name' => $payment->agent->name])); ?></p>
                <p>
                    <a href="mailto:<?php echo e($payment->agent->email); ?>" class="font-semibold hover:underline hover:text-blue-600">
                        <?php echo e($payment->agent->email); ?>

                    </a>
                    <?php if($payment->agent->phone_number): ?>
                    <?php echo e(__('invoice.or')); ?> <span class="font-semibold"><?php echo e($payment->agent->phone_number); ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const radioButtons = document.querySelectorAll('input[name="selected_payment_method"]');
                const tncInput = document.getElementById('payment_method_input_tnc');
                const nonTncInput = document.getElementById('payment_method_input_non_tnc');

                const checkedRadio = document.querySelector('input[name="selected_payment_method"]:checked');
                if (checkedRadio) {
                    if (tncInput) tncInput.value = checkedRadio.value;
                    if (nonTncInput) nonTncInput.value = checkedRadio.value;
                }

                radioButtons.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (tncInput) tncInput.value = this.value;
                        if (nonTncInput) nonTncInput.value = this.value;

                        const finalAmount = this.getAttribute('data-final-amount');
                        if (finalAmount) {
                            const formattedAmount = parseFloat(finalAmount).toFixed(3) + ' <?php echo e($payment->currency); ?>';
                            document.getElementById('display-amount').textContent = formattedAmount;
                            document.getElementById('total-amount').textContent = formattedAmount;
                        }
                    });
                });

                const forms = [
                    document.getElementById('payment-form-mobile'),
                    document.getElementById('payment-form-desktop')
                ];

                forms.forEach(form => {
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            const selectedMethod = document.querySelector('input[name="selected_payment_method"]:checked');
                            if (!selectedMethod) {
                                e.preventDefault();
                                alert('<?php echo e(__("invoice.select_payment_method_alert")); ?>');
                                return false;
                            }
                        });
                    }
                });
            });
        </script>
    </div>
</body>

</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/payment/link/multi-payment.blade.php ENDPATH**/ ?>