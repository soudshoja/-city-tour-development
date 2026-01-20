<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <h2 class="text-3xl font-bold">Agents List</h2>
            <div data-tooltip="Number of agents" class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $agents->total() }}</span>
            </div>
        </div>

        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>

            <a href="{{ route('users.create', ['openForm' => 'agentForm']) }}">
                <div data-tooltip-left="Create new agent" class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <x-admin-card title="agents" :companyId="request('company_id')" />

    <div>
        <div class="panel rounded-lg">
            <x-search
                :action="route('agents.index')"
                searchParam="q"
                placeholder="Quick search for agents" />

            <div class="dataTable-wrapper mt-4">
                <div class="dataTable-container h-max">
                    <table class="table-hover whitespace-nowrap dataTable-table">
                        <thead>
                            <tr class="p-3 text-left text-md font-bold text-gray-500">
                                <th>Agent Name</th>
                                <th>Amadeus (ID)</th>
                                <th>Agent Email</th>
                                <th>Agent Contact</th>
                                <th>Agent Type</th>
                                <th>Commission (%)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($agents->isEmpty())
                                <tr>
                                    <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500">
                                        No data for now.... Create new!
                                    </td>
                                </tr>
                            @else
                            @foreach ($agents as $agent)
                                <tr id="agent_row_{{ $agent->id }}" class="cursor-pointer transition-colors duration-150 p-3 text-sm font-semibold text-gray-600"
                                    onclick="window.location='{{ route('agents.show', ['id' => $agent->id]) }}'">
                                    <td>{{ $agent->name }}</td>
                                    <td>{{ $agent->amadeus_id ?? '—' }}</td>
                                    <td>{{ $agent->email ?? '—' }}</td>
                                    <td>{{ $agent->phone_number ?? '—' }}</td>
                                    <td>{{ $agent->agentType?->name ?? '—'}}</td>
                                    <td>
                                        @if($agent->type_id != 1)
                                            {{ $agent->commission * 100 }}
                                        @else
                                            Salary-based
                                        @endif
                                    </td>
                                    <td onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-center gap-2">
                                            <a data-tooltip-left="View agent" target="_blank" href="{{ route('agents.show', ['id' => $agent->id]) }}"
                                                class="inline-flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                            </a>

                                            @if($agent->type_id != 1)
                                            <div x-data="{ editAgent: false }" class="inline-flex">
                                                <button @click="editAgent = true" :data-tooltip-left="editAgent ? null : 'Edit commission'"
                                                    class="inline-flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                        <path fill="none" stroke="#00ab55" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                            d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42"
                                                            opacity=".5" />
                                                    </svg>
                                                </button>

                                                <div x-cloak x-show="editAgent" x-transition class="fixed inset-0 z-50 bg-gray-500 bg-opacity-50 flex items-center justify-center"
                                                    @click.self="editAgent = false">
                                                    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md relative" @click.stop>
                                                        <div class="flex items-center justify-between mb-6">
                                                            <div>
                                                                <h2 class="text-xl font-bold text-gray-800">Edit Agent Commission</h2>
                                                                <p class="text-gray-600 italic text-xs mt-1">Update the commission rate for {{ $agent->name }}</p>
                                                            </div>
                                                            <button @click="editAgent = false"
                                                                    class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                                &times;
                                                            </button>
                                                        </div>
                                                        <form action="{{ route('agents.update-commission', $agent->id) }}" method="POST">
                                                            @csrf
                                                            @method('PUT')
                                                            <div class="mb-4">
                                                                <label for="commission_{{ $agent->id }}" class="block text-sm font-medium text-gray-700">Commission Rate (%)</label>
                                                                <input type="number" name="commission" id="commission_{{ $agent->id }}" value="{{ $agent->commission * 100 }}"
                                                                    step="0.01" min="0" max="100"
                                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                            </div>
                                                            <div class="flex justify-end space-x-4">
                                                                <button type="button" @click="editAgent = false"
                                                                    class="rounded-full shadow-md border border-gray-200 hover:bg-gray-100 px-4 py-2 transition">
                                                                    Cancel
                                                                </button>
                                                                <button type="submit"
                                                                    class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 transition">
                                                                    Update
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <x-pagination :data="$agents" />
        </div>
    </div>
</x-app-layout>