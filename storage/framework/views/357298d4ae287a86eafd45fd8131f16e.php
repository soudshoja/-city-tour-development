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
    <!-- main page container -->
    <div class="">

        <!-- cards -->
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-3 mt-3">
            <!-- card 1 (Customers) -->
            <div class="p-4 bg-green-100/50 dark:bg-green-900/50 rounded-lg shadow-md w-full flex">
                <div class="w-full">
                    <h1 class="text-2xl font-bold text-green-800 dark:text-green-300">580<span class="text-green-500 dark:text-green-400">K</span></h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Customers</p>
                </div>
                <div class="mt-4 w-full text-center">
                    <svg
                        class="w-full h-6 text-green-400 dark:text-green-300"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 100 30">
                        <path d="M0 20 C25 10, 50 30, 75 10 S125 20, 150 10 S175 20, 200 10 S225 20, 250 10 S275 20, 300 10 S325 20, 350 10 S375 20, 400 10" />
                    </svg>
                </div>
            </div>
            <!-- ./card 1 -->

            <!-- card 2 (Agents) -->
            <div class="p-4 bg-blue-100/50 dark:bg-blue-900/50 rounded-lg shadow-md w-full flex">
                <div class="w-full">
                    <h1 class="text-2xl font-bold text-blue-800 dark:text-blue-300">430<span class="text-blue-500 dark:text-blue-400">K</span></h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Agents</p>
                </div>
                <div class="mt-4 w-full text-center">
                    <svg
                        class="w-full h-6 text-blue-400 dark:text-blue-300"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 100 30">
                        <path d="M0 20 C25 10, 50 30, 75 10 S125 20, 150 10 S175 20, 200 10 S225 20, 250 10 S275 20, 300 10 S325 20, 350 10 S375 20, 400 10" />
                    </svg>
                </div>
            </div>
            <!-- ./card 2 -->

            <!-- card 3 (Items) -->
            <div class="p-4 bg-red-100/50 dark:bg-red-900/50 rounded-lg shadow-md w-full flex">
                <div class="w-full">
                    <h1 class="text-2xl font-bold text-red-800 dark:text-red-300">120<span class="text-red-500 dark:text-red-400">Kwd</span></h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Items</p>
                </div>
                <div class="mt-4 w-full text-center">
                    <svg
                        class="w-full h-6 text-red-400 dark:text-red-300"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 100 30">
                        <path d="M0 20 C25 10, 50 30, 75 10 S125 20, 150 10 S175 20, 200 10 S225 20, 250 10 S275 20, 300 10 S325 20, 350 10 S375 20, 400 10" />
                    </svg>
                </div>
            </div>
            <!-- ./card 3 -->

            <!-- card 4 (Branches) -->
            <div class="p-4 bg-yellow-100/50 dark:bg-yellow-900/50 rounded-lg shadow-md w-full flex">
                <div class="w-full">
                    <h1 class="text-2xl font-bold text-yellow-800 dark:text-yellow-300">35<span class="text-yellow-500 dark:text-yellow-400">K</span></h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Branches</p>
                </div>
                <div class="mt-4 w-full text-center">
                    <svg
                        class="w-full h-6 text-yellow-400 dark:text-yellow-300"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 100 30">
                        <path d="M0 20 C25 10, 50 30, 75 10 S125 20, 150 10 S175 20, 200 10 S225 20, 250 10 S275 20, 300 10 S325 20, 350 10 S375 20, 400 10" />
                    </svg>
                </div>
            </div>
            <!-- ./card 4 -->
        </div>
        <!-- ./cards -->

        <!-- second container -->
        <div class="my-5 w-full p-5 bg-opacity-50 bg-white dark:bg-gray-800">
            <!-- Income chart -->

            <h2 class="text-3xl font-bold">Earnings</h2>
            <div class="relative w-full min-h-96">
                <canvas id="earningsChart"></canvas>
            </div>

            <!-- ./Income chart -->
        </div>
        <!-- ./second container -->



    </div>
    <!-- ./main page container -->

    <!-- income chart -->
    <script>
        const ctx = document.getElementById('earningsChart').getContext('2d');

        // Labels for the chart
        const labels = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        const paidAmounts = <?php echo json_encode($dashboardData['paidAmounts'], 15, 512) ?>; // Real paid data
        const unpaidAmounts = <?php echo json_encode($dashboardData['unpaidAmounts'], 15, 512) ?>; // Real unpaid data

        // Data for the chart
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
                    label: 'unpaid Amounts - Invoices',
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

        // Chart configuration
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
    </script>

    <!-- ./income chart -->




 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/companies/index.blade.php ENDPATH**/ ?>