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
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">

                <div class="card shadow-sm border-0 rounded-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Send WhatsApp Message via Resayil</h4>
                    </div>

                    <div class="card-body p-4">

                        <form action="<?php echo e(route('whatsapp.sendToResayilSimple')); ?>" method="POST">
                            <?php echo csrf_field(); ?>

                            <div class="mb-3">
                                <label for="client_id" class="form-label">Select Client</label>
                                <select name="client_id" id="client_id" class="form-select" required>
                                    <option value="">-- Select Client --</option>
                                    <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($client->id); ?>">
                                            <?php echo e($client->full_name); ?> (<?php echo e($client->phone); ?>)
                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="message" class="form-label">Message to Send</label>
                                <textarea name="message" id="message" rows="4" class="form-control p-2 w-full rounded-md shadow-sm "
                                    placeholder="Enter your message here..." required></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    Send Message
                                </button>
                            </div>

                        </form>

                    </div>
                </div>

            </div>
        </div>
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
<?php endif; ?>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/whatsapp/send.blade.php ENDPATH**/ ?>