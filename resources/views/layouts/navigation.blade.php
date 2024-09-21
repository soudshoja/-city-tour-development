<div x-data="{ sidebarOpen: false, darkMode: localStorage.getItem('darkMode') === 'true' }"
    :class="{ 'dark': darkMode }" class="flex h-screen">
    <!-- Sidebar -->
    @include('layouts.sidebar')
    <!-- This includes the sidebar.blade.php -->


    <!-- desktop & pads Header -->
    <div :class="sidebarOpen ? 'ml-[260px]' : 'ml-0'" class="flex-1 transition-all duration-300 ease-in-out">
        <!-- Header (Navigation) 1st -->

        <nav class="CityDisplaayNone bg-white text-black dark:bg-black dark:text-white shadow-sm">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center space-x-4">
                        <!-- Sidebar Toggle Button -->
                        <button @click="sidebarOpen = !sidebarOpen"
                            class="text-gray-500 dark:text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none">
                            <svg class="h-6 w-6" stroke="CurrentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <!-- Add this to your CSS file -->
                        <style>
                        .fade-in {
                            opacity: 0;
                            transition: opacity 0.3s ease-in;
                        }

                        .fade-in-loaded {
                            opacity: 1;
                        }
                        </style>

                        <!-- Logo and App Name -->
                        <a href="{{ route('dashboard') }}" class="flex items-center ml-2">
                            <img id="logo" class="ml-[5px] w-12 flex-none pr-2 fade-in"
                                src="{{ asset('images/City0logo.svg') }}" alt="City App Logo">
                            <span id="appName"
                                class="ml-2 text-lg font-semibold text-gray-900 dark:text-white fade-in">City
                                App</span>
                        </a>

                        <!-- Add this to your script (can be at the bottom of your page) -->
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            document.getElementById('logo').classList.add('fade-in-loaded');
                            document.getElementById('appName').classList.add('fade-in-loaded');
                        });
                        </script>



                        <!-- Search Bar (visible on desktop, hidden on mobile and iPad) -->
                        <div class="relative max-w-md ml-4 hidden md:block">
                            <input type="text" placeholder="Search..."
                                class="focus:ring-2 focus:ring-[#0d324d] focus:outline-none w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:border-transparent placeholder:text-gray-500">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-300 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-4.35-4.35" stroke="currentColor" />
                            </svg>

                        </div>



                    </div>

                    <!-- Right Side Content -->
                    <div class="flex items-center space-x-4 ml-auto sm:flex sm:space-x-2">
                        <!-- Dark Mode Toggle Button -->
                        <button x-cloak
                            @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode); document.documentElement.classList.toggle('dark', darkMode)"
                            class="relative flex items-center justify-between w-16 h-10 p-1 bg-gray-200 dark:bg-gray-700 rounded-full focus:outline-none transition-colors duration-300 ease-in-out sm:w-12 sm:h-6">

                            <div :class="{ 'translate-x-0': !darkMode, 'translate-x-8 sm:translate-x-6': darkMode }"
                                class="w-6 h-6 bg-white dark:bg-gray-200 rounded-full transform transition-transform duration-300 ease-in-out">
                                <svg width="20" height="20" fill="none" stroke="#0d324d"
                                    class="absolute inset-0 m-auto text-gray-500 dark:text-gray-800" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="5" stroke-width="1.5"></circle>
                                    <path d="M12 2V4" stroke-width="1.5" stroke-linecap="round"></path>
                                    <path d="M12 20V22" stroke-width="1.5" stroke-linecap="round"></path>
                                    <path d="M4 12L2 12" stroke-width="1.5" stroke-linecap="round"></path>
                                    <path d="M22 12L20 12" stroke-width="1.5" stroke-linecap="round"></path>
                                    <path opacity="0.5" d="M19.7778 4.22266L17.5558 6.25424" stroke-width="1.5"
                                        stroke-linecap="round"></path>
                                    <path opacity="0.5" d="M4.22217 4.22266L6.44418 6.25424" stroke-width="1.5"
                                        stroke-linecap="round"></path>
                                    <path opacity="0.5" d="M6.44434 17.5557L4.22211 19.7779" stroke-width="1.5"
                                        stroke-linecap="round"></path>
                                    <path opacity="0.5" d="M19.7778 19.7773L17.5558 17.5551" stroke-width="1.5"
                                        stroke-linecap="round"></path>
                                </svg>
                            </div>
                        </button>

                        <!-- Notification -->
                        <div class="">
                            <a href="#" class="block hover:bg-white-light/90 hover:text-primary dark:bg-gray-700 ">
                                <a href="javascript:;"
                                    class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-gray-700"
                                    @click="toggle">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="stroke-current text-gray-800 dark:text-gray-200">
                                        <path
                                            d="M19.0001 9.7041V9C19.0001 5.13401 15.8661 2 12.0001 2C8.13407 2 5.00006 5.13401 5.00006 9V9.7041C5.00006 10.5491 4.74995 11.3752 4.28123 12.0783L3.13263 13.8012C2.08349 15.3749 2.88442 17.5139 4.70913 18.0116C9.48258 19.3134 14.5175 19.3134 19.291 18.0116C21.1157 17.5139 21.9166 15.3749 20.8675 13.8012L19.7189 12.0783C19.2502 11.3752 19.0001 10.5491 19.0001 9.7041Z"
                                            stroke="currentColor" stroke-width="1.5"></path>
                                        <path
                                            d="M7.5 19C8.15503 20.7478 9.92246 22 12 22C14.0775 22 15.845 20.7478 16.5 19"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        <path d="M12 6V10" stroke="currentColor" stroke-width="1.5"
                                            stroke-linecap="round"></path>
                                    </svg>

                                    <span class="absolute top-0 flex h-3 w-3 ltr:right-0 rtl:left-0">
                                        <span
                                            class="absolute -top-[3px] inline-flex h-full w-full animate-ping rounded-full bg-success/50 opacity-75 ltr:-left-[3px] rtl:-right-[3px]"></span>
                                        <span
                                            class="relative inline-flex h-[6px] w-[6px] rounded-full bg-success"></span>
                                    </span>
                                </a>
                            </a>
                        </div>

                        <!-- Chat -->
                        <div class="">
                            <a href="#"
                                class="block hover:bg-white-light/90 hover:text-primary dark:bg-gray-700 dark:hover:bg-gray-200">
                                <a href="javascript:;"
                                    class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-gray-700 dark:hover:bg-gray-200"
                                    @click="toggle">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                        class="stroke-current text-gray-800 dark:text-gray-200">
                                        <path
                                            d="M22 10C22.0185 10.7271 22 11.0542 22 12C22 15.7712 22 17.6569 20.8284 18.8284C19.6569 20 17.7712 20 14 20H10C6.22876 20 4.34315 20 3.17157 18.8284C2 17.6569 2 15.7712 2 12C2 8.22876 2 6.34315 3.17157 5.17157C4.34315 4 6.22876 4 10 4H13"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        <path
                                            d="M6 8L8.1589 9.79908C9.99553 11.3296 10.9139 12.0949 12 12.0949C13.0861 12.0949 14.0045 11.3296 15.8411 9.79908"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        <circle cx="19" cy="5" r="3" stroke="currentColor" stroke-width="1.5"></circle>
                                    </svg>

                                </a>
                            </a>
                        </div>

                        <!-- Profile Dropdown -->
                        <div x-data="{ open: false }" @click.away="open = false"
                            class="dark:bg-gray-700 flex justify-center items-center bg-gray-100 rounded-full sm:ml-auto">
                            <x-dropdown align="right" width="48"
                                class="top-11 w-[230px] !py-0 font-semibold text-dark ltr:right-0 rtl:left-0 dark:text-white-dark dark:text-white-light/90">
                                <x-slot name="trigger">
                                    <button
                                        class="profile-icon flex items-center justify-center text-sm font-medium text-gray-500 dark:text-gray-400 bg-transparent rounded-full focus:outline-none p-1">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="stroke-current text-gray-800 dark:text-gray-200">
                                            <circle cx="12" cy="6" r="4" stroke="currentColor" stroke-width="1.5">
                                            </circle>
                                            <path
                                                d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18"
                                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        </svg>

                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <!-- Profile Info Section -->
                                    <div class="flex items-center px-4 py-4">
                                        <div class="flex-none">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="12" cy="6" r="4" stroke="#0d324d" stroke-width="1.5">
                                                </circle>
                                                <path
                                                    d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18"
                                                    stroke="#0d324d" stroke-width="1.5" stroke-linecap="round">
                                                </path>
                                            </svg>
                                        </div>
                                        <div class="ml-4 truncate">
                                            <a class="text-sm text-gray-500 hover:text-primary dark:text-gray-400 dark:hover:text-white"
                                                href="{{ route('profile.edit') }}">
                                                <h4 class="text-base font-semibold text-dark dark:text-white">
                                                    {{ Auth::user()->name }}
                                                    <span
                                                        class="rounded bg-success-light px-1 text-xs text-success ml-2">Pro</span>
                                                </h4>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-200 dark:border-gray-600"></div>

                                    <!-- Profile Link -->
                                    <x-dropdown-link :href="route('profile.edit')"
                                        class="flex items-center py-2 dark:hover:text-white">
                                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 mr-3" fill="CurrentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                                        </svg>
                                        {{ __('Profile') }}
                                    </x-dropdown-link>

                                    <div class="border-t border-gray-200 dark:border-gray-600 mt-2"></div>

                                    <x-dropdown-link :href="route('profile.edit')"
                                        class="flex items-center py-2 dark:hover:text-white">
                                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 mr-3" fill="CurrentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                                        </svg>
                                        Add New Admin
                                    </x-dropdown-link>

                                    <div class="border-t border-gray-200 dark:border-gray-600 mt-2"></div>

                                    <!-- Logout Link -->
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault(); this.closest('form').submit();"
                                            class="flex items-center py-3 text-danger dark:hover:text-white">
                                            <svg class="w-5 h-5 text-danger mr-3" fill="CurrentColor"
                                                viewBox="0 0 24 24">
                                                <path d="M16 13v-2H7V8l-5 4 5 4v-3h9zm5-9H7V2h14v2z" />
                                            </svg>
                                            {{ __('Log Out') }}
                                        </x-dropdown-link>
                                    </form>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>

                </div>
            </div>
        </nav>


        <!-- Header (Navigation) 2nd -->
        <nav
            class="CityDisplaayNone bg-white text-black dark:bg-gray-900 dark:text-white shadow-sm border-t border-gray-200 dark:border-gray-700">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex justify-start h-12 items-center space-x-8">

                    <!-- First Menu Item with Active State -->
                    <div x-data="{ open: false }" x-cloak class="relative">
                        <a @mouseenter="open = true" @mouseleave="open = false"
                            class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                            href="#">
                            <i class="icon-item2 mr-2"></i>
                            <span>Dashboard</span>
                            <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                        <!-- Dropdown Menu -->
                        <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                            class="absolute z-10 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95">
                            <div class="py-1">
                                <a href="#"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Revenue</a>
                                <a href="#"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Report</a>
                            </div>
                        </div>
                    </div>

                           <!-- Companies -->
                           <div x-data="{ open: false }" x-cloak class="relative">
                        <a @mouseenter="open = true" @mouseleave="open = false"
                            class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                            href="#">
                            <i class="icon-item2 mr-2"></i>
                            <span>Companies</span>
                            <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                        <!-- Companies Dropdown Menu -->
                        <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                            class="absolute z-10 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95">
                            <div class="py-1">
                                <a href="{{ route('companiesnew.new') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Add Company
                                </a>
                                <a href="{{ route('companies.index') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Companies List
                                </a>
                            </div>
                        </div>
                    </div>


                    <!-- Agents -->
                    <div x-data="{ open: false }" x-cloak class="relative">
                        <a @mouseenter="open = true" @mouseleave="open = false"
                            class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                            href="#">
                            <i class="icon-item2 mr-2"></i>
                            <span>Agents</span>
                            <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                        <!-- Agents Dropdown Menu -->
                        <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                            class="absolute z-10 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95">
                            <div class="py-1">
                                <a href="{{ route('agentsnew.new') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Add Agent
                                </a>
                                <a href="{{ route('agents.index') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Agent List
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Tasks -->
                    <div x-data="{ open: false }" x-cloak class="relative">
                        <a @mouseenter="open = true" @mouseleave="open = false"
                            class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                            href="#">
                            <i class="icon-item2 mr-2"></i>
                            <span>Tasks</span>
                            <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                        <!-- Tasks Dropdown Menu -->
                        <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                            class="absolute z-10 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95">
                            <div class="py-1">
                                <a href="{{ route('tasks.index') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Tasks List
                                </a>
                                <a href="{{ route('tasksupload.upload') }}"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    Tasks Upload
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </nav>

        <!-- Mobile menu-->

        <!-- Mobile menu ends-->


        <!-- Page Content -->
        <main class="p-4">
            <!-- Your main content goes here -->
            {{ $slot }}
        </main>
        <!-- Footer -->
        @include('layouts.footer')


    </div>

</div>