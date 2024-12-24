<x-app-layout>
    <style>
        button[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
    
    <div id="invoiceModalComponent">

        <div class="flex flex-col gap-2.5 xl:flex-row">
            <div class="panel flex-1 px-0 py-6 lg:mr-6 ">
                <!-- company details -->
                <div class="flex flex-wrap justify-between px-4">
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
                        <div class="flex items-center w-full space-x-4">
                         <label class="text-sm font-semibold">Branch</label>
                            <select id="branch" name="branch" class=" border border-gray-300 p-2 rounded">
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
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

                        <!-- Refresh Button -->
                        <div class="mt-6 flex justify-end">
                            <button type="button" onclick="location.reload()" class="px-4 py-2 city-light-yellow text-white rounded hover:text-[#004c9e] flex items-center">
                                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                    <div class="table-responsive">
                        <table id="itemsTable">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Client</th>
                                    <th>Agent</th>
                                    <th>Branch</th>
                                    <th class="w-1">Quantity</th>
                                    <th class="w-1">Task Price</th>
                                    <th>Invoice Price</th>
                                    <th class="w-1"></th>
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
                        <div class="sm:w-2/5 flex justify-end">
                            <div class="mt-4 flex items-center font-semibold">
                                <div class="mr-2">Total:</div>
                                <span id="subT">0.00</span>
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

                     <!-- Payment Type Section -->
                     <div  id="paymentMethod" class="mt-4">
                            <h2 class="text-lg font-semibold mb-3 text-gray-700">Payment Type</h2>
                            <div class="flex gap-4">
                                <!-- Full Payment Tab -->
                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        id="payment_type_full"
                                        name="payment_type"
                                        value="full"
                                        onclick="hideModal()"
                                        hidden
                                        class="peer"
                                        checked
                                    />
                                    <div class="peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition flex flex-col items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-blue-500 peer-checked:text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19a1 1 0 00.76-.36l3-4a1 1 0 00-.76-1.64H12V7a1 1 0 00-2 0v6H8a1 1 0 00-.76 1.64l3 4A1 1 0 0011 19z" />
                                        </svg>
                                        <span class="font-medium">Full</span>
                                        <p class="text-sm text-gray-500">Pay the total amount.</p>
                                    </div>
                                </label>

                                <!-- Partial Payment Tab -->
                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        id="payment_type_partial"
                                        name="payment_type"
                                        value="partial"
                                        onclick="showModal('partial')"
                                        hidden
                                        class="peer"
                                    />
                                    <div class="peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition flex flex-col items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-blue-500 peer-checked:text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-4H5a1 1 0 01-.9-1.4L8.1 7h1.7l3 4H11v4h2a1 1 0 01.9 1.4l-3.1 5H9z" />
                                        </svg>
                                        <span class="font-medium">Partial</span>
                                        <p class="text-sm text-gray-500">Split the amount into parts.</p>
                                    </div>
                                </label>

                                <!-- Split Payment Tab -->
                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        id="payment_type_split"
                                        name="payment_type"
                                        value="split"
                                        onclick="showModal('split')"
                                        hidden
                                        class="peer"
                                    />
                                    <div class="peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition flex flex-col items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-blue-500 peer-checked:text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19l-3-3m0 0l3-3m-3 3h12m-9-6V5a1 1 0 011-1h2a1 1 0 011 1v6m-5-3h6" />
                                        </svg>
                                        <span class="font-medium">Split</span>
                                        <p class="text-sm text-gray-500">Split and generate links.</p>
                                    </div>
                                </label>
                            </div>

                                                <!-- Payment Gateway Section -->
                                <section  id="payment_gateway_section" class="mb-6">
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
                                                fill="currentColor"
                                            />
                                            <path
                                                d="M11.25 8V4.75a.75.75 0 011.5 0V8h2.25a.75.75 0 010 1.5H12.75V12a.75.75 0 01-1.5 0V9.5H9a.75.75 0 010-1.5h2.25z"
                                                fill="currentColor"
                                            />
                                        </svg>
                                        Update Invoice
                                    </button>
                                    </div>
                                </section>

                                <!-- Added Buttons/Links Section -->
                            <section id="additional-actions" class="mt-6">
                                <div class="flex flex-wrap gap-4">

                                    <!-- Share Buttons -->
                                    <div class="flex items-center gap-2">
                                        <button onclick="shareViaWhatsApp()" class="inline-flex items-center px-4 py-2 text-sm text-white bg-gray-600 hover:bg-blue-700 rounded">
                                            Share via WhatsApp
                                        </button>
                                        <button onclick="shareViaEmail()" class="inline-flex items-center px-4 py-2 text-sm text-white bg-gray-600 hover:bg-indigo-700 rounded">
                                            Share via Email
                                        </button>
                                        <button onclick="copyLink()" class="inline-flex items-center px-4 py-2 text-sm text-white bg-gray-600 hover:bg-gray-700 rounded">
                                            Copy Link
                                        </button>
                                    </div>

                                    <!-- View Button -->
                                    <button onclick="viewInvoice()" class="inline-flex items-center px-4 py-2 text-sm text-white bg-teal-600 hover:bg-teal-700 rounded">
                                        View
                                    </button>
                                </div>
                            </section>


                        </div>

                </div>
                <div class="panel">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-1">

                        <button id="generate-invoice-btn" type="button"  class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
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

                        <button id="send-invoice-btn" type="button"  class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                        city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
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
                                                                <input type="number" id="total-amount" class="w-full border-gray-300 rounded-md shadow-sm opacity-50" placeholder="0" disabled/>
                                                            </div>
                                                            <div>
                                                                <label class="block text-sm font-medium mb-1">Split into *</label>
                                                                <input type="number" id="split-into" class="w-full border-gray-300 rounded-md shadow-sm" placeholder="1" oninput="updateRows()" />
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
                                                         
                                                         <div >
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
                                                            <label  class="block text-sm font-medium mb-1">Client Name</label>
                                                            <span id="receiverName1">AHMED</span>
                                                        </div>
                                                        <div>
                                                            <label for="receiverEmail1" class="mb-0 w-1/3 mr-2 ">Invoice Total</label>
                                                            <span id="subT1">             0.00</span>
                                                        </div>
                                                     </div>
    
                                                   <div class="grid grid-cols-3 gap-4 mb-5">
                                                         <div>
                                                            <label class="block text-sm font-medium mb-1">Split into *</label>
                                                            <input type="number" id="split-into1" class="w-full border-gray-300 rounded-md shadow-sm" placeholder="1" oninput="updateRows1()" />
                                                        </div>
                                                        <div>
                                                            <label  class="block text-sm font-medium mb-1">Payment Gateway</label>
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

    <script>
        let selectedTasks = @json($selectedTasks);
        let clients = @json($clients);
        let agents = @json($agents);
        let items = [];
        let tasks = [];
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

        
        const invoiceIdInput = document.getElementById('invoiceId');
        

        function checkInvoiceId() {
        const tabs = document.querySelectorAll('input[name="payment_type"]');
        const clientButton = document.getElementById("openClientModalButton");
        const agentButton = document.getElementById("select-agent");
        const taskButton = document.getElementById("openTaskModalButton");
        const sendInvoice = document.getElementById("send-invoice-btn");
        const generateInvoice = document.getElementById("generate-invoice-btn");
        const paymentGatewaySection = document.getElementById('payment_gateway_section');
        const paymentType = document.querySelector('input[name="payment_type"]:checked').value;


        if (paymentType === 'full') {
            paymentGatewaySection.style.display = 'block'; // Show the section
        } else {
            paymentGatewaySection.style.display = 'none'; // Hide the section
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
            sendInvoice.classList.add('hidden');
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
            sendInvoice.classList.remove('hidden');
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
        if(type == 'split'){
            document.getElementById('paymentModal').classList.remove('hidden');
        } else   if(type == 'partial'){
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
                noItemsRow.innerHTML = '<td colspan="5" class="!text-center font-semibold">No Item Available</td>';
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
                                <p>${item.agent_name}</p>
                                </td>
                                <td>
                                <p>${item.branch_name}</p>
                                </td>
                                <td class="border-b px-4 py-2">
                                  <p>${item.quantity}</p>
                                </td>
                                 <td class="border-b px-4 py-2">${(item.total * item.quantity).toFixed(2)}</td>
                                <td class="border-b px-4 py-2">
                                        <input 
                                        id="invprice-${item.id}" 
                                        type="number" 
                                        name="invprice" 
                                        placeholder="Invoice Price" 
                                        class="form-input w-2/3 lg:w-[150px]" 
                                        oninput="updateItemPrice(${item.id})"
                                    />
                                </td>

                                <td class="border-b px-4 py-2">
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

                // Check if client and agent exist
                if (client && agent) {
                    // Update hidden fields
                    document.getElementById('receiverId').value = client.id;

                    // Update input fields for client
                    document.getElementById('receiverName').value = client.name;
                    document.getElementById('receiverName1').textContent = client.name;
                    document.getElementById('receiverEmail').value = client.email;
                    document.getElementById('receiverPhone').value = client.phone;

                    // Update input fields for agent
                    document.getElementById('agentName').value = agent.name;
                    document.getElementById('agentEmail').value = agent.email;
                    document.getElementById('agentPhone').value = agent.phone;
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

        function savePartial(mode) {
     
            if (mode === 'full') {
                    const gateway = document.getElementById('payment_gateway').value;
                    const date = document.getElementById('duedate').value;
                    const amount = document.getElementById('subTotal').value;
                    const fullData = [];

                    fullData.push({ date, amount, gateway });
                    save('full', fullData);
            }else
            if (mode === 'split') {
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

                    splitData.push({ clientId, clientName, date, amount, gateway });
                });

                console.log('Split Payment Data:', { totalAmount, splitInto, description, splitData });
                save('split', splitData);

            } else if (mode === 'partial') {
                // Collect Partial Payment Data
                const totalAmount1 = parseFloat(document.getElementById('total-amount').value) || 0;
                const splitInto1 = parseInt(document.getElementById('split-into1').value) || 0;
                const partialRows = document.querySelectorAll('#split-rows1 tr');
                const gateway = document.getElementById('payment_gateway1').value;

                const partialData = [];

                partialRows.forEach(row => {
                    const date = row.querySelector('input[type="date"]').value;
                    const amount = parseFloat(row.querySelector('input[type="number"]').value) || 0;

                    partialData.push({ date, amount, gateway });
                });
   
                console.log('Partial Payment Data:', partialData);
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
                        const { date, amount, gateway } = item;

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
        }else
         if (type === 'split') {  
        // Handle split payment, generate links for each row
        try {
            const invoiceLinks = []; // Store links for each client
            for (const item of data) {
                const { clientId, clientName, date, amount, gateway } = item;

                console.log(invoiceId,clientId,type,date,amount);
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
           
        } catch (error) {
            console.error('Error generating invoices:', error);
            displayErrorMessage("Error generating one or more invoices. Please check your data.");
        } finally {
            afterPaymentType();
            hideModal();
        }

            } else if (type === 'partial') {
                // Handle partial payment as before
               const clientId = document.getElementById('receiverId').value;

                try { 

                    for (const item of data) {
                        const { date, amount, gateway } = item;

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

        function afterPaymentType(){
            const tabs = document.querySelectorAll('input[name="payment_type"]');
            const partial = document.getElementById('payment_type_partial');
            const split = document.getElementById('payment_type_split');
            const full = document.getElementById('payment_type_full');
            const update = document.getElementById('update-invoice-btn');
            const paymentType = document.querySelector('input[name="payment_type"]:checked').value;

            if (paymentType === 'full') {
                partial.disabled = true;
                split.disabled = true;
                full.disabled = false;
                update.disabled = true;
            } else if (paymentType === 'partial') {
                partial.disabled = false;
                split.disabled = true;
                full.disabled = true;
            } else if(paymentType === 'split') {
                partial.disabled = true;
                split.disabled = false;
                full.disabled = true;
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
                const { invoiceId } = result;
                console.log(invoiceId);

                document.getElementById('invoiceId').value = invoiceId;
                const generatedLink = appUrl + '/invoice/' + invoiceNumber;

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
                    checkInvoiceId();
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

            tasks = @json($tasks);
            let clients = @json($clients);


            // Initial rendering of items
            renderItems();


            // Initialize modals with full data
            renderClientList(clients);
            renderTaskList(tasks);



        });
    </script>


</x-app-layout>