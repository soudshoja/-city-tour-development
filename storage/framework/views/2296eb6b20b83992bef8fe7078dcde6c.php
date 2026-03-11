<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>">
    <head>
        <title><?php echo e(__('myfatoorah.pageCheckout')); ?></title>
        <link rel="stylesheet" href="<?php echo e(asset('vendor/myfatoorah/css/style.css')); ?>"/>
    </head>

    <body dir="<?php echo e(App::isLocale('ar') ? 'rtl' : 'ltr'); ?>">
        <div class="mf-payment-methods-container" id="mf-noPaymentGateways">
            <div class="mf-danger-text">
                <?php echo e(__('myfatoorah.noPaymentGateways')); ?>

            </div>
        </div>
        <div class="mf-payment-methods-container" id="mf-paymentGateways" >
            <div class="mf-grey-text">
                <?php echo e(__('myfatoorah.howWouldYouLikeToPay')); ?>

            </div>

            <!-- Google Pay & Apple Pay -->
            <div id="mf-sectionButtons">
                <!-- Apple Pay -->
                <?php if(!empty($paymentMethods['ap'])): ?>
                <div id="mf-sectionAP">
                    <div id="mf-ap-element" style="height: 40px;"></div>
                </div>
                <?php endif; ?>
                <!-- Google Pay -->
                <?php if(!empty($paymentMethods['gp'])): ?>
                <div id="mf-sectionGP">
                    <div id="mf-gp-element"></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if(!empty($paymentMethods['cards'] )): ?>
            <div id="mf-sectionCard">
                <div class="mf-divider card-divider" id="mf-payWith-cardDivider">
                    <span class="mf-divider-span" id="mf-payWith-divider">
                        <span id="mf-or-cardsDivider">
                            <?php echo e(!empty($paymentMethods['ap'] ) || !empty($paymentMethods['gp'] ) ? __('myfatoorah.or') : ''); ?>

                        </span>
                        <?php echo e(__('myfatoorah.payWith')); ?>

                    </span>
                </div>
                <div id="mf-cards">
                    <?php echo $__env->make('myfatoorah.includes.sectionCards', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment Form -->
            <?php if(!empty($paymentMethods['form'])): ?>
            <div class="mf-divider">
                <span class="mf-divider-span">
                    <span id="mf-or-formDivider">
                        <?php echo e(!empty($paymentMethods['cards'] ) || !empty($paymentMethods['ap'] ) || !empty($paymentMethods['gp'] ) ? __('myfatoorah.or') :''); ?>

                    </span>
                    <?php echo e(__('myfatoorah.insertCardDetails')); ?>

                </span>
            </div>
            <div id="mf-form-element" style="width:99%; max-width:800px; padding: 0rem 0.2rem"></div>

            <button class="mf-btn mf-pay-now-btn" onclick="submit()" type="button" style="
                    border: none; border-radius: 8px;
                    padding: 7px 3px; background-color: #0293cc">
                <span class="mf-pay-now-span">
                    <?php echo e(__('myfatoorah.payNow')); ?>

                </span>
            </button>
            <?php endif; ?>

            <script src="<?php echo e(asset('vendor/myfatoorah/js/checkout.js')); ?>"></script>
            <script>
                function mfCallback(response) {
                    window.location.href = "<?php echo e(url('myfatoorah')); ?>?sid=" + response.sessionId;
                }
            </script>

            <!-- Google Pay Scripts -->
            <?php if(!empty($paymentMethods['gp'])): ?>
            <?php echo $__env->make('myfatoorah.includes.sectionGooglePay', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endif; ?>

            <!-- Apple Pay Scripts -->
            <?php if(!empty($paymentMethods['ap'])): ?>
            <?php echo $__env->make('myfatoorah.includes.sectionApplePay', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endif; ?>

            <!-- Payment Form Scripts -->
            <?php if(!empty($paymentMethods['form'])): ?>
            <?php echo $__env->make('myfatoorah.includes.sectionForm', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endif; ?>
        </div>
    </body>
</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/myfatoorah/checkout.blade.php ENDPATH**/ ?>