<header>
    <div class="mt-2 container mx-auto flex flex-wrap items-center justify-between px-6 py-4">
        <!-- Logo -->
        <div class="flex items-center w-full md:w-auto mb-4 md:mb-0 justify-center md:justify-start">
            <!-- Logo Image & home link -->
            <a href="{{ url('/') }}" class="flex items-center">
                <img src="{{ asset('images/City0logo.svg') }}" alt="Logo" class="h-8 mr-4">
                <span class="text-lg font-bold text-gray-800">City Tour</span>
            </a>

            <div class="ml-5 hidden lg:block xl:block">
                <!-- company menu -->
                @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
                @include('layouts.menus.company')
                @endif

                <!-- branch menu -->
                @if(Auth()->user()->role_id ==\App\Models\Role::BRANCH)
                @include('layouts.menus.branch')
                @endif

                <!-- agent menu -->
                @if(Auth()->user()->role_id ==\App\Models\Role::AGENT)
                @include('layouts.menus.agent')
                @endif

                <!-- admin menu -->
                @if(Auth()->user()->role_id ==\App\Models\Role::ADMIN)
                @include('layouts.menus.admin')
                @endif

            </div>
        </div>


        <!-- Right Section -->
        <div x-data="{ 
                toggle: false,
                chatBox: false
            }"
            class="flex items-center space-x-4 w-full md:w-auto mb-4 md:mb-0 justify-center md:justify-start">
            <!-- Search Icon -->
            <div class="relative w-12 h-12 flex items-center justify-center bg-gray-200 hover:bg-gray-300 rounded-full shadow-sm">
                <svg class="w-6 h-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 15 16">
                    <path fill="currentColor" d="M6.5 13.02a5.5 5.5 0 0 1-3.89-1.61C1.57 10.37 1 8.99 1 7.52s.57-2.85 1.61-3.89c2.14-2.14 5.63-2.14 7.78 0C11.43 4.67 12 6.05 12 7.52s-.57 2.85-1.61 3.89a5.5 5.5 0 0 1-3.89 1.61m0-10c-1.15 0-2.3.44-3.18 1.32C2.47 5.19 2 6.32 2 7.52s.47 2.33 1.32 3.18a4.51 4.51 0 0 0 6.36 0C10.53 9.85 11 8.72 11 7.52s-.47-2.33-1.32-3.18A4.48 4.48 0 0 0 6.5 3.02" />
                    <path fill="currentColor" d="M13.5 15a.47.47 0 0 1-.35-.15l-3.38-3.38c-.2-.2-.2-.51 0-.71s.51-.2.71 0l3.38 3.38c.2.2.2.51 0 .71c-.1.1-.23.15-.35.15Z" />
                </svg>
            </div>

            <!-- chat Icon -->
            <div
                @click="chatBox = true"
                class="relative w-12 h-12 flex items-center justify-center bg-gray-200 hover:bg-gray-300 rounded-full shadow-sm cursor-pointer ">
                <span class="absolute top-1 right-1 bg-red-500 w-3 h-3 rounded-full"></span>

                <svg class="w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                    <path fill="currentColor" d="M0 262q0 43 24.5 81T90 405q-2 7-4.5 18t-7 34.5t-3.5 39T85 512q30 0 60.5-16t48.5-32t19-16q55 0 107-21q-6-2-22.5-12T277 405h-64q-18 0-38 20q-28 25-53 36l6-77l-17-15q-68-44-68-107q0-16 6-36q-4-6-5.5-18.5T42 185v-23l1-13Q0 195 0 262M299 0q-89 0-151.5 52T85 177q0 72 62 118t152 46q1 0 20.5 21.5t51.5 43t62 21.5q7 0 8.5-11t-1.5-26.5t-7-31.5t-7-27l-4-11q41-25 65.5-62.5T512 177q0-73-62.5-125T299 0m102 284l-28 17l11 32q2 5 5 17t6 19q-22-15-52-45q-23-25-42-25q-70 0-120.5-32.5T130 177q-1-56 48.5-95T299 43t120.5 39t49.5 95q0 63-68 107" />
                </svg>
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
                    class="flex flex-col justify-end absolute bg-white top-16 right-0 shadow-md w-120 h-160 bg-white rounded-lg z-20 ">
                    <livewire:chat />
                </div>
            </div>

            <!-- Notification Icon -->
            <div @click="toggle = true"
                class="relative w-12 h-12 flex items-center justify-center bg-gray-200 hover:bg-gray-300 rounded-full shadow-sm">

                <span class="absolute top-1 right-1 bg-red-500 w-3 h-3 rounded-full"></span>

                <svg class="w-6 h-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M10.146 3.248a2 2 0 0 1 3.708 0A7 7 0 0 1 19 10v4.697l1.832 2.748A1 1 0 0 1 20 19h-4.535a3.501 3.501 0 0 1-6.93 0H4a1 1 0 0 1-.832-1.555L5 14.697V10c0-3.224 2.18-5.94 5.146-6.752M10.586 19a1.5 1.5 0 0 0 2.829 0zM12 5a5 5 0 0 0-5 5v5a1 1 0 0 1-.168.555L5.869 17H18.13l-.963-1.445A1 1 0 0 1 17 15v-5a5 5 0 0 0-5-5" />
                </svg>
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
                    class="absolute top-16 right-0 w-120 bg-white border-2 border-gray dark:bg-gray-700 rounded-lg shadow-md z-60">
                    <h2 class="bg-black text-white text-lg font-semibold font-lg p-4 rounded-t-lg">
                        Notifications
                    </h2>
                    <div class="p-4">
                        <livewire:notification />
                    </div>
                </div>
            </div>

            <!-- Profile Picture with Dropdown -->
            <div class="relative w-12 h-12 flex items-center justify-center bg-gray-200 hover:bg-gray-300 rounded-full shadow-sm">
                @if(Auth::check())
                <!-- Authenticated User -->
                <div x-data="{ open: false }" class="relative">
                    <!-- Profile Image -->
                    <div @click="open = !open" class="w-full h-full object-cover cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 13c2.396 0 4.575.694 6.178 1.671c.8.49 1.484 1.065 1.978 1.69c.486.616.844 1.352.844 2.139c0 .845-.411 1.511-1.003 1.986c-.56.45-1.299.748-2.084.956c-1.578.417-3.684.558-5.913.558s-4.335-.14-5.913-.558c-.785-.208-1.524-.506-2.084-.956C3.41 20.01 3 19.345 3 18.5c0-.787.358-1.523.844-2.139c.494-.625 1.177-1.2 1.978-1.69C7.425 13.694 9.605 13 12 13" class="duoicon-primary-layer" />
                            <path fill="currentColor" d="M12 2c3.849 0 6.255 4.167 4.33 7.5A5 5 0 0 1 12 12c-3.849 0-6.255-4.167-4.33-7.5A5 5 0 0 1 12 2" class="duoicon-secondary-layer" opacity=".3" />
                        </svg>
                    </div>

                    <!-- Dropdown Menu -->
                    <div x-cloak x-show="open" @click.away="open = false" class="absolute top-14 right-0 w-64 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg z-10">
                        <!-- User Information & Profile -->
                        <a href="{{ route('profile.edit') }}">
                            <div class="flex items-center p-2 border-b border-gray-200 hover:bg-gray-300 hover:rounded-t">
                                <div class="w-12 h-12 flex items-center justify-center bg-gray-200 hover:bg-gray-300 rounded-full shadow-sm">
                                    <svg class="w-6 h-6 rounded-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <path fill="currentColor" d="M12 13c2.396 0 4.575.694 6.178 1.671c.8.49 1.484 1.065 1.978 1.69c.486.616.844 1.352.844 2.139c0 .845-.411 1.511-1.003 1.986c-.56.45-1.299.748-2.084.956c-1.578.417-3.684.558-5.913.558s-4.335-.14-5.913-.558c-.785-.208-1.524-.506-2.084-.956C3.41 20.01 3 19.345 3 18.5c0-.787.358-1.523.844-2.139c.494-.625 1.177-1.2 1.978-1.69C7.425 13.694 9.605 13 12 13" class="duoicon-primary-layer" />
                                        <path fill="currentColor" d="M12 2c3.849 0 6.255 4.167 4.33 7.5A5 5 0 0 1 12 12c-3.849 0-6.255-4.167-4.33-7.5A5 5 0 0 1 12 2" class="duoicon-secondary-layer" opacity=".3" />
                                    </svg>
                                </div>

                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}
                                        <span class="ml-1 text-green-500 text-xs bg-green-200 py-0.5 px-1 rounded">Pro</span>
                                    </h4>
                                    <p class="text-xs text-gray-500 mt-1">See Your Profile here</p>
                                </div>
                            </div>
                        </a>

                        <!-- Dropdown Links -->
                        <div>

                            <!-- Logout -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <a href="{{ route('logout') }}" class="flex items-center justify-center p-2 text-sm text-red-600 bg-gray-200 hover:bg-gray-300 hover:rounded-b"
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
                    <div x-show="open" @click.away="open = false" class="absolute top-14 right-0 w-48 mt-2 bg-white border border-gray-200 rounded-md shadow-lg z-10">
                        <x-dropdown-link :href="route('login')">
                            {{ __('Login') }}
                        </x-dropdown-link>
                    </div>
                </div>
                @endif
            </div>



        </div>
    </div>
</header>