<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

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

    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">INVOICE</h1>
                <p class="text-sm text-gray-600">Invoice #<?php echo e($invoice->invoice_number); ?></p>
                <p class="text-sm text-gray-600">Date: <?php echo e($invoice->created_at->format('d M, Y')); ?></p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold text-gray-800"><?php echo e($invoice->agent->branch->company->name); ?></h2>
                <p class="text-sm text-gray-600">123 Main Street, City, Country</p>
                <p class="text-sm text-gray-600"><?php echo e($invoice->agent->branch->company->phone); ?></p>
                <p class="text-sm text-gray-600"><?php echo e($invoice->agent->branch->company->email); ?></p>
            </div>
        </div>

        <!-- Client Details -->
        <div class="mb-8">
            <h3 class="text-lg font-bold text-gray-800">Bill To:</h3>
            <p class="text-sm text-gray-600"><?php echo e($invoice->client->full_name ?? 'N/A'); ?></p>
            <p class="text-sm text-gray-600"><?php echo e($invoice->client->address ?? 'N/A'); ?></p>
            <p class="text-sm text-gray-600"><?php echo e($invoice->client->email ?? 'N/A'); ?></p>
        </div>

        <!-- Invoice Items -->
        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">Item Description</th>
                    <th class="px-4 py-2 border">Quantity</th>
                    <th class="px-4 py-2 border">Price</th>
                    <th class="px-4 py-2 border">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $invoiceDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="text-sm text-gray-700">
                        <td class="px-4 py-2 border"><?php echo e($detail->task_description ?? 'N/A'); ?></td>
                        <td class="px-4 py-2 border"><?php echo e($detail->quantity ?? 0); ?></td>
                        <td class="px-4 py-2 border"><?php echo e(number_format($detail->task_price ?? 0, 2)); ?></td>
                        <td class="px-4 py-2 border">
                            <?php echo e(number_format(($detail->quantity ?? 0) * ($detail->task_price ?? 0), 2)); ?></td>
                    </tr>
                    <input type="hidden" name="selected_items[]" value="<?php echo e($detail->id); ?>" form="paymentForm">
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="flex justify-end mb-8">
            <div class="w-1/3 text-sm">
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Subtotal:</span>
                    <span><?php echo e(number_format($invoice->amount, 2)); ?></span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Tax (<?php echo e($invoice->tax_rate); ?>%):</span>
                    <span><?php echo e(number_format($invoice->tax, 2)); ?></span>
                </div>
                <div class="flex justify-between py-2 font-bold text-gray-800">
                    <span>Total:</span>
                    <span><?php echo e(number_format($invoice->amount, 2)); ?></span>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="mb-8 inline-flex gap-2">
            <?php if($invoice->status === 'unpaid'): ?>
                <form id="paymentForm"
                    action="<?php echo e(route('payment.create', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>"
                    method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="total_amount" value="<?php echo e($invoice->amount); ?>">
                    <input type="hidden" name="client_email" value="<?php echo e($invoice->client->email); ?>">
                    <input type="hidden" name="client_name" value="<?php echo e($invoice->client->full_name); ?>">
                    <input type="hidden" name="client_phone" value="<?php echo e($invoice->client->phone); ?>">
                    <input type="hidden" name="payment_method" value="credit_card">
                    <button type="submit" id="payNowBtn" class="btn btn-primary">
                        Pay Now
                    </button>
                    <div id="loadingSpinner" class="hidden mt-2">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        Processing...
                    </div>
                </form>
                <?php if(auth()->user() &&
                        (auth()->user()->role === 'admin' || auth()->user()->role === 'company' || auth()->user()->role === 'agent')): ?>
                    <div class="flex gap-2 mt-2" id="invoice-link">
                        <p>
                            <?php echo e(route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>

                        </p>
                        <button
                            onclick="copyToClipboard('<?php echo e(route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>')">
                            <img src="<?php echo e(asset('images/svg/copy.svg')); ?>" alt="Copy Link" class="w-4 h-4">
                        </button>

                    </div>
                <?php endif; ?>
            <?php else: ?>
                <span class="text-green-600 font-bold">PAID</span>
            <?php endif; ?>
        </div>

        <!-- Signature Section -->
        <div class="flex justify-between items-center">
            <div class="text-sm">
                <p class="text-gray-600">If you have any questions about this invoice, please contact:</p>
                <p class="text-gray-600"><?php echo e($invoice->agent->branch->company->name); ?>,
                    <?php echo e($invoice->agent->branch->company->phone); ?>, <?php echo e($invoice->agent->branch->company->email); ?></p>
            </div>
            <div class="text-right">
                <p class="font-bold text-gray-800">Thank you for your business!</p>
            </div>
        </div>
    </div>


</body>

</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/clientInvoice.blade.php ENDPATH**/ ?>