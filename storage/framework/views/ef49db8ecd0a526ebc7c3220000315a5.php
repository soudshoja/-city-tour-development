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
        .exchange-input::-webkit-outer-spin-button,
        .exchange-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .exchange-input {
            -moz-appearance: textfield;
        }
    </style>

    <div x-data='{ createRateModal: false }'>
        <div class="flex justify-between items-center gap-5 my-3">
            <div class="flex items-center gap-5">
                <h2 class="text-3xl font-bold dark:text-white">Currency Exchange List</h2>
                <div data-tooltip="Number of currency exchange"
                    class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                    <span class="text-lg font-bold text-white"><?php echo e($currencyExchanges->count()); ?></span>
                </div>
            </div>
            <div class="flex items-center gap-5">
                <button onclick="window.location.reload()" data-tooltip-left="Reload"
                    class="refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="currentColor"
                            d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                        <path fill="currentColor"
                            d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                            opacity=".5" />
                    </svg>
                </button>
                <button @click="createRateModal = true" data-tooltip-left="Create new rate"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm cursor-pointer hover:opacity-90 transition-opacity">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>

        <div
            x-show="createRateModal"
            x-cloak
            class="fixed inset-0 z-50 bg-gray-500 bg-opacity-50 flex items-center justify-center"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">
            <div
                @click.away="createRateModal = false"
                class="bg-white dark:bg-gray-800 rounded-md shadow-lg w-1/2"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95">
                <?php echo $__env->make('currency-exchange.partials.create', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </div>
        </div>
    </div>

    <div class="mt-3 p-4 bg-white rounded-md shadow dark:bg-gray-800 overflow-auto">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-gray-600 dark:text-gray-300">Exchange Rate for:</span>
            <span class="font-semibold text-blue-600"><?php echo e($companyName ?? 'Selected Company'); ?></span>
        </div>

        <hr class="mb-4 dark:border-gray-700">

        <div class="dataTable-wrapper">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table w-full">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500 dark:text-gray-400">
                            <th class="p-3">From Currency</th>
                            <th class="p-3">To Currency</th>
                            <th class="p-3">Exchange Rate</th>
                            <th class="p-3">Updating Method</th>
                            <th class="p-3">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $currencyExchanges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $currencyExchange): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="p-3 text-sm font-semibold text-gray-600 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="p-3">
                                <span class="px-2 py-1 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 rounded font-medium">
                                    <?php echo e($currencyExchange->base_currency); ?>

                                </span>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded font-medium">
                                    <?php echo e($currencyExchange->exchange_currency); ?>

                                </span>
                            </td>
                            <td class="p-3">
                                <?php if(auth()->user()->can('update currency exchange') && $currencyExchange->is_manual): ?>
                                <div class="inline-flex justify-between gap-2 items-center" id="exchange-input-container-<?php echo e($currencyExchange->id); ?>">
                                    <input type="number"
                                        class="rounded-md border-gray-300 dark:bg-gray-600 dark:border-gray-500 exchange-input py-1 px-2 w-32 text-sm focus:ring-blue-500 focus:border-blue-500"
                                        value="<?php echo e($currencyExchange->exchange_rate); ?>"
                                        id="<?php echo e($currencyExchange->id); ?>"
                                        onkeydown="setInitialRate(this)"
                                        onkeyup="exchangeRateDiffer(this)">
                                </div>
                                <?php else: ?>
                                <div id="exchange-input-container-<?php echo e($currencyExchange->id); ?>">
                                    <?php echo e($currencyExchange->exchange_rate); ?>

                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <div class="w-full flex items-center gap-3">
                                    <div class="method-text" data-id="<?php echo e($currencyExchange->id); ?>">
                                        <?php if($currencyExchange->is_manual): ?>
                                        <span class="badge whitespace-nowrap px-2 py-1 rounded text-xs font-medium badge-outline-primary">
                                            Manual
                                        </span>
                                        <?php else: ?>
                                        <span class="badge whitespace-nowrap px-2 py-1 rounded text-xs font-medium badge-outline-success">
                                            Auto
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <label class="w-11 h-6 relative cursor-pointer">
                                        <input
                                            type="checkbox"
                                            class="toggle-method absolute w-full h-full opacity-0 z-10 cursor-pointer peer"
                                            data-id="<?php echo e($currencyExchange->id); ?>"
                                            <?php echo e($currencyExchange->is_manual ? '' : 'checked'); ?> />
                                        <span class="bg-blue-500 block h-full rounded-full before:absolute before:left-1 before:bg-white dark:peer-checked:before:bg-white before:bottom-1 before:w-4 before:h-4 before:rounded-full peer-checked:before:left-6 peer-checked:bg-green-500 before:transition-all before:duration-300">
                                        </span>
                                    </label>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex flex-col">
                                    <span class="text-gray-700 dark:text-gray-300"><?php echo e($currencyExchange->updated_at->diffForHumans()); ?></span>
                                    <span class="text-xs text-gray-400"><?php echo e($currencyExchange->updated_at->format('d M Y, H:i')); ?></span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5" class="text-center p-6 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-12 h-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p>No exchange rates found for this company.</p>
                                    <button @click="createRateModal = true" class="text-blue-600 hover:underline text-sm">
                                        Create your first rate
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="update-exchange-rate" class="opacity-0 bg-gradient-to-t from-gray-500/80 pt-4 pb-6 fixed bottom-0 left-0 w-full m-auto flex justify-center transition-opacity duration-150 ease-in-out z-40">
        <div class="bg-white dark:bg-gray-800 p-3 px-8 shadow-lg rounded-lg border border-gray-200 dark:border-gray-700">
            <button class="btn btn-primary" onclick="updateRateManual()">
                <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
                Update Exchange Rate
            </button>
        </div>
    </div>

    <script>
        let updateExchangeContainer = document.getElementById('update-exchange-rate');
        let updateManualUrl = "<?php echo route('exchange.update.manual'); ?>";
        let updateAutoUrl = "<?php echo route('exchange.update.auto'); ?>";
        let toggleMethod = document.querySelectorAll('.toggle-method');

        exchangeRate = {};
        let differentCount;

        setInitialRate = (element) => {
            if (!exchangeRate[element.id]) {
                exchangeRate[element.id] = {
                    'initial': element.value,
                }
            }
        }

        exchangeRateDiffer = (element) => {
            if (exchangeRate[element.id]) {
                exchangeRate[element.id].current = element.value;
            } else {
                alert('something went wrong');
            }

            differentCount = 0;

            for (const key in exchangeRate) {
                let element = document.getElementById(key);

                if (exchangeRate[key].initial != exchangeRate[key].current) {
                    differentCount++;

                    if (element.value < 0 || !/^\d+(\.\d{1,6})?$/.test(element.value)) {
                        element.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                        element.classList.remove('border-gray-300');
                    } else {
                        element.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                        element.classList.add('border-blue-500', 'ring-1', 'ring-blue-500');
                        element.classList.remove('border-gray-300');
                    }
                } else {
                    element.classList.remove('border-blue-500', 'ring-1', 'ring-blue-500', 'border-red-500');
                    element.classList.add('border-gray-300');
                }

                if (differentCount > 0) {
                    updateExchangeContainer.classList.remove('opacity-0');
                    updateExchangeContainer.classList.add('opacity-100');
                } else {
                    updateExchangeContainer.classList.add('opacity-0');
                    updateExchangeContainer.classList.remove('opacity-100');
                }
            }
        }

        updateRateManual = () => {
            if (differentCount > 0 && exchangeRate) {
                let data = [];
                for (const key in exchangeRate) {
                    if (exchangeRate[key].current < 0 || !/^\d+(\.\d{1,6})?$/.test(exchangeRate[key].current)) {
                        alert('Exchange rate must be a positive number and maximum 6 decimal places');
                        return;
                    }

                    data.push({
                        id: key,
                        exchange_rate: exchangeRate[key].current,
                        is_manual: true
                    });
                }

                fetch(updateManualUrl, {
                    method: 'PUT',
                    body: JSON.stringify(data),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        alert('Something went wrong');
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    alert(data.message);
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        }

        updateRateFromApi = (element) => {
            if (!confirm('Are you sure you want to update all exchange rate automatically?')) return;

            let data = {
                id: element.getAttribute('data-id'),
                is_manual: false
            }

            fetch(updateAutoUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    alert('Something went wrong');
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                alert(data.message);
                window.location.reload();
            })
        }

        toggleMethod.forEach(element => {
            element.addEventListener('change', (e) => {
                let id = e.target.getAttribute('data-id');
                toggleUpdateMethod(element, id);
            });
        });

        toggleUpdateMethod = (element, id) => {
            let updateMethodUrl = "<?php echo route('exchange.update.method', '__id__'); ?>".replace('__id__', id);

            fetch(updateMethodUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                },
            })
            .then(response => {
                if (!response.ok) {
                    alert('Something went wrong');
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const alertEl = document.createElement('div');
                alertEl.className = 'fixed top-4 right-4 z-50 alert flex items-center justify-between rounded-lg bg-green-50 border border-green-200 p-3.5 text-green-700 shadow-lg';
                alertEl.innerHTML = `
                    <span>${data.message}</span>
                    <button class="ml-4 text-green-500 hover:text-green-700 font-semibold" onclick="this.parentElement.remove()">×</button>
                `;
                document.body.append(alertEl);

                setTimeout(() => {
                    alertEl.remove();
                }, 3000);

                let exchangeRateContainer = document.getElementById(`exchange-input-container-${id}`);

                if (data.currencyExchange.is_manual) {
                    exchangeRateContainer.classList.add('inline-flex', 'justify-between', 'gap-2', 'items-center');
                    exchangeRateContainer.innerHTML = `
                        <input
                            type="number"
                            class="rounded-md border-gray-300 dark:bg-gray-600 dark:border-gray-500 exchange-input py-1 px-2 w-32 text-sm focus:ring-blue-500 focus:border-blue-500"
                            value="${data.currencyExchange.exchange_rate}"
                            id="${data.currencyExchange.id}"
                            onkeydown="setInitialRate(this)"
                            onkeyup="exchangeRateDiffer(this)">
                    `;
                } else {
                    exchangeRateContainer.innerHTML = data.currencyExchange.exchange_rate;
                }

                let methodText = element.parentElement.parentElement.querySelector('.method-text');

                if (data.currencyExchange.is_manual) {
                    methodText.innerHTML = '<span class="badge whitespace-nowrap px-2 py-1 rounded text-xs font-medium badge-outline-primary">Manual</span>';
                } else {
                    methodText.innerHTML = '<span class="badge whitespace-nowrap px-2 py-1 rounded text-xs font-medium badge-outline-success">Auto</span>';
                }
            })
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/currency-exchange/index.blade.php ENDPATH**/ ?>