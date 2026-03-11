 <!DOCTYPE html>
 <html lang="ar" dir="rtl">

 <head>
     <meta charset="utf-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

     <script>
         // Check localStorage for the dark mode setting before the page is fully loaded
         if (localStorage.getItem('darkMode') === 'true') {
             document.documentElement.classList.add('dark');
         } else {
             document.documentElement.classList.remove('dark');
         }
     </script>

     <title><?php echo e(config('app.name', 'Laravel')); ?></title>

     <!-- Fonts -->
     <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">

     <link rel="preconnect" href="https://fonts.bunny.net">
     <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
     <link rel="icon" type="image/x-icon" href="<?php echo e(asset('images/City0logo.svg')); ?>" />
     <link rel="preconnect" href="https://fonts.googleapis.com" />
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
     <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap"
         rel="stylesheet" />

     <!-- CSS -->
     <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css']); ?>

     <!-- Scripts -->
     <script src="https://code.jquery.com/jquery-3.7.1.slim.js"
         integrity="sha256-UgvvN8vBkgO0luPSUl2s8TIlOSYRoGFAX4jlCIm9Adc=" crossorigin="anonymous"></script>

 </head>

 <body class="overflow-y-auto font-nunito antialiased bg-gray-100">

     <?php if(session('status')): ?>
         <div class="bg-green-100 text-green-700 p-4 rounded mb-4">
             <?php echo e(session('status')); ?>

         </div>
     <?php endif; ?>

     <?php if(session('error')): ?>
         <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
             <?php echo e(session('error')); ?>

         </div>
     <?php endif; ?>
     <?php if($invoicePartial->status === 'paid'): ?>
         <div
             class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg">
             <div class="flex items-center justify-between text-white">
                 <p class="text-3xl">تم الدفع</p>
                 <h5 class="text-2xl ltr:mr-auto rtl:mr-auto"></h5>
             </div>
         </div>
     <?php endif; ?>
     <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        <div class="flex justify-between items-center mb-10">
            <div class="text-right">
                <h1 class="text-2xl font-bold text-gray-800">الفاتورة</h1>
                <p class="text-sm text-gray-600"><?php echo e($invoice->invoice_number); ?></p>
                <p class="text-sm text-gray-600">التاريخ: <?php echo e($invoice->created_at->format('d M, Y')); ?></p>
            </div>
            <div>
                <img class="w-auto h-[90px] object-contain" src="<?php echo e($invoice->agent->branch->company->logo ? Storage::url($invoice->agent->branch->company->logo) : asset('images/UserPic.svg')); ?>" alt="Company logo" />
                <p class="text-base font-semibold"><?php echo e($invoice->agent->branch->company->name); ?></p>
            </div>
           
        </div>

        <div class="flex justify-between items-center mb-8">
            <div class="text-right">
                <h3 class="text-lg font-bold text-gray-800">الفاتورة مرسلة إلى:</h3>
                <p class="text-sm text-gray-600"><?php echo e($invoicePartial->client->full_name); ?></p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:<?php echo e($invoicePartial->client->email); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoicePartial->client->email ?? 'N/A'); ?>

                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:<?php echo e($invoicePartial->client->country_code); ?><?php echo e($invoicePartial->client->phone); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoicePartial->client->country_code ?? ''); ?><?php echo e($invoicePartial->client->phone ?? 'N/A'); ?>

                    </a>
                </p>
            </div>
            <div class="text-left max-w-xs">
                <h2 class="text-xl font-bold text-gray-800"><?php echo e($invoice->agent->branch->company->name); ?></h2>
                <p class="text-sm text-gray-600"><?php echo e($invoice->agent->branch->company->address); ?></p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:<?php echo e($invoice->agent->branch->company->email); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoice->agent->branch->company->email); ?>

                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:<?php echo e($invoice->agent->branch->company->phone); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoice->agent->branch->company->phone); ?>

                    </a>
                </p>
            </div>
        </div>

        <!-- Invoice Items -->
        <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo e(ucfirst($invoicePartial->type)); ?> Payment (<?php echo e($invoice->currency); ?>)</h3>
       
        <?php
            $creditBalance = \App\Models\Credit::getTotalCreditsByClient($invoicePartial->client->id);
         ?>
         <table class="min-w-full mb-8 border border-gray-200">
             <thead>
                 <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                     <th class="px-4 py-2 border">الوصف</th>
                     <th class="px-4 py-2 border">العدد</th>
                     <th class="px-4 py-2 border">السعر</th>
                     <th class="px-4 py-2 border">المجموع</th>
                 </tr>
             </thead>
             <tbody>
                 <?php $__currentLoopData = $invoiceDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                     <tr class="text-sm text-gray-700">
                         <td class="px-4 py-2 border"><?php echo e($detail->task_description ?? 'N/A'); ?>

                             <p>
                                 <br>المعلومات: <?php echo e($detail->task->additional_info); ?>

                                 <br>النوع: <?php echo e(ucfirst($detail->task->type)); ?>

                                 <br>المكان: <?php echo e($detail->task->venue); ?>

                                 <br>ملاحظات: <?php echo e($detail->client_notes ?? 'N/A'); ?>

                             </p>
                         </td>
                         <td class="px-4 py-2 border"><?php echo e($detail->quantity ?? 1); ?></td>
                         <td class="px-4 py-2 border"><?php echo e(number_format($invoicePartial->amount ?? 0, 3)); ?></td>
                         <td class="px-4 py-2 border">
                             <?php echo e(number_format(($detail->quantity ?? 1) * ($invoicePartial->amount ?? 0), 3, '.', ',')); ?>


                         </td>
                     </tr>
                     <!--  <input type="hidden" name="selected_items[]" value="<?php echo e($detail->id); ?>" form="paymentForm"> -->
                 <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
             </tbody>
         </table>

         <!-- Totals Section -->
         <div class="flex justify-start mb-8">
             <div class="w-1/3 text-sm">
                 <div class="flex justify-between py-2 border-b border-gray-200">
                     <span>المجموع الفرعي:</span>
                     <span><?php echo e(number_format($invoicePartial->status === 'paid' ? $invoicePartial->amount - $invoicePartial->service_charge : $invoicePartial->amount, 3)); ?></span>
                 </div>
                 <?php if($checkUtilizeCredit && $checkUtilizeCredit->count()): ?>
                     <?php $__currentLoopData = $checkUtilizeCredit; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $credit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                         <div class="flex justify-between py-2 border-b border-gray-200">
                             <span>محفظة العميل (<?php echo e($credit->created_at->format('d M Y')); ?>):</span>
                             <span><?php echo e(number_format($credit->amount, 3)); ?></span>
                         </div>
                     <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                 <?php endif; ?>
                 <div class="flex justify-between py-2 border-b border-gray-200">
                     <span>الضريبة (<?php echo e($invoice->tax_rate); ?>%):</span>
                     <span><?php echo e(number_format($invoice->tax, 3)); ?></span>
                 </div>
                 <?php if(isset($gatewayFee['paid_by']) && $gatewayFee['paid_by'] !== 'Company' && $invoicePartial->service_charge > 0): ?>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>رسوم الخدمة:</span>
                    <span><?php echo e(number_format($invoicePartial->service_charge, 3)); ?></span>
                </div>
                <?php endif; ?>
                 <div class="flex justify-between py-2 font-bold text-gray-800">
                     <span>المجموع:</span>
                     <span><?php echo e(number_format($invoicePartial->final_amount - abs($checkUtilizeCredit->sum('amount')) ?? 0, 3)); ?></span>
                 </div>
             </div>
         </div>

         <!-- Payment Details -->
         <div class="mb-8 inline-flex gap-2">
             <?php if($invoicePartial->status === 'unpaid'): ?>
                 <?php if(auth()->check()): ?>
                     <form action="<?php echo e(route('resayil.share-partial-link')); ?>" method="POST">
                         <?php echo csrf_field(); ?>
                         <input type="hidden" name="client_id" value='<?php echo e($invoicePartial->client->id); ?>'>
                         <input type="hidden" name="invoiceNumber" value='<?php echo e($invoicePartial->invoice_number); ?>'>
                         <button type="submit"
                             class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-black">
                             أرسل الفاتورة إلى العميل
                         </button>
                     </form>
                 <?php endif; ?>
                 <form id="paymentForm"
                     action="<?php echo e(route('payment.create', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>"
                     method="POST">
                     <?php echo csrf_field(); ?>
                     <input type="hidden" name="total_amount" value="<?php echo e($invoicePartial->final_amount - abs($checkUtilizeCredit->sum('amount'))); ?>">
                     <input type="hidden" name="client_email" value="<?php echo e($invoicePartial->client->email); ?>">
                     <input type="hidden" name="client_name" value="<?php echo e($invoicePartial->client->full_name); ?>">
                     <input type="hidden" name="client_phone" value="<?php echo e($invoicePartial->client->phone); ?>">
                     <input type="hidden" name="payment_gateway" value="<?php echo e($invoicePartial->payment_gateway); ?>">
                     <input type="hidden" name="payment_method" value="<?php echo e($invoicePartial->payment_method); ?>">
                    <?php if($canGenerateLink): ?>
                     <div class="flex items-center gap-2">
                         <button type="submit" id="payNowBtn"
                             class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-black">
                             إدفع الآن
                         </button>
                     </div>
                    <?php else: ?>
                    <div class="p-2 rounded-lg border border-gray-300 text-gray-700 flex items-center gap-2 text-xs sm:text-sm">
                       هذه الفاتورة مدفوعة عبر نظام الدفع الإلكتروني <?php echo e($invoicePartial->payment_gateway); ?> للمدفوعات. يرجى التواصل مع وكيلكم للحصول على المساعدة.
                    </div>
                    <?php endif; ?>
                     <div id="loadingSpinner" class="hidden mt-2">
                         <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                         قيد المعالجة...
                     </div>
                 </form>

                 <?php if(auth()->user() &&
                         (auth()->user()->role === 'admin' || auth()->user()->role === 'company' || auth()->user()->role === 'agent')): ?>
                     <div class="flex gap-2 mt-2" id="invoice-link">
                         <p>
                             <?php echo e(route('invoice.show', ['invoiceNumber' => $invoice->invoice_number])); ?>

                         </p>
                         <button
                             onclick="copyToClipboard('<?php echo e(route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>')">
                             <img src="<?php echo e(asset('images/svg/copy.svg')); ?>" alt="Copy Link" class="w-4 h-4">
                         </button>

                     </div>
                 <?php endif; ?>


                 <!-- <div x-data="{ open: false }">
                     <?php if($creditBalance > 0): ?>
                         <div class="flex items-center gap-2">
                             <button @click="open = true" type="button"
                                 class="city-light-yellow hover:text-[#004c9e]
                                 rounded-full flex items-center justify-center peer-checked:ring-2
                                 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border
                                 border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f]
                                 hover:shadow-xl hover:text-black">
                                 Pay Now with Credit (Balance: <?php echo e(number_format($creditBalance, 3)); ?> KWD)
                             </button>
                         </div>
                     <?php endif; ?>

                     <div x-show="open" x-cloak
                         class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                         <div @click.away="open = false" class="bg-white p-6 rounded shadow max-w-md w-full">
                             <h2 class="text-lg font-semibold mb-4">Confirm Credit Use</h2>
                             <p class="text-sm mb-6">Use credit balance to pay this invoice split?</p>
                             <div class="flex justify-end space-x-3">
                                 <button @click="open = false"
                                     class="px-4 py-2 text-sm bg-gray-300 rounded">No</button>
                                 <?php
                                     $checkBalance =
                                         $invoicePartial->amount >= $creditBalance
                                             ? $creditBalance
                                             : $invoicePartial->amount;
                                 ?>
                                 <form method="POST"
                                     action="<?php echo e(route('credits.useCreditNow', [
                                         'invoice' => $invoicePartial->invoice_id,
                                         'invoicePartial' => $invoicePartial->id,
                                         'balanceCredit' => $checkBalance,
                                     ])); ?>">
                                     <?php echo csrf_field(); ?>
                                     <button type="submit"
                                         class="px-4 py-2 text-sm bg-blue-600 text-white rounded">Yes</button>
                                 </form>
                             </div>
                         </div>
                     </div>
                 </div> -->


                 <div class="flex items-center gap-2">
                     <span id="totalAmountDisplay" class="text-lg font-semibold text-gray-800">
                        <?php echo e(number_format($invoicePartial->final_amount - abs($checkUtilizeCredit->sum('amount')), 3)); ?>

                     </span>
                 </div>
             <?php else: ?>
                 <span class="text-green-600 font-bold">تم الدفع</span>
             <?php endif; ?>
         </div>

         <!-- Signature Section -->
         <div class="flex justify-between items-center">
             <div class="text-sm">
                 <p class="text-gray-600">إذا كانت لديكم أي استفسارات حول هذه الفاتورة، يرجى التواصل معنا.:</p>
                 <p class="text-gray-600"><?php echo e($invoice->agent->branch->company->name); ?>,
                     <?php echo e($invoice->agent->branch->company->phone); ?>, <?php echo e($invoice->agent->branch->company->email); ?></p>
             </div>
        
             <div class="text-right">
                <div class="flex justify-end mb-4">
                    <button
                        onclick="window.open('<?php echo e(route('invoice.split', ['invoiceNumber' => $invoicePartial->invoice_number,'clientId' => $invoicePartial->client_id,'partialId' => $invoicePartial->id])); ?>', '_blank')"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Show Invoice in English
                    </button>
                </div>
                 <p class="font-bold text-gray-800">شكراً لتعاملكم معنا!</p>
             </div>
         </div>
     </div>

     <script>
         let invoicePartial = <?php echo json_encode($invoicePartial, 15, 512) ?>;
         const paymentForm = document.getElementById('paymentForm');
         addHiddenInput("invoice_partial_id", invoicePartial.id, paymentForm);
         console.log("split blade");

         function addHiddenInput(name, value, form) {
             // Check if the hidden input already exists
             let existingInput = form.querySelector(`input[name="${name}"][value="${value}"]`);
             if (!existingInput) {
                 const hiddenInput = document.createElement("input");
                 hiddenInput.type = "hidden";
                 hiddenInput.name = name;
                 hiddenInput.value = value;
                 form.appendChild(hiddenInput);
             }
         }

         $(document).ready(function() {
             let selectedTotal = 0;
             const selectedItems = [];

             $('.item-select').change(function() {
                 const itemId = $(this).data('id');
                 const itemTotal = parseFloat($(this).data('total'));

                 if (this.checked) {
                     selectedTotal += itemTotal;
                     selectedItems.push(itemId);
                 } else {
                     selectedTotal -= itemTotal;
                     const index = selectedItems.indexOf(itemId);
                     if (index > -1) selectedItems.splice(index, 1);
                 }

                 $('#selectedTotal').text(selectedTotal.toFixed(3));
                 $('#selectedItems').val(selectedItems.join(','));
                 $('#totalAmount').val(selectedTotal.toFixed(3));
             });
         });
     </script>

     <script src="https://unpkg.com/alpinejs" defer></script>

 </body>

 </html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/split-arabic.blade.php ENDPATH**/ ?>