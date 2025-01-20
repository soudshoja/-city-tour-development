        <x-app-layout>

            <div class="grid grid-cols-5 gap-4">
                <!-- Task Revenue & main chart -->
                <div class="col-span-4 p-1">
                    <!-- Task Revenue -->
                    <div class="flex gap-4 mb-5">
                        <div class="panel w-[5%] md:w-[5%] flex items-center justify-center">
                            <h2 class="text-center text-sm font-semibold transform -rotate-90">
                                <span class="text-primary">Tasks</span> Revenue
                            </h2>
                        </div>

                        <!-- Total Tasks Card -->
                        <div
                            class="flex rounded-lg overflow-hidden bg-blue-500 text-white shadow-md w-[31.66%] md:w-[31.66%]">
                            <div class="flex items-center justify-center w-1/3 bg-blue-700 p-4">
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
                            <div class="flex gap-2 items-center justify-center w-2/3 p-4">
                                <p class="text-3xl font-bold" id="totalTasks"></p>
                                <p class="text-sm">Total Tasks</p>
                            </div>
                        </div>

                        <!-- Pending Tasks Card -->
                        <div
                            class="flex rounded-lg overflow-hidden bg-[#e7515a] text-white shadow-md w-[31.66%] md:w-[31.66%]">
                            <div class="flex items-center justify-center w-1/3 bg-[#c03f4c] p-4">
                                <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                    fill="currentColor">
                                    <path
                                        d="M12 12L9.0423 14.9289C6.11981 17.823 4.65857 19.27 5.06765 20.5185C5.10282 20.6258 5.14649 20.7302 5.19825 20.8307C5.80046 22 7.86697 22 12 22C16.133 22 18.1995 22 18.8017 20.8307C18.8535 20.7302 18.8972 20.6258 18.9323 20.5185C19.3414 19.27 17.8802 17.823 14.9577 14.9289L12 12ZM12 12L14.9577 9.07107C17.8802 6.177 19.3414 4.729 18.9323 3.48149C18.8972 3.37417 18.8535 3.26977 18.8017 3.16926C18.1995 2 16.133 2 12 2C7.86697 2 5.80046 2 5.19825 3.16926C5.14649 3.26977 5.10282 3.37417 5.06765 3.48149C4.65857 4.729 6.11981 6.177 9.0423 9.07107L12 12Z" />
                                    <path d="M10 5.5H14" />
                                    <path d="M10 18.5H14" />
                                </svg>
                            </div>
                            <div class="flex gap-2 items-center justify-center w-2/3 p-4">
                                <p class="text-3xl font-bold" id="pendingTasks"></p>
                                <p class="text-sm">Pending Tasks</p>
                            </div>
                        </div>

                        <!-- Completed Tasks Card -->
                        <div
                            class="flex rounded-lg overflow-hidden bg-green-500 text-white shadow-md w-[31.66%] md:w-[31.66%]">
                            <div class="flex items-center justify-center w-1/3 bg-green-700 p-4">
                                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path opacity="0.5"
                                        d="M2 12C2 7.28595 2 4.92893 3.46447 3.46447C4.92893 2 7.28595 2 12 2C16.714 2 19.0711 2 20.5355 3.46447C22 4.92893 22 7.28595 22 12C22 16.714 22 19.0711 20.5355 20.5355C19.0711 22 16.714 22 12 22C7.28595 22 4.92893 22 3.46447 20.5355C2 19.0711 2 16.714 2 12Z"
                                        stroke="#FFFFFF" stroke-width="1.5" />
                                    <path d="M8.5 12.5L10.5 14.5L15.5 9.5" stroke="#FFFFFF" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <div class="flex gap-2 items-center justify-center w-2/3 p-4">
                                <p class="text-3xl font-bold" id="completedTasks"></p>
                                <p class="text-sm">Completed Tasks</p>
                            </div>
                        </div>
                    </div>
                    <!-- ./Task Revenue -->

                    <!-- chart panel -->
                    <div class="panel col-span-3 h-auto">
                        <div class="mb-5 flex items-center dark:text-white-light">
                            <h5 class="text-lg font-semibold">Income Revenue</h5>
                            <!-- dropdown menu -->
                            <div x-data="{ dropdown: false }" class="dropdown ml-auto ">
                                <a href="javascript:;" @click="dropdown = ! dropdown">
                                    <svg class="h-5 w-5 text-black/70 hover:!text-primary dark:text-white/70"
                                        viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="5" cy="12" r="2" stroke="currentColor" stroke-width="1.5" />
                                        <circle opacity="0.5" cx="12" cy="12" r="2" stroke="currentColor"
                                            stroke-width="1.5" />
                                        <circle cx="19" cy="12" r="2" stroke="currentColor" stroke-width="1.5" />
                                    </svg>
                                </a>
                                <ul x-show="dropdown" class="right-0">
                                    <li><a href="javascript:;">Weekly</a></li>
                                    <li><a href="javascript:;">Monthly</a></li>
                                    <li><a href="javascript:;">Yearly</a></li>
                                </ul>
                            </div>
                        </div>
                        <!-- chart -->
                        <div class="relative overflow-hidden">
                            <div x-data="revenueChart()" class="rounded-lg bg-white dark:bg-black">

                                <!-- Chart canvas -->
                                <canvas x-ref="revenueChartCanvas" class="w-full max-h-[290px]"></canvas>
                            </div>






                        </div>
                        <!-- ./chart panel -->
                    </div>
                    <!-- ./chart -->

                </div>
                <!-- ./Task Revenue & main chart -->

                <!-- Balance  & quick income overview -->
                <div class="p-1">
                    <!-- quick income overview -->
                    <div
                        class="flex flex-col p-3 bg-white dark:bg-gray-800 shadow-md rounded-md text-gray-800 dark:text-gray-200 mb-2">
                        <div class="flex justify-between">
                            <h3 class="text-lg font-semibold">Balance</h3>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M3.67981 11.3333H2.92981H3.67981ZM3.67981 13L3.15157 13.5324C3.44398 13.8225 3.91565 13.8225 4.20805 13.5324L3.67981 13ZM5.88787 11.8657C6.18191 11.574 6.18377 11.0991 5.89203 10.8051C5.60029 10.511 5.12542 10.5092 4.83138 10.8009L5.88787 11.8657ZM2.52824 10.8009C2.2342 10.5092 1.75933 10.511 1.46759 10.8051C1.17585 11.0991 1.17772 11.574 1.47176 11.8657L2.52824 10.8009ZM18.6156 7.39279C18.8325 7.74565 19.2944 7.85585 19.6473 7.63892C20.0001 7.42199 20.1103 6.96007 19.8934 6.60721L18.6156 7.39279ZM16.8931 3.60787C16.5403 3.39077 16.0784 3.50074 15.8613 3.8535C15.6442 4.20626 15.7541 4.66822 16.1069 4.88532L16.8931 3.60787ZM12.4633 3.75939C12.877 3.77966 13.2288 3.46071 13.2491 3.047C13.2694 2.63328 12.9504 2.28146 12.5367 2.26119L12.4633 3.75939ZM12.0789 2.25C7.03155 2.25 2.92981 6.3112 2.92981 11.3333H4.42981C4.42981 7.15072 7.84884 3.75 12.0789 3.75V2.25ZM2.92981 11.3333L2.92981 13H4.42981L4.42981 11.3333H2.92981ZM4.20805 13.5324L5.88787 11.8657L4.83138 10.8009L3.15157 12.4676L4.20805 13.5324ZM4.20805 12.4676L2.52824 10.8009L1.47176 11.8657L3.15157 13.5324L4.20805 12.4676ZM19.8934 6.60721C19.1441 5.38846 18.1143 4.35941 16.8931 3.60787L16.1069 4.88532C17.1287 5.51419 17.9899 6.37506 18.6156 7.39279L19.8934 6.60721ZM12.5367 2.26119C12.385 2.25376 12.2323 2.25 12.0789 2.25V3.75C12.2078 3.75 12.336 3.75316 12.4633 3.75939L12.5367 2.26119Z"
                                    fill="currentColor" />
                                <path
                                    d="M11.8825 21V21.75V21ZM20.3137 12.6667H21.0637H20.3137ZM20.3137 11L20.8409 10.4666C20.5487 10.1778 20.0786 10.1778 19.7864 10.4666L20.3137 11ZM18.1002 12.1333C17.8056 12.4244 17.8028 12.8993 18.094 13.1939C18.3852 13.4885 18.86 13.4913 19.1546 13.2001L18.1002 12.1333ZM21.4727 13.2001C21.7673 13.4913 22.2421 13.4885 22.5333 13.1939C22.8245 12.8993 22.8217 12.4244 22.5271 12.1332L21.4727 13.2001ZM5.31769 16.6061C5.10016 16.2536 4.63806 16.1442 4.28557 16.3618C3.93307 16.5793 3.82366 17.0414 4.0412 17.3939L5.31769 16.6061ZM11.5331 20.2423C11.1193 20.224 10.769 20.5447 10.7507 20.9585C10.7325 21.3723 11.0531 21.7226 11.4669 21.7408L11.5331 20.2423ZM7.11292 20.4296C7.4677 20.6433 7.92861 20.529 8.14239 20.1742C8.35617 19.8195 8.24186 19.3586 7.88708 19.1448L7.11292 20.4296ZM11.8825 21.75C16.9448 21.75 21.0637 17.6915 21.0637 12.6667H19.5637C19.5637 16.8466 16.133 20.25 11.8825 20.25V21.75ZM21.0637 12.6667V11H19.5637V12.6667H21.0637ZM19.7864 10.4666L18.1002 12.1333L19.1546 13.2001L20.8409 11.5334L19.7864 10.4666ZM19.7864 11.5334L21.4727 13.2001L22.5271 12.1332L20.8409 10.4666L19.7864 11.5334ZM11.4669 21.7408C11.6047 21.7469 11.7433 21.75 11.8825 21.75V20.25C11.7653 20.25 11.6488 20.2474 11.5331 20.2423L11.4669 21.7408ZM4.0412 17.3939C4.80569 18.6327 5.86106 19.6752 7.11292 20.4296L7.88708 19.1448C6.83872 18.5131 5.95602 17.6405 5.31769 16.6061L4.0412 17.3939Z"
                                    fill="currentColor" />
                            </svg>

                        </div>

                        <div class="flex gap-1 mt-2 grid-cols-2">
                            <h4 class="text-3xl text-success font-semibold">
                                $528,976.<span class="opacity-50 text-gray-500 dark:text-gray-400">82</span>
                            </h4>
                            <div class="flex text-red-500 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 15l7-7 7 7" />
                                </svg>
                                <span class="TextXs">70.0%</span>
                            </div>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 my-2">Last update - Oct 31,
                            2024</span>
                    </div>
                    <!-- ./quick income overview -->


                    <!--  quick performance overview -->
                    <div class="space-y-2">
                        <div
                            class="gap-2 flex panel text-center justify-center bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200">

                            <h3 class="text-xl font-semibold">Quick Actions</h3>
                            <div
                                class="rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:hover:text-gray-900 focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg" class="dark:stroke-white stroke-black">
                                    <path d="M17 14.5L12 19.5L7 14.5" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                    <path opacity="0.5" d="M12 19.5C12 19.5 12 11.1667 12 9.5C12 7.83333 11 4.5 7 4.5"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </div>



                        </div>
                        <!-- Client -->
                        <div
                            class="bg-white flex items-center justify-center p-4 bg-gray-100 dark:bg-gray-800 shadow-md rounded-md ">

                            <a href="{{ route('clients.create') }}" target="_blank"
                                class="w-full relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 dark:text-gray-200 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:hover:text-gray-900 focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                                <span
                                    class="justify-center w-full gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                                    Add New Client
                                </span>
                            </a>
                        </div>

                        <!-- Invoices -->
                        <div
                            class="bg-white flex items-center justify-center p-4 bg-gray-100 dark:bg-gray-800 shadow-md rounded-md ">
                            <a href="{{ route('invoice.create') }}" target="_blank"
                                class="w-full relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 dark:text-gray-200 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:hover:text-gray-900 focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                                <span
                                    class="justify-center w-full gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                                    Create New Invoice
                                </span>
                            </a>
                        </div>

                        <!-- Tasks -->
                        <div
                            class="bg-white flex items-center justify-center p-4 bg-gray-100 dark:bg-gray-800 shadow-md rounded-md ">
                            <a href="javascript:void(0);" onclick="document.getElementById('pdfInput').click();"
                                class="w-full relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 dark:text-gray-200 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:hover:text-gray-900 focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                                <span
                                    class="justify-center w-full gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                                    Upload New Task
                                </span>
                            </a>
                            <form id="uploadTaskForm" action="{{ route('tasks.upload') }}" method="POST"
                                enctype="multipart/form-data" class="hidden">
                                @csrf
                                <input id="pdfInput" type="file" accept=".pdf" name="task_file"
                                    onchange="uploadTask()" />
                            </form>
                        </div>
                    </div>


                    <!--  ./quick performance overview -->

                </div>

            </div>


            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const AgentDashboarddata = @json($dashboardData);

                // Total Tasks
                document.getElementById('totalTasks').innerText = AgentDashboarddata
                    .totalTasks; // Corrected key

                // Pending Tasks
                document.getElementById('pendingTasks').innerText = AgentDashboarddata.pendingTasks;

                // Completed Tasks
                document.getElementById('completedTasks').innerText = AgentDashboarddata
                    .completedTasks; // Corrected key
            });
            </script>


        </x-app-layout>