<x-app-layout>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <style>
        .dt-length {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        #dt-search-0{
            width: 100%;
        }
    </style>
    <div class="">
        <div class="dt-permission">
            <div class="bg-white rounded-md text-center shadow-md my-2 p-4">
                <table id="newTestTable">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>Version</th>
                            <th>Description</th>
                            <th>Current Version</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($versions as $version)
                        <tr>
                            <td>{{ $version['id'] }}</td>
                            <td>{{ $version['version'] }}</td>
                            <td>{{ $version['descriptions'] }}</td>
                            <td x-data="{ openModal: false }" class="">
                                    <button type="button" class="text-blue-500 text-xs" @click="openModal = true">See All</button>
                            </td>
                            <td>
                            <a href="#" class="text-blue-500 text-xs"
                                @click.prevent="$dispatch('open-modal', { id: '{{ $version['id'] }}', version: '{{ $version['version'] }}', descriptions: '{{ $version['descriptions'] }}' })">
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
    </div>

    <div x-data="{ openModal: false, id: '', version: '', descriptions: '' }"
    x-on:open-modal.window="
        openModal = true;
        id = $event.detail.id;
        version = $event.detail.version;
        descriptions = $event.detail.descriptions;
    ">

    <!-- Modal -->
    <div x-show="openModal" class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-96">
            <h2 class="text-lg font-semibold mb-4" x-text="id ? 'Edit Version' : 'Add Version'"></h2>

            <form :action="id ? '/version/update/' + id : '{{ route('version.store') }}'" method="POST">
                @csrf
                <input type="hidden" name="id" x-model="id">

                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Version</label>
                    <input type="text" name="version" x-model="version" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>

                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="descriptions" x-model="descriptions" class="w-full p-2 border border-gray-300 rounded-md" required></textarea>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" class="btn btn-gray" @click="openModal = false">Cancel</button>
                    <button type="submit" class="btn btn-primary" x-text="id ? 'Update' : 'Save'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script>

    document.addEventListener('open-modal', (event) => {
            console.log('Event fired with data:', event.detail);
        });

        let dataTable = new DataTable("#newTestTable", {
            lengthMenu: [5, 10, 20, 50, 75, 100],
            language: {
                search: ''
            },
            layout: {
                topStart: null,
                topEnd: [
                    function() {
                        let createRoleButton = document.createElement('button');
                    createRoleButton.classList.add('btn', 'btn-primary', 'mt-auto');
                    createRoleButton.innerText = 'Add Version';
                    createRoleButton.onclick = function() {
                        window.dispatchEvent(new Event('open-modal')); // Dispatch event for Alpine.js
                    };

                        return createRoleButton;
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



        function exportTable(type) {
            let table = document.getElementById('myTable');
            let rows = table.rows;
            let data = [];

            for (let i = 0; i < rows.length; i++) {
                let row = [];
                let cols = rows[i].cells;

                for (let j = 0; j < cols.length; j++) {
                    row.push(cols[j].innerText);
                }

                data.push(row);
            }

            let a = document.createElement('a');
            let file = new Blob([JSON.stringify(data)], {
                type: 'application/json'
            });

            a.href = URL.createObjectURL(file);
            a.download = 'data.json';
            a.click();
        }

        function filterPermissions(roleId) {

            // Get the search input value
            let input = document.getElementById('searchInput_' + roleId);
            let filter = input.value.toLowerCase();

            // Get the container and all permission items
            let container = document.getElementById('permissionsContainer_' + roleId);
            let items = container.getElementsByClassName('permission-item');

            // Loop through all permission items and hide those that don't match the search query
            for (let i = 0; i < items.length; i++) {
                let span = items[i].getElementsByTagName('span')[0];
                let txtValue = span.textContent || span.innerText;
                if (txtValue.toLowerCase().indexOf(filter) > -1) {
                    items[i].style.display = "";
                } else {
                    items[i].style.display = "none";
                }
            }
        }
    </script>
</x-app-layout>