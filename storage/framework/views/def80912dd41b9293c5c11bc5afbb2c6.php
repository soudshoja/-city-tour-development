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
    <div class="container mx-auto px-4 py-8">

        
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">WhatsApp AI &mdash; DOTW API Tokens</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
                Manage per-company Sanctum tokens for n8n GraphQL integration. Tokens are generated per company's primary user account.
            </p>
        </div>

        
        <?php if($newTokenPlaintext): ?>
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60"
             x-data="{ copied: false }">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-lg w-full mx-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    Token Generated &mdash; Company #<?php echo e($newTokenCompanyId); ?>

                </h2>
                <p class="text-sm text-amber-600 dark:text-amber-400 mb-4">
                    Copy this token now. It will not be shown again after you close this dialog.
                </p>
                <div class="flex items-center gap-2 mb-6">
                    <input id="token-plaintext"
                           type="text"
                           readonly
                           value="<?php echo e($newTokenPlaintext); ?>"
                           class="flex-1 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm font-mono bg-gray-50 dark:bg-gray-900 dark:text-white focus:outline-none" />
                    <button
                        @click="
                            navigator.clipboard.writeText('<?php echo e($newTokenPlaintext); ?>');
                            copied = true;
                            setTimeout(() => copied = false, 2500);
                        "
                        class="px-3 py-2 text-sm rounded-md bg-blue-600 hover:bg-blue-700 text-white transition-colors whitespace-nowrap">
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" x-cloak>Copied!</span>
                    </button>
                </div>
                <div class="flex justify-end">
                    <button wire:click="dismissToken"
                            class="px-4 py-2 text-sm rounded-md bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                        I've copied it &mdash; Close
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Primary User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Token (masked)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php $__empty_1 = true; $__currentLoopData = $credentials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cred): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $user = $cred->company?->user;
                        $existingToken = $user?->tokens->first(); {{-- eager-loaded, filtered to dotw-n8n --}}
                        $hasToken = !is_null($existingToken);
                    ?>
                    <tr>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo e($cred->company?->name ?? '—'); ?>

                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">ID: <?php echo e($cred->company_id); ?></div>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if($cred->is_active): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                            <?php echo e($user?->email ?? '—'); ?>

                            <?php if(!$user): ?>
                                <span class="text-red-500 text-xs">(No user)</span>
                            <?php endif; ?>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-gray-400">
                            <?php if($hasToken): ?>
                                <?php echo e(Str::mask($existingToken->token, '*', 4)); ?>

                            <?php else: ?>
                                <span class="text-gray-400 italic">No token</span>
                            <?php endif; ?>
                        </td>

                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <button wire:click="generateToken(<?php echo e($cred->company_id); ?>)"
                                        wire:loading.attr="disabled"
                                        wire:target="generateToken(<?php echo e($cred->company_id); ?>)"
                                        class="px-3 py-1.5 rounded-md text-xs font-medium bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white transition-colors">
                                    <span wire:loading.remove wire:target="generateToken(<?php echo e($cred->company_id); ?>)">
                                        <?php echo e($hasToken ? 'Regenerate' : 'Generate'); ?>

                                    </span>
                                    <span wire:loading wire:target="generateToken(<?php echo e($cred->company_id); ?>)">
                                        Generating...
                                    </span>
                                </button>

                                <?php if($hasToken): ?>
                                <button wire:click="revokeToken(<?php echo e($cred->company_id); ?>)"
                                        wire:confirm="Revoke dotw-n8n token for <?php echo e($cred->company?->name); ?>? n8n workflows using this token will stop working immediately."
                                        wire:loading.attr="disabled"
                                        wire:target="revokeToken(<?php echo e($cred->company_id); ?>)"
                                        class="px-3 py-1.5 rounded-md text-xs font-medium bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white transition-colors">
                                    Revoke
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500 text-sm italic">
                            No companies have DOTW credentials configured yet.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

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
<?php endif; ?>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/livewire/admin/dotw-api-token-index.blade.php ENDPATH**/ ?>