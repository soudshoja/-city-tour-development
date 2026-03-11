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
    <div class="container mx-auto p-4"
        x-data="settlementModalHandler()"
        x-init="init()">
        <h1 class="text-center font-semibold text-xl mb-4">Settlement Transactions Report</h1>

        <?php
        $defaultFrom = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');
        $defaultTo = \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d');
        $reportFrom = request('from', $defaultFrom);
        $reportTo = request('to', $defaultTo);
        $selectedGateway = request('payment_gateway');
        ?>

        <!-- Filter Form -->
        <div class="bg-gray-100 p-6 rounded shadow">
            <form method="GET" action="<?php echo e(route('reports.settlements')); ?>" class="flex flex-wrap items-end justify-center gap-4">

                <div class="flex flex-col w-64">
                    <label for="date_range" class="text-sm font-medium">Date Range:</label>
                    <input type="text" id="date_range" name="date_range"
                        value="<?php echo e($reportFrom); ?> - <?php echo e($reportTo); ?>"
                        class="border border-gray-300 rounded px-2 py-1 h-10 w-full" autocomplete="off">
                    <input type="hidden" name="from" id="from" value="<?php echo e($reportFrom); ?>">
                    <input type="hidden" name="to" id="to" value="<?php echo e($reportTo); ?>">
                </div>

                <div class="flex flex-col w-48">
                    <label for="reference_type" class="text-sm font-medium">Reference Type:</label>
                    <select name="reference_type" id="reference_type"
                        class="border border-gray-300 rounded px-2 py-1 h-10 w-full">
                        <option value="">All</option>
                        <option value="invoice" <?php echo e(request('reference_type') == 'invoice' ? 'selected' : ''); ?>>Invoice</option>
                        <option value="payment" <?php echo e(request('reference_type') == 'payment' ? 'selected' : ''); ?>>Payment</option>
                    </select>
                </div>

                <div class="flex flex-col w-48">
                    <label for="payment_gateway" class="text-sm font-medium">Payment Gateway:</label>
                    <select name="payment_gateway" id="payment_gateway"
                        class="border border-gray-300 rounded px-2 py-1 h-10 w-full">
                        <option value="">All</option>
                        <?php $__currentLoopData = $gateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($gateway); ?>" <?php echo e($selectedGateway === $gateway ? 'selected' : ''); ?>>
                            <?php echo e($gateway); ?>

                        </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="flex gap-3 items-center">
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition w-28">
                        Filter
                    </button>
                    <a href="<?php echo e(route('reports.settlements')); ?>"
                        class="bg-gray-300 text-black px-4 py-2 rounded hover:bg-gray-400 transition w-28 text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Report Period Display -->
        <div class="mt-4 text-right text-sm text-gray-600">
            <strong>Report Period:</strong> <?php echo e($reportFrom); ?> to <?php echo e($reportTo); ?>

        </div>

        <!-- Results Table -->
        <div class="mt-4 bg-white p-4 rounded shadow">
            <?php if($transactions->isEmpty()): ?>
            <p class="text-gray-600">No transactions found for the selected criteria.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border text-sm">
                    <thead>
                        <tr class="bg-gray-200 text-left text-sm text-gray-700">
                            <th class="py-2 px-4">Transaction Date</th>
                            <th class="py-2 px-4">Company</th>
                            <th class="py-2 px-4">Description</th>
                            <th class="py-2 px-4">Payment Gateway</th>
                            <th class="py-2 px-4 text-right">Amount (KWD)</th>
                            <th class="py-2 px-4 text-center">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                        // Attempt to extract the last word from the description as date
                        $descParts = explode(' ', $tx->description);
                        $journalDate = end($descParts); // Assumes last word is date in format YYYY-MM-DD
                        ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-2 px-4"><?php echo e(\Carbon\Carbon::parse($tx->created_at)->format('Y-m-d')); ?></td>
                            <td class="py-2 px-4"><?php echo e($tx->company->name ?? '-'); ?></td>
                            <td class="py-2 px-4"><?php echo e($tx->description); ?></td>
                            <td class="py-2 px-4"><?php echo e(explode(' ', $tx->description)[0] ?? '-'); ?></td>
                            <td class="py-2 px-4 text-right"><?php echo e(number_format($tx->amount, 2)); ?></td>
                            <td class="py-2 px-4 text-center">
                                <a href="#"
                                    @click.prevent="loadEntries('<?php echo e(route('reports.settlements.entries.by_date')); ?>?date=<?php echo e($journalDate); ?>')"
                                    class="text-blue-600 hover:underline">
                                    View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>

                </table>

                <!-- Pagination -->
                <div class="mt-4">
                    <?php echo e($transactions->appends(request()->query())->links()); ?>

                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Journal Entries Modal -->
        <div class="fixed inset-0 flex items-center justify-center z-50" x-show="open" x-cloak>
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="open = false"></div>

            <div class="relative bg-white rounded-lg shadow-lg max-w-3xl w-full z-10 p-6">
                <button @click="open = false"
                    class="absolute top-2 right-3 text-gray-500 hover:text-gray-800 text-xl font-bold">
                    &times;
                </button>

                <h2 class="text-xl font-semibold mb-4">
                    Journal Entries
                    <template x-if="selectedDate">
                        <span x-text="'for ' + selectedDate" class="text-gray-600 text-base ml-1"></span>
                    </template>
                </h2>

                <div class="overflow-y-auto" style="max-height: 350px;">
                    <template x-if="entries.length === 0">
                        <p class="text-gray-600">No journal entries found.</p>
                    </template>

                    <template x-if="entries.length > 0">
                        <table class="min-w-full border text-sm mb-4">
                            <thead>
                                <tr class="bg-gray-200 text-left text-sm text-gray-700">
                                    <th class="py-2 px-4">Account</th>
                                    <th class="py-2 px-4">Group</th>
                                    <th class="py-2 px-4">Debit</th>
                                    <th class="py-2 px-4">Credit</th>
                                    <th class="py-2 px-4">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="entry in entries" :key="entry.id">
                                    <tr class="border-t">
                                        <td class="py-2 px-4" x-text="entry.account_name"></td>
                                        <td class="py-2 px-4" x-text="entry.root_name"></td>
                                        <td class="py-2 px-4" x-text="entry.debit"></td>
                                        <td class="py-2 px-4" x-text="entry.credit"></td>
                                        <td class="py-2 px-4" x-text="entry.description ?? '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                </div>

                <div class="text-right mt-2">
                    <button @click="open = false"
                        class="bg-gray-200 hover:bg-gray-300 text-sm font-medium px-4 py-2 rounded">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        flatpickr("#date_range", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: [
                "<?php echo e($reportFrom); ?>",
                "<?php echo e($reportTo); ?>"
            ].filter(Boolean),
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    document.getElementById('from').value = instance.formatDate(selectedDates[0], "Y-m-d");
                    document.getElementById('to').value = instance.formatDate(selectedDates[1], "Y-m-d");
                } else if (selectedDates.length === 1) {
                    document.getElementById('from').value = instance.formatDate(selectedDates[0], "Y-m-d");
                    document.getElementById('to').value = instance.formatDate(selectedDates[0], "Y-m-d");
                }
            }
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const range = document.getElementById('date_range').value.split(' to ');
            document.getElementById('from').value = range[0] ? range[0].trim() : '';
            document.getElementById('to').value = range[1] ? range[1].trim() : range[0];
        });
    </script>
    <script>
        function settlementModalHandler() {
            return {
                open: false,
                entries: [],
                selectedDate: null, // <-- add this
                init() {
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') this.open = false;
                    });
                },
                loadEntries(url) {
                    const urlObj = new URL(url);
                    this.selectedDate = urlObj.searchParams.get("date");

                    fetch(url)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            this.entries = data.entries;
                            this.open = true;
                        })
                        .catch(error => {
                            console.error('Error loading journal entries:', error);
                            alert('Failed to load journal entries. Please try again.');
                        });
                }
            };
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/settlements.blade.php ENDPATH**/ ?>