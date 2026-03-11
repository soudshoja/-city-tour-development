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
    <!-- Breadcrumbs -->
    <?php if (isset($component)) { $__componentOriginal360d002b1b676b6f84d43220f22129e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal360d002b1b676b6f84d43220f22129e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.breadcrumbs','data' => ['breadcrumbs' => [
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Add Client'] ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Add Client'] ])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $attributes = $__attributesOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__attributesOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $component = $__componentOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__componentOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
    <!-- ./Breadcrumbs -->


    <div class="grid grid-cols-3 gap-4">
        <!--  client details -->
        <div class="col-span-2 panel p-3">
            <div x-data="{ Form: false }">
                <a href="javascript:void(0);" @click.prevent="Form = ! Form"
                    class="w-full relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 dark:text-gray-200 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:hover:text-gray-900 focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                    <span
                        class="justify-center w-full gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                        Entering client details
                    </span>
                </a>

                <div x-show="Form" class="my-5 px-5">
                    <form action="<?php echo e(route('clients.store')); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <!-- Name field -->
                        <div class="flex flex-col sm:flex-row">
                            <label for="name" class="mb-0  sm:w-1/4 sm:mr-2">Name</label>
                            <input id="name" name="name" type="text" required placeholder="Enter name"
                                class="form-input flex-1">
                        </div>
                        <!-- ./Name field -->

                        <!-- Email field -->
                        <div class="flex flex-col sm:flex-row mt-5">
                            <label for="email" class="mb-0  sm:w-1/4 sm:mr-2">Email</label>
                            <input id="email" name="email" type="email" required placeholder="Enter Email"
                                class="form-input flex-1">
                        </div>
                        <!-- ./Email field -->

                        <!-- passport number field -->
                        <div class="flex flex-col sm:flex-row mt-5">
                            <label for="passport_no" class="mb-0  sm:w-1/4 sm:mr-2">Passport Number</label>
                            <input id="passport_no" name="passport_no" type="text" required
                                placeholder="Enter Passport Number" class="form-input flex-1">
                        </div>

                        <!-- ./passport number field -->


                        <!-- Address field -->
                        <div class="flex flex-col sm:flex-row mt-5">
                            <label for="address" class="mb-0  sm:w-1/4 sm:mr-2">Address</label>
                            <input id="address" name="address" type="text" required placeholder="Enter Address"
                                class="form-input flex-1">
                        </div>

                        <!-- ./Address field -->


                        <!-- Status field -->
                        <div class="flex flex-col sm:flex-row mt-5">
                            <label class=" sm:w-1/4 sm:mr-2">Choose Status</label>
                            <div class="flex-1">
                                <div class="mb-2">
                                    <label class="inline-flex cursor-pointer">
                                        <input type="radio" name="status" value="active"
                                            class="peer form-radio outline-success">
                                        <span class="peer-checked:text-success pl-2">Active</span>
                                    </label>

                                </div>

                                <div class="mb-2">
                                    <label class="inline-flex cursor-pointer">
                                        <input type="radio" name="status" value="inactive"
                                            class="peer form-radio outline-danger">
                                        <span class="peer-checked:text-danger pl-2">Inactive</span>
                                    </label>
                                </div>

                            </div>
                        </div>
                        <!-- ./Status field -->


                        <!-- submit button -->
                        <div class="mt-5 flex justify-center">
                            <button
                                class="w-[80%] inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:text-white focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                                <span
                                    class="w-full px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                                    Submit
                                </span>
                            </button>
                        </div>


                        <!-- ./submit button -->

                    </form>

                </div>
            </div>

        </div>
        <!-- ./client details -->

        <!--  upload client -->
        <div class="panel p-3">
            <a href=""
                class="w-full relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 dark:text-gray-200 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:hover:text-gray-900 focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                <span
                    class="justify-center w-full gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                    Upload Client
                </span>
            </a>
        </div>
        <!-- ./upload client -->

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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/clients/create.blade.php ENDPATH**/ ?>