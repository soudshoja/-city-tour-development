<x-app-layout>
    <ul class="flex space-x-2 rtl:space-x-reverse">
        <li>
            <a href="{{ route('dashboard') }}" class="text-primary hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] ltr:before:mr-1 rtl:before:ml-1">
            <span>Tasks List</span>
        </li>
    </ul>

    <div class="mt-5 panel">

        <div class="flex mb-5">
           <p>Click <a href="{{ route('download.tasks') }}" class="text-primary">here</a> to download the Excel template</p>
        </div>
        <!-- Flex container for buttons and search input, with responsive handling for mobile -->
        <div class="mb-5 flex flex-col md:flex-row justify-between items-center w-full space-y-4 md:space-y-0">

            <!-- Buttons on the left -->
            <div class="flex space-x-2">
                    <x-primary-button id="uploadExcelBtn">Upload Excel</x-primary-button>
                    <input type="file" id="excelFileInput" class="hidden" name="excelFile" accept=".xlsx, .xls">
                    <x-primary-button  id="printPage" onclick="printPage()">PRINT</x-primary-button>
                    <x-primary-button onclick="window.location='{{ route('tasks.exportCsv') }}'">Export CSV</x-primary-button>
            </div>

            <!-- Search input on the right -->
            <div class="w-full md:w-auto">
                <input type="text" placeholder="Search..."
                    class="w-full md:w-auto pr-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder:text-gray-500" />
            </div>
        </div>
    </div>

    <div id="printableArea" class="mt-5 panel">
        <div class="overflow-x-auto">
         <div class="space-y-4">
                @foreach ($trips as $trip)
                    <div class="border rounded-lg overflow-hidden">
                        <button class="w-full text-left px-4 py-2 bg-gray-200 font-semibold" 
                                onclick="toggleTrip('trip-{{ $trip->id }}')">
                            {{ $trip->trip_name }} ({{ $trip->tasks->count() }} Tasks)
                        </button>

                        <!-- Trip's Tasks (hidden by default) -->
                        <div id="trip-{{ $trip->id }}" class="hidden p-4">
                            <table class="min-w-full table-auto border-collapse">
                                <thead>
                                    <tr>
                                        <th class="border px-4 py-2">Task Name</th>
                                        <th class="border px-4 py-2">Agent Name</th>
                                        <th class="border px-4 py-2">Company Name</th>
                                        <th class="border px-4 py-2">Client Name</th>
                                        <th class="border px-4 py-2">Status</th>
                                        <th class="border px-4 py-2">Task Date</th>
                                        <th class="border px-4 py-2">Delay (Days)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trip->tasks as $task)
                                    @php
                                        // Calculate the delay in rounded days
                                        $delay = round(\Carbon\Carbon::parse($task->created_at)->diffInDays(now()));
                                    @endphp
                                        <tr>
                                            <td class="border px-4 py-2">{{ $task->description }}</td>
                                            <td class="border px-4 py-2">{{ $task->agent->name }}</td>
                                            <td class="border px-4 py-2">{{ $task->agent->company->name ?? 'No company' }}</td>
                                            <td class="border px-4 py-2">{{ $task->client->name ?? 'No client' }}</td>
                                            <td class="border px-4 py-2">{{ $task->status }}</td>
                                            <td class="border px-4 py-2">{{ $task->created_at->format('Y-m-d') }}</td>
                                            <td class="border px-4 py-2 
                                                @if ($delay > 3) text-red-600 font-semibold @endif">
                                                {{ $delay }} days
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>


    <script>
    // Upload Excel functionality
    document.getElementById('uploadExcelBtn').addEventListener('click', function (event) {
        event.preventDefault();
        document.getElementById('excelFileInput').click(); // Trigger the file input click
    });

    // When a file is selected, submit via AJAX (or other method)
    document.getElementById('excelFileInput').addEventListener('change', function () {
        let file = this.files[0];
        if (file) {
            let formData = new FormData();
            formData.append('excel_file', file);

             // Show the loading spinner
             document.getElementById('loadingSpinner').classList.remove('hidden');
             document.getElementById('statusMessage').classList.add('hidden'); // Hide previous messages


            // Use fetch or Axios to send the file via AJAX to the backend
            fetch("{{ route('agentsupload.import') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}", // Include CSRF token for security
                },
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                // Hide the loading spinner
                document.getElementById('loadingSpinner').classList.add('hidden');

                // Show success message
                document.getElementById('statusMessage').classList.remove('hidden');
                document.getElementById('statusMessage').innerHTML = `<p class="text-green-600">File uploaded successfully!</p>`;
  
                alert('File uploaded successfully!');

                      // Refresh the page after a short delay
            setTimeout(() => {
                location.reload();
            }, 1000); // Adjust the delay as needed (2000 ms = 2 seconds)

            })
            .catch(error => {

                 // Hide the loading spinner
                 document.getElementById('loadingSpinner').classList.add('hidden');

                // Show error message
                document.getElementById('statusMessage').classList.remove('hidden');
                document.getElementById('statusMessage').innerHTML = `<p class="text-red-600">Error uploading file: ${error.message}</p>`;

                console.error('Error uploading file:', error);

                      // Refresh the page after a short delay
            setTimeout(() => {
                location.reload();
            }, 1000); // Adjust the delay as needed (2000 ms = 2 seconds)
            });
        }
    });


</script>

<script>
function printPage() {
    // Show the printable area temporarily
    var printableArea = document.getElementById('printableArea');
    printableArea.classList.remove('hidden');

    // Open a new window for printing
    var printWindow = window.open('', '_blank');

    // Get the content you want to print
    var content = printableArea.innerHTML;

    // Create the new document and write the content to it
    printWindow.document.write(`
        <html>
            <head>
                <title>Print</title>
                <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
            </head>
            <body>
                <div class="p-4">
                    ${content}
                </div>
            </body>
        </html>
    `);

    // Close the document for printing
    printWindow.document.close();

    // Wait for the content to be fully loaded
    printWindow.onload = function() {
        printWindow.print();
        printWindow.close();
    };

    // Hide the printable area again after the printing
    printableArea.classList.add('hidden');
}

</script>
 <script>
        function toggleTrip(id) {
            var element = document.getElementById(id);
            if (element.classList.contains('hidden')) {
                element.classList.remove('hidden');
            } else {
                element.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>