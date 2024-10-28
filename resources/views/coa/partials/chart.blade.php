<div>

    <!-- Chart.js Script -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Breadcrumbs -->
    <ul class="flex space-x-2 pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <span>Chart of Account</span>
        </li>
    </ul>
    <!-- ./Breadcrumbs -->

    <div class="flex justify-center items-center min-h-screen bg-gray-100 p-6">
        <div class="w-1/3 space-y-4">
            <!-- Sidebar items -->
            <div class="bg-blue-600 text-white font-bold p-4 rounded-lg flex items-center space-x-2">
                <span>EXPENSES</span>
                <img src="/path-to-icon/expenses-icon.svg" alt="Expenses Icon" class="h-6 w-6">
            </div>
            <div class="bg-blue-800 text-white font-bold p-4 rounded-lg flex items-center space-x-2">
                <span>INCOME</span>
                <img src="/path-to-icon/income-icon.svg" alt="Income Icon" class="h-6 w-6">
            </div>
            <div class="bg-purple-600 text-white font-bold p-4 rounded-lg flex items-center space-x-2">
                <span>LIABILITIES</span>
                <img src="/path-to-icon/liabilities-icon.svg" alt="Liabilities Icon" class="h-6 w-6">
            </div>
            <div class="bg-purple-400 text-white font-bold p-4 rounded-lg flex items-center space-x-2">
                <span>ASSETS</span>
                <img src="/path-to-icon/assets-icon.svg" alt="Assets Icon" class="h-6 w-6">
            </div>
        </div>

        <div class="w-2/3 flex justify-center items-center">
            <!-- Donut Chart Container -->
            <div class="w-2/3">
                <canvas id="coaChart"></canvas>
                <div class="text-center mt-4">
                    <h2 class="text-lg font-semibold">CHART OF ACCOUNT</h2>
                    <p>City Tour Company</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    // JavaScript for Donut Chart
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('coaChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Assets', 'Expenses', 'Liabilities', 'Income'],
                datasets: [{
                    data: [25, 25, 25, 25], // Replace with actual values if needed
                    backgroundColor: ['#C29BFF', '#336699', '#664E8C', '#20375F'],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 14
                            }
                        }
                    }
                }
            }
        });
    });
    </script>

    <style>
    /* Additional styles to match the screenshot */
    .flex {
        display: flex;
    }

    .min-h-screen {
        min-height: 100vh;
    }

    .bg-gray-100 {
        background-color: #f9f9f9;
    }

    .rounded-lg {
        border-radius: 0.5rem;
    }

    .text-center {
        text-align: center;
    }

    .font-semibold {
        font-weight: 600;
    }
    </style>

</div>