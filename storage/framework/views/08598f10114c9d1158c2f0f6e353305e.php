<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>">
    <head>
        <title><?php echo e(__('myfatoorah.pageError')); ?></title>
        <link rel="stylesheet" href="<?php echo e(asset('vendor/myfatoorah/css/style.css')); ?>"/>
    </head>

    <body dir="<?php echo e(App::isLocale('ar') ? 'rtl' : 'ltr'); ?>">
        <div class="mf-payment-methods-container">
            <div class="mf-danger-text">
                <?php echo e($exMessage); ?>

            </div>
        </div>
    </body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/myfatoorah/error.blade.php ENDPATH**/ ?>