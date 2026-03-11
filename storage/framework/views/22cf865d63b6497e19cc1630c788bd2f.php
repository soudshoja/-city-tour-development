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
    <div class="max-w-7xl mx-auto" x-data="{ showModal: false }">
        <nav class="flex items-center space-x-2 rtl:space-x-reverse text-sm mb-4 sm:mb-6 overflow-x-auto">
            <a href="<?php echo e(route('role.index')); ?>" class="text-gray-500 hover:text-gray-700 transition whitespace-nowrap">Roles</a>
            <span class="text-gray-400">&gt;</span>
            <span class="text-blue-600 font-medium truncate max-w-[200px] sm:max-w-none">New Role</span>
        </nav>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 my-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-white">Create New Role</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Select permissions for the new role</p>
            </div>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-500 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleAllPermissions(this)" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    Select All
                </label>
                <a href="<?php echo e(route('role.index')); ?>" 
                    class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg transition-colors flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back
                </a>
            </div>
        </div>

        <form id="permissionForm" action="<?php echo e(route('role.store')); ?>" method="POST">
            <?php echo csrf_field(); ?>

            <div class="space-y-4">
                <?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $groupPermission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="bg-gray-50 dark:bg-gray-700/50 px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-600">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white uppercase tracking-wide"><?php echo e(ucfirst($key)); ?></h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo e(count($groupPermission)); ?> permissions</p>
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-500 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                    <input type="checkbox" onchange="toggleGroupPermissions('<?php echo e($key); ?>', this)" class="group-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" data-group="<?php echo e($key); ?>">
                                    Select All
                                </label>
                            </div>
                        </div>

                        <div class="p-4 sm:p-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3" id="<?php echo e($key); ?>-sub">
                                <?php $__currentLoopData = $groupPermission; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <label for="perm_<?php echo e($permission['id']); ?>" 
                                        class="relative flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:border-blue-300 dark:hover:border-blue-500 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 cursor-pointer transition-all group">
                                        <input type="checkbox" 
                                            id="perm_<?php echo e($permission['id']); ?>" 
                                            name="permissionsId[]" 
                                            value="<?php echo e($permission['id']); ?>" 
                                            class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-500 rounded transition-all">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200 group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors">
                                            <?php echo e($permission['name']); ?>

                                        </span>
                                    </label>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="sticky bottom-0 mt-6">
                <div class="mx-auto px-4 py-3 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)]">
                    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-3">
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                <span class="font-semibold text-blue-600 dark:text-blue-400" id="selectedCount">0</span> permissions selected
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="<?php echo e(route('role.index')); ?>" 
                                class="px-4 py-2 text-sm bg-white hover:bg-gray-50 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg font-medium transition-colors border border-gray-300 dark:border-gray-500">
                                Cancel
                            </a>
                            <button type="button" @click="showModal = true"
                                class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Create Role
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="showModal" x-cloak
                class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-sm"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0">
                <div class="flex items-center justify-center min-h-screen px-4 py-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md"
                        @click.away="showModal = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100">
                        
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Create New Role</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Enter role details below</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Role Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" id="name" required
                                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                                    placeholder="e.g., Manager, Supervisor">
                            </div>
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Description
                                </label>
                                <textarea name="description" id="description" rows="3"
                                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white resize-none"
                                    placeholder="Brief description of this role..."></textarea>
                            </div>
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                                <p class="text-sm text-blue-700 dark:text-blue-400 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span><strong id="modalSelectedCount">0</strong> permissions will be assigned</span>
                                </p>
                            </div>
                        </div>

                        <div class="p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 rounded-b-xl">
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="showModal = false"
                                    class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg font-medium transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Create Role
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function toggleAllPermissions(checkbox) {
            document.querySelectorAll('input[name="permissionsId[]"]').forEach(cb => cb.checked = checkbox.checked);
            document.querySelectorAll('.group-checkbox').forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCount();
        }

        function toggleGroupPermissions(groupId, checkbox) {
            const subFeatures = document.getElementById(groupId + '-sub');
            subFeatures.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCount();
            updateSelectAllCheckbox();
        }

        function updateSelectedCount() {
            const total = document.querySelectorAll('input[name="permissionsId[]"]:checked').length;
            document.getElementById('selectedCount').textContent = total;
            const modalCount = document.getElementById('modalSelectedCount');
            if (modalCount) modalCount.textContent = total;
        }

        function updateSelectAllCheckbox() {
            const allCheckboxes = document.querySelectorAll('input[name="permissionsId[]"]');
            const checkedCheckboxes = document.querySelectorAll('input[name="permissionsId[]"]:checked');
            document.getElementById('selectAllCheckbox').checked = allCheckboxes.length === checkedCheckboxes.length;
        }

        function updateGroupCheckbox(checkbox) {
            const container = checkbox.closest('[id$="-sub"]');
            if (container) {
                const groupId = container.id.replace('-sub', '');
                const groupCheckboxes = container.querySelectorAll('input[type="checkbox"]');
                const checkedInGroup = container.querySelectorAll('input[type="checkbox"]:checked');
                const groupSelectAll = document.querySelector(`.group-checkbox[data-group="${groupId}"]`);
                if (groupSelectAll) {
                    groupSelectAll.checked = groupCheckboxes.length === checkedInGroup.length;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
            document.querySelectorAll('input[name="permissionsId[]"]').forEach(cb => {
                cb.addEventListener('change', function() {
                    updateSelectedCount();
                    updateSelectAllCheckbox();
                    updateGroupCheckbox(this);
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/role/create.blade.php ENDPATH**/ ?>