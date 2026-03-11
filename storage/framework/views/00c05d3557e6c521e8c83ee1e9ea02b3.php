<li x-data="{ open: false, showAddCategoryForm: false }" class="relative w-full flex flex-col">
    <div class="group grid grid-cols-12 gap-2 items-center py-3 px-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gradient-to-r hover:from-<?php echo e($color); ?>-50/50
        hover:to-transparent dark:hover:from-<?php echo e($color); ?>-900/20 dark:hover:to-transparent transition-all duration-200 cursor-pointer relative"
        :class="{ 'bg-<?php echo e($color); ?>-50/30 dark:bg-<?php echo e($color); ?>-900/10': open }"
        @click="if (!showAddCategoryForm) open = !open">

        <div class="absolute left-0 top-0 bottom-0 w-0.5 bg-<?php echo e($color); ?>-500 opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-r"
            :class="{ 'opacity-100': open }"></div>

        <div class="col-span-5 flex items-center gap-2 pl-1">
            <?php if($account->is_group || ($account->childAccounts && $account->childAccounts->isNotEmpty())): ?>
                <svg class="w-4 h-4 text-gray-400 group-hover:text-<?php echo e($color); ?>-500 transition-all duration-200 flex-shrink-0"
                    :class="{ 'rotate-90 text-<?php echo e($color); ?>-500': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            <?php else: ?>
                <div class="w-4"></div>
            <?php endif; ?>

            <span class="text-gray-800 dark:text-gray-200 truncate group-hover:text-<?php echo e($color); ?>-700 dark:group-hover:text-<?php echo e($color); ?>-300 transition-colors duration-200"
                :class="{ 'text-<?php echo e($color); ?>-700 dark:text-<?php echo e($color); ?>-300': open }">
                <?php echo e($account->name); ?>

            </span>

            <?php if($account->ledger): ?>
                <a class="text-xs text-blue-500 hover:text-blue-700 hover:underline flex-shrink-0 transition-colors duration-200"
                    target="_blank" href="<?php echo e(route('journal-entries.show', $account->id)); ?>" @click.stop>
                    Ledger
                </a>
            <?php endif; ?>
        </div>

        <div class="col-span-2 flex justify-center">
            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-<?php echo e($color); ?>-100 text-<?php echo e($color); ?>-600 group-hover:bg-<?php echo e($color); ?>-200 transition-colors duration-200">
                <?php echo e($account->code); ?>

            </span>
        </div>

        <div class="col-span-3 flex items-center justify-end gap-2">
            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo e($account->balance < 0 ? 'bg-red-100 text-red-600' : 'bg-' . $color . '-100 text-' . $color . '-600'); ?>

                group-hover:bg-<?php echo e($color); ?>-200 transition-colors duration-200">
                <?php echo e($account->balance); ?>

            </span>

            <?php if(isset($account->excluded_payment_balance) && $account->excluded_payment_balance != 0): ?>
                <span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-700 border border-yellow-300 flex-shrink-0"
                    title="Payment accounts excluded: <?php echo e(number_format($account->excluded_payment_debit - $account->excluded_payment_credit, 2)); ?>">
                    ※ Excl: <?php echo e($account->excluded_payment_balance); ?>

                </span>
            <?php endif; ?>

            <?php if($account->currency !== null && $account->currency !== 'KWD'): ?>
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 flex-shrink-0">
                    <?php echo e($account->original_balance); ?> <?php echo e($account->currency); ?>

                </span>
            <?php endif; ?>
        </div>

        <div class="col-span-2 flex items-center justify-end gap-2">
            <?php if($account->name == 'Amadeus' && $account->root->name == 'Liabilities' && $account->journalEntries->count() > 0): ?>
            <div x-data="{ delegateBalanceAmadeus: false }">
                <button @click.stop="delegateBalanceAmadeus = true"
                    class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all duration-200 animate-pulse hover:animate-none"
                    data-tooltip-left="Delegate balance to issuing company">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M9.5 14C11.1569 14 12.5 15.3431 12.5 17C12.5 18.6568 11.1569 20 9.5 20C7.84315 20 6.5 18.6568 6.5 17C6.5 15.3431 7.84315 14 9.5 14Z" stroke-width="1.5" />
                        <path d="M14.5 3.99998C12.8431 3.99998 11.5 5.34312 11.5 6.99998C11.5 8.65683 12.8431 9.99998 14.5 9.99998C16.1569 9.99998 17.5 8.65683 17.5 6.99998C17.5 5.34312 16.1569 3.99998 14.5 3.99998Z" stroke-width="1.5" />
                        <path d="M15 16.9585L22 16.9585" stroke-width="1.5" stroke-linecap="round" />
                        <path d="M9 6.9585L2 6.9585" stroke-width="1.5" stroke-linecap="round" />
                        <path d="M2 16.9585L4 16.9585" stroke-width="1.5" stroke-linecap="round" />
                        <path d="M22 6.9585L20 6.9585" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </button>

                <div x-cloak x-show="delegateBalanceAmadeus"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    class="fixed inset-0 flex items-center justify-center z-50 bg-gray-900/60 backdrop-blur-sm cursor-default text-black dark:text-white">
                    <div @click.away="delegateBalanceAmadeus = false"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 relative border-t-4 border-<?php echo e($color); ?>-500">
                        <button @click="delegateBalanceAmadeus = false"
                            class="absolute top-3 right-3 p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <h2 class="text-xl font-semibold mb-3 text-<?php echo e($color); ?>-600">Delegate Balance</h2>
                        <hr class="mb-3 border-gray-200 dark:border-gray-700">
                        <form action="<?php echo e(route('coa.delegate-price')); ?>" method="POST">
                            <?php echo csrf_field(); ?>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">
                                Delegating the balance of Amadeus account will transfer the balance to the company that issued tasks
                            </p>
                            <div class="p-3 mb-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                                <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                    ⚠️ Please enter the code for the new account that will be created
                                </p>
                            </div>
                            <input type="hidden" name="account_id" value="<?php echo e($account->id); ?>">
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-1">Code<span class="text-red-500">*</span></label>
                                <input type="number" name="code" required placeholder="Enter new code" min="<?php echo e($account->code + 1); ?>"
                                    class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm px-3 py-2 
                                           focus:outline-none focus:ring-2 focus:ring-<?php echo e($color); ?>-500 focus:border-transparent transition-all duration-200">
                            </div>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="delegateBalanceAmadeus = false"
                                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="bg-<?php echo e($color); ?>-600 text-white px-4 py-2 rounded-lg hover:bg-<?php echo e($color); ?>-700
                                           shadow-lg shadow-<?php echo e($color); ?>-500/30 hover:shadow-<?php echo e($color); ?>-500/50 transition-all duration-200">
                                    Delegate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if($account->is_group): ?>
                <button @click.stop="showAddCategoryForm = true"
                    class="p-1.5 text-<?php echo e($color); ?>-500 hover:text-<?php echo e($color); ?>-700 hover:bg-<?php echo e($color); ?>-50 rounded-lg transition-all duration-200 opacity-0 group-hover:opacity-100"
                    title="Add child account">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div x-show="showAddCategoryForm" x-cloak
        @keydown.escape.window="showAddCategoryForm = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        class="fixed inset-0 flex items-center justify-center z-50 bg-gray-900/60 backdrop-blur-sm cursor-default">
        <div @click.away="showAddCategoryForm = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 relative">
            <button @click="showAddCategoryForm = false"
                class="absolute top-3 right-3 p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <h2 class="text-xl font-semibold mb-3 text-<?php echo e($color); ?>-600">New Account</h2>
            <hr class="mb-3 border-gray-200 dark:border-gray-700">
            <form action="<?php echo e(route('coa.addCategory')); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Category Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required placeholder="Enter category name"
                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm px-3 py-2 
                            focus:outline-none focus:ring-2 focus:ring-<?php echo e($color); ?>-500 focus:border-transparent transition-all duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Code <span class="text-red-500">*</span></label>
                        <input type="text" name="code" required placeholder="Enter code"
                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm px-3 py-2 
                            focus:outline-none focus:ring-2 focus:ring-<?php echo e($color); ?>-500 focus:border-transparent transition-all duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Label</label>
                        <select name="label"
                            class="w-full h-[42px] border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm px-3 py-2
                            focus:outline-none focus:ring-2 focus:ring-<?php echo e($color); ?>-500 focus:border-transparent transition-all duration-200">
                            <option value="" disabled selected>Select a label</option>
                            <?php $__currentLoopData = $labelType; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($label->value); ?>"><?php echo e(ucfirst(strtolower($label->name))); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div x-data x-init="new TomSelect($refs.entity, { closeAfterSelect: true, hideSelected: true, create: false })">
                        <label class="block text-sm font-medium mb-1">Entity</label>
                        <select data-level="<?php echo e($account->level); ?>" data-account-id="<?php echo e($account->id); ?>"
                            name="entity" x-ref="entity" class="entitySelect" placeholder="Select entity" autocomplete="off">
                            <option value="">Select entity</option>
                            <option value="client">Client</option>
                            <option value="agent">Agent</option>
                            <option value="branch">Branch</option>
                        </select>
                    </div>

                    <div id="entity-container-<?php echo e($account->id); ?>"></div>
                </div>

                <input type="hidden" name="root_id" value="<?php echo e($account->root_id); ?>">
                <input type="hidden" name="parent_id" value="<?php echo e($account->id); ?>">
                <input type="hidden" name="level" value="<?php echo e($account->level + 1); ?>">

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="showAddCategoryForm = false"
                        class="px-4 py-2 text-gray-600 bg-gray-100 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="bg-<?php echo e($color); ?>-600 text-white px-4 py-2 rounded-lg hover:bg-<?php echo e($color); ?>-700
                        shadow-lg shadow-<?php echo e($color); ?>-500/30 hover:shadow-<?php echo e($color); ?>-500/50 transition-all duration-200">
                        Create New
                    </button>
                </div>
            </form>
        </div>
    </div>

    <ul x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="ml-6 border-l-2 border-<?php echo e($color); ?>-200 dark:border-<?php echo e($color); ?>-800/50">
        <?php if($account->childAccounts && $account->childAccounts->isNotEmpty()): ?>
            <?php $__currentLoopData = $account->childAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $childAccount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php echo $__env->make('coa.partials.child-account', ['account' => $childAccount, 'color' => $color], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php else: ?>
            <li class="py-3 px-4 text-sm text-gray-400 dark:text-gray-500 italic flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                No child accounts available
            </li>
        <?php endif; ?>
    </ul>
</li>

<style>
    .ts-wrapper .ts-control {
        border: 1px solid #d1d5db !important;
        border-radius: 0.5rem !important;
        padding: 0.5rem 0.75rem !important;
        font-size: 0.875rem !important;
        line-height: 1.25rem !important;
        min-height: 42px !important;
        background: white !important;
    }
    
    .dark .ts-wrapper .ts-control {
        border-color: #4b5563 !important;
        background: #374151 !important;
    }
    
    .ts-wrapper.focus .ts-control {
        border-color: transparent !important;
        box-shadow: 0 0 0 2px var(--ts-ring-color, #22c55e) !important;
        outline: none !important;
    }
    
    .ts-wrapper .ts-control input {
        font-size: 0.875rem !important;
    }
    
    .ts-dropdown {
        border-radius: 0.5rem !important;
        border: 1px solid #d1d5db !important;
        margin-top: 4px !important;
    }
</style><?php /**PATH /home/soudshoja/soud-laravel/resources/views/coa/partials/child-account.blade.php ENDPATH**/ ?>