<x-app-layout>
    <div x-data="{ createModal: false }">
        <div class="flex justify-between items-center my-4">
            <div class="flex items-center gap-5">
                <h2 class="text-3xl font-bold">ResailAI API Keys</h2>
                <div data-tooltip="Number of API keys"
                    class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                    <span class="text-xl font-bold text-white">{{ $credentials->count() }}</span>
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
                <button @click="createModal = true"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm cursor-pointer"
                    data-tooltip-left="Generate new API key">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7">
                        </path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="panel bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-600 mb-4">
                API keys are used to authenticate callbacks from ResailAI n8n webhook.
                Keys are encrypted at rest and displayed only once upon creation.
            </div>

            <div class="dataTable-wrapper dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-center text-md font-bold text-gray-500">
                            <th>Name</th>
                            <th>API Key</th>
                            <th>Status</th>
                            <th>Expires</th>
                            <th>Last Used</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($credentials as $credential)
                        <tr class="text-sm font-semibold text-gray-600 text-center">
                            <td>{{ $credential['name'] }}</td>
                            <td class="font-mono text-xs">{{ $credential['api_key'] }}</td>
                            <td>
                                @if($credential['is_active'])
                                    <span class="inline-flex px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($credential['expires_at'])
                                    <span class="text-gray-600">{{ \Carbon\Carbon::parse($credential['expires_at'])->format('Y-m-d') }}</span>
                                @else
                                    <span class="text-gray-400">Never</span>
                                @endif
                            </td>
                            <td>
                                @if($credential['last_used_at'])
                                    <span class="text-gray-600">{{ \Carbon\Carbon::parse($credential['last_used_at'])->diffForHumans() }}</span>
                                @else
                                    <span class="text-gray-400">Never</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-center gap-2">
                                    <button type="button" @click="showRevoke({{ $credential['id'] }}, '{{ $credential['name'] }}')"
                                        data-tooltip-left="Revoke API key"
                                        class="p-2 rounded-lg hover:bg-red-50 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                            <path fill="none" stroke="#ef4444" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9.17 4a3.001 3.001 0 0 1 5.66 0m5.67 2h-17m15.333 2.5l-.46 6.9c-.177 2.654-.265 3.981-1.13 4.79c-.865.81-2.195.81-4.856.81h-.774c-2.66 0-3.99 0-4.856-.81c-.865-.809-.953-2.136-1.13-4.79l-.46-6.9M9.5 11l.5 5m4.5-5l-.5 5" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-gray-400 py-3">No API keys configured yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Modal -->
        <template x-teleport="body">
            <div x-cloak x-show="createModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-40 backdrop-blur-sm transition">
                <form action="{{ route('admin.resailai.api-keys.generate') }}" method="POST"
                    class="inline-flex flex-col gap-4 items-center w-full">
                    @csrf
                    <div @click.away="createModal=false"
                        class="w-full sm:max-w-screen-sm mx-4 bg-white rounded-md border p-6 relative overflow-y-auto max-h-[90vh]">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Generate API Key</h2>
                                <p class="text-gray-600 italic text-xs mt-1">
                                    Create a new API key for ResailAI webhook authentication.
                                    <br>
                                    <span class="text-red-600 font-semibold">Important:</span> The API key will be displayed once. Copy it immediately.
                                </p>
                            </div>
                            <button type="button" @click="createModal=false"
                                    class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                &times;
                            </button>
                        </div>
                        <div class="flex flex-col gap-6">
                            <div class="flex flex-col sm:flex-row gap-4">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="name" required
                                        class="border border-gray-300 p-2 rounded-md w-full text-base"
                                        placeholder="e.g., Production Webhook" />
                                    <p class="text-xs text-gray-500 mt-1">Identify this key for future reference</p>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Expires In (Days)
                                    </label>
                                    <input type="number" name="expires_in_days" min="1" max="365"
                                        class="border border-gray-300 p-2 rounded-md w-full text-base"
                                        placeholder="365" />
                                    <p class="text-xs text-gray-500 mt-1">Leave empty for no expiry</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex flex-col sm:flex-row justify-between gap-4">
                            <button type="button" @click="createModal=false"
                                class="px-6 py-2 text-gray-700 font-semibold rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                Cancel
                            </button>
                            <button type="submit"
                                class="w-full sm:w-auto px-6 py-2 text-white font-semibold rounded-full bg-blue-600 hover:bg-blue-700 transition">
                                Generate Key
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </template>

        <!-- Revoke Confirmation Modal -->
        <template x-teleport="body">
            <div x-show="revokeModal" x-cloak x-transition.opacity
                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-40 backdrop-blur-sm">
                <div x-transition.scale @click.away="revokeModal=false" class="bg-white rounded-xl shadow-xl px-8 py-8 w-full max-w-sm text-center">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Revoke API Key?</h2>
                    <div class="border-t border-gray-200 mb-6"></div>
                    <p class="text-base font-medium text-gray-600 leading-snug mb-6">
                        Are you sure you want to revoke <span class="font-bold text-gray-900" x-text="credentialName"></span>?
                        <br>
                        <span class="block text-sm text-gray-500 leading-snug mt-1">
                            This will immediately disable webhook authentication for this key.
                            Any services using this key will receive 401 errors.
                        </span>
                    </p>
                    <div class="border-t border-gray-200 mb-6"></div>
                    <div class="flex justify-center gap-4">
                        <button @click="revokeModal=false" class="px-6 py-2.5 rounded-full font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 transition">
                            Cancel
                        </button>
                        <button @click="confirmRevoke" class="px-6 py-2.5 rounded-full font-medium text-white bg-red-600 hover:bg-red-700 transition">
                            Yes, Revoke
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <script>
            document.addEventListener('alpine:init', () => {
                document.addEventListener('click', (e) => {
                    if (e.target.closest('[x-show="createModal"]')) {
                        console.log('Create modal opened');
                    }
                });
            });

            document.addEventListener('alpine:init', () => {
                Alpine.data('ResailAIAdmin', () => ({
                    revokeModal: false,
                    credentialId: null,
                    credentialName: '',

                    showRevoke(id, name) {
                        this.credentialId = id;
                        this.credentialName = name;
                        this.revokeModal = true;
                    },

                    async confirmRevoke() {
                        try {
                            const response = await fetch(
                                `/admin/resailai/api-keys/${this.credentialId}`,
                                {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                        'Content-Type': 'application/json',
                                    },
                                }
                            );

                            const data = await response.json();

                            if (data.success) {
                                alert('API key revoked successfully');
                                window.location.reload();
                            } else {
                                alert('Failed to revoke API key: ' + (data.error || 'Unknown error'));
                            }
                        } catch (error) {
                            console.error('Revoke error:', error);
                            alert('An error occurred while revoking the API key');
                        }
                    },
                }));
            });
        </script>
    </div>
</x-app-layout>
