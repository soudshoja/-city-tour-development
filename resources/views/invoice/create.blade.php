<x-app-layout>
<div class="container tasks bg-white rounded-lg shadow-md p-5 mt-4">
    <div class="flex justify-between items-center w-full mb-3">
        <div class="bg-gray-200 p-2.5 rounded flex-grow">
            <h2><strong>Create Invoice</strong></h2>
        </div>
        <a href="{{ url()->previous() }}" class="btn btn-success ml-2">Back to Item Details</a>
        <button type="button" class="btn btn-primary ml-2" data-toggle="modal" data-target="#amountInvoiceModal">
            Create Invoice
        </button>
    </div>
    <div class="tasks">
        <h2><strong>Selected Tasks</strong></h2>
        @if (!empty($tasks))
            <div class="list-group w-full">
                @foreach($tasks as $task)
                    <div class="list-group-item">
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
<!-- Modal -->
<div class="modal fade" id="amountInvoiceModal" tabindex="-1" role="dialog" aria-labelledby="amountInvoiceModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Enter Invoice Amount</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="invoice-form" action="{{ route('invoice.store') }}" method="POST">
          @csrf
          <div class="form-group">
            <label for="invoice-amount">Amount</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">$</span>
              </div>
              <input type="number" class="form-control" id="invoice-amount" name="amount" required>
            </div>
          </div>
          <div class="form-group">
            <label for="client-select">Client</label>
            <select class="form-control" id="client-select" name="client_id" onchange="toggleClientFields()">
              <option value="">Select an existing client</option>
              @foreach($clients as $client)
                <option value="{{ $client->id }}">{{ $client->name }}</option>
              @endforeach
              <option value="new">Create new client</option>
            </select>
          </div>
          <div id="new-client-fields" style="display: none;">
            <div class="form-group">
              <label for="new-client-name">Client Name</label>
              <input type="text" class="form-control" id="new-client-name" name="new_client_name">
            </div>
            <div class="form-group">
              <label for="new-client-email">Client Email</label>
              <input type="email" class="form-control" id="new-client-email" name="new_client_email">
            </div>
            <div class="form-group">
              <label for="new-client-phone">Client Phone</label>
              <input type="text" class="form-control" id="new-client-phone" name="new_client_phone">
            </div>
          </div>
          <input type="hidden" id="task-ids" name="task_ids">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
</x-app-layout>

<script>
    function toggleClientFields() {
        const clientSelect = document.getElementById('client-select');
        const newClientFields = document.getElementById('new-client-fields');

        if (clientSelect.value === 'new') {
            newClientFields.style.display = 'block';
        } else {
            newClientFields.style.display = 'none';
        }
    }
</script>
