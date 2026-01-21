<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3 ">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Clients List</h2>
            <div data-tooltip="Number of clients" class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $clients->total() }}</span>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>

            <a href="{{ route('users.create', ['openForm' => 'clientForm']) }}">
                <div data-tooltip-left="Create new client" class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7"/>
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <div>
        <div class="panel rounded-lg">
            <x-search action="{{ route('clients.index') }}" searchParam='search' placeholder="Quick search for clients" />

            <div class="dataTable-wrapper mt-4">
                <div class="dataTable-container h-max">
                    <table class="table-hover whitespace-nowrap dataTable-table">
                        <thead>
                            <tr class="p-3 text-md font-bold text-gray-500">
                                <th class="text-left">Client's Name</th>
                                <th>Civil No</th>
                                <th>Created</th>
                                <th>Credit (KWD)</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Agent's Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($clients->isEmpty())
                            <tr>
                                <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">No data for now.... Create new!</td>
                            </tr>
                            @else
                            @foreach ($clients as $client)
                            <tr data-name="{{ $client->full_name }}" data-email="{{ $client->email }}" data-phone="{{ $client->phone }}"
                                class="p-3 text-sm font-semibold text-gray-600 text-center">
                                <td class="text-left">
                                    <div class="w-full text-blue-600 dark:text-gray-300 flex justify-between items-center">
                                        <a href="{{ route('clients.show', ['id' => $client->id]) }}">
                                            <p>{{ $client->fullname }}</p>
                                        </a>
                                        <a href="javascript:void(0);" id="copyNameButton"
                                            class="mx-auto text-green-600 dark:text-green-300 ml-2"
                                            data-name="{{ $client->full_name }}"
                                            data-tooltip="Copy Name">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                                <g fill="none" stroke="currentColor" stroke-width="1">
                                                    <rect width="13" height="13" x="9" y="9" rx="2" ry="2" />
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                                </g>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                                <td>{{ $client->civil_no ?? 'N/A' }}</td>
                                <td>{{ date('d M Y', strtotime($client->created_at)) }}</td>
                                <td>
                                    <a href="javascript:void(0);"
                                        class="clientCreditLink font-bold {{ ($client->totalCredit ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}"
                                        data-client-id="{{ $client->id }}">
                                        {{ number_format($client->totalCredit ?? 0, 2) }}
                                    </a>
                                </td>
                                <td>{{ $client->email ? $client->email : 'N/A' }}</td>
                                <td>{{ $client->phone ? $client->phone : 'N/A' }}</td>
                                <td>
                                    @if($client->agents->isEmpty())
                                        {{ $client->agent->name }}
                                    @else
                                        @if($client->agents->count() == 1)
                                            {{ $client->agents->first()->name }}
                                        @else
                                            <div class="dropdown inline-block relative" x-data="{ open: false }">
                                                <button @click="open = !open" x-ref="button"
                                                    class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold py-1 px-2 rounded inline-flex items-center">
                                                    <span class="mr-1">Multiple Agents</span>
                                                    <svg class="fill-current h-4 w-4 transform transition-transform duration-200" :class="{ 'rotate-180': open }"
                                                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                        <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                                                    </svg>
                                                </button>

                                                <div x-show="open"
                                                    x-transition:enter="transition ease-out duration-100"
                                                    x-transition:enter-start="transform opacity-0 scale-95"
                                                    x-transition:enter-end="transform opacity-100 scale-100"
                                                    x-transition:leave="transition ease-in duration-75"
                                                    x-transition:leave-start="transform opacity-100 scale-100"
                                                    x-transition:leave-end="transform opacity-0 scale-95"
                                                    @click.away="open = false"
                                                    x-init="$watch('open', value => {
                                                        if (value) {
                                                            $nextTick(() => {
                                                                const rect = $refs.button.getBoundingClientRect();
                                                                $el.style.position = 'fixed';
                                                                $el.style.top = (rect.bottom + 4) + 'px';
                                                                $el.style.left = rect.left + 'px';
                                                                $el.style.zIndex = '10';
                                                            });
                                                        }
                                                    })"
                                                    class="dropdown-menu bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded shadow-lg min-w-max">
                                                    <ul class="text-gray-700 dark:text-gray-300 pt-1">
                                                        @foreach($client->agents as $agent)
                                                        <li>
                                                            <a class="rounded-t bg-white dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 py-2 px-4 block whitespace-no-wrap"
                                                                href="javascript:void(0);">{{ $agent->name }}</a>
                                                        </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>

                </div>

                <x-pagination :data="$clients" />

            </div>
        </div>
    </div>

    <div id="creditDetailsModal" class="fixed inset-0 z-50 hidden bg-gray-800 bg-opacity-50 flex items-center justify-center px-4"  onclick="closeModalOnOutsideClick(event)">
        <div class="bg-white dark:bg-gray-800 rounded-lg w-full max-w-3xl relative max-h-[90vh]" onclick="event.stopPropagation();">
            <div class="flex justify-between items-center p-4 border-b">
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200">Credit Transaction Details</h2>
                <div class="flex items-center gap-3">
                    <a id="openLedgerBtn" href="#" target="_blank" rel="noopener"
                        class="hidden inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-md bg-blue-50 text-blue-700 border border-blue-100
                        hover:bg-blue-100 hover:text-blue-800 dark:bg-slate-700 dark:text-slate-100 dark:border-slate-600 dark:hover:bg-slate-600">
                        View Full Ledger
                    </a>
                    <button id="closeModal" class="text-gray-500 hover:text-red-500 text-2xl leading-none">&times;</button>
                </div>
            </div>

            <div class="p-4">
                <form id="creditFilterForm" class="flex flex-wrap gap-4 mb-4">
                    <input type="date" name="from" id="filterFromDate" class="border p-2 rounded w-full sm:w-auto">
                    <input type="date" name="to" id="filterToDate" class="border p-2 rounded w-full sm:w-auto">
                    <input type="hidden" id="modalClientId" name="client_id">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Filter</button>
                </form>

                <div class="overflow-y-auto max-h-[350px]">
                    <table class="w-full text-m text-left border-collapse">
                        <thead class="sticky top-0 bg-gray-100 dark:bg-gray-700">
                            <tr class="p-2 border-b">
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-right">Amount (KWD)</th>
                            </tr>
                        </thead>
                        <tbody id="creditDetailsBody">
                            <!-- Rows will be populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('#copyNameButton').forEach(button => {
            button.addEventListener('click', function() {
                const name = this.getAttribute('data-name');
                navigator.clipboard.writeText(name).then(() => {
                    const originalTooltip = this.getAttribute('data-tooltip');
                    this.setAttribute('data-tooltip', 'Copied!');
                    setTimeout(() => {
                        this.setAttribute('data-tooltip', originalTooltip);
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                });
            });
        });

        //credit details modal
        document.querySelectorAll('.clientCreditLink').forEach(link => {
            link.addEventListener('click', function() {
                const clientId = this.dataset.clientId;
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

                const toDateString = date => {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                };

                const from = toDateString(firstDay);
                const to = toDateString(lastDay);

                document.getElementById('filterFromDate').value = from;
                document.getElementById('filterToDate').value = to;
                document.getElementById('modalClientId').value = clientId;

                const ledgerBtn = document.getElementById('openLedgerBtn');
                const ledgerUrlTemplate = "{{ route('clients.credits', ':clientId') }}";
                if (ledgerBtn) {
                    ledgerBtn.href = ledgerUrlTemplate.replace(':clientId', clientId);
                    ledgerBtn.classList.remove('hidden');
                }

                fetchCredits(clientId, from, to);
                document.getElementById('creditDetailsModal').classList.remove('hidden');
            });
        });


        document.getElementById('closeModal').addEventListener('click', function() {
            document.getElementById('creditDetailsModal').classList.add('hidden');
        });

        document.getElementById('creditFilterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const clientId = document.getElementById('modalClientId').value;
            const from = document.getElementById('filterFromDate').value;
            const to = document.getElementById('filterToDate').value;
            fetchCredits(clientId, from, to);
        });

        function fetchCredits(clientId, from, to) {
            fetch(`/credits/filter?client_id=${clientId}&from=${from}&to=${to}`)
                .then(response => response.json())
                .then(data => {
                    const body = document.getElementById('creditDetailsBody');
                    body.innerHTML = '';

                    if (data.length === 0) {
                        body.innerHTML =
                            `<tr><td colspan="4" class="p-2 text-center text-gray-500">No records found</td></tr>`;
                        return;
                    }

                    data.forEach(credit => {
                        body.innerHTML += `
                        <tr>
                            <td class="p-2">${credit.date}</td>
                            <td class="p-2">${credit.type ?? '-'}</td>
                            <td class="p-2">${credit.description ?? '-'}</td>
                            <td class="p-2 text-right font-semibold ${credit.amount >= 0 ? 'text-green-600' : 'text-red-600'}">
                                ${parseFloat(credit.amount).toFixed(3)}
                            </td>
                        </tr>
                    `;
                    });
                });
        }

        function closeModalOnOutsideClick(event) {
            const modal = document.getElementById('creditDetailsModal');
            modal.classList.add('hidden');
        }

        document.getElementById('closeModal').addEventListener('click', () => {
            document.getElementById('creditDetailsModal').classList.add('hidden');
        });
    </script>

</x-app-layout>