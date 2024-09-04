<x-app-layout>
    <!-- Main Content of the page -->
    <div class="mb-6 grid gap-6 xl:grid-cols-3">

        <!-- 1st column -->
         <div class="panel h-full mb-5 p-2 xl:col-span-1">

                    <div
                        class="-mx-5 mb-5 flex items-start justify-between border-b border-[#e0e6ed] p-5 pt-0 dark:border-[#1b2e4b] dark:text-white-light">
                        <h5 class="text-lg font-semibold">Activity Log</h5>
                        <div x-data="dropdown" @click.outside="open = false" class="dropdown">
                            <a href="javascript:;" @click="toggle">
                                <svg class="h-5 w-5 text-black/70 hover:!text-primary dark:text-white/70"
                                    viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="5" cy="12" r="2" stroke="currentColor" stroke-width="1.5"></circle>
                                    <circle opacity="0.5" cx="12" cy="12" r="2" stroke="currentColor"
                                        stroke-width="1.5"></circle>
                                    <circle cx="19" cy="12" r="2" stroke="currentColor" stroke-width="1.5"></circle>
                                </svg>
                            </a>
                            <ul x-show="open" x-transition="" x-transition.duration.300ms=""
                                class="ltr:right-0 rtl:left-0" style="display: none;">
                                <li><a href="javascript:;" @click="toggle">View All</a></li>
                                <li><a href="javascript:;" @click="toggle">Mark as Read</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="perfect-scrollbar relative -mr-3 h-[360px] pr-3 ps">
                        <div class="space-y-7">
                            <div class="flex">
                                <div
                                    class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 ltr:mr-2 rtl:ml-2">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-secondary text-white shadow shadow-secondary">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="1.5" fill="none" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="font-semibold dark:text-white-light">
                                        New project created : <a href="javascript:;" class="text-success">[VRISTO Admin
                                            Template]</a>
                                    </h5>
                                    <p class="text-xs text-white-dark">27 Feb, 2020</p>
                                </div>
                            </div>
                            <div class="flex">
                                <div
                                    class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 ltr:mr-2 rtl:ml-2">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-success text-white shadow-success">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path opacity="0.5"
                                                d="M2 12C2 8.22876 2 6.34315 3.17157 5.17157C4.34315 4 6.22876 4 10 4H14C17.7712 4 19.6569 4 20.8284 5.17157C22 6.34315 22 8.22876 22 12C22 15.7712 22 17.6569 20.8284 18.8284C19.6569 20 17.7712 20 14 20H10C6.22876 20 4.34315 20 3.17157 18.8284C2 17.6569 2 15.7712 2 12Z"
                                                stroke="currentColor" stroke-width="1.5"></path>
                                            <path
                                                d="M6 8L8.1589 9.79908C9.99553 11.3296 10.9139 12.0949 12 12.0949C13.0861 12.0949 14.0045 11.3296 15.8411 9.79908L18 8"
                                                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="font-semibold dark:text-white-light">
                                        Mail sent to <a href="javascript:;" class="text-white-dark">HR</a> and
                                        <a href="javascript:;" class="text-white-dark">Admin</a>
                                    </h5>
                                    <p class="text-xs text-white-dark">28 Feb, 2020</p>
                                </div>
                            </div>
                            <div class="flex">
                                <div
                                    class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 ltr:mr-2 rtl:ml-2">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path opacity="0.5" d="M4 12.9L7.14286 16.5L15 7.5" stroke="currentColor"
                                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            </path>
                                            <path d="M20.0002 7.5625L11.4286 16.5625L11.0002 16" stroke="currentColor"
                                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            </path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="font-semibold dark:text-white-light">Server Logs Updated</h5>
                                    <p class="text-xs text-white-dark">27 Feb, 2020</p>
                                </div>
                            </div>
                            <div class="flex">
                                <div
                                    class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 ltr:mr-2 rtl:ml-2">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-danger text-white">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path opacity="0.5" d="M4 12.9L7.14286 16.5L15 7.5" stroke="currentColor"
                                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            </path>
                                            <path d="M20.0002 7.5625L11.4286 16.5625L11.0002 16" stroke="currentColor"
                                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            </path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="font-semibold dark:text-white-light">
                                        Task Completed : <a href="javascript:;" class="text-success">[Backup Files
                                            EOD]</a>
                                    </h5>
                                    <p class="text-xs text-white-dark">01 Mar, 2020</p>
                                </div>
                            </div>
                            <div class="flex">
                                <div
                                    class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 ltr:mr-2 rtl:ml-2">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-warning text-white">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path
                                                d="M15.3929 4.05365L14.8912 4.61112L15.3929 4.05365ZM19.3517 7.61654L18.85 8.17402L19.3517 7.61654ZM21.654 10.1541L20.9689 10.4592V10.4592L21.654 10.1541ZM3.17157 20.8284L3.7019 20.2981H3.7019L3.17157 20.8284ZM20.8284 20.8284L20.2981 20.2981L20.2981 20.2981L20.8284 20.8284ZM14 21.25H10V22.75H14V21.25ZM2.75 14V10H1.25V14H2.75ZM21.25 13.5629V14H22.75V13.5629H21.25ZM14.8912 4.61112L18.85 8.17402L19.8534 7.05907L15.8947 3.49618L14.8912 4.61112ZM22.75 13.5629C22.75 11.8745 22.7651 10.8055 22.3391 9.84897L20.9689 10.4592C21.2349 11.0565 21.25 11.742 21.25 13.5629H22.75ZM18.85 8.17402C20.2034 9.3921 20.7029 9.86199 20.9689 10.4592L22.3391 9.84897C21.9131 8.89241 21.1084 8.18853 19.8534 7.05907L18.85 8.17402ZM10.0298 2.75C11.6116 2.75 12.2085 2.76158 12.7405 2.96573L13.2779 1.5653C12.4261 1.23842 11.498 1.25 10.0298 1.25V2.75ZM15.8947 3.49618C14.8087 2.51878 14.1297 1.89214 13.2779 1.5653L12.7405 2.96573C13.2727 3.16993 13.7215 3.55836 14.8912 4.61112L15.8947 3.49618ZM10 21.25C8.09318 21.25 6.73851 21.2484 5.71085 21.1102C4.70476 20.975 4.12511 20.7213 3.7019 20.2981L2.64124 21.3588C3.38961 22.1071 4.33855 22.4392 5.51098 22.5969C6.66182 22.7516 8.13558 22.75 10 22.75V21.25ZM1.25 14C1.25 15.8644 1.24841 17.3382 1.40313 18.489C1.56076 19.6614 1.89288 20.6104 2.64124 21.3588L3.7019 20.2981C3.27869 19.8749 3.02502 19.2952 2.88976 18.2892C2.75159 17.2615 2.75 15.9068 2.75 14H1.25ZM14 22.75C15.8644 22.75 17.3382 22.7516 18.489 22.5969C19.6614 22.4392 20.6104 22.1071 21.3588 21.3588L20.2981 20.2981C19.8749 20.7213 19.2952 20.975 18.2892 21.1102C17.2615 21.2484 15.9068 21.25 14 21.25V22.75ZM21.25 14C21.25 15.9068 21.2484 17.2615 21.1102 18.2892C20.975 19.2952 20.7213 19.8749 20.2981 20.2981L21.3588 21.3588C22.1071 20.6104 22.4392 19.6614 22.5969 18.489C22.7516 17.3382 22.75 15.8644 22.75 14H21.25ZM2.75 10C2.75 8.09318 2.75159 6.73851 2.88976 5.71085C3.02502 4.70476 3.27869 4.12511 3.7019 3.7019L2.64124 2.64124C1.89288 3.38961 1.56076 4.33855 1.40313 5.51098C1.24841 6.66182 1.25 8.13558 1.25 10H2.75ZM10.0298 1.25C8.15538 1.25 6.67442 1.24842 5.51887 1.40307C4.34232 1.56054 3.39019 1.8923 2.64124 2.64124L3.7019 3.7019C4.12453 3.27928 4.70596 3.02525 5.71785 2.88982C6.75075 2.75158 8.11311 2.75 10.0298 2.75V1.25Z"
                                                fill="currentColor"></path>
                                            <path opacity="0.5"
                                                d="M13 2.5V5C13 7.35702 13 8.53553 13.7322 9.26777C14.4645 10 15.643 10 18 10H22"
                                                stroke="currentColor" stroke-width="1.5"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="font-semibold dark:text-white-light">
                                        Documents Submitted from <a href="javascript:;">Sara</a>
                                    </h5>
                                    <p class="text-xs text-white-dark">10 Mar, 2020</p>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="shrink-0 ltr:mr-2 rtl:ml-2">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-dark text-white">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg" class="h-4 w-4">
                                            <path opacity="0.5"
                                                d="M2 17C2 15.1144 2 14.1716 2.58579 13.5858C3.17157 13 4.11438 13 6 13H18C19.8856 13 20.8284 13 21.4142 13.5858C22 14.1716 22 15.1144 22 17C22 18.8856 22 19.8284 21.4142 20.4142C20.8284 21 19.8856 21 18 21H6C4.11438 21 3.17157 21 2.58579 20.4142C2 19.8284 2 18.8856 2 17Z"
                                                stroke="currentColor" stroke-width="1.5"></path>
                                            <path opacity="0.5"
                                                d="M2 6C2 4.11438 2 3.17157 2.58579 2.58579C3.17157 2 4.11438 2 6 2H18C19.8856 2 20.8284 2 21.4142 2.58579C22 3.17157 22 4.11438 22 6C22 7.88562 22 8.82843 21.4142 9.41421C20.8284 10 19.8856 10 18 10H6C4.11438 10 3.17157 10 2.58579 9.41421C2 8.82843 2 7.88562 2 6Z"
                                                stroke="currentColor" stroke-width="1.5"></path>
                                            <path d="M11 6H18" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round"></path>
                                            <path d="M6 6H8" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round"></path>
                                            <path d="M11 17H18" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round"></path>
                                            <path d="M6 17H8" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="font-semibold dark:text-white-light">Server rebooted successfully</h5>
                                    <p class="text-xs text-white-dark">06 Apr, 2020</p>
                                </div>
                            </div>
                        </div>
                        <div class="ps__rail-x" style="left: 0px; bottom: 0px;">
                            <div class="ps__thumb-x" tabindex="0" style="left: 0px; width: 0px;"></div>
                        </div>
                        <div class="ps__rail-y" style="top: 0px; right: 0px;">
                            <div class="ps__thumb-y" tabindex="0" style="top: 0px; height: 0px;"></div>
                        </div>
                    </div>
              

            <!-- Add more here -->
        </div>

        <!------------------------------------------------------------------------------->
        <!--  2nd columns -->
        <div class="panel h-full xl:col-span-2">
            <div class="mb-5 flex items-center dark:text-white-light">
                <h5 class="text-lg font-semibold">Agents List</h5>
            </div>
            <div
                class="apexcharts-yaxistooltip apexcharts-yaxistooltip-0 apexcharts-yaxistooltip-left apexcharts-theme-light">
                <div class="apexcharts-yaxistooltip-text"></div>
            </div>
        </div>

</x-app-layout>