<x-app-layout>
    <style>
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
    <div>
        <!-- Breadcrumbs -->
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline"> Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <a href="{{ route('agents.index') }}" class="customBlueColor hover:underline">Agents List</a>

            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>Agent Details </span>
            </li>
        </ul>
        <!-- ./Breadcrumbs -->

        <!-- details section -->
        <div class="md:flex gap-2">
            <!-- Agents Overview -->
            <div class="panel w-[100%] md:w-[75%]">
                <div class="mb-5 flex justify-between items-center">
                    <h5 class="text-lg font-semibold dark:text-white-light">
                        <span class="customBlueColor">Clients</span> List
                    </h5>
                </div>
                <div>
                    @if($clients->isEmpty())
                    <p class="text-gray-600">No clients for this agent.</p>
                    @else
                    <div class="max-h-72 overflow-y-auto custom-scrollbar">
                        <table class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700">
                            <thead>
                                <tr>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Client Name</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Paid (KWD)</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Pending (KWD)</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Email</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Phone</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Address</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clients as $client)
                                <tr>
                                    <td class="py-4 px-6 border-b">{{ $client->full_name }}</td>
                                    <td class="py-4 px-6 border-b">
                                        <x-paid>
                                            {{ $client->paid }}
                                        </x-paid>
                                    </td>
                                    <td class="py-4 px-6 border-b">
                                        <x-unpaid>
                                            {{ $client->unpaid }}
                                        </x-unpaid>
                                    </td>
                                    <td class="py-4 px-6 border-b">{{ $client->email }}</td>
                                    <td class="py-4 px-6 border-b">{{ $client->phone }}</td>
                                    <td class="py-4 px-6 border-b">{{ $client->address ?? 'Not Set' }}</td>
                                    <td class="py-4 px-6 border-b">
                                        <a href="{{ url('/clients/' . $client->id) }}" class="text-blue-500">View</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $clients->appends(['section' => 'clients'])->links() }}
                    </div>
                    @endif

                </div>
            </div>
            <!-- ./ Agents Overview -->

            <!-- Agent Details -->
            <div class="panel overflow-hidden border-0 p-0 w-[100%] md:w-[25%] mt-5 sm:mt-0
                {{ $agent->type_id == 1 ? 'max-h-[380px]' : ($agent->type_id != 2 ? 'max-h-[420px]' : 'max-h-[350px]') }}">
                <div class="h-full bg-gradient-to-r from-[#4361ee] to-[#160f6b] p-6">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center rounded-full bg-black/50 p-1 font-semibold text-white pr-3 ">
                            <x-application-logo
                                class="block h-8 w-8 rounded-full border-2 border-white/50 object-cover ltr:mr-1 rtl:ml-1" />
                            <h3 class="px-2">{{ $agent->name }}</h3>
                        </div>
                        <button type="button" onclick="EditAgentDetails()"
                            class="flex h-9 w-9 items-center justify-between rounded-md bg-black text-white hover:opacity-80 ltr:ml-auto rtl:mr-auto">
                            <svg class="m-auto h-6 w-6" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M12 8.25C9.92894 8.25 8.25 9.92893 8.25 12C8.25 14.0711 9.92894 15.75 12 15.75C14.0711 15.75 15.75 14.0711 15.75 12C15.75 9.92893 14.0711 8.25 12 8.25ZM9.75 12C9.75 10.7574 10.7574 9.75 12 9.75C13.2426 9.75 14.25 10.7574 14.25 12C14.25 13.2426 13.2426 14.25 12 14.25C10.7574 14.25 9.75 13.2426 9.75 12Z"
                                    fill="#F5F5F5" />
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M11.9747 1.25C11.5303 1.24999 11.1592 1.24999 10.8546 1.27077C10.5375 1.29241 10.238 1.33905 9.94761 1.45933C9.27379 1.73844 8.73843 2.27379 8.45932 2.94762C8.31402 3.29842 8.27467 3.66812 8.25964 4.06996C8.24756 4.39299 8.08454 4.66251 7.84395 4.80141C7.60337 4.94031 7.28845 4.94673 7.00266 4.79568C6.64714 4.60777 6.30729 4.45699 5.93083 4.40743C5.20773 4.31223 4.47642 4.50819 3.89779 4.95219C3.64843 5.14353 3.45827 5.3796 3.28099 5.6434C3.11068 5.89681 2.92517 6.21815 2.70294 6.60307L2.67769 6.64681C2.45545 7.03172 2.26993 7.35304 2.13562 7.62723C1.99581 7.91267 1.88644 8.19539 1.84541 8.50701C1.75021 9.23012 1.94617 9.96142 2.39016 10.5401C2.62128 10.8412 2.92173 11.0602 3.26217 11.2741C3.53595 11.4461 3.68788 11.7221 3.68786 12C3.68785 12.2778 3.53592 12.5538 3.26217 12.7258C2.92169 12.9397 2.62121 13.1587 2.39007 13.4599C1.94607 14.0385 1.75012 14.7698 1.84531 15.4929C1.88634 15.8045 1.99571 16.0873 2.13552 16.3727C2.26983 16.6469 2.45535 16.9682 2.67758 17.3531L2.70284 17.3969C2.92507 17.7818 3.11058 18.1031 3.28089 18.3565C3.45817 18.6203 3.64833 18.8564 3.89769 19.0477C4.47632 19.4917 5.20763 19.6877 5.93073 19.5925C6.30717 19.5429 6.647 19.3922 7.0025 19.2043C7.28833 19.0532 7.60329 19.0596 7.8439 19.1986C8.08452 19.3375 8.24756 19.607 8.25964 19.9301C8.27467 20.3319 8.31403 20.7016 8.45932 21.0524C8.73843 21.7262 9.27379 22.2616 9.94761 22.5407C10.238 22.661 10.5375 22.7076 10.8546 22.7292C11.1592 22.75 11.5303 22.75 11.9747 22.75H12.0252C12.4697 22.75 12.8407 22.75 13.1454 22.7292C13.4625 22.7076 13.762 22.661 14.0524 22.5407C14.7262 22.2616 15.2616 21.7262 15.5407 21.0524C15.686 20.7016 15.7253 20.3319 15.7403 19.93C15.7524 19.607 15.9154 19.3375 16.156 19.1985C16.3966 19.0596 16.7116 19.0532 16.9974 19.2042C17.3529 19.3921 17.6927 19.5429 18.0692 19.5924C18.7923 19.6876 19.5236 19.4917 20.1022 19.0477C20.3516 18.8563 20.5417 18.6203 20.719 18.3565C20.8893 18.1031 21.0748 17.7818 21.297 17.3969L21.3223 17.3531C21.5445 16.9682 21.7301 16.6468 21.8644 16.3726C22.0042 16.0872 22.1135 15.8045 22.1546 15.4929C22.2498 14.7697 22.0538 14.0384 21.6098 13.4598C21.3787 13.1586 21.0782 12.9397 20.7378 12.7258C20.464 12.5538 20.3121 12.2778 20.3121 11.9999C20.3121 11.7221 20.464 11.4462 20.7377 11.2742C21.0783 11.0603 21.3788 10.8414 21.6099 10.5401C22.0539 9.96149 22.2499 9.23019 22.1547 8.50708C22.1136 8.19546 22.0043 7.91274 21.8645 7.6273C21.7302 7.35313 21.5447 7.03183 21.3224 6.64695L21.2972 6.60318C21.0749 6.21825 20.8894 5.89688 20.7191 5.64347C20.5418 5.37967 20.3517 5.1436 20.1023 4.95225C19.5237 4.50826 18.7924 4.3123 18.0692 4.4075C17.6928 4.45706 17.353 4.60782 16.9975 4.79572C16.7117 4.94679 16.3967 4.94036 16.1561 4.80144C15.9155 4.66253 15.7524 4.39297 15.7403 4.06991C15.7253 3.66808 15.686 3.2984 15.5407 2.94762C15.2616 2.27379 14.7262 1.73844 14.0524 1.45933C13.762 1.33905 13.4625 1.29241 13.1454 1.27077C12.8407 1.24999 12.4697 1.24999 12.0252 1.25H11.9747ZM10.5216 2.84515C10.5988 2.81319 10.716 2.78372 10.9567 2.76729C11.2042 2.75041 11.5238 2.75 12 2.75C12.4762 2.75 12.7958 2.75041 13.0432 2.76729C13.284 2.78372 13.4012 2.81319 13.4783 2.84515C13.7846 2.97202 14.028 3.21536 14.1548 3.52165C14.1949 3.61826 14.228 3.76887 14.2414 4.12597C14.271 4.91835 14.68 5.68129 15.4061 6.10048C16.1321 6.51968 16.9974 6.4924 17.6984 6.12188C18.0143 5.9549 18.1614 5.90832 18.265 5.89467C18.5937 5.8514 18.9261 5.94047 19.1891 6.14228C19.2554 6.19312 19.3395 6.27989 19.4741 6.48016C19.6125 6.68603 19.7726 6.9626 20.0107 7.375C20.2488 7.78741 20.4083 8.06438 20.5174 8.28713C20.6235 8.50382 20.6566 8.62007 20.6675 8.70287C20.7108 9.03155 20.6217 9.36397 20.4199 9.62698C20.3562 9.70995 20.2424 9.81399 19.9397 10.0041C19.2684 10.426 18.8122 11.1616 18.8121 11.9999C18.8121 12.8383 19.2683 13.574 19.9397 13.9959C20.2423 14.186 20.3561 14.29 20.4198 14.373C20.6216 14.636 20.7107 14.9684 20.6674 15.2971C20.6565 15.3799 20.6234 15.4961 20.5173 15.7128C20.4082 15.9355 20.2487 16.2125 20.0106 16.6249C19.7725 17.0373 19.6124 17.3139 19.474 17.5198C19.3394 17.72 19.2553 17.8068 19.189 17.8576C18.926 18.0595 18.5936 18.1485 18.2649 18.1053C18.1613 18.0916 18.0142 18.045 17.6983 17.8781C16.9973 17.5075 16.132 17.4803 15.4059 17.8995C14.68 18.3187 14.271 19.0816 14.2414 19.874C14.228 20.2311 14.1949 20.3817 14.1548 20.4784C14.028 20.7846 13.7846 21.028 13.4783 21.1549C13.4012 21.1868 13.284 21.2163 13.0432 21.2327C12.7958 21.2496 12.4762 21.25 12 21.25C11.5238 21.25 11.2042 21.2496 10.9567 21.2327C10.716 21.2163 10.5988 21.1868 10.5216 21.1549C10.2154 21.028 9.97201 20.7846 9.84514 20.4784C9.80512 20.3817 9.77195 20.2311 9.75859 19.874C9.72896 19.0817 9.31997 18.3187 8.5939 17.8995C7.86784 17.4803 7.00262 17.5076 6.30158 17.8781C5.98565 18.0451 5.83863 18.0917 5.73495 18.1053C5.40626 18.1486 5.07385 18.0595 4.81084 17.8577C4.74458 17.8069 4.66045 17.7201 4.52586 17.5198C4.38751 17.314 4.22736 17.0374 3.98926 16.625C3.75115 16.2126 3.59171 15.9356 3.4826 15.7129C3.37646 15.4962 3.34338 15.3799 3.33248 15.2971C3.28921 14.9684 3.37828 14.636 3.5801 14.373C3.64376 14.2901 3.75761 14.186 4.0602 13.9959C4.73158 13.5741 5.18782 12.8384 5.18786 12.0001C5.18791 11.1616 4.73165 10.4259 4.06021 10.004C3.75769 9.81389 3.64385 9.70987 3.58019 9.62691C3.37838 9.3639 3.28931 9.03149 3.33258 8.7028C3.34348 8.62001 3.37656 8.50375 3.4827 8.28707C3.59181 8.06431 3.75125 7.78734 3.98935 7.37493C4.22746 6.96253 4.3876 6.68596 4.52596 6.48009C4.66055 6.27983 4.74468 6.19305 4.81093 6.14222C5.07395 5.9404 5.40636 5.85133 5.73504 5.8946C5.83873 5.90825 5.98576 5.95483 6.30173 6.12184C7.00273 6.49235 7.86791 6.51962 8.59394 6.10045C9.31998 5.68128 9.72896 4.91837 9.75859 4.12602C9.77195 3.76889 9.80512 3.61827 9.84514 3.52165C9.97201 3.21536 10.2154 2.97202 10.5216 2.84515Z"
                                    fill="#F5F5F5" />
                            </svg>
                        </button>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-white">
                            <p class="text-lg">Email</p>
                            <h5 class="text-base ml-auto">{{ $agent->email }}</h5>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-white">
                            <p class="text-lg">Phone</p>
                            <h5 class="text-base ml-auto">{{ $agent->phone_number }}</h5>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-white">
                            <p class="text-lg">Branch</p>
                            <h5 class="text-base ml-auto">{{ $agent->branch->name }}</h5>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-white">
                            <p class="text-lg">Company</p>
                            <h5 class="text-base ml-auto">{{ $agent->branch->company->name }}</h5>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-white">
                            <p class="text-lg">Type</p>
                            <h5 class="text-base ml-auto">{{ $agent->agentType->name }}</h5>
                        </div>
                        @if($agent->type_id != 2)
                        <div class="mt-2 flex items-center justify-between text-white">
                            <p class="text-lg">Salary</p>
                            <h5 class="text-base ml-auto">{{ $agent->salary }} KWD</h5>
                        </div>
                        @endif
                        @if($agent->type_id == 3 || $agent->type_id == 4)
                        <div class="mt-2 flex items-center justify-between text-white">
                            <p class="text-lg">Target</p>
                            <h5 class="text-base ml-auto">{{ $agent->target }}</h5>
                        </div>
                        @endif
                        <div class="flex justify-evenly gap-2 w-full mt-2">
                            <x-paid>{{$paid}} KWD</x-paid>
                            <x-unpaid>{{$unpaid}} KWD</x-unpaid>
                        </div>
                    </div>
                </div>

            </div>
            <!--./ Agent Details -->
        </div>
        <!-- ./details secion -->


        <!-- edit Agent details modal -->
        <div id="editAgentModal" onclick="closemodalContentAgentIfClickedOutside(event)"
            class="fixed z-10 inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm hidden">
            <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative">

                <!-- Close Button (Top Right) -->
                <button onclick="closeAgentModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Modal Title -->
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 text-center">Edit Agent Details
                </h2>

                <!-- Modal Form -->
                <form id="agentForm" method="POST" action="{{ route('agents.update', $agent->id) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <!-- Name Field -->
                    <div class="space-y-1">
                        <label for="name" class="block text-sm font-semibold text-gray-700">Name</label>
                        <input id="name" name="name" type="text" value="{{ $agent->name }}" required
                            class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="agent Name" />
                    </div>

                    <!-- Email Field -->
                    <div class="space-y-1">
                        <label for="email" class="block text-sm font-semibold text-gray-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ $agent->email }}" required
                            class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="agent Email" />
                    </div>

                    <!-- Phone Number -->
                    <div class="mb-6">
                        <label for="phone_number" class="block text-gray-700 font-semibold mb-2">Phone Number</label>
                        <input type="text" name="phone_number" id="phone_number" value="{{ $agent->phone_number }}"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <div x-data="{ typeId: {{ (int) $agent->type_id }} }" class="space-y-4">
                        <div class="mb-6">
                            <label for="type" class="block text-gray-700 font-semibold mb-2">Type</label>
                            <select name="type_id" id="type" x-model.number="typeId"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                                @foreach($agentType as $type)
                                <option value="{{ $type->id }}">
                                    {{ $type->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-6" x-show="typeId !== 2" x-transition>
                            <label for="salary" class="block text-gray-700 font-semibold mb-2">Salary</label>
                            <input type="number" name="salary" id="salary" value="{{ $agent->salary }}" min="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        </div>
                        <div class="mb-6" x-show="typeId === 3 || typeId === 4" x-transition>
                            <label for="target" class="block text-gray-700 font-semibold mb-2">Target</label>
                            <input type="number" name="target" id="target" value="{{ $agent->target }}" min="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        </div>
                    </div>

                    @if(in_array('Amadeus', $supplierCompany))
                    <label for="amadeus_id" class="block text-gray-700 font-semibold mb-2">Amadeus ID</label>
                    <input
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300"
                        type="text" name="amadeus_id" id="amadeus_id" placeholder="Amadeus ID" value="{{ $agent->amadeus_id }}">
                    @endif

                    @if(in_array('TBO Holiday', $supplierCompany))
                    <label for="tbo_reference" class="block text-gray-700 font-semibold mb-2">TBO Reference</label>
                    <input
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300"
                        type="text" name="tbo_reference" id="tbo_reference" placeholder="TBO Reference" value="{{ $agent->tbo_reference }}">
                    @endif

                    <!-- Submit Button -->
                    <div class="flex space-x-2">
                        <button type="submit"
                            class="p-2 btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                            Update Agent
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- ./edit agent details modal -->

            <div class="mt-5 panel">
                <div class="mb-5 flex items-center justify-between">
                    <!-- Left: Title -->
                    <h5 class="text-lg font-semibold dark:text-white-light">
                        <span class="customBlueColor">Invoices</span> List
                    </h5>

                    <!-- Right: Filter -->
                    <form method="GET"
                        class="ml-auto flex items-center gap-3 bg-white dark:bg-gray-800 px-4 py-2 rounded-lg">
                        <div class="relative">
                            <input type="month" id="month" name="month"
                                value="{{ request('month', now()->format('Y-m')) }}"
                                class="px-3 py-1.5 text-sm rounded-lg w-42 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        </div>

                        <button type="submit"
                            class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>

                        @if(request()->has('month'))
                            <a href="{{ url()->current() }}"
                                class="bg-red-100 hover:bg-red-300 text-red-800 text-sm font-medium px-4 py-1.5 rounded-lg transition">
                                <i class="fas fa-times mr-1"></i> Clear
                            </a>
                        @endif
                    </form>
                </div>
                <div class="mb-5 mt-5 items-center justify-center flex gap-6 flex-wrap shadow-sm">
                    <div class="flex-1 min-w-[200px] bg-green-100 text-green-700 px-4 py-2 text-center rounded-md shadow">
                        <p>Total Client Paid</p>
                        <p class="text-lg font-bold">{{ $totalPaid }} KWD</p>
                    </div>
                    <div class="flex-1 min-w-[200px] bg-red-100 text-red-700 px-4 py-2 text-center rounded-md shadow">
                        <p>Total Client Outstanding</p>
                        <p class="text-lg font-bold">{{ $totalOutstanding }} KWD</p>
                    </div>
                    @if($agent->type_id != 1)
                    <div class="flex-1 min-w-[200px] bg-yellow-100 text-yellow-700 px-4 py-2 text-center rounded-md shadow">
                        <p>Total Commission</p>
                        <p class="text-lg font-bold">{{ $totalCommission }} KWD</p>
                    </div>
                    @endif
                    <div class="flex-1 min-w-[200px] bg-blue-100 text-blue-800 px-4 py-2 text-center rounded-md shadow">
                        <p>Total Profit</p>
                        <p class="text-lg font-bold">{{ $totalProfit }} KWD</p>
                    </div>
                </div>
                <div>
                    @if($invoices->isEmpty() && request()->has('month'))
                        <p class="font-semibold text-gray-600">No invoices found for {{ \Carbon\Carbon::parse(request('month'))->format('F Y') }}.</p>
                        <p class="text-sm mt-1 text-gray-600">Try selecting a different month or clear the filter.</p>
                    @elseif($invoices->isEmpty())
                    <p class="text-gray-600">No invoices for this agent.</p>
                    @else
                    <div class="max-h-100 overflow-y-auto custom-scrollbar" x-data="{ openRow: null }">
                        <table class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700">
                            <thead>
                                <tr class="text-center">
                                    <th class="py-3 px-6 font-semibold text-gray-600 border-b">Invoice Number</th>
                                    <th class="py-3 px-6 font-semibold text-gray-600 border-b">Invoice Date</th>
                                    <th class="py-3 px-6 font-semibold text-gray-600 border-b">Status</th>
                                    <th class="py-3 px-6 font-semibold text-gray-600 border-b">Tasks Count</th>
                                    @if(in_array($agent->type_id, [2, 3, 4]))
                                    <th class="py-3 px-6 font-semibold text-gray-600 border-b">Commission (KWD)</th>
                                    @endif
                                    <th class="py-3 px-6 font-semibold text-gray-600 border-b">Profit (KWD)</th>
                                    <th class="py-3 px-6 font-semibold text-gray-600 border-b">Client</th>
                                    <th class="py-3 px-6 font-semibold text-gray-600 border-b">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoices as $invoice)
                                    <tr class="cursor-pointer text-center"
                                        :class="openRow === {{ $invoice->id }} ? 'bg-blue-50 hover:bg-gray-50 dark:bg-blue-900 hover:dark:bg-blue-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200'" 
                                        @click="openRow === {{ $invoice->id }} ? openRow = null : openRow = {{ $invoice->id }}">
                                        <td class="py-4 px-6 border-b">
                                            <a href="{{ route('invoice.details', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                                class="text-blue-500 hover:underline" @click.stop target="_blank"> {{ $invoice->invoice_number }}
                                            </a>
                                        </td>
                                        <td class="py-4 px-6 border-b">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</td>
                                        <td class="py-4 px-6 border-b">
                                            @if($invoice->status == 'paid')
                                            <x-paid>
                                                {{ $invoice->status }}
                                            </x-paid>
                                            @else
                                            <x-unpaid>
                                                {{ $invoice->status }}
                                            </x-unpaid>
                                            @endif
                                        </td>
                                        <td class="py-4 px-6 border-b">{{ $invoice->task_count }}</td>
                                        @if(in_array($agent->type_id, [2, 3, 4]))
                                        <td class="py-4 px-6 border-b text-green-700 font-semibold">
                                            {{ $invoice->total_commission }}
                                        </td>
                                        @endif
                                        <td class="py-4 px-6 border-b text-blue-700 font-semibold">
                                            {{ $invoice->total_profit }}
                                        </td>
                                        <td class="py-4 px-6 border-b">{{ $invoice->client->full_name }}</td>
                                        <td class="py-4 px-6 border-b">
                                            <a href="{{ route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])}}" class="text-blue-500 hover:underline" @click.stop target="_blank">View</a>
                                        </td>
                                    </tr>
                                    <tr x-show="openRow === {{ $invoice->id }}" x-cloak>
                                        <td colspan="{{ in_array($agent->type_id, [2, 3, 4]) ? 8 : 7 }}" class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 rounded-b-lg shadow-inner">
                                            <div class="space-y-4">
                                                <h4 class="font-semibold text-lg mb-3">Tasks in this Invoice:</h4>
                                                @foreach($invoice->tasks as $task)
                                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                        <div><strong>Reference:</strong> {{ $task['task_reference'] }}</div>
                                                        <div><strong>Passenger:</strong> {{ $task['passenger_name'] }}</div>
                                                        <div><strong>Task Price:</strong> {{ number_format($task['task_price'], 2) }} KWD</div>
                                                        <div><strong>Markup:</strong> {{ number_format($task['markup_price'], 2) }} KWD</div>
                                                    </div>
                                                </div>
                                                @endforeach
                                                @if($invoice->invoice_charge > 0)
                                                <div class="bg-yellow-50 dark:bg-yellow-900 p-3 rounded-lg border border-yellow-200 dark:border-yellow-700">
                                                    <div><strong>Invoice Charge:</strong> {{ number_format($invoice->invoice_charge, 2) }} KWD</div>
                                                </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $invoices->appends(['section' => 'invoices', 'month' => request('month')])->links() }}
                    </div>
                    @endif
                </div>
            </div>
            <div class="mt-5 panel">
                <div class="mb-5 flex justify-between">
                    <h5 class="text-lg font-semibold dark:text-white-light">
                        <span class="customBlueColor">Tasks</span> List
                    </h5>
                    <div class="flex gap-2 w-96">
                        <x-paid class="relative group">
                            {{$taskInvoiced}} Invoiced
                            <div class="absolute right-0 -top-11 bg-gray-900 border-black rounded-md p-2 invisible group-hover:visible">
                                <p class="font-normal">Task that invoiced</p>
                            </div>
                        </x-paid>
                        <x-unpaid class="relative group">
                            {{$taskNotInvoiced}} Not Invoiced
                            <div class="absolute right-0 -top-11 bg-gray-900 border-black rounded-md p-2 invisible group-hover:visible w-60 z-10">
                                <p class="font-normal ">Task that not invoiced yet</p>
                            </div>
                        </x-unpaid>
                    </div>
                    <!-- add an icon here -->
                </div>
                <!-- tasks Section -->
                <div class="mt-5">
                    <div class="">
                        @if($tasks->isEmpty())
                        <div class="max-h-96 overflow-y-auto custom-scrollbar">
                            <p class="text-gray-600">No tasks for this agent.</p>
                        </div>
                        @else
                        <div class="max-h-98 overflow-y-auto custom-scrollbar">
                            <table class="min-w-full bg-white border border-gray-300">
                                <thead>
                                    <tr class="">
                                        <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Task Name
                                        </th>
                                        <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Task Date
                                        </th>
                                        <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Status</th>
                                        <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Client</th>
                                    </tr>
                                </thead>
                                <tbody class="overflow-auto">
                                    @foreach($tasks as $task)
                                    <tr class="{{ $task->invoiceDetail !== null ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700' }}">
                                        <td class="py-4 px-6 border-b border-gray-300"> {{ $task->reference }}-{{ $task->additional_info }} {{ $task->venue }}</td>
                                        <td class="py-4 px-6 border-b border-gray-300">{{ $task->created_at }}</td>
                                        <td class="py-4 px-6 border-b border-gray-300">{{ $task->status }}</td>
                                        <td class="py-4 px-6 border-b border-gray-300">{{ $task->client !== null ? $task->client->full_name : $task->client_name ?? 'Not Set' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $tasks->appends(['section' => 'tasks'])->links() }}
                        </div>
                        @endif
                    </div>
                </div>
                <!-- ./tasks Section -->
            </div>

        <!-- ./invoices and clients section -->


    </div><!-- ./p-3 -->




    <script>
        let agentFormOriginalClone = null;

        window.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('agentForm');
            if (form) {
                agentFormOriginalClone = form.cloneNode(true);
            }
        });

        // edit company details modal
        function EditAgentDetails() {
            const modal = document.getElementById('editAgentModal');
            const formContainer = modal.querySelector('#agentForm');
            if (formContainer && agentFormOriginalClone) {
                formContainer.replaceWith(agentFormOriginalClone.cloneNode(true));
            }

            modal.classList.remove('hidden');
        }

        function closeAgentModal() {
            // Hide the modal when "Cancel" is clicked
            document.getElementById('editAgentModal').classList.add('hidden');
        }

        function closemodalContentAgentIfClickedOutside(event) {
            // Close the modal if the user clicks outside of the modal content
            const modalContentAgent = document.querySelector('#editAgentModal > div');
            if (!modalContentAgent.contains(event.target)) {
                closeAgentModal();
            }
        }
    </script>
</x-app-layout>