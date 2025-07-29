<x-app-layout>
    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>Suppliers List</span>
            </li>
        </ul>
        <div class="flex flex-col md:flex-row items-center justify-between p-3 bg-white dark:bg-gray-800 shadow rounded-lg space-y-3 md:space-y-0 text-gray-700 dark:text-gray-300">
            <div class="flex items-start md:items-center border border-gray-300 rounded-lg p-2 space-y-3 md:space-y-0 md:space-x-3">
                <div class="flex gap-2 mr-2">
                    <a class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                        <span class="text-black dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Total Suppliers </span>
                    </a>
                    <a class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-info-light dark:bg-gray-700">
                        <span id="suppliersData">
                            {{ $suppliersCount }}
                        </span>
                    </a>
                </div>
            </div>
            <div class="flex items-center gap-3 space-y-3 md:space-y-0 md:space-x-2">
                <div class="mt07 relative flex items-center h-12">
                    <input id="searchInput" type="text" placeholder="Search"
                        class="w-full h-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-300">
                    <svg class="w-5 h-5 text-gray-500 absolute left-3 top-1/2 transform -translate-y-1/2"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M9.5 17A7.5 7.5 0 109.5 2a7.5 7.5 0 000 15z" />
                    </svg>
                </div>


            </div>
        </div>
    </div>
    <div x-data="{addSupplierModal : false}" class="flex gap-2 justify-between items-center my-5 p-2 bg-white dark:bg-dark shadow-md rounded-md">
        <div class="flex justify-start">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 7V13" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" />
                <circle cx="12" cy="16" r="1" fill="#ff0000" />
                <path d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" />
            </svg>
            @if(auth()->user()->role->name === 'admin')
            <span class="">Activate supplier to allow the system users to request API from the supplier</span>
            @else
            <span class="">Only system admin can activate suppliers, please contact your admin to activate the supplier</span>
            @endif
        </div>
        @if(auth()->user()->role->name === 'admin')
        <x-primary-button @click="addSupplierModal = true">Add Supplier</x-primary-button>
        <div
            x-cloak
            x-show="addSupplierModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
            <div
                @click.away="addSupplierModal = false"
                class="bg-white w-1/2 max-h-1/4 rounded-md shadow-md p-5">
                <h1 class="font-semibold">
                    Add Supplier To The System
                </h1>
                <form action="{{ route('suppliers.store') }}" method="POST" class="flex flex-col gap-2 mb-2">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Supplier Name</label>
                        <input type="text" name="name" id="name" placeholder="Supplier Name"
                            class="border border-gray-300 rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                    </div>
                    <div class="mb-3">
                        <label for="auth_type" class="block text-sm font-medium text-gray-700 mb-1">Authentication Type</label>
                        <select name="auth_type" id="auth_type"
                            class="border border-gray-300 rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                            @foreach ($supplierAuthTypes as $type)
                            <option value="{{ $type }}">{{ strtolower($type->name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" name="has_hotel" id="has_hotel"
                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-400 transition">
                        <label for="has_hotel" class="ml-2 text-sm text-gray-700">Has Hotel</label>
                    </div>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" name="has_flight" id="has_flight"
                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-400 transition">
                        <label for="has_flight" class="ml-2 text-sm text-gray-700">Has Flight</label>
                    </div>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" name="has_visa" id="has_visa"
                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-400 transition">
                        <label for="has_visa" class="ml-2 text-sm text-gray-700">Has Visa</label>
                    </div>
                    <div class="flex items-center mb-2">
                        <input type="checkbox" name="has_insurance" id="has_insurance"
                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-400 transition">
                        <label for="has_insurance" class="ml-2 text-sm text-gray-700">Has Insurance</label>
                    </div>
                    <div>
                        <x-searchable-dropdown
                            name="country_id"
                            :items="$countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name])"
                            placeholder="Select Country" />
                    </div>
                    <x-primary-button type="submit" class="py-2 px-6 bg-blue-500 text-white w-fit rounded shadow">Submit</x-primary-button>
                </form>

            </div>

        </div>
        @endif
    </div>

    @role('admin')
    <div class="max-h-160 overflow-y-auto custom-scrollbar bg-white dark:bg-dark rounded-md p-2">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-2 border-b border-r">Supplier Name</th>
                    <th class="px-4 py-2 border-b border-r">Company</th>
                    <th class="px-4 py-2 border-b">Actions</th>
                </tr>
            </thead>
            <tbody>
                @if($suppliers->isEmpty())
                <tr>
                    <td colspan="3" class="text-center">No suppliers found</td>
                </tr>
                @else
                @foreach($suppliers as $supplier)
                <tr class="hover:bg-gray-200 dark:hover:bg-gray-600">
                    <td class="border-r px-4 py-2">
                        {{ $supplier->name }}
                    </td>
                    <td class="border-r px-4 py-2 overflow-x-auto">
                        <div class="flex gap-2">
                            @if($supplier->companies->isEmpty())
                            <p class="text-center font-semibold">
                                No companies registered
                            </p>
                            @else
                            @foreach($supplier->companies as $company)
                            <div class="p-2 bg-gray-100 rounded">
                                {{ $company->name }}
                            </div>
                            @endforeach
                            @endif
                        </div>
                    </td>
                    <td x-data="{editSuppliers : false}" class="px-4 py-2 flex">
                        <a href="{{ route('supplier-company.edit', $supplier->id) }}" class="group">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-black group-hover:stroke-blue-500">
                                <path d="M22 22L2 22" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M17 22V6C17 4.11438 17 3.17157 16.4142 2.58579C15.8284 2 14.8856 2 13 2H11C9.11438 2 8.17157 2 7.58579 2.58579C7 3.17157 7 4.11438 7 6V22" stroke="" stroke-width="1.5" />
                                <path d="M21 22V8.5C21 7.09554 21 6.39331 20.6629 5.88886C20.517 5.67048 20.3295 5.48298 20.1111 5.33706C19.6067 5 18.9045 5 17.5 5" stroke="" stroke-width="1.5" />
                                <path d="M3 22V8.5C3 7.09554 3 6.39331 3.33706 5.88886C3.48298 5.67048 3.67048 5.48298 3.88886 5.33706C4.39331 5 5.09554 5 6.5 5" stroke="" stroke-width="1.5" />
                                <path d="M12 22V19" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M10 12H14" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M5.5 11H7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M5.5 14H7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M17 11H18.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M17 14H18.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M5.5 8H7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M17 8H18.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M10 15H14" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M12 9V5" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M14 7L10 7" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                        <button type="button" @click="editSuppliers = true" class="ml-2" data-left-tooltip="Edit Supplier">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14.3601 4.07866L15.2869 3.15178C16.8226 1.61607 19.3125 1.61607 20.8482 3.15178C22.3839 4.68748 22.3839 7.17735 20.8482 8.71306L19.9213 9.63993M14.3601 4.07866C14.3601 4.07866 14.4759 6.04828 16.2138 7.78618C17.9517 9.52407 19.9213 9.63993 19.9213 9.63993M14.3601 4.07866L5.83882 12.5999C5.26166 13.1771 4.97308 13.4656 4.7249 13.7838C4.43213 14.1592 4.18114 14.5653 3.97634 14.995C3.80273 15.3593 3.67368 15.7465 3.41556 16.5208L2.32181 19.8021M19.9213 9.63993L11.4001 18.1612C10.8229 18.7383 10.5344 19.0269 10.2162 19.2751C9.84082 19.5679 9.43469 19.8189 9.00498 20.0237C8.6407 20.1973 8.25352 20.3263 7.47918 20.5844L4.19792 21.6782M4.19792 21.6782L3.39584 21.9456C3.01478 22.0726 2.59466 21.9734 2.31063 21.6894C2.0266 21.4053 1.92743 20.9852 2.05445 20.6042L2.32181 19.8021M4.19792 21.6782L2.32181 19.8021" stroke="#1C274C" stroke-width="1.5" />
                            </svg>
                        </button>
                        <div x-show="editSuppliers" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
                            <div @click.away="editSuppliers = false" class="bg-white w-1/2 max-h-1/4 rounded-md shadow-md p-5">
                                <h1 class="font-semibold">Edit Supplier</h1>
                                <form action="{{ route('suppliers.update', $supplier->id) }}" method="POST" class="flex flex-col gap-2 mb-2">
                                    @csrf
                                    @method('PUT')
                                    <div class="mb-3">
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Supplier Name</label>
                                        <input type="text" name="name" id="name" value="{{ $supplier->name }}" placeholder="Supplier Name"
                                            class="border border-gray-300 rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                    </div>
                                    <div class="mb-3">
                                        <label for="auth_type" class="block text-sm font-medium text-gray-700 mb-1">Authentication Type</label>
                                        <select name="auth_type" id="auth_type"
                                            class="border border-gray-300 rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                            @foreach ($supplierAuthTypes as $type)
                                            <option value="{{ $type }}" {{ $supplier->auth_type === $type ? 'selected' : '' }}>{{ strtolower($type->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex items-center mb-2">
                                        <input type="checkbox" name="has_hotel" id="has_hotel" {{ $supplier->has_hotel ? 'checked' : '' }}
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-400 transition">
                                        <label for="has_hotel" class="ml-2 text-sm text-gray-700">Has Hotel</label>
                                        <input type="checkbox" name="has_flight" id="has_flight" {{ $supplier->has_flight ? 'checked' : '' }}
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-400 transition ml-4">
                                        <label for="has_flight" class="ml-2 text-sm text-gray-700">Has Flight</label>
                                         <input type="checkbox" name="has_visa" id="has_visa" {{ $supplier->has_visa ? 'checked' : '' }}
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-400 transition ml-4">
                                        <label for="has_visa" class="ml-2 text-sm text-gray-700">Has Visa</label>
                                         <input type="checkbox" name="has_insurance" id="has_insurance" {{ $supplier->has_insurance ? 'checked' : '' }}
                                            class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-400 transition ml-4">
                                        <label for="has_insurance" class="ml-2 text-sm text-gray-700">Has Insurance</label>
                                    </div>
                                    <div>
                                        <x-searchable-dropdown
                                            name="country_id"
                                            :items="$countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name])"
                                            placeholder="Select Country" 
                                            :selectedId="$supplier->country->id" 
                                            :selectedName="$supplier->country->name"
                                            />
                                    </div>
                                    <x-primary-button type="submit" class="py-2 px-6 bg-blue-500 text-white w-fit rounded shadow">Update</x-primary-button>
                                </form>
                            </div>

                        </div>
                    </td>
                </tr>
                @endforeach
                @endif
            </tbody>
        </table>

    </div>
    @else
    <div class="max-h-160 overflow-y-auto custom-scrollbar">
        <table class="">
            <thead class="sticky top-0">
                <tr>
                    <th class="px-4 py-2">Supplier Name</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-dark rounded-md p-2" id="suppliersTable">
                @if($suppliers->isEmpty())
                <tr>
                    <td colspan="2" class="text-center">No suppliers found</td>
                </tr>
                @else
                @foreach ($suppliers as $supplier)
                <tr class=" hover:bg-gray-200 dark:hover:bg-gray-600">
                    <td class="px-4 py-2 border dark:border-gray-600 cursor-pointer">
                        <a href="{{ route('suppliers.show', $supplier->id) }}">
                            <span class="font-bold">» {{ $supplier->name }}</span><br>
                        </a>
                    </td>
                    <td class="px-4 py-2 border dark:border-gray-600 text-center space-x-2 flex">
                        <div x-data="{credentialModal: false}">
                            <x-primary-button @click="credentialModal = true">
                                Credentials
                            </x-primary-button>
                            @include('suppliers.partials.supplier_credential', ['supplier' => $supplier])
                        </div>
                        <x-primary-a-button href="{{ route('tasks.supplier', $supplier->id) }}">
                            Get All Task
                        </x-primary-a-button>
                        @if($supplier->named_route)
                        <x-primary-a-button href="">Configure</x-primary-a-button>
                        @endif
                    </td>
                </tr>
                @endforeach
                @endif
            </tbody>
        </table>
    </div>
    @endrole
    <script>
        const searchInput = document.getElementById('searchInput');
        const suppliersData = document.getElementById('suppliersData');
        const suppliers = @json($suppliers);

        searchInput.addEventListener('input', (e) => {

            const searchValue = e.target.value;
            const filteredSuppliers = suppliers.filter(supplier => supplier.name.toLowerCase().includes(searchValue.toLowerCase()));
            suppliersData.innerText = filteredSuppliers.length;
            const suppliersTable = document.getElementById('suppliersTable');
            const basedUrl = @json(config('app.url'));

            suppliersTable.innerHTML = '';
            filteredSuppliers.forEach(supplier => {

                let showUrl = basedUrl + '/suppliers/' + supplier.id;
                let url = basedUrl + '/suppliers/' + supplier.route + '/index';

                const tr = document.createElement('tr');
                tr.classList.add('hover:bg-gray-200', 'dark:hover:bg-gray-600');
                tr.innerHTML = `
                    <td class="px-4 py-2 border dark:border-gray-600 cursor-pointer">
                        <a href="${showUrl}">
                            <span class="font-bold">» ${supplier.name}</span><br>
                        </a>
                    </td>
                    <td class="px-4 py-2 border dark:border-gray-600 text-center space-x-2">
                        <button class="bg-green-500 text-white px-2 py-1 rounded">Activate</button>
                        <button class="bg-gray-300 text-gray-700 px-2 py-1 rounded">Deactivate</button>
                        ${supplier.named_route ? `<a href="${url}" class="bg-gray-300 text-gray-700 px-2 py-1 rounded">Configure</a>` : ''}
                    </td>
                `;
                suppliersTable.appendChild(tr);
            });
        });

        const supplierSelect = document.getElementById('supplier');
        const basicInput = document.querySelector('.basic-input');
        const oauthInput = document.querySelector('.oauth-input');

        supplierSelect.addEventListener('change', (e) => {
            const supplier = JSON.parse(e.target.selectedOptions[0].getAttribute('data-supplier'));
            const authMethod = supplier.auth_type
            let type = document.getElementById('supplier_company_type');

            console.log(type);
            type.value = authMethod;
            console.log(type);
            if (authMethod === 'basic') {
                basicInput.classList.remove('hidden');
                oauthInput.classList.add('hidden');
            } else {
                basicInput.classList.add('hidden');
                oauthInput.classList.remove('hidden');
            }
        });
    </script>
</x-app-layout>