<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3 ">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Agents List</h2>

            <div data-tooltip="number of Agents" class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $agentCount }}</span>
            </div>
        </div>

        <div class="flex items-center gap-5">
            <div data-tooltip="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>

            <a href="{{ route('users.create') }}?openForm=agentForm">
                <div data-tooltip="Create new Agent" class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>


        </div>


    </div>

    <div class="tableCon">
        <div class="content-70">
            <!-- Table  -->
            <div class="panel oxShadow rounded-lg">

                <x-search action="{{ route('agents.index') }}" />

                <!-- ./search icon -->
                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <!-- table -->
                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <!-- <th>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" id="selectAll" class="form-checkbox hidden">
                                            <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </th> -->
                                    <!-- <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th> -->
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Agent Name</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Amadeus (ID)</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Agent Email</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Agent Contact</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Agent Type</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Commission (%)</th>
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
                                <tr id="agent_row_{{ $agent->id }}"> <!-- Ensure each row has a unique ID -->
                                    <!-- Toggle Switch Column -->
                                    <td class="p-3 text-sm flex items-center gap-3">
                                        <label class="w-12 h-6 relative">
                                            <input type="checkbox" class="custom_switch absolute w-full h-full opacity-0 z-10 cursor-pointer peer"
                                                id="agent_toggle_{{ $agent->id }}"
                                                data-agent-id="{{ $agent->id }}"
                                                onchange="toggleAgentStatus(this)"
                                                checked />
                                            <span class="bg-[#ebedf2] dark:bg-dark block h-full rounded-full 
                                                before:absolute before:left-1 before:bg-white dark:before:bg-white-dark 
                                                dark:peer-checked:before:bg-white before:bottom-1 before:w-4 before:h-4 before:rounded-full 
                                                peer-checked:before:left-7 peer-checked:bg-primary before:transition-all before:duration-300">
                                            </span>
                                        </label>
                                        @if($agent->type_id != 1)
                                        <div x-data="{ editAgent: false }" class="inline">
                                            <button @click="editAgent = true" class="hover:text-primary text-gray-500 dark:text-gray-400 transition duration-200">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                                        d="M11.9426 1.25L13.5 1.25C13.9142 1.25 14.25 1.58579 14.25 2C14.25 2.41421 13.9142 2.75 13.5 2.75H12C9.62177 2.75 7.91356 2.75159 6.61358 2.92637C5.33517 3.09825 4.56445 3.42514 3.9948 3.9948C3.42514 4.56445 3.09825 5.33517 2.92637 6.61358C2.75159 7.91356 2.75 9.62177 2.75 12C2.75 14.3782 2.75159 16.0864 2.92637 17.3864C3.09825 18.6648 3.42514 19.4355 3.9948 20.0052C4.56445 20.5749 5.33517 20.9018 6.61358 21.0736C7.91356 21.2484 9.62177 21.25 12 21.25C14.3782 21.25 16.0864 21.2484 17.3864 21.0736C18.6648 20.9018 19.4355 20.5749 20.0052 20.0052C20.5749 19.4355 20.9018 18.6648 21.0736 17.3864C21.2484 16.0864 21.25 14.3782 21.25 12V10.5C21.25 10.0858 21.5858 9.75 22 9.75C22.4142 9.75 22.75 10.0858 22.75 10.5V12.0574C22.75 14.3658 22.75 16.1748 22.5603 17.5863C22.366 19.031 21.9607 20.1711 21.0659 21.0659C20.1711 21.9607 19.031 22.366 17.5863 22.5603C16.1748 22.75 14.3658 22.75 12.0574 22.75H11.9426C9.63423 22.75 7.82519 22.75 6.41371 22.5603C4.96897 22.366 3.82895 21.9607 2.93414 21.0659C2.03933 20.1711 1.63399 19.031 1.43975 17.5863C1.24998 16.1748 1.24999 14.3658 1.25 12.0574V11.9426C1.24999 9.63423 1.24998 7.82519 1.43975 6.41371C1.63399 4.96897 2.03933 3.82895 2.93414 2.93414C3.82895 2.03933 4.96897 1.63399 6.41371 1.43975C7.82519 1.24998 9.63423 1.24999 11.9426 1.25ZM16.7705 2.27592C18.1384 0.908029 20.3562 0.908029 21.7241 2.27592C23.092 3.6438 23.092 5.86158 21.7241 7.22947L15.076 13.8776C14.7047 14.2489 14.4721 14.4815 14.2126 14.684C13.9069 14.9224 13.5761 15.1268 13.2261 15.2936C12.929 15.4352 12.6169 15.5392 12.1188 15.7052L9.21426 16.6734C8.67801 16.8521 8.0868 16.7126 7.68711 16.3129C7.28742 15.9132 7.14785 15.322 7.3266 14.7857L8.29477 11.8812C8.46079 11.3831 8.56479 11.071 8.7064 10.7739C8.87319 10.4239 9.07761 10.0931 9.31605 9.78742C9.51849 9.52787 9.7511 9.29529 10.1224 8.924L16.7705 2.27592ZM20.6634 3.33658C19.8813 2.55448 18.6133 2.55448 17.8312 3.33658L17.4546 3.7132C17.4773 3.80906 17.509 3.92327 17.5532 4.05066C17.6965 4.46372 17.9677 5.00771 18.48 5.51999C18.9923 6.03227 19.5363 6.30346 19.9493 6.44677C20.0767 6.49097 20.1909 6.52273 20.2868 6.54543L20.6634 6.16881C21.4455 5.38671 21.4455 4.11867 20.6634 3.33658ZM19.1051 7.72709C18.5892 7.50519 17.9882 7.14946 17.4193 6.58065C16.8505 6.01185 16.4948 5.41082 16.2729 4.89486L11.2175 9.95026C10.801 10.3668 10.6376 10.532 10.4988 10.7099C10.3274 10.9297 10.1804 11.1676 10.0605 11.4192C9.96337 11.623 9.88868 11.8429 9.7024 12.4017L9.27051 13.6974L10.3026 14.7295L11.5983 14.2976C12.1571 14.1113 12.377 14.0366 12.5808 13.9395C12.8324 13.8196 13.0703 13.6726 13.2901 13.5012C13.468 13.3624 13.6332 13.199 14.0497 12.7825L19.1051 7.72709Z"
                                                        fill="#1C274C" />
                                                </svg>
                                            </button>
                                            <div x-cloak x-transition x-show="editAgent"
                                                class="fixed inset-0 z-10 bg-gray-500 bg-opacity-50 flex items-center justify-center">
                                                <div class="bg-white p-6 rounded shadow-lg w-full max-w-md relative">
                                                    <div class="flex items-center justify-between mb-6">
                                                        <div>
                                                            <h2 class="text-xl font-bold text-gray-800">Edit Agent Commission</h2>
                                                            <p class="text-gray-600 italic text-xs mt-1">Please update the agent commission rate to ensure accurate information</p>
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
                                                            <label for="commission"
                                                                class="block text-sm font-medium text-gray-700">Commission Rate (%)</label>
                                                            <input type="number" name="commission" id="commission"
                                                                value="{{ $agent->commission * 100 }}"
                                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" max="100">
                                                        </div>
                                                        <div class="flex justify-between space-x-4">
                                                            <button type="button" @click="editAgent = false"
                                                                class="rounded-full shadow-md border border-gray-200 hover:bg-gray-400 px-4 py-2">Cancel</button>
                                                            <button type="submit"
                                                                class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        <a href="{{ route('agents.show', ['id' => $agent->id]) }}" class="block">
                                            {{ $agent->name }}
                                        </a>
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $agent->amadeus_id ?? 'N/A' }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $agent->email ?? 'N/A' }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $agent->phone_number ?? 'N/A' }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">
                                        {{ optional($agent->agentType)->name }}
                                    </td>
                                    @if($agent->type_id != 1)
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $agent->commission * 100 }}</td>
                                    @else
                                    <td class="p-3 text-sm font-semibold text-gray-500">Salary-based</td>
                                    @endif
                                </tr>
                                @endforeach
                                @endif
                            </tbody>

                        </table>

                    </div>
                    <!-- ./table -->

                </div>

                <x-pagination :data="$agents" />

            </div>
            <!-- ./Table  -->

        </div>
        <!-- right -->
        <div class="content-30 hidden">

            <div class="flex lg:flex-col md:flex-row justify-center text-center gap-5">
                <!-- customize -->
                <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                        <path fill="#333333" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                    </svg>
                    <span class="text-sm">Customize</span>
                </button>
                <!-- ./customize -->

                <!-- filter -->
                <button class="flex px-5 py-3 gap-2 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
                    </svg>
                    <span class="text-sm">Filter</span>
                </button>
                <!-- ./filter -->

                <!-- export -->
                <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                    <svg class="svgW" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path fill="#333333" d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                    </svg>
                    <span class="text-sm">Export</span>
                </button>
                <!-- ./export -->
            </div>
            <div class="mt-5 ">
                <div id="AgentDetails" class="panel w-full xl:mt-0 rounded-lg h-auto hidden"></div> <!-- display Agent details here-->

            </div>
        </div>
    </div>
</x-app-layout>
<script>
    function toggleAgentStatus(checkbox) {
        let agentId = checkbox.dataset.agentId; // Fetch the agent's ID from dataset
        let row = document.getElementById('agent_row_' + agentId); // Locate the row

        if (!row) {
            console.error("Row not found for agent ID:", agentId);
            return;
        }

        let rowCheckbox = row.querySelector('.rowCheckbox');

        if (!checkbox.checked) {
            if (!confirm("Are you sure you want to disable this agent?")) {
                checkbox.checked = true; // Restore checked state if user cancels
                return;
            }

            row.style.opacity = "0.5"; // Dim entire row to indicate disabled state
            rowCheckbox?.setAttribute("disabled", "true"); // Disable row selection (if applicable)
        } else {
            row.style.opacity = "1"; // Restore row visibility
            rowCheckbox?.removeAttribute("disabled"); // Enable row selection (if applicable)
        }
    }
</script>