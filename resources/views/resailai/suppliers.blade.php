<x-app-layout>
    <div x-data="{ toggleLoading: {}, toggleModal: false }">
        <div class="flex justify-between items-center my-4">
            <div class="flex items-center gap-5">
                <h2 class="text-3xl font-bold">ResailAI Supplier Settings</h2>
                <div data-tooltip="Number of suppliers with ResailAI configured"
                    class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                    <span class="text-xl font-bold text-white">{{ $suppliers->count() }}</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="location.reload()" data-tooltip-left="Reload"
                    class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="currentColor"
                            d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                        <path fill="currentColor"
                            d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                            opacity=".5" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="panel bg-white rounded-lg shadow p-4 mb-4">
            <div class="text-sm text-gray-600 mb-4">
                Enable or disable automatic PDF document processing via ResailAI for each supplier.
                When enabled, PDF files uploaded for this supplier will be automatically sent to ResailAI for extraction.
            </div>

            <div class="dataTable-wrapper dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-center text-md font-bold text-gray-500">
                            <th>Supplier</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Auto-Process PDF</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppliers as $supplier)
                        <tr class="text-sm font-semibold text-gray-600 text-center">
                            <td>
                                <div class="font-bold text-gray-900">{{ $supplier['supplier_name'] }}</div>
                                <div class="text-xs text-gray-500">ID: {{ $supplier['supplier_id'] }}</div>
                            </td>
                            <td>
                                @if($supplier['company_id'])
                                    <span class="text-gray-700">Company: {{ $supplier['company_id'] }}</span>
                                @else
                                    <span class="text-gray-400">All Companies</span>
                                @endif
                            </td>
                            <td>
                                @if($supplier['is_active'])
                                    <span class="inline-flex px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox"
                                        class="sr-only peer"
                                        x-bind:checked="toggleLoading['{{ $supplier['supplier_id'] }}'] ? false : {{ $supplier['auto_process_pdf'] ? 'true' : 'false' }}"
                                        @change="toggleAutoProcess({{ $supplier['supplier_id'] }})"
                                        x-bind:disabled="toggleLoading['{{ $supplier['supplier_id'] }}']"
                                    />
                                    <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                        after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"
                                        x-bind:class="toggleLoading['{{ $supplier['supplier_id'] }}'] ? 'bg-gray-400' : ''"></div>
                                </label>
                                <div x-show="toggleLoading['{{ $supplier['supplier_id'] }}']" class="text-xs text-blue-600 mt-1 animate-pulse">
                                    Updating...
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.resailai.api-keys.index') }}"
                                        class="p-2 rounded-lg hover:bg-blue-50 transition-colors"
                                        data-tooltip-left="Manage API Keys">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                            <path fill="currentColor"
                                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2m-1 17.93c-3.95-.49-7-3.85-7-7.93h2c0 3.31 2.69 6 6 6s6-2.69 6-6h2c0 4.08-3.05 7.44-7 7.93m-.78-4.51l-.39-2.13h-2.34l-.39 2.13c-.09.51.38.92.87.92s.96-.41.87-.92m2.64-2.13l.39-2.13h2.34l.39 2.13c.09.51-.38.92-.87.92s-.96-.41-.87-.92m-3.21-3.61l.39-2.13h2.34l.39 2.13c.09.51-.38.92-.87.92s-.96-.41-.87-.92m-2.64-2.13l-.39-2.13H9.78l-.39 2.13c-.09.51.38.92.87.92s.96-.41.87-.92" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-gray-400 py-3">No suppliers configured yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-lg font-bold text-blue-900 mb-2">How It Works</h3>
            <ul class="list-disc list-inside text-sm text-blue-800 space-y-1">
                <li>When enabled, PDF files uploaded for a supplier will be automatically sent to ResailAI</li>
                <li>ResailAI will extract data and send results back via webhook</li>
                <li>Results are processed through the existing TaskWebhook pipeline</li>
                <li>Disabled suppliers will use the traditional file processing method</li>
            </ul>
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('ResailAISuppliers', () => ({
                    toggleLoading: {},

                    async toggleAutoProcess(supplierId) {
                        // Show loading state
                        this.toggleLoading[supplierId] = true;

                        try {
                            const response = await fetch(
                                `/admin/resailai/suppliers/${supplierId}/toggle`,
                                {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        company_id: {{ $companyId ?? 1 }}, // Default to first company for demo
                                    }),
                                }
                            );

                            const data = await response.json();

                            if (data.success) {
                                console.log('Toggle successful:', data);
                                // Reload page to show updated state
                                setTimeout(() => {
                                    this.toggleLoading[supplierId] = false;
                                    window.location.reload();
                                }, 500);
                            } else {
                                alert('Failed to update: ' + (data.error || 'Unknown error'));
                                this.toggleLoading[supplierId] = false;
                            }
                        } catch (error) {
                            console.error('Toggle error:', error);
                            alert('An error occurred while updating the setting');
                            this.toggleLoading[supplierId] = false;
                        }
                    },
                }));
            });
        </script>
    </div>
</x-app-layout>
