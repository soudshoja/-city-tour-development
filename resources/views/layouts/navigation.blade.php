@php
use App\Models\Role;
@endphp
<div x-data="{ sidebarOpen: false, darkMode: localStorage.getItem('darkMode') === 'true' }"
    :class="{ 'dark': darkMode }" class="flex h-screen">

    <!-- Mobile Header -->
    <nav
        class="p-5 fixed top-0 left-0 right-0 flex items-center justify-center bg-white dark:bg-gray-800 shadow-md z-50 CityDisplaayNoneDesk">
        <!-- Logo and App Name -->
        <a href="{{ route('dashboard') }}" class="flex items-center">
            <x-application-logo />

        </a>
    </nav>

    @include('layouts.sidebar')

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
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                class="stroke-current text-[#1C274C] dark:text-white">
                                <path d="M19 10L11 10M5 10H7" stroke-width="1.5" stroke-linecap="round"
                                    class="stroke-current" />
                                <path d="M5 18H13M19 18H17" stroke-width="1.5" stroke-linecap="round"
                                    class="stroke-current" />
                                <path d="M19 14L5 14" stroke-width="1.5" stroke-linecap="round"
                                    class="stroke-current" />
                                <path d="M19 6L5 6" stroke-width="1.5" stroke-linecap="round" class="stroke-current" />
                            </svg>


                        </button>

                        <a href="{{ route('dashboard') }}" class="flex items-center ml-2">
                            <img id="logo" src="{{ asset('images/City0logo.svg') }}" alt="City App Logo">
                            <span id="appName"
                                class="ml-2 text-lg font-semibold text-gray-900 dark:text-white fade-in">City
                                App</span>
                        </a>

                    </div>

                    <div class="flex items-center space-x-4 ml-auto sm:flex sm:space-x-2">
                        <!-- Dark Mode Toggle Button -->
                        <button x-cloak
                            @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode); document.documentElement.classList.toggle('dark', darkMode)"
                            class="relative flex items-center justify-between w-16 h-10 p-1 bg-gray-200 dark:bg-gray-700 rounded-full focus:outline-none transition-colors duration-300 ease-in-out sm:w-12 sm:h-6">

                            <div :class="{ 'translate-x-0': !darkMode, 'translate-x-8 sm:translate-x-6': darkMode }"
                                class="w-6 h-6 bg-white dark:bg-gray-200 rounded-full transform transition-transform duration-300 ease-in-out">
                                <svg width="20" height="20" fill="none" stroke="#0d324d"
                                    class="absolute inset-0 m-auto text-gray-500 dark:text-gray-800"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
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

                        <!-- Todo List -->
                        <div>
                            <a href="{{ route('todolist.index') }}"
                                class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-gray-700"
                                @click="toggle">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="stroke-current text-gray-800 dark:text-gray-200">
                                    <path d="M2 5.5L3.21429 7L7.5 3" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M2 12.5L3.21429 14L7.5 10" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M2 19.5L3.21429 21L7.5 17" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M22 12H17M12 12H13.5" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path d="M12 19H17M20.5 19H22" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path d="M22 5L12 5" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" />
                                </svg>
                            </a>
                        </div>

                        <!-- Notification -->
                        <div>
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

                                    <span class="absolute top-0 flex h-3 w-3 right-0">
                                        <span
                                            class="absolute -top-[3px] inline-flex h-full w-full animate-ping rounded-full bg-success/50 opacity-75 -left-[3px]"></span>
                                        <span
                                            class="relative inline-flex h-[6px] w-[6px] rounded-full bg-success"></span>
                                    </span>
                                </a>
                            </a>
                        </div>

                        <!-- Chat -->
                        <div>
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
                                class="top-11 w-[230px] !py-0 font-semibold text-dark left-0 dark:text-white-dark dark:text-white-light/90">
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
                                            <svg class="mr-3 w-5 h-5 text-gray-500 dark:text-gray-400"
                                                viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                                                class="stroke-current text-gray-800 dark:text-gray-200">
                                                <circle cx="12" cy="6" r="4" stroke="currentColor" stroke-width="1.5">
                                                </circle>
                                                <path
                                                    d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                                </path>
                                            </svg>
                                        </div>
                                        <div class="ml-3 truncate">
                                            <a class="text-sm text-gray-500 hover:text-primary dark:text-gray-400 dark:hover:text-white"
                                                href="{{ route('profile.edit') }}">
                                                <h4 class="text-sm font-semibold text-dark dark:text-white">
                                                    {{ Auth::user()->name }}
                                                    <span
                                                        class="rounded bg-success-light px-1 text-xs text-success ml-2">Pro</span>
                                                </h4>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 dark:border-gray-600"></div>
                                    @if(Auth::user()->role_id === Role::ADMIN)
                                    <!-- Add New Admin Link -->
                                    <div class="flex items-center px-4 py-2">
                                        <div class="flex-none">
                                            <svg class="mr-3 w-5 h-5 text-gray-500 dark:text-gray-400"
                                                viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                                                class="stroke-current text-gray-800 dark:text-gray-200">
                                                <circle cx="12" cy="6" r="4" stroke="currentColor" stroke-width="1.5">
                                                </circle>
                                                <path
                                                    d="M20.4141 18.5H18.9999M18.9999 18.5H17.5857M18.9999 18.5L18.9999 17.0858M18.9999 18.5L18.9999 19.9142"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                                </path>
                                                <path
                                                    d="M12 13C14.6083 13 16.8834 13.8152 18.0877 15.024M15.5841 20.4366C14.5358 20.7944 13.3099 21 12 21C8.13401 21 5 19.2091 5 17C5 15.6407 6.18652 14.4398 8 13.717"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                                </path>
                                            </svg>
                                        </div>
                                        <div class="ml-3 truncate">
                                            <a class="text-sm text-gray-500 hover:text-primary dark:text-gray-400 dark:hover:text-white"
                                                href="{{ route('register') }}">
                                                <h4 class="text-sm font-semibold text-dark dark:text-white">
                                                    Add New Admin
                                                </h4>
                                            </a>
                                        </div>
                                    </div>


                                    <div class="border-t border-gray-200 dark:border-gray-600 mt-2"></div>
                                    @endif
                                    <!-- Logout Link -->

                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault(); this.closest('form').submit();"
                                            class="flex items-center py-2 dark:hover:text-white">
                                            <div class="flex items-center px-4">
                                                <div class="flex-none">
                                                    <svg class="mr-3 w-5 h-5 text-gray-500 dark:text-gray-400"
                                                        viewBox="0 0 24 24" fill="none"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        class="stroke-current text-gray-800 dark:text-gray-200">
                                                        <circle cx="12" cy="6" r="4" stroke="currentColor"
                                                            stroke-width="1.5"></circle>
                                                        <path
                                                            d="M20.4141 18.5H18.9999M18.9999 18.5H17.5857M18.9999 18.5L18.9999 17.0858M18.9999 18.5L18.9999 19.9142"
                                                            stroke="currentColor" stroke-width="1.5"
                                                            stroke-linecap="round"></path>
                                                        <path
                                                            d="M12 13C14.6083 13 16.8834 13.8152 18.0877 15.024M15.5841 20.4366C14.5358 20.7944 13.3099 21 12 21C8.13401 21 5 19.2091 5 17C5 15.6407 6.18652 14.4398 8 13.717"
                                                            stroke="currentColor" stroke-width="1.5"
                                                            stroke-linecap="round"></path>
                                                    </svg>
                                                </div>
                                                <div class="ml-3 truncate">
                                                    <h4 class="text-sm font-semibold text-red-500">
                                                        {{ __('Log Out') }}
                                                    </h4>
                                                </div>
                                            </div>
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
                    <!-- admin menu -->
                    @if(Auth::user()->role_id === Role::ADMIN)
                    @include('layouts.menus.admin')
                    @endif
                    <!-- company menu -->
                    @if(Auth::user()->role_id === Role::COMPANY )
                    @include('layouts.menus.company')
                    @endif
                    <!-- agent menu -->
                    @if(Auth()->user()->role === Role::AGENT )
                    @include('layouts.menus.agent')
                    @endif




                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="p-4 mobile-m-5 min-h-full ">
            <!-- Your main content goes here -->
            <div class="p-3">
                {{ $slot }}

                @if($errors->any())
                @foreach($errors->all() as $error)
                <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
                    {{ $error }}
                    <button type="button" class="close text-white ml-2" aria-label="Close"
                        onclick="this.parentElement.style.display='none';">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endforeach
                @endif

                @if(session('success'))
                <div
                    class="alert alert-success fixed mt-5 top-1 right-4 bg-green-500 text-white p-4 rounded shadow-lg">
                    {{ session('success') }}
                    <button type="button" class="close text-white ml-2" aria-label="Close"
                        onclick="this.parentElement.style.display='none';">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @elseif(session('error'))
                <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
                    {{ session('error') }}
                    <button type="button" class="close text-white ml-2" aria-label="Close"
                        onclick="this.parentElement.style.display='none';">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif
            </div>



        </main>

        @include('layouts.footer')

    </div>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('logo').classList.add('fade-in-loaded');
        document.getElementById('appName').classList.add('fade-in-loaded');
    });
</script>