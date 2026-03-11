<div>
    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3">
        <!-- title -->
        <div class="flex items-center space-x-4">
            <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center heartbeat">
                <!-- SVG Icon -->
                <a href="javascript:history.back()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                        <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z"></path>
                    </svg>
                </a>
            </div>
            <h2 class="text-2xl font-semibold text-gray-800 text-center dark:text-white">Notifications</h2>
        </div>
        <!--/ title -->

        <!-- Filter, Date Picker, Export Button -->


        <div class="flex items-center gap-5">
            <div data-tooltip="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="text-gray-700 dark:text-white">
                    <path d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" fill="currentColor"></path>
                    <path d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" fill="currentColor"></path>
                </svg>
            </div>

        </div>

    </div>
    <!-- page title -->

    <!-- Notification List -->

    <div class="panel BoxShadow rounded-lg overflow-y-auto max-h-[550px]">
        <?php $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="notification-item p-2 my-2 <?php echo e($notification->status == 'read' ? 'mt-5 px-4 py-3 mb-3 rounded-md transition duration-200 
        bg-green-100/50 dark:bg-green-900/50' : 'mt-5 px-4 py-3 mb-3 rounded-md transition duration-200 
        bg-red-100/50 dark:bg-red-900/50 text-red-500 dark:text-red-400'); ?>">
            <div class="flex justify-between items-center hover:-translate-y-2 transition duration-300">
                <div class="flex align-top">
                    <div class="rounded-full text-white p-2 h-auto m-2 
                    <?php echo e($notification->status == 'read' ? 'mt-5 px-4 py-3 mb-3 rounded-md transition duration-200 
        bg-green-800 dark:bg-green-300 text-green-500 dark:text-white' : 'mt-5 px-4 py-3 mb-3 rounded-md transition duration-200 
        bg-red-800 dark:bg-red-300 text-red-500 dark:text-white'); ?>">
                        <i class="fas fa-bell"></i>
                    </div>
                    <button class="notification-content text-start " wire:click="markAsRead(<?php echo e($notification->id); ?>)">
                        <p class="text-sm font-semibold dark:text-white"><?php echo e($notification->title); ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($notification->message); ?></p>
                    </button>
                </div>
                <div class="notification-time text-xs text-gray-500 dark:text-white line-clamp-1">
                    <?php echo e($notification->formatted_created_at); ?>

                </div>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>


    <!--./ Notification List -->
</div><?php /**PATH /home/soudshoja/soud-laravel/resources/views/livewire/notification-index.blade.php ENDPATH**/ ?>