<x-app-layout>
    <div class="grid grid-cols-1">
        <div class="flex flex-col items-start">
            <h2 class="text-2xl md:text-3xl font-bold">{{ __('profile.profile') }}</h2>
            <nav class="flex items-center space-x-2 rtl:space-x-reverse text-sm mb-4 sm:mb-6 overflow-x-auto">
                <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-700 transition whitespace-nowrap">{{ __('general.dashboard') }}</a>
                <span class="text-gray-400">&gt;</span>
                <span class="text-blue-600 font-medium truncate max-w-[200px] sm:max-w-none">{{ __('profile.users_profile', ['name' => $user->name]) }}</span>
            </nav>
        </div>

        <!-- Profile -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100">
            @php
                $tabs = [
                    ['label' => __('profile.account_information'), 'value' => 'Account', 'icon' => 'user'],
                    ['label' => __('profile.change_password'), 'value' => 'Security', 'icon' => 'shield'],
                    ['label' => __('profile.iata_settings'), 'value' => 'Iata', 'icon' => 'globe'],
                ];

                $typeId = optional($user->agent)->type_id;
                if ($user->role_id == 4 && $typeId) {
                    if (in_array($typeId, [1, 2])) {
                        $tabs[] = ['label' => $typeId == 1 ? __('profile.profit') : __('profile.commission'), 'value' => 'Commission', 'icon' => 'hand-money'];
                    } elseif (in_array($typeId, [3, 4])) {
                        $tabs[] = ['label' => __('profile.commission_profit'), 'value' => 'Commission', 'icon' => 'hand-money'];
                    }
                }

                if($filteredBonuses && $filteredBonuses->isNotEmpty())
                    $tabs[] = ['label' => __('profile.bonus'), 'value' => 'Bonus', 'icon' => 'credit-card'];
            @endphp

            <div class="flex h-[45rem]" x-data="{
                tab: new URLSearchParams(window.location.search).get('tab') || 'Account',
                switchTab(newTab) {
                    this.tab = newTab;
                    const url = new URL(window.location);
                    url.searchParams.set('tab', newTab);
                    url.searchParams.delete('commission');
                    window.history.pushState({}, '', url);
                }
            }" x-cloak>

                <!-- Sidebar -->
                <div class="w-80 border-r border-gray-200 dark:border-gray-700 p-4 flex-shrink-0">
                    <nav class="space-y-1">
                        @foreach ($tabs as $t)
                        <button type="button"
                            @click="switchTab('{{ $t['value'] }}')"
                            :class="tab === '{{ $t['value'] }}'
                                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                            class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                            @switch($t['icon'])
                                @case('user')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                @break
                                @case('shield')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                @break
                                @case('globe')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                                </svg>
                                @break
                                @case('hand-money')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                @break
                                @case('credit-card')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                @break
                            @endswitch
                            <span>{{ $t['label'] }}</span>
                        </button>
                        @endforeach
                    </nav>
                </div>

                <!-- Content Area -->
                <div class="flex-1 p-6 overflow-y-auto dark:text-black">
                    <div x-show="tab === 'Account'">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                    <div x-show="tab === 'Security'">
                        @include('profile.password.update-password-form')
                    </div>
                    <div x-show="tab === 'Iata'">
                        @include('profile.partials.iata-settings-form')
                    </div>
                    <div x-show="tab === 'Commission'">
                        @include('profile.partials.commission-list')
                    </div>
                    @if ($filteredBonuses)
                    <div x-show="tab === 'Bonus'">
                        @include('profile.partials.bonus-list')
                    </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const eyeOpen = btn.querySelector('.eye-open');
            const eyeClosed = btn.querySelector('.eye-closed');

            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>