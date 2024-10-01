<x-app-layout>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  @if (session('success'))
  <div class="bg-green-500 text-white p-4 rounded mb-4">
    {{ session('success') }}
  </div>
  @elseif (session('error'))
  <div class="bg-red-500 text-white p-4 rounded mb-4">
    {{ session('error') }}
  </div>
  @endif
  <div x-data="invoiceModal()">
  <div class="bg-white rounded-lg shadow-md p-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card bg-light text-light">

                <div class="card-body bg-white shadow-lg p-6 rounded-lg border-none">
                <h2 class="text-2xl font-bold text-gray-800">Create Invoice</h2>
                        <p class="text-sm text-gray-500 mb-4">Generate a new invoice and share it with clients.</p>

                        <!-- Total Value -->
                        <div class="flex items-center space-x-4 py-4">
                            <label class="form-label text-gray-700 font-semibold">Total Invoice: 
                                <span x-text="total" class="text-3xl font-bold text-gray-800">$0</span>
                            </label>
                            <label class="form-label text-gray-700 font-semibold">Currency:</label>
                            <select id="currency" class="form-control bg-light text-dark" x-model="currency">
                                <option value="usd">USD</option>
                                <option value="eur">EUR</option>
                                <option value="myr">MYR</option>
                                <option value="kwd">KWD</option>
                            </select>
                    </div>

       <!-- Choose Client -->
          <div class="flex items-center space-x-4 py-4">
              <p class="text-gray-400 text-lg">Choose Client:</p>
              
              <!-- Input showing selected client -->
              <input
                  id="selectedClientName"
                   x-model="selectedClientName"
                  class="w-2/3 bg-gray-100 text-gray-700 border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                  placeholder="Click to Choose Client"
                  value="{{ old('client_name', $selectedClientName ?? '') }}"
                  readonly
              />

              <!-- Button to open modal -->
              <button @click="openClientModal()" class="ml-4 p-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-all duration-300 ease-in-out">
                  <i class="fas fa-user-plus"></i> Select Client
              </button>
          </div>

          <!-- Choose Task -->
          <div class="flex items-center space-x-4 py-4">
              <p class="text-gray-400 text-lg">Choose Task:</p>
              
              <!-- Input showing selected task -->
              <input
                id="selectedTaskName"
                x-model="selectedTaskName"
                class="w-2/3 bg-gray-100 text-gray-700 border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                placeholder="Click to Choose Task"
                value="{{ old('task_name', $selectedTaskName ?? '') }}"
                readonly
              />

              <!-- Button to open modal -->
              <button @click="openTaskModal()" class="ml-4 p-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-all duration-300 ease-in-out">
                  <i class="fas fa-tasks"></i> Select Task
              </button>
          </div>


                    <!-- Task Selection -->
                    <div id="task-list" class="mb-3">

                    <div class="flex items-center space-x-4 py-4">
                        <p class="text-gray-400 text-lg">Invoice Items:</p>
                            <input type="text"  x-model="taskRemark" class="w-1/3 bg-gray-100 text-gray-700 border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" placeholder="Insert Remark" id="remark" />
                            <input type="number"  x-model="taskPrice" class="w-1/3 bg-gray-100 text-gray-700 border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent" placeholder="Price" id="price" />
                            <button  @click="addTask()" class="ml-4 p-2 bg-yellow-500 text-white rounded-lg hover:bg-orange-600 transition-all duration-300 ease-in-out" id="add-task-btn">Add Item</button>
                        </div>

                    </div>

                     <!-- Display Added Tasks Below -->
 <div class="p-5">
        <p class="text-xl text-yellow-500 font-bold">Items</p>
        <p class="text-xs text-gray-400 mb-2"><strong>Client:</strong> <span x-text="selectedClientName"></span></p>

        <!-- Table for Tasks -->
        <div class="overflow-x-auto">
            <table class="table-auto w-full text-left text-gray-400">
                <thead class="border-b border-gray-600">
                    <tr>
                        <th class="p-2 text-yellow-500">Description</th>
                        <th class="p-2 text-yellow-500">Remark</th>
                        <th class="p-2 text-yellow-500">Price</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(task, index) in tasksNew" :key="index">
                        <tr class="border-b border-gray-600">
                            <td class="p-2 text-sm" x-text="task.taskName"></td>
                            <td class="p-2 text-sm" x-text="task.remark"></td>
                            <td class="p-2 text-sm" x-text="task.price"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

                    <!-- Generate Invoice Button -->
                    <div class="text-center mt-4">
                        <button @click="generateInvoice()" class="ml-4 p-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-all duration-300 ease-in-out" id="generate-invoice-btn">
                        <span id="button-text">Generate Invoice</span>
                        <span id="button-loading" class="hidden">Generating...</span>
                       </button>
                    </div>
                </div>
             </form>
            </div>
        </div>
    </div>
</div>




  <!-- Clients Modal -->
<div x-show="isClientModalOpen" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75" style="display: none;">
    <div class="bg-white p-4 rounded-lg shadow-lg w-3/4 md:w-1/2">
        <p class="text-yellow-500 font-bold mb-4">Choose Client</p>

        <!-- Search Box -->
        <input
            type="text"
            placeholder="Search Client..."
            x-model="searchClient"
            class="w-full p-2 mb-4 border border-gray-300 rounded-md text-gray-900 focus:outline-none focus:ring focus:ring-yellow-500"
        />

        <!-- List of Clients -->
        <ul class="max-h-60 overflow-y-auto">
            <template x-for="client in filteredClients" :key="client.id">
                <li @click="selectClient(client)" class="cursor-pointer p-2 hover:bg-gray-100 text-gray-800">
                    <span x-text="client.name"></span> - <span x-text="client.email"></span>
                </li>
            </template>
        </ul>

        <!-- Close Modal Button -->
        <div class="text-right mt-4">
            <button @click="closeClientModal()" class="bg-yellow-500 text-white px-4 py-2 rounded-lg">Close</button>
        </div>
    </div>
</div>

<!-- Tasks Modal -->
<div x-show="isTaskModalOpen" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75" style="display: none;">
    <div class="bg-white p-4 rounded-lg shadow-lg w-3/4 md:w-1/2">
        <p class="text-yellow-500 font-bold mb-4">Choose Task</p>

        <!-- Search Box -->
        <input
            type="text"
            placeholder="Search Task..."
            x-model="searchTask"
            class="w-full p-2 mb-4 border border-gray-300 rounded-md text-gray-900 focus:outline-none focus:ring focus:ring-yellow-500"
        />

        <!-- List of Tasks -->
        <ul class="max-h-60 overflow-y-auto">
            <template x-for="task in filteredTasks" :key="task.id">
                <li @click="selectTask(task)" class="cursor-pointer p-2 hover:bg-gray-100 text-gray-800">
                    <span x-text="task.description"></span>
                </li>
            </template>
        </ul>

        <!-- Close Modal Button -->
        <div class="text-right mt-4">
            <button @click="closeTaskModal()" class="bg-blue-500 text-white px-4 py-2 rounded-lg">Close</button>
        </div>
    </div>
</div>


  <!-- Modal background overlay -->
  <div id="modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-lg mx-auto">
      <h2 class="text-2xl font-bold mb-4">Enter Invoice Amount</h2>
      <form id="invoice-form" action="{{ route('invoice.store') }}" method="POST">
        @csrf
        <div class="mb-4">
          <label for="invoice-amount" class="block text-sm font-medium text-gray-700">Amount</label>
          <div class="mt-1 relative rounded-md shadow-sm">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="text-gray-500 sm:text-sm">$</span>
            </div>
            <input type="number" name="amount" id="invoice-amount" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md" placeholder="0.00" required>
          </div>
        </div>
        <div class="mb-4">
          <label for="client-select" class="block text-sm font-medium text-gray-700">Client</label>
          <select id="client-select" name="client_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" onchange="toggleClientFields()">
            <option value="">Select an existing client</option>
            @foreach($clients as $client)
            <option value="{{ $client->id }}">{{ $client->name }}</option>
            @endforeach
            <option value="new">Create new client</option>
          </select>
        </div>
        <div id="new-client-fields" class="mb-4" style="display: none;">
          <!-- New client fields go here -->
        </div>
        <div class="flex justify-end">
          <button type="button" id="closeModalBtn" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 focus:outline-none mr-3">Cancel</button>
          <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none">Save</button>
        </div>

    </div>
  </div>
</div>
  <script>
    function invoiceModal() {
        return {
            isClientModalOpen: false,
            isTaskModalOpen: false,
            searchClient: '',
            searchTask: '',
            clients: @json($clients), // Make sure clients are passed from the controller
            tasks: @json($tasks), // Make sure tasks are passed from the controller
            selectedClient: null,
            selectedClientId: null,
            selectedClientName: null,
            selectedTaskName: null,
            selectedTask: null,
            taskRemark: '',
            taskPrice: 0,
            total: 0,
            tasksNew: [],
            currency: 'usd',

            openClientModal() {
                this.isClientModalOpen = true;
            },

            closeClientModal() {
                this.isClientModalOpen = false;
            },

            selectClient(client) {
                this.selectedClient = client;
                this.selectedClientId = client.id;
                this.selectedClientName = client.name;
                document.getElementById('selectedClientName').value =  client.name + '  (' + client.email + ')';
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
                this.selectedTaskName = task.description;
                document.getElementById('selectedTaskName').value =  task.description;
                this.closeTaskModal();
            },

              // Method to add task
              addTask() {
                if (this.taskRemark && this.taskPrice !== null) {
                    const newTask = {
                        clientName:  this.selectedClientName,
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
            generateInvoice() {
              // const client_id = document.getElementById('client').value;
              const currency = document.getElementById('currency').value;
              const total = this.total;
              const tasks = this.tasksNew;
              const clientId = this.selectedClientId;
              const button = document.getElementById('generate-invoice-btn');
              const buttonText = document.getElementById('button-text');
              const buttonLoading = document.getElementById('button-loading');
              

               // Start loading state
              button.disabled = true;
              buttonText.classList.add('hidden');
              buttonLoading.classList.remove('hidden');


              fetch('{{ route("invoice.store") }}', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                      'X-CSRF-TOKEN': '{{ csrf_token() }}'
                  },
                  body: JSON.stringify({ clientId, currency, total, tasks })
              })
              .then(response => response.json())
              .then(data => {
                  if (data.redirect_url) {
                      window.location.href = data.redirect_url;
                  } else {
                      alert('Failed to generate invoice.');
                  }
              })
              .catch(error => {
                  console.error("Error generating invoice:", error);
                  alert("There was an error generating the invoice.");
              })
              .finally(() => {
                  // Reset button state
                  button.disabled = false;
                  buttonText.classList.remove('hidden');
                  buttonLoading.classList.add('hidden');
              });

            },

            get filteredClients() {
                return this.clients.filter(client =>
                    client.name.toLowerCase().includes(this.searchClient.toLowerCase())
                );
            },

            get filteredTasks() {
            return this.tasks
        .filter(task => task.client_id === this.selectedClientId) // Filter by selected client ID
        .filter(task => task.description.toLowerCase().includes(this.searchTask.toLowerCase())); // Filter by search term
},
        }
    }
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

    document.getElementById('add-task-btn').addEventListener('click', function () {
        const selectedTaskId = document.querySelector('input[type="checkbox"]:checked').value;
        const remark = document.getElementById('remark').value;
        const price = parseFloat(document.getElementById('price').value);

        tasks.push({ task_id: selectedTaskId, remark: remark, price: price });

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

    function updateTotal() {
        const total = tasks.reduce((sum, task) => sum + task.price, 0);
        document.getElementById('total').innerText = `$${total.toFixed(2)}`;
    }

    document.getElementById('generate-invoice-btn').addEventListener('click', function () {
        const client_id = document.getElementById('client').value;
        const currency = document.getElementById('currency').value;

        fetch('{{ route("invoice.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ client_id, currency, tasks })
        }).then(response => response.json()).then(data => {
            window.location.href = data.redirect_url;
        });
    });
</script>
</x-app-layout>