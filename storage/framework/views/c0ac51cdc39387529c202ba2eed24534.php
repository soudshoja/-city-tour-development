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

    <style>
        input[type="checkbox"].disabled-checkbox {
            cursor: not-allowed;
            /* Change cursor to indicate it's not clickable */
            opacity: 0.6;
            /* Reduce opacity to indicate it's disabled */
            background-color: #e2e8f0;
            /* Light gray background to show it's disabled */
        }

        tr.disabled-row {
            cursor: not-allowed;
            /* Change cursor to indicate the row is not clickable */
            opacity: 0.6;
            /* Reduce opacity to indicate it's disabled */
            background-color: #e2e8f0;
            /* Light gray background to show the row is disabled */
        }

        /* Make the disabled checkbox also look like it's disabled */
        tr.disabled-row input[type="checkbox"] {
            cursor: not-allowed;
            /* Prevent interaction */
            opacity: 1;
            /* Keep checkbox opacity full */
        }
    </style>
    <!-- Scripts -->
    <script src="//unpkg.com/alpinejs" defer></script>

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
    <?php if(in_array($invoice->status, ['paid', 'paid by refund', 'refunded'])): ?>
        <div class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 text-white rounded-lg">
            <p class="text-3xl">تم الدفع</p>
            <?php if($invoice->status === 'paid'): ?>
                <p class="text-sm">This invoice has been fully paid</p>
            <?php elseif($invoice->status === 'paid by refund'): ?>
                <p class="text-sm">This invoice has been settled through an adjustment from a refund invoice</p>
            <?php elseif($invoice->status === 'refunded'): ?>
                <p class="text-sm">This invoice has already been refunded to the client</p>
            <?php endif; ?>
        </div>
    <?php elseif($invoice->status === 'partial'): ?>
    <div class="max-w-4xl mx-auto rounded-lg border border-yellow-300 bg-yellow-100 p-6 flex items-center rounded-lg">
        <div class="flex items-center gap-2 text-yellow-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zM9 5h2v5H9V5zm0 6h2v2H9v-2z" clip-rule="evenodd" />
            </svg>
            <div class="font-semibold">تم دفع الفاتورة جزئيا.</div>
            <div class="text-sm">تم سداد بعض الأقساط، والبعض الآخر لا يزال معلقًا. يمكنك المتابعة أدناه.</div>
        </div>
    </div>
    <?php endif; ?>
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        <!-- Header -->
        <div class="flex justify-between items-center mb-10">
            <div class="text-right">
                <h1 class="text-2xl font-bold text-gray-800">فاتورة</h1>
                <p class="text-sm text-gray-600"><?php echo e($invoice->invoice_number); ?></p>
                <p class="text-sm text-gray-600">التاريخ: <?php echo e($invoice->created_at->format('d M, Y')); ?></p>
            </div>    
            <div>
                <?php if (isset($component)) { $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72 = $attributes; } ?>
<?php $component = App\View\Components\ApplicationLogo::resolve(['companyLogo' => ''.e($company->logo).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('application-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ApplicationLogo::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-auto h-[90px] object-contain']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $attributes = $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $component = $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
                <p class="text-base font-semibold"><?php echo e($invoice->agent->branch->company->name); ?></p>
            </div>
            
        </div>

        <!-- Header Ends -->

        <div class="flex justify-between items-center mb-8">
            <div class="text-right">
                <h3 class="text-lg font-bold text-gray-800">الفاتورة مرسلة إلى:</h3>
                <p class="text-sm text-gray-600 text-justify"><?php echo e($invoice->client->full_name); ?></p>
                <p class="text-sm text-justify text-gray-600">
                    <a href="mailto:<?php echo e($invoice->client->email); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoice->client->email ?? 'N/A'); ?>

                    </a>
                </p>
                <p class="text-sm text-gray-600 text-justify">
                    <a href="tel:<?php echo e($invoice->client->country_code); ?><?php echo e($invoice->client->phone); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoice->client->country_code ?? ''); ?><?php echo e($invoice->client->phone ?? 'N/A'); ?>

                    </a>
                </p>
            </div>
            <div class="text-right max-w-xs">
                <h2 class="text-xl font-bold text-gray-800 text-justify text-end"><?php echo e($invoice->agent->branch->company->name); ?></h2>
                <p class="text-sm text-gray-600 text-justify text-end"><?php echo e($invoice->agent->branch->company->address); ?></p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:<?php echo e($invoice->agent->branch->company->email); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoice->agent->branch->company->email); ?>

                    </a>
                </p>
                <p class="text-sm text-gray-600 text-justify text-end">
                    <a href="tel:<?php echo e($invoice->agent->branch->company->phone); ?>" class="hover:underline hover:text-blue-600">
                        <?php echo e($invoice->agent->branch->company->phone); ?>

                    </a>
                </p>
            </div>
        </div>

        <?php if(in_array($invoice->payment_type, ['full', 'credit', 'cash'], true)): ?>
        <div class="flex justify-end mb-4">
            <h3 class="text-lg font-bold text-gray-800"><?php echo e(ucfirst($invoice->payment_type )); ?> Payment (<?php echo e($invoice->currency); ?>)</h3>
        </div>
        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">وصف الحجز</th>
                    <th class="px-4 py-2 border">العدد</th>
                    <th class="px-4 py-2 border">السعر</th>
                    <th class="px-4 py-2 border">المجموع</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $invoiceDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="text-sm text-gray-700">
                    <td class="px-4 py-2 border">
                        <?php if($detail->task->type === 'hotel'): ?>
                        <?php
                        $roomDetails = json_decode($detail->task->hotelDetails->room_details, true);
                        $passengerCount = count($roomDetails['passengers'] ?? []);
                        ?>
                        <p>
                            <br>اسم العميل: <?php echo e($detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A')); ?>

                            <br>اسم الفندق: <?php echo e($detail->task->hotelDetails->hotel->name ?? 'N/A'); ?>

                            <br>تسجيل دخول: <?php echo e($detail->task->hotelDetails->check_in ?? 'N/A'); ?>

                            <br>تسجيل خروج: <?php echo e($detail->task->hotelDetails->check_out ?? 'N/A'); ?>

                            <br>عدد الأشخاص: <?php echo e($passengerCount ?? $detail->task->number_of_pax ?? 'N/A'); ?>

                            <br>نوع الغرفة: <?php echo e($detail->task->hotelDetails->room_type ?? $detail->task->hotelDetails->room_category ?? 'N/A'); ?>

                        </p>
                        <?php elseif($detail->task->type === 'flight'): ?>
                        <p>
                            مرجع الحجز: <?php echo e($detail->task->gds_reference ?? 'N/A'); ?>

                            <br>اسم العميل: <?php echo e($detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A')); ?>

                            <br>خط الرحلة:
                            <?php echo e($detail->task->flightDetails->countryFrom->name ?? ''); ?>

                            (<?php echo e($detail->task->flightDetails->airport_from ?? ''); ?>)
                            →
                            <?php echo e($detail->task->flightDetails->countryTo->name ?? ''); ?>

                            (<?php echo e($detail->task->flightDetails->airport_to ?? ''); ?>)
                            <br>فئة الحجز(الدرجة): <?php echo e(ucfirst($detail->task->flightDetails->class_type ?? 'N/A')); ?>

                        </p>
                        <?php elseif($detail->task->type === 'visa'): ?>
                        <p>
                            اسم العميل: <?php echo e($detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A')); ?>

                            <br>نوع الفيزا: <?php echo e($detail->task->visaDetails->visa_type ?? 'N/A'); ?>

                            <br>رقم المرجع #: <?php echo e($detail->task->visaDetails->application_number ?? 'N/A'); ?>

                            <br>تاريخ انتهاء الصلاحية: <?php echo e(!empty($visa?->expiry_date) ? \Carbon\Carbon::parse($visa->expiry_date)->format('d M Y') : 'N/A'); ?>

                            <br>عدد مرات الدخول: <?php echo e($detail->task->visaDetails->number_of_entries ?? 'N/A'); ?>

                            <br>مدة الإقامة: <?php echo e($detail->task->visaDetails->stay_duration ?? 'N/A'); ?>

                            <br>الدولة المصدرة: <?php echo e($detail->task->visaDetails->issuing_country ?? 'N/A'); ?>

                        </p>
                        <?php elseif($detail->task->type === 'insurance'): ?>
                        <p>
                            اسم العميل: <?php echo e($detail->task->client_name ?? ($invoice->client->full_name ?? 'N/A')); ?>

                            <br>نوع التأمين: <?php echo e($detail->task->insuranceDetails->insurance_type ?? 'N/A'); ?>

                            <br>الوجهة: <?php echo e($detail->task->insuranceDetails->destination ?? 'N/A'); ?>

                            <br>نوع الخطة: <?php echo e($detail->task->insuranceDetails->plan_type ?? 'N/A'); ?>

                            <br>المدة: <?php echo e($detail->task->insuranceDetails->duration ?? 'N/A'); ?>

                            <br>الباقة: <?php echo e($detail->task->insuranceDetails->package ?? 'N/A'); ?>

                            <br>مرجع الوثيقة: <?php echo e($detail->task->insuranceDetails->document_reference ?? 'N/A'); ?>

                            <br>الإجازات المدفوعة: <?php echo e($detail->task->insuranceDetails->paid_leaves ?? 'N/A'); ?>

                        </p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2 border"><?php echo e($detail->quantity ?? 1); ?></td>
                    <td class="px-4 py-2 border"><?php echo e(number_format($detail->task_price ?? 0, 3)); ?></td>
                    <td class="px-4 py-2 border">
                        <?php echo e(number_format(($detail->quantity ?? 1) * ($detail->task_price ?? 0), 3, '.', ',')); ?>

                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>

       <?php
            $typeIsPartial = strcasecmp(trim($invoice->payment_type ?? ''), 'partial') === 0;

            // true when there are 2+ different gateways among partials
            $hasMismatch = collect($invoicePartials)
                ->pluck('payment_gateway')
                ->filter(fn ($g) => filled($g))
                ->unique()
                ->count() > 1;
        ?>

        <?php if($invoice->payment_type === 'partial' && !$hasMismatch): ?>
        <!-- Partial Payment Table -->
        <h3 class="text-lg font-bold text-gray-800 mb-4">الدفع الجزئي (<?php echo e($invoice->currency); ?>)</h3>

        <div class="mb-4">
            <h4 class="text-lg font-bold text-gray-800">الوصف</h4>
            <ul class="list-disc pl-6">
                <?php $__currentLoopData = $invoiceDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li class="text-sm text-gray-700">
                    <strong><?php echo e($detail->task_description ?? 'N/A'); ?></strong>:
                    <?php echo e($detail->quantity ?? 0); ?> (Note: <?php echo e($detail->client_notes ?? 'N/A'); ?>)
                </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>

        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">-- اختر --</th>
                    <th class="px-4 py-2 border">تاريخ إنتهاء الصلاحية</th>
                    <th class="px-4 py-2 border">الحالة</th>
                    <th class="px-4 py-2 border">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $invoicePartials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $partial): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="text-sm text-gray-700 <?php if($partial->status === 'paid'): ?> disabled-row <?php endif; ?>">
                    <td class="px-4 py-2 border">
                        <input type="checkbox" class="partial-checkbox" name="selected_partials[]"
                            value="<?php echo e($partial->id); ?>" data-amount="<?php echo e($partial->amount); ?>" data-final-amount="<?php echo e($partial->final_amount); ?>"
                            <?php if($partial->status == 'paid'): ?> disabled <?php endif; ?>>
                    </td>
                    <td class="px-4 py-2 border">
                        <?php echo e(\Carbon\Carbon::parse($partial->expiry_date)->format('d M, Y') ?? 'N/A'); ?>

                    </td>
                    <td class="px-4 py-2 border"><?php echo e($partial->status); ?></td>
                    <td class="px-4 py-2 border">
                        <?php if($partial->status !== 'paid'): ?>
                        <?php echo e(number_format($partial->final_amount ?? $partial->amount, 3)); ?>

                        <?php else: ?>
                        <?php echo e(number_format($partial->amount, 3)); ?>

                        <?php endif; ?>
                    </td>
                    <!-- <td class="px-4 py-2 border"><?php echo e(number_format($partial->amount ?? 0, 3)); ?></td> -->
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Partial Payment of Different Gateway -->
        <?php if($invoice->payment_type === 'partial' && $hasMismatch): ?>
            <h3 class="text-lg font-bold text-gray-800 mb-4">الدفع الجزئي (<?php echo e($invoice->currency); ?>)</h3>        

            <div class="mb-4">
            <h4 class="text-lg font-bold text-gray-800">الوصف</h4>
            <ul class="list-disc pl-6">
                <?php $__currentLoopData = $invoiceDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li class="text-sm text-gray-700">
                    <strong><?php echo e($detail->task_description ?? 'N/A'); ?></strong>:
                    <?php echo e($detail->quantity ?? 0); ?>

                </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>

        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">بوابة الدفع</th>
                    <th class="px-4 py-2 border">الرابط</th>
                    <th class="px-4 py-2 border">تاريخ إنتهاء الصلاحية</th>
                    <th class="px-4 py-2 border">الحالة</th>
                    <th class="px-4 py-2 border">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                ?>
                <?php $__currentLoopData = $invoicePartials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $partial): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                $creditBalance = \App\Models\Credit::getTotalCreditsByClient($partial->client->id);
                ?>

                <tr x-data="{ open: false }" class="text-sm text-gray-700 text-center">
                    <td class="px-4 py-2 border"><?php echo e($partial->payment_gateway ?? 'N/A'); ?></td>
                    <td class="px-4 py-2 border">
                        <a href="<?php echo e(route('invoice.split-arabic', ['invoiceNumber' => $partial->invoice_number, 'clientId' => $partial->client_id, 'partialId' => $partial->id])); ?>"
                            class="text-blue-500 underline" target="_blank">
                            View Details
                        </a>
                    </td>
                    <td class="px-4 py-2 border">
                        <?php echo e(\Carbon\Carbon::parse($partial->expiry_date)->format('d M, Y') ?? 'N/A'); ?>

                    </td>
                    <td class="px-4 py-2 border"> <?php echo e($partial->status); ?></td>
                    <td class="px-4 py-2 border">
                        <?php if($partial->status !== 'paid'): ?>
                        <?php echo e(number_format($partial->final_amount ?? $partial->amount, 3)); ?>

                        <?php else: ?>
                        <?php echo e(number_format($partial->amount, 3)); ?>

                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if($invoice->payment_type === 'split'): ?>
        <!-- Split Payment Table -->
        <h3 class="text-lg font-bold text-gray-800 mb-4">الدفع المقسم(<?php echo e($invoice->currency); ?>)</h3>

        <div class="mb-4">
            <h4 class="text-lg font-bold text-gray-800">الوصف</h4>
            <ul class="list-disc pl-6">
                <?php $__currentLoopData = $invoiceDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li class="text-sm text-gray-700">
                    <strong><?php echo e($detail->task_description ?? 'N/A'); ?></strong>:
                    <?php echo e($detail->quantity ?? 0); ?>

                </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>

        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">عدد التقسيمات #</th>
                    <th class="px-4 py-2 border">الرابط</th>
                    <th class="px-4 py-2 border">العميل</th>
                    <th class="px-4 py-2 border">تاريخ إنتهاء الصلاحية</th>
                    <th class="px-4 py-2 border">بوابة الدفع</th>
                    <th class="px-4 py-2 border">الحالة</th>
                    <th class="px-4 py-2 border">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                ?>
                <?php $__currentLoopData = $invoicePartials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $partial): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                $creditBalance = \App\Models\Credit::getTotalCreditsByClient($partial->client->id);
                ?>

                <tr x-data="{ open: false }" class="text-sm text-gray-700">
                    <td class="px-4 py-2 border">
                        <?php echo e($count); ?>

                    </td>
                    <td class="px-4 py-2 border">
                        <a href="<?php echo e(route('invoice.split-arabic', ['invoiceNumber' => $partial->invoice_number, 'clientId' => $partial->client_id, 'partialId' => $partial->id])); ?>"
                            class="text-blue-500 underline" target="_blank">
                            رؤية التفاصيل
                        </a>
                    </td>

                    <td class="px-4 py-2 border">
                        <?php echo e($partial->client->full_name); ?>


                        <?php if($creditBalance > 0 && $partial->status === 'unpaid'): ?>
                        <br>رصيد المحفظة: <?php echo e(number_format($creditBalance, 3)); ?> |
                        <button @click="open = true" type="button" class="text-blue-600 underline text">
                            استخدم الآن لسداد هذا الجزء؟
                        </button>
                        <?php endif; ?>

                        <!-- Modal -->
                        <div x-show="open" x-cloak
                            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
                            <div @click.away="open = false"
                                class="bg-white p-6 rounded shadow max-w-md w-full">
                                <h2 class="text-lg font-semibold mb-4">تأكيد استخدام المحفظة</h2>
                                <p class="text-sm mb-6">هل يمكنك استخدام رصيد المحفظة لسدادهذا الجزء من الفاتورة؟</p>
                                <div class="flex justify-end space-x-3">
                                    <button @click="open = false"
                                        class="px-4 py-2 text-sm bg-gray-300 rounded">No</button>
                                    <?php
                                    $checkBalance =
                                    $partial->amount >= $creditBalance
                                    ? $creditBalance
                                    : $partial->amount;
                                    ?>

                                    <form method="POST"
                                        action="<?php echo e(route('credits.useCreditNow', [
                                                    'invoice' => $partial->invoice_id,
                                                    'invoicePartial' => $partial->id,
                                                    'balanceCredit' => $checkBalance,
                                                ])); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit"
                                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded">
                                            نعم
                                        </button>
                                    </form>

                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2 border">
                        <?php echo e(\Carbon\Carbon::parse($partial->expiry_date)->format('d M, Y') ?? 'N/A'); ?>

                    </td>
                    <td class="px-4 py-2 border"><?php echo e($partial->payment_gateway); ?></td>
                    <td class="px-4 py-2 border"><?php echo e($partial->status); ?></td>
                    <td class="px-4 py-2 border">
                        <?php if($partial->status !== 'paid'): ?>
                        <?php echo e(number_format($partial->final_amount ?? $partial->amount, 3)); ?>

                        <?php else: ?>
                        <?php echo e(number_format($partial->amount, 3)); ?>

                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                $count++;
                ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>



        </table>
        <?php endif; ?>

        <!-- Totals Section -->
        <div class="flex mb-8">
            <div class="w-1/3 text-sm">
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>المجموع الفرعي:</span>
                    <span><?php echo e(number_format($invoice->sub_amount, 3)); ?></span>
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

                <?php if($invoice->status === 'paid' || $invoice->payment_type === 'split'): ?>
                <?php
                $paidServiceCharge = $invoice->invoicePartials->sum('service_charge');
                $paidTotalAmount = $invoice->invoicePartials->sum('amount');
                ?>
                <?php if($paidServiceCharge > 0): ?>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>رسوم الخدمة:</span>
                    <span><?php echo e(number_format($paidServiceCharge, 3)); ?></span>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <?php if(isset($totalGatewayFee['paid_by']) || $totalGatewayFee['paid_by'] !== 'Company'): ?>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>رسوم الخدمة <?php if(isset($totalGatewayFee['charge_type']) && $totalGatewayFee['charge_type'] === 'Percent'): ?> (%): <?php else: ?>: <?php endif; ?></span>
                    <span><?php echo e(number_format($totalGatewayFee['gatewayFee'], 3)); ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <div class="flex justify-between py-2 font-bold text-gray-800">
                    <span>المجموع الكلي:</span>
                    <span>
                        <?php echo e(number_format( (isset($totalGatewayFee['finalAmount']) ? $totalGatewayFee['finalAmount'] : $invoice->sub_amount) - abs($checkUtilizeCredit->sum('amount')), 3)); ?>

                    </span>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="mb-8 inline-flex gap-2">
            <?php if($invoice->status === 'unpaid' || $invoice->status === 'partial' || ($invoice->payment_type === 'partial' && !$hasMismatch)): ?>
            <?php if(auth()->check()): ?>

            <form id="whatsappForm" action="<?php echo e(route('resayil.share-invoice-link')); ?>" method="POST" onsubmit="showSpinner()">
                <?php echo csrf_field(); ?>
                <!-- Hidden Inputs -->
                <input type="hidden" name="client_id" id="clientid" value="<?php echo e($invoice->client->id ?? ''); ?>">
                <input type="hidden" name="invoiceNumber" value="<?php echo e($invoice->invoice_number); ?>">

                <button id="submitButton" type="submit"
                    class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                    <span id="buttonText">إرسال الفاتورة الى العميل</span>
                    <span id="spinner" class="hidden ml-2">
                        <svg class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 0-8-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"></path>
                        </svg>
                    </span>
                </button>
            </form>
            <?php endif; ?>
            <form id="paymentForm"
                action="<?php echo e(route('payment.create', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>"
                method="POST">
                <?php echo csrf_field(); ?>

                <input type="hidden" id="totalAmountInput" name="total_amount"
                    value="<?php echo e(number_format( (isset($totalGatewayFee['finalAmount']) ? $totalGatewayFee['finalAmount'] : $invoice->sub_amount) - abs($checkUtilizeCredit->sum('amount')), 3)); ?>">
                <input type="hidden" name="client_email" value="<?php echo e($invoice->client->email); ?>">
                <input type="hidden" name="client_name" value="<?php echo e($invoice->client->full_name); ?>">
                <input type="hidden" name="client_phone" value="<?php echo e($invoice->client->phone); ?>">
                <input type="hidden" name="payment_gateway" value="<?php echo e($invoice->invoicePartials->first()->payment_gateway); ?>">
                <input type="hidden" name="payment_method" value="<?php echo e($invoice->invoicePartials->first()->payment_method); ?>">

                <?php if($canGenerateLink): ?>
                <div class="flex items-center gap-2">
                    <?php if($invoice->payment_type !== 'split' && !($invoice->payment_type === 'partial' && $hasMismatch)): ?>
                    <button type="submit" id="payNowBtn"
                        class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                        ادفع الآن
                    </button>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <?php if(!in_array($invoice->payment_type, ['split', 'partial'], true)): ?>
                    <div class="p-2 rounded-lg border border-gray-300 text-gray-700 flex items-center gap-2 text-xs sm:text-sm">
                        تتم معالجة الدفعة لهذه الفاتورة عبر <?php echo e($invoice->invoicePartials->first()->payment_gateway); ?>. يُرجى التواصل مع وكيلك للحصول على المساعدة.
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

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
            <div class="flex items-center gap-2">
                <p><span class="text-green-600 font-bold">تم الدفع</span></p>
            </div>

            <?php endif; ?>
        </div>
        <!-- Signatdiure Section -->
        <div class="flex justify-between items-center">
            <div class="text-sm">
                <p class="text-gray-600">إذا كان لديك أي أسئلة بخصوص هذه الفاتورة، يرجى التواصل معنا:</p>
                <p class="text-gray-600"><?php echo e($invoice->agent->branch->company->name); ?>,
                    <?php echo e($invoice->agent->branch->company->phone); ?>, <?php echo e($invoice->agent->branch->company->email); ?>

                </p>
            </div>
            <div class="text-right">
                          <div class="flex justify-end mb-4">
        <button
            onclick="window.open('<?php echo e(route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>', '_blank')"
            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            Show Invoice in English
        </button>
    </div>
                <p class="font-bold text-gray-800">شكراً لتعاونك معنا</p>
            </div>
        </div>
    </div>
    <?php if($invoice->is_client_credit == 1): ?>
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg mt-6 text-center">
        <p class="text-lg font-semibold text-green-500">    تم تطبيق هذه الفاتورة على رصيد العميل.
        </p>
    </div>
    <?php endif; ?>
    <?php if($invoice->status === 'paid' || $invoice->status === 'partial'): ?>
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg mt-6">
        <div class="invoice">
            <div class="payment-status bg-green-100 p-6 rounded-lg mt-4">
                <h3 class="text-xl font-semibold text-green-700 mb-2">إيصال الدفع</h3>
            </div>

            <table class="min-w-full mb-8 border border-gray-200">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                        <th class="px-4 py-2 border">ايصال #</th>
                        <th class="px-4 py-2 border">المرجع</th>
                        <th class="px-4 py-2 border">تاريخ الدفع</th>
                        <th class="px-4 py-2 border">بوابة الدفع</th>
                        <th class="px-4 py-2 border">المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $paidPartials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $partial): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="text-sm text-gray-700">
                        <td class="px-4 py-2 border">
                            <?php if(optional($partial->payment)->voucher_number): ?>
                            <a href="<?php echo e(route('payment.link.show-arabic', ['companyId' => $companyId, 'voucherNumber' => $partial->payment->voucher_number])); ?>"
                                class="text-blue-500 underline" target="_blank"><?php echo e($partial->payment->voucher_number); ?>

                            </a>
                            <?php else: ?>
                            <a href="<?php echo e(route('clients.credits', $partial->client_id)); ?>" class="text-blue-500 underline" target="_blank">المحفظة</a>
                            <?php endif; ?>
                        </td>
                        <?php
                            $paymentReferenceCredit = \App\Models\Credit::getTotalUtilizeCreditsByClientPartial($partial->client_id, $partial->id);
                        ?>
                        <?php if($paymentReferenceCredit): ?>
                            <td class="px-4 py-2 border">محفظة العميل بواسطة<?php echo e($partial->client->full_name); ?>

                                (<?php echo e($paymentReferenceCredit); ?>)
                            </td>
                        <?php else: ?>
                            <td class="px-4 py-2 border"><?php echo e($partial->payment->payment_reference ?? 'N/A'); ?></td>
                        <?php endif; ?>
                        <td class="px-4 py-2 border">
                            <?php echo e($partial->payment ? \Carbon\Carbon::parse($partial->payment->payment_date)->format('d M, Y H:i') : \Carbon\Carbon::parse($partial->updated_at)->format('d M, Y H:i')); ?>

                        </td>
                        <?php if($paymentReferenceCredit): ?>
                            <td class="px-4 py-2 border">محفظة العميل</td>
                        <?php else: ?>
                            <td class="px-4 py-2 border"><?php echo e($partial->payment_gateway); ?></td>
                        <?php endif; ?>
                        <td class="px-4 py-2 border">
                            <?php echo e(number_format($partial->amount ?? 0, 3)); ?>

                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>

            <div class="flex justify-end mb-8">
                <div class="w-1/3 text-sm">
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span>الرصيد:</span>
                        <span id="balance"></span>
                    </div>
                </div>
            </div>

            <div class="thank-you mt-6 bg-gray-100 p-6 rounded-lg">
                <h4 class="text-xl font-semibold text-gray-800 mb-2">شكرا لك على الدفع!</h4>
                <p class="text-lg text-gray-600">نشكرك على تعاملك معنا! تم إرسال رسالة تأكيد إلى عنوانك.</p>
            </div>
        </div>



    </div>
    <?php endif; ?>


    <script>
        let invoice = <?php echo json_encode($invoice, 15, 512) ?>;
        let invoicePartials = <?php echo json_encode($invoicePartials, 15, 512) ?>;

        console.log('invoice', invoice);
        console.log('invoicePartials', invoicePartials);

        // Calculate the total paid amount from invoicePartials
        let totalPaidAmount = invoicePartials.filter(partial => partial.status === 'paid')
            .reduce((sum, partial) => sum + parseFloat(partial.amount), 0);

        let totalPaidServiceCharge = invoicePartials.filter(partial => partial.status === 'paid')
            .reduce((sum, partial) => sum + parseFloat(partial.service_charge), 0);

        // Calculate balance
        let balance = invoice.amount - totalPaidAmount + totalPaidServiceCharge;

        let balanceElement = document.getElementById('balance');
        if (balanceElement) {
            balanceElement.textContent = balance.toFixed(3);
        }

        const totalAmountDisplay = document.getElementById("totalAmountDisplay");
        const paymentForm = document.getElementById('paymentForm');
        const totalAmountInput = document.getElementById("totalAmountInput");
        const checkboxes = document.querySelectorAll(".partial-checkbox");

        if (invoice.payment_type === 'full') {

            console.log('full');
            // Ensure there’s only one hidden input for the 'full' payment type
            addHiddenInput("invoice_partial_id", invoicePartials[0]?.id, paymentForm);
        } else if (invoice.payment_type === 'partial' || invoice.payment_type === 'split') {

            console.log('partials');


            checkboxes.forEach((checkbox) => {
                const partialId = checkbox.value;

                if (checkbox.disabled) {
                    console.log('disable');
                    checkbox.checked = false; // Disabled checkboxes should remain checked
                } else {
                    console.log('cheked');
                    checkbox.checked = true; // Set all non-disabled checkboxes to checked by default
                    addHiddenInput("invoice_partial_id", partialId, paymentForm); // Add hidden input
                }

                ///addHiddenInput("invoice_partial_id", partialId, paymentForm); // Add corresponding hidden input

                calculateTotal();

                checkbox.addEventListener("change", (event) => {
                    const partialId = event.target.value;
                    console.log(partialId);
                    if (event.target.checked) {
                        // Add hidden input if checkbox is checked
                        addHiddenInput("invoice_partial_id", partialId, paymentForm);
                    } else {
                        // Remove hidden input if checkbox is unchecked
                        removeHiddenInput("invoice_partial_id", partialId, paymentForm);
                    }

                    calculateTotal();
                });
            });

        }


        function addHiddenInput(name, value, form) {
            // Check if the hidden input already exists
            console.log(name);
            let existingInput = form.querySelector(`input[name="${name}"][value="${value}"]`);
            if (!existingInput) {
                const hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = name;
                hiddenInput.value = value;
                form.appendChild(hiddenInput);
            }
        }


        // Utility to remove hidden inputs
        function removeHiddenInput(name, value, form) {
            let existingInput = form.querySelector(`input[name="${name}"][value="${value}"]`);
            if (existingInput) {
                existingInput.remove();
            }
        }

        function calculateTotal() {
            let totalForSubmission = 0;
            let totalForDisplay = 0;

            checkboxes.forEach((checkbox) => {
                if (checkbox.checked && !checkbox.disabled) {
                    totalForSubmission += parseFloat(checkbox.dataset.amount || 0);
                    totalForDisplay += parseFloat(checkbox.dataset.finalAmount || 0);
                }
            });

            totalAmountInput.value = totalForSubmission.toFixed(3);

            if (totalAmountDisplay) {
                totalAmountDisplay.textContent = totalForDisplay.toFixed(3);
            }

            console.log("Amount for submission (backend):", totalAmountInput.value);
            console.log("Amount for display (frontend):", totalForDisplay.toFixed(3));
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

        function showSpinner() {
            document.getElementById("submitButton").disabled = true;
            document.getElementById("buttonText").textContent = "Sending...";
            document.getElementById("spinner").classList.remove("hidden");
        }
    </script>

</body>

</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/show-arabic.blade.php ENDPATH**/ ?>