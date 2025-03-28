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
        <div class="mb-3 p-4 bg-white rounded-lg shadow-md">
            <h5 class="text-lg font-bold">Item Ref: {{ $item->item_ref }}</h5>
            <p class="text-gray-700"><strong>Description:</strong> {{ $item->description }}</p>
            <p class="text-gray-700"><strong>Item Type:</strong> {{ $item->item_type }}</p>
            <p class="text-gray-700"><strong>Client ID:</strong> {{ $item->client_id }}</p>
            <p class="text-gray-700"><strong>Item Status:</strong> {{ $item->item_status }}</p>
            <p class="text-gray-700"><strong>Item ID:</strong> {{ $item->item_id }}</p>
            <p class="text-gray-700"><strong>Item Code:</strong> {{ $item->item_code }}</p>
            <p class="text-gray-700"><strong>Time Signed:</strong> {{ $item->time_signed }}</p>
            <p class="text-gray-700"><strong>Client Email:</strong> {{ $item->client_email }}</p>
            <p class="text-gray-700"><strong>Agent Email:</strong> {{ $item->agent_email }}</p>
            <p class="text-gray-700"><strong>Total Price:</strong> {{ $item->total_price }}</p>
            <p class="text-gray-700"><strong>Payment Date:</strong> {{ $item->payment_date }}</p>
            <p class="text-gray-700"><strong>Paid:</strong> {{ $item->paid ? 'Yes' : 'No' }}</p>
            <p class="text-gray-700"><strong>Payment Time:</strong> {{ $item->payment_time }}</p>
            <p class="text-gray-700"><strong>Payment Amount:</strong> {{ $item->payment_amount }}</p>
            <p class="text-gray-700"><strong>Refunded:</strong> {{ $item->refunded ? 'Yes' : 'No' }}</p>
            <p class="text-gray-700"><strong>Trip Name:</strong> {{ $item->trip_name }}</p>
            <p class="text-gray-700"><strong>Trip Code:</strong> {{ $item->trip_code }}</p>
        </div>
    </div>
    <div class="tasks bg-white rounded-lg shadow-md p-5 mt-4">
        <div class="flex justify-between items-center w-full mb-3">
            <div class="bg-gray-200 p-2.5 rounded flex-grow">
                <h2 class="text-lg font-bold">Associated Tasks</h2>
            </div>
            <button type="button" class="bg-blue-500 text-white px-4 py-2 rounded ml-2 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" onclick="createInvoice()">Create Invoice</button>
        </div>
        @if (!empty($tasks))
        <div class="divide-y divide-gray-200">
            @foreach($tasks as $task)
            <div class="flex justify-between items-center py-2">
                <div class="flex-grow">
                    <p class="text-gray-700 font-semibold">{{ $task['reference'] }}</p>
                    <small class="text-gray-500">{{ $task['description'] }}</small>
                </div>
                <div>
                    <input type="checkbox" class="task-checkbox h-5 w-5 text-blue-600" value="{{ $task['id'] }}" id="task-{{ $task['id'] }}" name="tasks[]">
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500">No tasks associated with this item.</p>
        @endif
    </div>

</x-app-layout>

<script>
    function createInvoice() {
        const checkboxes = document.querySelectorAll(".task-checkbox:checked");
        const taskIds = [];

        checkboxes.forEach((checkbox) => {
            taskIds.push(checkbox.value);
        });

        if (taskIds.length === 0) {
            alert("Please select at least one task to create an invoice.");
            return;
        }

        // Construct the URL with query parameters
        const url = new URL(window.location.origin + "/invoices/create");
        url.searchParams.append("task_ids", JSON.stringify(taskIds));

        // Redirect to the constructed URL
        window.location.href = url.toString();
    }
</script>