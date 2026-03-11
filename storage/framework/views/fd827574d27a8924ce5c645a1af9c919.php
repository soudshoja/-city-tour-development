<script src="<?php echo e($jsDomain); ?>/cardview/v2/session.js"></script>
<script>
    var config = {
        countryCode: "<?php echo e($mfSession->CountryCode); ?>", // Here, add your Country Code.
        sessionId: "<?php echo e($mfSession->SessionId); ?>", // Here you add the "SessionId" you receive from InitiateSession Endpoint.
        cardViewId: "mf-form-element",
        // The following style is optional.
        style: {
            hideCardIcons: false,
            direction: "<?php echo e(App::isLocale('ar') ? 'rtl' : 'ltr'); ?>",
            cardHeight: <?php echo e($userDefinedField ? 190 : 130); ?>,
            tokenHeight: 160,
            input: {
                color: "black",
                fontSize: "13px",
                fontFamily: "sans-serif",
                inputHeight: "32px",
                inputMargin: "0px",
                borderColor: "c7c7c7",
                borderWidth: "1px",
                borderRadius: "8px",
                boxShadow: "",
                placeHolder: {
                    holderName:   "<?php echo e(__('myfatoorah.holderName')); ?>",
                    cardNumber:   "<?php echo e(__('myfatoorah.cardNumber')); ?>",
                    expiryDate:   "<?php echo e(__('myfatoorah.expiryDate')); ?>",
                    securityCode: "<?php echo e(__('myfatoorah.securityCode')); ?>",
                }
            },
            label: {
                display: false,
                color: "black",
                fontSize: "13px",
                fontWeight: "normal",
                fontFamily: "sans-serif",
                text: {
                    holderName:   "<?php echo e(__('myfatoorah.cardHolderNameLabel')); ?>",
                    cardNumber:   "<?php echo e(__('myfatoorah.cardNumberLabel')); ?>",
                    expiryDate:   "<?php echo e(__('myfatoorah.expiryDateLabel')); ?>",
                    securityCode: "<?php echo e(__('myfatoorah.securityCodeLabel')); ?>",
                },
            },
            error: {
                borderColor: "red",
                borderRadius: "8px",
                boxShadow: "0px",
            },
            text: {
                saveCard: "<?php echo e(__('myfatoorah.saveCard')); ?>",
                addCard:  "<?php echo e(__('myfatoorah.addCard')); ?>",
                deleteAlert: {
                    tilte:   "<?php echo e(__('myfatoorah.deleteAlert.title')); ?>",
                    message: "<?php echo e(__('myfatoorah.deleteAlert.message')); ?>",
                    confirm: "<?php echo e(__('myfatoorah.deleteAlert.confirm')); ?>",
                    cancel:  "<?php echo e(__('myfatoorah.deleteAlert.cancel')); ?>"
                }
            }
        },
    };
    myFatoorah.init(config);

    function submit() {
        myFatoorah.submit()
            // On success
            .then(function (response) {
                mfCallback(response);
            })
            // In case of errors
            .catch(function (error) {
                alert(error);
            });
    }
</script>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/myfatoorah/includes/sectionForm.blade.php ENDPATH**/ ?>