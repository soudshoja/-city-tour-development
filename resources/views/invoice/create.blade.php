<x-app-layout>

    <style>
        button[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Highlight selected button */
        .highlight-selected {
            background-color: #e0f2ff;
            /* Light blue background for the selected option */
            border-color: #3b82f6;
            /* Blue border */
            opacity: 1;
            /* Fully visible */
            transition: all 0.3s ease-in-out;
            pointer-events: none;
        }

        /* Fade unchecked buttons */
        .fade-unchecked {
            opacity: 0.5;
            /* Reduce visibility for unselected options */
            pointer-events: none;
            /* Prevent interactions */
            transition: all 0.3s ease-in-out;
        }

        #coa-activities-container {
            padding: 20px;
            background-color: #f8f9fa;
            /* Light background for activities */
            display: none;
            /* Initially hide */
        }

        #invoice-container {
            position: relative;
            z-index: 1;
            /* Ensure invoice content is above activities */
        }

        .table-container {
            overflow-x: auto;
            /* Enable horizontal scrolling */
            overflow-y: auto;
            /* Enable vertical scrolling if needed */
            max-height: 500px;
            /* Adjust height as per your layout */
            max-width: 1000px;
            border: 1px solid #e0e6ed;
            /* Optional: add border around the scroll area */
        }

        table {
            width: 100%;
            /* Ensure table takes up full width */
            border-spacing: 0;
            /* Remove extra spacing between cells */
            border-collapse: collapse;
            /* Merge borders */
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
            <div class="panel flex-1 px-0 py-6 max-w-[900px] sm:max-w-[500px] md:max-w-[500px] lg:max-w-[600px] xl:max-w-[1200px]">
                <!-- company details -->
                <div class="flex flex-wrap justify-between px-6 ">
                    <div class=" shrink-0 items-center text-black dark:text-white">
                        <x-application-logo class="custom-logo-size" />

                        <div class="pl-2">
                            @if($company)
                            <h3>{{ $company->name }}</h3>
                            <p>{{ $company->address }}</p>
                            @else
                            <p>No company assigned</p>
                            @endif
                        </div>

                        <div class="flex">
                            <p class="pl-1">{{ $company->email }}</p>
                        </div>
                        <div class="flex">
                            <p class="pl-1">{{ $company->phone }}</p>
                        </div>
                        <!-- Branch Select Dropdown -->
                        <div class="custom-select w-full border rounded-lg mt-4">
                            <!-- Trigger -->
                            <div class="select-trigger px-4 py-2 cursor-pointer dark:text-white">Select Branch</div>

                            <!-- Options Container -->
                            <div class="select-options hidden absolute left-0 top-full w-full rounded-md shadow-lg grid {{ count($branches) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 py-3">
                                @foreach ($branches as $branch)
                                <div class="select-option px-4 py-3 text-center bg-white dark:bg-gray-700 BoxShadow rounded-lg dark:hover:bg-gray-800
                                    border border-gray-300 cursor-pointer" data-value="{{ $branch->id }}">
                                    {{ $branch->name }}
                                </div>
                                @endforeach
                            </div>

                            <!-- Hidden input to store selected value -->
                            <input type="hidden" name="branch_id" id="selectedBranch">
                        </div>
                        <!-- ./Branch Selection -->

                    </div>
                    <!-- invoice details -->
                    <div class="space-y-1 text-gray-900 dark:text-gray-400">

                        <div class="flex items-center w-full">
                            <label for="invoiceNumber" class="w-full text-sm font-semibold">Invoice Number</label>
                            <input id="invoiceNumber" type="text" name="invoiceNumber" value="{{$invoiceNumber}}" class="w-full form-input"
                                placeholder="Invoice Number" />
                        </div>

                        <div class="mt-4 flex items-center">
                            <label for="invoiceDate" class="w-full text-sm font-semibold">Invoice Date</label>
                            <input id="invoiceDate" type="date" name="invoiceDate" class="w-full form-input" value={{$todayDate}} disabled />
                        </div>

                        <div class="mt-4 flex items-center">
                            <label for="dueDate" class="w-full text-sm font-semibold">Due Date</label>
                            <input id="dueDate" type="date" name="dueDate" class="w-full form-input" />
                        </div>
                        <!-- Refresh Button -->
                        <div class="mt-6 flex justify-end">
                            <button type="button" onclick="location.reload()" class="px-2 py-2 city-light-yellow text-white rounded hover:text-[#004c9e] flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 1 1 2.026 5.255M3 12H8m-5 0V7" />
                                </svg>
                            </button>
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
                                </svg><span class="pl-5">Choose Client</span>
                            </button>
                            <input id="receiverId" type="hidden" name="receiverId" />
                            <input id="agentId" type="hidden" name="agentId" value="{{ is_string($agentId) || is_numeric($agentId) ? $agentId : '' }}" />
                        </div>

                        <p class="my-2 text-gray-400 text-center text-xs">details will displaying below after choosing a client</p>
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
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#004c9e"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="10" cy="6" r="4" fill="#004c9e" />
                                    <path
                                        d="M18 17.5C18 19.9853 18 22 10 22C2 22 2 19.9853 2 17.5C2 15.0147 5.58172 13 10 13C14.4183 13 18 15.0147 18 17.5Z"
                                        fill="#004c9e" />
                                    <path d="M21 10H19M19 10H17M19 10L19 8M19 10L19 12"
                                        stroke="#004c9e" stroke-width="1.5" stroke-linecap="round" />
                                </svg><span class="pl-5">Choose Agent</span>
                            </button>
                            @endcan
                        </div>
                        <p class="my-2 text-gray-400 text-center text-xs">details will displaying below after choosing an Agent</p>

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
                    <div class="overflow-x-auto max-w-[1100px] border border-gray-200">
                        <table id="itemsTable" class="text-left table-auto border-collapse w-full text-xs">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">No.</th>
                                    <th class="px-4 py-2 min-w-[200px] text-gray-900 dark:text-gray-100">Task</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Type</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Venue</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Task Price</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Invoice Price</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Client Name</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Agent Name</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Branch Name</th>
                                    <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Action</th>
                                </tr>
                            </thead>
                            <tbody id="items-body" class="divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Dynamically populated rows -->
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
                        <div class="sm:w-2/5 flex justify-end">
                            <div class="mt-4 flex items-center font-semibold">
                                <div class="mr-2">Total:</div>
                                <span id="subT">0.00</span>
                                <input id="subTotal" type="hidden" name="subTotal" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel flex-1 px-6 py-6 lg:mr-6" id="coa-activities-container">
                    <h3 class="font-bold text-xl mb-4">COA Activities:</h3>
                    <ul id="coa-activities-list" class="list-disc pl-6 space-y-2">
                        <!-- COA activities will be inserted here by the coaActivites function -->
                    </ul>
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

                    <!-- Payment Type Section -->
                    <div id="paymentMethod" class="mt-4">
                        <h2 class="text-lg font-semibold mb-3 text-gray-700">Payment Type</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-1">
                            <!-- Full Payment Tab -->
                            <label class="cursor-pointer rounded-full shadow">
                                <input
                                    type="radio"
                                    id="payment_type_full"
                                    name="payment_type"
                                    value="full"
                                    onclick="hideModal()"
                                    hidden
                                    class="peer"
                                    checked />
                                <div class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2">
                                    <span class="font-medium">Fully Payment</span>
                                </div>
                            </label>

                            <!-- Partial Payment Tab -->
                            <label class="cursor-pointer rounded-full shadow">
                                <input
                                    type="radio"
                                    id="payment_type_partial"
                                    name="payment_type"
                                    value="partial"
                                    onclick="showModal('partial')"
                                    hidden
                                    class="peer" />
                                <div class="city-light-yellow hover:text-[#004c9e] rounded-full  flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2">
                                    <span class="font-medium">Partially Payment</span>
                                </div>
                            </label>



                            <!-- Split Payment Tab -->
                            <label class="cursor-pointer rounded-full shadow">
                                <input
                                    type="radio"
                                    id="payment_type_split"
                                    name="payment_type"
                                    value="split"
                                    onclick="showModal('split')"
                                    hidden
                                    class="peer" />
                                <div class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2">
                                    <span class="font-medium">Split Payment</span>
                                </div>
                            </label>



                        </div>

                        <!-- Payment Gateway Section -->
                        <section id="payment_gateway_section" class="mb-6">
                            <div class="mt-4">
                                <h2 class="text-lg font-semibold mb-3 text-gray-700">Choose Payment Gateway</h2>
                                <select id="payment_gateway" name="payment_gateway" class="border border-gray-300 p-2 rounded w-full">
                                    @foreach($paymentGateways as $gateway)
                                    <option value="{{ $gateway }}">{{ $gateway }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mt-4">
                                <button onclick="savePartial('full')" id="update-invoice-btn" type="button" class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                                        city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mr-2">
                                        <path
                                            d="M17.657 6.343a8 8 0 11-11.314 0L4.929 5.03a9.998 9.998 0 1014.142 0l-1.414 1.314z"
                                            fill="currentColor" />
                                        <path
                                            d="M11.25 8V4.75a.75.75 0 011.5 0V8h2.25a.75.75 0 010 1.5H12.75V12a.75.75 0 01-1.5 0V9.5H9a.75.75 0 010-1.5h2.25z"
                                            fill="currentColor" />
                                    </svg>
                                    Update Invoice
                                </button>
                            </div>
                        </section>

                        <!-- Added Buttons/Links Section -->
                        <section id="additional-actions" class="mt-6">
                           <input type="hidden" name="shared" id="shared" value="false">
                            <div class="flex flex-wrap gap-4">
                                <h2 class="text-lg font-semibold mb-3 text-gray-700">Share Invoice</h2>

                                <!-- Share Buttons -->
                                <div class="flex items-center gap-2 w-full">
                                    <form action="{{ route('whatsapp.send') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="client" id="client">
                                        <input type="hidden" name="invoiceNumber" value="{{$invoiceNumber}}">
                                        <button type="submit" class="w-full items-center py-3 px-5 text-xs text-white btn-success rounded-full">
                                            Share via WhatsApp
                                        </button>
                                    </form>

                                    <button onclick="shareViaEmail()" class="w-full items-center py-3 px-5 text-sm text-white btn-info rounded-full ">
                                        Share via Email
                                    </button>

                                </div>

                                <button onclick="copyLink()" class="py-3 px-5 w-full inline-flex items-center justify-center text-sm text-white rounded-full gap-2 DarkBGcolor">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                        <g fill="none" stroke="#ffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5">
                                            <path d="M16.75 5.75a3 3 0 0 0-3-3h-6.5a3 3 0 0 0-3 3v9.5a3 3 0 0 0 3 3h6.5a3 3 0 0 0 3-3z" />
                                            <path d="M19.75 6.75v8.5a6 6 0 0 1-6 6h-5.5" />
                                        </g>
                                    </svg>
                                    Copy Link
                                </button>

                                <!-- View Button -->
                                <button onclick="viewInvoice()" class="py-3 px-5 w-full inline-flex items-center justify-center text-sm text-white rounded-full gap-2 DarkBGcolor">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 ltr:mr-2 rtl:ml-2">
                                        <path opacity="0.5" d="M3.27489 15.2957C2.42496 14.1915 2 13.6394 2 12C2 10.3606 2.42496 9.80853 3.27489 8.70433C4.97196 6.49956 7.81811 4 12 4C16.1819 4 19.028 6.49956 20.7251 8.70433C21.575 9.80853 22 10.3606 22 12C22 13.6394 21.575 14.1915 20.7251 15.2957C19.028 17.5004 16.1819 20 12 20C7.81811 20 4.97196 17.5004 3.27489 15.2957Z" stroke="currentColor" stroke-width="1.5"></path>
                                        <path d="M15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z" stroke="currentColor" stroke-width="1.5"></path>
                                    </svg>
                                    View
                                </button>
                                <p id="copyFeedback" class="mt-2 text-sm text-green-600 hidden">Link copied to clipboard!</p>

                            </div>
                        </section>


                    </div>

                </div>
                <div id="viewInvoiceModal"
                    class="fixed z-10 inset-0 flex items-center justify-center backdrop-blur-sm hidden">
                    <div class="relative">
                        <!-- Modal Content -->
                        <div class="w-full">

                        </div>
                        <div id="invoiceContent" class="">
                            <!-- Invoice content will be loaded here dynamically -->
                        </div>
                    </div>
                </div>
                <div class="panel">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-1">

                        <button id="generate-invoice-btn" type="button" class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                        city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mr-2">
                                <path d="M3.46447 20.5355C4.92893 22 7.28595 22 12 22C16.714 22 19.0711 22 20.5355 20.5355C22 19.0711 22 16.714 22 12C22 11.6585 22 11.4878 21.9848 11.3142C21.9142 10.5049 21.586 9.71257 21.0637 9.09034C20.9516 8.95687 20.828 8.83317 20.5806 8.58578L15.4142 3.41944C15.1668 3.17206 15.0431 3.04835 14.9097 2.93631C14.2874 2.414 13.4951 2.08581 12.6858 2.01515C12.5122 2 12.3415 2 12 2C7.28595 2 4.92893 2 3.46447 3.46447C2 4.92893 2 7.28595 2 12C2 16.714 2 19.0711 3.46447 20.5355Z" stroke="currentColor" stroke-width="1.5" />
                                <path d="M17 22V21C17 19.1144 17 18.1716 16.4142 17.5858C15.8284 17 14.8856 17 13 17H11C9.11438 17 8.17157 17 7.58579 17.5858C7 18.1716 7 19.1144 7 21V22" stroke="currentColor" stroke-width="1.5" />
                                <path opacity="0.5" d="M7 8H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                            <span id="button-text">Save</span>
                            <span id="button-loading" style="display: none;">Saving...</span>
                            <span id="button-saved" style="display: none;">Saved</span>
                        </button>
                        <input id="invoiceId" type="hidden" name="invoiceId" />
                        <!-- add form here-->


                        <div id="errorMessage" class="hidden text-red-500">
                            <!-- Error message -->
                        </div>

                        <!-- Modal -->
                        <div id="paymentModal" class="fixed inset-0 z-50 hidden bg-gray-800 bg-opacity-50 flex items-center justify-center">
                            <div class="bg-white rounded-lg shadow-lg w-3/4 p-5">
                                <h3 class="text-xl font-bold mb-4">Split Payment Details</h3>
                                <!-- Include your previous page content here -->
                                <div class="bg-gray-100 p-5">
                                    <div class="max-w-5xl mx-auto bg-white shadow-md rounded-lg p-6">

                                        <!-- Split Payment Tab Content -->
                                        <div id="split-payment-container" class="tab-content">
                                            <form>
                                                <!-- Top Fields -->
                                                <div class="grid grid-cols-3 gap-4 mb-5">
                                                    <div>
                                                        <label class="block text-sm font-medium mb-1">Amount *</label>
                                                        <input type="number" id="total-amount" class="w-full border-gray-300 rounded-md shadow-sm opacity-50" placeholder="0" disabled />
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium mb-1" for="split-into">Split into *</label>
                                                        <select id="split-into" class="w-full border-gray-300 rounded-md shadow-sm" onchange="updateRows()">
                                                            <option value="" disabled selected>Select a value</option>
                                                            <option value="1">1</option>
                                                            <option value="2">2</option>
                                                            <option value="3">3</option>
                                                            <option value="4">4</option>
                                                            <option value="5">5</option>
                                                            <option value="6">6</option>
                                                            <option value="7">7</option>
                                                            <option value="8">8</option>
                                                            <option value="9">9</option>
                                                            <option value="10">10</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Expiry and Description -->
                                                <div class="grid grid-cols-2 gap-4 mb-5">
                                                    <div>
                                                        <label class="block text-sm font-medium mb-1">Description *</label>
                                                        <textarea id="split-desc" class="w-full border-gray-300 rounded-md shadow-sm" placeholder="Add Description"></textarea>
                                                    </div>
                                                </div>

                                                <!-- Table -->
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full bg-white border border-gray-300 text-center">
                                                        <thead>
                                                            <tr>
                                                                <th class="border-b px-4 py-2">S.No</th>
                                                                <th class="border-b px-4 py-2">Choose Client</th>
                                                                <th class="border-b px-4 py-2">Expiry Date</th>
                                                                <th class="border-b px-4 py-2">Amount</th>
                                                                <th class="border-b px-4 py-2">Payment Gateway</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="split-rows">
                                                            <!-- Dynamic rows will be generated here -->
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <!-- Buttons -->

                                                <div>
                                                    <button type="button" onclick="savePartial('split')" class="inline-flex items-center justify-center text-sm text-black font-semibold
                                                            city-light-yellow hover:bg-[#004c9e] hover:text-white  py-2 px-4  rounded-full shadow">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <button onclick="hideModal()" class="bg-gray-600 text-white px-4 py-2 rounded-md">Close</button>
                                </div>
                            </div>
                        </div>

                        <div id="paymentModal1" class="fixed inset-0 z-50 hidden bg-gray-800 bg-opacity-50 flex items-center justify-center">
                            <div class="bg-white rounded-lg shadow-lg w-3/4 p-5">
                                <h3 class="text-xl font-bold mb-4">Partial Payment Details</h3>
                                <div class="bg-gray-100 p-5">
                                    <div class="max-w-5xl mx-auto bg-white shadow-md rounded-lg p-6">
                                        <!-- Partial Payment Tab Content -->
                                        <div id="partial-payment-container" class="tab-content">
                                            <div class="grid grid-cols-3 gap-4 mb-5">
                                                <div>
                                                    <label class="block text-sm font-medium mb-1">Client Name</label>
                                                    <span id="receiverName1">AHMED</span>
                                                </div>
                                                <div>
                                                    <label for="receiverEmail1" class="mb-0 w-1/3 mr-2 ">Invoice Total</label>
                                                    <span id="subT1">0.00</span>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-3 gap-4 mb-5">
                                                <div>
                                                    <label class="block text-sm font-medium mb-1" for="split-into1">Split into *</label>
                                                    <select id="split-into1" class="w-full border-gray-300 rounded-md shadow-sm" onchange="updateRows1()">
                                                        <option value="" disabled selected>Select a value</option>
                                                        <option value="1">1</option>
                                                        <option value="2">2</option>
                                                        <option value="3">3</option>
                                                        <option value="4">4</option>
                                                        <option value="5">5</option>
                                                        <option value="6">6</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="block text-sm font-medium mb-1">Payment Gateway</label>
                                                    <select id="payment_gateway1" name="payment_gateway1" class="w-full p-2 border-gray-300 rounded-md shadow-sm">
                                                        @foreach($paymentGateways as $gateway)
                                                        <option value="{{ $gateway }}">{{ $gateway }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <h2 class="text-lg font-semibold mb-3 text-gray-700">Partial Payment Breakdown</h2>
                                            <table class="min-w-full bg-white border border-gray-300 text-center">
                                                <thead>
                                                    <tr>
                                                        <th class="border-b px-4 py-2">S.No</th>
                                                        <th class="border-b px-4 py-2">Expiry Date</th>
                                                        <th class="border-b px-4 py-2">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="split-rows1">
                                                    <!-- Dynamic rows will be generated here -->
                                                </tbody>
                                            </table>

                                            <p id="error-message" class="text-red-500 mt-3 hidden">The total of partial payments must match the invoice total.</p>

                                            <div class="flex space-x-4 mt-5">
                                                <button onclick="savePartial('partial')" type="button" class="inline-flex items-center justify-center text-sm text-black font-semibold
                                                            city-light-yellow hover:bg-[#004c9e] hover:text-white  py-2 px-4  rounded-full shadow">Save</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <button onclick="hideModal()" class="bg-gray-600 text-white px-4 py-2 rounded-md">Close</button>
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
        const itemsBody = document.getElementById('items-body');
        const appUrl = @json($appUrl);
        let toggle = false;

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

        function checkInvoiceId() {
            const tabs = document.querySelectorAll('input[name="payment_type"]');
            const clientButton = document.getElementById("openClientModalButton");
            const agentButton = document.getElementById("select-agent");
            const taskButton = document.getElementById("openTaskModalButton");
            const generateInvoice = document.getElementById("generate-invoice-btn");
            const paymentGatewaySection = document.getElementById('payment_gateway_section');
            const paymentType = document.querySelector('input[name="payment_type"]:checked').value;
            const shareSection = document.getElementById('additional-actions');
            const shared = document.getElementById('shared').value;
            
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
                    options.forEach(opt => opt.classList.remove('active')); // Remove active class from others
                    this.classList.add('active'); // Add active class to clicked option
                });
            });


            if (paymentType === 'full') {
                paymentGatewaySection.style.display = 'block'; // Show the section
            } else {
                paymentGatewaySection.style.display = 'none'; // Hide the section
            }

            if (shared === 'false') {
                shareSection.style.display = 'none'; // hide the section
            } else {
                shareSection.style.display = 'block'; // show the section
            }


            console.log(invoiceIdInput.value);
            if (!invoiceIdInput.value) {
                tabs.forEach(tab => {
                    tab.disabled = true;
                });
                clientButton.disabled = false;
                agentButton.disabled = false;
                taskButton.disabled = false;
                generateInvoiceButton.disabled = false;
                generateInvoice.classList.remove('hidden');
                document.getElementById('paymentMethod').classList.add('hidden');

            } else {
                tabs.forEach(tab => {
                    tab.disabled = false;
                });
                clientButton.disabled = true;
                agentButton.disabled = true;
                taskButton.disabled = true;
                generateInvoiceButton.disabled = false;
                generateInvoice.classList.add('hidden');
                document.getElementById('paymentMethod').classList.remove('hidden');
            }
        }

        // Run the check on page load and whenever the input value changes
        document.addEventListener('DOMContentLoaded', checkInvoiceId);
        invoiceIdInput.addEventListener('input', checkInvoiceId);


        // Set initial states
        let isSaving = false;
        let isSaved = false;

        function showModal(type) {
            if (type == 'split') {
                document.getElementById('paymentModal').classList.remove('hidden');
            } else if (type == 'partial') {
                document.getElementById('paymentModal1').classList.remove('hidden');
            }

            checkInvoiceId();
        }

        function hideModal() {
            document.getElementById('paymentModal').classList.add('hidden');
            document.getElementById('paymentModal1').classList.add('hidden');
            checkInvoiceId();
        }


        function showClientModal() {
            // Create the modal container
            const modalContainer = document.createElement('div');
            modalContainer.id = 'clientModal';
            modalContainer.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';

            // Modal content
            modalContainer.innerHTML = `
                    <div class="bg-white w-full max-w-lg rounded-lg shadow-lg p-6 relative">
                        <!-- Close Button -->
                        <button class="absolute top-3 right-3 text-gray-500 hover:text-gray-800" onclick="closeClientModal1()">✕</button>

                        <!-- Search Box -->
                        <div id="selectTab" class="p-6">
                            <div class="relative mb-4">
                                <input type="text" placeholder="Search Client..."
                                    class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                                    id="clientSearchInput">
                            </div>
                            <!-- ./Search Box -->

                            <!-- List of Clients -->
                            <ul id="clientList1"
                                class="shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] border rounded-lg mb-4 max-h-60 overflow-y-auto custom-scrollbar">
                                <!-- Dynamic list items go here -->
                            </ul>
                            <!-- ./List of Clients -->
                        </div>
                    </div>
                `;

            // Append the modal to the body
            document.body.appendChild(modalContainer);
        }

        function closeClientModal1() {
            // Remove the modal from the DOM
            const modal = document.getElementById('clientModal');
            if (modal) {
                modal.remove();
            }
        }

        function updateRows() {
            const splitInto = parseInt(document.getElementById('split-into').value) || 0;
            const totalAmount = parseFloat(document.getElementById('total-amount').value) || 0;
            const perRowAmount = splitInto > 0 ? (totalAmount / splitInto).toFixed(2) : 0;

            const tbody = document.getElementById('split-rows');
            tbody.innerHTML = ''; // Clear existing rows

            for (let i = 1; i <= splitInto; i++) {
                const row = document.createElement('tr');
                row.innerHTML = `
                        <td class="border-b px-4 py-2">${i}</td>
                        <td class="border-b px-4 py-2">
                        <select  id="customer_name_${i}" name="customer_name_${i}" class="w-full p-2 border rounded-md account-select" placeholder="Select Client">
                            ${clients.map(client => `<option value="${client.id}">${client.name}</option>`).join('')}
                        </select>
                        </td>
                        <td class="border-b px-4 py-2">
                            <input type="date" id="date_${i}" name="date_${i}" class="border-gray-300 rounded-md shadow-sm" />
                        </td>
                        <td class="border-b px-4 py-2">
                            <input type="number" id="amount_${i}" name="amount_${i}" class="border-gray-300 rounded-md" value="${perRowAmount}" />
                        </td>
                        <td class="border-b px-4 py-2">
                            <select id="payment_gateway2" name="payment_gateway2" class="border border-gray-300 p-2 rounded w-full">
                                @foreach($paymentGateways as $gateway)
                                <option value="{{ $gateway }}">{{ $gateway }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-2 border"></td>
                    `;
                tbody.appendChild(row);

                const selectElement = row.querySelector('.account-select');
                new TomSelect(selectElement, {
                    create: false,
                    sortField: {
                        field: 'text',
                        direction: 'asc'
                    }
                });

            }
        }

        function updateRows1() {
            const splitInto1 = parseInt(document.getElementById('split-into1').value) || 0;
            const totalAmount1 = parseFloat(document.getElementById('total-amount').value) || 0;
            const perRowAmount1 = splitInto1 > 0 ? (totalAmount1 / splitInto1).toFixed(2) : 0;

            const tbody = document.getElementById('split-rows1');
            tbody.innerHTML = ''; // Clear existing rows

            for (let i = 1; i <= splitInto1; i++) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="border-b px-4 py-2">${i}</td>
                    <td class="border-b px-4 py-2">
                        <input type="date" id="date_${i}" name="date_${i}" class="border-gray-300 rounded-md shadow-sm" />
                    </td>
                    <td class="border-b px-4 py-2">
                        <input type="number" id="amount_${i}" name="amount_${i}" class="border-gray-300 rounded-md" value="${perRowAmount1}" />
                    </td>
                `;
                tbody.appendChild(row);

            }
        }

        function updateField(itemId, fieldId) {
            const inputField = document.getElementById(`${fieldId}-${itemId}`);
            const newValue = inputField.value || NULL;

            const item = items.find(item => item.id === itemId);
            if (item) {
                item[fieldId] = newValue;
            }

            if (fieldId === 'invprice') {
                let formattedValue = parseFloat(newValue).toFixed(2);

                document.getElementById(`invPriceAtTable_${itemId}`).textContent = formattedValue + ' KWD';
                calculateSubtotal();
            }

        }

        function calculateSubtotal() {
            const subtotal = items.reduce((sum, item) => sum + (parseFloat(item.invprice) || 0), 0);

            document.getElementById('subT').textContent = `${subtotal.toFixed(2)}`;
            document.getElementById('subT1').textContent = `${subtotal.toFixed(2)}`;
            document.getElementById('subTotal').value = subtotal;
            document.getElementById('total-amount').value = subtotal;
        }


        function renderItems() {
            itemsBody.innerHTML = ''; // Clear existing rows

            if (items.length === 0) {
                // If no items, display the "No Item Available" row
                const noItemsRow = document.createElement('tr');
                noItemsRow.innerHTML = '<td colspan="13" class="w-full !text-center font-semibold text-gray-900 dark:bg-[#121e32] dark:text-white">No Tasks Available</td>';
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
                    <p>${item.description}</p>
                    </td>
                    <td>
                    <p>${item.type}</p>
                    </td>
                    <td class="flex-grow">
                    <p>${item.venue}</p>
                    </td>
                    <td>
                    <p>${item.price} KWD</p>
                    </td>
                    <td>
                    <p id="invPriceAtTable_${item.id}">0.00 KWD</p>
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
                    <div
                        class="inline-flex items-center justify-evenly">
                        <div 
                            id="modal-open-button_${item.id}"
                            data-tooltip="See Details">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" >
                            <path d="M14.3601 4.07866L15.2869 3.15178C16.8226 1.61607 19.3125 1.61607 20.8482 3.15178C22.3839 4.68748 22.3839 7.17735 20.8482 8.71306L19.9213 9.63993M14.3601 4.07866C14.3601 4.07866 14.4759 6.04828 16.2138 7.78618C17.9517 9.52407 19.9213 9.63993 19.9213 9.63993M14.3601 4.07866L12 6.43872M19.9213 9.63993L14.6607 14.9006L11.5613 18L11.4001 18.1612C10.8229 18.7383 10.5344 19.0269 10.2162 19.2751C9.84082 19.5679 9.43469 19.8189 9.00498 20.0237C8.6407 20.1973 8.25352 20.3263 7.47918 20.5844L4.19792 21.6782M4.19792 21.6782L3.39584 21.9456C3.01478 22.0726 2.59466 21.9734 2.31063 21.6894C2.0266 21.4053 1.92743 20.9852 2.05445 20.6042L2.32181 19.8021M4.19792 21.6782L2.32181 19.8021M2.32181 19.8021L3.41556 16.5208C3.67368 15.7465 3.80273 15.3593 3.97634 14.995C4.18114 14.5653 4.43213 14.1592 4.7249 13.7838C4.97308 13.4656 5.26166 13.1771 5.83882 12.5999L8.5 9.93872" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>

                        <dialog data-modal-invoice="${item.id}" class="rounded-md h-near-full w-full min-h-80 overflow-y-scroll">
                            <div class="flex justify-between items-center p-4 border-b border-black">
                                <h2 class="text-lg font-bold text-gray-700">INVOICE DETAILS</h2>
                                <button class="text-gray-500 hover:text-gray-800" id="modal-close-button_${item.id}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                            <div id="task-details_${item.id}" class="min-w-72 w-full p-4 text-lg"> </div> 
                        </dialog>
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
                                        id="invprice-${item.id}"
                                        type="number"
                                        name="invprice",
                                        placeholder="Enter Invoice Price",
                                        class="border border-gray-300 p-2 rounded-md"
                                        onInput="updateField(${item.id}, 'invprice')"
                                    >
                                    <input
                                        id="remark-${item.id}"
                                        type="text"
                                        name="remark",
                                        placeholder="Enter Remark",
                                        class="border border-gray-300 p-2 rounded-md"
                                        onInput="updateField(${item.id}, 'remark')"
                                    >
                                    <input
                                        id="note-${item.id}"
                                        type="text"
                                        name="note",
                                        placeholder="Enter Note",
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
                            <div class="text-lg font-bold mt-4">Flight Details</div>
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
                                <details class="flex justify-between items-center bg-gray-100 p-2 rounded-md group" >
                                    <summary class="list-none flex flex-wrap items-center cursor-pointer">
                                        <h3 class="flex flex-1 p-4 font-semibold">Ticket Info</h3>
                                        <div class="flex w-10 items-center justify-center">
                                            <div class="border-8 border-transparent border-l-black ml-2 group-open:rotate-90 transition-transform origin-left"></div>
                                        </div>
                                    </summary>
                                    <div class="p-4">
                                        
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Departure Time</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.flight_details.departure_time}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Country From</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.country_from.name}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Airport From</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.airport_from}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Terminal From</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.terminal_from}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Arrival Time</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.arrival_time}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Country To</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.country_to.name}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Airport To</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.airport_to}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Terminal To</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.terminal_to}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Airline</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.airline_id}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Class</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.class_type}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full line-clamp-1">Baggage Allowed</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.baggage_allowed}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Equipment</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.equipment}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Flight Meal</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.flight_meal}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Seat No</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.seat_no}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Created At</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.created_at}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Updated At</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${item.flight_details.updated_at}" disabled>
                                        </div>
                                    </div>
                                </details>
                                <details class="flex justify-between items-center bg-gray-100 p-2 rounded-md group">
                                    <summary class="list-none flex flex-wrap items-center cursor-pointer">
                                        <h3 class="flex flex-1 p-4 font-semibold">Route Info</h3>
                                        <div class="flex w-10 items-center justify-center">
                                            <div class="border-8 border-transparent border-l-black ml-2 group-open:rotate-90 transition-transform origin-left"></div>
                                        </div>
                                    </summary>
                                    <div class="p-4">
                                    </div>
                                </details>
                                <details class="flex justify-between items-center bg-gray-100 p-2 rounded-md group">
                                    <summary class="list-none flex flex-wrap items-center cursor-pointer">
                                        <h3 class="flex flex-1 p-4 font-semibold">Fare Info</h3>
                                        <div class="flex w-10 items-center justify-center">
                                            <div class="border-8 border-transparent border-l-black ml-2 group-open:rotate-90 transition-transform origin-left"></div>
                                        </div>
                                    </summary>
                                    <div class="p-4">
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Farebase</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${item.flight_details.farebase}">
                                        </div>
                                    </div>
                                </details>
                                <details class="flex justify-between items-center bg-gray-100 p-2 rounded-md group">
                                    <summary class="list-none flex flex-wrap items-center cursor-pointer">
                                        <h3 class="flex flex-1 p-4 font-semibold">Void Info</h3>
                                        <div class="flex w-10 items-center justify-center">
                                            <div class="border-8 border-transparent border-l-black ml-2 group-open:rotate-90 transition-transform origin-left"></div>
                                        </div>
                                    </summary>
                                    <div class="p-4">
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
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Hotel ID</div>
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
                        console.log(item.id);
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

                    // removeButton = document.getElementById('remove-button-' + item.id);

                    // removeButton.addEventListener('click', function() {
                    //     removeItem(item.id);
                    // });
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
            renderItems(); // Re-render the table after removal
            renderTaskList(tasks);
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
            items = [];
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


        // Function to select a task
        function selectTask(task) {
            console.log('task selected', task);
            items.push({
                ...task, // Spread the properties of the task object
                remark: '', // Add default empty remark
                quantity: 1, // Default quantity is 1
                description: `${task.reference} - ${task.additional_info}`, // Custom description format
                client_name: task.client_name
            });

            // Set the selected task name
            selectedTaskName = `${task.reference}-${task.type}${task.additional_info}(${task.venue})`;

            updateClientAgent(task.client_id, task.agent_id);
            // Call a function to update the total, passing the current items array
            //  updateTotal(items);
            renderTaskList(tasks);
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
            document.getElementById('client').value = client;
            document.getElementById('receiverId').value = client.id;

            // Update input fields
            document.getElementById('receiverName').value = client.name;
            document.getElementById('receiverName1').textContent = client.name;
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
              console.log('taskData', taskData);

              if (!Array.isArray(taskData)) {
                    console.error('taskData is not an array:', taskData);
                    taskData = []; // Fallback to an empty array
                }
                
            taskData = taskData.filter(task =>
                !items.some(selectedTask => selectedTask.id === task.id)
            );

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
                document.getElementById('receiverId').value = client.id;

                // Update input fields for client
                document.getElementById('receiverName').value = client.name;
                document.getElementById('receiverName1').textContent = client.name;
                document.getElementById('receiverEmail').value = client.email;
                document.getElementById('receiverPhone').value = client.phone;

                document.getElementById('agentId').value = agent.id;
                // Update input fields for agent
                document.getElementById('agentName').value = agent.name;
                document.getElementById('agentEmail').value = agent.email;
                document.getElementById('agentPhone').value = agent.phone;

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
            document.getElementById('receiverName').value = client.name;
            document.getElementById('receiverName1').textContent = client.name;
            document.getElementById('receiverEmail').value = client.email;
            document.getElementById('receiverPhone').value = client.phone;

            document.getElementById('agentId').value = agent.id;
            document.getElementById('agentName').value = agent.name;
            document.getElementById('agentEmail').value = agent.email;
            document.getElementById('agentPhone').value = agent.phone;
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

        function savePartial(mode) {

            if (mode === 'full') {

                if (!validateFullPayment()) return;

                const gateway = document.getElementById('payment_gateway').value;
                const date = document.getElementById('dueDate').value;
                const amount = document.getElementById('subTotal').value;
                const fullData = [];

                fullData.push({
                    date,
                    amount,
                    gateway
                });
                save('full', fullData);
            } else
            if (mode === 'split') {

                if (!validateSplitPayment()) return;

                // Collect Split Payment Data
                const totalAmount = parseFloat(document.getElementById('total-amount').value) || 0;
                const splitInto = parseInt(document.getElementById('split-into').value) || 0;
                const description = document.getElementById('split-desc').value;
                const rows = document.querySelectorAll('#split-rows tr');

                const splitData = [];
                rows.forEach(row => {
                    const selectElement = row.querySelector('select');
                    const clientId = selectElement.value;
                    const date = row.querySelector('input[type="date"]').value;
                    const gateway = row.querySelector('#payment_gateway2').value || null;
                    const amount = parseFloat(row.querySelector('input[type="number"]').value) || 0;
                    const clientName = selectElement.options[selectElement.selectedIndex].text;

                    splitData.push({
                        clientId,
                        clientName,
                        date,
                        amount,
                        gateway
                    });
                });

                save('split', splitData);

            } else if (mode === 'partial') {
                if (!validatePartialPayment()) return;

                // Collect Partial Payment Data
                const totalAmount1 = parseFloat(document.getElementById('total-amount').value) || 0;
                const splitInto1 = parseInt(document.getElementById('split-into1').value) || 0;
                const partialRows = document.querySelectorAll('#split-rows1 tr');
                const gateway = document.getElementById('payment_gateway1').value;

                const partialData = [];

                partialRows.forEach(row => {
                    const date = row.querySelector('input[type="date"]').value;
                    const amount = parseFloat(row.querySelector('input[type="number"]').value) || 0;

                    partialData.push({
                        date,
                        amount,
                        gateway
                    });
                });

                save('partial', partialData);

            }
        }

        async function save(type, data) {
            const invoiceUrl = "{{ route('invoice.partial') }}";
            const csrfToken = "{{ csrf_token() }}";
            const invoiceId = document.getElementById('invoiceId').value;
            const invoiceNumber = document.getElementById('invoiceNumber').value;

            if (type === 'full') {
                const clientId = document.getElementById('receiverId').value;

                try {
                    for (const item of data) {
                        const {
                            date,
                            amount,
                            gateway
                        } = item;

                        // Send POST request for each client
                        const response = await fetch(invoiceUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                invoiceId,
                                invoiceNumber,
                                clientId,
                                type,
                                date,
                                amount,
                                gateway
                            }),
                        });

                        if (!response.ok) {
                            throw new Error(`Failed to generate invoice for client ID: ${clientId}`);
                        }

                        const result = await response.json();

                    }

                    // Display links

                } catch (error) {
                    console.error('Error generating invoices:', error);
                    displayErrorMessage("Error generating one or more invoices. Please check your data.");
                } finally {
                    afterPaymentType();
                    hideModal();
                }
            } else
            if (type === 'split') {
                // Handle split payment, generate links for each row
                try {
                    const invoiceLinks = []; // Store links for each client
                    for (const item of data) {
                        const {
                            clientId,
                            clientName,
                            date,
                            amount,
                            gateway
                        } = item;

                        console.log(invoiceId, clientId, type, date, amount);
                        console.log(csrfToken);
                        console.log(clientName)
                        // Send POST request for each client
                        const response = await fetch(invoiceUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                invoiceId,
                                invoiceNumber,
                                clientId,
                                type,
                                date,
                                amount,
                                gateway
                            }),
                        });

                        if (!response.ok) {
                            throw new Error(`Failed to generate invoice for client ID: ${clientId}`);
                        }

                        const result = await response.json();
                    }

                    // Set the linkVisible flag to true
                    //linkVisible = true;

                    // Update the visibility of the link
                    updateLinkVisibility(invoiceNumber);


                } catch (error) {
                    console.error('Error generating invoices:', error);
                    displayErrorMessage("Error generating one or more invoices. Please check your data.");
                } finally {
                    afterPaymentType();
                    //hideModal();
                }

            } else if (type === 'partial') {
                // Handle partial payment as before
                const clientId = document.getElementById('receiverId').value;

                try {

                    for (const item of data) {
                        const {
                            date,
                            amount,
                            gateway
                        } = item;

                        const response = await fetch(invoiceUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                invoiceId,
                                invoiceNumber,
                                clientId,
                                type,
                                date,
                                amount,
                                gateway
                            }),
                        });

                        if (!response.ok) {
                            throw new Error("Failed to generate partial invoice.");
                        }
                    }
                } catch (error) {
                    console.error('Error generating invoice:', error);
                    displayErrorMessage("Error generating invoice. Please try again.");
                } finally {
                    afterPaymentType();
                    hideModal();
                }
            }
        }


        function validateFullPayment() {
            const gateway = document.getElementById('payment_gateway').value;
            const date = document.getElementById('dueDate').value;
            const amount = parseFloat(document.getElementById('subTotal').value) || 0;

            if (!gateway || !date || amount <= 0) {
                displayErrorMessage("All fields are required and amount must be greater than 0 for full payment.");
                return false;
            }
            return true;
        }

        function validateSplitPayment() {
            const rows = document.querySelectorAll('#split-rows tr');
            const subTotal = parseFloat(document.getElementById('subTotal').value) || 0;
            let totalAmount = 0;

            for (const row of rows) {
                const selectElement = row.querySelector('select');
                const clientId = selectElement.value;
                const date = row.querySelector('input[type="date"]').value;
                const amount = parseFloat(row.querySelector('input[type="number"]').value) || 0;

                if (!clientId || !date || amount <= 0) {
                    displayErrorMessage("Each split payment row must have a client, valid date, and amount greater than 0.");
                    return false;
                }

                totalAmount += amount;
            }

            if (totalAmount > subTotal) {
                displayErrorMessage(`The total amount of split payments (${totalAmount}) cannot exceed the subtotal (${subTotal}).`);
                return false;
            }

            if (totalAmount < subTotal) {
                displayErrorMessage(`The total amount of split payments (${totalAmount}) must equal the subtotal (${subTotal}).`);
                return false;
            }

            return true;
        }

        function validatePartialPayment() {
            const rows = document.querySelectorAll('#split-rows1 tr');
            const gateway = document.getElementById('payment_gateway1').value;
            const subTotal = parseFloat(document.getElementById('subTotal').value) || 0;
            let totalAmount = 0;

            for (const row of rows) {
                const date = row.querySelector('input[type="date"]').value;
                const amount = parseFloat(row.querySelector('input[type="number"]').value) || 0;

                if (!date || amount <= 0) {
                    displayErrorMessage("Each partial payment row must have a valid date and amount greater than 0.");
                    return false;
                }

                totalAmount += amount;
            }

            if (!gateway) {
                displayErrorMessage("Payment gateway is required for partial payment.");
                return false;
            }

            if (totalAmount > subTotal) {
                displayErrorMessage(`The total amount of partial payments (${totalAmount}) cannot exceed the subtotal (${subTotal}).`);
                return false;
            }

            if (totalAmount < subTotal) {
                displayErrorMessage(`The total amount of partial payments (${totalAmount}) must equal the subtotal (${subTotal}).`);
                return false;
            }

            return true;
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

        function afterPaymentType() {
            const partial = document.getElementById('payment_type_partial');
            const split = document.getElementById('payment_type_split');
            const full = document.getElementById('payment_type_full');
            const update = document.getElementById('update-invoice-btn');
            const paymentType = document.querySelector('input[name="payment_type"]:checked').value;
            // Get all payment type inputs
            const paymentOptions = document.querySelectorAll('input[name="payment_type"]');

            // Get the selected payment type
            const selectedOption = document.querySelector('input[name="payment_type"]:checked');
            const shared = document.getElementById('shared');
            update.disabled = true;
            shared.value = "true";

            // Disable all options
            paymentOptions.forEach(option => {
                option.disabled = true; // Disable the radio button
                const label = option.closest('label'); // Find the parent label
                if (option === selectedOption) {
                    // Highlight the selected label
                    label.classList.add('highlight-selected');
                } else {
                    // Fade the unselected labels
                    label.classList.add('fade-unchecked');
                }
            });

        }

        function updateLinkVisibility(invoiceNumber) {
            const rows = document.querySelectorAll("#split-rows tr");
            rows.forEach(row => {
                // Get the clientId from the select element or hidden input
                const clientIdSelect = row.querySelector("select[name^='customer_name_']");
                const clientId = clientIdSelect ? clientIdSelect.value : null;

                // Update the link only if clientId is available
                if (clientId) {
                    const linkCell = row.querySelector("td:last-child");
                    linkCell.innerHTML = `
                        <a href="/invoice/partial/${invoiceNumber}/${clientId}" 
                        class="text-blue-500 underline" 
                        target="_blank">
                        View Details
                        </a>
                    `;
                }
            });
        }

        // Generate invoice
        async function generateInvoice() {

            isSaving = true;
            updateButtonState();

            const invoiceUrl = "{{ route('invoice.store') }}";
            const csrfToken = "{{ csrf_token() }}";

            const currencyElement = document.getElementById('currency');
            const invoiceNumberElement = document.getElementById('invoiceNumber');
            const invdateElement = document.getElementById('invoiceDate');
            const duedateElement = document.getElementById('dueDate');
            const subTotalElement = document.getElementById('subTotal');
            const clientIdElement = document.getElementById('receiverId');
            const agentIdElement = document.getElementById('agentId');
            const selectedBranch = document.getElementById('selectedBranch');

            const currency = currencyElement ? currencyElement.value : null;
            const invoiceNumber = invoiceNumberElement ? invoiceNumberElement.value : null;
            const invdate = invdateElement ? invdateElement.value : null;
            const duedate = duedateElement ? duedateElement.value : null;
            const subTotal = subTotalElement ? subTotalElement.value : null;
            const clientId = clientIdElement ? clientIdElement.value : null;
            const agentId = agentIdElement ? agentIdElement.value : null;
            const selectedBranchValue = selectedBranch ? selectedBranch.value : null;
            const tasks = items;

            // Show loading state
            buttonText.style.display = "none";
            buttonLoading.style.display = "inline";

            console.log(
                'clientId:', clientId,
                'agentId:', agentId,
                'tasksLength:', tasks.length,
                'selectedBranchValue:', selectedBranchValue,
                'currency:', currency,
                'invoiceNumber:', invoiceNumber,
                'invdate:', invdate,
                'duedate:', duedate,
                'subTotal:', subTotal,
            );

            let errorMessages = [];

            // Validate all inputs and add specific messages
            if (!currency) errorMessages.push("Currency is missing.");
            if (!invoiceNumber) errorMessages.push("Invoice number is missing.");
            if (!invdate) errorMessages.push("Invoice date is missing.");
            if (!duedate) errorMessages.push("Due date is missing.");
            if (!subTotal) errorMessages.push("Subtotal is missing.");
            if (!clientId) errorMessages.push("Client ID is missing.");
            if (!agentId) errorMessages.push("Agent ID is missing.");
            if (!items.length) errorMessages.push("No tasks have been selected.");
            if (!selectedBranchValue) errorMessages.push("Branch selection is required.");

            // Check if there are any errors
            if (errorMessages.length > 0) {
                // Create the error notification element
                let errorNotification = document.createElement('div');
                errorNotification.className = "alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg";
                errorNotification.innerHTML = `
                        <ul>
                            ${errorMessages.map(message => `<li>${message}</li>`).join('')}
                        </ul>
                        <button type="button" class="close text-white ml-2" aria-label="Close"
                            onclick="this.parentElement.style.display='none';">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    `;

                // Append the error notification to the body
                document.body.appendChild(errorNotification);

                // Reset button state or perform any cleanup
                resetButtonState();
                return;
            }

            // Proceed with the form submission or further processing
            console.log("All required data is provided. Proceeding...");

            try {
                console.log('invoiceUrl: ', invoiceUrl);
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
                    throw new Error("Failed to generate");
                }

                const result = await response.json();
                const {
                    invoiceId
                } = result;
                console.log(invoiceId);

                document.getElementById('invoiceId').value = invoiceId;
                const generatedLink = appUrl + '/invoice/' + invoiceNumber;

                // Show success state
                isSaved = true; // Mark as saved after generating
                updateButtonState();

                coaActivites(items, subTotal);

                setTimeout(() => {
                    checkInvoiceId();
                    // Show COA activities container
                    document.getElementById("coa-activities-container").style.display = "block";
                }, 1000);

            } catch (error) {
                console.error(error);
                let alert = document.createElement('div');
                alert.innerHTML = ` 
                        <div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">
                            Error Generating Invoice
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
            }
        };


        function coaActivites(items, subTotal) {
            const supplierTotals = new Map(); // To track cumulative amounts for each supplier
            let cumulativeMarkup = 0; // Total markup income

            const clientNameInput = document.getElementById("receiverName");
            const defaultClientName = "Unknown Client"; // Fallback if input is empty or unavailable
            const clientNameFromInput = clientNameInput ? clientNameInput.value.trim() : defaultClientName;

            const activities = items.map(item => {
                // Extract relevant details for each activity
                const taskId = item.reference || "Unknown Task ID"; // Task ID
                const supplierName = item.supplier_name || "Unknown Supplier";
                const agentName = item.agent_name || "Unknown Agent";
                const totalAmount = parseFloat(item.price || 0); // Payable amount to the supplier
                const markupValue = parseFloat(item.invprice || 0) - parseFloat(item.price || 0); // Markup = invprice - price

                // Update cumulative totals per supplier
                if (!supplierTotals.has(supplierName)) {
                    supplierTotals.set(supplierName, 0);
                }
                supplierTotals.set(supplierName, supplierTotals.get(supplierName) + totalAmount);

                // Update cumulative markup
                cumulativeMarkup += markupValue;

                // Construct the activities
                return [
                    `Task ID: ${taskId} - Income of KWD${markupValue.toFixed(2)} from agent: ${agentName}`
                ];
            }).flat(); // Flatten the array since map creates a nested array for each item

            activities.push(`Payments to receive from: ${clientNameFromInput} amount: KWD${parseFloat(subTotal || 0).toFixed(2)}`);
            // Add cumulative totals for each supplier
            supplierTotals.forEach((total, supplierName) => {
                activities.push(`Payment need to be made to ${supplierName}: KWD${total.toFixed(2)}`);
            });

            // Add overall cumulative totals
            activities.push(`Total markup income: KWD${cumulativeMarkup.toFixed(2)}`);

            // Get the container where activities will be displayed
            const activitiesList = document.getElementById("coa-activities-list");

            // Clear any previous content
            activitiesList.innerHTML = "";

            // Display the activities
            activities.forEach(activity => {
                const listItem = document.createElement("li");
                listItem.textContent = activity;
                activitiesList.appendChild(listItem);
            });
        }

        function viewInvoice(){
            openInvoiceModal(document.getElementById('invoiceNumber').value);
        }

        function openInvoiceModal(invoiceNumber) {
            const modal = document.getElementById("viewInvoiceModal");
            const contentDiv = document.getElementById("invoiceContent");

            // Clear previous content
            contentDiv.innerHTML = "";

            // Open the modal
            modal.classList.remove("hidden");
            url =
                "{{ route('invoice.show', ['invoiceNumber' => ':invoiceNumber']) }}".replace(
                    ":invoiceNumber",
                    invoiceNumber
                );

            // Fetch the invoice details
            fetch(url)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error("Network response was not ok");
                    }
                    return response.text();
                })
                .then((data) => {
                    contentDiv.innerHTML = data;

                    // Close the modal when the backdrop is clicked
                    modal.addEventListener("click", (event) => {
                        if (event.target === modal) {
                            closeInvoiceModal();
                        }
                    });


                })
                .catch((error) => {
                    console.error("Error fetching invoice details:", error);
                    contentDiv.innerHTML =
                        '<p class="text-center text-red-500">Failed to load invoice details.</p>';

                });
        }

        function closeInvoiceModal() {
            const modal = document.getElementById("viewInvoiceModal");
            modal.classList.add("hidden");
        }


        function resetButtonState() {
            isSaving = false;
            isSaved = false;
            updateButtonState();
        }


        function copyLink() {
            const invoiceNumber = document.getElementById('invoiceNumber').value;
            const copyFeedback = document.getElementById('copyFeedback');
            const baseUrl = window.location.origin; 
            const invoiceLink = `${baseUrl}/invoice/${invoiceNumber}/pdf`;
            const fetchUrl =
                "{{ route('invoice.pdf', ['invoiceNumber' => ':invoiceNumber']) }}".replace(
                    ":invoiceNumber",
                    invoiceNumber
                );

            navigator.clipboard.writeText(invoiceLink).then(() => {
                alert('Link copied to clipboard: ' + invoiceLink);  // Use invoiceLink here
                copyFeedback.classList.remove('hidden');
                setTimeout(() => copyFeedback.classList.add('hidden'), 3000);

                fetch(fetchUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/pdf',
                    },
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.blob();
                    })
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `Invoice_${invoiceNumber}.pdf`; // Filename for the downloaded PDF
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url); // Clean up the URL object
                    })
                    .catch(err => {
                        console.error('Failed to download PDF: ', err);
                        alert('Failed to download PDF. Please try again.');
                    });

            }).catch(err => {
                alert('Failed to copy link: ' + err);
            });
        }

        document.addEventListener("DOMContentLoaded", function() {

            tasks = @json($tasks);
            tasks = Array.isArray(tasks) ? tasks : Object.values(tasks);
            let clients = @json($clients);
            console.log('tasks', tasks);
            console.log(Array.isArray(tasks));
            // Initial rendering of items
            renderItems();


            // Initialize modals with full data
            renderClientList(clients);
            renderTaskList(tasks);



        });
    </script>

</x-app-layout>