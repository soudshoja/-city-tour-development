<div class="body bg-gradient-to-br from-gray-50 to-gray-200 dark:from-gray-900 dark:to-gray-800 mt-2 
rounded-lg shadow-md p-4 flex flex-col space-y-6 items-center mx-auto w-full">
    <div class="flex flex-col w-full">
        <?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $groupPermission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="flex items-center gap-x-6 border-b border-gray-300 dark:border-gray-700 pb-4 w-full">
            <!-- Group Title & Buttons in Two Rows -->
            <div class="flex flex-col gap-3">
                <!-- First Row: Group Title -->
                <div class="text-lg font-bold text-gray-900 dark:text-gray-100 text-center uppercase tracking-wide">
                    <?php echo e(ucfirst($key)); ?>

                </div>

                <!-- Second Row: Buttons -->
                <div class="flex gap-4 flex-wrap">
                    <button type="button" onclick="enableSubFeatures('<?php echo e($key); ?>')"
                        class="border border-gray-500 dark:border-gray-400 rounded-lg px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-300 text-sm hover:bg-gray-300 dark:hover:bg-gray-600 transition duration-150 ease-in-out shadow hover:shadow-md">
                        Enable All
                    </button>
                    <button type="button" onclick="disableSubFeatures('<?php echo e($key); ?>')"
                        class="border border-gray-500 dark:border-gray-400 rounded-lg px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-300 text-sm hover:bg-gray-300 dark:hover:bg-gray-600 transition duration-150 ease-in-out shadow hover:shadow-md">
                        Disable All
                    </button>
                </div>
            </div>


            <!-- Permissions List -->
            <div class="flex flex-wrap items-center justify-center gap-4 p-3" id="<?php echo e($key); ?>-sub">
                <?php $__currentLoopData = $groupPermission; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="flex items-center  justify-center gap-3 bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-300 dark:border-gray-700 hover:shadow-md transform transition duration-150 ease-in-out hover:scale-105">
                    <input type="checkbox" id="<?php echo e($permission['id']); ?>" name="permissionsId[]" value="<?php echo e($permission['id']); ?>" class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-400 rounded cursor-pointer transition-all ease-in-out duration-150" <?php echo e($permission['checked'] ? 'checked' : ''); ?>>
                    <label for="<?php echo e($permission['id']); ?>" class="text-gray-900 dark:text-gray-200 text-base 
                    font-medium tracking-wide"><?php echo e($permission['name']); ?></label>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <script>
            function enableSubFeatures(id) {
                var subFeatures = document.getElementById(id + '-sub');
                var checkboxes = subFeatures.getElementsByTagName('input');

                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = true;
                }
            }

            function disableSubFeatures(id) {
                var subFeatures = document.getElementById(id + '-sub');
                var checkboxes = subFeatures.getElementsByTagName('input');

                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = false;
                }
            }
        </script>
    </div>
</div><?php /**PATH /home/soudshoja/soud-laravel/resources/views/role/partials/permission.blade.php ENDPATH**/ ?>