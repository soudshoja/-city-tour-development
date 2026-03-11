<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($title ?? 'Notification'); ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9fafb;
            color: #333;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            padding: 35px 40px;
            border: 1px solid #e5e7eb;
        }
        .logo {
            display: block;
            margin: 0 auto 10px;
            max-height: 80px;
        }
        .brand {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #1d4ed8;
            margin-bottom: 20px;
        }
        h2 {
            text-align: center;
            color: #2563eb;
        }
        .message {
            background: #f3f4f6;
            padding: 15px 20px;
            border-left: 4px solid #2563eb;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 15px;
            line-height: 1.6;
        }
        .footer {
            font-size: 12px;
            color: #6b7280;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            margin-top: 30px;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if(isset($company) && $company->logo): ?>
            <img src="<?php echo e($company->logo ? asset('storage/' . $company->logo) : asset('images/UserPic.svg')); ?>" alt="Company logo" class="logo">
        <?php endif; ?>

        <div class="brand"><?php echo e($company->name ?? config('app.name', 'City Tour')); ?></div>

        <h2><?php echo e($title); ?></h2>

        <div class="message">
            <?php echo nl2br(e($body)); ?>

        </div>

        <p style="margin-top: 20px;">Thank you for using <?php echo e($company->name ?? config('app.name', 'City Tour')); ?>.</p>

        <div class="footer">
            &copy; <?php echo e(date('Y')); ?> <?php echo e($company->name ?? config('app.name', 'City Tour')); ?>. All rights reserved.
        </div>
    </div>
</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/email/notification.blade.php ENDPATH**/ ?>