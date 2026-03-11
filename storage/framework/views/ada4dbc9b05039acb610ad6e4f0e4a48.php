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
        #dashboard-revenue>div {
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 600;
            color: #fff;
        }
    </style>
    <div class="">
        <?php
        
            if (auth()->user()->hasRole('admin')) {
                $gridCols = 'sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3';
            } elseif (auth()->user()->hasRole('company')) {
                $gridCols = 'sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3';
            } elseif (auth()->user()->hasRole('branch')) {
                $gridCols = 'sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-2';
            } elseif (auth()->user()->hasRole('client')) {
                $gridCols = 'sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-1';
            } elseif (auth()->user()->hasRole('accountant')) {
                $gridCols = 'sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-1';
            } else {
                $gridCols = 'sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-1';
            }
        ?>

        <div class="grid <?php echo e($gridCols); ?> gap-3 mt-3">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Company::class && auth()->user()->hasRole('admin') || auth()->user()->hasRole('accountant'))): ?>
                <div class="p-4 bg-green-100/50 dark:bg-green-900/50 rounded-lg shadow-md w-full flex">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-green-800 dark:text-green-300"><?php echo e($companies->count()); ?></h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Companies</p>
                    </div>
                    <div class="mt-4 w-full text-center">
                    </div>
                </div>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Branch::class)): ?>
                <div class="p-4 bg-blue-100/50 dark:bg-blue-900/50 rounded-lg shadow-md w-full flex">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-blue-800 dark:text-blue-300"><?php echo e($branches->count()); ?></h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Branches</p>
                    </div>
                    <div class="mt-4 w-full text-center">
                    </div>
                </div>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Agent::class)): ?>
                <div class="p-4 bg-red-100/50 dark:bg-red-900/50 rounded-lg shadow-md w-full flex">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-red-800 dark:text-red-300"><?php echo e($agents->count()); ?></h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Agents</p>
                    </div>
                    <div class="mt-4 w-full text-center">
                    </div>
                </div>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAny', App\Models\Client::class)): ?>
                <div class="p-4 bg-yellow-100/50 dark:bg-yellow-900/50 rounded-lg shadow-md w-full flex">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-yellow-800 dark:text-yellow-300"><?php echo e($clients->count()); ?></h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"> Total Clients</p>
                    </div>
                    <div class="mt-4 w-full text-center">
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if(!empty($iataErrorMessage)): ?>
            <div class="p-3 m-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-md">
                ⚠️ <?php echo e($iataErrorMessage); ?>

            </div>
        <?php endif; ?>
        <div class="mt-8 rounded-xl border border-slate-200 dark:border-slate-800 bg-white/40 dark:bg-slate-900/40 backdrop-blur-md shadow-sm p-6">
            <div class="flex flex-wrap justify-start items-start gap-0">
                <?php if(!empty($wallets) && count($wallets) > 0): ?>
                    <div class="flex-1 min-w-[45%] max-w-[600px]">
                        <div class="mb-3">
                            <h2 class="text-2xl font-bold text-slate-800 dark:text-slate-100">IATA EasyPay Wallets</h2>
                            <div class="inline-flex items-center gap-2 text-sm px-3 py-1 text-emerald-700 dark:text-emerald-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 
                                        8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 
                                        7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span>Open balance:</span>
                                <strong class="text-emerald-700 dark:text-emerald-300"><?php echo e(number_format($iataBalance, 3)); ?></strong> KWD
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-0">
                            <?php $__currentLoopData = $wallets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $wallet): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-emerald-50 dark:bg-emerald-950/40 shadow-sm p-4">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Wallet</p>
                                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100"><?php echo e($wallet['name']); ?></h3>
                                        </div>
                                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            OPEN
                                        </span>
                                    </div>
                                    <div class="mt-3 flex items-end justify-between">
                                        <div>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Available</p>
                                            <p class="text-xl font-bold text-slate-900 dark:text-slate-100">
                                                <?php echo e(number_format($wallet['balance'], 3)); ?>

                                                <span class="text-[13px] font-medium text-slate-500 dark:text-slate-400"><?php echo e($wallet['currency']); ?></span>
                                            </p>
                                        </div>
                                        <div class="w-8 h-8 flex items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                                            <svg class="w-4 h-4 text-emerald-700 dark:text-emerald-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path d="M12 8v8m4-4H8"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if(!empty($jazeeraCredit) && count($jazeeraCredit) > 0): ?>
                    <div class="flex-1 min-w-[45%] ml-0 md:ml-8">
                        <div class="mb-3">
                            <h2 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Jazeera Airways Credit</h2>
                            <div class="inline-flex items-center gap-2 text-sm px-3 py-1 text-sky-700 dark:text-sky-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 
                                        8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 
                                        7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span>Amount to Pay:</span>
                                <strong class="text-sky-700 dark:text-sky-300"><?php echo e($jazeeraCredit->sum('balance')); ?></strong> KWD
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-0">
                            <?php $__currentLoopData = $jazeeraCredit; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $credit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-sky-50 dark:bg-sky-950/40 shadow-sm p-4">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Account</p>
                                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100"><?php echo e($credit->name); ?></h3>
                                        </div>
                                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">
                                            Active
                                        </span>
                                    </div>
                                    <div class="mt-3 flex items-end justify-between">
                                        <div>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Spent</p>
                                            <p class="text-xl font-bold text-slate-900 dark:text-slate-100">
                                                <?php echo e(number_format($credit->balance, 3)); ?>

                                                <span class="text-[13px] font-medium text-slate-500 dark:text-slate-400"><?php echo e($credit->currency ?? 'KWD'); ?></span>
                                            </p>
                                        </div>
                                        <div class="w-8 h-8 flex items-center justify-center rounded-full bg-sky-100 dark:bg-sky-900/30">
                                            <svg class="w-4 h-4 text-sky-700 dark:text-sky-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path d="M12 8v8m4-4H8"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        

        <?php if(auth()->user()->company && auth()->user()->hasRole('company') || auth()->user()->hasRole('accountant')): ?>
            <div class="my-5 w-full">
                <div class="flex flex-col lg:flex-row gap-3">
                    <?php if(isset($paidAmounts) && isset($unpaidAmounts)): ?>
                        <div class="w-full p-5 bg-opacity-50 bg-white dark:bg-gray-800 rounded-md shadow-md">
                            <h2 class="text-3xl font-bold">Earnings</h2>
                            <div id="earnings" class="relative w-full min-h-96"
                                data-paid="<?php echo e(json_encode($paidAmounts)); ?>"
                                data-unpaid="<?php echo e(json_encode($unpaidAmounts)); ?>">
                                <canvas id="earningsChart"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div
                        class="p-10 pt-10 bg-white dark:bg-gray-900 rounded-md shadow-md flex flex-col w-full lg:w-1/2">
                        <h1>
                            <?php echo e($pieChartTitle); ?>

                        </h1>
                        <div x-data="chart" class="flex justify-center">
                            <div x-ref="donutChart" class="bg-white dark:bg-black rounded-lg"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="my-5 w-full p-10 pt-5 bg-white dark:bg-gray-900 rounded-md shadow-md flex flex-col w-full" id="dashboard-stats-container">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                    <a href="<?php echo e(route('reports.payable-supplier')); ?>"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-red-500 dark:border-red-400 bg-red-50 dark:bg-red-900 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-red-500 dark:text-red-400 font-medium">Payable Supplier</p>
                        <p class="text-xs text-red-400 dark:text-red-200">Amount owed to suppliers</p>
                        <p id="stat-payable-supplier" class="text-red-600 dark:text-red-400 text-xl font-bold">
                            <span class="stat-loading animate-pulse bg-gray-200 dark:bg-gray-700 rounded h-6 w-20 inline-block"></span>
                        </p>
                        <span class="absolute top-2 right-2 text-red-400 dark:text-red-300 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                    <a href="<?php echo e(route('reports.total-receivable')); ?>"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-green-500 dark:border-green-400 bg-green-50 dark:bg-green-900 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-green-600 dark:text-green-400 font-medium">Total Receivable</p>
                        <p class="text-xs text-green-500 dark:text-green-200">Total amount due from clients or customers</p>
                        <p id="stat-total-receivable" class="text-green-600 dark:text-green-400 text-xl font-bold">
                            <span class="stat-loading animate-pulse bg-gray-200 dark:bg-gray-700 rounded h-6 w-20 inline-block"></span>
                        </p>
                        <span class="absolute top-2 right-2 text-green-400 dark:text-green-300 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                    <a href="<?php echo e(route('reports.profit-agent')); ?>"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-koromiko-500 bg-koromiko-50 dark:bg-koromiko-700 dark:border-koromiko-300 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-koromiko-500 dark:text-koromiko-400 font-medium">Profit Agent Wise</p>
                        <p class="text-xs text-koromiko-400 dark:text-koromiko-200">Profit earned by agents</p>
                        <p id="stat-profit-agent" class="text-koromiko-600 dark:text-koromiko-300 text-lg font-semibold">
                            <span class="stat-loading animate-pulse bg-gray-200 dark:bg-gray-700 rounded h-6 w-20 inline-block"></span>
                        </p>
                        <span class="absolute top-2 right-2 text-koromiko-400 dark:text-koromiko-200 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                    <a href="<?php echo e(route('reports.total-bank')); ?>"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-koromiko-500 bg-koromiko-50 dark:bg-koromiko-700 dark:border-koromiko-300 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-koromiko-500 dark:text-koromiko-400 font-medium">Total Bank</p>
                        <p class="text-xs text-koromiko-400 dark:text-koromiko-200">Total balance across all bank accounts</p>
                        <p id="stat-total-bank" class="text-koromiko-600 dark:text-koromiko-300 text-lg font-semibold">
                            <span class="stat-loading animate-pulse bg-gray-200 dark:bg-gray-700 rounded h-6 w-20 inline-block"></span>
                        </p>
                        <span class="absolute top-2 right-2 text-koromiko-400 dark:text-koromiko-200 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                    <a href="<?php echo e(route('reports.gateway-receivable')); ?>"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-koromiko-500 bg-koromiko-50 dark:bg-koromiko-700 dark:border-koromiko-300 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-koromiko-500 dark:text-koromiko-400 font-medium">Gateway Receivable</p>
                        <p class="text-xs text-koromiko-400 dark:text-koromiko-200">Outstanding amounts from payment gateways</p>
                        <p id="stat-gateway-receivable" class="text-koromiko-600 dark:text-koromiko-300 text-lg font-semibold">
                            <span class="stat-loading animate-pulse bg-gray-200 dark:bg-gray-700 rounded h-6 w-20 inline-block"></span>
                        </p>
                        <span class="absolute top-2 right-2 text-koromiko-400 dark:text-koromiko-200 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('alpine:init', () => {
            let pieChartNumbers = <?php echo json_encode($pieChartNumbers, 15, 512) ?>;
            let pieChartLabels = <?php echo json_encode($pieChartLabels, 15, 512) ?>;
            let pieChartColors = <?php echo json_encode($pieChartColors, 15, 512) ?>;

            Alpine.data('chart', () => ({
                init() {
                    let donutChart = new ApexCharts(this.$refs.donutChart, {
                        series: pieChartNumbers,
                        labels: pieChartLabels,
                        // colors: pieChartColors,
                        chart: {
                            type: 'donut',
                            zoom: {
                                enabled: true
                            },
                            toolbar: {
                                show: true
                            },
                            width: '100%',
                            // foreColor: '#fff'
                        },
                        stroke: {
                            show: false,
                        },
                        legend: {
                            position: 'bottom',
                            formatter: function(seriesName, opts) {
                                return [seriesName, " - ", opts.w.globals.series[opts
                                    .seriesIndex], 'KWD']
                            }
                        },
                        tooltip: {
                            enabled: false
                        },
                        dataLabels: {
                            style: {
                                fontSize: '0.8em',
                                colors: ['black']
                            },
                            background: {
                                enabled: true,
                                borderRadius: 4,
                                borderWidth: 1,
                                borderColor: 'black',
                                opacity: 0.8,
                            },

                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '75%'
                                }
                            }
                        },
                        responsive: [{
                            breakpoint: 370,
                            options: {
                                chart: {
                                    width: 250
                                },
                                legend: {
                                    position: 'bottom'
                                },
                                dataLabels: {
                                    style: {
                                        fontSize: '0.6em',
                                    }
                                }
                            }
                        }]


                    });


                    donutChart.render();

                }
            }))
        })


        const ctx = document.getElementById('earningsChart').getContext('2d');

        const labels = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        const earningsDiv = document.getElementById('earnings');

        const paidAmounts = JSON.parse(earningsDiv.getAttribute('data-paid'));
        const unpaidAmounts = JSON.parse(earningsDiv.getAttribute('data-unpaid'));

        const data = {
            labels: labels,
            datasets: [{
                    label: 'Paid Amounts - Invoices',
                    data: paidAmounts,
                    borderColor: 'rgb(34 197 94)',
                    backgroundColor: 'rgb(34 197 94)',
                    borderWidth: 3,
                    borderRadius: 10, // Medium rounded corners
                    borderSkipped: 'start',
                    hoverBackgroundColor: 'rgb(74 222 128)',
                    hoverBorderColor: 'rgb(74 222 128)',
                },
                {
                    label: 'Unpaid Amounts - Invoices',
                    data: unpaidAmounts,
                    borderColor: 'rgb(239 68 68)',
                    backgroundColor: 'rgb(239 68 68)',
                    borderWidth: 3,
                    borderRadius: 10, // Medium rounded corners
                    borderSkipped: 'start',
                    hoverBackgroundColor: 'rgb(248 113 113)',
                    hoverBorderColor: 'rgb(248 113 113)',
                },
            ],
        };

        const config = {
            type: 'bar',
            data: data,
            options: {
                responsive: true, // Chart resizes with container
                maintainAspectRatio: false, // Disable fixed aspect ratio to fill height

                plugins: {
                    legend: {
                        gap: 30, // Add 30px of space between legend items
                        position: 'top', // Place legend on the top
                        align: 'end', // Align legend to the right
                        labels: {
                            usePointStyle: true, // Use point style for legend box
                            boxWidth: 20, // Width of the legend box
                            padding: 10, // Padding around the legend text
                            font: {
                                size: 12, // Font size for legend text
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false, // Removes vertical grid lines
                            drawOnChartArea: false, // Make sure grid lines are not drawn over the chart area
                            drawTicks: false, // Remove tick marks on the x-axis
                        },
                        border: {
                            display: false, // Removes the border line for the x-axis
                        },
                        ticks: {
                            display: true, // Keep the tick labels (months)
                            font: {
                                size: 12, // Adjust size if needed
                            },
                            color: 'rgb(75, 85, 99)', // Gray color for labels
                        },
                        offset: true, // Adds space between the chart area and the first/last bars
                    },
                    y: {
                        grid: {
                            display: false, // Removes horizontal grid lines
                            drawOnChartArea: false, // Make sure grid lines are not drawn over the chart area
                            drawTicks: false, // Remove tick marks on the y-axis
                        },
                        border: {
                            display: false, // Removes the border line for the y-axis
                        },
                        ticks: {
                            display: true, // Keep the tick labels (numbers)
                            font: {
                                size: 12, // Adjust size if needed
                            },
                            color: 'rgb(75, 85, 99)', // Gray color for labels
                        },
                        beginAtZero: true,
                        grace: '10%', // Adds padding to the top of the y-axis to provide space above the highest bar
                    },
                },
                elements: {
                    bar: {
                        barPercentage: 0.8, // Adjust bar size to provide more spacing between bars and axes
                        categoryPercentage: 0.9, // Adjust category percentage to provide spacing between bars within categories
                    },
                },
            },
            animations: {
                tension: {
                    duration: 1000, // Duration in milliseconds
                    easing: 'linear', // Animation easing type
                    from: 1,
                    to: 0,
                    loop: true,
                },
            },
        };

        // Create the chart
        const earningsChart = new Chart(ctx, config);

        // Actions (e.g., randomize data)
        // const actions = [{
        //     name: 'Randomize',
        //     handler(chart) {
        //         chart.data.datasets.forEach((dataset) => {
        //             dataset.data = generateRandomNumbers(12, -100, 100);
        //         });
        //         chart.update();
        //     },
        // }, ];

        document.addEventListener('DOMContentLoaded', () => {
            const jazeeraData = <?php echo json_encode($jazeeraCredit ?? [], 15, 512) ?>;
            // console.log('Jazeera Airways Credit Data:', jazeeraData);

            displayJazeeraData({
                records: jazeeraData,
                total: jazeeraData.reduce((sum, e) => sum + parseFloat(e.balance || 0), 0)
            });

            loadDashboardStats();
        });

        function loadDashboardStats() {
            const statsContainer = document.getElementById('dashboard-stats-container');
            if (!statsContainer) return;

            fetch('<?php echo e(route("reports.ajax.dashboard-stats")); ?>')
                .then(response => response.json())
                .then(data => {
                    updateStatElement('stat-payable-supplier', data.payableSupplier, data.payableSupplier < 0);
                    updateStatElement('stat-total-receivable', data.totalReceivable);
                    updateStatElement('stat-profit-agent', data.profitAgentWise);
                    updateStatElement('stat-total-bank', data.totalBank);
                    updateStatElement('stat-gateway-receivable', data.gatewayReceivable);
                })
                .catch(error => {
                    // console.error('Failed to load dashboard stats:', error);
                    document.querySelectorAll('.stat-loading').forEach(el => {
                        el.textContent = 'Error';
                    });
                });
        }

        function updateStatElement(id, value, isPositive = false) {
            const element = document.getElementById(id);
            if (!element) return;

            const formattedValue = typeof value === 'number' ? value.toFixed(3) : value;
            element.textContent = formattedValue;

            if (id === 'stat-payable-supplier' && isPositive) {
                element.classList.remove('text-red-600', 'dark:text-red-400');
                element.classList.add('text-green-600', 'dark:text-green-500');
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
<?php endif; ?>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/dashboard.blade.php ENDPATH**/ ?>