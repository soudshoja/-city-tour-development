<x-app-layout>
  @if (session('success'))
  <div class="bg-green-500 text-white p-4 rounded mb-4">
    {{ session('success') }}
  </div>
  @elseif (session('error'))
  <div class="bg-red-500 text-white p-4 rounded mb-4">
    {{ session('error') }}
  </div>
  @endif

  <div class="container tasks bg-white rounded-lg shadow-md p-5 mt-4">
    <div class="flex justify-between items-center w-full mb-3">
      <div class="bg-gray-200 p-2.5 rounded flex-grow">
        <h2 class="font-bold">Create Invoice</h2>
      </div>
      <a href="{{ url()->previous() }}" class="bg-green-500 text-white px-4 py-2 rounded ml-2">Back to Item Details</a>
      <button id="openModalBtn" class="bg-blue-500 text-white px-4 py-2 rounded ml-2">Create Invoice</button>
    </div>
    <div class="tasks">
      <h2 class="font-bold">Selected Tasks</h2>
      @if (!empty($tasks))
      <div class="divide-y divide-gray-200 w-full">
        @foreach($tasks as $task)
        <div class="py-2">
          <p class="mb-1"><strong>Reference:</strong> {{ $task->reference }}</p>
          <p class="mb-1"><strong>Description:</strong> {{ $task->description }}</p>
        </div>
        @endforeach
      </div>
      @else
      <p>No tasks selected.</p>
      @endif
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
      </form>
    </div>
  </div>

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
</x-app-layout>