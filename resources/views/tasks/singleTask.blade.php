<!-- singleTask.blade.php -->
<div id="taskModal" onclick="closeModalbgC(event)"
    class="fixed inset-0 hidden  bg-opacity-50 flex items-center justify-center z-50">
    <div class="panel my-8 w-full max-w-xl overflow-hidden rounded-lg border-0 p-0">

        <div class="flex items-center justify-between bg-[#fbfbfb] px-5 py-3 dark:bg-[#121c2c]">
            <div class="flex items-center rounded-full p-1 font-semibold text-white pr-3 ">
                <x-application-logo
                    class="block h-8 w-8 rounded-full border-2 border-white/50 object-cover ltr:mr-1 rtl:ml-1" />

                <h3 class="text-lg font-bold px-2 text-black">Task Details</h3>
            </div>
            <button class="text-white-dark hover:text-dark" onclick="closeTaskModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                    class="h-6 w-6">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <!-- Task details content -->
        <div id="taskDetails" class="text-gray-700 dark:text-gray-300 p-5 overflow-y-auto" style="max-height: 60vh">
            <div class="flex justify-between items-center mb-2 font-semibold text-white-dark">
                <h6 class="text-left">Status</h6>
                <p id="taskStatus" class="text-right"></p>
            </div>
            <div id="flightDetailsContainer" class="space-y-2 text-gray-700 dark:text-gray-300 p-5">
                <!-- Flight details will be populated here by JavaScript -->
            </div>
            <form action="" method="POST">
                @csrf
                @method('PUT')
                <x-input-label>Client Name</x-input-label>
                <select name="client_id" id="" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-30">
                    @foreach($clients as $client)
                    <option value="{{ $client->id }}" id="client_{{ $client->id }}">
                        {{ $client->first_name }}
                    </option>
                    @endforeach
                </select>
                <x-input-label>Agent Name</x-input-label>
                <select name="agent_id" id="" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-30">
                    @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" id="agent_{{ $agent->id }}">
                        {{ $agent->name }}
                    </option>
                    @endforeach
                </select>
                <x-input-label>Suppliers</x-input-label>
                <select name="supplier_id" id="" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-30">
                    @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" id="supplier_{{ $supplier->id }}">
                        {{ $supplier->name }}
                    </option>
                    @endforeach
                </select>

                <x-input-label>Type</x-input-label>
                <x-text-input label="Type" id="taskType" class="w-full" />
                <x-input-label>Net Price</x-input-label>
                <x-text-input label="Net Price" id="taskPrice" class="w-full" />
                <x-input-label>Surcharge</x-input-label>
                <x-text-input label="Surcharge" id="taskSurcharge" class="w-full" />
                <x-input-label>Tax</x-input-label>
                <x-text-input label="Tax" id="taskTax" class="w-full" />
                <x-input-label>Total</x-input-label>
                <x-text-input label="Total" id="taskTotal" class="w-full" />
                <x-input-label>Reference</x-input-label>
                <x-text-input label="Reference" id="taskReference" class="w-full" disabled />
                <x-primary-button type="submit" class="mt-2">Update Task</x-primary-button>
            </form>
        </div>
    </div>
</div>

<script>
    // JavaScript to handle showing the task modal
    function ShowTask(taskId) {
        console.log('Opening task modal for task ID:', taskId); // Debugging line to ensure function is triggered

        // Fetch task details using AJAX
        fetch(`/task/${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.task) {
                    console.log('Task data loaded:', data.task); // Debugging line to see the task data in console

                    // Populate modal with task details
                    const statusElement = document.getElementById('taskStatus');
                    statusElement.textContent = data.task.status;

                    // Apply conditional styling based on the task status
                    const statusLower = data.task.status.toLowerCase(); // Standardize status for comparison
                    statusElement.classList.remove('text-green-500', 'text-red-500', 'text-blue-500',
                        'text-gray-500'); // Reset previous classes

                    if (statusLower === 'confirmed') {
                        statusElement.classList.add('text-green-500'); // Green for confirmed
                    } else if (statusLower === 'pending') {
                        statusElement.classList.add('text-red-500'); // Red for pending
                    } else if (statusLower === 'completed') {
                        statusElement.classList.add('text-blue-500'); // Blue for completed
                    } else {
                        statusElement.classList.add('text-gray-500'); // Gray for unlisted statuses
                    }

                    form = document.querySelector('#taskModal form');
                    form.action = `/tasks-update/${taskId}`;
                    // Populate modal with task details
                    document.getElementById('taskStatus').textContent = data.task.status;
                    document.getElementById('taskType').value = data.task.type;
                    document.getElementById('taskPrice').value = data.task.price;
                    document.getElementById('taskSurcharge').value = data.task.surcharge;
                    document.getElementById('taskTax').value = data.task.tax;
                    document.getElementById('taskTotal').value = data.task.total;
                    document.getElementById('taskReference').value = data.task.reference;

                    if (data.task.client != null) {
                        clientOption = document.querySelector(`#client_${data.task.client.id}`);
                        clientOption.selected = true;
                    }

                    if (data.task.agent != null) {
                        agentOption = document.querySelector(`#agent_${data.task.agent.id}`);
                        agentOption.selected = true;
                    }

                    if(data.task.supplier != null) {
                        supplierOption = document.querySelector(`#supplier_${data.task.supplier.id}`);
                        supplierOption.selected = true;
                    }

                    // Display flight details
                    const flightDetailsContainer = document.getElementById('flightDetailsContainer');
                    flightDetailsContainer.innerHTML = ''; // Clear previous flight details
                    if (data.task.flightDetails && data.task.flightDetails.length > 0) {
                        data.task.flightDetails.forEach(detail => {
                            const detailItem = document.createElement('div');
                            detailItem.classList.add('mb-4', 'p-2', 'border', 'rounded');

                            // Populate each flight detail
                            detailItem.innerHTML = `
                            <p><strong>Farebase:</strong> ${detail.farebase}</p>
                            <p><strong>Departure Time:</strong> ${detail.departure_time}</p>
                            <p><strong>Departure From:</strong> ${detail.departure_from} (${detail.airport_from})</p>
                            <p><strong>Arrival Time:</strong> ${detail.arrival_time}</p>
                            <p><strong>Arrival To:</strong> ${detail.arrive_to} (${detail.airport_to})</p>
                            <p><strong>Flight Number:</strong> ${detail.flight_number}</p>
                            <p><strong>Class Type:</strong> ${detail.class_type}</p>
                            <p><strong>Baggage Allowed:</strong> ${detail.baggage_allowed}</p>
                            <p><strong>Equipment:</strong> ${detail.equipment}</p>
                            <p><strong>Seat No:</strong> ${detail.seat_no}</p>
                        `;
                            flightDetailsContainer.appendChild(detailItem);
                        });
                    } else {
                        flightDetailsContainer.textContent = 'No flight details available.';
                    }

                    // Show the modal
                    document.getElementById('taskModal').classList.remove('hidden');
                    console.log('Modal is now visible'); // Debugging line to confirm the modal is shown
                } else {
                    alert('Task details could not be loaded.');
                }
            })
            .catch(error => {
                console.error('Error fetching task details:', error);
            });
    }


    // Close the modal
    function closeTaskModal() {
        document.getElementById('taskModal').classList.add('hidden');
        console.log('Modal has been closed'); // Debugging line to confirm the modal is closed
    }

    // Close the modal when clicking outside the modal content (on the background)
    function closeModalOnBgClick(event) {
        const modal = document.getElementById('taskModal');
        const modalContent = document.querySelector('#taskModal > div');

        // If the clicked target is the modal itself (background), close the modal
        if (event.target === modal) {
            closeTaskModal();
        }
    }

    // Add event listener to close modal when clicking on the background
    document.getElementById('taskModal').addEventListener('click', closeModalOnBgClick);

    // Optional: Close the modal by pressing the Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            closeTaskModal();
        }
    });
</script>

<style>
    /* CSS for the modal */
    #taskModal .dark\:bg-gray-800 {
        background-color: #F3F4F6;
    }
</style>