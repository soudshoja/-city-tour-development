

<div class="payment-selection-container p-3 bg-blue-50 border border-blue-200 rounded mt-2" data-row-index="<?php echo e($rowIndex ?? ''); ?>">
    <h4 class="text-sm font-semibold text-blue-700 mb-2">Select Payment(s) to Apply:</h4>
    
    <div class="payment-list space-y-2 max-h-40 overflow-y-auto">
        
        <div class="loading-placeholder text-center py-2 text-gray-500">
            <span class="animate-pulse">Loading available payments...</span>
        </div>
    </div>
    
    <div class="payment-summary mt-2 pt-2 border-t border-blue-200 text-sm">
        <div class="flex justify-between">
            <span>Selected Amount:</span>
            <span class="selected-amount font-medium">0.000 KWD</span>
        </div>
        <div class="flex justify-between">
            <span>Required:</span>
            <span class="required-amount font-medium">0.000 KWD</span>
        </div>
    </div>
</div>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/invoice/partials/payment-selection.blade.php ENDPATH**/ ?>