<x-app-layout>
    <div class="container mx-auto px-4">
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                <a href="{{ route('payment.link.index') }}" class="hover:text-blue-500 hover:underline">Payment Links</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                <span class="text-gray-500">Create New</span>
            </li>
        </ul>

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" x-data="{
            advancedMode: false,
            currentLanguage: 'EN',
            addClientModal: false,
            importFatoorahModal: false,
            showUploadForm: false,
            showManualForm: false,
            toggleUploadForm() {
                this.showUploadForm = true;
                this.showManualForm = false;
            },
            toggleManualForm() {
                this.showUploadForm = false;
                this.showManualForm = true;
            },
            closeModal() {
                this.addClientModal = false;
                this.showUploadForm = false;
                this.showManualForm = false;
            }
        }">
            
            <!-- Header with Toggle and Buttons -->
            <div class="px-4 py-5 ">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-gray-900">
                        <span x-text="advancedMode ? 'Advanced Payment Link' : 'Quick Payment Link'"></span>
                    </h2>
                    
                    <!-- Toggle and Buttons -->
                    <div class="flex items-center gap-4">
                            <div class="flex items-center bg-gray-100 rounded-full p-1">
                            <button type="button" @click="advancedMode = false" :class="!advancedMode ? 'bg-blue-100 text-blue-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-1.5 rounded-full text-sm font-medium transition-all duration-200">
                                Quick
                            </button>
                            <button type="button" @click="advancedMode = true" :class="advancedMode ? 'bg-blue-100 text-blue-700 shadow-sm' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-1.5 rounded-full text-sm font-medium transition-all duration-200">
                                Advanced
                            </button>
                        </div>

                        <div class="w-px h-8 bg-gray-200"></div>

                        <div class="flex items-center gap-2">
                            <!-- Add New Client -->
                            <div @click="addClientModal = true"
                                class="p-2 text-center bg-white rounded-full shadow-xl ring-1 ring-black/5 group hover:bg-black cursor-pointer transition duration-150 ease-in-out"
                                data-tooltip-left="Add New Client">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-black group-hover:stroke-white">
                                    <path d="M16 21V19C16 17.3431 14.6569 16 13 16H7C5.34315 16 4 17.3431 4 19V21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <circle cx="10" cy="10" r="4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M20 8V14" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M23 11H17" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </div>

                            <!-- Import Payment -->
                            <div @click="importFatoorahModal = true"
                                class="p-2 text-center bg-white rounded-full shadow-xl ring-1 ring-black/5 group hover:bg-black cursor-pointer transition duration-150 ease-in-out"
                                data-tooltip-left="Import Payment">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-black group-hover:stroke-white">
                                    <path d="M12 5V19M5 12H19" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Client Modal -->
            <div x-show="addClientModal" x-cloak
                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-20"
                @click.away="addClientModal = false">
                <div class="bg-white rounded-lg p-6 w-full max-w-lg shadow-xl overflow-y-auto"
                    style="max-height: 90vh;">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Client Registration</h2>
                            <p class="text-gray-600 italic text-xs mt-1">Please fill in the required client
                                information to register</p>
                        </div>
                        <button @click="addClientModal = false"
                            class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">
                            &times;
                        </button>
                    </div>

                    <form action="{{ route('clients.store') }}" method="POST" id="client-formTask"
                        class="space-y-4">
                        @csrf
                        <input type="hidden" name="task_id" :value="modalTaskId">
                        <input type="hidden" name="agent_id" :value="modalAgentId">

                        <div id="upload-passport-container" class="my-2 border-2 border-dashed border-gray-400 rounded-md flex flex-col justify-center gap-2 items-center p-2 min-h-20 max-h-48 mb-2" ondrop="dropHandler(event);" ondragover="dragOverHandler(event);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 10L13 10" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M10 3H16.5C16.9644 3 17.1966 3 17.3916 3.02567C18.7378 3.2029 19.7971 4.26222 19.9743 5.60842C20 5.80337 20 6.03558 20 6.5" stroke="#1C274C" stroke-width="1.5" />
                                <path d="M2 6.94975C2 6.06722 2 5.62595 2.06935 5.25839C2.37464 3.64031 3.64031 2.37464 5.25839 2.06935C5.62595 2 6.06722 2 6.94975 2C7.33642 2 7.52976 2 7.71557 2.01738C8.51665 2.09229 9.27652 2.40704 9.89594 2.92051C10.0396 3.03961 10.1763 3.17633 10.4497 3.44975L11 4C11.8158 4.81578 12.2237 5.22367 12.7121 5.49543C12.9804 5.64471 13.2651 5.7626 13.5604 5.84678C14.0979 6 14.6747 6 15.8284 6H16.2021C18.8345 6 20.1506 6 21.0062 6.76946C21.0849 6.84024 21.1598 6.91514 21.2305 6.99383C22 7.84935 22 9.16554 22 11.7979V14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14V6.94975Z" stroke="#1C274C" stroke-width="1.5" />
                            </svg>
                            <input type="file" name="file" id="file-task-passport" class="hidden"
                                accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf">
                            <p id="task-passport-file-name">You can drag and drop a file here</p>
                            <label for="file-task-passport"
                                class="bg-black text-white font-semibold p-2 rounded-md border-2 border-black hover:border-2 hover:border-cyan-500">
                                Upload File
                            </label>
                        </div>

                        <div class="my-2">
                            <button id="task-passport-process-btn"
                                class="w-full bg-gray-300 text-gray-500 font-semibold py-2 rounded-full text-sm transition duration-150 cursor-not-allowed"
                                disabled>
                                Process File
                            </button>
                        </div>

                        <div class="my-2">
                            <label for="nameTask" class="block text-sm font-medium text-gray-700 mb-1">Client's
                                Name</label>
                            <input type="text" name="first_name" id="nameTask" :value="modalClientName"
                                placeholder="Client's name"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required>
                            <input type="text" name="middle_name" id="middleNameTask" placeholder="Client's middle name"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mt-2">
                            <input type="text" name="last_name" id="lastNameTask" placeholder="Client's last name"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mt-2">
                        </div>

                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Passenger's Name</label>
                            <input type="text" name="passenger_name" id="passengerName"
                                :value="modalPassengerName" placeholder="Passenger's name"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100 text-gray-500 focus:outline-none focus:ring-0 focus:border-gray-300 cursor-not-allowed"
                                disabled>
                        </div>

                        <div class="flex gap-4 mb-3">
                            <div class="w-2/3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" id="emailTask"
                                    placeholder="Client's email"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="w-1/3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of
                                    Birth</label>
                                <input type="date" name="date_of_birthTask"
                                    class="w-full text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <div class="flex gap-2">
                                <div class="w-40">
                                    <x-searchable-dropdown name="dial_code" :items="\App\Models\Country::all()->map(
                                        fn($country) => [
                                            'id' => $country->dialing_code,
                                            'name' => $country->dialing_code . ' ' . $country->name,
                                        ],
                                    )"
                                        placeholder=" Search Dial Code" :showAllOnOpen="true" />
                                </div>

                                <input type="text" name="phone" id="phoneTask"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Client's phone number" required>
                            </div>
                        </div>

                        <div class="flex gap-4 mb-3">
                            <div class="w-1/2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Passport
                                    Number</label>
                                <input type="text" name="passport" id="passport_noTask"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="w-1/2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Civil
                                    Number</label>
                                <input type="text" name="civil_no" id="civil_noTask"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <input type="text" name="address" id="addressTask"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Client's address">
                        </div>

                        <div>
                            @unlessrole('agent')
                                <x-searchable-dropdown name="agent_id" :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
                                    placeholder="Select an Agent" label="Agent" />
                            @else
                                <label for="agent_id"
                                    class="block text-sm font-medium text-gray-700">Agent</label>
                                <input type="text" name="agent_id" id="agent_id"
                                    value="{{ auth()->user()->agent->name }}"
                                    class="form-input w-full border rounded px-3 py-2 bg-gray-100 text-gray-500"
                                    readonly />

                                <input type="hidden" name="agent_id" value="{{ auth()->user()->agent->id }}">
                            @endunlessrole
                        </div>

                        <div class="flex justify-between pt-4 mt-4">
                            <button type="button" @click="addClientModal = false"
                                class="w-32 shadow-md border border-gray-200 hover:bg-gray-400 font-semibold py-2 rounded-full text-sm transition duration-150">
                                Cancel
                            </button>
                            <button type="submit"
                                class="w-32 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-full text-sm shadow-md transition duration-150">
                                Register Client
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Import Payment Modal -->
            <div x-show="importFatoorahModal" x-cloak
                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50"
                @click.away="importFatoorahModal = false">

                <div class="bg-white rounded-lg p-6 w-full max-w-lg shadow-xl overflow-y-auto" style="max-height: 90vh;">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Import Payment</h2>
                            <p class="text-gray-600 italic text-xs mt-1">Import a payment from an existing transaction on Portal</p>
                        </div>
                        <button @click="importFatoorahModal = false"
                            class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">
                            &times;
                        </button>
                    </div>

                                <form action="{{ route('payment.link.import.payment') }}" method="POST" class="space-y-4">
                                    @csrf
                                    <div x-data="{ gateway: '' }">
                                        <div>
                                            <label for="gateway" class="block text-sm font-medium text-gray-700 mb-1">
                                                Payment Gateway
                                            </label>
                                            <select name="gateway" id="gateway" x-model="gateway"
                                                class="block w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                                                required>
                                                <option value="" selected disabled hidden>Select Payment Gateway</option>
                                                @foreach($can_import as $gateway)
                                                <option value="{{ strtolower($gateway->name) }}">{{ $gateway->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                            <!-- MyFatoorah: Invoice ID -->
                            <div x-show="gateway === 'myfatoorah'" class="mt-4" x-cloak>
                                <label for="import_invoice_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Existing Invoice ID
                                </label>
                                <input type="text" name="import_invoice_id" id="import_invoice_id"
                                    class="block w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                                    placeholder="Enter invoice ID">
                            </div>

                            <!-- Hesabe: Order Reference -->
                            <div x-show="gateway === 'hesabe'" class="mt-4" x-cloak>
                                <label for="import_order_reference" class="block text-sm font-medium text-gray-700 mb-1">
                                    Existing Order Reference
                                </label>
                                <input type="text" name="import_order_reference" id="import_order_reference"
                                    class="block w-full rounded-md border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2"
                                    placeholder="Enter order reference">
                            </div>
                        </div>

                        <div class="flex justify-between pt-4 mt-4">
                            <button type="button" @click="importFatoorahModal = false"
                                class="w-32 shadow-md border border-gray-200 hover:bg-gray-400 font-semibold py-2 rounded-full text-sm transition duration-150">
                                Cancel
                            </button>
                            <button type="submit"
                                class="w-32 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-full text-sm shadow-md transition duration-150">
                                Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Form Body -->
            <form action="{{ route('payment.link.store') }}" method="POST" class="pl-6 pr-6 pt-4 pb-4 space-y-5"
                x-data="{ submitting: false }" 
                x-on:submit.prevent="if (submitting) return; submitting = true; $el.submit();">
                @csrf
                @php
                $prefill = session('prefill_data');
                @endphp

                @if ($prefill)
                <input type="hidden" name="payment_gateway" value="{{ $prefill['payment_gateway'] }}">
                <input type="hidden" name="payment_method" value="{{ $prefill['payment_method'] }}">
                <input type="hidden" name="amount" value="{{ $prefill['amount'] }}">
                <input type="hidden" name="client_id" value="{{ $prefill['client_id'] }}">
                <input type="hidden" name="agent_id" value="{{ $prefill['agent_id'] }}">
                <input type="hidden" name="notes" value="{{ $prefill['notes'] }}">
                @endif
                <input type="hidden" name="payment_id" value="{{ old('payment_id', $prefill['payment_id'] ?? '') }}">
                <input type="hidden" name="invoice_id" value="{{ old('invoice_id', $prefill['invoice_id'] ?? '') }}">
                <input type="hidden" name="source" value="{{ old('source', $prefill['source'] ?? '') }}">
                <input type="hidden" name="invoice_reference" value="{{ old('invoice_reference', $prefill['invoice_reference'] ?? '') }}">
                <input type="hidden" name="auth_code" value="{{ old('auth_code', $prefill['auth_code'] ?? '') }}">
                <input type="hidden" name="payment_reference" value="{{ old('payment_reference', $prefill['payment_reference'] ?? '') }}">
                <input type="hidden" name="track_id" value="{{ old('track_id', $prefill['track_id'] ?? '') }}">

                <!-- Client & Agent -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    @php
                    $selectedClient = null;
                    $clientPlaceholder = $selectedClient ? $selectedClient->full_name : 'Select a Client';
                    $selectedId = old('client_id', $selectedClient->id ?? null);
                    $selectedName = old('client_name', $selectedClient->full_name ?? null);
                    @endphp
                    <div>
                        <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1.5">Client</label>
                        <x-searchable-dropdown
                            name="client_id"
                            id="client_id"
                            :items="$clients->map(fn($c) => [
                                'id' => $c->id,
                                'name' => $c->full_name . ' - ' . $c->phone
                            ])"
                            :selectedId="$selectedId"
                            :selectedName="$selectedName"
                            placeholder="{{ $clientPlaceholder }}"
                            class="block w-full"
                        />
                    </div>

                    @php
                    $selectedAgent = null;
                    $agentPlaceholder = $selectedAgent ? $selectedAgent->name : 'Select an Agent';
                    $selectedId   = old('agent_id', $selectedAgent->id ?? null);
                    $selectedName = old('agent_name', $selectedAgent->name ?? null);
                    @endphp
                    <div>
                        <label for="agent_id" class="block text-sm font-medium text-gray-700 mb-1.5">Agent</label>
                        <x-searchable-dropdown
                            name="agent_id"
                            id="agent_id"
                            :items="$agents->map(fn($c) => ['id' => $c->id, 'name' => $c->name])"
                            :selectedId="$selectedId"
                            :selectedName="$selectedName"
                            placeholder="{{ $agentPlaceholder }}"
                            class="block w-full"
                        />
                    </div>
                </div>

                <!-- Payment Gateway -->
                @php
                $prefill = session('prefill_data');
                $selectedGateway = $prefill['payment_gateway'] ?? old('payment_gateway');
                @endphp

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                    <div class="flex flex-wrap gap-8">
                        @foreach ($paymentMethodChose as $chose)
                        <div class="flex items-center gap-4">
                            <div class="flex">
                                <input 
                                    type="checkbox" 
                                    name="payment_methods[]" 
                                    value="{{ $chose->paymentMethod->id }}" 
                                    id="payment_method_{{ $chose->paymentMethod->id }}"
                                    {{ in_array($chose->paymentMethod->id, old('payment_methods', [])) || (strtolower($chose->paymentMethod->charge->name) == 'myfatoorah' && strtolower($chose->paymentMethod->english_name) == 'knet') ? 'checked' : '' }}
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="payment_method_{{ $chose->paymentMethod->id }}" class="ml-2 text-sm text-gray-700">
                                    {{ $chose->paymentMethodGroup->name }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1.5">Amount</label>
                        <input type="number" name="amount" id="amount" step="0.001" min="0"
                            value="{{ old('amount') }}"
                            placeholder="0.000"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                            required>
                    </div>
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700 mb-1.5">Currency</label>
                        <select name="currency" id="currency"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors bg-white">
                            @foreach ($currencies as $currency)
                            <option value="{{ $currency->iso_code }}"
                                {{ old('currency', $currency->country?->name === 'Kuwait' ? $currency->iso_code : '') == $currency->iso_code ? 'selected' : '' }}>
                                {{ $currency->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1.5">Notes (optional)</label>
                    <input type="text" name="notes" id="notes"
                        value="{{ old('notes') }}"
                        placeholder="Add notes"
                        class="block w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors">
                </div>

                <!-- Language -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Invoice Language</label>
                    <p class="text-xs text-gray-500 mb-3">Select the language for the payment voucher sent to client</p>
                    
                    <div class="inline-flex rounded-lg border border-gray-300 p-1 bg-gray-100">
                        <input type="hidden" name="language" :value="currentLanguage">
                        
                        <button type="button" 
                            @click="currentLanguage = 'EN'"
                            :class="currentLanguage === 'EN' 
                                ? 'bg-white text-gray-900 shadow-sm' 
                                : 'text-gray-500 hover:text-gray-700'"
                            class="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all">
                            <span>🇬🇧</span> English
                        </button>
                        
                        <button type="button" 
                            @click="currentLanguage = 'ARB'"
                            :class="currentLanguage === 'ARB' 
                                ? 'bg-white text-gray-900 shadow-sm' 
                                : 'text-gray-500 hover:text-gray-700'"
                            class="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all">
                            <span>🇸🇦</span> العربية
                        </button>
                    </div>
                </div>     
                          
                <!-- Advanced Section -->
                <div x-show="advancedMode" 
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="pt-5 space-y-5">

                    <!-- Divider -->
                    <div class="relative py-2">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>

                        <div class="relative flex justify-center">
                            <span class="bg-blue-600 text-white px-4 py-1 rounded-full text-xs font-medium">
                                Advanced Settings
                            </span>
                        </div>
                    </div>

                    <!-- Advanced Section with left border -->
                    <div class="border-l-4 border-blue-500 bg-blue-50 rounded-r-lg p-4 space-y-4 shadow-md">
                        
                        <!-- Terms and Condition -->
                        <div x-data="termsConditionManager()" x-init="init()">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Terms and Conditions</label>
                            <p class="text-xs text-gray-500 mb-3">These terms will be displayed to the client before proceeding to payment</p>

                            <!-- Template Selector -->
                            <div class="mb-3">
                                <div class="flex items-center gap-3">
                                    <label class="text-xs font-medium text-gray-600">Template:</label>
                                    
                                    <!-- Custom Dropdown -->
                                    <div class="relative flex-1" x-data="{ open: false }">
                                        <button type="button" 
                                            @click="open = !open"
                                            @click.away="open = false"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-left bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 flex items-center justify-between">
                                            <span x-text="selectedTemplateId ? getSelectedTemplateName() : '-- Select a template --'" 
                                                :class="!selectedTemplateId ? 'text-gray-400' : 'text-gray-900'"></span>
                                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        
                                        <!-- Dropdown Options -->
                                        <div x-show="open" 
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto">
                                            
                                            <!-- Empty option -->
                                            <div @click="selectedTemplateId = ''; open = false"
                                                class="px-3 py-2 text-sm text-gray-400 hover:bg-gray-50 cursor-pointer">
                                                -- Select a template --
                                            </div>
                                            
                                            <!-- Template options -->
                                            <template x-for="template in filteredTemplates" :key="template.id">
                                                <div @click="selectedTemplateId = template.id; loadSelectedTemplate(); open = false"
                                                    :class="selectedTemplateId == template.id ? 'bg-blue-50' : 'hover:bg-gray-50'"
                                                    class="px-3 py-2 text-sm cursor-pointer flex items-center justify-between">
                                                    <span x-text="template.title" class="text-gray-900"></span>
                                                    <span x-show="template.is_default" class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">Default</span>
                                                </div>
                                            </template>
                                            
                                            <!-- No templates message -->
                                            <div x-show="filteredTemplates.length === 0" class="px-3 py-2 text-sm text-gray-400 italic">
                                                No templates available
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="button" 
                                        @click="resetToDefault()"
                                        x-show="filteredTemplates.length > 0"
                                        class="px-3 py-2 text-xs font-medium text-blue-600 bg-blue-100 hover:bg-blue-200 rounded-lg transition-colors"
                                        title="Load default template for selected language">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Reset
                                    </button>
                                </div>
                                
                                <!-- No templates message -->
                                <p x-show="filteredTemplates.length === 0 && !loadingTemplates" class="text-xs text-amber-600 mt-2">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    No templates available for <span x-text="currentLang === 'EN' ? 'English' : 'Arabic'"></span>. 
                                    <a href="{{ route('settings.index') }}" class="underline hover:text-amber-700">Create one in Settings</a>
                                </p>

                                <!-- Loading indicator -->
                                <p x-show="loadingTemplates" class="text-xs text-gray-500 mt-2">
                                    <svg class="animate-spin w-4 h-4 inline mr-1" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Loading templates...
                                </p>
                            </div>

                            <!-- Content Editor -->
                            <div class="relative">
                                <textarea 
                                    name="terms_conditions"
                                    id="terms_conditions"
                                    rows="6"
                                    x-model="content"
                                    @input="countWords(); markAsEdited()"
                                    placeholder="Enter the terms and conditions"
                                    :class="wordCount >= maxWords ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'"
                                    class="block w-full border rounded-lg px-3 py-2.5 text-sm focus:ring-1 outline-none transition-colors resize-none"
                                ></textarea>
                                
                                <!-- Edited indicator -->
                                <span x-show="isEdited && selectedTemplateId" 
                                    class="absolute top-2 right-2 px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 rounded">
                                    Edited
                                </span>
                            </div>

                            <!-- Footer info -->
                            <div class="flex justify-between items-center mt-1.5">
                                <div>
                                    <p x-show="selectedTemplateId && !isEdited" class="text-xs text-green-600">
                                        <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        Using template: <span class="font-medium" x-text="getSelectedTemplateName()"></span>
                                    </p>
                                    <p x-show="!selectedTemplateId && content.trim() !== ''" class="text-xs text-gray-500">
                                        Custom content
                                    </p>
                                </div>
                                <p class="text-xs" :class="wordCount >= maxWords ? 'text-red-500 font-medium' : 'text-gray-500'">
                                    <span x-text="wordCount"></span> / <span x-text="maxWords"></span> words
                                    <span x-show="wordCount >= maxWords" class="ml-1">limit reached</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between pt-4">
                    <a href="{{ route('payment.link.index') }}">
                        <button type="button"
                            class="px-5 py-2.5 rounded-full border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                    </a>
                    <button type="submit" :disabled="submitting"
                        class="px-6 py-2.5 rounded-full bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50">
                        Create Payment Link
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script>
function termsConditionManager() {
    return {
        templates: [],
        loadingTemplates: false,
        selectedTemplateId: '',
        content: '{{ old("terms_conditions") }}',
        originalContent: '',
        isEdited: false,
        wordCount: 0,
        maxWords: 2000,
        currentLang: 'EN',

        init() {
            this.countWords();
            this.loadTemplates();
            
            // Get initial language from parent
            this.currentLang = this.getParentLanguage();
            
            // Set up an interval to check for language changes
            // This is more reliable than $watch for nested scopes
            setInterval(() => {
                const parentLang = this.getParentLanguage();
                if (parentLang !== this.currentLang) {
                    this.currentLang = parentLang;
                    this.onLanguageChange();
                }
            }, 100);
        },

        getParentLanguage() {
            // Try to get from Alpine root data
            try {
                // Find the parent element with x-data that has currentLanguage
                const mainCard = document.querySelector('[x-data*="currentLanguage"]');
                if (mainCard && mainCard._x_dataStack) {
                    for (const data of mainCard._x_dataStack) {
                        if (data.currentLanguage) {
                            return data.currentLanguage;
                        }
                    }
                }
            } catch (e) {
                console.log('Error getting parent language:', e);
            }
            return 'EN';
        },

        get filteredTemplates() {
            return this.templates.filter(t => t.language === this.currentLang && t.is_active);
        },

        async loadTemplates() {
            this.loadingTemplates = true;
            try {
                const response = await fetch('{{ route("terms.templates.index") }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    this.templates = data.templates;
                    console.log('Loaded templates:', this.templates);
                    // Auto-select default template if content is empty
                    if (!this.content.trim()) {
                        this.resetToDefault();
                    }
                }
            } catch (error) {
                console.error('Error loading templates:', error);
            } finally {
                this.loadingTemplates = false;
            }
        },

        onLanguageChange() {
            console.log('Language changed to:', this.currentLang);
            this.selectedTemplateId = '';
            this.isEdited = false;
            this.resetToDefault();
        },

        loadSelectedTemplate() {
            if (!this.selectedTemplateId) return;
            
            const template = this.templates.find(t => t.id == this.selectedTemplateId);
            if (template) {
                this.content = template.content;
                this.originalContent = template.content;
                this.isEdited = false;
                this.countWords();
            }
        },

        resetToDefault() {
            console.log('Resetting to default for language:', this.currentLang);
            console.log('Filtered templates:', this.filteredTemplates);
            
            const defaultTemplate = this.filteredTemplates.find(t => t.is_default);
            if (defaultTemplate) {
                console.log('Found default template:', defaultTemplate.title);
                this.selectedTemplateId = defaultTemplate.id;
                this.content = defaultTemplate.content;
                this.originalContent = defaultTemplate.content;
                this.isEdited = false;
                this.countWords();
            } else if (this.filteredTemplates.length > 0) {
                const firstTemplate = this.filteredTemplates[0];
                console.log('Using first template:', firstTemplate.title);
                this.selectedTemplateId = firstTemplate.id;
                this.content = firstTemplate.content;
                this.originalContent = firstTemplate.content;
                this.isEdited = false;
                this.countWords();
            } else {
                console.log('No templates available for this language');
                this.selectedTemplateId = '';
                this.content = '';
                this.originalContent = '';
                this.isEdited = false;
                this.countWords();
            }
        },

        markAsEdited() {
            if (this.selectedTemplateId && this.content !== this.originalContent) {
                this.isEdited = true;
            } else if (this.content === this.originalContent) {
                this.isEdited = false;
            }
        },

        getSelectedTemplateName() {
            const template = this.templates.find(t => t.id == this.selectedTemplateId);
            return template ? template.title : '';
        },

        countWords() {
            const words = this.content.trim() === '' ? [] : this.content.trim().split(/\s+/);
            this.wordCount = words.length;
            
            if (this.wordCount > this.maxWords) {
                const trimmed = words.slice(0, this.maxWords).join(' ');
                this.$nextTick(() => {
                    this.content = trimmed;
                    this.wordCount = this.maxWords;
                });
            }
        }
    }
}
</script>

    <script>
        const clientForm = document.getElementById("client-formTask");

        const file = document.getElementById('file-task-passport');
        const fileName = document.getElementById('task-passport-file-name');
        const taskPassportProcessBtn = document.getElementById('task-passport-process-btn');

        if (file && fileName && taskPassportProcessBtn) {
            file.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            taskPassportProcessBtn.addEventListener('click', (e) => {
                e.preventDefault();
                processFileWithAI();
            });
        } else {
            console.warn("Required elements not found: file, fileName, or taskPassportProcessBtn");
        }

        file.addEventListener('change', (e) => {
            fileName.textContent = e.target.files[0].name;
            file.innerHTML = '';
            let img = document.createElement('img');
            img.src = URL.createObjectURL(e.target.files[0]);
            console.log(img.src);
            img.width = 100;
            img.height = 100;
            file.appendChild(img);

            enableButton(taskPassportProcessBtn);
        });

        dropHandler = (e) => {
            e.preventDefault();

            const droppedFile = e.dataTransfer.files[0];
            if (!droppedFile) return;

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(droppedFile);
            file.files = dataTransfer.files;

            fileName.textContent = droppedFile.name;

            if (droppedFile.type.startsWith('image/')) {
                file.innerHTML = '';
                const img = document.createElement('img');
                img.src = URL.createObjectURL(droppedFile);
                img.width = 100;
                img.height = 100;
                file.appendChild(img);
            }

            enableButton(taskPassportProcessBtn);
        };

        dragOverHandler = (e) => {
            console.log('File in drop area');
            e.preventDefault();
        }

        function processFileWithAI() {
            const fileInput = document.getElementById('file-task-passport');
            const processBtn = document.getElementById('task-passport-process-btn');
            if (fileInput.files.length === 0) {
                alert('Please upload a file first.');
                return;
            }

            processBtn.disabled = true;
            processBtn.textContent = 'Processing...';
            processBtn.classList.add('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            fetch("{{ route('tasks.upload.passport') }}", {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const client = data.data;
                        console.log("Extracted client data:", client);

                        const nameInput = document.getElementById('nameTask');
                        if (nameInput) nameInput.value = client.first_name || '';

                        const middleNameInput = document.getElementById('middleNameTask');
                        if (middleNameInput) middleNameInput.value = client.middle_name || '';

                        const lastNameInput = document.getElementById('lastNameTask');
                        if (lastNameInput) lastNameInput.value = client.last_name || '';

                        const passportInput = document.getElementById('passport_noTask');
                        if (passportInput) passportInput.value = client.passport_no || '';

                        const civilInput = document.getElementById('civil_noTask');
                        if (civilInput) civilInput.value = client.civil_no || '';

                        const addressInput = document.getElementById('addressTask');
                        if (addressInput) addressInput.value = client.address || '';

                        const dobInput = document.querySelector('input[name="date_of_birthTask"]');
                        if (dobInput && client.date_of_birth) {
                            dobInput.value = client.date_of_birth.replace(/\//g, '-');
                        }
                    } else {
                        alert('Error processing file: ' + data.message);
                        console.error('Error:', data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the file.');
                })
                .finally(() => {
                    processBtn.disabled = false;
                    processBtn.textContent = 'Process File';
                    processBtn.classList.remove('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');
                });
        }

        function disableButton(button) {
            console.log('Disabling button:', button);
            if (!button.classList.contains('cursor-not-allowed') && !button.classList.contains('opacity-50')) {
                button.classList.add('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');
            }
            button.disabled = true;
        }

        function enableButton(button) {
            console.log('Enabling button:', button);
            if (button.classList.contains('cursor-not-allowed') || button.classList.contains('opacity-50')) {
                button.classList.remove('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');
            }
            button.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white', 'font-semibold', 'py-2', 'rounded-full',
                'text-sm', 'transition', 'duration-150');
            button.disabled = false;
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const clientAgentMap = @json($clients->mapWithKeys(fn($c) => [$c->id => $c->agent_id]));
            const clientSelect = document.getElementById('client_id');
            const agentSelect = document.getElementById('agent_id');

            if (clientSelect) {
                clientSelect.addEventListener('change', function() {
                    const selectedClientId = this.value;
                    const agentId = clientAgentMap[selectedClientId];

                    if (agentId) {
                        agentSelect.value = agentId;
                    } else {
                        agentSelect.value = '';
                    }
                });
            }
        });
    </script>
</x-app-layout>