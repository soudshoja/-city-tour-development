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
        .spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    animation: spin 1s linear infinite;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: none; /* Hide by default */
}

.spinner:not(.hidden) {
    display: block; /* Show when not hidden */
}

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Add custom styling for buttons */
        .button {
            background-color: #3498db;
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 18px;
            width: 220px;
            text-align: center;
            margin: 15px;
            cursor: pointer;
            position: relative; /* Position the spinner inside the button */
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #2980b9;
        }

        .status {
            font-size: 18px;
            margin-top: 20px;
        }

        .button .text {
            visibility: visible;
        }

        .button.loading .text {
            visibility: hidden;
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
                <p class="text-gray-600">Commit: <span id="devSha" class="font-bold text-green-600">Loading...</span></p>
                <div class="mt-4 flex space-x-2">
                    <button onclick="fetchAllVersions()" class="bg-blue-500 text-white px-4 py-2 rounded-lg">Refresh</button>
                    <button onclick="triggerJenkinsJob('city_tour_dev_no_pipeline')" class="bg-red-500 text-white px-4 py-2 rounded-lg" id="devButton">
                        <div class="spinner hidden" id="devSpinner"></div>
                        <span class="text">Pull to Dev</span>
                    </button>
                </div>
            </div>

            <!-- UAT Server -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold mb-2">UAT Server</h3>
                <p class="text-gray-600">IP: <span class="font-mono text-blue-500">192.168.0.33</span></p>
                <p class="text-gray-600">Current Version: <span id="uatVersion" class="font-bold text-green-600">Loading...</span></p>
                <p class="text-gray-600">Commit: <span id="uatSha" class="font-bold text-green-600">Loading...</span></p>
                <div class="mt-4 flex space-x-2">
                    <button onclick="fetchAllVersions()" class="bg-blue-500 text-white px-4 py-2 rounded-lg">Refresh</button>
                    <button onclick="triggerJenkinsJob('UAT publish')" class="bg-red-500 text-white px-4 py-2 rounded-lg" id="uatButton">
                    <div class="spinner hidden" id="uatSpinner"></div>
                    <span class="text">Pull to UAT</span>
                    </button>
                </div>
            </div>

            <!-- Production Server -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold mb-2">Production Server</h3>
                <p class="text-gray-600">Domain: <span class="font-mono text-blue-500">tour.citytravellers.co</span></p>
                <p class="text-gray-600">Current Version: <span id="prodVersion" class="font-bold text-green-600">Loading...</span></p>
                <p class="text-gray-600">Commit: <span id="prodSha" class="font-bold text-green-600">Loading...</span></p>
                <div class="mt-4 flex space-x-2">
                    <button onclick="fetchAllVersions()" class="bg-blue-500 text-white px-4 py-2 rounded-lg">Refresh</button>
                    <button onclick="pullLatest('prod', 'tour.citytravellers.com')" class="bg-red-500 text-white px-4 py-2 rounded-lg">Pull Latest</button>
                </div>
            </div>
            <p id="status" class="status text-gray-700"></p>
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
     let versions = @json($versions);

     async function fetchAllVersions() {
            const url = "/monitor-versions"; // Laravel API to get all versions

            try {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error("Failed to fetch versions");
                }

                const data = await response.json();
                console.log('data1', data)
                updateVersionDisplay(data);
            } catch (error) {
                console.error("Error fetching versions:", error);
                document.getElementById("errorMsg").innerText = "Error fetching versions";
            }
        }

        
        async function updateVersionDisplay(data) {
                try {
                    const response = await fetch("{{ route('version.getCurrent') }}");
                    const versionData = await response.json();

                    console.log('versionData', versionData);

                    if (versionData && versionData.value) {
                        let currentVersion = versionData.value;

                        for (const server in data) {
                            let commit = data[server].commit || "Unknown";
                            let description = data[server].message || "No Descriptions";
                            let versionInfo = versions.find(v => v.sha === commit);

                            if (!versionInfo) {
                                // If version not found, generate next version
                                let newVersion = getNextVersion(currentVersion);

                                await updateMasterVersion(newVersion); // Update Master table
                                await autoAddVersion(newVersion, commit, description);

                                versionInfo = { version: newVersion, sha: commit };
                                versions.push(versionInfo); // Update local list

                                currentVersion = newVersion;
                            }

                            let version = versionInfo.version;
                            let sha = commit;

                            console.log(`Server: ${server}, Version: ${version}, SHA: ${sha}`);
                            document.getElementById(`${server}Version`).innerText = version;
                            document.getElementById(`${server}Sha`).innerText = sha;
                        }
                    }
                } catch (error) {
                    console.error("Error fetching current version:", error);
                }
            }

        function getNextVersion(currentVersion) {
            let parts = currentVersion.split(".");
            let main = parseInt(parts[0], 10);
            let sub = parseInt(parts[1], 10) + 1; // Increment subversion

            return `${main}.${sub.toString().padStart(3, '0')}`;
        }

        async function autoAddVersion(version, sha, description) {
                const versionStoreUrl = "{{ route('version.store') }}"; 
                const csrfToken = "{{ csrf_token() }}";

                try {
                    const response = await fetch(versionStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            version: version,
                            sha: sha,
                            descriptions: description
                        }),
                    });

                    if (!response.ok) {
                        throw new Error(`Failed to auto-add version: ${version}`);
                    }

                    const result = await response.json();
                    console.log("Version added successfully:", result);
                } catch (error) {
                    console.error("Error adding version:", error);
                }
            }
 
        async function updateMasterVersion(newVersion) {
                const masterUpdateUrl = "{{ route('version.updateMaster') }}"; 
                const csrfToken = "{{ csrf_token() }}";

                try {
                    const response = await fetch(masterUpdateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            value: newVersion,
                        }),
                    });

                    if (!response.ok) {
                        throw new Error(`Failed to update master version to: ${version}`);
                    }

                    const result = await response.json();
                    console.log("Master version updated successfully:", result);
                } catch (error) {
                    console.error("Error updating master version:", error);
                }
            }


            fetchAllVersions();


            function triggerJenkinsJob(jobName) {
                    console.log(`Triggering job: ${jobName}`);

                    // Show the spinner and update the status text to "Deploying..."
                    const button = document.getElementById(`${jobName === 'city_tour_dev_no_pipeline' ? 'dev' : 'uat'}Button`);
                    
                    const spinner = button.querySelector('.spinner');
                    const text = button.querySelector('.text');

                    // Set loading state to true
                    button.classList.add('loading');
                    spinner.classList.remove('hidden'); // Show the spinner
                    text.classList.add('hidden');
                    document.getElementById('status').innerText = 'Deploying... Please wait.';

                    const jenkinsUrl = "http://192.168.0.32:8080";
                    const jobUrl = `${jenkinsUrl}/job/${encodeURIComponent(jobName)}/build`;

                    const username = "admin";  // Replace with your Jenkins username
                    const apiToken = "1182ebdb5d0e5bd269a11afd66472ffac4";  // Replace with your real API token

                    // Get CSRF crumb first
                    fetch(`${jenkinsUrl}/crumbIssuer/api/json`, {
                        method: "GET",
                        headers: {
                            "Authorization": "Basic " + btoa(username + ":" + apiToken)
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        const crumb = data.crumb;
                        console.log(`Crumb received: ${crumb}`);

                        // Now trigger the Jenkins job
                        return fetch(jobUrl, {
                            method: "POST",
                            headers: {
                                "Authorization": "Basic " + btoa(username + ":" + apiToken),
                                "Jenkins-Crumb": crumb  // Add the crumb to avoid CSRF error
                            }
                        });
                    })
                    .then(response =>    {
                        if (response.ok) {
                            document.getElementById("status").innerText = `✅ Job ${jobName} triggered successfully!`;
                        } else {
                            document.getElementById("status").innerText = `❌ Failed to trigger job ${jobName}`;
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        document.getElementById("status").innerText = `❌ Failed to trigger job ${jobName}`;
                    })
                    .finally(() => {
                        // Hide the spinner and show the button text after the job trigger process is complete (either success or failure)
                        button.classList.remove('loading');
                        spinner.classList.add('hidden'); // Hide the spinner
                        text.classList.remove('hidden');
                    });
                }



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