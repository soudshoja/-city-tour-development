<x-app-layout>
@if (session('success') || session('error'))
    <div id="flash-message" class="alert 
        @if (session('success')) alert-success 
        @elseif (session('error')) alert-danger 
        @endif
        fixed-top-right">
        {{ session('success') ?? session('error') }}
    </div>
@endif
<div class="item-details bg-white rounded-lg shadow-md p-5">
    <div class="flex justify-between items-center w-full mb-3">
        <div class="bg-gray-200 p-2.5 rounded flex-grow">
            <h1><strong>Item Details</strong></h1>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-success ml-2">Back to Item List</a>
    </div>
  
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Item Ref: {{ $item->item_ref }}</h5>
            <p class="card-text"><strong>Description:</strong> {{ $item->description }}</p>
            <p class="card-text"><strong>Item Type:</strong> {{ $item->item_type }}</p>
            <p class="card-text"><strong>Client ID:</strong> {{ $item->client_id }}</p>
            <p class="card-text"><strong>Item Status:</strong> {{ $item->item_status }}</p>
            <p class="card-text"><strong>Item ID:</strong> {{ $item->item_id }}</p>
            <p class="card-text"><strong>Item Code:</strong> {{ $item->item_code }}</p>
            <p class="card-text"><strong>Time Signed:</strong> {{ $item->time_signed }}</p>
            <p class="card-text"><strong>Client Email:</strong> {{ $item->client_email }}</p>
            <p class="card-text"><strong>Agent Email:</strong> {{ $item->agent_email }}</p>
            <p class="card-text"><strong>Total Price:</strong> {{ $item->total_price }}</p>
            <p class="card-text"><strong>Payment Date:</strong> {{ $item->payment_date }}</p>
            <p class="card-text"><strong>Paid:</strong> {{ $item->paid ? 'Yes' : 'No' }}</p>
            <p class="card-text"><strong>Payment Time:</strong> {{ $item->payment_time }}</p>
            <p class="card-text"><strong>Payment Amount:</strong> {{ $item->payment_amount }}</p>
            <p class="card-text"><strong>Refunded:</strong> {{ $item->refunded ? 'Yes' : 'No' }}</p>
            <p class="card-text"><strong>Trip Name:</strong> {{ $item->trip_name }}</p>
            <p class="card-text"><strong>Trip Code:</strong> {{ $item->trip_code }}</p>
        </div>
    </div>
</div>
<div class="tasks bg-white rounded-lg shadow-md p-5 mt-4">
    <div class="flex justify-between items-center w-full mb-3">
        <div class="bg-gray-200 p-2.5 rounded flex-grow">
            <h2><strong>Associated Tasks</strong></h2>
        </div>
        <button type="button" class="btn btn-primary ml-2" onclick="createInvoice()">Create Invoice</button>
        <a href="{{ route('agent.tasks.create', $item->id) }}" class="btn btn-success ml-2">Add Task</a>
    </div>
    @if (!empty($tasks))
        <div class="list-group w-full">
            @foreach($tasks as $task)
                <div class="list-group-item flex justify-between items-center">
                    <div class="flex-grow">
                        <p class="mb-1">{{ $task['reference'] }}</p>
                        <small>{{ $task['description'] }}</small>
                    </div>
                    <div>
                        <input type="checkbox" class="task-checkbox" value="{{ $task['id'] }}">
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p>No tasks associated with this item.</p>
    @endif
</div>

</x-app-layout>

<script>
    function createInvoice() {
        const checkboxes = document.querySelectorAll(".task-checkbox:checked");
        const form = document.getElementById("task-form");
        const taskIds = [];

        checkboxes.forEach((checkbox) => {
            taskIds.push(checkbox.value);
        });

        // Construct the URL with query parameters
        const url = new URL(window.location.origin + "/invoice/create");
        url.searchParams.append("task_ids", JSON.stringify(taskIds));

        // Redirect to the constructed URL
        window.location.href = url.toString();
    }

</script>