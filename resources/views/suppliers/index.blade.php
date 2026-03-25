<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <h2 class="text-3xl font-bold">Suppliers</h2>
            <div data-tooltip="Total suppliers" class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $suppliers->total() }}</span>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload" class="rotate refresh-icon relative w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300
                rounded-full shadow-sm cursor-pointer" onclick="window.location.reload()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>
            @role('admin')
                <div x-data="{ addSupplierModal: false }">
                    <div data-tooltip-left="Add new supplier" class="relative w-10 h-10 md:w-12 md:h-12 flex items-center justify-center btn-success rounded-full shadow-sm cursor-pointer"
                        @click="addSupplierModal = true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                            <path fill="#fff" d="M12 4v16m8-8H4" stroke="#fff" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                    <div x-cloak x-show="addSupplierModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
                        <div @click.away="addSupplierModal = false"
                            class="bg-white dark:bg-gray-800 w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-lg shadow-xl mx-4 custom-scrollbar">
                            <div class="sticky top-0 bg-white dark:bg-gray-800 z-10 p-5 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between">
                                <div>
                                    <h1 class="text-lg md:text-xl font-semibold text-gray-900 dark:text-gray-100">Add Supplier</h1>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">Fill in the details to add a new supplier</p>
                                </div>
                                <button type="button" @click="addSupplierModal = false"
                                    class="p-2 -mr-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path fill-rule="evenodd" d="M6.225 4.811a1 1 0 0 1 1.414 0L12 9.172l4.361-4.361a1 1 0 1 1 1.414 1.414L13.414 10.586l4.361 4.361a1 1 0 0 1-1.414 1.414L12 12l-4.361 4.361a1 1 0 0 1-1.414-1.414l4.361-4.361-4.361-4.361a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <div class="p-5">
                                <form action="{{ route('suppliers.store') }}" method="POST" class="flex flex-col gap-2 mb-2">
                                    @csrf
                                    <div class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier Name</label>
                                            <input type="text" name="name" placeholder="Supplier Name"
                                                class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md p-2 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                        </div>
                                        <div>
                                            <label for="auth_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Authentication Type</label>
                                            <select name="auth_type" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md p-2 w-full
                                                focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                                @foreach ($supplierAuthTypes as $type)
                                                    <option value="{{ $type }}">{{ strtolower($type->name) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 mr-3 whitespace-nowrap shrink-0">Country of Origin</span>
                                    <div>
                                        <x-searchable-dropdown
                                            name="country_id"
                                            :items="$countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name])"
                                            placeholder="Select Country" />
                                    </div>

                                    @include('suppliers.partials.service-toggles', ['supplier' => new \App\Models\Supplier()])

                                    <div class="mt-5 flex items-center justify-between">
                                        <button type="button" @click="addSupplierModal = false"
                                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-300 shadow-md hover:bg-gray-50 dark:hover:bg-gray-700">
                                            Cancel
                                        </button>
                                        <button type="submit" class="py-2 px-6 bg-blue-600 text-white rounded-md shadow-md hover:bg-blue-700">
                                            Save
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endrole
        </div>
    </div>

    <div x-data="{
            @role('admin')
            activeTab: localStorage.getItem('supplier_tab') || 'suppliers',
            @else
            activeTab: 'suppliers',
            @endrole
            setTab(tab) {
                this.activeTab = tab;
                localStorage.setItem('supplier_tab', tab);
            }
        }">
        <div class="main-tabs-bar">
            <button @click="setTab('suppliers')" class="main-tab-shape main-tab main-tab-active"
                :class="{ 'main-tab-active': activeTab === 'suppliers', 'main-tab-inactive': activeTab !== 'suppliers' }">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Suppliers
                    <span class="main-tab-badge main-tab-badge-blue">{{ $suppliers->total() }}</span>
                </div>
            </button>

            @role('admin')
            <button @click="setTab('available')" class="main-tab-shape main-tab main-tab-inactive"
                :class="{ 'main-tab-active': activeTab === 'available', 'main-tab-inactive': activeTab !== 'available' }">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Available Suppliers
                    <span class="main-tab-badge main-tab-badge-amber">{{ $otherSuppliers->count() }}</span>
                </div>
            </button>
            @endrole
        </div>

        <div x-show="activeTab === 'suppliers'" class="main-tab-content">
            <x-search
                :action="route('suppliers.index')"
                searchParam="q"
                placeholder="Quick search for supplier" />

        @unlessrole('admin')
            <div class="mt-3 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg px-4 py-2.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>To activate, deactivate, or add new suppliers, please contact your administrator.</span>
            </div>
        @endunlessrole
        <div class="dataTable-wrapper mt-4">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500">
                            <th>Supplier Name</th>
                            <th>Services</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($suppliers->isEmpty())
                            <tr>
                                <td colspan="4" class="text-center p-3 text-gray-500 dark:text-gray-300 font-semibold">
                                    No suppliers found
                                </td>
                            </tr>
                        @else
                            @foreach ($suppliers as $supplier)
                                @php
                                    $supplierCompany = $supplier->companies->first();
                                @endphp
                                <tr class="transition-colors duration-150 cursor-pointer p-3 text-sm font-semibold text-gray-600 dark:text-gray-300">
                                    <td>
                                        <a href="{{ route('suppliers.show', $supplier->id) }}" class="hover:text-blue-600">
                                            {{ $supplier->name }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-1 flex-wrap">
                                            @foreach(['has_hotel' => 'Hotel', 'has_flight' => 'Flight', 'has_visa' => 'Visa', 'has_insurance' => 'Insurance', 'has_tour' => 'Tour', 'has_cruise' => 'Cruise', 'has_car' => 'Car', 'has_rail' => 'Rail', 'has_esim' => 'eSIM', 'has_event' => 'Event', 'has_lounge' => 'Lounge', 'has_ferry' => 'Ferry'] as $field => $label)
                                                @if($supplier->$field)
                                                    <span class="service-badge px-1.5 py-0.5 rounded text-xs font-medium" style="--hue: {{ crc32($label) % 360 }}">{{ $label }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        @if($supplierCompany && $supplierCompany->pivot->is_active)
                                            <span class="px-2 py-1 rounded text-xs font-medium border border-green-500 text-green-600 bg-green-50 dark:bg-green-900/30 dark:text-green-400 dark:border-green-600">Active</span>
                                        @else
                                            <span class="px-2 py-1 rounded text-xs font-medium border border-red-400 text-red-500 bg-red-50 dark:bg-red-900/30 dark:text-red-400 dark:border-red-600">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            @if(auth()->user()->role_id == \App\Models\Role::ADMIN)
                                                @if($supplierCompany && $supplierCompany->pivot->is_active)
                                                    <a data-tooltip-left="Deactivate supplier"
                                                        class="p-2 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 hover:shadow-sm dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/40
                                                        transition-all" href="{{ route('supplier-company.deactivate', ['supplier_id' => $supplier->id, 'company_id' => $companyId]) }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <rect x="1" y="5" width="22" height="14" rx="7" ry="7"/>
                                                            <circle cx="16" cy="12" r="3"/>
                                                        </svg>
                                                    </a>
                                                @else
                                                    <a data-tooltip-left="Activate supplier"
                                                        class="p-2 rounded-lg bg-green-50 text-green-500 hover:bg-green-100 hover:shadow-sm dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-900/40 transition-all"
                                                        href="{{ route('supplier-company.activate', ['supplier_id' => $supplier->id, 'company_id' => $companyId]) }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <rect x="1" y="5" width="22" height="14" rx="7" ry="7"/>
                                                            <circle cx="8" cy="12" r="3"/>
                                                        </svg>
                                                    </a>
                                                @endif

                                                <div x-data="{ manageCompaniesModal: false }">
                                                    <button type="button" data-tooltip-left="Manage companies"
                                                        class="p-2 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 hover:shadow-sm dark:bg-indigo-900/20 dark:text-indigo-400 dark:hover:bg-indigo-900/40 transition-all"
                                                        @click="manageCompaniesModal = true">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M3 21h18"/>
                                                            <path d="M5 21V7l8-4v18"/>
                                                            <path d="M19 21V11l-6-4"/>
                                                            <path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/>
                                                        </svg>
                                                    </button>

                                                    <div x-show="manageCompaniesModal" x-cloak
                                                        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
                                                        <div @click.away="manageCompaniesModal = false"
                                                            class="bg-white dark:bg-gray-800 w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-lg shadow-xl mx-4 text-left custom-scrollbar">
                                                            <div class="sticky top-0 bg-white dark:bg-gray-800 z-10 p-5 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between">
                                                                <div>
                                                                    <h1 class="text-lg md:text-xl font-semibold text-gray-900 dark:text-gray-100">Manage Companies</h1>
                                                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">Activate or deactivate <strong>{{ $supplier->name }}</strong> for each company</p>
                                                                </div>
                                                                <button type="button" @click="manageCompaniesModal = false"
                                                                    class="p-2 -mr-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M6.225 4.811a1 1 0 0 1 1.414 0L12 9.172l4.361-4.361a1 1 0 1 1 1.414 1.414L13.414 10.586l4.361 4.361a1 1 0 0 1-1.414 1.414L12 12l-4.361 4.361a1 1 0 0 1-1.414-1.414l4.361-4.361-4.361-4.361a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                            <div class="p-5 space-y-3">
                                                                @foreach($companies as $comp)
                                                                    @php
                                                                        $pivotRecord = $supplier->supplierCompanies->where('company_id', $comp->id)->first();
                                                                        $isLinked = $pivotRecord !== null;
                                                                        $isCompanyActive = $isLinked && $pivotRecord->is_active;
                                                                    @endphp
                                                                    <div class="flex items-center justify-between p-3 rounded-lg border
                                                                        {{ $isCompanyActive ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700' }}">
                                                                        <div class="flex items-center gap-3">
                                                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                                                                                {{ $isCompanyActive ? 'bg-green-200 text-green-700 dark:bg-green-800 dark:text-green-300' : 'bg-gray-200 text-gray-500 dark:bg-gray-600 dark:text-gray-400' }}">
                                                                                {{ strtoupper(substr($comp->name, 0, 2)) }}
                                                                            </div>
                                                                            <div>
                                                                                <p class="font-semibold text-sm text-gray-800 dark:text-gray-200">{{ $comp->name }}</p>
                                                                                <p class="text-xs {{ $isCompanyActive ? 'text-green-600 dark:text-green-400' : ($isLinked ? 'text-red-500 dark:text-red-400' : 'text-gray-400 dark:text-gray-500') }}">
                                                                                    {{ $isCompanyActive ? 'Active' : ($isLinked ? 'Inactive' : 'Not linked') }}
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                        @if($isCompanyActive)
                                                                            <a href="{{ route('supplier-company.deactivate', ['supplier_id' => $supplier->id, 'company_id' => $comp->id]) }}"
                                                                                class="px-3 py-1.5 text-xs font-medium rounded-md border border-red-300 text-red-600 bg-white hover:bg-red-50 dark:bg-red-900/30 dark:text-red-400 dark:border-red-700 dark:hover:bg-red-900/50 transition-all">
                                                                                Deactivate
                                                                            </a>
                                                                        @else
                                                                            <a href="{{ route('supplier-company.activate', ['supplier_id' => $supplier->id, 'company_id' => $comp->id]) }}"
                                                                                class="px-3 py-1.5 text-xs font-medium rounded-md border border-green-300 text-green-600 bg-white hover:bg-green-50 dark:bg-green-900/30 dark:text-green-400 dark:border-green-700 dark:hover:bg-green-900/50 transition-all">
                                                                                Activate
                                                                            </a>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                            <div class="sticky bottom-0 bg-white dark:bg-gray-800 p-4 border-t border-gray-200 dark:border-gray-700">
                                                                <button type="button" @click="manageCompaniesModal = false"
                                                                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                                    Close
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div x-data="{ editSupplierModal: false }">
                                                    <button type="button" data-tooltip-left="Edit supplier"
                                                        class="p-2 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 hover:shadow-sm dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/40 transition-all" @click="editSupplierModal = true">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42" opacity=".5" />
                                                        </svg>
                                                    </button>
                                                    <div x-show="editSupplierModal" x-cloak
                                                        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
                                                        <div @click.away="editSupplierModal = false" class="bg-white w-1/2 max-h-[90vh] overflow-y-auto rounded-md shadow-md text-left custom-scrollbar">
                                                            <div class="sticky top-0 bg-white z-10 p-5 border-b border-gray-200 flex items-start justify-between">
                                                                <div>
                                                                    <h1 class="text-lg md:text-xl font-semibold text-gray-900">Edit Supplier</h1>
                                                                    <p class="mt-1 text-sm text-gray-500 italic">Edit the details of the supplier for accurate information</p>
                                                                </div>
                                                                <button type="button" @click="editSupplierModal = false"
                                                                    class="p-2 -mr-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M6.225 4.811a1 1 0 0 1 1.414 0L12 9.172l4.361-4.361a1 1 0 1 1 1.414 1.414L13.414 10.586l4.361 4.361a1 1 0 0 1-1.414 1.414L12 12l-4.361 4.361a1 1 0 0 1-1.414-1.414l4.361-4.361-4.361-4.361a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                            <div class="p-5">
                                                                <form action="{{ route('suppliers.update', $supplier->id) }}" method="POST" class="flex flex-col gap-2 mb-2">
                                                                    @csrf
                                                                    @method('PUT')
                                                                    <div class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                                        <div>
                                                                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier Name</label>
                                                                            <input type="text" name="name" value="{{ $supplier->name }}" placeholder="Supplier Name"
                                                                                class="h-10 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                                                        </div>
                                                                        <div>
                                                                            <label for="auth_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Authentication Type</label>
                                                                            <select name="auth_type"
                                                                                class="h-10 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                                                                                required>
                                                                                <option value="basic" {{ old('auth_type', $supplier->auth_type) === 'basic' ? 'selected' : '' }}>Basic</option>
                                                                                <option value="oauth" {{ old('auth_type', $supplier->auth_type) === 'oauth' ? 'selected' : '' }}>OAuth</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 mr-3 whitespace-nowrap shrink-0">
                                                                        Country of Origin
                                                                    </span>
                                                                    <div>
                                                                        <x-searchable-dropdown
                                                                            name="country_id"
                                                                            :items="$countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name])"
                                                                            placeholder="Select Country"
                                                                            :selectedId="$supplier->country->id"
                                                                            :selectedName="$supplier->country->name" />
                                                                    </div>

                                                                    @include('suppliers.partials.service-toggles', ['supplier' => $supplier])

                                                                    @if($supplier->companies->isNotEmpty())
                                                                        <hr class="my-4">
                                                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Auto Extra Surcharge</label>
                                                                        <div id="auto-surcharge-wrapper" class="space-y-4">
                                                                            @foreach($supplier->companies as $company)
                                                                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 shadow-sm">
                                                                                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 rounded-t-lg">
                                                                                        <h3 class="font-semibold text-gray-800 dark:text-gray-200 text-base">{{ $company->name }}</h3>
                                                                                        <button type="button"
                                                                                            class="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1"
                                                                                            onclick="addSurchargeRow({{ $company->pivot->id }})">
                                                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                                                            </svg>
                                                                                            Add Label
                                                                                        </button>
                                                                                    </div>
                                                                                    <div class="p-4 space-y-3" id="company-surcharge-{{ $company->pivot->id }}">
                                                                                        @if($company->pivot && $company->pivot->supplierSurcharges->isNotEmpty())
                                                                                            @foreach($company->pivot->supplierSurcharges as $surcharge)
                                                                                                @include('suppliers.partials.surcharge-row', [
                                                                                                    'surcharge' => $surcharge,
                                                                                                    'pivotId' => $company->pivot->id,
                                                                                                ])
                                                                                            @endforeach
                                                                                        @else
                                                                                            <div class="text-sm text-gray-500 italic">No surcharges yet - click "Add Label" to create one.</div>
                                                                                        @endif
                                                                                    </div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif

                                                                    <input type="hidden" id="deleted_surcharges_{{ $supplier->id }}" name="deleted_surcharges" value="">
                                                                    <div class="mt-5 flex items-center justify-between">
                                                                        <button type="button" @click="editSupplierModal = false"
                                                                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-300 shadow-md hover:bg-gray-50 dark:hover:bg-gray-700">
                                                                            Cancel
                                                                        </button>
                                                                        <button type="submit"
                                                                            class="py-2 px-6 bg-blue-600 text-white rounded-md shadow-md hover:bg-blue-700">
                                                                            Update
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <div x-data="{ credentialModal: false }">
                                                    <button type="button" data-tooltip-left="Edit credentials"
                                                        class="p-2 rounded-lg bg-green-50 text-green-500 hover:bg-green-100 hover:shadow-sm dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-900/40 transition-all"
                                                        @click="credentialModal = true">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                                        </svg>
                                                    </button>
                                                    @include('suppliers.partials.supplier_credential', ['supplier' => $supplier])
                                                </div>

                                                <a data-tooltip-left="Get all tasks"
                                                    class="p-2 rounded-lg bg-purple-50 text-purple-600 hover:bg-purple-100 hover:shadow-sm dark:bg-purple-900/20 dark:text-purple-400 dark:hover:bg-purple-900/40 transition-all"
                                                    href="{{ route('tasks.supplier', $supplier->id) }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                        <polyline points="14 2 14 8 20 8"/>
                                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                                    </svg>
                                                </a>

                                                <div x-data="{ chargesModal: false }">
                                                    <button type="button" data-tooltip-left="Supplier charges"
                                                        class="p-2 rounded-lg bg-amber-50 text-amber-500 hover:bg-amber-100 hover:shadow-sm dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/40 transition-all"
                                                        @click="chargesModal = true">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                            <circle cx="12" cy="12" r="10" />
                                                            <path d="M8 15c0 2 2 3 4 3s4-1 4-3-2-3-4-3-4-1-4-3 2-3 4-3 4 1 4 3" />
                                                            <path d="M12 6v12" />
                                                        </svg>
                                                    </button>

                                                    <div x-show="chargesModal" x-cloak
                                                        class="fixed inset-0 z-50 flex items-center justify-center"
                                                        x-transition:enter="transition ease-out duration-300"
                                                        x-transition:enter-start="opacity-0"
                                                        x-transition:enter-end="opacity-100"
                                                        x-transition:leave="transition ease-in duration-200"
                                                        x-transition:leave-start="opacity-100"
                                                        x-transition:leave-end="opacity-0">
                                                        <div class="fixed inset-0 bg-gray-900 bg-opacity-50" @click="chargesModal = false"></div>
                                                        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto z-10 mx-4 text-left custom-scrollbar"
                                                            @click.stop>
                                                            <div class="flex items-center justify-between p-4 border-b dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                                                                <div>
                                                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Supplier Charges - {{ $supplier->name }}</h3>
                                                                    <p class="text-sm text-gray-500 dark:text-gray-400">Configure surcharges for this supplier</p>
                                                                </div>
                                                                <button @click="chargesModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                            <div class="p-4">
                                                                <form action="{{ route('suppliers.update.surcharges', $supplier->id) }}" method="POST" id="surchargeForm-{{ $supplier->id }}">
                                                                    @csrf
                                                                    <div id="auto-surcharge-wrapper" class="space-y-4">
                                                                        @foreach($supplier->companies as $company)
                                                                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 shadow-sm">
                                                                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 rounded-t-lg">
                                                                                <h3 class="font-semibold text-gray-800 dark:text-gray-200 text-base">{{ $company->name }}</h3>
                                                                                <button type="button"
                                                                                    class="text-sm text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1"
                                                                                    onclick="addSurchargeRow({{ $company->pivot->id }})">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                                                    </svg>
                                                                                    Add Label
                                                                                </button>
                                                                            </div>
                                                                            <div class="p-4 space-y-3" id="company-surcharge-{{ $company->pivot->id }}">
                                                                                @if($company->pivot && $company->pivot->supplierSurcharges->isNotEmpty())
                                                                                    @foreach($company->pivot->supplierSurcharges as $surcharge)
                                                                                    @include('suppliers.partials.surcharge-row', [
                                                                                        'surcharge' => $surcharge,
                                                                                        'pivotId' => $company->pivot->id,
                                                                                    ])
                                                                                    @endforeach
                                                                                @else
                                                                                <div class="text-sm text-gray-500 italic">No surcharges yet - click "Add Label" to create one.</div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                        @endforeach
                                                                    </div>
                                                                    <input type="hidden" name="deleted_surcharges" value="">
                                                                </form>
                                                            </div>
                                                            <div class="flex items-center justify-between gap-3 p-4 sticky bottom-0 bg-white dark:bg-gray-800">
                                                                <button type="button" @click="chargesModal = false"
                                                                    class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600">
                                                                    Cancel
                                                                </button>
                                                                <button type="submit" form="surchargeForm-{{ $supplier->id }}"
                                                                    class="px-4 py-2 text-sm text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                                                    Save Changes
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            <a data-tooltip-left="View supplier"
                                                class="p-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 hover:shadow-sm dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/40 transition-all"
                                                href="{{ route('suppliers.show', $supplier->id) }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                    <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                                    <path d="M12 4c-4.182 0-7.028 2.5-8.725 4.704C2.425 9.81 2 10.361 2 12c0 1.64.425 2.191 1.275 3.296C4.972 17.5 7.818 20 12 20s7.028-2.5 8.725-4.704C21.575 14.19 22 13.639 22 12c0-1.64-.425-2.191-1.275-3.296C19.028 6.5 16.182 4 12 4Z"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            <x-pagination :data="$suppliers" />
        </div>
        </div>

        @role('admin')
        <div x-show="activeTab === 'available'" class="main-tab-content">
            <div class="main-section-header">
                <div>
                    <h3 class="main-section-title">Available Suppliers</h3>
                    <p class="main-section-subtitle">{{ $otherSuppliers->count() }} {{ Str::plural('supplier', $otherSuppliers->count()) }} available for activation</p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-4 py-2.5 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>These suppliers have been created but are not yet assigned to your company. Click <strong>Activate</strong> to add them to your company.</span>
            </div>

            @if($otherSuppliers->isNotEmpty())
            <div class="dataTable-wrapper">
            <div class="dataTable-container h-max">
                <table class="table-hover whitespace-nowrap dataTable-table">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500">
                            <th>Supplier Name</th>
                            <th>Services</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($otherSuppliers as $supplier)
                            <tr class="transition-colors duration-150 p-3 text-sm font-semibold text-gray-600 dark:text-gray-300">
                                <td>{{ $supplier->name }}</td>
                                <td>
                                    <div class="flex items-center gap-1 flex-wrap">
                                        @foreach(['has_hotel' => 'Hotel', 'has_flight' => 'Flight', 'has_visa' => 'Visa', 'has_insurance' => 'Insurance', 'has_tour' => 'Tour', 'has_cruise' => 'Cruise', 'has_car' => 'Car', 'has_rail' => 'Rail', 'has_esim' => 'eSIM', 'has_event' => 'Event', 'has_lounge' => 'Lounge', 'has_ferry' => 'Ferry'] as $field => $label)
                                            @if($supplier->$field)
                                                <span class="service-badge px-1.5 py-0.5 rounded text-xs font-medium" style="--hue: {{ crc32($label) % 360 }}">{{ $label }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="px-2 py-1 rounded text-xs font-medium border border-gray-300 text-gray-500 bg-gray-50 dark:bg-gray-700 dark:text-gray-400 dark:border-gray-600">Not Activated</span>
                                </td>
                                <td class="text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        {{-- Activate --}}
                                        <a data-tooltip-left="Activate supplier"
                                            class="p-2 rounded-lg bg-green-50 text-green-500 hover:bg-green-100 hover:shadow-sm dark:bg-green-900/20 dark:text-green-400 dark:hover:bg-green-900/40 transition-all"
                                            href="{{ route('supplier-company.activate', ['supplier_id' => $supplier->id, 'company_id' => $companyId]) }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="1" y="5" width="22" height="14" rx="7" ry="7"/>
                                                <circle cx="8" cy="12" r="3"/>
                                            </svg>
                                        </a>

                                        {{-- Manage Companies --}}
                                        <div x-data="{ manageCompaniesModal: false }">
                                            <button type="button" data-tooltip-left="Manage companies"
                                                class="p-2 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 hover:shadow-sm dark:bg-indigo-900/20 dark:text-indigo-400 dark:hover:bg-indigo-900/40 transition-all"
                                                @click="manageCompaniesModal = true">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M3 21h18"/>
                                                    <path d="M5 21V7l8-4v18"/>
                                                    <path d="M19 21V11l-6-4"/>
                                                    <path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/>
                                                </svg>
                                            </button>

                                            <div x-show="manageCompaniesModal" x-cloak
                                                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
                                                <div @click.away="manageCompaniesModal = false"
                                                    class="bg-white dark:bg-gray-800 w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-lg shadow-xl mx-4 text-left custom-scrollbar">
                                                    <div class="sticky top-0 bg-white dark:bg-gray-800 z-10 p-5 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between">
                                                        <div>
                                                            <h1 class="text-lg md:text-xl font-semibold text-gray-900 dark:text-gray-100">Manage Companies</h1>
                                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">Activate or deactivate <strong>{{ $supplier->name }}</strong> for each company</p>
                                                        </div>
                                                        <button type="button" @click="manageCompaniesModal = false"
                                                            class="p-2 -mr-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M6.225 4.811a1 1 0 0 1 1.414 0L12 9.172l4.361-4.361a1 1 0 1 1 1.414 1.414L13.414 10.586l4.361 4.361a1 1 0 0 1-1.414 1.414L12 12l-4.361 4.361a1 1 0 0 1-1.414-1.414l4.361-4.361-4.361-4.361a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="p-5 space-y-3">
                                                        @foreach($companies as $comp)
                                                            @php
                                                                $pivotRecord = $supplier->supplierCompanies->where('company_id', $comp->id)->first();
                                                                $isLinked = $pivotRecord !== null;
                                                                $isCompanyActive = $isLinked && $pivotRecord->is_active;
                                                            @endphp
                                                            <div class="flex items-center justify-between p-3 rounded-lg border
                                                                {{ $isCompanyActive ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700' }}">
                                                                <div class="flex items-center gap-3">
                                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                                                                        {{ $isCompanyActive ? 'bg-green-200 text-green-700 dark:bg-green-800 dark:text-green-300' : 'bg-gray-200 text-gray-500 dark:bg-gray-600 dark:text-gray-400' }}">
                                                                        {{ strtoupper(substr($comp->name, 0, 2)) }}
                                                                    </div>
                                                                    <div>
                                                                        <p class="font-semibold text-sm text-gray-800 dark:text-gray-200">{{ $comp->name }}</p>
                                                                        <p class="text-xs {{ $isCompanyActive ? 'text-green-600 dark:text-green-400' : ($isLinked ? 'text-red-500 dark:text-red-400' : 'text-gray-400 dark:text-gray-500') }}">
                                                                            {{ $isCompanyActive ? 'Active' : ($isLinked ? 'Inactive' : 'Not linked') }}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                @if($isCompanyActive)
                                                                    <a href="{{ route('supplier-company.deactivate', ['supplier_id' => $supplier->id, 'company_id' => $comp->id]) }}"
                                                                        class="px-3 py-1.5 text-xs font-medium rounded-md border border-red-300 text-red-600 bg-white hover:bg-red-50 dark:bg-red-900/30 dark:text-red-400 dark:border-red-700 dark:hover:bg-red-900/50 transition-all">
                                                                        Deactivate
                                                                    </a>
                                                                @else
                                                                    <a href="{{ route('supplier-company.activate', ['supplier_id' => $supplier->id, 'company_id' => $comp->id]) }}"
                                                                        class="px-3 py-1.5 text-xs font-medium rounded-md border border-green-300 text-green-600 bg-white hover:bg-green-50 dark:bg-green-900/30 dark:text-green-400 dark:border-green-700 dark:hover:bg-green-900/50 transition-all">
                                                                        Activate
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <div class="sticky bottom-0 bg-white dark:bg-gray-800 p-4 border-t border-gray-200 dark:border-gray-700">
                                                        <button type="button" @click="manageCompaniesModal = false"
                                                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                            Close
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Edit Supplier --}}
                                        <div x-data="{ editSupplierModal: false }">
                                            <button type="button" data-tooltip-left="Edit supplier"
                                                class="p-2 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 hover:shadow-sm dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/40 transition-all"
                                                @click="editSupplierModal = true">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42" opacity=".5" />
                                                </svg>
                                            </button>
                                            <div x-show="editSupplierModal" x-cloak
                                                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
                                                <div @click.away="editSupplierModal = false" class="bg-white dark:bg-gray-800 w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-md shadow-md text-left custom-scrollbar">
                                                    <div class="sticky top-0 bg-white dark:bg-gray-800 z-10 p-5 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between">
                                                        <div>
                                                            <h1 class="text-lg md:text-xl font-semibold text-gray-900 dark:text-gray-100">Edit Supplier</h1>
                                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">Edit the details of the supplier</p>
                                                        </div>
                                                        <button type="button" @click="editSupplierModal = false"
                                                            class="p-2 -mr-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M6.225 4.811a1 1 0 0 1 1.414 0L12 9.172l4.361-4.361a1 1 0 1 1 1.414 1.414L13.414 10.586l4.361 4.361a1 1 0 0 1-1.414 1.414L12 12l-4.361 4.361a1 1 0 0 1-1.414-1.414l4.361-4.361-4.361-4.361a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="p-5">
                                                        <form action="{{ route('suppliers.update', $supplier->id) }}" method="POST" class="flex flex-col gap-2 mb-2">
                                                            @csrf
                                                            @method('PUT')
                                                            <div class="mb-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                                <div>
                                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier Name</label>
                                                                    <input type="text" name="name" value="{{ $supplier->name }}" placeholder="Supplier Name"
                                                                        class="h-10 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                                                                </div>
                                                                <div>
                                                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Authentication Type</label>
                                                                    <select name="auth_type"
                                                                        class="h-10 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                                                                        required>
                                                                        <option value="basic" {{ old('auth_type', $supplier->auth_type) === 'basic' ? 'selected' : '' }}>Basic</option>
                                                                        <option value="oauth" {{ old('auth_type', $supplier->auth_type) === 'oauth' ? 'selected' : '' }}>OAuth</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 mr-3 whitespace-nowrap shrink-0">Country of Origin</span>
                                                            <div>
                                                                <x-searchable-dropdown
                                                                    name="country_id"
                                                                    :items="$countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name])"
                                                                    placeholder="Select Country"
                                                                    :selectedId="$supplier->country->id ?? null"
                                                                    :selectedName="$supplier->country->name ?? ''" />
                                                            </div>

                                                            @include('suppliers.partials.service-toggles', ['supplier' => $supplier])

                                                            <div class="mt-5 flex items-center justify-between">
                                                                <button type="button" @click="editSupplierModal = false"
                                                                    class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-300 shadow-md hover:bg-gray-50 dark:hover:bg-gray-700">
                                                                    Cancel
                                                                </button>
                                                                <button type="submit"
                                                                    class="py-2 px-6 bg-blue-600 text-white rounded-md shadow-md hover:bg-blue-700">
                                                                    Update
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
            <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <p class="text-base font-semibold text-gray-500 dark:text-gray-400">No available suppliers</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">All suppliers are already assigned to your company</p>
            </div>
        @endif
        </div>
        @endrole
    </div>

    <script>
        function addSurchargeRow(supplierCompanyId) {
            const container = document.getElementById('company-surcharge-' + supplierCompanyId);
            const key = 'new_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);

            const wrapper = document.createElement('div');
            wrapper.className = 'border border-gray-200 rounded-lg p-3 mb-2 bg-white shadow-sm surcharge-row-wrapper';
            wrapper.dataset.surchargeKey = key;
            wrapper.setAttribute('x-data', "{ chargeMode: 'task' }");

            wrapper.innerHTML = `
                <div class="flex items-center gap-3">
                    <input type="hidden" name="surcharge_id[${supplierCompanyId}][]" value="">
                    <input type="text" name="surcharge_label[${supplierCompanyId}][${key}]"
                        placeholder="Label"
                        class="flex-1 border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                    <input type="number" name="surcharge_amount[${supplierCompanyId}][${key}]"
                        min="0" step="0.001" placeholder="Amount"
                        class="w-32 border border-gray-300 rounded-md px-3 py-1.5 text-sm text-right focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                    <button type="button" class="text-gray-400 hover:text-red-500" onclick="removeSurchargeRow(this)" title="Remove">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="mt-2 flex items-center flex-wrap gap-x-3 gap-y-1 text-sm mt-8">
                    <label class="text-gray-700 whitespace-nowrap">Charge Mode:</label>
                    <select name="charge_mode[${supplierCompanyId}][${key}]"
                        x-model="chargeMode"
                        class="min-w-[8rem] border border-gray-300 rounded-md px-1.5 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                        <option value="task">Task-wise</option>
                        <option value="reference">Reference-wise</option>
                    </select>
                </div>
                <div x-show="chargeMode === 'task'" x-cloak class="mt-4 border-t pt-3">
                    <div class="flex flex-wrap items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-800 mb-2 md:mb-0">Task Rules</h4>
                        <div class="flex flex-wrap items-center gap-3 rounded-md px-3 py-1.5">
                            ${['issued','reissued','confirmed','refund','void'].map(status => `
                                <label class="flex items-center text-xs gap-1 text-gray-700 whitespace-nowrap">
                                    <input type="checkbox" value="1" name="is_${status}[${supplierCompanyId}][${key}]"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    ${status.charAt(0).toUpperCase() + status.slice(1)}
                                </label>
                            `).join('')}
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-4 border-t pt-3 reference-section"
                    x-show="chargeMode === 'reference'" x-cloak>
                    <h4 class="text-sm font-semibold text-gray-800 mr-3">Reference Rules</h4>
                    <div class="flex items-center gap-2" id="reference-list-${key}">
                        <select name="charge_behavior[${key}][]"
                            class="min-w-[9rem] border border-gray-300 rounded-md px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                            <option value="single">Charge Once</option>
                            <option value="repetitive">Charge Repeatedly</option>
                        </select>
                    </div>
                </div>
            `;

            container.appendChild(wrapper);
        }

        function removeSurchargeRow(button) {
            const row = button.closest('.surcharge-row-wrapper');
            if (!row) return;

            const surchargeId = row.dataset.surchargeId;
            if (surchargeId) {
                const form = button.closest('form');
                const input = form?.querySelector('input[name="deleted_surcharges"]');
                if (input) {
                    const existing = input.value ? input.value.split(',') : [];
                    if (!existing.includes(surchargeId)) {
                        existing.push(surchargeId);
                    }
                    input.value = existing.join(',');
                }
            }

            row.remove();
        }
    </script>

    <style>
        .service-badge {
            background: hsl(var(--hue), 80%, 92%);
            color: hsl(var(--hue), 70%, 35%);
        }
        .dark .service-badge {
            background: hsl(var(--hue), 50%, 22%);
            color: hsl(var(--hue), 80%, 75%);
        }
    </style>
</x-app-layout>