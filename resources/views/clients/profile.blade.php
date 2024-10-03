<x-app-layout>
        @if(session('success'))
            <div class="bg-green-500 text-white p-4 rounded-md">
                {{ session('success') }}
            </div>
        @endif
  <div x-data="clientModal()">
    <div class="container mx-auto p-4 md:p-6">
        <!-- Client Info Section -->
        <div
            class="panel dark:bg-gray-800 shadow-md rounded-lg p-4 flex flex-col md:flex-row items-center justify-between">
            <!-- Client Picture and Name Section -->
            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-x-4">
                <!-- Client Picture -->
                <div class="flex-shrink-0">
                    <img src="{{ asset('images/userPic.svg') }}" alt="Client Picture" class="rounded-full w-24 h-24">
                </div>

                <!-- Client Name and Number -->
                <div class="text-center-mobile-left-desktop">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $client->name }}</h2>
                    <p class="text-gray-500 dark:text-gray-400">Email: {{ $client->email }}</p>
                    <p class="text-gray-500 dark:text-gray-400">Phone: {{ $client->phone }}</p>
                    <p class="text-gray-500 dark:text-gray-400">Address: {{ $client->address }}</p>
                    <p class="text-gray-500 dark:text-gray-400">Agent: {{ $client->agent->name }}</p>
                </div>




            </div>

            <!-- Button -->
            <div class="mt-4 md:mt-0">
                <x-primary-button @click="openClientModal()">Change Agent</x-primary-button>
            </div>
        </div>

        <!-- Client Modal -->
        <div x-show="isClientModalOpen" class="fixed z-10 inset-0 overflow-y-auto items-center justify-center bg-gray-800 bg-opacity-75" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

                    <!-- Modal Content -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl transform transition-all sm:max-w-lg sm:w-full p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Change Agent</h2>

                        <form action="{{ route('client.changeAgent', $client->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <label for="agent_id" class="block text-sm font-bold text-gray-700 dark:text-gray-100">Select New Agent</label>
                            <select id="agent_id" name="agent_id" class="block w-full px-3 py-2 mt-1 border-gray-300 dark:bg-gray-700 dark:text-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" required>
                                <option value="{{ $client->agent->id }}" selected>{{ $client->agent->name }}</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>

                            <!-- Modal Close/Submit Buttons -->
                            <div class="mt-4">
                                <x-primary-button type="submit">Save Changes</x-primary-button>
                                <x-secondary-button @click="openModal = false">Cancel</x-secondary-button>
                            </div>
                        </form>


                        <!-- Close Button -->
                        <x-secondary-button @click="closeClientModal()">Close</x-secondary-button>
                    </div>
                </div>
            </div>
        <!-- Client Orders Section -->
        <div class="panel dark:bg-gray-800 shadow-md rounded-lg p-4 mt-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Tasks</h3>
            @if($tasks->count() > 0)
            <ul>
                @foreach($tasks as $task)
                <li class="border-b border-gray-200 dark:border-gray-600 py-2">
                    <strong class="text-gray-900 dark:text-gray-100">Task: {{ $task->description }}:</strong> <span
                        class="text-gray-500 dark:text-gray-400">{{ $task->task_type }}</span> - 
                        <span
                        class="text-gray-500 dark:text-gray-400">{{ $task->status }}</span>
                </li>
                @endforeach
            </ul>
            @else
            <p class="text-gray-500 dark:text-gray-400">No tasks found for this client.</p>
            @endif
        </div>

        <!-- Invoice List Section -->
        <div class="panel dark:bg-gray-800 shadow-md rounded-lg p-4 mt-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Invoice List</h3>
            @if($invoices->count() > 0)
            <ul>
                @foreach($invoices as $invoice)
                <li class="border-b border-gray-200 dark:border-gray-600 py-2">
                    <strong class="text-gray-900 dark:text-gray-100">Invoice #{{ $invoice->invoice_number }}:</strong> <span
                        class="text-gray-500 dark:text-gray-400">{{ $invoice->amount }} - {{ $invoice->status }}</span>
                </li>
                @endforeach
            </ul>
            @else
            <p class="text-gray-500 dark:text-gray-400">No invoices found for this client.</p>
            @endif
        </div>
    </div>
    </div>
    <script>
    function clientModal() {
        return {
            isClientModalOpen: false,
            searchClient: '',
            selectedClient: null,
            agents: @json($agents),
            selectedClientId: null,
            selectedClientName: null,

            openClientModal() {
                this.isClientModalOpen = true;
            },

            closeClientModal() {
                this.isClientModalOpen = false;
            },

            filteredClients() {
                return this.agents.filter(agent => agent.name.toLowerCase().includes(this.searchClient.toLowerCase()));
            },

            selectClient(client) {
                this.selectedClient = client;
                this.selectedClientId = client.id;
                this.selectedClientName = client.name;
                this.closeClientModal();
            },

        }
    }
    </script>
</x-app-layout>