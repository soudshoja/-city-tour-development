<x-app-layout>
    <style>
        button[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        table {
            width: 100%;
            /* Ensure table takes up full width */
            border-spacing: 0;
            /* Remove extra spacing between cells */
            border-collapse: collapse;
            /* Merge borders */
            table-layout: fixed;

        }

        dialog::backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        #invoiceModalComponent {
            .task-details {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                padding-top: 1rem;
            }

            details>div {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(26rem, 1fr));
                gap: 1rem;
                margin-top: 1rem;
            }

            /* The switch - the box around the slider */
            .switch {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 22px;
            }

            /* Hide default HTML checkbox */
            .switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            /* The slider */
            .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                -webkit-transition: .4s;
                transition: .4s;
            }

            .slider:before {
                position: absolute;
                content: "";
                height: 14px;
                width: 14px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                -webkit-transition: .4s;
                transition: .4s;
            }

            input:checked+.slider {
                background-color: #2196F3;
            }

            input:focus+.slider {
                box-shadow: 0 0 1px #2196F3;
            }

            input:checked+.slider:before {
                -webkit-transform: translateX(18px);
                -ms-transform: translateX(18px);
                transform: translateX(18px);
            }

            /* Rounded sliders */
            .slider.round {
                border-radius: 20px;
            }

            .slider.round:before {
                border-radius: 50%;
            }
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

    <div id="invoiceModalComponent">
        <div class="flex flex-col gap-2.5 xl:flex-row">
            <div class="panel flex-1 px-0 py-6 lg:mr-6 ">
                <div class="flex flex-wrap justify-between px-6">
                    <div class="shrink-0 items-center text-black dark:text-white min-w-72 max-w-sm">
                        <div class="flex items-center space-x-4">
                            <x-application-logo class="h-20 w-auto" />
                            @if ($selectedCompany)
                            <div>
                                <h3 class="font-semibold text-lg">{{ $selectedCompany->name }}</h3>
                                <p>{!! nl2br(e($selectedCompany->address)) !!}</p>
                                <p>{{ $selectedCompany->email }}</p>
                                <p>{{ $selectedCompany->phone }}</p>
                            </div>
                        </div>
                        @else
                        <div class="custom-select w-full border rounded-lg mt-4">
                            <div class="select-trigger px-4 py-2 cursor-pointer dark:text-white">Select Company
                            </div>
                            <div
                                class="select-options hidden absolute left-0 top-full w-full rounded-md shadow-lg grid {{ count($branches) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 py-3">
                                @foreach ($companies as $company)
                                <div class="select-option px-4 py-3 text-center bg-white dark:bg-gray-700 BoxShadow rounded-lg dark:hover:bg-gray-800 border border-gray-300 cursor-pointer"
                                    data-value="{{ $company->id }}">
                                    {{ $company->name }}
                                </div>
                                @endforeach
                            </div>
                            <input type="hidden" name="branch_id" id="selectedBranch">
                        </div>
                        @endif
                        <div class="custom-select w-full border rounded-lg mt-4">
                            <div class="select-trigger px-4 py-2 cursor-pointer dark:text-white">Select Branch</div>
                            <div
                                class="select-options hidden absolute left-0 top-full w-full rounded-md shadow-lg grid {{ count($branches) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 py-3">
                                @foreach ($branches as $branch)
                                <div class="select-option px-4 py-3 text-center bg-white dark:bg-gray-700 BoxShadow rounded-lg dark:hover:bg-gray-800 border border-gray-300 cursor-pointer"
                                    data-value="{{ $branch->id }}">
                                    {{ $branch->name }}
                                </div>
                                @endforeach
                            </div>
                            <input type="hidden" name="branch_id" id="selectedBranch">
                        </div>
                        @if($isRefund ?? false)
                        <div class="mt-4">
                            <input id="refundRemarks" type="text" name="refundRemarks" value="{{ $refundRemarks ?? '' }}"
                                class="w-full form-input" placeholder="Refund Remarks" />
                        </div>
                        @endif
                    </div>
                    <div class="space-y-1 text-gray-900 dark:text-gray-400 mt-2">
                        <div class="flex items-center w-full">
                            <label for="invoiceNumber" class="w-full text-sm font-semibold">Invoice Number</label>
                            <input id="invoiceNumber" type="text" name="invoiceNumber" value="{{ $invoiceNumber }}"
                                class="w-full form-input" placeholder="Invoice Number" />
                        </div>
                        
                        @if($isRefund ?? false)
                        <div class="flex items-center w-full">
                            <label for="RefundNumber" class="w-full text-sm font-semibold">Refund Number</label>
                            <input id="RefundNumber" type="text" name="RefundNumber" value="{{ $refundNumber ?? ''}}"
                                class="w-full form-input" placeholder="Refund Number" />
                        </div>
                        @endif

                        <div class="mt-4 flex items-center">
                            <label for="invoiceDate" class="w-full text-sm font-semibold">Invoice Date</label>
                            <input id="invoiceDate" type="date" name="invoiceDate" class="w-full form-input" value="{{ $todayDate }}" />
                        </div>
                        <div class="mt-4 flex items-center">
                            <label for="dueDate" class="w-full text-sm font-semibold">Due Date</label>
                            <input id="dueDate" type="date" name="dueDate" class="w-full form-input" value="{{$invoiceExpireDefault}}" />
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="button" onclick="location.reload()"
                                class="px-2 py-2 city-light-yellow text-white rounded hover:text-[#004c9e] flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.5 12a7.5 7.5 0 1 1 2.026 5.255M3 12H8m-5 0V7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />
                <div class="flex justify-between px-4 gird gird-cols-2 gap-4">
                    <div class="w-full">
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
                                    <path d="M21 10H19M19 10H17M19 10L19 8M19 10L19 12" stroke="#004c9e"
                                        stroke-width="1.5" stroke-linecap="round" />
                                </svg><span class="pl-5">Choose Client</span>
                            </button>
                            <input id="receiverId" type="hidden" name="receiverId" />
                            <input id="agentId" type="hidden" name="agentId"
                                value="{{ is_string($agentId) || is_numeric($agentId) ? $agentId : '' }}" />
                        </div>
                        <p class="my-2 text-gray-400 text-center text-xs">details will displaying below after choosing a
                            client</p>
                        <div class="mt-4 flex items-center">
                            <input id="receiverName" type="text" name="receiverName" class="form-input flex-1"
                                placeholder="Client Name" disabled />
                        </div>
                        <div class="mt-4 flex items-center">
                            <input id="receiverEmail" type="email" name="receiverEmail" class="form-input flex-1"
                                placeholder="Client Email" disabled />
                        </div>
                        <div class="mt-4 flex items-center">
                            <input id="receiverPhone" type="text" name="receiverPhone" class="form-input flex-1"
                                placeholder="Client Phone Number" disabled />
                        </div>
                    </div>

                    <div class="w-full">
                        <div class="flex items-center">
                            @can('pickAgent', App\Models\Invoice::class)
                            <button id="select-agent" type="button" onclick="openAgentModal()"
                                class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                                        city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#004c9e"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="10" cy="6" r="4" fill="#004c9e" />
                                    <path
                                        d="M18 17.5C18 19.9853 18 22 10 22C2 22 2 19.9853 2 17.5C2 15.0147 5.58172 13 10 13C14.4183 13 18 15.0147 18 17.5Z"
                                        fill="#004c9e" />
                                    <path d="M21 10H19M19 10H17M19 10L19 8M19 10L19 12" stroke="#004c9e"
                                        stroke-width="1.5" stroke-linecap="round" />
                                </svg><span class="pl-5">Choose Agent</span>
                            </button>
                            @else
                            <button disabled id="select-agent" type="button"
                                class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                                            city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#004c9e"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="10" cy="6" r="4" fill="#004c9e" />
                                    <path
                                        d="M18 17.5C18 19.9853 18 22 10 22C2 22 2 19.9853 2 17.5C2 15.0147 5.58172 13 10 13C14.4183 13 18 15.0147 18 17.5Z"
                                        fill="#004c9e" />
                                    <path d="M21 10H19M19 10H17M19 10L19 8M19 10L19 12" stroke="#004c9e"
                                        stroke-width="1.5" stroke-linecap="round" />
                                </svg><span class="pl-5">Choose Agent</span>
                            </button>
                            @endcan
                        </div>
                        <p class="my-2 text-gray-400 text-center text-xs">details will displaying below after choosing an Agent</p>
                        <div class="mt-4 flex items-center">
                            <input id="agentName" type="text" name="agentName" class="form-input flex-1"
                                placeholder="Agent Name"
                                value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->name : '' }}"
                                disabled />
                        </div>
                        <div class="mt-4 flex items-center">
                            <input id="agentEmail" type="email" name="agentEmail" class="form-input flex-1"
                                placeholder="Agent Email"
                                value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->email : '' }}"
                                disabled />
                        </div>
                        <div class="mt-4 flex items-center">
                            <input id="agentPhone" type="text" name="agentPhone" class="form-input flex-1"
                                placeholder="Agent Phone"
                                value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->phone : '' }}"
                                disabled />
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <div class="overflow-x-auto border border-gray-200">
                        <table id="itemsTable" class="text-left table-auto border-collapse w-full text-xs">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">No.</th>
                                    <th class="px-4 py-2 min-w-[200px] text-gray-900 dark:text-gray-100">Task Detail
                                    </th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Task Price</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Invoice Price</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Client Name</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Agent Name</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Branch Name</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Supplier Name</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Task Type</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Action</th>
                                </tr>
                            </thead>
                            <tbody id="items-body" class="divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Dynamically populated rows -->
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 flex flex-col justify-between px-4 sm:flex-row">
                        <div class="mb-6 sm:mb-0">
                            <button id="openTaskModalButton"
                                class="inline-flex items-center justify-center text-sm text-black font-semibold
                                     city-light-yellow hover:bg-[#004c9e] hover:text-white  py-2 px-4  rounded-full shadow">
                                <svg class="w-6 h-6 pr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path fill="currentColor"
                                        d="M19 11h-4v4h-2v-4H9V9h4V5h2v4h4m1-7H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2M4 6H2v14a2 2 0 0 0 2 2h14v-2H4z" />
                                </svg> Add Task
                            </button>
                        </div>
                        <div class="sm:w-2/5 flex justify-end">
                            <div class="mt-4 flex flex-col items-end font-semibold space-y-1">
                                <div class="flex items-center border-b pb-1 font-medium">
                                    <div class="mr-2">Total Net:</div>
                                    <span id="netT">0.00</span>
                                    <input id="netTotal" type="hidden" name="netTotal" />
                                </div>
                                <div class="flex items-center">
                                    <div class="mr-2">Invoice Total:</div>
                                    <span id="subT">0.00</span>
                                    <input id="subTotal" type="hidden" name="subTotal" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6 w-full xl:mt-0 xl:w-96">
                <div id="client-credit" class=" p-2 mb-2 bg-white rounded shadow flex flex-col gap-2 justify-between">
                    <div class="flex items-center justify-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
                        <span class="ml-2 text-gray-700">Loading...</span>
                    </div>
                </div>
                <div class="panel mb-5">
                    <select id="currency" name="currency" class="form-select">
                        <!-- You can add your options here -->
                        <option value="KWD">KWD</option>
                        <option value="MYR">MYR</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>
                </div>
                <div class="panel">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-1">
                        <button id="generate-invoice-btn" type="button"
                            class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                        city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mr-2">
                                <path
                                    d="M3.46447 20.5355C4.92893 22 7.28595 22 12 22C16.714 22 19.0711 22 20.5355 20.5355C22 19.0711 22 16.714 22 12C22 11.6585 22 11.4878 21.9848 11.3142C21.9142 10.5049 21.586 9.71257 21.0637 9.09034C20.9516 8.95687 20.828 8.83317 20.5806 8.58578L15.4142 3.41944C15.1668 3.17206 15.0431 3.04835 14.9097 2.93631C14.2874 2.414 13.4951 2.08581 12.6858 2.01515C12.5122 2 12.3415 2 12 2C7.28595 2 4.92893 2 3.46447 3.46447C2 4.92893 2 7.28595 2 12C2 16.714 2 19.0711 3.46447 20.5355Z"
                                    stroke="currentColor" stroke-width="1.5" />
                                <path
                                    d="M17 22V21C17 19.1144 17 18.1716 16.4142 17.5858C15.8284 17 14.8856 17 13 17H11C9.11438 17 8.17157 17 7.58579 17.5858C7 18.1716 7 19.1144 7 21V22"
                                    stroke="currentColor" stroke-width="1.5" />
                                <path opacity="0.5" d="M7 8H13" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" />
                            </svg>
                            <span id="button-text">
                                @if($isRefund ?? false)
                                    Process Refund
                                @else
                                    Generate Invoice
                                @endif
                            </span>
                            <span id="button-loading" style="display: none;">Saving...</span>
                            <span id="button-saved" style="display: none;">Saved</span>
                        </button>
                        <input id="invoiceId" type="hidden" name="invoiceId" />
                        <div id="errorMessage" class="hidden text-red-500">
                            <!-- Error message -->
                        </div>

                        <!-- Agents Modal -->
                        <div id="agentModal"
                            class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden">
                            <div class="bg-white border rounded-lg shadow-lg w-3/4 md:w-1/2 mb-10">
                                <div
                                    class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3">
                                    <h5 class="text-lg font-bold">Agent Management</h5>
                                    <button type="button" onclick="closeAgentModal()"
                                        class="text-white-dark hover:text-dark" id="closeAgentModalButton">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                            class="h-6 w-6">
                                            <line x1="18" y1="6" x2="6" y2="18">
                                            </line>
                                            <line x1="6" y1="6" x2="18" y2="18">
                                            </line>
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
                                    <li class="cursor-pointer flex items-center justify-between px-4 py-3 hover:bg-gray-100"
                                        onclick="chooseTasksAgent('{{ $agent }}')">
                                        {{ $agent->name }}
                                    </li>
                                    @endforeach
                                </ul>
                                <p id="noAgentsFound" class="flex flex-col items-center justify-center py-6 text-center text-gray-500 text-sm gap-2 hidden">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9.75 9.75a.75.75 0 011.5 0v4.5a.75.75 0 01-1.5 0v-4.5zm3 0a.75.75 0 011.5 0v4.5a.75.75 0 01-1.5 0v-4.5zM12 21a9 9 0 100-18 9 9 0 000 18z" />
                                    </svg>
                                    <span>No agents found matching your search</span>
                                </p>
                                <!-- ./List of Agents -->
                            </div>
                        </div>

                        <!-- Clients Modal -->
                        <div id="clientModal"
                            class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden ">
                            <div class="bg-white border rounded-lg shadow-lg  w-3/4 md:w-1/2 mb-10">
                                <div
                                    class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3">
                                    <h5 class="text-lg font-bold">Client Management</h5>
                                    <button type="button" class="text-white-dark hover:text-dark"
                                        id="closeClientModalButton">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                            class="h-6 w-6">
                                            <line x1="18" y1="6" x2="6" y2="18">
                                            </line>
                                            <line x1="6" y1="6" x2="18" y2="18">
                                            </line>
                                        </svg>
                                    </button>
                                </div>
                                <div class="border-b flex justify-center">
                                    <button class="tab-button px-4 py-2 text-blue-500 border-b-2 border-blue-500"
                                        id="selectTabButton">Select Client</button>
                                    <button class="tab-button px-4 py-2 text-gray-500 hover:text-blue-500"
                                        id="addTabButton">Add New Client</button>
                                </div>
                                <div id="selectTab" class="p-6">
                                    <div class="relative mb-4">
                                        <input type="text" placeholder="Search Client..."
                                            class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                                            id="clientSearchInput" oninput="filterClients()">

                                    </div>
                                    <ul id="clientList"
                                        class="shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] border rounded-lg mb-4 max-h-60 overflow-y-auto custom-scrollbar">
                                        <!-- Dynamic list items go here -->
                                    </ul>
                                </div>

                                <div id="addTab" class="p-6 hidden">
                                    <h6 class="text-lg font-bold mb-3">Add New Client</h6>
                                    <form method="POST" action="{{ route('clients.store') }}">
                                        @csrf

                                        <div class="mb-4 flex gap-4">
                                            <div class="w-1/2">
                                                <label for="first_name"
                                                    class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">First Name</label>
                                                <input id="first_name" name="first_name" type="text" required
                                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                    placeholder="First Name *" />
                                            </div>

                                            <div class="w-1/2">
                                                <label for="middle_name"
                                                    class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Middle Name</label>
                                                <input id="middle_name" name="middle_name" type="text"
                                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                    placeholder="Middle Name (Optional)" />
                                            </div>
                                        </div>

                                        <div class="mb-4 flex gap-4">
                                            <div class="w-1/2">
                                                <label for="last_name"
                                                    class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Last Name</label>
                                                <input id="last_name" name="last_name" type="text"
                                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                    placeholder="Last Name" />
                                            </div>

                                            <div class="w-1/2">
                                                <label for="email"
                                                    class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                                                <input id="email" name="email" type="email"
                                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                    placeholder="Client Email" />
                                            </div>
                                        </div>

                                        <div class="mb-4 flex gap-4">
                                            <div class="w-1/2">
                                                <label for="dial_code" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Country Code</label>
                                                <select name="dial_code" id="dial_code"
                                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                                    @foreach ($countries as $country)
                                                    <option value="{{ $country->dialing_code }}" {{ $country->dialing_code == '+965' ? 'selected' : '' }}>
                                                        {{ $country->name }} ({{ $country->dialing_code }})
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="w-1/2">
                                                <label for="phone" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone Number</label>
                                                <input id="phone" name="phone" type="text" required
                                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                    placeholder="Client Phone *" />
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="civil_no" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Civil ID</label>
                                            <input id="civil_no" name="civil_no" type="text" required
                                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                placeholder="Civil ID *" />
                                        </div>

                                        <div class="mb-4">
                                            <label for="agent_id" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Agent</label>
                                            @unlessrole('agent')
                                            <select name="agent_id" id="agent_id"
                                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                                <option value="" disabled selected>Select Agent</option>
                                                @foreach ($agents as $agent)
                                                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                                @endforeach
                                            </select>
                                            @else
                                            <input type="text" name="agent_id" id="agent_id"
                                                value="{{ auth()->user()->agent->name }}"
                                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline" readonly />
                                            <input type="hidden" name="agent_id" value="{{ auth()->user()->agent->id }}">
                                            @endunlessrole
                                        </div>

                                        <div class="flex items-center justify-center">
                                            <button type="submit"
                                                class="btnCityGrayColor mt-3 w-full bg-black BtColor text-white px-4 py-2 rounded-lg">
                                                Register Client
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Tasks Modal -->
                        <div id="taskModal"
                            class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden">
                            <div class="bg-white border rounded-lg shadow-lg w-10/12 max-h-[80vh] flex flex-col">
                                <div class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3">
                                    <h5 class="text-lg font-bold">Choose Task</h5>
                                    <button type="button" class="text-white-dark hover:text-dark" id="closeTaskModalButton">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                </div>
                                <div class="m-6">
                                    <div class="flex items-center justify-between mb-6 gap-4">
                                        <div class="w-full max-w-xs">
                                            <input type="text" placeholder="Search Task..."
                                                class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                                                id="taskSearchInput" oninput="filterTasks()">
                                        </div>
                                        {{-- HIDE: Add Task For Specific Supplier --}}
                                        {{-- <div x-data="{ addTaskModal: false }">
                                            <div @click="addTaskModal = true"
                                                class="p-2 text-center bg-white rounded-full shadow group hover:bg-black dark:hover:bg-gray-600 dark:bg-gray-700 cursor-pointer"
                                                data-tooltip-left="Add Task">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="stroke-black dark:stroke-gray-300 group-hover:stroke-white group-focus:stroke-white">
                                                    <path d="M15 12L12 12M12 12L9 12M12 12L12 9M12 12L12 15"
                                                        stroke-width="1.5" stroke-linecap="round" />
                                                    <path
                                                        d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7"
                                                        stroke-width="1.5" stroke-linecap="round" />
                                                </svg>
                                            </div>
                                            <div x-cloak x-show="addTaskModal"
                                                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-20">
                                                <div @click.away="addTaskModal = false" class="bg-white rounded shadow w-96">
                                                    <div class="p-4 flex justify-between items-center">
                                                        Add Task For Specific Supplier
                                                    </div>
                                                    <hr>
                                                    <form id="agent-supplier-task" action="{{ route('tasks.agent.upload') }}"
                                                        class="p-4 flex flex-col gap-2" method="POST" enctype="multipart/form-data">
                                                        @csrf

                                                        @unlessrole('agent')
                                                        <select name="agent_id" id="task-agent-id"
                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-black">
                                                            <option value="">Select Agent</option>
                                                            @foreach ($agents as $agent)
                                                            <option value="{{ $agent->id }}" data-client="{{ $agent }}">{{ $agent->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @else
                                                        <input type="hidden" name="agent_id" value="{{ auth()->user()->agent->id }}">
                                                        @endunlessrole

                                                        <select name="supplier_id" id="select-supplier-task"
                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-black">
                                                            <option value="">Select Supplier</option>
                                                            @foreach ($suppliers as $supplier)
                                                            <option value="{{ $supplier->id }}" data-supplier="{{ $supplier }}">{{ $supplier->name }}</option>
                                                            @endforeach
                                                        </select>

                                                        <div id="form-task-container" class="mt-2" data-company-id="{{ $companyId }}"></div>
                                                    </form>
                                                    <hr>
                                                    <div class="p-4 flex justify-between items-center">
                                                        <button @click="addTaskModal = false" class="text-red-500">Cancel</button>
                                                        <x-primary-button type="submit" form="agent-supplier-task">Submit</x-primary-button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div> --}}
                                    </div>

                                    <!-- List of Tasks -->
                                    <div class="overflow-y-auto max-h-[calc(80vh-12rem)]">
                                        <table id="taskList" class="min-w-full table-auto border-collapse border rounded-lg">
                                            <thead class="sticky top-0 bg-gray-100 z-10">
                                                <tr class="bg-gray-100">
                                                    <th class="px-4 py-2 text-left">Reference</th>
                                                    <th class="px-4 py-2 text-left">Task Price</th>
                                                    <th class="px-4 py-2 text-left">Type</th>
                                                    <th class="px-4 py-2 text-left">Client</th>
                                                    <th class="px-4 py-2 text-left">Agent</th>
                                                    <th class="px-4 py-2 text-left">Branch</th>
                                                    <th class="px-4 py-2 text-left">Supplier</th>
                                                </tr>
                                            </thead>
                                            <tbody id="taskListBody"></tbody>
                                        </table>
                                    </div>

                                    <!-- Scrollable Body Wrapper -->
                                    <div class="overflow-y-auto max-h-60">
                                        <table class="min-w-full table-auto border-collapse border rounded-lg">
                                            <tbody>
                                                <!-- Dynamic task rows will be added here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedInvoiceTasks = @json($selectedTasks);
        let branches = @json($branches);
        let clients = @json($clients);
        let agents = @json($agents);
        let items = [];
        let tasks = [];
        let allTasks = [];
        let filteredTasks = [];
        const itemsBody = document.getElementById('items-body');
        const appUrl = @json($appUrl ?? null);
        let toggle = false;
        let selectedPaymentLink = null;
        let netTotal = 0;
        const isRefund = @json($isRefund ?? false);
        const refundNumber = @json($refundNumber ?? null);

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

        const invoiceIdInput = document.getElementById('invoiceId');

        if (selectedInvoiceTasks !== null && selectedInvoiceTasks.length > 0) {
            selectedInvoiceTasks.forEach(task => {
                selectTask(task);
            });
        }

        if (selectedPaymentLink !== null) {
            isSaved = true;
            updateButtonState()
        }

        function checkInvoiceId() {
            const clientButton = document.getElementById("openClientModalButton");
            const agentButton = document.getElementById("select-agent");
            const taskButton = document.getElementById("openTaskModalButton");
            const generateInvoice = document.getElementById("generate-invoice-btn");

            const options = document.querySelectorAll('.select-option');
            const selectedBranchInput = document.getElementById('selectedBranch');

            // Add click event listener to each option
            options.forEach(option => {
                option.addEventListener('click', function() {
                    // Get the data-value attribute from the clicked option
                    const branchId = this.getAttribute('data-value');

                    // Update the hidden input value
                    selectedBranchInput.value = branchId;
                    console.log(selectedBranchInput.value);
                    // Optional: Add active styling to the selected option
                    options.forEach(opt => opt.classList.remove(
                        'active')); // Remove active class from others
                    this.classList.add('active'); // Add active class to clicked option
                });
            });

            if (!invoiceIdInput.value) {
                clientButton.disabled = false;
                agentButton.disabled = false;
                taskButton.disabled = false;
                generateInvoiceButton.disabled = false;
                generateInvoice.classList.remove('hidden');

            } else {
                clientButton.disabled = true;
                agentButton.disabled = true;
                taskButton.disabled = true;
                generateInvoiceButton.disabled = false;
                generateInvoice.classList.add('hidden');
            }
        }

        // Run the check on page load and whenever the input value changes
        document.addEventListener('DOMContentLoaded', checkInvoiceId);
        invoiceIdInput.addEventListener('input', checkInvoiceId);

        // Set initial states
        let isSaving = false;
        let isSaved = false;

        function updateField(itemId, fieldId) {
            const inputField = document.getElementById(`${fieldId}-${itemId}`);
            const newValue = inputField ? inputField.value : '';

            const item = items.find(item => item.id === itemId);
            if (item) {
                if (fieldId.includes('invprice')) {
                    const numeric = newValue === '' ? '' : Number(newValue);
                    fieldId1 = 'invprice';
                    item.invprice = numeric;

                    if (fieldId === 'invprice-modal') {
                        const tableInput = document.getElementById(`invprice-table-${itemId}`);
                        if (tableInput) {
                            tableInput.value = newValue;
                        }
                    } else if (fieldId === 'invprice-table') {
                        const modalInput = document.getElementById(`invprice-modal-${itemId}`);
                        if (modalInput) {
                            modalInput.value = newValue;
                        }
                    }

                    calculateSubtotal(); // Recalculate the subtotal

                    const nettValue = (item.invprice - item.total);
                    netTotal = nettValue;

                    let existingAlert = document.getElementById("errorNotification");

                    if (nettValue <= 0) {
                        if (!existingAlert) {
                            let errorNotification = document.createElement('div');
                            errorNotification.id = "errorNotification"; // Prevent duplicate alerts
                            errorNotification.innerHTML = ` 
                            <div class="alert alert-danger fixed top-5 right-5 bg-red-500 text-white p-4 rounded shadow-lg">
                                The Invoice Price must be higher than the Task Price.
                                <button type="button" class="close text-white ml-2" aria-label="Close"
                                    onclick="document.getElementById('errorNotification').remove();">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>`;

                            document.body.appendChild(errorNotification);

                            setTimeout(() => {
                                let alertBox = document.getElementById("errorNotification");
                                if (alertBox) {
                                    alertBox.remove();
                                }
                            }, 10000);
                        }
                    } else {
                        if (existingAlert) {
                            existingAlert.remove();
                        }
                    }
                } else {
                    item[fieldId] = newValue; // Update other fields
                }
            }

        }

        function calculateSubtotal() {
            const subtotal = items.reduce((sum, item) => {
                const n = Number(item?.invprice);
                return sum + (Number.isFinite(n) ? n : 0);
            }, 0);
            const netTotals = items.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0);

            const subT = document.getElementById('subT');
            if (subT) subT.textContent = subtotal.toFixed(2);

            const subTotal = document.getElementById('subTotal');
            if (subTotal) subTotal.value = subtotal;

            const netT = document.getElementById('netT');
            if (netT) netT.textContent = netTotals.toFixed(2);

            const netTotal = document.getElementById('netTotal');
            if (netTotal) netTotal.value = netTotals.toFixed(2);
        }

        function renderItems() {
            itemsBody.innerHTML = ''; // Clear existing rows

            if (items.length === 0) {
                // If no items, display the "No Item Available" row
                const noItemsRow = document.createElement('tr');
                noItemsRow.innerHTML =
                    '<td colspan="15" class="w-full !text-center font-semibold text-gray-900 dark:bg-[#121e32] dark:text-white">No Tasks Available</td>';
                itemsBody.appendChild(noItemsRow);
            } else {
                // Iterate over items and create rows
                let count = 0;
                items.forEach(item => {
                    const row = document.createElement('tr');
                    row.classList.add('border-b', 'border-[#e0e6ed]', 'align-top', 'dark:border-[#1b2e4b]');
                    row.classList.add('TrX');

                    row.innerHTML = `
                    <td class="flex-grow">
                    <p>${++count}</p>
                    </td>
                    <td class="flex-grow">
                    <p><b>${item.description}</b><br>Info: ${item.additional_info}</br>
                    </p>
                    </td>
                    <td>
                    <p>${item.total} KWD</p>
                    </td>
                    <td>
                        <input
                            id="invprice-table-${item.id}"
                            type="text"
                            inputmode="decimal"
                            pattern="\d*\.?\d*"
                            class="border border-gray-300 rounded-md px-2 py-1 text-center w-28 min-w-[7rem] invoice-price-${item.id}"
                            value="${item.invprice ?? ''}"
                            oninput="updateField(${item.id}, 'invprice-table')"
                        />
                    </td>
                    <td>
                    <p>${item.client_name}</p>
                    </td>
                    <td>
                    <p>${item.agent.name}</p>
                    </td>
                    <td>
                    <p>${item.agent.branch.name}</p>
                    </td>
                    <td>
                        <p>${item.supplier_name}</p>
                    </td>
                    <td>
                        <p>${item.type.charAt(0).toUpperCase() + item.type.slice(1)}</p>
                    </td>
                    <td>
                    <div
                        class="inline-flex items-center justify-evenly">
                        <div 
                            id="modal-open-button_${item.id}"
                            data-tooltip-left="See Details">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" >
                            <path d="M14.3601 4.07866L15.2869 3.15178C16.8226 1.61607 19.3125 1.61607 20.8482 3.15178C22.3839 4.68748 22.3839 7.17735 20.8482 8.71306L19.9213 9.63993M14.3601 4.07866C14.3601 4.07866 14.4759 6.04828 16.2138 7.78618C17.9517 9.52407 19.9213 9.63993 19.9213 9.63993M14.3601 4.07866L12 6.43872M19.9213 9.63993L14.6607 14.9006L11.5613 18L11.4001 18.1612C10.8229 18.7383 10.5344 19.0269 10.2162 19.2751C9.84082 19.5679 9.43469 19.8189 9.00498 20.0237C8.6407 20.1973 8.25352 20.3263 7.47918 20.5844L4.19792 21.6782M4.19792 21.6782L3.39584 21.9456C3.01478 22.0726 2.59466 21.9734 2.31063 21.6894C2.0266 21.4053 1.92743 20.9852 2.05445 20.6042L2.32181 19.8021M4.19792 21.6782L2.32181 19.8021M2.32181 19.8021L3.41556 16.5208C3.67368 15.7465 3.80273 15.3593 3.97634 14.995C4.18114 14.5653 4.43213 14.1592 4.7249 13.7838C4.97308 13.4656 5.26166 13.1771 5.83882 12.5999L8.5 9.93872" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <dialog data-modal-invoice="${item.id}" class="rounded-md h-near-full w-1/2 min-h-80 overflow-y-scroll">
                            <div class="flex justify-between text-center items-center p-4 border-b border-black">
                                <h2 class="text-lg font-bold text-center text-gray-700">TASK DETAILS</h2>
                                <button class="text-gray-500 hover:text-gray-800" id="modal-close-button_${item.id}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                            <div id="task-details_${item.id}" class="min-w-72 w-full p-4 text-lg"> </div> 
                        </dialog>


                        <div class="ml-4 cursor-pointer" onclick="removeItem(${item.id})" data-tooltip-left="Remove Item">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3 6H21M10 11V17M14 11V17M5 6H19L18 21H6L5 6ZM8 6V4C8 3.44772 8.44772 3 9 3H15C15.5523 3 16 3.44772 16 4V6" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>

                    </div>
                    </td>
                `;
                    itemsBody.appendChild(row);

                    let taskDetails = document.getElementById('task-details_' + item.id);
                    taskDetails.innerHTML = `
                                <div class="mb-4 flex flex-col gap-2"> 
                                    <div class="header text-lg font-bold mt-4 border-b">Task Details</div> 
                                    <div class="flex justify-between items-center text-lg">
                                        <div>Quantitiy: <strong>${item.quantity}</strong></div>
                                        <div class="font-bold">${(item.quantity * item.total).toFixed(2)} KWD</div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <input
                                        id="invprice-modal-${item.id}"
                                        class="invoice-price-${item.id}"
                                        type="number"
                                        name="invprice"
                                        placeholder="Enter Invoice Price"
                                        class="border border-gray-300 p-2 rounded-md"
                                        onInput="updateField(${item.id}, 'invprice-modal')"
                                        value="${item.invprice ?? ''}"
                                    >
                                    <input
                                        id="remark-${item.id}"
                                        type="text"
                                        name="remark"
                                        placeholder="Enter Remark"
                                        class="border border-gray-300 p-2 rounded-md"
                                        onInput="updateField(${item.id}, 'remark')"
                                    >
                                    <input
                                        id="note-${item.id}"
                                        type="text"
                                        name="note"
                                        placeholder="Enter Note"
                                        class="border border-gray-300 p-2 rounded-md"
                                        onInput="updateField(${item.id}, 'note')"
                                    >
                                    </div>
                                </div>
                        `;

                    if (item.flight_details !== null && item.hotel_details !== null) {
                        taskDetails.innerHTML = '<div class="text-red-500">Something Went Wrong</div>';
                    } else if (item.flight_details !== null) {
                        taskDetails.innerHTML += `

                        <style>
                        .flight-details-container {
                            display: grid;
                            grid-template-columns: 1fr; /* Single column for mobile */
                            gap: 16px;
                            padding: 20px;
                            background-color: #f9fafb; /* Light gray background */
                            border-radius: 10px;
                            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
                        }

                        @media (min-width: 768px) { /* Two columns on medium screens and up */
                            .flight-details-container {
                                grid-template-columns: repeat(2, 1fr);
                            }
                        }
                        .flight-details {
                            display: grid;
                            grid-template-columns: repeat(2, 1fr); /* Two columns */
                            gap: 10px;
                            padding: 20px;
                            background-color: #f8f9fa; /* Light background */
                            border-radius: 10px;
                            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                        }

                        .flight-details div {
                            background-color: #ffffff; /* White background for items */
                            padding: 10px;
                            border-radius: 5px;
                            border-left: 5px solid #007bff; /* Blue accent */
                            font-weight: bold;
                            font-size: 14px;
                            color: #333;
                        }

                        .flight-details div:nth-child(odd) {
                            background-color: #e3f2fd; /* Light blue for alternating items */
                        }

                        .flight-detail {
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            background: #ffffff; /* White background */
                            padding: 12px;
                            border-radius: 8px;
                            box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1);
                            transition: transform 0.2s ease-in-out;
                        }

                        .flight-detail:hover {
                            transform: scale(1.02);
                        }

                        .flight-detail i {
                            color: #4f46e5; /* Indigo color for icons */
                            font-size: 18px;
                        }

                        .flight-detail strong {
                            font-weight: bold;
                            color: #1f2937; /* Dark gray text */
                        }

                        .flight-detail span {
                            color: #374151; /* Slightly lighter gray */
                            font-weight: 500;
                        }
                                                </style>
                                                
                                            <div class="text-lg font-bold mt-4 flex text-center gap-2">
                            <i class="fas fa-plane-departure text-blue-500"></i>
                            Flight Details
                        </div>
                        <hr class="my-2"/>

                        <div class="flex flex-row-reverse items-center gap-2">
                            <label class="switch">
                                <input type="checkbox" id="" onclick="toggleAll(${item.id})">
                                <span class="slider round"></span>
                            </label>
                            <strong>Toggle All</strong>
                        </div>

                        <form>
                            <div class="task-details p-4 rounded-lg shadow-md bg-white">
                                
                                <!-- Ticket Info -->
                                <details class="group border rounded-lg overflow-hidden shadow-sm mb-4">
                                    <summary class="flex items-center justify-between bg-gray-100 p-3 cursor-pointer">
                                        <h3 class="font-semibold flex items-center gap-2">
                                            <i class="fas fa-ticket-alt text-green-500"></i> Ticket Info
                                        </h3>
                                        <i class="fas fa-chevron-right transition-transform duration-300 group-open:rotate-90"></i>
                                    </summary>
                                    <div class="p-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                        <div><strong>Departure Time:</strong> ${item.flight_details.departure_time}</div>
                                        <div><strong>Country From:</strong> ${item.flight_details.country_from ? item.flight_details.country_from : 'null'}</div>
                                        <div><strong>Airport From:</strong> ${item.flight_details.airport_from ? item.flight_details.airport_from : 'null'} </div>
                                        <div><strong>Terminal From:</strong> ${item.flight_details.terminal_from}</div>
                                        <div><strong>Arrival Time:</strong> ${item.flight_details.arrival_time}</div>
                                        <div><strong>Country To:</strong> ${item.flight_details.country_to ? item.flight_details.country_to : 'null' }</div>
                                        <div><strong>Airport To:</strong> ${item.flight_details.airport_to ? item.flight_details.airport_to : 'null'}</div>
                                        <div><strong>Terminal To:</strong> ${item.flight_details.terminal_to}</div>
                                        <div><strong>Seat No:</strong> ${item.flight_details.seat_no}</div>
                                        <div><strong>Flight Meal:</strong> ${item.flight_details.flight_meal}</div>
                                        <div><strong>Equipment:</strong> ${item.flight_details.equipment}</div>
                                        <div><strong>Baggage Allowed:</strong> ${item.flight_details.baggage_allowed}</div>
                                        <div><strong>Class Type:</strong> ${item.flight_details.class_type}</div>
                                        <div><strong>Airline ID:</strong> ${item.flight_details.airline_id}</div>
                                    </div>
                                </details>
                                
                                <!-- Route Info -->
                                <details class="group border rounded-lg overflow-hidden shadow-sm mb-4">
                                    <summary class="flex items-center justify-between bg-gray-100 p-3 cursor-pointer">
                                        <h3 class="font-semibold flex items-center gap-2">
                                            <i class="fas fa-route text-blue-500"></i> Route Info
                                        </h3>
                                        <i class="fas fa-chevron-right transition-transform duration-300 group-open:rotate-90"></i>
                                    </summary>
                                    <div class="p-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                        <!-- Route details will be added here -->
                                    </div>
                                </details>
                                
                                <!-- Fare Info -->
                                <details class="group border rounded-lg overflow-hidden shadow-sm mb-4">
                                    <summary class="flex items-center justify-between bg-gray-100 p-3 cursor-pointer">
                                        <h3 class="font-semibold flex items-center gap-2">
                                            <i class="fas fa-dollar-sign text-yellow-500"></i> Fare Info
                                        </h3>
                                        <i class="fas fa-chevron-right transition-transform duration-300 group-open:rotate-90"></i>
                                    </summary>
                                    <div class="p-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                        <div><strong>Farebase:</strong> ${item.flight_details.farebase}</div>
                                    </div>
                                </details>
                                
                                <!-- Void Info -->
                                <details class="group border rounded-lg overflow-hidden shadow-sm mb-4">
                                    <summary class="flex items-center justify-between bg-gray-100 p-3 cursor-pointer">
                                        <h3 class="font-semibold flex items-center gap-2">
                                            <i class="fas fa-ban text-red-500"></i> Void Info
                                        </h3>
                                        <i class="fas fa-chevron-right transition-transform duration-300 group-open:rotate-90"></i>
                                    </summary>
                                    <div class="p-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                        <!-- Void details will be added here -->
                                    </div>
                                </details>
                                
                            </div>
                        </form>

                                                `;

                    } else if (item.hotel_details !== null) {

                        taskDetails.innerHTML += `
                                        <div class="text-lg font-bold mt-4">Hotel Details</div>
                        <hr/>
                        <div class="flex flex-row-reverse items-center">
                            <div class="p-2">
                                <label class="switch">
                                    <input type="checkbox" id="" onclick="toggleAll(${item.id})">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <strong>Toggle All</strong>
                        </div>
                        <form>
                            <div class="task-details" style="box-sizing: border-box;">
                                <details class="bg-gray-200 p-2 rounded-md group">
                                    <summary class="list-none flex flex-wrap items-center cursor-pointer">
                                        <h3 class="flex flex-1 p-4 font-semibold">General Information</h3>
                                        <div class="flex w-10 items-center justify-center">
                                            <div class="border-8 border-transparent border-l-black ml-2 group-open:rotate-90 transition-transform origin-left"></div>
                                        </div>
                                    </summary>
                                    <div class="p-4">
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Hotel</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.hotel_details.hotel.name}" disabled>
                                            </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Booking Time</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.hotel_details.booking_time}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Check-in</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.hotel_details.check_in}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Check-out</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.hotel_details.check_out}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Room Number</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.hotel_details.room_number}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Room Type</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.hotel_details.room_type}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Room Amount</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.hotel_details.room_amount}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Room Details</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.hotel_details.room_details}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Rate</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.hotel_details.rate}" disabled>
                                        </div>
                                    </div>
                                </details>
                                <details class="bg-gray-200 p-2 rounded-md group">
                                    <summary class="list-none flex flex-wrap items-center cursor-pointer">
                                        <h3 class="flex flex-1 p-4 font-semibold">Service Information</h3>
                                        <div class="flex w-10 items-center justify-center">
                                            <div class="border-8 border-transparent border-l-black ml-2 group-open:rotate-90 transition-transform origin-left"></div>
                                        </div>
                                    </summary>
                                    <div></div>
                                </details>
                                <details class="bg-gray-200 p-2 rounded-md group">
                                    <summary class="list-none flex flex-wrap items-center cursor-pointer">
                                        <h3 class="flex flex-1 p-4 font-semibold">Account Information</h3>
                                        <div class="flex w-10 items-center justify-center">
                                            <div class="border-8 border-transparent border-l-black ml-2 group-open:rotate-90 transition-transform origin-left"></div>
                                        </div>
                                    </summary>
                                    <div></div>
                                </details>
                                <details class="bg-gray-200 p-2 rounded-md group">
                                    <summary class="list-none flex flex-wrap items-center cursor-pointer">
                                        <h3 class="flex flex-1 p-4 font-semibold">Remarks</h3>
                                        <div class="flex w-10 items-center justify-center">
                                            <div class="border-8 border-transparent border-l-black ml-2 group-open:rotate-90 transition-transform origin-left"></div>
                                        </div>
                                    </summary>
                                    <div></div>
                                </details>
                                <details class="bg-gray-200 p-2 rounded-md group">
                                    <summary class="list-none flex flex-wrap items-center cursor-pointer">
                                        <h3 class="flex flex-1 p-4 font-semibold">Print Information</h3>
                                        <div class="flex w-10 items-center justify-center">
                                            <div class="border-8 border-transparent border-l-black ml-2 group-open:rotate-90 transition-transform origin-left"></div>
                                        </div>
                                    </summary>
                                    <div></div>
                                </details>
                            </div>
                        </form>
                        `;
                    }


                    let openButton = document.getElementById('modal-open-button_' + item.id);
                    let closeButton = document.getElementById('modal-close-button_' + item.id);
                    let modalInvoice = document.querySelector('dialog[data-modal-invoice="' + item.id + '"]');

                    openButton.addEventListener('click', function() {
                        // console.log(item.id);
                        modalInvoice.showModal();
                    });

                    closeButton.addEventListener('click', function() {
                        modalInvoice.close();
                    });

                    modalInvoice.addEventListener('click', function(event) {
                        if (event.target === modalInvoice) {
                            modalInvoice.close();
                        }
                    });
                });
            }


        }

        function toggleAll(itemId) {
            toggle = !toggle;
            let taskDetails = document.getElementById('task-details_' + itemId);
            if (toggle) {
                let detailsElement = taskDetails.querySelectorAll('details');
                detailsElement.forEach(element => {
                    element.open = true;
                });
            } else {
                let detailsElement = taskDetails.querySelectorAll('details');
                detailsElement.forEach(element => {
                    element.open = false;
                });
            }
        }

        function removeItem(itemId) {
            items = items.filter(item => item.id !== itemId);
            calculateSubtotal(); //re-calculate the total after remove item
            renderItems(); // Re-render the table after removal
            refreshTaskList();
        }

        function chooseTasksAgent(agent) {
            agent = JSON.parse(agent);
            const agentId = agent.id;
            const agentName = agent.name;
            const agentEmail = agent.email;
            const agentPhone = agent.phone_number;

            items = [];
            renderItems();
            calculateSubtotal();
            document.getElementById('agentId').value = agentId;
            document.getElementById('agentName').value = agentName;
            document.getElementById('agentEmail').value = agentEmail;
            document.getElementById('agentPhone').value = agentPhone;

            closeAgentModal();
            refreshTaskList();
        }

        selectTabButton.addEventListener('click', () => {
            selectTabButton.classList.add('text-blue-500', 'border-b-2', 'border-blue-500');
            selectTabButton.classList.remove('text-gray-500');
            addTabButton.classList.remove('text-blue-500', 'border-b-2', 'border-blue-500');
            addTabButton.classList.add('text-gray-500');

            selectTab.classList.remove('hidden');
            addTab.classList.add('hidden');
        });

        addTabButton.addEventListener('click', () => {
            addTabButton.classList.add('text-blue-500', 'border-b-2', 'border-blue-500');
            addTabButton.classList.remove('text-gray-500');
            selectTabButton.classList.remove('text-blue-500', 'border-b-2', 'border-blue-500');
            selectTabButton.classList.add('text-gray-500');

            addTab.classList.remove('hidden');
            selectTab.classList.add('hidden');
        });

        function selectTask(task) {
            items.push({
                ...task,
                remark: '',
                quantity: 1,
                description: `${task.reference}`,
                client_name: task.client_name
            });
            // console.log('item selected', items);

            selectedTaskName = `${task.reference}-${task.type}${task.additional_info}(${task.venue})`;

            updateClientAgent(task.client_id, task.agent_id);
            closeTaskModal();
            renderItems();
            refreshTaskList();
            calculateSubtotal();
        }

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

        function closeAgentModal() {
            const modal = document.getElementById("agentModal");
            modal.classList.add("hidden");
        }

        function filterClients() {
            const searchValue = document.getElementById('clientSearchInput').value.toLowerCase();
            const filteredClients = clients.filter(client =>
                (client.first_name && client.first_name.toLowerCase().includes(searchValue)) ||
                (client.middle_name && client.middle_name.toLowerCase().includes(searchValue)) ||
                (client.last_name && client.last_name.toLowerCase().includes(searchValue)) ||
                (client.email && client.email.toLowerCase().includes(searchValue))
            );
            renderClientList(filteredClients);
        }

        function renderClientList(clientData) {
            const clientList = document.getElementById('clientList');
            clientList.innerHTML = '';
            clientData.forEach(client => {
                const li = document.createElement('li');
                li.className = 'cursor-pointer p-2 hover:bg-gray-100 text-gray-800';
                li.innerText = `${client.full_name} (${client.email})`;
                li.onclick = () => selectClient(client);
                clientList.appendChild(li);
            });
        }

        function selectClient(client) {
            renderClientCredit(client);
            document.getElementById('receiverId').value = client.id;

            document.getElementById('receiverName').value = client.full_name;
            document.getElementById('receiverEmail').value = client.email;
            document.getElementById('receiverPhone').value = client.phone;
            closeClientModal();

        }

        function openTaskModal() {
            document.getElementById('taskModal').classList.remove('hidden');
            refreshTaskList();
        }

        function closeTaskModal() {
            document.getElementById('taskModal').classList.add('hidden');
        }

        function filterTasks() {
            const searchValue = document.getElementById('taskSearchInput').value.toLowerCase();
            const filtered = getFilteredTasks().filter(task =>
                task.reference.toLowerCase().includes(searchValue) || task.type.toLowerCase().includes(searchValue) || task.supplier_name.toLowerCase().includes(searchValue) ||
                task.agent?.name.toLowerCase().includes(searchValue) || task.client_name.toLowerCase().includes(searchValue)
            );
            const withoutSelected = filtered.filter(t => !items.some(sel => sel.id === t.id));
            renderTaskList(withoutSelected);
        }

        function renderTaskList(taskData) {
            const taskList = document.getElementById('taskListBody');

            if (!Array.isArray(taskData)) {
                console.error('taskData is not an array:', taskData);
                taskData = [];
            }

            taskData = taskData.filter(task =>
                !items.some(selectedTask => selectedTask.id === task.id)
            );

            taskList.innerHTML = '';

            if (taskData.length === 0) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 6; // Adjust to 7 columns (with the Route column)
                cell.className = 'text-center text-gray-500 py-4';
                cell.innerText = 'No Task Available';
                row.appendChild(cell);
                taskList.appendChild(row);
                return;
            }

            taskData.forEach(task => {
                const row = document.createElement('tr');
                row.className = 'cursor-pointer hover:bg-gray-100';

                // Make the entire row clickable
                row.onclick = () => selectTask(task);

                // Create table data cells
                const referenceCell = document.createElement('td');
                referenceCell.className = 'px-4 py-2';
                referenceCell.innerText = task.reference;

                const totalCell = document.createElement('td');
                totalCell.className = 'px-4 py-2';
                totalCell.innerText = `${task.total ?? 0} KWD`;

                const typeCell = document.createElement('td');
                typeCell.className = 'px-4 py-2';
                typeCell.innerText = task.type.charAt(0).toUpperCase() + task.type.slice(1);

                const clientCell = document.createElement('td');
                clientCell.className = 'px-4 py-2';
                clientCell.innerText = task.client_name;

                const agentCell = document.createElement('td');
                agentCell.className = 'px-4 py-2';
                agentCell.innerText = task.agent.name;

                const branchCell = document.createElement('td');
                branchCell.className = 'px-4 py-2';
                branchCell.innerText = task.branch_name;

                // Check if country_from and country_to exist before accessing 'name'

                const supplierCell = document.createElement('td');
                supplierCell.className = 'px-4 py-2';
                supplierCell.innerText = task.supplier_name;

                // Append cells to the row
                row.appendChild(referenceCell);
                row.appendChild(totalCell);
                row.appendChild(typeCell);
                row.appendChild(clientCell);
                row.appendChild(agentCell);
                row.appendChild(branchCell);
                row.appendChild(supplierCell);

                // Append the row to the table body
                taskList.appendChild(row);
            });
        }

        // Call the function with the selectedClient object
        if (selectedClient && selectedAgent) {
            updateFormFields(selectedClient, selectedAgent);
            renderClientCredit(selectedClient);
        }

        function updateClientAgent(clientId, agentId) {
            // Find the client by clientId
            let client = clients.find(c => c.id === clientId);

            // Find the agent by agentId
            let agent = agents.find(a => a.id === agentId);
            // Find the branch associated with the agent
            let branch = branches.find(b => b.id === agent.branch_id);

            // Check if client and agent exist
            if (client && agent && branch) {
                // Update hidden fields
                // Update input fields for client
                document.getElementById('receiverName').value = client.full_name;
                document.getElementById('receiverEmail').value = client.email;
                document.getElementById('receiverPhone').value = client.phone;

                document.getElementById('agentId').value = agent.id;
                // Update input fields for agent
                document.getElementById('agentName').value = agent.name;
                document.getElementById('agentEmail').value = agent.email;
                document.getElementById('agentPhone').value = agent.phone_number;

                // Update the selected branch
                document.getElementById('selectedBranch').value = branch.id;

                // Update the trigger text for branch selection
                document.querySelector('.select-trigger').textContent = branch.name;

            } else {
                console.error('Client or Agent not found');
            }
        }

        function updateFormFields(client, agent) {
            // Update hidden fields
            document.getElementById('receiverId').value = client.id;
            // Update input fields
            document.getElementById('receiverName').value = client.full_name;
            document.getElementById('receiverEmail').value = client.email;
            document.getElementById('receiverPhone').value = client.phone;

            document.getElementById('agentId').value = agent.id;
            document.getElementById('agentName').value = agent.name;
            document.getElementById('agentEmail').value = agent.email;
            document.getElementById('agentPhone').value = agent.phone_number;
        }

        generateInvoiceButton.addEventListener('click', async function(event) {
            event.preventDefault(); // Prevent form submission or default action
            if (isSaving || isSaved) return; // Prevent multiple clicks while saving or after saved

            // Start saving

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
        function updateButtonState(linkToPayment = false) {
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

        function displayErrorMessage(message) {
            const alert = document.createElement('div');
            alert.innerHTML = `
                    <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
                        ${message}
                        <button type="button" class="close text-white ml-2" aria-label="Close" onclick="this.parentElement.style.display='none';">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                `;
            document.body.appendChild(alert);
        }

        // Generate invoice for task or refund
        async function generateInvoice() {
            isSaving = true;
            updateButtonState();

            const invoiceUrl = isRefund
                ? "{{ route('refunds.store') }}"
                : "{{ route('invoice.store') }}";

            const csrfToken = "{{ csrf_token() }}";

            const currencyElement = document.getElementById('currency');
            const invoiceNumberElement = document.getElementById('invoiceNumber');
            const invdateElement = document.getElementById('invoiceDate');
            const duedateElement = document.getElementById('dueDate');
            const subTotalElement = document.getElementById('subTotal');
            const clientIdElement = document.getElementById('receiverId');
            const agentIdElement = document.getElementById('agentId');
            const selectedBranch = document.getElementById('selectedBranch');

            const currency = currencyElement?.value ?? null;
            const invoiceNumber = invoiceNumberElement?.value ?? null;
            const invdate = invdateElement?.value ?? null;
            const duedate = duedateElement?.value ?? null;
            const subTotal = subTotalElement?.value ?? null;

            const firstTask = Array.isArray(items) && items.length ? items[0] : null;

            let clientId =
                clientIdElement?.value ||
                firstTask?.client_id ||
                firstTask?.client?.id ||
                null;

            if (clientIdElement && !clientIdElement.value && clientId) {
                clientIdElement.value = clientId;
            }

            const agentId = agentIdElement?.value ?? null;
            const selectedBranchValue = selectedBranch?.value ?? null;
            const tasks = items;

            console.log('DEBUG -> isRefund:', isRefund);
            console.log('DEBUG -> clientId:', clientId, 'agentId:', agentId, 'items_count:', items.length);

            buttonText.style.display = "none";
            buttonLoading.style.display = "inline";


            let errorMessages = [];
            const companyId = "{{ $companyId ?? '' }}";

            if (!invdate) errorMessages.push("Invoice/Refund date is missing.");
            if (!items.length) errorMessages.push("No tasks have been selected.");

            // Invoice-specific validation
            if (!isRefund) {
                if (!currency) errorMessages.push("Currency is missing.");
                if (!invoiceNumber) errorMessages.push("Invoice number is missing.");
                if (!subTotal) errorMessages.push("Subtotal is missing.");
                if (!clientId) errorMessages.push("Client ID is missing.");
                if (!agentId) errorMessages.push("Agent ID is missing.");
                if (!selectedBranchValue) errorMessages.push("Branch selection is required.");
            }

            if (errorMessages.length > 0) {
                let errorNotification = document.createElement('div');
                errorNotification.className =
                    "alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg";
                errorNotification.innerHTML = `
                    <ul>
                        ${errorMessages.map(message => `<li>${message}</li>`).join('')}
                    </ul>
                    <button type="button" class="close text-white ml-2" aria-label="Close"
                        onclick="this.parentElement.style.display='none';">
                        <span aria-hidden="true">&times;</span>
                    </button>
                `;
                document.body.appendChild(errorNotification);
                resetButtonState();
                return;
            }

            console.log("All required data is provided. Proceeding...");


            const payload = isRefund
                ? {
                    date: invdate,
                    client_id: clientId,
                    remarks: document.getElementById('refundRemarks')?.value || null,
                    tasks: items.map(item => {
                        const originalInvoicePrice = Number(item.invprice ?? 0);
                        const originalTaskCost = Number(item.total ?? 0);
                        const originalTaskProfit = originalInvoicePrice - originalTaskCost;
                        const refundFee = Number(item.refund_fee_to_client ?? originalInvoicePrice);
                        const supplierCharge = Number(item.supplier_charge ?? 0);

                        return {
                            task_id: item.id,
                            original_invoice_price: originalInvoicePrice,
                            original_task_cost: originalTaskCost,
                            original_task_profit: originalTaskProfit,
                            refund_fee_to_client: refundFee,
                            supplier_charge: supplierCharge,
                            new_task_profit: originalTaskProfit - supplierCharge,
                            total_refund_to_client: refundFee,
                            remarks: item.remark ?? null,
                            payment_gateway_option: item.payment_gateway_option ?? null,
                            payment_method: item.payment_method ?? null,
                        };
                    }),
                }
                : {
                    clientId,
                    agentId,
                    tasks,
                    subTotal,
                    invoiceNumber,
                    currency,
                    invdate,
                    duedate,
                };

            console.log('REQUEST URL:', invoiceUrl);
            console.log('REQUEST PAYLOAD:', payload);

            try {
                const response = await fetch(invoiceUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    throw new Error(`Failed to ${isRefund ? 'process refund' : 'generate invoice'}`);
                }

                const result = await response.json();
                console.log('RESPONSE:', result);


                if (isRefund) {
                    console.log('Refund processed successfully');
                    isSaved = true;
                    updateButtonState();
                    
                    if (result.redirect) {
                        const url = new URL(result.redirect, window.location.origin);
                        const rfNumber = document.getElementById('RefundNumber')?.value;
                        const rfRemarks = document.getElementById('refundRemarks')?.value;
                        
                        if (rfNumber) url.searchParams.set('refund_number', rfNumber);
                        if (rfRemarks) url.searchParams.set('refund_remarks', rfRemarks);
                        
                        location.href = url.toString();
                    }
                } else {
                    // Invoice success
                    const { invoiceId: newInvoiceId, invoiceNumber: newInvoiceNumber } = result;

                    // Update hidden fields
                    if (!invoiceId) {
                        document.getElementById('invoiceNumber').value = newInvoiceNumber;
                    }
                    document.getElementById('invoiceId').value = newInvoiceId;

                    isSaved = true;
                    updateButtonState();

                    setTimeout(() => {
                        checkInvoiceId();
                    }, 100);

                    // Redirect to invoice edit page
                    location.href = "{{ route('invoice.edit', ['companyId' => ':companyId', 'invoiceNumber' => ':invoiceNumber']) }}"
                        .replace(':companyId', companyId)
                        .replace(':invoiceNumber', invoiceNumber || newInvoiceNumber);
                }

            } catch (error) {
                console.error('=== ERROR ===');
                console.error('Error:', error);

                let alert = document.createElement('div');
                alert.innerHTML = `
                    <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
                        ${isRefund ? 'Error Processing Refund' : 'Error Generating Invoice'}
                        <button type="button" class="close text-white ml-2" aria-label="Close"
                            onclick="this.parentElement.style.display='none';">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                `;
                document.body.appendChild(alert);

                resetButtonState();
            }
        }

        function resetButtonState() {
            isSaving = false;
            isSaved = false;
            updateButtonState();
        }

        function renderClientCredit(client) {
            const clientCredit = document.getElementById('client-credit');
            clientCredit.innerHTML = '';

            let totalAmount = 0;
            items.forEach(item => {
                totalAmount += parseFloat(item.total);
            });

            if (!client) {
                clientCredit.innerHTML = `<p class="text-gray-700">No client selected</p>`;
                return;
            }

            fetch(`/clients/${client.id}/credit-balance`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const currentCredit = parseFloat(data.credit);

                    clientCredit.innerHTML = `
                <p class="text-gray-700 font-semibold">${client.first_name} Credit: 
                    <span class="${currentCredit >= 0 ? 'text-green-600' : 'text-red-600'}">
                        ${currentCredit.toFixed(2)} KWD
                    </span>
                </p>
            `;

                    if (totalAmount > currentCredit) {
                        clientCredit.innerHTML += `
                    <p class="text-red-500 font-medium">Total Amount exceeds ${client.first_name}'s Credit</p>
                `;
                    } else {
                        clientCredit.innerHTML += `
                    <p class="text-green-500 font-medium">Total Amount is within Client Credit</p>
                `;
                    }
                })
                .catch(error => {
                    clientCredit.innerHTML =
                        `<p class="text-red-500">Error fetching credit balance: ${error.message}</p>`;
                });
        }

        function currentAgentId() {
            const val = document.getElementById('agentId')?.value;
            return val ? String(val) : '';
        }

        function getFilteredTasks() {
            const agentId = currentAgentId();
            if (!agentId) return allTasks.slice();
            return allTasks.filter(t => String(t.agent_id) === agentId);
        }

        function refreshTaskList() {
            const base = getFilteredTasks();
            const withoutSelected = base.filter(t => !items.some(sel => sel.id === t.id));
            renderTaskList(withoutSelected);
        }

        document.addEventListener("DOMContentLoaded", function() {
            const _tasks = @json($tasks);
            allTasks = Array.isArray(_tasks) ? _tasks : Object.values(_tasks);
            let clients = @json($clients);
            renderItems();
            calculateSubtotal();

            renderClientList(clients);
            refreshTaskList();
            renderClientCredit(selectedClient);
        });

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('agentSearchInput');
            const agentList = document.getElementById('agentList');
            const listItems = agentList.getElementsByTagName('li');
            const noAgentsFound = document.getElementById('noAgentsFound');

            searchInput.addEventListener('input', function() {
                const searchValue = this.value.toLowerCase();
                let anyVisible = false;

                Array.from(listItems).forEach(function(item) {
                    const text = item.textContent.toLowerCase();
                    const match = text.includes(searchValue);
                    item.style.display = match ? '' : 'none';
                    if (match) anyVisible = true;
                });

                noAgentsFound.classList.toggle('hidden', anyVisible);
            });
        });
   
        document.getElementById('select-supplier-task')?.addEventListener('change', function() {
            let selectedSupplier = this.options[this.selectedIndex].getAttribute('data-supplier');
            let supplier = JSON.parse(selectedSupplier);
            let formTaskContainer = document.getElementById('form-task-container');
            let companyIdData = formTaskContainer.getAttribute('data-company-id');
            let tboTaskUrl = "{!! route('tasks.get-tbo', ['companyId' => '__companyId__']) !!}".replace('__companyId__', companyIdData);

            formTaskContainer.innerHTML = '';

            if (supplier.name === 'Magic Holiday') {
                let input = document.createElement('input');
                input.type = 'text';
                input.name = 'supplier_ref';
                input.placeholder = 'Reference';
                input.classList.add('input', 'w-full', 'mt-2', 'rounded-lg', 'border',
                    'border-gray-300', 'dark:border-gray-700', 'dark:bg-gray-800',
                    'dark:text-gray-300', 'p-3');
                formTaskContainer.appendChild(input);
            } else if (supplier.name === 'TBO Holiday') {
                let input = document.createElement('input');
                input.type = 'text';
                input.name = 'supplier_ref';
                input.placeholder = 'Coming Soon...';
                input.classList.add('input', 'w-full', 'mt-2', 'rounded-lg', 'border',
                    'border-gray-300', 'dark:border-gray-700', 'dark:bg-gray-800',
                    'dark:text-gray-300', 'p-3', 'disabled:opacity-75', 'disabled:cursor-not-allowed');
                input.disabled = true;
                formTaskContainer.appendChild(input);
            } else if (supplier.name === 'Amadeus') {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.name = 'task_file';
                fileInput.id = 'amadeus-upload-task';
                fileInput.classList.add('bg-white', 'dark:bg-dark', 'p-2', 'shadow-md', 'rounded-md',
                    'w-full', 'mt-2');
                formTaskContainer.appendChild(fileInput);
            } else {
                let div = document.createElement('div');
                div.classList.add('text-red-500', 'text-sm', 'font-semibold', 'mt-2');
                div.innerHTML = 'API not available for this supplier';
                formTaskContainer.appendChild(div);
            }
        });
    </script>
</x-app-layout>