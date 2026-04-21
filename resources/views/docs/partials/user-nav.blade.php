    
    @php
        $user = auth()->user();
        $roleName = $user->roles->first()?->name ?? 'agent';
        $perms = [
            'viewRoles' => $user->can('viewAny', \App\Models\Role::class),
            'viewUsers' => $user->can('viewAny', \App\Models\User::class),
            'viewCompanies' => $user->can('viewAny', \App\Models\Company::class),
            'viewBranches' => $user->can('viewAny', \App\Models\Branch::class),
            'viewAgents' => $user->can('viewAny', \App\Models\Agent::class),
            'viewClients' => $user->can('viewAny', \App\Models\Client::class),
            'viewSuppliers' => $user->can('viewAny', \App\Models\Supplier::class),
            'viewSettings' => $user->can('viewAny', \App\Models\Setting::class),
            'manageSystemSettings' => $user->can('manage-system-settings'),
            'viewTasks' => $user->can('viewAny', \App\Models\Task::class),
            'createTasks' => $user->can('create', \App\Models\Task::class),
            'viewInvoices' => $user->can('viewAny', \App\Models\Invoice::class),
            'createInvoices' => $user->can('create', \App\Models\Invoice::class),
            'viewPayments' => $user->can('viewAny', \App\Models\Payment::class),
            'viewRefunds' => $user->can('viewAny', \App\Models\Refund::class),
            'viewAutoBilling' => $user->can('viewAny', \App\Models\AutoBilling::class),
            'viewCoa' => $user->can('viewAny', \App\Models\CoaCategory::class),
            'viewReports' => $user->can('viewAny', \App\Models\Report::class),
            'viewCurrencyExchange' => $user->can('viewAny', \App\Models\CurrencyExchange::class),
        ];
    @endphp

    const role = @json($roleName);
    const p = @json($perms);

    const groups = [];

    groups.push({
        label: @json(__("doc.nav.gettingStarted")),
        links: [
            { id: 'getting-started', title: @json(__("doc.nav.link.gettingStarted")), icon: 'fas fa-rocket' },
            ...(p.viewRoles ? [{ id: 'role-overview', title: @json(__("doc.nav.link.rolesOverview")), icon: 'fas fa-shield-halved' }] : []),
        ]
    });

    const setupLinks = [];
    if (p.viewUsers) {
        setupLinks.push({ id: 'user-management', title: @json(__("doc.nav.link.userManagement")), icon: 'fas fa-users' });
    } else if (p.viewClients) {
        setupLinks.push({ id: 'my-clients', title: @json(__("doc.nav.link.yourClients")), icon: 'fas fa-user' });
    }
    if (p.viewSuppliers) {
        setupLinks.push({ id: 'suppliers', title: @json(__("doc.nav.link.suppliers")), icon: 'fas fa-handshake' });
    }
    if (p.viewSettings) {
        setupLinks.push({ id: 'settings', title: @json(__("doc.nav.link.settings")), icon: 'fas fa-cog' });
    }
    if (p.manageSystemSettings) {
        setupLinks.push({ id: 'system-settings', title: @json(__("doc.nav.link.systemSettings")), icon: 'fas fa-server' });
    }
    if (setupLinks.length > 0) {
        groups.push({ label: @json(__("doc.nav.setup")), links: setupLinks });
    }

    const opsLinks = [];
    if (p.viewTasks) {
        opsLinks.push({ id: 'tasks', title: @json(__("doc.nav.link.tasks")), icon: 'fas fa-tasks' });
    }
    if (p.viewInvoices) {
        opsLinks.push(
            { id: 'invoices', title: @json(__("doc.nav.link.invoices")), icon: 'fas fa-file-invoice-dollar' },
            { id: 'invoices-link', title: @json(__("doc.nav.link.invoicesLink")), icon: 'fas fa-link' },
        );
    }
    if (p.viewPayments) {
        opsLinks.push({ id: 'payment-links', title: @json(__("doc.nav.link.paymentLinks")), icon: 'fas fa-money-check-alt' });
    }
    if (p.viewRefunds) {
        opsLinks.push({ id: 'refunds', title: @json(__("doc.nav.link.refunds")), icon: 'fas fa-undo' });
    }
    if (p.viewInvoices) {
        opsLinks.push({ id: 'reminders', title: @json(__("doc.nav.link.reminders")), icon: 'fas fa-clock' });
    }
    if (opsLinks.length > 0) {
        groups.push({ label: @json(__("doc.nav.operations")), links: opsLinks });
    }

    const finLinks = [];
    if (p.viewPayments) {
        finLinks.push({ id: 'outstanding', title: @json(__("doc.nav.link.outstanding")), icon: 'fas fa-exclamation-circle' });
    }
    if (p.viewAutoBilling) {
        finLinks.push({ id: 'auto-billing', title: @json(__("doc.nav.link.autoBilling")), icon: 'fas fa-sync-alt' });
    }
    if (p.viewCoa) {
        finLinks.push({ id: 'accounting', title: @json(__("doc.nav.link.accounting")), icon: 'fas fa-book' });
    }
    if (p.viewCurrencyExchange) {
        finLinks.push({ id: 'currency-exchange', title: @json(__("doc.nav.link.currencyExchange")), icon: 'fas fa-exchange-alt' });
    }
    if (p.viewReports) {
        finLinks.push({ id: 'reports', title: @json(__("doc.nav.link.reports")), icon: 'fas fa-chart-bar' });
    }
    if (finLinks.length > 0) {
        groups.push({ label: @json(__("doc.nav.finance")), links: finLinks });
    }

    groups.push({
        label: @json(__("doc.nav.help")),
        links: [
            { id: 'faq', title: @json(__("doc.nav.link.faq")), icon: 'fas fa-question-circle' },
        ]
    });

    const sections = [
        { id: 'getting-started', title: @json(__("doc.nav.link.gettingStarted")), keywords: 'login dashboard start' },
        { id: 'role-overview', title: @json(__("doc.nav.link.rolesOverview")), keywords: 'role permission admin company agent' },
        { id: 'user-management', title: @json(__("doc.nav.link.userManagement")), keywords: 'user company branch agent client accountant create' },
        { id: 'my-clients', title: @json(__("doc.nav.link.yourClients")), keywords: 'client add register' },
        { id: 'suppliers', title: @json(__("doc.nav.link.suppliers")), keywords: 'supplier activate service' },
        { id: 'settings', title: @json(__("doc.nav.link.settings")), keywords: 'settings payment gateway method terms notification' },
        { id: 'system-settings', title: @json(__("doc.nav.link.systemSettings")), keywords: 'system email whatsapp hotel country' },
        { id: 'tasks', title: @json(__("doc.nav.link.tasks")), keywords: 'task booking flight hotel visa insurance create' },
        { id: 'invoices', title: @json(__("doc.nav.link.invoices")), keywords: 'invoice create send pdf lock status partial split' },
        { id: 'invoices-link', title: @json(__("doc.nav.link.invoicesLink")), keywords: 'invoice link group share multiple' },
        { id: 'payment-links', title: @json(__("doc.nav.link.paymentLinks")), keywords: 'payment link share online full' },
        { id: 'refunds', title: @json(__("doc.nav.link.refunds")), keywords: 'refund cancel money back' },
        { id: 'reminders', title: @json(__("doc.nav.link.reminders")), keywords: 'reminder overdue due date follow up' },
        { id: 'outstanding', title: @json(__("doc.nav.link.outstanding")), keywords: 'outstanding unpaid balance pending' },
        { id: 'auto-billing', title: @json(__("doc.nav.link.autoBilling")), keywords: 'auto billing automatic invoice' },
        { id: 'accounting', title: @json(__("doc.nav.link.accounting")), keywords: 'coa chart accounts voucher receipt payment journal receivable payable lock management' },
        { id: 'currency-exchange', title: @json(__("doc.nav.link.currencyExchange")), keywords: 'currency exchange rate foreign' },
        { id: 'reports', title: @json(__("doc.nav.link.reports")), keywords: 'report sales profit agent financial commission' },
        { id: 'faq', title: @json(__("doc.nav.link.faq")), keywords: 'faq question help' },
    ];
