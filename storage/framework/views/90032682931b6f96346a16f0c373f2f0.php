<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            <?php echo e(__('OpenAI - Steps')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div>
        <?php $__currentLoopData = $runs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $run): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <p class="w-full bg-gray-200 p-2 rounded-md my-2 text-lg">
            <strong>RUN: </strong> <?php echo e($key); ?>

        </p>
        <?php $__currentLoopData = $run; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="bg-white shadow-md p-2 rounded-md my-2">

            <h3 class="text-2xl ">
                <strong>STEP:</strong>
                <?php echo e($step['id']); ?>

            </h3>
            <p class="text-lg"><?php echo e($step['step_details']['type']); ?></p>
            <?php if($step['step_details']['type'] == 'message_creation'): ?>
            <ul>
                <?php $__currentLoopData = $step['step_details']['message_creation']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message_creation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($message_creation); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
            <?php elseif($step['step_details']['type'] == 'tool_calls'): ?>
            <?php $__currentLoopData = $step['step_details']['tool_calls']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $tool_call): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <h3 class="text-xl font-bold"><?php echo e($tool_call['id']); ?></h3>
            <ul>
                <?php $__currentLoopData = $tool_call['function']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $function): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($function); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/ai/openai/steps.blade.php ENDPATH**/ ?>