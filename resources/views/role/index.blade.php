<x-app-layout>
    <style>
        .dt-length {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
    </style>
    <div class="">
        <div class="dt-permission">
            <div class="bg-white rounded-md text-center shadow-md my-2 p-2">
                <table id="newTestTable">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Permissions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $role)
                        <tr>
                            <td>{{ $role['id'] }}</td>
                            <td>{{ $role['name'] }}</td>
                            <td>{{ $role['description'] }}</td>
                            <td x-data="{ openModal: false }" class="">
                                @foreach($role->permissions as $permission)
                                <span class="inline-flex items-center justify-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10">{{ $permission['name'] }}</span>
                                @endforeach
                                @if(count($role['permissions']) > 3)
                                <button type="button" class="text-blue-500 text-xs" @click="openModal = true">See All</button>
                                @endif

                                <!-- Modal -->
                                <div x-show="openModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-gray-400 bg-opacity-50" x-on:keydown.escape.window="openModal = false">
                                    <div class="flex items-center justify-center min-h-screen px-4">
                                        <div class="bg-white rounded-lg shadow-lg w-full max-w-md" @click.away="openModal = false">
                                            <div class="p-4 border-b flex justify-between">
                                                <h2 class="text-lg font-semibold">Permissions</h2>
                                                <button type="button" class="text-gray-500 hover:text-gray-700" @click="openModal = false">Close</button>
                                            </div>
                                            <div class="p-4">
                                                <input type="text" id="searchInput_{{ $role['id'] }}" placeholder="Search..." class="w-full mb-4 p-2 border rounded" onkeyup="filterPermissions({{ $role['id'] }})">
                                                <div id="permissionsContainer_{{ $role['id'] }}" class="h-64 overflow-y-auto">
                                                    @foreach($role['permissions'] as $permission)
                                                    <div id="" class="permission-item flex items-center justify-between border-b py-2">
                                                        <span>{{ $permission }}</span>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('role.edit', ['roleId' => $role['id']]) }}" class="text-blue-500 text-xs">
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


    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script>
        let dataTable = new DataTable("#newTestTable", {
            lengthMenu: [5, 10, 20, 50, 75, 100],
            language: {
                search: ''
            },
            layout: {
                topStart: null,
                topEnd: [
                    function() {
                        let createRoleButton = document.createElement('a');
                        createRoleButton.href = "{{ route('role.create') }}";
                        createRoleButton.classList.add('btn', 'btn-primary', 'mt-auto');
                        createRoleButton.innerText = 'Create Role';

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