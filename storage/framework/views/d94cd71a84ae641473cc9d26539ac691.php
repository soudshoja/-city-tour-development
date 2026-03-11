
<?php
// Check if "Dashboard" is already in the breadcrumbs, and add it only if it’s missing
$breadcrumbs = collect($breadcrumbs)->prepend(['label' => 'Dashboard', 'url' =>
route('dashboard')])->unique('label')->toArray();
?>

<ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
    <?php $__currentLoopData = $breadcrumbs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $breadcrumb): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <li class="<?php echo e(!$loop->first ? 'before:content-[\'/\'] before:mr-1' : ''); ?>">
        <?php if(isset($breadcrumb['url'])): ?>
        <a href="<?php echo e($breadcrumb['url']); ?>" class="customBlueColor hover:underline"><?php echo e($breadcrumb['label']); ?></a>
        <?php else: ?>
        <span><?php echo e($breadcrumb['label']); ?></span>
        <?php endif; ?>
    </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</ul><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/breadcrumbs.blade.php ENDPATH**/ ?>