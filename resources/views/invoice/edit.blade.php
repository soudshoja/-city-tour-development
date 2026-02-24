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

    @if($isLocked)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4 flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="text-red-500 shrink-0">
                <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
            </svg>
            <div>
                <p class="font-semibold text-red-700">This invoice is locked</p>
                <p class="text-sm text-red-600">
                    Locked by {{ $invoice->lockedByUser?->name ?? 'System' }} 
                    on {{ $invoice->locked_at ? \Carbon\Carbon::parse($invoice->locked_at)->format('d M Y H:i') : '' }}.
                    You can only view this invoice.
                </p>
            </div>
        </div>
    @endif
    <div id="invoiceModalComponent">
        <div class="flex flex-col gap-2.5 xl:flex-row">
            <div class="panel flex-1 px-0 py-6 lg:mr-6 ">
                <div class="flex flex-wrap justify-between px-4">
                    <div class=" shrink-0 items-center text-black dark:text-white">
                        <x-application-logo class="h-20 w-auto" />
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
                                class="select-options hidden absolute left-0 top-full w-full rounded-md grid {{ count($branches) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 py-3">
                                @foreach ($branches as $branch)
                                <div class="select-option px-4 py-3 text-center bg-white dark:bg-gray-700 rounded-lg dark:hover:bg-gray-800 border border-gray-300 cursor-pointer"
                                    data-value="{{ $branch->id }}">
                                    {{ $branch->name }}
                                </div>
                                @endforeach
                            </div>
                            <input type="hidden" name="branch_id" id="selectedBranch">
                        </div>
                    </div>
                    <div class="space-y-1 text-gray-500 dark:text-gray-400">
                        <div class="flex items-center">
                            <label class="w-full text-sm font-semibold">Invoice Number:</label>
                            <input id="invoiceNumber" type="text" name="invoiceNumber" value="{{ $invoiceNumber }}" class="w-full form-input" placeholder="Invoice Number" />
                            <input type="hidden" id="invoiceNumber" name="invoiceNumber" value="{{ $invoiceNumber }}">
                            <input type="hidden" id="companyId" name="companyId" value="{{ $companyId }}">
                        </div>

                        <form id="invoice-date-form" method="POST" action="{{ route('invoice.updateDate', ['companyId' => $companyId, 'invoiceNumber' => $invoiceNumber]) }}">
                            @csrf
                            @method('PUT')
                            <div class="flex items-center">
                                <label class="w-full text-sm font-semibold">Invoice Date:</label>
                                @if(!$isLocked)
                                <button type="submit" class=" rounded hover:bg-gray-200 dark:hover:bg-gray-700" title="Save">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                        class="w-5 h-5 text-blue-600">
                                        <path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z" />
                                    </svg>
                                </button>
                                @endif
                                <input id="invdate" type="date" name="invdate" class="form-input w-full"
                                    value="{{ $invoice->invoice_date }}" />
                            </div>
                        </form>

                        <div class="mt-4 flex items-center">
                            <label class="w-full text-sm font-semibold">Due Date:</label>
                            <input id="duedate" type="date" name="duedate" class="w-full form-input"
                                value={{ $dueDate }} disabled />
                        </div>

                        @if($refund)
                        <div class="mt-4 flex items-center">
                            <label class="w-full text-sm font-semibold">Refund Number:</label>
                            <input type="text" class="w-full form-input" value="{{ $refund->refund_number }}" disabled />
                        </div>
                        @if($refund->remarks)
                        <div class="mt-4 flex items-center">
                            <label class="w-full text-sm font-semibold">Refund Remarks:</label>
                            <input type="text" class="w-full form-input" value="{{ $refund->remarks }}" disabled />
                        </div>
                        @endif
                        @endif
                    </div>
                </div>
                <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />
                <div class="flex justify-between px-4 gird gird-cols-2 gap-4">
                    <!-- client details -->
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
                                value="{{ auth()->user()->role_id == \App\Models\Role::AGENT ? auth()->user()->agent->phone_number : '' }}"
                                disabled />
                        </div>
                    </div>
                </div>

                <!-- choose items -->
                <div class="mt-8">
                    <form id="updateAmountForm" method="POST" action="{{ route('invoice.updateAmount', ['companyId' => $companyId, 'invoiceNumber' => $invoiceNumber]) }}">
                        @csrf
                        @method('PUT')
                        <div class="overflow-x-auto w-full border border-gray-200">
                            <table id="itemsTable" class="text-left table-auto border-collapse w-full text-xs">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-gray-900 dark:text-gray-100">No.</th>
                                        <th class="px-4 py-2 min-w-[200px] text-gray-900 dark:text-gray-100">Task</th>
                                        <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Client Name</th>
                                        <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Agent Name</th>
                                        <th class="px-4 py-2 text-gray-900 dark:text-gray-100">Task Price</th>
                                        <th class="px-4 py-2 w-36 text-gray-900 dark:text-gray-100">Invoice Price</th>
                                        <th class="px-4 py-2 w-20 text-gray-900 dark:text-gray-100" @if ($invoice->refund) style="display:none" @endif>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="items-body" class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <!-- Items will be added dynamically here -->
                                    <!-- "No Item Available" row will show if items.length <= 0 -->
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6 flex flex-col justify-between px-4 sm:flex-row">
                            <div class="mb-6 sm:mb-0">
                                <button id="openTaskModalButton" type="button"
                                    class="inline-flex items-center justify-center text-sm text-black font-semibold
                                     city-light-yellow hover:bg-[#004c9e] hover:text-white  py-2 px-4  rounded-full shadow"
                                    @if ($invoice->refund) style="display:none" @endif>
                                    <svg class="w-6 h-6 pr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <path fill="currentColor"
                                            d="M19 11h-4v4h-2v-4H9V9h4V5h2v4h4m1-7H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2M4 6H2v14a2 2 0 0 0 2 2h14v-2H4z" />
                                    </svg> Add Task
                                </button>
                            </div>
                            <div class="sm:w-2/5 flex justify-end flex-col items-end">
                                @php
                                    $isRefundInvoiceEdit = $invoice->refund;
                                    $refundBreakdown = null;
                                    if ($isRefundInvoiceEdit) {
                                        $refundObjEdit = \App\Models\Refund::where('refund_invoice_id', $invoice->id)
                                            ->with(['refundDetails.task.originalTask', 'originalInvoice.invoiceDetails', 'originalInvoice.invoicePartials'])
                                            ->first();
                                        if ($refundObjEdit && $refundObjEdit->originalInvoice) {
                                            $origInv = $refundObjEdit->originalInvoice;
                                            $paidParts = $origInv->invoicePartials->where('status', 'paid');
                                            $rPaidTotal = $paidParts->sum('amount') + $paidParts->sum('service_charge') + $paidParts->sum('invoice_charge');
                                            $rNonRefundable = $paidParts->sum('service_charge') + $paidParts->sum('invoice_charge');
                                            $rRefundedIds = $refundObjEdit->refundDetails
                                                ->map(fn($d) => $d->task?->originalTask?->id ?? $d->task_id)
                                                ->filter()->unique()->toArray();
                                            $rRemainingTotal = $origInv->invoiceDetails
                                                ->when(!empty($rRefundedIds), fn($c) => $c->whereNotIn('task_id', $rRefundedIds))
                                                ->sum('task_price');
                                            $refundBreakdown = [
                                                'paidTotal'       => $rPaidTotal,
                                                'remainingTasks'  => $rRemainingTotal,
                                                'nonRefundable'   => $rNonRefundable,
                                                'cancellationFee' => $refundObjEdit->total_refund_amount,
                                                'amountToCollect' => $invoice->amount,
                                            ];
                                        }
                                    }
                                @endphp

                                @if ($isRefundInvoiceEdit && $refundBreakdown)
                                <div class="mb-3 w-full p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs">
                                    <p class="font-semibold text-blue-800 mb-1">Refund Balance Calculation</p>
                                    <div class="space-y-0.5">
                                        <div class="flex justify-between text-gray-600">
                                            <span>Total Paid (original invoice):</span>
                                            <span>{{ number_format($refundBreakdown['paidTotal'], 3) }}</span>
                                        </div>
                                        <div class="flex justify-between text-red-600">
                                            <span>Remaining Task Costs:</span>
                                            <span>-{{ number_format($refundBreakdown['remainingTasks'], 3) }}</span>
                                        </div>
                                        @if ($refundBreakdown['nonRefundable'] > 0)
                                        <div class="flex justify-between text-red-600">
                                            <span>Non-refundable Charges:</span>
                                            <span>-{{ number_format($refundBreakdown['nonRefundable'], 3) }}</span>
                                        </div>
                                        @endif
                                        <div class="flex justify-between text-red-600">
                                            <span>Cancellation Fee:</span>
                                            <span>-{{ number_format($refundBreakdown['cancellationFee'], 3) }}</span>
                                        </div>
                                        <div class="flex justify-between font-bold border-t border-blue-300 pt-1 text-green-700">
                                            <span>Amount to Collect:</span>
                                            <span>{{ number_format($refundBreakdown['amountToCollect'], 3) }}</span>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                {{-- Standard totals always rendered so JS can reference these elements --}}
                                <div class="mt-4 flex flex-col items-end font-semibold space-y-1">
                                    <div class="flex items-center pb-1 font-medium">
                                        <div class="mr-2">Total Net:</div>
                                        <span id="netT">0.00</span>
                                        <input id="netTotal" type="hidden" name="netTotal" />
                                    </div>
                                    <div class="flex items-center mb-1">
                                        <div class="mr-2">Subtotal:</div>
                                        <span id="subTotalDisplay">0.00</span>
                                    </div>
                                    <div id="service_charge_display_row" class="flex items-center mb-1" style="display: none;">
                                        <div id="service_charge_label" class="mr-2">Service Charge:</div>
                                        <span id="serviceChargeDisplay">0.00</span>
                                    </div>
                                    <div id="final_amount_display_row" class="flex items-center mb-1 font-medium border-t pt-1" style="display: none;">
                                        <div class="mr-2">Final Amount:</div>
                                        <span id="finalAmountDisplay">0.00</span>
                                    </div>
                                    <div id="invoice_charge_display_row" class="flex items-center mb-1" style="display: none;">
                                        <div id="invoice_charge_label" class="mr-2">Invoice Charge:</div>
                                        <span id="invoiceChargeDisplay">0.00</span>
                                    </div>
                                    <div class="flex items-center border-t pt-1">
                                        <div class="mr-2">Invoice Total:</div>
                                        <span id="subT">0.00</span>
                                        <input id="subTotal" type="hidden" name="subTotal" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if ($invoice->status === 'paid' && ($invoice->payment_type === 'full'))
                        <div class="px-4 mt-8 border-t pt-6 flex justify-end">
                            <button type="submit" class="inline-flex items-center justify-center text-base font-semibold bg-blue-600 text-white hover:bg-blue-700 py-3 px-6 rounded-lg shadow-md">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 mr-2">
                                    <path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z" />
                                </svg>
                                Update Invoice Amounts
                            </button>
                        </div>
                        @endif
                    </form>
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
                        <h2 class="text-lg font-semibold mb-5 text-gray-700">Invoice Settings</h2>

                        <div id="paymentMethod" class="mt-4">
                            <div x-data="paymentSection()" x-init="initData()" class="mb-5">
                                <h2 class="text-lg font-semibold mb-3 text-gray-700 flex items-center justify-between">
                                    <div>
                                        <span>
                                            Payment Type :
                                            <span class="font-large text-success"
                                                x-text="paymentType ? paymentType.charAt(0).toUpperCase() + paymentType.slice(1) : 'N/A'">
                                            </span>
                                        </span>
                                    </div>

                                    @if(!$isLocked)
                                        @if ($invoice->status === 'unpaid')
                                        <span x-show="paymentType && hasInvoicePartials" class="text-xs text-blue-500 ml-2 cursor-pointer" @click="showModalType = true">
                                            (Change Type)
                                        </span>
                                        @elseif ($invoice->status === 'partial')
                                        <span x-show="paymentType" class="text-xs text-blue-500 ml-2 cursor-pointer" @click="openGatewayModal()">
                                            (Change Gateway)
                                        </span>
                                        @endif
                                    @endif
                                </h2>

                                <!-- Change Payment Type Modal -->
                                <div x-show="showModalType" x-cloak
                                    class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50">

                                    <div class="bg-white rounded-lg shadow-lg p-6 w-[28rem] border border-gray-200 space-y-5">
                                        <div class="flex justify-between items-center">
                                            <h2 class="text-xl font-bold text-gray-800">Change Payment Type</h2>
                                            <button type="button" @click="closeTypeModal()"
                                                class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                                        </div>

                                        <div class="space-y-3">
                                            <p class="text-sm text-gray-700 text-center">
                                                This invoice already has payment records. Changing the payment type will affect existing records.
                                            </p>
                                            <div class="flex justify-center items-center gap-2 text-sm text-gray-600">
                                                <span>Current:</span>
                                                <strong class="text-green-600" x-text="paymentType ? paymentType.charAt(0).toUpperCase() + paymentType.slice(1) : 'N/A'"></strong>
                                                <span>→</span>
                                                <span>New:</span>
                                                <strong class="text-blue-600" x-text="pendingPaymentType ? pendingPaymentType.charAt(0).toUpperCase() + pendingPaymentType.slice(1) : ''"></strong>
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route('invoice.update-type') }}" class="flex justify-between items-center pt-3 w-full">
                                            @csrf
                                            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                            <input type="hidden" name="new_payment_type" :value="pendingPaymentType">

                                            <button type="button"
                                                class="text-gray-600 text-sm px-4 py-2 border rounded-full shadow-md hover:bg-gray-100"
                                                @click="closeTypeModal()">
                                                Cancel
                                            </button>

                                            <button type="submit"
                                                class="text-white bg-blue-600 text-sm px-4 py-2 rounded-full shadow-md hover:bg-blue-700">
                                                Confirm Change
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Change Payment Gateway Modal -->
                                <div x-show="showModalGateway" x-cloak
                                    class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50">
                                    <div class="bg-white rounded-lg shadow-lg p-6 w-[32rem] border border-gray-200 space-y-5">
                                        <div class="flex justify-between items-center">
                                            <h2 class="text-xl font-bold text-gray-800">Change Payment Gateway</h2>
                                            <button @click="showModalGateway = false"
                                                class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                                        </div>

                                        <div class="space-y-3">
                                            <p class="text-sm text-gray-700">
                                                You can change the payment gateway for each unpaid partial of this invoice.
                                            </p>

                                            @foreach ($unpaidPartial as $partial)
                                                <form method="POST" action="{{ route('invoice.update-partial-gateway') }}" class="border border-gray-200 rounded-md p-3 mb-3">
                                                    @csrf
                                                    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                                    <input type="hidden" name="invoice_number" value="{{ $invoice->invoice_number }}">
                                                    <input type="hidden" name="invoice_partial_id" value="{{ $partial->id }}">

                                                    <div class="mb-2 flex justify-between items-center">
                                                        <strong class="text-gray-800">{{ $invoice->invoice_number }}</strong>
                                                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            Unpaid
                                                        </span>
                                                    </div>

                                                    <div class="mt-5 mb-5 flex justify-between">
                                                        <span class="text-gray-700">Amount:</span>
                                                        <span class="font-semibold">{{ $invoice->currency }} {{ number_format($partial->amount, 3) }}</span>
                                                    </div>

                                                    <!-- Gateway Selection -->
                                                    <div class="mb-2">
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Gateway</label>
                                                        <select 
                                                            name="gateway" 
                                                            id="gateway_{{ $partial->id }}" 
                                                            class="border border-gray-300 rounded-md text-sm p-1.5 w-full"
                                                            onchange="toggleMethod({{ $partial->id }})">
                                                            @foreach ($paymentGateways as $gateway)
                                                                <option value="{{ $gateway->name }}" 
                                                                    @selected($gateway->name == $partial->payment_gateway)>
                                                                    {{ $gateway->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <!-- Payment Method Selection -->
                                                    <div class="mb-4" id="method_section_{{ $partial->id }}" style="display: none;">
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                                        <select name="method" class="border border-gray-300 rounded-md text-sm p-1.5 w-full">
                                                            <option value="">Select a Method</option>
                                                            @foreach ($paymentGateways as $gateway)
                                                                @foreach ($gateway->methods as $method)
                                                                    <option value="{{ $method->id }}"
                                                                        @selected($method->id == $partial->payment_method)>
                                                                        {{ $method->english_name }}
                                                                    </option>
                                                                @endforeach
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <div class="flex justify-end">
                                                        <button type="submit"
                                                            class="text-white bg-blue-600 text-xs px-3 py-1.5 rounded-full shadow-md hover:bg-blue-700">
                                                            Update
                                                        </button>
                                                    </div>
                                                </form>
                                            @endforeach

                                            @if($unpaidPartial->isEmpty())
                                                <p class="text-sm text-gray-500 italic">No unpaid partials found for this invoice.</p>
                                            @endif
                                        </div>

                                        <div class="flex justify-end items-center pt-3">
                                            <button type="button"
                                                class="text-gray-600 text-sm px-4 py-2 border rounded-full shadow-md hover:bg-gray-100"
                                                @click="showModalGateway = false">
                                                Close
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" id="paymentTypeSaved" name="payment_type_saved"
                                    value="{{ $invoice->payment_type }}">
                                    
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-1">
                                    <!-- Client Credit Section -->
                                    <div x-data="{ clientCreditModal: false, generateInvoiceWithCreditModal: false }">
                                        @php
                                        $balanceCredit = \App\Models\Credit::getTotalCreditsByClient($selectedClient->id);
                                        @endphp
                                        @if ($invoice->amount <= $balanceCredit)
                                            <!-- Credit can cover full invoice -->
                                            <label class="rounded-full shadow transition-all duration-200 block"
                                                :class="isTypeLocked('credit') ? 'cursor-not-allowed' : 'cursor-pointer'"
                                                @click="handlePaymentTypeClick('credit', $event)">
                                                <button type="button" 
                                                    @click="if(!isTypeLocked('credit')) { showModal('credit') }"
                                                    class="rounded-full flex flex-col items-center justify-center w-full
                                                        px-4 py-2 border border-gray-300 transition gap-2
                                                        bg-white text-gray-700"
                                                    :class="isTypeLocked('credit') 
                                                        ? 'cursor-not-allowed' 
                                                        : 'hover:bg-green-500 hover:text-white hover:shadow-xl cursor-pointer'">
                                                    <span class="font-medium">{{ $selectedClient->first_name }}: KWD {{ $balanceCredit }}</span>
                                                </button>
                                            </label>
                                            
                                            <!-- Credit Modal with Payment Selection -->
                                            <div id="clientCreditModal"
                                                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden">
                                                <div class="bg-white rounded-lg p-6 shadow-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                                                    <h2 class="text-lg font-semibold mb-3 text-gray-700">Pay Invoice with Client Credit</h2>
                                                    <p class="text-gray-600 mb-2">Total Credit Balance: <strong>{{ $balanceCredit }} KWD</strong></p>
                                                    <p class="text-gray-600 mb-4">Invoice Amount: <strong>{{ $invoice->amount }} KWD</strong></p>
                                                    
                                                    <!-- Payment Mode Selection -->
                                                    <div class="mb-4">
                                                        <h3 class="font-medium text-gray-700 mb-2">Payment Mode:</h3>
                                                        <div class="flex gap-4">
                                                            <label class="flex items-center cursor-pointer">
                                                                <input type="radio" name="credit_payment_mode" value="full" 
                                                                    class="credit-payment-mode mr-2" checked
                                                                    onchange="updatePaymentMode()">
                                                                <span>Full Payment</span>
                                                            </label>
                                                        </div>
                                                        <p id="paymentModeDescription" class="text-sm text-gray-500 mt-1">
                                                            Credit must cover entire invoice amount.
                                                        </p>
                                                    </div>
                                                    
                                                    <!-- Payment Selection Section -->
                                                    <div class="mb-4">
                                                        <h3 class="font-medium text-gray-700 mb-2">Select Payment(s) to Apply:</h3>
                                                        <div id="availablePaymentsList" class="space-y-2 max-h-60 overflow-y-auto border rounded p-2">
                                                            @forelse($availablePayments as $index => $paymentData)
                                                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded hover:bg-gray-100">
                                                                    <label class="flex items-center flex-1 cursor-pointer">
                                                                        <input type="checkbox" 
                                                                            class="payment-checkbox mr-3" 
                                                                            data-credit-id="{{ $paymentData['credit_id'] }}"
                                                                            data-available-balance="{{ $paymentData['available_balance'] }}"
                                                                            data-voucher="{{ $paymentData['reference_number'] }}"
                                                                            data-source-type="{{ $paymentData['source_type'] }}"
                                                                            data-refund-id="{{ $paymentData['refund_id'] ?? '' }}"
                                                                            data-is-standalone="{{ ($paymentData['is_standalone'] ?? false) ? 'true' : 'false' }}"
                                                                            onchange="updatePaymentSelection()">
                                                                        <div class="flex flex-col">
                                                                            <span class="font-medium text-gray-800">{{ $paymentData['reference_number'] }}</span>

                                                                            <div class="flex items-center gap-2 mt-1">
                                                                                @if($paymentData['source_type'] === 'refund')
                                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                                                                                        </svg>
                                                                                        Refund
                                                                                    </span>
                                                                                @else
                                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                                                                                        </svg>
                                                                                        Topup
                                                                                    </span>
                                                                                @endif
                                                                                <span class="text-xs text-gray-500">{{ $paymentData['date']->format('d M Y') }}</span>
                                                                            </div>
                                                                        </div>
                                                                    </label>
                                                                    <div class="flex items-center gap-2">
                                                                        <span class="text-sm text-green-600">{{ number_format($paymentData['available_balance'], 3) }} KWD</span>
                                                                        <input type="number" 
                                                                            class="payment-amount-input w-24 px-2 py-1 border rounded text-sm"
                                                                            data-credit-id="{{ $paymentData['credit_id'] }}"
                                                                            data-max="{{ $paymentData['available_balance'] }}"
                                                                            data-user-edited="false"
                                                                            data-source-type="{{ $paymentData['source_type'] }}"
                                                                            data-refund-id="{{ $paymentData['refund_id'] ?? '' }}"
                                                                            placeholder="Amount"
                                                                            step="0.001"
                                                                            min="0"
                                                                            max="{{ $paymentData['available_balance'] }}"
                                                                            disabled
                                                                            oninput="markAsUserEdited(this)">
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <p class="text-gray-500 text-center py-4">No available payments or refunds found for this client.</p>
                                                            @endforelse
                                                        </div>

                                                        @if(count($availablePayments) > 0)
                                                            <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                                                <div class="flex items-center gap-1">
                                                                    <span class="w-3 h-3 bg-green-100 rounded"></span>
                                                                    <span>Credit Topup</span>
                                                                </div>
                                                                <div class="flex items-center gap-1">
                                                                    <span class="w-3 h-3 bg-orange-100 rounded"></span>
                                                                    <span>From Refund</span>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    
                                                    <!-- Split Payment Gateway Selection (hidden by default) -->
                                                    <div id="splitGatewaySection" class="mb-4 hidden">
                                                        <h3 class="font-medium text-gray-700 mb-2">Pay Remaining Amount With:</h3>
                                                        <div class="grid grid-cols-2 gap-4">
                                                            <div>
                                                                <label class="block text-sm text-gray-600 mb-1">Payment Gateway</label>
                                                                <select id="splitGateway" class="w-full p-2 border border-gray-300 rounded">
                                                                    @foreach ($paymentGateways as $gateway)
                                                                        <option value="{{ $gateway->name }}" data-charge-id="{{ $gateway->id }}">{{ $gateway->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div id="splitMethodSection">
                                                                <label class="block text-sm text-gray-600 mb-1">Payment Method</label>
                                                                <select id="splitMethod" class="w-full p-2 border border-gray-300 rounded">
                                                                    @foreach ($paymentMethods as $method)
                                                                        <option value="{{ $method->id }}">{{ $method->english_name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <p class="text-sm text-blue-600 mt-2">
                                                            Remaining <span id="splitRemainingAmount">0.000</span> KWD will be paid via selected gateway.
                                                        </p>
                                                    </div>
                                                    
                                                    <!-- Summary Section -->
                                                    <div class="bg-gray-100 rounded p-3 mb-4">
                                                        <div class="flex justify-between mb-1">
                                                            <span>Invoice Amount:</span>
                                                            <span id="creditModalInvoiceAmount">{{ number_format($invoice->amount, 3) }} KWD</span>
                                                        </div>
                                                        <div class="flex justify-between mb-1">
                                                            <span>Credit Selected:</span>
                                                            <span id="creditModalTotalSelected" class="font-medium">0.000 KWD</span>
                                                        </div>
                                                        <div class="flex justify-between border-t pt-1">
                                                            <span>Remaining:</span>
                                                            <span id="creditModalDifference" class="font-bold">{{ number_format($invoice->amount, 3) }} KWD</span>
                                                        </div>
                                                        <div id="creditModalExcessWarning" class="text-amber-600 text-sm mt-2 hidden">
                                                            ⚠️ Excess amount will remain available for future invoices.
                                                        </div>
                                                        <div id="creditModalShortageWarning" class="text-red-600 text-sm mt-2 hidden">
                                                            ❌ Insufficient credit for full payment. Select more payments or change payment mode.
                                                        </div>
                                                        <div id="creditModalSplitInfo" class="text-blue-600 text-sm mt-2 hidden">
                                                            ℹ️ Remaining amount will be charged via selected gateway.
                                                        </div>
                                                        <div id="creditModalPartialInfo" class="text-amber-600 text-sm mt-2 hidden">
                                                            ℹ️ Remaining amount will stay as unpaid balance on invoice.
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-4 flex justify-end gap-2">
                                                        <button onclick="hideModal()"
                                                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
                                                        <button id="applyPaymentsBtn" onclick="applySelectedPayments()"
                                                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                                            disabled>Apply Payments</button>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            @if ($creditUsed && $creditUsed->amount < 0)
                                                <!-- Credit already used - no locking needed, just display -->
                                                <a target="_blank" href="{{ route('invoice.show', ['companyId' => $companyId, 'invoiceNumber' => $invoice->invoice_number])}}">
                                                    <button type="button"
                                                        class="rounded-full flex flex-col items-center justify-center w-full
                                                            px-4 py-2 border border-gray-300 
                                                            bg-green-500 text-white shadow-xl">
                                                        <span>Credit {{ number_format(abs($creditUsed->amount), 3) ?? 0 }} KWD has been utilized.</span>
                                                        <span>Current balance of credit for {{ $selectedClient->first_name }}: {{ $balanceCredit }} KWD</span>
                                                    </button>
                                                </a>
                                            @else
                                                @if ($balanceCredit > 0 && $invoice->payment_type == '')
                                                    <!-- Partial credit available - apply locking -->
                                                    <label class="rounded-full shadow transition-all duration-200 block"
                                                        :class="isTypeLocked('credit') ? 'cursor-not-allowed' : 'cursor-pointer'"
                                                        @click="handlePaymentTypeClick('credit', $event)">
                                                        <button type="button" 
                                                            @click="if(!isTypeLocked('credit')) { generateInvoiceWithCreditModal = true }"
                                                            class="rounded-full flex flex-col items-center justify-center w-full
                                                                px-4 py-2 border border-gray-300 transition gap-2
                                                                bg-white text-gray-700"
                                                            :class="isTypeLocked('credit') 
                                                                ? 'cursor-not-allowed' 
                                                                : 'hover:bg-green-500 hover:text-white hover:shadow-xl cursor-pointer'">
                                                            <span>Still Paying With Client Credit?</span>
                                                            <span>Current balance of credit for {{ $selectedClient->first_name }}: {{ $balanceCredit }} KWD</span>
                                                        </button>
                                                    </label>
                                                @else
                                                    <!-- Just display credit balance - no interaction -->
                                                    <button type="button"
                                                        class="rounded-full flex flex-col items-center justify-center w-full
                                                            px-4 py-2 border border-gray-300 
                                                            bg-white text-gray-700 transition gap-2 shadow cursor-default">
                                                        <span>Current balance of credit for {{ $selectedClient->first_name }}: {{ $balanceCredit }} KWD</span>
                                                    </button>
                                                @endif
                                            @endif
                                            
                                            <!-- Generate Invoice With Credit Modal -->
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
                                                                            ({{ number_format($balanceCredit - $invoice->amount, 3) }}
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

                                    <!-- Full Payment Tab -->
                                    <label class="rounded-full shadow transition-all duration-200"
                                        :class="isTypeLocked('full') ? 'cursor-not-allowed' : 'cursor-pointer'"
                                        @click="handlePaymentTypeClick('full', $event)">
                                        <input type="radio" id="payment_type_full" name="payment_type" value="full"
                                            onclick="hideModal()" hidden class="peer"
                                            {{ $invoice->payment_type == 'full' ? 'checked' : '' }}
                                            :disabled="isTypeLocked('full')" />
                                        <div class="rounded-full flex items-center justify-center 
                                            peer-checked:ring-2 peer-checked:ring-blue-500 
                                            peer-checked:bg-green-500 peer-checked:text-white
                                            px-4 py-2 border border-gray-300 
                                            bg-white text-gray-700 transition gap-2"
                                            :class="isTypeLocked('full') ? 'bg-gray-100 text-gray-400 border-gray-200' : 'hover:bg-green-500 hover:text-white hover:shadow-xl'">
                                            <span class="font-medium">Full Payment</span>
                                        </div>
                                    </label>

                                    <!-- Partial Payment Tab -->
                                    <label class="rounded-full shadow transition-all duration-200"
                                        :class="isTypeLocked('partial') ? 'cursor-not-allowed' : 'cursor-pointer'"
                                        @click="handlePaymentTypeClick('partial', $event)">
                                        <input type="radio" id="payment_type_partial" name="payment_type" value="partial"
                                            onclick="showModal('partial')" hidden class="peer"
                                            {{ $invoice->payment_type == 'partial' ? 'checked' : '' }}
                                            :disabled="isTypeLocked('partial')" />
                                        <div class="rounded-full flex items-center justify-center 
                                            peer-checked:ring-2 peer-checked:ring-blue-500 
                                            peer-checked:bg-green-500 peer-checked:text-white
                                            px-4 py-2 border border-gray-300 
                                            bg-white text-gray-700 transition gap-2"
                                            :class="isTypeLocked('partial') ? 'bg-gray-100 text-gray-400 border-gray-200' : 'hover:bg-green-500 hover:text-white hover:shadow-xl'">
                                            <span class="font-medium">Partial Payment</span>
                                        </div>
                                    </label>

                                    <!-- Split Payment Tab -->
                                    <label class="rounded-full shadow transition-all duration-200"
                                        :class="isTypeLocked('split') ? 'cursor-not-allowed' : 'cursor-pointer'"
                                        @click="handlePaymentTypeClick('split', $event)">
                                        <input type="radio" id="payment_type_split" name="payment_type" value="split"
                                            onclick="showModal('split')" hidden class="peer"
                                            {{ $invoice->payment_type == 'split' ? 'checked' : '' }}
                                            :disabled="isTypeLocked('split')" />
                                        <div class="rounded-full flex items-center justify-center 
                                            peer-checked:ring-2 peer-checked:ring-blue-500 
                                            peer-checked:bg-green-500 peer-checked:text-white
                                            px-4 py-2 border border-gray-300 
                                            bg-white text-gray-700 transition gap-2"
                                            :class="isTypeLocked('split') ? 'bg-gray-100 text-gray-400 border-gray-200' : 'hover:bg-green-500 hover:text-white hover:shadow-xl'">
                                            <span class="font-medium">Split Payment</span>
                                        </div>
                                    </label>

                                    <!-- Import Payment -->
                                    <label class="rounded-full shadow transition-all duration-200"
                                        :class="isTypeLocked('import') ? 'cursor-not-allowed' : 'cursor-pointer'"
                                        @click="handlePaymentTypeClick('import', $event)">
                                        <input type="radio" id="payment_type_import" name="payment_type" value="import"
                                            onclick="showModal('import')" hidden class="peer"
                                            :disabled="isTypeLocked('import')" />
                                        <div class="rounded-full flex items-center justify-center 
                                            peer-checked:ring-2 peer-checked:ring-blue-500 
                                            peer-checked:bg-green-500 peer-checked:text-white
                                            px-4 py-2 border border-gray-300 
                                            bg-white text-gray-700 transition gap-2"
                                            :class="isTypeLocked('import') ? 'bg-gray-100 text-gray-400 border-gray-200' : 'hover:bg-green-500 hover:text-white hover:shadow-xl'">
                                            <span id="openImportModalBtn" class="font-medium">Import Payment</span>
                                        </div>
                                    </label>
                                </div>

                                @if(empty($invoice->payment_type))
                                <!-- Modal -->
                                    <div id="importModal"
                                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50 hidden">
                                        <div class="bg-white rounded-lg p-6 w-full max-w-lg shadow-xl overflow-y-auto" style="max-height: 90vh;">
                                                <!-- Header -->
                                                <div class="flex items-center justify-between mb-6">
                                                    <div>
                                                        <h2 class="text-xl font-bold text-gray-800">Import Payment</h2>
                                                        <p class="text-gray-600 italic text-xs mt-1">
                                                        Import a payment from a Payment Gateway or from a paid Receipt Voucher
                                                        </p>
                                                    </div>
                                                    <button id="closeImportModalBtn" class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">&times;</button>
                                                </div>

                                                <!-- Form -->
                                                <form id="importForm"
                                                    action="{{ route('payment.link.import.payment') }}"
                                                    method="POST"
                                                    class="space-y-4"
                                                    x-data="{ source: 'placeholder', gateway: '' }"
                                                    x-effect="
                                                        if (source !== 'gateway') { gateway=''; }
                                                        if (source !== 'receipt') { $refs.receiptRef && ($refs.receiptRef.value=''); }
                                                    ">
                                                    @csrf

                                                    <!-- Source -->
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Import From</label>
                                                        <select name="source" x-model="source"
                                                                class="block w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                                                                required>
                                                        <option value="placeholder" selected disabled>Select an option</option>
                                                        <option value="gateway">Payment Gateway</option>
                                                        <option value="receipt">Receipt Voucher</option>
                                                        </select>
                                                    </div>

                                                    <!-- Payment Gateway section -->
                                                    <div x-show="source === 'gateway'" x-cloak>
                                                        <div class="mt-4">
                                                        <label for="gateway" class="block text-sm font-medium text-gray-700 mb-1">
                                                            Payment Gateway
                                                        </label>
                                                        <select name="gateway" id="gateway" x-model="gateway"
                                                                class="block w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                                                                :required="source === 'gateway'">
                                                            <option value="" selected disabled hidden>Select Payment Gateway</option>
                                                            @foreach($can_import as $gateway)
                                                            <option value="{{ strtolower($gateway->name) }}">{{ $gateway->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        </div>

                                                        <!-- MyFatoorah -->
                                                        <div x-show="gateway === 'myfatoorah'" class="mt-4" x-cloak>
                                                            <label for="import_invoice_id" class="block text-sm font-medium text-gray-700 mb-1">
                                                                Existing Invoice ID
                                                            </label>
                                                            <input type="text" name="import_invoice_id" id="import_invoice_id"
                                                                    class="block w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                                                                    placeholder="Enter invoice ID"
                                                                    :required="source === 'gateway' && gateway === 'myfatoorah'">
                                                        </div>

                                                        <!-- Hesabe -->
                                                        <div x-show="gateway === 'hesabe'" class="mt-4" x-cloak>
                                                            <label for="import_order_reference" class="block text-sm font-medium text-gray-700 mb-1">
                                                                Existing Order Reference
                                                            </label>
                                                            <input type="text" name="import_order_reference" id="import_order_reference"
                                                                    class="block w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                                                                    placeholder="Enter order reference"
                                                                    :required="source === 'gateway' && gateway === 'hesabe'">
                                                        </div>
                                                    </div>

                                                    <!-- Receipt Voucher section -->
                                                    <div x-show="source === 'receipt'" x-cloak>
                                                        <div class="mt-4">
                                                            <label for="receipt_reference" class="block text-sm font-medium text-gray-700 mb-1">
                                                                Receipt Reference
                                                            </label>


                                                            <x-searchable-dropdown
                                                                name="receipt"
                                                                :items="$receiptVoucher
                                                                    ->filter(fn($r) => $r->transaction)
                                                                    ->map(fn($r) => [
                                                                        'id'   => $r->transaction->reference_number, 
                                                                        'name' => $r->transaction->reference_number 
                                                                                    .' — KWD '.number_format((float)$r->amount, 3),
                                                                    ])"
                                                                :placeholder="'Select Receipt Voucher'"
                                                                :selectedName="old('receipt') ?? ($selectedReceiptRef ?? null)"
                                                                x-model="receipt"
                                                            />

                                                        </div>
                                                    </div>

                                                    <!-- Success -->
                                                    <div id="successBox" class="hidden p-3 bg-green-50 border border-green-200 rounded-md text-green-800 text-sm"></div>

                                                    <!-- Error -->
                                                    <div id="errorBox" class="hidden p-3 bg-red-50 border border-red-200 rounded-md text-red-800 text-sm"></div>

                                                    <!-- Loading -->
                                                    <div id="loadingBox"
                                                        class="hidden p-3 bg-blue-50 border border-blue-200 rounded-md text-blue-800 text-sm flex items-center">
                                                        <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-blue-800" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                            viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                                stroke-width="4"></circle>
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
                                @endif

                                @if($invoice->status !== 'paid')
                                    <!-- Payment Gateway Section -->
                                    <section id="payment_gateway_section" class="mb-6" x-data="{ paymentType: '{{ $invoice->payment_type ?? '' }}' }" x-show="paymentType === '' || paymentType === 'full'" x-cloak>
                                        @php
                                        $selectedGateway = optional($invoice->invoicePartials->first())->payment_gateway ?? '';
                                        $selectedMethod = optional($invoice->invoicePartials->first())->payment_method ?? '';
                                        @endphp
                                        <div id="payment_gateway_dropdowns">
                                            <div x-data="{ 
                                                selectedGateway: '{{ $selectedGateway }}', 
                                                selectedMethod: '{{ $selectedMethod }}', 
                                                paymentType: '{{ $invoice->payment_type ?? '' }}',
                                                }">
                                                <div class="mt-4">
                                                    <div class="flex items-center justify-between">
                                                        <h2 class="text-lg font-semibold mb-3 text-gray-700">Choose Payment Gateway</h2>
                                                        @if(!$isLocked)
                                                        <span 
                                                            x-show="paymentType !== ''" 
                                                            class="text-xs text-blue-500 ml-2 mb-2 cursor-pointer"
                                                            @click="window.updateGateway && window.updateGateway()"
                                                        >
                                                            (Change)
                                                        </span>
                                                        @endif
                                                    </div>
                                                    <select id="payment_gateway_option" name="payment_gateway_option"
                                                        class="border border-gray-300 p-2 rounded w-full" x-model="selectedGateway"
                                                        @if($isLocked) disabled style="appearance: none; background-image: none; opacity: 0.7; pointer-events: none;" @endif>
                                                        <option value="">Choose a Payment Gateway</option>
                                                        @foreach ($paymentGateways as $gateway)
                                                        <option value="{{ $gateway->name }}" {{ $selectedGateway === $gateway->name ? 'selected' : '' }}>
                                                            {{ $gateway->name }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div
                                                    class="block">

                                                    @foreach($paymentGateways as $gateway)
                                                        @php
                                                            $companyMethods = $gateway->methods->where('company_id', $companyId);
                                                        @endphp
                                                        @if($companyMethods->isNotEmpty())
                                                        <template x-if="selectedGateway.toLowerCase() === '{{ strtolower($gateway->name) }}'">
                                                            <div class="mt-4" x-cloak x-transition>
                                                                <label for="payment-method-{{ strtolower($gateway->name) }}" class="block text-sm font-medium text-gray-700">Payment Method</label>
                                                                <select name="payment_method" id="payment_method_full"
                                                                    class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                                                    x-model="selectedMethod" @change="calculateSubtotal()"
                                                                    @if($isLocked) disabled style="appearance: none; background-image: none; opacity: 0.7; pointer-events: none;" @endif>
                                                                    @if($companyMethods->count() > 1)
                                                                    <option value="">Select Payment Method</option>
                                                                    @endif
                                                                    @foreach ($companyMethods as $method)
                                                                    <option value="{{ $method->id }}" {{ $selectedMethod == $method->id ? 'selected' : '' }}>
                                                                        {{ $method->english_name }}
                                                                    </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </template>
                                                        @endif
                                                    @endforeach
                                                </div>
                                                <input type="hidden" name="payment_method" :value="selectedMethod">

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
                                                    <div class="mb-3">
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Charge Amount:</label>
                                                        <input type="number" id="invoice_charge_amount_input" name="invoice_charge_amount_input"
                                                            class="form-input" step="0.01" min="0" value="{{ $invoice->invoice_charge }}"
                                                            placeholder="Enter charge amount"
                                                            oninput="document.getElementById('invoice_charge').value = this.value || 0; calculateSubtotal();">
                                                        <input type="hidden" id="invoice_charge_amount" name="invoice_charge_amount" value="{{ $invoice->invoice_charge }}">
                                                    </div>
                                                </div>
                                                @endif

                                                <!-- External URL Field -->
                                                <div class="mt-4" id="external_url_section" style="display: none;">
                                                    <h2 class="text-lg font-semibold mb-3 text-gray-700">External Payment URL (Optional)</h2>
                                                    <input type="url" id="external_url" name="external_url"
                                                        class="border border-gray-300 p-2 rounded w-full"
                                                        placeholder="Enter payment gateway URL (optional)"
                                                        value="{{ $invoice->external_url ?? '' }}">
                                                    <p class="text-sm text-gray-500 mt-1">Optionally provide an external payment gateway URL for this invoice</p>
                                                </div>
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

                                                <span id="button-text-full">Save Payment</span>
                                            </button>

                                        </div>
                                    </section>
                                @endif

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
                                        <div class="my-2 flex items-center w-full">
                                            <a target="_blank" href="{{ route('invoice.proforma', ['companyId' => $companyId, 'invoiceNumber' => $invoiceNumber]) }}"
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
                                        <a target="_blank" href="{{ route('invoice.show', ['companyId' => $companyId, 'invoiceNumber' => $invoiceNumber]) }}"
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

                                        <p id="copyFeedback" class="mt-2 text-sm text-green-600 hidden">Link copied to clipboard!</p>
                                    </div>
                                </section>

                                <section id="email-actions" class="mt-4">
                                    <h2 id="quick-actions-header" class="text-lg font-semibold mb-3 text-gray-700">Quick Actions</h2>
                                    <div class="flex flex-wrap gap-4">
                                        <button type="button" id="openSendEmailModal" class="w-full flex items-center justify-center py-3 px-5 text-sm text-white bg-indigo-600 hover:bg-indigo-900 rounded-full transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                            </svg>
                                            Send Invoice Email
                                        </button>
                                        <div id="sendEmailModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50 hidden">
                                            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                                                <div class="flex items-center justify-between mb-4">
                                                    <h2 class="text-xl font-bold text-gray-800">Send Invoice via Email</h2>
                                                    <button type="button" id="closeSendEmailModal" class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                                                </div>

                                                <form id="sendEmailForm" class="space-y-4">
                                                    @csrf
                                                    
                                                    <div class="space-y-3">
                                                        <p class="text-sm text-gray-600">Select recipients for invoice <strong>{{ $invoiceNumber }}</strong></p>
                                                        <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                                            <input type="checkbox" name="send_to_agent" id="send_to_agent" value="1" 
                                                                class="form-checkbox h-5 w-5 text-indigo-600 rounded" checked>
                                                            <div class="ml-3">
                                                                <span class="block font-medium text-gray-800">Agent</span>
                                                                <span class="block text-sm text-gray-500">{{ $selectedAgent->email ?? 'No email available' }}</span>
                                                            </div>
                                                        </label>
                                                        <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                                            <input type="checkbox" name="send_to_client" id="send_to_client" value="1"
                                                                class="form-checkbox h-5 w-5 text-indigo-600 rounded">
                                                            <div class="ml-3">
                                                                <span class="block font-medium text-gray-800">Client</span>
                                                                <span class="block text-sm text-gray-500">{{ $selectedClient->email ?? 'No email available' }}</span>
                                                            </div>
                                                        </label>
                                                        <div class="pt-2">
                                                            <label class="block text-sm font-medium text-gray-700 mb-1">Additional Email Addresses</label>
                                                            <input type="text" name="custom_emails" id="custom_emails" 
                                                                class="w-full border border-gray-300 rounded-lg p-2 text-sm"
                                                                placeholder="email1@example.com, email2@example.com">
                                                            <p class="text-xs text-gray-500 mt-1">Separate multiple emails with commas</p>
                                                        </div>
                                                    </div>

                                                    <div id="emailSuccessMessage" class="hidden p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm"></div>
                                                    <div id="emailErrorMessage" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm"></div>

                                                    <div class="flex justify-between pt-4">
                                                        <button type="button" id="cancelSendEmail"
                                                            class="px-4 py-2 border border-gray-300 rounded-full text-gray-700 hover:bg-gray-100 transition">
                                                            Cancel
                                                        </button>
                                                        <button type="submit" id="submitSendEmail"
                                                            class="px-6 py-2 bg-indigo-600 text-white rounded-full hover:bg-indigo-700 transition flex items-center">
                                                            <span id="sendEmailBtnText">Send Email</span>
                                                            <span id="sendEmailSpinner" class="hidden ml-2">
                                                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                </svg>
                                                            </span>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-1">

                            <input id="invoiceId" type="hidden" name="invoiceId" />

                            <div id="errorMessage" class="hidden text-red-500">
                                <!-- Error message -->
                            </div>

                            <!-- Split Payment -->
                            <div id="splitPaymentModal" class="fixed inset-0 z-50 hidden bg-gray-800/50 p-4 md:p-6 grid place-items-center overscroll-contain">
                                <div class="bg-white rounded-lg shadow-lg w-full max-w-[1300px] max-h-[85vh] flex flex-col transition-all duration-300 ease-out">

                                    <!-- Header -->
                                    <div class="px-6 py-4 border-b sticky top-0 bg-white rounded-t-lg">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-xl font-bold">Split Payment Details</h3>
                                            <button type="button" onclick="hideModal()"
                                                class="inline-flex items-center justify-center w-8 h-8 text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Body -->
                                    <div class="bg-gray-100 p-6 flex-1 overflow-y-auto">
                                        <div id="split-payment-container" class="space-y-5">

                                            <div class="flex items-start gap-5 mb-5">
                                                <div class="flex-1 flex items-center justify-between gap-4 p-4 bg-white border border-gray-200 rounded-lg flex-wrap">
                                                     <div>
                                                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Client Name</label>
                                                        <span class="receiver-name-display text-sm font-semibold text-gray-900">AHMED</span>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Client's Credit</label>
                                                        <span class="text-sm font-semibold text-green-600">{{ $balanceCredit }} KWD</span>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Invoice Number</label>
                                                        <span class="text-sm font-semibold text-gray-900">{{ $invoiceNumber }}</span>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Invoice Total</label>
                                                        <span id="subT1" class="text-sm font-semibold text-gray-900">0.000 KWD</span>
                                                    </div>
                                                    <div id="total-split-container" class="hidden">
                                                        <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
                                                            Split Payment Total
                                                            <span id="total-split-tooltip" class="hidden relative cursor-help"
                                                                onmouseenter="this.querySelector('.tooltip-box').classList.remove('opacity-0','invisible')"
                                                                onmouseleave="this.querySelector('.tooltip-box').classList.add('opacity-0','invisible')">
                                                                <svg class="w-3.5 h-3.5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                                </svg>
                                                                <span class="tooltip-box opacity-0 invisible absolute bottom-full right-0 mb-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs font-normal normal-case tracking-normal rounded-md whitespace-nowrap transition-all duration-150 pointer-events-none z-50">
                                                                    <span id="total-split-tooltip-text">Split total doesn't match invoice</span>
                                                                    <span class="absolute top-full right-2 border-4 border-transparent border-t-gray-800"></span>
                                                                </span>
                                                            </span>
                                                        </label>
                                                        <span id="total-split-display" class="text-sm font-semibold text-blue-600">0.000 KWD</span>
                                                    </div>
                                                </div>

                                                <div id="split-into-initial-wrap" class="w-36 shrink-0">
                                                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1" for="split-into">Split into *</label>
                                                    <select id="split-into"
                                                            class="w-full p-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
                                                            onchange="updateRowSplit()">
                                                        <option value="" disabled selected>Installments</option>
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

                                            <!-- Breakdown section (hidden until installments selected) -->
                                            <div id="split-breakdown-section"
                                                style="display: none; opacity: 0; transform: translateY(-8px); transition: opacity 0.2s ease-out, transform 0.2s ease-out;">

                                                <div class="flex items-center justify-between mb-3">
                                                    <h2 class="text-lg font-semibold text-gray-700">Split Payment Breakdown</h2>

                                                    <!-- Moved split-into dropdown (visible after selection) -->
                                                    <div id="split-into-moved-wrap" class="hidden flex items-center gap-2">
                                                        <label class="text-xs font-semibold text-gray-400 uppercase tracking-wide" for="split-into-moved">Split into *</label>
                                                        <select id="split-into-moved"
                                                                class="w-24 p-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
                                                                onchange="updateRowSplitFromMoved()">
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

                                                <!-- Cards rendered here by JS -->
                                                <div id="split-rows" class="space-y-3"></div>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- Footer Split -->
                                    <div class="px-6 py-4 border-t sticky bottom-0 bg-white rounded-b-lg">
                                        <div class="flex items-center justify-between">
                                            <button onclick="hideModal()" class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md shadow">Cancel</button>

                                            <div class="flex items-center gap-6">
                                                <!-- Summary (hidden until installments selected) -->
                                                <div id="split-footer-summary" class="hidden flex items-center gap-6">
                                                    <div class="text-right">
                                                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wide">Invoice Total</span>
                                                        <span id="invoice-total-display" class="invoice-total-display text-sm font-semibold text-gray-900">0.000 KWD</span>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wide">Split Total</span>
                                                        <span id="split-footer-split-total" class="text-sm font-semibold text-gray-900">0.000 KWD</span>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="flex items-center justify-end gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                                            Balance
                                                            <span id="split-footer-balance-tooltip" class="hidden relative cursor-help"
                                                                onmouseenter="this.querySelector('.tooltip-box').classList.remove('opacity-0','invisible')"
                                                                onmouseleave="this.querySelector('.tooltip-box').classList.add('opacity-0','invisible')">
                                                                <svg class="w-3.5 h-3.5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                                </svg>
                                                                <span class="tooltip-box opacity-0 invisible absolute bottom-full right-0 mb-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs font-normal normal-case tracking-normal rounded-md whitespace-nowrap transition-all duration-150 pointer-events-none z-50">
                                                                    <span id="split-footer-balance-tooltip-text">Payment doesn't match invoice total</span>
                                                                    <span class="absolute top-full right-2 border-4 border-transparent border-t-gray-800"></span>
                                                                </span>
                                                            </span>
                                                        </span>
                                                        <span id="split-footer-balance" class="text-sm font-bold text-green-600">0.000 KWD</span>
                                                    </div>

                                                    <!-- Charge Summary (hidden until charges exist) -->
                                                    <div id="split-footer-charge-summary" class="hidden flex items-center gap-6 pl-4 ml-4 border-l border-dashed border-gray-300">
                                                        <div class="text-right">
                                                            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wide">Service Charge</span>
                                                            <span id="split-footer-service-charge" class="text-sm font-semibold text-amber-600">0.000 KWD</span>
                                                        </div>
                                                        <div class="text-right">
                                                            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wide">Client Pays</span>
                                                            <span id="split-footer-client-pays" class="text-sm font-bold text-gray-900">0.000 KWD</span>
                                                        </div>
                                                    </div>

                                                    <div class="h-10 w-px bg-gray-300"></div>
                                                </div>

                                                <button id="splitbutton" onclick="savePartial('split')" type="button"
                                                    class="inline-flex items-center justify-center text-sm text-black font-semibold city-light-yellow hover:bg-[#004c9e] hover:text-white py-2 px-10 rounded-lg shadow">
                                                    <span id="button-icon-split" class="mr-2"></span>
                                                    <span id="button-text-split">Save Split Payment</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Partial Payment -->
                            <div id="partialPaymentModal" class="fixed inset-0 z-50 hidden bg-gray-800/50 p-4 md:p-6 grid place-items-center overscroll-contain">
                                <div class="bg-white rounded-lg shadow-lg w-full max-w-[1300px] max-h-[85vh] flex flex-col transition-all duration-300 ease-out">
                                    
                                    <!-- Header -->
                                    <div class="px-6 py-4 border-b sticky top-0 bg-white rounded-t-lg">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-xl font-bold">Partial Payment Details</h3>
                                            <button type="button" onclick="hideModal()"
                                                class="inline-flex items-center justify-center w-8 h-8 text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Body -->
                                    <div class="bg-gray-100 p-6 flex-1 overflow-y-auto">
                                        <div id="partial-payment-container" class="space-y-5">
                                            <div class="flex items-start gap-5 mb-5">
                                                <div class="flex-1 flex items-center justify-between gap-4 p-4 bg-white border border-gray-200 rounded-lg flex-wrap">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Client Name</label>
                                                        <span class="receiver-name-display text-sm font-semibold text-gray-900">AHMED</span>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Client's Credit</label>
                                                        <span class="text-sm font-semibold text-green-600">{{ $balanceCredit }} KWD</span>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Invoice Number</label>
                                                        <span class="text-sm font-semibold text-gray-900">{{ $invoiceNumber }}</span>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Invoice Total</label>
                                                        <span id="subT1" class="text-sm font-semibold text-gray-900">0.00</span>
                                                    </div>
                                                    <div id="total-partial-container" class="hidden">
                                                        <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
                                                            Partial Payment Total
                                                            <span id="total-partial-tooltip" class="hidden relative cursor-help"
                                                                onmouseenter="this.querySelector('.tooltip-box').classList.remove('opacity-0','invisible')"
                                                                onmouseleave="this.querySelector('.tooltip-box').classList.add('opacity-0','invisible')">
                                                                <svg class="w-3.5 h-3.5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                                </svg>
                                                                <span class="tooltip-box opacity-0 invisible absolute bottom-full right-0 mb-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs font-normal normal-case tracking-normal rounded-md whitespace-nowrap transition-all duration-150 pointer-events-none z-50">
                                                                    <span id="total-partial-tooltip-text">Partial payment total doesn't match invoice</span>
                                                                    <span class="absolute top-full right-2 border-4 border-transparent border-t-gray-800"></span>
                                                                </span>
                                                            </span>
                                                        </label>
                                                        <span id="total-partial-display" class="text-sm font-semibold text-blue-600">0.000 KWD</span>
                                                    </div>
                                                </div>
                                                
                                                <div id="split-into-initial" class="w-36 shrink-0">
                                                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1" for="split-into1">Split into *</label>
                                                    <select id="split-into1"
                                                            class="w-full p-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
                                                            onchange="updateRowPartial()">
                                                        <option value="" disabled selected>Installments</option>
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
                                            
                                            <div id="partial-breakdown-section" 
                                                style="display: none; opacity: 0; transform: translateY(-8px); transition: opacity 0.2s ease-out, transform 0.2s ease-out;">
                                                
                                                <div class="flex items-center justify-between mb-3">
                                                    <h2 class="text-lg font-semibold text-gray-700">Partial Payment Breakdown</h2>
                                                    
                                                    <!-- Moved Split Into Position (visible after selection) -->
                                                   <div id="partial-split-into-moved" class="hidden flex items-center gap-2">
                                                        <label class="text-xs font-semibold text-gray-400 uppercase tracking-wide" for="split-into1-moved">Split into *</label>
                                                        <select id="split-into1-moved"
                                                                class="w-24 p-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500"
                                                                onchange="updateRowPartialFromMoved()">
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
                                                
                                                <div id="partial-rows" class="space-y-3">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Footer Partial -->
                                    <div class="px-6 py-4 border-t sticky bottom-0 bg-white rounded-b-lg">
                                        <div class="flex items-center justify-between">
                                            <button onclick="hideModal()" class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-md shadow">Cancel</button>
                                            
                                            <!-- Summary + Save Button -->
                                            <div class="flex items-center gap-6">
                                                <!-- Financial Summary (hidden until installments selected) -->
                                                <div id="footer-summary" class="hidden flex items-center gap-6">
                                                    <div class="text-right">
                                                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wide">Invoice Total</span>
                                                        <span id="invoice-total-display" class="invoice-total-display text-sm font-semibold text-gray-900">0.000 KWD</span>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wide">Partial Total</span>
                                                        <span id="footer-partial-total" class="text-sm font-semibold text-gray-900">0.000 KWD</span>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="flex items-center justify-end gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">
                                                            Balance
                                                            <span id="footer-balance-tooltip" class="hidden relative cursor-help"
                                                                onmouseenter="this.querySelector('.tooltip-box').classList.remove('opacity-0','invisible')"
                                                                onmouseleave="this.querySelector('.tooltip-box').classList.add('opacity-0','invisible')">
                                                                <svg class="w-3.5 h-3.5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                                </svg>
                                                                <span class="tooltip-box opacity-0 invisible absolute bottom-full right-0 mb-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs font-normal normal-case tracking-normal rounded-md whitespace-nowrap transition-all duration-150 pointer-events-none z-50">
                                                                    <span id="footer-balance-tooltip-text">Payment doesn't match invoice total</span>
                                                                    <span class="absolute top-full right-2 border-4 border-transparent border-t-gray-800"></span>
                                                                </span>
                                                            </span>
                                                        </span>
                                                        <span id="footer-balance" class="text-sm font-bold text-green-600">0.000 KWD</span>
                                                    </div>

                                                    <!-- Charge Summary (hidden until charges exist) -->
                                                    <div id="footer-charge-summary" class="hidden flex items-center gap-6 pl-4 ml-4 border-l border-dashed border-gray-300">
                                                        <div class="text-right">
                                                            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wide">Service Charge</span>
                                                            <span id="footer-service-charge" class="text-sm font-semibold text-amber-600">0.000 KWD</span>
                                                        </div>
                                                        <div class="text-right">
                                                            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wide">Client Pays</span>
                                                            <span id="footer-client-pays" class="text-sm font-bold text-gray-900">0.000 KWD</span>
                                                        </div>
                                                    </div>

                                                    <!-- Vertical Divider -->
                                                    <div class="h-10 w-px bg-gray-300"></div>
                                                </div>
                                                
                                                <button id="partialbutton" onclick="savePartial('partial')" type="button" class="inline-flex items-center justify-center text-sm text-black font-semibold city-light-yellow hover:bg-[#004c9e] hover:text-white py-2 px-10 rounded-lg shadow">
                                                    <span id="button-icon-partial" class="mr-2"></span>
                                                    <span id="button-text-partial">Save Partial Payment</span>
                                                </button>
                                            </div>
                                        </div>
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
    </div>

    <!-- Payment Selection Module for Credit Payments -->
    <script src="{{ asset('js/invoice-payment-selection.js') }}"></script>
    
    <script>
        let invoice = @json($invoice);
        let items = [];
        let tasks = [];
        const itemsBody = document.getElementById('items-body');
        const appUrl = @json($appUrl);
        const charges = @json($paymentGateways);
        const invoiceCharges = @json($invoiceCharges);
        const clients = @json($clients);
        const partialCredit = Number(@json(\App\Models\Credit::getTotalCreditsByClient($invoice->client_id)) || 0);
        let creditRemaining = partialCredit;
        const creditUsed = {};
        const chargeBreakdownStore = { partial: {}, split: {} };

        // Refund invoice: use the pre-calculated invoice.amount instead of sum of task prices
        const isRefundInvoice = @json((bool) $invoice->refund);
        const refundInvoiceAmount = @json((float) $invoice->amount);
        const invoiceAmount = parseFloat("{{ $invoice->amount }}") || 0;
        let currentPaymentMode = 'full';
        const paymentMethods = Array.isArray(@json($paymentMethods)) ? @json($paymentMethods) : [];

        // Simple Invoice Charge Function
        function updateInvoiceChargeFromInput() {
            const invoiceChargeAmountInput = document.getElementById('invoice_charge_amount_input');
            const invoiceChargeAmount = parseFloat(invoiceChargeAmountInput.value) || 0;

            // Validate and prevent negative values
            if (invoiceChargeAmount < 0) {
                invoiceChargeAmountInput.value = 0;
                showValidationError(invoiceChargeAmountInput, 'Charge amount cannot be negative');
                return;
            } else {
                hideValidationError(invoiceChargeAmountInput);
            }

            // Update hidden fields
            const invoiceChargeElement = document.getElementById('invoice_charge');
            const invoiceChargeAmountHidden = document.getElementById('invoice_charge_amount');

            if (invoiceChargeElement) {
                invoiceChargeElement.value = invoiceChargeAmount.toFixed(3);
            }
            if (invoiceChargeAmountHidden) {
                invoiceChargeAmountHidden.value = invoiceChargeAmount.toFixed(3);
            }

            // Update displays
            calculateSubtotal();
        }

        function resetInvoiceCharge() {
            const invoiceChargeAmountInput = document.getElementById('invoice_charge_amount_input');
            const invoiceChargeElement = document.getElementById('invoice_charge');
            const invoiceChargeAmountHidden = document.getElementById('invoice_charge_amount');
            const invoiceChargeDisplay = document.getElementById('invoiceChargeDisplay');

            if (invoiceChargeAmountInput) invoiceChargeAmountInput.value = '';
            if (invoiceChargeElement) invoiceChargeElement.value = '0';
            if (invoiceChargeAmountHidden) invoiceChargeAmountHidden.value = '0';
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
                const creditPaymentRadio = document.getElementById('payment_type_credit');

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

                if (creditPaymentRadio) {
                    creditPaymentRadio.disabled = true;
                    creditPaymentRadio.parentElement.style.opacity = '0.5';
                    creditPaymentRadio.parentElement.style.pointerEvents = 'none';
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

        // Gateway selection setup function - will be called in main DOMContentLoaded
        function setupGatewaySelection() {
            const gatewaySelect = document.getElementById('payment_gateway_option');
            if (gatewaySelect) {
                gatewaySelect.addEventListener('change', function() {
                    checkExternalUrlVisibility();
                    calculateSubtotal(); // Recalculate when gateway changes
                });
                // Check on initial load
                checkExternalUrlVisibility();
                calculateSubtotal(); // Calculate on initial load
            }

            // Invoice Charge Event Listeners
            const invoiceChargeAmountInput = document.getElementById('invoice_charge_amount_input');

            if (invoiceChargeAmountInput) {
                invoiceChargeAmountInput.addEventListener('input', updateInvoiceChargeFromInput);
                invoiceChargeAmountInput.addEventListener('change', updateInvoiceChargeFromInput);

                // Add real-time validation for negative values
                invoiceChargeAmountInput.addEventListener('input', function() {
                    validateInvoiceChargeAmount(this);
                });

                // Prevent negative values on keydown
                invoiceChargeAmountInput.addEventListener('keydown', function(e) {
                    // Prevent typing minus sign
                    if (e.key === '-' || e.key === 'Minus') {
                        e.preventDefault();
                    }
                });

                // Validate on blur (when user leaves the field)
                invoiceChargeAmountInput.addEventListener('blur', function() {
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
        }

        // Helper function to check if a gateway can charge invoice
        function canGatewayChargeInvoice(gatewayName) {
            if (!gatewayName) return false;
            const gateway = charges.find(c => c.name === gatewayName);
            return gateway && gateway.can_charge_invoice === true;
        }

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
        const paymentTypeImport = document.getElementById('payment_type_import');
        const paymentTypeCredit = document.getElementById('payment_type_credit');
        const isInvoicePaid = "{{ $invoice->status === 'paid' }}"
        const hasPaymentType = "{{ !empty($invoice->payment_type) }}";

        const isInvoiceLocked = @json($isLocked ?? false);

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

        let clientCredits = [];

        function checkInvoiceId(forcedType) {
            let paymentType = forcedType;
            if (!paymentType) {
                const checked = document.querySelector('input[name="payment_type"]:checked');
                paymentType = checked ? checked.value : undefined;
            }

            const paymentGatewaySection = document.getElementById('payment_gateway_section');
            const additionalActions = document.getElementById('additional-actions');
            const paymentGatewayDropdowns = document.getElementById('payment_gateway_dropdowns');
            const splitPaymentModal = document.getElementById('splitPaymentModal');
            const partialPaymentModal = document.getElementById('partialPaymentModal');
            const creditModal = document.getElementById('clientCreditModal');
            const quickActionsHeader = document.getElementById('quick-actions-header');

            const show = (el) => el && (el.style.display = 'block');
            const hide = (el) => el && (el.style.display = 'none');

            if (paymentType === 'full') {
                show(paymentGatewaySection);
                show(additionalActions);
                hide(quickActionsHeader);
                paymentGatewayDropdowns?.classList.remove('hidden');
                hideModal();
            } else if (paymentType === 'partial') {
                show(paymentGatewaySection);
                show(additionalActions);
                hide(quickActionsHeader);
                paymentGatewayDropdowns?.classList.add('hidden');
            } else if (paymentType === 'split') {
                show(paymentGatewaySection);
                show(additionalActions);
                hide(quickActionsHeader);
                paymentGatewayDropdowns?.classList.add('hidden');
            } else if (paymentType === 'credit') {
                show(paymentGatewaySection);
                show(additionalActions);
                hide(quickActionsHeader);
                paymentGatewayDropdowns?.classList.add('hidden');
                creditModal?.classList.add('hidden');
            } else {
                hide(paymentGatewaySection);
                hide(additionalActions);
                show(quickActionsHeader);
            }
        }

        // Setup save button click handler - will be called in main DOMContentLoaded
        function setupSaveButton() {
            const saveBtn = document.getElementById('update-invoice-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    // Check which payment type is selected
                    const selectedPaymentType = document.querySelector('input[name="payment_type"]:checked');

                    savePartial('full');
                });
            }

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
        }

        // Consolidated DOMContentLoaded event handler
        document.addEventListener('DOMContentLoaded', function() {
            // Setup all initialization functions
            setupGatewaySelection();
            setupSaveButton();
            setupPaymentTypesAndTasks();
            setupImportModal();
            setupSendEmailModal();

            if (isInvoiceLocked) {
                lockInvoicePage();
            }

            // Run initial checks
            checkInvoiceId();

            // Setup invoice ID input listener
            invoiceIdInput.addEventListener('input', checkInvoiceId);

            // Calculate subtotal after all initialization is complete
            // Use setTimeout to ensure all DOM elements are fully rendered
            setTimeout(() => {
                calculateSubtotal();
            }, 100);
        });

        // Set initial states
        let isSaving = false;
        let isSaved = false;

        function showModal(type) {
            hideModal();

            creditSelectionState = { selectedInstallment: null, totalCreditSelected: 0, remainingBalance: 0 };
            for (const k in creditUsed) delete creditUsed[k];
            PaymentSelection.clearAllSelections(); 

            if (type == 'split') {
                document.getElementById('splitPaymentModal').classList.remove('hidden');
                const splitInto = document.getElementById('split-into');
                if (splitInto) { splitInto.value = ''; updateRowSplit(); }
            } else if (type == 'partial') {
                document.getElementById('partialPaymentModal').classList.remove('hidden');
                const splitInto1 = document.getElementById('split-into1');
                if (splitInto1) { splitInto1.value = ''; updateRowPartial(); }
            } else if (type == 'credit') {
                document.getElementById('clientCreditModal')?.classList.remove('hidden');
            }
        }

        function hideModal() {
            document.getElementById('splitPaymentModal')?.classList.add('hidden');
            document.getElementById('partialPaymentModal')?.classList.add('hidden');
            document.getElementById('clientCreditModal')?.classList.add('hidden');

            // Clear row containers to prevent duplicate element IDs
            // (split and partial both use amount_1, amount_2, etc.)
            const splitRows = document.getElementById('split-rows');
            if (splitRows) splitRows.innerHTML = '';

            const partialRows = document.getElementById('partial-rows');
            if (partialRows) partialRows.innerHTML = '';
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

        /* function updateCreditRow(rowNumber, clientId) {
            const selectedClient = clients.filter(client => client.id == clientId)[0];
            clientCredits[rowNumber] = selectedClient?.total_credit || 0;

            // Update credit display
            const creditDisplayElement = document.getElementById(`credit_display_${rowNumber}`);
            if (creditDisplayElement) {
                creditDisplayElement.innerText = `Credit: ${clientCredits[rowNumber]}`;
            } else {
                console.error('Credit display element not found:', `credit_display_${rowNumber}`);
            }

            // Update credit payment gateway option
            const creditOption = document.getElementById(`credit_option_${rowNumber}`);
            if (creditOption) {
                const creditAmount = clientCredits[rowNumber];
                creditOption.textContent = `Credit (${creditAmount})`;

                if (creditAmount > 0) {
                    creditOption.disabled = false;
                    creditOption.style.color = '#000'; // Enable styling
                } else {
                    creditOption.disabled = true;
                    creditOption.style.color = '#9ca3af'; // Gray out when disabled
                }
            } else {
                console.error('Credit option not found:', `credit_option_${rowNumber}`);
            }
        } */

        function updateCreditRow(rowNumber, clientId) {
            const selectedClientObj = clients.find(c => c.id == clientId);
            const credit = selectedClientObj?.total_credit || 0;
            clientCredits[rowNumber] = credit;

            // Update credit display under client dropdown
            const creditDisplayEl = document.getElementById(`credit_display_${rowNumber}`);
            if (creditDisplayEl) creditDisplayEl.textContent = `Credit: ${parseFloat(credit).toFixed(3)} KWD`;

            // Update credit option in gateway dropdown
            const creditOption = document.getElementById(`credit_option_${rowNumber}`);
            if (creditOption) {
                creditOption.textContent = `Credit (${parseFloat(credit).toFixed(3)})`;
                creditOption.disabled = credit <= 0;
                creditOption.style.color = credit > 0 ? '#000' : '#9ca3af';
            }
        }

        function gwKey(s) {
            return (s || '').toString().trim().toLowerCase().replace(/[\s_-]+/g, '');
        }

        const methodsByGateway = paymentMethods.reduce((acc, method) => {
            const key = gwKey(method.type ?? method.gateway ?? method.provider ?? '');
            if (!key) return acc;
            (acc[key] ||= []).push(method);
            return acc;
        }, {});

        function renderMethodOptions(selectEl, methods) {
            selectEl.innerHTML = methods.map(method => `<option value="${method.id}">${method.english_name}</option>`).join('');
        }

        function updateRowSplit() {
            const splitIntoEl  = document.getElementById('split-into');
            const splitInto    = parseInt(splitIntoEl.value) || 0;
            const totalAmount  = parseFloat(document.getElementById('subTotal')?.value) || 0;

            const section      = document.getElementById('split-breakdown-section');
            const initialWrap  = document.getElementById('split-into-initial-wrap');
            const movedWrap    = document.getElementById('split-into-moved-wrap');
            const movedSelect  = document.getElementById('split-into-moved');

            // ── Update the live invoice-total display in the info bar ────────────────
            const splitInvTotalEl = document.getElementById('split-invoice-total-display');
            if (splitInvTotalEl) {
                const invTotal = parseFloat(document.getElementById('subTotal')?.value) || 0;
                splitInvTotalEl.textContent = invTotal.toFixed(3) + ' KWD';
            }

            if (splitInto <= 0) {
                initialWrap.classList.remove('hidden');
                movedWrap.classList.add('hidden');
                section.style.opacity = '0';
                section.style.transform = 'translateY(-8px)';
                setTimeout(() => { section.style.display = 'none'; }, 200);
                document.getElementById('total-split-container')?.classList.add('hidden');
                document.getElementById('split-footer-summary')?.classList.add('hidden');
                return;
            }

            // Show badge and switch dropdown position
            initialWrap.classList.add('hidden');
            movedWrap.classList.remove('hidden');
            if (movedSelect) movedSelect.value = splitInto;

            section.style.display = 'block';
            requestAnimationFrame(() => {
                section.style.opacity  = '1';
                section.style.transform = 'translateY(0)';
            });

            // Show summary containers
            document.getElementById('total-split-container')?.classList.remove('hidden');
            const footerSummary = document.getElementById('split-footer-summary');
            if (footerSummary) {
                footerSummary.classList.remove('hidden');
                footerSummary.classList.add('flex');
            }

            // ── Calculate even-split base amounts ───────────────────────────────────
            const baseAmount    = Math.floor((totalAmount / splitInto) * 1000) / 1000;
            const totalBase     = baseAmount * splitInto;
            const remainder     = Math.round((totalAmount - totalBase) * 1000) / 1000;

            const container = document.getElementById('split-rows');
            container.innerHTML = '';

            for (let i = 1; i <= splitInto; i++) {
                const rowAmount = (i === splitInto)
                    ? (baseAmount + remainder).toFixed(3)
                    : baseAmount.toFixed(3);
                createSplitInstallmentCard(i, rowAmount, container);
            }

            updatePaymentSummary('split');
        }

        function createSplitInstallmentCard(i, initialAmount, container) {
            const wrapper = document.createElement('div');
            wrapper.className = 'flex gap-4';

            const card = document.createElement('div');
            card.className = 'flex-1 bg-white border border-gray-200 rounded-lg p-5 hover:shadow-sm transition-shadow';

            // Build options from the existing `clients` JS variable
            const clientOptions = clients.map(c => {
                const label = (c.full_name || c.name || '') + (c.phone ? ' - ' + c.phone : '');
                return `<option value="${c.id}">${label}</option>`;
            }).join('');

            card.innerHTML = `
                <!-- Card Header -->
                <div class="flex items-center justify-between mb-5">
                    <span class="text-sm font-semibold text-blue-700 uppercase tracking-wide">
                        Installment ${i}
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">
                        <span id="split_card_amount_badge_${i}">${parseFloat(initialAmount).toFixed(3)} KWD</span>
                    </span>
                </div>

                <!-- Client -->
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Client *</label>
                    <div id="searchable_dropdown_${i}" class="w-full relative">
                        <button type="button"
                                onclick="toggleSearchableDropdown(${i})"
                                class="w-full border border-gray-300 p-2 rounded text-sm text-left bg-white text-black">
                            <span id="selected_text_${i}" class="text-gray-400">Select Client</span>
                        </button>

                        <input type="hidden" name="customer_name_${i}" id="customer_name_${i}">

                        <div id="dropdown_${i}" style="display: none;"
                            class="fixed z-[9999] bg-white border rounded shadow-lg">
                            <div class="px-2 py-2">
                                <input type="text"
                                    id="search_input_${i}"
                                    placeholder="Search clients..."
                                    onkeyup="filterClientsSplit(${i}, this.value)"
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-black">
                            </div>
                            <div id="options_container_${i}" class="max-h-40 overflow-y-auto">
                                ${clients.map(client => `
                                    <div class="p-2 hover:bg-gray-100 cursor-pointer text-sm client-option"
                                        data-client-id="${client.id}"
                                        data-client-name="${(client.full_name || client.name || '').replace(/"/g, '&quot;')}"
                                        data-row-index="${i}">
                                        ${client.full_name || client.name || 'Unnamed Client'}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expiry Date & Amount -->
                <div class="flex justify-between gap-4 mb-4">
                    <div class="w-5/12">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Expiry Date</label>
                        <input type="date" id="date_${i}" name="date_${i}" value="${invoiceExpireDefault}"
                            class="w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm" />
                    </div>
                    <div class="w-5/12">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Amount (KWD)</label>
                        <input type="number" id="amount_${i}" name="amount_${i}" value="${initialAmount}"
                            class="w-full p-2 text-sm border border-gray-300 rounded-md no-spin shadow-sm"
                            onblur="handleSplitAmountChange(${i})"
                            oninput="handleSplitAmountChange(${i})" />
                    </div>
                </div>

               <!-- Gateway & Method -->
                <div class="flex justify-between gap-4 mb-4">
                    <div class="w-5/12 relative">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
                            Payment Gateway
                            <span id="split_charge_tooltip_${i}" class="hidden relative cursor-help"
                                onmouseenter="this.querySelector('.tooltip-box').classList.remove('opacity-0','invisible')"
                                onmouseleave="this.querySelector('.tooltip-box').classList.add('opacity-0','invisible')">
                                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="16" x2="12" y2="12"/>
                                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                                </svg>
                                <span class="tooltip-box opacity-0 invisible absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs font-normal normal-case tracking-normal rounded-md whitespace-nowrap transition-all duration-150 pointer-events-none z-10">
                                    <span id="split_charge_tooltip_text_${i}">Invoice charge not supported</span>
                                    <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"></span>
                                </span>
                            </span>
                        </label>
                        <select id="payment_gateway_${i}" name="payment_gateway_${i}"
                                class="w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm"
                                onchange="handleGatewayChangeSplit(${i})">
                            <option value="">Select gateway</option>
                            <option value="Credit" id="credit_option_${i}" disabled>Credit (0.000)</option>
                            ${charges.map(gw => `<option value="${gw.name}">${gw.name}</option>`).join('')}
                        </select>
                        <span id="split_no_method_tooltip_${i}" class="hidden absolute -right-6 bottom-2 cursor-help"
                            onmouseenter="this.querySelector('.tooltip-box').classList.remove('opacity-0','invisible')"
                            onmouseleave="this.querySelector('.tooltip-box').classList.add('opacity-0','invisible')">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="16" x2="12" y2="12"/>
                                <line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                            <span class="tooltip-box opacity-0 invisible absolute bottom-full right-0 mb-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs font-normal rounded-md whitespace-nowrap transition-all duration-150 pointer-events-none z-10">
                                No specific method required for this gateway
                                <span class="absolute top-full right-2 border-4 border-transparent border-t-gray-800"></span>
                            </span>
                        </span>
                    </div>
                    <div class="w-5/12">
                        <div id="payment_method_container_${i}" class="hidden">
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Payment Method</label>
                            <select id="payment_method_${i}" name="payment_method_${i}"
                                class="w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm"></select>
                        </div>
                    </div>
                </div>

                <!-- Invoice Charge (hidden by default) -->
                <div id="split_invoice_charge_wrapper_${i}" class="hidden mb-2">
                    <div class="w-5/12">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
                            Invoice Charge
                            <span class="relative cursor-help"
                                onmouseenter="this.querySelector('.tooltip-box').classList.remove('opacity-0','invisible')"
                                onmouseleave="this.querySelector('.tooltip-box').classList.add('opacity-0','invisible')">
                                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="16" x2="12" y2="12"/>
                                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                                </svg>
                                <span class="tooltip-box opacity-0 invisible absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs font-normal normal-case tracking-normal rounded-md whitespace-nowrap transition-all duration-150 pointer-events-none z-10">
                                    <span id="split_invoice_charge_tooltip_text_${i}">Gateway charge</span>
                                    <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"></span>
                                </span>
                            </span>
                        </label>
                        <input type="number" id="split_invoice_charge_${i}" name="split_invoice_charge_${i}" 
                            class="w-full p-2 text-sm border border-gray-300 rounded-md no-spin shadow-sm focus:ring-blue-500 focus:border-blue-500" 
                            value="0" step="0.001" min="0" placeholder="0.000" />
                    </div>
                </div>

                <input type="hidden" id="split_invoice_charge_${i}_fallback" name="split_invoice_charge_${i}" value="0" />

                <!-- Separator + Payment Breakdown -->
                <div id="split_charge_breakdown_${i}" class="hidden mb-4">
                    <hr class="my-4 border-dashed border-gray-300" />
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-md font-semibold text-gray-400 uppercase tracking-wide">Payment Breakdown</label>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">Paid By:</span>
                            <span id="split_charge_paid_by_${i}" class="text-sm font-medium">-</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-md p-3 text-sm">
                        <!-- Base Amount -->
                        <div class="flex justify-between items-center pb-2 mb-1 border-b border-gray-200">
                            <span class="text-md text-gray-500 font-medium uppercase tracking-wide mb-1">Base Amount</span>
                            <span id="split_charge_base_amount_${i}" class="font-semibold text-gray-700 tabular-nums">0.000</span>
                        </div>

                        <!-- Base + Service -->
                        <div class="flex justify-between items-start py-1.5 border-b border-gray-100 pl-4">
                            <div class="flex flex-col">
                                <span class="text-md text-gray-600">Base + Service Charge</span>
                                <span class="text-sm text-gray-500 pl-4">
                                    Service Charge (<span id="split_charge_fee_percent_${i}">0%</span>): <span id="split_charge_fee_${i}" class=" text-gray-500">0.000</span> KWD
                                </span>
                                <span class="text-sm text-gray-500 pl-4">Rounding Off: <span id="split_charge_fee_ceiled_${i}" class="text-gray-800 font-bold">0.000</span> KWD
                                </span>
                                <span class="text-sm text-gray-500 pl-4">
                                    Profit: <span id="split_charge_fee_profit_${i}" class="text-green-600 font-bold">0.000</span><span class="text-green-600"> KWD</span>
                                </span>
                            </div>
                            <span id="split_charge_subtotal_service_${i}" class="font-semibold text-gray-700">0.000</span>
                        </div>

                        <!-- Base + Invoice Charge -->
                        <div id="split_breakdown_invoice_row_${i}" class="flex justify-between items-start py-1.5 border-b border-gray-100 pl-4">
                            <div class="flex flex-col">
                                <span class="text-md text-gray-600">Base + Invoice Charge</span>
                                <span class="text-sm text-gray-500 pl-4">Invoice Charge: <span id="split_charge_invoice_charge_${i}" class="text-sm text-gray-800 font-bold">0.000</span> KWD</span>
                            </div>
                            <span id="split_charge_subtotal_invoice_${i}" class="font-semibold text-gray-700">0.000</span>
                        </div>

                        <div class="flex justify-between items-start pt-2 mt-1 font-bold">
                            <div class="flex flex-col">
                                <span class="text-md uppercase tracking-wide mb-1">Grand Total</span>
                                <span id="split_grand_total_subtext_${i}" class="text-sm font-normal text-gray-500 tabular-nums hidden">Base + Service Charge + Invoice Charge</span>
                            </div>
                            <span id="split_charge_total_${i}" class="tabular-nums">0.000</span>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="invoice_charge_${i}" name="invoice_charge_${i}" value="0" />
            `;

            // Credit panel setup
            const creditPanel = document.createElement('div');
            creditPanel.id = `credit_panel_${i}`;  // ← use `i` not `installmentIndex`
            creditPanel.className = 'hidden w-120 bg-white border-blue-200 border-2 rounded-lg p-4 transition-all duration-300 self-start';
            creditPanel.innerHTML = createCreditSelectionPanel('split', i, initialAmount);

            wrapper.appendChild(card);         // card is flex child of wrapper
            wrapper.appendChild(creditPanel);  // panel is flex child of wrapper
            container.appendChild(wrapper);    // wrapper goes into container

            container.appendChild(wrapper);

            // Wire gateway change for tooltip + invoice charge visibility
            const gatewaySelect = card.querySelector(`#payment_gateway_${i}`);
            const methodContainer = card.querySelector(`#payment_method_container_${i}`);
            const methodSelect2 = card.querySelector(`#payment_method_${i}`);

            function updateSplitMethodVisibility() {
                const selectedGateway = gatewaySelect.value;
                const key = gwKey(selectedGateway);
                const methods = methodsByGateway[key] || [];
                const noMethodTooltip = card.querySelector(`#split_no_method_tooltip_${i}`);

                if (methods.length > 0) {
                    renderMethodOptions(methodSelect2, methods);
                    methodContainer.classList.remove('hidden');
                    if (noMethodTooltip) noMethodTooltip.classList.add('hidden');
                } else {
                    methodContainer.classList.add('hidden');
                    methodSelect2.value = '';
                    if (noMethodTooltip) noMethodTooltip.classList.remove('hidden');
                }

                const chargeWrapper = card.querySelector(`#split_invoice_charge_wrapper_${i}`);
                const chargeInput = card.querySelector(`#split_invoice_charge_${i}`);
                const chargeTooltip = card.querySelector(`#split_charge_tooltip_${i}`);
                const chargeTooltipText = card.querySelector(`#split_charge_tooltip_text_${i}`);
                const invoiceChargeTooltipText = card.querySelector(`#split_invoice_charge_tooltip_text_${i}`);
                const canCharge = canGatewayChargeInvoice(selectedGateway);

                if (canCharge) {
                    chargeWrapper.classList.remove('hidden');
                    chargeTooltip.classList.add('hidden');
                    if (invoiceChargeTooltipText) {
                        invoiceChargeTooltipText.textContent = `${selectedGateway} charge`;
                    }
                } else {
                    chargeWrapper.classList.add('hidden');
                    chargeInput.value = '0';
                    if (selectedGateway) {
                        chargeTooltip.classList.remove('hidden');
                        chargeTooltipText.textContent = `Invoice charge not supported for ${selectedGateway}`;
                    } else {
                        chargeTooltip.classList.add('hidden');
                    }
                }

                const invoiceBreakdownRow = card.querySelector(`#split_breakdown_invoice_row_${i}`);
                const grandTotalSubtext   = card.querySelector(`#split_grand_total_subtext_${i}`);

                if (canCharge) {
                    invoiceBreakdownRow?.classList.remove('hidden');
                    grandTotalSubtext?.classList.remove('hidden');
                } else {
                    invoiceBreakdownRow?.classList.add('hidden');
                    grandTotalSubtext?.classList.add('hidden');
                }
            }

            // Remove inline onchange and wire properly
            gatewaySelect.removeAttribute('onchange');
            gatewaySelect.addEventListener('change', () => {
                handleGatewayChangeSplit(i);
                updateSplitMethodVisibility();
            });

            updateSplitMethodVisibility(); // run once on initial render

            // Wire method change + amount blur for charge calculation
            const methodSelect = document.getElementById(`payment_method_${i}`);
            if (methodSelect) {
                methodSelect.addEventListener('change', () => fetchChargeBreakdown('split', i));
            }
            
            const amountInput = card.querySelector(`#amount_${i}`);
            if (amountInput) {
                let debounceTimer;
                amountInput.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => fetchChargeBreakdown('split', i), 400);
                });
            }

            const invoiceChargeEl = card.querySelector(`#split_invoice_charge_${i}`);
            if (invoiceChargeEl) {
                invoiceChargeEl.addEventListener('input', () => updateInvoiceChargeInBreakdown('split', i));
                invoiceChargeEl.addEventListener('blur',  () => updateInvoiceChargeInBreakdown('split', i));
            }
        }

        function updateInvoiceChargeInBreakdown(mode, i) {
            const isPartial = mode === 'partial';

            const invoiceChargeElId = isPartial ? `partial_invoice_charge_${i}` : `split_invoice_charge_${i}`;
            const breakdownElId     = isPartial ? `charge_breakdown_${i}`       : `split_charge_breakdown_${i}`;
            const prefix            = isPartial ? 'charge'                      : 'split_charge';

            const invoiceChargeEl = document.getElementById(invoiceChargeElId);
            const invoiceCharge   = parseFloat(invoiceChargeEl?.value) || 0;

            const store     = chargeBreakdownStore[mode];
            const breakdown = store[i];

            const breakdownEl = document.getElementById(breakdownElId);
            if (!breakdownEl || breakdownEl.classList.contains('hidden')) return;

            const baseAmount = parseFloat(document.getElementById(`amount_${i}`)?.value) || 0;
            const gatewayFee  = breakdown?.gatewayFee || 0;
            const ceiledFee   = breakdown?.ceiledFee  || Math.ceil(gatewayFee * 100) / 100;

            const subtotalService = baseAmount + ceiledFee;   // ← use ceiledFee
            const subtotalInvoice = baseAmount + invoiceCharge;
            const grandTotal      = baseAmount + ceiledFee + invoiceCharge;  // ← use ceiledFee

            const invoiceChargeRow  = document.getElementById(`${prefix}_invoice_charge_${i}`);
            const subtotalServiceEl = document.getElementById(`${prefix}_subtotal_service_${i}`);
            const subtotalInvoiceEl = document.getElementById(`${prefix}_subtotal_invoice_${i}`);
            const totalEl           = document.getElementById(`${prefix}_total_${i}`);

            if (invoiceChargeRow)  invoiceChargeRow.textContent  = invoiceCharge.toFixed(3);
            if (subtotalServiceEl) subtotalServiceEl.textContent = subtotalService.toFixed(3);
            if (subtotalInvoiceEl) subtotalInvoiceEl.textContent = subtotalInvoice.toFixed(3);
            if (totalEl)           totalEl.textContent           = grandTotal.toFixed(3);

            if (store[i]) {
                store[i].invoiceCharge = invoiceCharge;
                store[i].finalAmount   = grandTotal;
            }

            updatePaymentSummary(mode);
        }

        function toggleSearchableDropdown(rowIndex) {
            const dropdown = document.getElementById(`dropdown_${rowIndex}`);
            const searchInput = document.getElementById(`search_input_${rowIndex}`);
            const button = document.querySelector(`#searchable_dropdown_${rowIndex} button`);

            // Close all other dropdowns first
            document.querySelectorAll('[id^="dropdown_"]').forEach(dd => {
                if (dd.id !== `dropdown_${rowIndex}`) {
                    dd.style.display = 'none';
                }
            });

            if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                // ✅ Move to body to escape modal transform/overflow context
                if (dropdown.parentElement !== document.body) {
                    document.body.appendChild(dropdown);
                }

                const buttonRect = button.getBoundingClientRect();
                const dropdownWidth = Math.max(320, buttonRect.width);

                dropdown.style.position = 'fixed';
                dropdown.style.zIndex   = '9999';
                dropdown.style.top      = (buttonRect.bottom + 4) + 'px';
                dropdown.style.left     = buttonRect.left + 'px';
                dropdown.style.width    = dropdownWidth + 'px';

                // Prevent going off-screen right
                const viewportWidth = window.innerWidth;
                if (buttonRect.left + dropdownWidth > viewportWidth) {
                    dropdown.style.left = (viewportWidth - dropdownWidth - 10) + 'px';
                }

                dropdown.style.display = 'block';
                searchInput.focus();
                searchInput.value = '';
                filterClientsSplit(rowIndex, '');
            } else {
                dropdown.style.display = 'none';
            }
        }

        function handleSplitAmountChange(i) {
            const amountInput = document.getElementById(`amount_${i}`);
            const badge       = document.getElementById(`split_card_amount_badge_${i}`);
            const amount      = parseFloat(amountInput?.value) || 0;

            if (badge) badge.textContent = amount.toFixed(3) + ' KWD';
            updatePaymentSummary('split');
            checkInputAmount('split', i);
        }

        function handleGatewayChangeSplit(i) {
            const gatewaySelect   = document.getElementById(`payment_gateway_${i}`);
            const selectedGw      = gatewaySelect.value;
            const methodContainer = document.getElementById(`payment_method_container_${i}`);
            const methodSelect    = document.getElementById(`payment_method_${i}`);

            const key     = gwKey(selectedGw);
            const methods = methodsByGateway[key] || [];
            if (methods.length > 0 && selectedGw !== 'Credit') {
                renderMethodOptions(methodSelect, methods);
                methodContainer.classList.remove('hidden');
            } else {
                methodContainer.classList.add('hidden');
                if (methodSelect) methodSelect.value = '';
            }

            // Delegate credit logic to shared handler
            handleCreditGatewayChange('split', i);

            // Fetch charge after method options are rendered
            requestAnimationFrame(() => fetchChargeBreakdown('split', i));
        }

        function updateRowSplitFromMoved() {
            const movedSelect    = document.getElementById('split-into-moved');
            const originalSelect = document.getElementById('split-into');
            if (movedSelect && originalSelect) {
                originalSelect.value = movedSelect.value;
                updateRowSplit();
            }
        }

        function updateCreditUI(splitCount) {
            updateAllCreditOptions('partial'); // ← was updateCreditOptionAvailability()

            for (let i = 1; i <= splitCount; i++) {
                const opt = document.getElementById(`credit_option1_${i}`);
                if (!opt) continue;
                const amt = Number(document.getElementById(`amount_${i}`)?.value || 0);
                const usesCredit = Number(creditUsed[i] || 0) > 0;
                opt.disabled = !usesCredit && (amt <= 0 || amt > creditRemaining);
                opt.textContent = `Credit (${creditRemaining.toFixed(3)})`;
                opt.style.color = opt.disabled ? '#9ca3af' : '#000';
            }
        }

        // Global state for credit selection tracking
        let creditSelectionState = {
            selectedInstallment: null,
            totalCreditSelected: 0,
            remainingBalance: 0
        };

        function updateRowPartial() {
            const splitInto1El = document.getElementById('split-into1');
            if (!splitInto1El) {
                console.error('❌ split-into1 element not found');
                return;
            }
            const section = document.getElementById('partial-breakdown-section');
            const splitInto1 = parseInt(document.getElementById('split-into1').value) || 0;
            
            const initialDropdown = document.getElementById('split-into-initial');
            const movedDropdown = document.getElementById('partial-split-into-moved'); // ← FIXED
            const movedSelect = document.getElementById('split-into1-moved');

            if (splitInto1 > 0) {
                if (initialDropdown) initialDropdown.classList.add('hidden');
                if (movedDropdown) movedDropdown.classList.remove('hidden');
                if (movedSelect) movedSelect.value = splitInto1;

                section.style.display = 'block';
                requestAnimationFrame(() => {
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                });
            } else {
                if (initialDropdown) initialDropdown.classList.remove('hidden');
                if (movedDropdown) movedDropdown.classList.add('hidden');
                updatePaymentSummary('partial');
                section.style.opacity = '0';
                section.style.transform = 'translateY(-8px)';
                setTimeout(() => { section.style.display = 'none'; }, 200);
                return;
            }

            const totalAmount1 = parseFloat(document.getElementById('subTotal').value) || 0;

            document.querySelectorAll('#subT1, .invoice-total-display').forEach(el => {
                el.textContent = totalAmount1.toFixed(3);
            });

            // NEW (fixed) — floor each installment, put remainder on the last one
            const baseAmount1    = Math.floor((totalAmount1 / splitInto1) * 1000) / 1000;
            const totalBase1     = baseAmount1 * splitInto1;
            const remainder1     = Math.round((totalAmount1 - totalBase1) * 1000) / 1000;

            const container = document.getElementById('partial-rows');

            container.innerHTML = '';

            creditRemaining = partialCredit;
            creditSelectionState = { selectedInstallment: null, totalCreditSelected: 0, remainingBalance: totalAmount1 };
            for (const k in creditUsed) delete creditUsed[k];

            for (let i = 1; i <= splitInto1; i++) {
                const rowAmount = (i === splitInto1)
                    ? (baseAmount1 + remainder1).toFixed(3)
                    : baseAmount1.toFixed(3);
                createPartialInstallmentCard(i, rowAmount, totalAmount1, container, splitInto1);
            }

            updateCreditUI(splitInto1);
            updatePaymentSummary('partial');
        }

        /* Partial Payment Modal  */
        function createPartialInstallmentCard(installmentIndex, initialAmount, totalInvoiceAmount, container, totalInstallments) {
            const wrapper = document.createElement('div');
            wrapper.className = 'flex gap-4';
            
            const card = document.createElement('div');
            card.className = 'flex-1 bg-white border border-gray-200 rounded-lg p-5 hover:shadow-sm transition-shadow';
            
            card.innerHTML = `
                <!-- Card Header -->
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center">
                        <span class="inline-flex items-center text-sm font-semibold text-blue-700 uppercase tracking-wide mb-1">
                            Installment ${installmentIndex}
                        </span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">
                        <span id="card_amount_badge_${installmentIndex}">${parseFloat(initialAmount).toFixed(3)} KWD</span>
                    </span>
                </div>

                <!-- Row 1: Date & Amount -->
                <div class="flex justify-between gap-4 mb-4">
                    <div class="w-5/12">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Expiry Date</label>
                        <input type="date" id="date_${installmentIndex}" name="date_${installmentIndex}" value="${invoiceExpireDefault}" 
                            class="w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" />
                    </div>
                    <div class="w-5/12">
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Amount (KWD)</label>
                        <input type="number" id="amount_${installmentIndex}" name="amount_${installmentIndex}" value="${initialAmount}"
                            class="w-full p-2 text-sm border border-gray-300 rounded-md no-spin shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            onblur="handleAmountChange('partial', ${installmentIndex})" 
                            oninput="handleAmountChange('partial', ${installmentIndex})" />
                    </div>
                </div>

                <!-- Row 2: Gateway & Method -->
                <div class="flex justify-between gap-4 mb-4">
                    <div class="w-5/12 relative">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
                            Payment Gateway
                            <span id="partial_charge_tooltip_${installmentIndex}" class="hidden relative cursor-help"
                                onmouseenter="this.querySelector('.tooltip-box').classList.remove('opacity-0','invisible')"
                                onmouseleave="this.querySelector('.tooltip-box').classList.add('opacity-0','invisible')">
                                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="16" x2="12" y2="12"/>
                                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                                </svg>
                                <span class="tooltip-box opacity-0 invisible absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs font-normal normal-case tracking-normal rounded-md whitespace-nowrap transition-all duration-150 pointer-events-none z-10">
                                    <span id="partial_charge_tooltip_text_${installmentIndex}">Invoice charge not supported</span>
                                    <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"></span>
                                </span>
                            </span>
                        </label>
                        <select id="payment_gateway1_${installmentIndex}" class="w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="" selected>Select gateway</option>
                            <option value="Credit" id="credit_option1_${installmentIndex}">
                                Credit (${partialCredit.toFixed(3)})
                            </option>
                            ${charges.map(gateway => `<option value="${gateway.name}">${gateway.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="w-5/12" id="method_wrapper_${installmentIndex}">
                        <div id="payment_method_container1_${installmentIndex}" class="hidden">
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Payment Method</label>
                            <select id="payment_method1_${installmentIndex}" name="payment_method1_${installmentIndex}" class="w-full p-2 text-sm border border-gray-300 rounded-md shadow-sm"></select>
                        </div>
                    </div>
                </div>

                <!-- Invoice Charge (hidden by default) -->
                <div id="partial_invoice_charge_wrapper_${installmentIndex}" class="hidden mb-2">
                    <div class="w-5/12">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
                            Invoice Charge
                            <span class="relative cursor-help"
                                onmouseenter="this.querySelector('.tooltip-box').classList.remove('opacity-0','invisible')"
                                onmouseleave="this.querySelector('.tooltip-box').classList.add('opacity-0','invisible')">
                                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="16" x2="12" y2="12"/>
                                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                                </svg>
                                <span class="tooltip-box opacity-0 invisible absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2.5 py-1.5 bg-gray-800 text-white text-xs font-normal normal-case tracking-normal rounded-md whitespace-nowrap transition-all duration-150 pointer-events-none z-10">
                                    <span id="partial_invoice_charge_tooltip_text_${installmentIndex}">Gateway charge</span>
                                    <span class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"></span>
                                </span>
                            </span>
                        </label>
                        <input type="number" id="partial_invoice_charge_${installmentIndex}" name="invoice_charge1_${installmentIndex}"
                            class="w-full p-2 text-sm border border-gray-300 rounded-md no-spin shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            value="0" step="0.001" min="0" placeholder="0.000" />
                    </div>
                </div>

                <!-- Payment Breakdown -->
                <div id="charge_breakdown_${installmentIndex}" class="hidden mb-4">
                    <hr class="my-4 border-dashed border-gray-300" />
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-md font-semibold text-gray-400 uppercase tracking-wide mb-1">Payment Breakdown</label>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500 uppercase tracking-wide">Paid By:</span>
                            <span id="charge_paid_by_${installmentIndex}" class="text-sm font-medium uppercase tracking-wide">-</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-md p-3 text-sm">
                        <!-- Base Amount -->
                        <div class="flex justify-between items-center pb-2 mb-1 border-b border-gray-200">
                            <span class="text-md text-gray-500 font-medium uppercase tracking-wide mb-1">Base Amount</span>
                            <span class="font-semibold text-gray-700"><span id="charge_base_${installmentIndex}" class="font-semibold text-gray-700">0.000</span> KWD</span>
                        </div>
                        <!-- Base + Service -->
                        <div class="flex justify-between items-start py-1.5 border-b border-gray-100 pl-4">
                            <div class="flex flex-col">
                                <span class="text-md text-gray-600">Base + Service Charge</span>
                                <span class="text-sm text-gray-500 pl-4">
                                    Service Charge (<span id="charge_fee_percent_${installmentIndex}" class="text-gray-500">0%</span>): 
                                    <span id="charge_fee_${installmentIndex}" class="text-gray-500">0.000</span> KWD
                                </span>
                                <span class="text-sm text-gray-500 pl-4">
                                    Rounding Off: <span id="charge_fee_ceiled_${installmentIndex}" class="text-gray-800 font-bold">0.000</span> KWD
                                </span>
                                <span class="text-sm text-gray-500 pl-4">
                                    Profit: <span id="charge_fee_profit_${installmentIndex}" class="text-green-600 font-bold">0.000</span><span class="text-green-600"> KWD</span>
                                </span>
                            </div>
                            <span class="font-semibold text-gray-700">
                                <span id="charge_subtotal_service_${installmentIndex}" class="font-semibold text-gray-700">0.000</span> KWD
                            </span>
                        </div>
                        <!-- Base + Invoice Charge -->
                        <div id="partial_breakdown_invoice_row_${installmentIndex}" class="hidden flex justify-between items-start py-1.5 border-b border-gray-100 pl-5">
                            <div class="flex flex-col pl-4 text-gray-600">
                                <span class="text-md text-gray-600">Base + Invoice Charge</span>
                                <span class="text-sm text-gray-500 pl-4">Invoice Charge: <span id="charge_invoice_charge_${installmentIndex}" class="text-sm font-bold text-gray-800">0.000</span> KWD</span>
                            </div>
                            <span class="font-semibold text-gray-700"><span id="charge_subtotal_invoice_${installmentIndex}" class="font-semibold text-gray-700">0.000</span> KWD</span>
                        </div>
                        <!-- Grand Total -->
                        <div class="flex justify-between items-start pt-2 mt-1 font-bold">
                            <div class="flex flex-col">
                                <span class="text-md uppercase tracking-wide mb-1">Grand Total</span>
                                <span id="partial_grand_total_subtext_${installmentIndex}" class="text-sm font-normal text-gray-500 hidden">Base + Service Charge + Invoice Charge</span>
                            </div>
                            <span><span id="charge_total_${installmentIndex}" class="tabular-nums">0.000</span> KWD</span>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="invoice_charge1_${installmentIndex}" name="invoice_charge1_${installmentIndex}" value="0" />
            `;

            // Credit panel setup
            const creditPanel = document.createElement('div');
            creditPanel.id = `credit_panel_${installmentIndex}`;
            creditPanel.className = 'hidden w-120 bg-white border-blue-200 border-2 rounded-lg p-4 transition-all duration-300 self-start';
            creditPanel.innerHTML = createCreditSelectionPanel('partial', installmentIndex, initialAmount);

            wrapper.appendChild(card);
            wrapper.appendChild(creditPanel);
            container.appendChild(wrapper);

            // Wire gateway change — inline closure like split
            const gatewaySelect = card.querySelector(`#payment_gateway1_${installmentIndex}`);

            function updatePartialMethodVisibility() {
                const selectedGateway = gatewaySelect.value;
                const canCharge = canGatewayChargeInvoice(selectedGateway);

                const chargeWrapper          = card.querySelector(`#partial_invoice_charge_wrapper_${installmentIndex}`);
                const chargeInput            = card.querySelector(`#partial_invoice_charge_${installmentIndex}`);
                const chargeTooltip          = card.querySelector(`#partial_charge_tooltip_${installmentIndex}`);
                const chargeTooltipText      = card.querySelector(`#partial_charge_tooltip_text_${installmentIndex}`);
                const invoiceTooltipText     = card.querySelector(`#partial_invoice_charge_tooltip_text_${installmentIndex}`);
                const invoiceBreakdownRow    = card.querySelector(`#partial_breakdown_invoice_row_${installmentIndex}`);
                const grandTotalSubtext      = card.querySelector(`#partial_grand_total_subtext_${installmentIndex}`);

                if (canCharge) {
                    chargeWrapper?.classList.remove('hidden');
                    chargeTooltip?.classList.add('hidden');
                    invoiceBreakdownRow?.classList.remove('hidden');
                    grandTotalSubtext?.classList.remove('hidden');
                    if (invoiceTooltipText) invoiceTooltipText.textContent = `${selectedGateway} charge`;
                } else {
                    chargeWrapper?.classList.add('hidden');
                    if (chargeInput) chargeInput.value = '0';
                    invoiceBreakdownRow?.classList.add('hidden');
                    grandTotalSubtext?.classList.add('hidden');
                    if (selectedGateway) {
                        chargeTooltip?.classList.remove('hidden');
                        if (chargeTooltipText) chargeTooltipText.textContent = `Invoice charge not supported for ${selectedGateway}`;
                    } else {
                        chargeTooltip?.classList.add('hidden');
                    }
                }
            }

            gatewaySelect.addEventListener('change', () => {
                updatePartialMethodVisibility();
                fetchChargeBreakdown('partial', installmentIndex);

                // Wire method select after gateway populates it
                setTimeout(() => {
                    const methodSelect = card.querySelector(`#payment_method1_${installmentIndex}`);
                    if (methodSelect) {
                        methodSelect.addEventListener('change', () => fetchChargeBreakdown('partial', installmentIndex));
                    }
                }, 100);
            });

            const amountInput = card.querySelector(`#amount_${installmentIndex}`);
            if (amountInput) {
                amountInput.addEventListener('input', () => fetchChargeBreakdown('partial', installmentIndex));
                amountInput.addEventListener('blur',  () => fetchChargeBreakdown('partial', installmentIndex));
            }

            const methodSelect = card.querySelector(`#payment_method1_${installmentIndex}`);
            if (methodSelect) {
                methodSelect.addEventListener('change', () => fetchChargeBreakdown('partial', installmentIndex));
            }
            updatePartialMethodVisibility(); // run once on initial render

            // Wire invoice charge input
            const invoiceChargeEl = card.querySelector(`#partial_invoice_charge_${installmentIndex}`);
            if (invoiceChargeEl) {
                invoiceChargeEl.addEventListener('input', () => updateInvoiceChargeInBreakdown('partial', installmentIndex));
                invoiceChargeEl.addEventListener('blur',  () => updateInvoiceChargeInBreakdown('partial', installmentIndex));
            }

            setupInstallmentEventHandlers('partial', installmentIndex, totalInvoiceAmount, totalInstallments);
        }

        function createCreditSelectionPanel(mode, installmentIndex, requiredAmount) {
            const vouchersId = mode === 'split'
                ? `credit_vouchers_split_${installmentIndex}`
                : `credit_vouchers_${installmentIndex}`;

            return `
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-1">
                        CREDIT SELECTION
                    </h4>
                    <span class="text-xs font-semibold text-blue-700 bg-blue-100 px-2 py-1 rounded-full">
                        Available: <span id="credit_available_display_${installmentIndex}">${partialCredit.toFixed(3)}</span> KWD
                    </span>
                </div>
                
                <div id="${vouchersId}" class="space-y-2 max-h-64 overflow-y-auto mb-4">
                    <div class="text-center text-sm text-gray-500 py-4">
                        <div class="animate-pulse">Loading available vouchers...</div>
                    </div>
                </div>
                
                <div id="credit_summary_${installmentIndex}" class="pt-3 mt-3 border-t border-blue-200">
                    <div class="flex justify-between text-xs text-gray-600 mb-2">
                        <span>Selected:</span>
                        <strong class="text-green-600" id="credit_selected_${installmentIndex}">0.000 KWD</strong>
                    </div>
                </div>
            `;
        }

        function handleGatewayChangePartial(installmentIndex) {
            const gatewaySelect = document.getElementById(`payment_gateway1_${installmentIndex}`);
            updatePaymentMethodVisibility(installmentIndex, gatewaySelect.value);
            handleCreditGatewayChange('partial', installmentIndex);

            // Delay so the method <select> DOM is populated before we read its value
            requestAnimationFrame(() => fetchChargeBreakdown('partial', installmentIndex));
        }

        /* Charge Calculation in Installment Card */
        async function fetchChargeBreakdown(mode, i) {
            const isPartial   = mode === 'partial';
            const gatewayId   = isPartial ? `payment_gateway1_${i}` : `payment_gateway_${i}`;
            const methodId    = isPartial ? `payment_method1_${i}`  : `payment_method_${i}`;
            const breakdownId = isPartial ? `charge_breakdown_${i}` : `split_charge_breakdown_${i}`;
            const prefix      = isPartial ? 'charge'                : 'split_charge';

            const gateway     = document.getElementById(gatewayId)?.value;
            const method      = document.getElementById(methodId)?.value;
            const amount      = parseFloat(document.getElementById(`amount_${i}`)?.value) || 0;
            const breakdownEl = document.getElementById(breakdownId);
            const store       = chargeBreakdownStore[mode];

            if (!gateway || gwKey(gateway) === gwKey('Credit') || amount <= 0) {
                breakdownEl?.classList.add('hidden');
                delete store[i];
                updatePaymentSummary(mode);
                return;
            }

            const invoiceChargeInputId = isPartial ? `invoice_charge1_${i}` : `split_invoice_charge_${i}`;
            const invoiceCharge = parseFloat(document.getElementById(invoiceChargeInputId)?.value) || 0;

            try {
                const response = await fetch("{{ route('invoice.calculate-charge') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    },
                    body: JSON.stringify({
                        amount: amount,
                        gateway: gateway,
                        method: method ? parseInt(method) : null,
                        invoice_id: {{ $invoice->id }},
                    }),
                });

                const data = await response.json();

                const gatewayFee = parseFloat(data.gatewayFee || 0);

                if (isPartial) {
                    const ceiledFee     = Math.ceil(gatewayFee * 100) / 100;   // ← MOVE THIS UP FIRST
                    const grandTotal    = amount + ceiledFee + invoiceCharge;   // ← now ceiledFee exists
                    const profit        = parseFloat((ceiledFee - gatewayFee).toFixed(3));
                    const chargePercent = amount > 0 ? ((gatewayFee / amount) * 100).toFixed(2) : '0.00';

                    document.getElementById(`${prefix}_base_${i}`).textContent    = amount.toFixed(3);
                    document.getElementById(`${prefix}_fee_${i}`).textContent     = gatewayFee.toFixed(3);
                    document.getElementById(`${prefix}_paid_by_${i}`).textContent = data.paid_by || '-';
                    document.getElementById(`${prefix}_total_${i}`).textContent   = grandTotal.toFixed(3);

                    const feePercentEl = document.getElementById(`charge_fee_percent_${i}`);
                    const feeProfitEl  = document.getElementById(`charge_fee_profit_${i}`);
                    const feeCeiledEl  = document.getElementById(`charge_fee_ceiled_${i}`);

                    if (feePercentEl) feePercentEl.textContent = `${chargePercent}%`;
                    if (feeProfitEl)  feeProfitEl.textContent  = profit.toFixed(3);
                    if (feeCeiledEl)  feeCeiledEl.textContent  = ceiledFee.toFixed(3);

                    const subtotalServiceEl = document.getElementById(`charge_subtotal_service_${i}`);
                    const subtotalInvoiceEl = document.getElementById(`charge_subtotal_invoice_${i}`);
                    const invoiceChargeRow  = document.getElementById(`charge_invoice_charge_${i}`);
                    if (subtotalServiceEl) subtotalServiceEl.textContent = (amount + ceiledFee).toFixed(3);
                    if (subtotalInvoiceEl) subtotalInvoiceEl.textContent = (amount + invoiceCharge).toFixed(3);
                    if (invoiceChargeRow)  invoiceChargeRow.textContent  = invoiceCharge.toFixed(3);

                    store[i] = {
                        gatewayFee:    gatewayFee,
                        ceiledFee:     ceiledFee,
                        finalAmount:   grandTotal,
                        invoiceCharge: invoiceCharge,
                        paid_by:       data.paid_by || 'Company',
                    };
                } else {
                    const ceiledFee       = Math.ceil(gatewayFee * 100) / 100;
                    const profit          = parseFloat((ceiledFee - gatewayFee).toFixed(3));
                    const chargePercent   = amount > 0 ? ((gatewayFee / amount) * 100).toFixed(2) : '0.00';
                    const subtotalService = amount + ceiledFee;
                    const subtotalInvoice = amount + invoiceCharge;
                    const grandTotal      = amount + ceiledFee + invoiceCharge;

                    const baseAmountEl = document.getElementById(`split_charge_base_amount_${i}`);
                    if (baseAmountEl) baseAmountEl.textContent = amount.toFixed(3);

                    const feeEl = document.getElementById(`${prefix}_fee_${i}`);
                    if (feeEl) feeEl.textContent = gatewayFee.toFixed(3);

                    const feePercentEl = document.getElementById(`split_charge_fee_percent_${i}`);
                    const feeCeiledEl  = document.getElementById(`split_charge_fee_ceiled_${i}`);
                    const feeProfitEl  = document.getElementById(`split_charge_fee_profit_${i}`);
                    if (feePercentEl) feePercentEl.textContent = `${chargePercent}%`;
                    if (feeCeiledEl)  feeCeiledEl.textContent  = ceiledFee.toFixed(3);
                    if (feeProfitEl)  feeProfitEl.textContent  = profit.toFixed(3);

                    const invoiceChargeRow = document.getElementById(`split_charge_invoice_charge_${i}`);
                    if (invoiceChargeRow) invoiceChargeRow.textContent = invoiceCharge.toFixed(3);

                    const subtotalServiceEl = document.getElementById(`split_charge_subtotal_service_${i}`);
                    const subtotalInvoiceEl = document.getElementById(`split_charge_subtotal_invoice_${i}`);
                    if (subtotalServiceEl) subtotalServiceEl.textContent = subtotalService.toFixed(3);
                    if (subtotalInvoiceEl) subtotalInvoiceEl.textContent = subtotalInvoice.toFixed(3);

                    document.getElementById(`${prefix}_paid_by_${i}`).textContent = data.paid_by || '-';
                    document.getElementById(`${prefix}_total_${i}`).textContent   = grandTotal.toFixed(3);

                    store[i] = {
                        gatewayFee:    gatewayFee,
                        ceiledFee:     ceiledFee,
                        finalAmount:   grandTotal,
                        invoiceCharge: invoiceCharge,
                        paid_by:       data.paid_by || 'Company',
                    };
                }

                breakdownEl?.classList.remove('hidden');
                updatePaymentSummary(mode);

            } catch (err) {
                console.error(`[${mode} ${i}] Charge calc failed:`, err);
                breakdownEl?.classList.add('hidden');
                delete store[i];
                updatePaymentSummary(mode);
            }
        }

        /**
         * Setup event handlers for each installment
         */
        function setupInstallmentEventHandlers(mode, installmentIndex, totalInvoiceAmount, totalInstallments) {
            const isPartial     = mode === 'partial';
            const gatewayId     = isPartial
                ? `payment_gateway1_${installmentIndex}`
                : `payment_gateway_${installmentIndex}`;
            const gatewaySelect = document.getElementById(gatewayId);
            const amountInput   = document.getElementById(`amount_${installmentIndex}`);

            if (!gatewaySelect || !amountInput) return;

            gatewaySelect.addEventListener('change', function() {
                if (isPartial) {
                    handleGatewayChangePartial(installmentIndex);
                } else {
                    handleGatewayChangeSplit(installmentIndex);
                }
            });

            amountInput.addEventListener('input', function() {
                handleAmountChange(mode, installmentIndex);
            });
        }

        /**
         * Handle gateway selection change
         */
        function handleGatewayChange(installmentIndex) {
            const gatewaySelect = document.getElementById(`payment_gateway1_${installmentIndex}`);
            const selectedGateway = gatewaySelect.value;
            const key = gwKey(selectedGateway);
            const creditPanel = document.getElementById(`credit_panel_${installmentIndex}`);
            const amountInput = document.getElementById(`amount_${installmentIndex}`);

            // Handle payment method visibility
            updatePaymentMethodVisibility(installmentIndex, selectedGateway);

            if (key === gwKey('credit')) {
                // CREDIT SELECTED: Show manual selection panel
                
                // Check if credit is already used by another installment
                if (creditSelectionState.selectedInstallment && creditSelectionState.selectedInstallment !== installmentIndex) {
                    alert(`Credit is already being used in Installment ${creditSelectionState.selectedInstallment}. Only one installment can use credit.`);
                    gatewaySelect.value = '';
                    return;
                }
                
                // Show credit panel
                creditPanel.classList.remove('hidden');
                requestAnimationFrame(() => {
                    creditPanel.style.opacity = '1';
                    creditPanel.style.transform = 'translateX(0)';
                });
                
                // Make amount input readonly (will be controlled by voucher selection)
                amountInput.readOnly = true;
                amountInput.style.backgroundColor = '#f3f4f6';
                amountInput.title = 'Amount will be set based on your voucher selection';
                
                // Mark this installment as using credit
                creditSelectionState.selectedInstallment = installmentIndex;
                
                // Load available vouchers
                loadCreditVouchers(installmentIndex);
                
                // Update credit option availability for other installments
                updateAllCreditOptions();
                
            } else {
                // NON-CREDIT SELECTED: Hide panel and reset
                
                // Hide credit panel
                creditPanel.style.opacity = '0';
                creditPanel.style.transform = 'translateX(10px)';
                setTimeout(() => creditPanel.classList.add('hidden'), 300);
                
                // Make amount input editable again
                amountInput.readOnly = false;
                amountInput.style.backgroundColor = '';
                amountInput.title = '';
                
                // Clear credit selection if this installment was using it
                if (creditSelectionState.selectedInstallment === installmentIndex) {
                    creditSelectionState.selectedInstallment = null;
                    creditSelectionState.totalCreditSelected = 0;
                    
                    // Reset other installment amounts to even split
                    recalculateWithoutCredit();
                }
                
                // Clear payment selection data
                PaymentSelection.hideForRow('partial', installmentIndex);
                
                // Update credit option availability
                updateAllCreditOptions();
            }
            
            updatePaymentSummary('partial');
        }

        // Enhanced PaymentSelection integration that properly reflects amounts
        const _origCheckbox = PaymentSelection.handleCheckboxChange;
        PaymentSelection.handleCheckboxChange = function(checkbox) {
            _origCheckbox.call(this, checkbox);
            syncInstallmentUI(checkbox.dataset.rowId);
        };

        const _origAmount = PaymentSelection.handleAmountChange;
        PaymentSelection.handleAmountChange = function(input) {
            _origAmount.call(this, input);
            syncInstallmentUI(input.dataset.rowId);
        };

        /**
         * Load available credit vouchers for selection
         */
        function loadCreditVouchers(mode, installmentIndex) {
            let clientId;

            console.log(`[loadCreditVouchers] mode=${mode}, installmentIndex=${installmentIndex}`);

            if (mode === 'partial') {
                const rawClientId = @json($invoice->client_id ?? null);
                clientId = rawClientId ? parseInt(rawClientId) : null;
                console.log(`[loadCreditVouchers] partial mode — invoice clientId:`, clientId);
            } else {
                const clientIdInput = document.getElementById(`customer_name_${installmentIndex}`);
                console.log(`[loadCreditVouchers] split mode — clientIdInput element:`, clientIdInput);
                console.log(`[loadCreditVouchers] split mode — clientIdInput value:`, clientIdInput?.value);

                if (!clientIdInput || !clientIdInput.value) {
                    console.warn(`[loadCreditVouchers] No client selected for split row ${installmentIndex}`);
                    const gw = document.getElementById(`payment_gateway_${installmentIndex}`);
                    if (gw) gw.value = '';
                    const panel = document.getElementById(`credit_panel_${installmentIndex}`);
                    if (panel) panel.classList.add('hidden');
                    if (creditSelectionState.selectedInstallment === installmentIndex) {
                        creditSelectionState.selectedInstallment = null;
                    }
                    alert('Please select a client first before choosing credit payment.');
                    return;
                }
                clientId = parseInt(clientIdInput.value);
                console.log(`[loadCreditVouchers] split mode — parsed clientId:`, clientId);
            }

            if (!clientId) {
                console.warn(`[loadCreditVouchers] clientId is null/0 — aborting`);
                const vouchersContainerId = mode === 'split'
                    ? `credit_vouchers_split_${installmentIndex}`
                    : `credit_vouchers_${installmentIndex}`;
                const vouchersContainer = document.getElementById(vouchersContainerId);
                console.log(`[loadCreditVouchers] vouchers container (${vouchersContainerId}):`, vouchersContainer);
                if (vouchersContainer) {
                    vouchersContainer.innerHTML = `
                        <div class="text-red-600 text-sm p-3 bg-red-50 rounded">
                            No client selected. Please select a client first.
                        </div>
                    `;
                }
                return;
            }

            // Use mode-aware container ID to match createCreditSelectionPanel
            const vouchersContainerId = mode === 'split'
                ? `credit_vouchers_split_${installmentIndex}`
                : `credit_vouchers_${installmentIndex}`;

            console.log(`[loadCreditVouchers] vouchers container ID:`, vouchersContainerId);
            console.log(`[loadCreditVouchers] vouchers container exists:`, !!document.getElementById(vouchersContainerId));
            console.log(`[loadCreditVouchers] calling PaymentSelection.showForRow(${mode}, ${installmentIndex}, ${clientId})`);

            PaymentSelection.showForRow(mode, installmentIndex, clientId);
        }

        /**
         * Handle voucher selection and amount input
         */
        function handleCreditVoucherSelection(mode, installmentIndex, selectedAmount) {
            const amountInput     = document.getElementById(`amount_${installmentIndex}`);
            const badgeId         = mode === 'partial'
                ? `card_amount_badge_${installmentIndex}`
                : `split_card_amount_badge_${installmentIndex}`;
            const badge           = document.getElementById(badgeId);
            const selectedDisplay = document.getElementById(`credit_selected_${installmentIndex}`);
            const statusDisplay   = document.getElementById(`credit_status_${installmentIndex}`);

            if (amountInput) amountInput.value = selectedAmount.toFixed(3);
            if (badge)       badge.textContent = `${selectedAmount.toFixed(3)} KWD`;
            if (selectedDisplay) selectedDisplay.textContent = `${selectedAmount.toFixed(3)} KWD`;

            creditSelectionState.totalCreditSelected = selectedAmount;
            creditUsed[installmentIndex] = selectedAmount;

            if (statusDisplay) {
                statusDisplay.textContent = selectedAmount > 0 ? 'Credit selected ✓' : 'No vouchers selected';
                statusDisplay.className   = selectedAmount > 0 ? 'text-green-600 font-medium' : 'text-gray-500';
            }

            recalculateAfterCreditSelection(mode, installmentIndex, selectedAmount);
            updatePaymentSummary(mode);
        }

        /**
         * Recalculate remaining installments after credit selection
         */
        function recalculateAfterCreditSelection(mode, creditInstallmentIndex, creditAmount) {
            const splitIntoElId = mode === 'partial' ? 'split-into1' : 'split-into';
            const totalAmount   = parseFloat(document.getElementById('subTotal').value) || 0;
            const splitInto     = parseInt(document.getElementById(splitIntoElId)?.value) || 0;

            if (splitInto <= 1) return;

            const remainingAmount       = totalAmount - creditAmount;
            const remainingInstallments = splitInto - 1;

            if (remainingInstallments <= 0 || remainingAmount <= 0) return;

            const amountPerOther = remainingAmount / remainingInstallments;

            for (let i = 1; i <= splitInto; i++) {
                if (i === creditInstallmentIndex) continue;
                const amountInput = document.getElementById(`amount_${i}`);
                const badgeId     = mode === 'partial'
                    ? `card_amount_badge_${i}`
                    : `split_card_amount_badge_${i}`;
                const badge = document.getElementById(badgeId);

                if (amountInput && !amountInput.readOnly) amountInput.value = amountPerOther.toFixed(3);
                if (badge) badge.textContent = `${amountPerOther.toFixed(3)} KWD`;
            }
        }

        /**
         * Recalculate to even split when credit is removed
         */
      function recalculateWithoutCredit(mode) {
            const splitIntoElId = mode === 'partial' ? 'split-into1' : 'split-into';
            const totalAmount   = parseFloat(document.getElementById('subTotal').value) || 0;
            const splitInto     = parseInt(document.getElementById(splitIntoElId)?.value) || 0;

            if (splitInto <= 0) return;

            const evenAmount = totalAmount / splitInto;

            for (let i = 1; i <= splitInto; i++) {
                const amountInput = document.getElementById(`amount_${i}`);
                const badgeId     = mode === 'partial'
                    ? `card_amount_badge_${i}`
                    : `split_card_amount_badge_${i}`;
                const badge = document.getElementById(badgeId);

                if (amountInput) amountInput.value = evenAmount.toFixed(3);
                if (badge)       badge.textContent = `${evenAmount.toFixed(3)} KWD`;
            }
        }

        /**
         * Update payment method visibility based on gateway selection
         */
        function updatePaymentMethodVisibility(installmentIndex, selectedGateway) {
            const methodContainer = document.getElementById(`payment_method_container1_${installmentIndex}`);
            const methodSelect = document.getElementById(`payment_method1_${installmentIndex}`);
            const chargeWrapper = document.getElementById(`invoice_charge_wrapper_${installmentIndex}`);
            const chargeInput = document.getElementById(`invoice_charge1_${installmentIndex}`);
            
            if (!selectedGateway) return;
            
            // Handle payment method display
            const key = gwKey(selectedGateway);
            const methods = methodsByGateway[key] || [];
            
            if (methods.length > 0 && selectedGateway !== 'Credit') {
                renderMethodOptions(methodSelect, methods);
                methodContainer.classList.remove('hidden');
            } else {
                methodContainer.classList.add('hidden');
                methodSelect.value = '';
            }
            
            // Handle invoice charge display (chargeWrapper may not exist for partial cards)
            if (chargeWrapper) {
                const canCharge = canGatewayChargeInvoice(selectedGateway);
                if (canCharge && selectedGateway !== 'Credit') {
                    chargeWrapper.classList.remove('hidden');
                    if (chargeInput) chargeInput.value = chargeInput.value || '0';
                } else {
                    chargeWrapper.classList.add('hidden');
                    if (chargeInput) chargeInput.value = '0';
                }
            }
        }

        /**
         * Update credit requirement when amount changes
         */
        function updateCreditRequirement(installmentIndex) {
            const amountInput = document.getElementById(`amount_${installmentIndex}`);
            const requiredDisplay = document.getElementById(`credit_required_${installmentIndex}`);
            
            if (amountInput && requiredDisplay) {
                const newAmount = parseFloat(amountInput.value) || 0;
                requiredDisplay.textContent = `${newAmount.toFixed(3)} KWD`;
                
                // Update PaymentSelection summary if needed
                const rowId = `partial_${installmentIndex}`;
                PaymentSelection.updatePartialSummary(installmentIndex, newAmount);
            }
        }

        /**
         * Update credit option availability across all installments
         */
        function updateAllCreditOptions(mode) {
            const isPartial = mode === 'partial';
            const splitIntoElId = isPartial ? 'split-into1' : 'split-into';
            const splitInto = parseInt(document.getElementById(splitIntoElId)?.value) || 0;

            for (let i = 1; i <= splitInto; i++) {
                const creditOptionId = isPartial ? `credit_option1_${i}` : `credit_option_${i}`;
                const creditOption = document.getElementById(creditOptionId);
                if (!creditOption) continue;

                if (isPartial) {
                    // Original logic: one credit slot for the whole partial modal
                    if (creditSelectionState.selectedInstallment && creditSelectionState.selectedInstallment !== i) {
                        creditOption.disabled = true;
                        creditOption.textContent = `Credit (Used in Installment ${creditSelectionState.selectedInstallment})`;
                        creditOption.style.color = '#9ca3af';
                    } else {
                        creditOption.disabled = partialCredit <= 0;
                        creditOption.textContent = `Credit (${parseFloat(partialCredit).toFixed(3)})`;
                        creditOption.style.color = partialCredit > 0 ? '#000' : '#9ca3af';
                    }
                } else {
                    // Split: check if THIS row's client is already using credit elsewhere
                    const thisClientId = document.getElementById(`customer_name_${i}`)?.value;
                    const available = clientCredits[i] || 0;

                    let blockedByDuplicate = false;
                    if (thisClientId) {
                        for (let j = 1; j <= splitInto; j++) {
                            if (j === i) continue;
                            const otherGw = document.getElementById(`payment_gateway_${j}`)?.value;
                            const otherClientId = document.getElementById(`customer_name_${j}`)?.value;
                            if (gwKey(otherGw) === gwKey('credit') && otherClientId === thisClientId) {
                                blockedByDuplicate = true;
                                creditOption.disabled = true;
                                creditOption.textContent = `Credit (Used in Installment ${j})`;
                                creditOption.style.color = '#9ca3af';
                                break;
                            }
                        }
                    }

                    if (!blockedByDuplicate) {
                        creditOption.disabled = available <= 0;
                        creditOption.textContent = `Credit (${parseFloat(available).toFixed(3)})`;
                        creditOption.style.color = available > 0 ? '#000' : '#9ca3af';
                    }
                }
            }
        }

        // Export functions for global access
        window.updateRowPartial = updateRowPartial;
        window.handleCreditVoucherSelection = handleCreditVoucherSelection;
        window.creditSelectionState = creditSelectionState;

        function updateRowPartialFromMoved() {
            const movedSelect = document.getElementById('split-into1-moved');
            const originalSelect = document.getElementById('split-into1');
            
            if (movedSelect && originalSelect) {
                // Sync the original dropdown value
                originalSelect.value = movedSelect.value;
                // Call the main update function
                updateRowPartial();
            }
        }

        function filterClientsSplit(rowIndex, searchTerm) {

            const optionsContainer = document.getElementById(`options_container_${rowIndex}`);
            const options = optionsContainer.querySelectorAll('.client-option');

            let matchCount = 0;
            options.forEach(option => {
                const clientName = option.getAttribute('data-client-name').toLowerCase();
                const matches = clientName.includes(searchTerm.toLowerCase());
                option.style.display = matches ? 'block' : 'none';

                if (matches) matchCount++;

                // Highlight matching text
                if (searchTerm && matches) {
                    const name = option.getAttribute('data-client-name');
                    const regex = new RegExp(`(${searchTerm})`, 'gi');
                    option.innerHTML = name.replace(regex, '<mark class="bg-blue-200">$1</mark>');
                } else {
                    option.innerHTML = option.getAttribute('data-client-name');
                }
            });

        }

        function selectSplitClient(rowIndex, clientId, clientName, element) {
            // Update hidden input
            const hiddenInput = document.getElementById(`customer_name_${rowIndex}`);
            if (hiddenInput) {
                hiddenInput.value = clientId;
            } else {
                console.error('Hidden input not found:', `customer_name_${rowIndex}`);
            }

            // Update display text
            const selectedText = document.getElementById(`selected_text_${rowIndex}`);
            if (selectedText) {
                selectedText.textContent = clientName;
                selectedText.className = 'text-black'; // Remove gray color
            } else {
                console.error('Selected text element not found:', `selected_text_${rowIndex}`);
            }

            // Close dropdown
            const dropdown = document.getElementById(`dropdown_${rowIndex}`);
            if (dropdown) {
                dropdown.style.display = 'none';
            } else {
                console.error('Dropdown not found:', `dropdown_${rowIndex}`);
            }

            // Update credit row
            updateCreditRow(rowIndex, clientId);
        }

        // Close dropdowns when clicking outside or handle option selection
        document.addEventListener('click', function(event) {
            // Handle client option selection - only for split payment rows
            if (event.target.classList.contains('client-option') && event.target.getAttribute('data-row-index')) {
                const clientId = event.target.getAttribute('data-client-id');
                const clientName = event.target.getAttribute('data-client-name');
                const rowIndex = event.target.getAttribute('data-row-index');

                selectSplitClient(parseInt(rowIndex), clientId, clientName, event.target);
                return;
            }

            // Close split payment dropdowns when clicking outside
            if (!event.target.closest('[id^="searchable_dropdown_"]')) {
                document.querySelectorAll('[id^="dropdown_"]').forEach(dropdown => {
                    dropdown.style.display = 'none';
                });
            }
        });


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

        window.invoicePartials = @json($invoice->invoicePartials ?? []);
        function calculateSubtotal() {
            // For refund invoices, the billable amount is pre-calculated — don't use task price sum
            const subtotal = isRefundInvoice
                ? refundInvoiceAmount
                : items.reduce((sum, item) => sum + (parseFloat(item.task_price) || 0), 0);

            const invoiceChargeElement = document.getElementById('invoice_charge');
            const invoiceChargeInputElement = document.getElementById('invoice_charge_amount_input');
            const invoiceCharge = invoiceChargeInputElement ? (parseFloat(invoiceChargeInputElement.value) || 0) 
                : (invoiceChargeElement ? parseFloat(invoiceChargeElement.value) || 0 : 0);

            // Check if invoice charge has been modified from original
            const originalInvoiceCharge = parseFloat("{{ $invoice->invoice_charge ?? 0 }}") || 0;
            const invoiceChargeChanged = Math.abs(invoiceCharge - originalInvoiceCharge) > 0.001;

            let serviceCharge = 0;

            // Only use stored partials' service charge if invoice charge hasn't changed
            if (window.invoicePartials && Array.isArray(window.invoicePartials) && window.invoicePartials.length > 0 && !invoiceChargeChanged) {
                serviceCharge = window.invoicePartials.reduce((sum, partial) => {
                    return sum + (parseFloat(partial.service_charge) || 0);
                }, 0);
            } else {
                // Calculate dynamically when no partials OR when invoice charge changed
                const selectedGateway = document.getElementById('payment_gateway_option')?.value;
                const selectedPaymentMethod = document.getElementById('payment_method_full')?.value;

                if (selectedGateway) {
                    const selectedCharge = charges.find(charge => charge.name === selectedGateway);

                    if (selectedCharge) {
                        let chargeValue = 0;
                        let chargeType = 'Flat Rate';
                        let paidBy = 'Company';

                        const gatewayKey = gwKey(selectedGateway);
                        const gatewayMethods = methodsByGateway[gatewayKey] || [];

                        // Priority 1: Payment method level
                        if (gatewayMethods.length > 0 && selectedPaymentMethod) {
                            const method = paymentMethods.find(m => m.id === parseInt(selectedPaymentMethod));
                            if (method) {
                                chargeValue = parseFloat(method.self_charge) || parseFloat(method.service_charge) || parseFloat(method.fee) || 0;
                                chargeType = method.charge_type || 'Flat Rate';
                                paidBy = method.paid_by || 'Company';
                            }
                        }

                        // Priority 2: Gateway level fallback
                        if (chargeValue <= 0) {
                            const selfCharge = parseFloat(selectedCharge.self_charge);
                            const gatewayAmount = parseFloat(selectedCharge.amount?.toString().replace(',', '')) || 0;
                            
                            if (!isNaN(selfCharge) && selfCharge > 0) {
                                chargeValue = selfCharge;
                                chargeType = selectedCharge.self_charge_type || 'Flat Rate';
                            } else {
                                chargeValue = gatewayAmount;
                                chargeType = selectedCharge.charge_type || 'Percent';
                            }
                            paidBy = selectedCharge.paid_by || 'Company';
                        }

                        // Only calculate if client pays
                        if (paidBy === 'Client' && chargeValue > 0) {
                            if (chargeType === 'Percent') {
                                const baseAmount = subtotal + invoiceCharge;
                                serviceCharge = Math.ceil((baseAmount * chargeValue) / 100);
                            } else {
                                serviceCharge = chargeValue;
                            }
                        }
                    }
                }
            }

            const finalAmount = subtotal + serviceCharge;
            const finalTotal = finalAmount + invoiceCharge;

            document.getElementById('subTotalDisplay').textContent = `${subtotal.toFixed(3)}`;

            const serviceChargeDisplayElement = document.getElementById('serviceChargeDisplay');
            const serviceChargeDisplayRow = document.getElementById('service_charge_display_row');
            if (serviceChargeDisplayElement) {
                serviceChargeDisplayElement.textContent = `${serviceCharge.toFixed(3)}`;
            }
            if (serviceChargeDisplayRow) {
                serviceChargeDisplayRow.style.display = serviceCharge > 0 ? 'flex' : 'none';
            }

            const finalAmountDisplayElement = document.getElementById('finalAmountDisplay');
            const finalAmountDisplayRow = document.getElementById('final_amount_display_row');
            if (finalAmountDisplayElement) {
                finalAmountDisplayElement.textContent = `${finalAmount.toFixed(3)}`;
            }
            if (finalAmountDisplayRow) {
                finalAmountDisplayRow.style.display = serviceCharge > 0 ? 'flex' : 'none';
            }

            document.getElementById('invoiceChargeDisplay').textContent = `${invoiceCharge.toFixed(3)}`;
            document.getElementById('subT').textContent = `${finalTotal.toFixed(3)}`;

            document.querySelectorAll('#subT1, .invoice-total-display').forEach(el => {
                el.textContent = `${finalTotal.toFixed(3)}`;
            });

            document.getElementById('subTotal').value = subtotal;

            const totalAmountElement = document.getElementById('subTotal');
            if (totalAmountElement) totalAmountElement.value = finalTotal;

            const netTotals = items.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0);
            const netT = document.getElementById('netT');
            if (netT) netT.textContent = netTotals.toFixed(3);

            const netTotal = document.getElementById('netTotal');
            if (netTotal) netTotal.value = netTotals.toFixed(3);
        }

        function renderItems() {
            const tbody = itemsBody;
            if (!tbody) return;
            tbody.innerHTML = '';

            if (!Array.isArray(items) || items.length === 0) {
                const noItemsRow = document.createElement('tr');
                noItemsRow.innerHTML =
                    '<td colspan="7" class="w-full !text-center font-semibold text-gray-900 dark:bg-[#121e32] dark:text-white">No Tasks Available</td>';
                tbody.appendChild(noItemsRow);
                return;
            }

            const frag = document.createDocumentFragment();
            let count = 0;
            const isInvoicePaid = "{{ $invoice->status === 'paid' && ($invoice->payment_type === 'full') }}";

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
                    const canSavePrice = (!invoice.payment_type || invoice.payment_type === 'full');
                    const row = document.createElement('tr');
                    row.className = `border-b border-[#e0e6ed] align-top dark:border-[#1b2e4b] ${!isSaved ? 'bg-sky-100' : ''}`;

                    row.innerHTML = `
                        <td class="px-4 py-3"><p>${++count}</p></td>
                        <td class="px-4 py-3">
                            <div>
                                <b>${task.desc}</b>
                                <span class="inline-flex items-center ml-2 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase ${
                                    task.type === 'flight' ? 'bg-blue-100 text-blue-700' :
                                    task.type === 'hotel'  ? 'bg-purple-100 text-purple-700' :
                                                            'bg-gray-100 text-gray-600'
                                }">${task.typeCap}</span>
                            </div>
                            ${task.info ? `<div class="text-gray-500 text-xs mt-0.5">Info: ${task.info}</div>` : ''}
                            ${task.supplierName ? `<div class="text-gray-400 text-xs mt-0.5">${task.supplierName}</div>` : ''}
                        </td>
                        <td><p>${task.clientName}</p></td>
                        <td class="px-4 py-3">
                            <p>${task.agentName}</p>
                            ${task.branchName ? `<div class="text-gray-400 text-xs mt-0.5">${task.branchName}</div>` : ''}
                        </td>    
                        <td class="px-4 py-3"><p>${task.total} KWD</p></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <input id="invprice-table-${task.id}"
                                    type="number"
                                    name="tasks[${item.id}]"
                                    class="no-spin border border-gray-300 rounded-md w-full p-2"
                                    value="${item.task_price}" 
                                    oninput="updateItemPrice(${item.id});" 
                                />
                                ${!isInvoicePaid && isSaved && !isInvoiceLocked ? `
                                    <button type="button" class="p-1 rounded hover:bg-gray-200" title="Save" onclick="saveTaskPrice(${task.id})" style="${isRefundInvoice ? 'display:none' : ''}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-blue-600">
                                            <path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-4-4zm-5 16a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm3-10H5V5h10v4z"/>
                                        </svg>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                        <td class="action-cell text-center px-4 py-3" style="${isRefundInvoice ? 'display:none' : ''}">
                            <div class="inline-flex space-x-2">
                                ${!isSaved ? `
                                    <button onclick="saveSingleTask(${item.id})" type="button" class="text-blue-500 hover:text-blue-700" data-tooltip-left="Save This Task">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                            <polyline points="7 3 7 8 15 8"></polyline>
                                        </svg>
                                    </button>
                                ` : ''}
                                <button onclick="removeTaskFromInvoice(${item.id})" type="button" class="text-red-500 hover:text-red-700" data-tooltip-left="Remove Item" style="${isRefundInvoice ? 'display:none' : ''}">
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
                        <div class="font-bold">${(task.quantity * Number(task.total || 0)).toFixed(3)} KWD</div>
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

            // console.info('renderItems(): rendered rows =', tbody.rows.length, 'from items len =', items.length);
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
                    calculatedChargeInput.value = invoice.invoice_charge.toFixed(3);
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
                client.full_name.toLowerCase().includes(searchValue) || client.email.toLowerCase().includes(searchValue)
            );
            renderClientList(filteredClients);
        }

        function renderClientList(clientData) {
            const clientList = document.getElementById('clientList');
            clientList.innerHTML = '';
            clientData.forEach(client => {
                const li = document.createElement('li');
                li.className = 'cursor-pointer p-2 hover:bg-gray-100 text-gray-800';
                li.innerText = `${client.full_name} - ${client.email}`;
                li.onclick = () => selectClient(client);
                clientList.appendChild(li);
            });
        }

        function selectClient(client) {
            document.getElementById('receiverId').value = client.id;

            // Update input fields
            document.getElementById('receiverName').value = client.full_name;
            document.getElementById('receiverName1').value = client.full_name;
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
                document.getElementById('receiverName').value = client.full_name;
                document.querySelectorAll('.receiver-name-display').forEach(el => el.textContent = client.full_name);                document.getElementById('receiverEmail').value = client.email;
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
            document.getElementById('clientid').value = client.id;
            // Update input fields
            document.getElementById('receiverName').value = client.full_name;
            document.querySelectorAll('.receiver-name-display').forEach(el => el.textContent = client.full_name);            document.getElementById('receiverEmail').value = client.email;
            document.getElementById('receiverPhone').value = client.phone;

            document.getElementById('agentName').value = agent.name;
            document.getElementById('agentEmail').value = agent.email;
            document.getElementById('agentPhone').value = agent.phone_number;
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

            // Include payment method if gateway has methods configured
            const methodElement = document.getElementById('payment_method_full');
            if (methodElement && methodElement.value) {
                data.method = methodElement.value;
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

        async function savePartial(mode) {
            const gateway = document.getElementById('payment_gateway_option')?.value;
            const validation = checkPaymentAmount(mode);

            if (!validation.isValid) {
                showErrorAlert(validation.errorMessage);
                return;
            }
            clearErrorAlert();

            // Validation for "full" mode: payment gateway and method selection
            if (mode === 'full') {
                if (!gateway) {
                    showErrorAlert('Please choose a payment gateway.');
                    return;
                }
                // Check if selected gateway requires payment method
                const key = gwKey(gateway);
                const methods = methodsByGateway[key] || [];
                if (methods.length > 0) {
                    const method = document.getElementById('payment_method_full')?.value;
                    if (!method) {
                        showErrorAlert('Please choose a payment method for ' + gateway + '.');
                        return;
                    }
                }
            }

            const requests = [];

            if (mode === 'full' || mode === 'credit') {
                const date = document.getElementById('duedate').value;
                const amount = document.getElementById('subTotal').value;
                const externalUrl = document.getElementById('external_url')?.value;
                const paymentGateway = mode === 'credit' ? 'Credit' : gateway;

                requests.push(save(mode, {
                    date,
                    amount,
                    gateway: paymentGateway,
                    external_url: externalUrl
                }));

                const button = document.getElementById('update-invoice-btn');
                const icon = document.getElementById('button-icon-full');
                const text = document.getElementById('button-text-full');

                button.disabled = true;
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
                    text.textContent = 'Saved';
                    location.reload();
                }, 500);

            } else if (mode === 'split') {
                const splitInto = parseInt(document.getElementById('split-into')?.value) || 0;

                for (let i = 1; i <= splitInto; i++) {
                    const clientId   = document.getElementById(`customer_name_${i}`)?.value || '';
                    const clientName = document.getElementById(`selected_text_${i}`)?.textContent.trim() || '';
 
                    const date   = document.getElementById(`date_${i}`)?.value || null;
                    const amount = parseFloat(document.getElementById(`amount_${i}`)?.value) || 0;

                    const gatewaySelect = document.getElementById(`payment_gateway_${i}`);
                    const gateway = gatewaySelect ? gatewaySelect.value : null;

                    const methodContainer = document.getElementById(`payment_method_container_${i}`);
                    const methodSelect    = document.getElementById(`payment_method_${i}`);
                    const method = (methodContainer && !methodContainer.classList.contains('hidden'))
                        ? (methodSelect?.value || null)
                        : null;

                    const invoiceChargeInput   = document.getElementById(`split_invoice_charge_${i}`);
                    const partialInvoiceCharge = parseFloat(invoiceChargeInput?.value) || 0;

                    let paymentAllocations = [];
                    if (gateway === 'Credit') {
                        const rowId = `split_${i}`;
                        paymentAllocations = PaymentSelection.getSelectedPaymentsForRow(rowId);
                        console.log(`Split row ${i} payment allocations:`, paymentAllocations);
                    }

                    requests.push(save('split', {
                        clientId,
                        clientName,
                        date,
                        amount,
                        gateway,
                        method,
                        partial_invoice_charge: partialInvoiceCharge,
                        payment_allocations: paymentAllocations
                    }));
                }

                const buttonSplit = document.getElementById('splitbutton');
                const iconSplit   = document.getElementById('button-icon-split');
                const textSplit   = document.getElementById('button-text-split');

                if (buttonSplit && iconSplit && textSplit) {
                    buttonSplit.disabled = true;
                    iconSplit.innerHTML = `
                        <svg class="animate-spin h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    `;
                    textSplit.textContent = 'Saving...';
                    setTimeout(() => {
                        iconSplit.innerHTML = '';
                        textSplit.textContent = 'Saved';
                        location.reload();
                    }, 500);
                } else {
                    console.error('Split button or icon/text elements not found in the DOM.');
                }
            } else if (mode === 'partial') {
                const splitInto = parseInt(document.getElementById('split-into1').value) || 0;

                for (let i = 1; i <= splitInto; i++) {
                    const date = document.getElementById(`date_${i}`)?.value || '';
                    const amount = parseFloat(document.getElementById(`amount_${i}`)?.value) || 0;
                    const gatewayEl = document.getElementById(`payment_gateway1_${i}`);
                    const methodBox = document.getElementById(`payment_method_container1_${i}`);
                    const methodEl = document.getElementById(`payment_method1_${i}`);

                    const gateway = gatewayEl ? gatewayEl.value : null;
                    const method = (methodBox && !methodBox.classList.contains('hidden')) ? (methodEl?.value || null) : null;
                    const invoiceChargeInput = document.getElementById(`partial_invoice_charge_${i}`);
                    const partialInvoiceCharge = parseFloat(invoiceChargeInput?.value) || 0;

                    // Get selected payments if gateway is Credit
                    let paymentAllocations = [];
                    if (gateway === 'Credit') {
                        const rowId = `partial_${i}`;
                        paymentAllocations = PaymentSelection.getSelectedPaymentsForRow(rowId);
                        console.log(`Partial row ${i} payment allocations:`, paymentAllocations);
                    }

                    requests.push(save('partial', {
                        date,
                        amount,
                        gateway,
                        method,
                        partial_invoice_charge: partialInvoiceCharge,
                        payment_allocations: paymentAllocations
                    }));

                    console.log(`row ${i}`, {
                        date,
                        amount,
                        gatewayId: gatewayEl?.id,
                        gateway,
                        methodId: methodEl?.id,
                        methodVisible: methodBox && !methodBox.classList.contains('hidden'),
                        method
                    });
                }

                // UI feedback (unchanged)
                const buttonPartial = document.getElementById('partialbutton');
                const iconPartial = document.getElementById('button-icon-partial');
                const textPartial = document.getElementById('button-text-partial');
                if (buttonPartial && iconPartial && textPartial) {
                    buttonPartial.disabled = true;
                    iconPartial.innerHTML = `
                        <svg class="animate-spin h-5 w-5 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>`;
                    textPartial.textContent = 'Saving...';
                    setTimeout(() => {
                        iconPartial.innerHTML = '';
                        textPartial.textContent = 'Saved';
                        location.reload();
                    }, 500);
                } else {
                    console.error('One or more elements (button, icon, text) not found in the DOM');
                }
            }

            if (requests.length === 0) {
                showErrorAlert('Nothing to save. Please add at least one row.');
                return;
            }

            const results = await Promise.all(requests);
            const allOk = results.length > 0 && results.every(r => r && r.ok !== false);
            if (allOk) {
                hideModal();
                checkInvoiceId(mode);
            }
        }

        async function save(type, item) {
            const invoiceUrl = "{{ route('invoice.partial') }}";
            const csrfToken = "{{ csrf_token() }}";
            const invoiceId = document.getElementById('invoiceId').value;
            const invoiceNumber = document.getElementById('invoiceNumber').value;
            const companyId = document.getElementById('companyId').value;

            let partialInvoiceCharge = 0;
            if (type === 'full') {
                partialInvoiceCharge = parseFloat(document.getElementById('invoice_charge')?.value) || 0;
            } else {
                partialInvoiceCharge = parseFloat(item.partial_invoice_charge) || 0;
            }

            let payload = {
                invoiceId,
                invoiceNumber,
                companyId,
                type,
                date: item.date,
                amount: item.amount,
                gateway: item.gateway,
                external_url: item.external_url || null,
                partial_invoice_charge: partialInvoiceCharge,
            };

            if (type === 'full' || type === 'credit') {
                payload.clientId = document.getElementById('receiverId').value;
                // Include payment method if gateway has methods
                const key = gwKey(item.gateway);
                const methods = methodsByGateway[key] || [];
                payload.method = methods.length > 0 ? (document.getElementById('payment_method_full')?.value || null) : null;
            } else if (type === 'partial') {
                payload.clientId = document.getElementById('receiverId').value;
                payload.method = item.method;
                
                // Include payment allocations for credit payments
                if (payload.gateway === 'Credit' && item.payment_allocations && item.payment_allocations.length > 0) {
                    payload.credit = true;
                    payload.payment_allocations = item.payment_allocations;
                }
            } else if (type === 'split') {
                payload.clientId = item.clientId;
                payload.method = item.method;

                if (payload.gateway === 'Credit') {
                    payload.credit = true;
                    // Include payment allocations for credit payments
                    if (item.payment_allocations && item.payment_allocations.length > 0) {
                        payload.payment_allocations = item.payment_allocations;
                    }
                }

            }

            if (type === 'credit') {
                payload.credit = true;
            }
            console.log(payload);

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

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    const msg = errorData.message || `Failed to process ${type} payment.`;
                    displayErrorMessage(msg);
                    return {
                        ok: false,
                        error: msg
                    };
                }

                const result = await response.json();
                displaySuccessMessage(result.message || `${type} payment processed successfully!`);
                return {
                    ok: true,
                    data: result
                };

            } catch (error) {
                console.error(`Error processing ${type} payment for item:`, item, error);
                displayErrorMessage(error.message || `Something went wrong with ${type} payment.`);
                return {
                    ok: false,
                    error
                };
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

        function copyLink() {
            const invoiceNumber = document.getElementById('invoiceNumber').value;
            const copyFeedback = document.getElementById('copyFeedback');
            const companyId = document.getElementById('companyId').value;
            const fetchUrl = "{{ route('invoice.show', ['companyId' => ':companyId', 'invoiceNumber' => ':invoiceNumber']) }}".replace(':companyId', companyId).replace(':invoiceNumber', invoiceNumber);

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


        // Setup payment types and initial tasks - will be called in main DOMContentLoaded
        function setupPaymentTypesAndTasks() {  
            tasks = @json($tasks);
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
        }

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

            setTimeout(() => {
                clearErrorAlert();
            }, 10000);
        }

        function clearErrorAlert() {
            let existingAlert = document.getElementById("paymentValidationError");
            if (existingAlert) {
                existingAlert.remove();
            }
        }

       function checkPaymentAmount(mode) {
            const totalInvoiceAmount = parseFloat(document.getElementById('subTotal').value) || 0;
            let totalEnteredAmount = 0;
            let isValid = true;
            let errorMessage = '';

            if (mode === 'split') {
                const amountInputs = document.querySelectorAll('#split-rows input[id^="amount_"]');

                amountInputs.forEach(input => {
                    totalEnteredAmount += parseFloat(input.value || 0);
                });

                if (Number(totalEnteredAmount.toFixed(3)) !== Number(totalInvoiceAmount.toFixed(3))) {
                    isValid = false;
                    errorMessage = `Total split payment amounts (${totalEnteredAmount.toFixed(3)} KWD) must equal the invoice amount (${totalInvoiceAmount.toFixed(3)} KWD). Please adjust the amounts to match exactly.`;
                }

            } else if (mode === 'partial') {
                // FIX: Query amount inputs directly by ID pattern instead of looking for table rows
                const splitInto = parseInt(document.getElementById('split-into1').value) || 0;

                for (let i = 1; i <= splitInto; i++) {
                    const amountInput = document.getElementById(`amount_${i}`);
                    if (amountInput) {
                        const amount = parseFloat(amountInput.value) || 0;
                        totalEnteredAmount += amount;
                    }
                }

                const roundedEntered = Number(totalEnteredAmount.toFixed(3));
                const roundedInvoice = Number(totalInvoiceAmount.toFixed(3));

                if (roundedEntered > roundedInvoice) {
                    isValid = false;
                    errorMessage = `Total partial payment amounts (${totalEnteredAmount.toFixed(3)} KWD) exceed the invoice amount (${totalInvoiceAmount.toFixed(3)} KWD). Partial payments cannot exceed the invoice total.`;
                } else if (roundedEntered < roundedInvoice) {
                    isValid = false;
                    errorMessage = `Total partial payment amounts (${totalEnteredAmount.toFixed(3)} KWD) are less than the invoice amount (${totalInvoiceAmount.toFixed(3)} KWD). Partial payments cannot be less than the invoice total.`;
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

        // Setup import modal - will be called in main DOMContentLoaded
        function setupImportModal() {
            const modal = document.getElementById('importModal');
            const openBtn = document.getElementById('openImportModalBtn');
            const closeBtn = document.getElementById('closeImportModalBtn');
            const cancelBtn = document.getElementById('cancelImport');
            const form = document.getElementById('importForm');
            const gateway = document.getElementById('gateway');
            const fatoorah = document.getElementById('import_invoice_id');
            const hesabe = document.getElementById('import_order_reference');

            const successBox = document.getElementById('successBox');
            const errorBox = document.getElementById('errorBox');
            const loadingBox = document.getElementById('loadingBox');

            if (!modal || !openBtn) return; // Exit if elements don't exist

            // Show modal
            openBtn.addEventListener('click', () => {
                gateway.value = '';
                fatoorah.value = '';
                hesabe.value = '';
                errorBox.classList.add('hidden');
                successBox.classList.add('hidden');
                loadingBox.classList.add('hidden');
                modal.classList.remove('hidden');
            });

            // Hide modal
            function closeModal() {
                modal.classList.add('hidden');
            }

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

            // Submit logic
            if (form) {
               form.addEventListener('submit', async (e) => {
                e.preventDefault();

                // UI reset
                successBox.classList.add('hidden');
                errorBox.classList.add('hidden');
                loadingBox.classList.remove('hidden');

                // Source selector (gateway | receipt)
                const sourceEl = form.querySelector('[name="source"]');
                const source   = (sourceEl?.value || '').trim();

                // Common context
                const agentName  = document.getElementById('agentName')?.value || '';
                const clientName = document.getElementById('receiverName')?.value || '';
                const invoiceNumber = document.getElementById('invoiceNumber')?.value || '';

                // Gateway inputs
                const gatewayVal = document.getElementById('gateway')?.value || '';
                const invoiceId  = (document.getElementById('import_invoice_id')?.value || '').trim();
                const orderRef   = (document.getElementById('import_order_reference')?.value || '').trim();

                // Receipt input (either datalist or your searchable component’s hidden/input)
                const receiptRef = (
                    document.getElementById('receipt_reference')?.value ||
                    document.querySelector('[name="receipt"]')?.value ||
                    ''
                ).trim();

                if (!agentName || !clientName) {
                    loadingBox.classList.add('hidden');
                    errorBox.textContent = 'Agent and Client must be selected.';
                    errorBox.classList.remove('hidden');
                    return;
                }

                let url = '';
                const fd = new FormData();
                fd.append('_token', '{{ csrf_token() }}');

                if (source === 'gateway') {
                    if (!gatewayVal) {
                    loadingBox.classList.add('hidden');
                    errorBox.textContent = 'Please select a payment gateway.';
                    errorBox.classList.remove('hidden');
                    return;
                    }
                    if (!invoiceId && !orderRef) {
                    loadingBox.classList.add('hidden');
                    errorBox.textContent = 'Payment ID or Order Reference is required.';
                    errorBox.classList.remove('hidden');
                    return;
                    }

                    url = `{{ route('payment.link.import.invoice') }}`;
                    fd.append('gateway', gatewayVal);
                    fd.append('agentName', agentName);
                    fd.append('receiverName', clientName);
                    fd.append('page', 'invoice');
                    if (invoiceId) fd.append('import_invoice_id', invoiceId);
                    if (orderRef)  fd.append('import_order_reference', orderRef);

                } else if (source === 'receipt') {
                    if (!receiptRef) {
                    loadingBox.classList.add('hidden');
                    errorBox.textContent = 'Please choose a Receipt Reference.';
                    errorBox.classList.remove('hidden');
                    return;
                    }

                    // ▶️ New endpoint for receipt import
                    url = `{{ route('receipt-voucher.import') }}`;

                    fd.append('source', 'receipt');
                    fd.append('receipt_reference', receiptRef);
                    // Include context if your controller needs it
                    fd.append('agent_name', agentName);
                    fd.append('client_name', clientName);
                    fd.append('invoice_number', invoiceNumber);
                } else {
                    loadingBox.classList.add('hidden');
                    errorBox.textContent = 'Please select where to import from.';
                    errorBox.classList.remove('hidden');
                    return;
                }

                // 🔎 DEBUG: log URL and payload being sent
                console.log('[IMPORT] POST URL =>', url);
                console.log('[IMPORT] FormData payload:');
                for (const [k, v] of fd.entries()) {
                    console.log('  ', k, '=>', v);
                }

                try {
                    const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                    });

                    loadingBox.classList.add('hidden');

                    if (!res.ok) {
                    let msg = 'Import failed.';
                    try { msg = (await res.json()).message || msg; }
                    catch { msg = (await res.text()).slice(0, 200) || msg; }
                    errorBox.textContent = msg;
                    errorBox.classList.remove('hidden');
                    return;
                    }

                    let data = {};
                    try { data = await res.json(); } catch {}

                    successBox.textContent = data.message || 'Imported successfully.';
                    successBox.classList.remove('hidden');
                    setTimeout(() => {
                    // optionally close modal here if you want:
                    // modal.classList.add('hidden');
                    window.location.reload();
                    }, 1200);

                } catch (err) {
                    loadingBox.classList.add('hidden');
                    errorBox.textContent = err.message || 'Network error.';
                    errorBox.classList.remove('hidden');
                }
                });
            }
        }

        function paymentSection() {
            return {
                showModalType: false,
                showModalGateway: false,
                paymentType: '{{ $invoice->payment_type ?? '' }}',
                hasInvoicePartials: {{ $invoice->invoicePartials->count() > 0 ? 'true' : 'false' }},
                pendingPaymentType: null,
                paymentGateways: @js($paymentGateways),
                allPaymentMethods: @js($paymentMethods),
                invoicePartials: [],

                initData() {
                    // Initialize from hidden input if needed
                    const savedType = document.getElementById('paymentTypeSaved')?.value || '';
                    if (savedType) {
                        this.paymentType = savedType;
                    }
                },

                // Check if payment type can be changed directly (no existing partials)
                canChangeType() {
                    return !this.paymentType || !this.hasInvoicePartials;
                },

                // Check if a specific type is locked
                isTypeLocked(type) {
                    // If no payment type set or no partials, nothing is locked
                    if (!this.paymentType || !this.hasInvoicePartials) {
                        return false;
                    }
                    // Lock all types except the current one
                    return this.paymentType !== type;
                },

                // Handle payment type click
                handlePaymentTypeClick(type, event) {
                    // If same type as current, allow normal behavior
                    if (this.paymentType === type) {
                        return true;
                    }

                    // If locked (has existing payment type and partials), show modal
                    if (this.isTypeLocked(type)) {
                        event.preventDefault();
                        event.stopPropagation();
                        this.pendingPaymentType = type;
                        this.showModalType = true;
                        return false;
                    }

                    // Otherwise allow normal behavior
                    return true;
                },

                // Open Gateway Modal
                openGatewayModal() {
                    this.showModalGateway = true;
                },

                // Close modals and reset
                closeTypeModal() {
                    this.showModalType = false;
                    this.pendingPaymentType = null;
                }
            }
        }

        function toggleMethod(partialId) {
            const gatewaySelect = document.getElementById(`gateway_${partialId}`);
            const methodSection = document.getElementById(`method_section_${partialId}`);
            const selectedGateway = gatewaySelect.value.toLowerCase();
        }

        function setupSendEmailModal() {
            const modal = document.getElementById('sendEmailModal');
            const openBtn = document.getElementById('openSendEmailModal');
            const closeBtn = document.getElementById('closeSendEmailModal');
            const cancelBtn = document.getElementById('cancelSendEmail');
            const form = document.getElementById('sendEmailForm');
            const successMsg = document.getElementById('emailSuccessMessage');
            const errorMsg = document.getElementById('emailErrorMessage');
            const submitBtn = document.getElementById('submitSendEmail');
            const btnText = document.getElementById('sendEmailBtnText');
            const spinner = document.getElementById('sendEmailSpinner');

            if (!modal || !openBtn) return;

            openBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
                successMsg.classList.add('hidden');
                errorMsg.classList.add('hidden');
            });

            function closeModal() {
                modal.classList.add('hidden');
            }

            closeBtn.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);

            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const recipients = [];
                const sendToAgent = document.getElementById('send_to_agent')?.checked;
                const sendToClient = document.getElementById('send_to_client')?.checked;
                const customEmails = document.getElementById('custom_emails')?.value || '';

                const agentEmail = '{{ $selectedAgent->email ?? "" }}';
                const clientEmail = '{{ $selectedClient->email ?? "" }}';

                if (sendToAgent && agentEmail) recipients.push(agentEmail);
                if (sendToClient && clientEmail) recipients.push(clientEmail);

                if (customEmails) {
                    customEmails.split(',').forEach(email => {
                        const trimmed = email.trim();
                        if (trimmed && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
                            recipients.push(trimmed);
                        }
                    });
                }

                if (recipients.length === 0) {
                    errorMsg.textContent = 'Please select at least one recipient or enter a valid email address.';
                    errorMsg.classList.remove('hidden');
                    successMsg.classList.add('hidden');
                    return;
                }

                submitBtn.disabled = true;
                btnText.textContent = 'Sending...';
                spinner.classList.remove('hidden');
                successMsg.classList.add('hidden');
                errorMsg.classList.add('hidden');

                try {
                    const companyId = '{{ $companyId }}';
                    const invoiceNumber = '{{ $invoiceNumber }}';
                    const url = `/invoice/${companyId}/${invoiceNumber}/send-email`;

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            recipients: recipients,
                            send_to_agent: sendToAgent,
                            send_to_client: sendToClient,
                            custom_emails: customEmails
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        successMsg.textContent = result.message;
                        successMsg.classList.remove('hidden');
                        errorMsg.classList.add('hidden');

                        setTimeout(() => {
                            closeModal();
                        }, 2000);
                    } else {
                        throw new Error(result.message || 'Failed to send email');
                    }

                } catch (error) {
                    errorMsg.textContent = error.message || 'An error occurred while sending the email.';
                    errorMsg.classList.remove('hidden');
                    successMsg.classList.add('hidden');
                } finally {
                    submitBtn.disabled = false;
                    btnText.textContent = 'Send Email';
                    spinner.classList.add('hidden');
                }
            });
        }

        // Payment Application Functions for Credit Payment
        function updatePaymentMode() {
            const modeRadio = document.querySelector('input[name="credit_payment_mode"]:checked');
            currentPaymentMode = modeRadio ? modeRadio.value : 'full';
            
            const splitSection = document.getElementById('splitGatewaySection');
            const descriptionEl = document.getElementById('paymentModeDescription');
            
            // Update description and show/hide split gateway section
            if (currentPaymentMode === 'full') {
                descriptionEl.textContent = 'Credit must cover entire invoice amount.';
                splitSection.classList.add('hidden');
            } else if (currentPaymentMode === 'split') {
                descriptionEl.textContent = 'Pay part with credit, rest with another gateway.';
                splitSection.classList.remove('hidden');
            } else if (currentPaymentMode === 'partial') {
                descriptionEl.textContent = 'Pay part with credit, remaining stays as unpaid balance.';
                splitSection.classList.add('hidden');
            }
            
            // Re-validate the current selection
            updatePaymentSelection();
        }
        
        function updatePaymentSelection() {
            const checkboxes = document.querySelectorAll('.payment-checkbox');
            
            // First pass: collect checked payments in FIFO order (list is already sorted by date)
            const checkedPayments = [];
            checkboxes.forEach(checkbox => {
                const creditId = checkbox.dataset.creditId;
                const amountInput = document.querySelector(`.payment-amount-input[data-credit-id="${creditId}"]`);
                const maxAmount = parseFloat(checkbox.dataset.availableBalance);
                
                if (checkbox.checked) {
                    amountInput.disabled = false;
                    checkedPayments.push({
                        creditId: creditId,
                        amountInput: amountInput,
                        maxAmount: maxAmount,
                        hasUserValue: amountInput.dataset.userEdited === 'true'
                    });
                } else {
                    amountInput.disabled = true;
                    amountInput.value = '';
                    amountInput.dataset.userEdited = 'false';
                }
            });
            
            // Second pass: FIFO auto-fill for payments without user-edited values
            let remainingToAllocate = invoiceAmount;
            let totalSelected = 0;
            
            checkedPayments.forEach(payment => {
                if (payment.hasUserValue && parseFloat(payment.amountInput.value) > 0) {
                    // User has manually set this value, respect it
                    const userAmount = parseFloat(payment.amountInput.value) || 0;
                    totalSelected += userAmount;
                    remainingToAllocate -= userAmount;
                } else {
                    // Auto-fill using FIFO: allocate as much as possible from oldest payments first
                    const autoAmount = Math.min(payment.maxAmount, Math.max(0, remainingToAllocate));
                    payment.amountInput.value = autoAmount.toFixed(3);
                    totalSelected += autoAmount;
                    remainingToAllocate -= autoAmount;
                }
            });
            
            // Update summary
            const totalSelectedEl = document.getElementById('creditModalTotalSelected');
            const differenceEl = document.getElementById('creditModalDifference');
            const excessWarning = document.getElementById('creditModalExcessWarning');
            const shortageWarning = document.getElementById('creditModalShortageWarning');
            const splitInfo = document.getElementById('creditModalSplitInfo');
            const partialInfo = document.getElementById('creditModalPartialInfo');
            const splitRemainingEl = document.getElementById('splitRemainingAmount');
            const applyBtn = document.getElementById('applyPaymentsBtn');
            
            const remaining = invoiceAmount - totalSelected;
            
            totalSelectedEl.textContent = totalSelected.toFixed(3) + ' KWD';
            differenceEl.textContent = remaining.toFixed(3) + ' KWD';
            
            if (splitRemainingEl) {
                splitRemainingEl.textContent = remaining.toFixed(3);
            }
            
            // Update difference color
            if (remaining <= 0) {
                differenceEl.classList.remove('text-red-600');
                differenceEl.classList.add('text-green-600');
            } else {
                differenceEl.classList.remove('text-green-600');
                differenceEl.classList.add('text-red-600');
            }
            
            // Hide all info messages first
            excessWarning.classList.add('hidden');
            shortageWarning.classList.add('hidden');
            splitInfo.classList.add('hidden');
            partialInfo.classList.add('hidden');
            
            // Validate based on payment mode
            let isValid = false;
            
            if (currentPaymentMode === 'full') {
                // Full payment: credit must cover entire invoice
                if (totalSelected >= invoiceAmount) {
                    isValid = true;
                    if (totalSelected > invoiceAmount) {
                        excessWarning.classList.remove('hidden');
                    }
                } else {
                    shortageWarning.classList.remove('hidden');
                }
            } else if (currentPaymentMode === 'split') {
                
                if (totalSelected > 0 && totalSelected < invoiceAmount) {
                    isValid = true;
                    splitInfo.classList.remove('hidden');
                } else if (totalSelected >= invoiceAmount) {
                    excessWarning.classList.remove('hidden');
                    document.getElementById('creditModalExcessWarning').innerHTML = 
                        '⚠️ Credit covers entire invoice. Consider using Full Payment mode.';
                    isValid = true; 
                } else {
                    shortageWarning.classList.remove('hidden');
                    document.getElementById('creditModalShortageWarning').innerHTML = 
                        '❌ Please select at least some credit amount.';
                }
            } else if (currentPaymentMode === 'partial') {
              
                if (totalSelected > 0 && totalSelected < invoiceAmount) {
                    isValid = true;
                    partialInfo.classList.remove('hidden');
                } else if (totalSelected >= invoiceAmount) {
                   
                    excessWarning.classList.remove('hidden');
                    document.getElementById('creditModalExcessWarning').innerHTML = 
                        '⚠️ Credit covers entire invoice. Consider using Full Payment mode.';
                    isValid = true; 
                } else {
                    shortageWarning.classList.remove('hidden');
                    document.getElementById('creditModalShortageWarning').innerHTML = 
                        '❌ Please select at least some credit amount.';
                }
            }
            
            applyBtn.disabled = !isValid || totalSelected === 0;
        }
        
        function markAsUserEdited(input) {
            input.dataset.userEdited = 'true';
            updatePaymentSelection();
        }
        
        async function applySelectedPayments() {
            const checkboxes = document.querySelectorAll('.payment-checkbox:checked');
            const paymentAllocations = [];
            
            checkboxes.forEach(checkbox => {
                const creditId = checkbox.dataset.creditId;
                const amountInput = document.querySelector(`.payment-amount-input[data-credit-id="${creditId}"]`);
                const amount = parseFloat(amountInput.value) || 0;
                
                if (amount > 0) {
                    paymentAllocations.push({
                        credit_id: parseInt(creditId),
                        amount: amount
                    });
                }
            });
            
            if (paymentAllocations.length === 0) {
                alert('Please select at least one payment/refund and enter an amount.');
                return;
            }
            
            const applyBtn = document.getElementById('applyPaymentsBtn');
            const originalText = applyBtn.textContent;
            applyBtn.disabled = true;
            applyBtn.textContent = 'Processing...';
            
            const requestBody = {
                invoice_id: "{{ $invoice->id }}",
                payment_allocations: paymentAllocations,
                payment_mode: currentPaymentMode
            };
            
            if (currentPaymentMode === 'split') {
                const gatewaySelect = document.getElementById('splitGateway');
                const methodSelect = document.getElementById('splitMethod');
                
                requestBody.other_gateway = gatewaySelect.value;
                requestBody.other_method = methodSelect.value;
                requestBody.charge_id = gatewaySelect.options[gatewaySelect.selectedIndex].dataset.chargeId || null;
            }
            
            try {
                const response = await fetch('{{ route("invoice.apply-payments") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message || 'Payments applied successfully!');
                    window.location.reload();
                } else {
                    alert(result.message || 'Failed to apply payments. Please try again.');
                    applyBtn.disabled = false;
                    applyBtn.textContent = originalText;
                }
            } catch (error) {
                console.error('Error applying payments:', error);
                alert('An error occurred. Please try again.');
                applyBtn.disabled = false;
                applyBtn.textContent = originalText;
            }
        }
    
        function lockInvoicePage() {
            // 1. Disable ALL inputs EXCEPT inside quick actions & share sections
            document.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.closest('#email-actions') || el.closest('#additional-actions')) return;
                el.disabled = true;
                el.style.cursor = 'not-allowed';
            });

            // 2. Hide action buttons completely
            ['openTaskModalButton', 'update-invoice-btn', 'applyPaymentsBtn'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });

            // 3. Disable payment type radio buttons
            document.querySelectorAll('input[name="payment_type"]').forEach(radio => {
                radio.disabled = true;
                if (radio.parentElement) {
                    radio.parentElement.style.opacity = '0.5';
                    radio.parentElement.style.pointerEvents = 'none';
                }
            });

            // Block clicks on entire payment type grid
            const paymentGrid = document.querySelector('#paymentMethod .grid');
            if (paymentGrid) paymentGrid.style.pointerEvents = 'none';

            // 4. Disable task price inputs & hide action cells
            document.querySelectorAll('[id^="invprice-table-"]').forEach(input => {
                input.disabled = true;
            });
            document.querySelectorAll('.action-cell').forEach(cell => {
                cell.style.display = 'none';
            });

            // 5. Block form submissions
            document.querySelectorAll('form').forEach(form => {
                if (form.closest('#email-actions') || form.closest('#additional-actions')) return;
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                });
            });

            // 6. Override save functions
            const blockedFns = ['savePartial', 'save', 'updateInvoice', 'updateGateway', 'saveSingleTask', 'removeTaskFromInvoice', 'saveTaskPrice', 'applySelectedPayments'];
            blockedFns.forEach(fn => {
                window[fn] = function() { return false; };
            });

            console.log('🔒 Invoice is locked — all editing disabled.');
        }

        function updateCreditRequired(rowIndex) {
            console.log('updateCreditRequired called for row:', rowIndex);
            const amountInput = document.getElementById(`amount_${rowIndex}`);
            const requiredEl = document.getElementById(`credit_required_${rowIndex}`);
            
            if (amountInput && requiredEl) {
                const newAmount = parseFloat(amountInput.value) || 0;
                requiredEl.textContent = newAmount.toFixed(3) + ' KWD';
                
                // Also update the badge amount
                const badge = document.getElementById(`card_amount_badge_${rowIndex}`);
                if (badge) {
                    badge.textContent = newAmount.toFixed(3) + ' KWD';
                }
                
                // Update PaymentSelection summary if credit gateway is selected
                const gatewaySelect = document.getElementById(`payment_gateway1_${rowIndex}`);
                if (gatewaySelect && gatewaySelect.value === 'Credit') {
                    // Pass the NEW amount, not the old one
                    PaymentSelection.updatePartialSummary(rowIndex);
                }
            }
        }

        function updateCreditOptionAvailability() {
            const splitInto = parseInt(document.getElementById('split-into1').value) || 0;
            
            // Find which row (if any) has Credit selected
            let creditUsedByRow = null;
            for (let i = 1; i <= splitInto; i++) {
                const gatewaySelect = document.getElementById(`payment_gateway1_${i}`);
                if (gatewaySelect && gatewaySelect.value === 'Credit') {
                    creditUsedByRow = i;
                    break;
                }
            }
            
            // Update all credit options
            for (let i = 1; i <= splitInto; i++) {
                const creditOption = document.getElementById(`credit_option1_${i}`);
                if (!creditOption) continue;
                
                if (creditUsedByRow !== null && creditUsedByRow !== i) {
                    // Credit is used by another row - disable and gray out this option
                    creditOption.disabled = true;
                    creditOption.textContent = `Credit (Used in Installment ${creditUsedByRow})`;
                    creditOption.style.color = '#9ca3af';
                } else if (creditUsedByRow === i) {
                    // This row is using credit - keep it enabled
                    creditOption.disabled = false;
                    creditOption.textContent = `Credit (${creditRemaining.toFixed(3)})`;
                    creditOption.style.color = '#000';
                } else {
                    // No row is using credit - enable based on amount availability
                    const amt = Number(document.getElementById(`amount_${i}`)?.value || 0);
                    const usesCredit = Number(creditUsed[i] || 0) > 0;
                    
                    creditOption.disabled = !usesCredit && (amt <= 0 || amt > creditRemaining);
                    creditOption.textContent = `Credit (${creditRemaining.toFixed(3)})`;
                    creditOption.style.color = creditOption.disabled ? '#9ca3af' : '#000';
                }
            }
        }

        function handleAmountChange(mode, installmentIndex) {
            const amountInput = document.getElementById(`amount_${installmentIndex}`);
            const badge = document.getElementById(`card_amount_badge_${installmentIndex}`);
            
            if (!amountInput) return;
            
            const newAmount = parseFloat(amountInput.value) || 0;
            
            // Update badge
            if (badge) {
                badge.textContent = `${newAmount.toFixed(3)} KWD`;
            }
            
            // Update footer totals
            updatePaymentSummary('partial');
            
            // Validate
            checkInputAmount(mode, installmentIndex);
        }
        
        /* Footer Summary */
        function updatePaymentSummary(mode) {
            const isPartial = mode === 'partial';

            const splitIntoId     = isPartial ? 'split-into1'               : 'split-into';
            const containerHdrId  = isPartial ? 'total-partial-container'   : 'total-split-container';
            const displayHdrId    = isPartial ? 'total-partial-display'     : 'total-split-display';
            const tooltipHdrId    = isPartial ? 'total-partial-tooltip'     : 'total-split-tooltip';
            const tooltipHdrTxtId = isPartial ? 'total-partial-tooltip-text': 'total-split-tooltip-text';
            const footerSummaryId = isPartial ? 'footer-summary'            : 'split-footer-summary';
            const footerTotalId   = isPartial ? 'footer-partial-total'      : 'split-footer-split-total';
            const footerBalanceId = isPartial ? 'footer-balance'            : 'split-footer-balance';
            const footerTipId     = isPartial ? 'footer-balance-tooltip'    : 'split-footer-balance-tooltip';
            const footerTipTxtId  = isPartial ? 'footer-balance-tooltip-text': 'split-footer-balance-tooltip-text';

            const splitIntoEl     = document.getElementById(splitIntoId);
            const splitInto       = parseInt(splitIntoEl?.value) || 0;

            const containerHdr    = document.getElementById(containerHdrId);
            const displayHdr      = document.getElementById(displayHdrId);
            const tooltipHdr      = document.getElementById(tooltipHdrId);
            const tooltipHdrTxt   = document.getElementById(tooltipHdrTxtId);
            const footerSummary   = document.getElementById(footerSummaryId);
            const footerTotal     = document.getElementById(footerTotalId);
            const footerBalance   = document.getElementById(footerBalanceId);
            const footerTip       = document.getElementById(footerTipId);
            const footerTipTxt    = document.getElementById(footerTipTxtId);

            if (splitInto <= 0) {
                containerHdr?.classList.add('hidden');
                footerSummary?.classList.add('hidden');
                return;
            }

            containerHdr?.classList.remove('hidden');
            if (footerSummary) {
                footerSummary.classList.remove('hidden');
                footerSummary.classList.add('flex');
            }

            let paymentTotal = 0;
            for (let i = 1; i <= splitInto; i++) {
                paymentTotal += parseFloat(document.getElementById(`amount_${i}`)?.value) || 0;
            }

            const invoiceTotal = parseFloat(document.getElementById('subTotal')?.value) || 0;
            const balance      = invoiceTotal - paymentTotal;

           
            if (displayHdr)   displayHdr.textContent   = paymentTotal.toFixed(3) + ' KWD';
            if (footerTotal)  footerTotal.textContent  = paymentTotal.toFixed(3) + ' KWD';

            const displayBalance = Math.abs(balance) < 0.001 ? 0 : balance;
            if (footerBalance) footerBalance.textContent = displayBalance.toFixed(3) + ' KWD';

            

            const allColors = ['text-green-600', 'text-red-600', 'text-amber-600'];

            const clearColors = (...els) => els.forEach(el => el?.classList.remove(...allColors));

            if (Math.abs(balance) < 0.001) {
                /* Match the invoice */
                clearColors(displayHdr, footerTotal, footerBalance);
                displayHdr?.classList.add('text-green-600');
                footerTotal?.classList.add('text-green-600');
                footerBalance?.classList.add('text-green-600');
                tooltipHdr?.classList.add('hidden');
                footerTip?.classList.add('hidden');

            } else if (balance < 0) {
                /*  Exceeds the invoice */
                const excess = Math.abs(balance).toFixed(3);
                clearColors(displayHdr, footerTotal, footerBalance);
                displayHdr?.classList.add('text-red-600');
                footerTotal?.classList.add('text-red-600');
                footerBalance?.classList.add('text-red-600');

                tooltipHdr?.classList.remove('hidden');
                tooltipHdr?.querySelector('svg')?.classList.replace('text-amber-500', 'text-red-500');
                footerTip?.classList.remove('hidden');
                footerTip?.querySelector('svg')?.classList.replace('text-amber-500', 'text-red-500');

                if (tooltipHdrTxt) tooltipHdrTxt.textContent = `Exceeds invoice by ${excess} KWD`;
                if (footerTipTxt)  footerTipTxt.textContent  = `Payment exceeds invoice by ${excess} KWD`;

            } else {
                /* Short from invoice */
                const shortage = balance.toFixed(3);
                clearColors(displayHdr, footerTotal, footerBalance);
                displayHdr?.classList.add('text-amber-600');
                footerTotal?.classList.add('text-amber-600');
                footerBalance?.classList.add('text-amber-600');

                tooltipHdr?.classList.remove('hidden');
                tooltipHdr?.querySelector('svg')?.classList.replace('text-red-500', 'text-amber-500');
                footerTip?.classList.remove('hidden');
                footerTip?.querySelector('svg')?.classList.replace('text-red-500', 'text-amber-500');

                if (tooltipHdrTxt) tooltipHdrTxt.textContent = `Short by ${shortage} KWD`;
                if (footerTipTxt)  footerTipTxt.textContent  = `Payment is short by ${shortage} KWD`;
            }

            // Charge summary for both partial and split
            const chargeStore      = chargeBreakdownStore[mode];
            const chargeSummaryId  = isPartial ? 'footer-charge-summary'  : 'split-footer-charge-summary';
            const serviceChargeId  = isPartial ? 'footer-service-charge'  : 'split-footer-service-charge';
            const clientPaysId     = isPartial ? 'footer-client-pays'     : 'split-footer-client-pays';

            const chargeSummaryEl  = document.getElementById(chargeSummaryId);
            const serviceChargeEl  = document.getElementById(serviceChargeId);
            const clientPaysEl     = document.getElementById(clientPaysId);

            let totalServiceCharge = 0;
            let totalClientPays    = 0;

            for (let i = 1; i <= splitInto; i++) {
                const charge = chargeStore[i];
                const baseAmount = parseFloat(document.getElementById(`amount_${i}`)?.value) || 0;

                if (charge) {
                    totalServiceCharge += charge.gatewayFee;
                    totalClientPays    += charge.finalAmount;
                } else {
                    totalClientPays += baseAmount;
                }
            }

            if (totalServiceCharge > 0 && chargeSummaryEl) {
                serviceChargeEl.textContent = totalServiceCharge.toFixed(3) + ' KWD';
                clientPaysEl.textContent    = totalClientPays.toFixed(3) + ' KWD';
                chargeSummaryEl.classList.remove('hidden');
                chargeSummaryEl.classList.add('flex');
            } else {
                chargeSummaryEl?.classList.add('hidden');
                chargeSummaryEl?.classList.remove('flex');
            }
        }

        function handleCreditGatewayChange(mode, i) {
            const isPartial     = mode === 'partial';
            const gatewayId     = isPartial ? `payment_gateway1_${i}` : `payment_gateway_${i}`;
            const gatewaySelect = document.getElementById(gatewayId);
            const selectedGw    = gatewaySelect.value;
            const key           = gwKey(selectedGw);
            const creditPanel   = document.getElementById(`credit_panel_${i}`);
            const amountInput   = document.getElementById(`amount_${i}`);

            if (key === gwKey('credit')) {

                // ── SPLIT: each client can use credit once, but different clients can each use it ──
                if (mode === 'split') {
                    const thisClientId = document.getElementById(`customer_name_${i}`)?.value;

                    if (!thisClientId) {
                        alert('Please select a client first before choosing credit payment.');
                        gatewaySelect.value = '';
                        return;
                    }

                    // Check if another row with the SAME client is already using credit
                    const splitInto = parseInt(document.getElementById('split-into')?.value) || 0;
                    for (let j = 1; j <= splitInto; j++) {
                        if (j === i) continue;
                        const otherGw       = document.getElementById(`payment_gateway_${j}`)?.value;
                        const otherClientId = document.getElementById(`customer_name_${j}`)?.value;
                        if (gwKey(otherGw) === gwKey('credit') && otherClientId === thisClientId) {
                            alert(`This client is already using credit in Installment ${j}. Each client can only use credit once.`);
                            gatewaySelect.value = '';
                            return;
                        }
                    }
                }

                // ── PARTIAL: single invoice client — only one installment can use credit ──
                if (mode === 'partial') {
                    if (creditSelectionState.selectedInstallment && creditSelectionState.selectedInstallment !== i) {
                        alert(`Credit is already being used in Installment ${creditSelectionState.selectedInstallment}. Only one installment can use credit.`);
                        gatewaySelect.value = '';
                        return;
                    }
                    creditSelectionState.selectedInstallment = i;
                }

                // Show credit panel
                if (creditPanel) {
                    creditPanel.classList.remove('hidden');
                    requestAnimationFrame(() => {
                        creditPanel.style.opacity  = '1';
                        creditPanel.style.transform = 'translateX(0)';
                    });
                }

                // Lock amount input
                amountInput.readOnly = true;
                amountInput.style.backgroundColor = '#f3f4f6';
                amountInput.title = 'Amount will be set based on your voucher selection';

                // Load vouchers (mode-aware)
                loadCreditVouchers(mode, i);

                // Update credit options across all rows
                updateAllCreditOptions(mode);

            } else {

                // Hide credit panel
                if (creditPanel) {
                    creditPanel.style.opacity   = '0';
                    creditPanel.style.transform = 'translateX(10px)';
                    setTimeout(() => creditPanel.classList.add('hidden'), 300);
                }

                // Restore amount input
                amountInput.readOnly = false;
                amountInput.style.backgroundColor = '';
                amountInput.title = '';

                // Clear partial state if this row was using credit
                if (mode === 'partial' && creditSelectionState.selectedInstallment === i) {
                    creditSelectionState.selectedInstallment  = null;
                    creditSelectionState.totalCreditSelected  = 0;
                    recalculateWithoutCredit(mode);
                }

                // For split, just recalculate without credit for this row
                if (mode === 'split') {
                    recalculateWithoutCredit(mode);
                }

                PaymentSelection.hideForRow(mode, i);
                updateAllCreditOptions(mode);
            }

            updatePaymentSummary(mode);
        }

        function syncInstallmentUI(rowId) {
            const [mode, idxStr] = rowId.split('_');
            const i = parseInt(idxStr);
            if (!i) return;

            const totalSelected = PaymentSelection.getSelectedTotal(rowId);

            // Update amount input (only if credit-controlled / readonly)
            const amountInput = document.getElementById(`amount_${i}`);
            if (amountInput && amountInput.readOnly) {
                amountInput.value = totalSelected.toFixed(3);
            }

            // Update card badge
            const badgeId = mode === 'partial' ? `card_amount_badge_${i}` : `split_card_amount_badge_${i}`;
            const badge = document.getElementById(badgeId);
            if (badge) badge.textContent = `${totalSelected.toFixed(3)} KWD`;

            // Update credit selected display
            const selectedDisplay = document.getElementById(`credit_selected_${i}`);
            if (selectedDisplay) selectedDisplay.textContent = `${totalSelected.toFixed(3)} KWD`;

            // Update credit status
            const statusDisplay = document.getElementById(`credit_status_${i}`);
            if (statusDisplay) {
                statusDisplay.textContent = totalSelected > 0 ? 'Credit selected ✓' : 'No vouchers selected';
                statusDisplay.className = totalSelected > 0 ? 'text-green-600 font-medium' : 'text-gray-500';
            }

            // Update global credit tracking
            creditSelectionState.totalCreditSelected = totalSelected;
            creditUsed[i] = totalSelected;

            // Recalculate other installments and footer
            recalculateAfterCreditSelection(mode, i, totalSelected);
            updatePaymentSummary(mode);
        }
    </script>

</x-app-layout>