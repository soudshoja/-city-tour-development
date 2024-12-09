// Agent badhboard js 

  document.addEventListener("alpine:init", () => {
                Alpine.data("revenueChart", () => ({
                    chart: null,
                    init() {
                        const ctx = this.$refs.revenueChartCanvas.getContext('2d');
                        const isDark = document.documentElement.classList.contains(
                            'dark');

                        const data = {
                            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                            ],
                            datasets: [{
                                    label: 'Total Income',
                                    data: [12000, 16800, 15500, 17800,
                                        15500, 17000, 19000, 16000,
                                        15000, 17000, 14000, 17000
                                    ],
                                    borderColor: isDark ? '#2196f3' : '#1b55e2',
                                    backgroundColor: null, // Remove background color
                                    fill: false, // Disable fill
                                    tension: 0.4
                                },
                                {
                                    label: 'Paid Amount',
                                    data: [11000, 17500, 16200, 17300,
                                        16000, 19500, 16000, 17000,
                                        16000, 19000, 18000, 19000
                                    ],
                                    borderColor: '#4caf50',
                                    backgroundColor: null, // Remove background color
                                    fill: false, // Disable fill
                                    tension: 0.4
                                },
                                {
                                    label: 'Unpaid Amount',
                                    data: [5000, 7000, 8000, 5000, 12000,
                                        6000, 4000, 8000, 9000, 7000,
                                        5700, 11000
                                    ],
                                    borderColor: '#e7515a',
                                    backgroundColor: null, // Remove background color
                                    fill: false, // Disable fill
                                    tension: 0.4
                                }
                            ]
                        };

                        const options = {
                            responsive: true,
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    }
                                },
                                y: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return value / 1000 + 'K';
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        usePointStyle: true
                                    }
                                },

                                tooltip: {
                                    enabled: true,
                                    callbacks: {
                                        label: function(tooltipItem) {
                                            return tooltipItem.dataset
                                                .label + ': ' + tooltipItem
                                                .raw / 1000 + 'K';
                                        }
                                    }
                                }
                            }
                        };

                        this.chart = new Chart(ctx, {
                            type: 'line',
                            data: data,
                            options: options
                        });
                    },
                    updateChart() {
                        if (this.chart) {
                            this.chart.update();
                        }
                    }
                }));
  });
            
  