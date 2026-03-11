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
    <style>
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: none !important;
        }
    </style>
    <div>
        <nav class="flex items-center space-x-2 rtl:space-x-reverse text-sm mb-4 sm:mb-6 overflow-x-auto">
            <a href="<?php echo e(route('agents.index')); ?>" class="text-gray-500 hover:text-gray-700 transition whitespace-nowrap">Agents List</a>
            <span class="text-gray-400">&gt;</span>
            <span class="text-blue-600 font-medium truncate max-w-[200px] sm:max-w-none"><?php echo e($agent->name); ?>'s Details</span>
        </nav>

        <?php if($bonuses->isNotEmpty()): ?>
           <div class="mt-5 flex flex-col md:flex-row gap-5">
                <div class="w-1/3">
                    <div class="h-[300px] bg-gradient-to-br from-blue-50 to-white dark:from-gray-800 dark:to-gray-900 
                                rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 flex flex-col justify-between">
                        
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-4">
                                    <?php if (isset($component)) { $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72 = $attributes; } ?>
<?php $component = App\View\Components\ApplicationLogo::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('application-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ApplicationLogo::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-12 w-12 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-700']); ?>
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
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo e($agent->name); ?></h2>
                                        <p class="text-md text-gray-500 dark:text-gray-400"><?php echo e($agent->branch->company->name); ?></p>
                                    </div>
                                </div>
                                <button onclick="EditAgentDetails()" data-tooltip-left="Edit details"
                                        class="transition hover:text-gray-700 dark:hover:text-gray-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" class="w-5 h-5 text-gray-500">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.06c1.523-.932 3.348.892 2.416 2.416a1.724 1.724 0 001.06 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.06 2.573c.932 1.523-.893 3.348-2.416 2.416a1.724 1.724 0 00-2.573 1.06c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.06c-1.523.932-3.348-.893-2.416-2.416a1.724 1.724 0 00-1.06-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.06-2.573c-.932-1.523.893-3.348 2.416-2.416a1.724 1.724 0 002.573-1.06z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>

                            <div class="flex justify-around border-y border-gray-200 dark:border-gray-700 py-3 text-center">
                                <div>
                                    <p class="text-xs text-gray-500">Paid Invoices</p>
                                    <p class="text-sm font-semibold text-emerald-600"><?php echo e(number_format($paid, 3)); ?> KWD</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Pending Invoices</p>
                                    <p class="text-sm font-semibold text-yellow-500"><?php echo e(number_format($unpaid, 3)); ?> KWD</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Clients</p>
                                    <p class="text-sm font-semibold text-blue-600"><?php echo e($clientCount); ?></p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-y-1 text-sm mt-3">
                                <span class="text-gray-500 dark:text-gray-400">Email</span>
                                <a href="mailto:<?php echo e($agent->email); ?>" 
                                    class="text-right text-gray-800 dark:text-gray-100 hover:text-blue-600 transition">
                                    <?php echo e($agent->email); ?>

                                </a>
                                <span class="text-gray-500 dark:text-gray-400">Phone</span>
                                <a href="tel:<?php echo e($agent->phone_number); ?>" 
                                    class="text-right text-gray-800 dark:text-gray-100 hover:text-blue-600 transition">
                                    <?php echo e($agent->phone_number); ?>

                                </a>
                                <span class="text-gray-500 dark:text-gray-400">Branch</span>
                                <span class="text-right text-gray-800 dark:text-gray-100"><?php echo e($agent->branch->name); ?></span>
                                <span class="text-gray-500 dark:text-gray-400">Type</span>
                                <span class="text-right text-gray-800 dark:text-gray-100"><?php echo e($agent->agentType->name); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-2/3">
                    <div class="h-[300px] panel p-6 flex flex-col text-left rounded-lg shadow-lg bg-white dark:bg-gray-900">
                        <div class="flex justify-between items-center mb-4">
                            <h5 class="text-lg font-semibold dark:text-white-light">Bonus Records</h5>

                            <form method="GET" action="<?php echo e(route('agents.show', $agent->id)); ?>"
                                class="flex items-center gap-3 bg-white/60 dark:bg-gray-800/40 backdrop-blur-md px-4 py-2 rounded-full shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">

                                <div class="relative">
                                    <select name="filter_month" onchange="this.form.submit()"
                                        class="appearance-none text-sm bg-transparent border-none focus:ring-0 dark:text-gray-100 text-gray-700 cursor-pointer pr-5">
                                        <?php $__currentLoopData = range(1, 12); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($m); ?>" <?php echo e(request('filter_month', now()->month) == $m ? 'selected' : ''); ?>>
                                                <?php echo e(\Carbon\Carbon::create()->month($m)->format('F')); ?>

                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>

                                <span class="text-gray-400 text-sm">/</span>

                                <div class="relative">
                                    <select name="filter_year" onchange="this.form.submit()"
                                        class="appearance-none text-sm bg-transparent border-none focus:ring-0 dark:text-gray-100 text-gray-700 cursor-pointer pr-5">
                                        <?php $__currentLoopData = range(now()->year, now()->year - 5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $y): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($y); ?>" <?php echo e(request('filter_year', now()->year) == $y ? 'selected' : ''); ?>>
                                                <?php echo e($y); ?>

                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                            </form>
                        </div>

                        <div class="mb-4 flex items-center justify-between">
                            <span class="text-md font-medium text-gray-600 dark:text-gray-400">Total Bonus</span>
                            <div class="text-right leading-tight">
                                <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                    <?php echo e(number_format($bonuses->sum('amount'), 2)); ?> KWD
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Last updated:
                                    <span class="text-blue-600 dark:text-blue-400"><?php echo e(now()->format('jS M Y')); ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                                    <tr>
                                        <th class="py-2 px-4 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Payment Ref</th>
                                        <th class="py-2 px-4 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Description</th>
                                        <th class="py-2 px-4 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Amount</th>
                                        <th class="py-2 px-4 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $bonuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bonus): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr class="transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-700">
                                        <td class="py-2 px-4 text-gray-700 dark:text-gray-500 font-medium"><?php echo e($bonus->transaction?->reference_number); ?></td>
                                        <td class="py-2 px-4 text-gray-700 dark:text-gray-500"><?php echo e($bonus->transaction?->description); ?></td>
                                        <td class="py-2 px-4 text-right font-semibold text-gray-700 dark:text-gray-500"><?php echo e(number_format($bonus->amount, 2)); ?></td>
                                        <td class="py-2 px-4 text-right font-semibold text-gray-700 dark:text-gray-500"><?php echo e($bonus->created_at->format('d M Y')); ?></td>
                                    </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-gray-500 dark:text-gray-400 italic">
                                            No bonus record found for this month.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        <?php else: ?>
            <div class="w-full">
                <div class="h-[310px] bg-gradient-to-br from-blue-50 to-white dark:from-gray-800 dark:to-gray-900 
                            rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 flex flex-col justify-between">
                    
                    <div>
                        <div class="flex items-center justify-between mb-5">
                            <div class="flex items-center gap-4">
                                <?php if (isset($component)) { $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72 = $attributes; } ?>
<?php $component = App\View\Components\ApplicationLogo::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('application-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ApplicationLogo::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-14 w-14 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-700']); ?>
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
                                <div>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white"><?php echo e($agent->name); ?></h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($agent->branch->company->name); ?></p>
                                </div>
                            </div>
                            <button onclick="EditAgentDetails()" data-tooltip-left="Edit details"
                                    class="transition hover:text-gray-700 dark:hover:text-gray-300">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="w-6 h-6 text-gray-500">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.06c1.523-.932 3.348.892 2.416 2.416a1.724 1.724 0 001.06 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.06 2.573c.932 1.523-.893 3.348-2.416 2.416a1.724 1.724 0 00-2.573 1.06c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.06c-1.523.932-3.348-.893-2.416-2.416a1.724 1.724 0 00-1.06-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.06-2.573c-.932-1.523.893-3.348 2.416-2.416a1.724 1.724 0 002.573-1.06z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex justify-around border-y border-gray-200 dark:border-gray-700 py-3 text-center">
                            <div>
                                <p class="text-sm text-gray-500">Paid Invoices</p>
                                <p class="text-base font-semibold text-emerald-600"><?php echo e(number_format($paid, 3)); ?> KWD</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Pending Invoices</p>
                                <p class="text-base font-semibold text-yellow-500"><?php echo e(number_format($unpaid, 3)); ?> KWD</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Clients</p>
                                <p class="text-base font-semibold text-blue-600"><?php echo e($clientCount); ?></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-y-2 text-sm mt-4">
                            <span class="text-gray-600 dark:text-gray-400">Email</span>
                            <a href="mailto:<?php echo e($agent->email); ?>" 
                                class="text-right text-gray-900 dark:text-gray-100 hover:text-blue-600 transition">
                                <?php echo e($agent->email); ?>

                            </a>
                            <span class="text-gray-600 dark:text-gray-400">Phone</span>
                            <a href="tel:<?php echo e($agent->phone_number); ?>" 
                                class="text-right text-gray-900 dark:text-gray-100 hover:text-blue-600 transition">
                                <?php echo e($agent->phone_number); ?>

                            </a>
                            <span class="text-gray-600 dark:text-gray-400">Branch</span>
                            <span class="text-right text-gray-900 dark:text-gray-100"><?php echo e($agent->branch->name); ?></span>
                            <span class="text-gray-600 dark:text-gray-400">Type</span>
                            <span class="text-right text-gray-900 dark:text-gray-100"><?php echo e($agent->agentType->name); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div id="editAgentModal" onclick="closemodalContentAgentIfClickedOutside(event)"
            class="fixed z-10 inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm hidden">
            <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative">

                <button onclick="closeAgentModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 text-center">Edit Agent Details</h2>

                <form id="agentForm" method="POST" action="<?php echo e(route('agents.update', $agent->id)); ?>" class="space-y-4">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>

                    <div class="space-y-1">
                        <label for="name" class="block text-sm font-semibold text-gray-700">Name</label>
                        <input id="name" name="name" type="text" value="<?php echo e($agent->name); ?>" required
                            class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="agent Name" />
                    </div>

                    <div class="space-y-1">
                        <label for="email" class="block text-sm font-semibold text-gray-700">Email</label>
                        <input id="email" name="email" type="email" value="<?php echo e($agent->email); ?>" required
                            class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="agent Email" />
                    </div>

                    <div class="mb-6">
                        <label for="phone_number" class="block text-gray-700 font-semibold mb-2">Phone Number</label>
                        <input type="text" name="phone_number" id="phone_number" value="<?php echo e($agent->phone_number); ?>" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <div x-data="{ typeId: <?php echo e((int) $agent->type_id); ?> }" class="space-y-4">
                        <div class="mb-6">
                            <label for="type" class="block text-gray-700 font-semibold mb-2">Type</label>
                            <select name="type_id" id="type" x-model.number="typeId"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                                <?php $__currentLoopData = $agentType; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($type->id); ?>">
                                    <?php echo e($type->name); ?>

                                </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="mb-6" x-show="typeId !== 2" x-transition>
                            <label for="salary" class="block text-gray-700 font-semibold mb-2">Salary</label>
                            <input type="number" name="salary" id="salary" value="<?php echo e($agent->salary); ?>" min="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        </div>
                        <div class="mb-6" x-show="typeId === 3 || typeId === 4" x-transition>
                            <label for="target" class="block text-gray-700 font-semibold mb-2">Target</label>
                            <input type="number" name="target" id="target" value="<?php echo e($agent->target); ?>" min="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        </div>
                    </div>

                    <?php if(in_array('Amadeus', $supplierCompany)): ?>
                    <label for="amadeus_id" class="block text-gray-700 font-semibold mb-2">Amadeus ID</label>
                    <input class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300"
                        type="text" name="amadeus_id" id="amadeus_id" placeholder="Amadeus ID" value="<?php echo e($agent->amadeus_id); ?>">
                    <?php endif; ?>

                    <?php if(in_array('TBO Holiday', $supplierCompany)): ?>
                    <label for="tbo_reference" class="block text-gray-700 font-semibold mb-2">TBO Reference</label>
                    <input class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300"
                        type="text" name="tbo_reference" id="tbo_reference" placeholder="TBO Reference" value="<?php echo e($agent->tbo_reference); ?>">
                    <?php endif; ?>

                    <div class="flex space-x-2">
                        <button type="submit" class="p-2 btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                            Update Agent
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-5 panel">
            <div class="mb-5 flex justify-between items-center">
                <h5 class="text-lg font-semibold dark:text-white-light">
                    <span class="customBlueColor">Clients</span> List
                </h5>
            </div>
            <div>
                <?php if($clients->isEmpty()): ?>
                <p class="text-gray-600">No clients for this agent.</p>
                <?php else: ?>
                <div class="max-h-72 overflow-y-auto custom-scrollbar flex-1 rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800">
                    <table class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 w-full rounded-lg overflow-hidden">
                        <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                            <tr class="py-3 px-4 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">
                                <th class="px-6">Client Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Paid</th>
                                <th>Pending</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-700">
                                <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                    <?php echo e($client->full_name); ?>

                                </td>
                                <td class="py-4 px-6 text-gray-700 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700"><?php echo e($client->email); ?></td>
                                <td class="py-4 px-6 text-gray-700 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700"><?php echo e($client->country_code); ?><?php echo e($client->phone); ?></td>
                                <td class="py-4 px-6 text-gray-700 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700">
                                    <?php echo e(ucwords(strtolower($client->address ?? 'Not Set'))); ?>

                                </td>
                                <td class="py-4 px-6 border-b border-gray-200 dark:border-gray-700">
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                        bg-green-100 text-green-700 ring-1 ring-green-500 shadow-none focus:outline-none focus:ring-0">
                                        <?php echo e($client->paid); ?> KWD
                                    </span>
                                </td>
                                <td class="py-4 px-6 border-b border-gray-200 dark:border-gray-700">
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                        bg-yellow-100 text-yellow-700 ring-1 ring-yellow-400 shadow-none focus:outline-none focus:ring-0">
                                        <?php echo e($client->unpaid); ?> KWD
                                    </span>
                                </td>
                                <td class="py-4 px-6 border-b border-gray-200 dark:border-gray-700">
                                    <a href="<?php echo e(url('/clients/' . $client->id)); ?>" class="text-blue-500 hover:underline">View</a>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <?php echo e($clients->appends(['section' => 'clients'])->links()); ?>

                </div>
                <?php endif; ?>

            </div>
        </div>

        <div class="mt-5 panel">
            <div class="mb-5 flex items-center justify-between">
                <h5 class="text-lg font-semibold dark:text-white-light">
                    <span class="customBlueColor">Invoices</span> List
                </h5>

                <form method="GET"
                    class="ml-auto flex items-center gap-3 bg-white/60 dark:bg-gray-800/40 backdrop-blur-md px-4 py-2 rounded-full shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">

                    <div class="relative">
                        <input type="month" id="month" name="month"
                            value="<?php echo e(request('month', now()->format('Y-m'))); ?>"
                            class="appearance-none bg-transparent border-none text-sm text-gray-700 dark:text-gray-100 focus:ring-0 cursor-pointer">
                    </div>

                    <button type="submit"
                        class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium px-4 py-1.5 rounded-full transition">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>

                    <?php if(request()->has('month')): ?>
                        <a href="<?php echo e(url()->current()); ?>"
                            class="bg-red-100 hover:bg-red-200 text-red-700 dark:bg-red-900/30 dark:text-red-300 text-sm font-medium px-4 py-1.5 rounded-full transition">
                            <i class="fas fa-times mr-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="mt-5 mb-5 flex flex-wrap justify-center items-center gap-5">
                <div class="flex-1 min-w-[220px] bg-green-600 text-white px-4 py-3 rounded-lg shadow-sm">
                    <p class="text-sm opacity-80">Total Client Paid</p>
                    <p class="text-xl font-bold"><?php echo e($totalPaid); ?> KWD</p>
                </div>

                <div class="flex-1 min-w-[220px] bg-red-600 text-white px-4 py-3 rounded-lg shadow-sm">
                    <p class="text-sm opacity-80">Total Client Outstanding</p>
                    <p class="text-xl font-bold"><?php echo e($totalOutstanding); ?> KWD</p>
                </div>

                <?php if($agent->type_id != 1): ?>
                <div class="flex-1 min-w-[220px] bg-yellow-500 text-gray-900 px-4 py-3 rounded-lg shadow-sm">
                    <p class="text-sm opacity-80">Total Commission</p>
                    <p class="text-xl font-bold"><?php echo e($totalCommission); ?> KWD</p>
                </div>
                <?php endif; ?>

                <div class="flex-1 min-w-[220px] bg-blue-600 text-white px-4 py-3 rounded-lg shadow-sm">
                    <p class="text-sm opacity-80">Total Profit</p>
                    <p class="text-xl font-bold"><?php echo e($totalProfit); ?> KWD</p>
                </div>
            </div>
  
            <div>
                <?php if($invoices->isEmpty() && request()->has('month')): ?>
                    <p class="font-semibold text-gray-600">No invoices found for <?php echo e(\Carbon\Carbon::parse(request('month'))->format('F Y')); ?>.</p>
                    <p class="text-sm mt-1 text-gray-600">Try selecting a different month or clear the filter.</p>
                <?php elseif($invoices->isEmpty()): ?>
                <p class="text-gray-600">No invoices for this agent.</p>
                <?php else: ?>
                <div class="max-h-100 overflow-y-auto custom-scrollbar flex-1 rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800" x-data="{ openRow: null }">
                    <table class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 w-full rounded-lg overflow-hidden">
                        <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                            <tr class="text-center">
                                <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Invoice Number</th>
                                <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Invoice Date</th>
                                <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Status</th>
                                <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Tasks Count</th>
                                <?php if(in_array($agent->type_id, [2, 3, 4])): ?>
                                <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Commission (KWD)</th>
                                <?php endif; ?>
                                <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Profit (KWD)</th>
                                <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Client</th>
                                <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="cursor-pointer text-center transition-all duration-200 
                                    hover:bg-blue-100 dark:hover:bg-blue-700 text-gray-700 dark:text-gray-200"
                                    :class="openRow === <?php echo e($invoice->id); ?> 
                                        ? 'bg-blue-50 dark:bg-blue-900' 
                                        : ''"
                                    @click="openRow === <?php echo e($invoice->id); ?> ? openRow = null : openRow = <?php echo e($invoice->id); ?>">

                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                        <a href="<?php echo e(route('invoice.details', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>"
                                            class="text-blue-500 hover:underline" @click.stop target="_blank"> <?php echo e($invoice->invoice_number); ?>

                                        </a>
                                    </td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700"><?php echo e(\Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')); ?></td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                        <?php if($invoice->status == 'paid'): ?>
                                            <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                                bg-green-100 text-green-700 ring-1 ring-green-500 shadow-none focus:outline-none focus:ring-0">
                                                <?php echo e(ucfirst($invoice->status)); ?>

                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                                bg-red-100 text-red-700 ring-1 ring-red-400 shadow-none focus:outline-none focus:ring-0">
                                                <?php echo e(ucfirst($invoice->status)); ?>

                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700"><?php echo e($invoice->task_count); ?></td>
                                    <?php if(in_array($agent->type_id, [2, 3, 4])): ?>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700 text-green-700 font-semibold">
                                        <?php echo e($invoice->total_commission); ?>

                                    </td>
                                    <?php endif; ?>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700 text-blue-700 font-semibold">
                                        <?php echo e($invoice->total_profit); ?>

                                    </td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700"><?php echo e($invoice->client->full_name); ?></td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                        <a href="<?php echo e(route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])); ?>" class="text-blue-500 hover:underline" @click.stop target="_blank">View</a>
                                    </td>
                                </tr>
                                <tr x-show="openRow === <?php echo e($invoice->id); ?>" x-cloak>
                                    <td colspan="<?php echo e(in_array($agent->type_id, [2, 3, 4]) ? 8 : 7); ?>" class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 rounded-b-lg shadow-inner">
                                        <div class="space-y-4">
                                            <h4 class="font-semibold text-lg mb-3">Tasks in this Invoice:</h4>
                                            <?php $__currentLoopData = $invoice->tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                    <div><strong>Reference:</strong> <?php echo e($task['task_reference']); ?></div>
                                                    <div><strong>Passenger:</strong> <?php echo e($task['passenger_name']); ?></div>
                                                    <div><strong>Task Price:</strong> <?php echo e(number_format($task['task_price'], 2)); ?> KWD</div>
                                                    <div><strong>Markup:</strong> <?php echo e(number_format($task['markup_price'], 2)); ?> KWD</div>
                                                </div>
                                            </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            <?php if($invoice->invoice_charge > 0): ?>
                                            <div class="bg-yellow-50 dark:bg-yellow-900 p-3 rounded-lg border border-yellow-200 dark:border-yellow-700">
                                                <div><strong>Invoice Charge:</strong> <?php echo e(number_format($invoice->invoice_charge, 2)); ?> KWD</div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <?php echo e($invoices->appends(['section' => 'invoices', 'month' => request('month')])->links()); ?>

                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-5 panel">
             <div class="mb-5 flex justify-between items-center">
                <h5 class="text-lg font-semibold dark:text-white-light">
                    <span class="customBlueColor">Tasks</span> List
                </h5>

                <div class="flex gap-2 items-center">
                    <div class="relative group inline-flex items-center px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-700 ring-1 ring-green-400 cursor-default">
                        <?php echo e($taskInvoiced); ?> Invoiced
                        <div class="absolute right-0 -top-11 bg-gray-900 text-gray-100 text-sm rounded-md px-3 py-2 invisible group-hover:visible opacity-0 group-hover:opacity-100
                            transition-all duration-200 shadow-lg w-48 z-10">
                            Task that invoiced
                        </div>
                    </div>

                    <div class="relative group inline-flex items-center px-3 py-1 text-sm font-medium rounded-full bg-red-100 text-red-700 ring-1 ring-red-400 cursor-default">
                        <?php echo e($taskNotInvoiced); ?> Not Invoiced
                        <div class="absolute right-0 -top-11 bg-gray-900 text-gray-100 text-sm rounded-md px-3 py-2 invisible group-hover:visible opacity-0 group-hover:opacity-100
                            transition-all duration-200 shadow-lg w-60 z-10">
                            Task that not invoiced yet
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <div class="">
                    <?php if($tasks->isEmpty()): ?>
                    <div class="max-h-96 overflow-y-auto custom-scrollbar">
                        <p class="text-gray-600">No tasks for this agent.</p>
                    </div>
                    <?php else: ?>
                    <div class="max-h-98 overflow-y-auto custom-scrollbar flex-1 rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800">
                        <table class="min-w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 w-full rounded-lg overflow-hidden">
                            <thead class= "sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                                <tr>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Task Name</th>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Task Date</th>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Status</th>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Client</th>
                                </tr>
                            </thead>
                            <tbody class="overflow-auto">
                                <?php $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="<?php echo e($task->invoiceDetail !== null ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'); ?> transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-700 text-center">
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700"> <?php echo e($task->reference); ?>-<?php echo e($task->additional_info); ?> <?php echo e($task->venue); ?></td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700"><?php echo e($task->created_at); ?></td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700"><?php echo e(ucfirst($task->status)); ?></td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700"><?php echo e($task->client !== null ? $task->client->full_name : $task->client_name ?? 'Not Set'); ?></td>
                                </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <?php echo e($tasks->appends(['section' => 'tasks'])->links()); ?>

                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <script>
        let agentFormOriginalClone = null;

        window.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('agentForm');
            if (form) {
                agentFormOriginalClone = form.cloneNode(true);
            }
        });

        // edit company details modal
        function EditAgentDetails() {
            const modal = document.getElementById('editAgentModal');
            const formContainer = modal.querySelector('#agentForm');
            if (formContainer && agentFormOriginalClone) {
                formContainer.replaceWith(agentFormOriginalClone.cloneNode(true));
            }

            modal.classList.remove('hidden');
        }

        function closeAgentModal() {
            // Hide the modal when "Cancel" is clicked
            document.getElementById('editAgentModal').classList.add('hidden');
        }

        function closemodalContentAgentIfClickedOutside(event) {
            // Close the modal if the user clicks outside of the modal content
            const modalContentAgent = document.querySelector('#editAgentModal > div');
            if (!modalContentAgent.contains(event.target)) {
                closeAgentModal();
            }
        }
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/agents/agentsShow.blade.php ENDPATH**/ ?>