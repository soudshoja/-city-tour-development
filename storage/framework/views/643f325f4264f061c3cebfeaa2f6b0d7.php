<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>

    <style>
        .tab-shape {
            clip-path: polygon(12px 0, calc(100% - 4px) 0, 100% 100%, 0 100%);
        }
    </style>

    <div x-data="{
        activeTab: 'invoice',
        showModal: false,
        showBulkModal: false,
        invoiceId: null,
        invoiceNumber: '',
        invoiceAmount: '',
        clientId: null,
        clientName: '',
        clientPhone: '',
        clientUnpaidCount: 0,
        agentId: null,
        agentName: '',
        agentPhone: '',
        agentUnpaidCount: 0,
        scope: 'invoice',
        sendToClient: true,
        sendToAgent: false,
        frequency: 'once',
        repeatValue: 3,
        repeatUnit: 'days',
        reminderCount: 3,

        openModal(id) {
            this.invoiceId = id;
            const row = document.querySelector(`[data-invoice-id='${id}']`);
            if (row) {
                this.invoiceNumber = row.dataset.invoiceNumber;
                this.invoiceAmount = row.dataset.invoiceAmount;
                this.clientId = row.dataset.clientId;
                this.clientName = row.dataset.clientName.toUpperCase();
                this.clientPhone = row.dataset.clientPhone;
                this.clientUnpaidCount = row.dataset.clientUnpaidCount;
                this.agentId = row.dataset.agentId;
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
            this.showModal = true;
        }
    }">

        <div class="flex justify-between items-center my-4">
            <div class="flex items-center gap-5">
                <h2 class="text-3xl font-bold">Reminder</h2>
                <div data-tooltip="Number of reminders"
                    class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                    <span class="text-xl font-bold text-white"><?php echo e($allReminders->count()); ?></span>
                </div>
            </div>
        </div>

        <div class="panel bg-white rounded-lg shadow p-4">
            <!-- Tabs Navigation -->
            <div class="flex gap-1 mb-0 bg-slate-100 px-2 pt-2 rounded-t-lg">
                <!-- Invoices Tab -->
                <button
                    @click="activeTab = 'invoice'"
                    class="tab-shape relative px-6 py-2.5 text-sm font-medium transition-all duration-200"
                    :class="activeTab === 'invoice' 
                            ? 'bg-white text-blue-600 z-10 rounded-t-lg' 
                            : 'bg-slate-200 text-slate-500 hover:bg-slate-300 hover:text-slate-700 rounded-t-lg'">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        Invoices
                        <span class="bg-blue-100 text-blue-600 text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo e($invoices->total()); ?></span>
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
                        <span class="bg-blue-100 text-blue-600 text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo e($paymentReminders->count()); ?></span>
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
                        <?php if(isset($reminderLogs) && $reminderLogs->count() > 0): ?>
                        <span class="bg-slate-200 text-slate-600 text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo e($reminderLogs->total()); ?></span>
                        <?php endif; ?>
                    </div>
                </button>
            </div>

            <!-- INVOICE: Unpaid Invoices -->
            <div x-show="activeTab === 'invoice'" class="bg-white dark:bg-gray-800 rounded-lg rounded-tl-none shadow-md p-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-slate-800">Unpaid Invoices</h3>
                        <p class="text-sm text-slate-500"><?php echo e($invoices->total()); ?> invoices pending payment</p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full">
                        <?php
                        $currentSort = request('sort', 'due_date');
                        $currentDirection = request('direction', 'asc');

                        function getSortUrl($field, $currentSort, $currentDirection) {
                        $direction = ($currentSort === $field && $currentDirection === 'asc') ? 'desc' : 'asc';
                        return request()->fullUrlWithQuery(['sort' => $field, 'direction' => $direction]);
                        }
                        ?>

                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <!-- Expand Toggle -->
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 w-10"></th>

                                <!-- Invoice - Sortable -->
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                    <a href="<?php echo e(getSortUrl('invoice_number', $currentSort, $currentDirection)); ?>"
                                        class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                        Invoice
                                        <span class="flex flex-col">
                                            <svg class="w-2.5 h-2.5 <?php echo e($currentSort === 'invoice_number' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300'); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="w-2.5 h-2.5 -mt-1 <?php echo e($currentSort === 'invoice_number' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300'); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>

                                <!-- Client - Sortable -->
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                    <a href="<?php echo e(getSortUrl('client_name', $currentSort, $currentDirection)); ?>"
                                        class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                        Client
                                        <span class="flex flex-col">
                                            <svg class="w-2.5 h-2.5 <?php echo e($currentSort === 'client_name' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300'); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="w-2.5 h-2.5 -mt-1 <?php echo e($currentSort === 'client_name' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300'); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>

                                <!-- Agent - Sortable -->
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                    <a href="<?php echo e(getSortUrl('agent_name', $currentSort, $currentDirection)); ?>"
                                        class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                        Agent
                                        <span class="flex flex-col">
                                            <svg class="w-2.5 h-2.5 <?php echo e($currentSort === 'agent_name' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300'); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="w-2.5 h-2.5 -mt-1 <?php echo e($currentSort === 'agent_name' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300'); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>

                                <!-- Amount -->
                                <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Amount</th>

                                <!-- Due Date - Sortable -->
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">
                                    <a href="<?php echo e(getSortUrl('due_date', $currentSort, $currentDirection)); ?>"
                                        class="flex items-center gap-1 hover:text-slate-700 cursor-pointer">
                                        Due Date
                                        <span class="flex flex-col">
                                            <svg class="w-2.5 h-2.5 <?php echo e($currentSort === 'due_date' && $currentDirection === 'asc' ? 'text-blue-500' : 'text-slate-300'); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="w-2.5 h-2.5 -mt-1 <?php echo e($currentSort === 'due_date' && $currentDirection === 'desc' ? 'text-blue-500' : 'text-slate-300'); ?>" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>

                                <!-- Status -->
                                <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>

                                <!-- Reminder -->
                                <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Reminder</th>

                                <!-- Action -->
                                <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Action</th>
                            </tr>
                        </thead>

                        <?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                        $dueDate = \Carbon\Carbon::parse($invoice->due_date);
                        $today = \Carbon\Carbon::today();
                        $daysOverdue = $today->diffInDays($dueDate, false);

                        $clientPhone = '';
                        if ($invoice->client?->phone) {
                        $clientPhone = str_starts_with($invoice->client->phone, '+')
                        ? $invoice->client->phone
                        : ($invoice->client->country_code ?? '') . $invoice->client->phone;
                        }

                        // Get reminders for this invoice
                        $invoiceReminders = $invoice->reminders ?? collect();
                        $hasReminders = $invoiceReminders->count() > 0;
                        $totalReminders = $invoiceReminders->count();
                        $sentCount = $invoiceReminders->where('status', 'sent')->count();
                        $pendingCount = $invoiceReminders->where('status', 'pending')->count();
                        $failedCount = $invoiceReminders->where('status', 'failed')->count();
                        ?>

                        <tbody x-data="{ expanded: false }" class="border-b border-slate-100">
                            <!-- Parent Row -->
                            <tr class="hover:bg-slate-50 transition-colors"
                                :class="expanded ? 'bg-blue-50 border-l-4 border-l-blue-500' : 'border-l-4 border-l-transparent'"
                                <?php if($hasReminders): ?> @click="expanded = !expanded" style="cursor: pointer;" <?php endif; ?>
                                data-invoice-id="<?php echo e($invoice->id); ?>"
                                data-invoice-number="<?php echo e($invoice->invoice_number); ?>"
                                data-invoice-amount="<?php echo e($invoice->currency); ?> <?php echo e(number_format($invoice->amount, 2)); ?>"
                                data-client-id="<?php echo e($invoice->client->id); ?>"
                                data-client-name="<?php echo e(strtoupper($invoice->client->name ?? 'N/A')); ?>"
                                data-client-phone="<?php echo e($clientPhone); ?>"
                                data-client-unpaid-count="<?php echo e($invoice->client ? $invoice->client->invoices()->where('status', 'unpaid')->count() : 0); ?>"
                                data-agent-id="<?php echo e($invoice->agent->id); ?>"
                                data-agent-name="<?php echo e(strtoupper($invoice->agent->name ?? 'N/A')); ?>"
                                data-agent-phone="<?php echo e($invoice->agent->phone_number ?? ''); ?>"
                                data-agent-unpaid-count="<?php echo e($invoice->agent ? $invoice->agent->invoices()->where('status', 'unpaid')->count() : 0); ?>">

                                <!-- Expand Toggle -->
                                <td class="px-4 py-3">
                                    
                                </td>

                                <!-- Invoice -->
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-800"><?php echo e($invoice->invoice_number); ?></span>
                                </td>

                                <!-- Client -->
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800"><?php echo e(strtoupper($invoice->client->name ?? 'N/A')); ?></p>
                                        <p class="text-xs text-slate-400"><?php echo e($clientPhone); ?></p>
                                    </div>
                                </td>

                                <!-- Agent -->
                                <td class="px-4 py-3">
                                    <span class="text-sm text-slate-600"><?php echo e(strtoupper($invoice->agent->name ?? 'N/A')); ?></span>
                                </td>

                                <!-- Amount -->
                                <td class="px-4 py-3 text-right">
                                    <span class="font-semibold text-slate-800"><?php echo e($invoice->currency); ?> <?php echo e(number_format($invoice->amount, 2)); ?></span>
                                </td>

                                <!-- Due Date -->
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800"><?php echo e($dueDate->format('M d, Y')); ?></p>
                                        <?php if($daysOverdue < 0): ?>
                                            <p class="text-xs text-red-500 font-medium"><?php echo e(abs($daysOverdue)); ?> days overdue</p>
                                            <?php elseif($daysOverdue <= 7): ?>
                                                <p class="text-xs text-amber-500 font-medium">Due in <?php echo e($daysOverdue); ?> days</p>
                                                <?php else: ?>
                                                <p class="text-xs text-slate-400">Due in <?php echo e($daysOverdue); ?> days</p>
                                                <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3 text-center">
                                    <?php if($daysOverdue < 0): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Overdue</span>
                                        <?php elseif($daysOverdue <= 7): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Due Soon</span>
                                            <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Pending</span>
                                            <?php endif; ?>
                                </td>

                                <!-- Reminder Status -->
                                <td class="px-4 py-3 text-center">
                                    <?php if($hasReminders): ?>
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                            <?php echo e($sentCount); ?>/<?php echo e($totalReminders); ?>

                                        </span>
                                        <?php if($failedCount > 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                            <?php echo e($failedCount); ?> Failed
                                        </span>
                                        <?php elseif($pendingCount > 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                            Active
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                            Done
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-xs text-slate-400">No reminder</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action -->
                                <td class="px-4 py-3 text-right" @click.stop>
                                    <button
                                        type="button"
                                        @click="openModal(<?php echo e($invoice->id); ?>)"
                                        class="p-2 text-slate-400 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="Send Reminder">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>

                            <!-- Expanded Details - Reminder Schedule -->
                            <?php if($hasReminders): ?>
                            <tr x-show="expanded">
                                <td colspan="9" class="p-0">
                                    <div class="p-4 bg-blue-50 border-l-4 border-l-blue-500">
                                        <div class="flex items-center gap-2 mb-3">  
                                            <p class="text-xs text-blue-700 uppercase tracking-wide font-semibold">
                                                Reminder Schedule for <?php echo e($invoice->invoice_number); ?>

                                            </p>
                                        </div>
                                        <table class="w-full bg-white rounded-lg border border-blue-200 overflow-hidden">
                                            <thead>
                                                <tr class="bg-blue-100 border-b border-blue-200">
                                                    <th class="text-left text-xs font-medium text-blue-700 px-4 py-2">#</th>
                                                    <th class="text-left text-xs font-medium text-blue-700 px-4 py-2">Group ID</th>
                                                    <th class="text-left text-xs font-medium text-blue-700 px-4 py-2">Scheduled At</th>
                                                    <th class="text-left text-xs font-medium text-blue-700 px-4 py-2">Sent At</th>
                                                    <th class="text-center text-xs font-medium text-blue-700 px-4 py-2">Status</th>
                                                    <th class="text-left text-xs font-medium text-blue-700 px-4 py-2">Send To</th>
                                                    <th class="text-left text-xs font-medium text-blue-700 px-4 py-2">Message</th>
                                                    <th class="text-left text-xs font-medium text-blue-700 px-4 py-2">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-blue-100">
                                                <?php $__currentLoopData = $invoiceReminders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $reminder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <tr class="hover:bg-blue-50">
                                                    <td class="px-4 py-2">
                                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium 
                                                            <?php if($reminder->status === 'sent'): ?> bg-green-100 text-green-700
                                                            <?php elseif($reminder->status === 'pending'): ?> bg-amber-100 text-amber-700
                                                            <?php elseif($reminder->status === 'failed'): ?> bg-red-100 text-red-700
                                                            <?php else: ?> bg-slate-100 text-slate-600
                                                            <?php endif; ?>">
                                                            <?php echo e($index + 1); ?>

                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <span class="text-xs text-slate-500 font-mono"><?php echo e($reminder->group_id ?? '-'); ?></span>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <div>
                                                            <p class="text-sm text-slate-800"><?php echo e($reminder->scheduled_at ? \Carbon\Carbon::parse($reminder->scheduled_at)->format('M d, Y') : 'N/A'); ?></p>
                                                            <p class="text-xs text-slate-400"><?php echo e($reminder->scheduled_at ? \Carbon\Carbon::parse($reminder->scheduled_at)->format('h:i A') : ''); ?></p>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if($reminder->sent_at): ?>
                                                        <div>
                                                            <p class="text-sm text-slate-800"><?php echo e(\Carbon\Carbon::parse($reminder->sent_at)->format('M d, Y')); ?></p>
                                                            <p class="text-xs text-slate-400"><?php echo e(\Carbon\Carbon::parse($reminder->sent_at)->format('h:i A')); ?></p>
                                                        </div>
                                                        <?php else: ?>
                                                        <span class="text-sm text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2 text-center">
                                                        <?php if($reminder->status === 'sent'): ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Sent</span>
                                                        <?php elseif($reminder->status === 'pending'): ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Pending</span>
                                                        <?php elseif($reminder->status === 'failed'): ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Failed</span>
                                                        <?php else: ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600"><?php echo e(ucfirst($reminder->status)); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <div class="flex gap-1">
                                                            <?php if($reminder->send_to_client): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Client</span>
                                                            <?php endif; ?>
                                                            <?php if($reminder->send_to_agent): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">Agent</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if($reminder->message): ?>
                                                        <p class="text-xs text-slate-600 max-w-xs truncate" title="<?php echo e($reminder->message); ?>"><?php echo e(Str::limit($reminder->message, 40)); ?></p>
                                                        <?php else: ?>
                                                        <span class="text-xs text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if($reminder->status === 'pending' && $reminder->scheduled_at): ?>
                                                        <span class="text-xs text-amber-600"><?php echo e(\Carbon\Carbon::parse($reminder->scheduled_at)->diffForHumans()); ?></span>
                                                        <?php elseif($reminder->status === 'failed' && $reminder->error_message): ?>
                                                        <span class="text-xs text-red-500" title="<?php echo e($reminder->error_message); ?>"><?php echo e(Str::limit($reminder->error_message, 30)); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-xs text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tbody>
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-slate-500">No unpaid invoices found</td>
                            </tr>
                        </tbody>
                        <?php endif; ?>
                    </table>
                </div>

                <?php if($invoices->hasPages()): ?>
                <div class="mt-4">
                    <?php echo e($invoices->links()); ?>

                </div>
                <?php endif; ?>
            </div>

            <!-- PAYMENT: Set up Payment Links -->
            <div x-show="activeTab === 'payment'" x-cloak class="bg-white dark:bg-gray-800 rounded-lg rounded-tl-none shadow-md p-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-slate-800">Payment Reminders</h3>
                        <p class="text-sm text-slate-500"><?php echo e($paymentReminders->count()); ?> reminder groups</p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 w-10"></th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Voucher</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Client</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Agent</th>
                                <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Amount</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Next Reminder</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Created At</th>
                                <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Progress</th>
                                <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>
                                <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <?php $__empty_1 = true; $__currentLoopData = $paymentReminders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupId => $reminders): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                        $firstReminder = $reminders->first();
                        $payment = $firstReminder->payment;
                        $totalReminders = $reminders->count();
                        $sentCount = $reminders->where('status', 'sent')->count();
                        $pendingCount = $reminders->where('status', 'pending')->count();
                        $failedCount = $reminders->where('status', 'failed')->count();
                        $nextReminder = $reminders->where('status', 'pending')->sortBy('scheduled_at')->first();
                        ?>

                        <tbody x-data="{ expanded: false }" class="border-b border-slate-100" :class="expanded ? 'bg-slate-50' : ''">
                            <!-- Parent Row -->
                            <tr @click="expanded = !expanded" class="transition-colors cursor-pointer" :class="expanded ? 'bg-blue-50 border-l-4 border-blue-500' : 'hover:bg-slate-50'">
                                <td class="px-4 py-3">
                                </td>

                                <!-- Voucher -->
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-800"><?php echo e($payment->voucher_number ?? 'N/A'); ?></span>
                                </td>

                                <!-- Client -->
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800"><?php echo e(strtoupper($firstReminder->client->full_name ?? 'N/A')); ?></p>
                                        <p class="text-xs text-slate-400"><?php echo e($firstReminder->client->country_code ?? ''); ?><?php echo e($firstReminder->client->phone ?? ''); ?></p>
                                    </div>
                                </td>

                                <!-- Agent -->
                                <td class="px-4 py-3">
                                    <span class="text-sm text-slate-600"><?php echo e(strtoupper($firstReminder->agent->name ?? 'N/A')); ?></span>
                                </td>

                                <!-- Amount -->
                                <td class="px-4 py-3 text-right">
                                    <span class="font-semibold text-slate-800"><?php echo e($payment->currency ?? 'KWD'); ?> <?php echo e(number_format($payment->amount ?? 0, 2)); ?></span>
                                </td>

                                <!-- Next Reminder -->
                                <td class="px-4 py-3">
                                    <?php if($nextReminder): ?>
                                    <div>
                                        <p class="text-sm text-slate-800"><?php echo e(\Carbon\Carbon::parse($nextReminder->scheduled_at)->format('M d, Y')); ?></p>
                                        <p class="text-xs text-amber-600"><?php echo e(\Carbon\Carbon::parse($nextReminder->scheduled_at)->diffForHumans()); ?></p>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-sm text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Created At -->
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800"><?php echo e($firstReminder->created_at ? $firstReminder->created_at->format('M d, Y') : 'N/A'); ?></p>
                                        <p class="text-xs text-slate-400"><?php echo e($firstReminder->created_at ? $firstReminder->created_at->format('h:i A') : ''); ?></p>
                                    </div>
                                </td>

                                <!-- Progress -->
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                        <?php echo e($sentCount); ?>/<?php echo e($totalReminders); ?>

                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3 text-center">
                                    <?php if($failedCount > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Failed</span>
                                    <?php elseif($pendingCount > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Active</span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Completed</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action -->
                                <td class="px-4 py-3 text-right" @click.stop>
                                    <button type="button" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Cancel Reminders">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>

                            <!-- Expanded Details - Nested Table -->
                            <tr x-show="expanded">
                                <td colspan="10" class="p-0 bg-slate-50">
                                    <div class="p-4">
                                        <p class="text-xs text-slate-500 uppercase tracking-wide mb-3 font-semibold">Reminder Schedule</p>
                                        <table class="w-full bg-white rounded-lg border border-slate-200 overflow-hidden">
                                            <thead>
                                                <tr class="bg-slate-100 border-b border-slate-200">
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2"></th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Scheduled At</th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Sent At</th>
                                                    <th class="text-center text-xs font-medium text-slate-500 px-4 py-2">Status</th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Send To</th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Message</th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <?php $__currentLoopData = $reminders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $reminder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-4 py-2">
                                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium 
                                                                    <?php if($reminder->status === 'sent'): ?> bg-green-100 text-green-700
                                                                    <?php elseif($reminder->status === 'pending'): ?> bg-amber-100 text-amber-700
                                                                    <?php elseif($reminder->status === 'failed'): ?> bg-red-100 text-red-700
                                                                    <?php else: ?> bg-slate-100 text-slate-600
                                                                    <?php endif; ?>">
                                                            <?php echo e($index + 1); ?>

                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <div>
                                                            <p class="text-sm text-slate-800"><?php echo e($reminder->scheduled_at ? \Carbon\Carbon::parse($reminder->scheduled_at)->format('M d, Y') : 'N/A'); ?></p>
                                                            <p class="text-xs text-slate-400"><?php echo e($reminder->scheduled_at ? \Carbon\Carbon::parse($reminder->scheduled_at)->format('h:i A') : ''); ?></p>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if($reminder->sent_at): ?>
                                                        <div>
                                                            <p class="text-sm text-slate-800"><?php echo e(\Carbon\Carbon::parse($reminder->sent_at)->format('M d, Y')); ?></p>
                                                            <p class="text-xs text-slate-400"><?php echo e(\Carbon\Carbon::parse($reminder->sent_at)->format('h:i A')); ?></p>
                                                        </div>
                                                        <?php else: ?>
                                                        <span class="text-sm text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2 text-center">
                                                        <?php if($reminder->status === 'sent'): ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">

                                                            Sent
                                                        </span>
                                                        <?php elseif($reminder->status === 'pending'): ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">

                                                            Pending
                                                        </span>
                                                        <?php elseif($reminder->status === 'failed'): ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">

                                                            Failed
                                                        </span>
                                                        <?php else: ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600"><?php echo e(ucfirst($reminder->status)); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <div class="flex gap-1">
                                                            <?php if($reminder->send_to_client): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Client</span>
                                                            <?php endif; ?>
                                                            <?php if($reminder->send_to_agent): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">Agent</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if($reminder->message): ?>
                                                        <p class="text-xs text-slate-600 max-w-xs truncate" title="<?php echo e($reminder->message); ?>"><?php echo e(Str::limit($reminder->message, 40)); ?></p>
                                                        <?php else: ?>
                                                        <span class="text-xs text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if($reminder->status === 'pending' && $reminder->scheduled_at): ?>
                                                        <span class="text-xs text-amber-600"><?php echo e(\Carbon\Carbon::parse($reminder->scheduled_at)->diffForHumans()); ?></span>
                                                        <?php elseif($reminder->status === 'failed' && $reminder->error_message): ?>
                                                        <span class="text-xs text-red-500" title="<?php echo e($reminder->error_message); ?>"><?php echo e(Str::limit($reminder->error_message, 30)); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-xs text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tbody>
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-slate-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                        </svg>
                                        <p>No payment reminders found</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- HISTORY: Reminder Logs -->
            <!-- HISTORY: All Reminders (Grouped) -->
            <div x-show="activeTab === 'history'" x-cloak class="bg-white dark:bg-gray-800 rounded-lg rounded-tl-none shadow-md p-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-slate-800">Reminder History</h3>
                        <p class="text-sm text-slate-500"><?php echo e($allReminders->count()); ?> reminder groups</p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3 w-10"></th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Type</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Reference</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Client</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Agent</th>
                                <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Amount</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Send To</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Created At</th>
                                <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Progress</th>
                                <th class="text-center text-xs font-semibold text-slate-500 uppercase tracking-wider px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <?php $__empty_1 = true; $__currentLoopData = $allReminders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupId => $reminders): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                        $firstReminder = $reminders->first();
                        $totalReminders = $reminders->count();
                        $sentCount = $reminders->where('status', 'sent')->count();
                        $pendingCount = $reminders->where('status', 'pending')->count();
                        $failedCount = $reminders->where('status', 'failed')->count();

                        // Get reference info based on type
                        $referenceNumber = null;
                        $currency = null;
                        $amount = null;

                        if ($firstReminder->target_type === 'invoice' && $firstReminder->invoice) {
                        $referenceNumber = $firstReminder->invoice->invoice_number;
                        $currency = $firstReminder->invoice->currency;
                        $amount = $firstReminder->invoice->amount;
                        } elseif ($firstReminder->target_type === 'payment' && $firstReminder->payment) {
                        $referenceNumber = $firstReminder->payment->voucher_number;
                        $currency = $firstReminder->payment->currency;
                        $amount = $firstReminder->payment->amount;
                        }
                        ?>

                        <tbody x-data="{ expanded: false }" class="border-b border-slate-200">
                            <!-- Parent Row -->
                            <tr @click="expanded = !expanded"
                                class="transition-all duration-200 cursor-pointer"
                                :class="expanded ? 'bg-slate-100 border-l-4 border-l-slate-500' : 'hover:bg-slate-50 border-l-4 border-l-transparent'">

                                <!-- Expand Toggle -->
                                <td class="px-4 py-3">
                                    <!-- <?php if($totalReminders > 1): ?>
                                <div class="p-1 rounded transition-colors inline-flex" :class="expanded ? 'bg-slate-200' : 'hover:bg-slate-200'">
                                    <svg class="w-4 h-4 transition-transform duration-200" :class="expanded ? 'rotate-90 text-slate-700' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            <?php endif; ?> -->
                                </td>

                                <!-- Type -->
                                <td class="px-4 py-3">
                                    <?php if($firstReminder->target_type === 'invoice'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Invoice</span>
                                    <?php elseif($firstReminder->target_type === 'payment'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-700">Payment</span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600"><?php echo e(ucfirst($firstReminder->target_type)); ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Reference -->
                                <td class="px-4 py-3">
                                    <div>
                                        <span class="font-medium" :class="expanded ? 'text-slate-800' : 'text-slate-800'"><?php echo e($referenceNumber ?? 'N/A'); ?></span>
                                        <p class="text-xs text-slate-400"><?php echo e($groupId); ?></p>
                                    </div>
                                </td>

                                <!-- Client -->
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800"><?php echo e(strtoupper($firstReminder->client->full_name ?? $firstReminder->client->name ?? 'N/A')); ?></p>
                                        <p class="text-xs text-slate-400"><?php echo e($firstReminder->client->country_code ?? ''); ?><?php echo e($firstReminder->client->phone ?? ''); ?></p>
                                    </div>
                                </td>

                                <!-- Agent -->
                                <td class="px-4 py-3">
                                    <span class="text-sm text-slate-600"><?php echo e(strtoupper($firstReminder->agent->name ?? 'N/A')); ?></span>
                                </td>

                                <!-- Amount -->
                                <td class="px-4 py-3 text-right">
                                    <?php if($currency && $amount): ?>
                                    <span class="font-semibold text-slate-800"><?php echo e($currency); ?> <?php echo e(number_format($amount, 2)); ?></span>
                                    <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Send To -->
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <?php if($firstReminder->send_to_client): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Client</span>
                                        <?php endif; ?>
                                        <?php if($firstReminder->send_to_agent): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">Agent</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Created At -->
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-sm text-slate-800"><?php echo e($firstReminder->created_at ? $firstReminder->created_at->format('M d, Y') : 'N/A'); ?></p>
                                        <p class="text-xs text-slate-400"><?php echo e($firstReminder->created_at ? $firstReminder->created_at->format('h:i A') : ''); ?></p>
                                    </div>
                                </td>

                                <!-- Progress -->
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                        <?php echo e($sentCount); ?>/<?php echo e($totalReminders); ?>

                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3 text-center">
                                    <?php if($failedCount > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Failed</span>
                                    <?php elseif($pendingCount > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Active</span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Expanded Details - Nested Table -->
                            <?php if($totalReminders > 1): ?>
                            <tr x-show="expanded">
                                <td colspan="10" class="p-0">
                                    <div class="p-4 bg-slate-50 border-l-4 border-l-slate-500">
                                        <div class="flex items-center gap-2 mb-3">
                                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <p class="text-xs text-slate-600 uppercase tracking-wide font-semibold">
                                                Reminder Schedule for <?php echo e($referenceNumber ?? $groupId); ?>

                                            </p>
                                        </div>
                                        <table class="w-full bg-white rounded-lg border border-slate-200 overflow-hidden">
                                            <thead>
                                                <tr class="bg-slate-100 border-b border-slate-200">
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2"></th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Scheduled At</th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Sent At</th>
                                                    <th class="text-center text-xs font-medium text-slate-500 px-4 py-2">Status</th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Send To</th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Message</th>
                                                    <th class="text-left text-xs font-medium text-slate-500 px-4 py-2">Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <?php $__currentLoopData = $reminders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $reminder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="px-4 py-2">
                                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-medium 
                                                            <?php if($reminder->status === 'sent'): ?> bg-green-100 text-green-700
                                                            <?php elseif($reminder->status === 'pending'): ?> bg-amber-100 text-amber-700
                                                            <?php elseif($reminder->status === 'failed'): ?> bg-red-100 text-red-700
                                                            <?php else: ?> bg-slate-100 text-slate-600
                                                            <?php endif; ?>">
                                                            <?php echo e($index + 1); ?>

                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <div>
                                                            <p class="text-sm text-slate-800"><?php echo e($reminder->scheduled_at ? \Carbon\Carbon::parse($reminder->scheduled_at)->format('M d, Y') : 'N/A'); ?></p>
                                                            <p class="text-xs text-slate-400"><?php echo e($reminder->scheduled_at ? \Carbon\Carbon::parse($reminder->scheduled_at)->format('h:i A') : ''); ?></p>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if($reminder->sent_at): ?>
                                                        <div>
                                                            <p class="text-sm text-slate-800"><?php echo e(\Carbon\Carbon::parse($reminder->sent_at)->format('M d, Y')); ?></p>
                                                            <p class="text-xs text-slate-400"><?php echo e(\Carbon\Carbon::parse($reminder->sent_at)->format('h:i A')); ?></p>
                                                        </div>
                                                        <?php else: ?>
                                                        <span class="text-sm text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2 text-center">
                                                        <?php if($reminder->status === 'sent'): ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                            Sent
                                                        </span>
                                                        <?php elseif($reminder->status === 'pending'): ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                                            Pending
                                                        </span>
                                                        <?php elseif($reminder->status === 'failed'): ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                            Failed
                                                        </span>
                                                        <?php else: ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600"><?php echo e(ucfirst($reminder->status)); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <div class="flex gap-1">
                                                            <?php if($reminder->send_to_client): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Client</span>
                                                            <?php endif; ?>
                                                            <?php if($reminder->send_to_agent): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">Agent</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if($reminder->message): ?>
                                                        <p class="text-xs text-slate-600 max-w-xs truncate" title="<?php echo e($reminder->message); ?>"><?php echo e(Str::limit($reminder->message, 40)); ?></p>
                                                        <?php else: ?>
                                                        <span class="text-xs text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <?php if($reminder->status === 'pending' && $reminder->scheduled_at): ?>
                                                        <span class="text-xs text-amber-600"><?php echo e(\Carbon\Carbon::parse($reminder->scheduled_at)->diffForHumans()); ?></span>
                                                        <?php elseif($reminder->status === 'failed' && $reminder->error_message): ?>
                                                        <span class="text-xs text-red-500" title="<?php echo e($reminder->error_message); ?>"><?php echo e(Str::limit($reminder->error_message, 30)); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-xs text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tbody>
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-slate-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <p>No reminder history yet</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: Single Reminder -->
        <div
            x-show="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @click.self="showModal = false"
            style="display: none;">
            <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col" @click.stop>

                <form action="<?php echo e(route('reminder.store')); ?>" method="POST" class="flex flex-col max-h-[90vh]">
                    <?php echo csrf_field(); ?>

                    <input type="hidden" name="invoice_id" x-bind:value="invoiceId">
                    <input type="hidden" name="client_id" x-bind:value="clientId">
                    <input type="hidden" name="agent_id" x-bind:value="agentId">


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
                                <!-- 
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
                                </label> -->
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
                            <!-- Preset Buttons -->
                            <div>
                                <label class="block text-xs text-slate-600 mb-2">Quick Presets</label>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="repeatValue = 1; repeatUnit = 'days'; reminderCount = 3"
                                        class="px-2.5 py-1 text-xs border rounded-md transition-colors"
                                        :class="repeatValue == 1 && repeatUnit === 'days' && reminderCount == 3 ? 'border-blue-500 bg-white text-blue-600' : 'border-slate-200 hover:border-slate-300'">
                                        Daily × 3
                                    </button>
                                    <button type="button" @click="repeatValue = 3; repeatUnit = 'days'; reminderCount = 3"
                                        class="px-2.5 py-1 text-xs border rounded-md transition-colors"
                                        :class="repeatValue == 3 && repeatUnit === 'days' && reminderCount == 3 ? 'border-blue-500 bg-white text-blue-600' : 'border-slate-200 hover:border-slate-300'">
                                        Every 3 days × 3
                                    </button>
                                    <button type="button" @click="repeatValue = 7; repeatUnit = 'days'; reminderCount = 4"
                                        class="px-2.5 py-1 text-xs border rounded-md transition-colors"
                                        :class="repeatValue == 7 && repeatUnit === 'days' && reminderCount == 4 ? 'border-blue-500 bg-white text-blue-600' : 'border-slate-200 hover:border-slate-300'">
                                        Weekly × 4
                                    </button>
                                </div>
                            </div>

                            <!-- Custom Settings -->
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-slate-600 mb-1">Interval</label>
                                    <input type="number" name="value" x-model="repeatValue" min="1" max="30"
                                        class="w-full border border-slate-300 rounded-md px-2 py-1.5 text-sm text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-600 mb-1">Unit</label>
                                    <select name="unit" x-model="repeatUnit" class="w-full border border-slate-300 rounded-md px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="hours">Hours</option>
                                        <option value="days">Days</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-600 mb-1">Total Reminders</label>
                                    <input type="number" name="number_of_reminder" x-model="reminderCount" min="1" max="10"
                                        class="w-full border border-slate-300 rounded-md px-2 py-1.5 text-sm text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <!-- Preview -->
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div class="text-xs text-blue-700">
                                        <p class="font-medium mb-1">Schedule Preview</p>
                                        <p>
                                            <span x-text="reminderCount"></span> reminders will be sent every
                                            <span x-text="repeatValue"></span>
                                            <span x-text="repeatUnit"></span>
                                        </p>
                                        <p class="mt-1 text-blue-600">
                                            • 1st: Immediately<br>
                                            <template x-for="i in Math.min(reminderCount - 1, 3)" :key="i">
                                                <span>
                                                    • <span x-text="i + 1"></span><span x-text="i + 1 == 2 ? 'nd' : (i + 1 == 3 ? 'rd' : 'th')"></span>:
                                                    In <span x-text="repeatValue * i"></span> <span x-text="repeatUnit"></span><br>
                                                </span>
                                            </template>
                                            <span x-show="reminderCount > 4" class="text-blue-500">... and <span x-text="reminderCount - 4"></span> more</span>
                                        </p>
                                    </div>
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


        <!-- MODAL: Bulk Reminder -->
        <div
            x-show="showBulkModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
            @click.self="showBulkModal = false"
            style="display: none;">
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full" @click.stop>

                <form action="<?php echo e(route('reminder.bulk')); ?>" method="POST">
                    <?php echo csrf_field(); ?>

                    <!-- Header -->
                    <div class="p-5 border-b border-slate-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-800">Send All Reminders</h3>
                                <p class="text-sm text-slate-500"><?php echo e($invoices->total()); ?> unpaid invoices</p>
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
                                <p class="text-sm font-medium text-amber-800">This will send reminders for all <?php echo e($invoices->total()); ?> unpaid invoices</p>
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
                            </div>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="p-5 border-t border-slate-200 flex justify-between items-center bg-slate-50 rounded-b-xl">
                        <button type="button" @click="showBulkModal = false" class="px-4 py-2 text-slate-600 hover:bg-slate-200 rounded-lg text-sm">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm">
                            Send to All (<?php echo e($invoices->total()); ?>)
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reminder/index.blade.php ENDPATH**/ ?>