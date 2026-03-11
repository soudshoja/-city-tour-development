<script src="<?php echo e($jsDomain); ?>/applepay/v2/applepay.js"></script>
<script>
var mfApConfig = {
    sessionId: "<?php echo e($mfSession->SessionId); ?>", // Here you add the "SessionId" you receive from the InitiateSession endpoint.
    countryCode: "<?php echo e($mfSession->CountryCode); ?>", // Here, add your country code.
    amount: "<?php echo e($paymentMethods['ap']->GatewayData['GatewayTotalAmount']); ?>", // Add the invoice amount.
    currencyCode: "<?php echo e($paymentMethods['ap']->GatewayData['GatewayCurrency']); ?>", // Here, add your currency code.
    cardViewId: "mf-ap-element",
    callback: mfCallback
};

myFatoorahAP.init(mfApConfig);
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/myfatoorah/includes/sectionApplePay.blade.php ENDPATH**/ ?>