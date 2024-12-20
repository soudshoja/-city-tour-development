<x-app-layout>
    <div id="invoiceModalComponent">

        <div class="flex flex-col gap-2.5 xl:flex-row">
            <div class="panel flex-1 px-0 py-6 lg:mr-6 ">
                <!-- company details -->
                <div class="flex flex-wrap justify-between px-4">
                    <div class="smPaddingBottom shrink-0 items-center text-black dark:text-white">
                        <div class="flex items-center ">
                            <x-application-logo class="custom-logo-size" />
                            <h3 class="pl-2 text-2xl font-bold">{{ $company->name }}</h3>
                        </div>
                        <div class="pl-2">
                            @if($company)

                            <p>{{ $company->address }}</p>
                            @else
                            <p>No company assigned</p>
                            @endif
                        </div>

                        <div class="">
                            <div class="flex">
                                <p class="pl-1">{{ $company->email }}</p>
                            </div>
                            <div class="flex">
                                <p class="pl-1">{{ $company->phone }}</p>
                            </div>
                        </div>


                    </div>
                    <!-- invoice details -->
                    <div class="space-y-1 text-gray-500 dark:text-gray-400">

                        <div class="flex items-center w-full">
                            <label for="invoiceNumber" class="w-full text-sm font-semibold">Invoice Number</label>
                            <input id="invoiceNumber" type="text" name="invoiceNumber" value="{{$invoiceNumber}}" class="w-full form-input"
                                placeholder="Invoice Number" />
                        </div>
                        <div class="mt-4 flex items-center">
                            <label for="invdate" class="w-full text-sm font-semibold">Invoice Date</label>
                            <input id="invdate" type="date" name="invdate" class="w-full form-input" value={{$todayDate}} disabled />
                        </div>
                        <div class="mt-4 flex items-center">
                            <label for="duedate" class="w-full text-sm font-semibold">Due Date</label>
                            <input id="duedate" type="date" name="duedate" class="w-full form-input" />
                        </div>


                    </div>
                    <!--./invoice details -->
                </div>
                <!-- ./company details -->


                <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />



                <!-- users details -->
                <div class="flex justify-between px-4 gird gird-cols-2 gap-4">
                    <!-- client details -->
                    <div class="w-full">
                        <!-- choose client button -->
                        <div class="flex items-center">
                            <button type="button" id="openClientModalButton"
                                class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                                     city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="#004c9e"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="10" cy="6" r="4" fill="#004c9e" />
                                    <path
                                        d="M18 17.5C18 19.9853 18 22 10 22C2 22 2 19.9853 2 17.5C2 15.0147 5.58172 13 10 13C14.4183 13 18 15.0147 18 17.5Z"
                                        fill="#004c9e" />
                                    <path d="M21 10H19M19 10H17M19 10L19 8M19 10L19 12"
                                        stroke="#004c9e" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                                <span class="pl-2">Choose Client</span>
                            </button>
                            <input id="receiverId" type="hidden" name="receiverId" />
                            <input id="agentId" type="hidden" name="agentId" value="{{ is_string($agentId) || is_numeric($agentId) ? $agentId : '' }}" />
                        </div>

                        <p class="my-4 text-gray-400 text-center text-xs">details will displaying below after choosing a client</p>
                        <!-- client name -->
                        <div class="mt-4 flex items-center">
                            <input id="receiverName" type="text" name="receiverName" class="form-input flex-1"
                                placeholder="Client Name" disabled />
                        </div>

                        <!-- client email -->
                        <div class="mt-4 flex items-center">
                            <input id="receiverEmail" type="email" name="receiverEmail"
                                class="form-input flex-1"
                                placeholder="Client Email" disabled />
                        </div>

                        <!-- client phone -->
                        <div class="mt-4 flex items-center">
                            <input id="receiverPhone" type="text" name="receiverPhone" class="form-input flex-1"
                                placeholder="Client Phone Number" disabled />
                        </div>

                    </div>
                    <!-- ./client details -->

                    <!-- Agent details -->
                    <div class="w-full">

                        <!-- choose agent button -->
                        <div class="flex items-center">
                            @can('pickAgent', App\Models\Invoice::class)
                            <button
                                id="select-agent"
                                type="button"
                                onclick="openAgentModal()"
                                class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                                     city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="#004c9e"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="10" cy="6" r="4" fill="#004c9e" />
                                    <path
                                        d="M18 17.5C18 19.9853 18 22 10 22C2 22 2 19.9853 2 17.5C2 15.0147 5.58172 13 10 13C14.4183 13 18 15.0147 18 17.5Z"
                                        fill="#004c9e" />
                                    <path d="M21 10H19M19 10H17M19 10L19 8M19 10L19 12"
                                        stroke="#004c9e" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                                <span class="pl-2">Choose Agent</span>
                            </button>
                            @endcan
                        </div>
                        <p class="my-4 text-gray-400 text-center text-xs">details will displaying below after choosing an Agent</p>

                        <!-- agent name -->
                        <div class="mt-4 flex items-center">
                            <input id="agentName" type="text" name="agentName" class="form-input flex-1"
                                placeholder="Agent Name" value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->name  : ''}}" disabled />
                        </div>

                        <!-- agent email -->
                        <div class="mt-4 flex items-center">
                            <input id="agentEmail" type="email" name="agentEmail"
                                class="form-input flex-1"
                                placeholder="Agent Email" value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->email  : ''}}" disabled />
                        </div>

                        <!-- agent phone -->
                        <div class="mt-4 flex items-center">
                            <input id="agentPhone" type="text" name="agentPhone" class="form-input flex-1"
                                placeholder="Agent Phone" value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->phone  : ''}}" disabled />
                        </div>



                    </div>
                    <!-- ./Agent details -->
                </div>
                <!-- users details -->


                <!-- choose items -->
                <div class="mt-8">
                    <!-- choose items -->
                    <div class="table-responsive">
                        <table id="itemsTable">
                            <thead>
                                <tr>
                                    <th class="whitespace-nowrap">Task</th>
                                    <th class="whitespace-nowrap">Client</th>
                                    <th class="whitespace-nowrap">Quantity</th>
                                    <th class="whitespace-nowrap">Task Price</th>
                                    <th class="whitespace-nowrap">Invoice Price</th>
                                </tr>
                            </thead>
                            <tbody id="items-body">
                                <!-- Items will be added dynamically here -->
                                <!-- "No Item Available" row will show if items.length <= 0 -->
                            </tbody>
                        </table>
                    </div>
                    <!-- ./choose items -->

                    <div class="mt-6 flex flex-col justify-between px-4 sm:flex-row">
                        <div class="mb-6 sm:mb-0">
                            <button id="openTaskModalButton" class="inline-flex items-center justify-center text-sm text-black font-semibold
                                     city-light-yellow hover:bg-[#004c9e] hover:text-white  py-2 px-4  rounded-full shadow">
                                <svg class="w-6 h-6 pr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M19 11h-4v4h-2v-4H9V9h4V5h2v4h4m1-7H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2M4 6H2v14a2 2 0 0 0 2 2h14v-2H4z" />
                                </svg> Add Task
                            </button>

                        </div>
                        <div class="sm:w-2/5">
                            <div class="mt-4 flex items-center justify-between font-semibold">
                                <div>Total</div>
                                <span id="subT">$0.00</span>
                                <input id="subTotal" type="hidden" name="subTotal" />
                            </div>
                        </div>
                    </div>
                </div>


            </div>
            <div class="mt-6 w-full xl:mt-0 xl:w-96">
                <div class="panel mb-5">
                    <select id="currency" name="currency" class="form-select">
                        <!-- You can add your options here -->
                        <option value="KWD">KWD</option>
                        <option value="MYR">MYR</option>
                        <option value="USD">USD</option>
                    </select>

                </div>
                <div class="panel">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-1">
                        <div id="invoice-link-container" style="display: none;" class="mt-4">
                            <label>Invoice Link:</label>
                            <a id="invoice-link" href="#" class="text-blue-600 underline" target="_blank"></a>
                        </div>

                        <button id="generate-invoice-btn" type="button" class="btn btn-success w-full gap-2">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mr-2">
                                <path d="M3.46447 20.5355C4.92893 22 7.28595 22 12 22C16.714 22 19.0711 22 20.5355 20.5355C22 19.0711 22 16.714 22 12C22 11.6585 22 11.4878 21.9848 11.3142C21.9142 10.5049 21.586 9.71257 21.0637 9.09034C20.9516 8.95687 20.828 8.83317 20.5806 8.58578L15.4142 3.41944C15.1668 3.17206 15.0431 3.04835 14.9097 2.93631C14.2874 2.414 13.4951 2.08581 12.6858 2.01515C12.5122 2 12.3415 2 12 2C7.28595 2 4.92893 2 3.46447 3.46447C2 4.92893 2 7.28595 2 12C2 16.714 2 19.0711 3.46447 20.5355Z" stroke="currentColor" stroke-width="1.5" />
                                <path d="M17 22V21C17 19.1144 17 18.1716 16.4142 17.5858C15.8284 17 14.8856 17 13 17H11C9.11438 17 8.17157 17 7.58579 17.5858C7 18.1716 7 19.1144 7 21V22" stroke="currentColor" stroke-width="1.5" />
                                <path opacity="0.5" d="M7 8H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                            <span id="button-text">Save</span>
                            <span id="button-loading" style="display: none;">Saving...</span>
                            <span id="button-saved" style="display: none;">Saved</span>
                        </button>

                        <!-- add form here-->

                        <button type="button" class="btn btn-info w-full gap-2">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mr-2 ">
                                <path
                                    d="M17.4975 18.4851L20.6281 9.09373C21.8764 5.34874 22.5006 3.47624 21.5122 2.48782C20.5237 1.49939 18.6511 2.12356 14.906 3.37189L5.57477 6.48218C3.49295 7.1761 2.45203 7.52305 2.13608 8.28637C2.06182 8.46577 2.01692 8.65596 2.00311 8.84963C1.94433 9.67365 2.72018 10.4495 4.27188 12.0011L4.55451 12.2837C4.80921 12.5384 4.93655 12.6658 5.03282 12.8075C5.22269 13.0871 5.33046 13.4143 5.34393 13.7519C5.35076 13.9232 5.32403 14.1013 5.27057 14.4574C5.07488 15.7612 4.97703 16.4131 5.0923 16.9147C5.32205 17.9146 6.09599 18.6995 7.09257 18.9433C7.59255 19.0656 8.24576 18.977 9.5522 18.7997L9.62363 18.79C9.99191 18.74 10.1761 18.715 10.3529 18.7257C10.6738 18.745 10.9838 18.8496 11.251 19.0285C11.3981 19.1271 11.5295 19.2585 11.7923 19.5213L12.0436 19.7725C13.5539 21.2828 14.309 22.0379 15.1101 21.9985C15.3309 21.9877 15.5479 21.9365 15.7503 21.8474C16.4844 21.5244 16.8221 20.5113 17.4975 18.4851Z"
                                    stroke="currentColor" stroke-width="1.5" />
                                <path opacity="0.5" d="M6 18L21 3" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                            </svg>
                            Send Invoice
                        </button>

                        <a href="#" class="btn btn-primary w-full gap-2">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mr-2 ">
                                <path opacity="0.5"
                                    d="M3.27489 15.2957C2.42496 14.1915 2 13.6394 2 12C2 10.3606 2.42496 9.80853 3.27489 8.70433C4.97196 6.49956 7.81811 4 12 4C16.1819 4 19.028 6.49956 20.7251 8.70433C21.575 9.80853 22 10.3606 22 12C22 13.6394 21.575 14.1915 20.7251 15.2957C19.028 17.5004 16.1819 20 12 20C7.81811 20 4.97196 17.5004 3.27489 15.2957Z"
                                    stroke="currentColor" stroke-width="1.5"></path>
                                <path
                                    d="M15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z"
                                    stroke="currentColor" stroke-width="1.5"></path>
                            </svg>
                            Preview
                        </a>

                        <button type="button" class="btn btn-secondary w-full gap-2">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mr-2 ">
                                <path opacity="0.5"
                                    d="M17 9.00195C19.175 9.01406 20.3529 9.11051 21.1213 9.8789C22 10.7576 22 12.1718 22 15.0002V16.0002C22 18.8286 22 20.2429 21.1213 21.1215C20.2426 22.0002 18.8284 22.0002 16 22.0002H8C5.17157 22.0002 3.75736 22.0002 2.87868 21.1215C2 20.2429 2 18.8286 2 16.0002L2 15.0002C2 12.1718 2 10.7576 2.87868 9.87889C3.64706 9.11051 4.82497 9.01406 7 9.00195"
                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                <path d="M12 2L12 15M12 15L9 11.5M12 15L15 11.5" stroke="currentColor"
                                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                            Download
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agents Modal -->
        <div id="agentModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden">
            <div class="bg-white border rounded-lg shadow-lg w-3/4 md:w-1/2 mb-10">
                <!-- Modal Header -->
                <div class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3">
                    <h5 class="text-lg font-bold">Agent Management</h5>
                    <button
                        type="button"
                        onclick="closeAgentModal()"
                        class="text-white-dark hover:text-dark" id="closeAgentModalButton">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                            class="h-6 w-6">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <!-- ./Modal Header -->

                <!-- Search Box -->
                <div class="relative mb-4 px-4">
                    <input type="text" placeholder="Search Agent..."
                        class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                        id="agentSearchInput">
                </div>
                <!-- ./Search Box -->

                <!-- List of Agents -->
                <ul id="agentList"
                    class="shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] border rounded-lg mb-4 max-h-60 overflow-y-auto custom-scrollbar mx-4">
                    <!-- Dynamic list items go here -->
                    @foreach ($agents as $agent)
                    <li class="cursor-pointer flex items-center justify-between px-4 py-3 hover:bg-gray-100" onclick="chooseTasksAgent('{{$agent}}')">
                        {{$agent->name}}
                    </li>
                    @endforeach
                </ul>
                <!-- ./List of Agents -->
            </div>
        </div>
        <!-- End Agents Modal -->
        <!-- Clients Modal -->
        <div id="clientModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden ">
            <div class="bg-white border rounded-lg shadow-lg  w-3/4 md:w-1/2 mb-10">
                <!-- Modal Header -->
                <div class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3">
                    <h5 class="text-lg font-bold">Client Management</h5>
                    <button type="button" class="text-white-dark hover:text-dark" id="closeClientModalButton">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                            class="h-6 w-6">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <!-- ./Modal Header -->

                <!-- Tabs -->
                <div class="border-b flex justify-center">
                    <button class="tab-button px-4 py-2 text-blue-500 border-b-2 border-blue-500" id="selectTabButton">Select Client</button>
                    <button class="tab-button px-4 py-2 text-gray-500 hover:text-blue-500" id="addTabButton">Add New Client</button>
                </div>
                <!-- ./Tabs -->

                <!-- Tab Content -->
                <div id="selectTab" class="p-6">
                    <!-- Search Box -->
                    <div class="relative mb-4">
                        <input type="text" placeholder="Search Client..."
                            class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                            id="clientSearchInput">
                    </div>
                    <!-- ./Search Box -->

                    <!-- List of Clients -->
                    <ul id="clientList"
                        class="shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] border rounded-lg mb-4 max-h-60 overflow-y-auto custom-scrollbar">
                        <!-- Dynamic list items go here -->
                    </ul>
                    <!-- ./List of Clients -->
                </div>

                <div id="addTab" class="p-6 hidden">
                    <!-- Add New Client Form -->
                    <h6 class="text-lg font-bold mb-3">Add New Client</h6>
                    <form method="POST" action="{{ route('invoices.clientAdd') }}">
                        @csrf

                        <div class="mb-4 flex gap-4">
                            <!-- Name Field -->
                            <div class="w-1/2">
                                <label for="name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                                <input id="name" name="name" type="text" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Client Name" />
                            </div>

                            <!-- Email Field -->
                            <div class="w-1/2">
                                <label for="email" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                                <input id="email" name="email" type="email" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Client Email" />
                            </div>
                        </div>

                        <div class="mb-4 flex gap-4">
                            <!-- Phone Field -->
                            <div class="w-1/2">
                                <label for="phone"
                                    class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone</label>
                                <input id="phone" name="phone" type="text" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Client Phone" />
                            </div>

                            <!-- Address Field -->
                            <div class="w-1/2">
                                <label for="address"
                                    class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Address</label>
                                <input id="address" name="address" type="text"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Client Address" />
                            </div>
                        </div>

                        <!-- Address Field -->
                        <div class="mb-4">
                            <label for="passport_no"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Passport Number</label>
                            <input id="passport_no" name="passport_no" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Passport Number" />
                        </div>

                        <!-- Email Field -->
                        <div class="mb-4">
                            <label for="agent_email"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Agent Email</label>
                            <input id="agent_email" name="agent_email" type="email" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Agent Email" />
                        </div>

                        <!-- Status Field -->
                        <div class="mb-4">

                            <div class="flex flex-col">
                                <div class="flex items-center space-x-4">
                                    <label class="text-lg font-semibold mb-2">status:</label>

                                    <!-- Active Radio Button -->
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="status" value="1" class="status-radio peer hidden" id="active" />
                                        <span class="flex items-center justify-center w-6 h-6 border border-gray-500 rounded-full peer-checked:border-[#00ab55] peer-checked:bg-[#00ab55] peer-checked:text-white peer-checked:font-semibold">
                                            <span class="w-3 h-3 bg-transparent rounded-full"></span>
                                        </span>
                                        <span class="ml-2 text-lg text-gray-700 peer-checked:text-[#00ab55] peer-checked:font-semibold">Active</span>
                                    </label>

                                    <!-- Inactive Radio Button -->
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" name="status" value="2" class="status-radio peer hidden" id="inactive" />
                                        <span class="flex items-center justify-center w-6 h-6 border border-gray-500 rounded-full peer-checked:border-[#e7515a] peer-checked:bg-[#e7515a] peer-checked:text-white peer-checked:font-semibold">
                                            <span class="w-3 h-3 bg-transparent rounded-full"></span>
                                        </span>
                                        <span class="ml-2 text-lg text-gray-700 peer-checked:text-[#e7515a] peer-checked:font-semibold">Inactive</span>
                                    </label>
                                </div>
                            </div>


                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-center">
                            <button type="submit"
                                class="btnCityGrayColor mt-3 w-full bg-black BtColor text-white px-4 py-2 rounded-lg">
                                Register Client
                            </button>
                        </div>
                    </form>
                </div>
                <!-- ./Tab Content -->
            </div>
        </div>

        <!-- Tasks Modal -->
        <div id="taskModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden">
            <div class="bg-white border rounded-lg shadow-lg w-3/4 md:w-1/2">
                <div class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3">
                    <h5 class="text-lg font-bold">Choose Task</h5>
                    <!-- Close Modal Button -->
                    <button type="button" class="text-white-dark hover:text-dark" id="closeTaskModalButton">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                            class="h-6 w-6">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="m-6">
                    <!-- Search Box -->
                    <div class="relative mb-10">
                        <input type="text" placeholder="Search Task..."
                            class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                            id="taskSearchInput" oninput="filterTasks()">
                    </div>
                    <!-- ./Search Box -->
                    <!-- List of Tasks -->
                    <ul id="taskList" class="border rounded-lg mb-10 max-h-60 overflow-y-auto custom-scrollbar">
                        <!-- Dynamic list items go here -->
                    </ul>
                </div>
            </div>
        </div>
        <!-- end main content section -->
    </div>

    <script>
        let selectedTasks = @json($selectedTasks);
        let items = [];
        const itemsBody = document.getElementById('items-body');
        const appUrl = @json($appUrl);

        // Handle Tab Switching
        const selectTabButton = document.getElementById('selectTabButton');
        const addTabButton = document.getElementById('addTabButton');
        const selectTab = document.getElementById('selectTab');
        const addTab = document.getElementById('addTab');

        document.getElementById("openClientModalButton").onclick = openClientModal;
        document.getElementById("closeClientModalButton").onclick = closeClientModal;
        document.getElementById('clientSearchInput').addEventListener('input', filterClients);


        document.getElementById("openTaskModalButton").onclick = openTaskModal;
        document.getElementById("closeTaskModalButton").onclick = closeTaskModal;
        document.getElementById('taskSearchInput').addEventListener('input', filterTasks);

        let selectedAgent = @json($selectedAgent);
        let selectedClient = @json($selectedClient);

        const generateInvoiceButton = document.getElementById('generate-invoice-btn');
        const buttonText = document.getElementById('button-text');
        const buttonLoading = document.getElementById('button-loading');
        const buttonSaved = document.getElementById('button-saved');

        // Set initial states
        let isSaving = false;
        let isSaved = false;

        function updateItemPrice(itemId) {
            // Find the input field by ID
            const inputField = document.getElementById(`invprice-${itemId}`);
            const newPrice = parseFloat(inputField.value) || 0;

            // Update the corresponding item in the `items` array
            const item = items.find(item => item.id === itemId);
            if (item) {
                item.invprice = newPrice; // Add or update the `invprice` property
            }
            calculateSubtotal();
        }


        function calculateSubtotal() {
            const subtotal = items.reduce((sum, item) => sum + (item.invprice || 0), 0);
            document.getElementById('subT').textContent = `$${subtotal.toFixed(2)}`;
            document.getElementById('subTotal').value = subtotal;
        }


        function renderItems() {
            itemsBody.innerHTML = ''; // Clear existing rows

            if (items.length === 0) {
                // If no items, display the "No Item Available" row
                const noItemsRow = document.createElement('tr');
                noItemsRow.innerHTML = '<td colspan="5" class="!text-center font-semibold">No Tasks Available</td>';
                itemsBody.appendChild(noItemsRow);
            } else {
                // Iterate over items and create rows
                items.forEach(item => {
                    const row = document.createElement('tr');
                    row.classList.add('border-b', 'border-[#e0e6ed]', 'align-top', 'dark:border-[#1b2e4b]');

                    row.innerHTML = `
                                <td>
                                <p>${item.description}</p>
                                </td>
                                <td>
                                <p>${item.client_name}</p>
                                </td>
                                <td>
                                  <p>${item.quantity}</p>
                                </td>
                                 <td>$${(item.total * item.quantity).toFixed(2)}</td>
                                <td>
                                        <input 
                                        id="invprice-${item.id}" 
                                        type="number" 
                                        name="invprice" 
                                        placeholder="Invoice Price" 
                                        class="form-input w-2/3 lg:w-[150px]" 
                                        oninput="updateItemPrice(${item.id})"
                                    />
                                </td>

                                <td>
                                    <button id="remove-button-${item.id}" type="button" onclick="" data-id="">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none" 
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                </td>
                            `;

                    itemsBody.appendChild(row);

                    removeButton = document.getElementById('remove-button-' + item.id);

                    removeButton.addEventListener('click', function() {
                        removeItem(item.id);
                    });
                });
            }
        }

        function removeItem(itemId) {
            items = items.filter(item => item.id !== itemId);
            renderItems(); // Re-render the table after removal
        }

        function chooseTasksAgent(agent) {

            agent = JSON.parse(agent);
            const agentId = agent.id;
            const agentName = agent.name;
            const agentEmail = agent.email;
            const agentPhone = agent.phone_number;

            itemsBody.innerHTML = '';
            document.getElementById('agentId').value = agentId;
            document.getElementById('agentName').value = agentName;
            document.getElementById('agentEmail').value = agentEmail;
            document.getElementById('agentPhone').value = agentPhone;
            let url = "{{ route('tasks.agent', ['agentId' => '_agentId_']) }}";
            url = url.replace('_agentId_', agentId);

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    tasks = data;
                    renderTaskList(tasks);
                })
                .catch(error => console.error(error));

            closeAgentModal();
        }
        // Show Select Client Tab
        selectTabButton.addEventListener('click', () => {
            selectTabButton.classList.add('text-blue-500', 'border-b-2', 'border-blue-500');
            selectTabButton.classList.remove('text-gray-500');
            addTabButton.classList.remove('text-blue-500', 'border-b-2', 'border-blue-500');
            addTabButton.classList.add('text-gray-500');

            selectTab.classList.remove('hidden');
            addTab.classList.add('hidden');
        });

        // Show Add New Client Tab
        addTabButton.addEventListener('click', () => {
            addTabButton.classList.add('text-blue-500', 'border-b-2', 'border-blue-500');
            addTabButton.classList.remove('text-gray-500');
            selectTabButton.classList.remove('text-blue-500', 'border-b-2', 'border-blue-500');
            selectTabButton.classList.add('text-gray-500');

            addTab.classList.remove('hidden');
            selectTab.classList.add('hidden');
        });

        if (Array.isArray(selectedTasks)) {
            // Iterate over the array and select each task
            selectedTasks.forEach(task => selectTask(task));
            // console.log('one', selectedTasks);
        } else {
            // console.log('tow', selectedTasks);
            // If it's a single task object, select it directly
            selectTask(selectedTasks);
        }

        // Function to select a task
        function selectTask(task) {
            console.log('task selected', task);
            items.push({
                ...task, // Spread the properties of the task object
                remark: '', // Add default empty remark
                quantity: 1, // Default quantity is 1
                description: `${task.reference} - ${task.type} ${task.additional_info} (${task.venue})`, // Custom description format
                client_name: task.client_name
            });

            // Set the selected task name
            selectedTaskName = `${task.reference}-${task.type}${task.additional_info}(${task.venue})`;

            // Call a function to update the total, passing the current items array
            //  updateTotal(items);

            closeTaskModal();
            renderItems();
        }

        function updateTotal(items) {
            const total = items.reduce((sum, item) => sum + (item.invoice_price * item.quantity),
                0); // Calculate total based on price and quantity
            this.subtotal = total;
            // this.updateSubTotal();
        };

        function openClientModal() {
            const modal = document.getElementById("clientModal");
            modal.classList.remove("hidden");
        }

        // Close Client Modal
        function closeClientModal() {
            const modal = document.getElementById("clientModal");
            modal.classList.add("hidden");
        }

        function openAgentModal() {
            const modal = document.getElementById("agentModal");
            modal.classList.remove("hidden");
        }

        // Close Agent Modal
        function closeAgentModal() {
            const modal = document.getElementById("agentModal");
            modal.classList.add("hidden");
        }

        function filterClients() {
            const searchValue = document.getElementById('clientSearchInput').value.toLowerCase();
            const filteredClients = clients.filter(client =>
                client.name.toLowerCase().includes(searchValue) || client.email.toLowerCase().includes(searchValue)
            );
            renderClientList(filteredClients);
        }

        function renderClientList(clientData) {
            const clientList = document.getElementById('clientList');
            clientList.innerHTML = '';
            clientData.forEach(client => {
                const li = document.createElement('li');
                li.className = 'cursor-pointer p-2 hover:bg-gray-100 text-gray-800';
                li.innerText = `${client.name} - ${client.email}`;
                li.onclick = () => selectClient(client);
                clientList.appendChild(li);
            });
        }

        function selectClient(client) {
            document.getElementById('receiverId').value = client.id;

            // Update input fields
            document.getElementById('receiverName').value = client.name;
            document.getElementById('receiverEmail').value = client.email;
            document.getElementById('receiverPhone').value = client.phone;
            closeClientModal();
        }

        function openTaskModal() {
            document.getElementById('taskModal').classList.remove('hidden');
        }

        function closeTaskModal() {
            document.getElementById('taskModal').classList.add('hidden');
        }

        function filterTasks() {
            const searchValue = document.getElementById('taskSearchInput').value.toLowerCase();
            const filteredTasks = tasks.filter(task =>
                task.reference.toLowerCase().includes(searchValue) || task.type.toLowerCase().includes(searchValue)
            );
            renderTaskList(filteredTasks);
        }

        function renderTaskList(taskData) {
            const taskList = document.getElementById('taskList');
            taskList.innerHTML = '';
            if (taskData.length == 0) {
                const p = document.createElement('p');
                p.className = 'text-center text-gray-500';
                p.innerText = 'No Task Available';
                taskList.appendChild(p);

                return;
            }
            taskData.forEach(task => {
                const li = document.createElement('li');
                li.className = 'cursor-pointer p-2 hover:bg-gray-100 text-gray-800';
                li.innerText = `${task.reference} - ${task.type} (${task.venue})`;
                li.onclick = () => selectTask(task);
                taskList.appendChild(li);
            });
        }

        // Call the function with the selectedClient object
        if (selectedClient && selectedAgent) {
            updateFormFields(selectedClient, selectedAgent);
        }

        function updateFormFields(client, agent) {
            // Update hidden fields
            document.getElementById('receiverId').value = client.id;

            // Update input fields
            document.getElementById('receiverName').value = client.name;
            document.getElementById('receiverEmail').value = client.email;
            document.getElementById('receiverPhone').value = client.phone;

            document.getElementById('agentName').value = agent.name;
            document.getElementById('agentEmail').value = agent.email;
            document.getElementById('agentPhone').value = agent.phone;
        }

        generateInvoiceButton.addEventListener('click', async function(event) {
            event.preventDefault(); // Prevent form submission or default action
            if (isSaving || isSaved) return; // Prevent multiple clicks while saving or after saved

            // Start saving
            isSaving = true;
            updateButtonState();

            try {
                // Simulate invoice generation (replace with your actual API call)
                await generateInvoice();
                updateButtonState();
            } catch (error) {
                console.error("Error generating invoice:", error);
                isSaving = false; // Reset saving state
                updateButtonState();

            }
        });

        // Function to update button state (text, loading spinner, disabled state)
        function updateButtonState() {
            if (isSaving) {
                buttonText.style.display = 'none';
                buttonLoading.style.display = 'inline-block';
                buttonSaved.style.display = 'none';
                generateInvoiceButton.disabled = true; // Disable button during saving
            } else if (isSaved) {
                buttonText.style.display = 'none';
                buttonLoading.style.display = 'none';
                buttonSaved.style.display = 'inline-block';
                generateInvoiceButton.disabled = false; // Re-enable button after saved
            } else {
                buttonText.style.display = 'inline-block';
                buttonLoading.style.display = 'none';
                buttonSaved.style.display = 'none';
                generateInvoiceButton.disabled = false; // Re-enable button if not saving or saved
            }
        }

        // Generate invoice
        async function generateInvoice() {

            const invoiceUrl = "{{ route('invoice.store') }}";
            const csrfToken = "{{ csrf_token() }}";

            const currency = document.getElementById('currency').value;
            const invoiceNumber = document.getElementById('invoiceNumber').value;
            const invdate = document.getElementById('invdate').value;
            const duedate = document.getElementById('duedate').value;
            const subTotal = document.getElementById('subTotal').value;
            const tasks = items;
            const clientId = document.getElementById('receiverId').value;
            const agentId = document.getElementById('agentId').value;

            // Show loading state
            buttonText.style.display = "none";
            buttonLoading.style.display = "inline";
            console.log(
                'clientId:', clientId,
                'agentId:', agentId,
                'tasksLength:', tasks.length,
            );
            if (!clientId || !agentId || !tasks.length) {
                console.error("Required data is missing.");
                let errorNotification = document.createElement('div');
                errorNotification.innerHTML = ` 
                 <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
                       Please Fill In All Required Data 
                     <button type="button" class="close text-white ml-2" aria-label="Close"
                         onclick="this.parentElement.style.display='none';">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>
                 `
                document.body.appendChild(errorNotification);
                resetButtonState();
                return;
            }

            try {
                const response = await fetch(invoiceUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        clientId,
                        agentId,
                        tasks,
                        subTotal,
                        invoiceNumber,
                        currency,
                        invdate,
                        duedate

                    })
                });
                if (!response.ok) {
                    throw new Error("Failed to reach the invoice controller.");
                }

                const result = await response.json();
                //const generatedLink = `http://127.0.0.1:8000/invoice/` + invoiceNumber;
                // const generatedLink = `https://tour.citytravelers.co/invoice/` + invoiceNumber;
                const generatedLink = appUrl + '/invoice/' + invoiceNumber;

                // Invoice link elements
                const invoiceLinkContainer = document.getElementById("invoice-link-container");
                const invoiceLink = document.getElementById("invoice-link");

                // Update and show the invoice link
                invoiceLink.href = generatedLink;
                invoiceLink.textContent = generatedLink;
                invoiceLinkContainer.style.display = "block";

                // Show success state
                isSaved = true; // Mark as saved after generating
                updateButtonState();

            } catch (error) {
                console.error('Error generating invoice:', error);
                let alert = document.createElement('div');
                alert.innerHTML = ` 
                 <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
                       Error Generating Invoice: make sure all fields are filled correctly
                     <button type="button" class="close text-white ml-2" aria-label="Close"
                         onclick="this.parentElement.style.display='none';">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>
                 `
                document.body.appendChild(alert);
                resetButtonState();
            } finally {
                // Reset button states
                buttonLoading.style.display = "none";
                setTimeout(() => {
                    resetButtonState();
                }, 1000);
            }
        };

        function resetButtonState() {
            isSaving = false;
            isSaved = false;
            updateButtonState();
        }

        document.addEventListener("DOMContentLoaded", function() {

            let tasks = @json($tasks);
            let clients = @json($clients);


            // Initial rendering of items
            renderItems();


            // Initialize modals with full data
            renderClientList(clients);
            renderTaskList(tasks);



        });
    </script>


</x-app-layout>