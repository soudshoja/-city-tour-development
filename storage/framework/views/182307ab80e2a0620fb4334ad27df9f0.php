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
    <div x-data="{ showFilter: false }">
        <!-- Page Heading -->
        <div class="flex justify-between items-center gap-5 my-3 mb-4">
            <div class="flex items-center space-x-4">
                <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center heartbeat">
                    <a href="javascript:history.back()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                            <path fill="#FFC107" fill-rule="evenodd"
                                d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                        </svg>
                    </a>
                </div>
                <h2 class="text-3xl font-bold dark:text-white">All Transaction Records</h2>
            </div>

            <!-- Filter + Export -->
            <div class="flex items-center space-x-4 mb-6">
                <!-- Filter Button & Modal -->
                <div class="relative" x-data="{ showFilter: false }"
                    @click.outside="if (!$event.target.closest('.flatpickr-calendar') && !$event.target.closest('#date-range')) { showFilter = false}">
                    <button @click="showFilter = !showFilter"
                        class="dark:text-white flex px-5 py-3 gap-2 city-light-yellow rounded-lg BoxShadow items-center text-xs md:text-sm">
                        <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                            <path fill="currentColor"
                                d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                        </svg>
                        <span class="text-xs md:text-sm dark:text-white">Filters</span>
                    </button>

                    <div x-cloak x-show="showFilter" x-transition
                        class="absolute right-0 mt-2 w-72 bg-white shadow-md p-4 rounded-lg border border-gray-300 z-50">
                        <form method="POST" action="<?php echo e(route('coa.transaction')); ?>" class="flex flex-col space-y-4">
                            <?php echo csrf_field(); ?>
                            <?php if(auth()->user()->role_id == \App\Models\Role::ADMIN && request('company_id')): ?>
                                <input type="hidden" name="company_id" value="<?php echo e(request('company_id')); ?>">
                            <?php endif; ?>
                            <div class="flex flex-col">
                                <label class="text-xs font-semibold text-gray-700 mb-1">Date Range</label>
                                <input type="text" id="date-range" class="form-select cursor-pointer bg-white dark:bg-gray-900" placeholder="Select date range" autocomplete="off" />
                                <input type="hidden" id="from_date" value="<?php echo e(request('from_date')); ?>">
                                <input type="hidden" id="to_date" value="<?php echo e(request('to_date')); ?>">
                            </div>
                            <div x-data="{ open: false }" class="relative">
                                <label class="text-xs font-semibold text-gray-700 mb-1 block">Reference Type</label>
                                <button type="button" @click="open = !open"
                                    class="w-full text-left border rounded-md px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-300 flex justify-between items-center">
                                    <span>Select reference type</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div x-show="open" @click.outside="open = false"
                                    class="absolute mt-1 w-full bg-white border rounded-md shadow-lg max-h-48 overflow-y-auto z-50">
                                    <?php $__currentLoopData = ['Receipt','Invoice','Payment','Refund']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 text-sm">
                                        <input type="checkbox" name="reference_type[]" value="<?php echo e($opt); ?>"
                                            class="mr-2 rounded border-gray-300"
                                            <?php if(collect(request('reference_type', []))->contains($opt)): echo 'checked'; endif; ?>>
                                        <?php echo e($opt); ?>

                                    </label>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                            <div x-data="{ open: false }" class="relative">
                                <label class="text-xs font-semibold text-gray-700 mb-1 block">Entity Type</label>
                                <button type="button" @click="open = !open"
                                    class="w-full text-left border rounded-md px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-300 flex justify-between items-center">
                                    <span>Select entity type</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div x-show="open" @click.outside="open = false"
                                    class="absolute mt-1 w-full bg-white border rounded-md shadow-lg max-h-48 overflow-y-auto z-50">
                                    <?php $__currentLoopData = ['company','branch','agent','client']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 text-sm">
                                        <input type="checkbox" name="entity_type[]" value="<?php echo e($opt); ?>"
                                            class="mr-2 rounded border-gray-300"
                                            <?php if(collect(request('entity_type', []))->contains($opt)): echo 'checked'; endif; ?>>
                                        <?php echo e(ucfirst($opt)); ?>

                                    </label>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                            <?php if (isset($component)) { $__componentOriginalca22bd07186d77d4a177532dc60413c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalca22bd07186d77d4a177532dc60413c3 = $attributes; } ?>
<?php $component = App\View\Components\MultiPicker::resolve(['label' => 'Agents','name' => 'agent_ids','items' => $agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->values()->all(),'preselected' => collect(request('agent_ids', []))->map(fn($v) => (int)$v)->all(),'allLabel' => 'All agents','placeholder' => 'Search agents...'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('multi-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\MultiPicker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $attributes = $__attributesOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__attributesOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $component = $__componentOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__componentOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginalca22bd07186d77d4a177532dc60413c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalca22bd07186d77d4a177532dc60413c3 = $attributes; } ?>
<?php $component = App\View\Components\MultiPicker::resolve(['label' => 'Accounts','name' => 'account_ids','items' => $accounts->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->values()->all(),'preselected' => collect(request('account_ids', []))->map(fn($v) => (int)$v)->all(),'allLabel' => 'All accounts','placeholder' => 'Search accounts...'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('multi-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\MultiPicker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $attributes = $__attributesOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__attributesOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalca22bd07186d77d4a177532dc60413c3)): ?>
<?php $component = $__componentOriginalca22bd07186d77d4a177532dc60413c3; ?>
<?php unset($__componentOriginalca22bd07186d77d4a177532dc60413c3); ?>
<?php endif; ?>
                            <div class="flex justify-between space-x-2">
                                <a href="<?php echo e(route('coa.transaction', auth()->user()->role_id == \App\Models\Role::ADMIN ? ['company_id' => request('company_id')] : [])); ?>"
                                    class="px-4 py-2 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400">
                                    Reset
                                </a>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-full">Apply Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Export Button -->
                <!-- <button class="dark:text-white flex px-5 py-3 gap-2 city-light-yellow rounded-lg BoxShadow items-center text-xs md:text-sm">
                    <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="currentColor"
                            d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                    </svg>
                    <span class="text-xs md:text-sm">Export</span>
                </button> -->
            </div>
        </div>

        <!-- Transaction List -->
        <div class="bg-white rounded-lg shadow-sm dark:bg-gray-800">
            <?php if($transactions->isEmpty()): ?>
            <div class="text-center text-gray-600 py-20">
                <h3 class="text-lg font-semibold">No transactions found</h3>
                <p class="text-sm text-gray-500 mt-1">Try adjusting your filters or date range.</p>
                <a href="<?php echo e(route('coa.transaction', auth()->user()->role_id == \App\Models\Role::ADMIN ? ['company_id' => request('company_id')] : [])); ?>"
                class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-full hover:bg-blue-700">Reset Filter</a>
            </div>
            <?php else: ?>
            <div class="relative p-4 max-h-[90vh] overflow-y-auto rounded-lg shadow">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-200 sticky top-0 z-20">
                        <tr>
                            <th class="p-3 border-b text-left w-[140px] text-md font-bold text-gray-900 dark:text-gray-300">Date</th>
                            <th class="p-3 border-b text-left w-[160px] text-md font-bold text-gray-900 dark:text-gray-300">Agent Name</th>
                            <th class="p-3 border-b text-left w-[235px] text-md font-bold text-gray-900 dark:text-gray-300">Description</th>
                            <th class="p-3 border-b text-left w-[160px] text-md font-bold text-gray-900 dark:text-gray-300">Account</th>
                            <th class="p-3 border-b text-right w-[90px] text-md font-bold text-gray-900 dark:text-gray-300">Debit</th>
                            <th class="p-3 border-b text-right w-[90px] text-md font-bold text-gray-900 dark:text-gray-300">Credit</th>
                            <th class="p-3 border-b text-right w-[115px] text-md font-bold text-gray-900 dark:text-gray-300">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $entries = $transaction->journalEntries ?? collect();
                            ?>
                            <?php if($entries->isNotEmpty()): ?>
                                <?php $__currentLoopData = $entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="p-[0.65rem]">
                                        <td class="border-b"><?php echo e(\Carbon\Carbon::parse($transaction->transaction_date)->format('d F Y')); ?></td>
                                        <td class="border-b"> <?php echo e($entry->task && $entry->task->agent ? $entry->task->agent->name
                                            : ($entry->invoice && $entry->invoice->agent ? $entry->invoice->agent->name : 'N/A')); ?>

                                        </td>
                                        <td class="border-b"><?php echo e($transaction->description ?? 'N/A'); ?></td>
                                        <td class="border-b"><?php echo e($entry->account->name ?? 'N/A'); ?></td>
                                        <td class="border-b text-right <?php echo e($entry->debit != 0 ? 'text-green-600 font-semibold' : 'text-gray-900'); ?>">
                                            <?php echo e(number_format($entry->debit, 2)); ?>

                                        </td>
                                        <td class="border-b text-right <?php echo e($entry->credit != 0 ? 'text-red-600 font-semibold' : 'text-gray-900'); ?>">
                                            <?php echo e(number_format($entry->credit, 2)); ?>

                                        </td>
                                        <td class="border-b text-right"><?php echo e(number_format($entry->running_balance ?? 0, 2)); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php else: ?>
                            <tr class="p-[0.65rem]">
                                <td class="border-b"><?php echo e(\Carbon\Carbon::parse($transaction->transaction_date)->format('d F Y')); ?></td>
                                <td class="border-b">N/A</td>
                                <td class="border-b"><?php echo e($transaction->description ?? 'N/A'); ?></td>
                                <td colspan="4" class="border-b text-center text-gray-500 font-semibold italic">No journal entries</td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="mt-2">
            <?php if (isset($component)) { $__componentOriginal41032d87daf360242eb88dbda6c75ed1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41032d87daf360242eb88dbda6c75ed1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pagination','data' => ['data' => $transactions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($transactions)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41032d87daf360242eb88dbda6c75ed1)): ?>
<?php $attributes = $__attributesOriginal41032d87daf360242eb88dbda6c75ed1; ?>
<?php unset($__attributesOriginal41032d87daf360242eb88dbda6c75ed1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41032d87daf360242eb88dbda6c75ed1)): ?>
<?php $component = $__componentOriginal41032d87daf360242eb88dbda6c75ed1; ?>
<?php unset($__componentOriginal41032d87daf360242eb88dbda6c75ed1); ?>
<?php endif; ?>
        </div>
    </div>

    <script>
        const fromEl = document.getElementById('from_date');
        const toEl = document.getElementById('to_date');
        if (fromEl.value) fromEl.name = 'from_date'; else fromEl.removeAttribute('name');
        if (toEl.value) toEl.name = 'to_date'; else toEl.removeAttribute('name');

        flatpickr("#date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: ["<?php echo e(request('from_date')); ?>","<?php echo e(request('to_date')); ?>"].filter(Boolean),
            onChange: function(selectedDates) {
                const [start, end] = selectedDates;
                fromEl.value = start ? start.toISOString().slice(0,10) : '';
                toEl.value = end ? end.toISOString().slice(0,10)   : '';

                if (fromEl.value) fromEl.name = 'from_date'; else fromEl.removeAttribute('name');
                if (toEl.value) toEl.name = 'to_date'; else toEl.removeAttribute('name');
            }
        });

        function multiPicker({
            items,
            preselected = [],
            allLabel = 'All',
            placeholder = 'Select items'
        }) {
            return {
                open: false,
                q: '',
                items,
                selected: [...preselected],
                get allSelected() {
                    return this.items.length > 0 && this.selected.length === this.items.length
                },
                filtered() {
                    const s = this.q.trim().toLowerCase();
                    return s ? this.items.filter(i => i.name.toLowerCase().includes(s)) : this.items;
                },
                toggle(id) {
                    const i = this.selected.indexOf(id);
                    i > -1 ? this.selected.splice(i, 1) : this.selected.push(id);
                },
                toggleAll() {
                    this.allSelected ? this.selected = [] : this.selected = this.items.map(i => i.id);
                },
                summary() {
                    if (this.selected.length === 0 || this.allSelected) return `${allLabel}`;
                    return `${this.selected.length} selected`;
                },
                placeholder
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/coa/transaction.blade.php ENDPATH**/ ?>