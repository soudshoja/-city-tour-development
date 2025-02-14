<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin</title>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    @vite(['resources/css/app.css'])
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .dt-length {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #dt-search-0 {
            width: 100%;
        }
    </style>
</head>

<body class="font-nunito antialiased bg-gray-100">

<div class="container mx-auto p-6">
        <h2 class="text-2xl font-bold mb-6 text-center">Server Version Monitor</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Development Server -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold mb-2">Development Server</h3>
                <p class="text-gray-600">IP: <span class="font-mono text-blue-500">192.168.0.32</span></p>
                <p class="text-gray-600">Current Version: <span id="devVersion" class="font-bold text-green-600">Loading...</span></p>
                <div class="mt-4 flex space-x-2">
                    <button onclick="fetchVersion('dev', '192.168.0.32')" class="bg-blue-500 text-white px-4 py-2 rounded-lg">Refresh</button>
                    <button onclick="pullLatest('dev', '192.168.0.32')" class="bg-red-500 text-white px-4 py-2 rounded-lg">Pull Latest</button>
                </div>
            </div>

            <!-- UAT Server -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold mb-2">UAT Server</h3>
                <p class="text-gray-600">IP: <span class="font-mono text-blue-500">192.168.0.33</span></p>
                <p class="text-gray-600">Current Version: <span id="uatVersion" class="font-bold text-green-600">Loading...</span></p>
                <div class="mt-4 flex space-x-2">
                    <button onclick="fetchVersion('uat', '192.168.0.33')" class="bg-blue-500 text-white px-4 py-2 rounded-lg">Refresh</button>
                    <button onclick="pullLatest('uat', '192.168.0.33')" class="bg-red-500 text-white px-4 py-2 rounded-lg">Pull Latest</button>
                </div>
            </div>

            <!-- Production Server -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold mb-2">Production Server</h3>
                <p class="text-gray-600">Domain: <span class="font-mono text-blue-500">tour.citytravellers.com</span></p>
                <p class="text-gray-600">Current Version: <span id="prodVersion" class="font-bold text-green-600">Loading...</span></p>
                <div class="mt-4 flex space-x-2">
                    <button onclick="fetchVersion('prod', 'tour.citytravellers.com')" class="bg-blue-500 text-white px-4 py-2 rounded-lg">Refresh</button>
                    <button onclick="pullLatest('prod', 'tour.citytravellers.com')" class="bg-red-500 text-white px-4 py-2 rounded-lg">Pull Latest</button>
                </div>
            </div>

        </div>
    </div>

    <div class="container mx-auto p-4">
        <div class="bg-white shadow-md rounded-md p-4">

        <div class="d-flex">
            <!-- Add Version Button -->
            <button id="createRoleButton" class="bg-white-500 text-white px-4 py-2 rounded"></button>

            <!-- Update Current Version Button -->
            <button id="updateVersionButton" class="bg-white-500 text-white px-4 py-2 rounded"></button>
        </div>

            <table id="newTestTable" class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-200 text-left">
                            <th class="p-2">Id</th>
                            <th class="p-2">Version</th>
                            <th class="p-2">Description</th>
                            <th class="p-2">Commit</th>
                            <th class="p-2">Updated On</th>
                            <th class="p-2">Actions</th>
                        </tr>
                    </thead>    
                    <tbody>
                        @foreach($versions as $version)
                        <tr class="border-t">
                            <td class="p-2">{{ $version['id'] }}</td>
                            <td class="p-2">{{ $version['version'] }}</td>
                            <td class="p-2">{{ $version['descriptions'] }}</td>
                            <td class="p-2">{{ $version['sha'] }}</td>
                            <td class="p-2">{{ $version['updated_at'] }}</td>
                            <td class="p-2">
                                <a href="#" class="text-blue-500 text-xs" onclick="openModalWithData(event, '{{ $version['id'] }}', '{{ $version['version'] }}', '{{ $version['descriptions'] }}', '{{ $version['sha'] }}')">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500 bg-green-100 border border-green-500 rounded" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828zM6 12v2H4v2h2v2h2v-2h2v-2H8v-2H6z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
    </div>

    <div class="CityDisplaayNone  p-6 pt-5 text-center dark:text-[#f3f4f6] bg-white dark:bg-gray-900">
    © <span id="footer-year">2024</span> city tour.  <span id="footer-version">Version 1.0</span>
    <p id="rootUrl"></p>
    </div>

    <div x-data="{ 
    openModal: false, id: '', version: '', descriptions: '', sha:'',
    openUpdateModal: false, currentVersion: '', currentVersionId: ''
}"
    x-on:open-modal.window="
        openModal = true;
        id = $event.detail.id;
        version = $event.detail.version;
        descriptions = $event.detail.descriptions;
        sha = $event.detail.sha;
    "
    x-on:open-update-modal.window="
        openUpdateModal = true;
        currentVersion = $event.detail.version;
        currentVersionId = $event.detail.id;
    "
>

    <!-- Add / Edit Version Modal -->
    <div x-show="openModal" class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-96">
            <h2 class="text-lg font-semibold mb-4" x-text="id ? 'Edit Version' : 'Add Version'"></h2>

            <form :action="id ? '/version/update/' + id : '{{ route('version.store') }}'" method="POST">
                @csrf
                <input type="hidden" name="id" x-model="id">

                <template x-if="id">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Version</label>
                    <input type="text" name="version" x-model="version" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="descriptions" x-model="descriptions" class="w-full p-2 border border-gray-300 rounded-md" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Commit</label>
                    <input type="text" name="sha" x-model="sha" class="w-full p-2 border border-gray-300 rounded-md">
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn btn-gray" @click="openModal = false">Cancel</button>
                    <button type="submit" class="btn btn-primary" x-text="id ? 'Update' : 'Save'"></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Current Version Modal -->
    <div x-show="openUpdateModal" class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50">
        <div class="bg-white p-6 rounded-md w-96">
            <h2 class="text-lg font-bold mb-4">Update Current Version</h2>
            <form action="{{ route('version.current') }}" method="POST">
                @csrf
                @method('POST')

                <input type="hidden" name="id" x-model="currentVersionId">

                <label class="block">Version</label>
                <input type="text" name="version" x-model="currentVersion" class="w-full border p-2 rounded" required>

                <div class="flex justify-end mt-4">
                    <button type="button" @click="openUpdateModal = false" class="btn btn-gray">Cancel</button>
                    <button type="submit" class="btn btn-secondary ml-2">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script>

    async function fetchVersions() {
            try {
                const response = await fetch('/monitor-versions');
                const data = await response.json();
                console.log(data);

            } catch (error) {
   
            }

            }

            fetchVersions();

    // Add Version Button Click Event
    document.getElementById("createRoleButton").addEventListener("click", function () {
                window.dispatchEvent(new Event('open-modal'));
            });

        // Update Current Version Button Click Event
        document.getElementById("updateVersionButton").addEventListener("click", function () {
            fetch("{{ route('version.getCurrent') }}")
                .then(response => response.json())
                .then(data => {
                    if (data && data.value) {
                        window.dispatchEvent(new CustomEvent('open-update-modal', {
                            detail: { version: data.value, id: data.id }
                        }));
                    }
                })
                .catch(error => console.error("Error fetching version:", error));
        });

        let dataTable = new DataTable("#newTestTable", {
            lengthMenu: [10, 20, 50],
            language: {
                search: ''
            },
            layout: {
                topStart: null,
                topEnd: [
                    function() {
                        let createRoleButton = document.createElement('button');
                    createRoleButton.classList.add('bg-blue-500', 'text-white', 'px-4', 'py-2', 'rounded');
                    createRoleButton.innerText = 'Add Version';
                    createRoleButton.onclick = function() {
                        window.dispatchEvent(new Event('open-modal')); // Dispatch event for Alpine.js
                    };

                        return createRoleButton;
                    },
                    function() {
                        let updateVersionButton = document.createElement('button');
                        updateVersionButton.classList.add('bg-green-500',  'text-white', 'px-4', 'py-2', 'rounded');
                        updateVersionButton.innerText = 'Update Current Version';

                        updateVersionButton.onclick = function() {
                            // Fetch the latest version from the server dynamically (replace this with actual logic)
                            fetch("{{ route('version.getCurrent') }}")
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.value) { // Assuming `value` holds the version
                                    window.dispatchEvent(new CustomEvent('open-update-modal', {
                                        detail: { version: data.value, id: data.id }
                                    }));
                                }
                            })
                            .catch(error => console.error("Error fetching version:", error));
                        };

                        return updateVersionButton;
                    },
                    {

                        features: {
                            search: {

                                placeholder: 'Search...'
                            },
                        }
                    }
                ],
                bottomStart: 'pageLength',
                bottom2Start: 'info'
            }
        });

        function openModalWithData(event, id, version, descriptions, sha) {
            event.preventDefault();

            window.dispatchEvent(new CustomEvent('open-modal', {
                detail: { id, version, descriptions, sha }
            }));
         }

        fetch("{{ route('version.getCurrent') }}")
        .then(response => response.json())
        .then(data => {
            if (data && data.value) { 
                // Assuming `value` holds the version, update the version dynamically
                const versionElement = document.getElementById('footer-version');
                if (versionElement) {
                    versionElement.textContent = `Version ${data.value}`;
                }
            }
        })
        .catch(error => console.error("Error fetching version:", error));

        function getRootUrl() {
            return window.location.origin;
        }

        // Display it on the page
        document.getElementById("rootUrl").innerText = getRootUrl();
        
    </script>
</html>