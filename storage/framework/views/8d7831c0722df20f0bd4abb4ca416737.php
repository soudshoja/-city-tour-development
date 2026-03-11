<?php if($errors->any()): ?>
<?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
    <?php echo e($error); ?>

    <button type="button" class="close text-white ml-2" aria-label="Close"
        onclick="this.parentElement.style.display='none';">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>

<div class="alert grid gap-2">
    <?php if(session('success')): ?>
    <div class="flex items-center justify-between rounded bg-green-500 p-3.5 text-white " role="alert">
        <div class="grid gap-2">
            <p>
                <?php echo e(session('success')); ?>

            </p>
            <?php if(session('data_success')): ?>
            <?php $__currentLoopData = session('data_success'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $data): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(is_array($data)): ?>
            <div class="my-2">
                <?php $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <p class="text-sm text-white">
                    <?php echo e($value); ?>

                </p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-white">
                <?php echo e($data); ?>

            </p>
            <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </div>
        <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.remove()">X</button>
    </div>
    <?php endif; ?>

    <?php if(session('error')): ?>
    <div class="flex items-center justify-between rounded bg-red-500 p-3.5 text-white dark:bg-danger-dark-light" role="alert">
        <div class="grid">
            <p>
                <?php echo e(session('error')); ?>

            </p>
            <?php if(session('data')): ?>
            <?php $__currentLoopData = session('data'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $data): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(is_array($data)): ?>
            <div class="my-2">
                <?php $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <p class="text-sm text-white">
                    <?php echo e($value); ?>

                </p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-white">
                <?php echo e($data); ?>

            </p>
            <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </div>
        <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.remove()">X</button>
    </div>
    <?php endif; ?>
</div>
<!-- for ajax alert -->
<div
    id="custom-success-alert"
    class="alert flex items-center justify-between rounded bg-green-500 p-3.5 text-white hidden" role="alert">
    <p></p>
    <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.remove()">X</button>
</div>

<div
    id="custom-error-alert"
    class="alert flex items-center justify-between rounded bg-red-500 p-3.5 text-white hidden" role="alert">
    <p></p>
    <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.classList.add('hidden')">X</button>
</div>

<div
    id="custom-success-ajax-alert"
    class="absolute top-8 right-24 z-10 flex items-center justify-between rounded shadow bg-green-500 p-3.5 text-white hidden" role="alert">
    <p></p>
    <button class="ml-4 bg-transparent font-semibold" onclick="this.parentElement.classList.add('hidden')">X</button>
</div><?php /**PATH /home/soudshoja/soud-laravel/resources/views/layouts/alert.blade.php ENDPATH**/ ?>