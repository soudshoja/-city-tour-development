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

        <!-- Agent Section -->
        @if($bonuses->isNotEmpty())
            <div class="mt-5 flex flex-col md:flex-row gap-5">
                <!-- Agent Details Section (1/3 width, compact height) -->
                <div class="w-1/3">
                    <div class="h-[280px] bg-gradient-to-br from-blue-50 to-white dark:from-gray-800 dark:to-gray-900 
                                rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 flex flex-col justify-between">
                        
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-4">
                                    <x-application-logo class="h-12 w-12 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-700" />
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $agent->name }}</h2>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $agent->branch->company->name }}</p>
                                    </div>
                                </div>
                                <button onclick="EditAgentDetails()" data-tooltip="Edit Agent Details" data-tooltip-placement="top"
                                        class="transition hover:text-gray-700 dark:hover:text-gray-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                        stroke="currentColor" class="w-5 h-5 text-gray-500">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.06c1.523-.932 3.348.892 2.416 2.416a1.724 1.724 0 001.06 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.06 2.573c.932 1.523-.893 3.348-2.416 2.416a1.724 1.724 0 00-2.573 1.06c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.06c-1.523.932-3.348-.893-2.416-2.416a1.724 1.724 0 00-1.06-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.06-2.573c-.932-1.523.893-3.348 2.416-2.416a1.724 1.724 0 002.573-1.06z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>

                            <div class="flex justify-around border-y border-gray-200 dark:border-gray-700 py-3 text-center">
                                <div>
                                    <p class="text-xs text-gray-500">Paid Invoices</p>
                                    <p class="text-sm font-semibold text-emerald-600">{{ $paid }} KWD</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Pending Invoices</p>
                                    <p class="text-sm font-semibold text-yellow-500">{{ $unpaid }} KWD</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Clients</p>
                                    <p class="text-sm font-semibold text-blue-600">{{ $clients->count() }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-y-1 text-xs mt-3">
                                <span class="text-gray-500 dark:text-gray-400">Email</span>
                                <a href="mailto:{{ $agent->email }}" 
                                class="text-right text-gray-800 dark:text-gray-100 hover:text-blue-600 transition">
                                    {{ $agent->email }}
                                </a>
                                <span class="text-gray-500 dark:text-gray-400">Phone</span>
                                <a href="tel:{{ $agent->phone_number }}" 
                                class="text-right text-gray-800 dark:text-gray-100 hover:text-blue-600 transition">
                                    {{ $agent->phone_number }}
                                </a>
                                <span class="text-gray-500 dark:text-gray-400">Branch</span>
                                <span class="text-right text-gray-800 dark:text-gray-100">{{ $agent->branch->name }}</span>
                                <span class="text-gray-500 dark:text-gray-400">Type</span>
                                <span class="text-right text-gray-800 dark:text-gray-100">{{ $agent->agentType->name }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bonus Section (2/3 width, same height, scrollable table) -->
                <div class="w-2/3">
                    <div class="h-[280px] panel p-6 flex flex-col text-left rounded-lg shadow-lg bg-white dark:bg-gray-900">
                        <div class="flex justify-between items-center mb-4">
                            <h5 class="text-base font-semibold dark:text-white-light">
                                <span class="text-blue-600 dark:text-blue-400">Bonus</span> Records
                            </h5>
                        </div>

                        <!-- Summary Row -->
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Total Bonus</span>
                            <div class="text-right leading-tight">
                                <p class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ number_format($bonuses->sum('amount'), 2) }} KWD</p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400">Updated at: 
                                    <span class="text-blue-600 dark:text-blue-400">{{ now()->format('M Y') }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Scrollable Table -->
                        <div class="flex-1 overflow-y-auto custom-scrollbar rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800 mt-5">
                            <table class="w-full text-xs">
                                <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                                    <tr>
                                        <th class="py-2 px-4 text-left font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[10px]">Payment Ref</th>
                                        <th class="py-2 px-4 text-left font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[10px]">Description</th>
                                        <th class="py-2 px-4 text-right font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[10px]">Amount</th>
                                        <th class="py-2 px-4 text-right font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[10px]">Created At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bonuses as $bonus)
                                    <tr class="group transition-all duration-200 hover:bg-blue-50/60 dark:hover:bg-blue-900/40">
                                        <td class="py-2 px-4 text-gray-800 dark:text-gray-100 font-medium">{{ $bonus->transaction?->reference_number }}</td>
                                        <td class="py-2 px-4 text-gray-600 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-gray-100">{{ $bonus->transaction?->description }}</td>
                                        <td class="py-2 px-4 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($bonus->amount, 2) }}</td>
                                        <td class="py-2 px-4 text-right font-semibold text-gray-900 dark:text-gray-100">{{ $bonus->created_at->format('M d, Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @else
        <!-- Agent Details Section full-width -->
            <div class="w-full">
                <div class="h-[310px] bg-gradient-to-br from-blue-50 to-white dark:from-gray-800 dark:to-gray-900 
                            rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 flex flex-col justify-between">
                    
                    <div>
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-5">
                            <div class="flex items-center gap-4">
                                <x-application-logo class="h-14 w-14 rounded-full object-cover ring-2 ring-gray-100 dark:ring-gray-700" />
                                <div>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $agent->name }}</h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $agent->branch->company->name }}</p>
                                </div>
                            </div>
                            <button onclick="EditAgentDetails()" data-tooltip="Edit Agent Details" data-tooltip-placement="top"
                                    class="transition hover:text-gray-700 dark:hover:text-gray-300">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="w-6 h-6 text-gray-500">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.06c1.523-.932 3.348.892 2.416 2.416a1.724 1.724 0 001.06 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.06 2.573c.932 1.523-.893 3.348-2.416 2.416a1.724 1.724 0 00-2.573 1.06c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.06c-1.523.932-3.348-.893-2.416-2.416a1.724 1.724 0 00-1.06-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.06-2.573c-.932-1.523.893-3.348 2.416-2.416a1.724 1.724 0 002.573-1.06z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>

                        <!-- Stats Row -->
                        <div class="flex justify-around border-y border-gray-200 dark:border-gray-700 py-3 text-center">
                            <div>
                                <p class="text-sm text-gray-500">Paid Invoices</p>
                                <p class="text-base font-semibold text-emerald-600">{{ $paid }} KWD</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Pending Invoices</p>
                                <p class="text-base font-semibold text-yellow-500">{{ $unpaid }} KWD</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Clients</p>
                                <p class="text-base font-semibold text-blue-600">{{ $clients->count() }}</p>
                            </div>
                        </div>

                        <!-- Info Grid -->
                        <div class="grid grid-cols-2 gap-y-2 text-sm mt-4">
                            <span class="text-gray-600 dark:text-gray-400">Email</span>
                            <a href="mailto:{{ $agent->email }}" 
                                class="text-right text-gray-900 dark:text-gray-100 hover:text-blue-600 transition">
                                {{ $agent->email }}
                            </a>
                            <span class="text-gray-600 dark:text-gray-400">Phone</span>
                            <a href="tel:{{ $agent->phone_number }}" 
                                class="text-right text-gray-900 dark:text-gray-100 hover:text-blue-600 transition">
                                {{ $agent->phone_number }}
                            </a>
                            <span class="text-gray-600 dark:text-gray-400">Branch</span>
                            <span class="text-right text-gray-900 dark:text-gray-100">{{ $agent->branch->name }}</span>
                            <span class="text-gray-600 dark:text-gray-400">Type</span>
                            <span class="text-right text-gray-900 dark:text-gray-100">{{ $agent->agentType->name }}</span>
                        </div>
                    </div>
                </div>
            </div>


        @endif
        <!-- End of Agent Section -->

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

        <!-- Client Section -->
        <div class="mt-5 panel">
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
        <!-- End of Client Section -->

        <!-- Invoice Section -->
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

        <!-- Task Section -->
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
        <!-- End of Task Section -->

    </div>

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