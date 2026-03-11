<div id="procedure-list" class="my-2 p-2">
    <?php if($supplierCompany): ?>
        <?php
            // Get procedures for this specific supplier-company relationship using the data from controller
            $procedures = $supplierCompany->procedures()->orderBy('created_at', 'desc')->get();
            $activeProcedure = $procedures->where('is_active', true)->first();
            $inactiveProcedures = $procedures->where('is_active', false);
        ?>
        
        <div class="mb-6 p-4 border border-gray-200 rounded-lg bg-white shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-semibold text-lg text-gray-800">
                    <i class="fas fa-clipboard-list mr-2 text-blue-500"></i>
                    Supplier Procedures
                </h4>
                <span class="text-xs text-gray-500">Total: <?php echo e($procedures->count()); ?> procedures</span>
            </div>
                
            <!-- Active Procedure -->
            <?php if($activeProcedure): ?>
                <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-400 rounded-r">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <h5 class="font-semibold text-green-800"><?php echo e($activeProcedure->name); ?></h5>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-medium">
                                <i class="fas fa-dot-circle mr-1"></i>Active
                            </span>
                            <span class="text-xs text-green-600">
                                Updated: <?php echo e($activeProcedure->updated_at->format('M d, Y')); ?>

                            </span>
                        </div>
                    </div>
                    <div class="text-sm text-gray-700 bg-white p-3 rounded border">
                        <?php echo $activeProcedure->procedure; ?>

                    </div>
                </div>
            <?php else: ?>
                <div class="mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                        <span class="text-yellow-800 font-medium">No active procedure set</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- All Procedures List -->
            <?php if($procedures->count() > 0): ?>
                <div class="mt-4">
                    <h6 class="font-medium text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        All Procedures (<?php echo e($procedures->count()); ?>)
                    </h6>
                    
                    <div class="space-y-3">
                        <?php $__currentLoopData = $procedures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $procedure): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="p-4 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors <?php echo e($procedure->is_active ? 'bg-green-50 border-green-200' : 'bg-gray-50'); ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex-grow">
                                        <div class="flex items-center mb-2">
                                            <?php if($procedure->is_active): ?>
                                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                            <?php else: ?>
                                                <i class="fas fa-clock text-gray-400 mr-2"></i>
                                            <?php endif; ?>
                                            <h6 class="font-semibold text-gray-800"><?php echo e($procedure->name); ?></h6>
                                            <?php if($procedure->is_active): ?>
                                                <span class="ml-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                                    Active
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="text-sm text-gray-600 mb-2">
                                            <?php echo Str::limit(strip_tags($procedure->procedure), 200, '...'); ?>

                                        </div>
                                        
                                        <div class="text-xs text-gray-500">
                                            Created: <?php echo e($procedure->created_at->format('M d, Y H:i')); ?>

                                            <?php if($procedure->updated_at != $procedure->created_at): ?>
                                                | Updated: <?php echo e($procedure->updated_at->format('M d, Y H:i')); ?>

                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2 ml-4">
                                        <?php if(!$procedure->is_active): ?>
                                            <form method="POST" action="<?php echo e(route('supplier-procedures.activate', $procedure->id)); ?>" style="display: inline;">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('PATCH'); ?>
                                                <button type="submit" 
                                                        class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-3 py-1 rounded transition-colors"
                                                        onclick="return confirm('This will activate this procedure and deactivate the current active one. Continue?')">
                                                    <i class="fas fa-play mr-1"></i>Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button type="button" 
                                                onclick="viewProcedure(<?php echo e($procedure->id); ?>)" 
                                                class="bg-gray-500 hover:bg-gray-600 text-white text-xs px-3 py-1 rounded transition-colors">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                        
                                        <form method="POST" action="<?php echo e(route('supplier-procedures.destroy', $procedure->id)); ?>" style="display: inline;">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button type="submit" 
                                                    class="bg-red-500 hover:bg-red-600 text-white text-xs px-3 py-1 rounded transition-colors"
                                                    onclick="return confirm('Are you sure you want to delete this procedure? This action cannot be undone.')">
                                                <i class="fas fa-trash mr-1"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-4 bg-gray-50 border-2 border-dashed border-gray-300 rounded text-center">
                    <i class="fas fa-file-alt text-gray-400 text-2xl mb-2"></i>
                    <p class="text-gray-500">No procedures defined yet</p>
                    <p class="text-sm text-gray-400">Use the form above to add the first procedure</p>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="p-6 bg-yellow-50 border border-yellow-200 rounded-lg text-center">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-3"></i>
            <h4 class="font-semibold text-yellow-800 mb-2">Supplier Not Active</h4>
            <p class="text-yellow-700 mb-4">This supplier is not activated for your company.</p>
            <p class="text-sm text-yellow-600">
                Please contact an administrator to activate this supplier.
            </p>
        </div>
    <?php endif; ?>
</div>

<script>
function viewProcedure(procedureId) {
    // Create a modal to show full procedure content
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 p-4';
    modal.id = 'procedure-modal';
    modal.innerHTML = `
        <div class="bg-white rounded-lg w-full max-w-3xl min-h-[33vh] max-h-[90vh] overflow-hidden shadow-2xl" style="min-width: 30rem;">
            <div class="flex justify-between items-center p-6 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-file-alt mr-2 text-blue-500"></i>
                    Procedure Details
                </h3>
                <button onclick="closeProcedureModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="overflow-y-auto" style="max-height: calc(90vh - 80px); min-height: calc(33vh - 80px);">
                <div id="procedure-content-${procedureId}" class="p-6">
                    <!-- Skeleton Loader -->
                    <div class="animate-pulse space-y-6">
                        <!-- Header Info Skeleton -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="h-6 bg-gray-300 rounded w-2/3 mb-3"></div>
                                    <div class="h-6 bg-gray-300 rounded w-20"></div>
                                </div>
                                <div class="space-y-2">
                                    <div class="h-4 bg-gray-300 rounded w-full"></div>
                                    <div class="h-4 bg-gray-300 rounded w-3/4"></div>
                                    <div class="h-4 bg-gray-300 rounded w-5/6"></div>
                                    <div class="h-4 bg-gray-300 rounded w-4/5"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Content Skeleton -->
                        <div>
                            <div class="h-6 bg-gray-300 rounded w-1/3 mb-3"></div>
                            <div class="bg-white border rounded-lg p-4 space-y-3">
                                <div class="h-4 bg-gray-200 rounded w-full"></div>
                                <div class="h-4 bg-gray-200 rounded w-11/12"></div>
                                <div class="h-4 bg-gray-200 rounded w-full"></div>
                                <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                                <div class="h-4 bg-gray-200 rounded w-full"></div>
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-4 bg-gray-200 rounded w-full"></div>
                                <div class="h-4 bg-gray-200 rounded w-4/5"></div>
                                <div class="h-4 bg-gray-200 rounded w-full"></div>
                                <div class="h-4 bg-gray-200 rounded w-2/3"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add escape key listener
    document.addEventListener('keydown', handleEscapeKey);
    
    // Fetch procedure data via AJAX
    fetch(`<?php echo e(url('supplier-procedures')); ?>/${procedureId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const procedure = data.data;
                document.getElementById(`procedure-content-${procedureId}`).innerHTML = `
                    <div class="space-y-6">
                        <!-- Header Info -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2 flex items-center">
                                        <i class="fas fa-tag mr-2 text-blue-500"></i>
                                        ${procedure.name}
                                    </h4>
                                    <div class="flex items-center space-x-2">
                                        ${procedure.is_active 
                                            ? '<span class="bg-green-100 text-green-800 text-xs px-3 py-1 rounded-full font-medium"><i class="fas fa-check-circle mr-1"></i>Active</span>'
                                            : '<span class="bg-gray-100 text-gray-600 text-xs px-3 py-1 rounded-full"><i class="fas fa-clock mr-1"></i>Inactive</span>'
                                        }
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <div class="mb-1">
                                        <i class="fas fa-building mr-2"></i>
                                        <strong>Company:</strong> ${procedure.company_name}
                                    </div>
                                    <div class="mb-1">
                                        <i class="fas fa-truck mr-2"></i>
                                        <strong>Supplier:</strong> ${procedure.supplier_name}
                                    </div>
                                    <div class="mb-1">
                                        <i class="fas fa-calendar-plus mr-2"></i>
                                        <strong>Created:</strong> ${procedure.created_at}
                                    </div>
                                    <div>
                                        <i class="fas fa-calendar-edit mr-2"></i>
                                        <strong>Updated:</strong> ${procedure.updated_at}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Procedure Content -->
                        <div>
                            <h5 class="font-semibold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-file-text mr-2 text-green-500"></i>
                                Procedure Content
                            </h5>
                            <div class="bg-white border rounded-lg p-4 prose max-w-none">
                                ${procedure.procedure}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById(`procedure-content-${procedureId}`).innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-3"></i>
                        <p class="text-red-600 font-medium">Failed to load procedure</p>
                        <p class="text-gray-600 text-sm">${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById(`procedure-content-${procedureId}`).innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-3"></i>
                    <p class="text-red-600 font-medium">Error loading procedure</p>
                    <p class="text-gray-600 text-sm">Please try again later.</p>
                </div>
            `;
        });
}

function closeProcedureModal() {
    const modal = document.getElementById('procedure-modal');
    if (modal) {
        modal.remove();
        document.removeEventListener('keydown', handleEscapeKey);
    }
}

function handleEscapeKey(event) {
    if (event.key === 'Escape') {
        closeProcedureModal();
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('procedure-modal');
    if (modal && event.target === modal) {
        closeProcedureModal();
    }
});
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/suppliers/partials/list_procedure.blade.php ENDPATH**/ ?>