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
                <a class="h-full flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none">
                    <svg class="w-5 h-5 mr-2 text-white dark:text-gray-300" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-white dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Add Supplier</span>
                </a>
            </div>
        </div>
    </div>
    <div class="flex justify-start items-center my-5 p-2 bg-white dark:bg-dark shadow-md rounded-md">
        <div class="mr-2">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 7V13" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" />
                <circle cx="12" cy="16" r="1" fill="#ff0000" />
                <path d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" />
            </svg>
        </div>
        <span class="">Activate supplier to allow the system users to request API from the supplier</span>
    </div>
    <div class="max-h-160 overflow-y-auto custom-scrollbar">
        <table class="">
            <thead class="sticky top-0">
                <tr>
                    <th class="px-4 py-2">Supplier Name</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-dark rounded-md p-2" id="suppliersTable">
                @foreach ($suppliers as $supplier)
                <tr class=" hover:bg-gray-200 dark:hover:bg-gray-600">
                    <td class="px-4 py-2 border dark:border-gray-600 cursor-pointer">
                        <a href="{{ route('suppliers.show', $supplier->id) }}">
                            <span class="font-bold">» {{ $supplier->name }}</span><br>
                        </a>
                    </td>
                    <td class="px-4 py-2 border dark:border-gray-600 text-center space-x-2">
                        <button class="bg-green-500 text-white px-2 py-1 rounded">Activate</button>
                        <button class="bg-gray-300 text-gray-700 px-2 py-1 rounded">Deactivate</button>
                        @if($supplier->named_route != null)
                        <a href="{{ route($supplier->named_route) }}" class="bg-gray-300 text-gray-700 px-2 py-1 rounded">Configure</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
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

                let url = basedUrl + '/suppliers/' + supplier.route + '/index';

                const tr = document.createElement('tr');
                tr.classList.add('hover:bg-gray-200', 'dark:hover:bg-gray-600');
                tr.innerHTML = `
                    <td class="px-4 py-2 border dark:border-gray-600 cursor-pointer">
                        <a href="{{ route('suppliers.show', $supplier->id) }}">
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
    </script>
</x-app-layout>