<?php $__currentLoopData = $paymentMethods['cards']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mfCard): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php ($mfCardTitle = App::isLocale('ar') ? $mfCard->PaymentMethodAr : $mfCard->PaymentMethodEn); ?>
<div class="mf-card-container mf-div-<?php echo e($mfCard->PaymentMethodCode); ?>" onclick="mfCardSubmit('<?php echo e($mfCard->PaymentMethodId); ?>')">
    <div class="mf-row-container">
        <img class="mf-payment-logo" src="<?php echo e($mfCard->ImageUrl); ?>" alt="<?php echo e($mfCardTitle); ?>">
        <span class="mf-payment-text mf-card-title"><?php echo e($mfCardTitle); ?></span>
    </div>
    <span class="mf-payment-text">
        <?php echo e($mfCard->GatewayData['GatewayTotalAmount']); ?> <?php echo e($mfCard->GatewayData['GatewayCurrency']); ?>

    </span>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<script>
    function mfCardSubmit(pmid){
        window.location.href = "<?php echo e(url('myfatoorah')); ?>?pmid=" + pmid;
    }
</script>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/myfatoorah/includes/sectionCards.blade.php ENDPATH**/ ?>