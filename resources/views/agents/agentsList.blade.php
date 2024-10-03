<x-app-layout>
    <div x-data="exportTable">
        <ul class="flex space-x-2 rtl:space-x-reverse">
            <li>
                <a href="{{ route('dashboard') }}" class="text-primary hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-1 rtl:before:ml-1">
                <span>Agents List</span>
            </li>
        </ul>


        <div class="mt-5 panel">

            <div class="flex mb-5">
            <p>Click <a href="{{ route('download.agent') }}" class="text-primary">here</a> to download the Excel template</p>
            </div>
            <!-- Flex container for buttons and search input, with responsive handling for mobile -->
            <div class="mb-5 flex flex-col md:flex-row justify-between items-center w-full space-y-4 md:space-y-0">

                <!-- Buttons on the left -->
                <div class="flex space-x-2">
                    <x-primary-button id="uploadExcelBtn">Upload Excel</x-primary-button>
                    <input type="file" id="excelFileInput" class="hidden" name="excelFile" accept=".xlsx, .xls">
                    <x-primary-button  id="printPage" onclick="printPage()">PRINT</x-primary-button>
                    <x-primary-button onclick="window.location='{{ route('agents.exportCsv') }}'">Export CSV</x-primary-button>
                </div>
            <!-- Loading Spinner --> 
            <div id="loadingSpinner" class="hidden mt-4 flex justify-center items-center">
                <span class="mr-2">Uploading...</span>
                <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>

            <!-- Status Message -->
            <div id="statusMessage" class="hidden mt-4"></div>

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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Company</th>
                            <th>Type</th>
                            <th>Actions</th>

                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agents as $agent)
                        <tr>
                            <td>{{ $agent->name }}</td>
                            <td>{{ $agent->email }}</td>
                            <td>{{ $agent->phone_number }}</td>
                            <td>{{ $agent->company->name ?? 'N/A' }}</td>
                            <td>{{ $agent->type }}</td>
                            <td class="flex">

                                <a href="{{ route('agentsshow.show', $agent->id) }}}">
                                    <button type="button"
                                        class="text-white bg-gradient-to-r from-teal-400 via-teal-500 to-teal-600 focus:ring-4 focus:outline-none focus:ring-teal-300 dark:focus:ring-teal-800 shadow-lg shadow-teal-500/50 dark:shadow-lg dark:shadow-teal-800/80 font-medium rounded-lg text-xs px-3 py-1.5 text-center me-2 mb-2">
                                        Agent Details
                                    </button>
                                </a>
                            </td>

                        </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <div id="printableArea" class="hidden">
    <!-- Place your content here that you want to print -->
    <h1 class="text-2xl font-bold">Agent Details</h1>
    <table class="min-w-full mt-4">
        <!-- Table Headers -->
        <thead>
            <tr>
            <th  class="py-2 px-4 border">Name</th>
            <th  class="py-2 px-4 border">Email</th>
            <th  class="py-2 px-4 border">Phone Number</th>
            <th  class="py-2 px-4 border">Company Name</th>
            <th  class="py-2 px-4 border">Type</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($agents as $agent)
                <tr>
                <td  class="py-2 px-4 border">{{ $agent->name }}</td>
                <td  class="py-2 px-4 border">{{ $agent->email }}</td>
                <td  class="py-2 px-4 border">{{ $agent->phone_number }}</td>
                <td  class="py-2 px-4 border">{{ $agent->company->name ?? 'N/A' }}</td>
                <td  class="py-2 px-4 border">{{ $agent->type }}</td>
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