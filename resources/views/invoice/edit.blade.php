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

            input[type="number"].no-spin::-webkit-outer-spin-button,
            input[type="number"].no-spin::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            input[type="number"].no-spin {
                -moz-appearance: textfield;
                appearance: textfield;
            }
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
                            @if ($company)
                            <h3>{{ $company->name }}</h3>
                            <p>{!! nl2br(e($company->address)) !!}</p>
                            <p>{{ $company->email }}</p>
                            <p>{{ $company->phone }}</p>
                            @else
                            <p>No company assigned</p>
                            @endif
                        </div>

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

                    </div>
                    <!-- invoice details -->
                    <div class="space-y-1 text-gray-500 dark:text-gray-400">

                        <div class="flex items-center">
                            <label class="w-full text-sm font-semibold">Invoice Number:</label>
                            <input id="invoiceNumber" type="text" name="invoiceNumber" value="{{ $invoiceNumber }}"
                                class="w-full form-input" placeholder="Invoice Number" />
                        </div>

                        <form id="invoice-date-form" method="POST" action="{{ route('invoice.updateDate', $invoice->invoice_number) }}">
                            @csrf
                            @method('PUT')
                            <div class="flex items-center">
                                <label class="w-full text-sm font-semibold">Invoice Date:</label>
                                <!-- Save icon button -->
                                <button type="submit" class=" rounded hover:bg-gray-200 dark:hover:bg-gray-700" title="Save">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="w-5 h-5 text-blue-600">
                                        <path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z" />
                                    </svg>
                                </button>
                                <input id="invdate" type="date" name="invdate" class="form-input w-full"
                                    value="{{ $invoice->invoice_date }}" />


                            </div>
                        </form>


                        <div class="mt-4 flex items-center">
                            <label class="w-full text-sm font-semibold">Due Date:</label>
                            <input id="duedate" type="date" name="duedate" class="w-full form-input"
                                value={{ $dueDate }} disabled />
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
                        <!-- client name -->
                        <div class="mt-4 flex items-center">
                            <input id="receiverName" type="text" name="receiverName" class="form-input flex-1"
                                placeholder="Client Name" disabled />
                            <input type="hidden" id="receiverName" name="receiverName" value="{{ request()->input('client_id') }}">
                        </div>

                        <!-- client email -->
                        <div class="mt-4 flex items-center">
                            <input id="receiverEmail" type="email" name="receiverEmail" class="form-input flex-1"
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
                        <p class="my-2 text-gray-400 text-center text-xs">details will displaying below after choosing
                            an Agent</p>

                        <!-- agent name -->
                        <div class="mt-4 flex items-center">
                            <input id="agentName" type="text" name="agentName" class="form-input flex-1"
                                placeholder="Agent Name"
                                value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->name : '' }}"
                                disabled />
                            <input type="hidden" id="agentName" name="agentName" value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->id : '' }}">
                        </div>

                        <!-- agent email -->
                        <div class="mt-4 flex items-center">
                            <input id="agentEmail" type="email" name="agentEmail" class="form-input flex-1"
                                placeholder="Agent Email"
                                value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->email : '' }}"
                                disabled />
                        </div>

                        <!-- agent phone -->
                        <div class="mt-4 flex items-center">
                            <input id="agentPhone" type="text" name="agentPhone" class="form-input flex-1"
                                placeholder="Agent Phone"
                                value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->phone : '' }}"
                                disabled />
                        </div>



                    </div>
                    <!-- ./Agent details -->
                </div>
                <!-- users details -->


                <!-- choose items -->
                <div class="mt-8">
                    <!-- choose items -->
                    <div class="overflow-x-auto w-full border border-gray-200">
                        <table id="itemsTable" class="text-left table-auto border-collapse w-full text-xs">
                            <thead>
                                <tr>
                                    <th class="text-gray-900 dark:text-gray-100">No.</th>
                                    <th class="px-4 py-2 min-w-[100px] text-gray-900 dark:text-gray-100">Task</th>
                                    <th class="px-6 py-6 text-gray-900 dark:text-gray-100">Task Price</th>
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
                                <!-- Items will be added dynamically here -->
                                <!-- "No Item Available" row will show if items.length <= 0 -->
                            </tbody>
                        </table>
                    </div>
                    <!-- ./choose items -->

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
                            <div class="mt-4 font-semibold">
                                <div class="flex items-center mb-1">
                                    <div class="mr-2">Subtotal:</div>
                                    <span id="subTotalDisplay">0.00</span>
                                </div>
                                <div id="invoice_charge_display_row" class="flex items-center mb-1" style="display: none;">
                                    <div id="invoice_charge_label" class="mr-2">Invoice Charge:</div>
                                    <span id="invoiceChargeDisplay">0.00</span>
                                </div>
                                <div class="flex items-center border-t pt-1">
                                    <div class="mr-2">Total:</div>
                                    <span id="subT">0.00</span>
                                    <input id="subTotal" type="hidden" name="subTotal" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


            </div>
            <div class="mt-6 w-full xl:mt-0 xl:w-96">
                <div class="panel mb-5">
                    <h2 class="text-lg font-semibold mb-3 text-gray-700">Currency</h2>
                    <select id="currency" name="currency" class="form-select">
                        <!-- You can add your options here -->
                        <option value="KWD">KWD</option>
                        <option value="MYR">MYR</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>

                    <!-- Invoice Payment Settings Section -->
                    <div class="mt-4">
                        <h2 class="text-lg font-semibold mb-3 text-gray-700">Invoice Settings</h2>

                        <!-- Payment Type Section -->
                        <div id="paymentMethod" class="mt-4">
                            <h2 class="text-lg font-semibold mb-3 text-gray-700">
                                <span> Payment Type</span>

                                @if ($invoice->payment_type)
                                : <span class="font-large text-success">{{ ucfirst($invoice->payment_type) }}</span>
                                @endif

                            </h2>
                            <input type="hidden" id="paymentTypeSaved" name="payment_type_saved"
                                value="{{ $invoice->payment_type }}">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-1">
                                <div x-data="{ clientCreditModal: false, generateInvoiceWithCreditModal: false }">
                                    @php
                                    $balanceCredit = \App\Models\Credit::getTotalCreditsByClient($selectedClient->id);
                                    @endphp
                                    @if ($invoice->amount <= $balanceCredit)
                                        <button type="button" @click="clientCreditModal = true"
                                        class="rounded-full flex flex-col items-center justify-center w-full
                                        px-4 py-2 border border-gray-300 
                                        bg-white text-gray-700 transition gap-2 
                                        hover:bg-green-500 hover:text-white hover:shadow-xl"
                                        {{ $invoice->amount > $balanceCredit ? 'disabled' : '' }}>
                                        <span class="font-medium">{{ $selectedClient->first_name }}:
                                            KWD {{ $balanceCredit }}</span>
                                        @if ($invoice->amount > $balanceCredit)
                                        <span class="text-red-500">Credit Limit Exceeded</span>
                                        @endif
                                        </button>
                                        <div x-cloak x-show="clientCreditModal"
                                            class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50">
                                            <div class="bg-white rounded-lg p-6 shadow-lg">
                                                <h2 class="text-lg font-semibold mb-3 text-gray-700">Are you sure you want
                                                    to proceed with this payment?</h2>
                                                <p class="text-gray-600">The client has a credit limit of
                                                    {{ $balanceCredit }} KWD.
                                                </p>
                                                <p>
                                                    <span>After payment: {{ $balanceCredit }} - {{ $invoice->amount }} =
                                                        {{ $balanceCredit - $invoice->amount }} KWD</span>
                                                </p>
                                                <div class="mt-4 flex justify-end">
                                                    <button @click="savePartial('credit')"
                                                        class="mr-2 px-4 py-2 bg-blue-500 text-white rounded">Proceed</button>
                                                    <button @click="clientCreditModal = false"
                                                        class="mr-2 px-4 py-2 bg-gray-300 text-gray-700 rounded">Cancel</button>
                                                </div>
                                            </div>

                                        </div>
                                        @else
                                        @if ($creditUsed && $creditUsed->amount < 0)
                                            <a target="_blank"
                                            href="{{ url('/invoice/' . $invoice->invoice_number) }}"><button
                                                type="button"
                                                class="rounded-full flex flex-col items-center justify-center w-full
                                            px-4 py-2 border border-gray-300 
                                            bg-green-500 text-white shadow-xl">
                                                <span>Credit {{ number_format(abs($creditUsed->amount), 2) ?? 0 }}
                                                    KWD
                                                    has
                                                    been utilized.
                                                </span>
                                                <span>Current balance of credit for {{ $selectedClient->first_name }}:
                                                    {{ $balanceCredit }} KWD</span>
                                            </button></a>
                                            @else
                                            @if ($balanceCredit > 0 && $invoice->payment_type == '')
                                            <button type="button" @click="generateInvoiceWithCreditModal = true"
                                                class="rounded-full flex flex-col items-center justify-center w-full
                                            px-4 py-2 border border-gray-300 
                                            bg-white text-gray-700 transition gap-2 
                                            hover:bg-green-500 hover:text-white hover:shadow-xl">
                                                <span> Still Paying With Client Credit?</span>
                                                <span> Current balance of credit for {{ $selectedClient->first_name }}:
                                                    {{ $balanceCredit }} KWD</span>
                                            </button>
                                            @else
                                            <button type="button"
                                                class="rounded-full flex flex-col items-center justify-center w-full
                                            px-4 py-2 border border-gray-300 
                                            bg-white text-gray-700 transition gap-2 shadow">

                                                <span>Current balance of credit for {{ $selectedClient->first_name }}:
                                                    {{ $balanceCredit }} KWD</span>
                                            </button>
                                            @endif
                                            @endif
                                            <div x-cloak x-show="generateInvoiceWithCreditModal"
                                                class="fixed inset-0 flex items-center justify-center z-50 bg-gray-800 bg-opacity-75 transition-opacity">
                                                <div class="min-h-40 min-w-40 p-7 bg-white rounded shadow"
                                                    @click.away="generateInvoiceWithCreditModal = false">
                                                    <header class="text-lg font-semibold mb-4">
                                                        Would you
                                                        like to pay the invoice by using the {{ $selectedClient->first_name }}'s
                                                        credit balance
                                                        ({{ $balanceCredit }} KWD)?
                                                    </header>

                                                    <main>
                                                        <form action="{{ route('invoice.client-credit') }}" method="POST"
                                                            x-effect="if (option !== 'generate_yes' && option !== 'use_credit') selectedGateway = ''"
                                                            x-data="{
                                                            option: '',
                                                            selectedGateway: '',
                                                            init() {
                                                                this.$nextTick(() => {
                                                                    this.selectedGateway = this.$refs.gateway?.value || '';
                                                                });
                                                            }
                                                        }"
                                                            x-init="init()" x-cloak>
                                                            @csrf

                                                            <div class="space-y-4 mb-6">
                                                                <!-- Option 1 -->
                                                                {{-- <label
                                                            class="flex items-center p-3 border border-gray-300 rounded hover:bg-gray-100 transition"
                                                            :class="{ 'bg-gray-100': option === 'generate_yes' }">
                                                            <input type="radio" name="selected_option"
                                                                value="generate_yes"
                                                                class="form-radio h-5 w-5 text-blue-600 mr-3"
                                                                x-model="option">
                                                            <span class="text-gray-800 text-base">[Yes] Generate new
                                                                invoice to topup the remaining balance.</span>
                                                        </label> --}}

                                                                <!-- Option 2 -->
                                                                <label
                                                                    class="flex items-center p-3 border border-gray-300 rounded hover:bg-gray-100 transition"
                                                                    :class="{ 'bg-gray-100': option === 'generate_no' }">
                                                                    <input type="radio" name="selected_option"
                                                                        value="generate_no"
                                                                        class="form-radio h-5 w-5 text-blue-600 mr-3"
                                                                        x-model="option">
                                                                    <span class="text-gray-800 text-base">[Yes] Set the
                                                                        same
                                                                        invoice as paid in advance.</span>
                                                                </label>

                                                                <!-- Option 3 -->
                                                                <label
                                                                    class="flex flex-col p-3 border border-gray-300 rounded hover:bg-gray-100 transition"
                                                                    :class="{ 'bg-gray-100': option === 'use_credit' }">
                                                                    <div class="flex items-center">
                                                                        <input type="radio" name="selected_option"
                                                                            value="use_credit"
                                                                            class="form-radio h-5 w-5 text-green-600 mr-3"
                                                                            x-model="option">
                                                                        <span class="text-gray-800 text-base">
                                                                            [Yes] Pay the
                                                                            remaining balance
                                                                            ({{ number_format($balanceCredit - $invoice->amount, 2) }}
                                                                            KWD)
                                                                            in the same invoice.
                                                                        </span>
                                                                    </div>
                                                                </label>

                                                            </div>

                                                            <input type="hidden" name="invoice_id"
                                                                value="{{ $invoice->id }}">
                                                            <input type="hidden" name="payment_gateway" :value="selectedGateway">

                                                            <div x-show="option === 'generate_yes' || option === 'use_credit'" x-cloak class="mb-4">
                                                                <label for="payment_gateway"
                                                                    class="block mb-1 text-sm text-gray-700">
                                                                    Payment Gateway
                                                                </label>
                                                                <select id="payment_gateway" name="payment_gateway"
                                                                    x-ref="gateway" x-model="selectedGateway"
                                                                    class="w-full p-2 border border-gray-300 rounded">
                                                                    @foreach ($paymentGateways as $gateway)
                                                                    <option value="{{ $gateway->name }}">
                                                                        {{ $gateway->name }}
                                                                    </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                            <input type="hidden" name="payment_method" x-bind:value="selectedGateway === 'MyFatoorah' ? $refs.method?.value : ''">

                                                            <div x-show="(option === 'generate_yes' || option === 'use_credit') && selectedGateway === 'MyFatoorah'" x-cloak class="mb-4">
                                                                <label for="payment_method" class="block mb-1 text-sm text-gray-700">
                                                                    Payment Method
                                                                </label>
                                                                <select id="payment_method" name="payment_method"
                                                                    x-ref="method"
                                                                    class="border border-gray-300 p-2 rounded w-full">
                                                                    @foreach ($paymentMethods as $methods)
                                                                    <option value="{{ $methods->id }}">{{ $methods->english_name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                            <div class="mt-6 flex justify-end">
                                                                <x-primary-button x-show="option !== ''"
                                                                    x-cloak>Submit</x-primary-button>
                                                            </div>
                                                        </form>
                                                    </main>
                                                </div>
                                            </div>


                                            @endif
                                </div>
                                <!-- <div
                                    class="rounded-full flex items-center justify-center 
                                        peer-checked:ring-2 peer-checked:ring-blue-500 
                                        peer-checked:bg-green-500
                                        peer-checked:text-white
                                        px-4 py-2 border border-gray-300 
                                        bg-white text-gray-700 transition gap-2 
                                        {{ $invoice->amount > $balanceCredit ? 'opacity-50 cursor-not-allowed' : 'hover:bg-green-500 hover:text-white hover:shadow-xl' }}">
                                    <span class="font-medium">{{ $selectedClient->first_name }}: KWD {{ $balanceCredit }}</span>
                                </div> -->

                                <!-- Full Payment Tab -->
                                <label class="cursor-pointer rounded-full shadow">
                                    <input type="radio" id="payment_type_full" name="payment_type" value="full"
                                        onclick="hideModal()" hidden class="peer"
                                        {{ $invoice->payment_type == 'full' ? 'checked' : '' }} />
                                    <div
                                        class="rounded-full flex items-center justify-center 
                                        peer-checked:ring-2 peer-checked:ring-blue-500 
                                        peer-checked:bg-green-500
                                        peer-checked:text-white
                                        px-4 py-2 border border-gray-300 
                                        bg-white text-gray-700 transition gap-2 
                                        hover:bg-green-500 hover:text-white hover:shadow-xl">
                                        <span class="font-medium">Fully Payment</span>
                                    </div>
                                </label>

                                <!-- Partial Payment Tab -->
                                <label class="cursor-pointer rounded-full shadow">
                                    <input type="radio" id="payment_type_partial" name="payment_type" value="partial"
                                        onclick="showModal('partial')" hidden class="peer"
                                        {{ $invoice->payment_type == 'partial' ? 'checked' : '' }} />
                                    <div
                                        class="rounded-full flex items-center justify-center 
                                        peer-checked:ring-2 peer-checked:ring-blue-500 
                                        peer-checked:bg-green-500
                                        peer-checked:text-white
                                        px-4 py-2 border border-gray-300 
                                        bg-white text-gray-700 transition gap-2 
                                        hover:bg-green-500 hover:text-white hover:shadow-xl">
                                        <span class="font-medium">Partially Payment</span>
                                    </div>
                                </label>

                                <!-- Split Payment Tab -->
                                <label class="cursor-pointer rounded-full shadow">
                                    <input type="radio" id="payment_type_split" name="payment_type" value="split"
                                        onclick="showModal('split')" hidden class="peer"
                                        {{ $invoice->payment_type == 'split' ? 'checked' : '' }} />
                                    <div
                                        class="rounded-full flex items-center justify-center 
                                        peer-checked:ring-2 peer-checked:ring-blue-500 
                                        peer-checked:bg-green-500
                                        peer-checked:text-white
                                        px-4 py-2 border border-gray-300 
                                        bg-white text-gray-700 transition gap-2 
                                        hover:bg-green-500 hover:text-white hover:shadow-xl">
                                        <span class="font-medium">Split Payment</span>
                                    </div>
                                </label>

                                <!-- Trigger Button -->
                                <label class="cursor-pointer rounded-full shadow">
                                    <input type="radio" id="payment_type_import" name="payment_type" value="import"
                                        onclick="showModal('import')" hidden class="peer" />
                                    <div
                                        class="rounded-full flex items-center justify-center 
                                        peer-checked:ring-2 peer-checked:ring-blue-500 
                                        peer-checked:bg-green-500
                                        peer-checked:text-white
                                        px-4 py-2 border border-gray-300 
                                        bg-white text-gray-700 transition gap-2 
                                        hover:bg-green-500 hover:text-white hover:shadow-xl">
                                        <span id="openImportModalBtn" class="font-medium">Import from MyFatoorah</span>
                                    </div>
                                </label>
                                <!-- <button id="openImportModalBtn" class="rounded-full px-4 py-2 bg-green-500 text-white shadow">
                                Import from MyFatoorah
                            </button> -->
                            </div>
                            <!-- Modal -->
                            <div id="importModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50 hidden">
                                <div class="bg-white rounded-lg p-6 w-full max-w-lg shadow-xl overflow-y-auto" style="max-height: 90vh;">
                                    <!-- Header -->
                                    <div class="flex items-center justify-between mb-6">
                                        <div>
                                            <h2 class="text-xl font-bold text-gray-800">Import MyFatoorah Payment</h2>
                                            <p class="text-gray-600 italic text-xs mt-1">
                                                Import a payment from an existing transaction on MyFatoorah Portal
                                            </p>
                                        </div>
                                        <button id="closeImportModalBtn"
                                            class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">&times;</button>
                                    </div>

                                    <!-- Form -->
                                    <form id="importForm" class="space-y-4">
                                        <div>
                                            <label for="import_invoice_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                Existing Invoice ID
                                            </label>
                                            <input type="text" id="import_invoice_id"
                                                class="block w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                                                placeholder="Enter invoice ID" required>
                                        </div>

                                        <!-- Success Message -->
                                        <div id="successBox" class="hidden p-3 bg-green-50 border border-green-200 rounded-md text-green-800 text-sm"></div>

                                        <!-- Error Message -->
                                        <div id="errorBox" class="hidden p-3 bg-red-50 border border-red-200 rounded-md text-red-800 text-sm"></div>

                                        <!-- Loading Spinner -->
                                        <div id="loadingBox" class="hidden p-3 bg-blue-50 border border-blue-200 rounded-md text-blue-800 text-sm flex items-center">
                                            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-blue-800" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Processing import...
                                        </div>

                                        <!-- Buttons -->
                                        <div class="flex justify-between pt-4 mt-4">
                                            <button type="button" id="cancelImport"
                                                class="w-32 shadow-md border border-gray-200 hover:bg-gray-400 font-semibold py-2 rounded-full text-sm transition duration-150">
                                                Cancel
                                            </button>
                                            <button type="submit" id="submitImportBtn"
                                                class="w-32 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-full text-sm shadow-md transition duration-150">
                                                Import
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Payment Gateway Section -->
                            <section id="payment_gateway_section" class="mb-6">
                                @php
                                $selectedGateway = optional($invoice->invoicePartials->first())->payment_gateway ?? '';
                                $selectedMethod = optional($invoice->invoicePartials->first())->payment_method ?? '';
                                @endphp
                                <div id="payment_gateway_dropdowns">
                                    <div x-data="{ selectedGateway: '{{ $selectedGateway }}', selectedMethod: '{{ $selectedMethod }}', paymentType: '{{ $invoice->payment_type ?? '' }}' }">
                                        <div class="mt-4">
                                            <div class="flex items-center">
                                                <h2 class="text-lg font-semibold mb-3 text-gray-700">Choose Payment Gateway</h2>
                                                <span x-show="paymentType !== ''" class="text-xs text-blue-500 ml-2 mb-2 cursor-pointer"
                                                    @click="updateGateway">(Change)</span>
                                            </div>
                                            <select id="payment_gateway_option" name="payment_gateway_option"
                                                class="border border-gray-300 p-2 rounded w-full" x-model="selectedGateway">
                                                @foreach ($paymentGateways as $gateway)
                                                <option value="{{ $gateway->name }}"
                                                    {{ $selectedGateway === $gateway->name ? 'selected' : '' }}>
                                                    {{ $gateway->name }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mt-4" x-cloak
                                            x-show="selectedGateway === 'MyFatoorah'" x-transition>
                                            <h2 class="text-lg font-semibold mb-3 text-gray-700">Choose Payment Method</h2>
                                            <select name="payment_method" id="payment_method_full"
                                                class="border border-gray-300 p-2 rounded w-full">
                                                @foreach ($paymentMethods as $methods)
                                                <option value="{{ $methods->id }}" {{ $selectedMethod == $methods->id ? 'selected' : '' }}>{{ $methods->english_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- External URL Field -->
                                        <div class="mt-4" id="external_url_section" style="display: none;">
                                            <h2 class="text-lg font-semibold mb-3 text-gray-700">External Payment URL (Optional)</h2>
                                            <input type="url" id="external_url" name="external_url"
                                                class="border border-gray-300 p-2 rounded w-full"
                                                placeholder="Enter payment gateway URL (optional)"
                                                value="{{ $invoice->external_url ?? '' }}">
                                            <p class="text-sm text-gray-500 mt-1">Optionally provide an external payment gateway URL for this invoice</p>
                                        </div>

                                        <!-- Auto Payment Notification -->
                                        <div class="mt-4" id="auto_payment_notification" style="display: none;">
                                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                <div class="flex items-center">
                                                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <p class="text-sm text-blue-800 font-medium">Auto Payment Enabled</p>
                                                </div>
                                                <p class="text-xs text-blue-700 mt-1">This payment gateway will automatically mark the invoice as paid when processing full payment.</p>
                                            </div>
                                        </div>

                                        <!-- Hidden input for invoice charge (always present for calculations) -->
                                        <input type="hidden" id="invoice_charge" name="invoice_charge" value="{{ $invoice->invoice_charge }}">

                                        <!-- Invoice Charge Section (Initially Hidden) -->
                                        @if($invoiceCharges->count() > 0)
                                        <div id="invoice_charge_section" class="mt-4" style="display: none;">
                                            <h2 id="invoice_charge_title" class="text-lg font-semibold mb-3 text-gray-700">Invoice Charge</h2>

                                            <!-- Calculation Method -->
                                            <div class="mb-3">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Calculation Method:</label>
                                                <select id="charge_calculation_method" name="charge_calculation_method" class="form-select">
                                                    <option value="flat">Flat Rate</option>
                                                    <option value="percentage">Percentage (%)</option>
                                                </select>
                                            </div>

                                            <!-- Charge Amount Input -->
                                            <div class="mb-3">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Charge Amount:</label>
                                                <input type="number" id="invoice_charge_amount" name="invoice_charge_amount"
                                                    class="form-input" step="0.01" min="0"
                                                    value="{{ $invoice->invoice_charge }}"
                                                    placeholder="Enter charge amount">
                                            </div>

                                            <!-- Calculated Invoice Charge (Read-only display) -->
                                            <div class="mb-3">
                                                <label id="calculated_charge_label" class="block text-sm font-medium text-gray-700 mb-1">Calculated Invoice Charge:</label>
                                                <input type="text" id="calculated_invoice_charge" class="form-input bg-gray-100"
                                                    value="{{ number_format($invoice->invoice_charge, 2) }}" readonly>
                                            </div>

                                            <!-- Important Note -->
                                            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                                                <div class="flex items-start">
                                                    <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    <div>
                                                        <h4 class="text-sm font-medium text-yellow-800">Important Note</h4>
                                                        <p class="text-sm text-yellow-700 mt-1">
                                                            When using percentage calculation, only the final calculated amount is saved in our database.
                                                            The percentage and calculation method are for your convenience only and are not stored.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                    <div id="payment-response-message" class="hidden mt-4 text-sm font-semibold rounded px-4 py-2"></div>
                                </div>
                                <div class="mt-4">
                                    <button id="update-invoice-btn" type="button"
                                        class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                                        city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow
                                        hover:bg-[#f7b14f] hover:shadow-xl hover:text-black transition">

                                        <span id="button-icon-full" class="mr-2 inline-block">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                                xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0">
                                                <path
                                                    d="M17.657 6.343a8 8 0 11-11.314 0L4.929 5.03a9.998 9.998 0 1014.142 0l-1.414 1.314z"
                                                    fill="currentColor" />
                                                <path
                                                    d="M11.25 8V4.75a.75.75 0 011.5 0V8h2.25a.75.75 0 010 1.5H12.75V12a.75.75 0 01-1.5 0V9.5H9a.75.75 0 010-1.5h2.25z"
                                                    fill="currentColor" />
                                            </svg>
                                        </span>

                                        <span id="button-text-full">Save Full Payment</span>
                                    </button>
                                    <div class="mt-4">
                                        <a target="_blank" href="{{ route('invoice.proforma', $invoice->invoice_number) }}"
                                            class="py-3 px-5 w-full inline-flex items-center justify-center text-sm text-white rounded-full gap-2 bg-blue-500 hover:bg-blue-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                <polyline points="14,2 14,8 20,8" />
                                                <line x1="16" y1="13" x2="8" y2="13" />
                                                <line x1="16" y1="17" x2="8" y2="17" />
                                                <polyline points="10,9 9,9 8,9" />
                                            </svg>
                                            Proforma Invoice
                                        </a>
                                    </div>
                                </div>
                            </section>

                            <!-- Added Buttons/Links Section -->
                            <section id="additional-actions" class="mt-6">
                                <div class="flex flex-wrap gap-4">
                                    <h2 class="text-lg font-semibold mb-3 text-gray-700">Share Invoice</h2>

                                    <!-- Share Buttons -->
                                    <div class="flex items-center gap-2 w-full">
                                        <form id="whatsappForm" action="{{ route('resayil.share-invoice-link') }}"
                                            method="POST" onsubmit="showSpinner()">
                                            @csrf
                                            <!-- Assuming you have a $client object or list -->
                                            <input type="hidden" name="client_id" id="clientid"
                                                value="{{ $client->id ?? '' }}">
                                            <input type="hidden" name="invoiceNumber" value="{{ $invoiceNumber }}">

                                            <button id="submitButton" type="submit"
                                                class="w-full flex items-center justify-center py-3 px-5 text-xs text-white btn-success rounded-full">
                                                <span id="buttonText">Share via WhatsApp</span>
                                                <span id="spinner" class="hidden ml-2">
                                                    <svg class="w-4 h-4 animate-spin text-white"
                                                        xmlns="http://www.w3.org/2000/svg" fill="none"
                                                        viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                                            stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor"
                                                            d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"></path>
                                                    </svg>
                                                </span>
                                            </button>
                                        </form>

                                        <button onclick="shareViaEmail()"
                                            class="w-full items-center py-3 px-5 text-sm text-white btn-info rounded-full ">
                                            Share via Email
                                        </button>

                                    </div>

                                    <button onclick="copyLink()"
                                        class="py-3 px-5 w-full inline-flex items-center justify-center text-sm text-white rounded-full gap-2 DarkBGcolor">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                            viewBox="0 0 24 24">
                                            <g fill="none" stroke="#ffff" stroke-linecap="round"
                                                stroke-linejoin="round" stroke-width="1.5">
                                                <path
                                                    d="M16.75 5.75a3 3 0 0 0-3-3h-6.5a3 3 0 0 0-3 3v9.5a3 3 0 0 0 3 3h6.5a3 3 0 0 0 3-3z" />
                                                <path d="M19.75 6.75v8.5a6 6 0 0 1-6 6h-5.5" />
                                            </g>
                                        </svg>
                                        Copy Link
                                    </button>

                                    <!-- View Button -->
                                    {{-- <button onclick="viewInvoice()"
                                    class="py-3 px-5 w-full inline-flex items-center justify-center text-sm text-white rounded-full gap-2 DarkBGcolor">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 ltr:mr-2 rtl:ml-2">
                                        <path opacity="0.5"
                                            d="M3.27489 15.2957C2.42496 14.1915 2 13.6394 2 12C2 10.3606 2.42496 9.80853 3.27489 8.70433C4.97196 6.49956 7.81811 4 12 4C16.1819 4 19.028 6.49956 20.7251 8.70433C21.575 9.80853 22 10.3606 22 12C22 13.6394 21.575 14.1915 20.7251 15.2957C19.028 17.5004 16.1819 20 12 20C7.81811 20 4.97196 17.5004 3.27489 15.2957Z"
                                            stroke="currentColor" stroke-width="1.5"></path>
                                        <path
                                            d="M15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z"
                                            stroke="currentColor" stroke-width="1.5"></path>
                                    </svg>
                                    View
                                </button> --}}
                                    <a target="_blank" href="{{ url('/invoice/' . $invoice->invoice_number) }}"
                                        class="py-3 px-5 w-full inline-flex items-center justify-center text-sm text-white rounded-full gap-2 DarkBGcolor">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 ltr:mr-2 rtl:ml-2">
                                            <path opacity="0.5"
                                                d="M3.27489 15.2957C2.42496 14.1915 2 13.6394 2 12C2 10.3606 2.42496 9.80853 3.27489 8.70433C4.97196 6.49956 7.81811 4 12 4C16.1819 4 19.028 6.49956 20.7251 8.70433C21.575 9.80853 22 10.3606 22 12C22 13.6394 21.575 14.1915 20.7251 15.2957C19.028 17.5004 16.1819 20 12 20C7.81811 20 4.97196 17.5004 3.27489 15.2957Z"
                                                stroke="currentColor" stroke-width="1.5"></path>
                                            <path
                                                d="M15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z"
                                                stroke="currentColor" stroke-width="1.5"></path>
                                        </svg>
                                        View
                                    </a>

                                    <p id="copyFeedback" class="mt-2 text-sm text-green-600 hidden">Link copied to
                                        clipboard!</p>
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

                            {{-- <button id="generate-invoice-btn" type="button"
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
                            <span id="button-text">Update</span>
                            <span id="button-loading" style="display: none;">Saving...</span>
                            <span id="button-saved" style="display: none;">Saved</span>
                        </button> --}}

                            <!-- Delete Button -->
                            {{-- <button id="delete-invoice-btn" type="button"
                            class="w-full inline-flex items-center justify-center text-sm font-semibold text-white bg-red-500 hover:bg-red-700 py-4 rounded-full shadow">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 mr-2">
                                <path d="M3 6H5H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path
                                    d="M8 6V4C8 3.44772 8.44772 3 9 3H15C15.5523 3 16 3.44772 16 4V6M19 6V19C19 20.1046 18.1046 21 17 21H7C5.89543 21 5 20.1046 5 19V6H19Z"
                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path d="M10 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                                <path d="M14 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                            <span>Delete</span>
                        </button> --}}

                            <input id="invoiceId" type="hidden" name="invoiceId" />
                            <!-- add form here-->


                            <div id="errorMessage" class="hidden text-red-500">
                                <!-- Error message -->
                            </div>

                            <!-- Modal -->
                            <div id="paymentModal"
                                class="fixed inset-0 z-50 hidden bg-gray-800 bg-opacity-50 flex items-center justify-center">
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
                                                            <input type="number" id="total-amount"
                                                                class="w-full border-gray-300 rounded-md shadow-sm opacity-50"
                                                                placeholder="0" disabled />
                                                        </div>
                                                        <div>
                                                            <label class="block text-sm font-medium mb-1"
                                                                for="split-into">Split into *</label>
                                                            <select id="split-into"
                                                                class="w-full p-2 border-gray-300 rounded-md shadow-sm"
                                                                onchange="updateRows()">
                                                                <option value="" disabled selected>Select a value
                                                                </option>
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
                                                            <label class="block text-sm font-medium mb-1">Description
                                                                *</label>
                                                            <textarea id="split-desc" class="w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="Add Description"></textarea>
                                                        </div>
                                                    </div>

                                                    <!-- Table -->
                                                    <div class="overflow-x-auto">
                                                        <table
                                                            class="min-w-full bg-white border border-gray-300 text-center">
                                                            <thead>
                                                                <tr>
                                                                    <th class="border-b px-4 py-2">S.No</th>
                                                                    <th class="border-b px-4 py-2">Choose Client</th>
                                                                    <th class="border-b px-4 py-2">Expiry Date</th>
                                                                    <th class="border-b px-4 py-2">Amount</th>
                                                                    <th class="border-b px-4 py-2">Payment Gateway</th>
                                                                    <th class="border-b px-4 py-2">Payment Method</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="split-rows">
                                                                <!-- Dynamic rows will be generated here -->
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    <!-- Buttons -->

                                                    <div class="flex space-x-4 mt-5">
                                                        <button type="button" id="splitbutton"
                                                            onclick="savePartial('split')"
                                                            class="inline-flex items-center justify-center text-sm text-black font-semibold
                                                            city-light-yellow hover:bg-[#004c9e] hover:text-white  py-2 px-10 rounded-full shadow">
                                                            <span id="button-icon-split" class="mr-2"></span>
                                                            <span id="button-text-split">Save Split Payment</span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button onclick="hideModal()"
                                            class="bg-gray-600 text-white px-4 py-2 rounded-md">Close</button>
                                    </div>
                                </div>
                            </div>

                            <div id="paymentModal1"
                                class="fixed inset-0 z-50 hidden bg-gray-800 bg-opacity-50 flex items-center justify-center">
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
                                                        <label for="receiverEmail1" class="mb-0 w-1/3 mr-2 ">Invoice
                                                            Total</label>
                                                        <span id="subT1">0.00</span>
                                                    </div>
                                                </div>

                                                <div x-data="{ paymentGateway: '' }" x-init="paymentGateway = document.getElementById('payment_gateway1').value;
                                            document.getElementById('payment_gateway1').addEventListener('change', e => paymentGateway = e.target.value)"
                                                    class="grid grid-cols-3 gap-4 mb-5">
                                                    <div>
                                                        <label class="block text-sm font-medium mb-1"
                                                            for="split-into1">Split into *</label>
                                                        <select id="split-into1"
                                                            class="w-full p-2 border-gray-300 rounded-md shadow-sm"
                                                            onchange="updateRows1()">
                                                            <option value="" disabled selected>Select a value
                                                            </option>
                                                            <option value="1">1</option>
                                                            <option value="2">2</option>
                                                            <option value="3">3</option>
                                                            <option value="4">4</option>
                                                            <option value="5">5</option>
                                                            <option value="6">6</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium mb-1">Payment
                                                            Gateway</label>
                                                        <select id="payment_gateway1" name="payment_gateway1"
                                                            class="w-full p-2 border-gray-300 rounded-md shadow-sm">
                                                            @foreach ($paymentGateways as $gateway)
                                                            <option value="{{ $gateway->name }}">{{ $gateway->name }}
                                                            </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div x-cloak x-show="paymentGateway === 'MyFatoorah'" x-transition>
                                                        <label class="block text-sm font-medium mb-1">Payment
                                                            Method</label>
                                                        <select name="payment_method1" id="payment_method1"
                                                            class="w-full p-2 border-gray-300 rounded-md shadow-sm">
                                                            @foreach ($paymentMethods as $methods)
                                                            <option value="{{ $methods->id }}">
                                                                {{ $methods->english_name }}
                                                            </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <h2 class="text-lg font-semibold mb-3 text-gray-700">Partial Payment
                                                    Breakdown</h2>
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

                                                <p id="error-message" class="text-red-500 mt-3 hidden">The total of
                                                    partial payments must match the invoice total.</p>

                                                <div class="flex space-x-4 mt-5">
                                                    <button id="partialbutton" onclick="savePartial('partial')"
                                                        type="button"
                                                        class="inline-flex items-center justify-center text-sm text-black font-semibold
                                                            city-light-yellow hover:bg-[#004c9e] hover:text-white  py-2 px-10  rounded-full shadow">
                                                        <span id="button-icon-partial" class="mr-2"></span>
                                                        <span id="button-text-partial">Save Partial Payment</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button onclick="hideModal()"
                                            class="bg-gray-600 text-white px-4 py-2 rounded-md">Close</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Agents Modal -->
                            <div id="agentModal"
                                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden">
                                <div class="bg-white border rounded-lg shadow-lg w-3/4 md:w-1/2 mb-10">
                                    <!-- Modal Header -->
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
                                    <!-- ./List of Agents -->
                                </div>
                            </div>
                            <!-- End Agents Modal -->

                            <!-- Clients Modal -->
                            <div id="clientModal"
                                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden ">
                                <div class="bg-white border rounded-lg shadow-lg  w-3/4 md:w-1/2 mb-10">
                                    <!-- Modal Header -->
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
                                    <!-- ./Modal Header -->

                                    <!-- Tabs -->
                                    <div class="border-b flex justify-center">
                                        <button class="tab-button px-4 py-2 text-blue-500 border-b-2 border-blue-500"
                                            id="selectTabButton">Select Client</button>
                                        <button class="tab-button px-4 py-2 text-gray-500 hover:text-blue-500"
                                            id="addTabButton">Add New Client</button>
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
                                                    <label for="name"
                                                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                                                    <input id="name" name="name" type="text" required
                                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                        placeholder="Client Name" />
                                                </div>

                                                <!-- Email Field -->
                                                <div class="w-1/2">
                                                    <label for="email"
                                                        class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
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
                                                    class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Passport
                                                    Number</label>
                                                <input id="passport_no" name="passport_no" type="text" required
                                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                    placeholder="Passport Number" />
                                            </div>

                                            <!-- Email Field -->
                                            <div class="mb-4">
                                                <label for="agent_email"
                                                    class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Agent
                                                    Email</label>
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
                                                            <input type="radio" name="status" value="1"
                                                                class="status-radio peer hidden" id="active" />
                                                            <span
                                                                class="flex items-center justify-center w-6 h-6 border border-gray-500 rounded-full peer-checked:border-[#00ab55] peer-checked:bg-[#00ab55] peer-checked:text-white peer-checked:font-semibold">
                                                                <span class="w-3 h-3 bg-transparent rounded-full"></span>
                                                            </span>
                                                            <span
                                                                class="ml-2 text-lg text-gray-700 peer-checked:text-[#00ab55] peer-checked:font-semibold">Active</span>
                                                        </label>

                                                        <!-- Inactive Radio Button -->
                                                        <label class="flex items-center cursor-pointer">
                                                            <input type="radio" name="status" value="2"
                                                                class="status-radio peer hidden" id="inactive" />
                                                            <span
                                                                class="flex items-center justify-center w-6 h-6 border border-gray-500 rounded-full peer-checked:border-[#e7515a] peer-checked:bg-[#e7515a] peer-checked:text-white peer-checked:font-semibold">
                                                                <span class="w-3 h-3 bg-transparent rounded-full"></span>
                                                            </span>
                                                            <span
                                                                class="ml-2 text-lg text-gray-700 peer-checked:text-[#e7515a] peer-checked:font-semibold">Inactive</span>
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
                            <div id="taskModal"
                                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden">
                                <div class="bg-white border rounded-lg shadow-lg w-3/4 md:w-1/2">
                                    <div
                                        class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3">
                                        <h5 class="text-lg font-bold">Choose Task</h5>
                                        <!-- Close Modal Button -->
                                        <button type="button" class="text-white-dark hover:text-dark"
                                            id="closeTaskModalButton">
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
                                    <div class="m-6">
                                        <!-- Search Box -->
                                        <div class="relative mb-10">
                                            <input type="text" placeholder="Search Task..."
                                                class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                                                id="taskSearchInput" oninput="filterTasks()">
                                        </div>
                                        <!-- ./Search Box -->
                                        <!-- List of Tasks -->
                                        <ul id="taskList"
                                            class="border rounded-lg mb-10 max-h-60 overflow-y-auto custom-scrollbar">
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
            let invoice = @json($invoice);
            let items = [];
            let tasks = [];
            const itemsBody = document.getElementById('items-body');
            const appUrl = @json($appUrl);
            const charges = @json($paymentGateways);
            const invoiceCharges = @json($invoiceCharges);

            // console.log(items);

            // Invoice Charge Functions
            function calculateInvoiceCharge() {
                const calculationMethod = document.getElementById('charge_calculation_method').value;
                const chargeAmountInput = document.getElementById('invoice_charge_amount');
                const calculatedChargeInput = document.getElementById('calculated_invoice_charge');
                const invoiceChargeHidden = document.getElementById('invoice_charge');

                const subtotal = items.reduce((sum, item) => sum + (parseFloat(item.task_price) || 0), 0);
                let chargeAmount = parseFloat(chargeAmountInput.value) || 0;

                // Validate and prevent negative values
                if (chargeAmount < 0) {
                    chargeAmount = 0;
                    chargeAmountInput.value = 0;
                    showValidationError(chargeAmountInput, 'Charge amount cannot be negative');
                    return;
                } else {
                    hideValidationError(chargeAmountInput);
                }

                let finalChargeAmount = 0;

                if (chargeAmount > 0) {
                    if (calculationMethod === 'percentage') {
                        finalChargeAmount = (subtotal * chargeAmount) / 100;
                    } else {
                        finalChargeAmount = chargeAmount;
                    }
                }

                calculatedChargeInput.value = finalChargeAmount.toFixed(2);
                invoiceChargeHidden.value = finalChargeAmount;

                calculateSubtotal();
            }

            function resetInvoiceCharge() {
                const chargeAmountInput = document.getElementById('invoice_charge_amount');
                const calculatedChargeInput = document.getElementById('calculated_invoice_charge');
                const invoiceChargeHidden = document.getElementById('invoice_charge');
                const invoiceChargeDisplay = document.getElementById('invoiceChargeDisplay');

                if (chargeAmountInput) chargeAmountInput.value = '';
                if (calculatedChargeInput) calculatedChargeInput.value = '0.00';
                if (invoiceChargeHidden) invoiceChargeHidden.value = '0';
                if (invoiceChargeDisplay) invoiceChargeDisplay.textContent = '0.00';

                calculateSubtotal();
            }

            // Validation helper functions
            function showValidationError(inputElement, message) {
                // Remove existing error message
                hideValidationError(inputElement);

                // Add error styling
                inputElement.classList.add('border-red-500');

                // Create and show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'text-red-500 text-sm mt-1 validation-error';
                errorDiv.textContent = message;
                inputElement.parentNode.appendChild(errorDiv);
            }

            function hideValidationError(inputElement) {
                // Remove error styling
                inputElement.classList.remove('border-red-500');

                // Remove error message
                const existingError = inputElement.parentNode.querySelector('.validation-error');
                if (existingError) {
                    existingError.remove();
                }
            }

            // Function to validate invoice charge amount input
            function validateInvoiceChargeAmount(inputElement) {
                const value = parseFloat(inputElement.value);

                if (value < 0) {
                    inputElement.value = 0;
                    showValidationError(inputElement, 'Charge amount cannot be negative');
                    return false;
                } else {
                    hideValidationError(inputElement);
                    return true;
                }
            }

            // Function to update invoice charge labels with payment gateway name
            function updateInvoiceChargeLabels(gatewayName) {
                const invoiceChargeTitle = document.getElementById('invoice_charge_title');
                const invoiceChargeLabel = document.getElementById('invoice_charge_label');
                const calculatedChargeLabel = document.getElementById('calculated_charge_label');

                if (gatewayName) {
                    const chargeText = `${gatewayName} Charge`;
                    const calculatedChargeText = `Calculated ${gatewayName} Charge:`;

                    if (invoiceChargeTitle) {
                        invoiceChargeTitle.textContent = chargeText;
                    }
                    if (invoiceChargeLabel) {
                        invoiceChargeLabel.textContent = chargeText + ':';
                    }
                    if (calculatedChargeLabel) {
                        calculatedChargeLabel.textContent = calculatedChargeText;
                    }
                } else {
                    // Fallback to default labels
                    if (invoiceChargeTitle) {
                        invoiceChargeTitle.textContent = 'Invoice Charge';
                    }
                    if (invoiceChargeLabel) {
                        invoiceChargeLabel.textContent = 'Invoice Charge:';
                    }
                    if (calculatedChargeLabel) {
                        calculatedChargeLabel.textContent = 'Calculated Invoice Charge:';
                    }
                }
            }


            // Function to check if external URL should be shown and handle auto-payment
            function checkExternalUrlVisibility() {
                const selectedGateway = document.getElementById('payment_gateway_option').value;
                const externalUrlSection = document.getElementById('external_url_section');
                const externalUrlInput = document.getElementById('external_url');
                const autoPaymentNotification = document.getElementById('auto_payment_notification');

                // Find the charge settings for the selected gateway
                const selectedCharge = charges.find(charge => charge.name === selectedGateway);

                // Handle external URL visibility
                if (selectedCharge && typeof selectedCharge.has_url !== 'undefined' && selectedCharge.has_url) {
                    externalUrlSection.style.display = 'block';
                    // External URL is optional, not required
                } else {
                    externalUrlSection.style.display = 'none';
                    if (externalUrlInput) {
                        externalUrlInput.value = '';
                    }
                }

                // Handle invoice charge section visibility
                const invoiceChargeSection = document.getElementById('invoice_charge_section');
                const invoiceChargeDisplayRow = document.getElementById('invoice_charge_display_row');

                if (selectedCharge && selectedCharge.can_charge_invoice) {
                    if (invoiceChargeSection) {
                        invoiceChargeSection.style.display = 'block';
                        // Update labels with payment gateway name
                        updateInvoiceChargeLabels(selectedGateway);
                    }
                    if (invoiceChargeDisplayRow) {
                        invoiceChargeDisplayRow.style.display = 'flex';
                    }
                } else {
                    if (invoiceChargeSection) {
                        invoiceChargeSection.style.display = 'none';
                        // Reset invoice charge values when hidden
                        resetInvoiceCharge();
                    }
                    if (invoiceChargeDisplayRow) {
                        invoiceChargeDisplayRow.style.display = 'none';
                    }
                }

                // Handle auto-payment logic
                if (selectedCharge && selectedCharge.is_auto_paid) {
                    // Show auto-payment notification
                    autoPaymentNotification.style.display = 'block';

                    // Auto-select full payment
                    const fullPaymentRadio = document.getElementById('payment_type_full');
                    if (fullPaymentRadio) {
                        fullPaymentRadio.checked = true;
                        fullPaymentRadio.click(); // Trigger any onclick events
                    }

                    // Disable other payment options
                    const partialPaymentRadio = document.getElementById('payment_type_partial');
                    const splitPaymentRadio = document.getElementById('payment_type_split');
                    const importPaymentRadio = document.getElementById('payment_type_import');

                    if (partialPaymentRadio) {
                        partialPaymentRadio.disabled = true;
                        partialPaymentRadio.parentElement.style.opacity = '0.5';
                        partialPaymentRadio.parentElement.style.pointerEvents = 'none';
                    }
                    if (splitPaymentRadio) {
                        splitPaymentRadio.disabled = true;
                        splitPaymentRadio.parentElement.style.opacity = '0.5';
                        splitPaymentRadio.parentElement.style.pointerEvents = 'none';
                    }
                    if (importPaymentRadio) {
                        importPaymentRadio.disabled = true;
                        importPaymentRadio.parentElement.style.opacity = '0.5';
                        importPaymentRadio.parentElement.style.pointerEvents = 'none';
                    }
                } else {
                    // Hide auto-payment notification
                    autoPaymentNotification.style.display = 'none';

                    // Re-enable other payment options
                    const partialPaymentRadio = document.getElementById('payment_type_partial');
                    const splitPaymentRadio = document.getElementById('payment_type_split');
                    const importPaymentRadio = document.getElementById('payment_type_import');

                    if (partialPaymentRadio) {
                        partialPaymentRadio.disabled = false;
                        partialPaymentRadio.parentElement.style.opacity = '1';
                        partialPaymentRadio.parentElement.style.pointerEvents = 'auto';
                    }
                    if (splitPaymentRadio) {
                        splitPaymentRadio.disabled = false;
                        splitPaymentRadio.parentElement.style.opacity = '1';
                        splitPaymentRadio.parentElement.style.pointerEvents = 'auto';
                    }
                    if (importPaymentRadio) {
                        importPaymentRadio.disabled = false;
                        importPaymentRadio.parentElement.style.opacity = '1';
                        importPaymentRadio.parentElement.style.pointerEvents = 'auto';
                    }
                }
            }

            // Add event listener for gateway selection change
            document.addEventListener('DOMContentLoaded', function() {
                const gatewaySelect = document.getElementById('payment_gateway_option');
                if (gatewaySelect) {
                    gatewaySelect.addEventListener('change', checkExternalUrlVisibility);
                    // Check on initial load
                    checkExternalUrlVisibility();
                }

                // Invoice Charge Event Listeners
                const chargeCalculationMethod = document.getElementById('charge_calculation_method');
                const invoiceChargeAmount = document.getElementById('invoice_charge_amount');

                if (chargeCalculationMethod) {
                    chargeCalculationMethod.addEventListener('change', calculateInvoiceCharge);
                }

                if (invoiceChargeAmount) {
                    invoiceChargeAmount.addEventListener('input', calculateInvoiceCharge);

                    // Add real-time validation for negative values
                    invoiceChargeAmount.addEventListener('input', function() {
                        validateInvoiceChargeAmount(this);
                    });

                    // Prevent negative values on keydown
                    invoiceChargeAmount.addEventListener('keydown', function(e) {
                        // Prevent typing minus sign
                        if (e.key === '-' || e.key === 'Minus') {
                            e.preventDefault();
                        }
                    });

                    // Validate on blur (when user leaves the field)
                    invoiceChargeAmount.addEventListener('blur', function() {
                        validateInvoiceChargeAmount(this);
                    });
                }


                // Check if invoice charge section should be visible on page load
                const currentGateway = document.getElementById('payment_gateway_option')?.value;
                if (currentGateway) {
                    const currentCharge = charges.find(charge => charge.name === currentGateway);
                    const invoiceChargeSection = document.getElementById('invoice_charge_section');
                    const invoiceChargeDisplayRow = document.getElementById('invoice_charge_display_row');

                    if (currentCharge && currentCharge.can_charge_invoice && invoice.invoice_charge > 0) {
                        if (invoiceChargeSection) {
                            invoiceChargeSection.style.display = 'block';
                            // Update labels with payment gateway name on page load
                            updateInvoiceChargeLabels(currentGateway);
                        }
                        if (invoiceChargeDisplayRow) {
                            invoiceChargeDisplayRow.style.display = 'flex';
                        }
                    }
                }
            });

            // console.log('invoice', invoice);
            // Handle Tab Switching
            const selectTabButton = document.getElementById('selectTabButton');
            const addTabButton = document.getElementById('addTabButton');
            const selectTab = document.getElementById('selectTab');
            const addTab = document.getElementById('addTab');
            const clientButton = document.getElementById("openClientModalButton");
            const agentButton = document.getElementById("select-agent");
            const updateInvoiceBtn = document.getElementById("update-invoice-btn");

            const paymentTypeFull = document.getElementById("payment_type_full");
            const paymentTypePartial = document.getElementById("payment_type_partial");
            const paymentTypeSplit = document.getElementById("payment_type_split");
            const isInvoicePaid = @json($invoice->status === 'paid');
            const hasPaymentType = @json(!empty($invoice->payment_type));

            clientButton.disabled = true;
            agentButton.disabled = true;

            document.getElementById("openClientModalButton").onclick = openClientModal;
            document.getElementById("closeClientModalButton").onclick = closeClientModal;
            document.getElementById('clientSearchInput').addEventListener('input', filterClients);

            document.getElementById("openTaskModalButton").onclick = openTaskModal;
            document.getElementById("closeTaskModalButton").onclick = closeTaskModal;
            document.getElementById('taskSearchInput').addEventListener('input', filterTasks);

            let selectedTasks1 = @json($selectedTasks);
            let selectedAgent = @json($selectedAgent);
            let selectedClient = @json($selectedClient);

            updateClientAgent(selectedClient.id, selectedAgent.id);

            const buttonText = document.getElementById('button-text');
            const buttonLoading = document.getElementById('button-loading');
            const buttonSaved = document.getElementById('button-saved');


            const invoiceIdInput = document.getElementById('invoiceId');
            invoiceIdInput.value = invoice.id;

            const invoiceExpireDefault = @json($invoiceExpireDefault);

            function checkInvoiceId() {
                const tabs = document.querySelectorAll('input[name="payment_type"]');
                const paymentType = invoice.payment_type;
                // console.log('paymenttype', paymentType);
                const paymentGatewaySection = document.getElementById('payment_gateway_section');
                const additionalActions = document.getElementById('additional-actions');
                const paymentModal = document.getElementById('paymentModal');
                const paymentModal1 = document.getElementById('paymentModal1');

                const paymentGatewayDropdowns = document.getElementById('payment_gateway_dropdowns');

                if (paymentType === 'full') {
                    paymentGatewaySection.style.display = 'block'; // Show the section
                    additionalActions.style.display = 'block';
                    updateInvoiceBtn.disabled = true;
                    paymentTypeFull.disabled = true;
                    paymentTypePartial.disabled = true;
                    paymentTypeSplit.disabled = true;
                    paymentGatewayDropdowns.classList.remove('hidden');
                } else if (paymentType === 'partial') {
                    paymentGatewaySection.style.display = 'block'; // Show the section
                    additionalActions.style.display = 'block';
                    updateInvoiceBtn.disabled = true;
                    paymentTypeFull.disabled = true;
                    paymentTypePartial.disabled = true;
                    paymentTypeSplit.disabled = true;
                    paymentGatewayDropdowns.classList.add('hidden');
                    paymentModal1.classList.add('hidden');
                } else if (paymentType === 'split') {
                    paymentGatewaySection.style.display = 'block'; // Show the section
                    additionalActions.style.display = 'block';
                    updateInvoiceBtn.disabled = true;
                    paymentTypeFull.disabled = true;
                    paymentTypePartial.disabled = true;
                    paymentTypeSplit.disabled = true;
                    paymentGatewayDropdowns.classList.add('hidden');
                    paymentModal.classList.add('hidden');
                } else {
                    paymentGatewaySection.style.display = 'none'; // Hide the section
                    additionalActions.style.display = 'none';
                    updateInvoiceBtn.disabled = false;
                    paymentTypeFull.disabled = false;
                    paymentTypePartial.disabled = false;
                    paymentTypeSplit.disabled = false;
                }

            }

            // Run the check on page load and whenever the input value changes
            document.addEventListener('DOMContentLoaded', checkInvoiceId);
            document.addEventListener('DOMContentLoaded', function() {
                const saveBtn = document.getElementById('update-invoice-btn');

                saveBtn.addEventListener('click', function() {
                    savePartial('full');
                });

                const addTaskButton = document.getElementById('openTaskModalButton');
                const actionsHeader = document.querySelector('#itemsTable th:last-child');
                const actionCells = document.querySelectorAll('#itemsTable td:last-child');

                if (isInvoicePaid || hasPaymentType) {
                    if (addTaskButton) {
                        addTaskButton.style.display = 'none';
                    }
                    if (actionsHeader) {
                        actionsHeader.style.display = 'none';
                    }
                    actionCells.forEach(cell => cell.style.display = 'none');
                }
            });


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


            function updateRows() { //split payment
                const splitInto = parseInt(document.getElementById('split-into').value) || 0;
                const totalAmount = parseFloat(document.getElementById('total-amount').value) || 0;
                const perRowAmount = splitInto > 0 ? (totalAmount / splitInto).toFixed(2) : 0;
                const clients = @json($clients);
                const paymentMethods = @json($paymentMethods);
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
                        <input type="date" id="date_${i}" name="date_${i}" value="${invoiceExpireDefault}" class="border-gray-300 rounded-md shadow-sm" />
                    </td>
                    <td class="border-b px-4 py-2">
                        <input type="number" id="amount_${i}" name="amount_${i}" class="border-gray-300 rounded-md" value="${perRowAmount}" 
                            onblur="checkInputAmount('split', ${i})" oninput="checkInputAmount('split', ${i})" />
                    </td>
                    <td class="border-b px-4 py-2">
                        <select id="payment_gateway_${i}" name="payment_gateway_${i}" class="border border-gray-300 p-2 rounded w-full">
                            @foreach ($paymentGateways as $gateway)
                            <option value="{{ $gateway->name }}">{{ $gateway->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="border-b px-4 py-2">
                        <div id="payment_method_container_${i}" class="hidden">
                            <select id="payment_method_${i}" name="payment_method_${i}" class="border border-gray-300 p-2 rounded w-full">
                                ${paymentMethods.map(method => `<option value="${method.id}">${method.english_name}</option>`).join('')}
                            </select>
                        </div>
                        <div id="payment_method_text_${i}" class="text-gray-500 p-2">No specific method required</div>
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

                    const gatewaySelect = row.querySelector(`#payment_gateway_${i}`);
                    const methodContainer = row.querySelector(`#payment_method_container_${i}`);
                    const methodText = row.querySelector(`#payment_method_text_${i}`);

                    const updateMethodVisibility = () => {
                        if (gatewaySelect.value.toLowerCase() === 'myfatoorah') {
                            methodContainer.classList.remove('hidden');
                            methodText.classList.add('hidden');
                        } else {
                            methodContainer.classList.add('hidden');
                            methodText.classList.remove('hidden');
                        }
                    };

                    updateMethodVisibility();

                    gatewaySelect.addEventListener('change', updateMethodVisibility);
                }
            }

            function updateRows1() { //partial payment
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
                        <input type="date" id="date_${i}" name="date_${i}" value="${invoiceExpireDefault}" class="border-gray-300 rounded-md shadow-sm" />
                    </td>
                    <td class="border-b px-4 py-2">
                        <input type="number" id="amount_${i}" name="amount_${i}" class="border-gray-300 rounded-md" value="${perRowAmount1}" 
                            onblur="checkInputAmountOnInput('partial', ${i})" oninput="checkInputAmount('partial', ${i})" />
                    </td>
                `;
                    tbody.appendChild(row);

                }
            }

            function updateField(itemId, fieldId) {
                // console.log('updated', itemId + '-' + fieldId);
                const inputField = document.getElementById(`${fieldId}-${itemId}`);
                const newValue = inputField.value || NULL;

                const item = items.find(item => item.id === itemId);

                if (item) {
                    // if (fieldId === 'invprice') {
                    if (fieldId.includes('invprice')) {
                        // Set fieldId to 'invprice' if it includes 'invprice'
                        fieldId1 = 'invprice'; // Update fieldId to 'invprice' if modal or table input is updated
                        item[fieldId1] = newValue;


                        if (fieldId === 'invprice-modal') {
                            // Update the corresponding table input
                            const tableInput = document.getElementById(`invprice-table-${itemId}`);
                            if (tableInput) {
                                tableInput.value = newValue;
                            }
                        } else if (fieldId === 'invprice-table') {
                            // Update the corresponding modal input
                            const modalInput = document.getElementById(`invprice-modal-${itemId}`);
                            if (modalInput) {
                                modalInput.value = newValue;
                            }
                        }

                        const nettValue = (item.invprice - item.price);
                        // console.log(item);
                        // console.log('Supplier price: ' + item.total);
                        // console.log('Invoice price: ' + item.invprice);
                        // console.log('Nett of markup: ' + nettValue);
                        calculateSubtotal(); // Recalculate the subtotal

                        let existingAlert = document.getElementById("errorNotification");

                        if (nettValue <= 0) {
                            // console.log("The Invoice Price must be higher than the Task Price.");

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

                                // Auto-close after 5 seconds
                                setTimeout(() => {
                                    let alertBox = document.getElementById("errorNotification");
                                    if (alertBox) {
                                        alertBox.remove();
                                    }
                                }, 10000);
                            }
                        } else {
                            // Remove error notification if nettValue is fixed (>= 0)
                            if (existingAlert) {
                                existingAlert.remove();
                            }
                        }




                    } else {
                        item[fieldId] = newValue; // Update other fields
                    }
                }

            }

            function updateItemPrice(itemId) {
                const item = items.find(item => item.id === itemId);
                const priceInput = document.getElementById(`invprice-table-${itemId}`);
                if (item && priceInput) {
                    item.task_price = parseFloat(priceInput.value) || 0;
                    calculateSubtotal();
                }
            }

            function calculateSubtotal() {
                const subtotal = items.reduce((sum, item) => sum + (parseFloat(item.task_price) || 0), 0);
                const invoiceChargeElement = document.getElementById('invoice_charge');
                const invoiceCharge = invoiceChargeElement ? parseFloat(invoiceChargeElement.value) || 0 : 0;
                const finalTotal = subtotal + invoiceCharge;

                // console.log('Calculating subtotal:', { subtotal, invoiceCharge, finalTotal, itemsCount: items.length });

                // Update all display elements
                document.getElementById('subTotalDisplay').textContent = `${subtotal.toFixed(2)}`;
                document.getElementById('invoiceChargeDisplay').textContent = `${invoiceCharge.toFixed(2)}`;
                document.getElementById('subT').textContent = `${finalTotal.toFixed(2)}`;
                
                const subT1Element = document.getElementById('subT1');
                if (subT1Element) subT1Element.textContent = `${finalTotal.toFixed(2)}`;
                
                document.getElementById('subTotal').value = subtotal;
                
                const totalAmountElement = document.getElementById('total-amount');
                if (totalAmountElement) totalAmountElement.value = finalTotal;
            }


            function renderItems() {
                const tbody = itemsBody;
                if (!tbody) return;
                tbody.innerHTML = '';

                if (!Array.isArray(items) || items.length === 0) {
                    const noItemsRow = document.createElement('tr');
                    noItemsRow.innerHTML =
                        '<td colspan="13" class="w-full !text-center font-semibold text-gray-900 dark:bg-[#121e32] dark:text-white">No Tasks Available</td>';
                    tbody.appendChild(noItemsRow);
                    return;
                }

                const frag = document.createDocumentFragment();
                let count = 0;

                for (const item of items) {
                    try {
                        const task = {
                            desc: item?.description ?? '',
                            info: item?.additional_info ?? '',
                            total: item?.total ?? 0,
                            taskPrice: item?.task_price ?? 0,
                            clientName: item?.client_name ?? '',
                            agentName: item?.agent?.name ?? item?.agent_name ?? '',
                            branchName: item?.agent?.branch?.name ?? item?.branch_name ?? '',
                            supplierName: item?.supplier_name ?? item?.supplier?.name ?? '',
                            type: (item?.type ?? ''),
                            typeCap: (item?.type ? (item.type.charAt(0).toUpperCase() + item.type.slice(1)) : ''),
                            id: item?.id ?? `row-${count+1}`,
                            quantity: item?.quantity ?? 1,
                            invprice: item?.invprice ?? '',
                            flight: item?.flight_details ?? null,
                            hotel: item?.hotel_details ?? null,
                        };

                        const isSaved = item.saved === true;
                        const row = document.createElement('tr');
                        row.className = `border-b border-[#e0e6ed] align-top dark:border-[#1b2e4b] ${!isSaved ? 'bg-sky-100' : ''}`;

                        row.innerHTML = `
                    <td class="flex-grow"><p>${++count}</p></td>
                    <td class="flex-grow">
                    <p><b>${task.desc}</b><br>Info: ${task.info}</br></p>
                    </td>
                    <td><p>${task.total} KWD</p></td>
                    <td>
                    <div class="flex items-center">
                        <input id="invprice-table-${task.id}" type="number" class="no-spin border border-gray-300 rounded-md w-full" value="${task.taskPrice}" oninput="updateItemPrice(${item.id}); updateField(${JSON.stringify(task.id)}, 'invprice-table')" />
                        ${isSaved ? `
                            <button type="button" class="p-1 rounded hover:bg-gray-200" title="Save" onclick="saveTaskPrice(${JSON.stringify(task.id)})">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                    class="w-5 h-5 text-blue-600">
                                    <path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/>
                                </svg>
                            </button>
                        ` : ''}
                    </div>
                    </td>
                    <td><p>${task.clientName}</p></td>
                    <td><p>${task.agentName}</p></td>
                    <td><p>${task.branchName}</p></td>
                    <td><p>${task.supplierName}</p></td>
                    <td><p>${task.typeCap}</p></td>
                    <td class="action-cell text-center">
                        <div class="inline-flex space-x-2">
                            ${!isSaved ? `
                                <button onclick="saveSingleTask(${item.id})" class="text-blue-500 hover:text-blue-700" data-tooltip-left="Save This Task">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                </button>
                            ` : ''}
                            <button onclick="removeTaskFromInvoice(${item.id} )" class="text-red-500 hover:text-red-700" data-tooltip-left="Remove Item">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 6H21M10 11V17M14 11V17M5 6H19L18 21H6L5 6ZM8 6V4C8 3.44772 8.44772 3 9 3H15C15.5523 3 16 3.44772 16 4V6" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                `;

                        frag.appendChild(row);

                        const taskDetails = document.getElementById('task-details_' + task.id);
                        if (taskDetails) {
                            taskDetails.innerHTML = `
                    <div class="mb-4 flex flex-col gap-2"> 
                        <div class="header text-lg font-bold mt-4 border-b">Task Details</div> 
                        <div class="flex justify-between items-center text-lg">
                        <div>Quantitiy: <strong>${task.quantity}</strong></div>
                        <div class="font-bold">${(task.quantity * Number(task.total || 0)).toFixed(2)} KWD</div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <input id="invprice-modal-${task.id}" type="number" name="invprice"
                            placeholder="Enter Invoice Price" class="border border-gray-300 p-2 rounded-md"
                            oninput="updateField(${JSON.stringify(task.id)}, 'invprice-modal')" value="${task.invprice}">
                        <input id="remark-${task.id}" type="text" name="remark" placeholder="Enter Remark"
                            class="border border-gray-300 p-2 rounded-md"
                            oninput="updateField(${JSON.stringify(task.id)}, 'remark')">
                        <input id="note-${task.id}" type="text" name="note" placeholder="Enter Note"
                            class="border border-gray-300 p-2 rounded-md"
                            oninput="updateField(${JSON.stringify(task.id)}, 'note')">
                        </div>
                    </div>
                    `;

                            if (task.flight !== null && task.hotel !== null) {
                                taskDetails.innerHTML = '<div class="text-red-500">Something Went Wrong</div>';
                            } else if (task.flight !== null) {
                                taskDetails.innerHTML += ` <div class="text-lg font-bold mt-4">Flight Details</div>
                            <hr/> 
                                <div class="flex flex-row-reverse items-center">
                                    <div class="p-2">
                                        <label class="switch">
                                            <input type="checkbox" id="" onclick="toggleAll(${task.id})">
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
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.flight_details.departure_time}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Country From</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.country_from}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Airport From</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.airport_from ? task.flight_details.airport_from : 'null'}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Terminal From</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.terminal_from}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Arrival Time</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.arrival_time}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Country To</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.country_to}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Airport To</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.airport_to}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Terminal To</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.terminal_to}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Airline</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.airline_id}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Class</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.class_type}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full line-clamp-1">Baggage Allowed</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.baggage_allowed}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Equipment</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.equipment}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Flight Meal</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.flight_meal}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Seat No</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.seat_no}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Created At</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.created_at}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Updated At</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md" value="${task.flight_details.updated_at}" disabled>
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
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.flight_details.farebase}">
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

                            } else if (task.hotel !== null) {
                                taskDetails.innerHTML += ` <div class="text-lg font-bold mt-4">Hotel Details</div>
                            <hr/>
                            <div class="flex flex-row-reverse items-center">
                                <div class="p-2">
                                    <label class="switch">
                                        <input type="checkbox" id="" onclick="toggleAll(${task.id})">
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
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.hotel_details.hotel.name}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Booking Time</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.hotel_details.booking_time}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Check-in</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.hotel_details.check_in}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                            <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Check-out</div>
                                            <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.hotel_details.check_out}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                        <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Room Number</div>
                                        <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.hotel_details.room_number}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                        <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Room Type</div>
                                        <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.hotel_details.room_type}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                        <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Room Amount</div>
                                        <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.hotel_details.room_amount}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                        <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Room Details</div>
                                        <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.hotel_details.room_details}" disabled>
                                        </div>
                                        <div class="flex justify-center items-center">
                                        <div class="font-semibold rounded-l-md bg-gray-200 p-2 border-0 w-full">Rate</div>
                                        <input type="text" class="border-2 border-gray-200 p-2 rounded-r-md h-full" value="${task.hotel_details.rate}" disabled>
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
                        }

                        if (isInvoicePaid || hasPaymentType) {
                            const actionCell = row.querySelector('.action-cell');
                            if (actionCell) actionCell.style.display = 'none';
                        }

                        const openButton = document.getElementById('modal-open-button_' + task.id);
                        const closeButton = document.getElementById('modal-close-button_' + task.id);
                        const modal = document.querySelector('dialog[data-modal-invoice="' + task.id + '"]');

                        if (openButton && closeButton && modal) {
                            openButton.addEventListener('click', function() {
                                // console.log(item.id);
                                modalInvoice.showModal();
                            });

                            closeButton.addEventListener('click', function() {
                                modalInvoice.close();
                            });
                        }

                    } catch (err) {
                        console.error('renderItems(): failed on item ->', item, err);
                    }
                }

                tbody.appendChild(frag);

                console.info('renderItems(): rendered rows =', tbody.rows.length, 'from items len =', items.length);
            }

            function saveTaskPrice(itemId) {
                const input = document.getElementById(`invprice-table-${itemId}`);
                const newPrice = parseFloat(input.value);

                if (isNaN(newPrice) || newPrice <= 0) {
                    displayErrorMessage('Please enter a valid price.');
                    return;
                }

                // Send AJAX request to update the price
                fetch(`/invoice/update-task-price`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        },
                        body: JSON.stringify({
                            task_id: itemId,
                            new_price: newPrice,
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the item in the items array
                            const item = items.find(item => item.id === itemId);
                            if (item) {
                                item.task_price = newPrice;
                            }
                            calculateSubtotal(); // <-- This updates #subT and others
                            displaySuccessMessage('Task price updated!');
                        } else {
                            displayErrorMessage(data.message || 'Failed to update price.');
                        }
                    })
                    .catch(() => {
                        displayErrorMessage('Failed to update price.');
                    });
            }

            function removeItem(itemId) {
                items = items.filter(item => item.id !== itemId);
                renderItems(); // Re-render the table after removal
                renderTaskList(tasks);
            }

            function removeItem(itemId) {
                const itemIndex = items.findIndex(item => item.id === itemId);
                if (itemIndex > -1) {
                    items.splice(itemIndex, 1);
                    renderItems();
                    calculateSubtotal();
                    renderTaskList(tasks);
                }
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

            function loadInitialTasks(initialTasks) {
                items = initialTasks.map(task => ({
                    ...task,
                    saved: true,
                    remark: task.remark || '',
                    quantity: task.quantity || 1,
                    description: task.description || `${task.reference}`,
                    client_name: task.client_name,
                    task_price: task.task_price || 0
                }));
                renderItems();
                calculateSubtotal();

                // Set initial invoice charge value if it exists
                if (invoice.invoice_charge > 0) {
                    const invoiceChargeInput = document.getElementById('invoice_charge_amount');
                    const calculatedChargeInput = document.getElementById('calculated_invoice_charge');
                    const invoiceChargeHidden = document.getElementById('invoice_charge');

                    if (invoiceChargeInput) {
                        invoiceChargeInput.value = invoice.invoice_charge;
                    }
                    if (calculatedChargeInput) {
                        calculatedChargeInput.value = invoice.invoice_charge.toFixed(2);
                    }
                    if (invoiceChargeHidden) {
                        invoiceChargeHidden.value = invoice.invoice_charge;
                    }
                }
            }

            function selectTask(task) {
                const newTask = {
                    ...task,
                    saved: false,
                    task_price: 0,
                    remark: '',
                    quantity: 1,
                    description: `${task.reference}`,
                    client_name: task.client_name
                };

                items.push(newTask);
                renderItems();
                calculateSubtotal();
                renderTaskList();
                closeTaskModal();
            }

            async function saveSingleTask(taskId) {
                const taskToSave = items.find(item => item.id === taskId);

                if (!taskToSave) {
                    console.error("Could not find the task to save.");
                    return;
                }

                const priceInput = document.getElementById(`invprice-table-${taskId}`);
                const price = parseFloat(priceInput.value);

                if (isNaN(price) || price <= 0) {
                    displayErrorMessage("Please enter a valid invoice price for the task before saving.");
                    priceInput.focus();
                    return;
                }

                taskToSave.task_price = price;

                try {
                    const response = await fetch('{{ route("invoice.add-task") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            invoice_id: invoice.id,
                            task_id: taskToSave.id,
                            task_price: taskToSave.task_price
                        })
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Failed to save task.');
                    }

                    taskToSave.saved = true;
                    renderItems();
                    displaySuccessMessage('Task saved successfully!');

                } catch (error) {
                    displayErrorMessage(error.message);
                }
            }

            async function removeTaskFromInvoice(taskId) {
                if (items.length <= 1) {
                    displayErrorMessage("An invoice must have at least one task. You cannot remove the last item.");
                    return;
                }

                const taskToRemove = items.find(item => item.id === taskId);
                if (!taskToRemove) return;

                if (taskToRemove.saved) {
                    if (!confirm('Are you sure you want to remove this saved task?')) {
                        return;
                    }

                    try {
                        const response = await fetch('{{ route("invoice.remove-task") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                invoice_id: invoice.id,
                                task_id: taskId
                            })
                        });

                        const result = await response.json();
                        if (!response.ok) throw new Error(result.message || 'Failed to remove task.');

                        displaySuccessMessage('Task removed successfully!');

                    } catch (error) {
                        displayErrorMessage(error.message);
                        return;
                    }
                }

                items = items.filter(item => item.id !== taskId);

                renderItems();
                calculateSubtotal();
                renderTaskList();
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
                document.getElementById('receiverName1').value = client.name;
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
                renderTaskList();
            }

            function renderTaskList() {
                const taskList = document.getElementById('taskList');
                const searchValue = document.getElementById('taskSearchInput').value.toLowerCase();
                taskList.innerHTML = '';

                let availableTasks = tasks.filter(task =>
                    !items.some(selectedItem => selectedItem.id === task.id)
                );

                if (searchValue) {
                    availableTasks = availableTasks.filter(task =>
                        (task.reference && task.reference.toLowerCase().includes(searchValue)) ||
                        (task.type && task.type.toLowerCase().includes(searchValue))
                    );
                }

                if (availableTasks.length === 0) {
                    const p = document.createElement('p');
                    p.className = 'text-center text-gray-500 p-4';
                    p.innerText = 'No more tasks available to add.';
                    taskList.appendChild(p);
                    return;
                }

                availableTasks.forEach(task => {
                    const li = document.createElement('li');
                    li.className = 'cursor-pointer p-3 hover:bg-gray-100 text-gray-800 border-b';
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
                const clients = @json($clients);
                const agents = @json($agents);
                const branches = @json($branches);
                // Find the client by clientId
                let client = clients.find(c => c.id === clientId);

                // Find the agent by agentId
                let agent = agents.find(a => a.id === agentId);
                // Find the branch associated with the agent
                // Find the branch associated with the agent
                let branch = branches.find(b => b.id === agent.branch_id);


                // Check if client and agent exist
                if (client && agent && branch) {
                    // Update hidden fields
                    document.getElementById('receiverId').value = client.id;
                    document.getElementById('clientid').value = client.id;
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
                document.getElementById('clientid').value = client.id;
                // Update input fields
                document.getElementById('receiverName').value = client.name;
                document.getElementById('receiverName1').value = client.name;
                document.getElementById('receiverEmail').value = client.email;
                document.getElementById('receiverPhone').value = client.phone;

                document.getElementById('agentName').value = agent.name;
                document.getElementById('agentEmail').value = agent.email;
                document.getElementById('agentPhone').value = agent.phone;
            }

            async function updateGateway() {
                const gateway = document.getElementById('payment_gateway_option').value;
                const amount = document.getElementById('subTotal').value;
                const invoiceId = document.getElementById('invoiceId').value;
                const invoiceNumber = document.getElementById('invoiceNumber').value;
                const responseBox = document.getElementById('payment-response-message');

                const data = {
                    invoiceId,
                    gateway,
                    amount,
                    invoiceNumber
                };

                if (gateway !== 'Tap') {
                    const method = document.getElementById('payment_method_full').value;
                    data.method = method;
                }

                const csrfToken = "{{ csrf_token() }}";
                const invoiceUrl = "{{ route('invoice.update-gateway') }}";

                try {
                    const response = await fetch(invoiceUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(data),
                    });

                    const result = await response.json();

                    responseBox.classList.remove('hidden', 'bg-red-100', 'text-red-700');
                    responseBox.classList.add('bg-green-100', 'text-green-700');
                    responseBox.textContent = result.message || 'Success';

                } catch (error) {
                    responseBox.classList.remove('hidden', 'bg-green-100', 'text-green-700');
                    responseBox.classList.add('bg-red-100', 'text-red-700');
                    responseBox.textContent = error.message || 'Something went wrong';
                }

                setTimeout(() => {
                    responseBox.classList.add('hidden');
                    responseBox.textContent = '';
                }, 3000);
            }

            function savePartial(mode) {
                const validation = checkPaymentAmount(mode);
    
                if (!validation.isValid) {
                    showErrorAlert(validation.errorMessage);
                    return;
                }

                clearErrorAlert();

                if (mode === 'full') {
                    const gateway = document.getElementById('payment_gateway_option')?.value;
                    const date = document.getElementById('duedate').value;
                    const amount = document.getElementById('subTotal').value;
                    const externalUrl = document.getElementById('external_url')?.value;
                    const fullData = [];

                    fullData.push({
                        date,
                        amount,
                        gateway,
                        external_url: externalUrl
                    });

                    for (const item of fullData) {
                        save('full', item);
                    }

                    const button = document.getElementById('update-invoice-btn');
                    const icon = document.getElementById('button-icon-full');
                    const text = document.getElementById('button-text-full');

                    button.disabled = true;

                    // Replace icon with spinner
                    icon.innerHTML = `
                    <svg class="animate-spin h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                `;
                    text.textContent = 'Saving...';

                    setTimeout(() => {
                        icon.innerHTML = '';
                        text.textContent = 'Saved ✅';
                        location.reload();
                    }, 500);


                } else if (mode === 'split') {
                    // Collect Split Payment Data
                    const rows = document.querySelectorAll('#split-rows tr');
                    const splitData = [];
                    rows.forEach((row, index) => {
                        const clientSelectElement = row.querySelector(`#customer_name_${index + 1}`);

                        if (!clientSelectElement) {
                            console.error(`Client select element not found for row ${index + 1}`);
                            return;
                        }

                        const clientId = clientSelectElement.value;
                        const clientName = clientSelectElement.options[clientSelectElement.selectedIndex].text;

                        const dateInput = row.querySelector(`input[type="date"]`);
                        const date = dateInput ? dateInput.value : null;

                        const amountInput = row.querySelector(`input[type="number"]`);
                        const amount = parseFloat(amountInput ? amountInput.value : 0) || 0;

                        const gatewaySelect = row.querySelector(`#payment_gateway_${index + 1}`);
                        const gateway = gatewaySelect ? gatewaySelect.value : null;

                        const methodSelect = row.querySelector(`#payment_method_${index + 1}`);
                        const method = methodSelect ? methodSelect.value : null;


                        splitData.push({
                            clientId,
                            clientName,
                            date,
                            amount,
                            gateway,
                            method
                        });
                    });

                    for (const item of splitData) {
                        save('split', item);
                    }


                    const buttonSplit = document.getElementById('splitbutton');
                    const iconSplit = document.getElementById('button-icon-split');
                    const textSplit = document.getElementById('button-text-split');

                    if (buttonSplit && iconSplit && textSplit) {
                        buttonSplit.disabled = true;

                        // Show spinner
                        iconSplit.innerHTML = `
                        <svg class="animate-spin h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    `;

                        textSplit.textContent = 'Saving...';

                        setTimeout(() => {
                            iconSplit.innerHTML = ''; // remove spinner
                            textSplit.textContent = 'Saved ✅';
                            location.reload();
                        }, 500);
                    } else {
                        console.error('Split button or icon/text elements not found in the DOM.');
                    }


                } else if (mode === 'partial') {
                    // Collect Partial Payment Data
                    const totalAmount1 = parseFloat(document.getElementById('total-amount').value) || 0;
                    const splitInto1 = parseInt(document.getElementById('split-into1').value) || 0;
                    const partialRows = document.querySelectorAll('#split-rows1 tr');
                    const gateway = document.getElementById('payment_gateway1')?.value || '';
                    const method = document.getElementById('payment_method1')?.value || '';

                    const partialData = [];

                    partialRows.forEach(row => {
                        const date = row.querySelector('input[type="date"]').value;
                        const amount = parseFloat(row.querySelector('input[type="number"]').value) || 0;

                        partialData.push({
                            date,
                            amount,
                            gateway,
                            method
                        });
                    });

                    for (const item of partialData) {
                        save('partial', item);
                    }

                    const buttonPartial = document.getElementById('partialbutton');
                    const iconPartial = document.getElementById('button-icon-partial');
                    const textPartial = document.getElementById('button-text-partial');

                    if (buttonPartial && iconPartial && textPartial) {
                        buttonPartial.disabled = true;

                        // Spinner icon (cleaned up)
                        iconPartial.innerHTML = `
                        <svg class="animate-spin h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    `;

                        textPartial.textContent = 'Saving...';

                        setTimeout(() => {
                            iconPartial.innerHTML = ''; // remove icon
                            textPartial.textContent = 'Saved ✅';
                            location.reload(); // or redirect if you want
                        }, 500);
                    } else {
                        console.error('One or more elements (button, icon, text) not found in the DOM');
                    }

                } else if (mode === 'credit') {
                    const gateway = document.getElementById('payment_gateway_option').value;
                    const date = document.getElementById('duedate').value;
                    const amount = document.getElementById('subTotal').value;
                    const fullData = [];

                    fullData.push({
                        date,
                        amount,
                        gateway: 'Credit'
                    });
                    for (const item of fullData) {
                        save('credit', item);
                    }

                    const button = document.getElementById('update-invoice-btn');
                    const icon = document.getElementById('button-icon-full');
                    const text = document.getElementById('button-text-full');

                    button.disabled = true;

                    // Replace icon with spinner
                    icon.innerHTML = `
                    <svg class="animate-spin h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                `;
                    text.textContent = 'Saving...';

                    displaySuccessMessage("Invoice saved and paid successfully!");

                    setTimeout(() => {
                        icon.innerHTML = '';
                        text.textContent = 'Saved ✅';
                        window.location.href = "{{ route('invoices.index') }}";
                    }, 1000);

                }
            }

            async function save(type, item) {
                // console.log("Sending single item:", item);

                const invoiceUrl = "{{ route('invoice.partial') }}";
                const csrfToken = "{{ csrf_token() }}";
                const invoiceId = document.getElementById('invoiceId').value;
                const invoiceNumber = document.getElementById('invoiceNumber').value;

                const invoiceCharge = document.getElementById('invoice_charge') ? document.getElementById('invoice_charge').value : 0;

                let payload = {
                    invoiceId,
                    invoiceNumber,
                    type,
                    date: item.date,
                    amount: item.amount,
                    gateway: item.gateway,
                    external_url: item.external_url || null,
                    invoice_charge: invoiceCharge,
                };

                if (type === 'full' || type === 'credit') {
                    payload.clientId = document.getElementById('receiverId').value;
                    if (item.gateway === 'MyFatoorah') {
                        payload.method = document.getElementById('payment_method_full')?.value;
                    } else {
                        payload.method = null;
                    }
                } else if (type === 'partial') {
                    payload.clientId = document.getElementById('receiverId').value;
                    payload.method = item.method;
                } else if (type === 'split') {
                    payload.clientId = item.clientId;
                    payload.method = item.method;
                }

                if (type === 'credit') {
                    payload.credit = true;
                }

                try {
                    const response = await fetch(invoiceUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload),
                    });

                    // console.log("Response status:", response.status);

                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || `Failed to process ${type} payment.`);
                    }

                    const result = await response.json();
                    // console.log("Backend response for single item:", result);
                    displaySuccessMessage(result.message || `${type} payment processed successfully!`);

                } catch (error) {
                    console.error(`Error processing ${type} payment for item:`, item, error);
                    displayErrorMessage(error.message || `Something went wrong with ${type} payment.`);
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

            function displaySuccessMessage(message) {
                const alert = document.createElement('div');
                alert.innerHTML = `
                  <div class="alert alert-success fixed mt-5 top-1 right-4 bg-green-500 text-white p-4 rounded shadow-lg">
                      ${message}
                      <button type="button" class="close text-white ml-2" aria-label="Close" onclick="this.parentElement.style.display='none';">
                          <span aria-hidden="true">&times;</span>
                      </button>
                  </div>
              `;
                document.body.appendChild(alert);
            }

            function afterPaymentType() {
                const tabs = document.querySelectorAll('input[name="payment_type"]');
                const partial = document.getElementById('payment_type_partial');
                const split = document.getElementById('payment_type_split');
                const full = document.getElementById('payment_type_full');
                const update = document.getElementById('update-invoice-btn');
                const updateSplitButton = document.getElementById('splitbutton');
                const updatePartialButton = document.getElementById('partialbutton');


            }

            function showNotification(message, type) {
                let notification = document.createElement('div');
                notification.innerHTML = `
                <div class="alert alert-${type} fixed mt-5 top-1 right-4 p-4 rounded shadow-lg ${
                    type === 'danger' ? 'bg-red-500 text-white' : 'bg-green-500 text-white'
                }">
                    ${message}
                    <button type="button" class="close text-white ml-2" aria-label="Close"
                        onclick="this.parentElement.style.display='none';">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `;
                document.body.appendChild(notification);
            }

            // Generate invoice
            async function updateInvoice() {

                const invoiceUrl = `/invoice/${invoice.id}`;
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
                // console.log(
                //     'clientId:', clientId,
                //     'agentId:', agentId,
                //     'tasksLength:', tasks.length,
                // );
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
                    return;
                }

                try {
                    const response = await fetch(invoiceUrl, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            invoice,
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
                    const {
                        invoiceId
                    } = result;
                    // console.log(invoiceId);

                    document.getElementById('invoiceId').value = invoiceId;
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
                } finally {
                    // Reset button states
                    buttonLoading.style.display = "none";
                    setTimeout(() => {
                        checkInvoiceId();
                    }, 1000);
                }
            };



            function viewInvoice() {
                openInvoiceModal(invoice.invoice_number);
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



            function copyLink() {
                const invoiceNumber = document.getElementById('invoiceNumber').value;
                const copyFeedback = document.getElementById('copyFeedback');
                const fetchUrl =
                    "{{ route('invoice.show', ['invoiceNumber' => ':invoiceNumber']) }}".replace(
                        ":invoiceNumber",
                        invoiceNumber
                    );

                navigator.clipboard.writeText(fetchUrl).then(() => {
                    alert('Link copied to clipboard: ' + fetchUrl); // Use invoiceLink here
                    copyFeedback.classList.remove('hidden');
                    // console.log(fetchUrl);
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


            function showSpinner() {
                document.getElementById("submitButton").disabled = true;
                document.getElementById("buttonText").textContent = "Sending...";
                document.getElementById("spinner").classList.remove("hidden");
            }


            document.addEventListener("DOMContentLoaded", function() {

                tasks = @json($tasks);
                let clients = @json($clients);
                let initialTasks = @json($selectedTasks);

                if (initialTasks && initialTasks.length > 0) {
                    loadInitialTasks(initialTasks);
                } else {
                    // Ensure subtotal is calculated even with no initial tasks
                    calculateSubtotal();
                }

                // Initialize modals with full data
                renderClientList(clients);
                renderTaskList();

                const paymentTypeRadios = document.querySelectorAll('input[name="payment_type"]');
                const paymentTypeSavedInput = document.getElementById('paymentTypeSaved');
                const paymentTypeSaved = paymentTypeSavedInput ? paymentTypeSavedInput.value : '';

                if (paymentTypeSaved) {
                    const matchingRadio = document.querySelector(
                        `input[name="payment_type"][value="${paymentTypeSaved}"]`);
                    if (matchingRadio) {
                        matchingRadio.checked = true;

                        // Trigger modal function manually if needed
                        if (paymentTypeSaved === 'partial') {
                            showModal('partial');
                        } else if (paymentTypeSaved === 'split') {
                            showModal('split');
                        } else {
                            hideModal();
                        }
                    }
                }

                // Optional: Attach listeners to update hidden input
                const radios = document.querySelectorAll('input[name="payment_type"]');
                radios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        paymentTypeSavedInput.value = this.value;
                    });
                });

                creditClientRadioChoice = document.getElementsByName('choice-invoice')

                yesChosen = document.getElementById('yes-chosen');
                noChosen = document.getElementById('no-chosen');

                if (yesChosen && noChosen) {
                    yesChosen.style.display = 'none';
                    noChosen.style.display = 'none';
                }

                creditClientRadioChoice.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.value === 'yes') {
                            yesChosen.style.display = 'block';
                            noChosen.style.display = 'none';
                        } else {
                            yesChosen.style.display = 'none';
                            noChosen.style.display = 'block';
                        }
                    });
                });

            });

            function showErrorAlert(message) {
                clearErrorAlert();

                let errorNotification = document.createElement('div');
                errorNotification.id = "paymentValidationError";
                errorNotification.innerHTML = ` 
                    <div class="alert alert-danger fixed top-5 right-5 bg-red-500 text-white p-4 rounded shadow-lg z-50">
                        ${message}
                        <button type="button" class="close text-white ml-2" aria-label="Close"
                            onclick="clearErrorAlert();">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>`;

                document.body.appendChild(errorNotification);

                setTimeout(() => { clearErrorAlert(); }, 10000);
            }

            function clearErrorAlert() {
                let existingAlert = document.getElementById("paymentValidationError");
                if (existingAlert) {
                    existingAlert.remove();
                }
            }

            function checkPaymentAmount(mode) {
                const totalInvoiceAmount = parseFloat(document.getElementById('total-amount').value) || 0;
                let totalEnteredAmount = 0;
                let isValid = true;
                let errorMessage = '';

                if (mode === 'split') {
                    const rows = document.querySelectorAll('#split-rows tr');
                    
                    rows.forEach((row, index) => {
                        const amountInput = row.querySelector(`input[type="number"]`);
                        const amount = parseFloat(amountInput ? amountInput.value : 0) || 0;
                        totalEnteredAmount += amount;
                    });

                    if (totalEnteredAmount !== totalInvoiceAmount) {
                        isValid = false;
                        errorMessage = `Total split payment amounts (${totalEnteredAmount.toFixed(2)} KWD) must equal the invoice amount (${totalInvoiceAmount.toFixed(2)} KWD). Please adjust the amounts to match exactly.`;
                    }

                } else if (mode === 'partial') {
                    const partialRows = document.querySelectorAll('#split-rows1 tr');
                    
                    partialRows.forEach(row => {
                        const amountInput = row.querySelector('input[type="number"]');
                        const amount = parseFloat(amountInput ? amountInput.value : 0) || 0;
                        totalEnteredAmount += amount;
                    });

                    if (totalEnteredAmount > totalInvoiceAmount) {
                        isValid = false;
                        errorMessage = `Total partial payment amounts (${totalEnteredAmount.toFixed(2)} KWD) exceed the invoice amount (${totalInvoiceAmount.toFixed(2)} KWD). Partial payments cannot exceed the invoice total.`;
                    } else if (totalEnteredAmount < totalInvoiceAmount) {
                        isValid = false;
                        errorMessage = `Total partial payment amounts (${totalEnteredAmount.toFixed(2)} KWD) are less than the invoice amount (${totalInvoiceAmount.toFixed(2)} KWD). Partial payments cannot be less than the invoice total.`;
                    }
                }

                return {
                    isValid,
                    errorMessage,
                    totalEnteredAmount,
                    totalInvoiceAmount
                };
            }

            function checkInputAmount(mode, rowIndex = null) {
                const validation = checkPaymentAmount(mode);
                
                const existingError = document.getElementById('payment-validation-error');
                if (existingError) {
                    existingError.remove();
                }

                if (!validation.isValid) {
                    showErrorAlert(validation.errorMessage);

                    const modalContent = mode === 'split' ? 
                        document.querySelector('#paymentModal .bg-white') : 
                        document.querySelector('#paymentModal1 .bg-white');
                    
                    if (modalContent) {
                        modalContent.insertBefore(errorDiv, modalContent.firstChild);
                    }

                    const saveButton = mode === 'split' ? 
                        document.getElementById('splitbutton') : 
                        document.getElementById('partialbutton');
                    
                    if (saveButton) {
                        saveButton.disabled = true;
                        saveButton.style.opacity = '0.5';
                        saveButton.style.cursor = 'not-allowed';
                    }
                } else {
                    clearErrorAlert();

                    const saveButton = mode === 'split' ? 
                        document.getElementById('splitbutton') : 
                        document.getElementById('partialbutton');
                    
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.style.opacity = '1';
                        saveButton.style.cursor = 'pointer';
                    }
                }
            }
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('importModal');
                const openBtn = document.getElementById('openImportModalBtn');
                const closeBtn = document.getElementById('closeImportModalBtn');
                const cancelBtn = document.getElementById('cancelImport');
                const form = document.getElementById('importForm');
                const input = document.getElementById('import_invoice_id');

                const successBox = document.getElementById('successBox');
                const errorBox = document.getElementById('errorBox');
                const loadingBox = document.getElementById('loadingBox');

                // Show modal
                openBtn.addEventListener('click', () => {
                    input.value = '';
                    errorBox.classList.add('hidden');
                    successBox.classList.add('hidden');
                    loadingBox.classList.add('hidden');
                    modal.classList.remove('hidden');
                });

                // Hide modal
                function closeModal() {
                    modal.classList.add('hidden');
                }
                closeBtn.addEventListener('click', closeModal);
                cancelBtn.addEventListener('click', closeModal);

                // Submit logic
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    const agentName = document.getElementById('agentName')?.value || '';
                    const clientName = document.getElementById('receiverName')?.value || '';
                    const paymentId = input.value.trim();
                    const page = 'invoice';

                    successBox.classList.add('hidden');
                    errorBox.classList.add('hidden');
                    loadingBox.classList.remove('hidden');

                    if (!agentName || !clientName) {
                        loadingBox.classList.add('hidden');
                        errorBox.textContent = 'Agent and Client must be selected.';
                        errorBox.classList.remove('hidden');
                        return;
                    }

                    if (!paymentId) {
                        loadingBox.classList.add('hidden');
                        errorBox.textContent = 'Payment ID is required.';
                        errorBox.classList.remove('hidden');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('_token', '{{ csrf_token() }}');
                    formData.append('import_invoice_id', paymentId);
                    formData.append('agentName', agentName);
                    formData.append('receiverName', clientName);
                    formData.append('page', page);

                    try {
                        const res = await fetch(`{{ route('payment.link.import-fatoorah.invoice') }}`, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData,
                        });

                        loadingBox.classList.add('hidden');

                        if (res.ok) {
                            successBox.textContent = `Payment imported successfully.`;
                            successBox.classList.remove('hidden');
                            input.value = '';
                            setTimeout(() => {
                                closeModal();
                                window.location.reload();
                            }, 2000);
                        } else {
                            const data = await res.json();
                            errorBox.textContent = `${data.message}`;
                            errorBox.classList.remove('hidden');
                        }

                    } catch (err) {
                        console.error(err);
                        loadingBox.classList.add('hidden');
                        errorBox.textContent = err.message;
                        errorBox.classList.remove('hidden');
                    }
                });
            });
        </script>
</x-app-layout>