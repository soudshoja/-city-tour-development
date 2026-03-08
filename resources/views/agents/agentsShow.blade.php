@push('styles')
@vite(['resources/css/agent/index.css'])

@endpush
<x-app-layout>
    <div x-data="{
        activeTab: '{{ request('tab', 'client-list') }}',
        openRow: null,
        showModal: false,
        setTab(tab) { 
            this.activeTab = tab;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        }
    }">

        <div class="agent-header">
            <div class="agent-header-left">
                <h2 class="agent-title">Agent Details</h2>
                <div class="flex items-center space-x-2 rtl:space-x-reverse text-sm mb-4 sm:mb-6 overflow-x-auto">
                    <a href="{{ route('agents.index') }}" class="text-gray-500 hover:text-gray-700 transition whitespace-nowrap">Agents List</a>
                    <span class="text-gray-400">&gt;</span>
                    <span class="text-blue-600 font-medium truncate max-w-[200px] sm:max-w-none">
                        {{ \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower($agent->name)) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-5">
            <div class="w-1/3">
                <div class="h-[300px] bg-slate-400 bg-gradient-to-br dark:from-gray-800 dark:to-gray-900 rounded-xl shadow-lg border border-gray-400 dark:border-gray-700 p-6 flex flex-col justify-between">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <x-application-logo class="h-12 w-12 rounded-full object-cover ring-2 dark:ring-gray-700" />
                            <div>
                                <h2 class="text-lg font-semibold text-white">{{ $agent->name }}</h2>
                                <p class="text-md text-gray-300 dark:text-gray-400">{{ $agent->branch->company->name }}</p>
                            </div>
                        </div>
                        <button @click="showModal = true" :data-tooltip-left="showModal ? null : 'Edit details'"
                            class="transition hover:text-gray-700 dark:hover:text-gray-300">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-5 h-5 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.06c1.523-.932 3.348.892 2.416 2.416a1.724 1.724 0 001.06 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.06 2.573c.932 1.523-.893 3.348-2.416 2.416a1.724 1.724 0 00-2.573 1.06c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.06c-1.523.932-3.348-.893-2.416-2.416a1.724 1.724 0 00-1.06-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.06-2.573c-.932-1.523.893-3.348 2.416-2.416a1.724 1.724 0 002.573-1.06z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex justify-around border-y border-gray-300 dark:border-gray-700 py-3 text-center">
                        <div>
                            <p class="text-xs text-gray-300">Paid Invoices</p>
                            <p class="text-sm font-semibold text-emerald-600">{{ number_format($paid, 3) }} KWD</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-300">Pending Invoices</p>
                            <p class="text-sm font-semibold text-yellow-500">{{ number_format($unpaid, 3) }} KWD</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-300">Clients</p>
                            <p class="text-sm font-semibold text-blue-600">{{ $clientCount }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-y-1 text-sm mt-3">
                        <span class="text-gray-300 dark:text-gray-400">Email</span>
                        <a href="mailto:{{ $agent->email }}"
                            class="text-right text-white dark:text-gray-100 hover:text-blue-600 transition">
                            {{ $agent->email }}
                        </a>
                        <span class="text-gray-300 dark:text-gray-400">Phone</span>
                        <a href="tel:{{ $agent->phone_number }}"
                            class="text-right text-white dark:text-gray-100 hover:text-blue-600 transition">
                            {{ $agent->phone_number }}
                        </a>
                        <span class="text-gray-300 dark:text-gray-400">Branch</span>
                        <span class="text-right text-white dark:text-gray-100">{{ $agent->branch->name }}</span>
                        <span class="text-gray-300 dark:text-gray-400">Type</span>
                        <span class="text-right text-white dark:text-gray-100">{{ $agent->agentType->name }}</span>
                    </div>
                </div>
            </div>

            <div class="w-2/3">
                <div class="h-[300px] bg-gradient-to-br from-blue-50 to-white dark:from-gray-800 dark:to-gray-900 panel p-6 flex flex-col text-left rounded-lg shadow-lg">
                    <div class="flex justify-between items-center mb-4">
                        <h5 class="text-lg font-semibold dark:text-white-light">Bonus Records</h5>
                        <div class="flex items-center gap-2">
                            <form method="GET" id="agentBonusFilterForm" action="{{ route('agents.show', $agent->id) }}"
                                class="flex items-center gap-1 bg-white/60 z-20 dark:bg-gray-800/40 px-4 py-2 rounded-full shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">

                                <div x-data="{
                                    open: false,
                                    selected: {{ request('filter_month', now()->month) }},
                                    months: ['January','February','March','April','May','June','July','August','September','October','November','December']
                                }" class="relative">
                                    <input type="hidden" name="filter_month" x-model="selected">
                                    <button type="button" @click="open = !open" @click.outside="open = false"
                                        class="text-sm text-gray-700 dark:text-gray-100 cursor-pointer">
                                        <span x-text="months[selected - 1]"></span>
                                    </button>
                                    <div x-show="open" x-cloak
                                        class="absolute top-8 left-0 z-[9999] bg-white dark:bg-gray-800 rounded-xl shadow-lg ring-1 ring-gray-100 dark:ring-gray-700 py-2 min-w-[140px]">
                                        <template x-for="(month, index) in months" :key="index">
                                            <button type="button"
                                                @click="selected = index + 1; open = false"
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                                                :class="selected === index + 1 ? 'text-blue-600 font-semibold bg-blue-50 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-200'"
                                                x-text="month">
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <span class="text-gray-400 text-sm">/</span>

                                <div x-data="{
                                    open: false,
                                    selected: {{ request('filter_year', now()->year) }},
                                    years: {{ json_encode(range(now()->year, now()->year - 5)) }}
                                }" class="relative">
                                    <input type="hidden" name="filter_year" x-model="selected">
                                    <button type="button" @click="open = !open" @click.outside="open = false"
                                        class="text-sm text-gray-700 dark:text-gray-100 cursor-pointer">
                                        <span x-text="selected"></span>
                                    </button>
                                    <div x-show="open" x-cloak
                                        class="absolute top-8 left-0 z-[9999] bg-white dark:bg-gray-800 rounded-xl shadow-lg ring-1 ring-gray-100 dark:ring-gray-700 py-2 min-w-[90px]">
                                        <template x-for="year in years" :key="year">
                                            <button type="button"
                                                @click="selected = year; open = false"
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                                                :class="selected === year ? 'text-blue-600 font-semibold bg-blue-50 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-200'"
                                                x-text="year">
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </form>

                            <!-- Filter Button -->
                            <button type="submit" form="agentBonusFilterForm"
                                class="w-8 h-8 inline-flex items-center justify-center rounded-full bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm"
                                title="Filter">
                                <i class="fas fa-filter text-sm"></i>
                            </button>

                            <!-- Reset Button -->
                            @php
                                $isFiltered = request('filter_month') && (
                                    (int)request('filter_month') !== now()->month ||
                                    (int)request('filter_year', now()->year) !== now()->year
                                );
                            @endphp
                            @if($isFiltered)
                            <a href="{{ route('agents.show', $agent->id) }}"
                                class="w-8 h-8 inline-flex items-center justify-center rounded-full bg-gray-600 text-white hover:bg-gray-700 transition shadow-sm"
                                title="Reset">
                                <i class="fas fa-rotate-left text-sm"></i>
                            </a>
                            @endif
                        </div>
                    </div>

                    <div class="mb-4 flex flex-col">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total bonus earned in {{ \Carbon\Carbon::createFromDate(request('filter_year', now()->year), request('filter_month', now()->month), 1)->format('F Y') }}
                        </span>
                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400 mt-1">
                            {{ number_format($bonuses->sum('amount'), 2) }} KWD
                        </p>
                    </div>

                    <div class="flex-1 overflow-y-auto custom-scrollbar rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800 shadow-lg">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                                <tr>
                                    <th class="py-2 px-4 font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Payment Ref</th>
                                    <th class="py-2 px-4 font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Description</th>
                                    <th class="py-2 px-4 font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Amount</th>
                                    <th class="py-2 px-4 font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($bonuses as $bonus)
                                <tr class="transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-700">
                                    <td class="py-2 px-4 text-gray-700 dark:text-gray-500 font-medium">{{ $bonus->transaction?->reference_number }}</td>
                                    <td class="py-2 px-4 text-gray-700 dark:text-gray-500">{{ $bonus->transaction?->description }}</td>
                                    <td class="py-2 px-4 font-semibold text-gray-700 dark:text-gray-500">{{ number_format($bonus->amount, 2) }}</td>
                                    <td class="py-2 px-4 font-semibold text-gray-700 dark:text-gray-500">{{ $bonus->created_at->format('d M Y') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-gray-500 dark:text-gray-400 italic">
                                        No bonus record found for this month.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-center text-gray-500 dark:text-gray-400 mt-5">
                        Last updated:
                        <span class="text-blue-600 dark:text-blue-400">{{ now()->format('jS M Y') }}</span>
                    </p>
                </div>
            </div>

        </div>
        
        <!-- Edit Agent Details Modal -->
        <div x-show="showModal" x-cloak @click.self="showModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col mx-4"
                @click.away="showCreateModal = false">

                <form action="{{ route('agents.update', $agent->id) }}" id="agentForm" method="POST">
                    @csrf

                    <!-- Modal Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Edit Agent Details</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Update the agent information for accuracy of data</p>
                        </div>
                        <button type="button" @click="showModal = false"
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="flex-1 overflow-y-auto px-6 py-4">
                        <div class="space-y-4">
                            {{-- Name --}}
                            <div class="grid grid-cols-1 gap-4">
                                <div class="space-y-1">
                                    <label for="name" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Name</label>
                                    <input id="name" name="name" type="text" value="{{ $agent->name }}" required
                                        class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="agent Name" />
                                </div>
                            </div>

                            {{-- Email + Phone --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label for="email" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Email</label>
                                    <input id="email" name="email" type="email" value="{{ $agent->email }}" required
                                        class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="agent Email" />
                                </div>

                                <div class="space-y-1">
                                    <label for="phone_number" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Phone Number</label>
                                    <input type="text" name="phone_number" id="phone_number" value="{{ $agent->phone_number }}" required
                                        class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="Phone Number">
                                </div>
                            </div>

                            <div x-data="{ typeId: {{ (int) $agent->type_id }} }" class="space-y-5">
                                {{-- Type --}}
                                <div class="grid grid-cols-1 gap-4">
                                    <div class="space-y-1">
                                        <label for="type" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Type</label>
                                        <select name="type_id" id="type" x-model.number="typeId"
                                            class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                            @foreach($agentType as $type)
                                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                {{-- Salary + Target --}}
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-1" x-show="typeId !== 2" x-transition>
                                        <label for="salary" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Salary</label>
                                        <input type="number" name="salary" id="salary" value="{{ $agent->salary }}" min="0"
                                            class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    </div>

                                    <div class="space-y-1" x-show="typeId === 3 || typeId === 4" x-transition>
                                        <label for="target" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Target</label>
                                        <input type="number" name="target" id="target" value="{{ $agent->target }}" min="0"
                                            class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    </div>
                                </div>
                            </div>

                            {{-- Amadeus + TBO --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @if(in_array('Amadeus', $supplierCompany))
                                <div class="space-y-1">
                                    <label for="amadeus_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Amadeus ID</label>
                                    <input type="text" name="amadeus_id" id="amadeus_id" value="{{ $agent->amadeus_id }}"
                                        class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                                @endif

                                @if(in_array('TBO Holiday', $supplierCompany))
                                <div class="space-y-1">
                                    <label for="tbo_reference" class="block text-sm font-semibold text-gray-700 dark:text-gray-200">TBO Reference</label>
                                    <input type="text" name="tbo_reference" id="tbo_reference" value="{{ $agent->tbo_reference }}"
                                        class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 rounded-b-xl">
                        <button type="button" @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                            Create Template
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-10">
            <div class="flex gap-1 mb-0 bg-slate-400 px-2 pt-2 rounded-t-lg">
                <button @click="setTab('client-list')" class="tab-shape main-tab main-tab-active" :class="{ 'main-tab-active': activeTab === 'client-list', 'main-tab-inactive': activeTab !== 'client-list' }">
                    <div class="main-tab-content-wrapper">
                        Clients
                        <span class="main-tab-badge main-tab-badge-amber">{{ $clients->total() }}</span>
                    </div>
                </button>

                <button @click="setTab('invoice-list')" class="main-tab-shape main-tab main-tab-active" :class="{ 'main-tab-active': activeTab === 'invoice-list', 'main-tab-inactive': activeTab !== 'invoice-list' }">
                    <div class="main-tab-content-wrapper">
                        Invoices
                        <span class="main-tab-badge main-tab-badge-amber">{{ $invoices->total() }}</span>
                    </div>
                </button>

                <button @click="setTab('task-list')" class="main-tab-shape main-tab main-tab-active" :class="{ 'main-tab-active': activeTab === 'task-list', 'main-tab-inactive': activeTab !== 'task-list' }">
                    <div class="main-tab-content-wrapper">
                        Tasks
                        <span class="main-tab-badge main-tab-badge-amber">{{ $tasks->total() }}</span>
                    </div>
                </button>
            </div>

            <!-- Client List -->
            <div x-show="activeTab === 'client-list' && !showModal" class="main-tab-content">
                <div class="flex items-center justify-between mt-2">
                    <div>
                        <h3 class="font-semibold text-slate-800">Clients List</h3>
                        <p class="text-sm text-slate-500">{{ $clients->total() }} clients that has tasks registered under their name</p>
                    </div>
                </div>
                <div class="mt-10">
                    @if($clients->isEmpty())
                    <p class="text-gray-600">No clients for this agent.</p>
                    @else
                    <div class="flex-1 rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800">
                        <table class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 w-full rounded-lg">
                            <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                                <tr class="py-3 px-4 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">
                                    <th class="px-6">Client Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Paid</th>
                                    <th>Pending</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clients as $client)
                                <tr class="text-center transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-700">
                                    <td class="text-left py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                        {{ $client->full_name }}
                                    </td>
                                    <td class="py-4 px-6 text-gray-700 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700">{{ $client->email }}</td>
                                    <td class="py-4 px-6 text-gray-700 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700">{{ $client->country_code }}{{ $client->phone }}</td>
                                    <td class="py-4 px-6 text-gray-700 dark:text-gray-500 border-b border-gray-200 dark:border-gray-700">
                                        {{ ucwords(strtolower($client->address ?? 'Not Set')) }}
                                    </td>
                                    <td class="py-4 px-6 border-b border-gray-200 dark:border-gray-700">
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                            bg-green-100 text-green-700 ring-1 ring-green-500 shadow-none focus:outline-none focus:ring-0">
                                            {{ $client->paid }} KWD
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 border-b border-gray-200 dark:border-gray-700">
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                            bg-yellow-100 text-yellow-700 ring-1 ring-yellow-400 shadow-none focus:outline-none focus:ring-0">
                                            {{ $client->unpaid }} KWD
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 border-b border-gray-200 dark:border-gray-700">
                                        <a href="{{ url('/clients/' . $client->id) }}" class="text-blue-500 hover:text-blue-700 transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
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

            <!-- Invoice List -->
            <div x-show="activeTab === 'invoice-list'" class="main-tab-content" x-cloak>
                <div class="flex items-center justify-between mt-2">
                    <div>
                        <h3 class="font-semibold text-slate-800">Invoices List</h3>
                        <p class="text-sm text-slate-500">{{ $invoices->total() }} invoices that were created by {{ $agent->name}}
                            @if(request('month'))
                            on {{ \Carbon\Carbon::parse(request('month'))->format('F Y') }}
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <form method="GET" id="invoiceListFilterForm"
                            class="flex items-center gap-1 bg-white/60 z-20 dark:bg-gray-800/40 px-4 py-2 rounded-full shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                            <input type="hidden" name="tab" value="invoice-list">

                            <div x-data="{
                                monthOpen: false,
                                yearOpen: false,
                                month: {{ request('month') ? (int)\Carbon\Carbon::parse(request('month'))->format('m') : now()->month }},
                                year: {{ request('month') ? (int)\Carbon\Carbon::parse(request('month'))->format('Y') : now()->year }},
                                months: ['January','February','March','April','May','June','July','August','September','October','November','December'],
                                years: {{ json_encode(range(now()->year, now()->year - 5)) }}
                            }" class="flex items-center gap-1">

                                <input type="hidden" name="month" :value="`${year}-${String(month).padStart(2, '0')}`">

                                {{-- Month --}}
                                <div class="relative">
                                    <button type="button" @click="monthOpen = !monthOpen; yearOpen = false" @click.outside="monthOpen = false"
                                        class="text-sm text-gray-700 dark:text-gray-100 cursor-pointer">
                                        <span x-text="months[month - 1]"></span>
                                    </button>
                                    <div x-show="monthOpen" x-cloak
                                        class="absolute top-8 left-0 z-[9999] bg-white dark:bg-gray-800 rounded-xl shadow-lg ring-1 ring-gray-100 dark:ring-gray-700 py-2 min-w-[140px]">
                                        <template x-for="(m, index) in months" :key="index">
                                            <button type="button"
                                                @click="month = index + 1; monthOpen = false"
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                                                :class="month === index + 1 ? 'text-blue-600 font-semibold bg-blue-50 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-200'"
                                                x-text="m">
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <span class="text-gray-400 text-sm">/</span>

                                {{-- Year --}}
                                <div class="relative">
                                    <button type="button" @click="yearOpen = !yearOpen; monthOpen = false" @click.outside="yearOpen = false"
                                        class="text-sm text-gray-700 dark:text-gray-100 cursor-pointer">
                                        <span x-text="year"></span>
                                    </button>
                                    <div x-show="yearOpen" x-cloak
                                        class="absolute top-8 left-0 z-[9999] bg-white dark:bg-gray-800 rounded-xl shadow-lg ring-1 ring-gray-100 dark:ring-gray-700 py-2 min-w-[90px]">
                                        <template x-for="y in years" :key="y">
                                            <button type="button"
                                                @click="year = y; yearOpen = false"
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                                                :class="year === y ? 'text-blue-600 font-semibold bg-blue-50 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-200'"
                                                x-text="y">
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Filter Button -->
                        <button type="submit" form="invoiceListFilterForm"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-full bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm"
                            title="Filter">
                            <i class="fas fa-filter text-sm"></i>
                        </button>

                        <!-- Reset Button -->
                        @if(request()->has('month'))
                        <a href="{{ url()->current() }}?tab=invoice-list"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-full bg-gray-600 text-white hover:bg-gray-700 transition shadow-sm"
                            title="Reset">
                            <i class="fas fa-rotate-left text-sm"></i>
                        </a>
                        @endif
                    </div>
                </div>

                <div class="mt-10 mb-10 flex flex-wrap justify-center items-center gap-5">
                    <div class="flex-1 min-w-[220px] bg-green-600 text-white px-4 py-3 rounded-lg shadow-sm">
                        <p class="text-sm opacity-80">Total Client Paid</p>
                        <p class="text-xl font-bold">{{ $totalPaid }} KWD</p>
                    </div>

                    <div class="flex-1 min-w-[220px] bg-orange-600 text-white px-4 py-3 rounded-lg shadow-sm">
                        <p class="text-sm opacity-80">Total Client Outstanding</p>
                        <p class="text-xl font-bold">{{ $totalOutstanding }} KWD</p>
                    </div>

                    @if($agent->type_id != 1)
                    <div class="flex-1 min-w-[220px] bg-yellow-500 text-gray-900 px-4 py-3 rounded-lg shadow-sm">
                        <p class="text-sm opacity-80">Total Commission</p>
                        <p class="text-xl font-bold">{{ $totalCommission }} KWD</p>
                    </div>
                    @endif

                    <div class="flex-1 min-w-[220px] bg-blue-600 text-white px-4 py-3 rounded-lg shadow-sm">
                        <p class="text-sm opacity-80">Total Profit</p>
                        <p class="text-xl font-bold">{{ $totalProfit }} KWD</p>
                    </div>

                    <div class="flex-1 min-w-[220px] bg-red-600 text-white px-4 py-3 rounded-lg shadow-sm">
                        <p class="text-sm opacity-80">Total Loss</p>
                        <p class="text-xl font-bold"> {{ $totalLoss}} KWD</p>
                    </div>
                </div>

                <div class="">
                    @if($invoices->isEmpty())
                    <p class="text-gray-600">No invoices for this agent.</p>
                    @else
                    <div class="max-h-100 overflow-y-auto custom-scrollbar flex-1 rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800" x-data="{ openRow: null }">
                       <table class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 w-full rounded-lg">
                            <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                                <tr class="text-center">
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Invoice Number</th>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Invoice Date</th>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Status</th>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Tasks Count</th>
                                    @if(in_array($agent->type_id, [2, 3, 4]))
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Commission (KWD)</th>
                                    @endif
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Profit (KWD)</th>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Loss (KWD)</th>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Client</th>
                                    <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoices as $invoice)
                                <tr class="cursor-pointer text-center transition-all duration-200 
                                            hover:bg-blue-100 dark:hover:bg-blue-700 text-gray-700 dark:text-gray-200"
                                    :class="openRow === {{ $invoice->id }} 
                                                ? 'bg-blue-50 dark:bg-blue-900' 
                                                : ''"
                                    @click="openRow === {{ $invoice->id }} ? openRow = null : openRow = {{ $invoice->id }}">

                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                        <a href="{{ route('invoice.details', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                            class="text-blue-500 hover:underline" @click.stop target="_blank"> {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') }}</td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                        @if($invoice->status == 'paid')
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                                        bg-green-100 text-green-700 ring-1 ring-green-500 shadow-none focus:outline-none focus:ring-0">
                                            {{ ucfirst($invoice->status) }}
                                        </span>
                                        @else
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                                        bg-red-100 text-red-700 ring-1 ring-red-400 shadow-none focus:outline-none focus:ring-0">
                                            {{ ucfirst($invoice->status) }}
                                        </span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">{{ $invoice->task_count }}</td>
                                    @if(in_array($agent->type_id, [2, 3, 4]))
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700 text-green-700 font-semibold">
                                        {{ $invoice->total_commission }}
                                    </td>
                                    @endif
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700 text-blue-700 font-semibold">
                                        {{ $invoice->total_profit }}
                                    </td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700 text-blue-700 font-semibold">
                                        {{ $invoice->total_loss }}
                                    </td>
                                    <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">{{ $invoice->client->full_name }}</td>
                                    <td class="py-4 px-6 text-center text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                        <a href="{{ route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                            class="text-blue-500 hover:text-blue-700 transition" @click.stop target="_blank">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
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

            <!-- Task List -->
            <div x-show="activeTab === 'task-list'" class="main-tab-content" x-cloak>
                <div class="flex items-center justify-between mt-2">
                    <div>
                        <h3 class="font-semibold text-slate-800">Tasks List</h3>
                        <p class="text-sm text-slate-500">
                            {{ $tasks->total() }} tasks that were assigned to {{ $agent->name }}.
                            <span class="font-bold text-green-700">{{ $taskInvoiced }} invoiced</span>,
                            <span class="font-bold text-red-700">{{ $taskNotInvoiced }} uninvoiced</span>
                        </p>
                    </div>
                </div>

                <div class="mt-10">
                    <div class="">
                        @if($tasks->isEmpty())
                        <div class="max-h-96 overflow-y-auto custom-scrollbar">
                            <p class="text-gray-600">No tasks for this agent.</p>
                        </div>
                        @else
                        <div class="max-h-98 overflow-y-auto custom-scrollbar flex-1 rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800">
                            <table class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 w-full rounded-lg">                                
                                <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                                    <tr>
                                        <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Task Name</th>
                                        <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Task Date</th>
                                        <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Status</th>
                                        <th class="py-3 px-6 text-center font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-sm">Client</th>
                                    </tr>
                                </thead>
                                <tbody class="overflow-auto text-center">
                                    @foreach($tasks as $task)
                                    <tr class="transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-700">
                                        <td class="py-4 px-6 text-left text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="font-bold">{{ $task->reference }}</span>
                                                <span class="text-xs text-gray-400">{{ $task->additional_info }}</span>
                                                <span class="text-xs text-gray-400">{{ $task->venue }}</span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">{{ $task->created_at }}</td>
                                        <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">
                                            @if (in_array($task->status, ['void', 'cancelled']))
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">{{ ucfirst($task->status) }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">{{ ucfirst($task->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="py-4 px-6 text-gray-800 dark:text-gray-500 font-medium border-b border-gray-200 dark:border-gray-700">{{ $task->client !== null ? $task->client->full_name : $task->client_name ?? 'Not Set' }}</td>
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
            </div>

        </div>

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