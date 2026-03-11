<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Error Analytics Dashboard</h1>
            <p class="text-muted">Real-time error monitoring and analysis</p>
        </div>
    </div>

    <!-- Time Range Selector -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary" data-range="24h" id="range-24h">Last 24 Hours</button>
                <button type="button" class="btn btn-outline-primary" data-range="7d" id="range-7d">Last 7 Days</button>
                <button type="button" class="btn btn-outline-primary" data-range="30d" id="range-30d">Last 30 Days</button>
            </div>
            <span class="ms-3 text-muted" id="loading-indicator" style="display:none;">
                <i class="spinner-border spinner-border-sm"></i> Loading...
            </span>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4" id="summary-cards">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Processed</h6>
                    <h2 class="mb-0" id="card-total">-</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Failed</h6>
                    <h2 class="mb-0" id="card-failed">-</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Success Rate</h6>
                    <h2 class="mb-0" id="card-success-rate">-%</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Avg Processing Time</h6>
                    <h2 class="mb-0" id="card-avg-time">-s</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Trend Chart -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Error Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="error-trend-chart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Type Distribution & Top Failing Suppliers -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Error Type Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="error-type-chart" height="200"></canvas>
                    <div id="error-type-list" class="mt-3"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Failing Suppliers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="supplier-errors-table">
                            <thead>
                                <tr>
                                    <th>Supplier ID</th>
                                    <th>Total</th>
                                    <th>Failed</th>
                                    <th>Error Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Type Errors & Recent Errors -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Document Type Errors</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="doc-type-errors-table">
                            <thead>
                                <tr>
                                    <th>Document Type</th>
                                    <th>Total</th>
                                    <th>Failed</th>
                                    <th>Error Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Errors</h5>
                </div>
                <div class="card-body">
                    <div id="recent-errors-list" style="max-height: 400px; overflow-y: auto;">
                        <p class="text-center text-muted">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="<?php echo e(route('admin.manual-intervention.index')); ?>" class="btn btn-primary">
                        View Manual Intervention Dashboard
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="refreshData()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh Data
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
let currentRange = '24h';
let errorTrendChart = null;
let errorTypeChart = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set default active button
    document.getElementById('range-24h').classList.add('active');

    // Fetch initial data
    fetchMetrics(currentRange);

    // Add event listeners to range buttons
    document.querySelectorAll('[data-range]').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('[data-range]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentRange = this.getAttribute('data-range');
            fetchMetrics(currentRange);
        });
    });
});

function refreshData() {
    fetchMetrics(currentRange);
}

function fetchMetrics(range) {
    showLoading(true);

    fetch(`<?php echo e(route('admin.error-dashboard.metrics')); ?>?range=${range}`)
        .then(response => response.json())
        .then(data => {
            updateSummaryCards(data.summary);
            updateErrorTrendChart(data.error_trend);
            updateErrorTypeChart(data.error_type_distribution);
            updateSupplierTable(data.supplier_errors);
            updateDocTypeTable(data.document_type_errors);
            updateRecentErrors(data.recent_errors);
            showLoading(false);
        })
        .catch(error => {
            console.error('Error fetching metrics:', error);
            showLoading(false);
            alert('Failed to load metrics. Please try again.');
        });
}

function showLoading(show) {
    document.getElementById('loading-indicator').style.display = show ? 'inline' : 'none';
}

function updateSummaryCards(summary) {
    document.getElementById('card-total').textContent = summary.total_processed.toLocaleString();
    document.getElementById('card-failed').textContent = summary.total_failed.toLocaleString();
    document.getElementById('card-success-rate').textContent = summary.success_rate.toFixed(1) + '%';
    document.getElementById('card-avg-time').textContent = summary.avg_processing_time_seconds.toFixed(2) + 's';
}

function updateErrorTrendChart(trendData) {
    const ctx = document.getElementById('error-trend-chart').getContext('2d');

    if (errorTrendChart) {
        errorTrendChart.destroy();
    }

    errorTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(d => d.period),
            datasets: [
                {
                    label: 'Total',
                    data: trendData.map(d => d.total),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Failed',
                    data: trendData.map(d => d.failed),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Completed',
                    data: trendData.map(d => d.completed),
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function updateErrorTypeChart(distribution) {
    const ctx = document.getElementById('error-type-chart').getContext('2d');

    if (errorTypeChart) {
        errorTypeChart.destroy();
    }

    if (distribution.length === 0) {
        document.getElementById('error-type-list').innerHTML = '<p class="text-muted">No error data available</p>';
        return;
    }

    errorTypeChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: distribution.map(d => d.error_code),
            datasets: [{
                data: distribution.map(d => d.count),
                backgroundColor: [
                    '#dc3545', '#fd7e14', '#ffc107', '#20c997', '#0dcaf0',
                    '#6610f2', '#d63384', '#6c757d', '#198754', '#0d6efd'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });

    // Update list
    let listHtml = '<ul class="list-unstyled">';
    distribution.forEach(d => {
        listHtml += `<li><code>${d.error_code}</code>: ${d.count} occurrences</li>`;
    });
    listHtml += '</ul>';
    document.getElementById('error-type-list').innerHTML = listHtml;
}

function updateSupplierTable(suppliers) {
    const tbody = document.querySelector('#supplier-errors-table tbody');

    if (suppliers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>';
        return;
    }

    let html = '';
    suppliers.forEach(supplier => {
        html += `
            <tr>
                <td><span class="badge bg-secondary">${supplier.supplier_id}</span></td>
                <td>${supplier.total}</td>
                <td class="text-danger">${supplier.failed}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-danger" role="progressbar"
                             style="width: ${supplier.error_rate}%">
                            ${supplier.error_rate}%
                        </div>
                    </div>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function updateDocTypeTable(docTypes) {
    const tbody = document.querySelector('#doc-type-errors-table tbody');

    if (docTypes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>';
        return;
    }

    let html = '';
    docTypes.forEach(docType => {
        html += `
            <tr>
                <td><span class="badge bg-info">${docType.document_type.toUpperCase()}</span></td>
                <td>${docType.total}</td>
                <td class="text-danger">${docType.failed}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-danger" role="progressbar"
                             style="width: ${docType.error_rate}%">
                            ${docType.error_rate}%
                        </div>
                    </div>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

function updateRecentErrors(errors) {
    const container = document.getElementById('recent-errors-list');

    if (errors.length === 0) {
        container.innerHTML = '<p class="text-center text-muted">No recent errors</p>';
        return;
    }

    let html = '<div class="list-group">';
    errors.forEach(error => {
        html += `
            <div class="list-group-item">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">
                        <code class="text-danger">${error.error_code}</code>
                    </h6>
                    <small class="text-muted">${error.created_at_human}</small>
                </div>
                <p class="mb-1 small">${error.error_message}</p>
                <small>
                    Doc: <code>${error.document_id.substring(0, 16)}...</code> |
                    Company: ${error.company_name} |
                    Supplier: ${error.supplier_id} |
                    Type: ${error.document_type.toUpperCase()}
                </small>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/error-dashboard/index.blade.php ENDPATH**/ ?>