<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <h2 class="text-3xl font-bold">Users</h2>
            <div data-tooltip="Total users"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $users->total() }}</span>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload"
                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm cursor-pointer"
                onclick="window.location.reload()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>
            <a href="{{ route('users.create') }}">
                <div data-tooltip-left="Create new user"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <x-admin-card title="users" :companyId="request('company_id')" />

    <div class="panel rounded-lg">
        <x-search
            :action="route('users.index')"
            searchParam="q"
            placeholder="Quick search for user" />

        <div class="dataTable-wrapper mt-4">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500">
                            <th>Name</th>
                            <th>Email</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $userItem)
                        <tr class="transition-colors duration-150 cursor-pointer p-3 text-sm font-semibold text-gray-600">
                            <td>
                                {{ $userItem->name }}
                            </td>
                            <td>
                                {{ $userItem->email }}
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-2 flex-wrap">
                                    @foreach($userItem->getRoleNames() as $role)
                                    <span class="whitespace-nowrap px-2 py-1 rounded text-xs font-medium border
                                        {{ $role === 'admin' ? 'border-koromiko-300 text-koromiko-500 bg-koromiko-50' : '' }}
                                        {{ $role === 'company' ? 'border-blue-500 text-blue-500 bg-blue-50' : '' }}
                                        {{ $role === 'branch' ? 'border-amber-700 text-amber-700 bg-amber-50' : '' }}
                                        {{ $role === 'agent' ? 'border-purple-500 text-purple-500 bg-purple-50' : '' }}
                                        {{ $role === 'accountant' ? 'border-red-500 text-red-500 bg-red-50' : '' }}
                                        {{ !in_array($role, ['admin', 'company', 'branch', 'agent', 'accountant']) ? 'border-gray-500 text-gray-500 bg-gray-50' : '' }}">
                                        {{ ucfirst($role) }}
                                    </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <a data-tooltip-left="Edit user"
                                        href="{{ route('users.edit', $userItem->id) }}"
                                        class="text-sm font-medium text-blue-600 hover:underline">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                            <path fill="none" stroke="#00ab55" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42"
                                                opacity=".5" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center p-3 text-gray-500 dark:text-gray-300 font-semibold text-gray-600">
                                No users found
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :data="$users" />
        </div>
    </div>
</x-app-layout>