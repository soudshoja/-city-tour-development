<div x-data="{
    mobileDrawerOpen: false,
    activeMenu: null,
    notification: false,
    open: false,
    iataWallet: false,
    touchStartX: 0,
    touchCurrentX: 0,
    isDragging: false,
    swipeThreshold: 80,

    handleTouchStart(e) {
        this.touchStartX = e.touches[0].clientX;
        this.touchCurrentX = this.touchStartX;
        this.isDragging = true;
    },

    handleTouchMove(e) {
        if (!this.isDragging) return;
        this.touchCurrentX = e.touches[0].clientX;
        const diff = this.touchCurrentX - this.touchStartX;
        if (diff < 0) {
            this.$refs.drawer.style.transform = `translateX(${diff}px)`;
            this.$refs.backdrop.style.opacity = Math.max(0, 1 + (diff / 300));
        }
    },

    handleTouchEnd() {
        if (!this.isDragging) return;
        this.isDragging = false;
        const diff = this.touchCurrentX - this.touchStartX;

        this.$refs.drawer.style.transform = '';
        this.$refs.backdrop.style.opacity = '';

        if (diff < -this.swipeThreshold) {
            this.mobileDrawerOpen = false;
        }
    }
}"
    @open-mobile-drawer.window="mobileDrawerOpen = true"
    @keydown.escape.window="mobileDrawerOpen = false">

    <div x-show="mobileDrawerOpen"
        x-cloak
        x-ref="backdrop"
        @click="mobileDrawerOpen = false"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="mobile-drawer-backdrop">
    </div>

    <div x-show="mobileDrawerOpen"
        x-cloak
        x-ref="drawer"
        @touchstart="handleTouchStart"
        @touchmove="handleTouchMove"
        @touchend="handleTouchEnd"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="mobile-drawer">

        <div class="mobile-drawer-header">
            <a href="<?php echo e(route('dashboard')); ?>" class="mobile-drawer-logo">
                <?php if (isset($component)) { $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72 = $attributes; } ?>
<?php $component = App\View\Components\ApplicationLogo::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('application-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ApplicationLogo::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['width' => '40','height' => '40','class' => 'rounded-full']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $attributes = $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $component = $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
                <span class="mobile-drawer-brand"><?php echo e($companyName); ?></span>
            </a>
            <button @click="mobileDrawerOpen = false" class="mobile-drawer-close">
                <?php if (isset($component)) { $__componentOriginalf6464b9a54d2bedc8c500f17bdd4af0b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf6464b9a54d2bedc8c500f17bdd4af0b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.close','data' => ['class' => 'w-6 h-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.close'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-6 h-6']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf6464b9a54d2bedc8c500f17bdd4af0b)): ?>
<?php $attributes = $__attributesOriginalf6464b9a54d2bedc8c500f17bdd4af0b; ?>
<?php unset($__attributesOriginalf6464b9a54d2bedc8c500f17bdd4af0b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf6464b9a54d2bedc8c500f17bdd4af0b)): ?>
<?php $component = $__componentOriginalf6464b9a54d2bedc8c500f17bdd4af0b; ?>
<?php unset($__componentOriginalf6464b9a54d2bedc8c500f17bdd4af0b); ?>
<?php endif; ?>
            </button>
        </div>

        <div class="mobile-drawer-content">
            <a href="<?php echo e(route('dashboard')); ?>" class="mobile-drawer-item">
                <?php if (isset($component)) { $__componentOriginaldd7efffb9c9f6e09cb77b3f1b8d38adf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldd7efffb9c9f6e09cb77b3f1b8d38adf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.dashboard','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.dashboard'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldd7efffb9c9f6e09cb77b3f1b8d38adf)): ?>
<?php $attributes = $__attributesOriginaldd7efffb9c9f6e09cb77b3f1b8d38adf; ?>
<?php unset($__attributesOriginaldd7efffb9c9f6e09cb77b3f1b8d38adf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldd7efffb9c9f6e09cb77b3f1b8d38adf)): ?>
<?php $component = $__componentOriginaldd7efffb9c9f6e09cb77b3f1b8d38adf; ?>
<?php unset($__componentOriginaldd7efffb9c9f6e09cb77b3f1b8d38adf); ?>
<?php endif; ?>
                <span>Dashboard</span>
            </a>

            <div class="mobile-drawer-divider"></div>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Task')): ?>
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'tasks' ? null : 'tasks'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <?php if (isset($component)) { $__componentOriginal3b08d7ced82e42d72946e3e57042175e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3b08d7ced82e42d72946e3e57042175e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.tasks','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.tasks'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
                        <span>Tasks</span>
                    </div>
                    <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4 transition-transform duration-200','xBind:class' => 'activeMenu === \'tasks\' ? \'rotate-180\' : \'\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4 transition-transform duration-200','x-bind:class' => 'activeMenu === \'tasks\' ? \'rotate-180\' : \'\'']); ?>
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
                </button>
                <div x-show="activeMenu === 'tasks'" x-collapse class="mobile-drawer-submenu">
                    <a href="<?php echo e(route('tasks.index')); ?>" class="mobile-drawer-subitem">Tasks List</a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Payment::class)): ?>
                    <a href="<?php echo e(route('payment.outstanding')); ?>" class="mobile-drawer-subitem">Outstanding Payments</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\CoaCategory')): ?>
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'finances' ? null : 'finances'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <?php if (isset($component)) { $__componentOriginal63c41a5ad8db3c50465bfe4f67fcf34c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal63c41a5ad8db3c50465bfe4f67fcf34c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.finances','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.finances'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
                        <span>Finances</span>
                    </div>
                    <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4 transition-transform duration-200','xBind:class' => 'activeMenu === \'finances\' ? \'rotate-180\' : \'\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4 transition-transform duration-200','x-bind:class' => 'activeMenu === \'finances\' ? \'rotate-180\' : \'\'']); ?>
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
                </button>
                <div x-show="activeMenu === 'finances'" x-collapse class="mobile-drawer-submenu">
                    <a href="<?php echo e(route('coa.index')); ?>" class="mobile-drawer-subitem">Chart of Account</a>
                    <a href="<?php echo e(route('bank-payments.index')); ?>" class="mobile-drawer-subitem">Payment Voucher</a>
                    <a href="<?php echo e(route('receipt-voucher.index')); ?>" class="mobile-drawer-subitem">Receipt Voucher</a>
                    <a href="<?php echo e(route('receivable-details.receivable-create')); ?>" class="mobile-drawer-subitem">Receivable</a>
                    <a href="<?php echo e(route('payable-details.payable-create')); ?>" class="mobile-drawer-subitem">Payable</a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewCompanySummary', 'App\Models\Account')): ?>
                    <a href="<?php echo e(route('accounting.index')); ?>" class="mobile-drawer-subitem">Accounting</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Invoice')): ?>
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'invoices' ? null : 'invoices'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <?php if (isset($component)) { $__componentOriginalc46322d19064179106b5b785a736a05a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc46322d19064179106b5b785a736a05a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.invoices','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.invoices'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
                        <span>Invoices</span>
                    </div>
                    <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4 transition-transform duration-200','xBind:class' => 'activeMenu === \'invoices\' ? \'rotate-180\' : \'\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4 transition-transform duration-200','x-bind:class' => 'activeMenu === \'invoices\' ? \'rotate-180\' : \'\'']); ?>
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
                </button>
                <div x-show="activeMenu === 'invoices'" x-collapse class="mobile-drawer-submenu">
                    <a href="<?php echo e(route('invoices.index')); ?>" class="mobile-drawer-subitem">Invoices List</a>
                    <a href="<?php echo e(route('invoices.link')); ?>" class="mobile-drawer-subitem">Invoices Link</a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Payment')): ?>
                    <a href="<?php echo e(route('payment.link.index')); ?>" class="mobile-drawer-subitem">Payment Link</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Refund')): ?>
                    <a href="<?php echo e(route('refunds.index')); ?>" class="mobile-drawer-subitem">Refund</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\AutoBilling')): ?>
                    <a href="<?php echo e(route('auto-billing.index')); ?>" class="mobile-drawer-subitem">Auto Billing</a>
                    <?php endif; ?>
                    <a href="<?php echo e(route('reminder.index')); ?>" class="mobile-drawer-subitem">Reminder</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\User')): ?>
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'users' ? null : 'users'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <?php if (isset($component)) { $__componentOriginal46848001facf1cdb1a84c118cea2e25d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal46848001facf1cdb1a84c118cea2e25d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.users','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.users'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
                        <span>Users</span>
                    </div>
                    <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4 transition-transform duration-200','xBind:class' => 'activeMenu === \'users\' ? \'rotate-180\' : \'\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4 transition-transform duration-200','x-bind:class' => 'activeMenu === \'users\' ? \'rotate-180\' : \'\'']); ?>
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
                </button>
                <div x-show="activeMenu === 'users'" x-collapse class="mobile-drawer-submenu">
                    <a href="<?php echo e(route('users.index')); ?>" class="mobile-drawer-subitem">Users List</a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Company')): ?>
                    <a href="<?php echo e(route('companies.list')); ?>" class="mobile-drawer-subitem">Companies List</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Branch::class)): ?>
                    <a href="<?php echo e(route('branches.index')); ?>" class="mobile-drawer-subitem">Branches List</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Agent::class)): ?>
                    <a href="<?php echo e(route('agents.index')); ?>" class="mobile-drawer-subitem">Agents List</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Client::class)): ?>
                    <a href="<?php echo e(route('clients.index')); ?>" class="mobile-drawer-subitem">Clients List</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\Report')): ?>
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'reports' ? null : 'reports'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <?php if (isset($component)) { $__componentOriginal83f730244a1580e05abb005c9e07a001 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal83f730244a1580e05abb005c9e07a001 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.reports','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.reports'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
                        <span>Reports</span>
                    </div>
                    <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4 transition-transform duration-200','xBind:class' => 'activeMenu === \'reports\' ? \'rotate-180\' : \'\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4 transition-transform duration-200','x-bind:class' => 'activeMenu === \'reports\' ? \'rotate-180\' : \'\'']); ?>
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
                </button>
                <div x-show="activeMenu === 'reports'" x-collapse class="mobile-drawer-submenu">
                    <a href="<?php echo e(route('reports.paid-report')); ?>" class="mobile-drawer-subitem">Paid Acc Pay/Receive</a>
                    <a href="<?php echo e(route('reports.unpaid-report')); ?>" class="mobile-drawer-subitem">Unpaid Acc Pay/Receive</a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewProfitLoss', 'App\Models\Report')): ?>
                    <a href="<?php echo e(route('reports.profit-loss')); ?>" class="mobile-drawer-subitem">Profit & Loss</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewSettlement', 'App\Models\Report')): ?>
                    <a href="<?php echo e(route('reports.settlements')); ?>" class="mobile-drawer-subitem">Bank Settlement</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', 'App\Models\CoaCategory')): ?>
                    <a href="<?php echo e(route('coa.transaction')); ?>" class="mobile-drawer-subitem">Transaction List</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewCreditors', 'App\Models\Report')): ?>
                    <a href="<?php echo e(route('reports.creditors')); ?>" class="mobile-drawer-subitem">Creditors Report</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewDailySales', 'App\Models\Report')): ?>
                    <a href="<?php echo e(route('reports.daily-sales')); ?>" class="mobile-drawer-subitem">Daily Sales</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewTaskReport', 'App\Models\Report')): ?>
                    <a href="<?php echo e(route('reports.tasks')); ?>" class="mobile-drawer-subitem">Task Report</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewClientReport', 'App\Models\Report')): ?>
                    <a href="<?php echo e(route('reports.client')); ?>" class="mobile-drawer-subitem">Client Report</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'settings' ? null : 'settings'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <?php if (isset($component)) { $__componentOriginal675d5ec13ccf645c64542fb04e9f331e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal675d5ec13ccf645c64542fb04e9f331e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.settings','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.settings'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
                        <span>Settings</span>
                    </div>
                    <?php if (isset($component)) { $__componentOriginalfb5ab559e4014313073efeb5cdff727a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfb5ab559e4014313073efeb5cdff727a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.chevron-down','data' => ['class' => 'w-4 h-4 transition-transform duration-200','xBind:class' => 'activeMenu === \'settings\' ? \'rotate-180\' : \'\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.chevron-down'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-4 h-4 transition-transform duration-200','x-bind:class' => 'activeMenu === \'settings\' ? \'rotate-180\' : \'\'']); ?>
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
                </button>
                <div x-show="activeMenu === 'settings'" x-collapse class="mobile-drawer-submenu">
                    <a href="<?php echo e(route('settings.index')); ?>" class="mobile-drawer-subitem">Settings</a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage-system-settings')): ?>
                    <a href="<?php echo e(route('system-settings.index')); ?>" class="mobile-drawer-subitem">System Settings</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Supplier::class)): ?>
                    <a href="<?php echo e(route('suppliers.index')); ?>" class="mobile-drawer-subitem">Suppliers</a>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Role::class)): ?>
                    <a href="<?php echo e(route('role.index')); ?>" class="mobile-drawer-subitem">Manage Roles</a>
                    <?php endif; ?>
                    <a href="#" class="mobile-drawer-subitem">Documentations</a>
                    <a href="#" class="mobile-drawer-subitem">Help</a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\CurrencyExchange::class)): ?>
                    <a href="<?php echo e(route('exchange.index')); ?>" class="mobile-drawer-subitem">Currency Exchange</a>
                    <a href="<?php echo e(route('exchange.histories.all')); ?>" class="mobile-drawer-subitem">Exchange History</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mobile-drawer-footer">
            <div class="first-section">
                <?php if(auth()->user()->role_id == \App\Models\Role::ADMIN): ?>
                <div class="mobile-drawer-company">
                    <?php if (isset($component)) { $__componentOriginala9f01e55519b39a266bb7ec0cf1019c6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala9f01e55519b39a266bb7ec0cf1019c6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.sidebar-company','data' => ['companies' => $sidebarCompanies ?? collect(),'currentCompanyId' => $currentCompanyId ?? 1]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('sidebar-company'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['companies' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sidebarCompanies ?? collect()),'currentCompanyId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentCompanyId ?? 1)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala9f01e55519b39a266bb7ec0cf1019c6)): ?>
<?php $attributes = $__attributesOriginala9f01e55519b39a266bb7ec0cf1019c6; ?>
<?php unset($__attributesOriginala9f01e55519b39a266bb7ec0cf1019c6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala9f01e55519b39a266bb7ec0cf1019c6)): ?>
<?php $component = $__componentOriginala9f01e55519b39a266bb7ec0cf1019c6; ?>
<?php unset($__componentOriginala9f01e55519b39a266bb7ec0cf1019c6); ?>
<?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\CurrencyExchange::class)): ?>
                <div class="mobile-drawer-currency-exchange"
                    x-data="currencyConverter({ companyId: window.APP_COMPANY_ID, convertUrl: '<?php echo e(route('exchange.convert')); ?>'})">
                    <button @click="showModal = true" class="currency-exchange-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M8 15c0 2 2 3 4 3s4-1 4-3-2-3-4-3-4-1-4-3 2-3 4-3 4 1 4 3" />
                            <path d="M12 6v12" />
                        </svg>
                        <span>Currency Exchange</span>
                    </button>

                    <template x-teleport="body">
                        <div x-show="showModal" x-cloak
                            x-init="$watch('showModal', value => {
                                if (value) {
                                    $nextTick(() => {
                                        const fromEl = document.getElementById('mobileFromSelect');
                                        const toEl = document.getElementById('mobileToSelect');
                                        if (!from && fromEl) from = fromEl.value;
                                        if (!to && toEl) to = toEl.value;
                                        convertIfReady();
                                    });
                                }
                            })"
                            @click.self="showModal = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm">
                            <div @click.stop
                                class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-[calc(100vw-2rem)] max-w-sm max-h-[80vh] overflow-visible">

                                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                                    <div>
                                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Currency Exchange</h2>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs">Quick currency conversion</p>
                                    </div>
                                    <button @click="showModal = false" class="text-gray-400 hover:text-red-500 text-2xl">&times;</button>
                                </div>

                                <div class="p-4 max-h-[calc(80vh-4rem)] overflow-y-auto">
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Amount</label>
                                        <input type="text" x-model.number="amount"
                                            @input.debounce.400ms="convertIfReady"
                                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>

                                    <div class="mb-4 space-y-3">
                                        <div>
                                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">From</label>
                                            <select id="mobileFromSelect" x-model="from" @change="convertIfReady"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                                                <?php $__currentLoopData = $allIso ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php $c = $currencies[$code] ?? null; ?>
                                                <option value="<?php echo e($code); ?>"><?php echo e($code); ?></option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </select>
                                        </div>

                                        <div class="flex justify-center">
                                            <button type="button" @click="swap(); convertIfReady()" class="p-2 rounded-full border bg-white dark:bg-gray-600 shadow">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                                </svg>
                                            </button>
                                        </div>

                                        <div>
                                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">To</label>
                                            <select id="mobileToSelect" x-model="to" @change="convertIfReady"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                                                <?php $__currentLoopData = $allIso ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php $c = $currencies[$code] ?? null; ?>
                                                <option value="<?php echo e($code); ?>"><?php echo e($code); ?></option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </select>
                                        </div>
                                    </div>

                                    <template x-if="ready">
                                        <div class="text-center py-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <p class="text-sm text-gray-600 dark:text-gray-300" x-text="`${format(amount)} ${from} =`"></p>
                                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                                <span x-text="format(converted)"></span>
                                                <span x-text="to"></span>
                                            </p>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                <p x-text="`1 ${from} = ${parseFloat(rate).toFixed(4)} ${to}`"></p>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="error">
                                        <div class="mt-2 text-center text-red-500 text-sm" x-text="error"></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <?php endif; ?>

                <div class="mobile-drawer-profile-actions">
                    <div @click="notification = true">
                        <div class="mobile-drawer-action-btn w-full">
                            <?php if (isset($component)) { $__componentOriginal8f761131f58dfa1a7853debf168f89ec = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8f761131f58dfa1a7853debf168f89ec = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.notification','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.notification'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8f761131f58dfa1a7853debf168f89ec)): ?>
<?php $attributes = $__attributesOriginal8f761131f58dfa1a7853debf168f89ec; ?>
<?php unset($__attributesOriginal8f761131f58dfa1a7853debf168f89ec); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8f761131f58dfa1a7853debf168f89ec)): ?>
<?php $component = $__componentOriginal8f761131f58dfa1a7853debf168f89ec; ?>
<?php unset($__componentOriginal8f761131f58dfa1a7853debf168f89ec); ?>
<?php endif; ?>
                            <span>Notifications</span>
                        </div>
                        <div
                            x-show="notification"
                            x-cloak
                            class="notification-wrapper">
                            <div
                                @click.away="notification = false"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 transform scale-90"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 transform scale-100"
                                x-transition:leave-end="opacity-0 transform scale-90"
                                class="profile-notification-dropdown">
                                <div class="profile-notification-header">
                                    <h2 class="profile-notification-title">
                                        Notifications
                                    </h2>

                                    <!-- Close button -->
                                    <button type="button" @click.stop="notification = false" aria-label="Close">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="profile-notification-close-icon">
                                            <path d="M14.5 9.50002L9.5 14.5M9.49998 9.5L14.5 14.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                            <path d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="profile-notification-list">
                                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('notification', []);

$__html = app('livewire')->mount($__name, $__params, 'lw-2070298562-0', $__slots ?? [], get_defined_vars());

echo $__html;

unset($__html);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
                                </div>

                                <div class="profile-notification-footer">
                                    <a
                                        href="javascript:void(0);"
                                        wire:click="markAllAsRead"
                                        class="profile-notification-mark-read">
                                        Mark all as read
                                    </a>

                                    <a
                                        href="<?php echo e(route('notifications.index')); ?>"
                                        class="profile-notification-view-all">
                                        View all notifications
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-ref="walletTrigger"
                        x-init="$watch('iataWallet', value => { if (value) checkAndLoadWalletData($refs.walletTrigger); })"
                        class="flex-1">
                        <button @click="iataWallet = !iataWallet" class="mobile-drawer-action-btn w-full">
                            <?php if (isset($component)) { $__componentOriginal643fe1b47aec0b76658e1a0200b34b2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c = $attributes; } ?>
<?php $component = BladeUI\Icons\Components\Svg::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('heroicon-o-wallet'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\BladeUI\Icons\Components\Svg::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-5 h-5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c)): ?>
<?php $attributes = $__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c; ?>
<?php unset($__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal643fe1b47aec0b76658e1a0200b34b2c)): ?>
<?php $component = $__componentOriginal643fe1b47aec0b76658e1a0200b34b2c; ?>
<?php unset($__componentOriginal643fe1b47aec0b76658e1a0200b34b2c); ?>
<?php endif; ?>
                            <span>Wallet</span>
                        </button>

                        <div x-show="iataWallet" x-cloak class="wallet-wrapper">
                            <div @click.away="iataWallet = false"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 transform scale-90"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 transform scale-100"
                                x-transition:leave-end="opacity-0 transform scale-90"
                                class="profile-wallet-dropdown">
                            <div class="profile-wallet-iata-header">
                                <div class="profile-wallet-header-row">
                                    <h5 class="profile-wallet-heading">
                                        <?php if (isset($component)) { $__componentOriginal5a6b4d1d251c59913fae8edd35183a23 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5a6b4d1d251c59913fae8edd35183a23 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.wallet','data' => ['class' => 'profile-wallet-heading-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.wallet'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'profile-wallet-heading-icon']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5a6b4d1d251c59913fae8edd35183a23)): ?>
<?php $attributes = $__attributesOriginal5a6b4d1d251c59913fae8edd35183a23; ?>
<?php unset($__attributesOriginal5a6b4d1d251c59913fae8edd35183a23); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5a6b4d1d251c59913fae8edd35183a23)): ?>
<?php $component = $__componentOriginal5a6b4d1d251c59913fae8edd35183a23; ?>
<?php unset($__componentOriginal5a6b4d1d251c59913fae8edd35183a23); ?>
<?php endif; ?>
                                        IATA Company Wallet
                                    </h5>
                                    <button @click.stop="checkAndLoadWalletData($refs.walletTrigger, true)" class="profile-wallet-reload-btn" title="Reload">
                                        <?php if (isset($component)) { $__componentOriginal576f4d42079cd7cc622d4037ec77e086 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal576f4d42079cd7cc622d4037ec77e086 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.refresh','data' => ['class' => 'profile-wallet-reload-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.refresh'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'profile-wallet-reload-icon']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal576f4d42079cd7cc622d4037ec77e086)): ?>
<?php $attributes = $__attributesOriginal576f4d42079cd7cc622d4037ec77e086; ?>
<?php unset($__attributesOriginal576f4d42079cd7cc622d4037ec77e086); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal576f4d42079cd7cc622d4037ec77e086)): ?>
<?php $component = $__componentOriginal576f4d42079cd7cc622d4037ec77e086; ?>
<?php unset($__componentOriginal576f4d42079cd7cc622d4037ec77e086); ?>
<?php endif; ?>
                                        Reload
                                    </button>
                                </div>
                                <div class="iata-info profile-wallet-info"></div>
                            </div>
                            <div class="jazeera-section profile-wallet-jazeera-section">
                                <div class="profile-wallet-header-row">
                                    <h5 class="profile-wallet-heading">
                                        <?php if (isset($component)) { $__componentOriginal5a6b4d1d251c59913fae8edd35183a23 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5a6b4d1d251c59913fae8edd35183a23 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.wallet','data' => ['class' => 'profile-wallet-jazeera-heading-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.wallet'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'profile-wallet-jazeera-heading-icon']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5a6b4d1d251c59913fae8edd35183a23)): ?>
<?php $attributes = $__attributesOriginal5a6b4d1d251c59913fae8edd35183a23; ?>
<?php unset($__attributesOriginal5a6b4d1d251c59913fae8edd35183a23); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5a6b4d1d251c59913fae8edd35183a23)): ?>
<?php $component = $__componentOriginal5a6b4d1d251c59913fae8edd35183a23; ?>
<?php unset($__componentOriginal5a6b4d1d251c59913fae8edd35183a23); ?>
<?php endif; ?>
                                        Jazeera Airways Credit
                                    </h5>
                                </div>
                                <div class="jazeera-info profile-wallet-info"></div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="<?php echo e(route('profile.edit')); ?>" class="mobile-drawer-user-card">
                    <div class="mobile-drawer-user-avatar <?php echo e($color); ?>">
                        <?php if (isset($component)) { $__componentOriginal959bb22bf9fcfced3506d30ad37a3723 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal959bb22bf9fcfced3506d30ad37a3723 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.user-avatar','data' => ['class' => 'w-6 h-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-6 h-6']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal959bb22bf9fcfced3506d30ad37a3723)): ?>
<?php $attributes = $__attributesOriginal959bb22bf9fcfced3506d30ad37a3723; ?>
<?php unset($__attributesOriginal959bb22bf9fcfced3506d30ad37a3723); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal959bb22bf9fcfced3506d30ad37a3723)): ?>
<?php $component = $__componentOriginal959bb22bf9fcfced3506d30ad37a3723; ?>
<?php unset($__componentOriginal959bb22bf9fcfced3506d30ad37a3723); ?>
<?php endif; ?>
                    </div>
                    <div class="mobile-drawer-user-info">
                        <span class="mobile-drawer-user-name"><?php echo e(Auth::user()->name); ?></span>
                        <span class="mobile-drawer-user-email"><?php echo e(Auth::user()->email); ?></span>
                    </div>
                    <?php if (isset($component)) { $__componentOriginal32022bdceaa704d305484041fc21cb4a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal32022bdceaa704d305484041fc21cb4a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.edit','data' => ['class' => 'mobile-drawer-edit-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.edit'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mobile-drawer-edit-icon']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal32022bdceaa704d305484041fc21cb4a)): ?>
<?php $attributes = $__attributesOriginal32022bdceaa704d305484041fc21cb4a; ?>
<?php unset($__attributesOriginal32022bdceaa704d305484041fc21cb4a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal32022bdceaa704d305484041fc21cb4a)): ?>
<?php $component = $__componentOriginal32022bdceaa704d305484041fc21cb4a; ?>
<?php unset($__componentOriginal32022bdceaa704d305484041fc21cb4a); ?>
<?php endif; ?>
                </a>

            </div>
            <div class="mobile-drawer-utilities">
                <button id="mobileThemeToggle" class="mobile-drawer-theme-btn">
                    <?php if (isset($component)) { $__componentOriginal8830ba8b2f7854e9618874504e31c2e3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8830ba8b2f7854e9618874504e31c2e3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.theme-light','data' => ['id' => 'mobileLightIcon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.theme-light'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'mobileLightIcon']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8830ba8b2f7854e9618874504e31c2e3)): ?>
<?php $attributes = $__attributesOriginal8830ba8b2f7854e9618874504e31c2e3; ?>
<?php unset($__attributesOriginal8830ba8b2f7854e9618874504e31c2e3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8830ba8b2f7854e9618874504e31c2e3)): ?>
<?php $component = $__componentOriginal8830ba8b2f7854e9618874504e31c2e3; ?>
<?php unset($__componentOriginal8830ba8b2f7854e9618874504e31c2e3); ?>
<?php endif; ?>
                    <span>Theme</span>
                </button>

                <form method="POST" action="<?php echo e(route('logout')); ?>" class="mobile-drawer-logout-form">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="mobile-drawer-logout-btn">
                        <?php if (isset($component)) { $__componentOriginal88de01fd0a2dfb43f9ff296f6277e232 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal88de01fd0a2dfb43f9ff296f6277e232 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icons.logout','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icons.logout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal88de01fd0a2dfb43f9ff296f6277e232)): ?>
<?php $attributes = $__attributesOriginal88de01fd0a2dfb43f9ff296f6277e232; ?>
<?php unset($__attributesOriginal88de01fd0a2dfb43f9ff296f6277e232); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal88de01fd0a2dfb43f9ff296f6277e232)): ?>
<?php $component = $__componentOriginal88de01fd0a2dfb43f9ff296f6277e232; ?>
<?php unset($__componentOriginal88de01fd0a2dfb43f9ff296f6277e232); ?>
<?php endif; ?>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileThemeToggle = document.getElementById('mobileThemeToggle');
        if (mobileThemeToggle) {
            mobileThemeToggle.addEventListener('click', function() {
                const themeButton = document.getElementById('themeButton');
                if (themeButton) {
                    themeButton.click();
                }
            });
        }
    });
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/layouts/mobile-drawer.blade.php ENDPATH**/ ?>