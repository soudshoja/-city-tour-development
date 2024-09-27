<!-- start footer section -->
<!-- desktok footer section -->
<div class="CityDisplaayNone mt-auto p-6 pt-0 text-center dark:text-white-dark ltr:sm:text-left rtl:sm:text-right">
    © <span id="footer-year">2024</span>. city tour.
</div>
<!-- desktok footer section end-->
<!-- Mobile footer section -->
<div class="CityDisplaayNoneDesk">
    <!-- Mobile Icons -->
    <div class="fixed top-40 end-0 flex flex-col space-y-2 z-50">

        <!-- Dark Mode Toggle Button -->
        <button x-cloak
            @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode); document.documentElement.classList.toggle('dark', darkMode);"
            class="border-l-2 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none relative flex items-center justify-between w-14 h-10 bg-gray-200 dark:bg-gray-700 rounded-l-full transition-colors duration-300 ease-in-out sm:w-12 sm:h-6"
            :class="{ 'animate-pulse': darkMode }">

            <div class="w-8 h-8 bg-white dark:bg-gray-200 rounded-full flex items-center justify-center">
                <svg width="26" height="26" fill="none" stroke="#0d324d" class="text-gray-500 dark:text-gray-800"
                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="5" stroke-width="1.5"></circle>
                    <path d="M12 2V4" stroke-width="1.5" stroke-linecap="round"></path>
                    <path d="M12 20V22" stroke-width="1.5" stroke-linecap="round"></path>
                    <path d="M4 12L2 12" stroke-width="1.5" stroke-linecap="round"></path>
                    <path d="M22 12L20 12" stroke-width="1.5" stroke-linecap="round"></path>
                    <path opacity="0.5" d="M19.7778 4.22266L17.5558 6.25424" stroke-width="1.5" stroke-linecap="round">
                    </path>
                    <path opacity="0.5" d="M4.22217 4.22266L6.44418 6.25424" stroke-width="1.5" stroke-linecap="round">
                    </path>
                    <path opacity="0.5" d="M6.44434 17.5557L4.22211 19.7779" stroke-width="1.5" stroke-linecap="round">
                    </path>
                    <path opacity="0.5" d="M19.7778 19.7773L17.5558 17.5551" stroke-width="1.5" stroke-linecap="round">
                    </path>
                </svg>
            </div>
        </button>


        <!-- Sidebar Toggle Button -->
        <button x-cloak @click="sidebarOpen = !sidebarOpen"
            class="border-l-2 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none relative flex items-center justify-between w-14 h-10 bg-gray-200 dark:bg-gray-700 rounded-l-full transition-colors duration-300 ease-in-out sm:w-12 sm:h-6 mt-2">

            <div class="w-8 h-8 bg-white dark:bg-gray-200 rounded-full flex items-center justify-center">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 10L11 10M5 10H7" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                    <path d="M5 18H13M19 18H17" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                    <path d="M19 14L5 14" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                    <path d="M19 6L5 6" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </div>
        </button>


    </div>



    <div class="mt-auto p-6 pt-0 text-center dark:text-white-dark ltr:sm:text-left rtl:sm:text-right">
        <!-- Mobile navigation bar -->
        <div
            class="fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-black border-t dark:border-white dark:text-white">
            <div class="flex justify-around items-center p-4 mb-6">
                <!-- Icon 1 Chat -->
                <div class="flex flex-col items-center space-y-1">
                    <a href="{{ route('dashboard') }}"
                        class="block hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60">
                        <a href="javascript:;"
                            class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60"
                            @click="toggle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M22 22L2 22" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M2 11L10.1259 4.49931C11.2216 3.62279 12.7784 3.62279 13.8741 4.49931L22 11"
                                    stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path opacity="0.5"
                                    d="M15.5 5.5V3.5C15.5 3.22386 15.7239 3 16 3H18.5C18.7761 3 19 3.22386 19 3.5V8.5"
                                    stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M4 22V9.5" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M20 22V9.5" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path opacity="0.5"
                                    d="M15 22V17C15 15.5858 15 14.8787 14.5607 14.4393C14.1213 14 13.4142 14 12 14C10.5858 14 9.87868 14 9.43934 14.4393C9 14.8787 9 15.5858 9 17V22"
                                    stroke="#1C274C" stroke-width="1.5" />
                                <path opacity="0.5"
                                    d="M14 9.5C14 10.6046 13.1046 11.5 12 11.5C10.8954 11.5 10 10.6046 10 9.5C10 8.39543 10.8954 7.5 12 7.5C13.1046 7.5 14 8.39543 14 9.5Z"
                                    stroke="#1C274C" stroke-width="1.5" />
                            </svg>

                        </a>
                    </a>
                </div>
                <!-- Icon 2 Notification -->
                <div class="flex flex-col items-center space-y-1">
                    <a href="#"
                        class="block hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60">
                        <a href="javascript:;"
                            class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60"
                            @click="toggle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                class="stroke-current text-gray-800 dark:text-gray-200">
                                <path
                                    d="M19.0001 9.7041V9C19.0001 5.13401 15.8661 2 12.0001 2C8.13407 2 5.00006 5.13401 5.00006 9V9.7041C5.00006 10.5491 4.74995 11.3752 4.28123 12.0783L3.13263 13.8012C2.08349 15.3749 2.88442 17.5139 4.70913 18.0116C9.48258 19.3134 14.5175 19.3134 19.291 18.0116C21.1157 17.5139 21.9166 15.3749 20.8675 13.8012L19.7189 12.0783C19.2502 11.3752 19.0001 10.5491 19.0001 9.7041Z"
                                    stroke="currentColor" stroke-width="1.5"></path>
                                <path d="M7.5 19C8.15503 20.7478 9.92246 22 12 22C14.0775 22 15.845 20.7478 16.5 19"
                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                <path d="M12 6V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                </path>
                            </svg>
                            <span class="absolute top-0 flex h-3 w-3 ltr:right-0 rtl:left-0">
                                <span
                                    class="absolute -top-[3px] inline-flex h-full w-full animate-ping rounded-full bg-success/50 opacity-75 ltr:-left-[3px] rtl:-right-[3px]"></span>
                                <span class="relative inline-flex h-[6px] w-[6px] rounded-full bg-success"></span>
                            </span>
                        </a>
                    </a>
                </div>



                <!-- Center icon (Profile) -->
                <div class="bg-white dark:bg-black rounded-full p-4 shadow-lg transform -translate-y-6">
                    <a href="{{ route('profile.edit') }}">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                            class="stroke-current text-gray-800 dark:text-gray-200">
                            <circle cx="12" cy="6" r="4" stroke="currentColor" stroke-width="1.5">
                            </circle>
                            <path
                                d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18"
                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        </svg>
                    </a>
                </div>

                <!-- Icon 1 Chat -->
                <div class="flex flex-col items-center space-y-1">
                    <a href="{{ route('dashboard') }}"
                        class="block hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60">
                        <a href="javascript:;"
                            class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60"
                            @click="toggle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M22 22L2 22" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M2 11L10.1259 4.49931C11.2216 3.62279 12.7784 3.62279 13.8741 4.49931L22 11"
                                    stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path opacity="0.5"
                                    d="M15.5 5.5V3.5C15.5 3.22386 15.7239 3 16 3H18.5C18.7761 3 19 3.22386 19 3.5V8.5"
                                    stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M4 22V9.5" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M20 22V9.5" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path opacity="0.5"
                                    d="M15 22V17C15 15.5858 15 14.8787 14.5607 14.4393C14.1213 14 13.4142 14 12 14C10.5858 14 9.87868 14 9.43934 14.4393C9 14.8787 9 15.5858 9 17V22"
                                    stroke="#1C274C" stroke-width="1.5" />
                                <path opacity="0.5"
                                    d="M14 9.5C14 10.6046 13.1046 11.5 12 11.5C10.8954 11.5 10 10.6046 10 9.5C10 8.39543 10.8954 7.5 12 7.5C13.1046 7.5 14 8.39543 14 9.5Z"
                                    stroke="#1C274C" stroke-width="1.5" />
                            </svg>

                        </a>
                    </a>
                </div>
                <!-- Icon 2 Notification -->
                <div class="flex flex-col items-center space-y-1">
                    <a href="#"
                        class="block hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60">
                        <a href="javascript:;"
                            class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60"
                            @click="toggle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                class="stroke-current text-gray-800 dark:text-gray-200">
                                <path
                                    d="M19.0001 9.7041V9C19.0001 5.13401 15.8661 2 12.0001 2C8.13407 2 5.00006 5.13401 5.00006 9V9.7041C5.00006 10.5491 4.74995 11.3752 4.28123 12.0783L3.13263 13.8012C2.08349 15.3749 2.88442 17.5139 4.70913 18.0116C9.48258 19.3134 14.5175 19.3134 19.291 18.0116C21.1157 17.5139 21.9166 15.3749 20.8675 13.8012L19.7189 12.0783C19.2502 11.3752 19.0001 10.5491 19.0001 9.7041Z"
                                    stroke="currentColor" stroke-width="1.5"></path>
                                <path d="M7.5 19C8.15503 20.7478 9.92246 22 12 22C14.0775 22 15.845 20.7478 16.5 19"
                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                <path d="M12 6V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                </path>
                            </svg>
                            <span class="absolute top-0 flex h-3 w-3 ltr:right-0 rtl:left-0">
                                <span
                                    class="absolute -top-[3px] inline-flex h-full w-full animate-ping rounded-full bg-success/50 opacity-75 ltr:-left-[3px] rtl:-right-[3px]"></span>
                                <span class="relative inline-flex h-[6px] w-[6px] rounded-full bg-success"></span>
                            </span>
                        </a>
                    </a>
                </div>

            </div>
        </div>


        <!-- Copyright section -->
        <div class="border-t border-t-gray border-t-[1px]
 fixed bottom-0 left-0 right-0 z-50 text-center text-sm py-2 bg-white dark:bg-black">
            © <span id="footer-year">2024</span>. city tour.
        </div>
    </div>
</div>

<!-- Mobile footer section ends -->




<!-- end footer section -->