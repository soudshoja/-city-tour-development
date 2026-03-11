<nav class="w-full">
    <menu class="flex flex-wrap gap-8 mx-4">
        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full shadow-md">
            <?php if (isset($component)) { $__componentOriginal3b08d7ced82e42d72946e3e57042175e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3b08d7ced82e42d72946e3e57042175e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.tasks','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.tasks'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3b08d7ced82e42d72946e3e57042175e)): ?>
<?php $attributes = $__attributesOriginal3b08d7ced82e42d72946e3e57042175e; ?>
<?php unset($__attributesOriginal3b08d7ced82e42d72946e3e57042175e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3b08d7ced82e42d72946e3e57042175e)): ?>
<?php $component = $__componentOriginal3b08d7ced82e42d72946e3e57042175e; ?>
<?php unset($__componentOriginal3b08d7ced82e42d72946e3e57042175e); ?>
<?php endif; ?>
            <span class="px-2 text-sm">Tasks</span>

            <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $attributes = $__attributesOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $component = $__componentOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__componentOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
        </a>
        <menu>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Task')): ?>
            <menuitem><a href="<?php echo e(route('tasks.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Tasks
                list</a></menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Payment::class)): ?>
            <menuitem>
                <a href="<?php echo e(route('payment.outstanding')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Outstanding
                    </div>
                </a>
            </menuitem>
            <?php endif; ?>
        </menu>
        </menuitem>

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <?php if (isset($component)) { $__componentOriginal63c41a5ad8db3c50465bfe4f67fcf34c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal63c41a5ad8db3c50465bfe4f67fcf34c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.finances','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.finances'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal63c41a5ad8db3c50465bfe4f67fcf34c)): ?>
<?php $attributes = $__attributesOriginal63c41a5ad8db3c50465bfe4f67fcf34c; ?>
<?php unset($__attributesOriginal63c41a5ad8db3c50465bfe4f67fcf34c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal63c41a5ad8db3c50465bfe4f67fcf34c)): ?>
<?php $component = $__componentOriginal63c41a5ad8db3c50465bfe4f67fcf34c; ?>
<?php unset($__componentOriginal63c41a5ad8db3c50465bfe4f67fcf34c); ?>
<?php endif; ?>
            <span class="px-2 text-sm">Finances</span>
            <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $attributes = $__attributesOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $component = $__componentOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__componentOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
        </a>
        <menu>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\CoaCategory')): ?>
            <menuitem><a href="<?php echo e(route('coa.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Chart Of Account</a></menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\CoaCategory')): ?>
            <menuitem><a href="<?php echo e(route('bank-payments.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Payment Voucher</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\CoaCategory')): ?>
            <menuitem><a href="<?php echo e(route('receipt-voucher.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Receipt 
                Voucher</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\CoaCategory')): ?>
            <menuitem><a href="<?php echo e(route('receivable-details.receivable-create')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Receivable</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\CoaCategory')): ?>
            <menuitem><a href="<?php echo e(route('payable-details.payable-create')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Payable</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Charge')): ?>
            <menuitem><div
                data-tooltip="This feature has been relocated to Settings."
                class="rounded-lg shadow-lg text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow cursor-not-allowed">Manage Charges</div>
            </menuitem>
            <?php endif; ?>
            <!-- <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Account')): ?>
            <menuitem><a href="<?php echo e(route('accounting.transaction')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Transactions</a>
            </menuitem>
            <?php endif; ?> -->
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewCompanySummary', 'App\Models\Account')): ?>
            <menuitem><a href="<?php echo e(route('accounting.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Accounting</a>
            </menuitem>
            <?php endif; ?>
        </menu>
        </menuitem>

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <?php if (isset($component)) { $__componentOriginalc46322d19064179106b5b785a736a05a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc46322d19064179106b5b785a736a05a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.invoices','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.invoices'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc46322d19064179106b5b785a736a05a)): ?>
<?php $attributes = $__attributesOriginalc46322d19064179106b5b785a736a05a; ?>
<?php unset($__attributesOriginalc46322d19064179106b5b785a736a05a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc46322d19064179106b5b785a736a05a)): ?>
<?php $component = $__componentOriginalc46322d19064179106b5b785a736a05a; ?>
<?php unset($__componentOriginalc46322d19064179106b5b785a736a05a); ?>
<?php endif; ?>
            <span class="px-2 text-sm">Invoices</span>
            <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $attributes = $__attributesOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $component = $__componentOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__componentOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
        </a>
        <menu>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Invoice')): ?>
            <menuitem>
            <a href="<?php echo e(route('invoices.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Invoices List</a>
            </menuitem>
            <menuitem><a href="<?php echo e(route('invoices.link')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Invoices Link</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Payment')): ?>
            <menuitem><a href="<?php echo e(route('payment.link.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Payment Link</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Refund')): ?>
            <menuitem><a href="<?php echo e(route('refunds.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Refund</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\AutoBilling')): ?>
            <menuitem><a href="<?php echo e(route('auto-billing.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Auto Billing</a>
            </menuitem>
            <?php endif; ?>
            <menuitem><a href="<?php echo e(route('reminder.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Reminder</a>
            </menuitem>
        </menu>
        </menuitem>

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <?php if (isset($component)) { $__componentOriginal46848001facf1cdb1a84c118cea2e25d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal46848001facf1cdb1a84c118cea2e25d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.users','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.users'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal46848001facf1cdb1a84c118cea2e25d)): ?>
<?php $attributes = $__attributesOriginal46848001facf1cdb1a84c118cea2e25d; ?>
<?php unset($__attributesOriginal46848001facf1cdb1a84c118cea2e25d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal46848001facf1cdb1a84c118cea2e25d)): ?>
<?php $component = $__componentOriginal46848001facf1cdb1a84c118cea2e25d; ?>
<?php unset($__componentOriginal46848001facf1cdb1a84c118cea2e25d); ?>
<?php endif; ?>
            <span class="px-2 text-sm">Users</span>
            <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $attributes = $__attributesOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $component = $__componentOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__componentOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
        </a>
        <menu>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\User')): ?>
            <menuitem>
            <a href="<?php echo e(route('users.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Users List</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Company')): ?>
            <menuitem>
            <a href="<?php echo e(route('companies.list')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Companies List</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Branch::class)): ?>
            <menuitem><a href="<?php echo e(route('branches.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Branches List</a></menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Agent::class)): ?>
            <menuitem><a href="<?php echo e(route('agents.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Agents List</a></menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Client::class)): ?>
            <menuitem><a href="<?php echo e(route('clients.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Clients List</a></menuitem>
            <?php endif; ?>

        </menu>
        </menuitem>

        <!-- <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <g fill="none">
                    <path stroke="currentColor" d="M9 6a3 3 0 1 0 6 0a3 3 0 0 0-6 0Zm-4.562 7.902a3 3 0 1 0 3 5.195a3 3 0 0 0-3-5.196Zm15.124 0a2.999 2.999 0 1 1-2.998 5.194a2.999 2.999 0 0 1 2.998-5.194Z" />
                    <path fill="currentColor" fill-rule="evenodd" d="M9.003 6.125a3 3 0 0 1 .175-1.143a8.5 8.5 0 0 0-5.031 4.766a8.5 8.5 0 0 0-.502 4.817a3 3 0 0 1 .902-.723a7.5 7.5 0 0 1 4.456-7.717m5.994 0a7.5 7.5 0 0 1 4.456 7.717q.055.028.11.06c.3.174.568.398.792.663a8.5 8.5 0 0 0-5.533-9.583a3 3 0 0 1 .175 1.143m2.536 13.328a3 3 0 0 1-1.078-.42a7.5 7.5 0 0 1-8.91 0l-.107.065a3 3 0 0 1-.971.355a8.5 8.5 0 0 0 11.066 0" clip-rule="evenodd" />
                </g>
            </svg>
            <span class="px-2 text-sm">Branches</span>

            <svg class="h-4 w-4 rotate-90" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </a>
        <menu>
            <menuitem><a href="<?php echo e(route('branches.index')); ?>" class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Branches List</a></menuitem>

        </menu>
        </menuitem> -->

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <?php if (isset($component)) { $__componentOriginal83f730244a1580e05abb005c9e07a001 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal83f730244a1580e05abb005c9e07a001 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.reports','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.reports'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal83f730244a1580e05abb005c9e07a001)): ?>
<?php $attributes = $__attributesOriginal83f730244a1580e05abb005c9e07a001; ?>
<?php unset($__attributesOriginal83f730244a1580e05abb005c9e07a001); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal83f730244a1580e05abb005c9e07a001)): ?>
<?php $component = $__componentOriginal83f730244a1580e05abb005c9e07a001; ?>
<?php unset($__componentOriginal83f730244a1580e05abb005c9e07a001); ?>
<?php endif; ?>
            <span class="px-2 text-sm">Reports</span>
            <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $attributes = $__attributesOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $component = $__componentOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__componentOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
        </a>
        <menu>
            <!-- <menuitem><a href="<?php echo e(route('reports.summary')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Summary</a>
            </menuitem>
            <menuitem><a href="<?php echo e(route('reports.accsummary')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Accounts</a>
            </menuitem>
            <menuitem><a href="<?php echo e(route('reports.performance')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Performance</a>
            </menuitem>
            <menuitem><a href="<?php echo e(route('reports.agent')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Agent
                Reports</a>
            </menuitem>
            <menuitem><a href="<?php echo e(route('reports.client')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Client
                Reports</a>
            </menuitem> -->
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Report')): ?>
            <menuitem>
            <a href="<?php echo e(route('reports.paid-report')); ?>"
                class="text-xs p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow w-full text-center break-words whitespace-normal">
                Paid Acc Pay/Receive
            </a>
            </menuitem>
            <menuitem>
            <a href="<?php echo e(route('reports.unpaid-report')); ?>"
                class="text-xs p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow w-full text-center break-words whitespace-normal">
                Unpaid Acc Pay/Receive
            </a>
            </menuitem>
            <?php endif; ?>
          <!--   <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewReconcile', 'App\Models\Report')): ?>
            <menuitem><a href="<?php echo e(route('reports.acc-reconcile')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Acc Reconcile</a>
            </menuitem>
            <?php endif; ?> -->
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewProfitLoss', 'App\Models\Report')): ?>
            <menuitem><a href="<?php echo e(route('reports.profit-loss')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Profit & Loss</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewSettlement', 'App\Models\Report')): ?>
            <menuitem>
            <a href="<?php echo e(route('reports.settlements')); ?>"
                class="block text-xs text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white shadow">
                Bank Settlement
            </a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\CoaCategory')): ?>
            <menuitem>
            <a href="<?php echo e(route('coa.transaction')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Transaction List</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewCreditors', 'App\Models\Report')): ?>
            <menuitem>
            <a href="<?php echo e(route('reports.creditors')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Creditors Report</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewDailySales', 'App\Models\Report')): ?>
            <menuitem>
            <a href="<?php echo e(route('reports.daily-sales')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Daily Sales</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewTaskReport', 'App\Models\Report')): ?>
            <menuitem>
            <a href="<?php echo e(route('reports.tasks')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Task Report</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewClientReport', 'App\Models\Report')): ?>
            <menuitem>
            <a href="<?php echo e(route('reports.client')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Client Report</a>
            </menuitem>
            <?php endif; ?>
        </menu>
        </menuitem>

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <?php if (isset($component)) { $__componentOriginal675d5ec13ccf645c64542fb04e9f331e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal675d5ec13ccf645c64542fb04e9f331e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.settings','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.settings'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal675d5ec13ccf645c64542fb04e9f331e)): ?>
<?php $attributes = $__attributesOriginal675d5ec13ccf645c64542fb04e9f331e; ?>
<?php unset($__attributesOriginal675d5ec13ccf645c64542fb04e9f331e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal675d5ec13ccf645c64542fb04e9f331e)): ?>
<?php $component = $__componentOriginal675d5ec13ccf645c64542fb04e9f331e; ?>
<?php unset($__componentOriginal675d5ec13ccf645c64542fb04e9f331e); ?>
<?php endif; ?>
            <span class="px-2 text-sm">Settings</span>
            <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $attributes = $__attributesOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__attributesOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfb5ab559e4014313073efeb5cdff727a)): ?>
<?php $component = $__componentOriginalfb5ab559e4014313073efeb5cdff727a; ?>
<?php unset($__componentOriginalfb5ab559e4014313073efeb5cdff727a); ?>
<?php endif; ?>
        </a>
        <menu>
            <menuitem>
            <a href="<?php echo e(route('settings.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Settings</a>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-system-settings')): ?>
            <menu class="flex px-2">
                <menuitem>
                <a href="<?php echo e(route('system-settings.index')); ?>"
                    class="text-xs justify-center text-center   px-4 py-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                    System Setting
                </a>
                </menuitem>
            </menu>
            <?php endif; ?>
            </menuitem>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Supplier::class)): ?>
            <menuitem>
            <a href="<?php echo e(route('suppliers.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Suppliers</a>
            </menuitem>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Role::class)): ?>
            <menuitem>
            <a href="<?php echo e(route('role.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Manage Roles</a>
            </menuitem>
            <?php endif; ?>
            <menuitem>
            <a href="#"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Documentations</a>
            </menuitem>
            <menuitem>
            <a href="#"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Help
            </a>
            </menuitem>
            <!-- Main Menu Item -->
            <menuitem>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\CurrencyExchange::class)): ?>
            <a href="<?php echo e(route('exchange.index')); ?>"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Currency
                Exchange</a>
            <menu class="flex px-2">
                <menuitem>
                <a href="<?php echo e(route('exchange.histories.all')); ?>"
                    class="text-xs justify-center text-center   px-4 py-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                    Exchange History
                </a>
                </menuitem>
            </menu>
            <?php endif; ?>
            </menuitem>

            <!-- Sub Menu -->


        </menu>
        </menuitem>

    </menu>
</nav><?php /**PATH /home/soudshoja/soud-laravel/resources/views/layouts/menu.blade.php ENDPATH**/ ?>