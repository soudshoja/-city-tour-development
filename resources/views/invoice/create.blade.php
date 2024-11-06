<x-app-layout>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <div x-data="invoiceModal()">
        <div x-data="invoiceAdd">
            <div class="flex flex-col gap-2.5 xl:flex-row">
                <div class="panel flex-1 px-0 py-6 lg:mr-6 ">
                    <div class="flex flex-wrap justify-between px-4">
                        <div class="mb-6 w-full lg:w-1/2">
                            <div class="flex shrink-0 items-center text-black dark:text-white">
                                <x-application-logo class="w-14" />
                                @if($company)
                                <h3 class="pl-2">{{ $company->name }}</h3>
                                @else
                                <p>No company assigned</p>
                                @endif
                            </div>
                            <div class="mt-6 space-y-1 text-gray-500 dark:text-gray-400">
                                <div>{{ $company->address }}</div>
                                <div>{{ $company->email }}</div>
                                <div>{{ $company->phone }}</div>
                            </div>
                        </div>
                        <div class="w-full lg:w-1/2 lg:max-w-fit">
                            <div class="flex items-center">
                                <label for="invoiceNumber" class="mb-0 flex-1 mr-2 ">Invoice Number</label>
                                <input type="text" name="invoiceNumber" class="form-input w-2/3 lg:w-[250px]"
                                    placeholder="#8801" x-model="params.invoiceNumber" value="{{$invoiceNumber}}" />
                            </div>
                            <div class="mt-4 flex items-center">
                                <label for="invoiceLabel" class="mb-0 flex-1 mr-2 ">Invoice Label</label>
                                <input id="invoiceLabel" type="text" name="inv-label"
                                    class="form-input w-2/3 lg:w-[250px]" placeholder="Enter Invoice Label"
                                    x-model="params.label" />
                            </div>
                            <div class="mt-4 flex items-center">
                                <label for="startDate" class="mb-0 flex-1 mr-2 ">Invoice Date</label>
                                <input id="startDate" type="date" name="inv-date" class="form-input w-2/3 lg:w-[250px]"
                                    x-model="params.invoiceDate" />
                            </div>
                            <div class="mt-4 flex items-center">
                                <label for="dueDate" class="mb-0 flex-1 mr-2 ">Due Date</label>
                                <input id="dueDate" type="date" name="due-date" class="form-input w-2/3 lg:w-[250px]"
                                    x-model="params.dueDate" />
                            </div>
                        </div>
                    </div>
                    <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />
                    <div class="mt-8 px-4">
                        <div class="flex flex-col justify-between lg:flex-row">
                            <div class="mb-6 w-full lg:w-1/2 lg:mr-6 ">
                                <div class="flex items-center justify-between">
                                    <div class="text-lg font-semibold">Bill To</div>
                                    <button @click="openClientModal()"
                                        class="p-2 bg-blue-500 text-white rounded-lg hover:bg-yellow-600 transition-all duration-300 ease-in-out">
                                        <i class="fas fa-user-plus"></i> Select Client
                                    </button>
                                </div>

                                <div class="mt-5 flex items-center justify-between">
                                    <div class="text-lg font-semibold">Add Item</div>
                                    <button @click="openTaskModal()"
                                        class="ml-4 p-2 bg-blue-500 text-white rounded-lg hover:bg-yellow-600 transition-all duration-300 ease-in-out">
                                        <i class="fas fa-tasks"></i> Add Item
                                    </button>
                                </div>

                            </div>

                            <div class="sm:w-2/5">
                                <div class="flex items-center justify-between">
                                    <div>Subtotal</div>
                                    <span x-text="subtotal">$0.00</span>
                                </div>
                                <div class="mt-4 flex items-center justify-between">
                                    <div>Tax(%)</div>
                                    <div x-text="params.tax + '%'">0%</div>
                                </div>
                                <div class="mt-4 flex items-center justify-between">
                                    <div>Shipping Rate($)</div>
                                    <div x-text="params.shippingCharge">$0.00</div>
                                </div>
                                <div class="mt-4 flex items-center justify-between">
                                    <div>Discount(%)</div>
                                    <div x-text="params.discount + '%'">0%</div>
                                </div>
                                <div class="mt-4 flex items-center justify-between font-semibold">
                                    <div>Total</div>
                                    <span x-text="total">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8">
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="w-1">Quantity</th>
                                        <th class="w-1">Price</th>
                                        <th>Total</th>
                                        <th class="w-1"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-if="items.length <= 0">
                                        <tr>
                                            <td colspan="5" class="!text-center font-semibold">No Item Available</td>
                                        </tr>
                                    </template>
                                    <template x-for="(item, i) in items" :key="i">
                                        <tr class="border-b border-[#e0e6ed] align-top dark:border-[#1b2e4b]">
                                            <td>
                                                <input type="text" class="form-input min-w-[200px]"
                                                    placeholder="Enter Item Name" x-model="item.description" />
                                                <textarea class="form-textarea mt-4" placeholder="Enter Description"
                                                    id="item-name" x-model="item.remark"></textarea>
                                            </td>
                                            <td><input type="number" class="form-input w-32" placeholder="Quantity"
                                                    x-model="item.quantity" /></td>
                                            <td>
                                                <input type="text" class="form-input w-32" placeholder="Price"
                                                    x-model.number="item.total" @input="updateItemTotal(item)" />
                                            </td>
                                            <td x-text="`$${(item.total * item.quantity).toFixed(2)}`"></td>
                                            <td>
                                                <button type="button" @click="removeItem(item)">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round" class="h-5 w-5">
                                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-6 flex flex-col justify-between px-4 sm:flex-row">
                            <div class="mb-6 sm:mb-0">


                            </div>

                        </div>
                    </div>
                    <div class="mt-8 px-4">
                        <div>
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="form-textarea min-h-[130px]"
                                placeholder="Notes...." x-model="params.notes"></textarea>
                        </div>
                    </div>
                </div>
                <div class="mt-6 w-full xl:mt-0 xl:w-96">
                    <div class="panel mb-5">
                        <div>
                            <label for="currency">Currency</label>
                            <select id="currency" name="currency" class="form-select" x-model="selectedCurrency">
                                <template x-for="(currency, i) in currencyList" :key="i">
                                    <option :value="currency" x-text="params.currency"></option>
                                </template>
                            </select>
                        </div>
                        <div class="mt-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="tax">Tax(%) </label>
                                    <input id="tax" type="number" name="tax" class="form-input" placeholder="Tax"
                                        @input="updateSubTotal()" x-model="params.tax" />
                                </div>
                                <div>
                                    <label for="discount">Discount(%) </label>
                                    <input id="discount" type="number" name="discount" class="form-input"
                                        @input="updateSubTotal()" placeholder="Discount" x-model="params.discount" />
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div>
                                <label for="shipping-charge">Shipping Charge($) </label>
                                <input id="shipping-charge" type="number" name="shipping-charge" class="form-input"
                                    @input="updateSubTotal()" placeholder="Shipping Charge"
                                    x-model="params.shippingCharge" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <label for="payment-method">Accept Payment Via</label>
                            <select id="payment-method" name="payment-method" class="form-select"
                                x-model="params.paymentMethod">
                                <option value="">Select Payment</option>
                                <option value="bank">Bank Account</option>
                                <option value="paypal">Paypal</option>
                                <option value="upi">UPI Transfer</option>
                            </select>
                        </div>
                    </div>
                    <div class="panel">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-1">
                            <!-- Invoice Link Display -->
                            <div x-show="isSaved" class="mt-4">
                                <label>Invoice Link:</label>
                                <a :href="invoiceLink" class="text-blue-600 underline" target="_blank"
                                    x-text="invoiceLink"></a>
                            </div>

                            <button @click="generateInvoice()" type="button" :disabled="isSaving"
                                class="btn btn-success w-full gap-2" id="generate-invoice-btn">
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
                                <span x-show="!isSaving && !isSaved" id="button-text">Save</span>
                                <span x-show="isSaving" id="button-loading">Saving...</span>
                                <span x-show="isSaved" id="button-saved">Saved</span>
                            </button>

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

                            <a href="apps-invoice-preview.html" class="btn btn-primary w-full gap-2">
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
                    <!-- Clients Modal -->
                    <div x-show="isClientModalOpen"
                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75"
                        style="display: none;">
                        <div class="bg-white p-4 rounded-lg shadow-lg w-3/4 md:w-1/2">
                            <p class="text-yellow-500 font-bold mb-4">Choose Client</p>

                            <!-- Search Box -->
                            <input type="text" placeholder="Search Client..." x-model="searchClient"
                                class="w-full p-2 mb-4 border border-gray-300 rounded-md text-gray-900 focus:outline-none focus:ring focus:ring-yellow-500" />

                            <!-- List of Clients -->
                            <ul class="max-h-60 overflow-y-auto">
                                <template x-for="client in filteredClients" :key="client.id">
                                    <li @click="selectClient(client)"
                                        class="cursor-pointer p-2 hover:bg-gray-100 text-gray-800">
                                        <span x-text="client.name"></span> - <span x-text="client.email"></span>
                                    </li>
                                </template>
                            </ul>

                            <!-- Close Modal Button -->
                            <div class="text-right mt-4">
                                <button @click="closeClientModal()"
                                    class="bg-blue-500 text-white px-4 py-2 rounded-lg">Close</button>
                            </div>
                        </div>
                    </div>


                    <!-- Tasks Modal -->
                    <div x-show="isTaskModalOpen"
                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75"
                        style="display: none;">
                        <div class="bg-white p-4 rounded-lg shadow-lg w-3/4 md:w-1/2">
                            <p class="text-yellow-500 font-bold mb-4">Choose Task</p>

                            <!-- Search Box -->
                            <input type="text" placeholder="Search Task..." x-model="searchTask"
                                class="w-full p-2 mb-4 border border-gray-300 rounded-md text-gray-900 focus:outline-none focus:ring focus:ring-yellow-500" />

                            <!-- List of Tasks -->
                            <ul class="max-h-60 overflow-y-auto">
                                <template x-for="task in filteredTasks" :key="task.id">
                                    <li @click="selectTask(task)"
                                        class="cursor-pointer p-2 hover:bg-gray-100 text-gray-800">
                                        <span x-text="task.reference"></span>-
                                        <span x-text="task.type"></span>
                                        <span x-text="task.additional_info"></span>
                                        ( <span x-text="task.venue"></span>)
                                    </li>
                                </template>
                            </ul>

                            <!-- Close Modal Button -->
                            <div class="text-right mt-4">
                                <button @click="closeTaskModal()"
                                    class="bg-blue-500 text-white px-4 py-2 rounded-lg">Close</button>
                            </div>
                        </div>
                    </div>



                </div>
            </div>
        </div>
        <!-- end main content section -->
    </div>

    <script>
    function invoiceModal() {

        return {
            isClientModalOpen: false,
            isTaskModalOpen: false,
            searchClient: '',
            searchTask: '',
            clients: @json($clients),
            tasks: @json($tasks),
            suppliers: @json($suppliers),
            selectedClient: null,
            selectedClientId: null,
            receiverName: null,
            receiverName: null,
            receiverEmail: null,
            receiverAddress: null,
            receiverPhone: null,
            selectedTaskName: null,
            selectedTask: null,
            taskRemark: '',
            taskPrice: 0,
            subtotal: 0,
            total: 0,
            tasksNew: [],
            currency: 'USD',
            items: [],
            invoiceNumber: @json($invoiceNumber),
            selectedCurrency: 'USD',
            isSaving: false,
            isSaved: false,
            invoiceLink: '',
            params: {
                label: '',
                invoiceDate: '',
                dueDate: '',
                accNo: '',
                bankName: '',
                swiftCode: '',
                ibanNo: '',
                country: '',
                currency: '',
                tax: 0,
                discount: 0,
                shippingCharge: 0,
                paymentMethod: '',
                invoiceNumber: @json($invoiceNumber),
            },

            openClientModal() {
                this.isClientModalOpen = true;
            },

            closeClientModal() {
                this.isClientModalOpen = false;
            },

            selectClient(client) {
                this.selectedClient = client;
                this.selectedClientId = client.id ?? '';
                this.receiverName = client.name ?? '';
                this.receiverAddress = client.address ?? '';
                this.receiverPhone = client.phone ?? '';
                this.receiverEmail = client.email ?? '';
                document.getElementById('receiverName').value = client.name ?? '';
                document.getElementById('receiverEmail').value = client.email ?? '';
                const addressField = document.getElementById('receiverAddress');
                if (addressField) {
                    addressField.value = client.address ? client.address : '';
                }

                const phoneField = document.getElementById('receiverPhone');
                if (phoneField) {
                    phoneField.value = client.phone ? client.phone : '';
                }
                this.closeClientModal();
            },

            openTaskModal() {
                this.isTaskModalOpen = true;
            },

            closeTaskModal() {
                this.isTaskModalOpen = false;
            },

            selectTask(task) {
                this.selectedTask = task;
                const taskExists = this.items.some(item => item.id === task.id);

                if (!taskExists) {
                    this.items.push({
                        ...task,
                        remark: '',
                        quantity: 1,
                        price: task.total || 0,
                        description: `${task.reference} - ${task.type} ${task.additional_info} (${task.venue})`
                    });
                }
                this.selectedTaskName = task.reference + '-' + task.type + task.additional_info + '(' + task.venue +
                    ')';
                this.updateTotal(this.items);
                //  document.getElementById('item-name').value =  task.reference + '-' +  task.type +  task.additional_info +'('+task.venue+')';
                this.closeTaskModal();
            },

            updateItemTotal(item) {
                // Update total if necessary
                item.total = parseFloat(item.total) || 0; // Ensure total is a valid number
                item.quantity = parseFloat(item.quantity) || 1; // Ensure quantity is at least 1
                // Update any other logic or overall total here if needed
                this.updateTotal(this.items); // Update the overall total
            },

            updateSubTotal() {
                const taxAmount = this.subtotal * (this.params.tax / 100);
                const discountAmount = this.subtotal * (this.params.discount / 100);

                // Calculate total
                this.total = this.subtotal + taxAmount + this.params.shippingCharge - discountAmount;

            },

            updateTotal(items) {
                const total = items.reduce((sum, item) => sum + (item.total * item.quantity),
                    0); // Calculate total based on price and quantity
                this.subtotal = total;
                this.updateSubTotal();
            },
            // Method to add task
            addTask() {
                if (this.taskRemark && this.taskPrice !== null) {
                    const newTask = {
                        clientName: this.selectedClientName,
                        taskId: this.selectedTask.id,
                        taskName: this.selectedTaskName,
                        remark: this.taskRemark,
                        price: this.taskPrice
                    };

                    this.tasksNew.push(newTask);
                    this.total += parseFloat(this.taskPrice);
                    this.clearInputs();
                } else {
                    alert('Please fill in all fields');
                }
            },

            // Clear input fields
            clearInputs() {
                this.taskRemark = '';
                this.taskPrice = 0;
            },

            // Method to generate invoice
            async generateInvoice() {
                if (this.isSaving) return;

                // Indicate saving process
                this.isSaving = true;
                this.isSaved = false;
                this.invoiceLink = null;

                // Extract necessary values
                const invoiceUrl = "{{ route('invoice.store') }}";
                const csrfToken = "{{ csrf_token() }}";
                const currency = this.selectedCurrency;
                const params = this.params;
                const total = this.total;
                const subtotal = this.subtotal;
                const tasks = this.items;
                const clientId = this.selectedClientId;

                // Basic validation
                if (!clientId || !subtotal || !total || !tasks.length) {
                    console.error("Required data is missing.");
                    this.isSaving = false;
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
                            subtotal,
                            total,
                            tasks,
                            params,
                            currency
                        })
                    });

                    if (!response.ok) {
                        throw new Error("Failed to reach the invoice controller.");
                    }

                    const result = await response.json();

                    // Generate invoice link after success
                    this.invoiceLink = `http://127.0.0.1:8000/invoice/` + this.invoiceNumber;
                    this.isSaved = true;
                } catch (error) {
                    console.error("Error generating invoice:", error);
                    this.isSaved = false;
                } finally {
                    // Reset saving state after delay
                    setTimeout(() => {
                        this.isSaving = false;
                    }, 1000);
                }
            },

            get filteredClients() {
                return this.clients.filter(client =>
                    client.name.toLowerCase().includes(this.searchClient.toLowerCase())
                );
            },

            get filteredTasks() {
                return this.tasks
                    .filter(task => task.client_id === this.selectedClientId) // Filter by selected client ID
                    .filter(task => task.additional_info.toLowerCase().includes(this.searchTask.toLowerCase()));
            },
        }
    };
    </script>


    <script>
    const modal = document.getElementById("modal");
    const openModalBtn = document.getElementById("openModalBtn");
    const closeModalBtn = document.getElementById("closeModalBtn");

    openModalBtn.addEventListener("click", () => {
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    });

    closeModalBtn.addEventListener("click", () => {
        modal.classList.add("hidden");
    });

    function toggleClientFields() {
        var clientSelect = document.getElementById('client-select');
        var newClientFields = document.getElementById('new-client-fields');
        if (clientSelect.value === 'new') {
            newClientFields.style.display = 'block';
        } else {
            newClientFields.style.display = 'none';
        }
    }
    </script>
    <script>
    let tasks = [];

    document.getElementById('add-task-btn').addEventListener('click', function() {
        const selectedTaskId = document.querySelector('input[type="checkbox"]:checked').value;
        const remark = document.getElementById('remark').value;
        const price = parseFloat(document.getElementById('price').value);

        tasks.push({
            task_id: selectedTaskId,
            remark: remark,
            price: price
        });

        updateTaskList();
        updateTotal();
    });

    function updateTaskList() {
        const taskListElement = document.getElementById('tasks');
        taskListElement.innerHTML = '';

        tasks.forEach(task => {
            const taskElement = document.createElement('li');
            taskElement.className = 'list-group-item bg-dark text-light';
            taskElement.innerText = `Task ${task.task_id}: ${task.remark} - $${task.price}`;
            taskListElement.appendChild(taskElement);
        });
    }
    </script>

</x-app-layout>