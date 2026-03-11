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
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800">Profit & Loss Report</h2>
            <p class="text-sm text-gray-500 mt-1">See the net profit/loss for the chosen month in the chart, with a detailed account breakdown below</p>
        </div>

        <div class="flex flex-col md:flex-row items-center justify-between gap-4 mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700">Month</label>
                    <input type="month" name="month" id="month" value="<?php echo e($month); ?>"
                        class="border border-gray-300 rounded px-4 py-2 shadow-sm focus:ring focus:ring-blue-300" required>
                </div>
                <button type="submit"
                    class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">Filter</button>
            </form>
        </div>

        <div class="bg-white shadow rounded-lg p-6 mb-10">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">📈 Yearly Profit / Loss Graph (<?php echo e($year); ?>)</h3>
            <form method="GET" class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                <input type="hidden" name="month" value="<?php echo e($month); ?>">
                <label for="year" class="whitespace-nowrap font-medium text-gray-600">Filter Chart By Year:</label>
                <select name="year" id="year" onchange="this.form.submit()"
                    class="border rounded px-2 py-1 text-sm h-8 w-24 focus:outline-none focus:ring-1 focus:ring-blue-300">
                    <?php $__currentLoopData = range(now()->year - 5, now()->year); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $y): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($y); ?>" <?php echo e($year == $y ? 'selected' : ''); ?>><?php echo e($y); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </form>
            <canvas id="profitLossChart" height="100"></canvas>
        </div>

        <?php
            $totalIncome = 0;
            $totalExpense = 0;
        ?>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-xl font-semibold text-green-700 mb-4">🟢 Incomes</h3>
                    <table class="w-full text-sm">
                        <tbody>
                        <?php $__currentLoopData = $incomeAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $acc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b">
                                <td class="p-2 font-medium"><?php echo e($acc['account']->name); ?></td>
                                <td class="p-2 text-right text-green-600"><?php echo e(number_format($acc['amount'], 2)); ?></td>
                            </tr>
                            <?php
                                $totalIncome += $acc['amount'];
                            ?>
                            <?php $__currentLoopData = $acc['children']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="text-xs text-gray-600">
                                    <td class="pl-6 py-1">↳ <?php echo e($child['account']->name); ?></td>
                                    <td class="p-2 text-right"><?php echo e(number_format($child['amount'], 2)); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-red-700 mb-4">🔴 Expenses</h3>
                    <table class="w-full text-sm">
                        <tbody>
                        <?php $__currentLoopData = $expenseAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $acc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-b">
                                <td class="p-2 font-medium"><?php echo e($acc['account']->name); ?></td>
                                <td class="p-2 text-right text-red-600"><?php echo e(number_format(abs($acc['amount']), 2)); ?></td>
                            </tr>
                            <?php
                                $totalExpense += abs($acc['amount']);
                            ?>
                            <?php $__currentLoopData = $acc['children']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="text-xs text-gray-600">
                                    <td class="pl-6 py-1">↳ <?php echo e($child['account']->name); ?></td>
                                    <td class="p-2 text-right"><?php echo e(number_format(abs($child['amount']), 2)); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php
                $netProfit = $totalIncome - abs($totalExpense);
                $isProfit = $netProfit >= 0;
            ?>

            <div class="mt-8 border-t pt-4 text-base">
                <div class="flex justify-between mb-2">
                    <span class="text-gray-700 font-medium">Total Income:</span>
                    <span class="text-green-600 font-semibold"><?php echo e(number_format($totalIncome, 2)); ?> KWD</span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-700 font-medium">Total Expenses:</span>
                    <span class="text-red-600 font-semibold"><?php echo e(number_format(abs($totalExpense), 2)); ?> KWD</span>
                </div>
                <div class="mt-4 text-white font-bold text-lg text-center py-3 rounded-lg shadow
                    <?php echo e($isProfit ? 'bg-green-500' : 'bg-red-500'); ?>">
                    <?php echo e($isProfit ? 'Net Profit:' : 'Net Loss:'); ?>

                    <?php echo e(number_format($netProfit, 2)); ?> KWD
                </div>
            </div>
        </div>
    </div>
    <script>
        const ctx = document.getElementById('profitLossChart');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($monthlyLabels, 15, 512) ?>,
                datasets: [{
                    label: 'Monthly Net',
                    data: <?php echo json_encode($monthlyProfits, 15, 512) ?>,
                    backgroundColor: <?php echo json_encode($monthlyProfitsColors, 15, 512) ?>,
                    borderRadius: 6
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return value + ' KWD';
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.parsed.y;
                                return (value >= 0 ? 'Profit: ' : 'Loss: ') + Math.abs(value) + ' KWD';
                            }
                        }
                    }
                }
            }
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/profit-loss.blade.php ENDPATH**/ ?>