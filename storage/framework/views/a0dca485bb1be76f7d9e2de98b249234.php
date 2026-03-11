<?php if (isset($component)) { $__componentOriginal69dc84650370d1d4dc1b42d016d7226b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b = $attributes; } ?>
<?php $component = App\View\Components\GuestLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('guest-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\GuestLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="bg-slate-400 h-screen flex items-center justify-center">
        <div class="grid grid-cols-1 lg:grid-cols-2 rounded-lg items-stretch justify-center">
            <!-- Left Side - Form -->
            <div class="w-full p-8 bg-primary text-white flex flex-col justify-center items-center mx-auto rounded-md custom-rounded-left">
                <h2 class="text-center text-2xl font-semibold mb-4 w-3/4 max-w-sm">Hey Pal!</h2>
                <p class="text-center text-sm mb-8 w-3/4 max-w-sm">We're excited to have you onboard...</p>
                <form id="adminForm" method="POST" action="<?php echo e(route('register.admin')); ?>" class="w-full flex flex-col items-center">
                    <?php echo csrf_field(); ?>
                    <?php echo RecaptchaV3::field('register'); ?>

                    <!-- Name -->
                    <div class="relative text-white-dark mb-4 w-3/4 max-w-sm">
                        <input id="name" name="name" type="text" placeholder="Your Name"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            :value="old('name')" required autofocus />
                    </div>

                    <!-- Email -->
                    <div class="relative text-white-dark mb-4 w-3/4 max-w-sm">
                        <input id="email" type="email" name="email" placeholder="Your Email"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            :value="old('email')" required />
                    </div>

                    <!-- Password -->
                    <div class="relative text-white-dark mb-4 w-3/4 max-w-sm">
                        <input id="password" type="password" name="password" placeholder="Your Password"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>

                    <!-- Confirm Password -->
                    <div class="relative text-white-dark mb-4 w-3/4 max-w-sm">
                        <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm Password"
                            class="form-input block w-full bg-white text-black rounded-md py-3 px-3 shadow focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                    <?php if (isset($component)) { $__componentOriginalf94ed9c5393ef72725d159fe01139746 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf94ed9c5393ef72725d159fe01139746 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-error','data' => ['messages' => $errors->get('password_confirmation'),'class' => 'my-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['messages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->get('password_confirmation')),'class' => 'my-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $attributes = $__attributesOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__attributesOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $component = $__componentOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__componentOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>

                    <!-- Register Button -->
                    <div class="mb-4 w-3/4 max-w-sm">
                        <button type="submit"
                            class="text-black w-full bg-[#F7BE38] hover:bg-[#F7BE38]/80 font-medium rounded-lg px-5 py-2.5 shadow">
                            <?php echo e(__('Register')); ?>

                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Side - Image -->
            <div class="flex items-center justify-center bg-white rounded-lg lg:rounded-none custom-rounded-right">
                <img src="<?php echo e(asset('images/registerPic.svg')); ?>" alt="city app" class="-mt-5 object-contain max-h-[500px]">
            </div>
        </div>

    </div>


 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $attributes = $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $component = $__componentOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/auth/register.blade.php ENDPATH**/ ?>