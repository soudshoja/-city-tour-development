<script src="<?php echo e($jsDomain); ?>/googlepay/v1/googlepay.js"></script>
<script>
var mfGpConfig = {
    sessionId: "<?php echo e($mfSession->SessionId); ?>", // Here you add the "SessionId" you receive from the InitiateSession endpoint.
    countryCode: "<?php echo e($mfSession->CountryCode); ?>", // Here, add your country code.
    amount: "<?php echo e($paymentMethods['gp']->GatewayData['GatewayTotalAmount']); ?>", // Add the invoice amount.
    currencyCode: "<?php echo e($paymentMethods['gp']->GatewayData['GatewayCurrency']); ?>", // Here, add your currency code.
    cardViewId: "mf-gp-element",
    isProduction: <?php echo e(Config::get('myfatoorah.test_mode')? 'false' : 'true'); ?>,
    callback: mfCallback
};

myFatoorahGP.init(mfGpConfig);
</script>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/myfatoorah/includes/sectionGooglePay.blade.php ENDPATH**/ ?>