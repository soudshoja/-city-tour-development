<div class="flex flex-col justify-between items-center space-y-48">
    <!-- sidebar menu -->
    <div class="flex flex-col justify-between items-center space-y-4">
        <!-- quick actions icon -->
        <div class="flex flex-col items-center heartbeat-container tooltip">
            <span class="tooltiptext">
                quick actions
            </span>
            <div class="relative">
                <!-- Icon Button -->
                <div
                    class="p-3 LightBCcolor rounded-full shadow-md flex items-center justify-center heartbeat">
                    <!-- SVG Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48">
                        <path fill="#FFC107" d="M33 22h-9.4L30 5H19l-6 21h8.6L17 45z" />
                    </svg>
                </div>
            </div>
        </div>
        <!-- ./quick actions icon -->

        <!--   dashboard -->
        <div class="flex flex-col items-center ">
            <!-- Menu item-->
            <div class="relative group">
                <!-- Icon Button -->
                <div
                    class="p-3 bg-white rounded-full shadow-md hover:bg-gray-300/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                    <!-- SVG Icon -->
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <g fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="19" cy="5" r="2.5" />
                            <path stroke-linecap="round" d="M21.25 10v5.25a6 6 0 0 1-6 6h-6.5a6 6 0 0 1-6-6v-6.5a6 6 0 0 1 6-6H14" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="m7.27 15.045l2.205-2.934a.9.9 0 0 1 1.197-.225l2.151 1.359a.9.9 0 0 0 1.233-.261l2.214-3.34" />
                        </g>
                    </svg>
                </div>


                <a
                    href="{{ route('dashboard') }}"
                    class="p-3 absolute left-full top-1/2 hover:bg-gray-300 transform -translate-y-1/2 opacity-0 group-hover:opacity-100 group-hover:translate-x-2 bg-white text-gray-800 text-sm rounded-full shadow-md transition-all duration-200">
                    Dashboard
                </a>
            </div>
        </div>

        <!-- ./  dashboard -->

        <!-- Add New -->
        <div class="flex flex-col items-center ">
            <!-- Menu item-->
            <div class="relative group">
                <!-- Icon Button -->
                <div
                    class="p-3 bg-white rounded-full shadow-md hover:bg-gray-300/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                    <!-- SVG Icon -->
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h4m4 0h-4m0 0V8m0 4v4m0 6c5.523 0 10-4.477 10-10S17.523 2 12 2S2 6.477 2 12s4.477 10 10 10" />
                    </svg>
                </div>


                <a
                    href="{{ route('companiesnew.new') }}"
                    class="p-3 absolute left-full top-1/2 hover:bg-gray-300 transform -translate-y-1/2 opacity-0 group-hover:opacity-100 group-hover:translate-x-2 bg-white text-gray-800 text-sm rounded-full shadow-md transition-all duration-200 whitespace-nowrap">
                    Add New
                </a>
            </div>
        </div>
        <!-- ./Add New -->



    </div>

    <!-- mode switchers -->
    <div class="flex flex-col justify-between items-center space-y-4">
        <div class="flex flex-col items-center">
            <!-- Menu item -->
            <div class="relative">
                <!-- Icon Button -->
                <div
                    class="p-3 bg-white rounded-full shadow-md flex items-center justify-center heartbeat">
                    <!-- SVG Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 21a9 9 0 0 0 8.997-9.252a7 7 0 0 1-10.371-8.643A9 9 0 0 0 12 21" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="flex flex-col items-center ">
            <!-- Menu item -->
            <div class="relative">
                <!-- Icon Button -->
                <div
                    class="p-3 bg-black rounded-full shadow-md flex items-center justify-center heartbeat">
                    <!-- SVG Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#ffffff" d="M12 15q1.25 0 2.125-.875T15 12t-.875-2.125T12 9t-2.125.875T9 12t.875 2.125T12 15m0 2q-2.075 0-3.537-1.463T7 12t1.463-3.537T12 7t3.538 1.463T17 12t-1.463 3.538T12 17m-7-4H1v-2h4zm18 0h-4v-2h4zM11 5V1h2v4zm0 18v-4h2v4zM6.4 7.75L3.875 5.325L5.3 3.85l2.4 2.5zm12.3 12.4l-2.425-2.525L17.6 16.25l2.525 2.425zM16.25 6.4l2.425-2.525L20.15 5.3l-2.5 2.4zM3.85 18.7l2.525-2.425L7.75 17.6l-2.425 2.525zM12 12" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

</div>