<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<div class="container mx-auto">

    <!-- Chat Section -->
    <div class="bg-white shadow-md rounded-lg h-[550px] w-[450px] flex flex-col"
        style="background-image: url('images/aibgPic02.svg'); background-position: center; background-size: cover; background-repeat: no-repeat;">
        <!-- Header -->
        <div class="px-4 py-2 justify-start font-semibold flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 14 14">
                <g fill="none" stroke="#ffc107" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6.022 4.347a18.5 18.5 0 0 0-1.93 1.686C1.248 8.877-.192 12.046.874 13.113c1.066 1.066 4.236-.375 7.079-3.218a18.5 18.5 0 0 0 1.686-1.931" />
                    <path d="M9.639 7.964c1.677 2.226 2.36 4.32 1.532 5.148c-1.067 1.067-4.236-.374-7.08-3.217C1.249 7.05-.191 3.882.875 2.815c.828-.827 2.922-.144 5.148 1.532" />
                    <path d="M5.522 7.964a.5.5 0 1 0 1 0a.5.5 0 0 0-1 0m2.51-4.354c-.315-.055-.315-.506 0-.56a2.84 2.84 0 0 0 2.29-2.193L10.34.77c.068-.31.51-.312.58-.003l.024.101a2.86 2.86 0 0 0 2.296 2.18c.316.055.316.509 0 .563a2.86 2.86 0 0 0-2.296 2.18l-.024.101c-.07.31-.512.308-.58-.002l-.02-.087A2.84 2.84 0 0 0 8.03 3.61Z" />
                </g>
            </svg>
            <p class="pl-2">AI Assistant</p>
        </div>

        <!-- Chat Section -->
        <div class="flex-grow p-4 overflow-y-auto">
            <div id="chat-log" class="mb-4 p-3">
                <!-- Chat messages will appear here -->
            </div>
        </div>

        <!-- Input Section -->
        <div class="p-4 items-center">
            <div class="border border-[#1e40af] rounded-lg flex items-center overflow-hidden">
                <input id="user-message" type="text"
                    class="flex-grow border-none dark:bg-gray-700 dark:text-white focus:ring-0 focus:border-none"
                    placeholder="Type your message...">
                <button id="send-message"
                    class="bg-[#1e40af] relative w-12 h-12 flex items-center justify-center m-2 dark:bg-gray-700 hover:bg-gray-300 rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <g fill="none">
                            <path d="m12.594 23.258l-.012.002l-.071.035l-.02.004l-.014-.004l-.071-.036q-.016-.004-.024.006l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.016-.018m.264-.113l-.014.002l-.184.093l-.01.01l-.003.011l.018.43l.005.012l.008.008l.201.092q.019.005.029-.008l.004-.014l-.034-.614q-.005-.019-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.003-.011l.018-.43l-.003-.012l-.01-.01z" />
                            <path fill="#fff" d="M20.235 5.686c.432-1.195-.726-2.353-1.921-1.92L3.709 9.048c-1.199.434-1.344 2.07-.241 2.709l4.662 2.699l4.163-4.163a1 1 0 0 1 1.414 1.414L9.544 15.87l2.7 4.662c.638 1.103 2.274.957 2.708-.241z" />
                        </g>
                    </svg>
                </button>
            </div>

        </div>
    </div>


    <!-- Task Selection Section -->
    <div id="task-selection" class="bg-white shadow-md rounded-lg mb-6 hidden">
        <div class="bg-green-500 text-white px-4 py-2 rounded-t-lg font-semibold flex justify-between items-center">
            <span>Task Selection</span>
            <button id="close-task-selection" class="text-white hover:text-gray-200">&times;</button>
        </div>
        <div class="p-4">
            <p class="mb-4">Select tasks to include in the invoice:</p>
            <ul id="task-list" class="space-y-2">
                <!-- Tasks will be dynamically loaded here -->
            </ul>
            <button id="confirm-tasks" class="mt-4 bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">Confirm Tasks</button>
        </div>
    </div>

    <!-- Task Pricing Section -->
    <div id="task-pricing" class="bg-white shadow-md rounded-lg mb-6 hidden">
        <div class="bg-yellow-500 text-white px-4 py-2 rounded-t-lg font-semibold flex justify-between items-center">
            <span>Task Pricing</span>
            <button id="close-task-pricing" class="text-white hover:text-gray-200">&times;</button>
        </div>
        <div class="p-4">
            <p class="mb-4">Enter invoice prices for selected tasks:</p>
            <form id="pricing-form" class="space-y-4">
                <div id="pricing-fields" class="space-y-2">
                    <!-- Pricing fields will be dynamically loaded here -->
                </div>
                <button type="submit" class="btn primary-btn">Generate Invoice</button>
            </form>
        </div>
    </div>

    <!-- Payment Modal -->

    <input id="invoiceNumberChat" type="hidden" name="invoiceNumberChat" />
    <input id="invoiceIdChat" type="hidden" name="invoiceIdChat" />
    <input id="invoiceAmountChat" type="hidden" name="invoiceAmountCHat" />
    <input id="subTotalChat" type="hidden" name="subTotalChat" />
    <input id="due_dateChat" type="hidden" name="due_dateChat" />
    <input id="receiverIdChat" type="hidden" name="receiverIdChat" />


    <div id="open-payment-type" class="bg-white shadow-md rounded-lg mb-6 hidden">
        <div class="bg-yellow-500 text-white px-4 py-2 rounded-t-lg font-semibold flex justify-between items-center">
            <span>Select Payment Type</span>
            <button id="close-payment-type" class="text-white hover:text-gray-200">&times;</button>
        </div>
        <div class="p-4">
            <div class="modal-body">
                <form id="payment-type-form">
                    <div class="form-group">
                        <label for="payment-type">Choose Payment Type:</label>
                        <select id="payment-typeChat" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="full">Full</option>
                            <option value="partial">Partial</option>
                            <option value="split">Split</option>
                        </select>
                    </div>
                    <div id="payment-details" class="mt-3"></div>
                    <button type="submit" class="btn btn-primary mt-3">Proceed</button>
                </form>
            </div>
        </div>
    </div>

    <div id="payment_gateway_section" class="fixed inset-0 z-50 hidden bg-gray-800 bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg w-3/4 p-5">
            <h2 class="text-lg font-semibold mb-3 text-gray-700">Choose Payment Gateway</h2>
            <div class="bg-gray-100 p-5">
                <div class="max-w-5xl mx-auto bg-white shadow-md rounded-lg p-6">

                    <!-- Split Payment Tab Content -->
                    <div id="split-payment-container" class="tab-content">

                        <select id="payment_gateway" name="payment_gateway" class="border border-gray-300 p-2 rounded w-full">
                            <option value="Tap">Tap</option>
                            <option value="Hesabe">Hesabe</option>
                            <option value="MyFatoorah">MyFatoorah</option>
                        </select>
                        <div>
                            <button type="button" onclick="savePartial('full')" class="inline-flex items-center justify-center text-sm text-black font-semibold
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
                                    <input type="number" id="total-amountChat" class="w-full border-gray-300 rounded-md shadow-sm opacity-50" placeholder="0" disabled />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1" for="split-into">Split into *</label>
                                    <select id="split-intoChat" class="w-full border-gray-300 rounded-md shadow-sm" onchange="updateRows()">
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
                                    <textarea id="split-descChat" class="w-full border-gray-300 rounded-md shadow-sm" placeholder="Add Description"></textarea>
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
                                    <tbody id="split-rowsChat">
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
                                <span id="receiverName1Chat">AHMED</span>
                            </div>
                            <div>
                                <label for="subT1Chat" class="mb-0 w-1/3 mr-2 ">Invoice Total</label>
                                <span id="subT1Chat">0.00</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 mb-5">
                            <div>
                                <label class="block text-sm font-medium mb-1" for="split-into1">Split into *</label>
                                <select id="split-into1Chat" class="w-full border-gray-300 rounded-md shadow-sm" onchange="updateRows1()">
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
                                <select id="payment_gateway1Chat" name="payment_gateway1Chat" class="w-full p-2 border-gray-300 rounded-md shadow-sm">
                                    <option value="Tap">Tap</option>
                                    <option value="Hesabe">Hesabe</option>
                                    <option value="MyFatoorah">MyFatoorah</option>
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
                            <tbody id="split-rows1Chat">
                                <!-- Dynamic rows will be generated here -->
                            </tbody>
                        </table>

                        <p id="error-messageChat" class="text-red-500 mt-3 hidden">The total of partial payments must match the invoice total.</p>

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

    <div id="client-options-modal" class="bg-white shadow-md rounded-lg mb-6 hidden">
                                <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-md">
                                    <div class="border-b border-gray-200 p-4 flex justify-between">
                                        <h5 class="text-lg font-semibold text-gray-700">Add New Client</h5>
                                        <button id="close-option-client" class="text-black hover:text-gray-200">&times;</button>
                                    </div>
                                    <div class="p-6">
                                        <p class="text-gray-600 mb-4">Please choose how you want to proceed:</p>
                                        <div class="flex justify-between space-x-4">
                                            <button
                                                id="upload-passport-btn"
                                                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg focus:outline-none shadow-md"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-5 w-5 inline-block mr-2"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        d="M3 3a2 2 0 00-2 2v2a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2H3z"
                                                    />
                                                    <path
                                                        d="M17 10H3a2 2 0 00-2 2v2a2 2 0 002 2h14a2 2 0 002-2v-2a2 2 0 00-2-2z"
                                                    />
                                                </svg>
                                                Upload Passport
                                            </button>
                                            <button
                                                id="fill-form-btn"
                                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg focus:outline-none shadow-md"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-5 w-5 inline-block mr-2"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        d="M5 3a2 2 0 012-2h6a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V3z"
                                                    />
                                                </svg>
                                                Fill Form
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>



                <div id="passport" >
                    <input type="file" id="passport-upload-input" accept="image/*,application/pdf" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg cursor-pointer dark:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" hidden>
                    <div id="file-preview-container" class="mt-4"></div> <!-- For image preview -->
                    <div id="upload-status" class="mt-2 text-sm text-gray-600"></div> <!-- For upload status -->
                    <div id="passport-details" class="mt-4 text-sm text-gray-800"></div> <!-- For displaying extracted details -->
                </div>


          <!-- Create Client -->
        <div id="create-client" class="bg-white shadow-md rounded-lg mb-6 hidden">
             <div class="bg-green-500 text-white px-4 py-2 rounded-t-lg font-semibold flex justify-between items-center">
                <span>Create Client</span>
                <button id="close-create-client" class="text-white hover:text-gray-200">&times;</button>
            </div>
            <div class="p-4">
                <p class="mb-4">Enter client Information:</p>
                <form id="client-form" class="space-y-4">
                          <div class="mb-4 flex gap-4">
                                <!-- Name Field -->
                                <div class="w-1/2">
                                    <label for="name" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                                    <input id="nameChat" name="name" type="text" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Client Name" />
                                </div>

                                <!-- Email Field -->
                                <div class="w-1/2">
                                    <label for="email" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                                    <input id="emailChat" name="email" type="email" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Client Email" />
                                </div>
                            </div>

                            <div class="mb-4 flex gap-4">
                                <!-- Phone Field -->
                                <div class="w-1/2">
                                    <label for="phone" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone</label>
                                    <input id="phoneChat" name="phone" type="text" required
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Client Phone" />
                                </div>

                                <!-- Address Field -->
                                <div class="w-1/2">
                                    <label for="address" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Address</label>
                                    <input id="addressChat" name="address" type="text"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Client Address" />
                                </div>
                            </div>

                            <!-- Passport Number Field -->
                            <div class="mb-4 flex gap-4">
                             <div class="w-1/2">
                                <label for="passport_no" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Passport Number</label>
                                <input id="passport_noChat" name="passport_no" type="text" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Passport Number" />
                            </div>
                            <div class="w-1/2">
                                <label for="civil_no" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Civil Number</label>
                                <input id="civil_noChat" name="civil_noChat" type="text" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Civil Number" />
                            </div>
                            </div>

                            <!-- Agent Email Field -->
                            <div class="mb-4 flex gap-4">
                                <div class="w-1/2">
                                    <label for="agent_id" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Agent</label>
                                    <select id="agent_idChat" name="agent_idChat" class="w-full p-2 border rounded-md" placeholder="Select Agent">
                                    @foreach ($agents as $agent)
                                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                    @endforeach
                                    </select>
                                </div>
                                <div class="w-1/2">
                                <label for="civil_no" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Date of Birth</label>
                                <input id="date_of_birthChat" type="date" name="date_of_birthChat" 
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                     />
                                </div>
                           </div>
                           <input id="clientForm" type="hidden" name="clientForm" />    
                           <input id="clientId" type="hidden" name="clientId" />    
                            <!-- Submit Button -->
                            <div class="flex items-center justify-center">
                                <button type="submit" class="btnCityGrayColor mt-3 w-full bg-black BtColor text-white px-4 py-2 rounded-lg">
                                    Register Client
                                </button>
                            </div>
                </form>
            </div>
        </div>


    <!-- Create Agent -->
    <div id="create-agent" class="bg-white shadow-md rounded-lg mb-6 hidden">
        <div class="bg-green-500 text-white px-4 py-2 rounded-t-lg font-semibold flex justify-between items-center">
            <span>Create Agent</span>
            <button id="close-create-agent" class="text-white hover:text-gray-200">&times;</button>
        </div>
        <div class="p-4">
            <p class="mb-4">Enter agent Information:</p>
            <form id="agent-form" class="space-y-4">
                <div class="mb-4">
                    <label for="name"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                    <input name="name" type="text" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Agent Name" />
                </div>

                <!-- Email Address -->
                <div class="mb-4">
                    <label for="email"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                    <input name="email" type="email" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Agent Email" />
                </div>

                <!-- Phone Field -->
                <div class="mb-4">
                    <label for="phone_number"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone
                        Number</label>
                    <input id="phone_number" name="phone_number" type="text" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Phone Number" />
                </div>

                <!-- Agent Type -->
                <div class="mb-4">
                    <label for="type"
                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Type</label>
                    <select id="type" name="type" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div id="agent-fields" class="space-y-2">
                    <!-- Pricing fields will be dynamically loaded here -->
                </div>
                <div class="flex items-center justify-center">
                    <button type="submit" class="btnCityGrayColor mt-3 w-full bg-black BtColor text-white px-4 py-2 rounded-lg">
                        Register Agent
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Branch -->
    <div id="create-branch" class="bg-white shadow-md rounded-lg mb-6 hidden">
        <div class="bg-green-500 text-white px-4 py-2 rounded-t-lg font-semibold flex justify-between items-center">
            <span>Create Branch</span>
            <button id="close-create-branch" class="text-white hover:text-gray-200">&times;</button>
        </div>
        <div class="p-4">
            <p class="mb-4">Enter branch Information:</p>
            <form id="branch-form" class="space-y-4">
                <div id="branch-modal" class="w-full lg:w-3/5 p-8 flex items-center justify-center">
                    <div class="w-full">
                        <!-- Name Field -->
                        <div class="mb-4">
                            <label for="name"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Branch Name</label>
                            <input id="branch_name" name="branch_name" type="text" required autofocus
                                autocomplete="name"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Branch Name" />
                        </div>

                        <div class="mb-4">
                            <label for="name"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">User Name</label>
                            <input name="name" type="text" required autofocus
                                autocomplete="name"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="User Name" />
                        </div>

                        <!-- Email Field -->
                        <div class="mb-4">
                            <label for="email"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                            <input type="email" name="email" required
                                autocomplete="username"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Email" />
                        </div>
                        <!-- phone Field -->
                        <div class="mb-4">
                            <label for="phone"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Phone
                                Number</label>
                            <input name="phone" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Contact" />
                        </div>

                        <!-- Address Field -->
                        <div class="mb-4">
                            <label for="address"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Address</label>
                            <input name="address" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Address" />
                        </div>


                        <!-- Submit Button -->
                        <div class="flex items-center justify-center">
                            <button type="submit" class="btnCityGrayColor mt-3 w-full bg-black BtColor text-white px-4 py-2 rounded-lg">
                                Register Branch
                            </button>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>

</div>


<script>
    const chatLog = $("#chat-log");
    const userMessageInput = $("#user-message");
    const sendMessageButton = $("#send-message");
    const taskSelection = $("#task-selection");
    const taskList = $("#task-list");
    const confirmTasksButton = $("#confirm-tasks");
    const taskPricing = $("#task-pricing");
    const openpaymentType = $("#open-payment-type");
    const createClient = $("#create-client");
    const createAgent = $("#create-agent");
    const createBranch = $("#create-branch");
    const pricingFields = $("#pricing-fields");
    const clientFields = $("#client-fields");
    const agentFields = $("#agent-fields");
    const branchFields = $("#branch-fields");
    const clientOption = $("#client-options-modal");
        const passport = $("#passport");
        passport.hide();
        // let agents = [];
        // let clients = [];
        let clientsChat = @json($clients);
        let agentsChat = @json($agents);

    let selectedTasks = [];
    appendMessage("cityTour", "Welcome to City Tour. You can ask anything.");

    function appendMessage(role, content) {
            const messageClass = role === "user" ? "text-end" : "text-start";
            const message = `<div class="${messageClass}"><strong>${role}:</strong> ${content}</div>`;
            chatLog.append(message);
            chatLog.scrollTop(chatLog.prop("scrollHeight"));
        }



    // Send message on button click
    sendMessageButton.on("click", function() {
        const userMessage = userMessageInput.val().trim();
        if (!userMessage) return;

        appendMessage("user", userMessage);

        $.ajax({
            url: "{{ route('chat.process') }}",
            method: "POST",
            data: {
                messages: [{
                    role: "user",
                    content: userMessage
                }],
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), // Ensure CSRF token is included
            },
            success: function(response) {
                if (response.tasks) {
                    loadTaskSelection(response.tasks);
                } else if (response.taskPricing) {
                    loadTaskPricing(response.taskPricing);
                } else if (response.client) {
                    loadClient(response.client);
                } else if (response.agent) {
                    loadAgent(response.agent);
                } else if (response.branch) {
                    loadBranch(response.branch);
                } else {
                    if (response && response.choices && response.choices.length > 0) {
                        const botMessage = response.choices[0].message.content;
                        if (botMessage.includes('-') || botMessage.includes('•')) {
                            appendMessage('cityTour', formatList(botMessage));
                        } else {
                            appendMessage('cityTour', botMessage);
                        }
                    } else {
                        appendMessage('cityTour', "No response from chatbot. Please try again.");
                    }
                }
            },
            error: function(xhr) {
                appendMessage("cityTour", "Error: " + (xhr.responseJSON?.error || xhr.statusText));
            },
        });

        userMessageInput.val("");
    });

    // Send message on Enter key press
    userMessageInput.on("keypress", function(event) {
        if (event.key === "Enter") {
            event.preventDefault(); // Prevent default form submission behavior
            const userMessage = userMessageInput.val().trim();
            if (!userMessage) return;

            appendMessage("user", userMessage);

            $.ajax({
                url: "{{ route('chat.process') }}",
                method: "POST",
                data: {
                    messages: [{
                        role: "user",
                        content: userMessage
                    }],
                },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), // Ensure CSRF token is included
                },
                success: function(response) {
                    if (response.tasks) {
                        loadTaskSelection(response.tasks);
                    } else if (response.taskPricing) {
                        loadTaskPricing(response.taskPricing);
                    } else if (response.client) {
                        loadClient(response.client);
                    } else if (response.agent) {
                        loadAgent(response.agent);
                    } else if (response.branch) {
                        loadBranch(response.branch);
                    } else {
                        if (response && response.choices && response.choices.length > 0) {
                            const botMessage = response.choices[0].message.content;
                            if (botMessage.includes('-') || botMessage.includes('•')) {
                                appendMessage('cityTour', formatList(botMessage));
                            } else {
                                appendMessage('cityTour', botMessage);
                            }
                        } else {
                            appendMessage('cityTour', "No response from chatbot. Please try again.");
                        }
                    }
                },
                error: function(xhr) {
                    appendMessage("cityTour", "Error: " + (xhr.responseJSON?.error || xhr.statusText));
                },
            });

            userMessageInput.val("");
        }
    });


    function loadTaskSelection(tasks) {
        taskList.empty();
        taskSelection.show();
        tasks.forEach(task => {
            const listItem = `
                    <li class="list-group-item">
                        <input type="checkbox" class="form-check-input me-2" data-task-id="${task.id}">
                        ${task.description} (Client: ${task.client})
                    </li>`;
            taskList.append(listItem);
        });
    }

    function formatList(message) {
        // Handle both bullet points and dashed lists (you can add more formatting cases if necessary)
        let listItems = [];
        if (message.includes('-')) {
            listItems = message.split('-').map(item => `<li>${item.trim()}</li>`).filter(item => item.trim().length > 0);
        } else if (message.includes('•')) {
            listItems = message.split('•').map(item => `<li>${item.trim()}</li>`).filter(item => item.trim().length > 0);
        }
        return `<ul>${listItems.join('')}</ul>`;
    }

    confirmTasksButton.on("click", function() {
        selectedTasks = taskList.find("input[type='checkbox']:checked").map(function() {
            return parseInt($(this).data("task-id"));
        }).get();

        if (selectedTasks.length === 0) {
            alert("Please select at least one task.");
            return;
        }

        $.ajax({
            url: "{{ route('chat.select') }}",
            method: "POST",
            data: {
                tasks: selectedTasks // Send selected task IDs
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), // Ensure CSRF token is included
            },
            success: function(response) {
                loadTaskPricing(response.taskPricing);
            },
            error: function(xhr) {
                alert("Error: " + (xhr.responseJSON?.error || xhr.statusText));
            },
        });

        taskSelection.hide();
    });

    function loadTaskPricing(tasks) {
        pricingFields.empty();
        taskPricing.show();

        if (tasks.length === 0) {
            pricingFields.append('<p>No tasks available.</p>'); // Optional: Show a message if no tasks
            return;
        }

        tasks.forEach(task => {
            const field = `
                    <div class="mb-3">
                        <label class="form-label">${task.description} (Client: ${task.client} Price: ${task.taskprice})</label>
                        <input type="number" class="form-control" name="task-${task.id}" placeholder="Enter price" value="${task.invoice_price}">
                    </div>`;
            pricingFields.append(field);
        });

        selectedTasks = tasks;
    }

    $("#pricing-form").on("submit", function(event) {

        event.preventDefault();
        const tasks = selectedTasks.map(task => {
            const invprice = parseFloat($(`input[name='task-${task.id}']`).val());
            if (isNaN(invprice) || invprice <= 0) {
                alert(`Please enter a valid price for task ${task.id}`);
                return null; // Skip invalid tasks
            }
            return {
                id: task.id,
                invprice: invprice,
            };
        }).filter(task => task !== null); // Remove any null values (invalid tasks)

        if (tasks.length === 0) {
            alert("No valid tasks selected.");
            return;
        }

        // Log the data for debugging
        console.log("Submitting tasks:", JSON.stringify({
            tasks
        }));
        $.ajax({
            url: "{{ route('chat.create') }}",
            method: "POST",
            contentType: 'application/json',
            data: JSON.stringify({
                tasks
            }),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), // Ensure CSRF token is included
            },
            success: function(response) {
                if (response.success) {
                    const generatedLink = response.invoiceLink;
                    const invoiceNumber = response.invoiceNumber; // Assuming this is part of the response
                    const invoiceId = response.invoiceId; // Replace with actual response key for invoice ID
                    const invoiceAmount = response.invoiceAmount; // Replace with actual response key for amount
                    const due_date = response.due_date;
                    clientsChat = response.clients; // Assuming this is an array of clients

                    // Generate clickable link
                    const clickableLink = `<a href="${generatedLink}" target="_blank">Invoice generated! View it here</a>`;
                    appendMessage("cityTour", clickableLink);

                    document.getElementById('invoiceNumberChat').value = response.invoiceNumber;
                    document.getElementById('invoiceIdChat').value = response.invoiceId;
                    document.getElementById('invoiceAmountChat').value = response.invoiceAmount;
                    document.getElementById('subTotalChat').value = response.invoiceAmount;
                    document.getElementById('receiverIdChat').value = response.clientId;

                    // Serialize the clients array as a JSON string
                    document.getElementById('total-amountChat').value = response.invoiceAmount;
                    document.getElementById('due_dateChat').value = response.due_date;
                    document.getElementById('subT1Chat').textContent = `${response.invoiceAmount.toFixed(2)}`;
                    // Show payment type selection
                    showPaymentTypeSelection();
                }
            },
            error: function(xhr) {
                alert("Error: " + (xhr.responseJSON?.error || xhr.statusText));
            },
        });
        taskPricing.hide();
    });


    function showPaymentTypeSelection() {

        openpaymentType.show();
    }

    // Handle payment type selection change
    document.getElementById('payment-typeChat').addEventListener('change', function() {
        const paymentType = this.value;

        console.log(paymentType);
        if (paymentType === 'full') {
            document.getElementById('payment_gateway_section').classList.remove('hidden');
        } else if (paymentType === 'partial') {
            document.getElementById('paymentModal1').classList.remove('hidden');
        } else if (paymentType === 'split') {
            document.getElementById('paymentModal').classList.remove('hidden');
        }
    });



    function updateRows() {
        const splitInto = parseInt(document.getElementById('split-intoChat').value) || 0;
        const totalAmount = parseFloat(document.getElementById('total-amountChat').value) || 0;
        const perRowAmount = splitInto > 0 ? (totalAmount / splitInto).toFixed(2) : 0;

        const tbody = document.getElementById('split-rowsChat');
        tbody.innerHTML = ''; // Clear existing rows

        for (let i = 1; i <= splitInto; i++) {
            const row = document.createElement('tr');
            row.innerHTML = `
                                    <td class="border-b px-4 py-2">${i}</td>
                                    <td class="border-b px-4 py-2">
                                    <select  id="customer_name_${i}" name="customer_name_${i}" class="w-full p-2 border rounded-md account-selectChat" placeholder="Select Client">
                                        ${clientsChat.map(client => `<option value="${client.id}">${client.name}</option>`).join('')}
                                    </select>
                                    </td>
                                    <td class="border-b px-4 py-2">
                                        <input type="date" id="date_${i}" name="date_${i}" class="border-gray-300 rounded-md shadow-sm" />
                                    </td>
                                    <td class="border-b px-4 py-2">
                                        <input type="number" id="amount_${i}" name="amount_${i}" class="border-gray-300 rounded-md" value="${perRowAmount}" />
                                    </td>
                                    <td class="border-b px-4 py-2">
                                        <select id="payment_gateway2Chat" name="payment_gateway2Chat" class="border border-gray-300 p-2 rounded w-full">
                                            <option value="Tap">Tap</option>
                                            <option value="Hesabe">Hesabe</option>
                                            <option value="MyFatoorah">MyFatoorah</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2 border"></td>
                                `;
            tbody.appendChild(row);

            const selectElement = row.querySelector('.account-selectChat');
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
        const splitInto1 = parseInt(document.getElementById('split-into1Chat').value) || 0;
        const totalAmount1 = parseFloat(document.getElementById('total-amountChat').value) || 0;
        const perRowAmount1 = splitInto1 > 0 ? (totalAmount1 / splitInto1).toFixed(2) : 0;

        const tbody = document.getElementById('split-rows1Chat');
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


    function savePartial(mode) {

        if (mode === 'full') {
            console.log('savepartial');
            if (!validateFullPayment()) return;

            const gateway = document.getElementById('payment_gatewayChat').value;
            const date = document.getElementById('due_dateChat').value;
            const amount = parseFloat(document.getElementById('total-amountChat').value) || 0;
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
            const totalAmount = parseFloat(document.getElementById('total-amountChat').value) || 0;
            const splitInto = parseInt(document.getElementById('split-intoChat').value) || 0;
            const description = document.getElementById('split-descChat').value;
            const rows = document.querySelectorAll('#split-rowsChat tr');

            const splitData = [];
            rows.forEach(row => {
                const selectElement = row.querySelector('select');
                const clientId = selectElement.value;
                const date = row.querySelector('input[type="date"]').value;
                const gateway = row.querySelector('#payment_gateway2Chat').value || null;
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
            const totalAmount1 = parseFloat(document.getElementById('total-amountChat').value) || 0;
            const splitInto1 = parseInt(document.getElementById('split-into1Chat').value) || 0;
            const partialRows = document.querySelectorAll('#split-rows1Chat tr');
            const gateway = document.getElementById('payment_gateway1Chat').value;
            console.log(gateway);
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
        console.log('save');
        const invoiceUrl = "{{ route('invoice.partial') }}";
        const csrfToken = "{{ csrf_token() }}";
        const invoiceId = document.getElementById('invoiceIdChat').value;
        const invoiceNumber = document.getElementById('invoiceNumberChat').value;

        if (type === 'full') {
            const clientId = document.getElementById('receiverIdChat').value;

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
                afterPaymentType(type, invoiceNumber);
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
                afterPaymentType(type, invoiceNumber);
                //hideModal();
            }

        } else if (type === 'partial') {
            // Handle partial payment as before
            const clientId = document.getElementById('receiverIdChat').value;

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
                afterPaymentType(type, invoiceNumber);
                hideModal();
            }
        }
    }


    function afterPaymentType(type, invoiceNumber) {
        appendMessage("cityTour", `Payment type: <span style="color: #ff9800; font-weight: bold;">${type}</span>  has been saved for invoice number: ${invoiceNumber}`);
        openpaymentType.hide();
    }

    function validateFullPayment() {
        const gateway = document.getElementById('payment_gatewayChat').value;
        const date = document.getElementById('due_dateChat').value;
        const amount = parseFloat(document.getElementById('subTotalChat').value) || 0;

        if (!gateway || !date || amount <= 0) {
            displayErrorMessage("All fields are required and amount must be greater than 0 for full payment.");
            return false;
        }
        return true;
    }

    function updateLinkVisibility(invoiceNumber) {
        const rows = document.querySelectorAll("#split-rowsChat tr");
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


    function validateSplitPayment() {
        const rows = document.querySelectorAll('#split-rowsChat tr');
        const subTotal = parseFloat(document.getElementById('subTotalChat').value) || 0;
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
        const rows = document.querySelectorAll('#split-rows1Chat tr');
        const gateway = document.getElementById('payment_gateway1Chat').value;
        const subTotal = parseFloat(document.getElementById('subTotalChat').value) || 0;
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

    function hideModal() {
        document.querySelectorAll('.fixed').forEach(modal => modal.classList.add('hidden'));
    }

    document.getElementById('close-task-selection').addEventListener('click', function() {
        taskSelection.hide();
    });

    document.getElementById('close-task-pricing').addEventListener('click', function() {
        taskPricing.hide();
    });


    function loadClient() {
            clientOption.show();
            // Handle button clicks
            $('#upload-passport-btn').on('click', function () {
                // Open file upload dialog
                $('#passport-upload-input').click(); // Assuming a hidden file input exists
            });

            $('#fill-form-btn').on('click', function () {
                // Show the client creation form
                document.getElementById('clientForm').value ="new";
                createClient.show();
                clientOption.hide(); // Hide the options modal
            });

                // Show the client creation form
                // createClient.show();
        }

        $('#passport-upload-input').on('change', function (event) {
                const file = event.target.files[0];

                // Check if a file is selected
                if (file) {
                    // Create a preview of the uploaded image
                    const previewContainer = document.getElementById('file-preview-container'); // Ensure this container exists in your HTML
                    previewContainer.innerHTML = ''; // Clear previous previews

                    let img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.width = 100;
                    img.height = 100;
                    img.alt = "Uploaded File Preview";
                    img.className = "rounded shadow"; // Add TailwindCSS styles
                    previewContainer.appendChild(img);
                    passport.show();
                    // Create a FormData object to send the file via AJAX
                    const formData = new FormData();
                    formData.append('file', file);

                    // Perform AJAX request to upload the file
                    $.ajax({
                        url: "{{ route('chat.handleFileUpload') }}",
                        method: 'POST',
                        data: formData,
                        contentType: false, // Needed for FormData
                        processData: false, // Needed for FormData
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), // Add CSRF token for security
                        },
                        beforeSend: function () {
                            // Show a loader or disable the upload button during the request
                            $('#upload-status').text('Uploading...');
                        },
                        success: function (response) {
                            // Handle success response
                            if (response.success) {
                                $('#upload-status').text('Upload successful!');
                                console.log(response.message);
                                console.log(response.data);
                                // Optionally, update UI with extracted data (if returned)
                                if (response.data) {
                                        const client = response.data;

                                        // Display client details
                                        $('#passport-details').html(`
                                            <p>Passport Number: ${client.passport_no}</p>
                                            <p>Date of Birth: ${client.date_of_birth}</p>
                                            <p>Address: ${client.address || 'N/A'}</p>
                                            <p>Status: ${client.status}</p>
                                        `);


                                        // Populate other form fields
                                        document.getElementById('clientForm').value = "update";
                                        document.getElementById('clientId').value = client.id || '';
                                        document.getElementById('date_of_birthChat').value = client.date_of_birth || '';
                                        document.getElementById('nameChat').value = client.name || '';
                                        document.getElementById('addressChat').value = client.address || '';
                                        document.getElementById('civil_noChat').value = client.civil_no || '';
                                        document.getElementById('passport_noChat').value = client.passport_no || '';

                                        // Hide the passport modal and show the client form
                                        passport.hide();
                                        clientOption.hide();
                                        createClient.show();
                                    }

                            } else {
                                $('#upload-status').text('Upload failed: ' + response.message);
                            }
                        },
                        error: function (xhr) {
                            // Handle error response
                            $('#upload-status').text('Error uploading file. Please try again.');
                            console.error(xhr.responseText);
                        },
                        complete: function () {
                            // Remove loader or re-enable the upload button
                        }
                    });
                } else {
                    // Handle case where no file is selected
                    $('#upload-status').text('No file selected.');
                }
            });




        // Handle form submission
        $('#client-form').on('submit', function (event) {
            event.preventDefault();
            // Collect form data
            const formData = $(this).serialize();

            // Submit the form via AJAX
            $.ajax({
                url: "{{ route('chat.client') }}",
                method: "POST",
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                success: function (response) {
                    if (response.success) {
                    const client = response.client;
                    const action = response.action;

                                // Determine the message based on the action
                    const actionMessage = action === 'create' 
                        ? `New client created: <span style="color: #ff9800; font-weight: bold;">${client.name}</span>` 
                        : `Client updated: <span style="color: #4caf50; font-weight: bold;">${client.name}</span>`;

                    // Append the message
                    appendMessage("cityTour", actionMessage);
                    createClient.hide();

                    }
                },
                error: function (xhr) {
                    alert("Error: " + (xhr.responseJSON?.message || "Unable to register client."));
                },
            });
        });


    function loadAgent(branches) {
        console.log(branches);
        agentFields.empty();
        createAgent.show();
        // Define the HTML for the "Add New Client" form

        const branchOptions = branches.map(branch => `<option value="${branch.id}">${branch.name}</option>`).join('');

        const addAgentForm = `
                                 
                               <!-- Branches Dropdown -->
                                    <div class="mb-4">
                                        <label for="branch_id" class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Branch</label>
                                        <select id="branch_id" name="branch_id" required
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                            <option value="" disabled selected>Select Branch</option>
                                            ${branchOptions}
                                        </select>
                                    </div>
                `;

        // Append the form to the clientFields container
        agentFields.append(addAgentForm);
    }


    // Handle form submission
    $('#agent-form').on('submit', function(event) {
        event.preventDefault();

        // Collect form data
        const formData = $(this).serialize();
        console.log('data:', formData);
        // Submit the form via AJAX
        $.ajax({
            url: "{{ route('chat.agent') }}",
            method: "POST",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            success: function(response) {
                if (response.success) {
                    const agent = response.agent;
                    appendMessage("cityTour", `New agent created: <span style="color: #ff9800; font-weight: bold;">${agent.name}</span>`);
                    createAgent.hide();

                }
            },
            error: function(xhr) {
                alert("Error: " + (xhr.responseJSON?.message || "Unable to register agent."));
            },
        });
    });



    function loadBranch(tasks) {
        createBranch.show();
    }


    // Handle form submission
    $('#branch-form').on('submit', function(event) {
        event.preventDefault();

        // Collect form data
        const formData = $(this).serialize();

        // Submit the form via AJAX
        $.ajax({
            url: "{{ route('chat.branch') }}",
            method: "POST",
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            success: function(response) {
                if (response.success) {
                    const branch = response.branch;
                    appendMessage("cityTour", `New branch created: <span style="color: #ff9800; font-weight: bold;">${branch.name}</span>`);
                    createBranch.hide();

                }
            },
            error: function(xhr) {
                alert("Error: " + (xhr.responseJSON?.message || "Unable to register branch."));
            },
        });
    });

         const agentDropdown = new TomSelect("#agent_idChat", {
                    placeholder: "Select Agent",  // Placeholder text
                    allowEmptyOption: true,      // Allows the first empty option
                    create: false,               // Prevent creating new options
                    searchField: ["text"],       // Enable searching by option text
                    maxItems: 1,                 // Limit to single select
                });
      

    document.getElementById('close-option-client').addEventListener('click', function() {
        clientOption.hide();
    });

    document.getElementById('close-create-client').addEventListener('click', function() {
        createClient.hide();
    });

    document.getElementById('close-create-agent').addEventListener('click', function() {
        createAgent.hide();
    });

    document.getElementById('close-create-branch').addEventListener('click', function() {
        createBranch.hide();
    });
</script>