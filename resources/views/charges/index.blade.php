<x-app-layout>

    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Charges</h2>
            <div data-tooltip="number of Charges"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $totalCharges }}</span>
            </div>
        </div>
        <!-- add new charge & refresh page -->
        <div class="flex items-center gap-5">
            @if(auth()->user()->hasRole('admin'))
            <select
                onchange="window.location='{{ route('charges.index') }}?company_id='+this.value"
                name="company_id" id="company_id" class="px-4 py-2 border border-gray-300 rounded-full bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm text-sm font-medium text-gray-700 cursor-pointer">
                @foreach(App\Models\Company::all() as $company)
                <option value="{{ $company->id }}" {{ $companyId == $company->id ? 'selected' : '' }}>
                    {{ $company->name }}
                </option>
                @endforeach
            </select>
            @endif
            <div data-tooltip-left="Reload"
                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>
            <!-- Add New Charge Button -->
            <div x-data="{ createModal: false }" class="relative">
                @if(auth()->user()->hasRole('admin'))
                <div id="createCharge" data-tooltip-left="Add new charge"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm cursor-pointer"
                    @click="createModal = true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7">
                        </path>
                    </svg>
                </div>
                @else
                <div id="createCharge" data-tooltip-left="Add custom gateway (system gateways managed by admin)"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm cursor-pointer"
                    @click="createModal = true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7">
                        </path>
                    </svg>
                </div>
                @endif

                <!-- Create Charge Modal -->
                <div x-cloak x-show="createModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-30 backdrop-blur-sm">
                    <div class="bg-white rounded-lg w-full max-w-lg shadow max-h-[80vh] flex flex-col" @click.away="createModal = false">
                        <div class="flex items-center justify-between px-5 pt-6 pb-4">
                            <h2 class="text-lg font-bold">Create New Charges</h2>
                            <button @click="createModal = false" class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">&times;</button>
                        </div>

                        <div class="overflow-y-auto px-8 pb-8 [scrollbar-gutter:stable]">
                            <form method="POST" action="{{ route('charges.store') }}">
                                @csrf

                                <div class="mb-4">
                                    <label class="block text-sm font-medium">Gateway Name</label>
                                    <input type="text" name="name" class="w-full border px-3 py-2 rounded-full" placeholder="Enter charge name" required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium">Charges For</label>
                                    <input type="text" name="type" value="Payment Gateway" class="w-full border px-3 py-2 rounded-full bg-gray-100 text-gray-700 cursor-not-allowed" readonly>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium">Amount</label>
                                    <input type="number" name="amount" step="0.01" class="w-full border px-3 py-2 rounded-full" placeholder="Enter charge amount" required>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium">Self Charge</label>
                                    <input type="number" name="self_charge" step="0.01" class="w-full border px-3 py-2 rounded-full" placeholder="Enter self charge amount (optional)">
                                    <p class="text-xs text-gray-500 mt-1">If set, this will override the gateway amount</p>
                                </div>

                                <div class="mb-4 flex gap-4">
                                    <div class="w-1/2">
                                        <label class="block text-sm font-medium">Paid By</label>
                                        <select name="paid_by" class="w-full border px-3 py-2 rounded-full" required>
                                            <option value="" disabled selected hidden></option>
                                            <option value="Company">Company</option>
                                            <option value="Client">Client</option>
                                        </select>
                                    </div>
                                    <div class="w-1/2">
                                        <label class="block text-sm font-medium">Charge Type</label>
                                        <select name="charge_type" class="w-full border px-3 py-2 rounded-full" required>
                                            <option value="" disabled selected hidden></option>
                                            <option value="Flat Rate">Flat Rate</option>
                                            <option value="Percent">Percent</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium">COA (Assets) for Debited Bank Account</label>
                                    <select name="coa" class="w-full border px-3 py-2 rounded-full" required>
                                        <option value="" disabled selected hidden></option>
                                        <option value="Kuwait International Bank" class="text-black">Kuwait International Bank</option>
                                        <option value="Ahli United Bank Kuwait" class="text-black">Ahli United Bank Kuwait</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium">Description</label>
                                    <input type="text" name="description" class="w-full border px-3 py-2 rounded-full">
                                </div>

                                <div class="mb-4">
                                    <label for="api_key" class="block text-sm font-medium">API Key</label>
                                    <input type="text" name="api_key" required class="w-full border px-3 py-2 rounded-full" placeholder="Paste your secret key">
                                    <p class="text-xs text-gray-500 mt-1">This key is required to connect with the payment gateway.</p>
                                </div>

                                <div class="mb-4 flex gap-4">
                                    <div class="w-1/2">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="is_active" id="is_active" value="1" checked
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                            <label for="is_active" class="ml-2 text-sm font-medium text-gray-700">Active</label>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">If unchecked, gateway will be saved but inactive</p>
                                    </div>
                                    <div class="w-1/2">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="can_generate_link" id="can_generate_link" value="1" checked
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                            <label for="can_generate_link" class="ml-2 text-sm font-medium text-gray-700">Can Generate Link</label>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Allow customers to pay invoices using this gateway</p>
                                    </div>
                                </div>

                                <!-- Auto Payment and External URL Settings -->
                                <div class="mb-4 flex gap-4">
                                    <div class="w-1/3">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="is_auto_paid" id="is_auto_paid" value="1" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                            <label for="is_auto_paid" class="ml-2 text-sm font-medium text-gray-700">Auto Payment</label>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Invoice will be automatically paid</p>
                                    </div>
                                    <div class="w-1/3">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="has_url" id="has_url" value="1" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                            <label for="has_url" class="ml-2 text-sm font-medium text-gray-700">External URL</label>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Can put external payment gateway URL</p>
                                    </div>
                                    <div class="w-1/3">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="can_charge_invoice" id="can_charge_invoice" value="1" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                            <label for="can_charge_invoice" class="ml-2 text-sm font-medium text-gray-700">Invoice Charge</label>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">Allow charging additional fees on invoices</p>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center mt-6">
                                    <button type="button" @click="createModal = false" class="bg-gray-300 px-4 py-2 rounded-full">Cancel</button>
                                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-full">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>


    </div>

    <div class="tableCon">
        <div class="content-70">
            <div class="panel oxShadow rounded-lg">
                <!-- <x-search action="{{ route('charges.index') }}" searchParam='search' placeholder="Quick search for charges" /> -->

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <!-- table -->
                    <div x-data="{open: {},
                            createModal: false,
                            editParentModal: null,
                            editChildModal: null,
                            editCredsModal: null,

                            closeAll() {
                                this.editParentModal = null;
                                this.editChildModal = null;
                                this.editCredsModal = null;
                                this.createModal = false;
                            }
                        }">
                        <div class="dataTable-container h-max overflow-x-auto">
                            <table id="myTable" class="table-hover whitespace-nowrap dataTable-table w-full relative" x-data="{ open: {} }">
                                <thead>
                                    <tr>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Charge Name</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Paid By</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Self Charge</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Charge Type</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500">Active</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-500 sticky right-0 bg-white z-10">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if ($charges->isEmpty())
                                    <tr>
                                        <td colspan="12" class="p-6 text-center text-sm text-blue-500 align-middle">
                                            No charges found.
                                        </td>
                                    </tr>
                                    @else
                                    @foreach ($charges as $charge)
                                    <tr class="cursor-pointer bg-gray-100 hover:bg-gray-200" @click="open[{{ $charge->id }}] = !open[{{ $charge->id }}]">
                                        <td class="p-3 font-bold text-gray-800 bg-gray-100" colspan="5">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $charge->name }}</span>
                                                @if($charge->is_system_default)
                                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold bg-purple-100 text-purple-800 rounded-full">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                    System Gateway
                                                </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="p-3 bg-gray-100 sticky right-0 z-10">
                                            <div class="relative group inline-block">
                                                <button @click.stop="editCredsModal = {{ $charge->id }}" class="text-blue-600 hover:text-blue-800">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                                            d="M12 8.25C9.92894 8.25 8.25 9.92893 8.25 12C8.25 14.0711 9.92894 15.75 12 15.75C14.0711 15.75 15.75 14.0711 15.75 12C15.75 9.92893 14.0711 8.25 12 8.25ZM9.75 12C9.75 10.7574 10.7574 9.75 12 9.75C13.2426 9.75 14.25 10.7574 14.25 12C14.25 13.2426 13.2426 14.25 12 14.25C10.7574 14.25 9.75 13.2426 9.75 12Z"
                                                            fill="currentColor" />
                                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                                            d="M11.9747 1.25C11.5303 1.24999 11.1592 1.24999 10.8546 1.27077C10.5375 1.29241 10.238 1.33905 9.94761 1.45933C9.27379 1.73844 8.73843 2.27379 8.45932 2.94762C8.31402 3.29842 8.27467 3.66812 8.25964 4.06996C8.24756 4.39299 8.08454 4.66251 7.84395 4.80141C7.60337 4.94031 7.28845 4.94673 7.00266 4.79568C6.64714 4.60777 6.30729 4.45699 5.93083 4.40743C5.20773 4.31223 4.47642 4.50819 3.89779 4.95219C3.64843 5.14353 3.45827 5.3796 3.28099 5.6434C3.11068 5.89681 2.92517 6.21815 2.70294 6.60307L2.67769 6.64681C2.45545 7.03172 2.26993 7.35304 2.13562 7.62723C1.99581 7.91267 1.88644 8.19539 1.84541 8.50701C1.75021 9.23012 1.94617 9.96142 2.39016 10.5401C2.62128 10.8412 2.92173 11.0602 3.26217 11.2741C3.53595 11.4461 3.68788 11.7221 3.68786 12C3.68785 12.2778 3.53592 12.5538 3.26217 12.7258C2.92169 12.9397 2.62121 13.1587 2.39007 13.4599C1.94607 14.0385 1.75012 14.7698 1.84531 15.4929C1.88634 15.8045 1.99571 16.0873 2.13552 16.3727C2.26983 16.6469 2.45535 16.9682 2.67758 17.3531L2.70284 17.3969C2.92507 17.7818 3.11058 18.1031 3.28089 18.3565C3.45817 18.6203 3.64833 18.8564 3.89769 19.0477C4.47632 19.4917 5.20763 19.6877 5.93073 19.5925C6.30717 19.5429 6.647 19.3922 7.0025 19.2043C7.28833 19.0532 7.60329 19.0596 7.8439 19.1986C8.08452 19.3375 8.24756 19.607 8.25964 19.9301C8.27467 20.3319 8.31403 20.7016 8.45932 21.0524C8.73843 21.7262 9.27379 22.2616 9.94761 22.5407C10.238 22.661 10.5375 22.7076 10.8546 22.7292C11.1592 22.75 11.5303 22.75 11.9747 22.75H12.0252C12.4697 22.75 12.8407 22.75 13.1454 22.7292C13.4625 22.7076 13.762 22.661 14.0524 22.5407C14.7262 22.2616 15.2616 21.7262 15.5407 21.0524C15.686 20.7016 15.7253 20.3319 15.7403 19.93C15.7524 19.607 15.9154 19.3375 16.156 19.1985C16.3966 19.0596 16.7116 19.0532 16.9974 19.2042C17.3529 19.3921 17.6927 19.5429 18.0692 19.5924C18.7923 19.6876 19.5236 19.4917 20.1022 19.0477C20.3516 18.8563 20.5417 18.6203 20.719 18.3565C20.8893 18.1031 21.0748 17.7818 21.297 17.3969L21.3223 17.3531C21.5445 16.9682 21.7301 16.6468 21.8644 16.3726C22.0042 16.0872 22.1135 15.8045 22.1546 15.4929C22.2498 14.7697 22.0538 14.0384 21.6098 13.4598C21.3787 13.1586 21.0782 12.9397 20.7378 12.7258C20.464 12.5538 20.3121 12.2778 20.3121 11.9999C20.3121 11.7221 20.464 11.4462 20.7377 11.2742C21.0783 11.0603 21.3788 10.8414 21.6099 10.5401C22.0539 9.96149 22.2499 9.23019 22.1547 8.50708C22.1136 8.19546 22.0043 7.91274 21.8645 7.6273C21.7302 7.35313 21.5447 7.03183 21.3224 6.64695L21.2972 6.60318C21.0749 6.21825 20.8894 5.89688 20.7191 5.64347C20.5418 5.37967 20.3517 5.1436 20.1023 4.95225C19.5237 4.50826 18.7924 4.3123 18.0692 4.4075C17.6928 4.45706 17.353 4.60782 16.9975 4.79572C16.7117 4.94679 16.3967 4.94036 16.1561 4.80144C15.9155 4.66253 15.7524 4.39297 15.7403 4.06991C15.7253 3.66808 15.686 3.2984 15.5407 2.94762C15.2616 2.27379 14.7262 1.73844 14.0524 1.45933C13.762 1.33905 13.4625 1.29241 13.1454 1.27077C12.8407 1.24999 12.4697 1.24999 12.0252 1.25H11.9747ZM10.5216 2.84515C10.5988 2.81319 10.716 2.78372 10.9567 2.76729C11.2042 2.75041 11.5238 2.75 12 2.75C12.4762 2.75 12.7958 2.75041 13.0432 2.76729C13.284 2.78372 13.4012 2.81319 13.4783 2.84515C13.7846 2.97202 14.028 3.21536 14.1548 3.52165C14.1949 3.61826 14.228 3.76887 14.2414 4.12597C14.271 4.91835 14.68 5.68129 15.4061 6.10048C16.1321 6.51968 16.9974 6.4924 17.6984 6.12188C18.0143 5.9549 18.1614 5.90832 18.265 5.89467C18.5937 5.8514 18.9261 5.94047 19.1891 6.14228C19.2554 6.19312 19.3395 6.27989 19.4741 6.48016C19.6125 6.68603 19.7726 6.9626 20.0107 7.375C20.2488 7.78741 20.4083 8.06438 20.5174 8.28713C20.6235 8.50382 20.6566 8.62007 20.6675 8.70287C20.7108 9.03155 20.6217 9.36397 20.4199 9.62698C20.3562 9.70995 20.2424 9.81399 19.9397 10.0041C19.2684 10.426 18.8122 11.1616 18.8121 11.9999C18.8121 12.8383 19.2683 13.574 19.9397 13.9959C20.2423 14.186 20.3561 14.29 20.4198 14.373C20.6216 14.636 20.7107 14.9684 20.6674 15.2971C20.6565 15.3799 20.6234 15.4961 20.5173 15.7128C20.4082 15.9355 20.2487 16.2125 20.0106 16.6249C19.7725 17.0373 19.6124 17.3139 19.474 17.5198C19.3394 17.72 19.2553 17.8068 19.189 17.8576C18.926 18.0595 18.5936 18.1485 18.2649 18.1053C18.1613 18.0916 18.0142 18.045 17.6983 17.8781C16.9973 17.5075 16.132 17.4803 15.4059 17.8995C14.68 18.3187 14.271 19.0816 14.2414 19.874C14.228 20.2311 14.1949 20.3817 14.1548 20.4784C14.028 20.7846 13.7846 21.028 13.4783 21.1549C13.4012 21.1868 13.284 21.2163 13.0432 21.2327C12.7958 21.2496 12.4762 21.25 12 21.25C11.5238 21.25 11.2042 21.2496 10.9567 21.2327C10.716 21.2163 10.5988 21.1868 10.5216 21.1549C10.2154 21.028 9.97201 20.7846 9.84514 20.4784C9.80512 20.3817 9.77195 20.2311 9.75859 19.874C9.72896 19.0817 9.31997 18.3187 8.5939 17.8995C7.86784 17.4803 7.00262 17.5076 6.30158 17.8781C5.98565 18.0451 5.83863 18.0917 5.73495 18.1053C5.40626 18.1486 5.07385 18.0595 4.81084 17.8577C4.74458 17.8069 4.66045 17.7201 4.52586 17.5198C4.38751 17.314 4.22736 17.0374 3.98926 16.625C3.75115 16.2126 3.59171 15.9356 3.4826 15.7129C3.37646 15.4962 3.34338 15.3799 3.33248 15.2971C3.28921 14.9684 3.37828 14.636 3.5801 14.373C3.64376 14.2901 3.75761 14.186 4.0602 13.9959C4.73158 13.5741 5.18782 12.8384 5.18786 12.0001C5.18791 11.1616 4.73165 10.4259 4.06021 10.004C3.75769 9.81389 3.64385 9.70987 3.58019 9.62691C3.37838 9.3639 3.28931 9.03149 3.33258 8.7028C3.34348 8.62001 3.37656 8.50375 3.4827 8.28707C3.59181 8.06431 3.75125 7.78734 3.98935 7.37493C4.22746 6.96253 4.3876 6.68596 4.52596 6.48009C4.66055 6.27983 4.74468 6.19305 4.81093 6.14222C5.07395 5.9404 5.40636 5.85133 5.73504 5.8946C5.83873 5.90825 5.98576 5.95483 6.30173 6.12184C7.00273 6.49235 7.86791 6.51962 8.59394 6.10045C9.31998 5.68128 9.72896 4.91837 9.75859 4.12602C9.77195 3.76889 9.80512 3.61827 9.84514 3.52165C9.97201 3.21536 10.2154 2.97202 10.5216 2.84515Z"
                                                            fill="currentColor" />
                                                    </svg>
                                                </button>
                                                <div class="absolute bottom-full right-0 hidden group-hover:block text-xs text-white bg-black px-2 py-1 rounded shadow-md z-10">
                                                    API Settings
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    @if ($charge->methods->isNotEmpty())
                                    @foreach ($charge->methods as $method)
                                    <tr x-cloak x-show="open[{{ $charge->id }}]" x-transition>
                                        <td class="p-3 pl-6 text-sm text-gray-600">{{ $method->english_name }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $method->paid_by }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ number_format($method->self_charge ?? 0, 2) }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $method->charge_type }}</td>
                                        <td class="p-3 text-sm">
                                            @if($charge->is_active && $method->is_active)
                                            <span class="inline-flex px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                                            @else
                                            <span class="inline-flex px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-sm text-gray-600 sticky right-0 bg-white z-10">
                                            <div class="relative group inline-block">
                                                <button @click.stop="editChildModal = {{ $method->id }}" class="text-blue-600 hover:text-blue-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536M9 11l6.768-6.768a2.5 2.5 0 113.536 3.536L12.536 14.5H9v-3.5z" />
                                                    </svg>
                                                </button>
                                                <div class="absolute bottom-full mb-1 hidden group-hover:block text-xs text-white bg-black px-2 py-1 rounded shadow-md z-10">
                                                    Edit
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Payment Method Edit Modal -->
                                    <div x-cloak x-show="editChildModal === {{ $method->id }}" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-30 backdrop-blur-sm">
                                        <div class="bg-white rounded-xl w-full max-w-lg shadow-xl max-h-[85vh] flex flex-col" @click.away="editChildModal = null">
                                            <!-- Header -->
                                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                                <div>
                                                    <h2 class="text-lg font-bold text-gray-800">Edit Payment Method</h2>
                                                    <p class="text-sm text-gray-500">{{ $charge->name }} - {{ $method->english_name }}</p>
                                                </div>
                                                <button @click="editChildModal = null" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>

                                            <!-- Body -->
                                            <div class="overflow-y-auto flex-1 px-6 py-4">
                                                <form id="editMethodForm_{{ $method->id }}" action="{{ route('payment-method.update', $method->id) }}" method="POST">
                                                    @csrf
                                                    @method('PUT')

                                                    <!-- Basic Info Section -->
                                                    <div class="mb-6">
                                                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                                            Basic Information
                                                        </h3>
                                                        <div class="space-y-3">
                                                            <div class="grid grid-cols-2 gap-3">
                                                                <div>
                                                                    <label class="block text-xs font-medium text-gray-500 mb-1">Arabic Name</label>
                                                                    <input type="text" value="{{ $method->arabic_name }}" class="w-full border border-gray-200 bg-gray-50 px-3 py-2 rounded-lg text-sm text-gray-600" readonly />
                                                                </div>
                                                                <div>
                                                                    <label class="block text-xs font-medium text-gray-500 mb-1">English Name</label>
                                                                    <input type="text" value="{{ $method->english_name }}" class="w-full border border-gray-200 bg-gray-50 px-3 py-2 rounded-lg text-sm text-gray-600" readonly />
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-500 mb-1">Currency</label>
                                                                <input type="text" value="{{ $method->currency }}" class="w-full border border-gray-200 bg-gray-50 px-3 py-2 rounded-lg text-sm text-gray-600" readonly>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Charges Section -->
                                                    <div class="mb-6">
                                                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                                            Charges
                                                        </h3>
                                                        <div class="grid grid-cols-2 gap-3">
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-500 mb-1">Self Charge</label>
                                                                <input type="text" name="self_charge" value="{{ $method->self_charge }}" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-500 mb-1">API Service Charge</label>
                                                                <input type="text" name="service_charge" value="{{ $method->service_charge }}" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                            </div>
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-3 mt-3">
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-500 mb-1">Paid By</label>
                                                                <select name="paid_by" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                                    <option value="Company" @selected(old('paid_by', $method->paid_by) === 'Company')>Company</option>
                                                                    <option value="Client" @selected(old('paid_by', $method->paid_by) === 'Client')>Client</option>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-500 mb-1">Charge Type</label>
                                                                <select name="charge_type" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                                    <option value="Flat Rate" @selected(old('charge_type', $method->charge_type) === 'Flat Rate')>Flat Rate</option>
                                                                    <option value="Percent" @selected(old('charge_type', $method->charge_type) === 'Percent')>Percent</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Description -->
                                                    <div class="mb-6">
                                                        <label class="block text-xs font-medium text-gray-500 mb-1">Description</label>
                                                        <input type="text" name="description" value="{{ $method->description }}" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter description" />
                                                    </div>

                                                    <!-- Settings Section -->
                                                    <div class="mb-2">
                                                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                                            Settings
                                                        </h3>

                                                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                                                            <!-- Active Toggle - This one uses $method (exists in payment_methods) -->
                                                            @if($charge->is_active)
                                                            <div class="flex items-center justify-between py-2">
                                                                <div>
                                                                    <p class="text-sm font-medium text-gray-700">Active</p>
                                                                    <p class="text-xs text-gray-400">Enable this payment method</p>
                                                                </div>
                                                                <label class="relative inline-flex items-center cursor-pointer">
                                                                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $method->is_active ? 'checked' : '' }}>
                                                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                </label>
                                                            </div>
                                                            @endif

                                                            <!-- Invoice Charge Toggle - Uses $charge (exists in charges table) -->
                                                            <div class="flex items-center justify-between py-2 border-t border-gray-200">
                                                                <div>
                                                                    <p class="text-sm font-medium text-gray-700">Invoice Charge</p>
                                                                    <p class="text-xs text-gray-400">Allow charging fees on invoices</p>
                                                                </div>
                                                                <label class="relative inline-flex items-center cursor-pointer">
                                                                    <input type="checkbox" name="can_charge_invoice" value="1" class="sr-only peer" {{ $charge->can_charge_invoice ? 'checked' : '' }}>
                                                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                </label>
                                                            </div>

                                                            <!-- Auto Payment Toggle - Uses $charge -->
                                                            <div class="flex items-center justify-between py-2 border-t border-gray-200">
                                                                <div>
                                                                    <p class="text-sm font-medium text-gray-700">Auto Payment</p>
                                                                    <p class="text-xs text-gray-400">Auto mark as paid after transaction</p>
                                                                </div>
                                                                <label class="relative inline-flex items-center cursor-pointer">
                                                                    <input type="checkbox" name="is_auto_paid" value="1" class="sr-only peer" {{ $charge->is_auto_paid ? 'checked' : '' }}>
                                                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                </label>
                                                            </div>

                                                            <!-- External URL Toggle - Uses $charge -->
                                                            <div class="flex items-center justify-between py-2 border-t border-gray-200">
                                                                <div>
                                                                    <p class="text-sm font-medium text-gray-700">External URL</p>
                                                                    <p class="text-xs text-gray-400">Allow external payment gateway URL</p>
                                                                </div>
                                                                <label class="relative inline-flex items-center cursor-pointer">
                                                                    <input type="checkbox" name="has_url" value="1" class="sr-only peer" {{ $charge->has_url ? 'checked' : '' }}>
                                                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                </label>
                                                            </div>

                                                            <!-- Generate Link Toggle - Uses $charge -->
                                                            <div class="flex items-center justify-between py-2 border-t border-gray-200">
                                                                <div>
                                                                    <p class="text-sm font-medium text-gray-700">Generate Link</p>
                                                                    <p class="text-xs text-gray-400">Allow generating payment links</p>
                                                                </div>
                                                                <label class="relative inline-flex items-center cursor-pointer">
                                                                    <input type="checkbox" name="can_generate_link" value="1" class="sr-only peer" {{ $charge->can_generate_link ? 'checked' : '' }}>
                                                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>

                                            <!-- Footer -->
                                            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                                                <button type="button" @click="editChildModal = null" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                                    Cancel
                                                </button>
                                                <button type="submit" form="editMethodForm_{{ $method->id }}" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                                                    Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                    @else
                                    <tr x-cloak x-show="open[{{ $charge->id }}]" x-transition>
                                        <td colspan="14" class="p-3 pl-6 italic text-sm text-red-500 text-center align-middle">
                                            No payment method for this payment gateway
                                        </td>
                                    </tr>
                                    <tr x-cloak x-show="open[{{ $charge->id }}]" x-transition>
                                        <td class="p-3 pl-6 text-sm text-gray-600">{{ $charge->name }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $charge->paid_by }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ number_format($charge->self_charge ?? 0, 2) }}</td>
                                        <td class="p-3 text-sm text-gray-600">{{ $charge->charge_type }}</td>
                                        <td class="p-3 text-sm">
                                            @if($charge->is_active)
                                            <span class="inline-flex px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                                            @else
                                            <span class="inline-flex px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-sm sticky right-0 bg-white z-10">
                                            <div class="flex items-center gap-3">
                                                <!-- Edit Button -->
                                                <div class="relative group inline-block">
                                                    <button
                                                        @click.stop="editParentModal = {{ $charge->id }}"
                                                        class="text-blue-600 hover:text-blue-800">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <title>Edit</title>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536M9 11l6.768-6.768a2.5 2.5 0 113.536 3.536L12.536 14.5H9v-3.5z" />
                                                        </svg>
                                                    </button>
                                                    <div class="absolute bottom-full mb-1 hidden group-hover:block text-xs text-white bg-black px-2 py-1 rounded shadow-md z-10">
                                                        Edit
                                                    </div>
                                                </div>

                                                <!-- Delete Button -->
                                                @if($charge->can_be_deleted)
                                                <div class="relative group inline-block">
                                                    <form method="POST" action="{{ route('charges.destroy', $charge->id)}}" onsubmit="return confirm('Are you sure?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-800">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <title>Delete</title>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0a2 2 0 00-2-2H9a2 2 0 00-2 2m12 0H3" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                    <div class="absolute bottom-full mb-1 hidden group-hover:block text-xs text-white bg-black px-2 py-1 rounded shadow-md z-10">
                                                        Delete
                                                    </div>
                                                </div>
                                                @else
                                                <div class="relative group inline-block">
                                                    <button type="button" class="text-gray-400 cursor-not-allowed" disabled>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <title>System gateway cannot be deleted</title>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0a2 2 0 00-2-2H9a2 2 0 00-2 2m12 0H3" />
                                                        </svg>
                                                    </button>
                                                </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                    <!-- Parent Method Edit Modal -->
                                    <div x-cloak x-show="editParentModal === {{ $charge->id }}" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-30 backdrop-blur-sm">
                                        <div class="bg-white rounded-lg w-full max-w-lg shadow max-h-[80vh] flex flex-col" @click.away="editParentModal = null">
                                            <div class="flex items-center justify-between px-5 pt-6 pb-4">
                                                <div class="flex items-center gap-2">
                                                    <h2 class="text-lg font-bold">Edit Gateway Charges</h2>
                                                    @if($charge->is_system_default && !auth()->user()->hasRole('admin'))
                                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded-full">
                                                        Limited Editing
                                                    </span>
                                                    @endif
                                                </div>
                                                <button @click="editParentModal = null" class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">&times;</button>
                                            </div>

                                            <div class="overflow-y-auto px-8 pb-8 [scrollbar-gutter:stable]">
                                                <!-- Parent Method Edit Modal -->
                                                <div x-cloak x-show="editParentModal === {{ $charge->id }}" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-30 backdrop-blur-sm">
                                                    <div class="bg-white rounded-xl w-full max-w-lg shadow-xl max-h-[85vh] flex flex-col" @click.away="editParentModal = null">
                                                        <!-- Header -->
                                                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                                            <div>
                                                                <h2 class="text-lg font-bold text-gray-800">Edit Gateway Charges</h2>
                                                                <p class="text-sm text-gray-500">{{ $charge->name }}</p>
                                                            </div>
                                                            <button @click="editParentModal = null" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                </svg>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="overflow-y-auto flex-1 px-6 py-4">
                                                            <form id="editChargeForm_{{ $charge->id }}" action="{{ route('charges.update', $charge->id) }}" method="POST">
                                                                @method('PUT')
                                                                @csrf

                                                                <!-- Basic Info Section -->
                                                                <div class="mb-6">
                                                                    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                                                        Basic Information
                                                                    </h3>
                                                                    <div class="space-y-3">
                                                                        @if(auth()->user()->hasRole('admin'))
                                                                        <div>
                                                                            <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
                                                                            <input type="text" name="name" value="{{ $charge->name }}" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                                                        </div>
                                                                        @else
                                                                        <div>
                                                                            <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
                                                                            <input type="text" value="{{ $charge->name }}" class="w-full border border-gray-200 bg-gray-50 px-3 py-2 rounded-lg text-sm text-gray-600" readonly />
                                                                            <p class="text-xs text-gray-400 mt-1">System gateway name cannot be changed</p>
                                                                        </div>
                                                                        @endif

                                                                        @if(auth()->user()->hasRole('admin'))
                                                                        <div>
                                                                            <label class="block text-xs font-medium text-gray-500 mb-1">Description</label>
                                                                            <input type="text" name="description" value="{{ $charge->description }}" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter description" />
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                </div>

                                                                <!-- Charges Section -->
                                                                <div class="mb-6">
                                                                    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                                                        Charges
                                                                    </h3>
                                                                    <div class="space-y-3">
                                                                        <div class="grid grid-cols-2 gap-3">
                                                                            <div>
                                                                                <label class="block text-xs font-medium text-gray-500 mb-1">Self Charge</label>
                                                                                <input type="number" name="self_charge" value="{{ $charge->self_charge }}" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Optional">
                                                                            </div>
                                                                            <div>
                                                                                <label class="block text-xs font-medium text-gray-500 mb-1">API Service Charge</label>
                                                                                <input type="text" name="amount" value="{{ $charge->amount }}" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                                            </div>
                                                                        </div>
                                                                        <div>
                                                                            <label class="block text-xs font-medium text-gray-500 mb-1">Extra Charge (KWD)</label>
                                                                            <input type="number" name="extra_charge" value="{{ $charge->extra_charge }}" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                                                                        </div>
                                                                        <div class="grid grid-cols-2 gap-3">
                                                                            <div>
                                                                                <label class="block text-xs font-medium text-gray-500 mb-1">Paid By</label>
                                                                                <select name="paid_by" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                                                    <option value="Company" {{ $charge->paid_by === 'Company' ? 'selected' : '' }}>Company</option>
                                                                                    <option value="Client" {{ $charge->paid_by === 'Client' ? 'selected' : '' }}>Client</option>
                                                                                </select>
                                                                            </div>
                                                                            <div>
                                                                                <label class="block text-xs font-medium text-gray-500 mb-1">Charge Type</label>
                                                                                <select name="charge_type" class="w-full border border-gray-300 px-3 py-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                                                    <option value="Flat Rate" {{ $charge->charge_type === 'Flat Rate' ? 'selected' : '' }}>Flat Rate</option>
                                                                                    <option value="Percent" {{ $charge->charge_type === 'Percent' ? 'selected' : '' }}>Percent</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Settings Section -->
                                                                <div class="mb-2">
                                                                    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                                                        Settings
                                                                    </h3>

                                                                    <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                                                                        <!-- Active Toggle -->
                                                                        <div class="flex items-center justify-between py-2">
                                                                            <div>
                                                                                <p class="text-sm font-medium text-gray-700">Active</p>
                                                                                <p class="text-xs text-gray-400">Enable this payment gateway</p>
                                                                            </div>
                                                                            <label class="relative inline-flex items-center cursor-pointer">
                                                                                <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $charge->is_active ? 'checked' : '' }}>
                                                                                <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                            </label>
                                                                        </div>

                                                                        <!-- Invoice Charge Toggle -->
                                                                        <div class="flex items-center justify-between py-2 border-t border-gray-200">
                                                                            <div>
                                                                                <p class="text-sm font-medium text-gray-700">Invoice Charge</p>
                                                                                <p class="text-xs text-gray-400">Allow charging fees on invoices</p>
                                                                            </div>
                                                                            <label class="relative inline-flex items-center cursor-pointer">
                                                                                <input type="checkbox" name="can_charge_invoice" value="1" class="sr-only peer" {{ $charge->can_charge_invoice ? 'checked' : '' }}>
                                                                                <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                            </label>
                                                                        </div>

                                                                        <!-- Auto Payment Toggle -->
                                                                        <div class="flex items-center justify-between py-2 border-t border-gray-200">
                                                                            <div>
                                                                                <p class="text-sm font-medium text-gray-700">Auto Payment</p>
                                                                                <p class="text-xs text-gray-400">Auto mark as paid after transaction</p>
                                                                            </div>
                                                                            <label class="relative inline-flex items-center cursor-pointer">
                                                                                <input type="checkbox" name="is_auto_paid" value="1" class="sr-only peer" {{ $charge->is_auto_paid ? 'checked' : '' }}>
                                                                                <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                            </label>
                                                                        </div>

                                                                        <!-- External URL Toggle -->
                                                                        <div class="flex items-center justify-between py-2 border-t border-gray-200">
                                                                            <div>
                                                                                <p class="text-sm font-medium text-gray-700">External URL</p>
                                                                                <p class="text-xs text-gray-400">Allow external payment gateway URL</p>
                                                                            </div>
                                                                            <label class="relative inline-flex items-center cursor-pointer">
                                                                                <input type="checkbox" name="has_url" value="1" class="sr-only peer" {{ $charge->has_url ? 'checked' : '' }}>
                                                                                <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                            </label>
                                                                        </div>

                                                                        <!-- Generate Link Toggle -->
                                                                        <div class="flex items-center justify-between py-2 border-t border-gray-200">
                                                                            <div>
                                                                                <p class="text-sm font-medium text-gray-700">Generate Link</p>
                                                                                <p class="text-xs text-gray-400">Allow generating payment links</p>
                                                                            </div>
                                                                            <label class="relative inline-flex items-center cursor-pointer">
                                                                                <input type="checkbox" name="can_generate_link" value="1" class="sr-only peer" {{ $charge->can_generate_link ? 'checked' : '' }}>
                                                                                <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                        </div>

                                                        <!-- Footer -->
                                                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                                                            <button type="button" @click="editParentModal = null" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                                                Cancel
                                                            </button>
                                                            <button type="submit" form="editChargeForm_{{ $charge->id }}" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                                                                Save Changes
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div x-cloak x-show="editCredsModal === {{ $charge->id }}" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-30 backdrop-blur-sm">
                                        <div class="bg-white rounded-lg w-full max-w-lg shadow max-h-[80vh] flex flex-col" @click.away="editCredsModal = null">
                                            <div class="flex items-center justify-between px-5 pt-6 pb-4">
                                                <div class="flex items-center gap-2">
                                                    <h2 class="text-lg font-bold">Gateway API Settings</h2>
                                                    @if($charge->is_system_default && !auth()->user()->hasRole('admin'))
                                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded-full">
                                                        Limited Access
                                                    </span>
                                                    @endif
                                                </div>
                                                <button @click.stop="editCredsModal = null" class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">&times;</button>
                                            </div>

                                            <div class="overflow-y-auto px-8 pb-8 [scrollbar-gutter:stable]">
                                                <form action="{{ route('charges.credentials.update', $charge->id) }}" method="POST">
                                                    @csrf
                                                    @method('PUT')

                                                    <div class="mb-4">
                                                        <label class="block text-sm font-medium">Gateway</label>
                                                        <input type="text" class="w-full border px-3 py-2 rounded-full bg-gray-200" value="{{ $charge->name }}" readonly>
                                                    </div>

                                                    @if($charge->is_system_default)
                                                    <div class="mb-4">
                                                        <label class="block text-sm font-medium">API Key</label>
                                                        <textarea name="api_key" id="api_key_{{ $charge->id }}" class="w-full border px-3 py-2 rounded-md resize-y min-h-[6rem]"
                                                            placeholder="Provide new key to replace existing">{{ old('api_key', $charge->api_key) }}</textarea>
                                                    </div>
                                                    @endif

                                                    <div class="mb-6">
                                                        <div class="flex items-center">
                                                            <input type="checkbox" name="is_active" value="1" id="is_active_{{ $charge->id }}"
                                                                @checked(old('is_active', (bool)$charge->is_active))
                                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                                            <label class="ml-2 text-sm font-medium text-gray-700">Active</label>
                                                        </div>
                                                        <p class="text-xs text-gray-500 mt-1">Enable or disable this gateway for your company</p>
                                                    </div>

                                                    @if(auth()->user()->hasRole('admin') || !$charge->is_system_default)

                                                    <div class="mb-6 flex gap-4">

                                                        <div class="w-1/2">
                                                            <div class="flex items-center">
                                                                <input type="checkbox" name="can_generate_link" value="1" id="can_generate_link_{{ $charge->id }}"
                                                                    @checked(old('can_generate_link', (bool)$charge->can_generate_link))
                                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                                                <label class="ml-2 text-sm font-medium text-gray-700">Can Generate Link</label>
                                                            </div>
                                                            <p class="text-xs text-gray-500 mt-1">Allow customers to pay invoices using this gateway</p>
                                                        </div>
                                                    </div>

                                                    <div class="mb-4 flex gap-4">
                                                        <div class="w-1/3">
                                                            <div class="flex items-center">
                                                                <input type="checkbox" name="is_auto_paid" value="1" id="is_auto_paid_{{ $charge->id }}"
                                                                    @checked(old('is_auto_paid', (bool)$charge->is_auto_paid))
                                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                                                <label class="ml-2 text-sm font-medium text-gray-700">Auto Payment</label>
                                                            </div>
                                                            <p class="text-xs text-gray-500 mt-1">Invoice will be automatically paid</p>
                                                        </div>
                                                        <div class="w-1/3">
                                                            <div class="flex items-center">
                                                                <input type="checkbox" name="has_url" value="1" id="has_url_{{ $charge->id }}"
                                                                    @checked(old('has_url', (bool)$charge->has_url))
                                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                                                <label class="ml-2 text-sm font-medium text-gray-700">External URL</label>
                                                            </div>
                                                            <p class="text-xs text-gray-500 mt-1">Can put external payment gateway URL</p>
                                                        </div>
                                                        <div class="w-1/3">
                                                            <div class="flex items-center">
                                                                <input type="checkbox" name="can_charge_invoice" value="1" id="can_charge_invoice_{{ $charge->id }}"
                                                                    @checked(old('can_charge_invoice', (bool)$charge->can_charge_invoice))
                                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                                                <label class="ml-2 text-sm font-medium text-gray-700">Invoice Charge</label>
                                                            </div>
                                                            <p class="text-xs text-gray-500 mt-1">Allow charging additional fees on invoices</p>
                                                        </div>
                                                    </div>
                                                    @endif

                                                    <div class="flex justify-between items-center mt-6">
                                                        <button type="button" @click="editCredsModal = null" class="bg-gray-300 px-4 py-2 rounded-full">Cancel</button>
                                                        <button type="submit" class="bg-violet-600 text-white px-4 py-2 rounded-full">Save</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>