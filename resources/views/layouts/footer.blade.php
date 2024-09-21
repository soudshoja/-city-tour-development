<!-- start footer section -->
<!-- desktok footer section -->
<div class="CityDisplaayNone mt-auto p-6 pt-0 text-center dark:text-white-dark ltr:sm:text-left rtl:sm:text-right">
    © <span id="footer-year">2024</span>. city tour.
</div>
<!-- desktok footer section end-->
<!-- Mobile footer section -->
<div class="CityDisplaayNoneDesk mt-auto p-6 pt-0 text-center dark:text-white-dark ltr:sm:text-left rtl:sm:text-right">
    <!-- Mobile navigation bar -->
    <div class="fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-black border-t dark:border-white dark:text-white">
        <div class="flex justify-around items-center p-4 mb-6">
            <!-- Icon 1 Chat -->
            <div class="flex flex-col items-center space-y-1">
                <a href="#"
                    class="block hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60">
                    <a href="javascript:;"
                        class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60"
                        @click="toggle">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M22 10C22.0185 10.7271 22 11.0542 22 12C22 15.7712 22 17.6569 20.8284 18.8284C19.6569 20 17.7712 20 14 20H10C6.22876 20 4.34315 20 3.17157 18.8284C2 17.6569 2 15.7712 2 12C2 8.22876 2 6.34315 3.17157 5.17157C4.34315 4 6.22876 4 10 4H13"
                                stroke="#0152A1" stroke-width="1.5" stroke-linecap="round"></path>
                            <path
                                d="M6 8L8.1589 9.79908C9.99553 11.3296 10.9139 12.0949 12 12.0949C13.0861 12.0949 14.0045 11.3296 15.8411 9.79908"
                                stroke="#0152A1" stroke-width="1.5" stroke-linecap="round"></path>
                            <circle cx="19" cy="5" r="3" stroke="#0152A1" stroke-width="1.5"></circle>
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
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M19.0001 9.7041V9C19.0001 5.13401 15.8661 2 12.0001 2C8.13407 2 5.00006 5.13401 5.00006 9V9.7041C5.00006 10.5491 4.74995 11.3752 4.28123 12.0783L3.13263 13.8012C2.08349 15.3749 2.88442 17.5139 4.70913 18.0116C9.48258 19.3134 14.5175 19.3134 19.291 18.0116C21.1157 17.5139 21.9166 15.3749 20.8675 13.8012L19.7189 12.0783C19.2502 11.3752 19.0001 10.5491 19.0001 9.7041Z"
                                stroke="#0152A1" stroke-width="1.5"></path>
                            <path d="M7.5 19C8.15503 20.7478 9.92246 22 12 22C14.0775 22 15.845 20.7478 16.5 19"
                                stroke="#0152A1" stroke-width="1.5" stroke-linecap="round"></path>
                            <path d="M12 6V10" stroke="#0152A1" stroke-width="1.5" stroke-linecap="round">
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
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="6" r="4" stroke="#0152A1" stroke-width="1.5">
                        </circle>
                        <path
                            d="M15 20.6151C14.0907 20.8619 13.0736 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C15.866 13 19 14.7909 19 17C19 17.3453 18.9234 17.6804 18.7795 18"
                            stroke="#0152A1" stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                </a>
            </div>

            <!-- Icon 3 Chat -->
            <div class="flex flex-col items-center space-y-1">
                <a href="#"
                    class="block hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60">
                    <a href="javascript:;"
                        class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60"
                        @click="toggle">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M22 10C22.0185 10.7271 22 11.0542 22 12C22 15.7712 22 17.6569 20.8284 18.8284C19.6569 20 17.7712 20 14 20H10C6.22876 20 4.34315 20 3.17157 18.8284C2 17.6569 2 15.7712 2 12C2 8.22876 2 6.34315 3.17157 5.17157C4.34315 4 6.22876 4 10 4H13"
                                stroke="#0152A1" stroke-width="1.5" stroke-linecap="round"></path>
                            <path
                                d="M6 8L8.1589 9.79908C9.99553 11.3296 10.9139 12.0949 12 12.0949C13.0861 12.0949 14.0045 11.3296 15.8411 9.79908"
                                stroke="#0152A1" stroke-width="1.5" stroke-linecap="round"></path>
                            <circle cx="19" cy="5" r="3" stroke="#0152A1" stroke-width="1.5"></circle>
                        </svg>
                    </a>
                </a>
            </div>
            <!-- Icon 4 Notification -->
            <div class="flex flex-col items-center space-y-1">
                <a href="#"
                    class="block hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60">
                    <a href="javascript:;"
                        class="relative block rounded-full bg-white-light/40 p-2 hover:bg-white-light/90 hover:text-primary dark:bg-dark/40 dark:hover:bg-dark/60"
                        @click="toggle">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M19.0001 9.7041V9C19.0001 5.13401 15.8661 2 12.0001 2C8.13407 2 5.00006 5.13401 5.00006 9V9.7041C5.00006 10.5491 4.74995 11.3752 4.28123 12.0783L3.13263 13.8012C2.08349 15.3749 2.88442 17.5139 4.70913 18.0116C9.48258 19.3134 14.5175 19.3134 19.291 18.0116C21.1157 17.5139 21.9166 15.3749 20.8675 13.8012L19.7189 12.0783C19.2502 11.3752 19.0001 10.5491 19.0001 9.7041Z"
                                stroke="#0152A1" stroke-width="1.5"></path>
                            <path d="M7.5 19C8.15503 20.7478 9.92246 22 12 22C14.0775 22 15.845 20.7478 16.5 19"
                                stroke="#0152A1" stroke-width="1.5" stroke-linecap="round"></path>
                            <path d="M12 6V10" stroke="#0152A1" stroke-width="1.5" stroke-linecap="round">
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
<!-- Mobile footer section ends -->




<!-- end footer section -->