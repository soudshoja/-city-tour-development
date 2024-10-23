<x-app-layout>

    <style>
    /* Apply dark mode specific styles only */
    @media (prefers-color-scheme: dark) {
        .dark-scrollbar {
            scrollbar-width: thin;
            /* Firefox */
            scrollbar-color: #444 #2d2d2d;
            /* Firefox */
        }

        .dark-scrollbar::-webkit-scrollbar {
            width: 8px;
            /* Width for Webkit browsers */
        }

        .dark-scrollbar::-webkit-scrollbar-track {
            background: #2d2d2d;
            /* Dark mode track color */
        }

        .dark-scrollbar::-webkit-scrollbar-thumb {
            background-color: #444;
            /* Dark mode thumb color */
            border-radius: 6px;
        }
    }

    /* Remove custom scrollbar styles in light mode */
    .dark-scrollbar {
        scrollbar-width: auto;
        /* Reset for light mode */
        scrollbar-color: initial;
        /* Reset for light mode */
    }

    .dark-scrollbar::-webkit-scrollbar {
        width: auto;
        /* Reset width for light mode */
    }

    .dark-scrollbar::-webkit-scrollbar-track,
    .dark-scrollbar::-webkit-scrollbar-thumb {
        background: initial;
        /* Reset colors for light mode */
    }
    </style>



    <style>
    /* Add this CSS to ensure the hidden class works */
    .hidden {
        display: none;
    }
    </style>




    <div>
        <!-- main content -->
        <!-- Tasks Revenue -->
        <div class="flex gap-4 mb-5">
            <div class="panel w-[5%] md:w-[5%] flex items-center justify-center">
                <h2 class="text-center text-sm font-semibold transform -rotate-90">
                    <span class="text-primary">Tasks</span> Revenue
                </h2>
            </div>

            <!-- Total Tasks Card -->
            <div class="flex rounded-lg overflow-hidden bg-blue-500 text-white shadow-md w-[31.66%] md:w-[31.66%]">
                <div class="flex items-center justify-center w-1/3 bg-blue-700 p-4">
                    <!-- Total Tasks Icon -->
                    <svg class="w-8 h-8" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M5.5 8C5.5 6.61929 6.61929 5.5 8 5.5H11C12.3807 5.5 13.5 6.61929 13.5 8V11C13.5 12.3807 12.3807 13.5 11 13.5H8C6.61929 13.5 5.5 12.3807 5.5 11V8Z"
                            stroke="#FFFFFF" stroke-width="1.5" />
                        <path
                            d="M5.5 19C5.5 17.6193 6.61929 16.5 8 16.5H11C12.3807 16.5 13.5 17.6193 13.5 19V22C13.5 23.3807 12.3807 24.5 11 24.5H8C6.61929 24.5 5.5 23.3807 5.5 22V19Z"
                            stroke="#FFFFFF" stroke-width="1.5" />
                        <path
                            d="M16.5 8C16.5 6.61929 17.6193 5.5 19 5.5H22C23.3807 5.5 24.5 6.61929 24.5 8V11C24.5 12.3807 23.3807 13.5 22 13.5H19C17.6193 13.5 16.5 12.3807 16.5 11V8Z"
                            stroke="#FFFFFF" stroke-width="1.5" />
                        <path
                            d="M16.5 19C16.5 17.6193 17.6193 16.5 19 16.5H22C23.3807 16.5 24.5 17.6193 24.5 19V22C24.5 23.3807 23.3807 24.5 22 24.5H19C17.6193 24.5 16.5 23.3807 16.5 22V19Z"
                            stroke="#FFFFFF" stroke-width="1.5" />
                    </svg>


                </div>
                <div class="flex flex-col items-center justify-center w-2/3 p-4">
                    <p class="text-3xl font-bold" id="totalTasks"></p>
                    <p class="text-sm">Total Tasks</p>
                </div>
            </div>

            <!-- Pending Tasks Card -->
            <div class="flex rounded-lg overflow-hidden bg-[#e7515a] text-white shadow-md w-[31.66%] md:w-[31.66%]">
                <div class="flex items-center justify-center w-1/3 bg-[#c03f4c] p-4">
                    <!-- Pending Tasks Icon -->
                    <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M12 12L9.0423 14.9289C6.11981 17.823 4.65857 19.27 5.06765 20.5185C5.10282 20.6258 5.14649 20.7302 5.19825 20.8307C5.80046 22 7.86697 22 12 22C16.133 22 18.1995 22 18.8017 20.8307C18.8535 20.7302 18.8972 20.6258 18.9323 20.5185C19.3414 19.27 17.8802 17.823 14.9577 14.9289L12 12ZM12 12L14.9577 9.07107C17.8802 6.177 19.3414 4.72997 18.9323 3.48149C18.8972 3.37417 18.8535 3.26977 18.8017 3.16926C18.1995 2 16.133 2 12 2C7.86697 2 5.80046 2 5.19825 3.16926C5.14649 3.26977 5.10282 3.37417 5.06765 3.48149C4.65857 4.72997 6.11981 6.177 9.0423 9.07107L12 12Z" />
                        <path d="M10 5.5H14" />
                        <path d="M10 18.5H14" />
                    </svg>
                </div>
                <div class="flex flex-col items-center justify-center w-2/3 p-4">
                    <p class="text-3xl font-bold" id="pendingTasks"></p>
                    <p class="text-sm">Pending Tasks</p>
                </div>
            </div>


            <!-- Completed Tasks Card -->
            <div class="flex rounded-lg overflow-hidden bg-green-500 text-white shadow-md w-[31.66%] md:w-[31.66%]">
                <div class="flex items-center justify-center w-1/3 bg-green-700 p-4">
                    <!-- Completed Tasks Icon -->
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path opacity="0.5"
                            d="M2 12C2 7.28595 2 4.92893 3.46447 3.46447C4.92893 2 7.28595 2 12 2C16.714 2 19.0711 2 20.5355 3.46447C22 4.92893 22 7.28595 22 12C22 16.714 22 19.0711 20.5355 20.5355C19.0711 22 16.714 22 12 22C7.28595 22 4.92893 22 3.46447 20.5355C2 19.0711 2 16.714 2 12Z"
                            stroke="#FFFFFF" stroke-width="1.5" />
                        <path d="M8.5 12.5L10.5 14.5L15.5 9.5" stroke="#FFFFFF" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>

                </div>
                <div class="flex flex-col items-center justify-center w-2/3 p-4">
                    <p class="text-3xl font-bold" id="completedTasks"></p>
                    <p class="text-sm">Completed Tasks</p>
                </div>
            </div>
        </div>

        <!-- ./ tasks revenue -->


        <!-- second row -->
        <div class="md:flex gap-2">
            <!-- income revenue -->
            <div class="panel w-[100%] md:w-[75%]">
                <div class="mb-5 flex justify-between">
                    <h5 class="text-lg font-semibold dark:text-white-light">
                        <span class="text-primary">Income</span> Revenue
                    </h5>
                    <i class="p-1 fas cursor-pointer fa-chevron-down" id="Income-icon"></i>

                </div>
                <p class="text-lg dark:text-white-light/90">Total Invoice Amount<span id="totalInvoiceAmount"
                        class="ml-2 text-primary"></span>
                </p>
                <div id="Income-content" class="relative overflow-hidden">
                    <div x-ref="revenueChart" id="revenueChart" class="rounded-lg bg-white dark:bg-[#0e1726]"
                        style="min-height: 340px;"></div>

                    <div class="flex justify-center gap-2">
                        <div
                            class="gap-2 flex items-center rounded-full bg-success/20 px-2 py-1 text-xs font-semibold text-success">
                            <svg width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M12 22.5c5.799 0 10.5-4.701 10.5-10.5S17.799 1.5 12 1.5 1.5 6.201 1.5 12 6.201 22.5 12 22.5zM16.03 9.47a.75.75 0 0 0-1.06-1.06l-4.72 4.72-1.72-1.72a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.06 0l5.25-5.25z"
                                    clip-rule="evenodd" />
                            </svg>


                            <sanp class="pl-1">Paid Invoices</sanp>
                            <p id="paidInvoices"
                                class="p-2 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success dark:bg-success dark:text-white-light">
                            </p>
                        </div>

                        <div
                            class="gap-2 flex items-center rounded-full bg-danger/20 px-2 py-1 text-xs font-semibold text-danger">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" fill="#e7515a" /> <!-- Dark circle background -->
                                <path d="M12 6v8" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" />
                                <!-- Exclamation line -->
                                <circle cx="12" cy="17" r="1" fill="#FFFFFF" /> <!-- Exclamation dot -->
                            </svg>

                            <sanp class="pl-1">Unpaid Invoices</sanp>
                            <p id="unpaidInvoices"
                                class="p-2 shrink-0 items-center justify-center rounded-xl bg-danger/10 text-danger dark:bg-danger dark:text-white-light">
                            </p>
                        </div>
                    </div>
                </div>






            </div>
            <!-- income revenue -->
            <!-- users revenue -->
            <div class="panel w-[100%] md:w-[25%] mt-5 sm:mt-0">
                <div class="mb-5 flex justify-between">
                    <h5 class="text-lg font-semibold dark:text-white-light">
                        <span class="text-primary">Users</span> Revenue
                    </h5>
                    <i class="p-1 fas cursor-pointer fa-chevron-down" id="toggle-icon"></i>

                </div>
                <div class="" id="toggle-content">
                    <!-- Content that you want to show or hide goes here -->
                    <div class="flex space-x-6 justify-between">

                        <div class="flex items-center space-x-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary dark:text-white-light">

                                <p id="totalAgents" class="dark:text-white-light"></p>
                            </div>
                            <div class="font-semibold">
                                <h5 class="text-xs text-[#506690]">Total Agents</h5>
                            </div>
                        </div>


                        <div class="flex items-center space-x-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success dark:bg-success dark:text-white-light">

                                <p id="totalClients" class="dark:text-white-light"></p>
                            </div>
                            <div class="font-semibold">
                                <h5 class="text-xs text-[#506690]">Total Clients</h5>
                            </div>
                        </div>
                    </div>
                    <!-- user activity -->
                    <div class="mt-5 h-full">
                        <div
                            class="-mx-5 mb-5 flex items-start justify-between border-y border-[#e0e6ed] p-5 pt-0 dark:border-[#1b2e4b] dark:text-white-light">
                            <h5 class="pt-5 text-lg font-semibold"><span class="text-primary">Users</span> Activity</h5>

                        </div>
                        <div class="perfect-scrollbar relative -mr-3 h-[360px] pr-3 ps overflow-y-auto dark-scrollbar">
                            <div class="space-y-7">
                                <div class="flex">
                                    <div
                                        class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2">
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
                                            New project created : <a href="javascript:;" class="text-success">[VRISTO
                                                Admin
                                                Template]</a>
                                        </h5>
                                        <p class="text-xs text-white-dark">27 Feb, 2020</p>
                                    </div>
                                </div>
                                <div class="flex">
                                    <div
                                        class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2 ">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-success text-white shadow-success">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path opacity="0.5"
                                                    d="M2 12C2 8.22876 2 6.34315 3.17157 5.17157C4.34315 4 6.22876 4 10 4H14C17.7712 4 19.6569 4 20.8284 5.17157C22 6.34315 22 8.22876 22 12C22 15.7712 22 17.6569 20.8284 18.8284C19.6569 20 17.7712 20 14 20H10C6.22876 20 4.34315 20 3.17157 18.8284C2 17.6569 2 15.7712 2 12Z"
                                                    stroke="currentColor" stroke-width="1.5"></path>
                                                <path
                                                    d="M6 8L8.1589 9.79908C9.99553 11.3296 10.9139 12.0949 12 12.0949C13.0861 12.0949 14.0045 11.3296 15.8411 9.79908L18 8"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                                </path>
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
                                        class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2 ">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path opacity="0.5" d="M4 12.9L7.14286 16.5L15 7.5"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                </path>
                                                <path d="M20.0002 7.5625L11.4286 16.5625L11.0002 16"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round">
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
                                        class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-danger text-white">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path opacity="0.5" d="M4 12.9L7.14286 16.5L15 7.5"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                </path>
                                                <path d="M20.0002 7.5625L11.4286 16.5625L11.0002 16"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round">
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
                                        class="relative z-10 shrink-0 before:absolute before:left-4 before:top-10 before:h-[calc(100%-24px)] before:w-[2px] before:bg-white-dark/30 mr-2 ">
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
                                    <div class="shrink-0 mr-2 ">
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
                                        <h5 class="font-semibold dark:text-white-light">Server rebooted successfully
                                        </h5>
                                        <p class="text-xs text-white-dark">06 Apr, 2020</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- user activity done -->
                </div>

            </div>
            <!-- users revenue done-->





        </div>
        <!-- ./ second row -->
    </div>



    <script>
    // JavaScript to toggle the visibility of the content
    function setupToggle(iconId, contentId) {
        const toggleIcon = document.getElementById(iconId);
        const toggleContent = document.getElementById(contentId);

        toggleIcon.addEventListener('click', () => {
            // Toggle the 'hidden' class to show/hide the content
            toggleContent.classList.toggle('hidden');

            // Change the icon from up to down depending on the content visibility
            if (toggleContent.classList.contains('hidden')) {
                toggleIcon.classList.remove('fa-chevron-up');
                toggleIcon.classList.add('fa-chevron-down');
            } else {
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-up');
            }
        });
    }

    // Call the setupToggle function for each toggle pair
    document.addEventListener('DOMContentLoaded', () => {
        setupToggle('Income-icon', 'Income-content');
        setupToggle('toggle-icon', 'toggle-content');
    });
    </script>


    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Example data (replace with your actual data)
        const dashboardData = @json($dashboardData);

        const formattedInvoiceAmount = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'MYR'
        }).format(dashboardData.totalInvoiceAmount);

        document.getElementById("totalInvoiceAmount").innerText = formattedInvoiceAmount;
        document.getElementById("totalAgents").innerText = dashboardData.agentsCount;
        document.getElementById("totalClients").innerText = dashboardData.clientsCount;
        document.getElementById("totalTasks").innerText = dashboardData.totalTasks;
        document.getElementById("pendingTasks").innerText = dashboardData.pendingTasks;
        document.getElementById("completedTasks").innerText = dashboardData.completedTasks;
        document.getElementById("paidInvoices").innerText = dashboardData.paidInvoices;
        document.getElementById("unpaidInvoices").innerText = dashboardData.unpaidInvoices;

        var incomeData = dashboardData.totalPaidAmountChart;
        var expensesData = dashboardData.totalUnpaidAmountChart;

        // Define all 12 months in an array
        const allMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // Create arrays with 12 elements initialized to 0 for income and expenses
        let incomeDataMapped = new Array(12).fill(0);
        let expensesDataMapped = new Array(12).fill(0);

        // Populate incomeDataMapped and expensesDataMapped based on the data from incomeData and expensesData
        incomeData.forEach(item => {
            const [year, month] = item.month.split('-'); // Splitting "YYYY-MM"
            incomeDataMapped[parseInt(month) - 1] = item
                .total; // Set the corresponding month (0-indexed)
        });

        expensesData.forEach(item => {
            const [year, month] = item.month.split('-'); // Splitting "YYYY-MM"
            expensesDataMapped[parseInt(month) - 1] = item
                .total; // Set the corresponding month (0-indexed)
        });

        // ApexCharts configuration
        const options = {
            series: [{
                    name: 'Piad',
                    data: incomeDataMapped
                },
                {
                    name: 'Unpaid',
                    data: expensesDataMapped
                },

            ],
            chart: {
                height: 325,
                type: 'area',
                fontFamily: 'Nunito, sans-serif',
                zoom: {
                    enabled: false,
                },
                toolbar: {
                    show: false,
                },
            },
            dataLabels: {
                enabled: false,
            },
            stroke: {
                show: true,
                curve: 'smooth',
                width: 2,
                lineCap: 'square',
            },
            dropShadow: {
                enabled: true,
                opacity: 0.2,
                blur: 10,
                left: -7,
                top: 22,
            },
            colors: ['#1b55e2', '#e7515a'],
            markers: {
                size: 0, // Hide markers for zero values by default
                colors: ['#ffffff'],
                strokeColors: ['#1b55e2', '#e7515a'],
                strokeWidth: 3,
                hover: {
                    size: 8,
                },
                discrete: [
                    ...incomeDataMapped.map((value, index) => (
                        value > 0 ? {
                            seriesIndex: 0,
                            dataPointIndex: index,
                            fillColor: '#1b55e2',
                            strokeColor: 'transparent',
                            size: 7,
                        } : null
                    )).filter(Boolean),
                    ...expensesDataMapped.map((value, index) => (
                        value > 0 ? {
                            seriesIndex: 1,
                            dataPointIndex: index,
                            fillColor: '#e7515a',
                            strokeColor: 'transparent',
                            size: 7,
                        } : null
                    )).filter(Boolean),
                ],
            },
            xaxis: {
                categories: allMonths,
                axisBorder: {
                    show: false,
                },
                axisTicks: {
                    show: false,
                },
                crosshairs: {
                    show: true,
                },
                labels: {
                    offsetX: 0,
                    offsetY: 5,
                    style: {
                        fontSize: '12px',
                        cssClass: 'apexcharts-xaxis-title',
                    },
                },
            },
            yaxis: {
                tickAmount: 7,
                labels: {
                    formatter: (value) => {
                        return value / 1000 + 'K';
                    },
                    offsetX: -10,
                    offsetY: 0,
                    style: {
                        fontSize: '12px',
                        cssClass: 'apexcharts-yaxis-title',
                    },
                },
                opposite: false,
            },
            grid: {
                borderColor: '#e0e6ed',
                strokeDashArray: 5,
                xaxis: {
                    lines: {
                        show: true,
                    },
                },
                yaxis: {
                    lines: {
                        show: false,
                    },
                },
                padding: {
                    top: 0,
                    right: 0,
                    bottom: 0,
                    left: 0,
                },
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '16px',
                markers: {
                    width: 10,
                    height: 10,
                    offsetX: -2,
                },
                itemMargin: {
                    horizontal: 10,
                    vertical: 5,
                },
            },
            tooltip: {
                marker: {
                    show: true,
                },
                x: {
                    show: false,
                },
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    inverseColors: false,
                    opacityFrom: 0.28,
                    opacityTo: 0.05,
                    stops: [45, 100],
                },
            },
        };

        // Create and render the chart
        const chart = new ApexCharts(document.querySelector("#revenueChart"), options);
        chart.render();
    });
    </script>




</x-app-layout>