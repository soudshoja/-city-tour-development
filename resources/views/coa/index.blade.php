<x-app-layout>
    <div id="coa-container" data-branches='@json($branches)' data-agents='@json($agents)'
        data-clients='@json($clients)' class="flex justify-between items-center gap-5 my-3 ">

        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Chart Of Account</h2>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload"
                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>

            <form action="{{ route('coa.transaction') }}" method="GET">
                <button type="submit"
                    class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-md transition">
                    Transaction Records
                </button>
            </form>

            <button id="openModalBtn" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded">
                Import/Export Accounts
            </button>

            <div id="modalBackdrop"
                class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center z-50 hidden">
                <div class="bg-white p-6 rounded shadow-lg w-full max-w-lg relative">
                    <h2 class="text-xl font-bold mb-4">Import / Export Accounts</h2>

                    <button id="closeModalBtn"
                        class="absolute top-2 right-2 text-gray-600 hover:text-gray-900">✕</button>

                    <div>
                        <label class="inline-flex items-center space-x-3 mb-4 mr-10">
                            <input type="radio" name="action" value="export" checked
                                class="form-radio text-blue-600" />
                            <span>Export Accounts</span>
                        </label>
                        <label class="inline-flex items-center space-x-3 mb-4">
                            <input type="radio" name="action" value="import" class="form-radio text-blue-600" />
                            <span>Import Accounts</span>
                        </label>
                    </div>

                    <form id="exportForm" action="{{ route('coa.export') }}" method="GET" class="mb-4">
                        <button type="submit"
                            class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded font-semibold">
                            Download Excel
                        </button>
                    </form>

                    <form id="importForm" action="{{ route('coa.import') }}" method="POST"
                        enctype="multipart/form-data" class="hidden">
                        @csrf
                        <input type="file" name="file" required
                            class="block mb-3 w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-semibold">
                            Upload File
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if (session('info'))
            <div class="alert alert-info">
                {{ session('info') }}
            </div>
        @endif

    </div>

   <div class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-400 rounded-r-lg">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3 text-sm">
                <h4 class="text-blue-800 font-medium">Account Balance & Tracking Systems</h4>
                <p class="text-blue-700 mt-1">
                    <strong>Static Exclusions (COA Display):</strong> Parent account balances include <strong>service</strong> and <strong>traditional</strong> accounts only. 
                    <strong>Payment accounts</strong> are excluded from parent totals to prevent double-counting. 
                    When you see :<br class="my-2"> <span class="px-2 py-1 text-xs w-fit bg-yellow-100 text-yellow-700 border border-yellow-300 rounded">※ Excl: X.XX</span><br class="my-2"> 
                    this shows excluded payment account amounts based on account dimensions.
                </p>
            </div>
        </div>
    </div>

    <div id="contentBox" class="AddNewSamePage">
        <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-2 gap-4 my-8">
            @php
                // Define types and their colors
                $types = [
                    'Assets' => '00ab55',
                    'Liabilities' => 'ffc107',
                    'Income' => '1e40af',
                    'Expenses' => 'AF1740',
                    'Equity' => '9744ad',
                ];
            @endphp

            @foreach ($types as $type => $color)
                <!-- Pass `type` and `color` to both card and modal components -->
                <x-coa-card :type="$type" :color="$color" />
                <x-coa-modal :type="$type" :color="$color" />
            @endforeach
        </div>
    </div>

    <div class="rounded-lg w-full">
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.assets')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.liabilities')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.income')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.expenses')</div>
        <div class="mb-5 search-item rounded-lg">@include('coa.partials.equity')</div>

    </div>

    <script>
        // Safely parse JSON attributes with fallback and error logging
        let branches = [],
            agents = [],
            clients = [];
        try {
            const container = document.getElementById('coa-container');
            if (container) {
                const branchesData = container.getAttribute('data-branches');
                const agentsData = container.getAttribute('data-agents');
                const clientsData = container.getAttribute('data-clients');

                branches = branchesData ? JSON.parse(branchesData) : [];
                agents = agentsData ? JSON.parse(agentsData) : [];
                clients = clientsData ? JSON.parse(clientsData) : [];
            } else {
                console.warn('#coa-container element not found');
            }
        } catch (error) {
            console.error('Failed to parse JSON from data attributes:', error);
        }

        const entitySelects = document.querySelectorAll('.entitySelect');

        function handleEntityChange(event) {
            console.log('Entity changed:', event.target.value);
            const entitySelect = event.target;
            const accountId = entitySelect.dataset.accountId;
            const selectedValue = entitySelect.value;

            const entityContainer = document.getElementById(`entity-container-${accountId}`);
            if (!entityContainer) return;
            entityContainer.innerHTML = ''; // Clear previous content

            if (!selectedValue) return;

            const label = document.createElement('label');
            label.classList.add('block', 'text-sm', 'font-medium', 'mb-1');
            label.innerHTML =
                `${selectedValue.charAt(0).toUpperCase() + selectedValue.slice(1)} Name<span class="text-red-500"> *</span>`;
            entityContainer.appendChild(label);

            let selectOptions = [];
            if (selectedValue === 'agent') selectOptions = agents;
            else if (selectedValue === 'client') selectOptions = clients;
            else if (selectedValue === 'branch') selectOptions = branches;

            if (selectOptions.length > 0) {
                const select = createSelectElement(
                    [{
                        id: '',
                        name: `Select ${selectedValue}`
                    }, ...selectOptions], {
                        name: selectedValue,
                        id: selectedValue,
                        required: 'required',
                        autocomplete: 'off'
                    },
                    ['w-full', 'border', 'rounded', 'text-sm', 'px-3', 'py-2', 'focus:outline-none', 'focus:ring-2',
                        'focus:ring-blue-300'
                    ]
                );
                entityContainer.appendChild(select);
            }
        }

        // Attach event listeners only if entitySelects exist
        if (entitySelects.length) {
            entitySelects.forEach(entitySelect => {
                entitySelect.addEventListener('change', handleEntityChange);
            });
        }

        const toggleBtn = document.getElementById('toggleBtn');
        const contentBox = document.getElementById('contentBox');

        if (toggleBtn && contentBox) {
            toggleBtn.addEventListener('click', () => {
                contentBox.classList.toggle('AddNewSamePageVisible');
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('modalBackdrop');
            const openBtn = document.getElementById('openModalBtn');
            const closeBtn = document.getElementById('closeModalBtn');
            const actionRadios = modal.querySelectorAll('input[name="action"]');
            const exportForm = document.getElementById('exportForm');
            const importForm = document.getElementById('importForm');

            openBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
            });

            closeBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });

            actionRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    if (radio.value === 'export' && radio.checked) {
                        exportForm.classList.remove('hidden');
                        importForm.classList.add('hidden');
                    } else if (radio.value === 'import' && radio.checked) {
                        importForm.classList.remove('hidden');
                        exportForm.classList.add('hidden');
                    }
                });
            });
        });
    </script>

</x-app-layout>
