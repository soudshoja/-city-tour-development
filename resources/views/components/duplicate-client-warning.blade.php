@if(session('duplicate_warning'))
<div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-30" 
     x-data="{ showModal: true }" 
     x-show="showModal"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-30"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-30"
     x-transition:leave-end="opacity-0">
    
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95">
        
        <!-- Header -->
        <div class="flex items-center mb-6">
            <div class="ml-4 flex-1">
                <h3 class="text-xl font-semibold text-gray-900">🚫 Client Already Exists</h3>
                <p class="text-sm text-gray-600 mt-1">This client is already registered in the system</p>
            </div>
            <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Existing Client Information -->
        <div class="mb-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <h4 class="font-medium text-blue-900 mb-2">Existing Client Details:</h4>
                <div class="text-sm text-blue-800 space-y-1">
                    <p><strong>{{ session('duplicate_data.duplicate_message') }}</strong></p>
                    @if(session('duplicate_data.existing_client'))
                    <p><strong>Name:</strong> {{ session('duplicate_data.existing_client.first_name') }} {{ session('duplicate_data.existing_client.middle_name') }} {{ session('duplicate_data.existing_client.last_name') }}</p>
                    <p><strong>Phone:</strong> {{ session('duplicate_data.existing_client.phone') }}</p>
                    <p><strong>Email:</strong> {{ session('duplicate_data.existing_client.email') ?? 'Not provided' }}</p>
                    @if(session('duplicate_data.existing_client.civil_no'))
                    <p><strong>Civil No:</strong> {{ session('duplicate_data.existing_client.civil_no') }}</p>
                    @endif
                    @endif
                </div>
            </div>
            
            <!-- Current Owner Information -->
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <h4 class="font-medium text-amber-900 mb-3">Current Owner Agent:</h4>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-amber-500 rounded-full flex items-center justify-center">
                            <span class="text-white font-semibold text-lg">
                                {{ substr(session('duplicate_data.owner_agent.name'), 0, 1) }}
                            </span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-semibold text-amber-900">{{ session('duplicate_data.owner_agent.name') }}</p>
                        <p class="text-xs text-amber-700">Owner Agent</p>
                        @if(session('duplicate_data.owner_agent.branch'))
                        <p class="text-xs text-amber-600 mt-1">Branch: {{ session('duplicate_data.owner_agent.branch.name') ?? 'N/A' }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Options -->
        <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-400">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">Recommended Solution</h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p>Instead of creating a duplicate client, you can request to be assigned to this existing client. This will:</p>
                        <ul class="list-disc list-inside mt-2 space-y-1">
                            <li>Send a notification to the owner agent requesting assignment</li>
                            <li>Allow you to work with this client once approved</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Request Assignment Form -->
        <form action="{{ route('clients.request-assignment') }}" method="POST" id="assignment-request-form">
            @csrf
            
            <input type="hidden" name="existing_client_id" value="{{ session('duplicate_data.existing_client.id') }}">
            <input type="hidden" name="owner_agent_id" value="{{ session('duplicate_data.owner_agent.id') }}">
            
            <div class="mb-6">
                <label for="request_reason" class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="text-red-500">*</span> Reason for requesting client assignment:
                </label>
                <textarea 
                    name="request_reason" 
                    minlength="5"
                    id="request_reason" 
                    rows="4" 
                    required
                    class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    placeholder="Please explain why you need access to this client (e.g., 'Client contacted me for new booking', 'Working on related family member booking', 'Client referred by owner agent', etc.)"
                ></textarea>
                <p class="text-xs text-gray-500 mt-1">This message will be sent to the owner agent for approval.</p>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button type="button" @click="showModal = false" 
                    class="px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancel
                </button>
                <button type="submit" 
                    class="px-6 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Request Assignment Access
                </button>
            </div>
        </form>
        
        <!-- Alternative Option -->
        <div class="mt-4 pt-4 border-t border-gray-200">
            <p class="text-xs text-gray-500 text-center">
                If this is genuinely a different client with the same details, please contact your administrator to resolve the conflict.
            </p>
        </div>
    </div>
</div>
@endif
