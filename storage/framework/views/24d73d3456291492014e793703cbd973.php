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
    <style>
        /* Add some basic styling for the loading spinner */
        .spinner {
            display: none;
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #000;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            margin: 0 auto;
            margin-top: 1em;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        #response-container {
            display: block;
            /* Change from inline-flex to block for proper word wrapping */
            text-align: left;
            justify-items: center;
            white-space: normal;
            /* Allow line breaks */
            word-wrap: break-word;
            /* Enable word wrapping */
            overflow-wrap: break-word;
            /* Allow long words to wrap */
            max-width: 100%;
            /* Prevent container from exceeding screen width */
            overflow-y: auto;
            /* Enable scrolling for overflow content */
            padding: 10px;
            font-weight: 700;
            max-height: 300px;
            /* Set a maximum height for scrolling */
        }
    </style>
    <div class="h-160 p-2">
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('chat', []);

$__html = app('livewire')->mount($__name, $__params, 'lw-708909306-0', $__slots ?? [], get_defined_vars());

echo $__html;

unset($__html);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
    </div>
    <script>

        var d = $('.chat-box');
        console.log(d.prop("scrollHeight"));
        d.scrollTop(d.prop("scrollHeight"));
    </script>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/ai/openai/index.blade.php ENDPATH**/ ?>