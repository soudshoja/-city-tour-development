<x-app-layout>
    @include('layouts.alert')

    <style>
        .no-client {
            color: red;
            position: relative;
            cursor: pointer;
        }

        .no-client:hover::after {
            content: 'This Task Not Link To Client CRM';
            position: absolute;
            background-color: red;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: medium;
            right: 0;
            bottom: 100%;
            margin-bottom: 8px;
            z-index: 50;
            white-space: nowrap;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
        }

        input:checked + .slider {
            background-color: #FCD34D; /* Yellow color */
        }

        input:focus + .slider {
            box-shadow: 0 0 1px #FCD34D;
        }

        input:checked + .slider:before {
            transform: translateX(16px);
        }

        .slider.round {
            border-radius: 34px;
        }

        .slider.round:before {
            border-radius: 50%;
        }

        .hover-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: transparent transparent;
        }

        .hover-scrollbar:hover {
            scrollbar-color: #CBD5E1 transparent;
        }

        .hover-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .hover-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .hover-scrollbar::-webkit-scrollbar-thumb {
            background: transparent;
            border-radius: 10px;
        }

        .hover-scrollbar:hover::-webkit-scrollbar-thumb {
            background: #CBD5E1;
        }

        .hover-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }

        .required-input {
            font-size: 0.7em;
            /* padding: 8px 12px;
            border-radius: 6px; */
        }

        .required-input::after {
            content: 'This field is required to enable task';
            color: red;
        }

        /* Mobile sidebar toggle */
        @media (max-width: 1023px) {
            .mobile-sidebar-overlay {
                position: fixed;
                inset: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
            
            .mobile-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            
            .mobile-sidebar.open {
                transform: translateX(0);
            }
        }
    </style>
    
    <div class="min-h-screen flex flex-col" 
        x-data="{
            selectedTaskId: {{ $tasks->first()->id }},
            tasks: {{ $tasks->toJson() }},
            bulkEditMode: false,
            singleEditMode: false,
            showMenu: false,
            showManualForm: false,
            showSidebar: false,
            modalTaskId: null,
            modalClientName: '',
            modalPassengerName: '',
            modalAgentName: '',
            modalAgentId: '',
            modalBranchName: '',
            init() {
                const urlParams = new URLSearchParams(window.location.search);
                const mode = urlParams.get('mode');
                
                if (mode === 'bulk' && {{ $tasks->count() }} > 1) {
                    this.bulkEditMode = true;
                } else if (mode === 'single') {
                    this.singleEditMode = true;
                }
                
                if (mode) {
                    urlParams.delete('mode');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState({}, '', newUrl);
                }
            },
            openManualForm(taskId, clientName, passengerName, agentName, agentId, branchName) {
                this.modalTaskId = taskId;
                this.modalClientName = clientName;
                this.modalPassengerName = passengerName;
                this.modalAgentName = agentName;
                this.modalAgentId = agentId;
                this.modalBranchName = branchName;
                this.showManualForm = true;
            },
            closeAll() {
                this.showManualForm = false;
                window.dispatchEvent(new CustomEvent('reset-dropdowns'));
            },
            getSelectedTask() {
                return this.tasks.find(t => t.id === this.selectedTaskId);
            },
            selectedForInvoice: [],
            toggleInvoiceSelection(taskId, canSelect) {
                if (!canSelect) return;

                if (this.selectedForInvoice.includes(taskId)) {
                    this.selectedForInvoice = this.selectedForInvoice.filter(id => id !== taskId);
                } else {
                    this.selectedForInvoice.push(taskId);
                }
            },
            getInvoiceUrl() {
                return `{{ route('invoices.create') }}?task_ids=` + this.selectedForInvoice.join(',');
            },
        }">
        
        <!-- Mobile Header -->
        <div class="lg:hidden flex items-center justify-between mb-4">
            <h1 class="text-2xl sm:text-3xl font-bold">
                Task Details
            </h1>
            <button 
                @click="showSidebar = true"
                class="p-2 bg-white rounded-lg shadow border border-gray-200 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <span class="text-sm font-medium text-gray-700">Tasks</span>
                <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $tasks->count() }}</span>
            </button>
        </div>

        <!-- Desktop Header -->
        <h1 class="hidden lg:block text-4xl font-bold mb-2">
            Task Details
        </h1>

        <div class="flex-1 pb-16">
            <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-sm sm:text-base">
                <li>
                    <a href="{{ route('tasks.index') }}" class="text-primary text-md hover:underline">Tasks List</a>
                </li>
                <li class="before:content-['>'] before:mr-2 text-md text-gray-600">
                    <span>Task Details</span>
                </li>
            </ul>

            <!-- Mobile Sidebar Overlay -->
            <div 
                x-show="showSidebar" 
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="showSidebar = false"
                class="lg:hidden fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm z-40"
                x-cloak>
            </div>

            <div class="flex flex-col lg:flex-row gap-4 items-stretch">                
                <!-- Mobile Sidebar Drawer (only visible on mobile) -->
                <div 
                    x-show="showSidebar"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="-translate-x-full"
                    class="lg:hidden fixed inset-y-0 left-0 z-50 w-80 flex-shrink-0 flex"
                    x-cloak>
                    <div class="bg-white shadow-sm border border-gray-200 flex flex-col w-full h-screen">
                        
                        <!-- Mobile Close Button -->
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                            <span class="text-sm font-semibold text-gray-700">Select Task</span>
                            <button @click="showSidebar = false" class="p-2 hover:bg-gray-100 rounded-lg">
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="px-4 py-3 flex-shrink-0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Selected {{ Str::plural('Task', $tasks->count())}} </h3>
                                    <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded-full">{{ $tasks->count() }}</span>
                                </div>

                                @if ($tasks->count() > 1)
                                <button 
                                    @click="bulkEditMode = true; showSidebar = false" 
                                    class="group relative p-2 hover:bg-gray-200 rounded-lg transition z-10">
                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-gray-900 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2 2m-2-2v6"/>
                                    </svg>
                                </button>
                                @endif
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto min-h-0 hover-scrollbar">
                            @foreach($tasks as $task)
                            <div
                                @click="selectedTaskId = {{ $task->id }}; bulkEditMode = false; showSidebar = false"
                                :class="selectedTaskId === {{ $task->id }} && !bulkEditMode ? 'bg-blue-50 border-l-4 border-blue-500' : 'bg-white hover:bg-gray-50 border-l-4 border-transparent'"
                                class="cursor-pointer transition-all border-b border-gray-200">
                                <div class="px-4 py-3">

                                    @php
                                        $canInvoice = $task->client_id && $task->agent_id && $task->company_id && $task->supplier_id && $task->status && $task->type && $task->total && $task->reference;
                                    @endphp
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-start gap-2 flex-1">
                                            
                                            <div class="pt-0.5" @click.stop>
                                                <input
                                                    type="checkbox"
                                                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                    :checked="selectedForInvoice.includes({{ $task->id }})"
                                                    @change="toggleInvoiceSelection({{ $task->id }}, {{ $canInvoice ? 'true' : 'false' }})"
                                                    {{ $canInvoice ? '' : 'disabled' }}
                                                >
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900">{{ $task->reference }}</p>
                                                <p class="text-xs text-gray-500 mt-1 uppercase">{{ $task->client->name ?? $task->client_name ?? 'No Client' }}</p>
                                            </div>

                                            @php
                                                $missing = [];
                                                if (!$task->client_id) $missing[] = 'Client';
                                                if (!$task->agent_id) $missing[] = 'Agent';
                                                if (!$task->company_id) $missing[] = 'Company';
                                                if (!$task->supplier_id) $missing[] = 'Supplier';
                                                if (!$task->type) $missing[] = 'Type';
                                                if (!$task->status) $missing[] = 'Status';
                                                if (!$task->reference) $missing[] = 'Reference';
                                                if (!$task->total) $missing[] = 'Total';

                                                $missingCount = count($missing);

                                                if ($missingCount === 0) {
                                                    $dotColor = 'bg-green-500';
                                                    $glowColor = 'bg-green-400/40';
                                                    $tooltipText = 'Ready for invoice';
                                                } elseif ($missingCount === 1) {
                                                    $dotColor = 'bg-yellow-500';
                                                    $glowColor = 'bg-yellow-400/40';
                                                    $tooltipText = 'Missing: ' . $missing[0];
                                                } else {
                                                    $dotColor = 'bg-red-500';
                                                    $glowColor = 'bg-red-400/40';
                                                    $tooltipText = 'Missing: ' . implode(' | ', $missing);
                                                }
                                            @endphp
                                            <div class="relative flex items-center justify-center group flex-shrink-0">
                                                <span class="relative flex h-2.5 w-2.5">
                                                    <span class="absolute inline-flex h-full w-full rounded-full {{ $glowColor }} animate-ping"></span>
                                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $dotColor }}"></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2 pl-6">
                                        <p class="text-sm font-semibold text-gray-700">
                                            {{ $task->currency ?? 'KWD' }} {{ number_format($task->total, 3) }}
                                        </p>
                                    </div>

                                </div>
                            </div>
                            @endforeach
                        </div>

                        <div class="px-4 py-4 bg-white flex-shrink-0 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <p class="text-xs text-gray-500 uppercase font-semibold">Total Amount</p>
                                <p class="text-lg font-bold text-blue-600">KWD {{ number_format($tasks->sum('total'), 3) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Sidebar (original, only visible on lg+) -->
                <div class="hidden lg:flex w-80 flex-shrink-0">
                    <div class="bg-white shadow-sm border border-gray-200 sticky top-4 flex flex-col w-full rounded-lg" style="max-height: calc(100vh - 2rem);">
                        <div class="px-4 py-3 flex-shrink-0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Selected {{ Str::plural('Task', $tasks->count())}} </h3>
                                    <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded-full">{{ $tasks->count() }}</span>
                                </div>

                                @if ($tasks->count() > 1)
                                <button 
                                    @click="bulkEditMode = true; showSidebar = false" 
                                    class="group relative p-2 hover:bg-gray-200 rounded-lg transition z-10">
                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-gray-900 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2 2m-2-2v6"/>
                                    </svg>
                                    
                                    <span class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 px-3 py-1.5 text-xs font-medium text-white bg-gray-900 rounded-md opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 whitespace-nowrap z-[100] shadow-lg">
                                        Bulk Edit
                                        <svg class="absolute top-full left-1/2 -translate-x-1/2 w-2 h-2 text-gray-900" viewBox="0 0 8 8">
                                            <path class="fill-current" d="M0,0 L4,4 L8,0 Z"/>
                                        </svg>
                                    </span>
                                </button>
                                @endif
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto min-h-0 hover-scrollbar">
                            @foreach($tasks as $task)
                            <div
                                @click="selectedTaskId = {{ $task->id }}; bulkEditMode = false; showSidebar = false"
                                :class="selectedTaskId === {{ $task->id }} && !bulkEditMode ? 'bg-blue-50 border-l-4 border-blue-500' : 'bg-white hover:bg-gray-50 border-l-4 border-transparent'"
                                class="cursor-pointer transition-all border-b border-gray-200">
                                <div class="px-4 py-3">

                                    @php
                                        $canInvoice = $task->client_id && $task->agent_id && $task->company_id && $task->supplier_id && $task->status && $task->type && $task->total && $task->reference;
                                    @endphp
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-start gap-2 flex-1">
                                            
                                            <div class="pt-0.5" @click.stop>
                                                <input
                                                    type="checkbox"
                                                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                                    :checked="selectedForInvoice.includes({{ $task->id }})"
                                                    @change="toggleInvoiceSelection({{ $task->id }}, {{ $canInvoice ? 'true' : 'false' }})"
                                                    {{ $canInvoice ? '' : 'disabled' }}
                                                >
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900">{{ $task->reference }}</p>
                                                <p class="text-xs text-gray-500 mt-1 uppercase">{{ $task->client->name ?? $task->client_name ?? 'No Client' }}</p>
                                            </div>

                                            @php
                                                $missing = [];

                                                // Always check these (your invoice requirements)
                                                if (!$task->client_id) $missing[] = 'Client';
                                                if (!$task->agent_id) $missing[] = 'Agent';
                                                if (!$task->company_id) $missing[] = 'Company';
                                                if (!$task->supplier_id) $missing[] = 'Supplier';
                                                if (!$task->type) $missing[] = 'Type';
                                                if (!$task->status) $missing[] = 'Status';
                                                if (!$task->reference) $missing[] = 'Reference';
                                                if (!$task->total) $missing[] = 'Total';

                                                $missingCount = count($missing);

                                                if ($missingCount === 0) {
                                                    $dotColor = 'bg-green-500';
                                                    $glowColor = 'bg-green-400/40';
                                                    $tooltipText = 'Ready for invoice';
                                                } elseif ($missingCount === 1) {
                                                    $dotColor = 'bg-yellow-500';
                                                    $glowColor = 'bg-yellow-400/40';
                                                    $tooltipText = 'Missing: ' . $missing[0];
                                                } else {
                                                    $dotColor = 'bg-red-500';
                                                    $glowColor = 'bg-red-400/40';
                                                    $tooltipText = 'Missing: ' . implode(' | ', $missing);
                                                }
                                            @endphp
                                            <div class="relative flex items-center justify-center group flex-shrink-0">
                                                <span class="relative flex h-2.5 w-2.5">
                                                    <span class="absolute inline-flex h-full w-full rounded-full {{ $glowColor }} animate-ping"></span>
                                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $dotColor }}"></span>
                                                </span>

                                                <div class="absolute right-full top-1/2 -translate-y-1/2 mr-2 px-3 py-2 text-xs font-medium text-white bg-gray-900 rounded-md shadow-lg whitespace-nowrap opacity-0 invisible group-hover:opacity-100 group-hover:visible transition z-50">
                                                    {{ $tooltipText }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2 pl-6">
                                        <p class="text-sm font-semibold text-gray-700">
                                            {{ $task->currency ?? 'KWD' }} {{ number_format($task->total, 3) }}
                                        </p>
                                    </div>

                                </div>
                            </div>
                            @endforeach
                        </div>

                        <div class="px-4 py-4 bg-white flex-shrink-0 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <p class="text-xs text-gray-500 uppercase font-semibold">Total Amount</p>
                                <p class="text-lg font-bold text-blue-600">KWD {{ number_format($tasks->sum('total'), 3) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="flex-1 flex flex-col min-w-0">

                    <div class="flex-1">
                        @foreach($tasks as $task)
                        <div x-cloak x-show="selectedTaskId === {{ $task->id }}" class="flex flex-col gap-4">

                            <!-- Task Header with Edit Icon -->
                            <div class="bg-gradient-to-r from-slate-800 to-slate-700 rounded-lg shadow-sm p-4 sm:p-6 flex-shrink-0">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Reference</p>
                                        <p class="text-lg sm:text-xl font-semibold text-white truncate">{{ $task->reference }}</p>
                                        <p class="text-sm text-gray-400 mt-1 truncate">{{ $task->ticket_number }}</p>
                                    </div>

                                    <!-- Edit Task Icon Button -->
                                    <button 
                                        @click="singleEditMode = true" 
                                        class="group relative p-2 hover:bg-slate-600 rounded-lg transition flex-shrink-0 ml-2">
                                        <svg class="w-5 h-5 text-gray-300 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        <span class="hidden sm:block absolute left-1/2 -translate-x-1/2 bottom-full mb-2 px-3 py-1.5 text-xs font-medium text-white bg-gray-900 rounded-md opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 whitespace-nowrap z-50 shadow-lg">
                                            Edit Task
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">                                
                                <!-- Task Information - 2 columns on lg -->
                                <div class="xl:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col">
                                    <div class="px-4 sm:px-6 py-4 bg-slate-50 rounded-t-lg flex-shrink-0">
                                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Task Information</h2>
                                    </div>
                                    <div class="p-4 sm:p-6 flex flex-col flex-1">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 sm:gap-x-8 gap-y-4 sm:gap-y-5">
                                            <div>
                                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Client</p>
                                                @if($task->client)
                                                    <p class="text-sm text-gray-900">{{ $task->client->full_name }}</p>
                                                @else
                                                    <button 
                                                        type="button"
                                                        @click="openManualForm({{ $task->id }}, '{{ $task->client_name ?? '' }}', '{{ $task->passenger_name ?? '' }}', '{{ $task->agent->name ?? 'Not Set' }}', '{{ $task->agent->id ?? 'Null' }}', '{{ $task->agent->branch->name ?? 'Not Set' }}')"
                                                        class="no-client text-sm font-medium capitalize">
                                                        {{ ucwords(strtolower($task->client_name)) ?: 'Not Set - Click to Register' }}
                                                    </button>
                                                @endif
                                            </div>

                                            <div>
                                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Client Phone Number</p>
                                                @if($task->client)
                                                    @php
                                                        $countryCode = $task->client->country_code ?? '';
                                                        $phone = $task->client->phone ?? '';
                                                        
                                                        $countryCode = ltrim($countryCode, '+');
                                                        
                                                        $phone = ltrim($phone, '+');
                                                        if ($countryCode && str_starts_with($phone, $countryCode)) {
                                                            $phone = substr($phone, strlen($countryCode));
                                                        }
                                                        
                                                        $formattedPhone = $countryCode && $phone ? "+{$countryCode} {$phone}" : ($phone ?: 'N/A');
                                                    @endphp
                                                    <p class="text-sm text-gray-900">{{ $formattedPhone }}</p>
                                                @else
                                                    <p class="text-sm text-gray-500 italic">Not Available</p>
                                                @endif
                                            </div>

                                            <div>
                                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Agent</p>
                                                @if ($task->agent) 
                                                <p class="text-sm text-gray-900 capitalize">{{ $task->agent->name }}</p>
                                                @else
                                                    <p class="text-sm text-gray-500 italic">Agent Not Set</p>
                                                @endif
                                            </div>

                                            <div>
                                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Branch</p>
                                                @if ($task->agent?->branch?->name)
                                                <p class="text-sm text-gray-900 capitalize">{{ $task->agent->branch->name }}</p>
                                                @else                                                                                                
                                                <p class="text-sm text-gray-500 italic">Not Available</p>
                                                @endif
                                            </div>

                                            <div>
                                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Supplier</p>
                                                <p class="text-sm text-gray-900 capitalize">{{ $task->supplier->name ?? '' }}</p>
                                            </div>

                                            <div>
                                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Status</p>
                                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full
                                                    @if($task->status === 'issued') bg-green-100 text-green-700
                                                    @elseif($task->status === 'confirmed') bg-blue-100 text-blue-700
                                                    @else bg-gray-100 text-gray-700
                                                    @endif">
                                                    {{ ucfirst($task->status) }}
                                                </span>
                                            </div>

                                            <div>
                                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Issued At</p>
                                                <p class="text-sm text-gray-900">{{ $task->supplier_pay_date ?? '' }}</p>
                                            </div>

                                            <div>
                                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Created At</p>
                                                <p class="text-sm text-gray-900">{{ $task->created_at ?? '' }}</p>
                                            </div>

                                            @if ($task->payment_method_account_id && $task->paymentMethod)
                                            <div>
                                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Payment Method</p>
                                                <p class="text-sm text-gray-900">{{ $task->paymentMethod->name }}</p>
                                            </div>
                                            @endif
                                        </div>

                                        <!-- Pricing -->
                                        <div class="mt-auto pt-6 sm:pt-8">
                                            <div class="space-y-3">
                                                <div class="flex justify-between items-center">
                                                    <p class="text-sm text-gray-600">Base Price:</p>
                                                    <p class="text-sm text-gray-900">{{ $task->currency ?? 'KWD' }} {{ number_format($task->price, 3) }}</p>
                                                </div>
                                                <div class="flex justify-between items-center pl-4">
                                                    <p class="text-sm text-gray-500">Tax:</p>
                                                    <p class="text-sm text-gray-700">{{ $task->currency ?? 'KWD' }} {{ number_format($task->tax, 3) }}</p>
                                                </div>
                                            </div>
                                            <div class="mt-4 pt-4 border-t border-gray-200">
                                                <div class="flex justify-between items-center">
                                                    <p class="text-sm font-semibold text-gray-700">Total:</p>
                                                    <p class="text-sm font-semibold text-gray-900">{{ $task->currency ?? 'KWD' }} {{ number_format($task->total, 3) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Type-Specific Details - 3 columns on lg -->
                                <div class="xl:col-span-3 bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col">
                                    <div class="px-4 sm:px-6 py-4 bg-slate-50 rounded-t-lg flex-shrink-0">
                                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                            {{ ucfirst($task->type) }} Details
                                        </h2>
                                    </div>
                                    <div class="flex-1 overflow-auto">
                                        @if($task->type === 'flight')
                                        <div class="p-4 sm:p-6">
                                            @php
                                            $hasDuration = $task->flightDetail->whereNotNull('duration_time')->isNotEmpty();
                                            $hasBaggage = $task->flightDetail->whereNotNull('baggage_allowed')->isNotEmpty();
                                            @endphp

                                            <!-- Mobile Flight Cards -->
                                            <div class="block sm:hidden space-y-4">
                                                @forelse($task->flightDetail as $flight)
                                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded-full">
                                                            Flight {{ $loop->iteration }}
                                                        </span>
                                                        @if($flight->class_type)
                                                        <span class="bg-blue-100 text-blue-700 text-xs font-medium px-2 py-0.5 rounded-full">
                                                            {{ ucfirst($flight->class_type) }}
                                                        </span>
                                                        @endif
                                                    </div>
                                                    
                                                    <div class="flex items-center gap-3 mb-3">
                                                        <div class="flex-1">
                                                            <p class="text-xs text-gray-500 uppercase">From</p>
                                                            <p class="text-sm font-medium text-gray-900">{{ $flight->airport_from ?? 'N/A' }}</p>
                                                            <p class="text-xs text-gray-500">
                                                                {{ $flight->departure_time ? \Carbon\Carbon::parse($flight->departure_time)->format('d M, H:i') : '-' }}
                                                            </p>
                                                        </div>
                                                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                                        </svg>
                                                        <div class="flex-1 text-right">
                                                            <p class="text-xs text-gray-500 uppercase">To</p>
                                                            <p class="text-sm font-medium text-gray-900">{{ $flight->airport_to ?? 'N/A' }}</p>
                                                            <p class="text-xs text-gray-500">
                                                                {{ $flight->arrival_time ? \Carbon\Carbon::parse($flight->arrival_time)->format('d M, H:i') : '-' }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="flex flex-wrap gap-3 text-xs text-gray-600 pt-2 border-t border-gray-200">
                                                        <div>
                                                            <span class="text-gray-400">Airline:</span>
                                                            <span class="font-medium">{{ $flight->airline ?? 'N/A' }} {{ $flight->flight_number ?? '' }}</span>
                                                        </div>
                                                        @if($hasDuration && $flight->duration_time)
                                                        <div>
                                                            <span class="text-gray-400">Duration:</span>
                                                            <span class="font-medium">{{ $flight->duration_time }}</span>
                                                        </div>
                                                        @endif
                                                        @if($hasBaggage && $flight->baggage_allowed)
                                                        <div>
                                                            <span class="text-gray-400">Baggage:</span>
                                                            <span class="font-medium">{{ $flight->baggage_allowed }}</span>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @empty
                                                <div class="text-center py-8 text-gray-500 italic">
                                                    No flight details available
                                                </div>
                                                @endforelse
                                            </div>

                                            <!-- Desktop Flight Table -->
                                            <div class="hidden sm:block overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flight</th>
                                                            @if($hasDuration)
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                                            @endif
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                                            @if($hasBaggage)
                                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Baggage</th>
                                                            @endif
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        @forelse($task->flightDetail as $flight)
                                                        <tr>
                                                            <td class="px-4 py-3 text-sm text-gray-500">{{ $loop->iteration }}</td>
                                                            <td class="px-4 py-3">
                                                                <p class="text-sm font-medium text-gray-900">{{ $flight->airport_from ?? 'N/A' }}</p>
                                                                <p class="text-xs text-gray-500 mt-1">
                                                                    {{ $flight->departure_time ? \Carbon\Carbon::parse($flight->departure_time)->format('d M Y, H:i') : '-' }}
                                                                </p>
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <p class="text-sm font-medium text-gray-900">{{ $flight->airport_to ?? 'N/A' }}</p>
                                                                <p class="text-xs text-gray-500 mt-1">
                                                                    {{ $flight->arrival_time ? \Carbon\Carbon::parse($flight->arrival_time)->format('d M Y, H:i') : '-' }}
                                                                </p>
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <p class="text-sm font-medium text-gray-900">{{ $flight->airline ?? 'N/A' }}</p>
                                                                <p class="text-xs text-gray-500">{{ $flight->flight_number ?? '' }}</p>
                                                            </td>
                                                            @if($hasDuration)
                                                            <td class="px-4 py-3 text-sm text-gray-500">{{ $flight->duration_time ?? '-' }}</td>
                                                            @endif
                                                            <td class="px-4 py-3">
                                                                @if($flight->class_type)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                                    {{ ucfirst($flight->class_type) }}
                                                                </span>
                                                                @else
                                                                <span class="text-sm text-gray-400">-</span>
                                                                @endif
                                                            </td>
                                                            @if($hasBaggage)
                                                            <td class="px-4 py-3">
                                                                @if($flight->baggage_allowed)
                                                                <span class="px-2 py-0.5 text-xs font-medium">
                                                                    {{ $flight->baggage_allowed }}
                                                                </span>
                                                                @else
                                                                <span class="text-sm text-gray-400">-</span>
                                                                @endif
                                                            </td>
                                                            @endif
                                                        </tr>
                                                        @empty
                                                        <tr>
                                                            <td colspan="{{ 4 + ($hasDuration ? 1 : 0) + ($hasBaggage ? 1 : 0) }}" class="px-4 py-3 text-sm text-gray-500 text-center italic">
                                                                No flight details available
                                                            </td>
                                                        </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        @elseif($task->type === 'hotel')
                                        <div class="p-4 sm:p-6">
                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
                                                <div class="sm:col-span-3">
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Hotel Name</p>
                                                    @if($task->supplier_id == 2 && isset($mapHotels[$task->id]))
                                                        <p class="text-sm font-medium text-gray-900">{{ $mapHotels[$task->id]->name ?? 'N/A' }}</p>
                                                    @else
                                                        <p class="text-sm font-medium text-gray-900">{{ ucfirst($task->hotelDetails->hotel->name ?? 'N/A') }}</p>
                                                    @endif
                                                </div>

                                                @if($task->supplier_id == 2 && isset($mapHotels[$task->id]))
                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Address</p>
                                                    <p class="text-sm font-medium text-gray-900">{{ ucfirst($mapHotels[$task->id]->address ?? 'N/A') }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Zipcode</p>
                                                    <p class="text-sm font-medium text-gray-900">{{ $mapHotels[$task->id]->zipCode ?? 'N/A' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Location</p>
                                                    <p class="text-sm text-gray-900">{{ ucwords(strtolower($mapHotels[$task->id]->city->name)) ?? 'N/A' }}, {{ $mapHotels[$task->id]->city->country->name ?? 'N/A' }}</p>
                                                </div>

                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Phone</p>
                                                    <p class="text-sm font-medium text-gray-900">+{{ $mapHotels[$task->id]->telephone ?? 'N/A' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Fax</p>
                                                    <p class="text-sm font-medium text-gray-900">{{ $mapHotels[$task->id]->fax ?? 'N/A' }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Email</p>
                                                    <p class="text-sm font-medium text-gray-900">{{ $mapHotels[$task->id]->email ?? 'N/A' }}</p>
                                                </div>
                                                @endif

                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Check In</p>
                                                    <p class="text-sm text-gray-900">{{ \Carbon\Carbon::parse($task->hotelDetails->check_in)->format('D, d M Y') }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Check Out</p>
                                                    <p class="text-sm text-gray-900">{{ \Carbon\Carbon::parse($task->hotelDetails->check_out)->format('D, d M Y') }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Duration</p>
                                                    <p class="text-sm text-gray-900 font-semibold">{{ \Carbon\Carbon::parse($task->hotelDetails->check_in)->diffInDays($task->hotelDetails->check_out) }} Nights</p>
                                                </div>
                                            </div>
                                        </div>
                                        @elseif($task->type === 'visa')
                                        <div class="p-4 sm:p-6">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Visa Type</p>
                                                    <p class="text-sm text-gray-900">{{ $task->visaDetails->visa_type ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        @elseif($task->type === 'insurance')
                                        <div class="p-4 sm:p-6">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                                                <div>
                                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Insurance Plan</p>
                                                    <p class="text-sm text-gray-900">{{ $task->insuranceDetails->plan_name ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        @else
                                        <div class="bg-gradient-to-br from-slate-50 to-gray-100 rounded-lg shadow-sm border border-gray-200 flex flex-col items-center justify-center p-8">
                                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="text-sm text-gray-400">No additional details for this task type</p>
                                        </div>
                                        @endif

                                        <!-- Collapsible Section -->
                                        <div class="" x-data="{ 
                                            showCancellation: false, 
                                            showAdditional: false 
                                        }">
                                            <!-- Cancellation Policy -->
                                            @if($task->cancellation_policy)
                                            <div>
                                                <button @click="showCancellation = !showCancellation" 
                                                        class="w-full px-4 sm:px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition">
                                                    <div class="flex items-center gap-2">
                                                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                        </svg>
                                                        <span class="text-sm font-semibold text-gray-700">Cancellation Policy</span>
                                                    </div>
                                                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="showCancellation ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                                <div x-cloak x-show="showCancellation" 
                                                    class="px-4 sm:px-6 pb-4 mt-3">
                                                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $task->cancellation_policy }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif

                                            <!-- Additional Information -->
                                            @if($task->additional_info || $task->venue)
                                            <div>
                                                <button @click="showAdditional = !showAdditional" 
                                                        class="w-full px-4 sm:px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition">
                                                    <div class="flex items-center gap-2">
                                                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span class="text-sm font-semibold text-gray-700">Additional Information</span>
                                                    </div>
                                                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="showAdditional ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                                <div x-cloak x-show="showAdditional" 
                                                    class="px-4 sm:px-6 pb-4 mt-3">
                                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-2">
                                                        @if($task->additional_info)
                                                        <div>
                                                            <p class="text-xs font-medium text-gray-500 uppercase mb-1">Notes</p>
                                                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $task->additional_info }}</p>
                                                        </div>
                                                        @endif
                                                        @if($task->venue)
                                                        <div>
                                                            <p class="text-xs font-medium text-gray-500 uppercase mb-1">Venue</p>
                                                            <p class="text-sm text-gray-700">{{ $task->venue }}</p>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Single Task Edit Modal -->
                        <div x-cloak x-show="singleEditMode && selectedTaskId === {{ $task->id }}"
                            class="fixed inset-0 z-50 overflow-y-auto"
                            style="display: none;">

                            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity"
                                @click="singleEditMode = false"></div>

                            <div class="flex min-h-screen items-center justify-center p-2 sm:p-4">
                                @php
                                    $isInvoicedAndPaid = \App\Models\InvoiceDetail::where('task_id', $task->id)
                                        ->whereHas('invoice', fn($q) => $q->where('status', 'paid'))
                                        ->exists();
                                @endphp
                                
                                <div x-show="singleEditMode"
                                    x-data="{ readOnly: {{ $isInvoicedAndPaid ? 'true' : 'false' }} }"
                                    class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl overflow-hidden"
                                    @click.stop>

                                    <form action="{{ route('tasks.update', $task->id) }}" method="POST" class="flex flex-col" style="max-height: 90vh;">
                                        @csrf
                                        @method('PUT')
                                        
                                        <!-- Header -->
                                        <div class="px-4 sm:px-6 py-4 bg-slate-50 border-b border-gray-200 flex items-start justify-between flex-shrink-0">
                                            <div class="flex-1 min-w-0 pr-2">
                                                <h2 class="text-lg sm:text-xl font-bold text-gray-800">Edit Task Details</h2>
                                                <p class="text-gray-600 italic text-xs mt-1">
                                                    @if($isInvoicedAndPaid)
                                                        This task is invoiced and paid - editing is disabled
                                                    @else
                                                        Please update the task details to ensure accurate information
                                                    @endif
                                                </p>
                                            </div>
                                            <button type="button" @click="singleEditMode = false" class="text-gray-400 hover:text-red-500 text-2xl p-2 flex-shrink-0">
                                                &times;
                                            </button>
                                        </div>

                                        <!-- Form Content - Scrollable -->
                                        <div class="p-4 sm:p-6 overflow-y-auto flex-1">
                                            <fieldset :disabled="readOnly" :class="readOnly ? 'opacity-80' : ''">
                                                <div class="flex flex-col gap-4 sm:gap-6">
                                                    <!-- Reference & Status -->
                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                        <div class="flex-1">
                                                            <label for="reference" class="block text-sm font-medium text-gray-700">Reference</label>
                                                            <input type="text" class="border border-gray-300 p-2 rounded-md w-full text-base" name="reference" value="{{ $task->reference }}">
                                                        </div>
                                                        <div class="flex-1">
                                                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                                            @if ($task->status === 'refund')
                                                            <select name="status" class="border border-gray-300 p-2 rounded-md w-full text-base" disabled>
                                                                <option value="refund" selected>Refund</option>
                                                            </select>
                                                            <input type="hidden" name="status" value="refund">
                                                            @else
                                                            <select name="status" id="status_{{ $task->id }}" class="border border-gray-300 p-2 rounded-md w-full text-base">
                                                                <option value="">Set Status</option>
                                                                <option value="confirmed" {{ $task->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                                                <option value="issued" {{ $task->status === 'issued' ? 'selected' : '' }}>Issued</option>
                                                                <option value="reissued" {{ $task->status === 'reissued' ? 'selected' : '' }}>Reissued</option>
                                                                <option value="refund" {{ $task->status === 'refund' ? 'selected' : '' }}>Refund</option>
                                                                <option value="void" {{ $task->status === 'void' ? 'selected' : '' }}>Void</option>
                                                                <option value="emd" {{ $task->status === 'emd' ? 'selected' : '' }}>Emd</option>
                                                            </select>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <!-- Supplier & Type -->
                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Supplier</label>
                                                            <input type="text" class="border border-gray-300 p-2 rounded-md w-full bg-gray-200" value="{{ $task->supplier->name ?? '' }}" readonly>
                                                            <input type="hidden" name="supplier_id" value="{{ $task->supplier->id ?? '' }}">
                                                        </div>
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Task Type</label>
                                                            <input type="text" class="border border-gray-300 p-2 rounded-md w-full bg-gray-200" value="{{ ucfirst($task->type) }}" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Client & Agent -->
                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                        <div class="flex-1 min-w-0 {{ $task->client ?? 'required-input'}}">
                                                            <label class="block text-sm font-medium text-gray-700">Client</label>
                                                            <x-searchable-dropdown
                                                                name="client_id"
                                                                :items="$clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name . ' - ' . $c->phone])"
                                                                :selectedId="$task->client_id"
                                                                :selectedName="$task->client ? $task->client->name . ' - ' . $task->client->phone : null"
                                                                placeholder="Select Client" />
                                                        </div>
                                                        <div class="flex-1 min-w-0 {{ $task->agent ?? 'required-input'}}">
                                                            <label class="block text-sm font-medium text-gray-700">Agent</label>
                                                            <x-searchable-dropdown
                                                                name="agent_id"
                                                                :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
                                                                :selectedId="$task->agent_id"
                                                                :selectedName="$task->agent->name ?? null"
                                                                placeholder="Select Agent" />
                                                        </div>
                                                    </div>

                                                    <!-- Pricing -->
                                                    <div x-data="{
                                                        rawPrice: '{{ $task->price ?? 0 }}',
                                                        rawTax: '{{ $task->tax ?? 0 }}',
                                                        rawSurcharge: '{{ $task->surcharge ?? 0 }}',
                                                        rawSupplierSurcharge: '{{ $task->supplier_surcharge ?? 0 }}',
                                                        total: 0,
                                                        parseNum(v) {
                                                            if (!v) return 0;
                                                            const num = parseFloat(String(v).replace(/,/g,'').trim());
                                                            return isNaN(num) ? 0 : num;
                                                        },
                                                        calculate() {
                                                            const base = this.parseNum(this.rawPrice) + this.parseNum(this.rawTax) + this.parseNum(this.rawSurcharge);
                                                            const supplier = this.parseNum(this.rawSupplierSurcharge);
                                                            this.total = +(base + supplier).toFixed(3);
                                                        }
                                                    }" x-init="calculate()" x-effect="calculate()" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
                                                        <div class="col-span-1">
                                                            <label class="block text-sm font-medium text-gray-700">Price</label>
                                                            <input type="text" name="price" x-model="rawPrice" class="border border-gray-300 p-2 rounded-md w-full text-sm">
                                                        </div>
                                                        <div class="col-span-1">
                                                            <label class="block text-sm font-medium text-gray-700">Tax</label>
                                                            <input type="text" name="tax" x-model="rawTax" class="border border-gray-300 p-2 rounded-md w-full text-sm">
                                                        </div>
                                                        <div class="col-span-1">
                                                            <label class="block text-sm font-medium text-gray-700">Surcharge</label>
                                                            <input type="text" name="surcharge" x-model="rawSurcharge" class="border border-gray-300 p-2 rounded-md w-full text-sm">
                                                        </div>
                                                        <div class="col-span-1">
                                                            <label class="block text-xs sm:text-sm font-medium text-gray-700 truncate">Supplier Surcharge</label>
                                                            <input type="text" :value="parseNum(rawSupplierSurcharge).toFixed(3)" readonly class="border border-gray-300 bg-gray-100 p-2 rounded-md w-full text-sm">
                                                        </div>
                                                        <div class="col-span-2 sm:col-span-1">
                                                            <label class="block text-sm font-medium text-gray-700">Total</label>
                                                            <input type="text" name="total" :value="total.toFixed(3)" readonly class="border border-gray-300 p-2 rounded-md w-full text-sm font-semibold">
                                                        </div>
                                                    </div>

                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                                                            <select name="payment_method_account_id" class="border border-gray-300 p-2 rounded-md w-full text-sm">
                                                                <option value="">Select Payment Method</option>
                                                                @foreach($listOfCreditors as $groupName => $accounts)
                                                                <optgroup label="{{ $groupName }}">
                                                                    @foreach($accounts as $method)
                                                                    <option value="{{ $method['id'] }}" {{ $task->payment_method_account_id == $method['id'] ? 'selected' : '' }}>
                                                                        {{ $method['name'] }}
                                                                    </option>
                                                                    @endforeach
                                                                </optgroup>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Issued Date</label>
                                                            <input type="date" name="supplier_pay_date" class="border border-gray-300 p-2 rounded-md w-full" value="{{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('Y-m-d') : '' }}">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700">Additional Info</label>
                                                        <textarea rows="3" readonly class="border border-gray-300 p-3 rounded-md bg-gray-200 w-full resize-none text-sm">{{ $task->additional_info }} - {{ $task->venue }}</textarea>
                                                    </div>
                                                </div>
                                            </fieldset>
                                        </div>

                                        <!-- Footer -->
                                        <div class="px-4 sm:px-6 py-4 bg-slate-50 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 flex-shrink-0">
                                            <button type="button" @click="singleEditMode = false" class="w-full sm:w-auto px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                                Cancel
                                            </button>
                                            <button type="submit" 
                                                :disabled="readOnly" 
                                                :class="readOnly ? 'cursor-not-allowed opacity-60' : ''"
                                                :title="readOnly ? 'This task is invoiced and paid - editing is not allowed' : ''"
                                                class="w-full sm:w-auto px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                                                Update Task
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <!-- Proceed to Invoice Section -->
                    <div class="mt-6 bg-gradient-to-r from-blue-50 to-sky-50 rounded-lg border border-blue-200 p-4 sm:p-6 flex-shrink-0">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-base sm:text-lg font-semibold text-blue-800">Ready to Invoice?</h3>

                                <p class="text-sm text-blue-600 mt-1">
                                    Create invoice for
                                    <span class="font-semibold" x-text="selectedForInvoice.length"></span>
                                    out of
                                    <span class="font-semibold">{{ $tasks->count() }}</span>
                                    tasks
                                </p>
                            </div>

                            <a :href="getInvoiceUrl()"
                            class="w-full sm:w-auto px-6 py-3 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2"
                            :class="selectedForInvoice.length === 0 ? 'opacity-50 pointer-events-none' : ''">
                                <span>Create Invoice</span>
                                <span class="bg-white text-blue-700 text-xs font-bold px-2 py-1 rounded-full" x-text="selectedForInvoice.length"></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Task Edit Modal -->
        <div x-cloak x-show="bulkEditMode"
            class="fixed inset-0 z-50 overflow-y-auto"
            style="display: none;">

            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity" @click="bulkEditMode = false"></div>

            <div class="flex min-h-screen items-center justify-center p-2 sm:p-4">
                <div x-show="bulkEditMode"
                    class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl overflow-hidden"
                    @click.stop>

                    <form action="{{ route('tasks.bulk-update') }}" method="POST" class="flex flex-col" style="max-height: 90vh;">
                        @csrf
                        
                        <!-- Header -->
                        <div class="px-4 sm:px-6 py-4 bg-slate-50 border-b border-gray-200 flex items-start justify-between flex-shrink-0">
                            <div class="flex-1 min-w-0 pr-2">
                                <h2 class="text-lg sm:text-xl font-bold text-gray-800">Bulk Edit All Tasks</h2>
                                <p class="text-gray-600 italic text-xs mt-1">Changes will apply to all {{ $tasks->count() }} selected tasks</p>
                            </div>
                            <button type="button" @click="bulkEditMode = false" class="text-gray-400 hover:text-red-500 text-2xl p-2 flex-shrink-0">
                                &times;
                            </button>
                        </div>

                        <!-- Form Content - Scrollable -->
                        <div class="p-4 sm:p-6 overflow-y-auto flex-1">
                            @foreach($tasks as $task)
                            <input type="hidden" name="task_ids[]" value="{{ $task->id }}">
                            @endforeach

                            <div class="space-y-5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                                    <x-searchable-dropdown
                                        name="bulk_client_id"
                                        :items="$clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name . ' - ' . $c->phone])"
                                        placeholder="Select Client" />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Agent</label>
                                    <x-searchable-dropdown
                                        name="bulk_agent_id"
                                        :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
                                        placeholder="Select Agent" />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                    <x-searchable-dropdown
                                        name="bulk_payment_method_id"
                                        :items="$paymentMethod->map(fn($m) => ['id' => $m->id, 'name' => $m->name])"
                                        placeholder="Select Payment Method" />
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-4 sm:px-6 py-4 bg-slate-50 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 flex-shrink-0">
                            <button type="button" @click="bulkEditMode = false" class="w-full sm:w-auto px-6 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                Cancel
                            </button>
                            <button type="submit" class="w-full sm:w-auto px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                                Update All Tasks
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Client Registration Modal -->
        <div x-show="showUploadForm" x-transition x-cloak
            class="fixed inset-0 z-50 bg-gray-700 bg-opacity-60 flex items-center justify-center p-2">
            <div class="bg-white rounded-lg p-4 sm:p-6 w-full max-w-sm shadow-xl">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800">Upload Passport</h2>
                        <p class="text-gray-500 italic text-xs mt-1">Please choose
                            appropriate file to proceed</p>
                    </div>
                    <button @click="closeAll()"
                        class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">
                        &times;
                    </button>
                </div>

                <!--                                             <input type="hidden" name="task_id" :value="modalTaskId"> -->
                <div id="passport">
                    <input type="file" id="passport-upload-input"
                        accept="image/*,application/pdf"
                        class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg cursor-pointer dark:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        hidden>
                    <div id="file-preview-container" class="mt-4"></div>
                    <!-- For image preview -->
                    <div id="upload-status" class="mt-2 text-sm text-gray-600"></div>
                    <!-- For upload status -->
                    <div id="passport-details" class="mt-4 text-sm text-gray-800"></div>
                    <!-- For displaying extracted details -->
                </div>

                <div class="flex justify-between mt-10">
                    <button @click="closeAll()" type="button"
                        class="w-32 bg-gray-300 hover:bg-gray-400 font-semibold py-2 rounded-full text-sm transition duration-150">
                        Cancel
                    </button>

                    <button id="submit-passport-upload"
                        @click="showFileInput = !showFileInput"
                        class="w-32 flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-3 rounded-full shadow-sm transition-all duration-150">
                        Upload
                    </button>

                </div>
            </div>
        </div>
        <div x-cloak x-show="showManualForm" 
            x-cloak
            class="fixed inset-0 z-50 bg-gray-700 bg-opacity-60 flex items-center justify-center px-2">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-4 sm:p-6 max-h-[95vh] overflow-y-auto transition-all duration-300">
                <!-- Header with title and close button -->
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-800">Client Registration</h2>
                    <button @click="showManualForm = false"
                        class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                </div>

                <!-- Subtitle -->
                <p class="text-gray-600 italic text-xs mb-4 sm:mb-6">Please fill in the required client information to register</p>

                <!-- Form -->
                <form action="{{ route('clients.store') }}" method="POST"
                    id="client-formTask" class="space-y-3 sm:space-y-4">
                    @csrf
                    <input type="hidden" name="task_id" :value="modalTaskId">
                    <input type="hidden" name="agent_id" :value="modalAgentId">
                    <!-- Name -->
                    <div id="upload-passport-container"
                        class="my-2 border-2 border-dashed border-gray-400 rounded-md w-full w-full flex flex-col justify-center gap-2 items-center p-2 min-h-20 max-h-48"
                        ondrop="dropHandler(event);" ondragover="dragOverHandler(event);">
                        <svg width="24" height="24" viewBox="0 0 24 24"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 10L13 10" stroke="#1C274C" stroke-width="1.5"
                                stroke-linecap="round" />
                            <path
                                d="M10 3H16.5C16.9644 3 17.1966 3 17.3916 3.02567C18.7378 3.2029 19.7971 4.26222 19.9743 5.60842C20 5.80337 20 6.03558 20 6.5"
                                stroke="#1C274C" stroke-width="1.5" />
                            <path
                                d="M2 6.94975C2 6.06722 2 5.62595 2.06935 5.25839C2.37464 3.64031 3.64031 2.37464 5.25839 2.06935C5.62595 2 6.06722 2 6.94975 2C7.33642 2 7.52976 2 7.71557 2.01738C8.51665 2.09229 9.27652 2.40704 9.89594 2.92051C10.0396 3.03961 10.1763 3.17633 10.4497 3.44975L11 4C11.8158 4.81578 12.2237 5.22367 12.7121 5.49543C12.9804 5.64471 13.2651 5.7626 13.5604 5.84678C14.0979 6 14.6747 6 15.8284 6H16.2021C18.8345 6 20.1506 6 21.0062 6.76946C21.0849 6.84024 21.1598 6.91514 21.2305 6.99383C22 7.84935 22 9.16554 22 11.7979V14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14V6.94975Z"
                                stroke="#1C274C" stroke-width="1.5" />
                        </svg>
                        <input type="file" name="file" id="file-task-passport"
                            class="hidden"
                            accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf">
                        <p id="task-passport-file-name" class="text-sm text-center">
                            You can drag and drop a file here
                        </p>
                        <label for="file-task-passport"
                            class="bg-black text-white font-semibold p-2 rounded-md border-2 border-black hover:border-2 hover:border-cyan-500 text-sm">
                            Upload File
                        </label>
                    </div>
                    <div class="my-2">
                        <button id="task-passport-process-btn"
                            class="w-full bg-gray-300 text-gray-500 font-semibold py-2 rounded-full text-sm transition duration-150 cursor-not-allowed"
                            disabled>
                            Process File
                        </button>
                    </div>
                    <div class="mb-3">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                        <input type="text" name="first_name" id="nameTask"
                            :value="modalClientName" placeholder="Client's First Name"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            required>
                    </div>
                    <div class="mb-3">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                        <input type="text" name="middle_name" id="middleNameTask"
                            placeholder="Client's Middle Name"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-3">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input type="text" name="last_name" id="lastNameTask"
                            placeholder="Client's Last Name"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-3">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1">Passenger's Name</label>
                        <input type="text" name="passenger_name" id="passengerName"
                            :value="modalPassengerName" placeholder="Passengers's name"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100 text-gray-500 focus:outline-none focus:ring-0 focus:border-gray-300 cursor-not-allowed"
                            disabled>
                    </div>

                    <!-- Email + DOB -->
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-3">
                        <div class="flex-1">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" id="emailTask"
                                placeholder="Client's email"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex-1">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1">Date
                                of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birthTask"
                                class="w-full text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="mb-3">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="relative w-full sm:w-40">
                                <x-searchable-dropdown name="dial_code" :items="\App\Models\Country::all()->map(
                                    fn($country) => [
                                        'id' => $country->dialing_code,
                                        'name' =>
                                            $country->dialing_code . ' ' . $country->name,
                                    ],
                                )"
                                    placeholder=" Search Dial Code" :showAllOnOpen="true" />
                            </div>
                            <input type="text" name="phone"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                id="phoneTask" placeholder="Client's phone number"
                                required>
                        </div>
                    </div>

                    <!-- Passport + Civil -->
                    <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-3">
                        <div class="flex-1">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1">Passport
                                Number</label>
                            <input type="text" name="passport_no" id="passport_noTask"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex-1">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1">Civil
                                Number</label>
                            <input type="text" name="civil_no" id="civil_noTask"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <!-- Address -->
                    <div class="mb-3">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" name="address" id="addressTask"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Client's address">
                    </div>

                    <!-- Agent Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Agent's Name</label>
                        <x-searchable-dropdown name="agent_id"
                            :items="$agents" placeholder="Search Agent"
                            :showAllOnOpen="true" />
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-between gap-3 pt-4 mt-4">
                        <button type="button" @click="closeAll()"
                            class="flex-1 sm:w-32 sm:flex-none bg-gray-300 hover:bg-gray-400 font-semibold py-3 sm:py-2 rounded-full text-sm transition duration-150">
                            Cancel
                        </button>
                        <button type="submit"
                            class="flex-1 sm:w-32 sm:flex-none bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 sm:py-2 rounded-full text-sm transition duration-150">
                            Register Client
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const clientForm = document.getElementById("client-formTask");

        const file = document.getElementById('file-task-passport');
        const fileName = document.getElementById('task-passport-file-name');
        const taskPassportProcessBtn = document.getElementById('task-passport-process-btn');

        if (file && fileName && taskPassportProcessBtn) {
            file.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            taskPassportProcessBtn.addEventListener('click', (e) => {
                e.preventDefault();
                processFileWithAI();
            });
        } else {
            console.warn("Required elements not found: file, fileName, or taskPassportProcessBtn");
        }

        file.addEventListener('change', (e) => {
            fileName.textContent = e.target.files[0].name;
            file.innerHTML = '';
            let img = document.createElement('img');
            img.src = URL.createObjectURL(e.target.files[0]);
            console.log(img.src);
            img.width = 100;
            img.height = 100;
            file.appendChild(img);

            enableButton(taskPassportProcessBtn);
        });

        dropHandler = (e) => {
            e.preventDefault();

            const droppedFile = e.dataTransfer.files[0];
            if (!droppedFile) return;

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(droppedFile);
            file.files = dataTransfer.files;

            fileName.textContent = droppedFile.name;

            if (droppedFile.type.startsWith('image/')) {
                file.innerHTML = '';
                const img = document.createElement('img');
                img.src = URL.createObjectURL(droppedFile);
                img.width = 100;
                img.height = 100;
                file.appendChild(img);
            }

            enableButton(taskPassportProcessBtn);
        };

        dragOverHandler = (e) => {
            console.log('File in drop area');
            e.preventDefault();
        }

        function processFileWithAI() {
            const fileInput = document.getElementById('file-task-passport');
            const processBtn = document.getElementById('task-passport-process-btn');
            if (fileInput.files.length === 0) {
                alert('Please upload a file first.');
                return;
            }

            // Show loading indication and disable button
            processBtn.disabled = true;
            processBtn.textContent = 'Processing...';
            processBtn.classList.add('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            fetch("{{ route('tasks.upload.passport') }}", {
                    method: "POST",
                    body: formData,
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const client = data.data;
                        console.log("Extracted client data:", client);

                        const nameInput = document.getElementById('nameTask');
                        if (nameInput) nameInput.value = client.name || '';

                        const middleNameInput = document.getElementById('middleNameTask');
                        if (middleNameInput) middleNameInput.value = client.middle_name || '';

                        const lastNameInput = document.getElementById('lastNameTask');
                        if (lastNameInput) lastNameInput.value = client.last_name || '';

                        const passportInput = document.getElementById('passport_noTask');
                        if (passportInput) passportInput.value = client.passport_no || '';

                        const civilInput = document.getElementById('civil_noTask');
                        if (civilInput) civilInput.value = client.civil_no || '';

                        const addressInput = document.getElementById('addressTask');
                        if (addressInput) addressInput.value = client.address || '';

                        const dobInput = document.querySelector('input[name="date_of_birthTask"]');
                        if (dobInput && client.date_of_birth) {
                            dobInput.value = client.date_of_birth.replace(/\//g, '-');
                        }
                        // Handle the response data as needed
                    } else {
                        alert('Error processing file: ' + data.message);
                        console.error('Error:', data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the file.');
                })
                .finally(() => {
                    // Restore button state
                    processBtn.disabled = false;
                    processBtn.textContent = 'Process File';
                    processBtn.classList.remove('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');
                });
        }
        function disableButton(button) {
            console.log('Disabling button:', button);
            if (!button.classList.contains('cursor-not-allowed') && !button.classList.contains('opacity-50')) {
                button.classList.add('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');
            }
            button.disabled = true;
        }

        function enableButton(button) {
            console.log('Enabling button:', button);
            if (button.classList.contains('cursor-not-allowed') || button.classList.contains('opacity-50')) {
                button.classList.remove('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');
            }
            button.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white', 'font-semibold', 'py-2', 'rounded-full',
                'text-sm', 'transition', 'duration-150');
            button.disabled = false;
        }
    </script>
</x-app-layout>