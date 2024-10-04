<x-app-layout>
    <div class="container mx-auto p-5">
        <h1 class="text-3xl font-bold mb-5">{{$company->name}}</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold">Total Invoice Amount</h2>
                 <p id="totalInvoiceAmount" class="text-2xl">0</p>
            </div>
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold">Total Agents</h2>
                <p id="totalAgents" class="text-2xl">0</p>
            </div>
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold">Total Clients</h2>
                <p id="totalClients" class="text-2xl">0</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold">Total Tasks</h2>
                <p id="totalTasks" class="text-2xl">0</p>
            </div>
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold">Pending Tasks</h2>
                <p id="pendingTasks" class="text-2xl">0</p>
            </div>
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold">Completed Tasks</h2>
                <p id="completedTasks" class="text-2xl">0</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold">Total Invoices</h2>
                <p id="totalInvoices" class="text-2xl">0</p>
            </div>
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold">Paid Invoices</h2>
                <p id="paidInvoices" class="text-2xl">0</p>
            </div>
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold">Unpaid Invoices</h2>
                <p id="unpaidInvoices" class="text-2xl">0</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold mb-3">Tasks Overview</h2>
                <div id="tasksChart" class="h-48"></div>
            </div>

            <div class="bg-white shadow-md rounded-lg p-5">
                <h2 class="text-xl font-semibold mb-3">Invoices Overview</h2>
                <div id="invoicesChart" class="h-48"></div>
            </div>
        </div>

        <div class="mb-6">
            <h2 class="text-xl font-semibold mb-3">Agents Overview</h2>
            <div id="agentssOverview" class="bg-white shadow-md rounded-lg p-5">
                <table class="min-w-full border-collapse border border-gray-200">
                    <thead>
                        <tr>
                            <th class="border border-gray-300 px-4 py-2">Agent Name</th>
                            <th class="border border-gray-300 px-4 py-2">Task Count</th>
                            <th class="border border-gray-300 px-4 py-2">Total Invoices</th>
                            <th class="border border-gray-300 px-4 py-2">Pending Tasks</th>
                        </tr>
                    </thead>
                    <tbody id="agentsTableBody">
                        <!-- Agent rows will be dynamically added here -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-6">
            <h2 class="text-xl font-semibold mb-3">Clients Overview</h2>
            <div id="clientsOverview" class="bg-white shadow-md rounded-lg p-5">
                <table class="min-w-full border-collapse border border-gray-200">
                    <thead>
                        <tr>
                            <th class="border border-gray-300 px-4 py-2">Client Name</th>
                            <th class="border border-gray-300 px-4 py-2">Task Count</th>
                            <th class="border border-gray-300 px-4 py-2">Total Invoices</th>
                            <th class="border border-gray-300 px-4 py-2">Unpaid Invoices</th>
                        </tr>
                    </thead>
                    <tbody id="clientsTableBody">
                        <!-- Client rows will be dynamically added here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Sample data for demonstration
            const dashboardData = @json($dashboardData);

            const formattedInvoiceAmount = new Intl.NumberFormat('en-US', { 
            style: 'currency', 
            currency: 'MYR' 
        }).format(dashboardData.totalInvoiceAmount);

            // Populate key metrics      
            document.getElementById("totalInvoiceAmount").innerText = formattedInvoiceAmount;
            document.getElementById("totalAgents").innerText = dashboardData.agentsCount;
            document.getElementById("totalClients").innerText = dashboardData.clientsCount; 
            document.getElementById("totalTasks").innerText = dashboardData.totalTasks;
            document.getElementById("pendingTasks").innerText = dashboardData.pendingTasks;
            document.getElementById("completedTasks").innerText = dashboardData.completedTasks;
            document.getElementById("totalInvoices").innerText = dashboardData.totalInvoices;
            document.getElementById("paidInvoices").innerText = dashboardData.paidInvoices;
            document.getElementById("unpaidInvoices").innerText = dashboardData.unpaidInvoices;

            // Create Tasks Overview Chart
            const tasksChartOptions = {
                chart: {
                    type: 'bar'
                },
                series: [{
                    name: 'Tasks',
                    data: [dashboardData.pendingTasks, dashboardData.completedTasks]
                }],
                xaxis: {
                    categories: ['Pending', 'Completed']
                }
            };

            const tasksChart = new ApexCharts(document.querySelector("#tasksChart"), tasksChartOptions);
            tasksChart.render();

            // Create Invoices Overview Chart
            const invoicesChartOptions = {
                chart: {
                    type: 'pie'
                },
                series: [dashboardData.paidInvoices, dashboardData.unpaidInvoices],
                labels: ['Paid', 'Unpaid']
            };

            const invoicesChart = new ApexCharts(document.querySelector("#invoicesChart"), invoicesChartOptions);
            invoicesChart.render();

            // Populate Clients Overview Table
            const clientsTableBody = document.getElementById("clientsTableBody");
            dashboardData.clients.forEach(client => {
                const row = document.createElement("tr");
                row.innerHTML = `
                    <td class="border border-gray-300 px-4 py-2">${client.name}</td>
                    <td class="border border-gray-300 px-4 py-2">${client.taskCount}</td>
                    <td class="border border-gray-300 px-4 py-2">${client.totalInvoices}</td>
                    <td class="border border-gray-300 px-4 py-2">${client.unpaidInvoices}</td>
                `;
                clientsTableBody.appendChild(row);
            });

            const agentsTableBody = document.getElementById("agentsTableBody");
            dashboardData.agents.forEach(agent => {
                const row = document.createElement("tr");
                row.innerHTML = `
                    <td class="border border-gray-300 px-4 py-2">${agent.name}</td>
                    <td class="border border-gray-300 px-4 py-2">${agent.taskCount}</td>
                    <td class="border border-gray-300 px-4 py-2">${agent.totalInvoices}</td>
                    <td class="border border-gray-300 px-4 py-2">${agent.pendingTasks}</td>
                `;
                agentsTableBody.appendChild(row);
            });

        });
    </script>
</x-app-layout>
