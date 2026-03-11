<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo e(asset('images/City0logo.svg')); ?>" />

    <?php echo $__env->make('layouts.links', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <title><?php echo e(config('app.name', 'Laravel')); ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/guest.css']); ?>

    

</head>

<body>
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

    <?php if(session('success')): ?>
    <div class="alert alert-success fixed mt-5 top-1 right-4 bg-green-500 text-white p-4 rounded shadow-lg">
        <?php echo e(session('success')); ?>

        <button type="button" class="close text-white ml-2" aria-label="Close"
            onclick="this.parentElement.style.display='none';">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php elseif(session('error')): ?>
    <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
        <?php echo e(session('error')); ?>

        <button type="button" class="close text-white ml-2" aria-label="Close"
            onclick="this.parentElement.style.display='none';">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <div>
        <?php echo e($slot); ?>

    </div>

</body>

</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/layouts/guest.blade.php ENDPATH**/ ?>