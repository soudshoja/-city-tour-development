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

    <div class="mt-5 panel">
        <div class="overflow-x-auto">
            <table class="CityMobileTable table-fixed">
                <thead>
                    <tr>
                       <th>Agent Name</th>
                        <th>Agent Email</th>
                        <th>Task</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>
                </thead>
                <tbody>
                    @foreach($tasks as $task)
                    <tr>
                        <td>{{ $task->agent->name }}</td>
                        <td>{{ $task->agent_email }}</td>
                        <td>{{ $task->description }}</td>
                        <td>{{ $task->task_type }}</td>
                        <td class="py-3 px-4">
                            @if($task->status == 'completed')
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm">Completed</span>
                            @elseif($task->status == 'pending')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">Pending</span>
                            @elseif($task->status == 'inprogress')
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">In Progress</span>
                            @else
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-sm">Overdue</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <a href="#" class="ml-4 text-red-600 hover:text-red-900">Delete</a>
                        </td>
                    </tr>
                    @endforeach

                </tbody>
            </table>
        </div>

    </div>
    <div id="printableArea" class="hidden">
    <!-- Place your content here that you want to print -->
    <h1 class="text-2xl font-bold">Agent Details</h1>
    <table class="min-w-full mt-4">
        <!-- Table Headers -->
        <thead>
            <tr>
            <th  class="py-2 px-4 border">Agent Name</th>
            <th  class="py-2 px-4 border">Agent Email</th>
            <th  class="py-2 px-4 border">Task Description</th>
            <th  class="py-2 px-4 border">Status</th>
            <th  class="py-2 px-4 border">Task Type</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tasks as $task)
                <tr>
                <td  class="py-2 px-4 border">{{ $task->agent->name  }}</td>
                <td  class="py-2 px-4 border">{{ $task->agent->email }}</td>
                <td  class="py-2 px-4 border">{{ $task->description  }}</td>
                <td  class="py-2 px-4 border">{{ $task->status }}</td>
                <td  class="py-2 px-4 border">{{ $task->task_type }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
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
</x-app-layout>