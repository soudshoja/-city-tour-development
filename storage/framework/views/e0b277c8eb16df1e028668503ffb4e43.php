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

    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Transaction of Credits</h2>
            
        </div>
        <!-- add new credit & refresh page -->
        <div class="flex items-center gap-5">
            <div x-data="{ showTopupModal: false }" data-tooltip="Credit Topup">
                <a href="javascript:void(0)"
                    @click="showTopupModal = true"
                    class="w-12 h-12 flex items-center justify-center text-white bg-blue-700 hover:bg-blue-900 border border-gray-300 rounded-full shadow transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </a>

                <div x-cloak x-show="showTopupModal" @click.away="showTopupModal = false" x-transition
                    class="fixed inset-0 z-50 bg-black/30 flex items-center justify-center">

                    <form action="<?php echo e(route('credits.topup')); ?>" method="POST" class="bg-white rounded p-6 w-full max-w-md" @click.stop>
                        <?php echo csrf_field(); ?>
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold text-gray-800">New Client Top Up</h2>
                            <button @click="showTopupModal = false" class="text-gray-500 hover:text-gray-700 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <?php if(auth()->user()->role_id == App\Models\Role::COMPANY): ?>
                        <div class="mb-4">
                            <label for="agent" class="block text-sm font-medium text-gray-700">Agent</label>
                            <select name="agent_id" id="payment_agent_id"
                                class="p-2 mt-1 block w-full border-gray-300 rounded-full shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select Agent</option>
                                <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($agent->id); ?>"><?php echo e($agent->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <label for="client" class="block text-sm font-medium text-gray-700">Client</label>
                            <select name="client_id" id="payment_client_id"
                                class="p-2 mt-1 block w-full border-gray-300 rounded-full shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select Client</option>
                                <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($client->id); ?>"><?php echo e($client->full_name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Amount</label>
                            <input type="number" name="amount" step="0.01" required
                                class="form-input mt-1 w-full rounded-full">
                        </div>

                        <div class="mb-4">
                            <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
                            <select name="currency" id="currency" class="p-2 mt-1 block w-full border-gray-300 rounded-full shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <?php $__currentLoopData = $currencies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $currency): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($currency->iso_code); ?>" <?php echo e($currency->country && $currency->country->name == 'Kuwait' ? 'selected' : ''); ?>>
                                    <?php echo e($currency->name); ?>

                                </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>

                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="description"
                                class="form-textarea mt-1 w-full rounded-xl resize-none"></textarea>
                        </div>

                        <div class="flex justify-center gap-4">
                            <button @click="showTopupModal = false"
                                class="px-4 py-2 rounded-full bg-white text-gray-700 hover:bg-gray-300 shadow-md transition">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 rounded-full bg-blue-500 text-white hover:bg-blue-600 transition">
                                Top Up
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div data-tooltip="Reload"
                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>
        </div>


    </div>
    <!-- ./page title -->

    <!-- page content -->
    <div class="tableCon">
        <div class="content-70">
            <!-- Table  -->
            <div class="panel oxShadow rounded-lg">

                <?php if (isset($component)) { $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.search','data' => ['action' => route('credits.index'),'searchParam' => 'search','placeholder' => 'Quick search for credit transactions']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('search'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('credits.index')),'searchParam' => 'search','placeholder' => 'Quick search for credit transactions']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6)): ?>
<?php $attributes = $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6; ?>
<?php unset($__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6)): ?>
<?php $component = $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6; ?>
<?php unset($__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6); ?>
<?php endif; ?>

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <!-- table -->
                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Date</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Client</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Agent</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Description</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Amount (KWD)</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($allCreditRecords->isEmpty()): ?>
                                <tr>
                                    <td colspan="5" class="text-center p-3 text-sm font-semibold text-gray-500 ">
                                        No data for now.... Create new!</td>
                                </tr>
                                <?php else: ?>
                                <?php $__currentLoopData = $allCreditRecords; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recCredits): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        <?php echo e($recCredits->created_at); ?>

                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        <?php echo e($recCredits->client->full_name ?? ''); ?>

                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        <?php echo e($recCredits->client->agent->name ?? ''); ?>

                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        <?php echo e($recCredits->description ?? ''); ?>

                                    </td>
                                    <td
                                        class="p-3 text-sm font-bold <?php echo e($recCredits->amount < 0 ? 'text-red-500' : 'text-green-600'); ?>">
                                        <?php echo e(number_format($recCredits->amount, 2)); ?>

                                    </td>
                                    <td class="p-3 text-sm">
                                        <div class="flex items-center space-x-2">

                                        </div>

                                    </td>

                                </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    </div>
                    <!-- ./table -->
                </div>

                <?php if (isset($component)) { $__componentOriginal41032d87daf360242eb88dbda6c75ed1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41032d87daf360242eb88dbda6c75ed1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pagination','data' => ['data' => $allCreditRecords]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($allCreditRecords)]); ?>
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

            <!-- ./Table  -->

        </div>
        <!-- right -->
        <div class="content-30 hidden">

            <div class="flex lg:flex-col md:flex-row justify-center text-center gap-5">
                <!-- customize -->
                <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                        <path fill="#333333"
                            d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                    </svg>
                    <span class="text-sm">Customize</span>
                </button>
                <!-- ./customize -->

                <!-- filter -->
                <button class="flex px-5 py-3 gap-2 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
                    </svg>
                    <span class="text-sm">Filter</span>
                </button>
                <!-- ./filter -->

            </div>
            <div class="mt-5 ">
                <!-- display details here-->
                <div id="refundDetails" class="panel w-full xl:mt-0 rounded-lg h-auto hidden"></div>
                <!-- display details here-->

            </div>
        </div>
        <!-- ./right -->
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/credits/index.blade.php ENDPATH**/ ?>