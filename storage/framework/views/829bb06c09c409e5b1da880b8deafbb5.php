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
    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="<?php echo e(route('dashboard')); ?>" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>Suppliers List</span>
            </li>
        </ul>
        <div class="flex flex-col md:flex-row items-center justify-between p-3 bg-white dark:bg-gray-800 shadow rounded-lg space-y-3 md:space-y-0 text-gray-700 dark:text-gray-300">
            <div class="flex items-start md:items-center border border-gray-300 rounded-lg p-2 space-y-3 md:space-y-0 md:space-x-3">
                <div class="flex gap-2 mr-2">
                    <a class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                        <span class="text-black dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Total Suppliers </span>
                    </a>
                    <a class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-info-light dark:bg-gray-700">
                        <span id="suppliersData">
                            <?php echo e($suppliersCount); ?>

                        </span>
                    </a>
                </div>
            </div>
            <div class="flex items-center gap-3 space-y-3 md:space-y-0 md:space-x-2">
                <div class="mt07 relative flex items-center h-12">
                    <input id="searchInput" type="text" placeholder="Search"
                        class="w-full h-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-300">
                    <svg class="w-5 h-5 text-gray-500 absolute left-3 top-1/2 transform -translate-y-1/2"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M9.5 17A7.5 7.5 0 109.5 2a7.5 7.5 0 000 15z" />
                    </svg>
                </div>


            </div>
        </div>
    </div>
    <div x-data="{addSupplierModal : false}" class="flex gap-2 justify-between items-center my-5 p-2 bg-white dark:bg-dark shadow-md rounded-md">
        <div class="flex justify-start">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 7V13" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" />
                <circle cx="12" cy="16" r="1" fill="#ff0000" />
                <path d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" />
            </svg>
            <?php if(auth()->user()->role->name === 'admin'): ?>
            <span class="">Activate supplier to allow the system users to request API from the supplier</span>
            <?php else: ?>
            <span class="">Only system admin can activate suppliers, please contact your admin to activate the supplier</span>
            <?php endif; ?>
        </div>
        <?php if(auth()->user()->role->name === 'admin'): ?>
        <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => ['@click' => 'addSupplierModal = true']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['@click' => 'addSupplierModal = true']); ?>Add Supplier <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $attributes = $__attributesOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__attributesOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $component = $__componentOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__componentOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
        <div
            x-cloak
            x-show="addSupplierModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
            <div
                @click.away="addSupplierModal = false"
                class="bg-white w-1/2 max-h-1/4 rounded-md shadow-md p-5">
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <h1 class="text-lg md:text-xl font-semibold text-gray-900">Add Supplier</h1>
                        <p class="mt-1 text-sm text-gray-500 italic">Fill in the details to add a new supplier</p>
                    </div>

                    <button type="button"
                        @click="addSupplierModal = false"
                        class="p-2 -mr-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M6.225 4.811a1 1 0 0 1 1.414 0L12 9.172l4.361-4.361a1 1 0 1 1 1.414 1.414L13.414 10.586l4.361 4.361a1 1 0 0 1-1.414 1.414L12 12l-4.361 4.361a1 1 0 0 1-1.414-1.414l4.361-4.361-4.361-4.361a1 1 0 0 1 0-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <form action="<?php echo e(route('suppliers.store')); ?>" method="POST" class="flex flex-col gap-2 mb-2">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Supplier Name</label>
                            <input type="text" name="name" placeholder="Supplier Name"
                                class="border border-gray-300 rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                        </div>
                        <div>
                            <label for="auth_type" class="block text-sm font-medium text-gray-700 mb-1">Authentication Type</label>
                            <select name="auth_type"
                                class="border border-gray-300 rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                <?php $__currentLoopData = $supplierAuthTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($type); ?>"><?php echo e(strtolower($type->name)); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-gray-700 mr-3 whitespace-nowrap shrink-0">
                        Country of Origin
                    </span>
                    <div>
                        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'country_id','items' => $countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),'placeholder' => 'Select Country'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
                    </div>
                
                    <?php ($supplier = $supplier ?? new \App\Models\Supplier()); ?>
                    <div x-data="{
                            hasHotel: <?php echo e($supplier->has_hotel ? 'true' : 'false'); ?>,
                            hasFlight: <?php echo e($supplier->has_flight ? 'true' : 'false'); ?>,
                            hasVisa: <?php echo e($supplier->has_visa ? 'true' : 'false'); ?>,
                            hasInsurance: <?php echo e($supplier->has_insurance ? 'true' : 'false'); ?>,
                            hasTour: <?php echo e($supplier->has_tour ? 'true' : 'false'); ?>,
                            hasCruise: <?php echo e($supplier->has_cruise ? 'true' : 'false'); ?>,
                            hasCar: <?php echo e($supplier->has_car ? 'true' : 'false'); ?>,
                            hasRail: <?php echo e($supplier->has_rail ? 'true' : 'false'); ?>,
                            hasEsim: <?php echo e($supplier->has_esim ? 'true' : 'false'); ?>,
                            hasEvent: <?php echo e($supplier->has_event ? 'true' : 'false'); ?>,
                            hasLounge: <?php echo e($supplier->has_lounge ? 'true' : 'false'); ?>,
                            hasFerry: <?php echo e($supplier->has_ferry ? 'true' : 'false'); ?>,
                            hotelChannel: '<?php echo e(old('hotel_channel', ($supplier->is_online === null ? '' : ($supplier->is_online ? 'online' : 'offline')))); ?>',
                            isManual: <?php echo e($supplier->is_manual ? 'true' : 'false'); ?>,
                        }" class="mt-2">
                        <span class="text-sm font-medium text-gray-700 mr-3 whitespace-nowrap shrink-0">Service Type</span>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-24 gap-y-2" @click.stop>

                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Hotel</span>

                                <button type="button"
                                    @click="hasHotel = !hasHotel; if(!hasHotel) hotelChannel='';"
                                    :aria-pressed="hasHotel.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasHotel ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasHotel ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasHotel">
                                    <input type="hidden" name="has_hotel" value="1">
                                </template>
                            </div>

                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Flight</span>

                                <button type="button"
                                    @click="hasFlight = !hasFlight; if(!hasFlight) flightChannel='';"
                                    :aria-pressed="hasFlight.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasFlight ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasFlight ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasFlight">
                                    <input type="hidden" name="has_flight" value="1">
                                </template>
                            </div>

                            <!-- Has Visa -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Visa</span>
                                <button type="button" @click="hasVisa = !hasVisa"
                                    :aria-pressed="hasVisa.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasVisa ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasVisa ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasVisa">
                                    <input type="hidden" name="has_visa" value="1">
                                </template>
                            </div>

                            <!-- Has Insurance -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Insurance</span>
                                <button type="button" @click="hasInsurance = !hasInsurance"
                                    :aria-pressed="hasInsurance.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasInsurance ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasInsurance ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasInsurance">
                                    <input type="hidden" name="has_insurance" value="1">
                                </template>
                            </div>

                            <!-- Has Tour -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Tour</span>
                                <button type="button" @click="hasTour = !hasTour"
                                    :aria-pressed="hasTour.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasTour ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasTour ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasTour">
                                    <input type="hidden" name="has_tour" value="1">
                                </template>
                            </div>

                            <!-- Has Cruise -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Cruise</span>
                                <button type="button" @click="hasCruise = !hasCruise"
                                    :aria-pressed="hasCruise.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasCruise ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasCruise ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasCruise">
                                    <input type="hidden" name="has_cruise" value="1">
                                </template>
                            </div>

                            <!-- Has Car -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Car</span>
                                <button type="button" @click="hasCar = !hasCar"
                                    :aria-pressed="hasCar.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasCar ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasCar ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasCar">
                                    <input type="hidden" name="has_car" value="1">
                                </template>
                            </div>

                            <!-- Has Rail -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Rail</span>
                                <button type="button" @click="hasRail = !hasRail"
                                    :aria-pressed="hasRail.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasRail ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasRail ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasRail">
                                    <input type="hidden" name="has_rail" value="1">
                                </template>
                            </div>

                            <!-- Has Esim -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Esim</span>
                                <button type="button" @click="hasEsim = !hasEsim"
                                    :aria-pressed="hasEsim.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasEsim ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasEsim ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasEsim">
                                    <input type="hidden" name="has_esim" value="1">
                                </template>
                            </div>

                            <!-- Has Event -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Event</span>
                                <button type="button" @click="hasEvent = !hasEvent"
                                    :aria-pressed="hasEvent.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasEvent ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasEvent ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasEvent">
                                    <input type="hidden" name="has_event" value="1">
                                </template>
                            </div>

                            <!-- Has Lounge -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Lounge</span>
                                <button type="button" @click="hasLounge = !hasLounge"
                                    :aria-pressed="hasLounge.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasLounge ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasLounge ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasLounge">
                                    <input type="hidden" name="has_lounge" value="1">
                                </template>
                            </div>

                            <!-- Has Ferry -->
                            <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                <span class="text-sm text-gray-700">Has Ferry</span>
                                <button type="button" @click="hasFerry = !hasFerry"
                                    :aria-pressed="hasFerry.toString()"
                                    class="w-11 h-6 rounded-full relative transition"
                                    :class="hasFerry ? 'bg-blue-600' : 'bg-gray-200'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                        :class="hasFerry ? 'translate-x-5' : ''"></span>
                                </button>
                                <template x-if="hasFerry">
                                    <input type="hidden" name="has_ferry" value="1">
                                </template>
                            </div>

                        </div>

                        <div x-cloak x-show="hasHotel" class="mt-2" @click.stop>
                            <div class="flex flex-col md:flex-row md:items-end gap-6">
                                <div class="flex flex-col">
                                    <label for="hotel_channel" class="block text-sm font-medium text-gray-700 mb-1">Hotel Supplier Mode</label>
                                    <select name="hotel_channel" x-model="hotelChannel" :disabled="!hasHotel"
                                        class="block h-10 w-64 md:w-72 min-w-[16rem] border border-gray-300 rounded px-3 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                        <option value="" disabled>Select mode</option>
                                        <option value="online">Online</option>
                                        <option value="offline">Offline</option>
                                    </select>
                                    <template x-if="hasHotel">
                                        <input type="hidden" name="is_online" :value="hotelChannel === 'online' ? 1 : 0">
                                    </template>
                                </div>
                                <div class="flex flex-col">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Manual Supplier</label>
                                    <button type="button" @click="isManual = !isManual"
                                        :aria-pressed="isManual.toString()"
                                        class="w-11 h-6 rounded-full relative transition"
                                        :class="isManual ? 'bg-blue-600' : 'bg-gray-200'">
                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition" :class="isManual ? 'translate-x-5' : ''"></span>
                                    </button>
                                    <template x-if="isManual">
                                        <input type="hidden" name="is_manual" value="1">
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 flex items-center justify-between">
                        <button type="button"
                            @click="addSupplierModal = false"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 shadow-md hover:bg-gray-50">
                            Cancel
                        </button>

                        <button type="submit"
                            class="py-2 px-6 bg-blue-600 text-white rounded-md shadow-md hover:bg-blue-700">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (\Illuminate\Support\Facades\Blade::check('role', 'admin')): ?>
    <div class="max-h-160 overflow-y-auto custom-scrollbar bg-white dark:bg-dark rounded-md p-2">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-2 border-b border-r">Supplier Name</th>
                    <th class="px-4 py-2 border-b border-r">Company</th>
                    <th class="px-4 py-2 border-b">Actions</th>
                </tr>
            </thead>
            <tbody id="suppliersTable">
                <?php if($suppliers->isEmpty()): ?>
                <tr>
                    <td colspan="3" class="text-center">No suppliers found</td>
                </tr>
                <?php else: ?>
                <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-200 dark:hover:bg-gray-600">
                    <td class="border-r px-4 py-2">
                        <?php echo e($supplier->name); ?>

                    </td>
                    <td class="border-r px-4 py-2 overflow-x-auto">
                        <div class="flex gap-2">
                            <?php if($supplier->companies->isEmpty()): ?>
                            <p class="text-center font-semibold">
                                No companies registered
                            </p>
                            <?php else: ?>
                            <?php $__currentLoopData = $supplier->companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="p-2 bg-gray-100 rounded">
                                <?php echo e($company->name); ?>

                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td x-data="{editSuppliers : false}" class="px-4 py-2 flex">
                        <a href="<?php echo e(route('supplier-company.edit', $supplier->id)); ?>" class="group">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-black group-hover:stroke-blue-500">
                                <path d="M22 22L2 22" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M17 22V6C17 4.11438 17 3.17157 16.4142 2.58579C15.8284 2 14.8856 2 13 2H11C9.11438 2 8.17157 2 7.58579 2.58579C7 3.17157 7 4.11438 7 6V22" stroke="" stroke-width="1.5" />
                                <path d="M21 22V8.5C21 7.09554 21 6.39331 20.6629 5.88886C20.517 5.67048 20.3295 5.48298 20.1111 5.33706C19.6067 5 18.9045 5 17.5 5" stroke="" stroke-width="1.5" />
                                <path d="M3 22V8.5C3 7.09554 3 6.39331 3.33706 5.88886C3.48298 5.67048 3.67048 5.48298 3.88886 5.33706C4.39331 5 5.09554 5 6.5 5" stroke="" stroke-width="1.5" />
                                <path d="M12 22V19" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M10 12H14" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M5.5 11H7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M5.5 14H7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M17 11H18.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M17 14H18.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M5.5 8H7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M17 8H18.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M10 15H14" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M12 9V5" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M14 7L10 7" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                        <button type="button" @click="editSuppliers = true" class="ml-2" data-left-tooltip="Edit Supplier">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14.3601 4.07866L15.2869 3.15178C16.8226 1.61607 19.3125 1.61607 20.8482 3.15178C22.3839 4.68748 22.3839 7.17735 20.8482 8.71306L19.9213 9.63993M14.3601 4.07866C14.3601 4.07866 14.4759 6.04828 16.2138 7.78618C17.9517 9.52407 19.9213 9.63993 19.9213 9.63993M14.3601 4.07866L5.83882 12.5999C5.26166 13.1771 4.97308 13.4656 4.7249 13.7838C4.43213 14.1592 4.18114 14.5653 3.97634 14.995C3.80273 15.3593 3.67368 15.7465 3.41556 16.5208L2.32181 19.8021M19.9213 9.63993L11.4001 18.1612C10.8229 18.7383 10.5344 19.0269 10.2162 19.2751C9.84082 19.5679 9.43469 19.8189 9.00498 20.0237C8.6407 20.1973 8.25352 20.3263 7.47918 20.5844L4.19792 21.6782M4.19792 21.6782L3.39584 21.9456C3.01478 22.0726 2.59466 21.9734 2.31063 21.6894C2.0266 21.4053 1.92743 20.9852 2.05445 20.6042L2.32181 19.8021M4.19792 21.6782L2.32181 19.8021" stroke="#1C274C" stroke-width="1.5" />
                            </svg>
                        </button>
                        <div x-show="editSuppliers" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
                            <div @click.away="editSuppliers = false" class="bg-white w-1/2 max-h-[90vh] overflow-y-auto rounded-md shadow-md custom-scrollbar">
                                <div class="sticky top-0 bg-white z-10 p-5 border-b border-gray-200 flex items-start justify-between">
                                    <div>
                                        <h1 class="text-lg md:text-xl font-semibold text-gray-900">Edit Supplier</h1>
                                        <p class="mt-1 text-sm text-gray-500 italic">Edit the details of the supplier for accurate information</p>
                                    </div>

                                    <button type="button"
                                        @click="editSuppliers = false"
                                        class="p-2 -mr-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        aria-label="Close">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M6.225 4.811a1 1 0 0 1 1.414 0L12 9.172l4.361-4.361a1 1 0 1 1 1.414 1.414L13.414 10.586l4.361 4.361a1 1 0 0 1-1.414 1.414L12 12l-4.361 4.361a1 1 0 0 1-1.414-1.414l4.361-4.361-4.361-4.361a1 1 0 0 1 0-1.414z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="p-5">
                                    <form action="<?php echo e(route('suppliers.update', $supplier->id)); ?>" method="POST" class="flex flex-col gap-2 mb-2">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('PUT'); ?>
                                        <div class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Supplier Name</label>
                                                <input type="text" name="name" value="<?php echo e($supplier->name); ?>" placeholder="Supplier Name"
                                                    class="h-10 border border-gray-300 rounded-md px-3 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                            </div>

                                            <div>
                                                <?php ($authOptions = ['basic' => 'Basic', 'oauth' => 'OAuth']); ?>
                                                <label for="auth_type" class="block text-sm font-medium text-gray-700 mb-1">Authentication Type</label>
                                                <select name="auth_type"
                                                    class="h-10 border border-gray-300 rounded-md px-3 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                                                    required>
                                                    <?php $__currentLoopData = $authOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($val); ?>" <?php echo e(old('auth_type', $supplier->auth_type) === $val ? 'selected' : ''); ?>>
                                                        <?php echo e($label); ?>

                                                    </option>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </select>
                                            </div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-700 mr-3 whitespace-nowrap shrink-0">
                                            Country of Origin
                                        </span>
                                        <div>
                                            <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'country_id','items' => $countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),'placeholder' => 'Select Country'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($supplier->country->id),'selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($supplier->country->name)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
                                        </div>
                                        <div x-data="{
                                                hasHotel: <?php echo e($supplier->has_hotel ? 'true' : 'false'); ?>,
                                                hasFlight: <?php echo e($supplier->has_flight ? 'true' : 'false'); ?>,
                                                hasVisa: <?php echo e($supplier->has_visa ? 'true' : 'false'); ?>,
                                                hasInsurance: <?php echo e($supplier->has_insurance ? 'true' : 'false'); ?>,
                                                hasTour: <?php echo e($supplier->has_tour ? 'true' : 'false'); ?>,
                                                hasCruise: <?php echo e($supplier->has_cruise ? 'true' : 'false'); ?>,
                                                hasCar: <?php echo e($supplier->has_car ? 'true' : 'false'); ?>,
                                                hasRail: <?php echo e($supplier->has_rail ? 'true' : 'false'); ?>,
                                                hasEsim: <?php echo e($supplier->has_esim ? 'true' : 'false'); ?>,
                                                hasEvent: <?php echo e($supplier->has_event ? 'true' : 'false'); ?>,
                                                hasLounge: <?php echo e($supplier->has_lounge ? 'true' : 'false'); ?>,
                                                hasFerry: <?php echo e($supplier->has_ferry ? 'true' : 'false'); ?>,
                                                hotelChannel: '<?php echo e(old('hotel_channel', ($supplier->is_online === null ? '' : ($supplier->is_online ? 'online' : 'offline')))); ?>',
                                                isManual: <?php echo e($supplier->is_manual ? 'true' : 'false'); ?>,
                                            }" class="mt-2">
                                            <span class="text-sm font-medium text-gray-700 mr-3 whitespace-nowrap shrink-0">Service Type</span>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-24 gap-y-2" @click.stop>

                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Hotel</span>

                                                    <button type="button"
                                                        @click="hasHotel = !hasHotel; if(!hasHotel) hotelChannel='';"
                                                        :aria-pressed="hasHotel.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasHotel ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasHotel ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasHotel">
                                                        <input type="hidden" name="has_hotel" value="1">
                                                    </template>
                                                </div>

                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Flight</span>

                                                    <button type="button"
                                                        @click="hasFlight = !hasFlight; if(!hasFlight) flightChannel='';"
                                                        :aria-pressed="hasFlight.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasFlight ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasFlight ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasFlight">
                                                        <input type="hidden" name="has_flight" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Visa -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Visa</span>
                                                    <button type="button" @click="hasVisa = !hasVisa"
                                                        :aria-pressed="hasVisa.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasVisa ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasVisa ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasVisa">
                                                        <input type="hidden" name="has_visa" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Insurance -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Insurance</span>
                                                    <button type="button" @click="hasInsurance = !hasInsurance"
                                                        :aria-pressed="hasInsurance.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasInsurance ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasInsurance ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasInsurance">
                                                        <input type="hidden" name="has_insurance" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Tour -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Tour</span>
                                                    <button type="button" @click="hasTour = !hasTour"
                                                        :aria-pressed="hasTour.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasTour ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasTour ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasTour">
                                                        <input type="hidden" name="has_tour" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Cruise -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Cruise</span>
                                                    <button type="button" @click="hasCruise = !hasCruise"
                                                        :aria-pressed="hasCruise.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasCruise ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasCruise ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasCruise">
                                                        <input type="hidden" name="has_cruise" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Car -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Car</span>
                                                    <button type="button" @click="hasCar = !hasCar"
                                                        :aria-pressed="hasCar.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasCar ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasCar ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasCar">
                                                        <input type="hidden" name="has_car" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Rail -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Rail</span>
                                                    <button type="button" @click="hasRail = !hasRail"
                                                        :aria-pressed="hasRail.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasRail ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasRail ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasRail">
                                                        <input type="hidden" name="has_rail" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Esim -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Esim</span>
                                                    <button type="button" @click="hasEsim = !hasEsim"
                                                        :aria-pressed="hasEsim.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasEsim ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasEsim ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasEsim">
                                                        <input type="hidden" name="has_esim" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Event -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Event</span>
                                                    <button type="button" @click="hasEvent = !hasEvent"
                                                        :aria-pressed="hasEvent.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasEvent ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasEvent ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasEvent">
                                                        <input type="hidden" name="has_event" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Lounge -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Lounge</span>
                                                    <button type="button" @click="hasLounge = !hasLounge"
                                                        :aria-pressed="hasLounge.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasLounge ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasLounge ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasLounge">
                                                        <input type="hidden" name="has_lounge" value="1">
                                                    </template>
                                                </div>

                                                <!-- Has Ferry -->
                                                <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
                                                    <span class="text-sm text-gray-700">Has Ferry</span>
                                                    <button type="button" @click="hasFerry = !hasFerry"
                                                        :aria-pressed="hasFerry.toString()"
                                                        class="w-11 h-6 rounded-full relative transition"
                                                        :class="hasFerry ? 'bg-blue-600' : 'bg-gray-200'">
                                                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                            :class="hasFerry ? 'translate-x-5' : ''"></span>
                                                    </button>
                                                    <template x-if="hasFerry">
                                                        <input type="hidden" name="has_ferry" value="1">
                                                    </template>
                                                </div>
                                            </div>

                                            <div x-cloak x-show="hasHotel" class="mt-2" @click.stop>
                                                <div class="flex flex-col md:flex-row md:items-end gap-6">
                                                    <div class="flex flex-col">
                                                        <label for="hotel_channel" class="block text-sm font-medium text-gray-700 mb-1">Hotel Supplier Mode</label>
                                                        <select name="hotel_channel" x-model="hotelChannel" :disabled="!hasHotel"
                                                            class="block h-10 w-64 md:w-72 min-w-[16rem] border border-gray-300 rounded px-3 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                                            <option value="" disabled>Select mode</option>
                                                            <option value="online">Online</option>
                                                            <option value="offline">Offline</option>
                                                        </select>
                                                        <template x-if="hasHotel">
                                                            <input type="hidden" name="is_online" :value="hotelChannel === 'online' ? 1 : 0">
                                                        </template>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Manual Supplier</label>
                                                        <button type="button" @click="isManual = !isManual"
                                                            :aria-pressed="isManual.toString()"
                                                            class="w-11 h-6 rounded-full relative transition"
                                                            :class="isManual ? 'bg-blue-600' : 'bg-gray-200'">
                                                            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                                                                :class="isManual ? 'translate-x-5' : ''"></span>
                                                        </button>
                                                        <template x-if="isManual">
                                                            <input type="hidden" name="is_manual" value="1">
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr class="my-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Auto Extra Surcharge</label>
                                            <div id="auto-surcharge-wrapper" class="space-y-4">
                                                <?php $__currentLoopData = $supplier->companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <div class="border border-gray-200 rounded-lg bg-gray-50 hover:bg-gray-100/60 transition-colors duration-200 shadow-sm">
                                                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-100 rounded-t-lg">
                                                        <h3 class="font-semibold text-gray-800 text-base"><?php echo e($company->name); ?></h3>
                                                        <button type="button"
                                                            class="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1"
                                                            onclick="addSurchargeRow(<?php echo e($company->pivot->id); ?>)">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                                stroke="currentColor" class="w-4 h-4">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                            Add Label
                                                        </button>
                                                    </div>
                                                    <div class="p-4 space-y-3" id="company-surcharge-<?php echo e($company->pivot->id); ?>">
                                                        <?php if($company->pivot && $company->pivot->supplierSurcharges->isNotEmpty()): ?>
                                                            <?php $__currentLoopData = $company->pivot->supplierSurcharges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $surcharge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <div
                                                                x-data="{ chargeMode: '<?php echo e($surcharge->charge_mode); ?>' }"
                                                                class="border border-gray-200 rounded-lg p-3 mb-2 bg-white shadow-sm surcharge-row-wrapper"
                                                                data-surcharge-id="<?php echo e($surcharge->id); ?>">
                                                                <div class="flex items-center gap-3">
                                                                    <input type="hidden" name="surcharge_id[<?php echo e($company->pivot->id); ?>][]" value="<?php echo e($surcharge->id); ?>">
                                                                    <input type="text" name="surcharge_label[<?php echo e($company->pivot->id); ?>][<?php echo e($surcharge->id ?? 'new_' . uniqid()); ?>]"
                                                                        value="<?php echo e($surcharge->label); ?>"
                                                                        placeholder="Label"
                                                                        class="flex-1 border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                                                    <input type="number" name="surcharge_amount[<?php echo e($company->pivot->id); ?>][<?php echo e($surcharge->id ?? 'new_' . uniqid()); ?>]"
                                                                        value="<?php echo e($surcharge->amount); ?>" min="0" step="0.001" placeholder="Amount"
                                                                        class="w-32 border border-gray-300 rounded-md px-3 py-1.5 text-sm text-right focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                                                    <button type="button" class="text-gray-400 hover:text-red-500" onclick="removeSurchargeRow(this)" title="Remove">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                                <div class="mt-2 flex items-center flex-wrap gap-x-3 gap-y-1 text-sm mt-8">
                                                                    <label class="text-gray-700 whitespace-nowrap">Charge Mode:</label>
                                                                    <select name="charge_mode[<?php echo e($company->pivot->id); ?>][<?php echo e($surcharge->id ?? 'new_' . uniqid()); ?>]"
                                                                        x-model="chargeMode" class="min-w-[8rem] border border-gray-300 rounded-md px-1.5 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                                                                        <option value="task">Task-wise</option>
                                                                        <option value="reference">Reference-wise</option>
                                                                    </select>
                                                                </div>

                                                                <!-- Task Rule Section -->
                                                                <div x-show="chargeMode === 'task'" x-cloak class="mt-4 border-t pt-3">
                                                                    <div class="flex flex-wrap items-center justify-between">
                                                                        <h4 class="text-sm font-semibold text-gray-800 mb-2 md:mb-0">
                                                                            Task Rules
                                                                        </h4>

                                                                        <div class="flex flex-wrap items-center gap-3 rounded-md px-3 py-1.5">
                                                                            <?php $__currentLoopData = ['issued','reissued','confirmed','refund','void']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                            <label class="flex items-center text-xs gap-1 text-gray-700 whitespace-nowrap">
                                                                                <input type="checkbox" value="1" name="is_<?php echo e($status); ?>[<?php echo e($company->pivot->id); ?>][<?php echo e($surcharge->id); ?>]"
                                                                                    <?php echo e($surcharge->{'is_'.$status} ? 'checked' : ''); ?>

                                                                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                                                <?php echo e(ucfirst(str_replace('_', ' ', $status))); ?>

                                                                            </label>
                                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="flex items-center justify-between mt-4 border-t pt-3 reference-section" x-show="chargeMode === 'reference'" x-cloak>
                                                                    <h4 class="text-sm font-semibold text-gray-800 mr-3">
                                                                        Reference Rules
                                                                    </h4>

                                                                    <div class="flex items-center gap-2" id="reference-list-<?php echo e($surcharge->id ?? 'new_' . uniqid()); ?>">

                                                                        <select name="charge_behavior[<?php echo e($surcharge->id ?? 'new_' . uniqid()); ?>][]"
                                                                            class="min-w-[9rem] border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                                                                            <option value="single" <?php echo e($surcharge->charge_behavior === 'single' ? 'selected' : ''); ?>>Charge Once</option>
                                                                            <option value="repetitive" <?php echo e($surcharge->charge_behavior === 'repetitive' ? 'selected' : ''); ?>>Charge Repeatedly</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        <?php else: ?>
                                                        <div class="text-sm text-gray-500 italic">No surcharges yet — click “Add Label” to create one.</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        </div>
                                        <input type="hidden" id="deleted_surcharges_<?php echo e($supplier->id); ?>" name="deleted_surcharges" value="">
                                        <div class="mt-5 flex items-center justify-between">
                                            <button type="button"
                                                @click="editSuppliers = false"
                                                class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 shadow-md hover:bg-gray-50">
                                                Cancel
                                            </button>

                                            <button type="submit"
                                                class="py-2 px-6 bg-blue-600 text-white rounded-md shadow-md hover:bg-blue-700">
                                                Update
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="max-h-160 overflow-y-auto custom-scrollbar">
        <table class="">
            <thead class="sticky top-0">
                <tr>
                    <th class="px-4 py-2">Supplier Name</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-dark rounded-md p-2" id="suppliersTable">
                <?php if($suppliers->isEmpty()): ?>
                <tr>
                    <td colspan="2" class="text-center">No suppliers found</td>
                </tr>
                <?php else: ?>
                <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-200 dark:hover:bg-gray-600">
                    <td class="px-4 py-2 border dark:border-gray-600 cursor-pointer">
                        <a href="<?php echo e(route('suppliers.show', $supplier->id)); ?>">
                            <span class="font-bold">» <?php echo e($supplier->name); ?></span><br>
                        </a>
                    </td>
                    <td class="px-4 py-2 border dark:border-gray-600 text-center space-x-2 flex">
                        <div x-data="{credentialModal: false}">
                            <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => ['@click' => 'credentialModal = true']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['@click' => 'credentialModal = true']); ?>
                                Credentials
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $attributes = $__attributesOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__attributesOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $component = $__componentOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__componentOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
                            <?php echo $__env->make('suppliers.partials.supplier_credential', ['supplier' => $supplier], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                        </div>
                        <?php if (isset($component)) { $__componentOriginala34f4e4b332cf213ffe682aef739e34a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala34f4e4b332cf213ffe682aef739e34a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-a-button','data' => ['href' => ''.e(route('tasks.supplier', $supplier->id)).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-a-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('tasks.supplier', $supplier->id)).'']); ?>
                            Get All Task
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala34f4e4b332cf213ffe682aef739e34a)): ?>
<?php $attributes = $__attributesOriginala34f4e4b332cf213ffe682aef739e34a; ?>
<?php unset($__attributesOriginala34f4e4b332cf213ffe682aef739e34a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala34f4e4b332cf213ffe682aef739e34a)): ?>
<?php $component = $__componentOriginala34f4e4b332cf213ffe682aef739e34a; ?>
<?php unset($__componentOriginala34f4e4b332cf213ffe682aef739e34a); ?>
<?php endif; ?>
                        
                        <!-- Supplier Charges -->
                        <div x-data="{chargesModal: false}">
                            <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => ['@click' => 'chargesModal = true']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['@click' => 'chargesModal = true']); ?>
                                Supplier Charges
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $attributes = $__attributesOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__attributesOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $component = $__componentOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__componentOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
                            
                            <div x-show="chargesModal" 
                                x-cloak
                                class="fixed inset-0 z-50 flex items-center justify-center"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0">
                                
                                <div class="fixed inset-0 bg-gray-900 bg-opacity-50" @click="chargesModal = false"></div>
                                
                                <div class="relative bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto z-10 mx-4"
                                    @click.stop>
                                    
                                    <div class="flex items-center justify-between p-4 border-b sticky top-0 bg-white z-10">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Supplier Charges - <?php echo e($supplier->name); ?></h3>
                                            <p class="text-sm text-gray-500">Configure surcharges for this supplier</p>
                                        </div>
                                        <button @click="chargesModal = false" class="text-gray-400 hover:text-gray-600">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    <div class="p-4">
                                        <form action="<?php echo e(route('suppliers.update.surcharges', $supplier->id)); ?>" method="POST" id="surchargeForm-<?php echo e($supplier->id); ?>">
                                            <?php echo csrf_field(); ?>
                                            
                                            <div id="auto-surcharge-wrapper" class="space-y-4">
                                                <?php $__currentLoopData = $supplier->companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <div class="border border-gray-200 rounded-lg bg-gray-50 hover:bg-gray-100/60 transition-colors duration-200 shadow-sm">
                                                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-100 rounded-t-lg">
                                                        <h3 class="font-semibold text-gray-800 text-base"><?php echo e($company->name); ?></h3>
                                                        <button type="button"
                                                            class="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1"
                                                            onclick="addSurchargeRow(<?php echo e($company->pivot->id); ?>)">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                                                stroke="currentColor" class="w-4 h-4">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                            </svg>
                                                            Add Label
                                                        </button>
                                                    </div>
                                                    <div class="p-4 space-y-3" id="company-surcharge-<?php echo e($company->pivot->id); ?>">
                                                        <?php if($company->pivot && $company->pivot->supplierSurcharges->isNotEmpty()): ?>
                                                            <?php $__currentLoopData = $company->pivot->supplierSurcharges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $surcharge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <div
                                                                x-data="{ chargeMode: '<?php echo e($surcharge->charge_mode); ?>' }"
                                                                class="border border-gray-200 rounded-lg p-3 mb-2 bg-white shadow-sm surcharge-row-wrapper"
                                                                data-surcharge-id="<?php echo e($surcharge->id); ?>">
                                                                <div class="flex items-center gap-3">
                                                                    <input type="hidden" name="surcharge_id[<?php echo e($company->pivot->id); ?>][]" value="<?php echo e($surcharge->id); ?>">
                                                                    <input type="text" name="surcharge_label[<?php echo e($company->pivot->id); ?>][<?php echo e($surcharge->id); ?>]"
                                                                        value="<?php echo e($surcharge->label); ?>"
                                                                        placeholder="Label"
                                                                        class="flex-1 border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                                                    <input type="number" name="surcharge_amount[<?php echo e($company->pivot->id); ?>][<?php echo e($surcharge->id); ?>]"
                                                                        value="<?php echo e($surcharge->amount); ?>" min="0" step="0.001" placeholder="Amount"
                                                                        class="w-32 border border-gray-300 rounded-md px-3 py-1.5 text-sm text-right focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                                                    <button type="button" class="text-gray-400 hover:text-red-500" onclick="removeSurchargeRow(this)" title="Remove">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                                
                                                                <div class="mt-2 flex items-center flex-wrap gap-x-3 gap-y-1 text-sm mt-8">
                                                                    <label class="text-gray-700 whitespace-nowrap">Charge Mode:</label>
                                                                    <select name="charge_mode[<?php echo e($company->pivot->id); ?>][<?php echo e($surcharge->id); ?>]"
                                                                        x-model="chargeMode" class="min-w-[8rem] border border-gray-300 rounded-md px-1.5 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                                                                        <option value="task">Task-wise</option>
                                                                        <option value="reference">Reference-wise</option>
                                                                    </select>
                                                                </div>

                                                                <div x-show="chargeMode === 'task'" x-cloak class="mt-4 border-t pt-3">
                                                                    <div class="flex flex-wrap items-center justify-between">
                                                                        <h4 class="text-sm font-semibold text-gray-800 mb-2 md:mb-0">Task Rules</h4>
                                                                        <div class="flex flex-wrap items-center gap-3 rounded-md px-3 py-1.5">
                                                                            <?php $__currentLoopData = ['issued','reissued','confirmed','refund','void']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                            <label class="flex items-center text-xs gap-1 text-gray-700 whitespace-nowrap">
                                                                                <input type="checkbox" value="1" name="is_<?php echo e($status); ?>[<?php echo e($company->pivot->id); ?>][<?php echo e($surcharge->id); ?>]"
                                                                                    <?php echo e($surcharge->{'is_'.$status} ? 'checked' : ''); ?>

                                                                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                                                <?php echo e(ucfirst($status)); ?>

                                                                            </label>
                                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="flex items-center justify-between mt-4 border-t pt-3 reference-section" x-show="chargeMode === 'reference'" x-cloak>
                                                                    <h4 class="text-sm font-semibold text-gray-800 mr-3">Reference Rules</h4>
                                                                    <div class="flex items-center gap-2" id="reference-list-<?php echo e($surcharge->id); ?>">
                                                                        <select name="charge_behavior[<?php echo e($surcharge->id); ?>][]"
                                                                            class="min-w-[9rem] border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                                                                            <option value="single" <?php echo e($surcharge->charge_behavior === 'single' ? 'selected' : ''); ?>>Charge Once</option>
                                                                            <option value="repetitive" <?php echo e($surcharge->charge_behavior === 'repetitive' ? 'selected' : ''); ?>>Charge Repeatedly</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                        <?php else: ?>
                                                        <div class="text-sm text-gray-500 italic">No surcharges yet — click "Add Label" to create one.</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                            
                                            <input type="hidden" name="deleted_surcharges" value="">
                                        </form>
                                    </div>
                                    
                                    <div class="flex items-center justify-between gap-3 p-4 sticky bottom-0 bg-white">
                                        <button type="button" @click="chargesModal = false"
                                            class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                                            Cancel
                                        </button>
                                        <button type="submit" form="surchargeForm-<?php echo e($supplier->id); ?>"
                                            class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if($supplier->named_route): ?>
                        <?php if (isset($component)) { $__componentOriginala34f4e4b332cf213ffe682aef739e34a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala34f4e4b332cf213ffe682aef739e34a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-a-button','data' => ['href' => '']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-a-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => '']); ?>Configure <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala34f4e4b332cf213ffe682aef739e34a)): ?>
<?php $attributes = $__attributesOriginala34f4e4b332cf213ffe682aef739e34a; ?>
<?php unset($__attributesOriginala34f4e4b332cf213ffe682aef739e34a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala34f4e4b332cf213ffe682aef739e34a)): ?>
<?php $component = $__componentOriginala34f4e4b332cf213ffe682aef739e34a; ?>
<?php unset($__componentOriginala34f4e4b332cf213ffe682aef739e34a); ?>
<?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <script>
        const searchInput = document.getElementById('searchInput');
        const suppliersData = document.getElementById('suppliersData');
        const tbody = document.getElementById('suppliersTable');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        function applyFilter(q) {
            const query = q.trim().toLowerCase();
            let visible = 0;

            rows.forEach(row => {
                const nameCell = row.querySelector('td:first-child');
                if (!nameCell) return;

                const match = nameCell.textContent.toLowerCase().includes(query);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            suppliersData.textContent = visible;
        }

        applyFilter('');
        searchInput.addEventListener('input', (e) => applyFilter(e.target.value));

        function addSurchargeRow(supplierCompanyId) {
            const container = document.getElementById('company-surcharge-' + supplierCompanyId);
            const key = 'new_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);

            const wrapper = document.createElement('div');
            wrapper.className = 'border border-gray-200 rounded-lg p-3 mb-2 bg-white shadow-sm surcharge-row-wrapper';
            wrapper.dataset.surchargeKey = key;
            wrapper.setAttribute('x-data', "{ chargeMode: 'task' }");

            wrapper.innerHTML = `
                <div class="flex items-center gap-3">
                    <input type="hidden" name="surcharge_id[${supplierCompanyId}][]" value="">
                    <input type="text" name="surcharge_label[${supplierCompanyId}][${key}]"
                        placeholder="Label"
                        class="flex-1 border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                    <input type="number" name="surcharge_amount[${supplierCompanyId}][${key}]"
                        min="0" step="0.001" placeholder="Amount"
                        class="w-32 border border-gray-300 rounded-md px-3 py-1.5 text-sm text-right focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                    <button type="button" class="text-gray-400 hover:text-red-500" onclick="removeSurchargeRow(this)" title="Remove">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Charge Mode -->
                <div class="mt-2 flex items-center flex-wrap gap-x-3 gap-y-1 text-sm mt-8">
                    <label class="text-gray-700 whitespace-nowrap">Charge Mode:</label>
                    <select name="charge_mode[${supplierCompanyId}][${key}]" 
                        x-model="chargeMode"
                        class="min-w-[8rem] border border-gray-300 rounded-md px-1.5 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                        <option value="task">Task-wise</option>
                        <option value="reference">Reference-wise</option>
                    </select>
                </div>

                <!-- Task Rule Section -->
                <div x-show="chargeMode === 'task'" x-cloak class="mt-4 border-t pt-3">
                    <div class="flex flex-wrap items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-800 mb-2 md:mb-0">
                            Task Rules
                        </h4>
                        <div class="flex flex-wrap items-center gap-3 rounded-md px-3 py-1.5">
                            ${['issued','reissued','confirmed','refund','void'].map(status => `
                                <label class="flex items-center text-xs gap-1 text-gray-700 whitespace-nowrap">
                                    <input type="checkbox" value="1" name="is_${status}[${supplierCompanyId}][${key}]"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    ${status.charAt(0).toUpperCase() + status.slice(1)}
                                </label>
                            `).join('')}
                        </div>
                    </div>
                </div>

                <!-- Reference Rule Section -->
                <div class="flex items-center justify-between mt-4 border-t pt-3 reference-section"
                    x-show="chargeMode === 'reference'" x-cloak>
                    <h4 class="text-sm font-semibold text-gray-800 mr-3">
                        Reference Rules
                    </h4>

                    <div class="flex items-center gap-2" id="reference-list-${key}">
                        <select name="charge_behavior[${key}][]"
                            class="min-w-[9rem] border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                            <option value="single">Charge Once</option>
                            <option value="repetitive">Charge Repeatedly</option>
                        </select>
                    </div>
                </div>
            `;

            container.appendChild(wrapper);
        }

        function addReferenceRow(button) {
            const row = button.closest('.surcharge-row-wrapper');
            if (!row) return;
            const key = row.dataset.surchargeId || row.dataset.surchargeKey;
            const list = document.getElementById(`reference-list-${key}`);
            if (!key || !list) return;

            const div = document.createElement('div');
            div.className = 'flex flex-wrap items-center gap-2 border border-gray-200 rounded-md px-2 py-1 bg-gray-50';
            div.innerHTML = `
                <select name="charge_behavior[${key}][]" class="min-w-[9rem] border border-gray-300 rounded-md px-2 py-1 text-xs">
                <option value="single">Charge Once</option>
                <option value="repetitive">Charge Repeatedly</option>
                </select>
                <button type="button" class="text-red-500 hover:text-red-600" onclick="this.closest('div').remove()">✕</button>
            `;
            list.appendChild(div);
        }

        function removeSurchargeRow(button) {
            const row = button.closest('.surcharge-row-wrapper');
            if (!row) return;

            const surchargeId = row.dataset.surchargeId;
            if (surchargeId) {
                const form = button.closest('form');
                const input = form?.querySelector('input[name="deleted_surcharges"]');
                if (input) {
                    const existing = input.value ? input.value.split(',') : [];
                    if (!existing.includes(surchargeId)) {
                        existing.push(surchargeId);
                    }
                    input.value = existing.join(',');
                }
            }

            row.remove();
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('referenceManager', () => ({
                addReference(surchargeId) {
                    const list = document.getElementById(`reference-list-${surchargeId}`);
                    if (!list) return;

                    const div = document.createElement('div');
                    div.className = 'flex items-center gap-2 border border-gray-200 rounded-md px-2 py-1 bg-gray-50';
                    div.innerHTML = `
                        <input type="text" name="reference[${surchargeId}][]" placeholder="Reference"
                            class="flex-1 border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400">
                        <label class="flex items-center text-xs gap-1">
                            <input type="checkbox" name="combine_reference_ref[${surchargeId}][]" 
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Combine
                        </label>
                        <button type="button" class="text-red-500 hover:text-red-600" 
                            onclick="this.closest('div').remove()">✕</button>
                    `;
                    list.appendChild(div);
                },
            }));
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/suppliers/index.blade.php ENDPATH**/ ?>