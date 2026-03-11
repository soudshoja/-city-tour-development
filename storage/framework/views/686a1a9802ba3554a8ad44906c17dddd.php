<!-- Backdrop overlay for sidebar modals -->
<div
    x-show="toggle || iataWallet || open"
    x-cloak
    @click="toggle = false; iataWallet = false; open = false"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="profile-modal-backdrop">
</div>

<!-- Notification Icon -->
<div @click="toggle = true"
    class="profile-notification-wrapper">
    <div class="profile-notification-btn">
        <span class="profile-notification-badge"></span>
        <svg class="profile-notification-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path fill="currentColor" d="M10.146 3.248a2 2 0 0 1 3.708 0A7 7 0 0 1 19 10v4.697l1.832 2.748A1 1 0 0 1 20 19h-4.535a3.501 3.501 0 0 1-6.93 0H4a1 1 0 0 1-.832-1.555L5 14.697V10c0-3.224 2.18-5.94 5.146-6.752M10.586 19a1.5 1.5 0 0 0 2.829 0zM12 5a5 5 0 0 0-5 5v5a1 1 0 0 1-.168.555L5.869 17H18.13l-.963-1.445A1 1 0 0 1 17 15v-5a5 5 0 0 0-5-5" />
        </svg>
    </div>
    <div
        x-show="toggle"
        x-cloak
        @click.away="toggle = false"
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
            <button type="button" @click.stop="toggle = false" aria-label="Close">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="profile-notification-close-icon">
                    <path d="M14.5 9.50002L9.5 14.5M9.49998 9.5L14.5 14.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                    <path d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <!-- Notification List with scrollable area -->
        <div class="profile-notification-list">
            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('notification', []);

$__html = app('livewire')->mount($__name, $__params, 'lw-4126724320-0', $__slots ?? [], get_defined_vars());

echo $__html;

unset($__html);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
        </div>

        <!-- Sticky Footer Buttons -->
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

<!-- IATA Wallet -->
<div class="profile-wallet-trigger"
    x-ref="walletTrigger"
    @click="iataWallet = !iataWallet"
    x-init="$watch('iataWallet', value => { if (value) checkAndLoadWalletData($refs.walletTrigger); })">
    <div class="profile-wallet-btn">
        <div class="relative">
            <?php if (isset($component)) { $__componentOriginal643fe1b47aec0b76658e1a0200b34b2c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal643fe1b47aec0b76658e1a0200b34b2c = $attributes; } ?>
<?php $component = BladeUI\Icons\Components\Svg::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('heroicon-o-wallet'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\BladeUI\Icons\Components\Svg::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'profile-wallet-icon']); ?>
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

            <div x-cloak x-show="iataWallet" @click.away="iataWallet = false" class="profile-wallet-dropdown">
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

                        <!-- Reload Button -->
                        <button
                            @click.stop="checkAndLoadWalletData($refs.walletTrigger, true)"
                            class="profile-wallet-reload-btn"
                            title="Reload wallet data">
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

                    <div class="iata-info profile-wallet-info">
                        <!-- Initial content will be loaded by checkAndLoadWalletData() -->
                    </div>
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

<!-- Profile Picture with Dropdown -->
<div class="profile-avatar-btn <?php echo e($color); ?>">
    <div @click="open = !open" class="profile-avatar-click-area">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path fill="currentColor" d="M12 13c2.396 0 4.575.694 6.178 1.671c.8.49 1.484 1.065 1.978 1.69c.486.616.844 1.352.844 2.139c0 .845-.411 1.511-1.003 1.986c-.56.45-1.299.748-2.084.956c-1.578.417-3.684.558-5.913.558s-4.335-.14-5.913-.558c-.785-.208-1.524-.506-2.084-.956C3.41 20.01 3 19.345 3 18.5c0-.787.358-1.523.844-2.139c.494-.625 1.177-1.2 1.978-1.69C7.425 13.694 9.605 13 12 13" class="duoicon-primary-layer" />
            <path fill="currentColor" d="M12 2c3.849 0 6.255 4.167 4.33 7.5A5 5 0 0 1 12 12c-3.849 0-6.255-4.167-4.33-7.5A5 5 0 0 1 12 2" class="duoicon-secondary-layer" opacity=".3" />
        </svg>
    </div>

    <div x-cloak x-show="open" @click.away="open = false" class="profile-dropdown">
        <!-- User Information & Profile -->
        <a href="<?php echo e(route('profile.edit')); ?>">
            <div class="profile-user-info">
                <div class="profile-user-avatar">
                    <svg class="profile-user-avatar-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 13c2.396 0 4.575.694 6.178 1.671c.8.49 1.484 1.065 1.978 1.69c.486.616.844 1.352.844 2.139c0 .845-.411 1.511-1.003 1.986c-.56.45-1.299.748-2.084.956c-1.578.417-3.684.558-5.913.558s-4.335-.14-5.913-.558c-.785-.208-1.524-.506-2.084-.956C3.41 20.01 3 19.345 3 18.5c0-.787.358-1.523.844-2.139c.494-.625 1.177-1.2 1.978-1.69C7.425 13.694 9.605 13 12 13" class="duoicon-primary-layer" />
                        <path fill="currentColor" d="M12 2c3.849 0 6.255 4.167 4.33 7.5A5 5 0 0 1 12 12c-3.849 0-6.255-4.167-4.33-7.5A5 5 0 0 1 12 2" class="duoicon-secondary-layer" opacity=".3" />
                    </svg>
                </div>

                <div class="profile-user-details">
                    <h4 class="profile-user-name"><?php echo e(Auth::user()->name); ?>

                        <span class="profile-user-badge">Pro</span>
                    </h4>
                    <p class="profile-user-subtitle">View your profile</p>
                </div>
            </div>
        </a>

        <div>

            <!-- Logout -->
            <form method="POST" action="<?php echo e(route('logout')); ?>">
                <?php echo csrf_field(); ?>
                <a href="<?php echo e(route('logout')); ?>" class="profile-logout-btn"
                    onclick="event.preventDefault(); this.closest('form').submit();">
                    <svg class="profile-logout-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.023 5.5a9 9 0 1 0 9.953 0M12 2v8" color="currentColor" />
                    </svg>
                    Sign Out
                </a>
            </form>
        </div>
    </div>

</div>


<script>
// Prevent duplicate declarations when profile.blade.php is included multiple times
if (typeof window.profileScriptLoaded === 'undefined') {
    window.profileScriptLoaded = true;

function checkAndLoadWalletData(context, forceReload = false) {
    // Store context for use in callbacks
    currentWalletContext = context;

    const now = new Date().getTime();

    if (!forceReload && walletData && walletSessionExpiry && now < walletSessionExpiry) {
        displayWalletData(walletData, context);
    } else {
        if (forceReload) {
            walletData = null;
            walletSessionExpiry = null;
        }
        iataCompanyWallet(context);
    }
}

function iataCompanyWallet(context) {
    const url = "<?php echo e(route('iata.company-wallet')); ?>";
    let companyId = "<?php echo e($companyId); ?>";

    // Find elements within the context
    const iataInfo = context.querySelector('.iata-info');
    const reloadBtn = context.querySelector('.profile-wallet-reload-btn');

    if (!iataInfo) {
        // console.error('Could not find .iata-info element in context');
        return;
    }

    // Show loading state and disable reload button
    iataInfo.innerHTML = `
        <div class="flex items-center justify-center py-2">
            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 dark:border-blue-400"></div>
            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Loading...</span>
        </div>
    `;

    if (reloadBtn) {
        reloadBtn.disabled = true;
        reloadBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }

    fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                'company_id': companyId
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => Promise.reject(err));
            }
            return response.json();
        })
        .then(data => {
            // console.log('IATA Company Wallet Data:', data);
            if (data.error) {
                throw new Error(data.error);
            }

            // Cache the data with timestamp
            walletData = {
                wallets: data.wallets || [],
                iataBalance: parseFloat(data.iataBalance || 0).toFixed(3),
                walletName: data.walletName
            };
            walletSessionExpiry = new Date().getTime() + WALLET_SESSION_DURATION;

            // Display the data in the correct context
            displayWalletData(walletData, context);
        })
        .catch(error => {
            // console.error('Error fetching IATA Company Wallet:', error);

            const iataInfo = context.querySelector('.iata-info');
            if (iataInfo) {
                iataInfo.innerHTML = `
                    <div class="text-center py-4">
                        <svg class="mx-auto h-8 w-8 text-red-400 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <p class="text-sm text-red-600 dark:text-red-400 font-medium">Failed to load wallet</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${error.message || 'Please try again later'}</p>
                    </div>
                `;
            }
        })
        .finally(() => {
            const reloadBtn = context.querySelector('.profile-wallet-reload-btn');
            if (reloadBtn) {
                reloadBtn.disabled = false;
                reloadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
}

function displayWalletData(data, context) {
    const iataInfo = context.querySelector('.iata-info');

    if (!iataInfo) {
        // console.error('Could not find .iata-info element in context');
        return;
    }

    const {
        wallets,
        iataBalance,
        walletName
    } = data;

    if (wallets.length > 0) {
        const now = new Date().getTime();

        const walletsHtml = wallets.map(wallet => `
            <div class="bg-white dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex-1">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M21 7.28V5c0-1.1-.9-2-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-2.28A2 2 0 0 0 22 15V9a2 2 0 0 0-1-1.72M20 15H12V9h8zM5 19V5h14v2H12a2 2 0 0 0-2 2v6c0 1.1.9 2 2 2h7v2z"/>
                                <circle fill="currentColor" cx="16" cy="12" r="1.5"/>
                            </svg>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                ${wallet.name || wallet.wallet_name || 'Wallet'}
                            </span>
                        </div>
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">
                            ${parseFloat(wallet.balance).toFixed(3) || '0.00'} ${wallet.currency || ''}
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                            wallet.status === 'OPEN'
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                        }">
                            ${wallet.status || 'N/A'}
                        </span>
                    </div>
                </div>
            </div>
        `).join('');

        iataInfo.innerHTML = `
            <div class="space-y-3">
                <!-- Company Total (IATA Balance) -->
                <div class="bg-gradient-to-r from-green-50 to-teal-100 dark:from-blue-900/30 dark:to-indigo-900/30 rounded-lg p-4 border border-green-200 dark:border-green-800">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            <span class="text-sm font-semibold text-green-800 dark:text-green-200 uppercase tracking-wider">
                                Total Company Balance
                            </span>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-green-900 dark:text-green-100">
                        ${iataBalance || '0.000'}
                    </p>
                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                        ${wallets.length} wallet${wallets.length !== 1 ? 's' : ''} • IATA Balance
                    </p>
                </div>

                <!-- Individual Wallets -->
                <div class="space-y-2">
                    <h6 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-1">
                        Individual Wallets
                    </h6>
                    <div class="space-y-2 max-h-32 overflow-y-auto">
                        ${walletsHtml}
                    </div>
                </div>
            </div>
        `;
    } else {
        iataInfo.innerHTML = `
            <div class="text-center py-4">
                <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">No wallet data available</p>
            </div>
        `;
    }
}

function reloadJazeeraData() {
    // console.log('Reload Jazeera Airways Credit data');
    creditData = null;
    JazeeraAirwaysCredit();
}

function JazeeraAirwaysCredit() {
    const sections = document.querySelectorAll('.jazeera-section');
    const creditInfos = document.querySelectorAll('.jazeera-info');

    if (typeof data === 'undefined' || data === null) {
        sections.forEach(section => section.classList.add('hidden'));
        return;
    }

    if (!data.length) {
        sections.forEach(section => section.classList.remove('hidden'));
        creditInfos.forEach(creditInfo => {
            creditInfo.innerHTML = `
            <div class="text-center py-4">
                <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10
                    10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No Jazeera credit data available
                </p>
            </div>
            `;
        });
        return;
    }

    const total = data.reduce((sum, entry) => sum + parseFloat(entry.balance || 0), 0).toFixed(3);
    sections.forEach(section => section.classList.remove('hidden'));
    creditInfos.forEach(creditInfo => {
        creditInfo.innerHTML = `
        <div class="flex flex-col items-center py-2">
            <p class="text-lg font-semibold text-sky-700 dark:text-sky-300">${total} KWD</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Total Credit Spent</p>
        </div>
        `;
    });
}

document.addEventListener('DOMContentLoaded', JazeeraAirwaysCredit);

function displayJazeeraData(apiData) {
    const sections = document.querySelectorAll('.jazeera-section');
    const jazeeraInfos = document.querySelectorAll('.jazeera-info');
    const {
        records = [], total = 0
    } = apiData;

    if (!sections.length || !jazeeraInfos.length) return;

    if (!records.length) {
        sections.forEach(section => section.classList.remove('hidden'));
        jazeeraInfos.forEach(jazeeraInfo => {
            jazeeraInfo.innerHTML = `
            <div class="text-center py-4">
                <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10
                    10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No Jazeera credit data available
                </p>
            </div>
            `;
        });
        return;
    }

    sections.forEach(section => section.classList.remove('hidden'));
    jazeeraInfos.forEach(jazeeraInfo => {
        jazeeraInfo.innerHTML = `
        <div class="space-y-3">
            <div class="bg-gradient-to-r from-sky-50 to-blue-100 dark:from-sky-900/30 dark:to-blue-900/30 rounded-lg p-4 border border-sky-200 dark:border-sky-800">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-sky-600 dark:text-sky-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87
                            1.18 6.88L12 17.77l-6.18 3.25L7 14.14
                            2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <span class="text-sm font-semibold text-sky-800 dark:text-sky-200 uppercase tracking-wider">
                            Total Credit Spent
                        </span>
                    </div>
                </div>
                <p class="text-2xl font-bold text-sky-900 dark:text-sky-100">
                    ${parseFloat(total).toFixed(3)} KWD
                </p>
                <p class="text-xs text-sky-600 dark:text-sky-400 mt-1">
                    ${records.length} record${records.length !== 1 ? 's' : ''} • Spent Credit
                </p>
            </div>
        </div>
        `;
    });
}

}
</script>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/layouts/profile.blade.php ENDPATH**/ ?>