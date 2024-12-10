<x-app-layout>


    <div>

        <!-- first row -->
        <div class="mb-5">
            <div class="flex space-x-2">
                <div class="text-4xl font-bold text-gray-800">34</div>
                <h3 class="text-sm font-medium text-gray-500 mt-5">Companies</h3>

                <div class="relative">
                    <div class="bg-lime-200 absolute -top-2 -right-3 text-xs font-bold text-gray-900 
                                    rounded-full px-2 py-0.5">+3
                    </div>
                </div>
            </div>

        </div>




        <div class="w-full flex gap-5 mb-5">
            <div class="w-[95%]">
                <!-- table -->
                <div class="overflow-x-auto bg-white rounded-lg shadow-md p-4">
                    <table class="min-w-full border-collapse">
                        <thead class="bg-gray-200 text-left text-gray-600 text-sm uppercase font-bold">
                            <tr>
                                <th class="px-4 py-3">
                                    <input type="checkbox" class="form-checkbox" />
                                </th>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Region</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        @foreach ($companies as $company)
                        <tbody class="text-gray-700">
                            <tr class="border-b">
                                <td class="px-4 py-3">
                                    <input type="checkbox" class="form-checkbox" />
                                </td>
                                <td class="px-4 py-3">{{ $company->name }}</td>
                                <td class="px-4 py-3">{{ $company->email }}</td>
                                <td class="px-4 py-3">{{ $company->phone }}</td>
                                <!-- code -->
                                <td class="px-4 py-3">
                                    <span class="text-xs font-semibold text-purple-700 bg-purple-100 rounded-full px-2 py-0.5">{{ $company->code }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $company->nationality ? $company->nationality->name : 'N/A' }}</td>

                                <td class="px-4 py-3">
                                    <svg id="toggle-{{ $company->id }}" class="toggle-svg cursor-pointer"
                                        viewBox="0 0 44 24" width="44" height="24"
                                        onclick="toggleStatus({{ $company->id }}, '{{ $company->status }}')"
                                        data-status="{{ $company->status }}">
                                        <rect id="rect-{{ $company->id }}" width="44" height="24" rx="12"
                                            fill="{{ $company->status == 1 ? '#00ab55' : '#ccc' }}"></rect>
                                        <circle id="circle-{{ $company->id }}"
                                            cx="{{ $company->status == 0 ? '32' : '12' }}" cy="12" r="10" fill="white">
                                        </circle>
                                    </svg>
                                </td>
                                <td class="px-4 py-3">
                                    actions
                                </td>

                            </tr>


                        </tbody>
                        @endforeach
                    </table>
                </div>


                <!--./ table -->
            </div>
            <div class="w-[5%]">
                <div class="relative h-[200px]"> <!-- Adjust the height as needed -->
                    <div class="absolute text-white top-2 right-2 z-10 flex flex-col space-y-4">
                        <div class="w-12 h-12 bg-black rounded-full shadow-md flex items-center justify-center">
                            <!-- Icon 1 -->
                            1
                        </div>
                        <div class="w-12 h-12 bg-black rounded-full shadow-md flex items-center justify-center">
                            <!-- Icon 2 -->
                            2
                        </div>
                        <div class="w-12 h-12 bg-black rounded-full shadow-md flex items-center justify-center">
                            <!-- Icon 3 -->
                            3
                        </div>
                    </div>
                </div>
            </div>

        </div>





    </div>














</x-app-layout>