<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title><?php echo e($title ?? 'AutoBill Notification'); ?></title>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f8;
            color: #333;
            padding: 20px;
        }

        .email-container {
            max-width: 680px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px 50px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .logo {
            display: block;
            margin: 0 auto 10px auto;
            max-height: 80px;
        }

        .brand-name {
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            color: #2563eb;
            text-transform: uppercase;
            margin-bottom: 25px;
        }

        h2 {
            font-size: 26px;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            margin: 25px 0;
        }

        p {
            font-size: 15px;
            line-height: 1.7;
            margin: 15px 0;
        }

        .details-box {
            padding: 18px 24px;
            border-radius: 8px;
            margin: 25px 0;
        }

        .details-box strong {
            color: #111827;
        }

        .button {
            display: inline-block;
            text-decoration: none;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 8px;
            text-align: center;
            margin-top: 10px;
            transition: background 0.2s ease-in-out;
            color: #ffffff !important;
        }

        .footer {
            margin-top: 40px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }

        .success h2 {
            background-color: #eff6ff;
            color: #1d4ed8;
        }
        .success .details-box {
            background-color: #f9fafb;
            border-left: 4px solid #3b82f6;
        }
        .success .button {
            background-color: #2563eb;
        }
        .success .button:hover {
            background-color: #1d4ed8;
        }

        /* ❌ ERROR THEME */
        .failed h2 {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .failed .details-box {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
        }
        .failed .button {
            background-color: #dc2626;
        }
        .failed .button:hover {
            background-color: #b91c1c;
        }

        @media (prefers-color-scheme: dark) {
            body { background-color: #0f172a !important; color: #e2e8f0 !important; }
            .email-container { background-color: #1e293b !important; border-color: #334155 !important; }
            .footer { color: #94a3b8 !important; border-top-color: #334155 !important; }
            .success h2 { background-color: #1e3a8a !important; color: #ffffff !important; }
            .success .details-box { background-color: #334155 !important; border-left-color: #3b82f6 !important; }
            .success .button { background-color: #3b82f6 !important; }
            .failed h2 { background-color: #7f1d1d !important; color: #ffffff !important; }
            .failed .details-box { background-color: #3f3f46 !important; border-left-color: #f87171 !important; }
            .failed .button { background-color: #ef4444 !important; }
        }
    </style>
</head>

<body>
    <?php
        $isFailed = isset($title) && str_contains(strtolower($title), 'failed');
    ?>

    <div class="email-container <?php echo e($isFailed ? 'failed' : 'success'); ?>">
        <?php if(isset($company) && $company->logo): ?>
            <img src="<?php echo e($company->logo ? url('storage/' . $company->logo) : url('images/UserPic.svg')); ?>" alt="Company logo" class="logo">
        <?php endif; ?>

        <div class="brand-name"><?php echo e(strtoupper($company->name ?? config('app.name', 'City Tour'))); ?></div>

        <h2><?php echo e($title ?? 'AutoBill Invoice Generated'); ?></h2>

        <p>Hello,</p>

        <?php if($isFailed): ?>
            <p><strong>An error occurred during the AutoBilling process.</strong></p>
            <div class="details-box">
                <p><strong>Client:</strong> <?php echo e($clientName ?? 'N/A'); ?></p>
                <p><strong>Error Message:</strong> <?php echo e($errorMessage ?? 'Unknown error occurred.'); ?></p>
                <p><strong>Occurred At:</strong> <?php echo e(now()->format('d M, Y H:i')); ?></p>
            </div>
            <p>Please review the AutoBilling logs for more details.</p>
        <?php else: ?>
            <p>An automatic invoice has just been generated via the AutoBilling system.</p>
            <div class="details-box">
                <p><strong>Client:</strong> <?php echo e($clientName ?? 'N/A'); ?></p>
                <p><strong>Invoice Number:</strong> <?php echo e($invoiceNumber ?? 'N/A'); ?></p>
                <p><strong>Total Amount:</strong> <?php echo e($amount ?? 'N/A'); ?> <?php echo e($currency ?? 'KWD'); ?></p>
                <p><strong>Tasks Included:</strong> <?php echo e($taskCount ?? 'N/A'); ?></p>
                <?php if(!empty($taskRefs)): ?>
                    <ul style="margin-top: 5px; padding-left: 20px;">
                        <?php $__currentLoopData = $taskRefs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ref): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($ref); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                <?php endif; ?>
                <p><strong>Generated At:</strong> <?php echo e(now()->format('d M, Y H:i')); ?></p>
            </div>

            <?php if(isset($invoiceLink)): ?>
                <p>You can view the full invoice details here:</p>
                <p><a href="<?php echo e($invoiceLink); ?>" class="button" target="_blank">View Invoice</a></p>
            <?php endif; ?>
        <?php endif; ?>

        <p>Best regards,<br><?php echo e($company->name ?? config('app.name', 'City Tour')); ?> Team</p>

        <div class="footer">
            &copy; <?php echo e(date('Y')); ?> <?php echo e($company->name ?? config('app.name', 'City Tour')); ?>. All rights reserved.
        </div>
    </div>
</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/email/autobill-notification.blade.php ENDPATH**/ ?>