<div>
    <!-- Tabs for Filter -->
    <div class="flex items-center space-x-8 border-b border-gray-300 dark:border-gray-700">
        <button
            class="relative pb-2 font-semibold transition-all duration-300 ease-in-out <?php echo e($filter == 'all' ? 'text-black dark:text-white border-b-2 border-blue-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300'); ?>"
            wire:click="updateFilter('all')">
            All
            <span class="ml-2 text-xs bg-blue-100/50 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 px-2 py-0.5 rounded-full">
                <?php echo e($totalCount); ?>

            </span>
        </button>

        <button
            class="relative pb-2 font-semibold transition-all duration-300 ease-in-out <?php echo e($filter == 'read' ? 'text-black dark:text-white border-b-2 border-green-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300'); ?>"
            wire:click="updateFilter('read')">
            Read
            <span class="ml-2 text-xs bg-green-100/50 dark:bg-green-900/50 text-green-800 dark:text-green-300 px-2 py-0.5 rounded-full">
                <?php echo e($readCount); ?>

            </span>
        </button>

        <button
            class="relative pb-2 font-semibold transition-all duration-300 ease-in-out <?php echo e($filter == 'unread' ? 'text-black dark:text-white border-b-2 border-red-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300'); ?>"
            wire:click="updateFilter('unread')">
            Unread
            <span class="ml-2 text-xs bg-red-100/50 dark:bg-red-900/50 text-red-500 dark:text-red-400 px-2 py-0.5 rounded-full">
                <?php echo e($unreadCount); ?>

            </span>
        </button>
    </div>



    <!-- Notification List -->
    <?php $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div
        class="mt-5 px-4 py-3 mb-3 rounded-md transition duration-200 
        <?php echo e($notification->status == 'read' ? 'bg-green-100/50 dark:bg-green-900/50' : 'bg-red-100/50 dark:bg-red-900/50 text-red-500 dark:text-red-400'); ?>"
        wire:key="notification-<?php echo e($notification->id); ?>">
        
        <!-- Notification Header -->
        <div class="flex justify-between items-start mb-2">
            <p class="text-sm font-semibold text-gray-700 dark:text-white">
                <?php echo str_replace('\n', '<br>', e($notification->title)); ?>

            </p>
            <span class="text-xs text-gray-400">
                <?php echo e($notification->formatted_created_at); ?>

            </span>
        </div>

        <!-- Notification Message -->
        <?php if($notification->message): ?>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                <?php echo str_replace('\n', '<br>', e($notification->message)); ?>

            </p>
        <?php endif; ?>

        <!-- Action Buttons for Assignment Requests -->
        <?php if($notification->type === 'client_assignment_request' && $notification->data): ?>
            <?php
                $notificationData = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
                $requestToken = $notificationData['request_token'] ?? null;
            ?>
            
            <?php if($requestToken && $this->isAssignmentRequestPending($requestToken)): ?>
                <div class="flex space-x-2 mt-3">
                    <a href="<?php echo e($notificationData['actions']['approve_url'] ?? '#'); ?>" 
                       class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Approve Assignment
                    </a>
                    <a href="<?php echo e($notificationData['actions']['deny_url'] ?? '#'); ?>" 
                       class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Deny
                    </a>
                </div>
            <?php elseif($requestToken): ?>
                <!-- Show status for processed requests -->
                <?php
                    $processedRequest = $this->getAssignmentRequestStatus($requestToken);
                ?>
                <?php if($processedRequest): ?>
                    <div class="mt-3 p-2 rounded-md <?php echo e($processedRequest->status === 'approved' ? 'bg-green-100 text-green-800' : ($processedRequest->status === 'denied' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                        <span class="text-sm font-medium">
                            Status: <?php echo e(ucfirst($processedRequest->status)); ?>

                            <?php if($processedRequest->processed_at): ?>
                                - <?php echo e(\Carbon\Carbon::parse($processedRequest->processed_at)->diffForHumans()); ?>

                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Client Details for Assignment Requests -->
            <?php if(isset($notificationData['client_name'])): ?>
                <div class="mt-2 p-2 bg-gray-50 dark:bg-gray-800 rounded text-xs">
                    <strong>Client:</strong> <?php echo e($notificationData['client_name']); ?><br>
                    <?php if(isset($notificationData['client_phone'])): ?>
                        <strong>Phone:</strong> <?php echo e($notificationData['client_phone']); ?><br>
                    <?php endif; ?>
                    <?php if(isset($notificationData['requesting_agent_name'])): ?>
                        <strong>Requested by:</strong> <?php echo e($notificationData['requesting_agent_name']); ?><br>
                    <?php endif; ?>
                    <?php if(isset($notificationData['reason'])): ?>
                        <strong>Reason:</strong> <?php echo e($notificationData['reason']); ?>

                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Action Buttons for Other Notification Types -->
        <?php if($notification->type !== 'client_assignment_request' && $notification->data): ?>
            <?php
                $notificationData = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
            ?>
            <?php if(isset($notificationData['view_client_url'])): ?>
                <div class="mt-3">
                    <a href="<?php echo e($notificationData['view_client_url']); ?>" 
                       class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        View Client
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Mark as Read Button -->
        <?php if($notification->status === 'unread'): ?>
            <div class="mt-2 text-right">
                <button wire:click="markAsRead(<?php echo e($notification->id); ?>)" 
                        class="text-xs text-blue-600 hover:text-blue-800 underline">
                    Mark as read
                </button>
            </div>
        <?php endif; ?>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>


</div><?php /**PATH /home/soudshoja/soud-laravel/resources/views/livewire/notification.blade.php ENDPATH**/ ?>