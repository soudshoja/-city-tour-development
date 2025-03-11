<x-app-layout>
    <!-- Breadcrumbs -->
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('tasks.index') }}" class="customBlueColor hover:underline"> Tasks</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <span>Queue</span>
        </li>
    </ul>
    <!-- ./Breadcrumbs -->

    @if($queueTasks->isEmpty())
    <p class="text-center text-gray-500 dark:text-gray-300">No tasks in the queue</p>
    @else
    @foreach($queueTasks as $task)
    <div class="p-2 bg-white dark:bg-gray-700 rounded-md shadow-md mb-2">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $task->reference }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-300">{{ $task->agent->name ?? 'Not Agent Set' }}</p>
            </div>
            <div>
                <a href="{{ route('tasks.show', $task->id) }}" class="view-task text-blue-600 dark:text-blue-500">View</a>
            </div>
        </div>
    </div>
    @endforeach
    @endif

    <!-- Modal -->
    <div id="taskModal" class="fixed inset-0 flex items-center justify-center bg-opacity-50 hidden bg-gray-900 w">
        <div class="bg-white dark:bg-gray-700 rounded-md shadow-md p-4 max-w-md mx-auto">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Task Details</h2>
                <button id="closeModal" class="text-gray-500 dark:text-gray-300">&times;</button>
            </div>
            <div id="modalContent" class="mt-4">
                <!-- Task details will be loaded here -->
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('taskModal');
        const closeModal = document.getElementById('closeModal');
        const modalContent = document.getElementById('modalContent');

        document.querySelectorAll('.view-task').forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                const url = this.getAttribute('href');
                console.log('Fetching URL:', url); // Debugging log

                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Fetched Data:', data); // Debugging log
                        const html = `

              

<div class="bg-white dark:bg-gray-900 shadow-xl rounded-2xl p-6 space-y-6 max-w-lg mx-auto text-sm border border-gray-200 dark:border-gray-700">
  <!-- General Information -->
  <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg shadow-md">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">📌 General Information</h2>
    <div class="grid grid-cols-2 gap-4 mt-3">
      <div>
        <label class="text-gray-600 dark:text-gray-400 font-medium">Reference</label>
        <p class="text-gray-900 dark:text-gray-100 font-semibold">${data.reference}</p>
      </div>
      <div>
        <label class="text-gray-600 dark:text-gray-400 font-medium">Agent</label>
        <p class="text-gray-900 dark:text-gray-100 font-semibold">${data.agent.name}</p>
      </div>
      <div class="col-span-2">
        <label class="text-gray-600 dark:text-gray-400 font-medium">Client</label>
        <p class="text-gray-900 dark:text-gray-100 font-semibold">${data.client_name}</p>
      </div>
      <div class="col-span-2 border-t border-gray-300 dark:border-gray-600 pt-4">
        <label class="text-gray-600 dark:text-gray-400 font-medium">Description</label>
        <p class="text-gray-800 dark:text-gray-300">${data.description}</p>
      </div>
    </div>
  </div>
  <!-- Pricing Section -->
  <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg shadow-md">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">💰 Pricing Details</h2>
    <div class="grid grid-cols-3 gap-2 text-center mt-3">
      <div>
        <label class="text-gray-600 dark:text-gray-400 font-medium">Price</label>
        <p class="text-gray-900 dark:text-gray-100 font-semibold">${data.price}</p>
      </div>
      <div>
        <label class="text-gray-600 dark:text-gray-400 font-medium">Tax</label>
        <p class="text-gray-900 dark:text-gray-100 font-semibold">${data.tax}</p>
      </div>
      <div>
        <label class="text-gray-600 dark:text-gray-400 font-medium">Total</label>
        <p class="text-gray-900 dark:text-gray-100 font-semibold">${data.total}</p>
      </div>
    </div>
  </div>

  <!-- Flight Details -->
   <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg shadow-md">
     <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-1">✈️ Flight Details</h2>
        <div class="grid grid-cols-2 gap-4 text-gray-800 dark:text-gray-200">
          <div class="flex flex-col">
        <span class="text-gray-500 dark:text-gray-400 text-xs">Departure</span>
        <span class="font-medium">${data.flight_details.airport_from}</span>
        <span class="text-sm opacity-80">${data.flight_details.departure_time}</span>
         </div>
      
      <div class="flex flex-col text-right">
        <span class="text-gray-500 dark:text-gray-400 text-xs">Arrival</span>
        <span class="font-medium">${data.flight_details.airport_to}</span>
        <span class="text-sm opacity-80">${data.flight_details.arrival_time}</span>
      </div>

      <div class="flex flex-col border-t pt-3">
        <span class="text-gray-500 dark:text-gray-400 text-xs">Flight</span>
        <span class="font-medium">${data.flight_details.flight_number} - ${data.flight_details.class_type}</span>
      </div>

        <div class="flex flex-col border-t pt-3 text-right">
        <span class="text-gray-500 dark:text-gray-400 text-xs">Baggage</span>
        <span class="font-medium">${data.flight_details.baggage_allowed}</span>
          </div>
         </div>
     </div>
    </div>


                        `;
                        modalContent.innerHTML = html;
                        modal.classList.remove('hidden');
                    })
                    .catch(error => {
                        console.error('Error fetching task details:', error); // Error handling
                    });
            });
        });

        closeModal.addEventListener('click', function() {
            modal.classList.add('hidden');
        });
    });
</script>