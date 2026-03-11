<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Document Processing Failure Details</h1>
            <a href="<?php echo e(route('admin.manual-intervention.index')); ?>" class="btn btn-secondary">
                &larr; Back to List
            </a>
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

    <!-- Document Information -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Document Information</h5>
        </div>
        <div class="card-body">
            <table class="table table-borderless">
                <tr>
                    <th style="width: 200px;">Document ID</th>
                    <td><code><?php echo e($log->document_id); ?></code></td>
                </tr>
                <tr>
                    <th>Company</th>
                    <td><?php echo e($log->company->name ?? 'N/A'); ?> (ID: <?php echo e($log->company_id); ?>)</td>
                </tr>
                <tr>
                    <th>Supplier ID</th>
                    <td><span class="badge bg-secondary"><?php echo e($log->supplier_id); ?></span></td>
                </tr>
                <tr>
                    <th>Document Type</th>
                    <td><span class="badge bg-info"><?php echo e(strtoupper($log->document_type)); ?></span></td>
                </tr>
                <tr>
                    <th>File Path</th>
                    <td><code><?php echo e($log->file_path); ?></code></td>
                </tr>
                <tr>
                    <th>File Size</th>
                    <td><?php echo e($log->file_size_bytes ? number_format($log->file_size_bytes) . ' bytes' : 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>File Hash</th>
                    <td>
                        <?php if($log->file_hash): ?>
                            <code style="font-size: 0.85em;"><?php echo e($log->file_hash); ?></code>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <span class="badge bg-danger"><?php echo e(strtoupper($log->status)); ?></span>
                    </td>
                </tr>
                <tr>
                    <th>Created At</th>
                    <td>
                        <?php echo e($log->created_at->format('Y-m-d H:i:s')); ?>

                        <span class="text-muted">(<?php echo e($log->created_at->diffForHumans()); ?>)</span>
                    </td>
                </tr>
                <tr>
                    <th>Callback Received</th>
                    <td>
                        <?php if($log->callback_received_at): ?>
                            <?php echo e($log->callback_received_at->format('Y-m-d H:i:s')); ?>

                            <span class="text-muted">(<?php echo e($log->callback_received_at->diffForHumans()); ?>)</span>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Processing Duration</th>
                    <td>
                        <?php if($log->processing_duration_ms): ?>
                            <?php echo e(number_format($log->processing_duration_ms)); ?> ms
                            (<?php echo e(number_format($log->processing_duration_ms / 1000, 2)); ?>s)
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Error Details -->
    <div class="card mb-3">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Error Details</h5>
        </div>
        <div class="card-body">
            <table class="table table-borderless">
                <tr>
                    <th style="width: 200px;">Error Code</th>
                    <td><code class="text-danger fs-5"><?php echo e($log->error_code); ?></code></td>
                </tr>
                <tr>
                    <th>Error Message</th>
                    <td>
                        <div class="alert alert-danger mb-0">
                            <?php echo e($log->error_message); ?>

                        </div>
                    </td>
                </tr>
                <tr>
                    <th>N8n Execution ID</th>
                    <td>
                        <?php if($log->n8n_execution_id): ?>
                            <code><?php echo e($log->n8n_execution_id); ?></code>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>N8n Workflow ID</th>
                    <td>
                        <?php if($log->n8n_workflow_id): ?>
                            <code><?php echo e($log->n8n_workflow_id); ?></code>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php if($log->error_context): ?>
                <hr>
                <h6>Error Context (JSON):</h6>
                <pre class="bg-dark text-light p-3 rounded"><code><?php echo e(json_encode($log->error_context, JSON_PRETTY_PRINT)); ?></code></pre>
            <?php endif; ?>

            <?php if($log->hmac_signature): ?>
                <hr>
                <h6>HMAC Signature:</h6>
                <code style="font-size: 0.85em;"><?php echo e($log->hmac_signature); ?></code>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actions -->
    <div class="card mb-3">
        <div class="card-header bg-warning">
            <h5 class="mb-0">Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <h6>Retry Processing</h6>
                    <p class="text-muted">Re-queue this document to N8n for another processing attempt</p>
                    <form method="POST" action="<?php echo e(route('admin.manual-intervention.retry', $log)); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-warning"
                                onclick="return confirm('Re-queue this document to N8n?')">
                            <i class="bi bi-arrow-clockwise"></i> Retry Processing
                        </button>
                    </form>
                </div>

                <div class="col-md-6 mb-3">
                    <h6>Mark as Resolved</h6>
                    <p class="text-muted">Mark this document as manually resolved without reprocessing</p>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#resolveModal">
                        <i class="bi bi-check-circle"></i> Mark Resolved
                    </button>
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="col-md-12">
                    <h6>Escalate to Engineering</h6>
                    <p class="text-muted">Escalate this issue to the engineering team for investigation</p>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#escalateModal">
                        <i class="bi bi-exclamation-triangle"></i> Escalate Issue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <a href="<?php echo e(route('admin.manual-intervention.index')); ?>" class="btn btn-secondary">
        &larr; Back to List
    </a>
</div>

<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo e(route('admin.manual-intervention.resolve', $log)); ?>">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">Mark Document as Resolved</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Resolution Notes (Optional)</label>
                        <textarea name="resolution_notes" class="form-control" rows="4"
                                  placeholder="Describe how this issue was resolved..."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <strong>Note:</strong> This will mark the document as completed without reprocessing.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark Resolved</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Escalate Modal -->
<div class="modal fade" id="escalateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo e(route('admin.manual-intervention.escalate', $log)); ?>">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">Escalate to Engineering</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Escalation Notes <span class="text-danger">*</span></label>
                        <textarea name="escalation_notes" class="form-control" rows="4" required
                                  placeholder="Describe why this issue needs engineering attention..."></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will notify the engineering team via logs (Slack integration coming in Phase 3).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Escalate Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/manual-intervention/show.blade.php ENDPATH**/ ?>