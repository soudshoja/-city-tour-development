<div x-data="sidebarCompanySelector()" class="switch-company-btn-wrapper">
    <button @click="open = !open" type="button" class="switch-company-btn">
        <div data-tooltip="Switch Company"
            class="">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
            </svg>
        </div>
        <span class="switch-company-title">Switch Company</span>
    </button>
    <div x-show="open" x-cloak @click="open = false" class="switch-company-backdrop"></div>
    <div x-show="open" x-cloak
        @click.outside="open = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-2"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-2"
        class="switch-company-dropdown absolute left-full ml-3 top-0 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 z-50 overflow-hidden">

        <div class="px-4 py-3 bg-gradient-to-r from-gray-700 to-gray-600 text-white">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd" />
                </svg>
                <span class="text-sm font-medium">Admin View</span>
            </div>
            <p class="text-xs text-gray-300 mt-1">Switch between companies</p>
        </div>

        <div class="p-3 border-b border-gray-200 dark:border-gray-700">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Search company..."
                    class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>

        <div class="max-h-60 overflow-y-auto">
            <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button type="button"
                x-show="'<?php echo e(strtolower($company->name)); ?>'.includes(search.toLowerCase())"
                @click="selectCompany(<?php echo e($company->id); ?>, '<?php echo e(addslashes($company->name)); ?>')"
                :disabled="loading"
                class="w-full px-4 py-3 text-left text-sm hover:bg-blue-50 dark:hover:bg-gray-700 flex items-center gap-3 transition-colors disabled:opacity-50 <?php echo e($company->id == $currentCompanyId ? 'bg-blue-50 dark:bg-gray-700 border-l-4 border-blue-500' : 'text-gray-700 dark:text-gray-300'); ?>">
                
                <?php if($company->id == $currentCompanyId): ?>
                <span class="flex-shrink-0 w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center">
                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <span class="font-medium text-blue-600 dark:text-blue-400"><?php echo e($company->name); ?></span>
                <?php else: ?>
                <span class="flex-shrink-0 w-5 h-5 bg-gray-200 dark:bg-gray-600 rounded-full"></span>
                <span><?php echo e($company->name); ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <div x-show="loading" class="px-4 py-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-sm flex items-center gap-2">
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Switching company...
        </div>
    </div>
</div>

<script>
    function sidebarCompanySelector() {
        return {
            open: false,
            search: '',
            loading: false,
            selectedCompanyId: <?php echo e($currentCompanyId ?? 1); ?>,

            async selectCompany(id, name) {
                if (id === this.selectedCompanyId) {
                    this.open = false;
                    return;
                }

                this.loading = true;
                this.open = false;

                try {
                    const response = await fetch('<?php echo e(route("users.set-company")); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ company_id: id })
                    });

                    const data = await response.json();

                    if (data.success) {
                        setTimeout(() => {
                            window.location.href = '<?php echo e(route("dashboard")); ?>';
                        }, 100);
                    } else {
                        alert(data.message || 'Failed to switch company');
                        this.loading = false;
                    }
                } catch (error) {
                    console.error('Error switching company:', error);
                    alert('Failed to switch company');
                    this.loading = false;
                }
            }
        }
    }
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/sidebar-company.blade.php ENDPATH**/ ?>