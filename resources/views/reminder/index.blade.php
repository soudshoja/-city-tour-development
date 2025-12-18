<x-app-layout>

    <style>
        .tab-shape {
            clip-path: polygon(12px 0, calc(100% - 4px) 0, 100% 100%, 0 100%);
        }
    </style>

    <div x-data="{
        activeTab: 'reminder',
        showModal: false,
        showBulkModal: false,
        invoiceId: null,
        invoiceNumber: '',
        invoiceAmount: '',
        clientName: '',
        clientPhone: '',
        clientUnpaidCount: 0,
        agentName: '',
        agentPhone: '',
        agentUnpaidCount: 0,
        scope: 'invoice',
        sendToClient: true,
        sendToAgent: false,
        frequency: 'once',
        repeatValue: 3,
        repeatUnit: 'days',
        maxReminders: 5,

        openModal(id) {
            this.invoiceId = id;
            const row = document.querySelector(`[data-invoice-id='${id}']`);
            if (row) {
                this.invoiceNumber = row.dataset.invoiceNumber;
                this.invoiceAmount = row.dataset.invoiceAmount;
                this.clientName = row.dataset.clientName.toUpperCase();
                this.clientPhone = row.dataset.clientPhone;
                this.clientUnpaidCount = row.dataset.clientUnpaidCount;
                this.agentName = row.dataset.agentName.toUpperCase();
                this.agentPhone = row.dataset.agentPhone;
                this.agentUnpaidCount = row.dataset.agentUnpaidCount;
            }
            this.scope = 'invoice';
            this.sendToClient = true;
            this.sendToAgent = false;
            this.frequency = 'once';
            this.repeatValue = 3;
            this.repeatUnit = 'days';
            this.maxReminders = 5;
            this.showModal = true;
        }
    }">

        <div class="flex justify-between items-center my-4">
            <div class="flex items-center gap-5">
                <h2 class="text-3xl font-bold">Reminder</h2>
                <div class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                    <span class="text-xl font-bold text-white">{{ $invoices->total() }}</span>
                </div>
            </div>
        </div>

        <div class="panel bg-white rounded-lg shadow p-4">
            <!-- ═══════════════════════════════════════════════════════════════════════════ -->
            <!-- TABS NAVIGATION -->
            <!-- ═══════════════════════════════════════════════════════════════════════════ -->
            <div class="flex gap-1 mb-0 bg-slate-100 px-2 pt-2 rounded-t-lg">
                <!-- Invoices Tab -->
                <button
                    @click="activeTab = 'reminder'"
                    class="tab-shape relative px-6 py-2.5 text-sm font-medium transition-all duration-200"
                    :class="activeTab === 'reminder' 
                            ? 'bg-white text-blue-600 z-10 rounded-t-lg' 
                            : 'bg-slate-200 text-slate-500 hover:bg-slate-300 hover:text-slate-700 rounded-t-lg'">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        Invoices
                        <span class="bg-blue-100 text-blue-600 text-xs font-semibold px-2 py-0.5 rounded-full">{{ $invoices->total() }}</span>
                    </div>
                </button>

                <!-- Payments Tab -->
                <button
                    @click="activeTab = 'payment'"
                    class="tab-shape relative px-6 py-2.5 text-sm font-medium transition-all duration-200"
                    :class="activeTab === 'payment' 
                            ? 'bg-white text-blue-600 z-10 rounded-t-lg' 
                            : 'bg-slate-200 text-slate-500 hover:bg-slate-300 hover:text-slate-700 rounded-t-lg'">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        Payment
                        <span class="bg-blue-100 text-blue-600 text-xs font-semibold px-2 py-0.5 rounded-full"></span>
                    </div>
                </button>

                <!-- History Tab -->
                <button
                    @click="activeTab = 'history'"
                    class="tab-shape relative px-6 py-2.5 text-sm font-medium transition-all duration-200"
                    :class="activeTab === 'history' 
                            ? 'bg-white text-blue-600 z-10 rounded-t-lg' 
                            : 'bg-slate-200 text-slate-500 hover:bg-slate-300 hover:text-slate-700 rounded-t-lg'">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        History
                        @if(isset($reminderLogs) && $reminderLogs->count() > 0)
                        <span class="bg-slate-200 text-slate-600 text-xs font-semibold px-2 py-0.5 rounded-full">{{ $reminderLogs->total() }}</span>
                        @endif
                    </div>
                </button>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════════════════ -->
            <!-- TAB: REMINDER (Unpaid Invoices) -->
            <!-- ═══════════════════════════════════════════════════════════════════════════ -->
            <div x-show="activeTab === 'reminder'" class="bg-white dark:bg-gray-800 rounded-lg rounded-tl-none shadow-md p-4">
                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-slate-800">Unpaid Invoices</h3>
                        <p class="text-sm text-slate-500">{{ $invoices->total() }} invoices pending payment</p>
                    </div>
                   
                    <!--  <button
                        type="button"
                        @click="showBulkModal = true"
                        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        Send All Reminders
                    </button> -->
                </div>

                <!-- Table -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full">
                        @php
                        $currentSort = request('sort', 'due_date');
                        $currentDirection = request('direction', 'asc');

                        function getSortUrl($field, $currentSort, $currentDirection) {
                        $direction = ($currentSort === $field && $currentDirection === 'asc') ? 'desc' : 'asc';
                        return request()->fullUrlWithQuery(['sort' => $field, 'direction' => $direction]);
                        }
                        @endphp

                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <!-- Invoice - Sortable -->
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                    <a href="{{ getSortUrl('invoice_number', $currentSort, $currentDirection) }}"
                                        class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                        Invoice
                                        <span class="flex flex-col">
                                            <svg class="w-2.5 h-2.5 {{ $currentSort === 'invoice_number' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'invoice_number' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>

                                <!-- Client - Sortable (Alphabetical) -->
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                    <a href="{{ getSortUrl('client_name', $currentSort, $currentDirection) }}"
                                        class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                        Client
                                        <span class="flex flex-col">
                                            <svg class="w-2.5 h-2.5 {{ $currentSort === 'client_name' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'client_name' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>

                                <!-- Agent - Sortable (Alphabetical) -->
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                    <a href="{{ getSortUrl('agent_name', $currentSort, $currentDirection) }}"
                                        class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                        Agent
                                        <span class="flex flex-col">
                                            <svg class="w-2.5 h-2.5 {{ $currentSort === 'agent_name' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'agent_name' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>

                                <!-- Amount - Not Sortable -->
                                <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Amount</th>

                                <!-- Due Date - Sortable -->
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                    <a href="{{ getSortUrl('due_date', $currentSort, $currentDirection) }}"
                                        class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                        Due Date
                                        <span class="flex flex-col">
                                            <svg class="w-2.5 h-2.5 {{ $currentSort === 'due_date' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="w-2.5 h-2.5 -mt-1 {{ $currentSort === 'due_date' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>

                                <!-- Status - Not Sortable -->
                                <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>

                                <!-- Action - Not Sortable -->
                                <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($invoices as $invoice)
                            @php
                            $dueDate = \Carbon\Carbon::parse($invoice->due_date);
                            $today = \Carbon\Carbon::today();
                            $daysOverdue = $today->diffInDays($dueDate, false);

                            $clientPhone = '';
                            if ($invoice->client?->phone) {
                            $clientPhone = str_starts_with($invoice->client->phone, '+')
                            ? $invoice->client->phone
                            : ($invoice->client->country_code ?? '') . $invoice->client->phone;
                            }
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors"
                                data-invoice-id="{{ $invoice->id }}"
                                data-invoice-number="{{ $invoice->invoice_number }}"
                                data-invoice-amount="{{ $invoice->currency }} {{ number_format($invoice->amount, 2) }}"
                                data-client-name="{{ strtoupper($invoice->client->name ?? 'N/A') }}"
                                data-client-phone="{{ $clientPhone }}"
                                data-client-unpaid-count="{{ $invoice->client ? $invoice->client->invoices()->where('status', 'unpaid')->count() : 0 }}"
                                data-agent-name="{{ strtoupper($invoice->agent->name ?? 'N/A') }}"
                                data-agent-phone="{{ $invoice->agent->phone_number ?? '' }}"
                                data-agent-unpaid-count="{{ $invoice->agent ? $invoice->agent->invoices()->where('status', 'unpaid')->count() : 0 }}">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-800">{{ $invoice->invoice_number }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800">{{ strtoupper($invoice->client->name ?? 'N/A') }}</p>
                                        <p class="text-xs text-slate-400">{{ $clientPhone }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-slate-600">{{ strtoupper($invoice->agent->name ?? 'N/A') }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-semibold text-slate-800">{{ $invoice->currency }} {{ number_format($invoice->amount, 2) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800">{{ $dueDate->format('M d, Y') }}</p>
                                        @if($daysOverdue < 0)
                                            <p class="text-xs text-red-500 font-medium">{{ abs($daysOverdue) }} days overdue</p>
                                            @elseif($daysOverdue <= 7)
                                                <p class="text-xs text-amber-500 font-medium">Due in {{ $daysOverdue }} days</p>
                                                @else
                                                <p class="text-xs text-slate-400">Due in {{ $daysOverdue }} days</p>
                                                @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($daysOverdue < 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Overdue</span>
                                        @elseif($daysOverdue <= 7)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Due Soon</span>
                                            @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Pending</span>
                                            @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        @click="openModal({{ $invoice->id }})"
                                        class="p-2 text-slate-400 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="Send Reminder">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">No unpaid invoices found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($invoices->hasPages())
                <div class="mt-4">
                    {{ $invoices->links() }}
                </div>
                @endif
            </div>

            <!-- ═══════════════════════════════════════════════════════════════════════════ -->
            <!-- TAB: HISTORY (Reminder Logs) -->
            <!-- ═══════════════════════════════════════════════════════════════════════════ -->
            <div x-show="activeTab === 'history'" x-cloak class="bg-white dark:bg-gray-800 rounded-lg rounded-tl-none shadow-md p-4">
                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-slate-800">Reminder History</h3>
                        <p class="text-sm text-slate-500">All sent reminders</p>
                    </div>
                </div>

                <!-- History Table -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Invoice</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Sent To</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Recipient</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Sent At</th>
                                <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Response</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($reminderLogs ?? [] as $log)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3">
                                    <div>
                                        <span class="font-medium text-slate-800">#{{ $log->reminder->invoice_number ?? 'N/A' }}</span>
                                        @if($log->reminder)
                                        <p class="text-xs text-slate-400">{{ $log->reminder->invoice->currency ?? '' }} {{ number_format($log->reminder->invoice->amount ?? 0, 2) }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($log->sent_to === 'client')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Client</span>
                                    @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Agent</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800">{{ $log->recipient_name ?? 'N/A' }}</p>
                                        <p class="text-xs text-slate-400">{{ $log->recipient_phone ?? '' }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800">{{ $log->sent_at ? $log->sent_at->format('M d, Y') : 'N/A' }}</p>
                                        <p class="text-xs text-slate-400">{{ $log->sent_at ? $log->sent_at->format('h:i A') : '' }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($log->status === 'sent')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Sent</span>
                                    @elseif($log->status === 'delivered')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Delivered</span>
                                    @elseif($log->status === 'read')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Read</span>
                                    @elseif($log->status === 'failed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Failed</span>
                                    @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">{{ ucfirst($log->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($log->error_message)
                                    <p class="text-xs text-red-500">{{ Str::limit($log->error_message, 50) }}</p>
                                    @else
                                    <p class="text-xs text-slate-400">-</p>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <p>No reminder history yet</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if(isset($reminderLogs) && $reminderLogs->hasPages())
                <div class="mt-4">
                    {{ $reminderLogs->links() }}
                </div>
                @endif
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════════════ -->
        <!-- SINGLE INVOICE MODAL -->
        <!-- ═══════════════════════════════════════════════════════════════════════════ -->
        <div
            x-show="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @click.self="showModal = false"
            style="display: none;">
            <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col" @click.stop>

                <form action="{{ route('reminder.store') }}" method="POST" class="flex flex-col max-h-[90vh]">
                    @csrf

                    <input type="hidden" name="invoice_id" x-bind:value="invoiceId">

                    <!-- Header -->
                    <div class="p-5 border-b border-slate-200 flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-800">Send Reminder</h3>
                                <p class="text-sm text-slate-500">
                                    <span x-text="invoiceNumber"></span> • <span x-text="invoiceAmount"></span>
                                </p>
                            </div>
                            <button type="button" @click="showModal = false" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="p-5 space-y-5 overflow-y-auto flex-1">

                        <!-- SCOPE -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-3">Reminder Scope</label>
                            <div class="space-y-2">
                                <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-all"
                                    :class="scope === 'invoice' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                                    <input type="radio" name="target_type" value="invoice" x-model="scope" class="w-4 h-4 mt-0.5 text-blue-500">
                                    <div class="flex-1">
                                        <p class="font-medium text-slate-800 text-sm">This Invoice Only</p>
                                        <p class="text-xs text-slate-500" x-text="invoiceNumber + ' • ' + invoiceAmount"></p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-all"
                                    :class="scope === 'client' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                                    <input type="radio" name="target_type" value="client" x-model="scope" class="w-4 h-4 mt-0.5 text-blue-500">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <p class="font-medium text-slate-800 text-sm">All Client's Invoices</p>
                                            <span class="text-xs font-medium text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full" x-text="clientUnpaidCount + ' invoices'"></span>
                                        </div>
                                        <p class="text-xs text-slate-500" x-text="clientName"></p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition-all"
                                    :class="scope === 'agent' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                                    <input type="radio" name="target_type" value="agent" x-model="scope" class="w-4 h-4 mt-0.5 text-blue-500">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <p class="font-medium text-slate-800 text-sm">All Agent's Invoices</p>
                                            <span class="text-xs font-medium text-purple-600 bg-purple-100 px-2 py-0.5 rounded-full" x-text="agentUnpaidCount + ' invoices'"></span>
                                        </div>
                                        <p class="text-xs text-slate-500" x-text="agentName"></p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- RECIPIENTS -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-3">Send To</label>
                            <div class="space-y-2">
                                <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer transition-all"
                                    :class="sendToClient ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" name="send_to_client" value="1" x-model="sendToClient" class="w-4 h-4 text-blue-500 rounded">
                                        <div>
                                            <p class="font-medium text-slate-700 text-sm" x-text="clientName"></p>
                                            <p class="text-xs text-slate-400" x-text="clientPhone || 'No phone'"></p>
                                        </div>
                                    </div>
                                    <span class="text-xs text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">Client</span>
                                </label>

                                <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer transition-all"
                                    :class="sendToAgent ? 'border-purple-500 bg-purple-50' : 'border-slate-200'">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" name="send_to_agent" value="1" x-model="sendToAgent" class="w-4 h-4 text-purple-500 rounded">
                                        <div>
                                            <p class="font-medium text-slate-700 text-sm" x-text="agentName"></p>
                                            <p class="text-xs text-slate-400" x-text="agentPhone || 'No phone'"></p>
                                        </div>
                                    </div>
                                    <span class="text-xs text-purple-600 bg-purple-100 px-2 py-0.5 rounded-full">Agent</span>
                                </label>

                                <p x-show="!sendToClient && !sendToAgent" class="text-xs text-red-500">Please select at least one recipient</p>
                            </div>
                        </div>

                        <!-- MESSAGE -->
                        <div x-data="{
                            message: '',
                            maxWords: 500,
                            get wordCount() {
                                return this.message.trim() === '' ? 0 : this.message.trim().split(/\s+/).length;
                            },
                            limitWords() {
                                const words = this.message.trim().split(/\s+/);
                                if (words.length > this.maxWords) {
                                    this.message = words.slice(0, this.maxWords).join(' ');
                                }
                            }
                        }">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Message</label>
                            <textarea
                                name="message"
                                x-model="message"
                                @input="limitWords()"
                                rows="4"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"
                                placeholder="Enter your reminder message"></textarea>
                            <div class="flex justify-end mt-1">
                                <p class="text-xs" :class="wordCount >= maxWords ? 'text-red-500 font-medium' : 'text-slate-400'">
                                    <span x-text="wordCount"></span>/<span x-text="maxWords"></span> words
                                    <span x-show="wordCount >= maxWords" class="ml-1">limit reached</span>
                                </p>
                            </div>
                        </div>

                        <!-- FREQUENCY -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-3">Frequency</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center justify-center gap-2 p-3 border rounded-lg cursor-pointer"
                                    :class="frequency === 'once' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                                    <input type="radio" name="frequency" value="once" x-model="frequency" class="w-4 h-4 text-blue-500">
                                    <div class="text-center">
                                        <p class="font-medium text-slate-700 text-sm">One-time</p>
                                        <p class="text-xs text-slate-400">Send now only</p>
                                    </div>
                                </label>
                                <label class="flex items-center justify-center gap-2 p-3 border rounded-lg cursor-pointer"
                                    :class="frequency === 'auto' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                                    <input type="radio" name="frequency" value="auto" x-model="frequency" class="w-4 h-4 text-blue-500">
                                    <div class="text-center">
                                        <p class="font-medium text-slate-700 text-sm">Auto-repeat</p>
                                        <p class="text-xs text-slate-400">Schedule recurring</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- AUTO SETTINGS -->
                        <div x-show="frequency === 'auto'" x-transition class="p-4 bg-slate-50 rounded-lg space-y-4">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="repeatValue = 1; repeatUnit = 'days'"
                                    class="px-2.5 py-1 text-xs border rounded-md"
                                    :class="repeatValue == 1 && repeatUnit === 'days' ? 'border-blue-500 bg-white text-blue-600' : 'border-slate-200'">Daily</button>
                                <button type="button" @click="repeatValue = 3; repeatUnit = 'days'"
                                    class="px-2.5 py-1 text-xs border rounded-md"
                                    :class="repeatValue == 3 && repeatUnit === 'days' ? 'border-blue-500 bg-white text-blue-600' : 'border-slate-200'">Every 3 days</button>
                                <button type="button" @click="repeatValue = 7; repeatUnit = 'days'"
                                    class="px-2.5 py-1 text-xs border rounded-md"
                                    :class="repeatValue == 7 && repeatUnit === 'days' ? 'border-blue-500 bg-white text-blue-600' : 'border-slate-200'">Weekly</button>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-slate-600 mb-1">Repeat Every</label>
                                    <div class="flex gap-2">
                                        <input type="number" name="value" x-model="repeatValue" min="1" class="w-16 border border-slate-300 rounded-md px-2 py-1.5 text-sm">
                                        <select name="unit" x-model="repeatUnit" class="flex-1 border border-slate-300 rounded-md px-2 py-1.5 text-sm">
                                            <option value="hours">Hours</option>
                                            <option value="days">Days</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-600 mb-1">Max Reminders</label>
                                    <input type="number" name="max_reminder" x-model="maxReminders" min="1" max="20" class="w-full border border-slate-300 rounded-md px-2 py-1.5 text-sm">
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="p-5 border-t border-slate-200 flex justify-between items-center bg-slate-50 rounded-b-xl flex-shrink-0">
                        <button type="button" @click="showModal = false" class="px-4 py-2 text-slate-600 hover:bg-slate-200 rounded-lg text-sm">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!sendToClient && !sendToAgent"
                            :class="!sendToClient && !sendToAgent ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-600'"
                            class="px-5 py-2 bg-blue-500 text-white font-medium rounded-lg flex items-center gap-2 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            <span x-text="frequency === 'auto' ? 'Send & Schedule' : 'Send Now'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>


        <!-- ═══════════════════════════════════════════════════════════════════════════ -->
        <!-- BULK MODAL -->
        <!-- ═══════════════════════════════════════════════════════════════════════════ -->
        <div
            x-show="showBulkModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @click.self="showBulkModal = false"
            style="display: none;">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full" @click.stop>

                <form action="{{ route('reminder.bulk') }}" method="POST">
                    @csrf

                    <!-- Header -->
                    <div class="p-5 border-b border-slate-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-800">Send All Reminders</h3>
                                <p class="text-sm text-slate-500">{{ $invoices->total() }} unpaid invoices</p>
                            </div>
                            <button type="button" @click="showBulkModal = false" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="p-5 space-y-5">

                        <!-- Warning -->
                        <div class="flex items-start gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-amber-800">This will send reminders for all {{ $invoices->total() }} unpaid invoices</p>
                            </div>
                        </div>

                        <!-- Recipients -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-3">Send To</label>
                            <div class="space-y-2">
                                <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer"
                                    :class="sendToClient ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" name="send_to_client" value="1" x-model="sendToClient" class="w-4 h-4 text-blue-500 rounded">
                                        <p class="font-medium text-slate-700 text-sm">All Clients</p>
                                    </div>
                                    <span class="text-xs text-blue-600 bg-blue-100 px-2 py-0.5 rounded">Client</span>
                                </label>

                                <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer"
                                    :class="sendToAgent ? 'border-purple-500 bg-purple-50' : 'border-slate-200'">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" name="send_to_agent" value="1" x-model="sendToAgent" class="w-4 h-4 text-purple-500 rounded">
                                        <p class="font-medium text-slate-700 text-sm">All Agents</p>
                                    </div>
                                    <span class="text-xs text-purple-600 bg-purple-100 px-2 py-0.5 rounded">Agent</span>
                                </label>
                            </div>
                        </div>

                        <!-- Frequency -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-3">Frequency</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center justify-center gap-2 p-3 border rounded-lg cursor-pointer"
                                    :class="frequency === 'once' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                                    <input type="radio" name="frequency" value="once" x-model="frequency" class="w-4 h-4 text-blue-500">
                                    <p class="font-medium text-slate-700 text-sm">One-time</p>
                                </label>
                                <label class="flex items-center justify-center gap-2 p-3 border rounded-lg cursor-pointer"
                                    :class="frequency === 'auto' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                                    <input type="radio" name="frequency" value="auto" x-model="frequency" class="w-4 h-4 text-blue-500">
                                    <p class="font-medium text-slate-700 text-sm">Auto-repeat</p>
                                </label>
                            </div>
                        </div>

                        <!-- Auto Settings -->
                        <div x-show="frequency === 'auto'" x-transition class="p-4 bg-slate-50 rounded-lg space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs text-slate-600 mb-1">Repeat Every</label>
                                    <div class="flex gap-2">
                                        <input type="number" name="value" x-model="repeatValue" min="1" class="w-16 border border-slate-300 rounded-md px-2 py-1.5 text-sm">
                                        <select name="unit" x-model="repeatUnit" class="flex-1 border border-slate-300 rounded-md px-2 py-1.5 text-sm">
                                            <option value="hours">Hours</option>
                                            <option value="days">Days</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-600 mb-1">Max Reminders</label>
                                    <input type="number" name="max_reminder" x-model="maxReminders" min="1" max="20" class="w-full border border-slate-300 rounded-md px-2 py-1.5 text-sm">
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="p-5 border-t border-slate-200 flex justify-between items-center bg-slate-50 rounded-b-xl">
                        <button type="button" @click="showBulkModal = false" class="px-4 py-2 text-slate-600 hover:bg-slate-200 rounded-lg text-sm">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm">
                            Send to All ({{ $invoices->total() }})
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

</x-app-layout>