<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <h2 class="text-3xl font-bold">Roles Management</h2>
            <div data-tooltip="Number of roles"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $roles->count() }}</span>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload"
                class="refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>

            @can('create', App\Models\Role::class)
            <a href="{{ route('role.create') }}">
                <div data-tooltip-left="Create new role"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
            @endcan
        </div>
    </div>
    <div class="panel rounded-lg">
        <div class="dataTable-wrapper">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-center text-md font-bold text-gray-500">
                            <th>Name</th>
                            <th>Description</th>
                            <th>Permissions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($roles->isEmpty())
                            <tr>
                                <td colspan="4" class="text-center p-6 text-sm font-semibold text-gray-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        <span>No roles found. Create a new role!</span>
                                    </div>
                                </td>
                            </tr>
                        @else
                            @foreach($roles as $role)
                                <tr class="text-sm font-semibold text-gray-600 text-center">
                                    <td>{{ $role->name }}</td>
                                    <td>
                                        {{ $role->description ?? '-' }}
                                    </td>
                                    <td x-data="{ openModal: false }">
                                        <div class="flex flex-wrap gap-1 items-center justify-center">
                                            @foreach($role->permissions->take(3) as $permission)
                                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                    {{ $permission->name }}
                                                </span>
                                            @endforeach
                                            @if(count($role->permissions) > 3)
                                                <button type="button" class="text-sky-600 hover:text-sky-800 text-xs font-medium ml-1" @click="openModal = true">
                                                    +{{ count($role->permissions) - 3 }} more
                                                </button>
                                            @elseif(count($role->permissions) == 0)
                                                <span class="text-gray-400 text-xs">No permissions</span>
                                            @endif

                                            <div x-show="openModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-sm" 
                                                x-on:keydown.escape.window="openModal = false"
                                                x-transition:enter="transition ease-out duration-200"
                                                x-transition:enter-start="opacity-0"
                                                x-transition:enter-end="opacity-100"
                                                x-transition:leave="transition ease-in duration-150"
                                                x-transition:leave-start="opacity-100"
                                                x-transition:leave-end="opacity-0">
                                                <div class="flex items-center justify-center min-h-screen px-4 py-6">
                                                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md" 
                                                        @click.away="openModal = false"
                                                        x-transition:enter="transition ease-out duration-200"
                                                        x-transition:enter-start="opacity-0 scale-95"
                                                        x-transition:enter-end="opacity-100 scale-100">
                                                        <div class="p-4 border-b flex justify-between items-center">
                                                            <h2 class="text-lg font-semibold text-gray-800">
                                                                {{ $role->name }} - Permissions ({{ count($role->permissions) }})
                                                            </h2>
                                                            <button type="button" 
                                                                class="text-gray-400 hover:text-gray-600 transition-colors" 
                                                                @click="openModal = false">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <div class="p-4">
                                                            <input type="text" 
                                                                id="searchInput_{{ $role->id }}" 
                                                                placeholder="Search permissions..." 
                                                                class="w-full mb-4 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                                onkeyup="filterPermissions({{ $role->id }})">
                                                            <div id="permissionsContainer_{{ $role->id }}" class="h-64 overflow-y-auto space-y-1">
                                                                @foreach($role->permissions as $permission)
                                                                    <div class="permission-item flex items-center px-3 py-2 rounded-lg hover:bg-gray-50">
                                                                        <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                            {{ $permission->name }}
                                                                        </span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-center gap-1">
                                            @can('update', App\Models\Role::class)
                                            <a href="{{ route('role.edit', ['roleId' => $role->id]) }}" 
                                                data-tooltip-left="Edit role"
                                                class="p-2 rounded-lg hover:bg-green-50 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                    <path fill="none" stroke="#00ab55" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                        d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42" />
                                                </svg>
                                            </a>
                                            @endcan
                                            @can('delete', App\Models\Role::class)
                                            <div x-data="{ showConfirm: false }">
                                                <button type="button" @click="showConfirm = true" data-tooltip-left="Delete role"
                                                    class="p-2 rounded-lg hover:bg-red-50 transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                        <path fill="none" stroke="#ef4444" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                            d="M9.17 4a3.001 3.001 0 0 1 5.66 0m5.67 2h-17m15.333 2.5l-.46 6.9c-.177 2.654-.265 3.981-1.13 4.79c-.865.81-2.195.81-4.856.81h-.774c-2.66 0-3.99 0-4.856-.81c-.865-.809-.953-2.136-1.13-4.79l-.46-6.9M9.5 11l.5 5m4.5-5l-.5 5" />
                                                    </svg>
                                                </button>
                                                <template x-teleport="body">
                                                    <div x-show="showConfirm" x-cloak x-transition.opacity
                                                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-40 backdrop-blur-sm z-50">
                                                        <div x-transition.scale @click.away="showConfirm = false" class="bg-white rounded-xl shadow-xl px-8 py-8 w-full max-w-sm text-center">
                                                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Delete Role?</h2>
                                                            <div class="border-t border-gray-200 mb-6"></div>
                                                            <p class="text-base font-medium text-gray-600 leading-snug mb-6">
                                                                Are you sure you want to delete this role?
                                                                <br>
                                                                <span class="block text-sm text-gray-500 leading-snug mt-1">
                                                                    This action cannot be undone. Users assigned to this role may lose access.
                                                                </span>
                                                            </p>
                                                            <div class="border-t border-gray-200 mb-6"></div>
                                                            <div class="flex justify-center gap-4">
                                                                <button @click="showConfirm = false" class="px-6 py-2.5 rounded-full font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 transition">
                                                                    Cancel
                                                                </button>
                                                                <form action="{{ route('role.destroy', ['roleId' => $role->id]) }}" method="POST">
                                                                    @csrf 
                                                                    @method('DELETE')
                                                                    <button type="submit" class="px-6 py-2.5 rounded-full font-medium text-white bg-red-600 hover:bg-red-700 transition">
                                                                        Yes, Delete
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function filterPermissions(roleId) {
            const input = document.getElementById('searchInput_' + roleId);
            const filter = input.value.toLowerCase();
            const container = document.getElementById('permissionsContainer_' + roleId);
            const items = container.getElementsByClassName('permission-item');

            // Loop through all permission items and hide those that don't match the search query
            for (let i = 0; i < items.length; i++) {
                const span = items[i].getElementsByTagName('span')[0];
                const txtValue = span.textContent || span.innerText;
                if (txtValue.toLowerCase().indexOf(filter) > -1) {
                    items[i].style.display = '';
                } else {
                    items[i].style.display = 'none';
                }
            }
        }
    </script>
</x-app-layout>