<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Error Timeline - Document <?php echo e(Str::limit($log->document_id, 32)); ?></h1>
            <a href="<?php echo e(route('admin.manual-intervention.show', $log)); ?>" class="btn btn-secondary">
                &larr; Back to Details
            </a>
        </div>
    </div>

    <!-- Document Summary -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Document Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Company:</strong><br>
                    <?php echo e($log->company->name ?? 'N/A'); ?>

                </div>
                <div class="col-md-3">
                    <strong>Supplier:</strong><br>
                    <span class="badge bg-secondary"><?php echo e($log->supplier_id); ?></span>
                </div>
                <div class="col-md-3">
                    <strong>Document Type:</strong><br>
                    <span class="badge bg-info"><?php echo e(strtoupper($log->document_type)); ?></span>
                </div>
                <div class="col-md-3">
                    <strong>Status:</strong><br>
                    <span class="badge bg-danger"><?php echo e(strtoupper($log->status)); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Processing Timeline (<?php echo e(count($timeline)); ?> events)</h5>
        </div>
        <div class="card-body">
            <?php if(empty($timeline)): ?>
                <p class="text-muted">No timeline events found</p>
            <?php else: ?>
                <div class="timeline">
                    <?php $__currentLoopData = $timeline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="timeline-item mb-4">
                            <div class="row">
                                <div class="col-md-2 text-end">
                                    <small class="text-muted">
                                        <?php echo e($event['timestamp']->format('Y-m-d H:i:s')); ?>

                                        <br>
                                        <span class="text-muted">(<?php echo e($event['timestamp']->diffForHumans()); ?>)</span>
                                    </small>
                                </div>
                                <div class="col-md-1 text-center">
                                    <?php if($event['status'] === 'info'): ?>
                                        <span class="badge bg-info">•</span>
                                    <?php elseif($event['status'] === 'success'): ?>
                                        <span class="badge bg-success">•</span>
                                    <?php elseif($event['status'] === 'warning'): ?>
                                        <span class="badge bg-warning">•</span>
                                    <?php elseif($event['status'] === 'danger'): ?>
                                        <span class="badge bg-danger">•</span>
                                    <?php endif; ?>
                                    <div class="timeline-line"></div>
                                </div>
                                <div class="col-md-9">
                                    <div class="card border-<?php echo e($event['status']); ?>">
                                        <div class="card-body py-2">
                                            <h6 class="mb-1"><?php echo e($event['event']); ?></h6>
                                            <p class="mb-0 small text-muted"><?php echo e($event['details']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-3">
        <a href="<?php echo e(route('admin.manual-intervention.show', $log)); ?>" class="btn btn-secondary">
            &larr; Back to Details
        </a>
        <a href="<?php echo e(route('admin.manual-intervention.index')); ?>" class="btn btn-secondary">
            Back to List
        </a>
    </div>
</div>

<style>
.timeline {
    position: relative;
}

.timeline-item {
    position: relative;
}

.timeline-line {
    width: 2px;
    height: 60px;
    background-color: #dee2e6;
    margin: 5px auto 0;
}

.timeline-item:last-child .timeline-line {
    display: none;
}
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/manual-intervention/timeline.blade.php ENDPATH**/ ?>