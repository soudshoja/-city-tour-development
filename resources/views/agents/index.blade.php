<x-app-layout>

    <!-- Tasks Revenue -->
    <div class="flex gap-4 mb-5">
        <div class="panel w-[5%] md:w-[5%] flex items-center justify-center">
            <h2 class="text-center text-sm font-semibold transform -rotate-90">
                <span class="text-primary">Tasks</span> Revenue
            </h2>
        </div>

        <!-- Total Tasks Card -->
        <div class="flex rounded-lg overflow-hidden bg-blue-500 text-white shadow-md w-[31.66%] md:w-[31.66%]">
            <div class="flex items-center justify-center w-1/3 bg-blue-700 p-4">
                <svg class="w-8 h-8" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M5.5 8C5.5 6.61929 6.61929 5.5 8 5.5H11C12.3807 5.5 13.5 6.61929 13.5 8V11C13.5 12.3807 12.3807 13.5 11 13.5H8C6.61929 13.5 5.5 12.3807 5.5 11V8Z"
                        stroke="#FFFFFF" stroke-width="1.5" />
                    <path
                        d="M5.5 19C5.5 17.6193 6.61929 16.5 8 16.5H11C12.3807 16.5 13.5 17.6193 13.5 19V22C13.5 23.3807 12.3807 24.5 11 24.5H8C6.61929 24.5 5.5 23.3807 5.5 22V19Z"
                        stroke="#FFFFFF" stroke-width="1.5" />
                    <path
                        d="M16.5 8C16.5 6.61929 17.6193 5.5 19 5.5H22C23.3807 5.5 24.5 6.61929 24.5 8V11C24.5 12.3807 23.3807 13.5 22 13.5H19C17.6193 13.5 16.5 12.3807 16.5 11V8Z"
                        stroke="#FFFFFF" stroke-width="1.5" />
                    <path
                        d="M16.5 19C16.5 17.6193 17.6193 16.5 19 16.5H22C23.3807 16.5 24.5 17.6193 24.5 19V22C24.5 23.3807 23.3807 24.5 22 24.5H19C17.6193 24.5 16.5 23.3807 16.5 22V19Z"
                        stroke="#FFFFFF" stroke-width="1.5" />
                </svg>
            </div>
            <div class="flex flex-col items-center justify-center w-2/3 p-4">
                <p class="text-3xl font-bold" id="totalTasks"></p>
                <p class="text-sm">Total Tasks</p>
            </div>
        </div>

        <!-- Pending Tasks Card -->
        <div class="flex rounded-lg overflow-hidden bg-[#e7515a] text-white shadow-md w-[31.66%] md:w-[31.66%]">
            <div class="flex items-center justify-center w-1/3 bg-[#c03f4c] p-4">
                <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path
                        d="M12 12L9.0423 14.9289C6.11981 17.823 4.65857 19.27 5.06765 20.5185C5.10282 20.6258 5.14649 20.7302 5.19825 20.8307C5.80046 22 7.86697 22 12 22C16.133 22 18.1995 22 18.8017 20.8307C18.8535 20.7302 18.8972 20.6258 18.9323 20.5185C19.3414 19.27 17.8802 17.823 14.9577 14.9289L12 12ZM12 12L14.9577 9.07107C17.8802 6.177 19.3414 4.729 18.9323 3.48149C18.8972 3.37417 18.8535 3.26977 18.8017 3.16926C18.1995 2 16.133 2 12 2C7.86697 2 5.80046 2 5.19825 3.16926C5.14649 3.26977 5.10282 3.37417 5.06765 3.48149C4.65857 4.729 6.11981 6.177 9.0423 9.07107L12 12Z" />
                    <path d="M10 5.5H14" />
                    <path d="M10 18.5H14" />
                </svg>
            </div>
            <div class="flex flex-col items-center justify-center w-2/3 p-4">
                <p class="text-3xl font-bold" id="pendingTasks"></p>
                <p class="text-sm">Pending Tasks</p>
            </div>
        </div>

        <!-- Completed Tasks Card -->
        <div class="flex rounded-lg overflow-hidden bg-green-500 text-white shadow-md w-[31.66%] md:w-[31.66%]">
            <div class="flex items-center justify-center w-1/3 bg-green-700 p-4">
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path opacity="0.5"
                        d="M2 12C2 7.28595 2 4.92893 3.46447 3.46447C4.92893 2 7.28595 2 12 2C16.714 2 19.0711 2 20.5355 3.46447C22 4.92893 22 7.28595 22 12C22 16.714 22 19.0711 20.5355 20.5355C19.0711 22 16.714 22 12 22C7.28595 22 4.92893 22 3.46447 20.5355C2 19.0711 2 16.714 2 12Z"
                        stroke="#FFFFFF" stroke-width="1.5" />
                    <path d="M8.5 12.5L10.5 14.5L15.5 9.5" stroke="#FFFFFF" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
            </div>
            <div class="flex flex-col items-center justify-center w-2/3 p-4">
                <p class="text-3xl font-bold" id="completedTasks"></p>
                <p class="text-sm">Completed Tasks</p>
            </div>
        </div>
    </div>

    <!-- Account Revenue -->
    <div class="mb-6 grid gap-6 xl:grid-cols-4">
        <div class="panel xl:col-span-3 ">
            <canvas id="AgentChart"></canvas>
        </div>
        <div class="panel bg-white p-5 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-center mb-4">All Expenses</h2>
            <div class="flex justify-around mb-4">
                <div class="text-center">
                    <h3 class="text-lg">Daily</h3>
                    <p class="text-sm">$682.20</p>
                </div>
                <div class="text-center">
                    <h3 class="text-lg">Weekly</h3>
                    <p class="text-sm">$2,183.26</p>
                </div>
                <div class="text-center">
                    <h3 class="text-lg">Monthly</h3>
                    <p class="text-sm">$6,638.72</p>
                </div>
            </div>

            <div class="chart-container mb-4" style="position: relative; height: 400px;">
                <canvas id="expenseChart"></canvas>
            </div>

            <div class="mt-4">
                <h4 class="text-lg font-semibold mb-2">Expense Breakdown</h4>
                <div class="flex justify-between">
                    <div class="text-green-600">Entertainments</div>
                    <div>46%</div>
                </div>
                <div class="flex justify-between">
                    <div class="text-red-600">Platform</div>
                    <div>56%</div>
                </div>
                <div class="flex justify-between">
                    <div class="text-orange-600">Shopping</div>
                    <div>48%</div>
                </div>
                <div class="flex justify-between">
                    <div class="text-green-500">Food & health</div>
                    <div>63%</div>
                </div>
            </div>

            <script>
            const ctxTask = document.getElementById('expenseChart').getContext('2d');

            const expenseChart = new Chart(ctxTask, {
                type: 'doughnut',
                data: {
                    labels: ['Total Tasks', 'Pending Tasks', 'Completed Tasks'],
                    datasets: [{
                            label: 'Total Tasks',
                            data: [300], // Main category (innermost ring)
                            backgroundColor: ['rgb(59 130 246)'], // Color for the main category
                            borderColor: 'white',
                            borderWidth: 5,
                            cutout: '50%', // Adjust this value for the inner cutout
                        },
                        {
                            label: 'Completed Tasks',
                            data: [985.90], // Main category (innermost ring)
                            backgroundColor: ['rgb(34 197 94)'], // Color for the main category
                            borderColor: 'white',
                            borderWidth: 5,
                            cutout: '40%', // Adjust this value for the inner cutout
                        },
                        {
                            label: 'Pending Tasks',
                            data: [500], // Subcategories data
                            backgroundColor: ['rgb(231, 81, 90)'],
                            borderColor: 'white',
                            borderWidth: 5,
                            cutout: '30%', // Adjust this value for the outer cutout
                        }
                    ]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(tooltipItem) {
                                    return tooltipItem.label + ': $' + tooltipItem.raw.toFixed(2);
                                }
                            }
                        },
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    }
                }
            });
            </script>
        </div>



    </div>

    <script>
    const ctx = document.getElementById('AgentChart').getContext('2d');
    const AgentChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                    label: 'Total Income',
                    data: [10389.49, 16000, 11000, 15000, 17000, 11000, 18000, 19000, 12000, 21000, 22000,
                        23000
                    ],
                    borderColor: 'rgba(0, 128, 0, 1)', // Green
                    backgroundColor: 'rgba(0, 128, 0, 0.2)', // Light green for the fill
                    borderWidth: 3, // Adjusted border width
                    pointRadius: 2, // Radius for the points
                    pointHoverRadius: 7, // Increased radius on hover
                    tension: 0.4 // Adjusted for smoother curves
                },
                {
                    label: 'Total Expenses',
                    data: [22400, 13000, 15500, 18000, 28500, 19000, 19500, 10000, 16600, 11000, 24500,
                        12000
                    ],
                    borderColor: 'rgb(234, 88, 12)', // Orange
                    backgroundColor: 'rgba(255, 165, 0, 0.2)', // Light orange for the fill
                    borderWidth: 3, // Adjusted border width
                    pointRadius: 2, // Radius for the points
                    pointHoverRadius: 7, // Increased radius on hover
                    tension: 0.4 // Adjusted for smoother curves
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(tooltipItem) {
                            return tooltipItem.dataset.label + ': $' + tooltipItem.raw.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: false // Remove y-axis grid lines
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                },
                x: {
                    grid: {
                        display: false // Remove x-axis grid lines
                    }
                }
            }
        }
    });
    </script>

</x-app-layout>