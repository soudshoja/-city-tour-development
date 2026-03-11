@push('styles')
    @vite(['resources/css/system-setting/main.css', 'resources/css/system-setting/hotel.css'])
@endpush
<x-app-layout>
    <nav class="flex items-center space-x-2 rtl:space-x-reverse text-sm mb-4 sm:mb-6 overflow-x-auto">
        <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-700 transition whitespace-nowrap">Dashboard</a>
        <span class="text-gray-400">&gt;</span>
        <span class="text-blue-600 font-medium truncate max-w-[200px] sm:max-w-none">System Settings</span>
    </nav>

    <div class="grid bg-white dark:bg-gray-800 rounded-xl shadow-sm gap-2">
        <div id="system-settings-index" x-data="{ 
            activeTab: '{{ session('system_settings_active_tab', 'email') }}',
            saveTab(tab) {
                this.activeTab = tab;
                fetch('{{ route('system-settings.save-tab') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ tab: tab })
                });
            }
        }" class="flex min-h-[500px]">
            <div class="w-56 border-r border-gray-200 dark:border-gray-700 p-4 flex-shrink-0">
                <nav class="space-y-1">
                    <button
                        @click="saveTab('email')"
                        :class="activeTab === 'email' 
                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600' 
                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        Email
                    </button>

                    <button
                        @click="saveTab('whatsapp')"
                        :class="activeTab === 'whatsapp' 
                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600' 
                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        WhatsApp
                    </button>

                    <button
                        @click="saveTab('hotel')"
                        :class="activeTab === 'hotel' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Hotel
                    </button>
                </nav>
            </div>

            <div class="flex-1 p-6">
                <div x-show="activeTab === 'email'" x-cloak>
                    @include('admin.system-settings.partials.email')
                </div>

                <div x-show="activeTab === 'whatsapp'" x-cloak>
                    @include('admin.system-settings.partials.whatsapp')
                </div>

                <div x-show="activeTab === 'hotel'">
                    @include('admin.system-settings.partials.hotel')
                </div>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</x-app-layout>
