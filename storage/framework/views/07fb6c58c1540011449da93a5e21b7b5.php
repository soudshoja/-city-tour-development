<div x-data="chargesTab()" x-init="init()">
    <div x-show="chargeLoading" class="flex justify-center items-center py-12">
        <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="ml-2 text-gray-600">Loading charges...</span>
    </div>

    <div x-show="!chargeLoading" x-cloak>
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-3">
                <h3 class="text-lg font-semibold text-gray-800">Payment Gateways</h3>
                <span x-show="charges.length > 0" class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-blue-600 rounded-full" x-text="charges.length"></span>
            </div>
            <button @click="showCreateModal = true" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Gateway
            </button>
        </div>

        <!-- Charges Table -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Gateway Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Paid By</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Self Charge</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Charge Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <template x-if="charges.length === 0">
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                <p class="mt-2">No payment gateways configured</p>
                                <button @click="showCreateModal = true" class="mt-3 text-blue-600 hover:text-blue-800 text-sm font-medium">Add your first gateway</button>
                            </td>
                        </tr>
                    </template>
                    <template x-for="charge in charges" :key="charge.id">
                        <tbody>
                            <!-- Parent Charge Row -->
                            <tr class="hover:bg-gray-50 cursor-pointer bg-gray-100" @click="toggleExpand(charge.id)">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <svg :class="{'rotate-90': expandedCharge === charge.id}" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                        <span class="font-medium text-gray-900" x-text="charge.name"></span>
                                        <span x-show="charge.is_system_default" class="inline-flex items-center px-2 py-0.5 text-xs font-semibold bg-purple-100 text-purple-800 rounded-full">
                                            System
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600" x-text="charge.paid_by"></td>
                                <td class="px-4 py-3 text-sm text-gray-600" x-text="parseFloat(charge.self_charge || 0).toFixed(2)"></td>
                                <td class="px-4 py-3 text-sm text-gray-600" x-text="charge.charge_type"></td>
                                <td class="px-4 py-3">
                                    <span :class="charge.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="inline-flex px-2 py-1 text-xs font-medium rounded-full" x-text="charge.is_active ? 'Active' : 'Inactive'"></span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click.stop="openEditCredsModal(charge)" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="API Settings">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <!-- Child Methods Rows -->
                            <template x-if="expandedCharge === charge.id && charge.methods && charge.methods.length > 0">
                                <template x-for="method in charge.methods" :key="method.id">
                                    <tr class="bg-white hover:bg-gray-50">
                                        <td class="px-4 py-3 pl-10 text-sm text-gray-600" x-text="method.english_name"></td>
                                        <td class="px-4 py-3 text-sm text-gray-600" x-text="method.paid_by"></td>
                                        <td class="px-4 py-3 text-sm text-gray-600" x-text="parseFloat(method.self_charge || 0).toFixed(2)"></td>
                                        <td class="px-4 py-3 text-sm text-gray-600" x-text="method.charge_type"></td>
                                        <td class="px-4 py-3">
                                            <span :class="(charge.is_active && method.is_active) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="inline-flex px-2 py-1 text-xs font-medium rounded-full" x-text="(charge.is_active && method.is_active) ? 'Active' : 'Inactive'"></span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button @click.stop="openEditMethodModal(method, charge)" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </template>
                            <!-- No methods message -->
                            <template x-if="expandedCharge === charge.id && (!charge.methods || charge.methods.length === 0)">
                                <tr class="bg-white">
                                    <td colspan="6" class="px-4 py-3 pl-10 text-sm text-gray-400 italic">
                                        No payment methods for this gateway
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </template>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Charge Modal -->
    <div x-cloak x-show="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-30 backdrop-blur-sm">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-xl max-h-[85vh] flex flex-col" @click.away="showCreateModal = false">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-800">Create New Gateway</h2>
                <button @click="showCreateModal = false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 px-6 py-4">
                <form method="POST" action="<?php echo e(route('charges.store')); ?>">
                    <?php echo csrf_field(); ?>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Name</label>
                            <input type="text" name="name" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter gateway name" required>
                        </div>

                        <input type="hidden" name="type" value="Payment Gateway">

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                                <input type="number" name="amount" step="0.01" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="0.00" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Self Charge</label>
                                <input type="number" name="self_charge" step="0.01" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Optional">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                                <select name="paid_by" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                    <option value="">Select...</option>
                                    <option value="Company">Company</option>
                                    <option value="Client">Client</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Charge Type</label>
                                <select name="charge_type" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                    <option value="">Select...</option>
                                    <option value="Flat Rate">Flat Rate</option>
                                    <option value="Percent">Percent</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" name="description" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Optional description">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">API Key <span class="text-gray-400 font-normal">(Optional)</span></label>
                            <textarea name="api_key" rows="3" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Paste your secret key (optional for custom gateways)"></textarea>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Active</p>
                                    <p class="text-xs text-gray-400">Enable this gateway</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" checked>
                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-200 pt-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Can Charge Invoice</p>
                                    <p class="text-xs text-gray-400">Allow charging invoices with this gateway</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="can_charge_invoice" value="1" class="sr-only peer" checked>
                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            <?php if(auth()->user()->role_id === \App\Models\Role::ADMIN && auth()->user()->hasRole('admin')): ?>
                            <div class="flex items-center justify-between border-t border-gray-200 pt-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Can Generate Link</p>
                                    <p class="text-xs text-gray-400">Allow payment link generation</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="can_generate_link" value="1" class="sr-only peer">
                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="can_generate_link" value="0">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6">
                        <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">Create Gateway</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Credentials Modal -->
    <div x-cloak x-show="showEditCredsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-30 backdrop-blur-sm">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-xl max-h-[85vh] flex flex-col" @click.away="showEditCredsModal = false">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Gateway API Settings</h2>
                    <p class="text-sm text-gray-500" x-text="editingCharge?.name"></p>
                </div>
                <button @click="showEditCredsModal = false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 px-6 py-4">
                <form :action="'<?php echo e(url('charges')); ?>/' + editingCharge?.id + '/credentials'" method="POST">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>

                    <div class="space-y-4">
                        <div x-show="editingCharge?.is_system_default">
                            <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                            <textarea name="api_key" rows="4" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter new API key to replace existing" x-model="editingCharge.api_key"></textarea>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Active</p>
                                    <p class="text-xs text-gray-400">Enable or disable this gateway</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" :checked="editingCharge?.is_active">
                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            <div class="flex items-center justify-between border-t border-gray-200 pt-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Can Charge Invoice</p>
                                    <p class="text-xs text-gray-400">Allow charging invoices with this gateway</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="can_charge_invoice" value="1" class="sr-only peer" :checked="editingCharge?.can_charge_invoice">
                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            <?php if(auth()->user()->role_id === \App\Models\Role::ADMIN && auth()->user()->hasRole('admin')): ?>
                            <div class="flex items-center justify-between border-t border-gray-200 pt-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Can Generate Link</p>
                                    <p class="text-xs text-gray-400">Allow payment link generation</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="can_generate_link" value="1" class="sr-only peer" :checked="editingCharge?.can_generate_link">
                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6">
                        <button type="button" @click="showEditCredsModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Method Modal -->
    <div x-cloak x-show="showEditMethodModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-30 backdrop-blur-sm">
        <div class="bg-white rounded-xl w-full max-w-lg shadow-xl max-h-[85vh] flex flex-col" @click.away="showEditMethodModal = false">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Edit Payment Method</h2>
                    <p class="text-sm text-gray-500" x-text="editingMethod?.english_name"></p>
                </div>
                <button @click="showEditMethodModal = false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 px-6 py-4">
                <form :action="'<?php echo e(url('payment-method')); ?>/' + editingMethod?.id" method="POST">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Arabic Name</label>
                                <input type="text" :value="editingMethod?.arabic_name" class="w-full border border-gray-200 bg-gray-50 px-3 py-2 rounded-lg text-sm text-gray-600" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">English Name</label>
                                <input type="text" :value="editingMethod?.english_name" class="w-full border border-gray-200 bg-gray-50 px-3 py-2 rounded-lg text-sm text-gray-600" readonly>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Self Charge</label>
                                <input type="number" name="self_charge" step="0.01" :value="editingMethod?.self_charge" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Service Charge</label>
                                <input type="number" name="service_charge" step="0.01" :value="editingMethod?.service_charge" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                                <select name="paid_by" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" x-model="editingMethod.paid_by">
                                    <option value="Company">Company</option>
                                    <option value="Client">Client</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Charge Type</label>
                                <select name="charge_type" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" x-model="editingMethod.charge_type">
                                    <option value="Flat Rate">Flat Rate</option>
                                    <option value="Percent">Percent</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" name="description" :value="editingMethod?.description" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Optional description">
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4" x-show="editingMethodCharge?.is_active">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Active</p>
                                    <p class="text-xs text-gray-400">Enable this payment method</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" :checked="editingMethod?.is_active">
                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6">
                        <button type="button" @click="showEditMethodModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function chargesTab() {
        return {
            charges: [],
            chargeLoading: false,
            expandedCharge: null,
            showCreateModal: false,
            showEditCredsModal: false,
            showEditMethodModal: false,
            companyId: "<?php echo e($companyId); ?>",
            editingCharge: {
                api_key: '',
                secret_key: '',
                name: ''
            },
            editingMethod: {
                paid_by: 'Client',
                charge_type: 'Percent',
                self_charge: 0,
                is_active: true
            },
            editingMethodCharge: {
                name: ''
            },

            init() {
                window.addEventListener('charges-tab-loaded', () => {
                    this.loadCharges();
                });
            },

            async loadCharges() {
                if (this.charges.length > 0) return;

                this.chargeLoading = true;

                let url = '<?php echo e(route("settings.charges")); ?>';
                if (this.companyId) {
                    url += '?company_id=' + this.companyId;
                }

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.charges = data.charges;
                    }
                } catch (error) {
                    console.error('Error loading charges:', error);
                } finally {
                    this.chargeLoading = false;
                }
            },

            toggleExpand(chargeId) {
                this.expandedCharge = this.expandedCharge === chargeId ? null : chargeId;
            },

            openEditCredsModal(charge) {
                this.editingCharge = {
                    ...charge
                };
                this.showEditCredsModal = true;
            },

            openEditMethodModal(method, charge) {
                this.editingMethod = {
                    ...method
                };
                this.editingMethodCharge = charge;
                this.showEditMethodModal = true;
            }
        }
    }
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/settings/partial/charges.blade.php ENDPATH**/ ?>