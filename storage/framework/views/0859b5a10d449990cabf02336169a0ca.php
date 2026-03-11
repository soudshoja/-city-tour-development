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
            <?php echo e(__('Choose Payment Method')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white rounded shadow p-6">
                <form method="POST" action="<?php echo e(route('payment-method.set-group')); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="company_id" value="<?php echo e($companyId); ?>">

                    <?php $__currentLoopData = $paymentMethodGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="mb-6">
                            <div class="flex justify-between items-center">
                            <h3 class="font-bold text-lg mb-3"><?php echo e($group->name); ?></h3>
                            <?php if(isset($choiceIds[$group->id])): ?>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                        data-choice-id="<?php echo e($choiceIds[$group->id]); ?>" 
                                        data-group-id="<?php echo e($group->id); ?>" 
                                        class="sr-only peer payment-toggle"
                                        <?php echo e(($enabledGroups[$group->id] ?? false) ? 'checked' : ''); ?>>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    <span class="ml-3 text-sm font-medium text-gray-900">Enabled</span>
                                </label>
                            <?php else: ?>
                                <span class="text-xs text-gray-500">Select a method to enable</span>
                            <?php endif; ?>
                            </div>

                            <?php
                                $methodsByGateway = $group->paymentMethods->groupBy(function($method) {
                                    return $method->charge ? $method->charge->name : 'Unknown';
                                });
                                
                                $hasOnlyOne = $methodsByGateway->count() === 1;
                                $selectedMethodId = $selectedMethods[$group->id] ?? null;
                            ?>

                            <div class="space-y-2">
                                <?php $__currentLoopData = $methodsByGateway; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gatewayName => $methods): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $method = $methods->first();
                                        $methodId = $method->id;
                                        $isActive = $method->is_active;
                                        $isChecked = ($hasOnlyOne && $isActive) || $selectedMethodId == $methodId;
                                    ?>
                                    
                                    <label class="flex items-center gap-2 p-3 border rounded <?php echo e($isActive ? 'cursor-pointer hover:bg-gray-50' : 'bg-gray-100 cursor-not-allowed opacity-60'); ?>">
                                        <input 
                                            type="radio" 
                                            name="payment_method_group_<?php echo e($group->id); ?>" 
                                            value="<?php echo e($methodId); ?>"
                                            <?php echo e($isChecked ? 'checked' : ''); ?>

                                            <?php echo e(!$isActive ? 'disabled' : ''); ?>

                                        >
                                        <span><?php echo e($gatewayName); ?></span>
                                        <?php if(!$isActive): ?>
                                            <span class="text-xs text-red-600 ml-auto">(Inactive - Activate first)</span>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                        Submit
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = document.querySelectorAll('.payment-toggle');
            
            toggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const choiceId = this.dataset.choiceId;
                    const groupId = this.dataset.groupId;
                    const url = "<?php echo e(route('payment-method.toggle-enable', ['id' => 'CHOICE_ID'])); ?>".replace('CHOICE_ID', choiceId);
                    
                    fetch( url , {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Toggle successful:', data);
                        } else {
                            console.error('Toggle failed:', data);
                            this.checked = !this.checked;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.checked = !this.checked;
                    });
                });
            });
        });
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/charges/partial/choose_payment_method.blade.php ENDPATH**/ ?>