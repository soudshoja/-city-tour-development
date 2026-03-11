<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Manual Intervention - Failed Documents</h1>
            <p class="text-muted">Review and retry failed document processing jobs</p>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(session('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo e(session('error')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Error Statistics -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Error Statistics</h5>
                </div>
                <div class="card-body">
                    <?php if($errorStats->isEmpty()): ?>
                        <p class="text-muted">No failed documents found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Error Code</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $total = $errorStats->sum('count');
                                    ?>
                                    <?php $__currentLoopData = $errorStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><code><?php echo e($stat->error_code); ?></code></td>
                                        <td><?php echo e($stat->count); ?></td>
                                        <td>
                                            <?php
                                                $percentage = $total > 0 ? round(($stat->count / $total) * 100, 1) : 0;
                                            ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-danger" role="progressbar"
                                                     style="width: <?php echo e($percentage); ?>%"
                                                     aria-valuenow="<?php echo e($percentage); ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo e($percentage); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="<?php echo e(route('admin.manual-intervention.index')); ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Error Code</label>
                        <select name="error_code" class="form-select">
                            <option value="">All Error Codes</option>
                            <?php $__currentLoopData = $errorStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($stat->error_code); ?>"
                                    <?php echo e(request('error_code') == $stat->error_code ? 'selected' : ''); ?>>
                                    <?php echo e($stat->error_code); ?> (<?php echo e($stat->count); ?>)
                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Company</label>
                        <select name="company_id" class="form-select">
                            <option value="">All Companies</option>
                            <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($company->id); ?>"
                                    <?php echo e(request('company_id') == $company->id ? 'selected' : ''); ?>>
                                    <?php echo e($company->name); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Supplier ID</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">All Suppliers</option>
                            <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplierId): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($supplierId); ?>"
                                    <?php echo e(request('supplier_id') == $supplierId ? 'selected' : ''); ?>>
                                    Supplier <?php echo e($supplierId); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Document Type</label>
                        <select name="document_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="air" <?php echo e(request('document_type') == 'air' ? 'selected' : ''); ?>>AIR</option>
                            <option value="pdf" <?php echo e(request('document_type') == 'pdf' ? 'selected' : ''); ?>>PDF</option>
                            <option value="image" <?php echo e(request('document_type') == 'image' ? 'selected' : ''); ?>>Image</option>
                            <option value="email" <?php echo e(request('document_type') == 'email' ? 'selected' : ''); ?>>Email</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control"
                               value="<?php echo e(request('date_from')); ?>">
                    </div>

                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <a href="<?php echo e(route('admin.manual-intervention.index')); ?>" class="btn btn-secondary btn-sm">
                            Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Failed Documents Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Failed Documents (<?php echo e($failedDocuments->total()); ?>)</h5>
            <div>
                <button type="button" class="btn btn-warning btn-sm" onclick="bulkRetrySelected()" id="bulk-retry-btn" disabled>
                    <i class="bi bi-arrow-clockwise"></i> Bulk Retry Selected
                </button>
                <a href="<?php echo e(route('admin.manual-intervention.export-csv', request()->all())); ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-download"></i> Export to CSV
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if($failedDocuments->isEmpty()): ?>
                <p class="text-muted">No failed documents found</p>
            <?php else: ?>
                <form id="bulk-retry-form" method="POST" action="<?php echo e(route('admin.manual-intervention.bulk-retry')); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select-all" class="form-check-input">
                                    </th>
                                    <th>Status</th>
                                    <th>Document ID</th>
                                    <th>Company</th>
                                    <th>Supplier</th>
                                    <th>Type</th>
                                    <th>Error Code</th>
                                    <th>Error Message</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $failedDocuments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="document_ids[]" value="<?php echo e($doc->id); ?>"
                                               class="form-check-input doc-checkbox">
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">FAILED</span>
                                    </td>
                                    <td>
                                        <code style="font-size: 0.85em;">
                                            <?php echo e(Str::limit($doc->document_id, 16, '...')); ?>

                                        </code>
                                    </td>
                                    <td><?php echo e($doc->company->name ?? 'N/A'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo e($doc->supplier_id); ?></span></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo e(strtoupper($doc->document_type)); ?></span>
                                    </td>
                                    <td>
                                        <code class="text-danger"><?php echo e($doc->error_code); ?></code>
                                    </td>
                                    <td><?php echo e(Str::limit($doc->error_message, 50)); ?></td>
                                    <td>
                                        <small><?php echo e($doc->created_at->diffForHumans()); ?></small>
                                        <br>
                                        <small class="text-muted"><?php echo e($doc->created_at->format('Y-m-d H:i')); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?php echo e(route('admin.manual-intervention.show', $doc)); ?>"
                                               class="btn btn-sm btn-info">
                                                View
                                            </a>
                                            <a href="<?php echo e(route('admin.manual-intervention.timeline', $doc)); ?>"
                                               class="btn btn-sm btn-secondary">
                                                Timeline
                                            </a>
                                            <form method="POST"
                                                  action="<?php echo e(route('admin.manual-intervention.retry', $doc)); ?>"
                                                  style="display:inline;">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="btn btn-sm btn-warning"
                                                        onclick="return confirm('Retry processing this document?')">
                                                    Retry
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    <?php echo e($failedDocuments->links()); ?>

                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Select all checkbox functionality
document.getElementById('select-all')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.doc-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateBulkRetryButton();
});

// Individual checkbox change
document.querySelectorAll('.doc-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkRetryButton);
});

function updateBulkRetryButton() {
    const checkedCount = document.querySelectorAll('.doc-checkbox:checked').length;
    const bulkRetryBtn = document.getElementById('bulk-retry-btn');
    bulkRetryBtn.disabled = checkedCount === 0;
    bulkRetryBtn.textContent = checkedCount > 0
        ? `Bulk Retry Selected (${checkedCount})`
        : 'Bulk Retry Selected';
}

function bulkRetrySelected() {
    const checkedCount = document.querySelectorAll('.doc-checkbox:checked').length;
    if (checkedCount === 0) {
        alert('Please select at least one document to retry');
        return;
    }

    if (confirm(`Are you sure you want to retry ${checkedCount} document(s)?`)) {
        document.getElementById('bulk-retry-form').submit();
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/manual-intervention/index.blade.php ENDPATH**/ ?>