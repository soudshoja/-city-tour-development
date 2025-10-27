<header>
    <div class="relative h-3 w-full -z-10 px-6 py-4 -top-18">
        <p class="text-center text-background">
            CityTourApp
        </p>
    </div>
    <div class="container mx-auto flex flex-wrap justify-center items-center gap-4 px-6 py-4">
        <div class="flex items-center w-full md:w-auto mb-4 md:mb-0 justify-center md:justify-start">
            <a href="{{ route('dashboard') }}" class="flex items-center">
                <x-application-logo class="h-20 w-auto" />
            </a>

            <div class="hidden md:block" id="responsiveMenu">
                @include('layouts.menu')
            </div>

            <div class="block md:invisible" id="menu-icon">
                <i class="fa fa-bars"></i>
            </div>

        </div>

        <!-- Right Section -->
        <div x-data="{ 
            toggle: false,
            chatBox: false
            }"
            class="flex items-center space-x-4 w-full md:w-auto mb-4 md:mb-0 justify-center md:justify-start">
            <!-- Search Icon -->
            <div class="relative w-12 h-12 flex items-center justify-center bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full shadow-sm">
                <svg class="w-6 h-6 text-gray-500 dark:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 15 16">
                    <path fill="currentColor" d="M6.5 13.02a5.5 5.5 0 0 1-3.89-1.61C1.57 10.37 1 8.99 1 7.52s.57-2.85 1.61-3.89c2.14-2.14 5.63-2.14 7.78 0C11.43 4.67 12 6.05 12 7.52s-.57 2.85-1.61 3.89a5.5 5.5 0 0 1-3.89 1.61m0-10c-1.15 0-2.3.44-3.18 1.32C2.47 5.19 2 6.32 2 7.52s.47 2.33 1.32 3.18a4.51 4.51 0 0 0 6.36 0C10.53 9.85 11 8.72 11 7.52s-.47-2.33-1.32-3.18A4.48 4.48 0 0 0 6.5 3.02" />
                    <path fill="currentColor" d="M13.5 15a.47.47 0 0 1-.35-.15l-3.38-3.38c-.2-.2-.2-.51 0-.71s.51-.2.71 0l3.38 3.38c.2.2.2.51 0 .71c-.1.1-.23.15-.35.15Z" />
                </svg>
            </div>

            <!-- chat Icon -->
            <div
                @click="chatBox = true"
                class="relative ">
                <div class="w-12 h-12 flex items-center justify-center bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full shadow-sm cursor-pointer">
                    <span class="absolute top-1 right-1 bg-red-500 w-3 h-3 rounded-full"></span>
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                        <path fill="currentColor" d="M0 262q0 43 24.5 81T90 405q-2 7-4.5 18t-7 34.5t-3.5 39T85 512q30 0 60.5-16t48.5-32t19-16q55 0 107-21q-6-2-22.5-12T277 405h-64q-18 0-38 20q-28 25-53 36l6-77l-17-15q-68-44-68-107q0-16 6-36q-4-6-5.5-18.5T42 185v-23l1-13Q0 195 0 262M299 0q-89 0-151.5 52T85 177q0 72 62 118t152 46q1 0 20.5 21.5t51.5 43t62 21.5q7 0 8.5-11t-1.5-26.5t-7-31.5t-7-27l-4-11q41-25 65.5-62.5T512 177q0-73-62.5-125T299 0m102 284l-28 17l11 32q2 5 5 17t6 19q-22-15-52-45q-23-25-42-25q-70 0-120.5-32.5T130 177q-1-56 48.5-95T299 43t120.5 39t49.5 95q0 63-68 107" />
                    </svg>
                </div>
                <div
                    x-show="chatBox"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform scale-90"
                    x-transition:enter-end="opacity-100 transform scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 transform scale-100"
                    x-transition:leave-end="opacity-0 transform scale-90"
                    @click.away="chatBox = false"
                    class="flex flex-col justify-end absolute bg-white dark:bg-gray-700 top-16 right-0 shadow-md  rounded-lg z-20 ">
                    <livewire:chat />
                </div>
            </div>

            <!-- Notification Icon -->
            <div x-data="{ toggle: false }" @click="toggle = true"
                class="relative">
                <div class="w-12 h-12 flex items-center justify-center bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full shadow-sm cursor-pointer">
                    <span class="absolute top-1 right-1 bg-red-500 w-3 h-3 rounded-full"></span>
                    <svg class="w-6 h-6 text-gray-500 dark:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M10.146 3.248a2 2 0 0 1 3.708 0A7 7 0 0 1 19 10v4.697l1.832 2.748A1 1 0 0 1 20 19h-4.535a3.501 3.501 0 0 1-6.93 0H4a1 1 0 0 1-.832-1.555L5 14.697V10c0-3.224 2.18-5.94 5.146-6.752M10.586 19a1.5 1.5 0 0 0 2.829 0zM12 5a5 5 0 0 0-5 5v5a1 1 0 0 1-.168.555L5.869 17H18.13l-.963-1.445A1 1 0 0 1 17 15v-5a5 5 0 0 0-5-5" />
                    </svg>
                </div>
                <div
                    x-show="toggle"
                    x-cloak
                    @click.away="toggle = false"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform scale-90"
                    x-transition:enter-end="opacity-100 transform scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 transform scale-100"
                    x-transition:leave-end="opacity-0 transform scale-90"
                    class="max-h-[550px] flex flex-col absolute top-16 right-0 w-96 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg shadow-md z-60">
                    <div class="p-4 flex justify-between items-center">
                        <h2 class="bg-white dark:bg-gray-800 text-lg font-semibold rounded-t-lg">
                            Notifications
                        </h2>

                        <!-- Close button -->
                        <button type="button" @click.stop="toggle = false" aria-label="Close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="cursor-pointer stroke-red-500 dark:stroke-red-300 hover:stroke-red-700 dark:hover:stroke-red-100">
                                <path d="M14.5 9.50002L9.5 14.5M9.49998 9.5L14.5 14.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <!-- Notification List with scrollable area -->
                    <div class="p-4 flex-1 overflow-y-auto">
                        <livewire:notification />
                    </div>

                    <!-- Sticky Footer Buttons -->
                    <div class="flex justify-between items-center  p-4 bg-gray-100 dark:bg-gray-700 rounded-b-lg sticky bottom-0">
                        <a
                            href="javascript:void(0);"
                            wire:click="markAllAsRead"
                            class="text-blue-800 dark:text-blue-300 hover:text-blue-500 hover:dark:text-blue-400 text-sm font-medium hover:underline cursor-pointer transition-all ease-in-out duration-200">
                            Mark all as read
                        </a>

                        <a
                            href="{{ route('notifications.index') }}"
                            class="bg-blue-100/50 dark:bg-blue-900/50 hover:bg-blue-900/50 hover:dark:bg-blue-900/80 text-blue-800 dark:text-blue-300 hover:text-white hover:dark:text-blue-400 text-sm font-semibold px-4 py-2 rounded-lg transition-all">
                            View all notifications
                        </a>
                    </div>
                </div>

            </div>


            <!-- Profile Picture with Dropdown -->
            <div class="p-0.5 h-12 w-12 rounded-full {{$color}}">
                <div class="relative w-full h-full flex items-center justify-center bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full shadow-sm">
                    @if(Auth::check())
                    <!-- Authenticated User -->
                    <div x-data="profileDropdown()" class="relative">
                        <!-- Profile Image -->
                        <div @click="open = !open" class="w-full h-full object-cover cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 13c2.396 0 4.575.694 6.178 1.671c.8.49 1.484 1.065 1.978 1.69c.486.616.844 1.352.844 2.139c0 .845-.411 1.511-1.003 1.986c-.56.45-1.299.748-2.084.956c-1.578.417-3.684.558-5.913.558s-4.335-.14-5.913-.558c-.785-.208-1.524-.506-2.084-.956C3.41 20.01 3 19.345 3 18.5c0-.787.358-1.523.844-2.139c.494-.625 1.177-1.2 1.978-1.69C7.425 13.694 9.605 13 12 13" class="duoicon-primary-layer" />
                                <path fill="currentColor" d="M12 2c3.849 0 6.255 4.167 4.33 7.5A5 5 0 0 1 12 12c-3.849 0-6.255-4.167-4.33-7.5A5 5 0 0 1 12 2" class="duoicon-secondary-layer" opacity=".3" />
                            </svg>
                        </div>

                        <!-- Dropdown Menu -->
                        <div x-cloak x-show="open" @click.away="open = false" class="absolute top-14 right-0 w-80 mt-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg z-10">
                            <!-- User Information & Profile -->
                            <a href="{{ route('profile.edit') }}">
                                <div class="flex items-center p-4 border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200">
                                    <div class="w-12 h-12 flex items-center justify-center bg-gray-200 dark:bg-gray-700 rounded-full shadow-sm">
                                        <svg class="w-6 h-6 text-gray-600 dark:text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M12 13c2.396 0 4.575.694 6.178 1.671c.8.49 1.484 1.065 1.978 1.69c.486.616.844 1.352.844 2.139c0 .845-.411 1.511-1.003 1.986c-.56.45-1.299.748-2.084.956c-1.578.417-3.684.558-5.913.558s-4.335-.14-5.913-.558c-.785-.208-1.524-.506-2.084-.956C3.41 20.01 3 19.345 3 18.5c0-.787.358-1.523.844-2.139c.494-.625 1.177-1.2 1.978-1.69C7.425 13.694 9.605 13 12 13" class="duoicon-primary-layer" />
                                            <path fill="currentColor" d="M12 2c3.849 0 6.255 4.167 4.33 7.5A5 5 0 0 1 12 12c-3.849 0-6.255-4.167-4.33-7.5A5 5 0 0 1 12 2" class="duoicon-secondary-layer" opacity=".3" />
                                        </svg>
                                    </div>

                                    <div class="ml-3 flex-1">
                                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ Auth::user()->name }}
                                            <span class="ml-1 text-green-600 text-xs bg-green-100 dark:bg-green-800 dark:text-green-300 py-0.5 px-2 rounded-full font-medium">Pro</span>
                                        </h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">View your profile</p>
                                    </div>
                                </div>
                            </a>

                            <!-- IATA Wallet Information -->
                            <div class="p-4 bg-gradient-to-r from-green-50 to-teal-50 dark:from-gray-800 dark:to-gray-750 border-b border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M21 7.28V5c0-1.1-.9-2-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-2.28A2 2 0 0 0 22 15V9a2 2 0 0 0-1-1.72M20 15H12V9h8zM5 19V5h14v2H12a2 2 0 0 0-2 2v6c0 1.1.9 2 2 2h7v2z"/>
                                            <circle fill="currentColor" cx="16" cy="12" r="1.5"/>
                                        </svg>
                                        IATA Company Wallet
                                    </h5>
                                    
                                    <!-- Reload Button -->
                                    <button 
                                        id="reload-wallet-btn" 
                                        onclick="reloadWalletData()" 
                                        class="flex items-center px-2 py-1 text-xs font-medium text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200 hover:bg-green-100 dark:hover:bg-green-900/30 rounded transition-colors duration-200"
                                        title="Reload wallet data">
                                        <svg class="w-3 h-3 mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                                        </svg>
                                        Reload
                                    </button>
                                </div>
                                
                                <div id="iata-info" class="space-y-2">
                                    <!-- Initial content will be loaded by checkAndLoadWalletData() -->
                                </div>
                            </div>
                            <div id="jazeera-section" class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-750 border-b border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M21 7.28V5c0-1.1-.9-2-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-2.28A2 2 0 0 0 22 15V9a2 2 0 0 0-1-1.72M20 15H12V9h8zM5 19V5h14v2H12a2 2 0 0 0-2 2v6c0 1.1.9 2 2 2h7v2z"/>
                                            <circle fill="currentColor" cx="16" cy="12" r="1.5"/>
                                        </svg>
                                        Jazeera Airways Credit
                                    </h5>
                                </div>

                                <div id="jazeera-info" class="space-y-2"></div>
                            </div>

                            <!-- Dropdown Links -->
                            <div>

                                <!-- Logout -->
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <a href="{{ route('logout') }}" class="flex items-center justify-center p-2 text-sm text-red-600 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 hover:rounded-b"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                        <svg class="w-5 h-5 text-red-500 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.023 5.5a9 9 0 1 0 9.953 0M12 2v8" color="currentColor" />
                                        </svg>
                                        Sign Out
                                    </a>
                                </form>
                            </div>
                        </div>

                    </div>
                    @else
                    <!-- Guest User -->
                    <div x-data="{ open: false }" class="relative">
                        <!-- Profile Image -->
                        <div @click="open = !open" class="w-full h-full object-cover cursor-pointer h-[30px] w-[30px]">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 13c2.396 0 4.575.694 6.178 1.671c.8.49 1.484 1.065 1.978 1.69c.486.616.844 1.352.844 2.139c0 .845-.411 1.511-1.003 1.986c-.56.45-1.299.748-2.084.956c-1.578.417-3.684.558-5.913.558s-4.335-.14-5.913-.558c-.785-.208-1.524-.506-2.084-.956C3.41 20.01 3 19.345 3 18.5c0-.787.358-1.523.844-2.139c.494-.625 1.177-1.2 1.978-1.69C7.425 13.694 9.605 13 12 13" class="duoicon-primary-layer" />
                                <path fill="currentColor" d="M12 2c3.849 0 6.255 4.167 4.33 7.5A5 5 0 0 1 12 12c-3.849 0-6.255-4.167-4.33-7.5A5 5 0 0 1 12 2" class="duoicon-secondary-layer" opacity=".3" />
                            </svg>
                        </div>


                        <!-- Dropdown Menu -->
                        <div x-show="open" @click.away="open = false" class="absolute top-14 right-0 w-48 mt-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg z-10">
                            <x-dropdown-link :href="route('login')">
                                {{ __('Login') }}
                            </x-dropdown-link>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    let walletData = null;
    let walletSessionExpiry = null;
    const WALLET_SESSION_DURATION = 60000;

    $(document).ready(function() {
        $('#menu-icon').click(function() {
            $('#responsiveMenu').toggle();
        });
    });

    document.addEventListener('alpine:init', () => {
        Alpine.data('profileDropdown', () => ({
            open: false,
            init() {
                this.$watch('open', (value) => {
                    if (value === true) {
                        checkAndLoadWalletData();
                    }
                });
            }
        }));
    });

    function checkAndLoadWalletData() {
        const now = new Date().getTime();
        
        if (walletData && walletSessionExpiry && now < walletSessionExpiry) {
            displayWalletData(walletData);
        } else {
            iataCompanyWallet();
        }
    }

    function reloadWalletData() {
        walletData = null;
        walletSessionExpiry = null;
        iataCompanyWallet();
    }

    function iataCompanyWallet() {
        const url = "{{ route('iata.company-wallet') }}";
        let companyId = "{{ $companyId }}";
        const iataInfo = document.getElementById('iata-info');
        const reloadBtn = document.getElementById('reload-wallet-btn');

        // Show loading state and disable reload button
        iataInfo.innerHTML = `
            <div class="flex items-center justify-center py-2">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 dark:border-blue-400"></div>
                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Loading...</span>
            </div>
        `;
        
        if (reloadBtn) {
            reloadBtn.disabled = true;
            reloadBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }

        fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    'company_id': companyId
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => Promise.reject(err));
                }
                return response.json();
            })
            .then(data => {
                console.log('IATA Company Wallet Data:', data);
                if (data.error) {
                    throw new Error(data.error);
                }

                // Cache the data with timestamp
                walletData = {
                    wallets: data.wallets || [],
                    iataBalance: parseFloat(data.iataBalance || 0).toFixed(3),
                    walletName: data.walletName
                };
                walletSessionExpiry = new Date().getTime() + WALLET_SESSION_DURATION;

                // Display the data
                displayWalletData(walletData);
            })
            .catch(error => {
                console.error('Error fetching IATA Company Wallet:', error);
                
                const iataInfo = document.getElementById('iata-info');
                iataInfo.innerHTML = `
                    <div class="text-center py-4">
                        <svg class="mx-auto h-8 w-8 text-red-400 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <p class="text-sm text-red-600 dark:text-red-400 font-medium">Failed to load wallet</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${error.message || 'Please try again later'}</p>
                    </div>
                `;
            })
            .finally(() => {
                const reloadBtn = document.getElementById('reload-wallet-btn');
                if (reloadBtn) {
                    reloadBtn.disabled = false;
                    reloadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            });
    }

    function displayWalletData(data) {
        const iataInfo = document.getElementById('iata-info');
        const { wallets, iataBalance, walletName } = data;

        if (wallets.length > 0) {
            const now = new Date().getTime();
            
            const walletsHtml = wallets.map(wallet => `
                <div class="bg-white dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M21 7.28V5c0-1.1-.9-2-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-2.28A2 2 0 0 0 22 15V9a2 2 0 0 0-1-1.72M20 15H12V9h8zM5 19V5h14v2H12a2 2 0 0 0-2 2v6c0 1.1.9 2 2 2h7v2z"/>
                                    <circle fill="currentColor" cx="16" cy="12" r="1.5"/>
                                </svg>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    ${wallet.name || wallet.wallet_name || 'Wallet'}
                                </span>
                            </div>
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">
                                ${parseFloat(wallet.balance).toFixed(3) || '0.00'} ${wallet.currency || ''}
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                wallet.status === 'OPEN'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                            }">
                                ${wallet.status || 'N/A'}
                            </span>
                        </div>
                    </div>
                </div>
            `).join('');

            iataInfo.innerHTML = `
                <div class="space-y-3">
                    <!-- Company Total (IATA Balance) -->
                    <div class="bg-gradient-to-r from-green-50 to-teal-100 dark:from-blue-900/30 dark:to-indigo-900/30 rounded-lg p-4 border border-green-200 dark:border-green-800">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                <span class="text-sm font-semibold text-green-800 dark:text-green-200 uppercase tracking-wider">
                                    Total Company Balance
                                </span>
                            </div>
                        </div>
                        <p class="text-2xl font-bold text-green-900 dark:text-green-100">
                            ${iataBalance || '0.000'}
                        </p>
                        <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                            ${wallets.length} wallet${wallets.length !== 1 ? 's' : ''} • IATA Balance
                        </p>
                    </div>
                    
                    <!-- Individual Wallets -->
                    <div class="space-y-2">
                        <h6 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-1">
                            Individual Wallets
                        </h6>
                        <div class="space-y-2 max-h-32 overflow-y-auto">
                            ${walletsHtml}
                        </div>
                    </div>
                </div>
            `;
        } else {
            iataInfo.innerHTML = `
                <div class="text-center py-4">
                    <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No wallet data available</p>
                </div>
            `;
        }
    }

    function reloadJazeeraData() {
    console.log('Reload Jazeera Airways Credit data');
    creditData = null;
    JazeeraAirwaysCredit();
}

function JazeeraAirwaysCredit() {
    const section = document.getElementById('jazeera-section');
    const creditInfo = document.getElementById('jazeera-info');

    // 1️⃣ If data variable itself is missing → hide section entirely (not implemented yet)
    if (typeof data === 'undefined' || data === null) {
        if (section) section.classList.add('hidden');
        return;
    }

    // 2️⃣ If data exists but is empty → show fallback message
    if (!data.length) {
        if (section) section.classList.remove('hidden');
        creditInfo.innerHTML = `
            <div class="text-center py-4">
                <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 
                    10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No Jazeera credit data available
                </p>
            </div>
        `;
        return;
    }

    // 3️⃣ If valid data exists → show total
    section.classList.remove('hidden');
    const total = data.reduce((sum, entry) => sum + parseFloat(entry.balance || 0), 0).toFixed(3);
    creditInfo.innerHTML = `
        <div class="flex flex-col items-center py-2">
            <p class="text-lg font-semibold text-sky-700 dark:text-sky-300">${total} KWD</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Total Credit Spent</p>
        </div>
    `;
}

document.addEventListener('DOMContentLoaded', JazeeraAirwaysCredit);

// Optional: API-based display hook
function displayJazeeraData(data) {
    const section = document.getElementById('jazeera-section');
    const jazeeraInfo = document.getElementById('jazeera-info');
    const { records = [], total = 0 } = data;

    if (!section || !jazeeraInfo) return;

    if (!records.length) {
        // keep visible if empty due to API error
        section.classList.remove('hidden');
        jazeeraInfo.innerHTML = `
            <div class="text-center py-4">
                <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 
                    10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No Jazeera credit data available
                </p>
            </div>
        `;
        return;
    }

    // ✅ Show valid data
    section.classList.remove('hidden');
    jazeeraInfo.innerHTML = `
        <div class="space-y-3">
            <div class="bg-gradient-to-r from-sky-50 to-blue-100 dark:from-sky-900/30 dark:to-blue-900/30 rounded-lg p-4 border border-sky-200 dark:border-sky-800">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-sky-600 dark:text-sky-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 
                            1.18 6.88L12 17.77l-6.18 3.25L7 14.14 
                            2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <span class="text-sm font-semibold text-sky-800 dark:text-sky-200 uppercase tracking-wider">
                            Total Credit Spent
                        </span>
                    </div>
                </div>
                <p class="text-2xl font-bold text-sky-900 dark:text-sky-100">
                    ${parseFloat(total).toFixed(3)} KWD
                </p>
                <p class="text-xs text-sky-600 dark:text-sky-400 mt-1">
                    ${records.length} record${records.length !== 1 ? 's' : ''} • Spent Credit
                </p>
            </div>
        </div>
    `;
}

</script>


<style>
    .top5Up {
        top: -4.5rem;
    }

    .text-background {

        padding: 0 !important;
        margin: 0 !important;
        background-image: url("{{ asset('images/bgCity.png') }}");
        opacity: 0.4;
        background-size: cover;
        background-position: center;
        color: transparent;
        font-size: 9rem;
        font-weight: bold;
        text-transform: uppercase;
        font-family: 'Archivo Black', sans-serif;
        letter-spacing: 2.5rem;
        -webkit-background-clip: text;
        background-clip: text;
        text-align: center;

    }

    /* Tablet and Mobile Specific Styles */
    @media (max-width: 768px) {
        .text-background {
            font-size: 3rem;
            /* Adjust font size for tablets */
            letter-spacing: 1.5rem;
            /* Adjust letter spacing for tablets */
        }
    }

    @media (max-width: 640px) {
        .text-background {
            font-size: 2.5rem;
            /* Adjust font size for mobile */
            letter-spacing: 1rem;
            /* Adjust letter spacing for mobile */
        }
    }
</style>