<div class="space-y-4 my-5 mt-3">
    <div class="flex flex-col justify-between items-center space-y-48">
        <div class="flex flex-col justify-between items-center space-y-4 mt-5">
          
            <!-- <div data-tooltip="Quick Actions" class="flex flex-col items-center heartbeat-container">
                
                <div class="relative">
                    
                    <div
                        class="p-3 DarkBGcolor  rounded-full shadow-md flex items-center justify-center heartbeat">
                    
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48">
                            <path fill="#FFC107" d="M33 22h-9.4L30 5H19l-6 21h8.6L17 45z" />
                        </svg>
                    </div>
                </div>
            </div> -->

            <div class="flex flex-col items-center ">
                <a
                    href="{{ route('dashboard') }}">
                    <div class="relative">
                        <div data-tooltip="Dashboard"
                            class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <g fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="19" cy="5" r="2.5" />
                                    <path stroke-linecap="round" d="M21.25 10v5.25a6 6 0 0 1-6 6h-6.5a6 6 0 0 1-6-6v-6.5a6 6 0 0 1 6-6H14" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m7.27 15.045l2.205-2.934a.9.9 0 0 1 1.197-.225l2.151 1.359a.9.9 0 0 0 1.233-.261l2.214-3.34" />
                                </g>
                            </svg>
                        </div>
                    </div>

                </a>
            </div>

            <div class="flex flex-col items-center ">
                <a href="{{ route('users.create') }}">
                    <div class="relative ">
                        <div data-tooltip="Add new user"
                            class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200"> 
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <g fill="none" fill-rule="evenodd">
                                    <path d="m12.594 23.258l-.012.002l-.071.035l-.02.004l-.014-.004l-.071-.036q-.016-.004-.024.006l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.016-.018m.264-.113l-.014.002l-.184.093l-.01.01l-.003.011l.018.43l.005.012l.008.008l.201.092q.019.005.029-.008l.004-.014l-.034-.614q-.005-.019-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.003-.011l.018-.43l-.003-.012l-.01-.01z" />
                                    <path fill="currentColor" d="M11 2a5 5 0 1 0 0 10a5 5 0 0 0 0-10M8 7a3 3 0 1 1 6 0a3 3 0 0 1-6 0M4 18.5c0-.18.09-.489.413-.899c.316-.4.804-.828 1.451-1.222C7.157 15.589 8.977 15 11 15q.563 0 1.105.059a1 1 0 1 0 .211-1.99A13 13 0 0 0 11 13c-2.395 0-4.575.694-6.178 1.672c-.8.488-1.484 1.064-1.978 1.69C2.358 16.976 2 17.713 2 18.5c0 .845.411 1.511 1.003 1.986c.56.45 1.299.748 2.084.956C6.665 21.859 8.771 22 11 22l.685-.005a1 1 0 1 0-.027-2L11 20c-2.19 0-4.083-.143-5.4-.492c-.663-.175-1.096-.382-1.345-.582C4.037 18.751 4 18.622 4 18.5M18 14a1 1 0 0 1 1 1v2h2a1 1 0 1 1 0 2h-2v2a1 1 0 1 1-2 0v-2h-2a1 1 0 1 1 0-2h2v-2a1 1 0 0 1 1-1" />
                                </g>
                            </svg>

                        </div>
                    </div>
                </a>
            </div>


            <div class="flex flex-col items-center ">
                <a
                    href="{{ route('invoice.create') }}">

                    <div class="relative">
                        
                        <div data-tooltip="Create Invoice"
                            class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                            
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M4 5.25A2.25 2.25 0 0 1 6.25 3h9.5A2.25 2.25 0 0 1 18 5.25V14h4v3.75A3.25 3.25 0 0 1 18.75 21h-6.772c.297-.463.536-.966.709-1.5H16.5V5.25a.75.75 0 0 0-.75-.75h-9.5a.75.75 0 0 0-.75.75v5.826a6.5 6.5 0 0 0-1.5.422zm9.75 7.25h-3.096a6.5 6.5 0 0 0-2.833-1.366A.75.75 0 0 1 8.25 11h5.5a.75.75 0 0 1 0 1.5m4.25 7h.75a1.75 1.75 0 0 0 1.75-1.75V15.5H18zM8.25 7a.75.75 0 0 0 0 1.5h5.5a.75.75 0 0 0 0-1.5zM12 17.5a5.5 5.5 0 1 0-11 0a5.5 5.5 0 0 0 11 0M7 18l.001 2.503a.5.5 0 1 1-1 0V18H3.496a.5.5 0 0 1 0-1H6v-2.5a.5.5 0 1 1 1 0V17h2.497a.5.5 0 0 1 0 1z" />
                            </svg>

                        </div>
                    </div>
                </a>
            </div>

        </div>

        <div class="flex flex-col justify-between items-center space-y-4">
            
            <div id="themeToggle" data-tooltip="switch theme">
                
                <button id="themeButton" class="p-3 rounded-full shadow-md flex items-center justify-center bg-black hover:bg-gray-700 dark:bg-gray-600  dark:hover:bg-gray-900/50 transition-all duration-200">
                    
                    <svg id="lightSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff" d="M12 15q1.25 0 2.125-.875T15 12t-.875-2.125T12 9t-2.125.875T9 12t.875 2.125T12 15m0 2q-2.075 0-3.537-1.463T7 12t1.463-3.537T12 7t3.538 1.463T17 12t-1.463 3.538T12 17m-7-4H1v-2h4zm18 0h-4v-2h4zM11 5V1h2v4zm0 18v-4h2v4zM6.4 7.75L3.875 5.325L5.3 3.85l2.4 2.5zm12.3 12.4l-2.425-2.525L17.6 16.25l2.525 2.425zM16.25 6.4l2.425-2.525L20.15 5.3l-2.5 2.4zM3.85 18.7l2.525-2.425L7.75 17.6l-2.425 2.525zM12 12" />
                    </svg>
                    
                    <svg id="darkSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                        style="display: none;">
                        <path fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21a9 9 0 0 0 8.997-9.252a7 7 0 0 1-10.371-8.643A9 9 0 0 0 12 21" />

                    </svg>
                </button>
            </div>

        </div>



    </div>


</div>