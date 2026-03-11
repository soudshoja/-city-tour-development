<form method="POST" action="<?php echo e(route('suppliers.exchange-rates.update', $supplier->id)); ?>">
    <?php echo csrf_field(); ?>
    <h2>Exchange Rates for <?php echo e($supplier->name); ?></h2>
 
    <hr>
    <?php $__currentLoopData = $currencies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $currency): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div>
            <label><?php echo e($currency); ?></label>
            <input type="number" step="0.000001" name="<?php echo e(strtolower($currency)); ?>"
                value="<?php echo e(optional($supplier->exchangeRates->where('currency', $currency)->first())->rate); ?>">
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <button type="submit">Save Rates</button>
</form><?php /**PATH /home/soudshoja/soud-laravel/resources/views/suppliers/exchange_rates.blade.php ENDPATH**/ ?>