<x-app-layout>
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
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-3 mt-3">
            @can('viewAny', App\Models\Company::class && auth()->user()->hasRole('admin'))
                <div class="p-4 bg-green-100/50 dark:bg-green-900/50 rounded-lg shadow-md w-full flex">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-green-800 dark:text-green-300">{{ $companies->count() }}</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Companies</p>
                    </div>
                    <div class="mt-4 w-full text-center">
                    </div>
                </div>
            @endcan
            @can('viewAny', App\Models\Branch::class)
                <div class="p-4 bg-blue-100/50 dark:bg-blue-900/50 rounded-lg shadow-md w-full flex">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-blue-800 dark:text-blue-300">{{ $branches->count() }}</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Branches</p>
                    </div>
                    <div class="mt-4 w-full text-center">
                    </div>
                </div>
            @endcan
            @can('viewAny', App\Models\Agent::class)
                <div class="p-4 bg-red-100/50 dark:bg-red-900/50 rounded-lg shadow-md w-full flex">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-red-800 dark:text-red-300">{{ $agents->count() }}</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total Agents</p>
                    </div>
                    <div class="mt-4 w-full text-center">
                    </div>
                </div>
            @endcan
            @can('viewAny', App\Models\Client::class)
                <div class="p-4 bg-yellow-100/50 dark:bg-yellow-900/50 rounded-lg shadow-md w-full flex">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-yellow-800 dark:text-yellow-300">{{ $clients->count() }}</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"> Total Clients</p>
                    </div>
                    <div class="mt-4 w-full text-center">
                    </div>
                </div>
            @endcan
        </div>

        @if (auth()->user()->company && auth()->user()->hasRole('company'))
            <div class="my-5 w-full">
                <div class="flex flex-col lg:flex-row gap-3">
                    @if (isset($paidAmounts) && isset($unpaidAmounts))
                        <div class="w-full p-5 bg-opacity-50 bg-white dark:bg-gray-800 rounded-md shadow-md">
                            <h2 class="text-3xl font-bold">Earnings</h2>
                            <div id="earnings" class="relative w-full min-h-96"
                                data-paid="{{ json_encode($paidAmounts) }}"
                                data-unpaid="{{ json_encode($unpaidAmounts) }}">
                                <canvas id="earningsChart"></canvas>
                            </div>
                        </div>
                    @endif
                    <!-- <div class="flex flex-col gap-4 w-full lg:max-w-sm bg-white dark:bg-gray-800 p-5 rounded-lg shadow-lg">
                    <a href="{{ route('reports.payable-supplier') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-red-500 dark:border-red-400 bg-red-50 dark:bg-red-900 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-red-500 dark:text-red-400 font-medium">Payable Supplier</p>
                        <p class="text-xs text-red-400 dark:text-red-200">Amount owed to suppliers</p>
                        <p class="@if ($payableSupplier->balance < 0) text-green-600 dark:text-green-500 @else text-red-600 dark:text-red-400 @endif text-xl font-bold">{{ $payableSupplier->balance }}</p>
                        <span class="absolute top-2 right-2 text-red-400 dark:text-red-300 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                    <a href="{{ route('reports.profit-agent') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-green-500 dark:border-green-400 bg-green-50 dark:bg-green-900 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-green-600 dark:text-green-400 font-medium">Profit Agent Wise</p>
                        <p class="text-xs text-green-500 dark:text-green-200">Profit earned by agents</p>
                        <p class="text-green-600 dark:text-green-400 text-xl font-bold">{{ $profitAgentWise }}</p>
                        <span class="absolute top-2 right-2 text-green-400 dark:text-green-300 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                    <a href="{{ route('reports.total-receivable') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-koromiko-500 bg-koromiko-50 dark:bg-koromiko-700 dark:border-koromiko-300 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-koromiko-600 dark:text-koromiko-400 font-medium">Total Receivable</p>
                        <p class="text-koromiko-600 dark:text-koromiko-300 text-lg font-semibold">{{ $totalReceivable }}</p>
                        <span class="absolute top-2 right-2 text-koromiko-400 dark:text-koromiko-200 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                    <a href="{{ route('reports.total-bank') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-koromiko-500 bg-koromiko-50 dark:bg-koromiko-700 dark:border-koromiko-300 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-koromiko-500 dark:text-koromiko-400 font-medium">Total Bank</p>
                        <p class="text-koromiko-600 dark:text-koromiko-300 text-lg font-semibold">{{ $totalBank }}</p>
                        <span class="absolute top-2 right-2 text-koromiko-400 dark:text-koromiko-200 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>

                    </a>
                    <a href="{{ route('reports.gateway-receivable') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-koromiko-500 bg-koromiko-50 dark:bg-koromiko-700 dark:border-koromiko-300 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-koromiko-500 dark:text-koromiko-400 font-medium">Gateway Receivable</p>
                        <p class="text-koromiko-600 dark:text-koromiko-300 text-lg font-semibold">{{ $gatewayReceivable }}</p>
                        <span class="absolute top-2 right-2 text-koromiko-400 dark:text-koromiko-200 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                </div> -->
                    <div class="p-10 pt-2 bg-white dark:bg-gray-900 rounded-md shadow-md flex flex-col w-full lg:w-1/2">
                        <h1>
                            {{ $pieChartTitle }}
                        </h1>
                        <div x-data="chart" class="flex justify-center">
                            <div x-ref="donutChart" class="bg-white dark:bg-black rounded-lg"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="my-5 w-full p-10 pt-5 bg-white dark:bg-gray-900 rounded-md shadow-md flex flex-col w-full">
                <div class="grid grid-cols-2 gap-4 mt-3 mx-5">
                    <a href="{{ route('reports.payable-supplier') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-red-500 dark:border-red-400 bg-red-50 dark:bg-red-900 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-red-500 dark:text-red-400 font-medium">Payable Supplier</p>
                        <p class="text-xs text-red-400 dark:text-red-200">Amount owed to suppliers</p>
                        <p
                            class="@if ($payableSupplier->balance < 0) text-green-600 dark:text-green-500 @else text-red-600 dark:text-red-400 @endif text-xl font-bold">
                            {{ $payableSupplier->balance }}</p>
                        <span
                            class="absolute top-2 right-2 text-red-400 dark:text-red-300 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                    <a href="{{ route('reports.total-receivable') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-green-500 dark:border-green-400 bg-green-50 dark:bg-green-900 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-green-600 dark:text-green-400 font-medium">Total Receivable</p>
                        <p class="text-xs text-green-500 dark:text-green-200">Total amount due from clients or customers
                        </p>
                        <p class="text-green-600 dark:text-green-400 text-xl font-bold">{{ $totalReceivable }}</p>
                        <span
                            class="absolute top-2 right-2 text-green-400 dark:text-green-300 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                </div>
                <div class="grid grid-cols-3 gap-3 mt-3">
                    <a href="{{ route('reports.profit-agent') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-koromiko-500 bg-koromiko-50 dark:bg-koromiko-700 dark:border-koromiko-300 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-koromiko-500 dark:text-koromiko-400 font-medium">Profit Agent Wise</p>
                        <p class="text-xs text-koromiko-400 dark:text-koromiko-200">Profit earned by agents</p>
                        <p class="text-koromiko-600 dark:text-koromiko-300 text-lg font-semibold">
                            {{ $profitAgentWise }}</p>
                        <span
                            class="absolute top-2 right-2 text-koromiko-400 dark:text-koromiko-200 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                    <a href="{{ route('reports.total-bank') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-koromiko-500 bg-koromiko-50 dark:bg-koromiko-700 dark:border-koromiko-300 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-koromiko-500 dark:text-koromiko-400 font-medium">Total Bank</p>
                        <p class="text-xs text-koromiko-400 dark:text-koromiko-200">Total balance across all bank
                            accounts</p>
                        <p class="text-koromiko-600 dark:text-koromiko-300 text-lg font-semibold">{{ $totalBank }}
                        </p>
                        <span
                            class="absolute top-2 right-2 text-koromiko-400 dark:text-koromiko-200 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                    <a href="{{ route('reports.gateway-receivable') }}"
                        class="relative group flex flex-col gap-1 p-4 border-l-4 border-koromiko-500 bg-koromiko-50 dark:bg-koromiko-700 dark:border-koromiko-300 rounded-md transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-[1.01] cursor-pointer">
                        <p class="text-sm text-koromiko-500 dark:text-koromiko-400 font-medium">Gateway Receivable</p>
                        <p class="text-xs text-koromiko-400 dark:text-koromiko-200">Outstanding amounts from payment
                            gateways</p>
                        <p class="text-koromiko-600 dark:text-koromiko-300 text-lg font-semibold">
                            {{ $gatewayReceivable }}</p>
                        <span
                            class="absolute top-2 right-2 text-koromiko-400 dark:text-koromiko-200 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-in-out text-sm">↗</span>
                    </a>
                </div>
            </div>
        @endif
    </div>
    <script>
        document.addEventListener('alpine:init', () => {
            let pieChartNumbers = @json($pieChartNumbers);
            let pieChartLabels = @json($pieChartLabels);
            let pieChartColors = @json($pieChartColors);

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
    </script>
</x-app-layout>
