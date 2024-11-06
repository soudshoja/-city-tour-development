<x-app-layout>
    <div x-data="invoiceModal()">
        <div x-data="invoiceAdd">
            <div class="flex flex-col gap-2.5 xl:flex-row">
                <div class="panel flex-1 px-0 py-6 lg:mr-6 ">
                    <!-- company details -->
                    <div class="flex flex-wrap justify-between px-4">
                        <div class="flex shrink-0 items-center text-black dark:text-white">
                            <x-application-logo class="custom-logo-size" />

                            <div class="pl-2">
                                @if($company)
                                <h3>{{ $company->name }}</h3>
                                <p>{{ $company->address }}</p>
                                @else
                                <p>No company assigned</p>
                                @endif
                            </div>


                        </div>
                        <div class="space-y-1 text-gray-500 dark:text-gray-400">
                            <div class="flex">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path opacity="0.5"
                                        d="M14.2 3H9.8C5.65164 3 3.57746 3 2.28873 4.31802C1 5.63604 1 7.75736 1 12C1 16.2426 1 18.364 2.28873 19.682C3.57746 21 5.65164 21 9.8 21H14.2C18.3484 21 20.4225 21 21.7113 19.682C23 18.364 23 16.2426 23 12C23 7.75736 23 5.63604 21.7113 4.31802C20.4225 3 18.3484 3 14.2 3Z"
                                        fill="#1C274C" />
                                    <path
                                        d="M19.1284 8.03302C19.4784 7.74133 19.5257 7.22112 19.234 6.87109C18.9423 6.52106 18.4221 6.47377 18.0721 6.76546L15.6973 8.74444C14.671 9.59966 13.9585 10.1915 13.357 10.5784C12.7747 10.9529 12.3798 11.0786 12.0002 11.0786C11.6206 11.0786 11.2258 10.9529 10.6435 10.5784C10.0419 10.1915 9.32941 9.59966 8.30315 8.74444L5.92837 6.76546C5.57834 6.47377 5.05812 6.52106 4.76643 6.87109C4.47474 7.22112 4.52204 7.74133 4.87206 8.03302L7.28821 10.0465C8.2632 10.859 9.05344 11.5176 9.75091 11.9661C10.4775 12.4334 11.185 12.7286 12.0002 12.7286C12.8154 12.7286 13.523 12.4334 14.2495 11.9661C14.947 11.5176 15.7372 10.859 16.7122 10.0465L19.1284 8.03302Z"
                                        fill="#1C274C" />
                                </svg>

                                <p class="pl-1">{{ $company->email }}</p>
                            </div>
                            <div class="flex">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M15.5562 14.5477L15.1007 15.0272C15.1007 15.0272 14.0181 16.167 11.0631 13.0559C8.10812 9.94484 9.1907 8.80507 9.1907 8.80507L9.47752 8.50311C10.1841 7.75924 10.2507 6.56497 9.63424 5.6931L8.37326 3.90961C7.61028 2.8305 6.13596 2.68795 5.26145 3.60864L3.69185 5.26114C3.25823 5.71766 2.96765 6.30945 3.00289 6.96594C3.09304 8.64546 3.81071 12.259 7.81536 16.4752C12.0621 20.9462 16.0468 21.1239 17.6763 20.9631C18.1917 20.9122 18.6399 20.6343 19.0011 20.254L20.4217 18.7584C21.3806 17.7489 21.1102 16.0182 19.8833 15.312L17.9728 14.2123C17.1672 13.7486 16.1858 13.8848 15.5562 14.5477Z"
                                        fill="#1C274C" />
                                    <path
                                        d="M13.2595 1.87983C13.3257 1.47094 13.7122 1.19357 14.1211 1.25976C14.1464 1.26461 14.2279 1.27983 14.2705 1.28933C14.3559 1.30834 14.4749 1.33759 14.6233 1.38082C14.9201 1.46726 15.3347 1.60967 15.8323 1.8378C16.8286 2.29456 18.1544 3.09356 19.5302 4.46936C20.906 5.84516 21.705 7.17097 22.1617 8.16725C22.3899 8.66487 22.5323 9.07947 22.6187 9.37625C22.6619 9.52466 22.6912 9.64369 22.7102 9.72901C22.7197 9.77168 22.7267 9.80594 22.7315 9.83125L22.7373 9.86245C22.8034 10.2713 22.5286 10.6739 22.1197 10.7401C21.712 10.8061 21.3279 10.53 21.2601 10.1231C21.258 10.1121 21.2522 10.0828 21.2461 10.0551C21.2337 9.9997 21.2124 9.91188 21.1786 9.79572C21.1109 9.56339 20.9934 9.21806 20.7982 8.79238C20.4084 7.94207 19.7074 6.76789 18.4695 5.53002C17.2317 4.29216 16.0575 3.59117 15.2072 3.20134C14.7815 3.00618 14.4362 2.88865 14.2038 2.82097C14.0877 2.78714 13.9417 2.75363 13.8863 2.7413C13.4793 2.67347 13.1935 2.28755 13.2595 1.87983Z"
                                        fill="#1C274C" />
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                        d="M13.4857 5.3293C13.5995 4.93102 14.0146 4.7004 14.4129 4.81419L14.2069 5.53534C14.4129 4.81419 14.4129 4.81419 14.4129 4.81419L14.4144 4.81461L14.4159 4.81505L14.4192 4.81602L14.427 4.81834L14.4468 4.8245C14.4618 4.82932 14.4807 4.8356 14.5031 4.84357C14.548 4.85951 14.6074 4.88217 14.6802 4.91337C14.8259 4.97581 15.0249 5.07223 15.2695 5.21694C15.7589 5.50662 16.4271 5.9878 17.2121 6.77277C17.9971 7.55775 18.4782 8.22593 18.7679 8.7154C18.9126 8.95991 19.009 9.15897 19.0715 9.30466C19.1027 9.37746 19.1254 9.43682 19.1413 9.48173C19.1493 9.50418 19.1555 9.52301 19.1604 9.53809L19.1665 9.55788L19.1688 9.56563L19.1698 9.56896L19.1702 9.5705C19.1702 9.5705 19.1707 9.57194 18.4495 9.77798L19.1707 9.57194C19.2845 9.97021 19.0538 10.3853 18.6556 10.4991C18.2607 10.6119 17.8492 10.3862 17.7313 9.99413L17.7276 9.98335C17.7223 9.96832 17.7113 9.93874 17.6928 9.89554C17.6558 9.8092 17.5887 9.66797 17.4771 9.47938C17.2541 9.10264 16.8514 8.53339 16.1514 7.83343C15.4515 7.13348 14.8822 6.73078 14.5055 6.50781C14.3169 6.39619 14.1757 6.32909 14.0893 6.29209C14.0461 6.27358 14.0165 6.26254 14.0015 6.25721L13.9907 6.25352C13.5987 6.13564 13.3729 5.72419 13.4857 5.3293Z"
                                        fill="#1C274C" />
                                </svg>

                                <p class="pl-1">{{ $company->phone }}</p>
                            </div>
                        </div>

                    </div>
                    <!-- ./company details -->
                    <!-- agent detials -->
                    <!-- 
                    <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />
                    
                    <div class="flex flex-wrap justify-between px-4">
                        <div class="flex shrink-0 items-center text-black dark:text-white">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="6" r="4" fill="#1C274C" />
                                <path opacity="0.5"
                                    d="M20 17.5C20 19.9853 20 22 12 22C4 22 4 19.9853 4 17.5C4 15.0147 7.58172 13 12 13C16.4183 13 20 15.0147 20 17.5Z"
                                    fill="#1C274C" />
                            </svg>

                            <div class="pl-2">
                                <h3>Choose An Agent</h3>

                            </div>


                        </div>
                        <div class="space-y-1 text-gray-500 dark:text-gray-400">

                            <button @click="openAgentModal()"
                                class="relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:text-white focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                                <span
                                    class="gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                                    Select Agent
                                </span>
                            </button>
                        </div>

                    </div>
-->
                    <!-- ./agent details -->
                    <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />

                    <div class="flex flex-wrap justify-between px-4">
                        <div class="mb-6 w-full lg:w-1/2">
                            <!-- client details -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="text-lg font-semibold">Bill To</div>
                                    <button @click="openClientModal()"
                                        class="relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:text-white focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                                        <span
                                            class="gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="10" cy="6" r="4" fill="currentColor" />
                                                <path
                                                    d="M18 17.5C18 19.9853 18 22 10 22C2 22 2 19.9853 2 17.5C2 15.0147 5.58172 13 10 13C14.4183 13 18 15.0147 18 17.5Z"
                                                    fill="currentColor" />
                                                <path d="M21 10H19M19 10H17M19 10L19 8M19 10L19 12"
                                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                            </svg> Select Client
                                        </span>
                                    </button>

                                </div>
                                <div class="mt-4 flex items-center">
                                    <label for="receiverName" class="mb-0 w-1/3 mr-2 ">Name</label>
                                    <input id="receiverName" type="text" name="receiverName" class="form-input flex-1"
                                        value="{{ old('client_name', $receiverName ?? '') }}" x-model="receiverName"
                                        placeholder="Enter Name" />
                                </div>
                                <div class="mt-4 flex items-center">
                                    <label for="receiverEmail" class="mb-0 w-1/3 mr-2 ">Email</label>
                                    <input id="receiverEmail" type="email" name="receiverEmail"
                                        class="form-input flex-1"
                                        value="{{ old('client_email', $receiverEmail ?? '') }}" x-model="receiverEmail"
                                        placeholder="Enter Email" />
                                </div>

                                <div class="mt-4 flex items-center">
                                    <label for="receiverPhone" class="mb-0 w-1/3 mr-2 ">Phone Number</label>
                                    <input id="receiverPhone" type="text" name="receiverPhone" class="form-input flex-1"
                                        value="{{ old('client_phone', $receiverPhone ?? '') }}" x-model="receiverPhone"
                                        placeholder="Enter Phone Number" />
                                </div>
                            </div>
                            <!-- ./client details -->
                        </div>
                        <!-- invoice details -->
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
                        <!-- ./invoice details -->
                    </div>
                    <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />
                    <!-- add items button-->

                    <div class="flex justify-center items-center px-10">
                        <button @click="openTaskModal()"
                            class="w-full relative inline-flex items-center justify-center p-0.5 mb-2 me-2 overflow-hidden text-sm font-medium text-gray-900 rounded-lg group bg-gradient-to-br from-green-400 to-blue-600 group-hover:from-green-400 group-hover:to-blue-600 hover:text-white dark:text-white focus:ring-4 focus:outline-none focus:ring-green-200 dark:focus:ring-green-800">
                            <span
                                class="justify-center w-full gap-2 flex px-5 py-2.5 transition-all ease-in duration-75 bg-white dark:bg-gray-900 rounded-md group-hover:bg-opacity-0">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                        d="M17.5 2.75C17.9142 2.75 18.25 3.08579 18.25 3.5V5.75H20.5C20.9142 5.75 21.25 6.08579 21.25 6.5C21.25 6.91421 20.9142 7.25 20.5 7.25H18.25V9.5C18.25 9.91421 17.9142 10.25 17.5 10.25C17.0858 10.25 16.75 9.91421 16.75 9.5V7.25H14.5C14.0858 7.25 13.75 6.91421 13.75 6.5C13.75 6.08579 14.0858 5.75 14.5 5.75H16.75V3.5C16.75 3.08579 17.0858 2.75 17.5 2.75Z"
                                        fill="currentColor" />
                                    <path
                                        d="M2 6.5C2 4.37868 2 3.31802 2.65901 2.65901C3.31802 2 4.37868 2 6.5 2C8.62132 2 9.68198 2 10.341 2.65901C11 3.31802 11 4.37868 11 6.5C11 8.62132 11 9.68198 10.341 10.341C9.68198 11 8.62132 11 6.5 11C4.37868 11 3.31802 11 2.65901 10.341C2 9.68198 2 8.62132 2 6.5Z"
                                        fill="currentColor" />
                                    <path
                                        d="M13 17.5C13 15.3787 13 14.318 13.659 13.659C14.318 13 15.3787 13 17.5 13C19.6213 13 20.682 13 21.341 13.659C22 14.318 22 15.3787 22 17.5C22 19.6213 22 20.682 21.341 21.341C20.682 22 19.6213 22 17.5 22C15.3787 22 14.318 22 13.659 21.341C13 20.682 13 19.6213 13 17.5Z"
                                        fill="currentColor" />
                                    <path opacity="0.5"
                                        d="M2 17.5C2 15.3787 2 14.318 2.65901 13.659C3.31802 13 4.37868 13 6.5 13C8.62132 13 9.68198 13 10.341 13.659C11 14.318 11 15.3787 11 17.5C11 19.6213 11 20.682 10.341 21.341C9.68198 22 8.62132 22 6.5 22C4.37868 22 3.31802 22 2.65901 21.341C2 20.682 2 19.6213 2 17.5Z"
                                        fill="currentColor" />
                                </svg>


                                Add Item
                            </span>
                        </button>

                    </div>

                    <!-- ./add items button-->
                    <div class="mt-8">
                        <!-- choose items -->
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

                                            </td>
                                            <td><input type="number" class="form-input w-32" placeholder="Quantity"
                                                    x-model="item.quantity" /></td>
                                            <td>
                                                <input type="text" class="form-input w-32" placeholder="Price"
                                                    x-model.number="item.total" @input="updateItemTotal(item)" />
                                            </td>
                                            <td x-text="`$${(item.total * item.quantity).toFixed(2)}`"></td>
                                            <td>
                                                <button type="button" @click="removeItem(item.id)">
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
                        <!-- ./choose items -->

                    </div>
                    <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />

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

                    <!-- Agents Modal -->
                    <div x-show="isAgentModalOpen"
                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75"
                        style="display: none;">
                        <div class="bg-white border rounded-lg shadow-lg w-3/4 md:w-1/2 mb-10">
                            <!-- Modal Header -->
                            <div
                                class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3 dark:bg-[#121c2c]">
                                <h5 class="text-lg font-bold">Choose Agent</h5>
                                <!-- Close Modal Button -->
                                <button type="button" class="text-white-dark hover:text-dark"
                                    @click="closeAgentModal()">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                            <!-- ./Modal Header -->
                            <div class="m-6 ">
                                <!-- Search Box -->
                                <div class="relative mb-2">
                                    <input type="text" placeholder="Search Client..."
                                        class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                                        x-model="searchClient">
                                    <button type="button"
                                        class="btn btn-primary absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1 ">
                                        <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="11.5" cy="11.5" r="9.5" stroke="currentColor" stroke-width="1.5"
                                                opacity="0.5"></circle>
                                            <path d="M18.5 18.5L22 22" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round"></path>
                                        </svg>
                                    </button>
                                </div>
                                <!-- ./Search Box -->


                                <!-- List of Agents -->
                                <ul
                                    class="shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] border rounded-lg mb-10 max-h-60 overflow-y-auto custom-scrollbar">
                                    <template x-for="Agent in filteredAgents" :key="Agent.id">
                                        <li @click="selectAgent(Agent)"
                                            class="cursor-pointer p-2 hover:bg-gray-100 text-gray-800">
                                            <span x-text="Agent.name"></span> - <span x-text="Agent.email"></span>
                                        </li>
                                    </template>
                                </ul>
                                <!-- ./List of Agents -->

                            </div>

                        </div>
                    </div>

                    <!-- Clients Modal -->
                    <div x-show="isClientModalOpen"
                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75"
                        style="display: none;">
                        <div class="bg-white border rounded-lg shadow-lg w-3/4 md:w-1/2 mb-10">
                            <!-- Modal Header -->
                            <div
                                class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3 dark:bg-[#121c2c]">
                                <h5 class="text-lg font-bold">Choose Client</h5>
                                <!-- Close Modal Button -->
                                <button type="button" class="text-white-dark hover:text-dark"
                                    @click="closeClientModal()">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                            <!-- ./Modal Header -->
                            <div class="m-6 ">
                                <!-- Search Box -->
                                <div class="relative mb-2">
                                    <input type="text" placeholder="Search Client..."
                                        class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                                        x-model="searchClient">
                                    <button type="button"
                                        class="btn btn-primary absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1 ">
                                        <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="11.5" cy="11.5" r="9.5" stroke="currentColor" stroke-width="1.5"
                                                opacity="0.5"></circle>
                                            <path d="M18.5 18.5L22 22" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round"></path>
                                        </svg>
                                    </button>
                                </div>
                                <!-- ./Search Box -->


                                <!-- List of Clients -->
                                <ul
                                    class="shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] border rounded-lg mb-10 max-h-60 overflow-y-auto custom-scrollbar">
                                    <template x-for="client in filteredClients" :key="client.id">
                                        <li @click="selectClient(client)"
                                            class="cursor-pointer p-2 hover:bg-gray-100 text-gray-800">
                                            <span x-text="client.name"></span> - <span x-text="client.email"></span>
                                        </li>
                                    </template>
                                </ul>
                                <!-- ./List of Clients -->

                            </div>

                        </div>
                    </div>

                    <!-- Tasks Modal -->
                    <div x-show="isTaskModalOpen"
                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75"
                        style="display: none;">
                        <div class="bg-white border rounded-lg shadow-lg w-3/4 md:w-1/2">
                            <div
                                class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3 dark:bg-[#121c2c]">
                                <h5 class="text-lg font-bold">Choose Task</h5>
                                <!-- Close Modal Button -->
                                <button type="button" class="text-white-dark hover:text-dark" @click="closeTaskModal()">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                            <div class="m-6">
                                <!-- Search Box -->
                                <div class="relative  mb-10">
                                    <input type="text" placeholder="Search Task..."
                                        class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                                        x-model="searchTask">
                                    <button type="button"
                                        class="btn btn-primary absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1 ">
                                        <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="11.5" cy="11.5" r="9.5" stroke="currentColor" stroke-width="1.5"
                                                opacity="0.5"></circle>
                                            <path d="M18.5 18.5L22 22" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round"></path>
                                        </svg>
                                    </button>
                                </div>
                                <!-- ./Search Box -->
                                <!-- List of Tasks -->
                                <ul class=" border rounded-lg mb-10 max-h-60 overflow-y-auto  custom-scrollbar">
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
                            </div>




                        </div>
                    </div>



                </div>
            </div>
        </div>
        <!-- end main content section -->
    </div>

    <script>
    // Invoice Add
    function invoiceModal() {

        return {
            isAgentModalOpen: false,
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
            removeItem(taskId) {
                this.items = this.items.filter(item => item.id !== taskId);
                this.updateTotal(this.items); // Update total if needed
            },


            openAgentModal() {
                this.isAgentModalOpen = true;
            },

            closeAgentModal() {
                this.isAgentModalOpen = false;
            },

            selectAgent(Agent) {
                this.selectedAgent = Agent;
                this.selectedAgentId = Agent.id ?? '';
                this.receiverName = Agent.name ?? '';
                this.receiverAddress = Agent.address ?? '';
                this.receiverPhone = Agent.phone ?? '';
                this.receiverEmail = Agent.email ?? '';
                document.getElementById('receiverName').value = Agent.name ?? '';
                document.getElementById('receiverEmail').value = Agent.email ?? '';
                const addressField = document.getElementById('receiverAddress');
                if (addressField) {
                    addressField.value = Agent.address ? Agent.address : '';
                }

                const phoneField = document.getElementById('receiverPhone');
                if (phoneField) {
                    phoneField.value = Agent.phone ? Agent.phone : '';
                }
                this.closeAgentModal();
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

            // updateSubTotal() {
            //     const taxAmount = this.subtotal * (this.params.tax / 100);
            //     const discountAmount = this.subtotal * (this.params.discount / 100);

            //     // Calculate total
            //     this.total = this.subtotal + taxAmount + this.params.shippingCharge - discountAmount;

            // },

            updateTotal(items) {
                const total = items.reduce((sum, item) => sum + (item.total * item.quantity),
                    0); // Calculate total based on price and quantity
                this.subtotal = total;
                // this.updateSubTotal();
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
                const total = this.subtotal;
                const subtotal = this.subtotal;
                const tasks = this.items;
                const clientId = this.selectedClientId;

                // Basic validation
                if (!clientId || !total || !tasks.length) {
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
                    this.invoiceLink = `https://tour.citytravelers.co/invoice/` + this.invoiceNumber;
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