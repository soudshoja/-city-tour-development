<x-app-layout>
    <!-- main page container -->
    <div class="">

        <!-- cards -->
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 mt-3">
            <!-- card 1 (Customers) -->
            <div class="p-4 bg-green-100/50 rounded-lg shadow-md w-full flex">
                <div class="w-full">
                    <h1 class="text-2xl font-bold text-green-800">{{$dashboardData['clientsCount']}}<span class="text-green-500"></span></h1>
                    <p class="text-xs text-gray-500 mt-1">Total Customers</p>
                </div>
                <div class="mt-4 w-full text-center">
                    <svg
                        class="w-full h-6 text-green-400"
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
            <div class="p-4 bg-blue-100/50 rounded-lg shadow-md w-full flex">
                <div class="w-full">
                    <h1 class="text-2xl font-bold text-blue-800">{{$dashboardData['agentsCount']}}<span class="text-blue-500"></span></h1>
                    <p class="text-xs text-gray-500 mt-1">Total Agents</p>
                </div>
                <div class="mt-4 w-full text-center">
                    <svg
                        class="w-full h-6 text-blue-400"
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
            <div class="p-4 bg-red-100/50  rounded-lg shadow-md w-full flex">
                <div class="w-full">
                    <h1 class="text-2xl font-bold text-red-800">{{$dashboardData['totalTasks']}}<span class="text-red-500"></span></h1>
                    <p class="text-xs text-gray-500 mt-1">Total Tasks</p>
                </div>
                <div class="mt-4 w-full text-center">
                    <svg
                        class="w-full h-6 text-red-400"
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
            <div class="p-4 bg-yellow-100/50 rounded-lg shadow-md w-full flex">
                <div class="w-full">
                    <h1 class="text-2xl font-bold text-yellow-800">{{$dashboardData['totalBranches']}}<span class="text-yellow-500"></span></h1>
                    <p class="text-xs text-gray-500 mt-1">Total Branches</p>
                </div>
                <div class="mt-4 w-full text-center">
                    <svg
                        class="w-full h-6 text-yellow-400"
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
        <div class="my-5 grid gap-6 xl:grid-cols-3">

            <!-- Income chart -->
            <div class="panel xl:col-span-2">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Earnings</h2>
                <div class="relative w-full min-h-96">
                    <canvas id="earningsChart"></canvas>
                </div>
            </div>
            <!-- ./Income chart -->

            <!-- Top Branchs chart -->
            <div class="panel">
                <!-- Title and Subtitle -->
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold">TOP BRANCHES</h2>
                    <p class="text-gray-600">Performance Metrics</p>
                </div>


                <div id="branchCardsContainer" class="flex flex-wrap justify-center gap-x-20 gap-y-16 mb-8">
                    <!-- Branches charts will be dynamically generated here -->
                </div>
            </div>
            <!-- ./Top Branchs chart -->

        </div>
        <!-- ./second container -->


        <!-- third container -->
        <div class="my-5 grid gap-6 xl:grid-cols-3 mt-5">
            <!-- Recent activities -->
            <div class="panel h-full">
                <div class="-mx-5 mb-5 flex items-start justify-between border-b border-[#e0e6ed] p-5 pt-0 dark:border-[#1b2e4b] dark:text-white-light">
                    <h5 class="text-lg font-semibold">Activity Log</h5>
                    <div x-data="dropdown" @click.outside="open = false" class="dropdown">
                        <a href="javascript:;" @click="toggle">
                            <svg class="h-5 w-5 text-black/70 hover:!text-primary dark:text-white/70" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="5" cy="12" r="2" stroke="currentColor" stroke-width="1.5"></circle>
                                <circle opacity="0.5" cx="12" cy="12" r="2" stroke="currentColor" stroke-width="1.5"></circle>
                                <circle cx="19" cy="12" r="2" stroke="currentColor" stroke-width="1.5"></circle>
                            </svg>
                        </a>
                        <ul x-show="open" x-transition="" x-transition.duration.300ms="" class="right-0" style="display: none;">
                            <li><a href="javascript:;" @click="toggle">View All</a></li>
                            <li><a href="javascript:;" @click="toggle">Mark as Read</a></li>
                        </ul>
                    </div>
                </div>
                <div class="perfect-scrollbar relative -mr-3 h-[360px] pr-3 ps ps--active-y">
                    <div class="space-y-7">
                        <div class="flex">
                            <div class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-secondary text-white shadow shadow-secondary">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <h5 class="font-semibold dark:text-white-light">
                                    New project created : <a href="javascript:;" class="text-success">[VRISTO Admin Template]</a>
                                </h5>
                                <p class="text-xs text-white-dark">27 Feb, 2020</p>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2 ">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-success text-white shadow-success">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path opacity="0.5" d="M2 12C2 8.22876 2 6.34315 3.17157 5.17157C4.34315 4 6.22876 4 10 4H14C17.7712 4 19.6569 4 20.8284 5.17157C22 6.34315 22 8.22876 22 12C22 15.7712 22 17.6569 20.8284 18.8284C19.6569 20 17.7712 20 14 20H10C6.22876 20 4.34315 20 3.17157 18.8284C2 17.6569 2 15.7712 2 12Z" stroke="currentColor" stroke-width="1.5"></path>
                                        <path d="M6 8L8.1589 9.79908C9.99553 11.3296 10.9139 12.0949 12 12.0949C13.0861 12.0949 14.0045 11.3296 15.8411 9.79908L18 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <h5 class="font-semibold dark:text-white-light">
                                    Mail sent to <a href="javascript:;" class="text-white-dark">HR</a> and
                                    <a href="javascript:;" class="text-white-dark">Admin</a>
                                </h5>
                                <p class="text-xs text-white-dark">28 Feb, 2020</p>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2 ">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path opacity="0.5" d="M4 12.9L7.14286 16.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M20.0002 7.5625L11.4286 16.5625L11.0002 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <h5 class="font-semibold dark:text-white-light">Server Logs Updated</h5>
                                <p class="text-xs text-white-dark">27 Feb, 2020</p>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2 ">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-danger text-white">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path opacity="0.5" d="M4 12.9L7.14286 16.5L15 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M20.0002 7.5625L11.4286 16.5625L11.0002 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <h5 class="font-semibold dark:text-white-light">
                                    Task Completed : <a href="javascript:;" class="text-success">[Backup Files EOD]</a>
                                </h5>
                                <p class="text-xs text-white-dark">01 Mar, 2020</p>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-warning text-white">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M15.3929 4.05365L14.8912 4.61112L15.3929 4.05365ZM19.3517 7.61654L18.85 8.17402L19.3517 7.61654ZM21.654 10.1541L20.9689 10.4592V10.4592L21.654 10.1541ZM3.17157 20.8284L3.7019 20.2981H3.7019L3.17157 20.8284ZM20.8284 20.8284L20.2981 20.2981L20.2981 20.2981L20.8284 20.8284ZM14 21.25H10V22.75H14V21.25ZM2.75 14V10H1.25V14H2.75ZM21.25 13.5629V14H22.75V13.5629H21.25ZM14.8912 4.61112L18.85 8.17402L19.8534 7.05907L15.8947 3.49618L14.8912 4.61112ZM22.75 13.5629C22.75 11.8745 22.7651 10.8055 22.3391 9.84897L20.9689 10.4592C21.2349 11.0565 21.25 11.742 21.25 13.5629H22.75ZM18.85 8.17402C20.2034 9.3921 20.7029 9.86199 20.9689 10.4592L22.3391 9.84897C21.9131 8.89241 21.1084 8.18853 19.8534 7.05907L18.85 8.17402ZM10.0298 2.75C11.6116 2.75 12.2085 2.76158 12.7405 2.96573L13.2779 1.5653C12.4261 1.23842 11.498 1.25 10.0298 1.25V2.75ZM15.8947 3.49618C14.8087 2.51878 14.1297 1.89214 13.2779 1.5653L12.7405 2.96573C13.2727 3.16993 13.7215 3.55836 14.8912 4.61112L15.8947 3.49618ZM10 21.25C8.09318 21.25 6.73851 21.2484 5.71085 21.1102C4.70476 20.975 4.12511 20.7213 3.7019 20.2981L2.64124 21.3588C3.38961 22.1071 4.33855 22.4392 5.51098 22.5969C6.66182 22.7516 8.13558 22.75 10 22.75V21.25ZM1.25 14C1.25 15.8644 1.24841 17.3382 1.40313 18.489C1.56076 19.6614 1.89288 20.6104 2.64124 21.3588L3.7019 20.2981C3.27869 19.8749 3.02502 19.2952 2.88976 18.2892C2.75159 17.2615 2.75 15.9068 2.75 14H1.25ZM14 22.75C15.8644 22.75 17.3382 22.7516 18.489 22.5969C19.6614 22.4392 20.6104 22.1071 21.3588 21.3588L20.2981 20.2981C19.8749 20.7213 19.2952 20.975 18.2892 21.1102C17.2615 21.2484 15.9068 21.25 14 21.25V22.75ZM21.25 14C21.25 15.9068 21.2484 17.2615 21.1102 18.2892C20.975 19.2952 20.7213 19.8749 20.2981 20.2981L21.3588 21.3588C22.1071 20.6104 22.4392 19.6614 22.5969 18.489C22.7516 17.3382 22.75 15.8644 22.75 14H21.25ZM2.75 10C2.75 8.09318 2.75159 6.73851 2.88976 5.71085C3.02502 4.70476 3.27869 4.12511 3.7019 3.7019L2.64124 2.64124C1.89288 3.38961 1.56076 4.33855 1.40313 5.51098C1.24841 6.66182 1.25 8.13558 1.25 10H2.75ZM10.0298 1.25C8.15538 1.25 6.67442 1.24842 5.51887 1.40307C4.34232 1.56054 3.39019 1.8923 2.64124 2.64124L3.7019 3.7019C4.12453 3.27928 4.70596 3.02525 5.71785 2.88982C6.75075 2.75158 8.11311 2.75 10.0298 2.75V1.25Z" fill="currentColor"></path>
                                        <path opacity="0.5" d="M13 2.5V5C13 7.35702 13 8.53553 13.7322 9.26777C14.4645 10 15.643 10 18 10H22" stroke="currentColor" stroke-width="1.5"></path>
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <h5 class="font-semibold dark:text-white-light">
                                    Documents Submitted from <a href="javascript:;">Sara</a>
                                </h5>
                                <p class="text-xs text-white-dark">10 Mar, 2020</p>
                            </div>
                        </div>
                        <div class="flex">
                            <div class="shrink-0 mr-2 ">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-dark text-white">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4">
                                        <path opacity="0.5" d="M2 17C2 15.1144 2 14.1716 2.58579 13.5858C3.17157 13 4.11438 13 6 13H18C19.8856 13 20.8284 13 21.4142 13.5858C22 14.1716 22 15.1144 22 17C22 18.8856 22 19.8284 21.4142 20.4142C20.8284 21 19.8856 21 18 21H6C4.11438 21 3.17157 21 2.58579 20.4142C2 19.8284 2 18.8856 2 17Z" stroke="currentColor" stroke-width="1.5"></path>
                                        <path opacity="0.5" d="M2 6C2 4.11438 2 3.17157 2.58579 2.58579C3.17157 2 4.11438 2 6 2H18C19.8856 2 20.8284 2 21.4142 2.58579C22 3.17157 22 4.11438 22 6C22 7.88562 22 8.82843 21.4142 9.41421C20.8284 10 19.8856 10 18 10H6C4.11438 10 3.17157 10 2.58579 9.41421C2 8.82843 2 7.88562 2 6Z" stroke="currentColor" stroke-width="1.5"></path>
                                        <path d="M11 6H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        <path d="M6 6H8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        <path d="M11 17H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        <path d="M6 17H8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <h5 class="font-semibold dark:text-white-light">Server rebooted successfully</h5>
                                <p class="text-xs text-white-dark">06 Apr, 2020</p>
                            </div>
                        </div>
                    </div>
                    <div class="ps__rail-x" style="left: 0px; bottom: 0px;">
                        <div class="ps__thumb-x" tabindex="0" style="left: 0px; width: 0px;"></div>
                    </div>

                </div>
            </div>
            <!-- ./Recent activities -->
            <!-- Top Agents chart-->
            <div class="panel xl:col-span-2 flex flex-col md:flex-row items-start gap-5">
                <!-- Agent Cards Container (Left Side) -->
                <div id="agentCardsContainer" class="w-full md:w-1/2 space-y-4 xl:mt-20 lg:mt-20"></div>

                <!-- Chart Container (Right Side) -->
                <div class="w-full md:w-1/2 flex items-center justify-center mt-5 md:mt-0 chart-container">
                    <canvas id="chartOfTopAgents" class="chart-canvas"></canvas>
                </div>
            </div>
            <!-- ./Top Agents chart -->

        </div>
        <!-- ./third container -->

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

        const paidAmounts = @json($dashboardData['paidAmounts']); // Real paid data
        const unpaidAmounts = @json($dashboardData['unpaidAmounts']); // Real unpaid data

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

    <!-- top branches chart -->
    <script>
        // Labels and data for "Top Branches" chart
        const chartOfTopBranchesLabels = @json($dashboardData['chartBranchData']->pluck('name'));
        const chartOfTopBranchesDataValues = @json($dashboardData['chartBranchData']->pluck('percentage'));

        // Container to hold all branch cards with charts
        const branchCardsContainer = document.getElementById('branchCardsContainer');

        chartOfTopBranchesLabels.forEach((label, index) => {
            const percentage = chartOfTopBranchesDataValues[index];

            // Create a container for each branch chart
            const card = document.createElement('div');
            card.className = 'flex flex-col items-center';

            // Create a canvas for the chart
            const canvas = document.createElement('canvas');
            canvas.id = `chartOfTopBranch_${index}`;
            canvas.width = 100;
            canvas.height = 100;

            // Append the canvas to the card
            card.appendChild(canvas);

            // Append the card to the container
            branchCardsContainer.appendChild(card);

            // Create the chart with Chart.js
            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [`${percentage}%`],
                    datasets: [{
                        label: label,
                        data: [percentage, 100 - percentage],
                        backgroundColor: [
                            'rgb(234 179 8)', // Main color for the filled portion
                            'rgba(0, 0, 0, 0.05)', // Light gray for the remaining portion
                        ],
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: false,
                    maintainAspectRatio: false,
                    cutout: '80%', // Create a thin ring to match the style in the image
                    plugins: {
                        legend: {
                            display: false, // Hide the default legend
                        },
                        tooltip: {
                            enabled: false, // Disable tooltips to match the image style
                        },
                    },
                    elements: {
                        arc: {
                            borderRadius: 5, // Add rounded edges for a smooth appearance
                        },
                    },
                },
                plugins: [{
                    id: 'centralTextPlugin',
                    beforeDraw: (chart) => {
                        const {
                            width,
                            height,
                            ctx
                        } = chart;
                        ctx.restore();

                        // Set text properties
                        const fontSize = (height / 140).toFixed(2);
                        ctx.font = `${fontSize}em sans-serif`;
                        ctx.textBaseline = 'middle';
                        ctx.textAlign = 'center';

                        // Branch name text (in blue)
                        const branchName = chart.data.datasets[0].label.toUpperCase();
                        ctx.fillStyle = 'rgb(234 179 8)';
                        const branchNameX = width / 2;
                        const branchNameY = height / 2 - 10; // Position above the percentage
                        ctx.fillText(branchName, branchNameX, branchNameY);

                        // Percentage text (in black)
                        const percentageText = `${chart.data.labels[0]}`;
                        ctx.fillStyle = 'rgb(133 77 14)';
                        ctx.font = `${fontSize * 1.2}em sans-serif`; // Make the percentage slightly larger
                        const percentageY = height / 2 + 10; // Position below the branch name
                        ctx.fillText(percentageText, branchNameX, percentageY);

                        ctx.save();
                    },
                }, ],
            });
        });
    </script>
    <!-- ./top branches chart -->

    <!--  top agent chart -->
    <script>
        const agentsData = @json($dashboardData['agentsData']);

        // Labels and data for "Top Agents" chart
        const chartOfTopAgentsLabels = agentsData.map(agent => agent.name);
        const chartOfTopAgentsDataValues = agentsData.map(agent => agent.percentage);

        // Data for the donut chart
        const chartOfTopAgentsData = {
            labels: chartOfTopAgentsLabels,
            datasets: [{
                data: chartOfTopAgentsDataValues,
                backgroundColor: [
                    'rgb(191 219 254)',
                    'rgb(147 197 253)',
                    'rgb(96 165 250)',
                    'rgb(59 130 246)',
                ],
                DarkColor: [
                    'rgb(30 58 138)',
                    'rgb(30 58 138)',
                    'rgb(30 58 138)',
                    'rgb(30 58 138)',

                ],

            }],
        };

        // Plugin for central text (title and subtitle)
        const centralTextPlugin = {
            id: 'centralText',
            beforeDraw: function(chart) {
                const ctx = chart.ctx;
                const width = chart.width;
                const height = chart.height;

                ctx.restore();
                const fontSize = (height / 160).toFixed(2);
                ctx.font = `${fontSize}em sans-serif`;
                ctx.textBaseline = 'middle';
                // Title text
                const title = 'TOP AGENTS';
                ctx.font = `${fontSize * 0.5}em sans-serif`; // Make the title smaller
                const textX = Math.round((width - ctx.measureText(title).width) / 2);
                const textY = height / 2 - 15; // Adjusted to move closer to the center
                ctx.fillStyle = 'rgb(30 58 138)';
                ctx.fillText(title, textX, textY);

                // Subtitle text
                const subtitle = 'for this month';
                ctx.font = `${fontSize * 0.5}em sans-serif`;
                const subtitleX = Math.round((width - ctx.measureText(subtitle).width) / 2);
                const subtitleY = height / 2 + 15; // Adjusted to move closer to the center
                ctx.fillStyle = '#000';
                ctx.fillText(subtitle, subtitleX, subtitleY);

                ctx.save();
            },
        };

        // Chart options to match the style in the image with adjusted width
        // Adjusted Chart.js configuration for the Donut Chart
        const chartOfTopAgentsOptions = {
            responsive: true,
            maintainAspectRatio: false, // Disable maintaining aspect ratio for the chart
            cutout: '75%', // Set donut cutout percentage
            plugins: {
                legend: {
                    display: false, // Hide the default legend
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.raw}%`;
                        },
                    },
                },
            },
            elements: {
                arc: {
                    borderRadius: 5, // Rounded edges for each arc segment
                },
            },
        };

        // Create the Donut Chart with central text
        const Agentsctx = document.getElementById('chartOfTopAgents').getContext('2d');
       // Create the chart with updated data
        new Chart(Agentsctx, {
            type: 'doughnut',
            data: chartOfTopAgentsData,
            options: chartOfTopAgentsOptions,
            plugins: [centralTextPlugin],
        });

        // Generate agent cards dynamically
        agentsData.forEach((agent, index) => {
            const bgColor = chartOfTopAgentsData.datasets[0].backgroundColor[index % 4]; // Rotate colors
            const textColor = chartOfTopAgentsData.datasets[0].DarkColor[index % 4];

            const card = document.createElement('div');
            card.className = 'flex items-center justify-between p-4 rounded-lg shadow-md w-full';
            card.style.backgroundColor = bgColor;

            card.innerHTML = `
                <div class="w-full flex flex-col space-y-3">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 flex items-center justify-center rounded-full" style="background-color: ${textColor}">
                            <span class="text-white font-bold text-sm">${agent.name.charAt(0)}</span>
                        </div>
                        <div class="flex w-full items-center justify-between">
                            <span class="text-sm font-semibold">${agent.name}</span>
                            <span class="text-sm font-semibold">${agent.percentage.toFixed(2)}%</span>
                        </div>
                    </div>
                </div>`;
            agentCardsContainer.appendChild(card);
        });
    </script>
    <!-- ./top agent chart -->






</x-app-layout>