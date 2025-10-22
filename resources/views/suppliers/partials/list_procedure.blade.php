<div id="procedure-list" class="my-2 p-2">
    @if($supplierCompany)
        @php
            // Get procedures for this specific supplier-company relationship using the data from controller
            $procedures = $supplierCompany->procedures()->orderBy('created_at', 'desc')->get();
            $activeProcedure = $procedures->where('is_active', true)->first();
            $inactiveProcedures = $procedures->where('is_active', false);
        @endphp
        
        <div class="mb-6 p-4 border border-gray-200 rounded-lg bg-white shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-semibold text-lg text-gray-800">
                    <i class="fas fa-clipboard-list mr-2 text-blue-500"></i>
                    Supplier Procedures
                </h4>
                <span class="text-xs text-gray-500">Total: {{ $procedures->count() }} procedures</span>
            </div>
                
            <!-- Active Procedure -->
            @if($activeProcedure)
                <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-400 rounded-r">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            <h5 class="font-semibold text-green-800">{{ $activeProcedure->name }}</h5>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-medium">
                                <i class="fas fa-dot-circle mr-1"></i>Active
                            </span>
                            <span class="text-xs text-green-600">
                                Updated: {{ $activeProcedure->updated_at->format('M d, Y') }}
                            </span>
                        </div>
                    </div>
                    <div class="text-sm text-gray-700 bg-white p-3 rounded border">
                        {!! $activeProcedure->procedure !!}
                    </div>
                </div>
            @else
                <div class="mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                        <span class="text-yellow-800 font-medium">No active procedure set</span>
                    </div>
                </div>
            @endif
            
            <!-- All Procedures List -->
            @if($procedures->count() > 0)
                <div class="mt-4">
                    <h6 class="font-medium text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-list mr-2"></i>
                        All Procedures ({{ $procedures->count() }})
                    </h6>
                    
                    <div class="space-y-3">
                        @foreach($procedures as $procedure)
                            <div class="p-4 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors {{ $procedure->is_active ? 'bg-green-50 border-green-200' : 'bg-gray-50' }}">
                                <div class="flex justify-between items-start">
                                    <div class="flex-grow">
                                        <div class="flex items-center mb-2">
                                            @if($procedure->is_active)
                                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                            @else
                                                <i class="fas fa-clock text-gray-400 mr-2"></i>
                                            @endif
                                            <h6 class="font-semibold text-gray-800">{{ $procedure->name }}</h6>
                                            @if($procedure->is_active)
                                                <span class="ml-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                                    Active
                                                </span>
                                            @endif
                                        </div>
                                        
                                        <div class="text-sm text-gray-600 mb-2">
                                            {!! Str::limit(strip_tags($procedure->procedure), 200, '...') !!}
                                        </div>
                                        
                                        <div class="text-xs text-gray-500">
                                            Created: {{ $procedure->created_at->format('M d, Y H:i') }}
                                            @if($procedure->updated_at != $procedure->created_at)
                                                | Updated: {{ $procedure->updated_at->format('M d, Y H:i') }}
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2 ml-4">
                                        @if(!$procedure->is_active)
                                            <form method="POST" action="{{ route('supplier-procedures.activate', $procedure->id) }}" style="display: inline;">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" 
                                                        class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-3 py-1 rounded transition-colors"
                                                        onclick="return confirm('This will activate this procedure and deactivate the current active one. Continue?')">
                                                    <i class="fas fa-play mr-1"></i>Activate
                                                </button>
                                            </form>
                                        @endif
                                        
                                        <button type="button" 
                                                onclick="viewProcedure({{ $procedure->id }})" 
                                                class="bg-gray-500 hover:bg-gray-600 text-white text-xs px-3 py-1 rounded transition-colors">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="p-4 bg-gray-50 border-2 border-dashed border-gray-300 rounded text-center">
                    <i class="fas fa-file-alt text-gray-400 text-2xl mb-2"></i>
                    <p class="text-gray-500">No procedures defined yet</p>
                    <p class="text-sm text-gray-400">Use the form above to add the first procedure</p>
                </div>
            @endif
        </div>
    @else
        <div class="p-6 bg-yellow-50 border border-yellow-200 rounded-lg text-center">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-3"></i>
            <h4 class="font-semibold text-yellow-800 mb-2">Supplier Not Active</h4>
            <p class="text-yellow-700 mb-4">This supplier is not activated for your company.</p>
            <p class="text-sm text-yellow-600">
                Please contact an administrator to activate this supplier.
            </p>
        </div>
    @endif
</div>

<script>
function viewProcedure(procedureId) {
    // Create a modal to show full procedure content
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg max-w-4xl max-h-[90vh] overflow-y-auto m-4 p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Procedure Details</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="procedure-content-${procedureId}">
                Loading...
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // You can add AJAX call here to fetch full procedure details
    // For now, we'll just show a placeholder
    setTimeout(() => {
        document.getElementById(`procedure-content-${procedureId}`).innerHTML = 
            '<p>Full procedure content would be loaded here via AJAX...</p>';
    }, 500);
}
</script>