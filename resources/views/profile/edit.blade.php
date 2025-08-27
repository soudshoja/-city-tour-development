<x-app-layout>
    <div class="grid grid-cols-1">
        <!-- Top bar with user info -->
        <div class="panel h-full overflow-hidden border-0 p-0">
            <div class="bg-white p-4">
                <div class="mb-6 flex items-center">
                    <div class="w-full justify-between flex items-center rounded-full bg-black/25 p-2 font-semibold text-white pr-3"
                        role="banner">
                        <div class="flex items-center">
                          
                            <img src="{{ $companyLogo }}" alt="Company Logo" class="h-10 w-auto pl-4 pr-4">
                    
                            <h3 class="px-2">{{ $user->name }}</h3>
                        </div>
                        <div>
                            <button type="button" aria-label="Reload" data-tooltip="Reload"
                                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                                @click="window.location.reload()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 24 24" fill="currentColor" class="text-gray-700 dark:text-gray-300">
                                    <path
                                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                                    <path
                                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                                        opacity=".5" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @php
            $tabs = [
            ['label' => 'Account Information', 'value' => 'Account', 'icon' => 'user'],
            ['label' => 'Change Password', 'value' => 'Security', 'icon' => 'shield'],
            ];

            $typeId = optional($user->agent)->type_id;
            if ($user->role_id == 4 && $typeId) {
            if (in_array($typeId, [1, 2])) {
            $tabs[] = ['label' => $typeId == 1 ? 'Profit' : 'Commission', 'value' => 'Commission', 'icon' => 'hand-money'];
            } elseif (in_array($typeId, [3, 4])) {
            $tabs[] = ['label' => 'Commission & Profit', 'value' => 'Commission', 'icon' => 'hand-money'];
            }
            }

            $tabs = array_merge($tabs, [
            ['label' => 'Payment', 'value' => 'Payment', 'icon' => 'credit-card'],
            ['label' => 'Invoices', 'value' => 'Invoices', 'icon' => 'file-text'],
            ['label' => 'Orders', 'value' => 'Orders', 'icon' => 'shopping-cart'],
            ['label' => 'Documentation', 'value' => 'Documentation', 'icon' => 'book-open'],
            ]);
            @endphp

            <div class="bg-white p-5 grid grid-cols-6 gap-3" x-data="{
                tab: new URLSearchParams(window.location.search).get('tab') || 'Account',
                switchTab(newTab) {
                    this.tab = newTab;
                    const url = new URL(window.location);
                    url.searchParams.set('tab', newTab);
                    url.searchParams.delete('commission');
                    window.history.pushState({}, '', url);
                }
            }" role="tabpanel"
                @keydown.arrow-right.prevent="switchTab(tabs[(tabs.findIndex(t => t.value === tab) + 1) % tabs.length].value)"
                @keydown.arrow-left.prevent="switchTab(tabs[(tabs.findIndex(t => t.value === tab) - 1 + tabs.length) % tabs.length].value)"
                x-init="$watch('tab', val => { document.activeElement.blur() })" x-cloak>
                <!-- Sidebar Tabs -->
                <div class="rounded-lg p-4 space-y-2" role="tablist" aria-orientation="vertical">
                    @foreach ($tabs as $t)
                    <button type="button" role="tab"
                        :aria-selected="tab === '{{ $t['value'] }}' ? 'true' : 'false'"
                        tabindex="{{ $loop->first ? '0' : '-1' }}" @click="switchTab('{{ $t['value'] }}')"
                        class="flex items-center space-x-3 px-4 py-3 rounded-lg transition-all w-full text-left
                            bg-gray-100 text-gray-700
                            hover:bg-gray-200 hover:text-primary
                            focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1
                            "
                        :class="{ 'text-primary bg-gray-200 font-semibold': tab === '{{ $t['value'] }}' }">
                        @switch($t['icon'])
                        @case('user')
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor"
                                d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4s-4 1.79-4 4s1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                        </svg>
                        @break

                        @case('shield')
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M12 3l9 4.5v5.25c0 4.556-2.727 8.682-6.894 10.374a1.932 1.932 0 01-1.212 0C5.727 21.432 3 17.306 3 12.75V7.5L12 3z" />
                        </svg>
                        @break

                        @case('hand-money')
                        <img class="w-5 h-5" src="https://img.icons8.com/external-smashingstocks-mixed-smashing-stocks/68/external-commission-digital-marketing-smashingstocks-mixed-smashing-stocks.png" />
                        @break

                        @case('credit-card')
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor"
                                d="M21 4H3C1.9 4 1 4.9 1 6v2h22V6c0-1.1-.9-2-2-2zM1 18c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2v-8H1v8z" />
                        </svg>
                        @break

                        @case('file-text')
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor"
                                d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM8 15h8v2H8v-2zm0-4h8v2H8v-2zm5-5.5V9h5.5L13 5.5z" />
                        </svg>
                        @break

                        @case('shopping-cart')
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor"
                                d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2s-.9-2-2-2zm0 2zM1 2v2h2l3.6 7.59l-1.35 2.44C5.16 14.37 6 16 7.5 16H19v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12l.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49a1 1 0 00-.87-1.48H5.21L4.27 2H1z" />
                        </svg>
                        @break

                        @case('book-open')
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor"
                                d="M2 6c0-1.1.9-2 2-2h6v16H4c-1.1 0-2-.9-2-2V6zm18-2c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2h-6V4h6z" />
                        </svg>
                        @break
                        @endswitch
                        <span class="text-sm">{{ $t['label'] }}</span>
                    </button>
                    @endforeach
                </div>

                <!-- Main content -->
                <div class="col-span-5">
                    <div x-show="tab === 'Account'" x-cloak>
                        @include('profile.partials.update-profile-information-form')
                    </div>

                    <div x-show="tab === 'Security'" x-cloak>
                        @include('profile.password.update-password-form')
                    </div>

                    <div x-show="tab === 'Commission'" x-cloak>
                        <p class="text-gray-700 text-sm">@include('profile.partials.commission-list')</p>
                    </div>

                    <div x-show="tab === 'Payment'" x-cloak>
                        <p class="text-gray-700 text-sm">This is the Payment section.</p>
                    </div>

                    <div x-show="tab === 'Invoices'" x-cloak>
                        <p class="text-gray-700 text-sm">This is the Invoices section.</p>
                    </div>

                    <div x-show="tab === 'Orders'" x-cloak>
                        <p class="text-gray-700 text-sm">This is the Orders section.</p>
                    </div>

                    <div x-show="tab === 'Documentation'" x-cloak>
                        <p class="text-gray-700 text-sm">This is the Documentation section.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>