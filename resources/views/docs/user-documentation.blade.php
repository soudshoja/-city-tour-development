<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Documentation - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eef1fb', 100: '#d4daf4', 200: '#a9b5e9', 300: '#7e90de',
                            400: '#536bd3', 500: '#2945a2', 600: '#213882', 700: '#192a61',
                            800: '#111d41', 900: '#080f20',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        html { scroll-behavior: smooth; }
        .sidebar-link { transition: all 0.2s; }
        .sidebar-link:hover { transform: translateX(4px); }
        #progress-bar { transition: width 0.1s linear; }
        .step-number {
            width: 28px; height: 28px; border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center; font-weight: 700; font-size: 13px;
            background-color: #2945a2; color: #fff; flex-shrink: 0;
        }
        .dark .step-number { background-color: #536bd3; }
        .info-box {
            background-color: #eef1fb; border-left: 4px solid #2945a2;
            padding: 1rem 1.25rem; border-radius: 0 0.5rem 0.5rem 0; margin: 1rem 0;
        }
        .dark .info-box { background-color: #111d41; border-left-color: #536bd3; }
        .warn-box {
            background-color: #fef3c7; border-left: 4px solid #f59e0b;
            padding: 1rem 1.25rem; border-radius: 0 0.5rem 0.5rem 0; margin: 1rem 0;
        }
        .dark .warn-box { background-color: #78350f; border-left-color: #fbbf24; }
        .doc-gif-wrap { position: relative; margin: 1rem 0; }
        .doc-gif-wrap .gif-badge {
            position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,0.6);
            color: #fff; font-size: 11px; font-weight: 600; padding: 3px 10px;
            border-radius: 20px; pointer-events: none; z-index: 1; letter-spacing: 0.5px;
        }
        .doc-gif { border-radius: 0.5rem; border: 2px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.12); width: 100%; }
        .dark .doc-gif { border-color: #374151; }
    </style>
</head>

@php
    $user = auth()->user();
    $roleName = $user->roles->first()?->name ?? 'agent';
    $isAdmin = $user->role_id == \App\Models\Role::ADMIN;
    $isCompany = $user->role_id == \App\Models\Role::COMPANY;
    $isBranch = $user->role_id == \App\Models\Role::BRANCH;
    $isAgent = $user->role_id == \App\Models\Role::AGENT;
    $isAccountant = $user->role_id == \App\Models\Role::ACCOUNTANT;

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

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200"
      x-data="docsApp()" x-init="init()">

    <div class="fixed top-0 left-0 w-full h-1 z-50 bg-gray-200 dark:bg-gray-700">
        <div id="progress-bar" class="h-full bg-primary-500" :style="'width:' + scrollProgress + '%'"></div>
    </div>

    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-1 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
                </button>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-500" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/>
                </svg>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">User Documentation</h1>
                <span class="text-xs bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 px-2 py-1 rounded-full font-semibold uppercase">{{ $roleName }}</span>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                    <i class="fas fa-arrow-left mr-1"></i> Back to App
                </a>
                <button @click="toggleDarkMode()" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 dark:hidden" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden dark:block text-yellow-300" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false" class="fixed inset-0 bg-black/50 z-40 lg:hidden"></div>

    <div x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-200 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-200 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
         class="fixed inset-y-0 left-0 z-50 w-72 bg-white dark:bg-gray-800 shadow-xl lg:hidden overflow-y-auto">
        <div class="p-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Navigation</h2>
                <button @click="sidebarOpen = false" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <div class="relative mb-4">
                <input type="text" x-model="searchQuery" @input="filterSections()" placeholder="Search docs..."
                       class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
            </div>
            <div x-show="searchQuery.length > 0 && searchResults.length > 0" class="mb-4 bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                <template x-for="result in searchResults" :key="result.id">
                    <a :href="'#' + result.id" @click="scrollToSection(result.id)" class="block px-3 py-2 text-sm rounded hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                        <span x-text="result.title" class="font-medium"></span>
                    </a>
                </template>
            </div>
            <template x-for="group in navGroups" :key="group.label">
                <div class="mb-3">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1 px-3" x-text="group.label"></p>
                    <template x-for="link in group.links" :key="link.id">
                        <a :href="'#' + link.id" @click="scrollToSection(link.id)"
                           class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md"
                           :class="activeSection === link.id ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
                            <i :class="link.icon + ' w-4 mr-3'"></i>
                            <span x-text="link.title"></span>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">

            <div class="hidden lg:block lg:col-span-3">
                <nav class="sticky top-24 space-y-1 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm max-h-[calc(100vh-8rem)] overflow-y-auto">
                    <div class="relative mb-4">
                        <input type="text" x-model="searchQuery" @input="filterSections()" placeholder="Search docs..."
                               class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
                    </div>
                    <div x-show="searchQuery.length > 0 && searchResults.length > 0" class="mb-4 bg-gray-50 dark:bg-gray-700 rounded-lg p-2">
                        <template x-for="result in searchResults" :key="result.id">
                            <a :href="'#' + result.id" @click="scrollToSection(result.id)" class="block px-3 py-2 text-sm rounded hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                <span x-text="result.title" class="font-medium"></span>
                            </a>
                        </template>
                    </div>
                    <div x-show="searchQuery.length === 0">
                        <template x-for="group in navGroups" :key="group.label">
                            <div class="mb-3">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1 px-3" x-text="group.label"></p>
                                <template x-for="link in group.links" :key="link.id">
                                    <a :href="'#' + link.id" @click.prevent="scrollToSection(link.id)"
                                       class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md"
                                       :class="activeSection === link.id ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'">
                                        <i :class="link.icon + ' w-4 mr-3'" :style="activeSection === link.id ? 'color: #2945a2' : ''"></i>
                                        <span x-text="link.title"></span>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </nav>
            </div>

            <div class="mt-8 lg:mt-0 lg:col-span-9">

                <div id="welcome" class="bg-gradient-to-r from-primary-600 to-primary-500 rounded-xl shadow-lg p-8 mb-12 text-white">
                    <h1 class="text-3xl sm:text-4xl font-extrabold mb-3">
                        @if($isAdmin) System Administration @elseif($isCompany) Company Management @elseif($isAgent) Agent Workspace @elseif($isAccountant) Accountant Toolkit @elseif($isBranch) Branch Operations @else Getting Started @endif
                    </h1>
                    <p class="text-sm opacity-75 font-medium uppercase tracking-wide mb-1">User Guide &mdash; {{ ucfirst($roleName) }} Role</p>
                    <p class="text-lg opacity-90 max-w-3xl">
                        @if($isAdmin)
                            Complete system administration guide. Manage companies, users, suppliers, settings, and monitor all operations.
                        @elseif($isCompany)
                            Manage your company operations &mdash; branches, staff, clients, tasks, invoices, and company settings.
                        @elseif($isAgent)
                            Your guide to creating tasks, managing clients, generating invoices, and tracking your bookings.
                        @else
                            Guide for using the Travel Agency Management System.
                        @endif
                    </p>
                </div>

                <section id="getting-started" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-rocket text-primary-500 mr-2"></i> Getting Started
                    </h2>

                    <h3 class="text-lg font-semibold mb-3">Logging In</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Open the system URL in your browser. You will see a login page with <strong>Email</strong> and <strong>Password</strong> fields. Enter the credentials provided by your administrator and click <strong>"Login"</strong>.
                    </p>
                    <div class="info-box mb-4">
                        <p class="text-sm"><strong><i class="fas fa-info-circle mr-1"></i> No public registration.</strong> All accounts are created internally by an Admin or Company Manager. If you do not have login credentials, contact your administrator to create an account for you.</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/login-flow.gif') }}" alt="Login Flow" class="doc-gif"></div>

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3 mt-6">Your Dashboard</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        After logging in, you land on the <strong>Dashboard</strong> which shows system-wide performance metrics &mdash; total tasks, invoices, revenue, and recent activity. Since you have access to all companies, you can use the <strong>company selector</strong> in the left sidebar to switch between companies and view their individual data.
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The sidebar navigation on the left gives you access to every module: <strong>Users</strong>, <strong>Tasks</strong>, <strong>Invoices</strong>, <strong>Payment Links</strong>, <strong>Finances</strong>, <strong>Reports</strong>, <strong>Settings</strong>, and more. As Admin, all menu items are visible to you.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/admin-dashboard.gif') }}" alt="Admin Dashboard" class="doc-gif"></div>

                    @elseif($isCompany)
                    <h3 class="text-lg font-semibold mb-3 mt-6">Your Dashboard</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        After logging in, your <strong>Dashboard</strong> shows your company's overall performance &mdash; total tasks created, invoices issued, revenue collected, and recent activity across all your branches and agents. Use the sidebar navigation to access your modules.
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        You can see most of the same menu items as an Admin, except you <strong>cannot</strong> access System Settings, manage other companies, or create/activate suppliers. Your data is scoped to your company only.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/company-dashboard.gif') }}" alt="Company Dashboard" class="doc-gif"></div>

                    @elseif($isAgent)
                    <h3 class="text-lg font-semibold mb-3 mt-6">After Login</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Once you log in, you are taken directly to the <strong>Tasks page</strong>. This is your main workspace where you manage all your bookings. You do not have a separate dashboard.
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Your sidebar navigation shows only the modules relevant to you: <strong>Tasks</strong>, <strong>Clients</strong>, <strong>Invoices</strong>, <strong>Payment Links</strong>, <strong>Refunds</strong>, and <strong>Currency Exchange</strong>. You will <strong>not</strong> see menu items like Users, Suppliers, Settings, Reports, or Accounting &mdash; those are managed by your Admin or Company Manager.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/agent-homepage.gif') }}" alt="Agent Tasks Home" class="doc-gif"></div>

                    @else
                    <h3 class="text-lg font-semibold mb-3 mt-6">After Login</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        After logging in, you will see your main workspace with relevant metrics and navigation based on your role and permissions.
                    </p>
                    @endif

                    <h3 class="text-lg font-semibold mb-3 mt-6">Navigating the System</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The system uses a <strong>sidebar navigation</strong> on the left side. Click any menu item to navigate to that module. On mobile devices, tap the <strong>hamburger menu</strong> (<i class="fas fa-bars"></i>) at the top-left to open the sidebar.
                    </p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>The sidebar shows only the modules you have <strong>permission</strong> to access</li>
                        <li>Use the <strong>search bar</strong> at the top of most pages to quickly find records</li>
                        <li>Most list pages support <strong>filtering</strong> by date, status, type, and other fields</li>
                        <li>Look for the <strong>round + button</strong> (<i class="fas fa-plus-circle text-primary-500"></i>) on list pages to create new records</li>
                    </ul>
                </section>

                @can('viewAny', App\Models\Role::class)
                <section id="role-overview" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-shield-halved text-primary-500 mr-2"></i> Roles & What Each Can Do
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        The system has 6 roles. You can manage permissions for each role from <strong>Settings &rarr; Roles</strong>. Here is what each role can do by default:
                    </p>

                    <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-5 @if($isAdmin) ring-2 ring-amber-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-amber-200 dark:bg-amber-800 text-amber-800 dark:text-amber-200 text-xs font-bold px-2 py-1 rounded">ADMIN</span>
                            <span class="font-semibold">Super Administrator</span>
                            @if($isAdmin) <span class="bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">Your Role</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Create &amp; manage companies</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Create branches, agents, accountants, clients</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Switch between companies (sidebar)</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Add, activate &amp; deactivate suppliers</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Configure system settings (email, WhatsApp, hotels, countries)</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Manage roles &amp; permissions</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Create tasks, invoices, payment links</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> View all reports across all companies</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Process payments &amp; refunds</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Manage Chart of Accounts &amp; accounting</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-5 @if($isCompany) ring-2 ring-blue-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-blue-200 dark:bg-blue-800 text-blue-800 dark:text-blue-200 text-xs font-bold px-2 py-1 rounded">COMPANY</span>
                            <span class="font-semibold">Company Manager</span>
                            @if($isCompany) <span class="bg-blue-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">Your Role</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Create branches for their company</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Add agents, accountants, clients</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Create &amp; manage tasks, invoices</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Configure company settings (payment, terms, gateways)</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> View company-wide reports</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Send payment links, process refunds</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Manage roles &amp; permissions for their company</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> View suppliers, edit charges &amp; credentials, get supplier tasks</div>
                            <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot create other companies</div>
                            <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot access system settings</div>
                            <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot add, activate, or deactivate suppliers</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-5 @if($isBranch) ring-2 ring-green-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-green-200 dark:bg-green-800 text-green-800 dark:text-green-200 text-xs font-bold px-2 py-1 rounded">BRANCH</span>
                            <span class="font-semibold">Branch Manager</span>
                            @if($isBranch) <span class="bg-green-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">Your Role</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Add agents &amp; clients to their branch</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Create &amp; manage tasks, invoices</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> View branch-level data only</div>
                            <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot create branches or companies</div>
                            <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot access company settings</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-5 @if($isAgent) ring-2 ring-indigo-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200 text-xs font-bold px-2 py-1 rounded">AGENT</span>
                            <span class="font-semibold">Travel Agent</span>
                            @if($isAgent) <span class="bg-indigo-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">Your Role</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Create tasks (auto-assigned to them)</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Add their own clients</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Create invoices &amp; payment links</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> See only their own data</div>
                            <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot see other agents' tasks</div>
                            <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot manage users or settings</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-pink-50 dark:bg-pink-900/20 border border-pink-200 dark:border-pink-800 rounded-lg p-5 @if($isAccountant) ring-2 ring-pink-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-pink-200 dark:bg-pink-800 text-pink-800 dark:text-pink-200 text-xs font-bold px-2 py-1 rounded">ACCOUNTANT</span>
                            <span class="font-semibold">Accountant</span>
                            @if($isAccountant) <span class="bg-pink-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">Your Role</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Manage Chart of Accounts</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Process payments &amp; refunds</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Handle accounting entries &amp; vouchers</div>
                            <div><i class="fas fa-check text-green-500 mr-1"></i> Generate financial reports</div>
                            <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot manage users or settings</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-purple-200 dark:bg-purple-800 text-purple-800 dark:text-purple-200 text-xs font-bold px-2 py-1 rounded">CLIENT</span>
                            <span class="font-semibold">Client / Customer</span>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            <p><i class="fas fa-info-circle text-blue-400 mr-1"></i> Clients <strong>do not log in</strong>. They are records managed by agents/admins. Clients receive payment links via email or WhatsApp to pay their invoices.</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">Managing Permissions</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Navigate to <strong>Settings &rarr; Roles</strong> to view and customize what each role can do. The Roles page displays all available roles as cards.
                    </p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Click on a <strong>role card</strong> (e.g., Agent, Company) to open its permissions panel.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">You will see a list of all permissions grouped by module (Users, Tasks, Invoices, etc.). Each permission has a <strong>toggle switch</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm"><strong>Turn a toggle ON</strong> to grant the permission, or <strong>OFF</strong> to revoke it. Changes are saved automatically.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">You can also <strong>create a new role</strong> by clicking the add button, giving it a name, and then assigning permissions to it.</p>
                    </div>
                    <div class="warn-box mb-4">
                        <p class="text-sm"><strong><i class="fas fa-exclamation-triangle mr-1"></i> Be careful when removing permissions.</strong> If you remove a permission from a role, all users with that role will immediately lose access to that feature. For example, removing "view task" from the Agent role means agents can no longer see the Tasks page.</p>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        <strong>Available permission types per module:</strong> Most modules support <strong>View</strong>, <strong>Create</strong>, <strong>Update</strong>, and <strong>Delete</strong>. Some modules have additional permissions like <strong>"view task price"</strong> (controls whether an agent can see cost/profit columns) or <strong>"update payment method"</strong> on invoices.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/admin-roles.gif') }}" alt="Roles & Permissions" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\User')
                <section id="user-management" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-users text-primary-500 mr-2"></i> User Management
                    </h2>

                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        All user accounts are created from within the system &mdash; there is no self-registration. Navigate to <strong>Users</strong> in the sidebar to see available user types. You can <strong>view</strong>, <strong>create</strong>, <strong>edit</strong>, and <strong>delete</strong> users depending on your permissions.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/user-management.gif') }}" alt="User Management Overview" class="doc-gif"></div>

                    @can('viewAny', 'App\Models\Company')
                    <div class="info-box mb-6">
                        <p class="text-sm"><strong><i class="fas fa-info-circle mr-1"></i> First-Time Setup Order:</strong> When setting up a new system, follow this recommended sequence: <strong>Create Company &rarr; Create Branch &rarr; Add Agent / Accountant &rarr; Add Clients &rarr; Setup Suppliers &rarr; Configure Settings</strong>. Each step depends on the previous one.</p>
                    </div>

                    <div id="companies" class="mb-10 scroll-mt-24">
                        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-building text-blue-500 mr-2"></i> Creating a Company</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            A Company is a travel agency business entity. This is the first thing you create. Go to <strong>Users &rarr; Companies</strong> and click <strong>"Add Company"</strong>.
                        </p>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">1</span>
                            <p class="text-sm">Fill in <strong>Company Name</strong>, <strong>Email</strong> (login email for the company manager), <strong>Password</strong>, <strong>Phone</strong>, <strong>Country</strong>, and <strong>Company Logo</strong> (appears on invoices).</p>
                        </div>
                        <div class="flex items-start gap-3 mb-4">
                            <span class="step-number">2</span>
                            <p class="text-sm">Click <strong>"Save"</strong>. The company manager can now log in with the email and password you set.</p>
                        </div>
                        <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/create-company.gif') }}" alt="Create Company" class="doc-gif"></div>
                    </div>
                    @endcan

                    @can('viewAny', App\Models\Branch::class)
                    <div id="branches" class="mb-10 scroll-mt-24">
                        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-code-branch text-green-500 mr-2"></i> Creating a Branch</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            A Branch is a physical or logical office under a company. Every company needs at least one branch. Go to <strong>Users &rarr; Branches</strong> and click the <strong>add button</strong>.
                        </p>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">1</span>
                            <p class="text-sm">Select the <strong>Company</strong>, fill in <strong>Branch Name</strong>, <strong>Email &amp; Password</strong> (for the branch manager), <strong>Phone</strong>, and <strong>Address</strong>.</p>
                        </div>
                        <div class="flex items-start gap-3 mb-4">
                            <span class="step-number">2</span>
                            <p class="text-sm">Click <strong>"Save"</strong>.</p>
                        </div>
                        <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/create-branch.gif') }}" alt="Create Branch" class="doc-gif"></div>
                    </div>
                    @endcan

                    @can('viewAny', App\Models\Agent::class)
                    <div id="agents" class="mb-10 scroll-mt-24">
                        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-user-tie text-indigo-500 mr-2"></i> Adding Agents</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Agents are the primary operators &mdash; they create tasks, manage bookings, and handle clients. Go to <strong>Users &rarr; Agents</strong> and click the <strong>add button</strong>.
                        </p>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">1</span>
                            <div class="text-sm">
                                <p>Fill in <strong>Company</strong>, <strong>Branch</strong>, <strong>Name</strong>, <strong>Email &amp; Password</strong>, <strong>Phone</strong>.</p>
                                <p class="mt-1">Select <strong>Agent Type</strong>:</p>
                                <ul class="list-disc list-inside text-gray-500 dark:text-gray-400 mt-1 ml-2">
                                    <li><strong>Salary</strong> &mdash; Fixed salary, no commissions</li>
                                    <li><strong>Commission</strong> &mdash; Earns % on bookings</li>
                                    <li><strong>Both-A</strong> &mdash; Salary + commission on all tasks</li>
                                    <li><strong>Both-B</strong> &mdash; Salary + commission on selected tasks</li>
                                </ul>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 mb-4">
                            <span class="step-number">2</span>
                            <p class="text-sm">Click <strong>"Save"</strong>.</p>
                        </div>
                        <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/create-agent.gif') }}" alt="Create Agent" class="doc-gif"></div>
                    </div>
                    @endcan

                    @can('viewAny', App\Models\Client::class)
                    <div id="add-clients" class="mb-10 scroll-mt-24">
                        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-user text-purple-500 mr-2"></i> Managing Clients</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Clients are your customers &mdash; the people you create bookings for. They <strong>do not log into the system</strong>. Instead, they are records linked to tasks and invoices, and they receive payment links via email or WhatsApp.
                        </p>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Go to <strong>Users &rarr; Clients</strong> to see all clients. You can <strong>view</strong>, <strong>create</strong>, <strong>edit</strong>, and <strong>delete</strong> client records from this page.
                        </p>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">1</span>
                            <p class="text-sm">Click the <strong>add button</strong> to create a new client.</p>
                        </div>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">2</span>
                            <p class="text-sm">Fill in <strong>Company</strong>, <strong>Client Name</strong>, <strong>Email</strong> (used for sending invoices and payment links), <strong>Phone</strong> (used for WhatsApp notifications), and <strong>Country</strong>.</p>
                        </div>
                        <div class="flex items-start gap-3 mb-4">
                            <span class="step-number">3</span>
                            <p class="text-sm">Click <strong>"Save"</strong>. The client is now available in the dropdown when creating tasks and invoices.</p>
                        </div>
                        <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/manage-clients.gif') }}" alt="Manage Clients" class="doc-gif"></div>
                        <div class="info-box">
                            <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> To <strong>edit</strong> a client, click the edit icon on their row. To <strong>delete</strong>, click the delete icon. Deleting a client does not delete their existing tasks or invoices.</p>
                        </div>
                    </div>
                    @endcan
                </section>
                @endcan

                @can('viewAny', App\Models\Client::class)
                @cannot('viewAny', 'App\Models\User')
                <section id="my-clients" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-user text-primary-500 mr-2"></i> Your Clients
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        As an agent, you manage your own client list. Clients are the customers you create bookings for. They <strong>do not log into the system</strong> &mdash; they are contact records used for tasks, invoices, and payment links.
                    </p>

                    <h3 class="text-lg font-semibold mb-3">What You Can Do</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>View</strong> your own clients</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Create</strong> new clients</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Edit</strong> client details</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Delete</strong> your clients</div>
                        <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot see other agents' clients</div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">Adding a Client</h3>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Go to <strong>Clients</strong> from the sidebar, click the <strong>add button</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Fill in the client's <strong>Name</strong>, <strong>Email</strong> (used for sending invoices and payment links), <strong>Phone</strong> (used for WhatsApp messages), and <strong>Country</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">Click <strong>"Save"</strong>. The client is now available when you create tasks and invoices.</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/agent-clients.gif') }}" alt="Agent Client Management" class="doc-gif"></div>

                    <div class="info-box">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> When creating tasks, you can <strong>only select clients registered under you</strong>. If you need to transfer a client to another agent, contact your Company Manager or Admin.</p>
                    </div>
                </section>
                @endcannot
                @endcan

                @can('viewAny', App\Models\Supplier::class)
                <section id="suppliers" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-handshake text-primary-500 mr-2"></i> Supplier Management
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Suppliers are the external service providers your company works with &mdash; airlines, hotels, visa processors, insurance companies, tour operators, car rental agencies, and more. Suppliers must be set up in the system before agents can use them when creating tasks (bookings).
                    </p>

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3">What You Can Do (Admin)</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>View</strong> all suppliers in the system</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Create</strong> new suppliers</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Edit</strong> supplier name and service types</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Delete</strong> suppliers</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Activate / Deactivate</strong> suppliers per company</div>
                    </div>
                    @elseif($isCompany)
                    <h3 class="text-lg font-semibold mb-3">What You Can Do (Company)</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>View</strong> suppliers activated for your company</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Edit</strong> supplier details</div>
                        <div><i class="fas fa-times text-red-400 mr-1"></i> <strong>Cannot create</strong> new suppliers</div>
                        <div><i class="fas fa-times text-red-400 mr-1"></i> <strong>Cannot activate/deactivate</strong> suppliers</div>
                    </div>
                    <div class="info-box mb-6">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> To add a new supplier or activate one for your company, contact your <strong>Admin</strong>.</p>
                    </div>
                    @endif

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3">Adding a New Supplier</h3>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Go to <strong>Suppliers</strong> from the sidebar, click the <strong>add button</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <div class="text-sm">
                            <p>Fill in <strong>Supplier Name</strong> and select one or more <strong>Service Types</strong>:</p>
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach(['Hotel','Flight','Visa','Insurance','Tour','Cruise','Car','Rail','eSIM','Event','Lounge','Ferry'] as $type)
                                <span class="bg-gray-100 dark:bg-gray-700 text-xs px-2 py-0.5 rounded">{{ $type }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">Click <strong>"Save"</strong>. The supplier is now created but <strong>not yet activated</strong> for any company.</p>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">Activating Suppliers for a Company</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        After creating a supplier, you need to <strong>activate</strong> it for each company that should be able to use it. On the Suppliers list, use the <strong>toggle button</strong> next to each company column to activate or deactivate. Only activated suppliers appear in the task creation form when agents of that company create a new booking.
                    </p>
                    @endif

                    <h3 class="text-lg font-semibold mb-3">Editing a Supplier</h3>
                    @if($isAdmin)
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Click the <strong>edit button</strong> on a supplier row to modify its name or service types. Click <strong>"Update"</strong> to save. Changes apply to all future tasks using this supplier.
                    </p>
                    @else
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Click the <strong>edit button</strong> on a supplier row. As a Company Manager, you can <strong>edit supplier charges</strong> (markup, fees), <strong>edit supplier credentials</strong> (API keys, login details), and <strong>view supplier tasks</strong>. You cannot change the supplier name, service types, or activation status &mdash; contact your Admin for those changes.
                    </p>
                    @endif

                    @if($isAdmin)
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/admin-suppliers.gif') }}" alt="Supplier Management (Admin)" class="doc-gif"></div>
                    @else
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/company-suppliers.gif') }}" alt="Supplier Management (Company)" class="doc-gif"></div>
                    @endif

                    <div class="warn-box">
                        <p class="text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> If an agent cannot see a supplier when creating a task, it means the supplier has not been activated for that agent's company. Ask your Admin to activate it.</p>
                    </div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Setting')
                <section id="settings" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-cog text-primary-500 mr-2"></i> Company Settings
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Navigate to <strong>Settings</strong> from the sidebar. This page controls your company's billing, payment, and notification configurations. Settings are organized into tabs &mdash; click each tab to configure that section.
                    </p>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/settings.gif') }}" alt="Settings Tabs" class="doc-gif"></div>

                    <div class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-money-bill text-green-500 mr-2"></i> Payment Settings</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Configure your company's billing defaults:</p>
                            <ul class="list-disc list-inside text-sm text-gray-500 dark:text-gray-400 space-y-1">
                                <li><strong>Default Currency</strong> &mdash; The currency used for new invoices and payment links (e.g., KWD, USD)</li>
                                <li><strong>Tax/VAT Rate</strong> &mdash; The tax percentage applied to invoices</li>
                                <li><strong>Invoice Prefix</strong> &mdash; Custom prefix for invoice numbers (e.g., INV-, CT-)</li>
                                <li><strong>Payment Due Days</strong> &mdash; Default number of days until payment is due</li>
                            </ul>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-file-contract text-blue-500 mr-2"></i> Terms & Regulation</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Set the <strong>terms and conditions</strong> text that appears on invoices and payment links. Clients must accept these terms before completing payment in Advanced mode. You can customize terms for different service types.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-credit-card text-purple-500 mr-2"></i> Payment Gateways</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Configure the online payment gateways so clients can pay through payment links. Supported gateways:</p>
                            <div class="flex flex-wrap gap-2 mb-3">
                                @foreach(['Tap','MyFatoorah','Hesabe','UPayment','Knet','Bank Transfer'] as $gw)
                                <span class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-xs px-3 py-1 rounded-lg font-medium">{{ $gw }}</span>
                                @endforeach
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Click a gateway card, enter the <strong>API Key</strong> and <strong>Secret Key</strong> provided by your payment provider, toggle to <strong>Active</strong>, and save.</p>
                            <div class="warn-box mt-3">
                                <p class="text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> You <strong>must</strong> configure at least one active gateway before you can create payment links or send invoices for online payment.</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-wallet text-orange-500 mr-2"></i> Payment Methods</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Define how payments are recorded internally. Available methods include <strong>Cash</strong>, <strong>Credit Card</strong>, <strong>Bank Transfer</strong>, and <strong>Cheque</strong>. These appear as options when manually recording payments on invoices or importing payments.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-percent text-red-500 mr-2"></i> Agent Charges</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Configure commission rates and charges for agents. The system uses the <strong>Agent Type</strong> (Salary, Commission, Both-A, Both-B) to automatically calculate commissions when tasks are completed.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-bell text-indigo-500 mr-2"></i> Notifications</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Control which events trigger notifications and how they are sent. Toggle notifications for events like <strong>invoice creation</strong>, <strong>payment received</strong>, <strong>payment reminders</strong>, and more. Notifications can be delivered via <strong>Email</strong> and <strong>WhatsApp</strong> (when configured).</p>
                        </div>
                    </div>
                </section>
                @endcan

                @can('manage-system-settings')
                <section id="system-settings" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-server text-primary-500 mr-2"></i> System Settings
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        System Settings control global configurations. Navigate to <strong>Settings &rarr; System Settings</strong>.
                    </p>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/system-settings.gif') }}" alt="System Settings" class="doc-gif"></div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-envelope text-blue-500 mr-1"></i> Email (SMTP)</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Configure SMTP server for sending emails. Test before going live.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-1"><i class="fab fa-whatsapp text-green-500 mr-1"></i> WhatsApp API</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Set API token and webhook for WhatsApp notifications to clients.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-hotel text-purple-500 mr-1"></i> Hotel Management</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Add hotels with ratings, locations for hotel-type tasks.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-globe text-teal-500 mr-1"></i> Country Management</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Manage countries used in profiles, destinations, and visa processing.</p>
                        </div>
                    </div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Task')
                <section id="tasks" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-tasks text-primary-500 mr-2"></i> Tasks (Bookings)
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Tasks are the core of the system &mdash; every booking (flight, hotel, visa, insurance, tour, etc.) is recorded as a task. Each task tracks the service type, supplier, client, agent, dates, pricing (selling price, cost price, profit), and status.
                    </p>

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3">What You Can Do (Admin)</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>View</strong> all tasks across all companies</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Create</strong> tasks and assign to any agent</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Edit</strong> any task details</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Delete</strong> tasks</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Edit financial details</strong> (cost, selling price, profit)</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Bulk edit</strong> multiple tasks at once</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Filter</strong> by agent, company, date, status</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> See <strong>File column</strong> with attached documents</div>
                    </div>
                    @elseif($isCompany)
                    <h3 class="text-lg font-semibold mb-3">What You Can Do (Company)</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>View</strong> all tasks in your company</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Create</strong> tasks and assign to your agents</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Edit</strong> task details</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Bulk edit</strong> multiple tasks at once</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> See <strong>File column</strong> and <strong>cancellation deadline</strong></div>
                        <div><i class="fas fa-times text-red-400 mr-1"></i> <strong>Cannot delete</strong> tasks (Admin only)</div>
                        <div><i class="fas fa-times text-red-400 mr-1"></i> <strong>Cannot edit financial details</strong> (Admin only)</div>
                    </div>
                    @else
                    <h3 class="text-lg font-semibold mb-3">What You Can Do (Agent)</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>View</strong> only your own tasks</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Create</strong> tasks (auto-assigned to you)</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> <strong>Edit</strong> your own tasks</div>
                        <div><i class="fas fa-check text-green-500 mr-1"></i> Select clients <strong>from your own client list</strong></div>
                        <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot see other agents' tasks</div>
                        <div><i class="fas fa-times text-red-400 mr-1"></i> <strong>Cannot delete</strong> tasks</div>
                        <div><i class="fas fa-times text-red-400 mr-1"></i> Cannot edit financial details</div>
                    </div>
                    @endif

                    <h3 class="text-lg font-semibold mb-3">Task List</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Navigate to <strong>Tasks</strong> from the sidebar. The task list shows all your tasks in a table with columns for task number, type, client, supplier, dates, status, and pricing. Use the filters at the top to narrow results by <strong>date range</strong>, <strong>status</strong>, <strong>task type</strong>, or <strong>client</strong>.
                        @cannot('viewAny', App\Models\Agent::class)
                        You will only see tasks assigned to you.
                        @endcannot
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-list.gif') }}" alt="Task List View" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Creating a New Task</h3>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Click the <strong>add button</strong> (round + icon) on the Tasks page.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <div class="text-sm">
                            <p><strong>Select the Task Type</strong> &mdash; the form fields change dynamically based on the type you choose:</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-2 mb-2">
                                <span class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-plane mr-1"></i> Flight</span>
                                <span class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-hotel mr-1"></i> Hotel</span>
                                <span class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-passport mr-1"></i> Visa</span>
                                <span class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-shield-alt mr-1"></i> Insurance</span>
                                <span class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-bus mr-1"></i> Tour / Cruise / Car / Rail</span>
                                <span class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-ellipsis-h mr-1"></i> eSIM / Event / Lounge / Ferry</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <div class="text-sm">
                            <p>Fill in the task details:</p>
                            <ul class="list-disc list-inside text-gray-500 dark:text-gray-400 mt-1 space-y-1">
                                @can('viewAny', App\Models\Agent::class)
                                <li><strong>Agent</strong> &mdash; Select which agent handles this booking</li>
                                @endcan
                                <li><strong>Client</strong> &mdash; Select the customer @cannot('viewAny', App\Models\Agent::class)(from your own client list)@endcannot</li>
                                <li><strong>Supplier</strong> &mdash; Select the service provider (only activated suppliers appear)</li>
                                <li><strong>Selling Price</strong> &mdash; Amount charged to the client</li>
                                <li><strong>Cost Price</strong> &mdash; Amount you pay to the supplier</li>
                                <li><strong>Status</strong> &mdash; Confirmed, Pending, Cancelled, etc.</li>
                                <li>Type-specific fields (flight routes, PNR, hotel check-in/out dates, room type, visa application dates, etc.)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">Click <strong>"Save"</strong>. The system automatically calculates <strong>Profit</strong> (Selling Price &minus; Cost Price) and assigns a task number.</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-create.gif') }}" alt="Create Task Workflow" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3 mt-6">Editing a Task</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Click on a task row in the list to open the task detail view. Click the <strong>edit button</strong> to modify any field, then click <strong>"Update"</strong> to save your changes.
                    </p>
                    @if($isAdmin)
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        As Admin, you also have access to the <strong>Financial Edit</strong> modal, which allows you to modify the cost price, selling price, and profit directly. This is useful for price corrections or adjustments after the task has been created.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-financial-edit.gif') }}" alt="Task Financial Edit (Admin)" class="doc-gif"></div>
                    @endif

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3 mt-6">Deleting a Task</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Only Admins can delete tasks. Click the <strong>delete button</strong> on a task row and confirm the deletion. Once deleted, the task cannot be recovered.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-delete.gif') }}" alt="Delete Task" class="doc-gif"></div>
                    <div class="warn-box mb-4">
                        <p class="text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> Deleting a task does <strong>not</strong> automatically delete related invoices. Make sure to handle any associated invoices separately.</p>
                    </div>
                    @endif

                    @can('create', 'App\Models\Invoice')
                    <h3 class="text-lg font-semibold mb-3 mt-6">Bulk Edit</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Select multiple tasks by <strong>clicking on the task row</strong> itself &mdash; selected rows will be highlighted. A toolbar appears at the top with bulk action options. Use <strong>Bulk Edit</strong> to update the agent, client, or payment method for all selected tasks at once.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-bulk-edit.gif') }}" alt="Bulk Edit Tasks" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3 mt-6">Create Invoice from Tasks</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Select one or more tasks by <strong>clicking on the task row</strong>, then click the <strong>"Create Invoice"</strong> button. This generates an invoice with the selected tasks as line items. This is the quickest way to bill a client for multiple bookings.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoice-create-from-tasks.gif') }}" alt="Create Invoice from Tasks" class="doc-gif"></div>
                    @endcan
                </section>
                @endcan

                @can('viewAny', 'App\Models\Invoice')
                <section id="invoices" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-file-invoice-dollar text-primary-500 mr-2"></i> Invoices
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Invoices are how you bill your clients. Each invoice contains one or more tasks as line items and tracks the total amount, payment status, and payment history. You can send invoices via <strong>email</strong>, <strong>WhatsApp</strong>, or as a <strong>PDF</strong>.
                    </p>

                    <h3 class="text-lg font-semibold mb-3">Invoice List</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Navigate to <strong>Invoices</strong> from the sidebar. The list shows each invoice's number, client name, total amount, payment gateway, status (color-coded), and action buttons. Use the filters to search by date, status, client, or invoice number.
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoice-list.gif') }}" alt="Invoice List" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Creating an Invoice</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">There are two ways to create an invoice:</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li><strong>From the Tasks page</strong> &mdash; Select tasks using checkboxes and click "Create Invoice" (fastest method)</li>
                        <li><strong>From the Invoices page</strong> &mdash; Click the add button and manually add tasks</li>
                    </ul>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Click the <strong>add button</strong> on the Invoices page.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <div class="text-sm">
                            <p>Select a <strong>Client</strong>@can('viewAny', App\Models\Agent::class) and <strong>Agent</strong>@endcan. Then configure:</p>
                            <ul class="list-disc list-inside text-gray-500 dark:text-gray-400 mt-1 space-y-1">
                                <li>Click <strong>"+ Add Task"</strong> to add tasks as line items</li>
                                <li>Set the <strong>Invoice Price</strong> for each task (can differ from the task's selling price)</li>
                                <li>Set <strong>Invoice Date</strong> and <strong>Due Date</strong></li>
                                <li>Choose a <strong>Payment Gateway</strong> (Tap, MyFatoorah, Knet, etc.)</li>
                                <li>Select the <strong>payment type</strong>: Full, Partial, Split, or Import</li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">Click <strong>"Save"</strong>. An invoice number is auto-generated using your company's prefix.</p>
                    </div>
                    @can('viewAny', App\Models\Agent::class)
                    <div class="info-box mb-4">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> As Admin or Company Manager, you can <strong>select which agent</strong> the invoice is for. Agents can only create invoices for their own clients.</p>
                    </div>
                    @endcan
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoice-create.gif') }}" alt="Create Invoice Workflow" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Invoice Statuses</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Invoices are automatically color-coded based on payment progress:</p>
                    <div class="flex flex-wrap gap-3 mb-6">
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span> <strong>Unpaid</strong> &mdash; No payment received</span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-yellow-500 inline-block"></span> <strong>Partial</strong> &mdash; Some payment received</span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span> <strong>Paid</strong> &mdash; Fully paid</span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> <strong>Paid by Refund</strong></span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-orange-500 inline-block"></span> <strong>Refunded</strong> &mdash; Fully refunded</span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-purple-500 inline-block"></span> <strong>Partial Refund</strong></span>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">Invoice Actions</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Each invoice row has action buttons on the right:</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li><i class="fas fa-eye text-blue-500 mr-1"></i> <strong>View</strong> &mdash; Open the full invoice detail page with all tasks, payments, and history</li>
                        <li><i class="fas fa-file-pdf text-red-500 mr-1"></i> <strong>PDF</strong> &mdash; Download the invoice as a PDF document (uses your company logo and branding)</li>
                        <li><i class="fas fa-envelope text-green-500 mr-1"></i> <strong>Email</strong> &mdash; Send the invoice directly to the client's email address</li>
                        <li><i class="fab fa-whatsapp text-green-600 mr-1"></i> <strong>WhatsApp</strong> &mdash; Send a payment link to the client via WhatsApp</li>
                        <li><i class="fas fa-lock text-gray-500 mr-1"></i> <strong>Lock</strong> &mdash; Prevent further editing of the invoice (recommended after sending to client)</li>
                        <li><i class="fas fa-trash text-red-500 mr-1"></i> <strong>Delete</strong> &mdash; Remove the invoice entirely</li>
                    </ul>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoice-actions.gif') }}" alt="Invoice Action Buttons" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Payment Types</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">When creating an invoice, choose how the client will pay:</p>
                    <div class="grid sm:grid-cols-2 gap-3 mb-4">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-check-circle text-green-500 mr-1"></i> Full Payment</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Client pays the entire invoice amount at once through the payment gateway.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-adjust text-yellow-500 mr-1"></i> Partial Payment</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Client pays a portion now. The remaining balance is tracked and can be collected later.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-random text-blue-500 mr-1"></i> Split Payment</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Split the invoice amount across multiple payment methods or gateways (e.g., part cash, part card).</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-file-import text-purple-500 mr-1"></i> Import Payment</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Record a payment already received outside the system (bank transfer, cash, etc.).</p>
                        </div>
                    </div>
                    <div class="info-box">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> Split and partial payment options are only available on <strong>invoices</strong>. Payment links always collect the full specified amount.</p>
                    </div>
                </section>

                <section id="invoices-link" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-link text-primary-500 mr-2"></i> Invoices Link
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        <strong>Invoices Link</strong> lets you group multiple invoices together and share them as a single URL. This is useful when a client has multiple bookings and you want them to see and pay all invoices from one page.
                    </p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Navigate to <strong>Invoices &rarr; Invoices Link</strong> and click <strong>"+ New Invoice Link"</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Select the <strong>invoices</strong> you want to group together from the list.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">Click <strong>"Create"</strong>. Share the generated link with the client via email, WhatsApp, or copy the URL.</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoices-link.gif') }}" alt="Invoices Link" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Payment')
                <section id="payment-links" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-money-check-alt text-primary-500 mr-2"></i> Payment Links
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Payment Links are used to <strong>collect money from clients online</strong>. When you create a payment link, the client receives a URL where they can make a payment through the configured payment gateway (Tap, MyFatoorah, Knet, etc.).
                    </p>

                    @can('viewAny', App\Models\Agent::class)
                    <div class="info-box mb-4">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> As Admin or Company Manager, you can see payment links from <strong>all agents</strong> in your scope and can choose which agent's client to create a link for. Agents can only see and create payment links for <strong>their own clients</strong>.</p>
                    </div>
                    @endcan

                    <h3 class="text-lg font-semibold mb-3">Payment Links Page</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Navigate to <strong>Payment Links</strong> from the sidebar. The page has two tabs:
                    </p>
                    <div class="grid sm:grid-cols-2 gap-4 mb-6">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-link text-blue-500 mr-1"></i> Payment Links (First Tab)</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">All payment links created within the system. Each row shows the client, amount, gateway, status (paid/unpaid/expired), and action buttons.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-file-import text-green-500 mr-1"></i> Imported (Second Tab)</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Payments made outside the system (bank transfer, cash, cheque) that were imported to keep records in sync.</p>
                        </div>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/payment-link.gif') }}" alt="Payment Links Page" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Payment Link Actions</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Each payment link row has these action buttons:</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-6">
                        <li><i class="fas fa-edit text-blue-500 mr-1"></i> <strong>Edit</strong> &mdash; Modify amount, expiry date, or other details</li>
                        <li><i class="fas fa-file-alt text-purple-500 mr-1"></i> <strong>View Voucher</strong> &mdash; See the payment receipt/voucher after payment is completed</li>
                        <li><i class="fab fa-whatsapp text-green-600 mr-1"></i> <strong>Send via WhatsApp</strong> &mdash; Send the payment link directly to the client on WhatsApp</li>
                        <li><i class="fas fa-ban text-orange-500 mr-1"></i> <strong>Disable</strong> &mdash; Deactivate the link so it can no longer be used (useful if you need to void it)</li>
                        <li><i class="fas fa-trash text-red-500 mr-1"></i> <strong>Delete</strong> &mdash; Remove the payment link from the system</li>
                    </ul>

                    <h3 class="text-lg font-semibold mb-3">Creating a Payment Link</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        There are two modes for creating a payment link:
                    </p>
                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-bolt text-blue-500 mr-1"></i> Quick Mode</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Simply request money. Set the client and amount &mdash; no product details needed. Best for collecting deposits or simple payments quickly.</p>
                        </div>
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-list-alt text-indigo-500 mr-1"></i> Advanced Mode</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Specify what the payment is for with itemized details. The client sees a breakdown and must read and accept <strong>Terms &amp; Conditions</strong> before paying.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Click <strong>"+ Create Payment Link"</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Choose <strong>Quick</strong> or <strong>Advanced</strong> mode. Select the <strong>Client</strong>@cannot('viewAny', App\Models\Agent::class) (from your own client list)@endcannot, set the <strong>Amount</strong>, choose the <strong>Payment Gateway</strong>, and set an <strong>Expiry Date</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">Click <strong>"Create"</strong>. Share the link with the client via <strong>WhatsApp</strong>, <strong>email</strong>, or <strong>copy the URL</strong>.</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/payment-link-create.gif') }}" alt="Create Payment Link" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Importing External Payments</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        When a client pays outside the system (bank transfer, cash, cheque), you can record it by importing the payment. Click <strong>"+ Create Payment Link"</strong> and select <strong>"Import"</strong>. Enter the payment details and attach any proof of payment. Only <strong>completed payments</strong> can be imported.
                    </p>

                    <div class="info-box">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> Payment links always collect the <strong>full payment amount</strong>. For partial or split payments, use the <strong>invoice payment options</strong> instead (see Invoices section above).</p>
                    </div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Refund')
                <section id="refunds" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-undo text-primary-500 mr-2"></i> Refunds
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        When a booking is cancelled or a client requests money back, process it through the <strong>Refunds</strong> module. Refunds can be full or partial and are linked to a specific invoice. <strong>Important:</strong> you can only create a refund for tasks that have already been invoiced &mdash; if a task has no invoice, it cannot be refunded. There are <strong>two ways</strong> to create a refund:
                    </p>

                    <h3 class="text-lg font-semibold mb-3">Method 1: From the Refunds Page</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">
                        The Refunds page shows all refund records with their status. From here you can <strong>view</strong>, <strong>edit</strong>, or <strong>create</strong> new refunds.
                    </p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Navigate to <strong>Refunds</strong> from the sidebar. You will see the list of all refunds with their status, amount, and linked invoice.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Click the <strong>"+ Create"</strong> button to start a new refund.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">Select the <strong>Invoice</strong> to refund, enter the <strong>Refund Amount</strong> (full or partial), select the <strong>Refund Method</strong> (same gateway, bank transfer, cash, etc.), and provide a <strong>Reason</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">Click <strong>"Process Refund"</strong>. The invoice status updates automatically to <strong>Refunded</strong> or <strong>Partial Refund</strong> based on the amount.</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/refund-process.gif') }}" alt="Refund List Page &amp; Create Refund" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Method 2: From the Task List</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">
                        You can also create a refund directly from the <strong>Tasks</strong> page. This is useful when you know which tasks need to be refunded.
                    </p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Go to the <strong>Tasks</strong> page and <strong>click on the task rows</strong> you want to refund &mdash; selected rows will be highlighted.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Click the <strong>"Refund"</strong> button in the toolbar that appears. You will be taken to the refund creation page with the selected tasks pre-loaded.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">The system automatically calculates refund amounts based on the <strong>original invoice</strong>: the <strong>Total Paid</strong>, <strong>Total to be Refunded</strong>, and whether the invoice was fully or partially paid. Review these amounts and adjust if needed.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">Confirm the refund details and click <strong>"Process Refund"</strong>.</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/refund-from-tasks.gif') }}" alt="Create Refund from Task List" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">How Refund Calculations Work</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The refund calculation depends on how much the client has already paid on the original invoice. The system handles three scenarios differently:
                    </p>

                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-3">
                        <h4 class="font-semibold text-green-800 dark:text-green-300 mb-2"><i class="fas fa-check-circle mr-1"></i> Original Invoice Fully Paid</h4>
                        <p class="text-sm text-green-700 dark:text-green-400">
                            The client has already paid the full invoice amount. The system will issue a <strong>credit</strong> back to the client immediately. No new invoice is created &mdash; the refund amount is calculated from the task price minus any refund fees, and the credit is applied to the client's account automatically.
                        </p>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-3">
                        <h4 class="font-semibold text-yellow-800 dark:text-yellow-300 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i> Original Invoice Not Paid (Unpaid)</h4>
                        <p class="text-sm text-yellow-700 dark:text-yellow-400">
                            The client has not paid anything yet. Since there is no money to return, the system creates a <strong>new refund invoice</strong> that includes the refund charges plus any remaining (non-refunded) tasks from the original invoice. The original invoice status changes to <strong>"Paid by Refund"</strong>, and the new invoice must be collected from the client.
                        </p>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-blue-800 dark:text-blue-300 mb-2"><i class="fas fa-info-circle mr-1"></i> Original Invoice Partially Paid</h4>
                        <p class="text-sm text-blue-700 dark:text-blue-400 mb-2">
                            The client has paid some but not all of the invoice. The system compares the <strong>amount already paid</strong> against the <strong>refund charge</strong> and <strong>remaining task totals</strong> to determine the outcome:
                        </p>
                        <ul class="text-sm text-blue-700 dark:text-blue-400 list-disc pl-5 space-y-1">
                            <li><strong>Paid amount covers refund + remaining tasks:</strong> The client receives a <strong>credit</strong> for the difference. No new invoice is needed.</li>
                            <li><strong>Paid amount does NOT cover remaining tasks:</strong> A <strong>new invoice</strong> is created for the shortfall that still needs to be collected from the client.</li>
                            <li><strong>Paid amount equals refund charge exactly:</strong> The refund balances out perfectly. If there are remaining tasks, a new invoice is created for those only.</li>
                        </ul>
                    </div>

                    <div class="warn-box">
                        <p class="text-sm"><i class="fas fa-calculator mr-1"></i> <strong>Per-task breakdown:</strong> For each refunded task, the system tracks the <strong>original invoice price</strong>, <strong>original supplier cost</strong>, <strong>supplier charge</strong> (if the supplier adjusts their cost), <strong>refund fee to client</strong> (any cancellation fee you charge), and the <strong>net refund to client</strong>. All these values are shown on the refund form so you can review and adjust before processing.</p>
                    </div>

                    @cannot('viewAny', App\Models\Agent::class)
                    <div class="info-box">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> As an agent, you can <strong>create refund requests</strong> for your own invoices. However, only Admin, Company, or Accountant roles can <strong>complete (approve)</strong> a refund. You can also <strong>delete</strong> refund requests that you created.</p>
                    </div>
                    @endcannot
                </section>
                @endcan

                @can('viewAny', App\Models\Payment::class)
                <section id="outstanding" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-exclamation-circle text-primary-500 mr-2"></i> Outstanding (Pending Actions)
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The <strong>Outstanding</strong> page is your <strong>to-do list for unfinished work</strong>. It shows you everything that still needs your attention &mdash; invoices that haven't been paid yet and payment links that haven't been collected. Navigate to <strong>Finances &rarr; Outstanding</strong>.
                    </p>

                    <h3 class="text-lg font-semibold mb-3">What This Page Tells You</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">
                        Each row represents an invoice or payment link that is <strong>not yet complete</strong>. Use this page to quickly identify:
                    </p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li><strong>Unpaid Invoices</strong> &mdash; Invoices you have sent to clients but they have not paid yet. You need to follow up or send a reminder.</li>
                        <li><strong>Partially Paid Invoices</strong> &mdash; Invoices where the client has paid some amount but there is still a remaining balance to collect.</li>
                        <li><strong>Uncollected Payment Links</strong> &mdash; Payment links you have created and shared, but the client has not clicked or completed the payment yet.</li>
                    </ul>

                    @cannot('viewAny', App\Models\Agent::class)
                    <div class="info-box">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> You will only see outstanding items for <strong>your own clients</strong>. Use this page daily to check what still needs to be closed so nothing falls through the cracks.</p>
                    </div>
                    @endcannot
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/outstanding-reminders.gif') }}" alt="Outstanding Pending Actions" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\AutoBilling')
                <section id="auto-billing" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-sync-alt text-primary-500 mr-2"></i> Auto Billing
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        <strong>Auto Billing</strong> automates invoice creation so agents don't have to create invoices one by one. When a new task is created and matches an auto billing rule, the system <strong>automatically creates an invoice</strong>, assigns the correct client, applies your configured markup and payment gateway, and can even <strong>send the invoice to the client via WhatsApp</strong> so they can pay immediately.
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Navigate to <strong>Finances &rarr; Auto Billing</strong> to set up your rules.
                    </p>

                    <h3 class="text-lg font-semibold mb-3">How It Works</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">
                        You create a <strong>rule</strong> that tells the system when to auto-generate an invoice. Each rule is based on matching conditions:
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-user-edit text-blue-500 mr-1"></i> Created By</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Match tasks created by a specific user</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-user-tie text-green-500 mr-1"></i> Agent</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Match tasks assigned to a specific agent</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-building text-purple-500 mr-1"></i> Issued By</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Match tasks issued by a specific company or branch</p>
                        </div>
                    </div>
                    <div class="info-box mb-4">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> At least <strong>one condition</strong> (Created By, Agent, or Issued By) must be set for the rule to work. When a new task matches the rule, the system automatically looks up the client from the task and creates the invoice.</p>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">What You Configure Per Rule</h3>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li><strong>Client</strong> &mdash; Automatically assigned from the task, or set a default client for the rule</li>
                        <li><strong>Add Amount (Markup)</strong> &mdash; Extra amount to add per task on top of the supplier cost</li>
                        <li><strong>Payment Gateway</strong> &mdash; Which gateway the client will use to pay (e.g., MyFatoorah, bank transfer)</li>
                        <li><strong>Payment Method</strong> &mdash; Payment method for the invoice</li>
                        <li><strong>Invoice Time</strong> &mdash; When the invoice should be generated (company timezone and system timezone)</li>
                        <li><strong>Auto Send WhatsApp</strong> &mdash; If enabled, the invoice is automatically sent to the client's WhatsApp number with a payment link so they can pay right away</li>
                        <li><strong>Active / Inactive</strong> &mdash; Toggle the rule on or off without deleting it</li>
                    </ul>

                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/auto-billing.gif') }}" alt="Auto Billing Setup" class="doc-gif"></div>

                    <div class="warn-box">
                        <p class="text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> Before setting up Auto Billing, make sure your <strong>Payment Settings</strong> (currency, tax rate, invoice prefix) and at least one <strong>Payment Gateway</strong> are properly configured in Settings. Without these, auto-generated invoices will not have the correct payment information.</p>
                    </div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Invoice')
                <section id="reminders" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-clock text-primary-500 mr-2"></i> Reminders
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The <strong>Reminders</strong> page lets you send reminders to clients who have <strong>unpaid invoices</strong> or <strong>uncollected payment links</strong>. Instead of manually messaging each client, you can send reminders via <strong>WhatsApp</strong> directly from this page &mdash; either one at a time or on an automatic schedule. Navigate to <strong>Reminders</strong> from the sidebar.
                    </p>

                    <h3 class="text-lg font-semibold mb-3">Three Tabs</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">The Reminders page is organized into three tabs:</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-file-invoice text-blue-500 mr-1"></i> Invoices</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">All unpaid invoices. Shows invoice number, client, agent, amount, due date, and how many reminders have already been sent (e.g., "2/3 sent").</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-link text-green-500 mr-1"></i> Payments</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Payment links that haven't been paid yet. Shows voucher number, client, agent, amount, and next scheduled reminder.</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-history text-purple-500 mr-1"></i> History</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">All past reminders with their status (Sent, Pending, Failed). Shows when each reminder was sent and to whom.</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">Sending a Reminder</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">
                        Click the <strong>reminder icon</strong> on any invoice or payment link row to open the reminder form. You can configure:
                    </p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li><strong>Send to Client</strong> &mdash; The client receives a WhatsApp message with the invoice/payment link and amount owed</li>
                        <li><strong>Send to Agent</strong> &mdash; The agent receives a WhatsApp message asking them to follow up with their client</li>
                        <li><strong>Custom Message</strong> &mdash; Add an optional message (up to 500 words) that will be included in the reminder</li>
                    </ul>

                    <h3 class="text-lg font-semibold mb-3">One-Time vs Auto-Repeat</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">You can choose how the reminder is sent:</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-paper-plane text-blue-500 mr-1"></i> One-Time</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Sends the reminder immediately, just once. Use this for a quick nudge to the client.</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-redo text-orange-500 mr-1"></i> Auto-Repeat</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Sends the first reminder immediately, then <strong>automatically sends follow-up reminders</strong> on a schedule you set. Great for persistent follow-ups without manual effort.</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">Auto-Repeat Settings</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">When you choose <strong>Auto-Repeat</strong>, configure the schedule:</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li><strong>Interval</strong> &mdash; How often to send (e.g., every 1 day, every 3 days, every 12 hours)</li>
                        <li><strong>Unit</strong> &mdash; Choose between <strong>Hours</strong> or <strong>Days</strong></li>
                        <li><strong>Total Reminders</strong> &mdash; How many reminders to send in total (1 to 10)</li>
                    </ul>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        <strong>Quick presets</strong> are available for common schedules: <strong>Daily &times; 3</strong>, <strong>Every 3 Days &times; 3</strong>, or <strong>Weekly &times; 4</strong>. The form shows a preview of exactly when each reminder will be sent so you know the full schedule before confirming.
                    </p>

                    <div class="info-box mb-4">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> The first reminder is always sent <strong>immediately</strong>. Subsequent reminders are sent automatically at the scheduled times. You can track the progress of each reminder set from the <strong>History</strong> tab (e.g., "2/5 sent" means 2 out of 5 reminders have been delivered so far).</p>
                    </div>

                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/reminders.gif') }}" alt="Reminders Page" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\CoaCategory')
                <section id="accounting" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-book text-primary-500 mr-2"></i> Chart of Accounts & Accounting
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The accounting module manages all financial records. The <strong>Chart of Accounts (COA)</strong> organizes your financial accounts into five standard categories: <strong>Assets</strong>, <strong>Liabilities</strong>, <strong>Equity</strong>, <strong>Revenue</strong>, and <strong>Expenses</strong>.
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Navigate to <strong>Finances &rarr; Chart of Accounts</strong> to view and manage your account structure. You can <strong>add new accounts</strong>, <strong>edit existing ones</strong>, and <strong>view balances</strong> for each account.
                    </p>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/coa.gif') }}" alt="Accounting Overview" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Automatic Accounting Entries</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-2">The system automatically creates double-entry accounting records when financial events occur:</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li><strong>Invoice Created</strong> &rarr; Debit Accounts Receivable, Credit Revenue</li>
                        <li><strong>Payment Received</strong> &rarr; Debit Cash/Bank, Credit Accounts Receivable</li>
                        <li><strong>Refund Processed</strong> &rarr; Reverses the original entries proportionally</li>
                    </ul>

                    <h3 class="text-lg font-semibold mb-3 mt-6">Receipt Voucher</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        A <strong>Receipt Voucher (RV)</strong> is used to record <strong>cash payments received</strong> from clients. For example, when a client pays an invoice in cash, you collect the money and then record it in the system through a Receipt Voucher. Navigate to <strong>Finances &rarr; Receipt Voucher</strong>.
                    </p>
                    <h4 class="font-semibold text-sm mb-2">How to Create a Receipt Voucher</h4>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Click <strong>"+ Create"</strong>. The system generates a reference number (RV-xxxx) automatically.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Select the <strong>Branch</strong>, set the <strong>Document Date</strong>, and enter who you <strong>Received From</strong> (search by client name).</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">In the line items, choose the <strong>type</strong> for each row: <strong>Invoice</strong> (link payment to a specific unpaid invoice), <strong>Account</strong> (record against a GL account), or <strong>Client Credit</strong> (add to client's credit balance).</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">4</span>
                        <p class="text-sm">Enter the <strong>Debit</strong> and <strong>Credit</strong> amounts, currency, and any cheque details if applicable. Make sure Total Debit equals Total Credit (the difference should be 0).</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">5</span>
                        <p class="text-sm">Add <strong>Remarks</strong> and submit. The voucher is saved with <strong>Pending</strong> status and must be <strong>approved</strong> by an Admin or Accountant before it takes effect. Once approved, the journal entries are created and the invoice is marked as paid.</p>
                    </div>
                    <div class="info-box mb-4">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> Receipt Vouchers require <strong>approval</strong> before they update invoice payments. This ensures cash received is verified before being recorded in the system. Statuses: <strong>Pending</strong> &rarr; <strong>Approved</strong> or <strong>Rejected</strong>.</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/receipt-voucher.gif') }}" alt="Receipt Voucher" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Payment Voucher (Bank Payment)</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        A <strong>Payment Voucher (PV)</strong> is used to <strong>transfer money from one account to another</strong>. For example, you have money in Bank Account A and need to pay a supplier or move funds to Account B &mdash; you create a Payment Voucher to deduct from one account and credit another. Navigate to <strong>Finances &rarr; Payable Details</strong>.
                    </p>
                    <h4 class="font-semibold text-sm mb-2">How to Create a Payment Voucher</h4>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Click <strong>"+ Create"</strong>. The system generates a reference number (PV-xxxx) automatically.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Select the <strong>Payment Type</strong>: <strong>Payment</strong> (standard), <strong>Payment by Date</strong> (with reconciliation), or <strong>Refund</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">Choose the <strong>Pay From</strong> account &mdash; this is the bank/cash account the money will be deducted from. The current balance is shown next to each account so you can verify sufficient funds.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">4</span>
                        <p class="text-sm">In the line items, select the <strong>target account</strong> (the account to receive the payment &mdash; e.g., a supplier account or expense account) and enter the <strong>amount</strong>. You can add multiple line items to split the payment across different accounts.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">5</span>
                        <p class="text-sm">Add <strong>Remarks</strong> and submit. The system creates double-entry journal entries: <strong>debit</strong> the target account and <strong>credit</strong> the source bank account.</p>
                    </div>
                    <div class="warn-box mb-4">
                        <p class="text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> You cannot pay from an account with <strong>insufficient balance</strong>. The system will show the remaining balance after payment so you can verify before submitting.</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/payment-voucher.gif') }}" alt="Payment Voucher" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Journal Entries</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        View all journal entries in <strong>Finances &rarr; Journal Entries</strong>. Entries are created automatically by invoices, payments, and vouchers. You can also create <strong>manual journal entries</strong> for adjustments, corrections, or transfers between accounts.
                    </p>

                    <h3 class="text-lg font-semibold mb-3">Accounting Summary</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Navigate to <strong>Finances &rarr; Accounting Summary</strong> to view a company-wide financial overview with totals for each account category (Assets, Liabilities, Revenue, Expenses). Use this to get a quick snapshot of your company's financial health.
                    </p>

                    <h3 class="text-lg font-semibold mb-3 mt-6">Receivable Details</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The <strong>Receivable Details</strong> page is used to record money received from agents or clients. Navigate to <strong>Finances &rarr; Receivable</strong>. The page has a <strong>two-column layout</strong>: the left side shows all existing receivable and income transactions, and the right side has the form to add a new record.
                    </p>
                    <h4 class="font-semibold text-sm mb-2">How to Add a Receivable Record</h4>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Select the <strong>Branch</strong> and <strong>Account Name</strong> (from your Chart of Accounts under Accounts Receivable).</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Select the <strong>Agent/Client Name</strong> who made the payment, and the <strong>Company's Bank Account</strong> where the money was deposited.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">Optionally link to an <strong>Invoice Number</strong> if this payment is for a specific invoice.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">4</span>
                        <p class="text-sm">Set the <strong>Transaction Date</strong>, enter a <strong>Description</strong>, and the <strong>Amount</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">5</span>
                        <p class="text-sm">Choose the <strong>Type</strong>: <strong>Receivable</strong> (money owed by client) or <strong>Income</strong> (general income). Click <strong>"Submit"</strong>. The system generates a reference number (RV-xxxxxx for Receivable, IN-xxxxxx for Income) and creates the journal entries automatically.</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/receivable-details.gif') }}" alt="Receivable Details" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Payable Details</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The <strong>Payable Details</strong> page is used to record money paid out to suppliers or for expenses. Navigate to <strong>Finances &rarr; Payable</strong>. Like Receivable, it has a <strong>two-column layout</strong>: the left side lists existing payable and expense transactions, and the right side has the form to add a new record.
                    </p>
                    <h4 class="font-semibold text-sm mb-2">How to Add a Payable Record</h4>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Select the <strong>Branch</strong> and <strong>Supplier Account</strong> (from your Chart of Accounts under Accounts Payable).</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Select the <strong>Bank Account</strong> the payment will be deducted from.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">Set the <strong>Transaction Date</strong>, enter a <strong>Description</strong>, and the <strong>Amount</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">Choose the <strong>Type</strong>: <strong>Payable</strong> (money owed to supplier) or <strong>Expenses</strong> (general expense). Click <strong>"Submit"</strong>. The system generates a reference number (PY-xxxxxx for Payable, EX-xxxxxx for Expenses) and creates the journal entries automatically.</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/payable-details.gif') }}" alt="Payable Details" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">Lock Management</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        <strong>Lock Management</strong> lets you lock financial records (invoices) to prevent modifications after a period is closed. Once locked, records cannot be edited or deleted until unlocked. Navigate to <strong>Finances &rarr; Lock Management</strong>.
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">
                        The page shows a dashboard with stats (total records, locked count, unlocked count, percentage locked) and a <strong>monthly breakdown</strong> where you can lock or unlock records month by month.
                    </p>

                    <h4 class="font-semibold text-sm mb-2">Locking Records</h4>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">There are two ways to lock records:</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li><strong>Lock by Month</strong> &mdash; Click the <strong>lock button</strong> on a month card to lock all unlocked records for that month. The card shows a progress bar and a "Closed" badge when 100% locked.</li>
                        <li><strong>Bulk Lock by Date</strong> &mdash; Click <strong>"Bulk lock by date"</strong> to lock records across a date range. Select the from/to dates, choose which record types to lock (Invoices), and filter by status (Paid, Unpaid, Partial).</li>
                    </ul>

                    <h4 class="font-semibold text-sm mb-2">Unlocking Records</h4>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        To unlock records, click the <strong>unlock button</strong> on a month card. You must provide a <strong>reason for unlocking</strong> &mdash; this is required and logged in the system for audit purposes. Only unlock when necessary, such as correcting an error in a closed period.
                    </p>
                    <div class="warn-box mb-4">
                        <p class="text-sm"><i class="fas fa-exclamation-triangle mr-1"></i> <strong>Locking is recommended</strong> after each month-end to protect your financial records from accidental changes. Once a period is closed and verified, lock it to maintain data integrity.</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/lock-management.gif') }}" alt="Lock Management" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', App\Models\CurrencyExchange::class)
                <section id="currency-exchange" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-exchange-alt text-primary-500 mr-2"></i> Currency Exchange
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        If your business handles multiple currencies, use the <strong>Currency Exchange</strong> module to manage exchange rates. Navigate to <strong>Finances &rarr; Currency Exchange</strong>.
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Set exchange rates between currency pairs (e.g., KWD &harr; USD, KWD &harr; EUR). These rates are used when creating tasks or invoices in foreign currencies, ensuring accurate conversion in your reports and accounting.
                    </p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Click <strong>"+ Add Exchange Rate"</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Select the <strong>From Currency</strong> and <strong>To Currency</strong>, enter the <strong>Exchange Rate</strong>.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">Click <strong>"Save"</strong>. Update rates regularly to keep conversions accurate.</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/currency-exchange.gif') }}" alt="Currency Exchange" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Report')
                <section id="reports" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-chart-bar text-primary-500 mr-2"></i> Reports
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The Reports module gives you access to various financial and operational reports. There is <strong>no single report dashboard</strong> &mdash; instead, each report is a separate page accessible from the <strong>Reports</strong> menu in the sidebar. Click on any report name to open it.
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Most reports support <strong>date range filtering</strong> so you can view data for a specific period. Many reports can also be <strong>exported to PDF</strong> for sharing or printing. Reports are scoped to your access level &mdash; Admin sees all companies, Company sees their own data.
                    </p>

                    <h3 class="text-lg font-semibold mb-3">Available Reports</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">The following reports are available from the sidebar under <strong>Reports</strong>:</p>

                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-chart-line text-blue-500 mr-1"></i> Daily Sales</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">View daily sales figures showing total revenue, costs, and profit for each day. Filter by date range and export to PDF. Useful for tracking day-to-day business performance.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-balance-scale text-green-500 mr-1"></i> Profit &amp; Loss</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Overview of total revenue minus total costs and expenses. Shows your net profit or loss for the selected period. Essential for understanding overall business health.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-file-invoice-dollar text-purple-500 mr-1"></i> Paid Accounts Payable/Receivable</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Shows all paid invoices and their payment details &mdash; what has been collected from clients and what has been paid to suppliers.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-exclamation-circle text-red-500 mr-1"></i> Unpaid Accounts Payable/Receivable</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Shows all unpaid invoices &mdash; amounts still owed by clients and amounts you still owe to suppliers. Helps track outstanding balances.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-tasks text-orange-500 mr-1"></i> Task Report</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">All tasks with type, status, agent, supplier, cost, and selling price breakdown. Filter by date range and export to PDF.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-user-friends text-teal-500 mr-1"></i> Client Report</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Client activity and total spending. Shows how much each client has been billed, paid, and what remains outstanding. Export to PDF.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-hand-holding-usd text-yellow-500 mr-1"></i> Creditors Report</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Shows amounts owed to creditors (suppliers, service providers). Helps track what needs to be paid out. Export to PDF.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-university text-indigo-500 mr-1"></i> Bank Settlement</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">View payment gateway settlements with journal entry details grouped by date. Track bank transactions and reconciliation status.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-credit-card text-pink-500 mr-1"></i> Payment Gateways Report</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Breakdown of payments received through each payment gateway. See which gateways are most used and total amounts processed. Export to PDF.</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-calculator text-gray-500 mr-1"></i> Trial Balance</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Standard accounting trial balance showing all account debits and credits. Verify that your books are balanced. Export to PDF or Excel.</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">How to Use Reports</h3>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">Open the <strong>Reports</strong> menu in the sidebar and click the report you want to view.</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">Each report opens on its own page. Use the <strong>date filters</strong> at the top to select the period you want to analyze (e.g., this month, last quarter, custom date range).</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">Click <strong>"Export PDF"</strong> or <strong>"Download"</strong> to save the report for sharing or printing.</p>
                    </div>

                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/reports.gif') }}" alt="Reports Navigation" class="doc-gif"></div>

                    <div class="info-box">
                        <p class="text-sm"><i class="fas fa-info-circle mr-1"></i> Reports are available to <strong>Admin</strong> and <strong>Company</strong> roles only. Agents and Accountants do not have access to Reports. If you need performance data, contact your Company Manager.</p>
                    </div>
                </section>
                @endcan

                <section id="faq" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-question-circle text-primary-500 mr-2"></i> Frequently Asked Questions
                    </h2>
                    <div class="space-y-3">
                        @can('viewAny', 'App\Models\User')
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">How do I reset a user's password?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                Go to the user list (Users &rarr; Companies, Agents, etc.), click the <strong>Edit</strong> button on the user's row, and change the password field. Alternatively, the user can click <strong>"Forgot Password"</strong> on the login page to reset their own password via email.
                            </div>
                        </div>
                        @endcan

                        @cannot('viewAny', App\Models\Supplier::class)
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">Why can't I see a supplier when creating a task?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                The supplier needs to be <strong>activated for your company</strong> by the Admin. Only activated suppliers appear in the task creation dropdown. Contact your Admin and ask them to check the supplier activation status on the Suppliers page.
                            </div>
                        </div>
                        @endcannot

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">Can a client log into the system?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                No. Clients are <strong>contact records only</strong> and do not have login access. They interact with the system only through payment links sent via email or WhatsApp.
                            </div>
                        </div>

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">How do partial payments work?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                When creating an invoice, select <strong>"Partial Payment"</strong> as the payment type. The client pays the specified amount, and the invoice status becomes <strong>"Partial"</strong>. The remaining balance is tracked and visible on the Outstanding page. You can create additional payment links to collect the remaining amount.
                            </div>
                        </div>

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">What does locking an invoice do?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                Locking an invoice <strong>prevents any edits</strong> to the invoice details (line items, amounts, dates). This protects the invoice from accidental changes after it has been sent to the client. You can still <strong>record payments</strong> and <strong>process refunds</strong> on a locked invoice. It is recommended to lock invoices after sending them.
                            </div>
                        </div>

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">What is the difference between a Payment Link and an Invoice?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                An <strong>Invoice</strong> is a detailed billing document listing tasks/services with support for partial and split payments. A <strong>Payment Link</strong> is a simple online collection tool &mdash; you set an amount and send a URL. Payment links always collect the full amount, while invoices support flexible payment options.
                            </div>
                        </div>

                        @cannot('viewAny', App\Models\Agent::class)
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">Why can't I delete a task?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                Task deletion is restricted to <strong>Admin users only</strong>. If you need a task removed, contact your Admin. You can still <strong>edit</strong> or <strong>change the status</strong> of your tasks (e.g., set it to "Cancelled").
                            </div>
                        </div>
                        @endcannot

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">How is profit calculated on a task?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                Profit is automatically calculated as <strong>Selling Price minus Cost Price</strong>. The selling price is what you charge the client, and the cost price is what you pay to the supplier. If the cost exceeds the selling price, the task shows a loss.
                            </div>
                        </div>

                        @can('viewAny', 'App\Models\Company')
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">How do I switch between companies?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                Use the <strong>company selector dropdown</strong> in the left sidebar (visible only to Admins). Click the current company name and select a different company. All pages (tasks, invoices, reports, etc.) will then show data for the selected company.
                            </div>
                        </div>
                        @endcan

                        @can('viewAny', 'App\Models\Setting')
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">How do I set up WhatsApp notifications?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                @if($isAdmin)
                                Go to <strong>Settings &rarr; System Settings &rarr; WhatsApp API</strong>. Enter your WhatsApp Business API token and configure the webhook URL. Once set up, enable WhatsApp notifications in <strong>Settings &rarr; Notifications</strong>.
                                @else
                                WhatsApp integration is configured by the Admin in System Settings. Once enabled, you can send invoices and payment links via WhatsApp using the WhatsApp button on invoice/payment link rows.
                                @endif
                            </div>
                        </div>
                        @endcan

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-left p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">I cannot access a page and see a "403 Forbidden" error. What do I do?</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                A 403 error means your role does not have permission to access that page. Contact your Admin or Company Manager to check your role's permissions in <strong>Settings &rarr; Roles</strong>. They can enable the required permission for your role.
                            </div>
                        </div>
                    </div>
                </section>

                <div class="text-center py-8 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">
                    <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                </div>

            </div>
        </div>
    </div>

    <button @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            x-show="scrollProgress > 10" x-transition
            class="fixed bottom-8 right-8 bg-primary-500 text-white w-10 h-10 rounded-full shadow-lg flex items-center justify-center hover:bg-primary-600 transition-colors z-30">
        <i class="fas fa-chevron-up"></i>
    </button>

    <script>
        function docsApp() {
            const role = @json($roleName);
            const p = @json($perms);

            const groups = [];

            groups.push({
                label: 'Getting Started',
                links: [
                    { id: 'getting-started', title: 'Getting Started', icon: 'fas fa-rocket' },
                    ...(p.viewRoles ? [{ id: 'role-overview', title: 'Roles Overview', icon: 'fas fa-shield-halved' }] : []),
                ]
            });

            const setupLinks = [];
            if (p.viewUsers) {
                setupLinks.push({ id: 'user-management', title: 'User Management', icon: 'fas fa-users' });
            } else if (p.viewClients) {
                setupLinks.push({ id: 'my-clients', title: 'Your Clients', icon: 'fas fa-user' });
            }
            if (p.viewSuppliers) {
                setupLinks.push({ id: 'suppliers', title: 'Suppliers', icon: 'fas fa-handshake' });
            }
            if (p.viewSettings) {
                setupLinks.push({ id: 'settings', title: 'Settings', icon: 'fas fa-cog' });
            }
            if (p.manageSystemSettings) {
                setupLinks.push({ id: 'system-settings', title: 'System Settings', icon: 'fas fa-server' });
            }
            if (setupLinks.length > 0) {
                groups.push({ label: 'Setup', links: setupLinks });
            }

            const opsLinks = [];
            if (p.viewTasks) {
                opsLinks.push({ id: 'tasks', title: 'Tasks', icon: 'fas fa-tasks' });
            }
            if (p.viewInvoices) {
                opsLinks.push(
                    { id: 'invoices', title: 'Invoices', icon: 'fas fa-file-invoice-dollar' },
                    { id: 'invoices-link', title: 'Invoices Link', icon: 'fas fa-link' },
                );
            }
            if (p.viewPayments) {
                opsLinks.push({ id: 'payment-links', title: 'Payment Links', icon: 'fas fa-money-check-alt' });
            }
            if (p.viewRefunds) {
                opsLinks.push({ id: 'refunds', title: 'Refunds', icon: 'fas fa-undo' });
            }
            if (p.viewInvoices) {
                opsLinks.push({ id: 'reminders', title: 'Reminders', icon: 'fas fa-clock' });
            }
            if (opsLinks.length > 0) {
                groups.push({ label: 'Operations', links: opsLinks });
            }

            const finLinks = [];
            if (p.viewPayments) {
                finLinks.push({ id: 'outstanding', title: 'Outstanding', icon: 'fas fa-exclamation-circle' });
            }
            if (p.viewAutoBilling) {
                finLinks.push({ id: 'auto-billing', title: 'Auto Billing', icon: 'fas fa-sync-alt' });
            }
            if (p.viewCoa) {
                finLinks.push({ id: 'accounting', title: 'Accounting & COA', icon: 'fas fa-book' });
            }
            if (p.viewCurrencyExchange) {
                finLinks.push({ id: 'currency-exchange', title: 'Currency Exchange', icon: 'fas fa-exchange-alt' });
            }
            if (p.viewReports) {
                finLinks.push({ id: 'reports', title: 'Reports', icon: 'fas fa-chart-bar' });
            }
            if (finLinks.length > 0) {
                groups.push({ label: 'Finance', links: finLinks });
            }

            groups.push({
                label: 'Help',
                links: [
                    { id: 'faq', title: 'FAQ', icon: 'fas fa-question-circle' },
                ]
            });

            return {
                sidebarOpen: false,
                activeSection: 'welcome',
                scrollProgress: 0,
                searchQuery: '',
                searchResults: [],
                navGroups: groups,
                sections: [
                    { id: 'getting-started', title: 'Getting Started', keywords: 'login dashboard start' },
                    { id: 'role-overview', title: 'Roles Overview', keywords: 'role permission admin company agent' },
                    { id: 'user-management', title: 'User Management', keywords: 'user company branch agent client accountant create' },
                    { id: 'my-clients', title: 'Your Clients', keywords: 'client add register' },
                    { id: 'suppliers', title: 'Suppliers', keywords: 'supplier activate service' },
                    { id: 'settings', title: 'Settings', keywords: 'settings payment gateway method terms notification' },
                    { id: 'system-settings', title: 'System Settings', keywords: 'system email whatsapp hotel country' },
                    { id: 'tasks', title: 'Tasks', keywords: 'task booking flight hotel visa insurance create' },
                    { id: 'invoices', title: 'Invoices', keywords: 'invoice create send pdf lock status partial split' },
                    { id: 'invoices-link', title: 'Invoices Link', keywords: 'invoice link group share multiple' },
                    { id: 'payment-links', title: 'Payment Links', keywords: 'payment link share online full' },
                    { id: 'refunds', title: 'Refunds', keywords: 'refund cancel money back' },
                    { id: 'reminders', title: 'Reminders', keywords: 'reminder overdue due date follow up' },
                    { id: 'outstanding', title: 'Outstanding Payments', keywords: 'outstanding unpaid balance pending' },
                    { id: 'auto-billing', title: 'Auto Billing', keywords: 'auto billing automatic invoice' },
                    { id: 'accounting', title: 'Accounting', keywords: 'coa chart accounts voucher receipt payment journal receivable payable lock management' },
                    { id: 'currency-exchange', title: 'Currency Exchange', keywords: 'currency exchange rate foreign' },
                    { id: 'reports', title: 'Reports', keywords: 'report sales profit agent financial commission' },
                    { id: 'faq', title: 'FAQ', keywords: 'faq question help' },
                ],
                init() {
                    if (localStorage.getItem('docs-theme') === 'dark' || (!localStorage.getItem('docs-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                    window.addEventListener('scroll', () => {
                        const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
                        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                        this.scrollProgress = height > 0 ? (winScroll / height) * 100 : 0;
                    });
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) this.activeSection = entry.target.id;
                        });
                    }, { rootMargin: '-100px 0px -66% 0px' });
                    document.querySelectorAll('section[id]').forEach(section => observer.observe(section));
                },
                toggleDarkMode() {
                    document.documentElement.classList.toggle('dark');
                    localStorage.setItem('docs-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                },
                scrollToSection(id) {
                    this.sidebarOpen = false;
                    this.searchQuery = '';
                    this.searchResults = [];
                    const el = document.getElementById(id);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
                filterSections() {
                    if (this.searchQuery.length === 0) { this.searchResults = []; return; }
                    const q = this.searchQuery.toLowerCase();
                    this.searchResults = this.sections.filter(s =>
                        s.title.toLowerCase().includes(q) || s.keywords.toLowerCase().includes(q)
                    ).slice(0, 8);
                }
            };
        }
    </script>
</body>
</html>
