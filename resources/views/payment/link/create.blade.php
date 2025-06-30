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
        <div class="p-6 bg-white rounded-lg shadow-md" x-data="{
                addClientModal: false, showUploadForm: false, showManualForm: false,
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
            <div class="flex justify-between items-center gap-5 my-3">
                <div class="flex items-center gap-5">
                    <h2 class="text-3xl font-bold">Payment Link</h2>
                </div>
                <div class="flex items-center gap-5">
                    <div @click="addClientModal = true"
                        class="p-2 text-center bg-white rounded-full shadow group hover:bg-black dark:hover:bg-gray-600 dark:bg-gray-700 cursor-pointer"
                        data-tooltip-left="Add New Client">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                            class="stroke-black dark:stroke-gray-300 group-hover:stroke-white group-focus:stroke-white">
                            <path d="M15 12L12 12M12 12L9 12M12 12L12 9M12 12L12 15" stroke="" stroke-width="1.5"
                                stroke-linecap="round" />
                            <path
                                d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7"
                                stroke="" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </div>

                    <!-- Modal -->
                    <div x-show="addClientModal" x-cloak class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-20" @click.away="addClientModal = false">
                        <div class="bg-white rounded-lg p-6 w-full max-w-lg shadow-xl overflow-y-auto" style="max-height: 90vh;">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-800">Client Registration</h2>
                                    <p class="text-gray-600 italic text-xs mt-1">Please fill in the required client information to register</p>
                                </div>
                                <button @click="addClientModal = false" class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">
                                    &times;
                                </button>
                            </div>

                            <form action="{{ route('clients.store') }}" method="POST" id="client-formTask" class="space-y-4">
                                @csrf
                                <input type="hidden" name="task_id" :value="modalTaskId">
                                <input type="hidden" name="agent_id" :value="modalAgentId">

                                <div id="upload-passport-container" class="my-2 border-2 border-dashed border-gray-400 rounded-md flex flex-col justify-center gap-2 items-center p-2 min-h-20 max-h-48 mb-2" ondrop="dropHandler(event);" ondragover="dragOverHandler(event);">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M18 10L13 10" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="M10 3H16.5C16.9644 3 17.1966 3 17.3916 3.02567C18.7378 3.2029 19.7971 4.26222 19.9743 5.60842C20 5.80337 20 6.03558 20 6.5" stroke="#1C274C" stroke-width="1.5" />
                                        <path d="M2 6.94975C2 6.06722 2 5.62595 2.06935 5.25839C2.37464 3.64031 3.64031 2.37464 5.25839 2.06935C5.62595 2 6.06722 2 6.94975 2C7.33642 2 7.52976 2 7.71557 2.01738C8.51665 2.09229 9.27652 2.40704 9.89594 2.92051C10.0396 3.03961 10.1763 3.17633 10.4497 3.44975L11 4C11.8158 4.81578 12.2237 5.22367 12.7121 5.49543C12.9804 5.64471 13.2651 5.7626 13.5604 5.84678C14.0979 6 14.6747 6 15.8284 6H16.2021C18.8345 6 20.1506 6 21.0062 6.76946C21.0849 6.84024 21.1598 6.91514 21.2305 6.99383C22 7.84935 22 9.16554 22 11.7979V14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14V6.94975Z" stroke="#1C274C" stroke-width="1.5" />
                                    </svg>
                                    <input type="file" name="file" id="file-task-passport" class="hidden" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf">
                                    <p id="task-passport-file-name">You can drag and drop a file here</p>
                                    <label for="file-task-passport" class="bg-black text-white font-semibold p-2 rounded-md border-2 border-black hover:border-2 hover:border-cyan-500">
                                        Upload File
                                    </label>
                                </div>

                                <div class="my-2">
                                    <button id="task-passport-process-btn" class="w-full bg-gray-300 text-gray-500 font-semibold py-2 rounded-full text-sm transition duration-150 cursor-not-allowed" disabled>
                                        Process File
                                    </button>
                                </div>

                                <div class="my-2">
                                    <label for="nameTask" class="block text-sm font-medium text-gray-700 mb-1">Client's Name</label>
                                    <input type="text" name="name" id="nameTask" :value="modalClientName" placeholder="Client's name"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                </div>

                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Passenger's Name</label>
                                    <input type="text" name="passenger_name" id="passengerName" :value="modalPassengerName" placeholder="Passenger's name"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100 text-gray-500 focus:outline-none focus:ring-0 focus:border-gray-300 cursor-not-allowed" disabled>
                                </div>

                                <div class="flex gap-4 mb-3">
                                    <div class="w-2/3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" name="email" id="emailTask" placeholder="Client's email"
                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="w-1/3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                                        <input type="date" name="date_of_birthTask"
                                            class="w-full text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                    <div class="flex gap-2">
                                        <div class="w-40">
                                            <x-searchable-dropdown
                                                name="dial_code"
                                                :items="\App\Models\Country::all()->map(fn($country) => [
                                                    'id' => $country->dialing_code,
                                                    'name' => $country->dialing_code . ' ' . $country->name
                                                ])"
                                                placeholder=" Search Dial Code"
                                                :showAllOnOpen="true" />
                                        </div>

                                        <input type="text" name="phone" id="phoneTask"
                                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Client's phone number" required>
                                    </div>
                                </div>

                                <div class="flex gap-4 mb-3">
                                    <div class="w-1/2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Passport Number</label>
                                        <input type="text" name="passport" id="passport_noTask"
                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="w-1/2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Civil Number</label>
                                        <input type="text" name="civil_no" id="civil_noTask"
                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                    <input type="text" name="address" id="addressTask"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Client's address">
                                </div>

                                <div>
                                    @unlessrole('agent')
                                    <x-searchable-dropdown
                                        name="agent_id"
                                        :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
                                        placeholder="Select an Agent"
                                        label="Agent" />
                                    @else
                                    <label for="agent_id" class="block text-sm font-medium text-gray-700">Agent</label>
                                    <input
                                        type="text"
                                        name="agent_id"
                                        id="agent_id"
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
                </div>
            </div>

            <form action="{{ route('payment.link.store') }}" method="POST" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="client_id" class="block text-sm font-medium text-gray-700">Client</label>
                        <select name="client_id" id="client_id" class="form-select mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">Select a Client</option>
                            @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="agent_id" class="block text-sm font-medium text-gray-700">Agent</label>
                        <select name="agent_id" id="agent_id" class="form-select mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">Select an Agent</option>
                            @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div x-data="{ selectedGateway: '' }">
                    <div :class="selectedGateway === 'MyFatoorah' ? 'grid grid-cols-1 md:grid-cols-2 gap-6 items-start' : 'block'">
                        <div>
                            <label for="payment-gateway" class="block text-sm font-medium text-gray-700">Payment Gateway</label>
                            <select name="payment_gateway" id="payment-gateway"
                                class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                x-model="selectedGateway">
                                <option value="" disabled>Select Payment Gateway</option>
                                @foreach ($paymentGateways as $gateway)
                                <option value="{{ $gateway->name }}">{{ $gateway->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <template x-if="selectedGateway === 'MyFatoorah'">
                            <div>
                                <label for="payment-method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                                <select name="payment_method" id="payment-method"
                                    class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach ($paymentMethods as $methods)
                                    <option value="{{ $methods->id }}">{{ $methods->english_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                        <input type="number" name="amount" id="amount"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            required>
                    </div>
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
                        <select name="currency" id="currency"
                            class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach ($currencies as $currency)
                            <option value="{{ $currency->iso_code }}"
                                {{ $currency->country ? $currency->country->name == 'Kuwait' ? 'selected' : '' : '' }}>
                                {{ $currency->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <input type="text" name="notes" id="notes"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="flex justify-between">
                    <a href="{{ route('payment.link.index') }}">
                        <button type="button"
                            class="rounded-full shadow-md border border-gray-200 hover:bg-gray-400 px-4 py-2">
                            Cancel
                        </button>
                    </a>
                    <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-full shadow-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Create Payment Link
                    </button>
                </div>
            </form>

        </div>
    </div>
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

            // create new DataTransfer to set file input (only way to populate programmatically)
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(droppedFile);
            file.files = dataTransfer.files;

            fileName.textContent = droppedFile.name;

            // Optional: preview image
            if (droppedFile.type.startsWith('image/')) {
                file.innerHTML = ''; // clear label
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

            // Show loading indication and disable button
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
                        if (nameInput) nameInput.value = client.name || '';
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
                        // Handle the response data as needed
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
                    // Restore button state
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
            button.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white', 'font-semibold', 'py-2', 'rounded-full', 'text-sm', 'transition', 'duration-150');
            button.disabled = false;
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const clientAgentMap = @json($clients->mapWithKeys(fn($c) => [$c->id => $c->agent_id]));
            const clientSelect = document.getElementById('client_id');
            const agentSelect = document.getElementById('agent_id');

            clientSelect.addEventListener('change', function() {
                const selectedClientId = this.value;
                const agentId = clientAgentMap[selectedClientId];

                if (agentId) {
                    agentSelect.value = agentId;
                } else {
                    agentSelect.value = '';
                }
            });
        });
    </script>
</x-app-layout>