 <style>
.fade-in {
    opacity: 0;
    transition: opacity 0.3s ease-in;
}

.fade-in-loaded {
    opacity: 1;
}
 </style>

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
     <!-- Mobile menu-->
     <!-- Mobile menu ends-->
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

                         <!-- Add this to your CSS file -->


                         <!-- Logo and App Name -->
                         <a href="{{ route('dashboard') }}" class="flex items-center ml-2">
                             <img id="logo" src="{{ asset('images/City0logo.svg') }}" alt="City App Logo">
                             <span id="appName"
                                 class="ml-2 text-lg font-semibold text-gray-900 dark:text-white fade-in">City
                                 App</span>
                         </a>

                         <!-- add Search Bar div here -->

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
                                     @if(Auth::user()->role == 'admin')
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
                     @if(Auth::user()->role == 'admin')
                     <!-- First Menu Item with Active State -->
                     <div x-data="{ open: false }" x-cloak class="relative">
                         <a @mouseenter="open = true" @mouseleave="open = false"
                             class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                             href="#">
                             <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg" class="shrink-0">
                                 <path opacity="0.5"
                                     d="M2 12.2039C2 9.91549 2 8.77128 2.5192 7.82274C3.0384 6.87421 3.98695 6.28551 5.88403 5.10813L7.88403 3.86687C9.88939 2.62229 10.8921 2 12 2C13.1079 2 14.1106 2.62229 16.116 3.86687L18.116 5.10812C20.0131 6.28551 20.9616 6.87421 21.4808 7.82274C22 8.77128 22 9.91549 22 12.2039V13.725C22 17.6258 22 19.5763 20.8284 20.7881C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.7881C2 19.5763 2 17.6258 2 13.725V12.2039Z"
                                     fill="currentColor"></path>
                                 <path
                                     d="M9 17.25C8.58579 17.25 8.25 17.5858 8.25 18C8.25 18.4142 8.58579 18.75 9 18.75H15C15.4142 18.75 15.75 18.4142 15.75 18C15.75 17.5858 15.4142 17.25 15 17.25H9Z"
                                     fill="currentColor"></path>
                             </svg> <span
                                 class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Dashboard</span>
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
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                             <div class="py-1">
                                 <a href="{{ route('dashboard') }}"
                                     class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Revenue</a>
                                 <a href="#"
                                     class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Report</a>
                             </div>
                         </div>
                     </div>

                     <!-- users -->
                     <div x-data="{ open: false }" x-cloak class="relative">
                         <a @mouseenter="open = true" @mouseleave="open = false"
                             class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                             href="#">
                             <svg width="20" height="20" class="fill-current text-[#1C274C] dark:text-white"
                                 viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                 <path fill-rule="evenodd" clip-rule="evenodd"
                                     d="M12 1.25C9.37665 1.25 7.25 3.37665 7.25 6C7.25 8.62335 9.37665 10.75 12 10.75C14.6234 10.75 16.75 8.62335 16.75 6C16.75 3.37665 14.6234 1.25 12 1.25ZM8.75 6C8.75 4.20507 10.2051 2.75 12 2.75C13.7949 2.75 15.25 4.20507 15.25 6C15.25 7.79493 13.7949 9.25 12 9.25C10.2051 9.25 8.75 7.79493 8.75 6Z"
                                     class="fill-current" />
                                 <path
                                     d="M18 3.25C17.5858 3.25 17.25 3.58579 17.25 4C17.25 4.41421 17.5858 4.75 18 4.75C19.3765 4.75 20.25 5.65573 20.25 6.5C20.25 7.34427 19.3765 8.25 18 8.25C17.5858 8.25 17.25 8.58579 17.25 9C17.25 9.41421 17.5858 9.75 18 9.75C19.9372 9.75 21.75 8.41715 21.75 6.5C21.75 4.58285 19.9372 3.25 18 3.25Z"
                                     class="fill-current" />
                                 <path
                                     d="M6.75 4C6.75 3.58579 6.41421 3.25 6 3.25C4.06278 3.25 2.25 4.58285 2.25 6.5C2.25 8.41715 4.06278 9.75 6 9.75C6.41421 9.75 6.75 9.41421 6.75 9C6.75 8.58579 6.41421 8.25 6 8.25C4.62351 8.25 3.75 7.34427 3.75 6.5C3.75 5.65573 4.62351 4.75 6 4.75C6.41421 4.75 6.75 4.41421 6.75 4Z"
                                     class="fill-current" />
                                 <path fill-rule="evenodd" clip-rule="evenodd"
                                     d="M12 12.25C10.2157 12.25 8.56645 12.7308 7.34133 13.5475C6.12146 14.3608 5.25 15.5666 5.25 17C5.25 18.4334 6.12146 19.6392 7.34133 20.4525C8.56645 21.2692 10.2157 21.75 12 21.75C13.7843 21.75 15.4335 21.2692 16.6587 20.4525C17.8785 19.6392 18.75 18.4334 18.75 17C18.75 15.5666 17.8785 14.3608 16.6587 13.5475C15.4335 12.7308 13.7843 12.25 12 12.25ZM6.75 17C6.75 16.2242 7.22169 15.4301 8.17338 14.7956C9.11984 14.1646 10.4706 13.75 12 13.75C13.5294 13.75 14.8802 14.1646 15.8266 14.7956C16.7783 15.4301 17.25 16.2242 17.25 17C17.25 17.7758 16.7783 18.5699 15.8266 19.2044C14.8802 19.8354 13.5294 20.25 12 20.25C10.4706 20.25 9.11984 19.8354 8.17338 19.2044C7.22169 18.5699 6.75 17.7758 6.75 17Z"
                                     class="fill-current" />
                                 <path
                                     d="M19.2674 13.8393C19.3561 13.4347 19.7561 13.1787 20.1607 13.2674C21.1225 13.4783 21.9893 13.8593 22.6328 14.3859C23.2758 14.912 23.75 15.6352 23.75 16.5C23.75 17.3648 23.2758 18.088 22.6328 18.6141C21.9893 19.1407 21.1225 19.5217 20.1607 19.7326C19.7561 19.8213 19.3561 19.5653 19.2674 19.1607C19.1787 18.7561 19.4347 18.3561 19.8393 18.2674C20.6317 18.0936 21.2649 17.7952 21.6829 17.4532C22.1014 17.1108 22.25 16.7763 22.25 16.5C22.25 16.2237 22.1014 15.8892 21.6829 15.5468C21.2649 15.2048 20.6317 14.9064 19.8393 14.7326C19.4347 14.6439 19.1787 14.2439 19.2674 13.8393Z"
                                     class="fill-current" />
                                 <path
                                     d="M3.83935 13.2674C4.24395 13.1787 4.64387 13.4347 4.73259 13.8393C4.82132 14.2439 4.56525 14.6439 4.16065 14.7326C3.36829 14.9064 2.73505 15.2048 2.31712 15.5468C1.89863 15.8892 1.75 16.2237 1.75 16.5C1.75 16.7763 1.89863 17.1108 2.31712 17.4532C2.73505 17.7952 3.36829 18.0936 4.16065 18.2674C4.56525 18.3561 4.82132 18.7561 4.73259 19.1607C4.64387 19.5653 4.24395 19.8213 3.83935 19.7326C2.87746 19.5217 2.0107 19.1407 1.36719 18.6141C0.724248 18.088 0.25 17.3648 0.25 16.5C0.25 15.6352 0.724248 14.912 1.36719 14.3859C2.0107 13.8593 2.87746 13.4783 3.83935 13.2674Z"
                                     class="fill-current" />
                             </svg>

                             <span
                                 class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Users</span>
                             <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                 <path fill-rule="evenodd"
                                     d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                     clip-rule="evenodd" />
                             </svg>
                         </a>
                         <!-- users Dropdown Menu -->
                         <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                             class="absolute z-10 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                             <div class="py-1">
                                 <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false"
                                     class="relative">
                                     <a href="{{ route('companies.index') }}"
                                         class="flex justify-between items-center block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                         Companies
                                         <svg class="h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                             <path fill-rule="evenodd"
                                                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                 clip-rule="evenodd" />
                                         </svg>
                                     </a>
                                     <div x-show="open"
                                         class="absolute right-full top-0 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95">
                                         <a href="{{ route('companies.index') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                             Companies List
                                         </a>

                                         <a href="{{ route('companiesnew.new') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                             Add Company
                                         </a>
                                     </div>
                                 </div>

                                 <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false"
                                     class="relative">
                                     <a href="{{ route('agents.index') }}"
                                         class="flex justify-between items-center block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                         Agents
                                         <svg class="h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                             <path fill-rule="evenodd"
                                                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                 clip-rule="evenodd" />
                                         </svg>
                                     </a>
                                     <div x-show="open"
                                         class="absolute right-full top-0 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95">
                                         <a href="{{ route('agents.index') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                             Agent List
                                         </a>
                                         <a href="{{ route('agentsnew.new') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                             Add Agent
                                         </a>
                                     </div>
                                 </div>

                                 <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false"
                                     class="relative">
                                     <a href="{{ route('clients.list') }}"
                                         class="flex justify-between items-center block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                         Clients
                                         <svg class="h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                             <path fill-rule="evenodd"
                                                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                 clip-rule="evenodd" />
                                         </svg>
                                     </a>
                                     <div x-show="open"
                                         class="absolute right-full top-0 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95">
                                         <a href="{{ route('clients.list') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Clients
                                             List</a>
                                         <a href="{{ route('clients.create') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Add
                                             Client</a>
                                     </div>
                                 </div>
                             </div>

                         </div>

                     </div>
                     <!-- Admins -->
                     <div x-data="{ open: false }" x-cloak class="relative">
                         <a @mouseenter="open = true" @mouseleave="open = false"
                             class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                             href="#">
                             <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg" class="text-[#1C274C] dark:text-white">
                                 <path
                                     d="M15.5 7.5C15.5 9.433 13.933 11 12 11C10.067 11 8.5 9.433 8.5 7.5C8.5 5.567 10.067 4 12 4C13.933 4 15.5 5.567 15.5 7.5Z"
                                     fill="currentColor" />
                                 <path
                                     d="M18 16.5C18 18.433 15.3137 20 12 20C8.68629 20 6 18.433 6 16.5C6 14.567 8.68629 13 12 13C15.3137 13 18 14.567 18 16.5Z"
                                     fill="currentColor" />
                                 <path
                                     d="M7.12205 5C7.29951 5 7.47276 5.01741 7.64005 5.05056C7.23249 5.77446 7 6.61008 7 7.5C7 8.36825 7.22131 9.18482 7.61059 9.89636C7.45245 9.92583 7.28912 9.94126 7.12205 9.94126C5.70763 9.94126 4.56102 8.83512 4.56102 7.47063C4.56102 6.10614 5.70763 5 7.12205 5Z"
                                     fill="currentColor" />
                                 <path
                                     d="M5.44734 18.986C4.87942 18.3071 4.5 17.474 4.5 16.5C4.5 15.5558 4.85657 14.744 5.39578 14.0767C3.4911 14.2245 2 15.2662 2 16.5294C2 17.8044 3.5173 18.8538 5.44734 18.986Z"
                                     fill="currentColor" />
                                 <path
                                     d="M16.9999 7.5C16.9999 8.36825 16.7786 9.18482 16.3893 9.89636C16.5475 9.92583 16.7108 9.94126 16.8779 9.94126C18.2923 9.94126 19.4389 8.83512 19.4389 7.47063C19.4389 6.10614 18.2923 5 16.8779 5C16.7004 5 16.5272 5.01741 16.3599 5.05056C16.7674 5.77446 16.9999 6.61008 16.9999 7.5Z"
                                     fill="currentColor" />
                                 <path
                                     d="M18.5526 18.986C20.4826 18.8538 21.9999 17.8044 21.9999 16.5294C21.9999 15.2662 20.5088 14.2245 18.6041 14.0767C19.1433 14.744 19.4999 15.5558 19.4999 16.5C19.4999 17.474 19.1205 18.3071 18.5526 18.986Z"
                                     fill="currentColor" />
                             </svg>


                             <span
                                 class="pl-3 text-black pl-3  dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Admins</span>

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
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                             <div class="py-1">
                                 <a href="{{ route('admin.users.index') }}"
                                     class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                     Admins List
                                 </a>

                             </div>
                         </div>
                     </div>
                     @endif

                     @if(Auth::user()->role == 'company')
                     <!-- company dashboard -->
                     <div x-data="{ open: false }" x-cloak class="relative">
                         <a @mouseenter="open = true" @mouseleave="open = false"
                             class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                             href="#">
                             <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg" class="shrink-0">
                                 <path opacity="0.5"
                                     d="M2 12.2039C2 9.91549 2 8.77128 2.5192 7.82274C3.0384 6.87421 3.98695 6.28551 5.88403 5.10813L7.88403 3.86687C9.88939 2.62229 10.8921 2 12 2C13.1079 2 14.1106 2.62229 16.116 3.86687L18.116 5.10812C20.0131 6.28551 20.9616 6.87421 21.4808 7.82274C22 8.77128 22 9.91549 22 12.2039V13.725C22 17.6258 22 19.5763 20.8284 20.7881C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.7881C2 19.5763 2 17.6258 2 13.725V12.2039Z"
                                     fill="currentColor"></path>
                                 <path
                                     d="M9 17.25C8.58579 17.25 8.25 17.5858 8.25 18C8.25 18.4142 8.58579 18.75 9 18.75H15C15.4142 18.75 15.75 18.4142 15.75 18C15.75 17.5858 15.4142 17.25 15 17.25H9Z"
                                     fill="currentColor"></path>
                             </svg> <span
                                 class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Dashboard</span>
                             <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                 <path fill-rule="evenodd"
                                     d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                     clip-rule="evenodd" />
                             </svg>
                         </a>
                         <!-- Dropdown Menu -->
                         <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                             class="absolute  z-10 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                             <div class="py-1">
                                 <a href="{{ route('dashboard') }}"
                                     class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Revenue</a>
                                 <a href="#"
                                     class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Report</a>
                             </div>
                         </div>
                     </div><!-- ./company dashboard -->

                     <!-- company tasks -->
                     <div x-data="{ open: false }" x-cloak class="relative">
                         <a @mouseenter="open = true" @mouseleave="open = false"
                             class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                             href="#">
                             <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg" class="shrink-0">
                                 <path opacity="0.5"
                                     d="M2 12.2039C2 9.91549 2 8.77128 2.5192 7.82274C3.0384 6.87421 3.98695 6.28551 5.88403 5.10813L7.88403 3.86687C9.88939 2.62229 10.8921 2 12 2C13.1079 2 14.1106 2.62229 16.116 3.86687L18.116 5.10812C20.0131 6.28551 20.9616 6.87421 21.4808 7.82274C22 8.77128 22 9.91549 22 12.2039V13.725C22 17.6258 22 19.5763 20.8284 20.7881C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.7881C2 19.5763 2 17.6258 2 13.725V12.2039Z"
                                     fill="currentColor"></path>
                                 <path
                                     d="M9 17.25C8.58579 17.25 8.25 17.5858 8.25 18C8.25 18.4142 8.58579 18.75 9 18.75H15C15.4142 18.75 15.75 18.4142 15.75 18C15.75 17.5858 15.4142 17.25 15 17.25H9Z"
                                     fill="currentColor"></path>
                             </svg> <span
                                 class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Tasks</span>
                             <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                 <path fill-rule="evenodd"
                                     d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                     clip-rule="evenodd" />
                             </svg>
                         </a>
                         <!-- Dropdown Menu -->
                         <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                             class="absolute  z-10 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                             <div class="py-1">
                                 <a href="{{ route('tasks.index') }}"
                                     class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Panding
                                     Tasks</a>
                                 <a href="{{ route('tasks.index') }}"
                                     class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Tasks
                                     List</a>
                             </div>
                         </div>
                     </div><!-- ./company Tasks -->

                     <!-- company finances -->
                     <div x-data="{ open: false }" x-cloak class="relative">
                         <a @mouseenter="open = true" @mouseleave="open = false"
                             class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                             href="#">
                             <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg" class="shrink-0">
                                 <path opacity="0.5"
                                     d="M2 12.2039C2 9.91549 2 8.77128 2.5192 7.82274C3.0384 6.87421 3.98695 6.28551 5.88403 5.10813L7.88403 3.86687C9.88939 2.62229 10.8921 2 12 2C13.1079 2 14.1106 2.62229 16.116 3.86687L18.116 5.10812C20.0131 6.28551 20.9616 6.87421 21.4808 7.82274C22 8.77128 22 9.91549 22 12.2039V13.725C22 17.6258 22 19.5763 20.8284 20.7881C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.7881C2 19.5763 2 17.6258 2 13.725V12.2039Z"
                                     fill="currentColor"></path>
                                 <path
                                     d="M9 17.25C8.58579 17.25 8.25 17.5858 8.25 18C8.25 18.4142 8.58579 18.75 9 18.75H15C15.4142 18.75 15.75 18.4142 15.75 18C15.75 17.5858 15.4142 17.25 15 17.25H9Z"
                                     fill="currentColor"></path>
                             </svg> <span
                                 class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Finances</span>
                             <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                 <path fill-rule="evenodd"
                                     d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                     clip-rule="evenodd" />
                             </svg>
                         </a>
                         <!-- Dropdown Menu -->
                         <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                             class="absolute  z-10 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                             <div class="py-1">
                                 <a href="{{ route('invoices.company.agents') }}"
                                     class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                     Invoices List</a>
                                 <a href="{{ route('invoice.create') }}"
                                     class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                     Create Invoice</a>
                             </div>
                         </div>
                     </div><!-- ./company finances -->

                     <!-- users -->
                     <div x-data="{ open: false }" x-cloak class="relative">
                         <a @mouseenter="open = true" @mouseleave="open = false"
                             class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
                             href="#">
                             <svg width="20" height="20" class="fill-current text-[#1C274C] dark:text-white"
                                 viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                 <path fill-rule="evenodd" clip-rule="evenodd"
                                     d="M12 1.25C9.37665 1.25 7.25 3.37665 7.25 6C7.25 8.62335 9.37665 10.75 12 10.75C14.6234 10.75 16.75 8.62335 16.75 6C16.75 3.37665 14.6234 1.25 12 1.25ZM8.75 6C8.75 4.20507 10.2051 2.75 12 2.75C13.7949 2.75 15.25 4.20507 15.25 6C15.25 7.79493 13.7949 9.25 12 9.25C10.2051 9.25 8.75 7.79493 8.75 6Z"
                                     class="fill-current" />
                                 <path
                                     d="M18 3.25C17.5858 3.25 17.25 3.58579 17.25 4C17.25 4.41421 17.5858 4.75 18 4.75C19.3765 4.75 20.25 5.65573 20.25 6.5C20.25 7.34427 19.3765 8.25 18 8.25C17.5858 8.25 17.25 8.58579 17.25 9C17.25 9.41421 17.5858 9.75 18 9.75C19.9372 9.75 21.75 8.41715 21.75 6.5C21.75 4.58285 19.9372 3.25 18 3.25Z"
                                     class="fill-current" />
                                 <path
                                     d="M6.75 4C6.75 3.58579 6.41421 3.25 6 3.25C4.06278 3.25 2.25 4.58285 2.25 6.5C2.25 8.41715 4.06278 9.75 6 9.75C6.41421 9.75 6.75 9.41421 6.75 9C6.75 8.58579 6.41421 8.25 6 8.25C4.62351 8.25 3.75 7.34427 3.75 6.5C3.75 5.65573 4.62351 4.75 6 4.75C6.41421 4.75 6.75 4.41421 6.75 4Z"
                                     class="fill-current" />
                                 <path fill-rule="evenodd" clip-rule="evenodd"
                                     d="M12 12.25C10.2157 12.25 8.56645 12.7308 7.34133 13.5475C6.12146 14.3608 5.25 15.5666 5.25 17C5.25 18.4334 6.12146 19.6392 7.34133 20.4525C8.56645 21.2692 10.2157 21.75 12 21.75C13.7843 21.75 15.4335 21.2692 16.6587 20.4525C17.8785 19.6392 18.75 18.4334 18.75 17C18.75 15.5666 17.8785 14.3608 16.6587 13.5475C15.4335 12.7308 13.7843 12.25 12 12.25ZM6.75 17C6.75 16.2242 7.22169 15.4301 8.17338 14.7956C9.11984 14.1646 10.4706 13.75 12 13.75C13.5294 13.75 14.8802 14.1646 15.8266 14.7956C16.7783 15.4301 17.25 16.2242 17.25 17C17.25 17.7758 16.7783 18.5699 15.8266 19.2044C14.8802 19.8354 13.5294 20.25 12 20.25C10.4706 20.25 9.11984 19.8354 8.17338 19.2044C7.22169 18.5699 6.75 17.7758 6.75 17Z"
                                     class="fill-current" />
                                 <path
                                     d="M19.2674 13.8393C19.3561 13.4347 19.7561 13.1787 20.1607 13.2674C21.1225 13.4783 21.9893 13.8593 22.6328 14.3859C23.2758 14.912 23.75 15.6352 23.75 16.5C23.75 17.3648 23.2758 18.088 22.6328 18.6141C21.9893 19.1407 21.1225 19.5217 20.1607 19.7326C19.7561 19.8213 19.3561 19.5653 19.2674 19.1607C19.1787 18.7561 19.4347 18.3561 19.8393 18.2674C20.6317 18.0936 21.2649 17.7952 21.6829 17.4532C22.1014 17.1108 22.25 16.7763 22.25 16.5C22.25 16.2237 22.1014 15.8892 21.6829 15.5468C21.2649 15.2048 20.6317 14.9064 19.8393 14.7326C19.4347 14.6439 19.1787 14.2439 19.2674 13.8393Z"
                                     class="fill-current" />
                                 <path
                                     d="M3.83935 13.2674C4.24395 13.1787 4.64387 13.4347 4.73259 13.8393C4.82132 14.2439 4.56525 14.6439 4.16065 14.7326C3.36829 14.9064 2.73505 15.2048 2.31712 15.5468C1.89863 15.8892 1.75 16.2237 1.75 16.5C1.75 16.7763 1.89863 17.1108 2.31712 17.4532C2.73505 17.7952 3.36829 18.0936 4.16065 18.2674C4.56525 18.3561 4.82132 18.7561 4.73259 19.1607C4.64387 19.5653 4.24395 19.8213 3.83935 19.7326C2.87746 19.5217 2.0107 19.1407 1.36719 18.6141C0.724248 18.088 0.25 17.3648 0.25 16.5C0.25 15.6352 0.724248 14.912 1.36719 14.3859C2.0107 13.8593 2.87746 13.4783 3.83935 13.2674Z"
                                     class="fill-current" />
                             </svg>

                             <span
                                 class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Users</span>
                             <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                 <path fill-rule="evenodd"
                                     d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                     clip-rule="evenodd" />
                             </svg>
                         </a>
                         <!-- users Dropdown Menu -->
                         <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
                             class="absolute z-10 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                             <div class="py-1">


                                 <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false"
                                     class="relative">
                                     <a href="{{ route('agents.index') }}"
                                         class="flex justify-between items-center block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                         Agents
                                         <svg class="h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                             <path fill-rule="evenodd"
                                                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                 clip-rule="evenodd" />
                                         </svg>
                                     </a>
                                     <div x-show="open"
                                         class="absolute left-full top-0 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95">
                                         <a href="{{ route('agents.index') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                             Agent List
                                         </a>
                                         <a href="{{ route('agentsnew.new') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                             Add Agent
                                         </a>
                                     </div>
                                 </div>

                                 <div x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false"
                                     class="relative">
                                     <a href="{{ route('clients.list') }}"
                                         class="flex justify-between items-center block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                                         Clients
                                         <svg class="h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
                                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                             <path fill-rule="evenodd"
                                                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                 clip-rule="evenodd" />
                                         </svg>
                                     </a>
                                     <div x-show="open"
                                         class="absolute left-full top-0 mt-2 w-40 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95">
                                         <a href="{{ route('clients.list') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Clients
                                             List</a>
                                         <a href="{{ route('clients.create') }}"
                                             class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Add
                                             Client</a>
                                     </div>
                                 </div>
                             </div>

                         </div>

                     </div><!-- ./users -->
                     @endif



                     @if(Auth()->user()->role === 'agent')
                     <a href="{{ route('invoice.create') }}" class="btn btn-success ml-2">Create Invoice</a>
                     @endif
                 </div>
             </div>
         </nav>



         <!-- Page Content -->
         <main class="p-4 mobile-m-5 ">
             <!-- Your main content goes here -->
             {{ $slot }}
         </main>



         <!-- Footer -->
         @include('layouts.footer')


     </div>

 </div>

 <!-- Scripts -->
 <script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('logo').classList.add('fade-in-loaded');
    document.getElementById('appName').classList.add('fade-in-loaded');
});
 </script>