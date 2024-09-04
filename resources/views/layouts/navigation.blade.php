<div x-data="{ sidebarOpen: false, darkMode: localStorage.getItem('darkMode') === 'true' }"
    :class="{ 'dark': darkMode }" class="flex h-screen">
    <!-- Sidebar -->
    @include('layouts.sidebar')
    <!-- This includes the sidebar.blade.php -->

    <!-- Main Content Area -->
    <div :class="sidebarOpen ? 'ml-[260px]' : 'ml-0'" class="flex-1 transition-all duration-300 ease-in-out">
        <!-- Header (Navigation) -->
        <nav class="bg-white text-black dark:bg-black dark:text-white shadow-sm">
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
                        <!-- Logo and App Name -->
                        <a href="{{ route('dashboard') }}" class="flex items-center ml-2">
                            <img class="ml-[5px] w-12 flex-none pr-2" src="{{ asset('images/City0logo.svg') }}"
                                alt="image">
                            <span class="ml-2 text-lg font-semibold text-gray-900 dark:text-white">City App</span>
                        </a>

                        <!-- Search Bar -->
                        <div class="relative max-w-md ml-4">
                            <input type="text" placeholder="Search..."
                                class="w-full pl-9 pr-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder:text-gray-500">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-300 h-6 w-6"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-4.35-4.35" />
                            </svg>
                        </div>
                    </div>

                    <!-- Right Side Content -->
                    <div class="flex items-center space-x-4 ml-auto">
                        <!-- Dark Mode Toggle Button -->
                        <button x-cloak
    @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode); document.documentElement.classList.toggle('dark', darkMode)"
    class="relative flex items-center justify-between w-16 h-8 p-1 bg-gray-200 dark:bg-gray-800 rounded-full focus:outline-none transition-colors duration-300 ease-in-out">
    
    <div :class="{ 'translate-x-0': !darkMode, 'translate-x-8': darkMode }"
         class="w-6 h-6 bg-white dark:bg-gray-200 rounded-full transform transition-transform duration-300 ease-in-out">
        <svg width="24" height="24" fill="none" stroke="currentColor"
            class="absolute inset-0 m-auto text-gray-500 dark:text-gray-800" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <circle cx="12" cy="12" r="5" stroke-width="1.5"></circle>
            <path d="M12 2V4" stroke-width="1.5" stroke-linecap="round"></path>
            <path d="M12 20V22" stroke-width="1.5" stroke-linecap="round"></path>
            <path d="M4 12L2 12" stroke-width="1.5" stroke-linecap="round"></path>
            <path d="M22 12L20 12" stroke-width="1.5" stroke-linecap="round"></path>
            <path opacity="0.5" d="M19.7778 4.22266L17.5558 6.25424" stroke-width="1.5" stroke-linecap="round"></path>
            <path opacity="0.5" d="M4.22217 4.22266L6.44418 6.25424" stroke-width="1.5" stroke-linecap="round"></path>
            <path opacity="0.5" d="M6.44434 17.5557L4.22211 19.7779" stroke-width="1.5" stroke-linecap="round"></path>
            <path opacity="0.5" d="M19.7778 19.7773L17.5558 17.5551" stroke-width="1.5" stroke-linecap="round"></path>
        </svg>
    </div>
</button>


                        <!-- Profile Dropdown -->
                        <div x-data="{ open: false }" @click.away="open = false"
                            class=" dark:bg-gray-800 flex justify-center items-center bg-gray-100 rounded-full">
                            <x-dropdown align="right" width="48"
                                class="top-11 w-[230px] !py-0 font-semibold text-dark ltr:right-0 rtl:left-0 dark:text-white-dark dark:text-white-light/90">
                                <x-slot name="trigger">
                                    <button
                                        class="profile-icon flex items-center justify-center text-sm font-medium text-gray-500 dark:text-gray-400 bg-transparent rounded-full focus:outline-none p-1">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="12" cy="6" r="4" stroke="CurrentColor" stroke-width="1.5">
                                            </circle>
                                            <path
                                                d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18"
                                                stroke="CurrentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <!-- Profile Info Section -->
                                    <div class="flex items-center px-4 py-4">
                                        <div class="flex-none ">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="12" cy="6" r="4" stroke="CurrentColor" stroke-width="1.5">
                                                </circle>
                                                <path
                                                    d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18"
                                                    stroke="CurrentColor" stroke-width="1.5" stroke-linecap="round">
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

        <!-- Page Content -->
        <main class="p-4">
            <!-- Your main content goes here -->
            {{ $slot }}
        </main>
    </div>
</div>